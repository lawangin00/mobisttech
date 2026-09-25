<?php

namespace Tests\Feature;

use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Digital\DigitalServiceLeads;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GADigitalServiceLandingTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config()->set('infrastructure.derived_cache_store', 'array');
        Cache::store('array')->clear();
        $modes = app(WebsiteModePublication::class);
        $mode = $modes->saveDraft($this->actor, 'hybrid');
        $modes->publish($this->actor, $mode['id']);
        app(DigitalServiceLeads::class)->configureService($this->actor, [
            'slug' => 'ga-digital-landing', 'name' => 'GA Digital Landing',
            'short_description' => 'Synthetic clean digital service', 'price_type' => 'quote',
            'price' => null, 'is_active' => true,
        ]);
    }

    public function test_published_service_landing_and_related_pages_are_linked_but_drafts_and_inactive_modes_are_private(): void
    {
        $cms = app(WebsiteCms::class);
        $this->getJson('/api/v1/services')->assertOk()->assertJsonPath('data.items.0.landing', null)
            ->assertJsonPath('data.items.0.related_pages', []);
        $draft = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA landing', 'slug' => 'ga-landing-page', 'content' => '<p>Published benefit</p>',
            'content_purpose' => 'service_landing', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-digital-landing'], 'seo_title' => 'GA managed landing SEO',
            'structured_content' => ['hero_heading' => 'GA published hero', 'features' => ['Private-safe feature', ['private_internal_notes' => 'GA_NESTED_SECRET']],
                'faq' => [['question' => 'Is this real?', 'answer' => 'Only synthetic demo data.']],
                'private_internal_notes' => 'NEVER_PUBLIC_INTERNAL_VALUE'],
        ]);
        $this->assertNull($this->getJson('/api/v1/services')->assertOk()->json('data.items.0.landing'));
        $cms->publishPage($this->actor, $draft['id']);
        $published = $this->getJson('/api/v1/services')->assertOk()
            ->assertJsonPath('data.items.0.landing.title', 'GA landing')
            ->assertJsonPath('data.items.0.landing.seo_title', 'GA managed landing SEO')
            ->assertJsonPath('data.items.0.landing.structured.hero_heading', 'GA published hero');
        $this->assertStringNotContainsString('NEVER_PUBLIC_INTERNAL_VALUE', $published->getContent());
        $this->assertStringNotContainsString('GA_NESTED_SECRET', $published->getContent());
        $cms->savePageDraft($this->actor, $draft['page_public_id'], [
            'title' => 'GA landing', 'slug' => 'ga-landing-page', 'content' => '<p>PRIVATE_FUTURE_CONTENT</p>',
            'content_purpose' => 'service_landing', 'capability_scope' => 'digital', 'service_slugs' => ['ga-digital-landing'],
        ]);
        $later = $this->getJson('/api/v1/services')->assertOk();
        $this->assertStringContainsString('Published benefit', $later->getContent());
        $this->assertStringNotContainsString('PRIVATE_FUTURE_CONTENT', $later->getContent());
        $case = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA published case', 'slug' => 'ga-published-case', 'content' => '<p>Anonymous case</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-digital-landing'], 'structured_content' => ['client_disclosure' => 'anonymous'],
        ]);
        $cms->publishPage($this->actor, $case['id']);
        $this->getJson('/api/v1/services')->assertOk()->assertJsonPath('data.items.0.related_pages.0.slug', 'ga-published-case');
        $mode = app(WebsiteModePublication::class)->saveDraft($this->actor, 'commerce_only');
        app(WebsiteModePublication::class)->publish($this->actor, $mode['id']);
        $this->getJson('/api/v1/services')->assertNotFound();
        $this->getJson('/api/v1/content/pages/ga-landing-page')->assertNotFound();
    }
}
