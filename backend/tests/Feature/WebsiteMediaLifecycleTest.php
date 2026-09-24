<?php

namespace Tests\Feature;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

final class WebsiteMediaLifecycleTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config()->set('infrastructure.derived_cache_store', 'array');
        Cache::store('array')->clear();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_safe_asset_replacement_uses_new_id_preserves_history_and_retires_only_unused_private_bytes(): void
    {
        $cms = app(WebsiteCms::class);
        $one = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', true);
        $two = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAEklEQVR4nGMQmhUlNCuKAUIBABuWBBkXZEqoAAAAAElFTkSuQmCC', true);
        $this->reject(fn () => $cms->registerMedia($this->actor, ['bytes' => '<svg><script>unsafe</script></svg>',
            'extension' => 'svg', 'original_name' => 'unsafe.svg']));
        $first = $cms->registerMedia($this->actor, ['bytes' => $one, 'extension' => 'png',
            'original_name' => 'w07-one.png', 'alt_text' => 'First image']);
        $path = $first['path'];
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
        $alt = $cms->updateMediaAlt($this->actor, $first['id'], '<em>Accessible image</em>');
        $this->assertSame('Accessible image', $alt['alt_text']);
        $replacement = $cms->replaceMedia($this->actor, $first['id'], [
            'bytes' => $two, 'extension' => 'png', 'original_name' => 'w07-two.png', 'alt_text' => 'Replacement image',
        ]);
        $second = $replacement['replacement'];
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertSame(true, $replacement['assign_explicitly']);
        $this->assertSame('active', DB::table('site_media_assets')->where('id', $first['id'])->value('status'));
        Storage::disk('local')->assertExists($path);
        Storage::disk('local')->assertExists($second['path']);
        $this->reject(fn () => $cms->replaceMedia($this->actor, $first['id'], [
            'bytes' => $one, 'extension' => 'png', 'original_name' => 'same.png',
        ]));
        $deleted = $cms->deleteUnusedMedia($this->actor, $first['id']);
        $this->assertTrue($deleted['retired']);
        $this->assertTrue($deleted['private_file_removed']);
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('retired', DB::table('site_media_assets')->where('id', $first['id'])->value('status'));
        $this->reject(fn () => $cms->updateMediaAlt($this->actor, $first['id'], 'cannot revive'));
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $first['id']));

        $page = $cms->savePageDraft($this->actor, null, ['title' => 'W07 Media History',
            'slug' => 'w07-media-history', 'content' => '<p>Private referenced image</p>',
            'social_image_media_id' => $second['id']]);
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $second['id']));
        $cms->publishPage($this->actor, $page['id']);
        $later = $cms->savePageDraft($this->actor, $page['page_public_id'], [
            'title' => 'W07 Media History', 'slug' => 'w07-media-history', 'content' => '<p>No visible image now.</p>',
            'social_image_media_id' => null,
        ]);
        $cms->publishPage($this->actor, $later['id']);
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $second['id']));
        Storage::disk('local')->assertExists($second['path']);
        $this->assertSame($second['id'], json_decode((string) DB::table('site_page_revisions')
            ->where('id', $page['id'])->value('snapshot'), true)['social_image_media_id']);
        $outsider = new Admin;
        $outsider->forceFill(['name' => 'Content only', 'email' => 'w07-media-content-only@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['website.content.manage']])->save();
        $this->reject(fn () => $cms->updateMediaAlt($outsider, $second['id'], 'forbidden'));
        $this->reject(fn () => $cms->deleteUnusedMedia($outsider, $second['id']));
    }

    public function test_explicit_usage_and_configuration_history_block_media_deletion(): void
    {
        $cms = app(WebsiteCms::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', true);
        $media = $cms->registerMedia($this->actor, ['bytes' => $png, 'extension' => 'png',
            'original_name' => 'w07-usage.png']);
        DB::table('site_media_usages')->insert([
            'media_asset_id' => $media['id'], 'usage_domain' => 'branding', 'usage_key' => 'header_logo',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $media['id']));
        DB::table('site_media_usages')->where('media_asset_id', $media['id'])->delete();
        $revised = $cms->savePresentationDraft($this->actor, ['branding' => ['header_logo_media_id' => $media['id']]]);
        $cms->publishPresentation($this->actor, $revised['id']);
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $media['id']));
        $replacement = $cms->savePresentationDraft($this->actor, ['branding' => ['header_logo_media_id' => null]]);
        $cms->publishPresentation($this->actor, $replacement['id']);
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $media['id']));
        Storage::disk('local')->assertExists($media['path']);
    }
}
