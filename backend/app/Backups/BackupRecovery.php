<?php

namespace App\Backups;

use App\Identity\Access;
use App\Models\Admin;
use App\Operations\AuditTrail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class BackupRecovery
{
    public function __construct(private RecoveryManifest $recovery, private AuditTrail $audit) {}

    public function rehearse(Admin $actor, int $backupRecordId, string $path, string $providedKeyId): array
    {
        $this->authorize($actor);
        $this->targetGuard();

        return $this->perform($actor, $backupRecordId, $path, $providedKeyId);
    }

    public function rehearseForReset(Admin $actor, int $backupRecordId, string $path, string $providedKeyId, string $level): array
    {
        abort_unless(in_array($level, ['transactional', 'business', 'factory'], true), 422);
        abort_unless(app(Access::class)->allows($actor, 'system.reset.'.$level), 403);
        $this->targetGuard();

        return $this->perform($actor, $backupRecordId, $path, $providedKeyId);
    }

    private function perform(Admin $actor, int $backupRecordId, string $path, string $providedKeyId): array
    {
        $record = DB::table('backup_records')->where('id', $backupRecordId)->firstOrFail();
        $manifestRow = DB::table('backup_manifests')->where('backup_record_id', $backupRecordId)->firstOrFail();

        try {
            $payload = $this->verify($record, $manifestRow, $path, $providedKeyId);
            $summary = [
                'contract' => RecoveryManifest::CONTRACT,
                'table_count' => count($payload['tables']),
                'row_count' => array_sum(array_map('count', $payload['tables'])),
            ];
            DB::table('backup_manifests')->where('id', $manifestRow->id)->update(['verified_at' => now(), 'updated_at' => now()]);
            $id = $this->recordRehearsal($actor, $record, $manifestRow, 'passed', null, $summary);
            $this->audit->admin($actor, 'backup_restore_rehearsal_passed', 'SYSTEM', '/recovery/backup', ['backup_id' => $backupRecordId]);

            return ['public_id' => $id, 'status' => 'passed', ...$summary];
        } catch (Throwable $error) {
            $code = $this->failureCode($error);
            DB::table('integration_connections')->whereIn('status', ['connected', 'connecting'])->update([
                'status' => 'error',
                'last_error_summary' => 'Recovery verification failed.',
                'updated_at' => now(),
            ]);
            $this->recordRehearsal($actor, $record, $manifestRow, 'failed', $code, ['failure_code' => $code]);
            $this->audit->admin($actor, 'backup_restore_rehearsal_failed', 'SYSTEM', '/recovery/backup', [
                'backup_id' => $backupRecordId,
                'failure_code' => $code,
            ], 409);
            throw new RuntimeException($code, previous: $error);
        }
    }

    private function verify(object $record, object $manifestRow, string $path, string $providedKeyId): array
    {
        $this->guard(is_file($path), 'ARCHIVE_NOT_FOUND');
        $backupRoot = realpath(storage_path('app/private/backups'));
        $realPath = realpath($path);
        $this->guard(is_string($backupRoot) && is_string($realPath), 'ARCHIVE_PATH_INVALID');
        $this->guard(dirname($realPath) === $backupRoot && basename($realPath) === $record->filename, 'ARCHIVE_PATH_INVALID');
        $this->guard(is_string($record->checksum) && hash_equals($record->checksum, hash_file('sha256', $realPath)), 'ARCHIVE_CHECKSUM_MISMATCH');
        $currentKeyId = $this->recovery->keyId();
        $this->guard(hash_equals($manifestRow->key_id, $providedKeyId) && hash_equals($manifestRow->key_id, $currentKeyId), 'RECOVERY_KEY_MISMATCH');

        try {
            $compressed = base64_decode(Crypt::decryptString(file_get_contents($realPath)), true);
            $this->guard(is_string($compressed), 'ARCHIVE_ENCODING_INVALID');
            $json = gzdecode($compressed);
            $this->guard(is_string($json), 'ARCHIVE_COMPRESSION_INVALID');
            $payload = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $error) {
            throw new RuntimeException('RECOVERY_DECRYPT_FAILED', previous: $error);
        }

        $this->guard(($payload['contract'] ?? null) === RecoveryManifest::CONTRACT, 'ARCHIVE_CONTRACT_MISMATCH');
        $this->guard(is_array($payload['manifest'] ?? null) && is_array($payload['tables'] ?? null), 'ARCHIVE_MANIFEST_INVALID');
        $manifest = $payload['manifest'];
        $storedManifest = json_decode($manifestRow->manifest, true, flags: JSON_THROW_ON_ERROR);
        $this->guard(hash_equals($manifestRow->manifest_sha256, $this->recovery->sha($manifest)), 'ARCHIVE_MANIFEST_HASH_MISMATCH');
        $this->guard(hash_equals($manifestRow->manifest_sha256, $this->recovery->sha($storedManifest)), 'STORED_MANIFEST_HASH_MISMATCH');
        $this->guard(($manifest['database'] ?? null) === DB::connection()->getDatabaseName(), 'TARGET_DATABASE_MISMATCH');
        $this->guard(($manifest['environment'] ?? null) === app()->environment(), 'TARGET_ENVIRONMENT_MISMATCH');
        $this->guard(hash_equals($manifestRow->schema_sha256, (string) ($manifest['schema_sha256'] ?? '')), 'ARCHIVE_SCHEMA_MANIFEST_MISMATCH');
        $this->guard(hash_equals($manifestRow->code_sha256, (string) ($manifest['code_sha256'] ?? '')), 'ARCHIVE_CODE_MANIFEST_MISMATCH');
        $this->guard(hash_equals($manifestRow->schema_sha256, $this->recovery->schemaSha256()), 'TARGET_SCHEMA_MISMATCH');
        $this->guard(hash_equals($manifestRow->code_sha256, $this->recovery->codeSha256()), 'TARGET_CODE_MISMATCH');

        $tableManifest = $manifest['tables'] ?? null;
        $this->guard(is_array($tableManifest) && array_keys($tableManifest) === array_keys($payload['tables']), 'ARCHIVE_TABLE_SET_MISMATCH');
        foreach ($payload['tables'] as $table => $rows) {
            $expected = $tableManifest[$table] ?? null;
            $this->guard(is_array($rows) && is_array($expected), 'ARCHIVE_TABLE_MANIFEST_INVALID');
            $this->guard((int) ($expected['rows'] ?? -1) === count($rows), 'ARCHIVE_ROW_COUNT_MISMATCH');
            $this->guard(hash_equals((string) ($expected['sha256'] ?? ''), $this->recovery->sha($rows)), 'ARCHIVE_ROW_HASH_MISMATCH');
        }

        return $payload;
    }

    private function recordRehearsal(Admin $actor, object $record, object $manifest, string $status, ?string $failureCode, array $result): string
    {
        $publicId = (string) Str::uuid();
        DB::table('backup_restore_rehearsals')->insert([
            'public_id' => $publicId,
            'backup_record_id' => $record->id,
            'status' => $status,
            'failure_code' => $failureCode,
            'environment' => app()->environment(),
            'database_name' => DB::connection()->getDatabaseName(),
            'key_id' => $manifest->key_id,
            'manifest_sha256' => $manifest->manifest_sha256,
            'checked_by_admin_id' => $actor->id,
            'checked_at' => now(),
            'result' => json_encode($result, JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $publicId;
    }

    private function targetGuard(): void
    {
        $allowed = config('backups.rehearsal_environments', []);
        $this->guard(in_array(app()->environment(), $allowed, true), 'RESTORE_REHEARSAL_ENVIRONMENT_DENIED');
        $this->guard(DB::connection()->getDatabaseName() === 'mobisttech_test', 'RESTORE_TARGET_DATABASE_DENIED');
        $this->guard((int) DB::connection()->getConfig('port') === 13306, 'RESTORE_TARGET_PORT_DENIED');
    }

    private function authorize(Admin $actor): void
    {
        $access = app(Access::class);
        abort_unless($access->allows($actor, 'backups.manage') && $access->allows($actor, 'config.integrations-backups.manage'), 403);
    }

    private function guard(bool $condition, string $code): void
    {
        if (! $condition) {
            throw new RuntimeException($code);
        }
    }

    private function failureCode(Throwable $error): string
    {
        $message = $error->getMessage();

        return preg_match('/\A[A-Z0-9_.-]{3,80}\z/', $message) ? $message : 'RECOVERY_VERIFICATION_FAILED';
    }
}
