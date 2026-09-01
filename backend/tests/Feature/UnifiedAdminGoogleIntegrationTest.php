<?php

namespace Tests\Feature;

use App\Backups\BackupArchive;
use App\Business\BusinessProfile;
use App\Integrations\GmailRecovery;
use App\Integrations\IntegrationManager;
use App\Integrations\RcloneGateway;
use App\Jobs\CreateGoogleDriveBackup;
use App\Models\Admin;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class UnifiedAdminGoogleIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = new Admin;
        $this->admin->forceFill(['name' => 'Integration Admin', 'email' => 'integration-admin@example.invalid', 'password' => Hash::make('SyntheticPass123!'),
            'permissions' => ['admin.integrations.manage', 'admin.business-profile.manage', 'backups.manage']])->save();
        config(['services.google.enabled' => true,
            'services.google.gmail.client_id' => 'synthetic-gmail-client', 'services.google.gmail.client_secret' => 'synthetic-gmail-secret',
            'services.google.gmail.redirect' => 'https://admin.mobisttech.test/internal/admin/integrations/gmail/callback',
            'services.google.google_drive.client_id' => 'synthetic-drive-client', 'services.google.google_drive.client_secret' => 'synthetic-drive-secret',
            'services.google.google_drive.redirect' => 'https://admin.mobisttech.test/internal/admin/integrations/google_drive/callback']);
    }

    public function test_canonical_business_profile_is_the_public_and_sale_time_authority(): void
    {
        $expected = ['business_name' => 'mobiST Technologies', 'business_email' => 'mobisttech@gmail.com', 'public_website' => 'https://mobisttech.com', 'version' => 1];
        $this->assertSame($expected, app(BusinessProfile::class)->current());
        $this->getJson('/api/v1/business-profile')->assertOk()->assertExactJson(['data' => $expected]);
        $updated = app(BusinessProfile::class)->update($this->admin, array_slice($expected, 0, 3));
        $this->assertSame(2, $updated['version']);
        $this->assertSame(1, DB::table('business_profiles')->count());
    }

    public function test_gmail_connect_uses_only_send_scope_verifies_account_and_hides_encrypted_tokens(): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'synthetic-access', 'refresh_token' => 'synthetic-refresh',
                'expires_in' => 3600, 'token_type' => 'Bearer', 'scope' => 'https://www.googleapis.com/auth/gmail.send']),
            'https://gmail.googleapis.com/gmail/v1/users/me/profile' => Http::response(['emailAddress' => 'mobisttech@gmail.com']),
            'https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'synthetic-message']),
        ]);
        $manager = app(IntegrationManager::class);
        $start = $manager->connect($this->admin, 'gmail');
        $parts = parse_url($start['authorization_url']);
        parse_str($parts['query'], $query);
        $this->assertSame('https://www.googleapis.com/auth/gmail.send', $query['scope']);
        $this->assertSame('offline', $query['access_type']);
        $this->assertSame('mobisttech@gmail.com', $query['login_hint']);
        $this->assertArrayHasKey('code_challenge', $query);
        $status = $manager->callback($this->admin, 'gmail', ['state' => $query['state'], 'code' => 'synthetic-code']);
        $this->assertSame('connected', $status['status']);
        $row = DB::table('integration_connections')->where('provider', 'gmail')->first();
        $this->assertStringNotContainsString('synthetic-refresh', $row->encrypted_credentials);
        $this->assertSame('synthetic-refresh', json_decode(Crypt::decryptString($row->encrypted_credentials), true)['refresh_token']);
        $this->assertArrayNotHasKey('encrypted_credentials', $status);
        Http::assertSent(fn (Request $request) => $request->url() === 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send'
            && str_contains(base64_decode(strtr($request['raw'], '-_', '+/')), 'From: mobiST Technologies <mobisttech@gmail.com>'));
        $this->assertSame('not_connected', $manager->disconnect($this->admin, 'gmail')['status']);
        $this->assertNull(DB::table('integration_connections')->where('provider', 'gmail')->value('encrypted_credentials'));
    }

    public function test_drive_connect_uses_private_backend_gateway_and_requires_read_write_delete_validation(): void
    {
        $gateway = new class extends RcloneGateway
        {
            public bool $configured = false;

            public bool $validated = false;

            public bool $disconnected = false;

            public function detectExisting(): bool
            {
                return false;
            }

            public function configure(array $tokens, string $clientId, string $clientSecret): void
            {
                $this->configured = isset($tokens['refresh_token']) && $clientSecret === 'synthetic-drive-secret';
            }

            public function validate(bool $managed = true): bool
            {
                $this->validated = true;

                return true;
            }

            public function disconnectManaged(): void
            {
                $this->disconnected = true;
            }
        };
        $this->app->instance(RcloneGateway::class, $gateway);
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'drive-access', 'refresh_token' => 'drive-refresh', 'expires_in' => 3600,
                'token_type' => 'Bearer', 'scope' => 'https://www.googleapis.com/auth/drive.file']),
            'https://www.googleapis.com/drive/v3/about*' => Http::response(['user' => ['emailAddress' => 'mobisttech@gmail.com']]),
        ]);
        $manager = app(IntegrationManager::class);
        $start = $manager->connect($this->admin, 'google_drive');
        parse_str(parse_url($start['authorization_url'], PHP_URL_QUERY), $query);
        $this->assertSame('https://www.googleapis.com/auth/drive.file', $query['scope']);
        $status = $manager->callback($this->admin, 'google_drive', ['state' => $query['state'], 'code' => 'drive-code']);
        $this->assertTrue($gateway->configured);
        $this->assertTrue($gateway->validated);
        $this->assertSame('connected', $status['status']);
        $this->assertSame('mobisttech-drive:', $status['remote']);
        $settings = $manager->backupSettings($this->admin, ['schedule' => 'daily', 'retention_days' => 30]);
        $this->assertSame('daily', $settings['backup_schedule']);
        $this->assertSame('30 days', $settings['retention_policy']);
        $manager->disconnect($this->admin, 'google_drive');
        $this->assertTrue($gateway->disconnected);
        $this->assertSame(0, DB::table('backup_records')->count());
    }

    public function test_recovery_email_contains_single_use_https_link_and_never_a_password(): void
    {
        Http::fake(['https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'recovery-message'])]);
        DB::table('integration_connections')->where('provider', 'gmail')->update(['status' => 'connected', 'account' => 'mobisttech@gmail.com',
            'encrypted_credentials' => Crypt::encryptString(json_encode(['access_token' => 'recovery-access', 'refresh_token' => 'recovery-refresh',
                'expires_at' => now()->addHour()->format('Y-m-d H:i:s.u')], JSON_THROW_ON_ERROR))]);
        config(['identity.customer_origin' => 'https://mobisttech.com']);
        $customer = new CustomerAccount;
        $customer->forceFill(['name' => 'Customer', 'email' => 'customer@example.invalid', 'password' => Hash::make('NeverEmailThisPassword!')])->save();
        app(GmailRecovery::class)->send($customer, 'customer', 'single-use-synthetic-token');
        Http::assertSent(function (Request $request) {
            if ($request->url() !== 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send') {
                return false;
            }
            $raw = base64_decode(strtr($request['raw'], '-_', '+/'));

            return str_contains($raw, 'https://mobisttech.com/reset-password?') && str_contains($raw, 'single-use-synthetic-token')
                && ! str_contains($raw, 'NeverEmailThisPassword!');
        });
    }

    public function test_rclone_validation_proves_write_read_delete_and_backup_job_uses_backend_only(): void
    {
        $gateway = new class extends RcloneGateway
        {
            public array $commands = [];

            public ?string $backupRemote = null;

            public bool $backupWasEncrypted = false;

            public ?bool $backupUsedManagedConfig = null;

            protected function run(array $arguments, bool $managed): string
            {
                $this->commands[] = [$arguments[0], $managed];

                return $arguments[0] === 'cat' ? 'mobisttech-drive-validation' : '';
            }

            public function copyTo(string $localPath, string $remotePath, bool $managed = true): void
            {
                $this->backupRemote = $remotePath;
                $this->backupUsedManagedConfig = $managed;
                $content = file_get_contents($localPath);
                $this->backupWasEncrypted = ! str_contains($content, 'business_profiles') && ! str_contains($content, 'mobisttech@gmail.com');
            }
        };
        $this->assertTrue($gateway->validate());
        $this->assertSame(['copyto', 'cat', 'deletefile', 'deletefile'], array_column($gateway->commands, 0));

        $connection = DB::table('integration_connections')->where('provider', 'google_drive')->first();
        DB::table('integration_connections')->where('id', $connection->id)->update([
            'status' => 'connected',
            'configuration' => json_encode(['managed' => false], JSON_THROW_ON_ERROR),
        ]);
        $record = DB::table('backup_records')->insertGetId(['integration_connection_id' => $connection->id, 'scope' => 'business', 'trigger' => 'manual',
            'requested_by_type' => 'admin', 'requested_by_id' => $this->admin->id, 'status' => 'queued', 'filename' => 'pending.backup',
            'remote_status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
        (new CreateGoogleDriveBackup($record))->handle(app(BackupArchive::class), $gateway);
        $result = DB::table('backup_records')->where('id', $record)->first();
        $this->assertSame('completed', $result->status);
        $this->assertSame('uploaded', $result->remote_status);
        $this->assertNull($result->path);
        $this->assertTrue($gateway->backupWasEncrypted);
        $this->assertFalse($gateway->backupUsedManagedConfig);
        $this->assertStringStartsWith('mobiST Tech/Backups/testing/', $gateway->backupRemote);
    }
}
