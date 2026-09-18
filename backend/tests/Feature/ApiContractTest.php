<?php

namespace Tests\Feature;

use App\Addendum\WebsiteCapabilities;
use App\Cms\WebsiteCms;
use App\Models\CustomerAccount;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
    }

    public function test_public_catalogue_is_bounded_fresh_cacheable_and_mode_aware(): void
    {
        $this->publishMode('hybrid', 1);
        $first = $this->listedProduct('alpha-phone');
        $second = $this->listedProduct('beta-phone');
        $this->acquire($first);
        $this->acquire($second);
        $page = $this->getJson('/api/v1/catalogue/products?limit=1')->assertOk()
            ->assertJsonPath('contract', 'catalogue-page.v1')
            ->assertJsonPath('data.page.limit', 1)
            ->assertJsonPath('data.page.has_more', true);
        $this->assertNotNull($page->json('data.page.next_cursor'));
        $this->assertArrayNotHasKey('purchase_price', $page->json('data.items.0'));
        $this->getJson('/api/v1/catalogue/products?q=ock')->assertOk()
            ->assertJsonCount(2, 'data.items');
        $etag = $page->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)->get('/api/v1/catalogue/products?limit=1')->assertStatus(304);

        $detail = $this->getJson('/api/v1/catalogue/products/alpha-phone')->assertOk();
        $this->assertSame(1, $detail->json('data.availability.quantity'));
        $this->assertSame('standard', $detail->json('data.variants.0.key'));
        $this->assertSame(1, $detail->json('data.variants.0.availability.quantity'));
        $variantJson = json_encode($detail->json('data.variants'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('imei', strtolower($variantJson));
        $this->assertStringNotContainsString('unit_no', strtolower($variantJson));
        $this->acquire($first);
        $this->getJson('/api/v1/catalogue/products/alpha-phone')->assertOk()
            ->assertJsonPath('data.availability.quantity', 2);
        $this->getJson('/api/v1/catalogue/products?limit=25')->assertStatus(422)
            ->assertJsonPath('error.code', 'api_422');

        $this->publishMode('digital_only', 2);
        $this->getJson('/api/v1/catalogue/products')->assertNotFound()
            ->assertJsonPath('error.code', 'api_404');
    }

    public function test_website_profile_exposes_authoritative_mode_navigation_cta_and_seo_plan(): void
    {
        $this->publishMode('hybrid', 1);
        $profile = $this->getJson('/api/v1/website-profile')->assertOk()
            ->assertJsonPath('contract', 'website-profile.v1')
            ->assertJsonPath('data.mode', 'hybrid')
            ->assertJsonPath('data.capabilities.commerce', true)
            ->assertJsonPath('data.capabilities.digital', true)
            ->assertJsonPath('data.copy_variant', 'hybrid')
            ->assertJsonPath('data.seo_sitemap.historical_routes_indexable', false)
            ->assertJsonPath('data.seo_sitemap.inactive_capabilities_indexable', false);
        $this->assertContains('products', $profile->json('data.routes'));
        $this->assertContains('services', $profile->json('data.routes'));
        $this->assertContains('browse_products', $profile->json('data.ctas'));
        $this->assertContains('service_enquiry', $profile->json('data.ctas'));

        $this->publishMode('digital_only', 2);
        $digital = $this->getJson('/api/v1/website-profile')->assertOk();
        $this->assertNotContains('products', $digital->json('data.routes'));
        $this->assertNotContains('browse_products', $digital->json('data.ctas'));
        $this->assertContains('products', $digital->json('data.seo_sitemap.excluded_routes'));
    }

    public function test_software_api_exposes_published_revision_and_release_only(): void
    {
        $this->publishMode('hybrid', 1);
        $cms = app(WebsiteCms::class);
        $draft = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('Published overview'));
        $cms->publishSoftware($this->actor, $draft['id']);
        $later = $cms->saveSoftwareDraft($this->actor, $draft['software_public_id'], $this->softwareInput('Private draft overview'));
        $release = $cms->saveReleaseDraft($this->actor, $draft['software_public_id'], [
            'version' => '1.0.0', 'release_date' => '2026-09-18', 'summary' => 'Initial public release',
            'notes' => ['added' => ['Public API release']], 'impact_review' => $this->releaseImpact(),
        ]);
        $releaseId = DB::table('software_releases')->where('public_id', $release['public_id'])->value('id');
        $cms->publishRelease($this->actor, (int) $releaseId);

        $overview = $this->getJson('/api/v1/software/mobist-pos')->assertOk()
            ->assertJsonPath('contract', 'software-overview.v1')
            ->assertJsonPath('data.overview.overview', 'Published overview')
            ->assertJsonPath('data.current_version', '1.0.0');
        $json = json_encode($overview->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Private draft overview', $json);
        $this->assertStringNotContainsString('impact_review', $json);
        $this->assertStringNotContainsString('state', $json);
        $this->getJson('/api/v1/software/mobist-pos/releases')->assertOk()
            ->assertJsonPath('data.items.0.version', '1.0.0');
        $this->assertSame('draft', DB::table('software_product_revisions')->where('id', $later['id'])->value('state'));
    }

    public function test_customer_api_uses_real_session_csrf_ownership_and_historical_exception(): void
    {
        $this->publishMode('hybrid', 1);
        $product = $this->listedProduct('session-phone');
        $this->acquire($product, 2);
        $customer = $this->customer('api-owner@example.invalid', '03001112222');
        $client = $this->client();
        $this->login($client, $customer->email)->assertOk();
        $quote = $this->send($client, 'POST', '/api/v1/cart/quote', [
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ])->assertOk();
        $cacheControl = (string) $quote->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('200.02', $quote->json('data.subtotal'));

        $payload = ['customer_name' => $customer->name, 'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email, 'city' => 'Karachi', 'delivery_address' => 'Synthetic address',
            'gateway' => 'cod', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]];
        $created = $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => 'api-order-'.Str::uuid()])
            ->assertCreated()->assertJsonPath('contract', 'order-create.v1');
        $orderId = $created->json('data.order_id');

        $this->publishMode('digital_only', 2);
        $this->send($client, 'GET', '/api/v1/orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.id', $orderId);
        $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => 'blocked-'.Str::uuid()])
            ->assertNotFound()->assertJsonPath('error.code', 'api_404');

        $other = $this->customer('api-other@example.invalid', '03001112223');
        $otherClient = $this->client();
        $this->login($otherClient, $other->email)->assertOk();
        $this->send($otherClient, 'GET', '/api/v1/orders/'.$orderId)->assertNotFound()
            ->assertJsonPath('error.code', 'api_404');
    }

    public function test_public_rate_limit_and_error_contract_are_enforced(): void
    {
        $this->publishMode('hybrid', 1);
        $this->getJson('/api/v1/catalogue/products?after=not-a-cursor')->assertStatus(422)
            ->assertJsonPath('error.code', 'api_422');
        for ($i = 0; $i < 119; $i++) {
            $this->getJson('/api/v1/website-profile')->assertOk();
        }
        $this->getJson('/api/v1/website-profile')->assertStatus(429);
    }

    private function listedProduct(string $slug)
    {
        $product = $this->product();
        DB::table('product_listings')->insert([
            'external_source' => 'pos', 'external_id' => 'api-'.$product->id, 'slug' => $slug,
            'name' => $product->name, 'category' => $product->category, 'is_online' => true,
            'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $product;
    }

    private function customer(string $email, string $mobile): CustomerAccount
    {
        $customer = new CustomerAccount;
        $customer->forceFill([
            'name' => 'Synthetic API customer', 'email' => $email, 'mobile' => $mobile,
            'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1, 'is_admin' => false,
        ])->save();

        return $customer;
    }

    private function softwareInput(string $overview): array
    {
        return [
            'name' => 'mobiST POS', 'slug' => 'mobist-pos', 'summary' => 'Synthetic public product summary',
            'overview' => $overview,
            'features' => [['title' => 'Inventory', 'description' => 'Shared backend authority']],
            'platforms' => ['Windows 10 x64', 'Windows 11 x64'],
            'system_requirements' => '<p>Supported Windows environment.</p>',
            'limitations' => ['External providers require configuration.'],
            'support' => ['channel' => 'support'], 'cta' => ['type' => 'contact'],
            'privacy' => '<p>Published privacy content.</p>', 'terms' => '<p>Published terms content.</p>',
            'faq' => [['question' => 'Is this published?', 'answer' => '<p>Yes.</p>']],
            'seo_title' => 'mobiST POS', 'seo_description' => 'Synthetic SEO', 'sitemap' => true,
        ];
    }

    private function releaseImpact(): array
    {
        return [
            'overview' => 'reviewed_no_change', 'privacy' => 'reviewed_no_change',
            'terms' => 'reviewed_no_change', 'faq' => 'reviewed_no_change',
            'system_requirements' => 'reviewed_no_change', 'support_guidance' => 'reviewed_no_change',
            'material' => [],
        ];
    }

    private function publishMode(string $mode, int $version): void
    {
        $commerce = in_array($mode, ['hybrid', 'commerce_only'], true);
        $digital = in_array($mode, ['hybrid', 'digital_only'], true);
        $routes = ['home', 'about', 'contact', 'policies', 'software'];
        if ($digital) {
            $routes = [...$routes, 'services', 'case-studies', 'enquiry', 'consultation'];
        }
        if ($commerce) {
            $routes = [...$routes, 'products', 'categories', 'compare', 'cart', 'checkout'];
        }
        $operations = [];
        foreach (WebsiteCapabilities::PUBLIC_OPERATIONS as $operation => $capability) {
            if (($capability === 'digital' && $digital) || ($capability === 'commerce' && $commerce)) {
                $operations[] = $operation;
            }
        }
        $scopes = ['common'];
        if ($digital) {
            $scopes[] = 'digital';
        }
        if ($commerce) {
            $scopes[] = 'commerce';
        }
        $historical = [];
        foreach (WebsiteCapabilities::HISTORICAL_RESOURCES as $resource) {
            $historical[$resource] = ['allowed' => true, 'authenticated_only' => true, 'indexable' => false];
        }
        $snapshot = [
            'contract' => 'website-mode.v1', 'mode' => $mode,
            'capabilities' => ['digital' => $digital, 'commerce' => $commerce],
            'content_scopes' => $scopes, 'copy_variant' => $mode, 'routes' => $routes,
            'api_operations' => $operations,
            'ctas' => [...($digital ? ['service_enquiry', 'consultation'] : []), ...($commerce ? ['browse_products', 'add_to_cart', 'checkout'] : [])],
            'seo_sitemap' => [
                'discoverable_routes' => $routes,
                'excluded_routes' => [
                    ...($digital ? [] : ['services', 'case-studies', 'enquiry', 'consultation']),
                    ...($commerce ? [] : ['products', 'categories', 'compare', 'cart', 'checkout']),
                ],
                'historical_routes_indexable' => false, 'inactive_capabilities_indexable' => false,
            ],
            'historical_access' => $historical,
        ];
        $revision = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => $version, 'state' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => $mode, 'version' => $version, 'revision_id' => $revision, 'published_at' => now(),
        ]);
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic API client'];
        $response = $this->send($client, 'GET', '/api/v1/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/api/v1/auth/login', [
            'email' => $email, 'password' => 'SyntheticPass123!',
        ]);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $extraServer = [])
    {
        $cookieName = str_starts_with($uri, '/internal/admin/') ? 'XSRF-TOKEN-admin' : 'XSRF-TOKEN';
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'], ...$extraServer];
        if ($csrf && isset($client['tokens'][$cookieName])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens'][$cookieName];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }
}
