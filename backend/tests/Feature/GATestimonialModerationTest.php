<?php

namespace Tests\Feature;

use App\Api\WebsiteApi;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GATestimonialModerationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config()->set('infrastructure.derived_cache_store', 'array');
        Cache::store('array')->clear();
        $mode = app(WebsiteModePublication::class)->saveDraft($this->actor, 'hybrid');
        app(WebsiteModePublication::class)->publish($this->actor, $mode['id']);
        DB::table('digital_services')->insert([
            'slug' => 'ga-testimonial-service', 'name' => 'GA Testimonial Service',
            'short_description' => 'Synthetic testimonial service', 'is_active' => true,
        ]);
    }

    public function test_testimonial_publish_rollback_direct_page_association_and_order_fail_closed(): void
    {
        $cms = app(WebsiteCms::class);
        $case = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA testimonial case', 'slug' => 'ga-testimonial-case', 'content' => '<p>Case body</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-testimonial-service'],
            'structured_content' => ['client_disclosure' => 'anonymous'],
        ]);
        $cms->publishPage($this->actor, $case['id']);

        $pending = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA pending client feedback', 'slug' => 'ga-pending-feedback', 'content' => '<p>Private pending feedback</p>',
            'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-testimonial-service'],
            'structured_content' => [
                'consent_confirmed' => true, 'moderation_state' => 'pending',
                'display_enabled' => true, 'display_order' => 20,
                'case_study_slugs' => ['ga-testimonial-case'],
            ],
        ]);
        $this->reject(fn () => $cms->publishPage($this->actor, $pending['id']));
        $this->reject(fn () => $cms->publicPage('ga-pending-feedback'));

        $approved = $cms->savePageDraft($this->actor, $pending['page_public_id'], [
            'title' => 'GA approved client feedback', 'slug' => 'ga-pending-feedback', 'content' => '<p>Approved feedback body</p>',
            'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-testimonial-service'],
            'structured_content' => [
                'consent_confirmed' => true, 'moderation_state' => 'approved',
                'display_enabled' => true, 'display_order' => 20,
                'case_study_slugs' => ['ga-testimonial-case'],
            ],
        ]);
        $cms->publishPage($this->actor, $approved['id']);

        $first = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA first client feedback', 'slug' => 'ga-first-feedback', 'content' => '<p>First feedback body</p>',
            'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-testimonial-service'],
            'structured_content' => [
                'consent_confirmed' => true, 'moderation_state' => 'approved',
                'display_enabled' => true, 'display_order' => 10,
            ],
        ]);
        $cms->publishPage($this->actor, $first['id']);

        $public = $cms->publicPage('ga-pending-feedback');
        $this->assertSame(['ga-testimonial-case'], $public['snapshot']['structured_content']['case_study_slugs']);
        $encoded = json_encode($public, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('moderation_state', $encoded);
        $this->assertStringNotContainsString('consent_confirmed', $encoded);
        $this->assertStringNotContainsString('display_order', $encoded);

        $service = collect(app(WebsiteApi::class)->services()['items'])
            ->firstWhere('slug', 'ga-testimonial-service');
        $this->assertNotNull($service);
        $related = collect($service['related_pages']);
        $this->assertSame(['case_study', 'digital_testimonial', 'digital_testimonial'], $related->pluck('purpose')->all());
        $this->assertSame(['ga-testimonial-case', 'ga-first-feedback', 'ga-pending-feedback'], $related->pluck('slug')->all());

        $legacy = $cms->savePageDraft($this->actor, $pending['page_public_id'], [
            'title' => 'GA legacy pending feedback', 'slug' => 'ga-pending-feedback', 'content' => '<p>Legacy pending</p>',
            'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-testimonial-service'],
            'structured_content' => [
                'consent_confirmed' => true, 'moderation_state' => 'pending',
                'display_enabled' => true, 'display_order' => 1,
                'case_study_slugs' => ['ga-testimonial-case'],
            ],
        ]);
        DB::table('site_page_revisions')->where('id', $legacy['id'])->update(['state' => 'superseded']);
        $countBefore = DB::table('site_page_revisions')->where('site_managed_page_id',
            DB::table('site_managed_pages')->where('public_id', $pending['page_public_id'])->value('id'))->count();
        $this->reject(fn () => $cms->rollbackPage($this->actor, $legacy['id']));
        $this->assertSame($countBefore, DB::table('site_page_revisions')->where('site_managed_page_id',
            DB::table('site_managed_pages')->where('public_id', $pending['page_public_id'])->value('id'))->count());

        $pageId = DB::table('site_managed_pages')->where('public_id', $pending['page_public_id'])->value('id');
        DB::table('site_page_revisions')->where('id', $approved['id'])->update(['state' => 'superseded']);
        DB::table('site_page_revisions')->where('id', $legacy['id'])->update(['state' => 'published']);
        DB::table('site_managed_pages')->where('id', $pageId)->update(['current_revision_id' => $legacy['id'], 'publish_state' => 'published']);
        Cache::store('array')->clear();

        try {
            $cms->publicPage('ga-pending-feedback');
            $this->fail('Malformed legacy testimonial unexpectedly became public.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->getJson('/api/v1/content/pages/ga-pending-feedback')->assertNotFound();
        $this->assertNotContains('ga-pending-feedback', collect($this->getJson('/api/v1/content')->assertOk()->json('data.pages'))->pluck('slug')->all());
    }
}
