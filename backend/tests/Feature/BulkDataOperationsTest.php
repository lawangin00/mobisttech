<?php

namespace Tests\Feature;

use App\Bulk\BulkDataOperations;
use App\Inventory\StockLedger;
use App\Inventory\TransactionalStock;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class BulkDataOperationsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private BulkDataOperations $bulk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->bulk = app(BulkDataOperations::class);
    }

    public function test_preview_rejects_formulas_historical_boundary_and_permissions_and_export_escapes_cells(): void
    {
        $product = $this->product();
        $product->forceFill(['name' => '=2+2'])->save();
        $formula = [
            ['row_key', 'product_id', 'purchase_price', 'sale_price'],
            ['formula', $product->public_id, '100.01', '=2+2'],
        ];
        $preview = $this->bulk->preview($this->actor, $this->outlet, 'price', 'xlsx', $formula);
        $this->assertSame(0, $preview['valid']);
        $this->assertSame(1, $preview['invalid']);
        $this->assertArrayHasKey('sale_price', $preview['rows'][0]['errors']);

        $this->reject(fn () => $this->bulk->preview($this->actor, $this->outlet, 'price', 'xlsx', $formula, 'historical'));
        $denied = new Admin;
        $denied->forceFill(['name' => 'Denied', 'email' => 'bulk-denied@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => []])->save();
        $denied->shops()->attach($this->outlet);
        $this->reject(fn () => $this->bulk->export($denied, $this->outlet, 'catalogue', 'csv'));

        $csv = $this->bulk->export($this->actor, $this->outlet, 'catalogue', 'csv');
        $this->assertStringContainsString("'=2+2", $csv);
        $this->assertStringNotContainsString('seller_cnic', $csv);
        $this->assertStringNotContainsString('seller_phone', $csv);
        $xlsx = $this->bulk->export($this->actor, $this->outlet, 'inventory', 'xlsx');
        $this->assertSame('xlsx-cell-matrix.v1', $xlsx['contract']);
        $this->assertSame(['product_id', 'product_code', 'name', 'track_imei', 'on_hand', 'held', 'available'], $xlsx['rows'][0]);
    }

    public function test_whole_batch_rolls_back_on_late_row_failure_and_replay_is_idempotent(): void
    {
        $before = DB::table('products')->count();
        $duplicate = [
            ['row_key', 'name', 'category', 'purchase_price', 'sale_price', 'warranty_type'],
            ['create-1', 'Bulk duplicate', 'accessory', '10.00', '20.00', 'no_warranty'],
            ['create-2', 'Bulk duplicate', 'accessory', '11.00', '21.00', 'no_warranty'],
        ];
        $failed = $this->bulk->import($this->actor, $this->outlet, 'catalogue', 'xlsx', $duplicate, 'batch-fail');
        $this->assertSame('failed', $failed['status']);
        $this->assertSame(0, $failed['applied']);
        $this->assertSame($before, DB::table('products')->count());

        $product = $this->product();
        $csv = "row_key,product_id,purchase_price,sale_price\r\n"
            ."price-1,{$product->public_id},150.10,250.20\r\n";
        $first = $this->bulk->import($this->actor, $this->outlet, 'price', 'csv', $csv, 'price-replay');
        $version = $product->fresh()->version;
        $second = $this->bulk->import($this->actor, $this->outlet, 'price', 'csv', $csv, 'price-replay');
        $this->assertEquals($first, $second);
        $this->assertSame('completed', $first['status']);
        $this->assertSame('150.10', $product->fresh()->purchase_price);
        $this->assertSame('250.20', $product->fresh()->sale_price);
        $this->assertSame($version, $product->fresh()->version);
    }

    public function test_row_recovery_applies_valid_inventory_once_and_preserves_failed_row_for_recovery(): void
    {
        $product = $this->product();
        $matrix = [
            ['row_key', 'product_id', 'action', 'quantity', 'unit_purchase_price', 'source_type_code', 'business_name', 'seller_phone', 'seller_address', 'reason'],
            ['stock-1', $product->public_id, 'acquire', '1', '123.45', 'supplier', 'Bulk Supplier', '03000000000', 'Synthetic address', 'Bulk receipt'],
            ['stock-2', $product->public_id, 'acquire', '1', '123.45', 'supplier', '', '03000000000', 'Synthetic address', 'Bulk receipt'],
        ];
        $first = $this->bulk->import($this->actor, $this->outlet, 'inventory', 'xlsx', $matrix, 'row-recovery', 'row');
        $this->assertSame('partial', $first['status']);
        $this->assertSame(1, $first['applied']);
        $this->assertSame('applied', $first['rows'][0]['status']);
        $this->assertSame('failed', $first['rows'][1]['status']);
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_acquisitions')->where('product_id', $product->id)->count());

        $second = $this->bulk->import($this->actor, $this->outlet, 'inventory', 'xlsx', $matrix, 'row-recovery', 'row');
        $this->assertSame(1, $second['applied']);
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_acquisitions')->where('product_id', $product->id)->count());
    }

    public function test_inventory_bulk_uses_stock_service_and_cannot_remove_held_quantity(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $reservation = $this->reservation($product);
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($reservation['line']));

        $matrix = [
            ['row_key', 'product_id', 'action', 'quantity', 'reason', 'type'],
            ['held-out', $product->public_id, 'adjust', '2', 'Bulk correction', 'correction_out'],
        ];
        $result = $this->bulk->import($this->actor, $this->outlet, 'inventory', 'xlsx', $matrix, 'held-stock', 'row');
        $this->assertSame('partial', $result['status']);
        $this->assertSame(0, $result['applied']);
        $this->assertSame('failed', $result['rows'][0]['status']);
        $this->assertSame(2, $product->fresh()->qty);
        $snapshot = app(StockLedger::class)->snapshot($product->id);
        $this->assertSame(1, $snapshot['held']);
        $this->assertSame(1, $snapshot['available']);
    }

    public function test_master_data_and_catalogue_imports_route_through_authoritative_services(): void
    {
        $master = [
            ['row_key', 'list', 'action', 'label'],
            ['brand-1', 'product_brand', 'create', 'Bulk Brand'],
        ];
        $masterResult = $this->bulk->import($this->actor, null, 'master_data', 'xlsx', $master, 'master-create');
        $this->assertSame('completed', $masterResult['status']);
        $brand = DB::table('pos_master_data_options')->where('list_key', 'product_brand')->where('label', 'Bulk Brand')->firstOrFail();

        $catalogue = [
            ['row_key', 'name', 'category', 'brand', 'model', 'purchase_price', 'sale_price', 'warranty_type'],
            ['catalogue-1', 'Bulk Device', 'mobile_phone', 'Bulk Brand', 'Bulk Model', '321.09', '654.32', 'no_warranty'],
        ];
        $created = $this->bulk->import($this->actor, $this->outlet, 'catalogue', 'xlsx', $catalogue, 'catalogue-create');
        $this->assertSame('completed', $created['status']);
        $product = DB::table('products')->where('name', 'Bulk Device')->firstOrFail();
        $this->assertSame($brand->id, $product->brand_master_data_id);
        $this->assertSame('321.09', $product->purchase_price);
        $this->assertSame('654.32', $product->sale_price);
        $this->assertSame(0, $product->qty);
    }
}
