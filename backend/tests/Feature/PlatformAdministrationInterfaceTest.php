<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use App\Payments\PosPaymentOperations;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformAdministrationInterfaceTest extends TestCase
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

    public function test_platform_data_is_permission_scoped_and_never_projects_provider_secrets(): void
    {
        [$admin, $outlet] = $this->admin('platform-admin@example.invalid', $this->platformPermissions());
        DB::table('integration_connections')->where('provider', 'gmail')->update([
            'status' => 'connected',
            'account' => 'mobisttech@gmail.com',
            'encrypted_credentials' => Crypt::encryptString(json_encode([
                'access_token' => 'PLATFORM-ACCESS-SECRET',
                'refresh_token' => 'PLATFORM-REFRESH-SECRET',
            ], JSON_THROW_ON_ERROR)),
        ]);
        app(PosPaymentOperations::class)->createDestination($admin, $outlet, 'mt44-secret-destination', [
            'method' => 'bank_transfer',
            'display_name' => 'MT44 Bank',
            'provider_label' => 'Synthetic provider',
            'masked_identifier' => '****4400',
            'active' => true,
            'internal_notes' => 'DESTINATION-INTERNAL-SECRET',
        ]);

        $client = $this->client();
        $this->login($client, $admin->email)->assertOk();
        $this->selectOutlet($client, $outlet);
        $response = $this->send($client, 'GET', '/internal/admin/platform/data')->assertOk();

        $response->assertJsonPath('data.business_profile.business_name', 'mobiST Technologies')
            ->assertJsonPath('data.payment_destinations.0.masked_identifier', '****4400');
        $this->assertSame('thermal', $response->json('data.pos_configuration.domains.documents.values')['invoice.default_output_format']);
        $payload = $response->getContent();
        $this->assertStringNotContainsString('PLATFORM-ACCESS-SECRET', $payload);
        $this->assertStringNotContainsString('PLATFORM-REFRESH-SECRET', $payload);
        $this->assertStringNotContainsString('DESTINATION-INTERNAL-SECRET', $payload);
        $this->assertStringNotContainsString('encrypted_credentials', $payload);

        [$sender, $senderOutlet] = $this->admin('document-sender@example.invalid', ['shops.enter', 'shop.documents.send']);
        $senderClient = $this->client();
        $this->login($senderClient, $sender->email)->assertOk();
        $this->selectOutlet($senderClient, $senderOutlet);
        $this->send($senderClient, 'GET', '/internal/admin/platform/data')->assertForbidden();

        [$documentsOnly, $documentsOutlet] = $this->admin('documents-only@example.invalid', ['shops.enter', 'config.documents.manage']);
        $documentsClient = $this->client();
        $this->login($documentsClient, $documentsOnly->email)->assertOk();
        $this->selectOutlet($documentsClient, $documentsOutlet);
        $scoped = $this->send($documentsClient, 'GET', '/internal/admin/platform/data')->assertOk()->json('data');
        $this->assertArrayHasKey('documents', $scoped['pos_configuration']['domains']);
        $this->assertArrayNotHasKey('theme', $scoped['pos_configuration']['domains']);
        $this->assertArrayNotHasKey('branding', $scoped['pos_configuration']['domains']);
        $this->assertSame([], $scoped['payment_destinations']);
        $this->assertSame([], $scoped['integrations']);
    }

    public function test_pos_configuration_drafts_are_isolated_and_publish_rollback_refreshes_runtime_cache(): void
    {
        [$admin, $outlet] = $this->admin('pos-config-admin@example.invalid', $this->platformPermissions());
        $client = $this->client();
        $this->login($client, $admin->email)->assertOk();
        $this->selectOutlet($client, $outlet);

        $draft = $this->send($client, 'POST', '/internal/admin/platform/pos-config/documents/draft', [
            'settings' => ['invoice.default_output_format' => 'a4'],
        ])->assertOk();
        $first = (int) $draft->json('data.id');
        $this->assertNull(DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));
        $beforePublish = $this->send($client, 'GET', '/internal/admin/platform/data')->assertOk();
        $this->assertSame('thermal', $beforePublish->json('data.pos_configuration.domains.documents.values')['invoice.default_output_format']);

        $this->send($client, 'POST', '/internal/admin/platform/pos-config/revisions/'.$first.'/publish')->assertOk()
            ->assertJsonPath('data.state', 'published');
        $this->assertSame('a4', DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));
        $afterPublish = $this->send($client, 'GET', '/internal/admin/platform/data')->assertOk();
        $this->assertSame('a4', $afterPublish->json('data.pos_configuration.domains.documents.values')['invoice.default_output_format']);

        $second = (int) $this->send($client, 'POST', '/internal/admin/platform/pos-config/documents/draft', [
            'settings' => ['invoice.default_output_format' => 'thermal'],
        ])->assertOk()->json('data.id');
        $this->send($client, 'POST', '/internal/admin/platform/pos-config/revisions/'.$second.'/publish')->assertOk();
        $this->assertSame('superseded', DB::table('pos_configuration_revisions')->where('id', $first)->value('state'));
        $this->assertSame('thermal', DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));

        $rollback = $this->send($client, 'POST', '/internal/admin/platform/pos-config/revisions/'.$first.'/rollback')->assertOk();
        $this->assertSame('published', $rollback->json('data.state'));
        $this->assertSame($first, (int) $rollback->json('data.restored_from_revision_id'));
        $this->assertSame('a4', DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));

        $this->send($client, 'POST', '/internal/admin/platform/pos-config/theme/preview', [
            'settings' => ['theme.text' => '#ffffff', 'theme.surface' => '#ffffff'],
        ])->assertUnprocessable();

        $this->send($client, 'POST', '/internal/admin/platform/pos-config/documents/preview', [
            'settings' => ['invented.setting' => 'x'],
        ])->assertUnprocessable();
    }

    public function test_branding_media_role_constraints_and_business_profile_recent_auth_are_enforced(): void
    {
        Carbon::setTestNow('2026-09-18 13:00:00');
        [$admin, $outlet] = $this->admin('branding-admin@example.invalid', $this->platformPermissions());
        $client = $this->client();
        $this->login($client, $admin->email)->assertOk();
        $this->selectOutlet($client, $outlet);

        $tinyPng = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl2nS0AAAAASUVORK5CYII=';
        $media = $this->send($client, 'POST', '/internal/admin/platform/pos-config/branding/media', [
            'base64' => $tinyPng,
            'extension' => 'png',
            'original_name' => 'tiny.png',
            'alt_text' => 'Synthetic tiny branding image',
        ])->assertOk();
        $mediaId = (int) $media->json('data.id');
        $this->assertDatabaseHas('pos_media_assets', ['id' => $mediaId, 'mime_type' => 'image/png', 'status' => 'active']);

        $this->send($client, 'POST', '/internal/admin/platform/pos-config/branding/preview', [
            'settings' => ['branding.app_icon_media_id' => $mediaId],
        ])->assertUnprocessable();

        $this->send($client, 'POST', '/internal/admin/platform/pos-config/branding/media', [
            'base64' => 'not-base64',
            'extension' => 'png',
            'original_name' => 'invalid.png',
        ])->assertUnprocessable();

        Carbon::setTestNow('2026-09-18 13:11:00');
        $profile = [
            'business_name' => 'mobiST Technologies',
            'business_email' => 'mobisttech@gmail.com',
            'public_website' => 'https://mobisttech.com',
        ];
        $this->send($client, 'PATCH', '/internal/admin/platform/business-profile', $profile)->assertForbidden();
        $this->send($client, 'POST', '/internal/admin/auth/confirm-password', ['password' => self::PASSWORD])->assertOk();
        $this->send($client, 'PATCH', '/internal/admin/platform/business-profile', $profile)->assertOk()
            ->assertJsonPath('data.business_name', 'mobiST Technologies');

        $this->send($client, 'PATCH', '/internal/admin/platform/business-profile', [
            ...$profile,
            'business_name' => 'Invented Brand',
        ])->assertUnprocessable();
    }

    private function platformPermissions(): array
    {
        return [
            'shops.enter',
            'config.documents.manage', 'config.theme.manage', 'config.branding.manage', 'config.payments.manage',
            'config.promotions.manage', 'config.loyalty.manage',
            'admin.business-profile.manage', 'admin.integrations.manage',
            'website.content.manage', 'website.publish', 'website.settings.manage', 'website.mode.preview', 'website.mode.publish',
            'website.media.manage', 'website.theme.manage', 'website.branding.manage', 'website.navigation.manage', 'website.seo.manage',
            'team-members.view', 'team-members.manage', 'team-members.roles.manage', 'team-members.full-access.assign',
        ];
    }

    private function admin(string $email, array $permissions): array
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Synthetic Platform Admin',
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();
        $outlet = new Outlet;
        $outlet->forceFill([
            'name' => 'MT44 Outlet '.Str::random(5),
            'public_id' => (string) Str::uuid(),
            'outlet_code' => (string) random_int(100, 999),
        ])->save();
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.4 browser'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $email,
            'password' => self::PASSWORD,
        ]);
    }

    private function selectOutlet(array &$client, Outlet $outlet): void
    {
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $outlet->public_id,
        ])->assertOk();
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $extraServer = [])
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
            ...$extraServer,
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
}
