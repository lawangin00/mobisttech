<?php

namespace Tests\Feature;

use App\Addendum\WebsiteCapabilities;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class WebsiteModePublicationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_edit_preview_publish_permissions_and_mode_impact_are_separate(): void
    {
        $this->publishScopedCmsFixtures();
        $modes = app(WebsiteModePublication::class);
        $editor = $this->adminWith(['website.settings.manage']);
        $previewer = $this->adminWith(['website.mode.preview']);
        $publisher = $this->adminWith(['website.mode.publish']);
        $draft = $modes->saveDraft($editor, 'digital_only');
        $this->reject(fn () => $modes->preview($editor, $draft['id']));
        $this->reject(fn () => $modes->saveDraft($previewer, 'hybrid'));
        $this->reject(fn () => $modes->publish($previewer, $draft['id']));

        $preview = $modes->preview($previewer, $draft['id']);
        $this->assertNull($preview['from_mode']);
        $this->assertSame('digital_only', $preview['to_mode']);
        $this->assertFalse($preview['data_deletion']);
        $this->assertContains('digital-page', $preview['pages']['becomes_visible']);
        $this->assertContains('digital-only-page', $preview['pages']['becomes_visible']);
        $this->assertContains('digital-nav', $preview['navigation']['becomes_visible']);
        $this->assertContains('checkout', $preview['seo_sitemap']['excluded_routes']);
        $this->assertTrue($preview['historical_access']['order.status']['allowed']);

        $published = $modes->publish($publisher, $draft['id']);
        $this->assertSame('digital_only', $published['mode']);
        $this->assertFalse($published['data_deleted']);
        $this->assertSame('digital_only', DB::table('website_operating_profiles')->where('id', 1)->value('mode'));
        $this->assertSame('published', DB::table('site_configuration_revisions')->where('id', $draft['id'])->value('state'));
    }

    public function test_switch_blocks_inactive_creation_but_preserves_history_and_cache_freshness(): void
    {
        $modes = app(WebsiteModePublication::class);
        $capabilities = app(WebsiteCapabilities::class);
        $first = $modes->saveDraft($this->actor, 'digital_only');
        $modes->publish($this->actor, $first['id']);
        $this->assertTrue($capabilities->allowsScope('common'));
        $this->assertTrue($capabilities->allowsScope('digital'));
        $this->assertTrue($capabilities->allowsScope('digital_only'));
        $this->assertFalse($capabilities->allowsScope('commerce'));
        DB::transaction(fn () => $this->assertSame('digital_only', $capabilities->assertCreationAllowed('service.read')['mode']));
        $this->reject(fn () => DB::transaction(fn () => $capabilities->assertCreationAllowed('checkout.create')));

        $order = DB::table('orders')->insertGetId([
            'order_number' => 'MODE-ORDER-'.Str::uuid(), 'order_type' => 'mobile',
            'customer_name' => 'Historical Customer', 'customer_mobile' => '03000000000',
            'public_id' => (string) Str::uuid(),
        ]);
        DB::table('invoices')->insert([
            'outlet_id' => $this->outlet->id, 'total_bill' => '10.00', 'final_bill' => '10.00',
            'order_id' => $order, 'public_id' => (string) Str::uuid(),
        ]);
        DB::table('project_quotes')->insert([
            'reference' => 'MODE-QUOTE-'.Str::uuid(), 'client_name' => 'Historical Client',
            'customer_mobile' => '03000000001', 'title' => 'Historical Project', 'amount' => '100.00',
            'public_id' => (string) Str::uuid(),
        ]);
        $before = [DB::table('orders')->count(), DB::table('invoices')->count(), DB::table('project_quotes')->count()];
        $cachedDigital = $modes->publicProfile();
        $this->assertSame('digital_only', $cachedDigital['mode']);
        $second = $modes->saveDraft($this->actor, 'commerce_only');
        $preview = $modes->preview($this->actor, $second['id']);
        $this->assertSame('digital_only', $preview['from_mode']);
        $this->assertContains('catalogue', $preview['affected_cache_domains']);
        $modes->publish($this->actor, $second['id']);

        $after = [DB::table('orders')->count(), DB::table('invoices')->count(), DB::table('project_quotes')->count()];
        $this->assertSame($before, $after);
        $freshCommerce = $modes->publicProfile();
        $this->assertSame('commerce_only', $freshCommerce['mode']);
        $this->assertGreaterThan($cachedDigital['version'], $freshCommerce['version']);
        $this->assertTrue($capabilities->allowsScope('commerce'));
        $this->assertFalse($capabilities->allowsScope('digital'));
        DB::transaction(fn () => $this->assertSame('commerce_only', $capabilities->assertCreationAllowed('checkout.create')['mode']));
        $this->reject(fn () => DB::transaction(fn () => $capabilities->assertCreationAllowed('enquiry.create')));
        foreach (WebsiteCapabilities::HISTORICAL_RESOURCES as $resource) {
            $this->assertSame('commerce_only', $capabilities->assertHistoricalAllowed($resource)['mode']);
        }
        $this->reject(fn () => $capabilities->assertHistoricalAllowed('catalogue.read'));
        $this->assertGreaterThan(0, (int) DB::table('publication_versions')->where('domain', 'website.mode')->value('version'));
        $this->assertGreaterThan(0, (int) DB::table('publication_versions')->where('domain', 'catalogue')->value('version'));
    }

    public function test_one_click_rollback_creates_new_published_revision_and_revalidates(): void
    {
        $modes = app(WebsiteModePublication::class);
        $digital = $modes->saveDraft($this->actor, 'digital_only');
        $modes->publish($this->actor, $digital['id']);
        $hybrid = $modes->saveDraft($this->actor, 'hybrid');
        $modes->publish($this->actor, $hybrid['id']);
        $this->assertSame('superseded', DB::table('site_configuration_revisions')->where('id', $digital['id'])->value('state'));

        $rolled = $modes->rollback($this->actor, $digital['id']);
        $this->assertSame('digital_only', $rolled['mode']);
        $this->assertGreaterThan($hybrid['version'], $rolled['version']);
        $currentId = (int) DB::table('website_operating_profiles')->where('id', 1)->value('revision_id');
        $this->assertNotSame($digital['id'], $currentId);
        $this->assertSame($digital['id'], (int) DB::table('site_configuration_revisions')->where('id', $currentId)->value('restored_from_revision_id'));
        $this->assertSame('published', DB::table('site_configuration_revisions')->where('id', $currentId)->value('state'));
        $this->assertSame('superseded', DB::table('site_configuration_revisions')->where('id', $hybrid['id'])->value('state'));
        $this->assertSame('digital_only', $modes->publicProfile()['mode']);
        $this->assertSame(3, DB::table('domain_events')->where('aggregate_type', 'website_mode')->count());
        $this->assertSame(3, DB::table('identity_audit_events')->where('action', 'website_mode_published')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('action', 'website_mode_rolled_back')->count());
        $this->reject(fn () => $modes->publish($this->actor, $hybrid['id']));
    }

    private function publishScopedCmsFixtures(): void
    {
        $cms = app(WebsiteCms::class);
        foreach ([
            ['common-page', 'common'], ['digital-page', 'digital'], ['commerce-page', 'commerce'],
            ['digital-only-page', 'digital_only'], ['hybrid-page', 'hybrid'], ['commerce-only-page', 'commerce_only'],
        ] as [$slug, $scope]) {
            $draft = $cms->savePageDraft($this->actor, null, [
                'title' => $slug, 'slug' => $slug, 'content' => '<p>'.$slug.'</p>', 'capability_scope' => $scope,
            ]);
            $cms->publishPage($this->actor, $draft['id']);
        }
        $presentation = $cms->savePresentationDraft($this->actor, [
            'navigation' => [
                ['key' => 'common-nav', 'label' => 'Common', 'destination_type' => 'route', 'destination_key' => 'home', 'capability_scope' => 'common'],
                ['key' => 'digital-nav', 'label' => 'Digital', 'destination_type' => 'route', 'destination_key' => 'services', 'capability_scope' => 'digital'],
                ['key' => 'commerce-nav', 'label' => 'Commerce', 'destination_type' => 'route', 'destination_key' => 'products', 'capability_scope' => 'commerce'],
                ['key' => 'digital-only-nav', 'label' => 'Digital only', 'destination_type' => 'route', 'destination_key' => 'digital-home', 'capability_scope' => 'digital_only'],
                ['key' => 'commerce-only-nav', 'label' => 'Commerce only', 'destination_type' => 'route', 'destination_key' => 'commerce-home', 'capability_scope' => 'commerce_only'],
            ],
        ]);
        $cms->publishPresentation($this->actor, $presentation['id']);
    }

    private function adminWith(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'Mode actor '.Str::uuid(), 'email' => Str::uuid().'@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => $permissions,
        ])->save();

        return $admin;
    }
}
