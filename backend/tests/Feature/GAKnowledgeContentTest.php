<?php

namespace Tests\Feature;

use App\Api\WebsiteApi;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GAKnowledgeContentTest extends TestCase
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
            'slug' => 'ga-knowledge-service', 'name' => 'GA Knowledge Service',
            'short_description' => 'Synthetic knowledge service', 'is_active' => true,
        ]);
    }

    public function test_typed_knowledge_taxonomy_reusable_faq_and_service_guides_publish_without_draft_leakage(): void
    {
        $cms = app(WebsiteCms::class);
        $this->reject(fn () => $cms->savePageDraft($this->actor, null, [
            'title' => 'Empty FAQ', 'slug' => 'ga-empty-faq', 'content' => '<p>No items</p>',
            'content_purpose' => 'faq', 'capability_scope' => 'digital',
            'structured_content' => ['category' => 'Support', 'items' => []],
        ]));

        $faq = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA reusable FAQ', 'slug' => 'ga-reusable-faq', 'content' => '<p>FAQ introduction.</p>',
            'content_purpose' => 'faq', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-knowledge-service'],
            'seo_title' => 'GA FAQ SEO', 'meta_description' => 'GA FAQ metadata.',
            'structured_content' => [
                'category' => '<b>Website Support</b>', 'tags' => ['Laravel', 'Security', 'Laravel'],
                'items' => [
                    ['question' => '<b>Is this reusable?</b>', 'answer' => '<p>Yes, from one typed FAQ item.</p>'],
                    ['question' => 'Is draft content public?', 'answer' => '<p>No.</p>'],
                ],
                'private_extra' => 'must-not-persist',
            ],
        ]);
        $cms->publishPage($this->actor, $faq['id']);

        $insight = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA insight', 'slug' => 'ga-knowledge-insight', 'content' => '<p>Published insight.</p>',
            'content_purpose' => 'insight', 'capability_scope' => 'digital',
            'structured_content' => ['category' => 'Engineering', 'tags' => ['Laravel', 'Architecture'], 'ignored' => 'private'],
        ]);
        $cms->publishPage($this->actor, $insight['id']);

        $guide = $cms->savePageDraft($this->actor, null, [
            'title' => 'GA service guide', 'slug' => 'ga-service-guide', 'content' => '<p>Published guide.</p>',
            'content_purpose' => 'guide', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-knowledge-service'],
            'structured_content' => ['category' => 'Getting Started', 'tags' => ['Guide']],
        ]);
        $cms->publishPage($this->actor, $guide['id']);

        $publicFaq = $cms->publicPage('ga-reusable-faq')['snapshot'];
        $this->assertSame('Website Support', $publicFaq['structured_content']['category']);
        $this->assertSame(['Laravel', 'Security'], $publicFaq['structured_content']['tags']);
        $this->assertSame('Is this reusable?', $publicFaq['structured_content']['items'][0]['question']);
        $this->assertSame('Yes, from one typed FAQ item.', $publicFaq['structured_content']['items'][0]['answer']);
        $this->assertArrayNotHasKey('private_extra', $publicFaq['structured_content']);

        $private = $cms->savePageDraft($this->actor, $faq['page_public_id'], [
            'title' => 'GA reusable FAQ', 'slug' => 'ga-reusable-faq', 'content' => '<p>PRIVATE_KNOWLEDGE_DRAFT</p>',
            'content_purpose' => 'faq', 'capability_scope' => 'digital',
            'service_slugs' => ['ga-knowledge-service'],
            'structured_content' => [
                'category' => 'Private Future Category', 'tags' => ['PrivateTag'],
                'items' => [['question' => 'Private question', 'answer' => '<p>Private answer</p>']],
            ],
        ]);
        $this->assertSame('draft', $private['state']);
        $this->assertSame('Website Support', $cms->publicPage('ga-reusable-faq')['snapshot']['structured_content']['category']);

        $index = collect(app(WebsiteApi::class)->contentIndex()['pages'])->keyBy('slug');
        $this->assertSame('Website Support', $index['ga-reusable-faq']['category']);
        $this->assertSame(['Laravel', 'Security'], $index['ga-reusable-faq']['tags']);
        $this->assertSame(['ga-knowledge-service'], $index['ga-reusable-faq']['service_slugs']);
        $this->assertSame('Engineering', $index['ga-knowledge-insight']['category']);
        $this->assertSame(['ga-knowledge-service'], $index['ga-service-guide']['service_slugs']);

        $profile = app(WebsiteModePublication::class)->publicProfile();
        $this->assertContains('knowledge', $profile['routes']);
        $commerce = app(WebsiteModePublication::class)->saveDraft($this->actor, 'commerce_only');
        app(WebsiteModePublication::class)->publish($this->actor, $commerce['id']);
        Cache::store('array')->clear();
        $this->assertNotContains('knowledge', app(WebsiteModePublication::class)->publicProfile()['routes']);
        $this->assertNotContains('ga-reusable-faq', collect(app(WebsiteApi::class)->contentIndex()['pages'])->pluck('slug')->all());
        $this->getJson('/api/v1/content/pages/ga-reusable-faq')->assertNotFound();
    }
}
