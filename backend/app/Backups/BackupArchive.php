<?php

namespace App\Backups;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BackupArchive
{
    public function __construct(private RecoveryManifest $recovery) {}

    public function create(int $recordId): array
    {
        $database = DB::connection()->getDatabaseName();
        $excluded = [
            'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches', 'failed_jobs',
            'password_reset_tokens', 'admin_password_reset_tokens', 'super_admin_password_reset_tokens',
            'site_admin_password_reset_tokens', 'integration_oauth_states',
        ];
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', $database)->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')->get(['table_name'])
            ->map(fn ($row) => $row->table_name ?? $row->TABLE_NAME)
            ->reject(fn ($name) => in_array($name, $excluded, true))->values();
        $rows = $tables->mapWithKeys(fn ($table) => [
            $table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all(),
        ])->all();
        $manifest = $this->recovery->backupManifest($rows);
        $manifestSha = $this->recovery->sha($manifest);
        $payload = [
            'contract' => RecoveryManifest::CONTRACT,
            'created_at' => now()->format('Y-m-d H:i:s.u'),
            'database' => $database,
            'manifest' => $manifest,
            'tables' => $rows,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $compressed = gzencode($json, 9);
        abort_unless(is_string($compressed), 500, 'Backup compression failed.');
        $encrypted = Crypt::encryptString(base64_encode($compressed));
        $directory = storage_path('app/private/backups');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $filename = 'mobisttech-'.now()->format('Ymd-His').'-'.Str::lower(Str::random(8)).'.backup';
        $path = $directory.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, $encrypted, LOCK_EX);
        if (DIRECTORY_SEPARATOR === '/') {
            chmod($path, 0600);
        }

        DB::table('backup_manifests')->updateOrInsert(
            ['backup_record_id' => $recordId],
            ['contract_version' => RecoveryManifest::CONTRACT, 'key_id' => $manifest['key_id'],
                'schema_sha256' => $manifest['schema_sha256'], 'code_sha256' => $manifest['code_sha256'],
                'manifest_sha256' => $manifestSha, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
                'verified_at' => null, 'created_at' => now(), 'updated_at' => now()]
        );
        $size = filesize($path);
        $checksum = hash_file('sha256', $path);
        DB::table('backup_records')->where('id', $recordId)->update([
            'filename' => $filename,
            'path' => $path,
            'size_bytes' => $size,
            'checksum' => $checksum,
            'updated_at' => now(),
        ]);

        return [
            'path' => $path,
            'filename' => $filename,
            'size' => $size,
            'checksum' => $checksum,
            'manifest_sha256' => $manifestSha,
            'key_id' => $manifest['key_id'],
        ];
    }
}
