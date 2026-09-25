<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class H01BackupBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_H01_BACKUP_E2E_ENABLED') === '1', 403, 'Explicit isolated H01 fixture opt-in required.');
        $receiptPath = base_path('../.local/h01-backup-browser-receipt.json');
        $mode = getenv('MT75_H01_BACKUP_FIXTURE_ACTION');
        abort_unless(in_array($mode, ['seed', 'cleanup'], true), 403);
        if ($mode === 'seed') {
            abort_unless(! is_file($receiptPath) && DB::table('backup_records')->count() === 0, 409, 'Existing backup record/receipt blocks fixture.');
            $owner = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->firstOrFail();
            $connection = DB::table('integration_connections')->where('provider', 'google_drive')->firstOrFail();
            $root = storage_path('app/private/backups');
            if (! is_dir($root)) {
                mkdir($root, 0700, true);
            }
            $name = 'h01-browser-'.Str::uuid().'.backup';
            $path = $root.DIRECTORY_SEPARATOR.$name;
            $bytes = 'h01-test-only-backup-'.Str::uuid();
            abort_unless(file_put_contents($path, $bytes, LOCK_EX) === strlen($bytes), 409);
            try {
                $id = DB::table('backup_records')->insertGetId([
                    'integration_connection_id' => $connection->id, 'scope' => 'business', 'trigger' => 'manual',
                    'requested_by_type' => 'admin', 'requested_by_id' => $owner->id, 'status' => 'completed',
                    'filename' => $name, 'path' => $path, 'remote_status' => 'not_configured',
                    'size_bytes' => strlen($bytes), 'created_at' => now(), 'updated_at' => now(),
                ]);
                file_put_contents($receiptPath, json_encode(['id' => $id, 'owner_id' => $owner->id,
                    'name' => $name, 'path' => $path, 'sha256' => hash('sha256', $bytes)], JSON_THROW_ON_ERROR));
            } catch (\Throwable $e) {
                if (is_file($path) && hash_file('sha256', $path) === hash('sha256', $bytes)) {
                    unlink($path);
                }
                throw $e;
            }
            $this->command?->info('H01 isolated synthetic backup browser fixture created.');

            return;
        }
        abort_unless(is_file($receiptPath), 409, 'Missing H01-owned receipt; cannot delete unowned records.');
        $fixture = json_decode(file_get_contents($receiptPath), true, flags: JSON_THROW_ON_ERROR);
        $row = DB::table('backup_records')->where('id', $fixture['id'])->firstOrFail();
        abort_unless($row->requested_by_type === 'admin' && (int) $row->requested_by_id === $fixture['owner_id']
            && $row->filename === $fixture['name'] && $row->scope === 'business' && $row->status === 'completed'
            && $row->remote_path === null && ($row->path === null || $row->path === $fixture['path']), 409, 'Foreign backup/altered fixture; cleanup refused.');
        if (is_file($fixture['path'])) {
            abort_unless(hash_file('sha256', $fixture['path']) === $fixture['sha256'], 409, 'Fixture file changed; cleanup refused.');
            unlink($fixture['path']);
        }
        DB::transaction(function () use ($fixture) {
            $log = DB::table('admin_audit_logs')->where('action', 'backup_local_deleted')
                ->where('actor_email', 'e2e-protected-owner@example.invalid')->where('path', '/operations/backups')->get();
            foreach ($log as $event) {
                $payload = json_decode($event->payload, true, flags: JSON_THROW_ON_ERROR);
                if (($payload['backup_id'] ?? null) === $fixture['id']) {
                    DB::table('admin_audit_logs')->where('id', $event->id)->delete();
                }
            }
            DB::table('backup_records')->where('id', $fixture['id'])->delete();
        });
        unlink($receiptPath);
        $this->command?->info('H01 exact synthetic backup fixture and own audit cleaned.');
    }
}
