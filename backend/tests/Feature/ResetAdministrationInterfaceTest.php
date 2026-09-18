<?php

namespace Tests\Feature;

use App\Infrastructure\PrivateObjects;
use App\Models\Admin;
use App\Operations\ResetObjectBackup;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ResetAdministrationInterfaceTest extends TestCase
{
    use DatabaseTransactions;

    private const PASSWORD = 'SyntheticPass123!';

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reset_admin_requires_permission_recent_auth_and_preserves_cancelled_and_stale_previews_without_backup(): void
    {
        $denied = $this->admin([]);
        $deniedClient = $this->client();
        $this->login($deniedClient, $denied->email)->assertOk();
        $this->send($deniedClient, 'GET', '/internal/admin/reset-administration/data')->assertForbidden();

        $actor = $this->admin($this->resetPermissions());
        $client = $this->client();
        $this->login($client, $actor->email)->assertOk();

        $data = $this->send($client, 'GET', '/internal/admin/reset-administration/data')->assertOk();
        $data->assertJsonPath('data.recent_authentication', true)
            ->assertJsonPath('data.execution_enabled_here', true)
            ->assertJsonPath('data.production_hold', true)
            ->assertJsonPath('data.levels.transactional.confirmation_required', 'TRANSACTIONAL DATA RESET')
            ->assertJsonPath('data.levels.business.confirmation_required', 'BUSINESS DATA RESET')
            ->assertJsonPath('data.levels.factory.confirmation_required', 'FACTORY RESET')
            ->assertJsonPath('data.levels.factory.factory_scope_locked', true);
        $this->assertContains('admins', $data->json('data.minimum_bootstrap.access_tables'));
        $this->assertContains('roles', $data->json('data.minimum_bootstrap.access_tables'));
        $this->assertContains('reset_operations', $data->json('data.minimum_bootstrap.recovery_evidence_tables'));
        $this->assertContains('backup_records', $data->json('data.minimum_bootstrap.recovery_evidence_tables'));

        $this->serviceRequest();
        Carbon::setTestNow(now()->addMinutes(11));
        $this->send($client, 'GET', '/internal/admin/reset-administration/data')->assertOk()
            ->assertJsonPath('data.recent_authentication', false);
        $this->send($client, 'POST', '/internal/admin/reset-administration/preview', [
            'level' => 'transactional', 'domains' => ['service_requests'],
        ])->assertForbidden();

        $this->send($client, 'POST', '/internal/admin/auth/confirm-password', ['password' => self::PASSWORD])->assertOk();
        $preview = $this->send($client, 'POST', '/internal/admin/reset-administration/preview', [
            'level' => 'transactional', 'domains' => ['service_requests'],
        ])->assertOk();
        $resetId = $preview->json('data.public_id');
        $preview->assertJsonPath('data.level', 'transactional')
            ->assertJsonPath('data.confirmation_required', 'TRANSACTIONAL DATA RESET')
            ->assertJsonPath('data.execution_enabled_here', true);
        $this->assertGreaterThan(0, (int) $preview->json('data.record_count'));

        // UI cancellation deliberately sends no mutation. Durable evidence remains previewed and no backup is created.
        $this->assertSame('previewed', DB::table('reset_operations')->where('public_id', $resetId)->value('status'));
        $this->assertSame(0, DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actor->id)->count());
        $history = $this->send($client, 'GET', '/internal/admin/reset-administration/data')->assertOk();
        $this->assertTrue(collect($history->json('data.operations'))->contains(
            fn ($row) => $row['public_id'] === $resetId && $row['status'] === 'previewed'
        ));

        // Changing the selected domain state after preview must fail before backup.
        $this->serviceRequest();
        $this->send($client, 'POST', '/internal/admin/reset-administration/operations/'.$resetId.'/execute', [
            'confirmation' => 'TRANSACTIONAL DATA RESET',
        ])->assertConflict();
        $this->assertSame('previewed', DB::table('reset_operations')->where('public_id', $resetId)->value('status'));
        $this->assertSame(0, DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actor->id)->count());

        $this->send($client, 'POST', '/internal/admin/reset-administration/preview', [
            'level' => 'factory', 'domains' => ['customers'],
        ])->assertUnprocessable();
    }

    public function test_failed_verified_backup_stops_reset_and_records_failure_without_deleting_selected_data(): void
    {
        $actor = $this->admin($this->resetPermissions());
        $client = $this->client();
        $this->login($client, $actor->email)->assertOk();
        $this->send($client, 'POST', '/internal/admin/auth/confirm-password', ['password' => self::PASSWORD])->assertOk();

        [, $requestId] = $this->serviceRequest();
        $preview = $this->send($client, 'POST', '/internal/admin/reset-administration/preview', [
            'level' => 'transactional', 'domains' => ['service_requests'],
        ])->assertOk();
        $resetId = $preview->json('data.public_id');

        $original = config('backups.rehearsal_environments');
        config(['backups.rehearsal_environments' => []]);
        try {
            $this->send($client, 'POST', '/internal/admin/reset-administration/operations/'.$resetId.'/execute', [
                'confirmation' => 'TRANSACTIONAL DATA RESET',
            ])->assertServerError();
        } finally {
            config(['backups.rehearsal_environments' => $original]);
        }

        $operation = DB::table('reset_operations')->where('public_id', $resetId)->firstOrFail();
        $this->assertSame('failed', $operation->status);
        $this->assertSame('BACKUP_VERIFICATION_FAILED', $operation->failure_code);
        $this->assertTrue(DB::table('service_requests')->where('id', $requestId)->exists());
        $this->assertTrue(DB::table('admin_audit_logs')->where('action', 'reset_backup_failed')->exists());
        $backupId = DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actor->id)
            ->orderByDesc('id')->value('id');
        $this->assertNotNull($backupId);
        $this->assertSame(0, DB::table('backup_restore_rehearsals')->where('backup_record_id', $backupId)->count());
        $this->assertNull(DB::table('backup_manifests')->where('backup_record_id', $backupId)->value('verified_at'));

        $this->cleanupBackupFiles($actor->id);
    }

    public function test_cleanup_pending_operation_can_resume_from_verified_private_object_backup(): void
    {
        $actor = $this->admin($this->resetPermissions());
        $client = $this->client();
        $this->login($client, $actor->email)->assertOk();
        $this->send($client, 'POST', '/internal/admin/auth/confirm-password', ['password' => self::PASSWORD])->assertOk();

        $sourceKey = 'client-files/'.Str::uuid().'.pdf';
        $bytes = 'partial cleanup recovery fixture';
        app(PrivateObjects::class)->put($sourceKey, $bytes, hash('sha256', $bytes));
        $manifest = app(ResetObjectBackup::class)->create([$sourceKey]);
        $backupId = DB::table('backup_records')->insertGetId([
            'scope' => 'reset', 'trigger' => 'reset', 'requested_by_type' => 'admin', 'requested_by_id' => $actor->id,
            'status' => 'completed', 'filename' => 'synthetic-partial.backup', 'remote_status' => 'not_configured',
            'created_at' => now(), 'updated_at' => now(), 'completed_at' => now(),
        ]);
        $publicId = (string) Str::uuid();
        DB::table('reset_operations')->insert([
            'public_id' => $publicId, 'level' => 'transactional',
            'selected_domains' => json_encode(['service_requests'], JSON_THROW_ON_ERROR),
            'scope_sha256' => str_repeat('a', 64), 'preview_sha256' => str_repeat('b', 64),
            'preview' => json_encode(['synthetic' => true], JSON_THROW_ON_ERROR),
            'record_count' => 1, 'file_count' => 1, 'actor_admin_id' => $actor->id,
            'status' => 'cleanup_pending', 'backup_record_id' => $backupId,
            'object_backup_manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'result' => json_encode(['deleted_records' => ['service_requests' => 1]], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->send($client, 'POST', '/internal/admin/reset-administration/operations/'.$publicId.'/execute', [
            'confirmation' => 'TRANSACTIONAL DATA RESET',
        ])->assertOk();
        $response->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.result.private_objects_deleted', 1)
            ->assertJsonPath('data.result.backup_record_id', $backupId);
        $this->assertFalse(app(PrivateObjects::class)->exists($sourceKey));
        $this->assertSame('completed', DB::table('reset_operations')->where('public_id', $publicId)->value('status'));
        $this->assertTrue(DB::table('admin_audit_logs')->where('action', 'reset_completed')->exists());

        foreach ($manifest['items'] as $item) {
            if (app(PrivateObjects::class)->exists($item['backup_key'])) {
                app(PrivateObjects::class)->delete($item['backup_key']);
            }
        }
    }

    private function resetPermissions(): array
    {
        return ['system.reset.preview', 'system.reset.transactional', 'system.reset.business', 'system.reset.factory'];
    }

    private function admin(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill([
            'public_id' => (string) Str::uuid(),
            'name' => 'MT47 Reset Admin',
            'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make(self::PASSWORD),
            'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();

        return $admin->fresh();
    }

    private function serviceRequest(?int $digitalServiceId = null): array
    {
        $digitalServiceId ??= DB::table('digital_services')->insertGetId([
            'slug' => 'mt47-'.Str::lower(Str::random(10)),
            'name' => 'MT47 Reset Service', 'short_description' => 'Disposable reset fixture',
            'price_type' => 'quote', 'is_active' => true, 'sort_order' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $requestId = DB::table('service_requests')->insertGetId([
            'reference' => 'MT47-'.Str::uuid(), 'digital_service_id' => $digitalServiceId,
            'customer_name' => 'MT47 Reset Lead', 'customer_mobile' => '03000000000',
            'requirements' => 'Disposable reset fixture', 'preferred_contact' => 'whatsapp',
            'status' => 'new', 'public_id' => (string) Str::uuid(), 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$digitalServiceId, $requestId];
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.7 browser'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $email, 'password' => self::PASSWORD,
        ]);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true)
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
        ];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }

    private function cleanupBackupFiles(int $actorId): void
    {
        foreach (DB::table('backup_records')->where('scope', 'reset')->where('requested_by_id', $actorId)->pluck('path') as $path) {
            if (is_string($path) && $path !== '' && is_file($path)) {
                @unlink($path);
            }
        }
    }
}
