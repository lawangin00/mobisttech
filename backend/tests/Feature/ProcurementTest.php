<?php

namespace Tests\Feature;

use App\Inventory\StockLedger;
use App\Models\Admin;
use App\Models\Outlet;
use App\Procurement\SupplierProcurement;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ProcurementTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_supplier_profiles_contacts_replay_and_delegated_outlet_permission_are_enforced(): void
    {
        $service = app(SupplierProcurement::class);
        $input = ['supplier_code' => 'sup-01', 'name' => 'Synthetic Supplier', 'phone' => '03000000000',
            'email' => 'supplier@example.invalid', 'address' => 'Synthetic supplier address', 'contacts' => [[
                'name' => 'Primary Contact', 'job_title' => 'Sales', 'phone' => '03110000000', 'is_primary' => true,
            ]]];
        $created = $service->createSupplier($this->actor, $this->outlet, 'supplier-create', $input);
        $this->assertEquals($created, $service->createSupplier($this->actor, $this->outlet, 'supplier-create', $input));
        $this->reject(fn () => $service->createSupplier($this->actor, $this->outlet, 'supplier-create', [...$input, 'name' => 'Changed']));
        $this->assertDatabaseHas('suppliers', ['public_id' => $created['supplier_id'], 'supplier_code' => 'SUP-01', 'version' => 1]);
        $this->assertDatabaseHas('supplier_contacts', ['name' => 'Primary Contact', 'is_primary' => true]);

        $updated = $service->updateSupplier($this->actor, $this->outlet, $created['supplier_id'], 'supplier-update', [
            'version' => 1, 'name' => 'Synthetic Supplier Updated', 'contacts' => [[
                'name' => 'Accounts Contact', 'email' => 'accounts@example.invalid', 'is_primary' => true,
            ]],
        ]);
        $this->assertSame(2, $updated['version']);
        $this->assertDatabaseMissing('supplier_contacts', ['name' => 'Primary Contact']);
        $this->assertDatabaseHas('supplier_contacts', ['name' => 'Accounts Contact']);
        $this->assertSame(2, DB::table('identity_audit_events')->whereIn('action', ['supplier_created', 'supplier_updated'])->count());

        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other procurement outlet', 'outlet_code' => '026'])->save();
        $limited = new Admin;
        $limited->forceFill(['name' => 'Limited', 'email' => 'limited-procurement@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => ['shops.enter']])->save();
        $limited->shops()->attach($this->outlet);
        $this->reject(fn () => $service->createSupplier($this->actor, $other, 'wrong-outlet', $input));
        $this->reject(fn () => $service->createSupplier($limited, $this->outlet, 'wrong-permission', $input));
    }

    public function test_purchase_order_exact_costs_status_and_event_snapshots_are_stable(): void
    {
        $product = $this->product();
        $supplier = $this->supplier();
        $service = app(SupplierProcurement::class);
        $input = ['supplier_id' => $supplier, 'expected_at' => '2026-09-10T12:00:00+05:00', 'notes' => 'Synthetic PO', 'lines' => [[
            'product_id' => $product->public_id, 'quantity' => 5, 'unit_cost' => '100.10', 'landed_unit_cost' => '105.25', 'notes' => 'Primary line',
        ]]];
        $order = $service->createOrder($this->actor, $this->outlet, 'po-create', $input);
        $this->assertEquals($order, $service->createOrder($this->actor, $this->outlet, 'po-create', $input));
        $this->reject(fn () => $service->createOrder($this->actor, $this->outlet, 'po-create', [...$input, 'notes' => 'Changed replay']));
        $this->assertSame('ordered', $order['status']);
        $this->assertStringStartsWith('PO-024-', $order['order_number']);
        $row = DB::table('purchase_orders')->where('public_id', $order['purchase_order_id'])->first();
        $this->assertSame('Synthetic Supplier', $row->supplier_name_snapshot);
        $line = DB::table('purchase_order_lines')->where('purchase_order_id', $row->id)->first();
        $this->assertSame('100.10', $line->ordered_unit_cost);
        $this->assertSame('105.25', $line->planned_landed_unit_cost);
        $event = DB::table('purchase_order_events')->where('purchase_order_id', $row->id)->first();
        $this->assertSame(hash('sha256', $event->snapshot), $event->snapshot_sha256);
        $this->assertSame('ordered', json_decode($event->snapshot, true)['status']);
    }

    public function test_partial_receiving_reconciles_acquisitions_stock_costs_and_supplier_history(): void
    {
        $product = $this->product();
        $supplier = $this->supplier();
        $order = $this->order($supplier, $product, 5);
        $line = DB::table('purchase_order_lines')->where('purchase_order_id', DB::table('purchase_orders')->where('public_id', $order)->value('id'))->first();
        $service = app(SupplierProcurement::class);
        $input = ['notes' => 'First truck', 'lines' => [[
            'line_id' => $line->public_id, 'quantity' => 2, 'unit_cost' => '101.00', 'landed_unit_cost' => '106.50',
        ]]];
        $first = $service->receive($this->actor, $this->outlet, $order, 'receive-1', $input);
        $this->assertEquals($first, $service->receive($this->actor, $this->outlet, $order, 'receive-1', $input));
        $this->assertSame('partially_received', $first['status']);
        $this->assertSame(2, $product->fresh()->qty);
        $acquisition = DB::table('stock_acquisitions')->where('id', $first['acquisitions'][0]['acquisition_id'])->first();
        $this->assertSame(2, $acquisition->quantity);
        $this->assertSame('106.50', $acquisition->unit_purchase_price);
        $this->assertSame('Synthetic Supplier', $acquisition->business_name);
        $receiptLine = DB::table('purchase_order_receipt_lines')->where('acquisition_id', $acquisition->id)->first();
        $this->assertDatabaseHas('acquisition_source_references', ['acquisition_id' => $acquisition->id, 'kind' => 'purchase_order_receipt', 'source_line_id' => $receiptLine->public_id]);

        $second = $service->receive($this->actor, $this->outlet, $order, 'receive-2', ['lines' => [[
            'line_id' => $line->public_id, 'quantity' => 3, 'unit_cost' => '102.00', 'landed_unit_cost' => '107.00',
        ]]]);
        $this->assertSame('received', $second['status']);
        $this->assertSame(5, $product->fresh()->qty);
        $this->assertSame(5, DB::table('purchase_order_lines')->where('id', $line->id)->value('received_quantity'));
        $this->assertCount(2, $service->supplierHistory($this->actor, $this->outlet, $supplier));
        $this->reject(fn () => $service->receive($this->actor, $this->outlet, $order, 'receive-3', $input));
        $this->assertSame(2, DB::table('stock_acquisitions')->count());
    }

    public function test_cancel_is_idempotent_and_partial_cancellation_preserves_received_history(): void
    {
        $product = $this->product();
        $supplier = $this->supplier();
        $order = $this->order($supplier, $product, 3);
        $service = app(SupplierProcurement::class);
        $line = DB::table('purchase_order_lines')->where('purchase_order_id', DB::table('purchase_orders')->where('public_id', $order)->value('id'))->first();
        $service->receive($this->actor, $this->outlet, $order, 'partial', ['lines' => [[
            'line_id' => $line->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
        ]]]);
        $cancelled = $service->cancel($this->actor, $this->outlet, $order, 'cancel', ['reason' => 'Supplier cannot fulfill remainder']);
        $this->assertEquals($cancelled, $service->cancel($this->actor, $this->outlet, $order, 'cancel', ['reason' => 'Supplier cannot fulfill remainder']));
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame('Supplier cannot fulfill remainder', DB::table('purchase_orders')->where('public_id', $order)->value('cancellation_reason'));
        $this->reject(fn () => $service->receive($this->actor, $this->outlet, $order, 'after-cancel', ['lines' => [[
            'line_id' => $line->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
        ]]]));
        $this->assertSame(1, DB::table('purchase_order_receipt_lines')->count());
    }

    public function test_failed_multi_line_receipt_rolls_back_every_stock_and_history_effect(): void
    {
        $products = [$this->product(), $this->product()];
        $supplier = $this->supplier();
        $service = app(SupplierProcurement::class);
        $order = $service->createOrder($this->actor, $this->outlet, 'two-lines', ['supplier_id' => $supplier, 'lines' => array_map(fn ($product) => [
            'product_id' => $product->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
        ], $products)]);
        $lines = DB::table('purchase_order_lines')->where('purchase_order_id', DB::table('purchase_orders')->where('public_id', $order['purchase_order_id'])->value('id'))->orderBy('public_id')->get();
        $this->reject(fn () => $service->receive($this->actor, $this->outlet, $order['purchase_order_id'], 'bad-receipt', ['lines' => [
            ['line_id' => $lines[0]->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00'],
            ['line_id' => $lines[1]->public_id, 'quantity' => 2, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00'],
        ]]));
        $this->assertSame(0, DB::table('stock_acquisitions')->count());
        $this->assertSame(0, DB::table('purchase_order_receipts')->count());
        $this->assertSame([0, 0], array_map(fn ($product) => $product->fresh()->qty, $products));
    }

    public function test_reorder_thresholds_are_versioned_scoped_and_account_for_open_orders(): void
    {
        $product = $this->product();
        $supplier = $this->supplier();
        $service = app(SupplierProcurement::class);
        $policy = $service->setReorderPolicy($this->actor, $this->outlet, $product->public_id, 'policy-1', [
            'reorder_threshold' => 2, 'target_stock' => 10,
        ]);
        $this->assertEquals($policy, $service->setReorderPolicy($this->actor, $this->outlet, $product->public_id, 'policy-1', [
            'reorder_threshold' => 2, 'target_stock' => 10,
        ]));
        $initial = $service->recommendations($this->actor, $this->outlet);
        $this->assertSame('out_of_stock', $initial[0]['stock_status']);
        $this->assertSame(10, $initial[0]['recommended_quantity']);
        $order = $this->order($supplier, $product, 10);
        $covered = $service->recommendations($this->actor, $this->outlet);
        $this->assertSame(10, $covered[0]['incoming']);
        $this->assertSame(0, $covered[0]['recommended_quantity']);
        $this->assertSame('covered_by_open_order', $covered[0]['action']);
        $service->cancel($this->actor, $this->outlet, $order, 'cancel-reorder', ['reason' => 'Synthetic cancellation']);
        $this->assertSame(10, $service->recommendations($this->actor, $this->outlet)[0]['recommended_quantity']);
        $this->reject(fn () => $service->setReorderPolicy($this->actor, $this->outlet, $product->public_id, 'stale', [
            'version' => 99, 'reorder_threshold' => 3, 'target_stock' => 10,
        ]));
    }

    public function test_tracked_receipt_reuses_stock_unit_authority_and_keeps_acquisition_links(): void
    {
        $product = $this->product(true);
        $supplier = $this->supplier();
        $order = $this->order($supplier, $product, 2);
        $line = DB::table('purchase_order_lines')->where('purchase_order_id', DB::table('purchase_orders')->where('public_id', $order)->value('id'))->first();
        $receipt = app(SupplierProcurement::class)->receive($this->actor, $this->outlet, $order, 'tracked-receipt', ['lines' => [[
            'line_id' => $line->public_id, 'quantity' => 2, 'unit_cost' => '100.00', 'landed_unit_cost' => '103.00',
        ]]]);
        $acquisition = $receipt['acquisitions'][0]['acquisition_id'];
        $this->assertSame(2, DB::table('stock_units')->where('stock_acquisition_id', $acquisition)->count());
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertSame(2, app(StockLedger::class)->snapshot($product->id)['incomplete_units']);
    }

    private function supplier(): string
    {
        return app(SupplierProcurement::class)->createSupplier($this->actor, $this->outlet, (string) Str::uuid(), [
            'supplier_code' => 'SUP-'.Str::upper(Str::random(6)), 'name' => 'Synthetic Supplier',
            'phone' => '03000000000', 'address' => 'Synthetic supplier address',
        ])['supplier_id'];
    }

    private function order(string $supplier, $product, int $quantity): string
    {
        return app(SupplierProcurement::class)->createOrder($this->actor, $this->outlet, (string) Str::uuid(), [
            'supplier_id' => $supplier, 'lines' => [[
                'product_id' => $product->public_id, 'quantity' => $quantity, 'unit_cost' => '100.00', 'landed_unit_cost' => '101.00',
            ]],
        ])['purchase_order_id'];
    }
}
