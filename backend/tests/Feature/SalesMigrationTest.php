<?php

namespace Tests\Feature;

use App\Migration\SalesImporter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class SalesMigrationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private int $run;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('1', 64), 'code_hash' => str_repeat('2', 64),
            'schema_hash' => str_repeat('3', 64), 'target_identity' => 'mobisttech_test', 'status' => 'sales_rehearsal', 'started_at' => now()]);
        $this->map('users', 7, 'outlets', $this->outlet->id);
    }

    public function test_invoice_and_sale_history_preserve_exact_snapshots_relationships_timezone_and_replay(): void
    {
        $product = $this->product();
        $this->map('products', 8, 'products', $product->id);
        $invoice = $this->row('invoices', ['id' => 9, 'shop_id' => 7, 'total_bill' => '200.02', 'discount' => '0.01', 'final_bill' => '200.01',
            'customer_name' => 'Historic buyer', 'invoice_number' => 'MST-OLD-9', 'created_at' => '2026-08-31 12:00:00']);
        $importer = app(SalesImporter::class);
        $result = $importer->import($this->run, 'invoices', $invoice, 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $sale = $this->row('sales', ['id' => 10, 'product_id' => 8, 'shop_id' => 7, 'invoice_id' => 9, 'sale_date' => '2026-08-31',
            'sale_price' => '200.02', 'quantity' => 1, 'total_price' => '200.02', 'purchase_price' => '250.00',
            'discount_allocated' => '0.01', 'net_total_price' => '200.01', 'profit' => '-49.99']);
        $saleResult = $importer->import($this->run, 'sales', $sale, 'Asia/Karachi');
        $this->assertSame('imported', $saleResult['outcome'], json_encode($saleResult));
        $this->assertSame('already_imported', $importer->import($this->run, 'sales', $sale, 'Asia/Karachi')['outcome']);
        $this->assertSame('2026-08-31 07:00:00.000000', DB::table('invoices')->where('id', $result['target_id'])->value('created_at'));
        $this->assertSame('-49.99', DB::table('sales')->where('id', $saleResult['target_id'])->value('profit'));
        $this->assertTrue(Str::isUuid(DB::table('sales')->where('id', $saleResult['target_id'])->value('public_id')));
        $this->assertSame(0, DB::table('sales')->where('id', $saleResult['target_id'])->value('returned_quantity'));
    }

    public function test_bad_arithmetic_unresolved_parents_changed_replay_and_unknown_fields_quarantine(): void
    {
        $invoice = $this->row('invoices', ['id' => 20, 'shop_id' => 7, 'total_bill' => '100.00', 'discount' => '1.00', 'final_bill' => '100.00']);
        $this->assertSame('quarantined', app(SalesImporter::class)->import($this->run, 'invoices', $invoice, 'UTC')['outcome']);
        $this->assertSame('quarantined', app(SalesImporter::class)->import($this->run, 'invoices', [...$invoice, 'id' => 21, 'extra' => 'bad'], 'UTC')['outcome']);
        $sale = $this->row('sales', ['id' => 22, 'product_id' => 999, 'shop_id' => 7, 'invoice_id' => 999, 'sale_date' => '2026-08-31',
            'sale_price' => '100.00', 'quantity' => 1, 'total_price' => '100.00', 'purchase_price' => '50.00', 'discount_allocated' => '0.00',
            'net_total_price' => '100.00', 'profit' => '50.00']);
        $this->assertSame('quarantined', app(SalesImporter::class)->import($this->run, 'sales', $sale, 'UTC')['outcome']);
        $this->assertSame(0, DB::table('invoices')->count());
        $this->assertSame(0, DB::table('sales')->count());
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
                'boolean' => false, 'integer', 'bigInteger', 'smallInteger', 'tinyInteger' => 1, 'decimal' => '0.00', 'date' => '2026-08-31', default => 'Synthetic',
            };
        }

        return [...$row, ...$overrides];
    }
}
