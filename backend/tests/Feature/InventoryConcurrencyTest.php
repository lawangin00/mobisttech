<?php

namespace Tests\Feature;

use App\Inventory\AcquisitionDocuments;
use App\Inventory\StockLedger;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Procurement\SupplierProcurement;
use App\Sales\SalesOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use InventoryFixture;

    // These tests deliberately commit fixtures so independent MySQL connections can see them.
    private array $tables = ['identity_audit_events', 'purchase_order_events', 'acquisition_source_references', 'purchase_order_receipt_lines', 'purchase_order_receipts',
        'purchase_order_lines', 'purchase_orders', 'supplier_contacts', 'reorder_policies', 'suppliers', 'domain_events', 'publication_versions', 'idempotency_requests', 'claim_events', 'claims', 'return_lines', 'returns', 'monetary_adjustments',
        'stock_unit_lineage', 'reservation_allocations', 'reservation_lines', 'reservations', 'product_imeis', 'active_imeis', 'stock_movements', 'stock_units',
        'stock_acquisitions', 'sales', 'invoices', 'order_items', 'orders', 'customers', 'document_sequences', 'pos_master_data_usages', 'products',
        'pos_master_data_options', 'outlet_admins', 'outlets', 'admins', 'super_admins'];

    private bool $ownsFixtures = false;

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::selectOne('SELECT DATABASE() db, @@port port');
        $this->assertSame('mobisttech_test', $db->db);
        $this->assertSame(13306, (int) $db->port);
        $this->assertSame(0, DB::transactionLevel());
        foreach ($this->tables as $table) {
            $this->assertSame(0, DB::table($table)->count(), 'Concurrency fixtures require an empty dedicated test schema: '.$table);
        }
        $this->ownsFixtures = true;
        $this->inventoryFixture();
    }

    protected function tearDown(): void
    {
        if ($this->ownsFixtures) {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            // Only fixture tables proven empty before this test, with foreign keys still enforced.
            foreach ($this->tables as $table) {
                DB::table($table)->delete();
            }
        }
        parent::tearDown();
    }

    public function test_separate_mysql_connections_arbitrate_quantity_sale_versus_reservation(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $reservation = $this->reservation($product);
        $sale = $this->sale($product);
        $results = $this->race([$product->id], [['operation' => 'sale', 'id' => $sale], ['operation' => 'reserve', 'id' => $reservation['line']]]);
        $this->oneWinner($results);
        $snapshot = DB::transaction(fn () => app(StockLedger::class)->snapshot($product->id));
        $this->assertSame(0, $snapshot['available']);
        $this->assertSame(1, $snapshot['held'] + $product->fresh()->sold_qty);
    }

    public function test_separate_mysql_connections_cannot_reserve_the_same_physical_unit_twice(): void
    {
        $product = $this->product(true);
        $this->acquire($product);
        $this->imeis($product);
        $a = $this->reservation($product);
        $b = $this->reservation($product);
        $results = $this->race([$product->id], [['operation' => 'reserve', 'id' => $a['line']], ['operation' => 'reserve', 'id' => $b['line']]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(2, DB::table('active_imeis')->count());
    }

    public function test_separate_products_competing_for_global_imei_claim_roll_back_loser(): void
    {
        $products = [$this->product(true), $this->product(true)];
        $requests = [];
        foreach ($products as $product) {
            $this->acquire($product);
            $requests[] = ['operation' => 'imeis', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id, 'product' => $product->public_id,
                'unit' => StockUnit::where('product_id', $product->id)->value('public_id'), 'key' => $product->public_id];
        }
        $results = $this->race(array_map(fn ($product) => $product->id, $products), $requests);
        $this->oneWinner($results);
        $this->assertSame(2, DB::table('active_imeis')->count());
        $this->assertSame(2, DB::table('product_imeis')->count());
        $this->assertSame(1, DB::table('active_imeis')->distinct()->count('stock_unit_id'));
        $this->assertSame(1, DB::table('idempotency_requests')->where('operation', 'inventory.imeis')->count());
    }

    public function test_separate_mysql_connections_cannot_accept_the_same_quantity_return_twice(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $input = ['invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic concurrent return', 'lines' => [[
            'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable',
        ]]];
        $request = ['operation' => 'return', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id, 'input' => $input];
        $results = $this->race([$product->id], [[...$request, 'key' => (string) Str::uuid()], [...$request, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('returns')->count());
        $this->assertSame(1, DB::table('return_lines')->count());
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(0, $product->fresh()->sold_qty);
    }

    public function test_separate_mysql_connections_cannot_open_claims_beyond_the_last_eligible_quantity(): void
    {
        $product = $this->product();
        $product->forceFill(['warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30])->save();
        $this->acquire($product);
        $sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $input = ['sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Synthetic concurrent warranty issue'];
        $request = ['operation' => 'claim', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id, 'input' => $input];
        $results = $this->race([$product->id], [[...$request, 'key' => (string) Str::uuid()], [...$request, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('claims')->count());
        $this->assertSame(1, DB::table('claim_events')->count());
        $this->assertSame('received', DB::table('claims')->value('status'));
        $this->assertSame(1, DB::table('claims')->value('quantity'));
    }

    public function test_private_acquisition_evidence_is_scoped_and_never_a_client_selected_path(): void
    {
        Storage::fake('local');
        $product = $this->product();
        $acquisition = $this->acquire($product)['acquisition_id'];
        $service = app(AcquisitionDocuments::class);
        $bytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS1sAAAAASUVORK5CYII=');
        $this->reject(fn () => $service->attach($this->actor, $this->outlet, $acquisition, 'front', '<svg>invalid</svg>'));
        $service->attach($this->actor, $this->outlet, $acquisition, 'front', $bytes);
        $this->assertSame($bytes, $service->read($this->actor, $this->outlet, $acquisition, 'front'));
        $this->reject(fn () => $service->read($this->actor, $this->outlet, $acquisition, '../../private'));
        $this->reject(fn () => $service->read($this->actor, $this->outlet, $acquisition, 'back'));
        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other synthetic outlet', 'outlet_code' => '025'])->save();
        try {
            $this->reject(fn () => $service->read($this->actor, $other, $acquisition, 'front'));
        } finally {
            $other->delete();
        }
    }

    public function test_separate_mysql_connections_cannot_receive_the_same_purchase_order_quantity_twice(): void
    {
        $product = $this->product();
        $service = app(SupplierProcurement::class);
        $supplier = $service->createSupplier($this->actor, $this->outlet, (string) Str::uuid(), [
            'supplier_code' => 'RACE-SUP', 'name' => 'Race Supplier', 'phone' => '03000000000', 'address' => 'Synthetic address',
        ]);
        $order = $service->createOrder($this->actor, $this->outlet, (string) Str::uuid(), ['supplier_id' => $supplier['supplier_id'], 'lines' => [[
            'product_id' => $product->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
        ]]]);
        $line = DB::table('purchase_order_lines')->where('purchase_order_id', DB::table('purchase_orders')->where('public_id', $order['purchase_order_id'])->value('id'))->value('public_id');
        $request = ['operation' => 'procurement_receive', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
            'order' => $order['purchase_order_id'], 'input' => ['lines' => [[
                'line_id' => $line, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
            ]]]];
        $results = $this->race([$product->id], [[...$request, 'key' => (string) Str::uuid()], [...$request, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('purchase_order_receipts')->count());
        $this->assertSame(1, DB::table('stock_acquisitions')->count());
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame('received', DB::table('purchase_orders')->where('public_id', $order['purchase_order_id'])->value('status'));
    }

    private function race(array $products, array $requests): array
    {
        $processes = [];
        $results = [];
        DB::beginTransaction();
        DB::table('products')->whereIn('id', $products)->orderBy('id')->lockForUpdate()->get();
        try {
            foreach ($requests as $request) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/stock_worker.php'), json_encode($request, JSON_THROW_ON_ERROR)], base_path(), timeout: 20);
                $process->start();
                $processes[] = $process;
            }
            foreach ($processes as $process) {
                $deadline = microtime(true) + 10;
                while (! str_contains($process->getOutput(), 'ATTEMPT') && $process->isRunning() && microtime(true) < $deadline) {
                    usleep(10000);
                }
                $this->assertStringContainsString('ATTEMPT', $process->getOutput(), $process->getErrorOutput());
                $this->assertTrue($process->isRunning(), 'Worker must contend while parent holds the product lock.');
            }
            DB::commit();
            $connections = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getOutput().$process->getErrorOutput());
                $lines = array_values(array_filter(explode("\n", trim($process->getOutput())), fn ($line) => str_starts_with($line, '{')));
                $connections[] = json_decode($lines[0], true)['connection'];
                $results[] = json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertCount(2, array_unique($connections));

            return $results;
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(1);
                }
            }
        }
    }

    private function oneWinner(array $results): void
    {
        $outcomes = array_column($results, 'outcome');
        sort($outcomes);
        $this->assertSame(['committed', 'rejected'], $outcomes, json_encode($results));
    }
}
