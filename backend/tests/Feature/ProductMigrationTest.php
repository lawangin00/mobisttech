<?php

namespace Tests\Feature;

use App\Catalog\ProductProjection;
use App\Migration\ProductImporter;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductMigrationTest extends TestCase
{
    use DatabaseTransactions;

    private int $run;

    private int $outlet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('1', 64), 'code_hash' => str_repeat('2', 64),
            'schema_hash' => str_repeat('3', 64), 'target_identity' => 'mobisttech_test', 'status' => 'product_rehearsal', 'started_at' => now()]);
        $this->outlet = DB::table('outlets')->insertGetId(['name' => 'Mapped outlet', 'public_id' => (string) Str::uuid(), 'outlet_code' => '007']);
        $this->map('users', 7, 'outlets', $this->outlet);
    }

    public function test_products_and_master_data_use_source_qualified_ids_with_unchanged_codes_and_replay(): void
    {
        $brand = $this->import('pos', 'pos_master_data_options', $this->row('pos', 'pos_master_data_options', ['id' => 9001, 'list_key' => 'product_brand', 'code' => 'source_brand', 'label' => 'Source Brand']));
        $this->assertSame('imported', $brand['outcome']);
        $row = $this->productRow(['brand_master_data_id' => 9001, 'brand' => 'Original brand snapshot', 'created_at' => '2026-08-31 12:00:00']);
        $result = $this->import('pos', 'products', $row);
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $product = Product::findOrFail($result['target_id']);
        $this->assertSame($this->outlet, $product->outlet_id);
        $this->assertSame($brand['target_id'], $product->brand_master_data_id);
        $this->assertNotSame(9001, $product->brand_master_data_id);
        $this->assertSame('MST-MOB-007-000007', $product->product_code);
        $this->assertSame('Original brand snapshot', $product->brand);
        $this->assertSame('Source Brand', $product->brandDisplay());
        $this->assertSame('100.01', $product->purchase_price);
        $this->assertSame('already_imported', $this->import('pos', 'products', $row)['outcome']);
        $this->assertSame('quarantined', $this->import('pos', 'products', [...$row, 'name' => 'Changed'])['outcome']);
        $this->assertSame('quarantined', app(ProductImporter::class)->import($this->run, 'pos', 'products', $row, 'Asia/Karachi')['outcome']);
        $this->assertSame(1, Product::count());
    }

    public function test_listing_candidate_must_match_product_outlet_category_and_contract_without_importing_cached_stock(): void
    {
        $product = $this->import('pos', 'products', $this->productRow());
        $row = $this->listingRow();
        foreach ([['external_source' => 'other'], ['external_id' => '999'], ['category' => 'tablet'],
            ['source_metadata' => ['shop_id' => '999', 'category' => 'mobile_phone']], ['source_metadata' => ['shop_id' => '7', 'category' => 'mobile_phone', 'sku' => 'WRONG']], ['source_metadata' => null]] as $bad) {
            $this->assertSame('quarantined', $this->import('website', 'products', [...$row, ...$bad])['outcome']);
        }
        $this->assertSame(0, DB::table('product_listings')->count());
        $result = $this->import('website', 'products', $row);
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $listing = DB::table('product_listings')->first();
        $this->assertSame($product['target_id'], $listing->product_id);
        $this->assertSame('original-source-slug', $listing->slug);
        $this->assertSame(999, $listing->legacy_stock_quantity);
        $this->assertSame(4, Product::findOrFail($product['target_id'])->qty);
        $this->assertSame('200.02', ProductProjection::publicMetadata(Product::findOrFail($product['target_id']))['sale_price']);
        $this->assertSame(2, DB::table('migration_identity_map')->where('source_repository', 'website')->where('source_primary_key', '7')->count());
        $this->assertSame('quarantined', $this->import('website', 'products', [...$row, 'id' => 8, 'slug' => 'duplicate-product'])['outcome']);
        $this->assertSame(1, DB::table('product_listings')->count());
    }

    public function test_unit_metadata_and_usage_relationships_preserve_history_and_wait_for_unresolved_parents(): void
    {
        $product = $this->import('pos', 'products', $this->productRow());
        $color = $this->import('pos', 'pos_master_data_options', $this->row('pos', 'pos_master_data_options', ['id' => 66, 'list_key' => 'unit_color', 'code' => 'black', 'label' => 'Black', 'metadata' => ['hex' => '#111111']]));
        $row = $this->row('pos', 'stock_units', ['id' => 9, 'product_id' => 7, 'unit_no' => 1, 'unit_code' => 'MST-MOB-007-000007-U0001',
            'status' => 'in_stock', 'color' => 'Old Black', 'color_master_data_id' => 66, 'purchase_price' => '100.01']);
        $this->assertSame('quarantined', $this->import('pos', 'stock_units', [...$row, 'invoice_id' => 999])['outcome']);
        $this->assertSame(0, StockUnit::count());
        $result = $this->import('pos', 'stock_units', $row);
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $unit = StockUnit::findOrFail($result['target_id']);
        $this->assertSame($product['target_id'], $unit->product_id);
        $this->assertSame('Old Black', $unit->color);
        $this->assertSame($row['unit_code'], $unit->unit_code);
        $this->assertSame($color['target_id'], $unit->color_master_data_id);
        $usage = $this->import('pos', 'pos_master_data_usages', $this->row('pos', 'pos_master_data_usages', ['master_data_option_id' => 66, 'usage_type' => 'stock_unit', 'usage_id' => '9', 'usage_field' => 'color']));
        $this->assertSame('imported', $usage['outcome']);
        $this->assertSame((string) $unit->id, DB::table('pos_master_data_usages')->value('usage_id'));
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame(0, DB::table('active_imeis')->count());
        $this->assertSame('quarantined', $this->import('pos', 'stock_units', [...$row, 'id' => 10])['outcome']);
    }

    public function test_invalid_source_values_and_field_shapes_quarantine_without_partial_products(): void
    {
        $base = $this->productRow();
        foreach ([['category' => 'laptop'], ['shop_id' => 999], ['sale_price' => 1.1], ['sale_price' => '1e5'], ['sale_price' => '-1'], ['sale_price' => '0.001'],
            ['product_code' => null], ['qty' => -1], ['track_imei' => 'false'], ['created_at' => '2026-02-31 12:00:00'], ['extra_field' => 3], ['name' => str_repeat('a', 256)]] as $bad) {
            $result = $this->import('pos', 'products', [...$base, ...$bad]);
            $this->assertSame('quarantined', $result['outcome'], json_encode($result));
        }
        $this->assertSame(0, Product::count());
        $this->assertSame(12, DB::table('migration_quarantine')->count());
        $this->assertSame(1, DB::table('migration_identity_map')->count());
    }

    public function test_wrong_option_lists_and_protected_category_or_metadata_changes_are_not_guessed(): void
    {
        $base = $this->row('pos', 'pos_master_data_options', ['list_key' => 'product_category', 'code' => 'laptop', 'label' => 'Laptop']);
        $this->assertSame('quarantined', $this->import('pos', 'pos_master_data_options', $base)['outcome']);
        $this->assertSame('quarantined', $this->import('pos', 'pos_master_data_options', [...$base, 'list_key' => 'stock_status'])['outcome']);
        $this->assertSame('quarantined', $this->import('pos', 'pos_master_data_options', [...$base, 'list_key' => 'device_sim_configuration', 'metadata' => ['imei_slots' => 3]])['outcome']);
        $option = $this->import('pos', 'pos_master_data_options', [...$base, 'id' => 1, 'list_key' => 'product_brand', 'code' => 'brand']);
        $this->assertSame('imported', $option['outcome']);
        $this->assertSame('quarantined', $this->import('pos', 'products', $this->productRow(['category_master_data_id' => 1]))['outcome']);
        $this->assertSame(0, Product::count());
    }

    public function test_import_is_atomic_with_outer_rollback_and_preserves_deleted_snapshot_and_timezone(): void
    {
        $baselineEvents = DB::table('domain_events')->count();
        DB::beginTransaction();
        $result = app(ProductImporter::class)->import($this->run, 'pos', 'products', $this->productRow(['isDeleted' => true, 'created_at' => '2026-08-31 12:00:00']), 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome']);
        $this->assertSame('2026-08-31 07:00:00.000000', DB::table('products')->value('created_at'));
        $this->assertTrue(Product::findOrFail($result['target_id'])->isDeleted);
        DB::rollBack();
        $this->assertSame(0, Product::count());
        $this->assertSame(0, DB::table('migration_identity_map')->where('source_table', 'products')->count());
        $this->assertSame($baselineEvents, DB::table('domain_events')->count());
    }

    private function map(string $table, int $old, string $target, int $id): void
    {
        DB::table('migration_identity_map')->insert(['source_repository' => 'pos', 'source_table' => $table, 'source_primary_key' => (string) $old,
            'target_table' => $target, 'target_id' => (string) $id, 'run_id' => $this->run, 'row_digest' => str_repeat('1', 64), 'outcome' => 'imported']);
    }

    private function import(string $source, string $table, array $row): array
    {
        return app(ProductImporter::class)->import($this->run, $source, $table, $row, 'UTC');
    }

    private function productRow(array $overrides = []): array
    {
        return $this->row('pos', 'products', ['id' => 7, 'shop_id' => 7, 'category' => 'mobile_phone', 'product_code' => 'MST-MOB-007-000007',
            'name' => 'Original device', 'price' => '200.02', 'sale_price' => '200.02', 'purchase_price' => '100.01',
            'warranty_type' => 'no_warranty', 'qty' => 4, 'sold_qty' => 2, 'track_imei' => true, ...$overrides]);
    }

    private function listingRow(): array
    {
        return $this->row('website', 'products', ['id' => 7, 'external_source' => 'mobist-pos', 'external_id' => '7', 'slug' => 'original-source-slug',
            'category' => 'mobile_phone', 'stock_quantity' => 999, 'online_price' => '999.99', 'availability_status' => 'in_stock', 'is_online' => true,
            'source_metadata' => ['shop_id' => '7', 'category' => 'mobile_phone']]);
    }

    private function row(string $source, string $table, array $overrides = []): array
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === $source && $entry['table'] === $table);
        $row = [];
        foreach ($entry['columns'] as $column) {
            $type = $column['target_type'];
            $row[$column['source_column']] = $type['nullable'] ? null : match ($type['type']) {
                'boolean' => false, 'integer', 'bigInteger', 'smallInteger' => 1, 'decimal' => '0.00', default => 'Synthetic',
            };
        }

        return [...$row, ...$overrides];
    }
}
