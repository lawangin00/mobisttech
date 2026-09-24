<?php

namespace Tests\Feature;

use App\Backups\BackupArchive;
use App\Backups\BackupRecovery;
use App\Backups\BackupService;
use App\Infrastructure\DomainEventLeases;
use App\Integrations\IntegrationManager;
use App\Integrations\RcloneGateway;
use App\Models\Admin;
use App\Operations\AuditTrail;
use App\Operations\ConfigurationRecovery;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperationalRecoveryTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new Admin;
        $this->admin->forceFill([
            'name' => 'Recovery Admin',
            'email' => 'recovery-admin@example.invalid',
            'password' => Hash::make('SyntheticPass123!'),
            'permissions' => [
                'backups.manage',
                'config.integrations-backups.manage',
                'admin.integrations.manage',
            ],
        ])->save();
        config([
            'backups.key_id' => 'synthetic-key-2026',
            'backups.external_jobs_enabled' => false,
            'services.google.enabled' => false,
        ]);
    }

    public function test_audit_redaction_and_configuration_recovery_never_restore_rotated_secrets(): void
    {
        DB::table('site_secret_settings')->insert([
            'key' => 'payment.api_secret',
            'ciphertext' => Crypt::encryptString('plain-secret-value'),
            'version' => 1,
            'rotated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(AuditTrail::class)->admin($this->admin, 'synthetic_audit', 'POST', '/settings?token=should-not-log', [
            'password' => 'do-not-log',
            'nested' => ['refresh_token' => 'also-secret', 'safe' => 'visible'],
            'cnic' => '42501-0000000-0',
        ]);
        $audit = DB::table('admin_audit_logs')->where('action', 'synthetic_audit')->latest('id')->firstOrFail();
        $payload = json_decode($audit->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/settings', $audit->path);
        $this->assertSame('[REDACTED]', $payload['password']);
        $this->assertSame('[REDACTED]', $payload['nested']['refresh_token']);
        $this->assertSame('[REDACTED]', $payload['cnic']);
        $this->assertSame('visible', $payload['nested']['safe']);
        $this->assertStringNotContainsString('do-not-log', $audit->payload);

        $service = app(ConfigurationRecovery::class);
        $snapshot = $service->capture($this->admin);
        $row = DB::table('configuration_recovery_snapshots')->where('public_id', $snapshot['public_id'])->firstOrFail();
        $this->assertStringNotContainsString('plain-secret-value', $row->manifest);
        $this->assertSame('synthetic-key-2026', $snapshot['key_id']);
        $verified = $service->verify($this->admin, $snapshot['public_id'], 'synthetic-key-2026');
        $this->assertNotNull($verified['verified_at']);
        // Wrong submitted key and changed runtime key are both hard failures, not silent restore.
        foreach (['wrong-key-2026', ''] as $wrongKey) {
            try {
                $service->verify($this->admin, $snapshot['public_id'], $wrongKey);
                $this->fail('Wrong recovery key was accepted.');
            } catch (HttpException $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        }
        config(['backups.key_id' => 'different-runtime-2026']);
        try {
            $service->verify($this->admin, $snapshot['public_id'], 'synthetic-key-2026');
            $this->fail('Changed runtime recovery key was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        } finally {
            config(['backups.key_id' => 'synthetic-key-2026']);
        }

        DB::table('site_secret_settings')->where('key', 'payment.api_secret')->update([
            'ciphertext' => Crypt::encryptString('rotated-secret-value'),
            'version' => 2,
            'rotated_at' => now(),
            'updated_at' => now(),
        ]);
        try {
            $service->verify($this->admin, $snapshot['public_id'], 'synthetic-key-2026');
            $this->fail('Rotated secret state was accepted by recovery verification.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
    }

    public function test_encrypted_backup_rehearsal_is_target_only_and_key_dependent(): void
    {
        $connection = DB::table('integration_connections')->where('provider', 'google_drive')->firstOrFail();
        $recordId = DB::table('backup_records')->insertGetId([
            'integration_connection_id' => $connection->id,
            'scope' => 'business',
            'trigger' => 'manual',
            'requested_by_type' => 'admin',
            'requested_by_id' => $this->admin->id,
            'status' => 'processing',
            'filename' => 'pending.backup',
            'remote_status' => 'not_configured',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $file = app(BackupArchive::class)->create($recordId);
        try {
            $encrypted = file_get_contents($file['path']);
            $this->assertStringNotContainsString('business_profiles', $encrypted);
            $this->assertStringNotContainsString('mobisttech@gmail.com', $encrypted);
            $result = app(BackupRecovery::class)->rehearse($this->admin, $recordId, $file['path'], $file['key_id']);
            $this->assertSame('passed', $result['status']);
            $this->assertGreaterThan(0, $result['table_count']);
            $this->assertSame(1, DB::table('backup_restore_rehearsals')->where('status', 'passed')->count());

            DB::table('integration_connections')->where('provider', 'google_drive')->update(['status' => 'connected']);
            try {
                app(BackupRecovery::class)->rehearse($this->admin, $recordId, $file['path'], 'wrong-key-identity');
                $this->fail('Wrong recovery key identity was accepted.');
            } catch (RuntimeException $error) {
                $this->assertSame('RECOVERY_KEY_MISMATCH', $error->getMessage());
            }
            $this->assertSame('error', DB::table('integration_connections')->where('provider', 'google_drive')->value('status'));
            $this->assertSame(1, DB::table('backup_restore_rehearsals')->where('status', 'failed')->count());
        } finally {
            @unlink($file['path']);
        }
    }

    public function test_domain_event_lease_reclaims_and_records_terminal_failure(): void
    {
        $id = (string) Str::uuid();
        DB::table('domain_events')->insert([
            'id' => $id,
            'aggregate_type' => 'synthetic',
            'aggregate_id' => '1',
            'aggregate_version' => 1,
            'event_type' => 'synthetic.event',
            'payload' => json_encode(['safe' => true], JSON_THROW_ON_ERROR),
            'operation_key' => 'mt33-'.Str::uuid(),
            'created_at' => now(),
        ]);
        $leases = app(DomainEventLeases::class);
        $first = $leases->claim('mt33-test', 5);
        $this->assertSame($id, $first['id']);
        $this->assertSame(1, $first['attempts']);
        $leases->fail($id, $first['lease_token'], 'SYNTHETIC_RETRY', 2, 0);
        $second = $leases->claim('mt33-test', 5);
        $this->assertSame(2, $second['attempts']);
        $this->assertNotSame($first['lease_token'], $second['lease_token']);
        try {
            $leases->complete($id, $first['lease_token']);
            $this->fail('Stale event lease was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        $leases->fail($id, $second['lease_token'], 'SYNTHETIC_TERMINAL', 2, 0);
        $row = DB::table('domain_events')->where('id', $id)->firstOrFail();
        $this->assertNotNull($row->failed_at);
        $this->assertSame('SYNTHETIC_TERMINAL', $row->last_error_code);
        $this->assertNull($leases->claim('mt33-test', 5));
    }

    public function test_external_jobs_default_off_and_rclone_contract_is_allowlisted(): void
    {
        $manager = app(IntegrationManager::class);
        try {
            $manager->connect($this->admin, 'gmail');
            $this->fail('Disabled external integration was started.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }

        app(BackupService::class)->dispatchDue();
        $this->assertSame(0, DB::table('backup_records')->count());

        config(['services.google_drive.rclone_binary' => 'cmd.exe']);
        $gateway = new class extends RcloneGateway
        {
            public function probe(): string
            {
                return $this->run(['listremotes'], false);
            }
        };
        try {
            $gateway->probe();
            $this->fail('Non-rclone executable was accepted.');
        } catch (RuntimeException $error) {
            $this->assertSame('Invalid rclone executable configuration.', $error->getMessage());
        }

        config(['services.google_drive.rclone_binary' => 'rclone']);
        try {
            $gateway->copyTo(__FILE__, '../outside.backup');
            $this->fail('Backup path outside the fixed namespace was accepted.');
        } catch (RuntimeException $error) {
            $this->assertSame('Invalid backup remote path.', $error->getMessage());
        }
    }
}
