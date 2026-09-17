<?php

namespace App\Operations;

use App\Addendum\ResetDomains;
use App\Backups\RecoveryManifest;
use App\Identity\Access;
use App\Identity\RealmSessionPolicy;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class GuardedResetService
{
    public function __construct(
        private ResetPlanner $planner,
        private ResetDomains $domains,
        private ResetBackup $backup,
        private ResetObjectBackup $objectBackup,
        private RecoveryManifest $recovery,
        private AuditTrail $audit,
    ) {}

    public function preview(Admin $actor, Request $request, string $level, array $domains): array
    {
        $actor = $this->authorize($actor, 'system.reset.preview');
        $this->recent($request);
        $plan = $this->planner->preview($level, $domains);
        $publicId = (string) Str::uuid();
        $previewSha = $this->recovery->sha($plan);
        $scopeSha = hash('sha256', json_encode([$level, $plan['domains']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        DB::table('reset_operations')->insert([
            'public_id' => $publicId,
            'level' => $level,
            'selected_domains' => json_encode($plan['domains'], JSON_THROW_ON_ERROR),
            'scope_sha256' => $scopeSha,
            'preview_sha256' => $previewSha,
            'preview' => json_encode($plan, JSON_THROW_ON_ERROR),
            'record_count' => $plan['record_count'],
            'file_count' => $plan['file_count'],
            'actor_admin_id' => $actor->id,
            'status' => 'previewed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->audit->admin($actor, 'reset_preview_created', 'SYSTEM', '/operations/reset', [
            'reset_id' => $publicId,
            'level' => $level,
            'domains' => $plan['domains'],
            'record_count' => $plan['record_count'],
            'file_count' => $plan['file_count'],
        ]);

        return $this->safePreview($publicId, $plan, $previewSha);
    }

    public function execute(Admin $actor, Request $request, string $publicId, string $confirmation): array
    {
        $this->executionEnvironment();
        $this->recent($request);
        $operation = DB::table('reset_operations')->where('public_id', $publicId)->firstOrFail();
        $actor = $this->authorize($actor, 'system.reset.'.$operation->level);
        abort_unless((int) $operation->actor_admin_id === (int) $actor->id, 403);
        $this->confirmation((string) $operation->level, $confirmation);

        if ($operation->status === 'cleanup_pending') {
            return $this->resumeCleanup($actor, $operation);
        }
        abort_unless(in_array($operation->status, ['previewed', 'backup_verified'], true), 409,
            'Reset operation is not executable from its current state.');
        $domains = json_decode($operation->selected_domains, true, flags: JSON_THROW_ON_ERROR);
        $plan = $this->planner->preview($operation->level, $domains);
        $this->assertFreshPreview($operation, $plan);
        abort_if($plan['barriers'] !== [], 409, 'Reset scope has unresolved dependency or operational barriers.');

        if ($operation->status === 'previewed') {
            $operation = $this->prepareBackup($actor, $operation, $plan);
        }
        $plan = $this->planner->preview($operation->level, $domains);
        $this->assertFreshPreview($operation, $plan, allowCreatedAtAge: true);
        abort_if($plan['barriers'] !== [], 409, 'Reset scope changed after backup verification.');

        return $this->deleteAndCleanup($actor, $operation, $plan);
    }

    private function prepareBackup(Admin $actor, object $operation, array $plan): object
    {
        DB::transaction(function () use ($operation) {
            $locked = DB::table('reset_operations')->where('id', $operation->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'previewed', 409, 'Reset preview was already claimed.');
            DB::table('reset_operations')->where('id', $locked->id)->update([
                'status' => 'preparing', 'started_at' => now(), 'failure_code' => null, 'updated_at' => now(),
            ]);
        });
        try {
            $selected = $this->domains->tables($operation->level, $plan['domains']);
            $objectKeys = $this->planner->objectKeys($selected);
            $hash = hash('sha256', json_encode($objectKeys, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            abort_unless(hash_equals($plan['object_keys_sha256'], $hash), 409, 'Private object scope changed.');
            $backup = $this->backup->createAndVerify($actor, $operation->level, $objectKeys);
            DB::table('reset_operations')->where('id', $operation->id)->update([
                'status' => 'backup_verified',
                'backup_record_id' => $backup['record_id'],
                'object_backup_manifest' => json_encode($backup['object_manifest'], JSON_THROW_ON_ERROR),
                'failure_code' => null,
                'updated_at' => now(),
            ]);
            $this->audit->admin($actor, 'reset_backup_verified', 'SYSTEM', '/operations/reset', [
                'reset_id' => $operation->public_id, 'backup_id' => $backup['record_id'], 'level' => $operation->level,
            ]);
        } catch (Throwable $error) {
            DB::table('reset_operations')->where('id', $operation->id)->update([
                'status' => 'failed', 'failure_code' => 'BACKUP_VERIFICATION_FAILED', 'updated_at' => now(),
            ]);
            $this->audit->admin($actor, 'reset_backup_failed', 'SYSTEM', '/operations/reset', [
                'reset_id' => $operation->public_id, 'level' => $operation->level,
            ], 409);
            throw $error;
        }

        return DB::table('reset_operations')->where('id', $operation->id)->firstOrFail();
    }

    private function deleteAndCleanup(Admin $actor, object $operation, array $plan): array
    {
        $manifest = json_decode($operation->object_backup_manifest ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $this->objectBackup->verify($manifest);
        $deleted = [];
        try {
            DB::transaction(function () use ($operation, $plan, &$deleted) {
                $locked = DB::table('reset_operations')->where('id', $operation->id)->lockForUpdate()->firstOrFail();
                abort_unless($locked->status === 'backup_verified', 409, 'Verified backup state is required.');
                DB::table('reset_operations')->where('id', $locked->id)->update(['status' => 'deleting', 'updated_at' => now()]);
                foreach ($plan['cycle_breaks'] as $table => $columns) {
                    DB::table($table)->update(array_fill_keys($columns, null));
                }
                foreach ($plan['deletion_order'] as $table) {
                    $deleted[$table] = $table === 'users'
                        ? DB::table('users')->where('is_admin', false)->delete()
                        : DB::table($table)->delete();
                }

                DB::table('reset_operations')->where('id', $locked->id)->update([
                    'status' => 'cleanup_pending',
                    'result' => json_encode(['deleted_records' => $deleted], JSON_THROW_ON_ERROR),
                    'failure_code' => null,
                    'updated_at' => now(),
                ]);
            }, 3);
        } catch (Throwable $error) {
            DB::table('reset_operations')->where('id', $operation->id)->update([
                'status' => 'backup_verified', 'failure_code' => 'DATABASE_DELETE_FAILED', 'updated_at' => now(),
            ]);
            throw $error;
        }
        Cache::flush();
        $after = $this->planner->preview($operation->level, $plan['domains']);
        if ($after['record_count'] !== 0 || $after['file_count'] !== 0 || $after['barriers'] !== []) {
            DB::table('reset_operations')->where('id', $operation->id)->update([
                'failure_code' => 'POST_DELETE_INTEGRITY_FAILED', 'updated_at' => now(),
            ]);
            throw new RuntimeException('POST_DELETE_INTEGRITY_FAILED');
        }

        return $this->finishCleanup($actor, DB::table('reset_operations')->where('id', $operation->id)->firstOrFail());
    }

    private function finishCleanup(Admin $actor, object $operation): array
    {
        $manifest = json_decode($operation->object_backup_manifest ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        try {
            $this->objectBackup->cleanupSources($manifest);
        } catch (Throwable $error) {
            DB::table('reset_operations')->where('id', $operation->id)->update([
                'status' => 'cleanup_pending', 'failure_code' => 'PRIVATE_OBJECT_CLEANUP_FAILED', 'updated_at' => now(),
            ]);
            $this->audit->admin($actor, 'reset_cleanup_pending', 'SYSTEM', '/operations/reset', [
                'reset_id' => $operation->public_id, 'level' => $operation->level,
            ], 409);
            throw $error;
        }
        $result = json_decode($operation->result ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $result['private_objects_deleted'] = (int) ($manifest['count'] ?? 0);
        $result['backup_record_id'] = (int) $operation->backup_record_id;
        DB::table('reset_operations')->where('id', $operation->id)->update([
            'status' => 'completed',
            'failure_code' => null,
            'completed_at' => now(),
            'result' => json_encode($result, JSON_THROW_ON_ERROR),
            'updated_at' => now(),
        ]);
        $this->audit->admin($actor, 'reset_completed', 'SYSTEM', '/operations/reset', [
            'reset_id' => $operation->public_id,
            'level' => $operation->level,
            'backup_id' => $operation->backup_record_id,
            'private_objects_deleted' => $result['private_objects_deleted'],
        ]);

        return ['public_id' => $operation->public_id, 'status' => 'completed', 'result' => $result];
    }

    private function resumeCleanup(Admin $actor, object $operation): array
    {
        abort_unless($operation->status === 'cleanup_pending', 409);

        return $this->finishCleanup($actor, $operation);
    }

    private function assertFreshPreview(object $operation, array $plan, bool $allowCreatedAtAge = false): void
    {
        $previewSha = $this->recovery->sha($plan);
        abort_unless(hash_equals((string) $operation->preview_sha256, $previewSha), 409, 'Reset preview is stale.');
        abort_unless((int) $operation->record_count === (int) $plan['record_count']
            && (int) $operation->file_count === (int) $plan['file_count'], 409, 'Reset preview counts changed.');
        $scopeSha = hash('sha256', json_encode([$operation->level, $plan['domains']], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        abort_unless(hash_equals((string) $operation->scope_sha256, $scopeSha), 409, 'Reset scope changed.');
        if (! $allowCreatedAtAge) {
            $ttl = max(1, (int) config('reset.preview_ttl_minutes', 10));
            abort_unless(strtotime((string) $operation->created_at) >= now()->subMinutes($ttl)->timestamp, 409, 'Reset preview expired.');
        }
    }

    private function safePreview(string $publicId, array $plan, string $previewSha): array
    {
        return [
            'public_id' => $publicId,
            'level' => $plan['level'],
            'domains' => $plan['domains'],
            'domain_counts' => $plan['domain_counts'],
            'record_count' => $plan['record_count'],
            'file_count' => $plan['file_count'],
            'barriers' => $plan['barriers'],
            'preservation' => $plan['preservation'],
            'preview_sha256' => $previewSha,
            'confirmation_required' => config('reset.confirmation.'.$plan['level']),
            'execution_enabled_here' => in_array(app()->environment(), config('reset.execution_environments', []), true),
        ];
    }

    private function authorize(Admin $actor, string $permission): Admin
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, $permission), 403);

        return $fresh;
    }

    private function recent(Request $request): void
    {
        abort_unless($request->hasSession()
            && app(RealmSessionPolicy::class)->recentlyAuthenticated($request), 403,
            'Recent authentication is required.');
    }

    private function confirmation(string $level, string $confirmation): void
    {
        $expected = (string) config('reset.confirmation.'.$level);
        abort_unless($expected !== '' && hash_equals($expected, trim($confirmation)), 422,
            'Typed reset confirmation does not match the reset level.');
    }

    private function executionEnvironment(): void
    {
        abort_unless(in_array(app()->environment(), config('reset.execution_environments', []), true), 403,
            'Destructive reset execution is not authorized in this environment.');
    }
}
