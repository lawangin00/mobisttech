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
        $limitedDraft = $this->send($documentsClient, 'POST', '/internal/admin/platform/pos-config/documents/draft', [
            'settings' => ['invoice.default_output_format' => 'a4'],
        ])->assertOk();
        $limitedId = (int) $limitedDraft->json('data.id');
        $this->send($documentsClient, 'POST', '/internal/admin/platform/pos-config/revisions/'.$limitedId.'/publish')
            ->assertForbidden();
        $this->assertSame('draft', DB::table('pos_configuration_revisions')->where('id', $limitedId)->value('state'));
        $this->assertNull(DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));
        // P07: a documents-only Admin cannot invoke other POS domains directly.
        $this->send($documentsClient, 'POST', '/internal/admin/platform/pos-config/theme/preview', [
            'settings' => ['theme.primary' => '#008080'],
        ])->assertForbidden();
        $this->send($documentsClient, 'POST', '/internal/admin/platform/pos-config/branding/draft', [
            'settings' => ['branding.header_logo_media_id' => 0],
        ])->assertForbidden();
        $this->send($documentsClient, 'POST', '/internal/admin/platform/pos-config/branding/media', [
            'base64' => 'not-a-valid-image', 'extension' => 'png', 'original_name' => 'synthetic.png',
        ])->assertForbidden();
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
        [$editor, $editorOutlet] = $this->admin('pos-documents-editor@example.invalid', ['shops.enter', 'config.documents.manage']);
        $editorClient = $this->client();
        $this->login($editorClient, $editor->email)->assertOk();
        $this->selectOutlet($editorClient, $editorOutlet);
        $this->send($editorClient, 'POST', '/internal/admin/platform/pos-config/revisions/'.$first.'/rollback')
            ->assertForbidden();
        $this->assertSame('a4', DB::table('pos_settings')->where('key', 'invoice.default_output_format')->value('value'));

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

    public function test_software_admin_publication_and_rollback_preserve_public_draft_isolation(): void
    {
        // Public routes require a published Website mode; isolate Software draft visibility, not a disabled site.
        $modeRevision = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $modeRevision, 'published_at' => now(),
        ]);
        [$editor] = $this->admin('mt75-editor@example.invalid', ['website.content.manage'], 'A75');
        [$publisher] = $this->admin('mt75-publisher@example.invalid', ['website.publish'], 'B75');
        $editorClient = $this->client();
        $this->login($editorClient, $editor->email)->assertOk();
        $publisherClient = $this->client();
        $this->login($publisherClient, $publisher->email)->assertOk();
        // Saving a legal policy draft must not imply factual verification or owner approval.
        $policyDraftInput = ['content' => '<p>Synthetic unreviewed policy.</p>',
            'effective_date' => '2026-09-19', 'approval_state' => 'draft',
            'factual_review_state' => 'pending', 'unresolved_decisions' => []];
        $this->send($editorClient, 'POST', '/internal/admin/platform/policies/privacy/draft', $policyDraftInput)
            ->assertOk()->assertJsonPath('data.approval_state', 'draft')
            ->assertJsonPath('data.factual_review_state', 'pending');
        $this->send($editorClient, 'POST', '/internal/admin/platform/policies/privacy/draft',
            [...$policyDraftInput, 'approval_state' => 'owner_approved', 'factual_review_state' => 'verified'])
            ->assertForbidden();
        $input = $this->mt75SoftwareInput('Initial verified synthetic overview');
        $draft = $this->send($editorClient, 'POST', '/internal/admin/platform/software', $input)
            ->assertOk()->json('data');
        $revision = (int) $draft['id'];
        $productId = $draft['software_public_id'];
        $this->getJson('/api/v1/software/mt75-fixture')->assertNotFound();
        $this->send($editorClient, 'POST', '/internal/admin/platform/software/revisions/'.$revision.'/publish')
            ->assertForbidden();
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/revisions/'.$revision.'/publish')
            ->assertOk();
        $this->getJson('/api/v1/software/mt75-fixture')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Initial verified synthetic overview');
        $this->getJson('/api/v1/software/mt75-fixture/privacy')->assertOk()
            ->assertJsonPath('data.privacy', '<p>Synthetic test-only privacy content.</p>');
        $this->getJson('/api/v1/software/mt75-fixture/terms')->assertOk()
            ->assertJsonPath('data.terms', '<p>Synthetic test-only terms.</p>');
        $this->getJson('/api/v1/software/mt75-fixture/faq')->assertOk()
            ->assertJsonPath('data.faq.0.question', 'Test product?');
        $releaseInput = [
            'version' => '1.0.0', 'release_date' => '2026-09-19',
            'summary' => 'Synthetic signed-in release',
            'notes' => ['added' => ['Test-only feature']],
            'impact_review' => [
                'overview' => 'reviewed_no_change', 'privacy' => 'reviewed_no_change',
                'terms' => 'reviewed_no_change', 'faq' => 'reviewed_no_change',
                'system_requirements' => 'reviewed_no_change', 'support_guidance' => 'reviewed_no_change',
                'material' => [],
            ],
        ];
        $release = $this->send($editorClient, 'POST', '/internal/admin/platform/software/'.$productId.'/releases', $releaseInput)
            ->assertOk()->json('data');
        $adminView = collect($this->send($editorClient, 'GET', '/internal/admin/platform/data')->assertOk()->json('data.software'))
            ->firstWhere('id', $productId);
        $this->assertSame(['Test-only feature'], $adminView['releases'][0]['notes']['added']);
        $this->assertSame('reviewed_no_change', $adminView['releases'][0]['impact_review']['privacy']);
        $this->getJson('/api/v1/software/mt75-fixture/releases')->assertOk()->assertJsonCount(0, 'data.items');
        $releaseId = (int) DB::table('software_releases')->where('public_id', $release['public_id'])->value('id');
        $this->send($editorClient, 'POST', '/internal/admin/platform/software/releases/'.$releaseId.'/publish')->assertForbidden();
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/releases/'.$releaseId.'/publish')->assertOk();
        $this->getJson('/api/v1/software/mt75-fixture/releases')->assertOk()
            ->assertJsonPath('data.items.0.version', '1.0.0');
        $later = $this->send($editorClient, 'POST', '/internal/admin/platform/software/'.$productId.'/draft',
            $this->mt75SoftwareInput('Updated reviewed synthetic overview'))->assertOk()->json('data');
        $this->getJson('/api/v1/software/mt75-fixture')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Initial verified synthetic overview');
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/revisions/'.$later['id'].'/publish')
            ->assertOk();
        $this->getJson('/api/v1/software/mt75-fixture')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Updated reviewed synthetic overview');
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/revisions/'.$revision.'/rollback')
            ->assertOk();
        $this->getJson('/api/v1/software/mt75-fixture')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Initial verified synthetic overview');
        $this->assertSame(3, DB::table('software_product_revisions')
            ->where('software_product_id', DB::table('software_products')->where('public_id', $productId)->value('id'))
            ->count());
        // A second independent product must not inherit the first product's release or public state.
        $otherInput = [...$this->mt75SoftwareInput('Independent second overview'),
            'name' => 'MT75 Second Software', 'slug' => 'mt75-second-fixture'];
        $other = $this->send($editorClient, 'POST', '/internal/admin/platform/software', $otherInput)
            ->assertOk()->json('data');
        $this->getJson('/api/v1/software/mt75-second-fixture')->assertNotFound();
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/revisions/'.$other['id'].'/publish')
            ->assertOk();
        $this->getJson('/api/v1/software/mt75-second-fixture')->assertOk()
            ->assertJsonPath('data.overview.name', 'MT75 Second Software')
            ->assertJsonPath('data.overview.overview', 'Independent second overview');
        $this->getJson('/api/v1/software/mt75-second-fixture/releases')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/software/mt75-fixture/releases')->assertOk()
            ->assertJsonPath('data.items.0.version', '1.0.0');
        $this->send($editorClient, 'POST', '/internal/admin/platform/software/'.$other['software_public_id'].'/draft',
            [...$otherInput, 'slug' => 'mt75-fixture'])->assertUnprocessable();
        $this->send($editorClient, 'POST', '/internal/admin/platform/software/'.$other['software_public_id'].'/archive')
            ->assertForbidden();
        $this->send($publisherClient, 'POST', '/internal/admin/platform/software/'.$other['software_public_id'].'/archive')
            ->assertOk();
        $this->getJson('/api/v1/software/mt75-second-fixture')->assertNotFound();
        $this->getJson('/api/v1/software/mt75-fixture')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Initial verified synthetic overview');
        $this->assertSame(1, DB::table('software_product_revisions')
            ->where('software_product_id', DB::table('software_products')->where('public_id', $other['software_public_id'])->value('id'))
            ->count());
    }

    private function mt75SoftwareInput(string $overview): array
    {
        return [
            'name' => 'MT75 Synthetic Software', 'slug' => 'mt75-fixture',
            'summary' => 'Synthetic authenticated publication regression', 'overview' => $overview,
            'features' => [['title' => 'Synthetic feature', 'description' => 'No real customer data']],
            'platforms' => ['Windows 11 x64'], 'system_requirements' => '<p>Synthetic platform.</p>',
            'limitations' => ['Not an approved release'],
            'support' => ['channel' => 'support'], 'cta' => ['type' => 'contact'],
            'privacy' => '<p>Synthetic test-only privacy content.</p>',
            'terms' => '<p>Synthetic test-only terms.</p>',
            'faq' => [['question' => 'Test product?', 'answer' => '<p>Yes, test-only.</p>']],
            'seo_title' => 'MT75 Synthetic Software', 'seo_description' => 'Synthetic fixture',
            'sitemap' => false,
        ];
    }

    private function platformPermissions(): array
    {
        return [
            'shops.enter',
            'config.documents.manage', 'config.theme.manage', 'config.branding.manage', 'config.publish', 'config.payments.manage',
            'config.promotions.manage', 'config.loyalty.manage',
            'admin.business-profile.manage', 'admin.integrations.manage',
            'website.content.manage', 'website.publish', 'website.settings.manage', 'website.mode.preview', 'website.mode.publish',
            'website.media.manage', 'website.theme.manage', 'website.branding.manage', 'website.navigation.manage', 'website.seo.manage',
            'team-members.view', 'team-members.manage', 'team-members.roles.manage', 'team-members.full-access.assign',
        ];
    }

    private function admin(string $email, array $permissions, ?string $fixtureOutletCode = null): array
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
            'outlet_code' => $fixtureOutletCode ?? (string) random_int(100, 999),
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
