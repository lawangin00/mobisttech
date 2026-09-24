<?php

namespace Tests\Feature;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class WebsiteCmsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        Storage::fake('public');
        Storage::fake('local');
    }

    public function test_presentation_media_and_pages_are_versioned_safe_and_do_not_leak_drafts(): void
    {
        $cms = app(WebsiteCms::class);
        $first = $cms->savePresentationDraft($this->actor, [
            'theme' => ['accent' => '#111111'],
            'navigation' => [['key' => 'home', 'label' => 'Home', 'destination_type' => 'route', 'destination_key' => 'home']],
        ]);
        $this->assertNull(DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'));
        $cms->publishPresentation($this->actor, $first['id']);
        $this->assertSame(['accent' => '#111111'], json_decode((string) DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'), true));
        $second = $cms->savePresentationDraft($this->actor, ['theme' => ['accent' => '#222222']]);
        $this->assertSame(['accent' => '#111111'], json_decode((string) DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'), true));
        $cms->publishPresentation($this->actor, $second['id']);
        $this->assertSame(['accent' => '#222222'], json_decode((string) DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'), true));
        $cms->rollbackPresentation($this->actor, $first['id']);
        $this->assertSame(['accent' => '#111111'], json_decode((string) DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'), true));

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=');
        $media = $cms->registerMedia($this->actor, ['bytes' => $png, 'extension' => 'png', 'original_name' => 'pixel.png', 'alt_text' => 'Pixel']);
        Storage::disk('local')->assertExists($media['path']);
        Storage::disk('public')->assertMissing($media['path']);
        $this->assertSame(hash('sha256', $png), $media['sha256']);
        $this->reject(fn () => $cms->registerMedia($this->actor, ['bytes' => $png, 'extension' => 'jpg', 'original_name' => 'spoof.jpg']));

        DB::table('digital_services')->insert(['slug' => 'web-development', 'name' => 'Web Development', 'short_description' => 'Synthetic service']);
        $draft = $cms->savePageDraft($this->actor, null, [
            'title' => 'Web Development', 'slug' => 'web-development-services', 'content' => '<p>Original service page</p>',
            'content_purpose' => 'service_landing', 'capability_scope' => 'digital', 'service_slugs' => ['web-development'],
            'social_image_media_id' => $media['id'], 'structured_content' => ['hero' => 'Build safely'],
        ]);
        $this->reject(fn () => $cms->publicPage('web-development-services'));
        $cms->publishPage($this->actor, $draft['id']);
        $public = $cms->publicPage('web-development-services');
        $this->assertSame('<p>Original service page</p>', $public['snapshot']['content']);
        $this->assertSame(['web-development'], $public['snapshot']['service_slugs']);

        $later = $cms->savePageDraft($this->actor, $draft['page_public_id'], [
            'title' => 'Web Development', 'slug' => 'web-development-services', 'content' => '<p>Private draft change</p>',
            'content_purpose' => 'service_landing', 'capability_scope' => 'digital', 'service_slugs' => ['web-development'],
            'structured_content' => ['hero' => 'Changed privately'],
        ]);
        $this->assertSame('<p>Original service page</p>', $cms->publicPage('web-development-services')['snapshot']['content']);
        $cms->publishPage($this->actor, $later['id']);
        $this->assertSame('<p>Private draft change</p>', $cms->publicPage('web-development-services')['snapshot']['content']);
        $cms->rollbackPage($this->actor, $draft['id']);
        $this->assertSame('<p>Original service page</p>', $cms->publicPage('web-development-services')['snapshot']['content']);

        $this->reject(fn () => $cms->savePageDraft($this->actor, null, ['title' => 'Bad', 'slug' => 'checkout', 'content' => 'No']));
        $this->reject(fn () => $cms->savePageDraft($this->actor, null, ['title' => 'Bad policy', 'slug' => 'privacy-policy', 'content' => 'No']));
        $this->reject(fn () => $cms->savePageDraft($this->actor, null, [
            'title' => 'No consent', 'slug' => 'client-story', 'content' => 'No', 'content_purpose' => 'digital_testimonial',
            'structured_content' => ['consent_confirmed' => false],
        ]));
        $this->reject(fn () => $cms->savePageDraft($this->actor, null, ['title' => 'Unsafe', 'slug' => 'unsafe-copy', 'content' => '<script>alert(1)</script>']));
    }

    public function test_global_seo_requires_distinct_permission_safe_canonical_and_publish_before_public_cache(): void
    {
        // Repeated disposable DB transactions can reuse version numbers; isolate derived cache per test.
        config(['infrastructure.derived_cache_store' => 'array']);
        $mode = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $mode, 'published_at' => now(),
        ]);
        $cms = app(WebsiteCms::class);
        $editor = new Admin;
        $editor->forceFill(['name' => 'SEO only', 'email' => 'w06-seo-only@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['website.seo.manage']])->save();
        $this->reject(fn () => $cms->savePresentationDraft($editor, ['navigation' => []]));
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['seo' => ['canonical_url' => 'https://other.example/']]));
        $safeText = $cms->savePresentationDraft($this->actor, ['seo' => ['title' => '<b>Unsafe</b>']]);
        $this->assertSame('Unsafe', $safeText['snapshot']['seo']['title']);
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['seo' => ['secret' => 'not public']]));
        $this->assertSame([], $this->getJson('/api/v1/content')->assertOk()->json('data.seo'));
        $draft = $cms->savePresentationDraft($editor, ['seo' => [
            'title' => 'W06 Global SEO Verified', 'description' => 'W06 visible only after publish',
            'social_title' => 'W06 social heading', 'canonical_url' => '/',
        ]]);
        $this->assertSame([], $this->getJson('/api/v1/content')->assertOk()->json('data.seo'));
        $this->reject(fn () => $cms->publishPresentation($editor, $draft['id']));
        $cms->publishPresentation($this->actor, $draft['id']);
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.seo.title', 'W06 Global SEO Verified')
            ->assertJsonPath('data.seo.canonical_url', '/');
        $other = $cms->savePresentationDraft($editor, ['seo' => ['title' => 'W06 private future title']]);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.seo.title', 'W06 Global SEO Verified');
        $cms->rollbackPresentation($this->actor, $draft['id']);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.seo.title', 'W06 Global SEO Verified');
        $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $other['id'])->value('state'));
    }

    public function test_managed_social_image_is_published_page_scoped_and_rejects_reserved_routes(): void
    {
        // Repeated disposable DB transactions can reuse version numbers; isolate derived cache per test.
        config(['infrastructure.derived_cache_store' => 'array']);
        $mode = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $mode, 'published_at' => now(),
        ]);
        $cms = app(WebsiteCms::class);
        foreach (['products', 'categories', 'compare', 'enquiry', 'services', 'reset-password', 'account'] as $reserved) {
            $this->reject(fn () => $cms->savePageDraft($this->actor, null, [
                'title' => 'Reserved route', 'slug' => $reserved, 'content' => '<p>Cannot shadow application routes</p>',
            ]));
        }
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', true);
        $asset = $cms->registerMedia($this->actor, ['bytes' => $bytes, 'extension' => 'png',
            'original_name' => 'w06-managed-social.png', 'alt_text' => 'Synthetic managed image']);
        Storage::disk('local')->assertExists($asset['path']);
        Storage::disk('public')->assertMissing($asset['path']);
        $first = $cms->savePageDraft($this->actor, null, ['title' => 'First W06', 'slug' => 'w06-managed-one',
            'content' => '<p>First public document.</p>', 'social_image_media_id' => $asset['id']]);
        $second = $cms->savePageDraft($this->actor, null, ['title' => 'Other W06', 'slug' => 'w06-managed-other',
            'content' => '<p>Other public document.</p>']);
        $url = '/api/v1/content/pages/w06-managed-one/media/'.$asset['id'];
        $this->get($url)->assertNotFound();
        $cms->publishPage($this->actor, $second['id']);
        $this->get('/api/v1/content/pages/w06-managed-other/media/'.$asset['id'])->assertNotFound();
        $cms->publishPage($this->actor, $first['id']);
        $this->assertSame($asset['id'], app(WebsiteCms::class)->publicPage('w06-managed-one')['snapshot']['social_image_media_id']);
        $this->getJson('/api/v1/content/pages/w06-managed-one')->assertOk()
            ->assertJsonPath('data.snapshot.social_image_media_id', $asset['id']);
        $response = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($bytes, $response->getContent());
        $this->get('/api/v1/content/pages/w06-managed-other/media/'.$asset['id'])->assertNotFound();
        $this->get('/api/v1/content/pages/w06-managed-one/media/999999999')->assertNotFound();
        $revision = $cms->savePageDraft($this->actor, $first['page_public_id'], [
            'title' => 'First W06', 'slug' => 'w06-managed-one', 'content' => '<p>Updated public document.</p>',
            'social_image_media_id' => null,
        ]);
        $this->get($url)->assertOk();
        $cms->publishPage($this->actor, $revision['id']);
        $this->get($url)->assertNotFound();
    }

    public function test_public_software_image_media_requires_its_own_published_reference(): void
    {
        // New independent synthetic scope; never expose uploaded-but-unpublished CMS media.
        $mode = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $mode, 'published_at' => now(),
        ]);
        $cms = app(WebsiteCms::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGMQmhUlNCuKAUIBABuWBBkXZEqoAAAAAElFTkSuQmCC', true);
        $media = $cms->registerMedia($this->actor, [
            'bytes' => $png, 'extension' => 'png', 'original_name' => 'mt75-media.png', 'alt_text' => 'Synthetic illustration',
        ]);
        $other = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('W06 Other', 'w06-other', 'Other overview'));
        $cms->publishSoftware($this->actor, $other['id']);
        $input = $this->softwareInput('W06 Media', 'w06-media', 'Software image overview');
        $input['hero_media_id'] = $media['id'];
        $input['logo_media_id'] = $media['id'];
        $input['screenshot_media_ids'] = [$media['id']];
        $input['social_image_media_id'] = $media['id'];
        $draft = $cms->saveSoftwareDraft($this->actor, null, $input);
        $url = '/api/v1/software/w06-media/media/'.$media['id'];
        $this->get($url)->assertNotFound();
        $this->get('/api/v1/software/w06-other/media/'.$media['id'])->assertNotFound();
        $cms->publishSoftware($this->actor, $draft['id']);
        $served = $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($png, $served->getContent());
        $this->get('/api/v1/software/w06-other/media/'.$media['id'])->assertNotFound();
        $this->get('/api/v1/software/w06-media/media/999999')->assertNotFound();
        $this->get('/api/v1/software/w06-media/media/not-an-id')->assertNotFound();
        $later = $cms->saveSoftwareDraft($this->actor, $draft['software_public_id'],
            $this->softwareInput('W06 Media', 'w06-media', 'Private new revision without media'));
        $this->get($url)->assertOk(); // Unpublished draft cannot revoke a currently published reference.
        $cms->publishSoftware($this->actor, $later['id']);
        $this->get($url)->assertNotFound();
        $cms->archiveSoftware($this->actor, $draft['software_public_id']);
        $this->get($url)->assertNotFound();
    }

    public function test_nested_public_navigation_hides_children_of_hidden_parents_and_rejects_cycles(): void
    {
        $mode = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $mode, 'published_at' => now(),
        ]);
        $cms = app(WebsiteCms::class);
        $draft = $cms->savePresentationDraft($this->actor, ['navigation' => [
            ['key' => 'w06-parent', 'label' => 'W06 Managed Solutions', 'destination_type' => 'route',
                'destination_key' => 'services', 'capability_scope' => 'digital', 'sort_order' => 10],
            ['key' => 'w06-child', 'parent_key' => 'w06-parent', 'label' => 'W06 Enquiries',
                'destination_type' => 'route', 'destination_key' => 'enquiry', 'capability_scope' => 'digital', 'sort_order' => 20],
            ['key' => 'w06-grandchild', 'parent_key' => 'w06-child', 'label' => 'W06 More Services',
                'destination_type' => 'route', 'destination_key' => 'services', 'capability_scope' => 'digital', 'sort_order' => 30],
            ['key' => 'w06-hidden', 'label' => 'W06 Hidden', 'destination_type' => 'route',
                'destination_key' => 'services', 'is_visible' => false],
            ['key' => 'w06-hidden-child', 'parent_key' => 'w06-hidden', 'label' => 'W06 Hidden Child',
                'destination_type' => 'route', 'destination_key' => 'enquiry'],
            ['key' => 'w06-off', 'label' => 'W06 Off', 'destination_type' => 'route',
                'destination_key' => 'services', 'is_enabled' => false],
            ['key' => 'w06-off-child', 'parent_key' => 'w06-off', 'label' => 'W06 Off Child',
                'destination_type' => 'route', 'destination_key' => 'enquiry'],
        ]]);
        $cms->publishPresentation($this->actor, $draft['id']);
        $items = collect($this->getJson('/api/v1/content')->assertOk()->json('data.navigation'))->keyBy('key');
        $this->assertSame(['w06-parent', 'w06-child', 'w06-grandchild'], $items->keys()->all());
        $this->assertNull($items['w06-parent']['parent_key']);
        $this->assertSame('w06-parent', $items['w06-child']['parent_key']);
        $this->assertSame('w06-child', $items['w06-grandchild']['parent_key']);
        $this->assertSame('enquiry', $items['w06-child']['destination_key']);
        $invalid = $cms->savePresentationDraft($this->actor, ['navigation' => [
            ['key' => 'w06-cycle-a', 'parent_key' => 'w06-cycle-b', 'label' => 'A', 'destination_type' => 'route', 'destination_key' => 'services'],
            ['key' => 'w06-cycle-b', 'parent_key' => 'w06-cycle-a', 'label' => 'B', 'destination_type' => 'route', 'destination_key' => 'enquiry'],
        ]]);
        $this->reject(fn () => $cms->publishPresentation($this->actor, $invalid['id']));
        $after = collect($this->getJson('/api/v1/content')->assertOk()->json('data.navigation'))->keyBy('key');
        $this->assertSame($items->keys()->all(), $after->keys()->all());
    }

    public function test_published_announcement_and_banners_are_versioned_and_block_unsafe_links(): void
    {
        $mode = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $mode, 'published_at' => now(),
        ]);
        $cms = app(WebsiteCms::class);
        $first = $cms->savePresentationDraft($this->actor, ['promotion' => [
            'announcement' => ['text' => '<strong>First W06 announcement</strong>', 'href' => '/services', 'scope' => 'digital'],
            'banners' => [
                ['title' => 'W06 commerce banner', 'body' => 'Synthetic products only', 'href' => '/products', 'scope' => 'commerce'],
                ['title' => 'W06 digital banner', 'body' => 'Synthetic services only', 'href' => '/services', 'scope' => 'digital'],
            ],
        ]]);
        $this->assertNull($this->getJson('/api/v1/content')->assertOk()->json('data.promotion.announcement'));
        $cms->publishPresentation($this->actor, $first['id']);
        $published = $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.promotion.announcement.text', 'First W06 announcement')
            ->assertJsonPath('data.promotion.announcement.href', '/services')
            ->assertJsonPath('data.promotion.banners.0.title', 'W06 commerce banner')
            ->assertJsonPath('data.promotion.banners.1.title', 'W06 digital banner');
        $this->assertCount(2, $published->json('data.promotion.banners'));
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['promotion' => [
            'announcement' => ['text' => 'Unsafe', 'href' => 'javascript:alert(1)'],
        ]]));
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['promotion' => [
            'banners' => [['title' => 'Private route', 'href' => '/checkout']],
        ]]));
        $next = $cms->savePresentationDraft($this->actor, ['promotion' => [
            'announcement' => ['text' => 'Private changed banner', 'href' => '/products'],
            'banners' => [],
        ]]);
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.promotion.announcement.text', 'First W06 announcement');
        $cms->publishPresentation($this->actor, $next['id']);
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.promotion.announcement.text', 'Private changed banner')
            ->assertJsonCount(0, 'data.promotion.banners');
        $cms->rollbackPresentation($this->actor, $first['id']);
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.promotion.announcement.text', 'First W06 announcement')
            ->assertJsonCount(2, 'data.promotion.banners');
    }

    public function test_legal_policy_publication_requires_authority_recent_auth_fact_review_and_resolved_decisions(): void
    {
        $cms = app(WebsiteCms::class);
        $editor = new Admin;
        $editor->forceFill(['name' => 'CMS editor', 'email' => 'cms-editor@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['website.content.manage']])->save();
        $this->reject(fn () => $cms->savePolicyDraft($editor, 'privacy', [
            'content' => '<p>Privacy facts.</p>', 'effective_date' => '2026-09-17',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified',
        ]));

        $blocked = $cms->savePolicyDraft($this->actor, 'privacy', [
            'content' => '<p>Privacy facts with an unresolved retention decision.</p>', 'effective_date' => '2026-09-17',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified',
            'unresolved_decisions' => ['Owner must approve the final retention wording.'],
        ]);
        $request = $this->recentAuthRequest();
        $this->reject(fn () => $cms->publishPolicy($this->actor, $blocked['id'], $request));

        $approved = $cms->savePolicyDraft($this->actor, 'privacy', [
            'content' => '<p>Reviewed privacy facts for the implemented system.</p>', 'effective_date' => '2026-09-17',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified', 'unresolved_decisions' => [],
        ]);
        $this->reject(fn () => $cms->publishPolicy($this->actor, $approved['id'], Request::create('/admin', 'POST')));
        $cms->publishPolicy($this->actor, $approved['id'], $request);
        $this->assertSame('privacy', $cms->publicPolicies()[0]['type']);
        $newer = $cms->savePolicyDraft($this->actor, 'privacy', [
            'content' => '<p>Later reviewed privacy wording.</p>', 'effective_date' => '2026-10-01',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified', 'unresolved_decisions' => [],
        ]);
        $cms->publishPolicy($this->actor, $newer['id'], $request);
        $this->assertStringContainsString('Later reviewed', $cms->publicPolicies()[0]['content']);
        $cms->rollbackPolicy($this->actor, $approved['id'], $request);
        $this->assertStringContainsString('Reviewed privacy facts', $cms->publicPolicies()[0]['content']);

        $cookie = $cms->savePolicyDraft($this->actor, 'cookie', [
            'content' => '', 'effective_date' => '2026-09-17', 'applicability_state' => 'not_required',
            'approval_state' => 'owner_approved', 'factual_review_state' => 'verified',
            'review_notes' => 'No non-essential analytics or marketing tracking is enabled in this checkpoint.',
        ]);
        $cms->publishPolicy($this->actor, $cookie['id'], $request);
        $this->assertCount(1, $cms->publicPolicies());
        $this->assertNull(DB::table('cms_policies')->where('policy_type', 'cookie')->value('footer_destination'));
    }

    public function test_software_template_preserves_private_drafts_releases_routes_and_history(): void
    {
        $cms = app(WebsiteCms::class);
        $this->assertSame(0, DB::table('software_products')->count());
        $first = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('mobiST POS', 'mobist-pos', 'Original POS overview'));
        $second = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('mobiST Suite', 'mobist-suite', 'Suite overview'));
        $this->assertSame(2, DB::table('software_products')->count());
        $this->reject(fn () => $cms->publicSoftware('mobist-pos'));
        $cms->publishSoftware($this->actor, $first['id']);
        $cms->publishSoftware($this->actor, $second['id']);
        $public = $cms->publicSoftware('mobist-pos');
        $this->assertSame('Original POS overview', $public['snapshot']['overview']);
        $this->assertSame('/software/mobist-pos/privacy', $public['routes']['privacy']);
        $later = $cms->saveSoftwareDraft($this->actor, $first['software_public_id'], $this->softwareInput('mobiST POS', 'mobist-pos', 'Private unreleased overview'));
        $this->assertSame('Original POS overview', $cms->publicSoftware('mobist-pos')['snapshot']['overview']);
        $this->reject(fn () => $cms->saveSoftwareDraft($this->actor, $first['software_public_id'], $this->softwareInput('mobiST POS', 'mobist-pos-renamed', 'Bad silent rename')));

        $badImpact = $this->releaseImpact(['privacy']);
        $badImpact['privacy'] = 'not_affected';
        $this->reject(fn () => $cms->saveReleaseDraft($this->actor, $first['software_public_id'], [
            'version' => '0.9.0', 'release_date' => '2026-09-17', 'summary' => 'Bad material review',
            'notes' => ['changed' => ['Changed privacy-sensitive behavior']], 'impact_review' => $badImpact,
        ]));

        $impact = $this->releaseImpact(['overview']);
        $impact['overview'] = 'reviewed_updated';
        $releaseOne = $cms->saveReleaseDraft($this->actor, $first['software_public_id'], [
            'version' => '1.0.0', 'release_date' => '2026-09-17', 'summary' => 'Initial reviewed release',
            'notes' => ['added' => ['Initial public release']], 'impact_review' => $impact,
        ]);
        $cms->publishRelease($this->actor, $releaseOne['public_id'] ? DB::table('software_releases')->where('public_id', $releaseOne['public_id'])->value('id') : 0);
        $releaseTwo = $cms->saveReleaseDraft($this->actor, $first['software_public_id'], [
            'version' => '1.1.0', 'release_date' => '2026-09-18', 'summary' => 'Second reviewed release',
            'notes' => ['fixed' => ['Customer-visible reliability fix']], 'impact_review' => $this->releaseImpact(),
        ]);
        $releaseTwoId = DB::table('software_releases')->where('public_id', $releaseTwo['public_id'])->value('id');
        $cms->publishRelease($this->actor, (int) $releaseTwoId);
        $published = $cms->publicSoftware('mobist-pos');
        $this->assertSame('1.1.0', $published['current_version']);
        $this->assertCount(2, $published['releases']);
        $this->assertSame(2, DB::table('software_releases')->where('software_product_id', DB::table('software_products')->where('public_id', $first['software_public_id'])->value('id'))->where('state', 'published')->count());

        $cms->changeSoftwareSlug($this->actor, $first['software_public_id'], 'mobist-pos-desktop', 'Approved canonical namespace clarification');
        $this->reject(fn () => $cms->publicSoftware('mobist-pos'));
        $renamed = $cms->publicSoftware('mobist-pos-desktop');
        $this->assertSame('mobist-pos-desktop', $renamed['snapshot']['slug']);
        $this->assertSame('/software/mobist-pos-desktop', $cms->resolveRedirect('/software/mobist-pos'));
        $this->assertSame('/software/mobist-pos-desktop/releases/1.0.0', $cms->resolveRedirect('/software/mobist-pos/releases/1.0.0'));

        $cms->rollbackSoftware($this->actor, $first['id']);
        $rolled = $cms->publicSoftware('mobist-pos-desktop');
        $this->assertSame('mobist-pos-desktop', $rolled['snapshot']['slug']);
        $this->assertSame('Original POS overview', $rolled['snapshot']['overview']);
        $this->assertSame('1.1.0', $rolled['current_version']);
        $cms->archiveSoftware($this->actor, $first['software_public_id']);
        $this->reject(fn () => $cms->publicSoftware('mobist-pos-desktop'));
        $this->assertGreaterThanOrEqual(3, DB::table('software_product_revisions')->where('software_product_id', DB::table('software_products')->where('public_id', $first['software_public_id'])->value('id'))->count());
        $this->assertSame('draft', DB::table('software_product_revisions')->where('id', $later['id'])->value('state'));
    }

    private function softwareInput(string $name, string $slug, string $overview): array
    {
        return [
            'name' => $name, 'slug' => $slug, 'summary' => $name.' customer-facing summary', 'overview' => $overview,
            'features' => [['title' => 'Offline-first operations', 'description' => 'Synthetic verified feature']],
            'platforms' => ['Windows 10 x64', 'Windows 11 x64'],
            'system_requirements' => '<p>Supported Windows installation with required local resources.</p>',
            'limitations' => ['External provider features require separate configuration.'],
            'support' => ['channel' => 'support'], 'cta' => ['type' => 'contact'],
            'privacy' => '<p>Product-specific privacy content linked only to this software identity.</p>',
            'terms' => '<p>Product-specific terms linked only to this software identity.</p>',
            'faq' => [['question' => 'Is this a reusable product page?', 'answer' => '<p>Yes, it uses the shared software template.</p>']],
            'seo_title' => $name, 'seo_description' => 'Synthetic SEO description', 'sitemap' => true,
        ];
    }

    private function releaseImpact(array $material = []): array
    {
        $impact = [
            'overview' => 'not_affected', 'privacy' => 'not_affected', 'terms' => 'not_affected',
            'faq' => 'not_affected', 'system_requirements' => 'not_affected', 'support_guidance' => 'not_affected',
            'material' => $material,
        ];
        foreach ($material as $key) {
            $impact[$key] = 'reviewed_no_change';
        }

        return $impact;
    }

    private function recentAuthRequest(): Request
    {
        $request = Request::create('/admin/policies/publish', 'POST');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        $request->session()->put('identity_recent_auth_at', now()->timestamp);

        return $request;
    }
}
