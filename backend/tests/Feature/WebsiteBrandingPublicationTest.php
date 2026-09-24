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

final class WebsiteBrandingPublicationTest extends TestCase
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

    public function test_private_branding_roles_publish_rollback_and_expose_only_current_scoped_image(): void
    {
        $cms = app(WebsiteCms::class);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9Z6ZsAAAAASUVORK5CYII=', true);
        $asset = $cms->registerMedia($this->actor, ['bytes' => $png, 'extension' => 'png', 'original_name' => 'w07-brand.png']);
        $id = $asset['id'];
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertNotFound();
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.branding.header_logo', null);
        foreach ([['bad_role' => $id], ['header_logo' => 'javascript:unsafe'], ['header_logo' => 999999],
            ['favicon' => -1], ['header_logo' => ['unexpected' => 1]]] as $bad) {
            $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['branding' => $bad]));
        }
        $editor = new Admin;
        $editor->forceFill(['name' => 'W07 theme-only operator', 'email' => 'w07-brand-role@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['website.theme.manage']])->save();
        $this->reject(fn () => $cms->savePresentationDraft($editor, ['branding' => ['header_logo' => $id]]));
        $this->reject(fn () => $cms->publishPresentation($editor, 999999));

        $first = $cms->savePresentationDraft($this->actor, ['branding' => ['header_logo' => $id, 'social_image' => $id]]);
        $this->getJson('/api/v1/content')->assertJsonPath('data.branding.header_logo', null);
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertNotFound();
        $cms->publishPresentation($this->actor, $first['id']);
        $this->getJson('/api/v1/content')->assertJsonPath('data.branding.header_logo', $id)
            ->assertJsonPath('data.branding.social_image', $id);
        $this->get('/api/v1/brand/header_logo/media/'.$id)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/api/v1/brand/footer_logo/media/'.$id)->assertNotFound();
        $this->get('/api/v1/brand/header_logo/media/999999')->assertNotFound();
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $id));

        $reset = $cms->savePresentationDraft($this->actor, ['branding' => ['header_logo' => null, 'social_image' => null]]);
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertOk();
        $cms->publishPresentation($this->actor, $reset['id']);
        $this->getJson('/api/v1/content')->assertJsonPath('data.branding.header_logo', null);
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertNotFound();
        // Current fallback must NOT license deletion of historical images.
        $this->reject(fn () => $cms->deleteUnusedMedia($this->actor, $id));
        $cms->rollbackPresentation($this->actor, $first['id']);
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertOk();

        DB::table('site_media_assets')->where('id', $id)->update(['disk' => 'public']);
        $this->getJson('/api/v1/content')->assertJsonPath('data.branding.header_logo', null);
        $this->getJson('/api/v1/brand/header_logo/media/'.$id)->assertNotFound();
    }
}
