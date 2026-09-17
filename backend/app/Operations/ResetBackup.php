<?php

namespace App\Operations;

use App\Backups\BackupArchive;
use App\Backups\BackupRecovery;
use App\Backups\RecoveryManifest;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class ResetBackup
{
    public function __construct(
        private BackupArchive $archive,
        private BackupRecovery $recovery,
        private RecoveryManifest $manifest,
        private ResetObjectBackup $objects,
    ) {}

    public function createAndVerify(Admin $actor, string $level, array $objectKeys): array
    {
        $objectManifest = $this->objects->create($objectKeys);
        $this->objects->verify($objectManifest);
        try {
            return $this->createDatabaseBackup($actor, $level, $objectManifest);
        } catch (Throwable $error) {
            $this->objects->discardBackups($objectManifest);
            throw $error;
        }
    }

    private function createDatabaseBackup(Admin $actor, string $level, array $objectManifest): array
    {
        $recordId = DB::table('backup_records')->insertGetId([
            'scope' => 'reset',
            'trigger' => 'reset',
            'requested_by_type' => 'admin',
            'requested_by_id' => $actor->id,
            'status' => 'processing',
            'filename' => 'pending-'.Str::uuid().'.backup',
            'remote_status' => 'not_configured',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $archive = $this->archive->create($recordId);
        DB::table('backup_records')->where('id', $recordId)->update([
            'status' => 'completed',
            'completed_at' => now(),
            'updated_at' => now(),
        ]);
        $verified = $this->recovery->rehearseForReset(
            $actor,
            $recordId,
            $archive['path'],
            $this->manifest->keyId(),
            $level,
        );

        return [
            'record_id' => $recordId,
            'filename' => $archive['filename'],
            'checksum' => $archive['checksum'],
            'manifest_sha256' => $archive['manifest_sha256'],
            'rehearsal' => $verified,
            'object_manifest' => $objectManifest,
        ];
    }
}
