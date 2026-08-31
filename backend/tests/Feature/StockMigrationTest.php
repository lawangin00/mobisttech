<?php

namespace Tests\Feature;

use App\Inventory\InventoryOperations;
use App\Migration\StockImporter;
use App\Models\StockUnit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class StockMigrationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private int $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('1', 64), 'code_hash' => str_repeat('2', 64),
            'schema_hash' => str_repeat('3', 64), 'target_identity' => 'mobisttech_test', 'status' => 'stock_rehearsal', 'started_at' => now()]);
        $this->map('users', 7, 'outlets', $this->outlet->id);
    }

    public function test_stock_acquisition_and_movements_preserve_money_source_identity_timezone_and_replay(): void
    {
        $product = $this->product();
        $this->map('products', 8, 'products', $product->id);
        $row = $this->row('stock_acquisitions', ['product_id' => 8, 'shop_id' => 7, 'source_type' => 'supplier', 'business_name' => 'Synthetic historic supplier',
            'unit_purchase_price' => '123.45', 'quantity' => 2, 'acquired_at' => '2026-08-31 12:00:00']);
        $importer = app(StockImporter::class);
        $result = $importer->import($this->run, 'stock_acquisitions', $row, 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $this->assertSame('already_imported', $importer->import($this->run, 'stock_acquisitions', $row, 'Asia/Karachi')['outcome']);
        $this->assertSame('quarantined', $importer->import($this->run, 'stock_acquisitions', [...$row, 'quantity' => 3], 'Asia/Karachi')['outcome']);
        $record = DB::table('stock_acquisitions')->where('id', $result['target_id'])->first();
        $this->assertSame('123.45', $record->unit_purchase_price);
        $this->assertSame('2026-08-31 07:00:00.000000', $record->acquired_at);
        $movement = $this->row('stock_movements', ['product_id' => 8, 'shop_id' => 7, 'type' => 'restock', 'quantity_change' => 2, 'stock_before' => 0, 'stock_after' => 2,
            'reference_type' => 'App\\Models\\StockAcquisition', 'reference_id' => 1, 'created_at' => '2026-08-31 12:00:00']);
        $result = $importer->import($this->run, 'stock_movements', $movement, 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        // Snapshot counters are imported independently; the stock importer does not replay history as new transactions.
        $this->assertSame(0, $product->fresh()->qty);
        DB::table('products')->where('id', $product->id)->update(['qty' => 2]);
        $this->assertSame('reconciled_recorded_history', $importer->reconcile($product->id)['movement_coverage']);
        $this->assertSame(2, $importer->reconcile($product->id)['available']);
    }

    public function test_active_imei_claims_rebuild_from_mapped_history_and_duplicate_or_unmapped_rows_quarantine(): void
    {
        $product = $this->product(true);
        $this->acquire($product, 2);
        $units = StockUnit::where('product_id', $product->id)->orderBy('id')->get();
        $this->map('products', 8, 'products', $product->id);
        $this->map('stock_units', 9, 'stock_units', $units[0]->id);
        $this->map('stock_units', 10, 'stock_units', $units[1]->id);
        $row = $this->row('product_imeis', ['product_id' => 8, 'stock_unit_id' => 9, 'device_no' => 1, 'slot_no' => 1, 'status' => 'in_stock', 'imei' => 'historic-imei-1']);
        $importer = app(StockImporter::class);
        $this->assertSame('imported', $importer->import($this->run, 'product_imeis', $row, 'UTC')['outcome']);
        $this->assertSame('already_imported', $importer->import($this->run, 'product_imeis', $row, 'UTC')['outcome']);
        foreach ([['id' => 2, 'stock_unit_id' => 10, 'device_no' => 2], ['id' => 3, 'product_id' => 999], ['id' => 4, 'status' => 'invented'], ['id' => 5, 'slot_no' => 0], ['id' => 6, 'stock_unit_id' => null]] as $bad) {
            $this->assertSame('quarantined', $importer->import($this->run, 'product_imeis', [...$row, ...$bad], 'UTC')['outcome']);
        }
        $this->assertSame(1, DB::table('active_imeis')->count());
        $this->assertSame(1, DB::table('product_imeis')->count());
        $this->assertSame('imported', $importer->import($this->run, 'product_imeis', [...$row, 'id' => 7, 'slot_no' => 2, 'imei' => 'historic-imei-2'], 'UTC')['outcome']);
        $snapshot = $importer->reconcile($product->id);
        $this->assertSame(1, $snapshot['available']);
        $this->assertSame(1, $snapshot['incomplete_units']);
        $this->assertSame(5, DB::table('migration_quarantine')->count());
    }

    public function test_source_private_paths_bad_arithmetic_unknown_columns_and_unresolved_references_are_not_guessed(): void
    {
        $product = $this->product();
        $this->map('products', 8, 'products', $product->id);
        $acquisition = $this->row('stock_acquisitions', ['product_id' => 8, 'shop_id' => 7, 'source_type' => 'supplier', 'quantity' => 1, 'acquired_at' => '2026-08-31 12:00:00']);
        foreach ([['cnic_front_path' => 'private/source-file.jpg'], ['quantity' => 0], ['extra' => 'field'], ['unit_purchase_price' => 1.1]] as $bad) {
            $this->assertSame('quarantined', app(StockImporter::class)->import($this->run, 'stock_acquisitions', [...$acquisition, ...$bad], 'UTC')['outcome']);
        }
        $movement = $this->row('stock_movements', ['product_id' => 8, 'shop_id' => 7, 'type' => 'restock', 'quantity_change' => 1, 'stock_before' => 0, 'stock_after' => 1]);
        foreach ([['stock_after' => 2], ['reference_type' => 'guessed', 'reference_id' => 1], ['reference_type' => 'App\\Models\\StockAcquisition', 'reference_id' => 999]] as $bad) {
            $this->assertSame('quarantined', app(StockImporter::class)->import($this->run, 'stock_movements', [...$movement, ...$bad], 'UTC')['outcome']);
        }
        $this->assertSame(0, DB::table('stock_acquisitions')->count());
        $this->assertSame(0, DB::table('stock_movements')->count());
        $this->assertSame('no_history_requires_baseline_approval', app(StockImporter::class)->reconcile($product->id)['movement_coverage']);
    }

    public function test_historical_imei_occurrences_remain_separate_from_current_global_claim(): void
    {
        $product = $this->product(true);
        $this->acquire($product, 2);
        $this->map('products', 8, 'products', $product->id);
        $units = StockUnit::where('product_id', $product->id)->orderBy('id')->get();
        foreach ($units as $index => $unit) {
            app(InventoryOperations::class)->adjust($this->actor, $this->outlet, $product->public_id, 'historic-'.$index,
                ['type' => 'damaged', 'quantity' => 1, 'unit_id' => $unit->public_id, 'reason' => 'Synthetic historical occurrence']);
            $this->map('stock_units', 9 + $index, 'stock_units', $unit->id);
            $row = $this->row('product_imeis', ['id' => 1 + $index, 'product_id' => 8, 'stock_unit_id' => 9 + $index,
                'device_no' => $unit->unit_no, 'slot_no' => 1, 'status' => 'sold', 'imei' => 'same-historical-imei']);
            $this->assertSame('imported', app(StockImporter::class)->import($this->run, 'product_imeis', $row, 'UTC')['outcome']);
        }
        $this->assertSame(0, DB::table('active_imeis')->count());
        $this->acquire($product);
        $current = StockUnit::where('product_id', $product->id)->where('status', 'in_stock')->firstOrFail();
        $this->imeis($product, $current, [1 => 'same-historical-imei', 2 => 'current-secondary-imei']);
        $this->assertSame(3, DB::table('product_imeis')->where('imei', 'same-historical-imei')->count());
        $this->assertSame(1, DB::table('active_imeis')->where('imei', 'same-historical-imei')->count());
        $this->assertSame(1, app(StockImporter::class)->reconcile($product->id)['available']);
    }

    private function map(string $source, int $old, string $target, int $id): void
    {
        DB::table('migration_identity_map')->insert(['source_repository' => 'pos', 'source_table' => $source, 'source_primary_key' => (string) $old,
            'target_table' => $target, 'target_id' => (string) $id, 'run_id' => $this->run, 'row_digest' => str_repeat('a', 64), 'outcome' => 'synthetic_parent']);
    }

    private function row(string $table, array $overrides): array
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === 'pos' && $entry['table'] === $table);
        $row = [];
        foreach ($entry['columns'] as $column) {
            $type = $column['target_type'];
            $row[$column['source_column']] = $type['nullable'] ? null : match ($type['type']) {
                'boolean' => false, 'integer', 'bigInteger', 'smallInteger', 'tinyInteger' => 1, 'decimal' => '0.00', default => 'Synthetic',
            };
        }

        return [...$row, ...$overrides];
    }
}
