<?php

namespace Tests\Feature;

use App\Catalog\ProductWebsitePublication;
use App\Cms\CataloguePresentation;
use App\Cms\WebsiteCms;
use App\Cms\WebsiteModePublication;
use App\Models\Admin;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class H01CataloguePresentationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config(['infrastructure.derived_cache_store' => 'array']);
        Cache::store('array')->clear();
    }

    public function test_typed_private_draft_publication_rollback_zero_stock_and_permissions(): void
    {
        $service = app(CataloguePresentation::class);
        $cms = app(WebsiteCms::class);
        $defaults = CataloguePresentation::DEFAULTS;
        $this->assertCount(14, $defaults);
        $this->assertSame($defaults, $service->publicValues());
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.catalogue.grid_desktop', 3);
        foreach ([['grid_desktop', 2], ['grid_mobile', 3], ['items_per_page', 49], ['image_ratio', '<script>'],
            ['show_compare', 'false'], ['show_out_of_stock', false]] as [$key, $bad]) {
            $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['catalogue' => [...$defaults, $key => $bad]]));
        }
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['catalogue' => ['grid_desktop' => 4]]));
        $this->reject(fn () => $cms->savePresentationDraft($this->actor, ['catalogue' => [...$defaults, 'unexpected' => 'secret']]));
        $outsider = new Admin;
        $outsider->forceFill(['name' => 'H01 limited', 'email' => 'h01-catalogue@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => []])->save();
        $this->reject(fn () => $cms->savePresentationDraft($outsider, ['catalogue' => $defaults]));
        $this->reject(fn () => $cms->publishPresentation($outsider, 999999));
        $first = $cms->savePresentationDraft($this->actor, ['catalogue' => $defaults]);
        $this->assertSame($defaults, $service->publicValues());
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.catalogue.grid_desktop', 3);
        $cms->publishPresentation($this->actor, $first['id']);
        $this->assertSame($defaults, $service->publicValues());
        $changed = [...$defaults, 'grid_desktop' => 6, 'grid_tablet' => 4, 'grid_mobile' => 2,
            'image_ratio' => '1:1', 'card_density' => 'compact', 'items_per_page' => 48,
            'default_sort' => 'newest', 'badge_behavior' => 'status_text', 'show_brand' => false,
            'show_specs' => false, 'show_colors' => false, 'show_warranty' => false, 'show_compare' => false];
        $second = $cms->savePresentationDraft($this->actor, ['catalogue' => $changed]);
        $this->assertSame($defaults, $service->publicValues());
        $cms->publishPresentation($this->actor, $second['id']);
        $this->assertSame($changed, $service->publicValues());
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.catalogue.grid_desktop', 6)
            ->assertJsonPath('data.catalogue.items_per_page', 48)
            ->assertJsonPath('data.catalogue.show_out_of_stock', true);
        $cms->rollbackPresentation($this->actor, $first['id']);
        $this->assertSame($defaults, $service->publicValues());
        $this->getJson('/api/v1/content')->assertOk()->assertJsonPath('data.catalogue.grid_desktop', 3);
        DB::table('site_settings')->where('key', 'cms.presentation.catalogue')->update(['value' => '{"unknown":"legacy"}']);
        $this->assertSame($defaults, $service->publicValues());
    }

    public function test_public_card_metadata_uses_only_available_unit_colors_pta_and_snapshot_warranty(): void
    {
        $mode = app(WebsiteModePublication::class)->saveDraft($this->actor, 'hybrid');
        app(WebsiteModePublication::class)->publish($this->actor, $mode['id']);
        $product = $this->product(true);
        $this->acquire($product);
        $this->imeis($product);
        $unit = StockUnit::where('product_id', $product->id)->firstOrFail();
        $unit->forceFill(['color' => 'H01 Synthetic Blue', 'pta_status' => 'pta_approved'])->save();
        $product->forceFill(['ram_gb' => 8, 'storage_gb' => 128])->save();
        $slug = 'h01-catalogue-safe-card';
        app(ProductWebsitePublication::class)->save($this->actor, $this->outlet, $product->public_id, [
            'slug' => $slug, 'description' => 'Synthetic H01 public catalogue metadata only.',
            'is_online' => true, 'expected_version' => 0,
        ]);
        DB::table('product_listings')->where('product_id', $product->id)->update(['warranty_summary' => 'H01 synthetic one-year warranty']);
        $items = $this->getJson('/api/v1/catalogue/products?limit=48')->assertOk();
        $items->assertJsonPath('data.page.limit', 48)
            ->assertJsonPath('data.items.0.specs.ram_gb', 8)
            ->assertJsonPath('data.items.0.specs.storage_gb', 128)
            ->assertJsonPath('data.items.0.specs.pta_statuses.0', 'pta_approved')
            ->assertJsonPath('data.items.0.colors.0', 'H01 Synthetic Blue')
            ->assertJsonPath('data.items.0.warranty_summary', 'H01 synthetic one-year warranty');
        foreach (['Synthetic-IMEI-1', 'Synthetic-IMEI-2', 'unit_code', 'purchase_price', 'seller_cnic', 'cnic_front_path'] as $private) {
            $this->assertStringNotContainsString($private, $items->getContent());
        }
        $detail = $this->getJson('/api/v1/catalogue/products/'.$slug)->assertOk();
        $detail->assertJsonPath('data.specs.pta_statuses.0', 'pta_approved');
        $this->getJson('/api/v1/catalogue/products?limit=49')->assertUnprocessable();
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
        } catch (HttpException $exception) {
            $this->assertContains($exception->getStatusCode(), [403, 422]);

            return;
        }
        $this->fail('Invalid catalogue presentation was accepted.');
    }
}
