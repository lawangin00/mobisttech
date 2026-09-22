<?php

namespace Tests\Feature;

use App\Addendum\WebsiteCapabilities;
use App\Cms\WebsiteCms;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Digital\DigitalServiceLeads;
use App\Inventory\TransactionalStock;
use App\Loyalty\LoyaltyServices;
use App\Models\CustomerAccount;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use App\Support\ProductVariantKey;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
    }

    public function test_public_catalogue_is_bounded_fresh_cacheable_and_mode_aware(): void
    {
        $this->publishMode('hybrid', 1);
        $first = $this->listedProduct('alpha-phone');
        $second = $this->listedProduct('beta-phone');
        $this->acquire($first);
        $this->acquire($second);
        $page = $this->getJson('/api/v1/catalogue/products?limit=1')->assertOk()
            ->assertJsonPath('contract', 'catalogue-page.v1')
            ->assertJsonPath('data.page.limit', 1)
            ->assertJsonPath('data.page.has_more', true);
        $this->assertNotNull($page->json('data.page.next_cursor'));
        $this->assertArrayNotHasKey('purchase_price', $page->json('data.items.0'));
        $this->getJson('/api/v1/catalogue/products?q=ock')->assertOk()
            ->assertJsonCount(2, 'data.items');
        $etag = $page->headers->get('ETag');
        $this->withHeader('If-None-Match', $etag)->get('/api/v1/catalogue/products?limit=1')->assertStatus(304);

        $detail = $this->getJson('/api/v1/catalogue/products/alpha-phone')->assertOk();
        $this->assertSame(1, $detail->json('data.availability.quantity'));
        $this->assertSame('standard', $detail->json('data.variants.0.key'));
        $this->assertSame(1, $detail->json('data.variants.0.availability.quantity'));
        $variantJson = json_encode($detail->json('data.variants'), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('imei', strtolower($variantJson));
        $this->assertStringNotContainsString('unit_no', strtolower($variantJson));
        $this->acquire($first);
        $this->getJson('/api/v1/catalogue/products/alpha-phone')->assertOk()
            ->assertJsonPath('data.availability.quantity', 2);
        $this->getJson('/api/v1/catalogue/products?limit=25')->assertStatus(422)
            ->assertJsonPath('error.code', 'api_422');

        $this->publishMode('digital_only', 2);
        $this->getJson('/api/v1/catalogue/products')->assertNotFound()
            ->assertJsonPath('error.code', 'api_404');
    }

    public function test_tracked_public_variants_group_available_units_without_private_identifiers(): void
    {
        $this->publishMode('hybrid', 1);
        $product = $this->product(true);
        DB::table('product_listings')->insert(['external_source' => 'pos', 'external_id' => 'api-'.$product->id, 'slug' => 'mt75-tracked-variants', 'name' => $product->name, 'category' => $product->category, 'is_online' => true, 'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->acquire($product);
        $first = StockUnit::where('product_id', $product->id)->firstOrFail();
        $first->forceFill(['color' => 'Black', 'condition' => 'used'])->save();
        $this->imeis($product, $first);
        $this->acquire($product);
        $second = StockUnit::where('product_id', $product->id)->orderByDesc('id')->firstOrFail();
        $second->forceFill(['color' => 'Blue', 'condition' => 'used'])->save();
        $this->imeis($product, $second, [1 => 'MT75-PRIVATE-IMEI-3', 2 => 'MT75-PRIVATE-IMEI-4']);
        $detail = $this->getJson('/api/v1/catalogue/products/mt75-tracked-variants')->assertOk();
        $detail->assertJsonPath('data.availability.quantity', 2)->assertJsonCount(2, 'data.variants');
        $variants = collect($detail->json('data.variants'))->keyBy('color');
        $this->assertSame(['Black', 'Blue'], $variants->keys()->sort()->values()->all());
        foreach ($variants as $variant) {
            $this->assertSame(1, $variant['availability']['quantity']);
            $this->assertSame('used', $variant['condition']);
            $this->assertSame(16, strlen($variant['key']));
        }
        $this->assertNotSame($variants['Black']['key'], $variants['Blue']['key']);
        $this->assertDoesNotMatchRegularExpression('/IMEI|unit_no|purchase_price|seller_phone|outlet_id/i', $detail->getContent());
    }

    public function test_compare_detail_uses_only_public_pos_device_configuration_and_eligible_variant_attributes(): void
    {
        $this->publishMode('hybrid', 1);
        $phone = $this->product(true);
        $phone->forceFill(['ram_gb' => 8, 'storage_gb' => 128])->save();
        DB::table('product_listings')->insert(['external_source' => 'pos', 'external_id' => 'api-'.$phone->id,
            'slug' => 'mt75-compare-spec-phone', 'name' => $phone->name, 'category' => $phone->category,
            'is_online' => true, 'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $phone->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->acquire($phone);
        $unit = StockUnit::where('product_id', $phone->id)->firstOrFail();
        $unit->forceFill(['color' => 'Black', 'condition' => 'used', 'pta_status' => 'pta_approved'])->save();
        $this->imeis($phone, $unit, [1 => 'MT75-PRIVATE-COMPARE-IMEI-1', 2 => 'MT75-PRIVATE-COMPARE-IMEI-2']);
        $device = $this->getJson('/api/v1/catalogue/products/mt75-compare-spec-phone')->assertOk()
            ->assertJsonPath('data.category.code', 'mobile_phone')->assertJsonPath('data.device.ram_gb', 8)
            ->assertJsonPath('data.device.storage_gb', 128)->assertJsonPath('data.device.sim', $phone->simDisplay())
            ->assertJsonPath('data.variants.0.color', 'Black')->assertJsonPath('data.variants.0.condition', 'used')
            ->assertJsonPath('data.variants.0.pta_status', 'pta_approved');
        $this->assertDoesNotMatchRegularExpression('/IMEI|unit_no|purchase_price|seller_phone|outlet_id/i', $device->getContent());
        $accessory = $this->listedProduct('mt75-compare-spec-accessory');
        $this->getJson('/api/v1/catalogue/products/mt75-compare-spec-accessory')->assertOk()
            ->assertJsonPath('data.category.code', 'accessory')->assertJsonPath('data.device.ram_gb', null)
            ->assertJsonPath('data.device.storage_gb', null)->assertJsonPath('data.device.sim', $accessory->simDisplay());
        $this->assertNotNull($accessory->id);
    }

    public function test_catalogue_device_specs_and_same_unit_condition_pta_filters(): void
    {
        $this->publishMode('hybrid', 1);
        $first = $this->product(true);
        $second = $this->product(true);
        foreach ([[$first, 'mt75-device-eight'], [$second, 'mt75-device-twelve']] as [$product, $slug]) {
            DB::table('product_listings')->insert(['external_source' => 'pos', 'external_id' => 'api-'.$product->id,
                'slug' => $slug, 'name' => $product->name, 'category' => $product->category, 'is_online' => true,
                'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $first->forceFill(['ram_gb' => 8, 'storage_gb' => 256])->save();
        $second->forceFill(['ram_gb' => 12, 'storage_gb' => 512])->save();
        $this->acquire($first);
        $black = StockUnit::where('product_id', $first->id)->firstOrFail();
        $black->forceFill(['color' => 'Black', 'condition' => 'used', 'pta_status' => 'pta_approved'])->save();
        $this->imeis($first, $black);
        $this->acquire($first);
        $blue = StockUnit::where('product_id', $first->id)->orderByDesc('id')->firstOrFail();
        $blue->forceFill(['color' => 'Blue', 'condition' => 'brand_new', 'pta_status' => 'non_pta'])->save();
        $this->imeis($first, $blue, [1 => 'MT75-IMEI-PRIVATE-3', 2 => 'MT75-IMEI-PRIVATE-4']);
        $this->acquire($second);
        $other = StockUnit::where('product_id', $second->id)->firstOrFail();
        $other->forceFill(['condition' => 'used', 'pta_status' => 'non_pta'])->save();
        $this->imeis($second, $other, [1 => 'MT75-IMEI-PRIVATE-5', 2 => 'MT75-IMEI-PRIVATE-6']);
        $path = '/api/v1/catalogue/products?category=mobile_phone';
        $this->getJson($path.'&ram_gb=8&storage_gb=256')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-eight');
        $this->getJson($path.'&ram_gb=12&storage_gb=512')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-twelve');
        $this->getJson($path.'&condition=used&pta_status=pta_approved')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-eight');
        $this->getJson($path.'&condition=brand_new&pta_status=pta_approved')->assertOk()
            ->assertJsonCount(0, 'data.items');
        $this->getJson($path.'&condition=used&pta_status=non_pta')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-twelve');
        $this->getJson($path.'&condition=brand_new&pta_status=non_pta&ram_gb=8')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-eight');
        // The matching used/PTA-approved physical unit is reserved; its other available variant must not satisfy the filter.
        $hold = $this->reservation($first, 1, ProductVariantKey::forUnit($black));
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($hold['line']));
        $this->getJson($path.'&condition=used&pta_status=pta_approved')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/catalogue/products/mt75-device-eight')->assertOk()
            ->assertJsonPath('data.availability.quantity', 1)->assertJsonCount(1, 'data.variants');
        $this->getJson($path.'&condition=brand_new&pta_status=non_pta&ram_gb=8')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-eight');
        DB::transaction(fn () => app(TransactionalStock::class)->release($hold['reservation']));
        $this->getJson($path.'&condition=used&pta_status=pta_approved')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-device-eight');
        $this->getJson($path.'&ram_gb=0')->assertUnprocessable();
        $this->getJson($path.'&storage_gb=999999')->assertUnprocessable();
        $this->getJson($path.'&condition=fake')->assertUnprocessable();
        $this->getJson($path.'&pta_status=fake')->assertUnprocessable();
        $payload = $this->getJson($path.'&condition=used&pta_status=pta_approved')->assertOk()->getContent();
        $this->assertDoesNotMatchRegularExpression('/IMEI|unit_no|purchase_price|seller_phone|outlet_id/i', $payload);
    }

    public function test_catalogue_cursor_preserves_zero_stock_and_revalidates_live_hold(): void
    {
        $this->publishMode('hybrid', 1);
        $budget = $this->listedProduct('mt75-page-budget');
        $middle = $this->listedProduct('mt75-page-middle');
        $premium = $this->listedProduct('mt75-page-premium');
        foreach ([[$budget, 'Budget', '90.00'], [$middle, 'Middle', '150.00'], [$premium, 'Premium', '210.00']] as [$product, $name, $price]) {
            $product->forceFill(['name' => 'MT75PAGE '.$name, 'sale_price' => $price])->save();
        }
        $this->acquire($middle, 3);
        $this->acquire($premium);
        $url = '/api/v1/catalogue/products?category=accessory&q=MT75PAGE&sort=price_asc&limit=1';
        $first = $this->getJson($url)->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-page-budget')
            ->assertJsonPath('data.items.0.availability.quantity', 0)->assertJsonPath('data.page.has_more', true);
        $cursor = (string) $first->json('data.page.next_cursor');
        $this->assertNotSame('', $cursor);
        $middleUrl = $url.'&after='.urlencode($cursor);
        $second = $this->getJson($middleUrl)->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-page-middle')
            ->assertJsonPath('data.items.0.availability.quantity', 3)->assertJsonPath('data.page.has_more', true);
        $next = (string) $second->json('data.page.next_cursor');
        $this->getJson($url.'&after='.urlencode($next))->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-page-premium')
            ->assertJsonPath('data.items.0.availability.quantity', 1)->assertJsonPath('data.page.has_more', false);
        $hold = $this->reservation($middle, 3);
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($hold['line']));
        $held = $this->getJson($middleUrl)->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'mt75-page-middle')
            ->assertJsonPath('data.items.0.availability.quantity', 0)
            ->assertJsonPath('data.items.0.availability.in_stock', false)->assertJsonPath('data.page.has_more', true);
        $this->assertNotSame($second->headers->get('ETag'), $held->headers->get('ETag'));
        $this->getJson($url.'&after='.urlencode($next))->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-page-premium')
            ->assertJsonPath('data.items.0.availability.quantity', 1);
        DB::transaction(fn () => app(TransactionalStock::class)->release($hold['reservation']));
        $this->getJson($middleUrl)->assertOk()->assertJsonPath('data.items.0.availability.quantity', 3)
            ->assertJsonPath('data.items.0.availability.in_stock', true);
        $this->getJson($url.'&min_price=200')->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'mt75-page-premium')->assertJsonPath('data.page.has_more', false);
        $this->getJson(str_replace('price_asc', 'name_desc', $middleUrl))->assertUnprocessable();
        $this->assertDoesNotMatchRegularExpression('/IMEI|unit_no|purchase_price|seller_phone|outlet_id/i', $held->getContent());
    }

    public function test_catalogue_availability_uses_locked_live_hold_and_filters_before_cursor_boundary(): void
    {
        $this->publishMode('hybrid', 1);
        $budget = $this->listedProduct('mt75-avail-budget');
        $middle = $this->listedProduct('mt75-avail-middle');
        $premium = $this->listedProduct('mt75-avail-premium');
        foreach ([[$budget, 'Budget', '90.00'], [$middle, 'Middle', '150.00'], [$premium, 'Premium', '210.00']] as [$product, $name, $price]) {
            $product->forceFill(['name' => 'MT75AVAIL '.$name, 'sale_price' => $price])->save();
        }
        $this->acquire($middle, 3);
        $this->acquire($premium);
        $url = '/api/v1/catalogue/products?q=MT75AVAIL&sort=price_asc&limit=1';
        $in = $this->getJson($url.'&availability=in_stock')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-middle')->assertJsonPath('data.page.has_more', true);
        $cursor = urlencode((string) $in->json('data.page.next_cursor'));
        $this->getJson($url.'&availability=in_stock&after='.$cursor)->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-premium')->assertJsonPath('data.page.has_more', false);
        $this->getJson($url.'&availability=out_of_stock')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-budget')->assertJsonPath('data.page.has_more', false);
        $hold = $this->reservation($middle, 3);
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($hold['line']));
        $this->getJson($url.'&availability=in_stock')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-premium')->assertJsonPath('data.page.has_more', false);
        $out = $this->getJson($url.'&availability=out_of_stock')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-budget')->assertJsonPath('data.page.has_more', true);
        $outCursor = urlencode((string) $out->json('data.page.next_cursor'));
        $heldPage = $this->getJson($url.'&availability=out_of_stock&after='.$outCursor)->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-avail-middle')->assertJsonPath('data.page.has_more', false);
        $this->assertDoesNotMatchRegularExpression('/IMEI|unit_no|purchase_price|seller_phone|outlet_id/i', $heldPage->getContent());
        DB::transaction(fn () => app(TransactionalStock::class)->release($hold['reservation']));
        $this->getJson($url.'&availability=in_stock')->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-avail-middle');
        $this->getJson($url.'&availability=invalid')->assertUnprocessable();
    }

    public function test_catalogue_subcategory_uses_live_managed_pos_definition_and_filter_scoped_pages(): void
    {
        $this->publishMode('hybrid', 1);
        $master = app(PosInventoryMasterData::class);
        $budgetSub = $master->create('product_subcategory', ['label' => 'MT75 Budget Accessories', 'parent_category_code' => 'accessory']);
        $premiumSub = $master->create('product_subcategory', ['label' => 'MT75 Premium Accessories', 'parent_category_code' => 'accessory']);
        $budget = $this->listedProduct('mt75-subcat-budget');
        $next = $this->listedProduct('mt75-subcat-next');
        $premium = $this->listedProduct('mt75-subcat-premium');
        foreach ([$budget, $next] as $product) {
            $product->forceFill(['subcategory_master_data_id' => $budgetSub->id])->save();
        }
        $premium->forceFill(['subcategory_master_data_id' => $premiumSub->id])->save();
        $url = '/api/v1/catalogue/products?category=accessory&subcategory='.$budgetSub->code.'&limit=1';
        $first = $this->getJson($url)->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'mt75-subcat-budget')
            ->assertJsonPath('data.items.0.subcategory.code', $budgetSub->code)
            ->assertJsonPath('data.items.0.subcategory.label', 'MT75 Budget Accessories')
            ->assertJsonPath('data.page.has_more', true);
        $this->getJson($url.'&after='.urlencode($first->json('data.page.next_cursor')))->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-subcat-next')
            ->assertJsonPath('data.page.has_more', false);
        $this->getJson('/api/v1/catalogue/products?subcategory='.$premiumSub->code)->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-subcat-premium');
        $this->getJson('/api/v1/catalogue/products?category=tablet&subcategory='.$budgetSub->code)->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/catalogue/products?subcategory=accessory_unknown')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson('/api/v1/catalogue/products?subcategory=bad!')->assertUnprocessable();
        $this->assertDoesNotMatchRegularExpression('/purchase_price|seller_phone|outlet_id|imei/i', $first->getContent());
    }

    public function test_sitemap_catalogue_exposes_241_published_products_across_eleven_live_pages(): void
    {
        $this->publishMode('hybrid', 1);
        for ($i = 1; $i <= 241; $i++) {
            $product = $this->listedProduct('mt75-sitemap-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $product->forceFill(['name' => 'MT75SITEMAP Published '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)])->save();
        }
        $seen = [];
        $after = null;
        for ($page = 0; $page < 11; $page++) {
            $url = '/api/v1/catalogue/products?limit=24&q=MT75SITEMAP'.($after ? '&after='.urlencode($after) : '');
            $response = $this->getJson($url)->assertOk();
            $items = $response->json('data.items');
            $this->assertCount($page === 10 ? 1 : 24, $items);
            foreach ($items as $item) {
                $this->assertArrayNotHasKey('purchase_price', $item);
                $seen[] = $item['slug'];
            }
            $this->assertSame($page !== 10, $response->json('data.page.has_more'));
            $after = $response->json('data.page.next_cursor');
        }
        $this->assertCount(241, array_unique($seen));
        $this->assertContains('mt75-sitemap-241', $seen);
        $this->assertNull($after);
    }

    public function test_comparison_search_can_find_a_published_product_after_first_24_options(): void
    {
        $this->publishMode('hybrid', 1);
        for ($i = 0; $i < 25; $i++) {
            $product = $this->listedProduct('mt75-compare-search-'.($i + 1));
            $product->forceFill(['name' => 'MT75COMPARE '.($i === 24 ? 'Searchable Twenty Five' : 'Option '.($i + 1))])->save();
        }
        $url = '/api/v1/catalogue/products?q=MT75COMPARE&limit=24';
        $first = $this->getJson($url)->assertOk()->assertJsonCount(24, 'data.items')
            ->assertJsonPath('data.page.has_more', true);
        $this->assertNotContains('mt75-compare-search-25', array_column($first->json('data.items'), 'slug'));
        $target = $this->getJson('/api/v1/catalogue/products?q=Searchable%20Twenty%20Five&limit=24')
            ->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'mt75-compare-search-25')
            ->assertJsonPath('data.items.0.availability.quantity', 0);
        $this->assertDoesNotMatchRegularExpression('/purchase_price|seller_phone|imei|outlet_id/i', $target->getContent());
        $cursor = $first->json('data.page.next_cursor');
        $this->getJson($url.'&after='.urlencode($cursor))->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.slug', 'mt75-compare-search-25');
    }

    public function test_catalogue_brand_and_model_filters_use_current_public_product_values(): void
    {
        $this->publishMode('hybrid', 1);
        $managed = $this->listedProduct('mt75-brand-managed');
        $legacy = $this->listedProduct('mt75-brand-legacy');
        $managed->forceFill(['name' => 'MT75BRAND Managed', 'brand' => 'Old Brand', 'model' => 'Alpha 128'])->save();
        $legacy->forceFill(['name' => 'MT75BRAND Legacy', 'brand_master_data_id' => null,
            'brand' => 'Legacy Budget', 'model' => 'Beta 64'])->save();
        $url = '/api/v1/catalogue/products?q=MT75BRAND';
        $this->getJson($url.'&brand=Synthetic%20brand&model=Alpha')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-brand-managed');
        $this->getJson($url.'&brand=Old%20Brand')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson($url.'&brand=Legacy%20Budget&model=Beta')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-brand-legacy');
        $this->getJson($url.'&model=Alpha')->assertOk()->assertJsonCount(1, 'data.items');
        $this->getJson($url.'&brand=Unknown')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson($url.'&brand=x')->assertUnprocessable();
        $this->getJson($url.'&model=x')->assertUnprocessable();
    }

    public function test_catalogue_price_bounds_follow_live_pos_prices_without_leaking_private_cost(): void
    {
        $this->publishMode('hybrid', 1);
        $budget = $this->listedProduct('mt75-bound-budget');
        $premium = $this->listedProduct('mt75-bound-premium');
        $budget->forceFill(['name' => 'MT75BOUND Budget', 'sale_price' => '90.00'])->save();
        $premium->forceFill(['name' => 'MT75BOUND Premium', 'sale_price' => '150.00'])->save();
        $url = '/api/v1/catalogue/products?q=MT75BOUND';
        $this->getJson($url.'&min_price=100&max_price=150')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-bound-premium')
            ->assertJsonPath('data.items.0.price', '150.00');
        $this->getJson($url.'&max_price=99.99')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'mt75-bound-budget');
        $this->getJson($url.'&min_price=151')->assertOk()->assertJsonCount(0, 'data.items');
        $this->getJson($url.'&min_price=151&max_price=90')->assertUnprocessable();
        $this->getJson($url.'&min_price=-1')->assertUnprocessable();
        $this->getJson($url.'&max_price=invalid')->assertUnprocessable();
    }

    public function test_catalogue_sort_uses_live_price_name_and_sort_scoped_cursor(): void
    {
        $this->publishMode('hybrid', 1);
        $alpha = $this->listedProduct('mt75-sort-alpha');
        $zulu = $this->listedProduct('mt75-sort-zulu');
        $alpha->forceFill(['name' => 'MT75SORT Alpha', 'sale_price' => '300.00'])->save();
        $zulu->forceFill(['name' => 'MT75SORT Zulu', 'sale_price' => '100.00'])->save();
        $url = '/api/v1/catalogue/products?q=MT75SORT&limit=1&sort=';
        $first = $this->getJson($url.'price_asc')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-sort-zulu')
            ->assertJsonPath('data.items.0.price', '100.00');
        $cursor = $first->json('data.page.next_cursor');
        $this->assertNotNull($cursor);
        $this->getJson($url.'price_asc&after='.urlencode($cursor))->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt75-sort-alpha')
            ->assertJsonPath('data.page.has_more', false);
        $this->getJson($url.'name_asc')->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-sort-alpha');
        $this->getJson($url.'name_desc')->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-sort-zulu');
        $this->getJson($url.'price_desc')->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-sort-alpha');
        $this->getJson($url.'newest')->assertOk()->assertJsonPath('data.items.0.slug', 'mt75-sort-zulu');
        $this->getJson($url.'price_desc&after='.urlencode($cursor))->assertUnprocessable();
        $this->getJson($url.'invalid')->assertUnprocessable();
    }

    public function test_website_profile_exposes_authoritative_mode_navigation_cta_and_seo_plan(): void
    {
        $this->publishMode('hybrid', 1);
        $profile = $this->getJson('/api/v1/website-profile')->assertOk()
            ->assertJsonPath('contract', 'website-profile.v1')
            ->assertJsonPath('data.mode', 'hybrid')
            ->assertJsonPath('data.capabilities.commerce', true)
            ->assertJsonPath('data.capabilities.digital', true)
            ->assertJsonPath('data.copy_variant', 'hybrid')
            ->assertJsonPath('data.seo_sitemap.historical_routes_indexable', false)
            ->assertJsonPath('data.seo_sitemap.inactive_capabilities_indexable', false);
        $this->assertContains('products', $profile->json('data.routes'));
        $this->assertContains('services', $profile->json('data.routes'));
        $this->assertContains('browse_products', $profile->json('data.ctas'));
        $this->assertContains('service_enquiry', $profile->json('data.ctas'));

        $this->publishMode('digital_only', 2);
        $digital = $this->getJson('/api/v1/website-profile')->assertOk();
        $this->assertNotContains('products', $digital->json('data.routes'));
        $this->assertNotContains('browse_products', $digital->json('data.ctas'));
        $this->assertContains('products', $digital->json('data.seo_sitemap.excluded_routes'));
    }

    public function test_software_api_exposes_published_revision_and_release_only(): void
    {
        $this->publishMode('hybrid', 1);
        $cms = app(WebsiteCms::class);
        $draft = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('Published overview'));
        $cms->publishSoftware($this->actor, $draft['id']);
        $later = $cms->saveSoftwareDraft($this->actor, $draft['software_public_id'], $this->softwareInput('Private draft overview'));
        $release = $cms->saveReleaseDraft($this->actor, $draft['software_public_id'], [
            'version' => '1.0.0', 'release_date' => '2026-09-18', 'summary' => 'Initial public release',
            'notes' => ['added' => ['Public API release']], 'impact_review' => $this->releaseImpact(),
        ]);
        $releaseId = DB::table('software_releases')->where('public_id', $release['public_id'])->value('id');
        $cms->publishRelease($this->actor, (int) $releaseId);

        $overview = $this->getJson('/api/v1/software/mobist-pos')->assertOk()
            ->assertJsonPath('contract', 'software-overview.v1')
            ->assertJsonPath('data.overview.overview', 'Published overview')
            ->assertJsonPath('data.current_version', '1.0.0');
        $json = json_encode($overview->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Private draft overview', $json);
        $this->assertStringNotContainsString('impact_review', $json);
        $this->assertStringNotContainsString('state', $json);
        $this->getJson('/api/v1/software/mobist-pos/releases')->assertOk()
            ->assertJsonPath('data.items.0.version', '1.0.0');
        $this->assertSame('draft', DB::table('software_product_revisions')->where('id', $later['id'])->value('state'));
    }

    public function test_customer_api_uses_real_session_csrf_ownership_and_historical_exception(): void
    {
        $this->publishMode('hybrid', 1);
        $product = $this->listedProduct('session-phone');
        $this->acquire($product, 2);
        $customer = $this->customer('api-owner@example.invalid', '03001112222');
        $client = $this->client();
        $this->login($client, $customer->email)->assertOk();
        $quote = $this->send($client, 'POST', '/api/v1/cart/quote', [
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ])->assertOk();
        $cacheControl = (string) $quote->headers->get('Cache-Control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertSame('200.02', $quote->json('data.subtotal'));

        $payload = ['customer_name' => $customer->name, 'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email, 'city' => 'Karachi', 'delivery_address' => 'Synthetic address',
            'gateway' => 'cod', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]];
        $created = $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => 'api-order-'.Str::uuid()])
            ->assertCreated()->assertJsonPath('contract', 'order-create.v1');
        $orderId = $created->json('data.order_id');

        $this->publishMode('digital_only', 2);
        $this->send($client, 'GET', '/api/v1/orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.id', $orderId);
        $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => 'blocked-'.Str::uuid()])
            ->assertNotFound()->assertJsonPath('error.code', 'api_404');

        $other = $this->customer('api-other@example.invalid', '03001112223');
        $otherClient = $this->client();
        $this->login($otherClient, $other->email)->assertOk();
        $this->send($otherClient, 'GET', '/api/v1/orders/'.$orderId)->assertNotFound()
            ->assertJsonPath('error.code', 'api_404');
    }

    public function test_mt_5_2_customer_adapters_preserve_guest_ownership_loyalty_and_review_eligibility(): void
    {
        $this->publishMode('hybrid', 1);
        $product = $this->listedProduct('mt52-phone');
        $this->acquire($product, 2);
        $guestToken = str_repeat('g', 64);

        $this->postJson('/api/v1/guest-wishlist/'.$product->public_id, ['guest_token' => $guestToken])
            ->assertCreated()->assertJsonPath('contract', 'guest-wishlist-item.v1');
        $this->postJson('/api/v1/guest-wishlist', ['guest_token' => $guestToken])
            ->assertOk()->assertJsonCount(1, 'data.items');

        $customer = $this->customer('mt52-owner@example.invalid', '03001112224');
        $client = $this->client();
        $this->login($client, $customer->email)->assertOk();
        $this->send($client, 'POST', '/api/v1/wishlist/claim', ['guest_token' => $guestToken])
            ->assertOk()->assertJsonCount(1, 'data.items');
        $this->send($client, 'GET', '/api/v1/wishlist')->assertOk()->assertJsonCount(1, 'data.items');
        $this->assertSame(0, DB::table('wishlist_items')->whereNotNull('guest_owner_hash')->count());

        app(LoyaltyServices::class)->configure($this->actor, [
            'enabled' => true, 'earn_basis_amount' => '100.00', 'earn_points' => 10,
            'redemption_value' => '1.00', 'min_redeem_points' => 5, 'max_redeem_points' => 100,
            'daily_redeem_points' => 100, 'expiry_days' => 30,
        ]);
        $ownedCustomerId = (int) DB::table('customers')->where('website_user_id', $customer->id)->value('id');
        DB::table('loyalty_accounts')->insert([
            'customer_id' => $ownedCustomerId, 'balance_points' => 42, 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->send($client, 'GET', '/api/v1/loyalty')->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.balance_points', 42)
            ->assertJsonPath('data.min_redeem_points', 5);

        $payload = [
            'customer_name' => $customer->name, 'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email, 'city' => 'Karachi', 'delivery_address' => 'Synthetic address',
            'gateway' => 'cod', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ];
        $created = $this->send($client, 'POST', '/api/v1/orders', $payload, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt52-order-'.Str::uuid(),
        ])->assertCreated();
        $orderId = $created->json('data.order_id');
        DB::table('orders')->where('public_id', $orderId)->update(['payment_status' => 'paid', 'updated_at' => now()]);

        $this->send($client, 'GET', '/api/v1/reviews/eligible')->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.order_id', $orderId)
            ->assertJsonPath('data.items.0.product_id', $product->public_id);
        $this->send($client, 'POST', '/api/v1/reviews', [
            'order_id' => $orderId, 'product_id' => $product->public_id,
            'rating' => 5, 'title' => 'Synthetic review', 'body' => 'Verified purchase review.',
        ])->assertCreated()->assertJsonPath('data.status', 'pending');
        $this->send($client, 'GET', '/api/v1/reviews')->assertOk()
            ->assertJsonCount(1, 'data.items');
    }

    public function test_public_rate_limit_and_error_contract_are_enforced(): void
    {
        $this->publishMode('hybrid', 1);
        $this->getJson('/api/v1/catalogue/products?after=not-a-cursor')->assertStatus(422)
            ->assertJsonPath('error.code', 'api_422');
        for ($i = 0; $i < 119; $i++) {
            $this->getJson('/api/v1/website-profile')->assertOk();
        }
        $this->getJson('/api/v1/website-profile')->assertStatus(429);
    }

    private function listedProduct(string $slug)
    {
        $product = $this->product();
        DB::table('product_listings')->insert([
            'external_source' => 'pos', 'external_id' => 'api-'.$product->id, 'slug' => $slug,
            'name' => $product->name, 'category' => $product->category, 'is_online' => true,
            'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return $product;
    }

    public function test_w04_project_payment_http_excludes_cod_and_rejects_unavailable_gateway_without_mutation(): void
    {
        $this->publishMode('digital_only', 1);
        $channels = '/api/v1/project-payment-channels';
        $this->getJson($channels)->assertUnauthorized();
        $customer = $this->customer('w04-project-channels@example.invalid', '03001112239');
        $client = $this->client();
        $this->login($client, $customer->email)->assertOk();
        $response = $this->send($client, 'GET', $channels)->assertOk()
            ->assertJsonPath('contract', 'project-payment-channels.v1')->assertJsonCount(3, 'data.items');
        $this->assertSame(['jazzcash', 'easypaisa', 'card'], array_column($response->json('data.items'), 'code'));
        $this->assertSame([false, false, false], array_column($response->json('data.items'), 'available'));
        $before = [DB::table('orders')->count(), DB::table('payments')->count(),
            DB::table('idempotency_requests')->count()];
        $this->send($client, 'POST', '/api/v1/project-milestones/pay', [
            'milestone_id' => (string) Str::uuid(), 'gateway' => 'cod',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'w04-project-cod-'.Str::uuid()])
            ->assertStatus(422)->assertJsonPath('error.code', 'api_422');
        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $this->send($client, 'POST', '/api/v1/project-milestones/pay', [
                'milestone_id' => (string) Str::uuid(), 'gateway' => $gateway,
            ], true, ['HTTP_IDEMPOTENCY_KEY' => 'w04-project-unavailable-'.Str::uuid()])
                ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        }
        $this->assertSame($before, [DB::table('orders')->count(), DB::table('payments')->count(),
            DB::table('idempotency_requests')->count()]);
    }

    public function test_mt_5_3_checkout_http_contract_is_fixed_owned_idempotent_and_provider_verified(): void
    {
        $this->publishMode('hybrid', 1);
        $product = $this->listedProduct('mt53-checkout-phone');
        $this->acquire($product, 3);
        $customer = $this->customer('mt53-owner@example.invalid', '03001112225');
        $client = $this->client();
        $this->login($client, $customer->email)->assertOk();

        $channels = $this->send($client, 'GET', '/api/v1/checkout/channels')->assertOk()
            ->assertJsonPath('contract', 'checkout-channels.v1')
            ->assertJsonCount(4, 'data.items');
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_column($channels->json('data.items'), 'code'));
        $this->assertTrue($channels->json('data.items.0.available'));
        $this->assertFalse($channels->json('data.items.1.available'));
        $this->assertArrayNotHasKey('merchant', $channels->json('data.items.0'));

        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $this->assertFalse($this->send($client, 'GET', '/api/v1/checkout/channels')->json('data.items.1.available'));

        $fake = new Mt53ApiPaymentProvider;
        $registry = new PaymentProviders;
        $registry->register('jazzcash', $fake);
        $this->app->instance(PaymentProviders::class, $registry);
        $this->assertTrue($this->send($client, 'GET', '/api/v1/checkout/channels')->json('data.items.1.available'));

        $payload = [
            'customer_name' => $customer->name, 'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email, 'city' => 'Karachi', 'delivery_address' => 'Synthetic checkout address',
            'gateway' => 'cod', 'coupon_codes' => [], 'loyalty_points' => 0,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ];
        $key = 'mt53-cod-'.Str::uuid();
        $created = $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => $key])
            ->assertCreated()->assertJsonPath('data.payment_status', 'pending_collection');
        $orderId = $created->json('data.order_id');
        $this->assertSame($orderId, $this->send($client, 'POST', '/api/v1/orders', $payload, true, ['HTTP_IDEMPOTENCY_KEY' => $key])
            ->assertCreated()->json('data.order_id'));
        $this->assertSame(1, DB::table('orders')->where('public_id', $orderId)->count());
        $this->send($client, 'GET', '/api/v1/orders/'.$orderId)->assertOk()
            ->assertJsonPath('data.payments.0.gateway', 'cod')
            ->assertJsonPath('data.payment_status', 'unpaid');

        $foreignCancel = $this->customer('mt53-foreign-cancel@example.invalid', '03001112228');
        $foreignCancelClient = $this->client();
        $this->login($foreignCancelClient, $foreignCancel->email)->assertOk();
        $this->send($foreignCancelClient, 'POST', '/api/v1/orders/'.$orderId.'/cancel', [], true, [
            'HTTP_IDEMPOTENCY_KEY' => 'w04-foreign-cancel-'.Str::uuid(),
        ])->assertNotFound();
        $this->assertSame('pending', DB::table('orders')->where('public_id', $orderId)->value('status'));
        $this->assertSame('held_cod', DB::table('reservations')->where('order_id',
            DB::table('orders')->where('public_id', $orderId)->value('id'))->value('state'));
        $this->send($client, 'POST', '/api/v1/orders/'.$orderId.'/cancel', [], true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt53-cancel-'.Str::uuid(),
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());

        $tampered = [...$payload, 'total' => '0.01'];
        $this->send($client, 'POST', '/api/v1/orders', $tampered, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt53-tamper-'.Str::uuid(),
        ])->assertUnprocessable();

        $gatewayPayload = [...$payload, 'gateway' => 'jazzcash'];
        $gateway = $this->send($client, 'POST', '/api/v1/orders', $gatewayPayload, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt53-gateway-'.Str::uuid(),
        ])->assertCreated();
        $gatewayOrder = $gateway->json('data.order_id');
        $payment = $gateway->json('data.payment_id');
        $foreignCustomer = $this->customer('mt53-foreign-payment@example.invalid', '03001112229');
        $foreign = $this->client();
        $this->login($foreign, $foreignCustomer->email)->assertOk();
        $this->send($foreign, 'POST', '/api/v1/payments/'.$payment.'/initiate', [])->assertNotFound();
        $this->assertNull(DB::table('payments')->where('public_id', $payment)->value('gateway_order_reference'));
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->send($client, 'POST', '/api/v1/payments/'.$payment.'/initiate', [], false)->assertStatus(419);
        $this->assertNull(DB::table('payments')->where('public_id', $payment)->value('gateway_order_reference'));
        $init = $this->send($client, 'POST', '/api/v1/payments/'.$payment.'/initiate', [])
            ->assertOk()->assertJsonPath('contract', 'payment-initiation.v1');
        $reference = $init->json('data.reference');
        $this->assertStringStartsWith('https://pay.example.invalid/', $init->json('data.redirect_url'));
        $this->send($foreign, 'POST', '/api/v1/payments/'.$payment.'/initiate', [])->assertNotFound();
        $this->assertSame($reference, DB::table('payments')->where('public_id', $payment)->value('gateway_order_reference'));

        $this->postJson('/api/v1/payment-callbacks/jazzcash', $fake->event('MT53-FAILED', $reference, $gateway->json('data.amount'), 'failed'))
            ->assertOk()->assertJsonPath('data.payment_status', 'failed');
        $attemptsBefore = DB::table('payments')->where('order_id',
            DB::table('orders')->where('public_id', $gatewayOrder)->value('id'))->count();
        $this->send($client, 'POST', '/api/v1/orders/'.$gatewayOrder.'/payments/retry',
            ['gateway' => 'jazzcash'], false, ['HTTP_IDEMPOTENCY_KEY' => 'w04-csrf-retry-'.Str::uuid()])
            ->assertStatus(419)->assertJsonPath('error.code', 'api_419');
        $this->assertSame($attemptsBefore, DB::table('payments')->where('order_id',
            DB::table('orders')->where('public_id', $gatewayOrder)->value('id'))->count());
        $this->send($foreign, 'POST', '/api/v1/orders/'.$gatewayOrder.'/payments/retry',
            ['gateway' => 'jazzcash'], true, ['HTTP_IDEMPOTENCY_KEY' => 'w04-foreign-retry-'.Str::uuid()])
            ->assertNotFound();
        $this->assertSame($attemptsBefore, DB::table('payments')->where('order_id',
            DB::table('orders')->where('public_id', $gatewayOrder)->value('id'))->count());
        $retry = $this->send($client, 'POST', '/api/v1/orders/'.$gatewayOrder.'/payments/retry', ['gateway' => 'jazzcash'], true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt53-retry-'.Str::uuid(),
        ])->assertCreated();
        $retryPayment = $retry->json('data.payment_id');
        $retryInit = $this->send($client, 'POST', '/api/v1/payments/'.$retryPayment.'/initiate', [])->assertOk();
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $fake->event('MT53-PAID', $retryInit->json('data.reference'), $retry->json('data.amount'), 'paid'))
            ->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->send($client, 'GET', '/api/v1/orders/'.$gatewayOrder)->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'confirmed');
        $paidState = [DB::table('orders')->where('public_id', $gatewayOrder)->value('status'),
            DB::table('orders')->where('public_id', $gatewayOrder)->value('payment_status'),
            DB::table('payments')->where('public_id', $retryPayment)->value('status'),
            DB::table('sales')->count(), DB::table('payment_receipts')->count()];
        $paidPaymentCount = DB::table('payments')->where('order_id',
            DB::table('orders')->where('public_id', $gatewayOrder)->value('id'))->count();
        $this->send($client, 'POST', '/api/v1/orders/'.$gatewayOrder.'/payments/retry',
            ['gateway' => 'jazzcash'], true, ['HTTP_IDEMPOTENCY_KEY' => 'w04-paid-order-retry-'.Str::uuid()])
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame($paidPaymentCount, DB::table('payments')->where('order_id',
            DB::table('orders')->where('public_id', $gatewayOrder)->value('id'))->count());
        $this->send($client, 'POST', '/api/v1/orders/'.$gatewayOrder.'/cancel', [], true, [
            'HTTP_IDEMPOTENCY_KEY' => 'w04-paid-order-cancel-'.Str::uuid(),
        ])->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame($paidState, [DB::table('orders')->where('public_id', $gatewayOrder)->value('status'),
            DB::table('orders')->where('public_id', $gatewayOrder)->value('payment_status'),
            DB::table('payments')->where('public_id', $retryPayment)->value('status'),
            DB::table('sales')->count(), DB::table('payment_receipts')->count()]);
        $this->publishMode('digital_only', 2);
        $this->send($client, 'GET', '/api/v1/orders/'.$gatewayOrder)->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')->assertJsonPath('data.status', 'confirmed');
        $this->send($client, 'POST', '/api/v1/orders/'.$gatewayOrder.'/cancel', [], true, [
            'HTTP_IDEMPOTENCY_KEY' => 'w04-paid-digital-mode-cancel-'.Str::uuid(),
        ])->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame($paidState, [DB::table('orders')->where('public_id', $gatewayOrder)->value('status'),
            DB::table('orders')->where('public_id', $gatewayOrder)->value('payment_status'),
            DB::table('payments')->where('public_id', $retryPayment)->value('status'),
            DB::table('sales')->count(), DB::table('payment_receipts')->count()]);
    }

    public function test_mt_5_4_public_content_services_enquiry_and_software_contracts_are_published_and_mode_aware(): void
    {
        $this->publishMode('hybrid', 1);
        $cms = app(WebsiteCms::class);
        $digital = app(DigitalServiceLeads::class);

        $service = $digital->configureService($this->actor, [
            'slug' => 'mt54-web-development', 'name' => 'MT54 Web Development',
            'short_description' => 'Synthetic published Website service', 'description' => 'Published service detail',
            'price_type' => 'package', 'price' => null, 'is_active' => true,
            'packages' => [['code' => 'starter', 'name' => 'Starter', 'pricing_type' => 'fixed', 'price' => '10000.00']],
            'addons' => [['code' => 'seo', 'name' => 'SEO Setup', 'pricing_type' => 'fixed', 'price' => '2500.00']],
        ]);
        $digital->configureConsultation($this->actor, [
            'enabled' => true, 'timezone' => 'Asia/Karachi',
            'weekly_availability' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']],
        ]);

        $presentation = $cms->savePresentationDraft($this->actor, [
            'navigation' => [
                ['key' => 'services', 'label' => 'Services', 'destination_type' => 'route', 'destination_key' => 'services', 'capability_scope' => 'digital'],
            ],
        ]);
        $cms->publishPresentation($this->actor, $presentation['id']);

        $page = $cms->savePageDraft($this->actor, null, [
            'title' => 'MT54 Service Landing', 'slug' => 'mt54-service-landing',
            'content' => '<p>Published MT54 service landing.</p>', 'content_purpose' => 'service_landing',
            'capability_scope' => 'digital', 'service_slugs' => ['mt54-web-development'],
            'structured_content' => ['hero_heading' => 'Published digital work'],
        ]);
        $cms->publishPage($this->actor, $page['id']);
        $privatePage = $cms->savePageDraft($this->actor, $page['page_public_id'], [
            'title' => 'MT54 Service Landing', 'slug' => 'mt54-service-landing',
            'content' => '<p>Private draft must not leak.</p>', 'content_purpose' => 'service_landing',
            'capability_scope' => 'digital', 'service_slugs' => ['mt54-web-development'],
        ]);

        $software = $cms->saveSoftwareDraft($this->actor, null, $this->softwareInput('Published MT54 overview', 'mt54-api-software', 'MT54 API Software'));
        $cms->publishSoftware($this->actor, $software['id']);
        $privateSoftware = $cms->saveSoftwareDraft($this->actor, $software['software_public_id'], $this->softwareInput('Private MT54 overview', 'mt54-api-software', 'MT54 API Software'));
        $release = $cms->saveReleaseDraft($this->actor, $software['software_public_id'], [
            'version' => '5.4.0', 'release_date' => '2026-09-18', 'summary' => 'MT54 public release',
            'notes' => ['added' => ['Published reusable software routes']], 'impact_review' => $this->releaseImpact(),
        ]);
        $releaseId = DB::table('software_releases')->where('public_id', $release['public_id'])->value('id');
        $cms->publishRelease($this->actor, (int) $releaseId);

        $index = $this->getJson('/api/v1/content')->assertOk()
            ->assertJsonPath('contract', 'content-index.v1')
            ->assertJsonPath('data.pages.0.slug', 'mt54-service-landing')
            ->assertJsonPath('data.navigation.0.destination_key', 'services')
            ->assertJsonPath('data.software.0.slug', 'mt54-api-software');
        $this->assertStringNotContainsString('Private draft must not leak', json_encode($index->json(), JSON_THROW_ON_ERROR));

        $this->getJson('/api/v1/content/pages/mt54-service-landing')->assertOk()
            ->assertJsonPath('data.snapshot.content', '<p>Published MT54 service landing.</p>');
        $this->assertSame('draft', DB::table('site_page_revisions')->where('id', $privatePage['id'])->value('state'));

        $this->getJson('/api/v1/services')->assertOk()
            ->assertJsonPath('data.items.0.slug', 'mt54-web-development')
            ->assertJsonPath('data.items.0.packages.0.price', '10000.00');
        $this->getJson('/api/v1/consultation')->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.timezone', 'Asia/Karachi');

        $lead = [
            'service_slug' => 'mt54-web-development', 'customer_name' => 'MT54 Lead',
            'customer_mobile' => '03005550000', 'requirements' => 'Need a deterministic Website project',
            'package_public_id' => $service['packages'][0]['public_id'],
        ];
        $first = $this->withHeader('Idempotency-Key', 'mt54-enquiry')->postJson('/api/v1/enquiries', $lead)
            ->assertCreated()->assertJsonPath('contract', 'digital-enquiry.v1');
        $second = $this->withHeader('Idempotency-Key', 'mt54-enquiry')->postJson('/api/v1/enquiries', $lead)
            ->assertCreated();
        $this->assertSame($first->json('data.public_id'), $second->json('data.public_id'));
        $this->assertSame(1, DB::table('service_requests')->where('customer_mobile', '03005550000')->count());

        $overview = $this->getJson('/api/v1/software/mt54-api-software')->assertOk()
            ->assertJsonPath('data.overview.overview', 'Published MT54 overview')
            ->assertJsonPath('data.current_version', '5.4.0');
        $this->assertStringNotContainsString('Private MT54 overview', json_encode($overview->json(), JSON_THROW_ON_ERROR));
        $this->getJson('/api/v1/software/mt54-api-software/privacy')->assertOk()->assertJsonPath('data.slug', 'mt54-api-software');
        $this->getJson('/api/v1/software/mt54-api-software/terms')->assertOk()->assertJsonPath('data.slug', 'mt54-api-software');
        $this->getJson('/api/v1/software/mt54-api-software/faq')->assertOk()->assertJsonPath('data.slug', 'mt54-api-software');
        $this->getJson('/api/v1/software/mt54-api-software/releases')->assertOk()->assertJsonPath('data.items.0.version', '5.4.0');
        $this->assertSame('draft', DB::table('software_product_revisions')->where('id', $privateSoftware['id'])->value('state'));

        $this->publishMode('commerce_only', 2);
        $this->getJson('/api/v1/services')->assertNotFound();
        $this->getJson('/api/v1/consultation')->assertNotFound();
        $this->getJson('/api/v1/content/pages/mt54-service-landing')->assertNotFound();
        $commerceIndex = $this->getJson('/api/v1/content')->assertOk();
        $this->assertSame([], $commerceIndex->json('data.pages'));
        $this->assertSame([], $commerceIndex->json('data.navigation'));
        $this->getJson('/api/v1/software/mt54-api-software')->assertOk();
    }

    private function customer(string $email, string $mobile): CustomerAccount
    {
        $customer = new CustomerAccount;
        $customer->forceFill([
            'name' => 'Synthetic API customer', 'email' => $email, 'mobile' => $mobile,
            'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1, 'is_admin' => false,
        ])->save();

        return $customer;
    }

    private function softwareInput(string $overview, string $slug = 'mobist-pos', string $name = 'mobiST POS'): array
    {
        return [
            'name' => $name, 'slug' => $slug, 'summary' => 'Synthetic public product summary',
            'overview' => $overview,
            'features' => [['title' => 'Inventory', 'description' => 'Shared backend authority']],
            'platforms' => ['Windows 10 x64', 'Windows 11 x64'],
            'system_requirements' => '<p>Supported Windows environment.</p>',
            'limitations' => ['External providers require configuration.'],
            'support' => ['channel' => 'support'], 'cta' => ['type' => 'contact'],
            'privacy' => '<p>Published privacy content.</p>', 'terms' => '<p>Published terms content.</p>',
            'faq' => [['question' => 'Is this published?', 'answer' => '<p>Yes.</p>']],
            'seo_title' => 'mobiST POS', 'seo_description' => 'Synthetic SEO', 'sitemap' => true,
        ];
    }

    private function releaseImpact(): array
    {
        return [
            'overview' => 'reviewed_no_change', 'privacy' => 'reviewed_no_change',
            'terms' => 'reviewed_no_change', 'faq' => 'reviewed_no_change',
            'system_requirements' => 'reviewed_no_change', 'support_guidance' => 'reviewed_no_change',
            'material' => [],
        ];
    }

    private function publishMode(string $mode, int $version): void
    {
        $commerce = in_array($mode, ['hybrid', 'commerce_only'], true);
        $digital = in_array($mode, ['hybrid', 'digital_only'], true);
        $routes = ['home', 'about', 'contact', 'policies', 'software'];
        if ($digital) {
            $routes = [...$routes, 'services', 'case-studies', 'enquiry', 'consultation'];
        }
        if ($commerce) {
            $routes = [...$routes, 'products', 'categories', 'compare', 'cart', 'checkout'];
        }
        $operations = [];
        foreach (WebsiteCapabilities::PUBLIC_OPERATIONS as $operation => $capability) {
            if (($capability === 'digital' && $digital) || ($capability === 'commerce' && $commerce)) {
                $operations[] = $operation;
            }
        }
        $scopes = ['common'];
        if ($digital) {
            $scopes[] = 'digital';
        }
        if ($commerce) {
            $scopes[] = 'commerce';
        }
        $historical = [];
        foreach (WebsiteCapabilities::HISTORICAL_RESOURCES as $resource) {
            $historical[$resource] = ['allowed' => true, 'authenticated_only' => true, 'indexable' => false];
        }
        $snapshot = [
            'contract' => 'website-mode.v1', 'mode' => $mode,
            'capabilities' => ['digital' => $digital, 'commerce' => $commerce],
            'content_scopes' => $scopes, 'copy_variant' => $mode, 'routes' => $routes,
            'api_operations' => $operations,
            'ctas' => [...($digital ? ['service_enquiry', 'consultation'] : []), ...($commerce ? ['browse_products', 'add_to_cart', 'checkout'] : [])],
            'seo_sitemap' => [
                'discoverable_routes' => $routes,
                'excluded_routes' => [
                    ...($digital ? [] : ['services', 'case-studies', 'enquiry', 'consultation']),
                    ...($commerce ? [] : ['products', 'categories', 'compare', 'cart', 'checkout']),
                ],
                'historical_routes_indexable' => false, 'inactive_capabilities_indexable' => false,
            ],
            'historical_access' => $historical,
        ];
        $revision = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => $version, 'state' => 'published',
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => $mode, 'version' => $version, 'revision_id' => $revision, 'published_at' => now(),
        ]);
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic API client'];
        $response = $this->send($client, 'GET', '/api/v1/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/api/v1/auth/login', [
            'email' => $email, 'password' => 'SyntheticPass123!',
        ]);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $extraServer = [])
    {
        $cookieName = str_starts_with($uri, '/internal/admin/') ? 'XSRF-TOKEN-admin' : 'XSRF-TOKEN';
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'], ...$extraServer];
        if ($csrf && isset($client['tokens'][$cookieName])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens'][$cookieName];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }
}

final class Mt53ApiPaymentProvider implements PaymentProvider
{
    public function initiate(array $intent): array
    {
        return [
            'reference' => 'MT53-'.$intent['payment_id'],
            'redirect_url' => 'https://pay.example.invalid/'.$intent['payment_id'],
        ];
    }

    public function verify(array $payload): array
    {
        $signature = $payload['signature'] ?? '';
        $unsigned = array_diff_key($payload, ['signature' => true]);
        if (! hash_equals(hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), 'mt53-secret'), $signature)) {
            throw new \LogicException('Invalid synthetic provider signature.');
        }

        return $unsigned;
    }

    public function event(string $event, string $reference, string $amount, string $status): array
    {
        $payload = [
            'event_id' => $event,
            'transaction_reference' => 'TX-'.$event,
            'order_reference' => $reference,
            'amount' => $amount,
            'currency' => 'PKR',
            'status' => $status,
            'payload_hash' => hash('sha256', $event.'|'.$reference.'|'.$amount.'|'.$status),
        ];
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'mt53-secret');

        return $payload;
    }
}
