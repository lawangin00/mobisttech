<?php

namespace Tests\Feature;

use App\Catalog\MasterDataAdministration;
use App\Catalog\ProductDefinitions;
use App\Catalog\ProductProjection;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\SuperAdmin;
use App\Models\WebsiteAdmin;
use App\Services\PosInventoryMasterData;
use App\Support\BusinessIdentifier;
use App\Support\PosMasterDataRegistry;
use App\Support\ProductVariantKey;
use Database\Seeders\PosMasterDataSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ProductMasterDataTest extends TestCase
{
    use DatabaseTransactions;

    private Outlet $outlet;

    private SuperAdmin $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PosMasterDataSeeder::class);
        $this->outlet = new Outlet;
        $this->outlet->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic outlet', 'outlet_code' => '007'])->save();
        $this->actor = new SuperAdmin;
        $this->actor->forceFill(['name' => 'Synthetic operator', 'email' => 'master@example.invalid', 'password' => 'SyntheticPass123!'])->save();
    }

    public function test_source_registry_defaults_and_reseeding_preserve_managed_customization(): void
    {
        $this->assertSame(array_keys(PosMasterDataRegistry::LISTS), array_keys(PosInventoryMasterData::LISTS));
        foreach (['stock_status', 'stock_quantity', 'sold_status'] as $key) {
            $this->assertArrayNotHasKey($key, PosMasterDataRegistry::LISTS);
        }
        $before = PosMasterDataOption::count();
        $category = $this->option('product_category', 'mobile_phone');
        $category->update(['label' => 'Renamed mobiles', 'is_active' => false]);
        $this->seed(PosMasterDataSeeder::class);
        $this->assertSame($before, PosMasterDataOption::count());
        $this->assertSame('Renamed mobiles', $category->fresh()->label);
        $this->assertFalse($category->fresh()->is_active);
        $this->assertSame(['mobile_phone', 'tablet', 'accessory'], PosMasterDataOption::where('list_key', 'product_category')->orderBy('id')->pluck('code')->all());
    }

    public function test_protected_codes_and_canonical_status_deletion_are_rejected(): void
    {
        $service = app(MasterDataAdministration::class);
        foreach (['create', 'archive', 'delete'] as $action) {
            $this->reject(fn () => $service->change($this->actor, $action, 'product_category', $action === 'create' ? ['label' => 'Laptops'] : [], $this->option('product_category', 'mobile_phone')->id));
        }
        $this->reject(fn () => $service->change($this->actor, 'delete', 'unit_condition', [], $this->option('unit_condition', 'used')->id));
        $this->reject(fn () => $service->change($this->actor, 'delete', 'acquisition_source_type', [], $this->option('acquisition_source_type', 'supplier')->id));
        $this->reject(fn () => $this->option('product_category', 'mobile_phone')->update(['code' => 'laptop']));
        $this->assertSame(0, DB::table('domain_events')->count());
    }

    public function test_master_data_requires_configuration_grant_and_rejects_actor_or_code_injection(): void
    {
        $admin = new Admin;
        $admin->forceFill(['name' => 'Operator', 'email' => 'operator@example.invalid', 'password' => 'SyntheticPass123!'])->save();
        $this->reject(fn () => app(MasterDataAdministration::class)->change($admin, 'create', 'product_brand', ['label' => 'Denied']));
        $admin->forceFill(['permissions' => ['config.master-data.manage']])->save();
        $option = app(MasterDataAdministration::class)->change($admin, 'create', 'product_brand', ['label' => 'Allowed']);
        $this->assertSame('allowed', $option->code);
        $this->assertSame($admin->id, $option->created_by_id);
        $this->reject(fn () => app(MasterDataAdministration::class)->change($admin, 'update', 'product_brand', ['code' => 'changed'], $option->id));
        $this->reject(fn () => app(MasterDataAdministration::class)->change($admin, 'create', 'product_brand', ['label' => 'Injected', 'actor_id' => 1]));
        $admin->forceFill(['permissions' => []])->save();
        $this->reject(fn () => app(MasterDataAdministration::class)->change($admin, 'archive', 'product_brand', [], $option->id));
        $this->assertNull($option->fresh()->archived_at);
    }

    public function test_option_validation_lifecycle_and_existing_inactive_reference_are_preserved(): void
    {
        $master = app(PosInventoryMasterData::class);
        foreach ([['unit_color', ['label' => 'Bad', 'hex' => 'red']], ['device_ram_gb', ['value' => 2049]], ['device_storage_gb', ['value' => 1.5]],
            ['device_sim_configuration', ['label' => 'Triple', 'imei_slots' => 3]], ['product_subcategory', ['label' => 'Gaming', 'parent_category_code' => 'laptop']],
            ['acquisition_source_type', ['label' => 'Source', 'party_kind' => 'other']], ['product_brand', ['label' => '<script>x</script>']]] as [$list, $data]) {
            $this->reject(fn () => $master->create($list, $data));
        }
        $brand = $master->create('product_brand', ['label' => 'Historical Brand']);
        $master->archive($brand);
        $this->reject(fn () => $master->resolveSubmission('product_brand', $brand->id));
        $this->assertSame($brand->id, $master->resolveSubmission('product_brand', $brand->id, null, $brand->id)->id);
        $this->reject(fn () => $master->resolveSubmission('unit_color', $brand->id));
        $this->reject(fn () => $master->setActive($brand->fresh(), true));
        $master->restore($brand->fresh(), false);
        $this->assertFalse($brand->fresh()->is_active);
        $master->delete($brand->fresh());
        $this->assertNull($brand->fresh());
    }

    public function test_product_definition_preserves_identifiers_usage_history_and_exact_money(): void
    {
        $product = $this->product();
        $code = $product->product_code;
        $this->assertStringStartsWith('MST-MOB-007-', $code);
        $this->assertSame('99999999999999999.99', $product->sale_price);
        $this->assertSame(0, $product->qty);
        $this->assertSame(2, $product->requiredImeiSlots());
        $brand = $product->brandMasterOption;
        app(MasterDataAdministration::class)->change($this->actor, 'update', 'product_brand', ['label' => 'Brand renamed'], $brand->id);
        $this->assertSame('Brand source', $product->fresh()->brand);
        $this->assertSame('Brand renamed', $product->fresh()->brandDisplay());
        $this->reject(fn () => app(PosInventoryMasterData::class)->delete($brand->fresh()));
        $this->reject(fn () => $product->forceFill(['product_code' => 'MST-MOB-999-000001'])->save());
        $this->assertSame($code, $product->fresh()->product_code);
        $this->assertSame(1, DB::table('pos_master_data_usages')->where('master_data_option_id', $brand->id)->where('usage_id', $product->id)->count());
        $this->assertSame(2, DB::table('publication_versions')->where('domain', 'catalogue')->value('version'));
    }

    public function test_definition_rejects_duplicate_names_invalid_category_money_and_stock_actor_injection(): void
    {
        $product = $this->product();
        $base = $this->input($product->brand_master_data_id);
        foreach ([['category' => 'laptop'], ['sale_price' => '1e4'], ['purchase_price' => '-1'], ['sale_price' => 0.1], ['qty' => 900], ['outlet_id' => 99], ['product_code' => 'fake'], ['opening_stock' => 1], ['name' => ''], ['model' => null]] as $bad) {
            $this->reject(fn () => app(ProductDefinitions::class)->save($this->actor, $this->outlet, [...$base, ...$bad], $product->public_id));
        }
        $this->reject(fn () => app(ProductDefinitions::class)->save($this->actor, $this->outlet, $base));
        $this->assertSame(1, Product::count());
        $this->assertSame(0, DB::table('stock_units')->count());
        $this->assertSame(1, DB::table('domain_events')->count());
    }

    public function test_accessory_subcategory_semantics_and_outlet_boundaries_are_enforced(): void
    {
        $brand = app(PosInventoryMasterData::class)->create('product_brand', ['label' => 'Brand source']);
        $sub = app(PosInventoryMasterData::class)->create('product_subcategory', ['label' => 'Cables', 'parent_category_code' => 'accessory']);
        $base = [...$this->input($brand->id), 'subcategory_master_data_id' => $sub->id];
        $this->reject(fn () => app(ProductDefinitions::class)->save($this->actor, $this->outlet, $base));
        $product = app(ProductDefinitions::class)->save($this->actor, $this->outlet, [...$base, 'category' => 'accessory', 'model' => null, 'track_imei' => true]);
        $this->assertFalse($product->track_imei);
        $this->assertNull($product->ram_gb);
        $this->assertSame('not_applicable', $product->sim_configuration);
        $this->assertSame('accessory_cables', $product->subcategoryCode());
        $admin = new Admin;
        $admin->forceFill(['name' => 'Unassigned', 'email' => 'unassigned@example.invalid', 'password' => 'SyntheticPass123!'])->save();
        $this->reject(fn () => app(ProductDefinitions::class)->save($admin, $this->outlet, $base));
        DB::table('outlet_admins')->insert(['outlet_id' => $this->outlet->id, 'admin_id' => $admin->id]);
        $admin->forceFill(['permissions' => ['shops.enter']])->save();
        $this->reject(fn () => app(ProductDefinitions::class)->save($admin, $this->outlet, $base));
        $cms = new WebsiteAdmin;
        $cms->forceFill(['name' => 'CMS', 'email' => 'cms-product@example.invalid', 'password' => 'SyntheticPass123!', 'is_admin' => true, 'admin_role' => 'owner'])->save();
        $this->reject(fn () => app(ProductDefinitions::class)->save($cms, $this->outlet, $base));
    }

    public function test_unit_variant_keys_and_private_metadata_remain_separate_from_display_labels(): void
    {
        $product = $this->product();
        $unit = new StockUnit;
        $unit->forceFill(['product_id' => $product->id, 'unit_no' => 1, 'purchase_price' => '12345.67', 'status' => 'in_stock', 'notes' => 'Private seller note',
            'color' => 'Black', 'condition' => 'used', 'pta_status' => 'unknown', 'carrier_lock_status' => null, 'mdm_status' => 'unknown'])->save();
        $unit = app(ProductDefinitions::class)->unitAttributes($this->actor, $this->outlet, $unit->public_id, ['color_master_data_id' => $this->option('unit_color', 'black')->id]);
        $key = ProductVariantKey::forUnit($unit);
        $code = $unit->unit_code;
        $master = app(PosInventoryMasterData::class);
        $master->update($unit->colorMasterOption, ['label' => 'Obsidian', 'hex' => '#202020']);
        $dto = ProductProjection::variant($unit->fresh());
        $this->assertSame($key, $dto['key']);
        $this->assertSame('Obsidian', $dto['color']);
        $this->assertSame('#202020', $dto['color_hex']);
        $this->assertSame('Black', $unit->fresh()->color);
        $this->assertSame($code, $unit->fresh()->unit_code);
        foreach (['purchase_price', 'notes', 'imei', 'invoice_id', 'sale_id', 'status'] as $field) {
            $this->assertArrayNotHasKey($field, $dto);
        }
        $public = ProductProjection::publicMetadata($product->fresh());
        foreach (['purchase_price', 'qty', 'sold_qty', 'outlet_id', 'legacy_stock_quantity', 'source_metadata'] as $field) {
            $this->assertArrayNotHasKey($field, $public);
        }
        $this->assertNotSame(ProductVariantKey::fromValues(null, null, null, null, null), ProductVariantKey::fromValues(null, 'unknown', null, null, null));
        $this->assertSame(ProductVariantKey::fromValues(' BLACK ', 'used', null, null, null), ProductVariantKey::fromValues('black', 'used', null, null, null));
        $this->reject(fn () => app(ProductDefinitions::class)->unitAttributes($this->actor, $this->outlet, $unit->public_id, ['status' => 'sold']));
        $this->reject(fn () => $unit->forceFill(['unit_code' => 'fake'])->save());
        $this->assertSame('in_stock', $unit->fresh()->status);
    }

    public function test_definition_transaction_rollback_does_not_publish_or_leave_a_product(): void
    {
        DB::beginTransaction();
        $product = $this->product();
        $id = $product->id;
        $this->assertSame(1, DB::table('domain_events')->count());
        DB::rollBack();
        $this->assertNull(Product::find($id));
        $this->assertSame(0, DB::table('domain_events')->count());
        $this->assertSame(0, DB::table('publication_versions')->count());
        $this->assertSame('007', BusinessIdentifier::outlet(null, 7));
        $this->reject(fn () => BusinessIdentifier::outlet(null, 1000));
    }

    public function test_pinned_source_constants_and_variant_name_characterization_match_target(): void
    {
        $source = json_decode(file_get_contents(base_path('../docs/catalog/SOURCE_CONTRACTS.json')), true);
        foreach (['registry' => PosMasterDataRegistry::LISTS, 'categories' => Product::CATEGORY_LABELS, 'sim_labels' => Product::SIM_LABELS,
            'colors' => StockUnit::COLOR_OPTIONS, 'conditions' => StockUnit::CONDITIONS, 'pta_statuses' => StockUnit::PTA_STATUSES,
            'carrier_lock_statuses' => StockUnit::CARRIER_LOCK_STATUSES, 'mdm_statuses' => StockUnit::MDM_STATUSES, 'stock_statuses' => StockUnit::STOCK_STATUSES] as $key => $target) {
            $this->assertSame($source[$key], $target);
        }
        foreach ($source['variants'] as $case) {
            $this->assertSame($case['key'], ProductVariantKey::fromValues(...$case['input']));
        }
        foreach ($source['device_names'] as $case) {
            $this->assertSame($case['name'], Product::generatedDeviceName(...$case['input']));
        }
    }

    public function test_existing_holds_block_definition_and_unit_changes_without_rewriting_history(): void
    {
        $product = $this->product();
        $outlet = $this->outlet->id;
        $order = DB::table('orders')->insertGetId(['order_number' => 'MT23-HOLD', 'order_type' => 'mobile', 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000', 'public_id' => (string) Str::uuid()]);
        $item = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_type' => 'mobile', 'title' => 'Immutable historical title', 'product_id' => $product->id, 'outlet_id' => $outlet]);
        $reservation = DB::table('reservations')->insertGetId(['order_id' => $order, 'outlet_id' => $outlet, 'website_order_number' => 'MT23-HOLD', 'reservation_reference' => (string) Str::uuid(), 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000']);
        $line = DB::table('reservation_lines')->insertGetId(['reservation_id' => $reservation, 'order_item_id' => $item, 'product_id' => $product->id, 'outlet_id' => $outlet, 'website_order_item_id' => '1', 'product_external_id' => '1', 'outlet_external_id' => '007', 'variant_key' => 'synthetic', 'quantity' => 1, 'unit_price' => '25.50', 'line_total' => '25.50']);
        $unit = new StockUnit;
        $unit->forceFill(['product_id' => $product->id, 'unit_no' => 1, 'status' => 'in_stock'])->save();
        $allocation = DB::table('reservation_allocations')->insertGetId(['reservation_line_id' => $line, 'product_id' => $product->id, 'stock_unit_id' => $unit->id]);
        $input = [...$this->input($product->brand_master_data_id), 'name' => 'Renamed product'];
        $this->reject(fn () => app(ProductDefinitions::class)->save($this->actor, $this->outlet, $input, $product->public_id));
        $this->reject(fn () => app(ProductDefinitions::class)->unitAttributes($this->actor, $this->outlet, $unit->public_id, ['color_master_data_id' => $this->option('unit_color', 'black')->id]));
        $this->assertSame('Synthetic Phone', $product->fresh()->name);
        DB::table('reservation_allocations')->where('id', $allocation)->update(['released_at' => now()]);
        app(ProductDefinitions::class)->save($this->actor, $this->outlet, $input, $product->public_id);
        $this->assertSame('Renamed product', $product->fresh()->name);
        $this->assertSame('Immutable historical title', DB::table('order_items')->where('id', $item)->value('title'));
        $this->assertSame(0, DB::table('stock_movements')->count());
    }

    private function product(): Product
    {
        $brand = app(PosInventoryMasterData::class)->create('product_brand', ['label' => 'Brand source']);

        return app(ProductDefinitions::class)->save($this->actor, $this->outlet, $this->input($brand->id));
    }

    private function input(int $brandId): array
    {
        return ['name' => 'Synthetic Phone', 'category' => 'mobile_phone', 'brand_master_data_id' => $brandId,
            'model' => 'Model One', 'sale_price' => '99999999999999999.99', 'purchase_price' => '100.01', 'track_imei' => true,
            'warranty_type' => 'no_warranty', 'ram_gb' => 8, 'storage_gb' => 128, 'sim_configuration' => 'dual_physical'];
    }

    private function option(string $list, string $code): PosMasterDataOption
    {
        return PosMasterDataOption::where('list_key', $list)->where('code', $code)->firstOrFail();
    }

    private function reject(callable $callback): void
    {
        try {
            $callback();
        } catch (\LogicException|ValidationException|HttpException $error) {
            $this->assertNotEmpty($error::class);

            return;
        }
        $this->fail('Invalid or unauthorized mutation succeeded.');
    }
}
