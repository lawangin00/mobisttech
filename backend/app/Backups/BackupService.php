<?php

namespace App\Backups;

use App\Identity\Access;
use App\Integrations\RcloneGateway;
use App\Jobs\CreateGoogleDriveBackup;
use App\Models\Admin;
use App\Operations\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class BackupService
{
    public function __construct(private RcloneGateway $rclone, private AuditTrail $audit) {}

    public function request(?Admin $actor, string $trigger): int
    {
        if ($actor) {
            abort_unless(app(Access::class)->allows($actor, 'backups.manage'), 403);
        }
        abort_unless(in_array($trigger, ['manual', 'scheduled'], true), 422);
        abort_unless((bool) config('services.google.enabled'), 409, 'External integrations are disabled.');
        if ($trigger === 'scheduled') {
            abort_unless((bool) config('backups.external_jobs_enabled'), 409, 'Scheduled external backups are disabled.');
        }
        $connection = DB::table('integration_connections')->where('provider', 'google_drive')->where('status', 'connected')->firstOrFail();
        $id = DB::table('backup_records')->insertGetId([
            'integration_connection_id' => $connection->id, 'scope' => 'business', 'trigger' => $trigger,
            'requested_by_type' => $actor ? 'admin' : 'scheduler', 'requested_by_id' => $actor?->id, 'status' => 'queued',
            'filename' => 'pending-'.Str::uuid().'.backup', 'remote_status' => 'queued', 'created_at' => now(), 'updated_at' => now(),
        ]);
        CreateGoogleDriveBackup::dispatch($id)->afterCommit();
        if ($actor) {
            $this->audit->admin($actor, 'backup_requested', 'SYSTEM', '/operations/backups', ['backup_id' => $id, 'trigger' => $trigger], 202);
        }

        return $id;
    }

    public function dispatchDue(): void
    {
        if (! config('services.google.enabled') || ! config('backups.external_jobs_enabled')) {
            return;
        }
        $connection = DB::table('integration_connections')->where('provider', 'google_drive')->where('status', 'connected')->first();
        if (! $connection) {
            return;
        }
        $config = json_decode($connection->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $hours = match ($config['schedule'] ?? 'disabled') {
            'daily' => 24,
            'weekly' => 168,
            default => null,
        };
        if ($hours === null) {
            return;
        }
        $latest = DB::table('backup_records')->where('integration_connection_id', $connection->id)
            ->whereIn('status', ['queued', 'processing', 'completed'])->max('created_at');
        if (! $latest || now()->diffInHours($latest) >= $hours) {
            $this->request(null, 'scheduled');
        }
    }

    public function history(Admin $actor): array
    {
        abort_unless(app(Access::class)->allows($actor, 'backups.manage'), 403);

        return DB::table('backup_records as b')->leftJoin('backup_manifests as m', 'm.backup_record_id', '=', 'b.id')
            ->orderByDesc('b.id')->limit(50)->get(['b.id', 'b.trigger', 'b.status', 'b.filename', 'b.path', 'b.remote_path', 'b.size_bytes', 'b.remote_status',
                'b.completed_at', 'b.created_at', 'm.contract_version', 'm.key_id', 'm.manifest_sha256', 'm.verified_at'])
            ->map(function ($row) {
                $data = (array) $row;
                $data['local_available'] = $row->status === 'completed' && $row->path !== null;
                $data['can_delete_local'] = $row->path !== null && $row->remote_path === null
                    && in_array($row->status, ['completed', 'failed'], true);
                unset($data['path'], $data['remote_path']);

                return $data;
            })->all();
    }

    public function readLocal(Admin $actor, int $backupRecordId): array
    {
        abort_unless(app(Access::class)->allows($actor, 'backups.manage'), 403);
        $row = DB::table('backup_records')->where('id', $backupRecordId)->firstOrFail();
        abort_unless($row->status === 'completed' && $row->scope === 'business', 409, 'Backup is not available for download.');
        $path = $this->safeLocalPath($row);
        abort_unless($path !== null && is_file($path), 404);

        return ['filename' => $row->filename, 'bytes' => file_get_contents($path)];
    }

    /** HTTP operator action: local-only, terminal records; never delete a remote backup via a browser request. */
    public function deleteLocal(Admin $actor, int $backupRecordId): void
    {
        abort_unless(app(Access::class)->allows($actor, 'backups.manage'), 403);
        DB::transaction(function () use ($actor, $backupRecordId) {
            $row = DB::table('backup_records')->where('id', $backupRecordId)->lockForUpdate()->firstOrFail();
            abort_unless($row->scope === 'business' && $row->remote_path === null
                && in_array($row->status, ['completed', 'failed'], true), 409,
                'Only terminal local-only backup copies can be deleted here.');
            $path = $this->safeLocalPath($row);
            abort_unless($path !== null && is_file($path), 404);
            abort_unless(unlink($path), 409, 'Backup file could not be removed.');
            DB::table('backup_records')->where('id', $row->id)->update([
                'path' => null, 'remote_status' => 'deleted', 'updated_at' => now(),
            ]);
            $this->audit->admin($actor, 'backup_local_deleted', 'SYSTEM', '/operations/backups', ['backup_id' => $row->id]);
        });
    }

    public function delete(Admin $actor, int $backupRecordId): void
    {
        abort_unless(app(Access::class)->allows($actor, 'backups.manage'), 403);
        $row = DB::table('backup_records')->where('id', $backupRecordId)->firstOrFail();
        $path = $this->safeLocalPath($row);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
        if (is_string($row->remote_path) && $row->remote_path !== '' && $row->remote_status === 'uploaded') {
            abort_unless((bool) config('services.google.enabled'), 409, 'External integrations are disabled.');
            $connection = DB::table('integration_connections')->where('id', $row->integration_connection_id)->firstOrFail();
            $configuration = json_decode($connection->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
            $this->rclone->deleteRemote($row->remote_path, (bool) ($configuration['managed'] ?? true));
        }
        DB::table('backup_records')->where('id', $row->id)->update([
            'path' => null,
            'remote_status' => $row->remote_path ? 'deleted' : $row->remote_status,
            'updated_at' => now(),
        ]);
        $this->audit->admin($actor, 'backup_deleted', 'SYSTEM', '/operations/backups', ['backup_id' => $row->id]);
    }

    private function safeLocalPath(object $row): ?string
    {
        if (! is_string($row->path) || $row->path === '') {
            return null;
        }
        $root = realpath(storage_path('app/private/backups'));
        $path = realpath($row->path);
        abort_unless(is_string($root) && is_string($path) && dirname($path) === $root && basename($path) === $row->filename, 409, 'Backup path is invalid.');

        return $path;
    }
}
