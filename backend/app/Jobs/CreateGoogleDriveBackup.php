<?php

namespace App\Jobs;

use App\Backups\BackupArchive;
use App\Integrations\RcloneGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

final class CreateGoogleDriveBackup implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $recordId) {}

    public function handle(BackupArchive $archive, RcloneGateway $rclone): void
    {
        $record = DB::table('backup_records')->where('id', $this->recordId)->firstOrFail();
        $connection = DB::table('integration_connections')->where('id', $record->integration_connection_id)
            ->where('provider', 'google_drive')->where('status', 'connected')->first();
        abort_unless($connection, 409, 'Google Drive is not connected.');
        $configuration = json_decode($connection->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $managed = (bool) ($configuration['managed'] ?? true);
        $file = $archive->create($this->recordId);
        $remote = 'mobiST Tech/Backups/'.app()->environment().'/'.now()->format('Y/m/d').'/'.$file['filename'];
        try {
            $rclone->copyTo($file['path'], $remote, $managed);
            DB::table('backup_records')->where('id', $this->recordId)->update(['status' => 'completed', 'remote_status' => 'uploaded',
                'remote_path' => $remote, 'completed_at' => now(), 'updated_at' => now()]);
            DB::table('integration_connections')->where('id', $record->integration_connection_id)->update(['last_success_at' => now(), 'last_error_summary' => null, 'updated_at' => now()]);
            $cutoff = isset($configuration['retention_days']) ? now()->subDays((int) $configuration['retention_days']) : null;
            if ($cutoff) {
                foreach (DB::table('backup_records')->where('integration_connection_id', $record->integration_connection_id)->where('status', 'completed')
                    ->where('completed_at', '<', $cutoff)->whereNotNull('remote_path')->get() as $expired) {
                    $rclone->deleteRemote($expired->remote_path, $managed);
                    DB::table('backup_records')->where('id', $expired->id)->update(['remote_status' => 'expired', 'updated_at' => now()]);
                }
            }
        } finally {
            @unlink($file['path']);
            DB::table('backup_records')->where('id', $this->recordId)->update(['path' => null, 'updated_at' => now()]);
        }
    }

    public function failed(\Throwable $error): void
    {
        DB::table('backup_records')->where('id', $this->recordId)->update(['status' => 'failed', 'remote_status' => 'failed',
            'error_message' => 'Google Drive backup failed.', 'completed_at' => now(), 'updated_at' => now()]);
    }
}
