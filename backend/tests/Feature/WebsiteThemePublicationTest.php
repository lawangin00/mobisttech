<?php

namespace Tests\Feature;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

final class WebsiteThemePublicationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config()->set('infrastructure.derived_cache_store', 'array');
        Cache::store('array')->clear();
    }

    public function test_approved_theme_tokens_are_safe_contrast_checked_permissioned_and_published_only(): void
    {
        $cms = app(WebsiteCms::class);
        foreach ([
            ['primary' => 'red'], ['primary' => '#ffffffff'], ['background' => 'url(javascript:alert(1))'],
            ['arbitrary_css' => '#008080'], ['text' => '#ffffff', 'surface' => '#ffffff'],
            ['muted_text' => '#ffffff', 'surface' => '#ffffff'],
        ] as $unsafe) {
            $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['theme' => $unsafe]));
        }
        $editor = new Admin;
        $editor->forceFill(['name' => 'W07 Theme-only operator', 'email' => 'w07-theme-only@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['website.theme.manage']])->save();
        $this->reject(fn () => $cms->savePresentationDraft($editor, ['branding' => []]));
        $this->reject(fn () => $cms->publishPresentation($editor, 999999));
        $first = $cms->savePresentationDraft($this->actor, ['theme' => ['primary' => '#005b60', 'background' => '#ffffff']]);
        $this->assertNull(DB::table('site_settings')->where('key', 'cms.presentation.theme')->value('value'));
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.theme', []);
        $cms->publishPresentation($this->actor, $first['id']);
        $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('data.theme.primary', '#005b60')
            ->assertJsonPath('data.theme.background', '#ffffff');
        $later = $cms->savePresentationDraft($this->actor, ['theme' => ['primary' => '#008080']]);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.theme.primary', '#005b60');
        $cms->publishPresentation($this->actor, $later['id']);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.theme.primary', '#008080');
        $cms->rollbackPresentation($this->actor, $first['id']);
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.theme.primary', '#005b60');
    }
}
