<?php

namespace Tests\Feature;

use App\Api\WebsiteApi;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GACaseStudyPresentationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        Storage::fake('local');
        config()->set('infrastructure.derived_cache_store', 'array');
        Cache::store('array')->clear();
        $mode = app(WebsiteModePublication::class)->saveDraft($this->actor, 'hybrid');
        app(WebsiteModePublication::class)->publish($this->actor, $mode['id']);
        DB::table('digital_services')->insert([
            'slug' => 'ga-case-service', 'name' => 'GA Case Service',
            'short_description' => 'Synthetic case service', 'is_active' => true,
        ]);
    }

    public function test_case_media_visibility_order_and_linked_testimonials_are_public_only_when_approved(): void
    {
        $cms = app(WebsiteCms::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=');
        $media = $cms->registerMedia($this->actor, [
            'bytes' => $png, 'extension' => 'png', 'original_name' => 'ga-case.png', 'alt_text' => 'GA case screenshot',
        ]);

        $hidden = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA hidden case', 'slug' => 'ga-hidden-case', 'content' => '<p>Hidden case.</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-case-service'],
            'structured_content' => [
                'client_disclosure' => 'anonymous', 'display_enabled' => false, 'display_order' => 1,
                'screenshot_media_ids' => [$media['id']], 'industry' => 'MUST_NOT_PERSIST',
            ],
        ]);
        $cms->publishPage($this->actor, $hidden['id']);
        $this->getJson('/api/v1/content/pages/ga-hidden-case')->assertNotFound();

        $case = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA visible case', 'slug' => 'ga-visible-case', 'content' => '<p>Visible case.</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-case-service'],
            'structured_content' => [
                'client_disclosure' => 'anonymous', 'category' => 'Web Development',
                'problem' => 'Synthetic challenge', 'solution' => 'Synthetic solution',
                'technologies' => ['Laravel'], 'outcomes' => ['Measured synthetic outcome'],
                'screenshot_media_ids' => [$media['id']], 'display_enabled' => true, 'display_order' => 20,
                'industry' => 'MUST_NOT_PERSIST',
            ],
        ]);
        $cms->publishPage($this->actor, $case['id']);
        $second = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA earlier case', 'slug' => 'ga-earlier-case', 'content' => '<p>Earlier case.</p>',
            'content_purpose' => 'case_study', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-case-service'],
            'structured_content' => [
                'client_disclosure' => 'industry_only', 'industry' => 'Technology',
                'display_enabled' => true, 'display_order' => 10,
            ],
        ]);
        $cms->publishPage($this->actor, $second['id']);

        $testimonial = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA linked feedback', 'slug' => 'ga-linked-feedback', 'content' => '<p>Approved linked feedback.</p>',
            'content_purpose' => 'digital_testimonial', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-case-service'],
            'structured_content' => [
                'consent_confirmed' => true, 'moderation_state' => 'approved', 'display_enabled' => true,
                'display_order' => 5, 'case_study_slugs' => ['ga-visible-case'],
            ],
        ]);
        $cms->publishPage($this->actor, $testimonial['id']);

        $public = app(WebsiteApi::class)->page('ga-visible-case');
        $this->assertSame([$media['id']], $public['snapshot']['structured_content']['screenshot_media_ids']);
        $this->assertNull($public['snapshot']['structured_content']['industry']);
        $this->assertSame([['slug' => 'ga-linked-feedback', 'title' => 'GA linked feedback']], $public['related_testimonials']);
        $this->get('/api/v1/content/pages/ga-visible-case/media/'.$media['id'])->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $service = collect(app(WebsiteApi::class)->services()['items'])->firstWhere('slug', 'ga-case-service');
        $cases = collect($service['related_pages'])->where('purpose', 'case_study')->pluck('slug')->values()->all();
        $this->assertSame(['ga-earlier-case', 'ga-visible-case'], $cases);
        $this->assertNotContains('ga-hidden-case', collect($service['related_pages'])->pluck('slug')->all());
    }
}
