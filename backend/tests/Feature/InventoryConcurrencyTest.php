<?php

namespace Tests\Feature;

use App\Cash\CashSessionOperations;
use App\Catalog\ProductDefinitions;
use App\Inventory\AcquisitionDocuments;
use App\Inventory\StockLedger;
use App\Inventory\StocktakeOperations;
use App\Inventory\StockTransferOperations;
use App\Loyalty\LoyaltyServices;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Payments\PosPaymentOperations;
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
    private array $tables = ['loyalty_claim_lots', 'loyalty_earn_lots', 'loyalty_entries', 'loyalty_claims', 'loyalty_accounts', 'loyalty_configurations', 'cash_entries', 'pos_refund_allocations', 'pos_settlement_events', 'pos_tender_allocations', 'cash_sessions', 'pos_payment_destinations', 'identity_audit_events', 'stock_transfer_receipt_lines', 'stock_transfer_receipts', 'stock_transfer_units', 'stock_transfer_lines', 'stock_transfers', 'stocktake_approvals', 'stocktake_recounts', 'stocktake_counts', 'stocktake_unit_baselines', 'stocktake_lines', 'stocktake_sessions', 'purchase_order_events', 'acquisition_source_references', 'purchase_order_receipt_lines', 'purchase_order_receipts',
        'purchase_order_lines', 'purchase_orders', 'supplier_contacts', 'reorder_policies', 'suppliers', 'domain_events', 'publication_versions', 'idempotency_requests', 'claim_events', 'claims', 'return_lines', 'returns', 'monetary_adjustments',
        'inventory_custody_holds', 'stock_unit_lineage', 'reservation_allocations', 'reservation_lines', 'reservations', 'product_imeis', 'active_imeis', 'stock_movements', 'stock_units',
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

    public function test_separate_mysql_connections_arbitrate_stocktake_approval_versus_sale(): void
    {
        $product = $this->product();
        $this->acquire($product);
        [$stocktake, $version] = $this->submittedStocktake($product);
        $sale = $this->sale($product);
        $results = $this->race([$product->id], [
            ['operation' => 'stocktake_approve', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
                'stocktake' => $stocktake, 'key' => (string) Str::uuid(), 'input' => ['session_version' => $version]],
            ['operation' => 'sale', 'id' => $sale],
        ]);
        $this->oneWinner($results);
        $this->assertSame(0, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->whereIn('type', ['sale', 'stocktake_adjustment'])->count());
        $this->assertSame(1, DB::table('stocktake_approvals')->count() + $product->fresh()->sold_qty);
    }

    public function test_separate_mysql_connections_arbitrate_stocktake_approval_versus_reservation(): void
    {
        $product = $this->product();
        $this->acquire($product);
        [$stocktake, $version] = $this->submittedStocktake($product);
        $reservation = $this->reservation($product);
        $results = $this->race([$product->id], [
            ['operation' => 'stocktake_approve', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
                'stocktake' => $stocktake, 'key' => (string) Str::uuid(), 'input' => ['session_version' => $version]],
            ['operation' => 'reserve', 'id' => $reservation['line']],
        ]);
        $this->oneWinner($results);
        $snapshot = DB::transaction(fn () => app(StockLedger::class)->snapshot($product->id));
        $this->assertSame(0, $snapshot['available']);
        $this->assertSame(1, DB::table('stocktake_approvals')->count() + DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertContains($product->fresh()->qty, [0, 1]);
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

    public function test_separate_mysql_connections_cannot_receive_the_same_transfer_twice(): void
    {
        $source = $this->product();
        $this->acquire($source);
        $destination = new Outlet;
        $destination->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Race destination', 'outlet_code' => '026'])->save();
        $this->actor->shops()->attach($destination);
        $target = app(ProductDefinitions::class)->save($this->actor, $destination, [
            'name' => $source->name, 'category' => $source->category, 'brand_master_data_id' => $source->brand_master_data_id,
            'subcategory_master_data_id' => $source->subcategory_master_data_id, 'model' => $source->model,
            'purchase_price' => $source->purchase_price, 'sale_price' => $source->sale_price, 'track_imei' => false,
            'ram_master_data_id' => null, 'storage_master_data_id' => null, 'sim_master_data_id' => null, 'warranty_type' => 'no_warranty',
        ]);
        $service = app(StockTransferOperations::class);
        $created = $service->create($this->actor, $this->outlet, (string) Str::uuid(), [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'quantity' => 1]],
        ]);
        $dispatch = $service->dispatch($this->actor, $this->outlet, $created['transfer_id'], (string) Str::uuid(), ['transfer_version' => 1]);
        $line = $service->details($this->actor, $created['transfer_id'])['lines'][0];
        $request = ['operation' => 'transfer_receive', 'actor' => $this->actor->id, 'outlet' => $destination->id,
            'transfer' => $created['transfer_id'], 'input' => ['transfer_version' => $dispatch['version'],
                'lines' => [['line_id' => $line['line_id'], 'receive_quantity' => 1, 'reject_quantity' => 0]]]];
        $results = $this->race([$source->id, $target->id], [[...$request, 'key' => (string) Str::uuid()], [...$request, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('stock_transfer_receipts')->count());
        $this->assertSame(0, $source->fresh()->qty);
        $this->assertSame(1, $target->fresh()->qty);
        $this->assertSame(0, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
        $this->assertSame(2, DB::table('stock_movements')->whereIn('type', ['transfer_out', 'transfer_in'])->count());
    }

    public function test_separate_mysql_connections_replay_same_pos_split_tender_once(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $service = app(PosPaymentOperations::class);
        app(CashSessionOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '0.00']);
        $cash = $service->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'cash', 'display_name' => 'Race Cash Drawer',
        ]);
        $card = $service->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'card', 'display_name' => 'Race Card Terminal', 'masked_identifier' => 'TERM-01',
        ]);
        $key = 'mt220-race-'.Str::uuid();
        $input = ['sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]], 'payments' => [
            ['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '50.02', 'cash_tendered' => '60.02'],
            ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '150.00', 'transaction_reference' => 'APP-RACE-1'],
        ]];
        $request = ['operation' => 'pos_payment_sale', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id, 'key' => $key, 'input' => $input];
        $results = $this->race([$product->id], [$request, $request]);
        $this->assertSame(['committed', 'committed'], array_column($results, 'outcome'));
        $this->assertSame($results[0]['result']['invoice_id'], $results[1]['result']['invoice_id']);
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(2, DB::table('pos_tender_allocations')->count());
        $this->assertSame(1, $product->fresh()->sold_qty);
    }

    public function test_separate_mysql_connections_allow_only_one_different_key_pos_sale_for_last_stock(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $service = app(PosPaymentOperations::class);
        app(CashSessionOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '0.00']);
        $cash = $service->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'cash', 'display_name' => 'Last Stock Cash Drawer',
        ]);
        $input = ['sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '200.02', 'cash_tendered' => '200.02']]];
        $base = ['operation' => 'pos_payment_sale', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id, 'input' => $input];
        $results = $this->race([$product->id], [[...$base, 'key' => (string) Str::uuid()], [...$base, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, DB::table('pos_tender_allocations')->count());
        $this->assertSame(1, $product->fresh()->sold_qty);
    }

    public function test_separate_mysql_connections_cannot_close_same_cash_session_twice(): void
    {
        $product = $this->product();
        $session = app(CashSessionOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '100.00']);
        $base = ['operation' => 'cash_close', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
            'session' => $session['session_id'], 'barrier_product' => $product->id,
            'input' => ['session_version' => 1, 'actual_cash' => '100.00']];
        $results = $this->race([$product->id], [[...$base, 'key' => (string) Str::uuid()], [...$base, 'key' => (string) Str::uuid()]]);
        $this->oneWinner($results);
        $this->assertSame('closed', DB::table('cash_sessions')->where('public_id', $session['session_id'])->value('status'));
        $this->assertSame(1, DB::table('cash_sessions')->where('public_id', $session['session_id'])->count());
    }

    public function test_cash_close_vs_sale_never_omits_committed_cash(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $session = app(CashSessionOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '0.00']);
        $cash = app(PosPaymentOperations::class)->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'cash', 'display_name' => 'Closing Race Drawer',
        ]);
        $close = ['operation' => 'cash_close', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
            'session' => $session['session_id'], 'key' => (string) Str::uuid(), 'barrier_product' => $product->id,
            'input' => ['session_version' => 1, 'actual_cash' => '0.00', 'variance_reason' => 'Synthetic close race']];
        $sale = ['operation' => 'pos_payment_sale', 'actor' => $this->actor->id, 'outlet' => $this->outlet->id,
            'key' => (string) Str::uuid(), 'barrier_product' => $product->id, 'input' => [
                'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
                'payments' => [['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '200.02', 'cash_tendered' => '200.02']],
            ]];
        $results = $this->race([$product->id], [$close, $sale]);
        $this->assertContains('committed', array_column($results, 'outcome'));
        $closed = json_decode(DB::table('cash_sessions')->where('public_id', $session['session_id'])->value('closing_snapshot'), true);
        $sold = DB::table('invoices')->count() === 1;
        $this->assertSame($sold ? '200.02' : '0.00', $closed['expected_cash']);
        $this->assertSame($sold ? 1 : 0, DB::table('pos_tender_allocations')->whereNotNull('cash_session_id')->count());
    }

    public function test_separate_mysql_connections_replay_same_loyalty_redemption_once(): void
    {
        $product = $this->product();
        [$customer, $invoice] = $this->loyaltyBalance();
        $owner = hash('sha256', 'same-loyalty-owner');
        $request = ['operation' => 'loyalty_claim', 'barrier_product' => $product->id, 'customer' => $customer,
            'owner' => $owner, 'points' => 10, 'max' => '200.02', 'bases' => ['200.02']];
        $results = $this->race([$product->id], [$request, $request]);
        $this->assertSame(['committed', 'committed'], array_column($results, 'outcome'));
        $this->assertSame($results[0]['result']['applications'][0]['claim_id'], $results[1]['result']['applications'][0]['claim_id']);
        $this->assertSame(1, DB::table('loyalty_claims')->count());
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'redeem')->count());
        $this->assertSame(10, (int) DB::table('loyalty_accounts')->where('customer_id', $customer)->value('balance_points'));
        $this->assertNotNull($invoice);
    }

    public function test_separate_mysql_connections_arbitrate_competing_loyalty_redemptions(): void
    {
        $product = $this->product();
        [$customer] = $this->loyaltyBalance();
        $base = ['operation' => 'loyalty_claim', 'barrier_product' => $product->id, 'customer' => $customer,
            'points' => 15, 'max' => '200.02', 'bases' => ['200.02']];
        $results = $this->race([$product->id], [
            [...$base, 'owner' => hash('sha256', 'loyalty-a')], [...$base, 'owner' => hash('sha256', 'loyalty-b')],
        ]);
        $this->oneWinner($results);
        $this->assertSame(1, DB::table('loyalty_claims')->count());
        $this->assertSame(5, (int) DB::table('loyalty_accounts')->where('customer_id', $customer)->value('balance_points'));
        $this->assertSame(15, (int) DB::table('loyalty_entries')->where('entry_type', 'redeem')->sum('points'));
    }

    public function test_separate_mysql_connections_replay_loyalty_earn_and_return_reversal(): void
    {
        $product = $this->product();
        $this->loyaltyConfig();
        $customer = $this->loyaltyCustomer();
        $invoice = DB::table('invoices')->insertGetId(['outlet_id' => $this->outlet->id, 'total_bill' => '200.02',
            'discount' => '0.00', 'final_bill' => '200.02', 'customer_id' => $customer, 'public_id' => (string) Str::uuid(), 'currency' => 'PKR']);
        $earn = ['operation' => 'loyalty_earn', 'barrier_product' => $product->id, 'invoice' => $invoice];
        $results = $this->race([$product->id], [$earn, $earn]);
        $this->assertSame(['committed', 'committed'], array_column($results, 'outcome'));
        $this->assertSame(20, (int) DB::table('loyalty_accounts')->where('customer_id', $customer)->value('balance_points'));
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'earn')->count());

        $sale = DB::table('sales')->insertGetId(['public_id' => (string) Str::uuid(), 'product_id' => $product->id,
            'outlet_id' => $this->outlet->id, 'invoice_id' => $invoice, 'sale_date' => now()->toDateString(),
            'sale_price' => '200.02', 'quantity' => 1, 'returned_quantity' => 1, 'total_price' => '200.02',
            'purchase_price' => '100.01', 'discount_allocated' => '0.00', 'net_total_price' => '200.02', 'profit' => '100.01']);
        $return = DB::table('returns')->insertGetId(['invoice_id' => $invoice, 'actor_type' => $this->actor::class,
            'actor_id' => $this->actor->id, 'reason' => 'Synthetic concurrent reversal', 'status' => 'accepted',
            'idempotency_key' => (string) Str::uuid(), 'public_id' => (string) Str::uuid()]);
        $snapshot = ['contract' => 'sale-return.v1', 'invoice_id' => DB::table('invoices')->where('id', $invoice)->value('public_id'),
            'sale_id' => DB::table('sales')->where('id', $sale)->value('public_id'), 'quantity' => 1, 'unit_price' => '200.02',
            'discount_amount' => '0.00', 'net_amount' => '200.02', 'purchase_amount' => '100.01', 'currency' => 'PKR'];
        DB::table('return_lines')->insert(['public_id' => (string) Str::uuid(), 'return_id' => $return, 'invoice_id' => $invoice,
            'sale_id' => $sale, 'quantity' => 1, 'unit_price' => '200.02', 'discount_amount' => '0.00', 'net_amount' => '200.02',
            'purchase_amount' => '100.01', 'currency' => 'PKR', 'sale_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'condition' => 'opened',
            'disposition' => 'sellable', 'accepted_at' => now()]);
        $reverse = ['operation' => 'loyalty_reverse', 'barrier_product' => $product->id, 'return' => $return];
        $results = $this->race([$product->id], [$reverse, $reverse]);
        $this->assertSame(['committed', 'committed'], array_column($results, 'outcome'));
        $this->assertSame(0, (int) DB::table('loyalty_accounts')->where('customer_id', $customer)->value('balance_points'));
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'earn_return_reversal')->count());
    }

    private function loyaltyBalance(): array
    {
        $this->loyaltyConfig();
        $customer = $this->loyaltyCustomer();
        $invoice = DB::table('invoices')->insertGetId(['outlet_id' => $this->outlet->id, 'total_bill' => '200.02',
            'discount' => '0.00', 'final_bill' => '200.02', 'customer_id' => $customer, 'public_id' => (string) Str::uuid(), 'currency' => 'PKR']);
        app(LoyaltyServices::class)->earnInvoice($invoice);

        return [$customer, $invoice];
    }

    private function loyaltyConfig(): void
    {
        app(LoyaltyServices::class)->configure($this->actor, ['enabled' => true, 'earn_basis_amount' => '100.00',
            'earn_points' => 10, 'redemption_value' => '1.00', 'min_redeem_points' => 1,
            'max_redeem_points' => 100, 'daily_redeem_points' => 100, 'expiry_days' => 30]);
    }

    private function loyaltyCustomer(): int
    {
        return DB::table('customers')->insertGetId(['display_name' => 'Concurrent loyalty customer',
            'email' => 'loyalty-race-'.Str::uuid().'@example.invalid', 'public_id' => (string) Str::uuid()]);
    }

    private function submittedStocktake($product): array
    {
        $service = app(StocktakeOperations::class);
        $started = $service->start($this->actor, $this->outlet, (string) Str::uuid(), ['kind' => 'cycle', 'product_ids' => [$product->public_id]]);
        $line = $service->session($this->actor, $this->outlet, $started['stocktake_id'])['lines'][0]['line_id'];
        $count = $service->countLine($this->actor, $this->outlet, $started['stocktake_id'], $line, (string) Str::uuid(), [
            'line_version' => 1, 'counted_quantity' => 0, 'reason_code' => 'loss', 'reason_notes' => 'Synthetic race variance',
        ]);

        return [$started['stocktake_id'], $count['session_version']];
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
