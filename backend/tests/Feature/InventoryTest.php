<?php

namespace Tests\Feature;

use App\Inventory\InventoryOperations;
use App\Inventory\StockLedger;
use App\Inventory\TransactionalStock;
use App\Migration\StockImporter;
use App\Models\Admin;
use App\Models\StockUnit;
use App\Support\ProductVariantKey;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_zero_stock_definition_and_stock_history_block_outlet_archive_and_archived_inventory_writes(): void
    {
        $product = $this->product();
        $fallback = new \App\Models\Outlet;
        $fallback->forceFill(['public_id' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'D03 inventory fallback', 'outlet_code' => '067'])->save();
        $owner = new Admin;
        $owner->forceFill(['name' => 'D03 inventory archive owner',
            'email' => 'd03-inventory-'.\Illuminate\Support\Str::uuid().'@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => [
                'shops.enter', 'team-members.full-access.assign', 'admin.business-profile.manage']])->save();
        $owner->roles()->attach(\App\Models\Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $owner->shops()->attach($fallback);
        $archive = app(\App\Identity\OutletLifecycleAdministration::class);
        foreach ([0, 2] as $quantity) {
            if ($quantity) {
                $this->acquire($product, $quantity, 'd03-inventory-receipt');
            }
            try {
                $archive->archive($owner, $this->outlet->public_id, (int) $this->outlet->version);
                $this->fail('A product definition or its stock history was silently archived.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
            $this->assertNull($this->outlet->fresh()->archived_at);
            $this->assertSame($quantity, (int) $product->fresh()->qty);
            $this->assertSame($this->outlet->id, (int) $product->fresh()->outlet_id);
        }
        $service = app(InventoryOperations::class);
        $entry = $this->acquisitionInput(2);
        $movement = DB::table('stock_movements')->where('product_id', $product->id)->firstOrFail();
        $receipt = DB::table('stock_acquisitions')->where('product_id', $product->id)->firstOrFail();
        $keys = DB::table('idempotency_requests')->count();
        // Deliberately simulated archived state only: production still forbids this archival.
        $this->outlet->forceFill(['archived_at' => now()])->save();
        foreach (['d03-inventory-receipt', 'd03-inventory-new'] as $key) {
            try {
                $service->acquire($this->actor, $this->outlet, $product->public_id, $key, $entry);
                $this->fail('Archived outlet accepted stock receipt or a completed-key replay.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        try {
            $service->adjust($this->actor, $this->outlet, $product->public_id,
                'd03-inventory-adjust', ['type' => 'lost', 'quantity' => 1, 'reason' => 'Synthetic']);
            $this->fail('Archived outlet accepted stock adjustment.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($keys, DB::table('idempotency_requests')->count());
        $this->assertSame(2, (int) $product->fresh()->qty);
        $this->assertEquals($movement, DB::table('stock_movements')->where('id', $movement->id)->firstOrFail());
        $this->assertEquals($receipt, DB::table('stock_acquisitions')->where('id', $receipt->id)->firstOrFail());
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $this->outlet->id)
            ->where('action', 'outlet_archived')->count());
    }

    public function test_acquisition_is_atomic_exact_idempotent_and_derives_physical_units(): void
    {
        $product = $this->product(true);
        $first = $this->acquire($product, 2, 'receipt-1');
        $this->assertEquals($first, $this->acquire($product, 2, 'receipt-1'));
        $this->reject(fn () => $this->acquire($product, 1, 'receipt-1'));
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertSame('123.45', $product->fresh()->purchase_price);
        $this->assertSame([1, 2], StockUnit::orderBy('id')->pluck('unit_no')->all());
        $this->assertSame(1, DB::table('stock_acquisitions')->count());
        $this->assertSame(1, DB::table('stock_movements')->count());
        $snapshot = app(StockImporter::class)->reconcile($product->id);
        $this->assertSame(0, $snapshot['available']);
        $this->assertSame(2, $snapshot['incomplete_units']);
        $this->assertSame(0, $snapshot['opening_balance']);
        $read = app(InventoryOperations::class)->acquisition($this->actor, $this->outlet, $first['acquisition_id']);
        $this->assertSame('03000000000', $read['seller_phone']);
        $this->assertArrayNotHasKey('cnic_front_path', $read);
    }

    public function test_unauthorized_actor_and_injected_fields_do_not_change_stock(): void
    {
        $product = $this->product();
        $admin = new Admin;
        $admin->forceFill(['name' => 'Denied', 'email' => 'denied-stock@example.invalid', 'password' => 'SyntheticPass123!'])->save();
        $service = app(InventoryOperations::class);
        $input = $this->acquisitionInput(1);
        $this->reject(fn () => $service->acquire($admin, $this->outlet, $product->public_id, 'denied', $input));
        foreach ([['qty' => 50], ['outlet_id' => 999], ['cnic_front_path' => 'https://external.invalid'], ['unit_purchase_price' => 1.2], ['quantity' => 0], ['seller_phone' => '123']] as $invalid) {
            $this->reject(fn () => $service->acquire($this->actor, $this->outlet, $product->public_id, 'invalid', [...$input, ...$invalid]));
        }
        $this->assertSame(0, DB::table('idempotency_requests')->count());
        $this->assertSame(0, $product->fresh()->qty);
    }

    public function test_imei_slots_versions_global_uniqueness_and_rollback(): void
    {
        $a = $this->product(true);
        $b = $this->product(true);
        $this->acquire($a);
        $this->acquire($b);
        $unit = StockUnit::where('product_id', $a->id)->firstOrFail();
        $this->reject(fn () => $this->imeis($a, $unit, [1 => 'only-one']));
        $this->imeis($a, $unit);
        $this->reject(fn () => $this->imeis($a, $unit));
        $this->reject(fn () => $this->imeis($b));
        $this->assertSame(2, DB::table('active_imeis')->count());
        $this->assertSame(0, DB::table('product_imeis')->where('product_id', $b->id)->count());
        $this->assertSame(1, app(StockLedger::class)->snapshot($a->id)['available']);
        $this->assertSame(0, app(StockLedger::class)->snapshot($b->id)['available']);
        DB::beginTransaction();
        $this->imeis($a, $unit->fresh(), [1 => 'replacement-1', 2 => 'replacement-2']);
        DB::rollBack();
        $this->assertSame(['Synthetic-IMEI-1', 'Synthetic-IMEI-2'], DB::table('active_imeis')->orderBy('imei')->pluck('imei')->all());
    }

    public function test_quantity_holds_block_sale_adjustment_and_archive_until_release(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $reservation = $this->reservation($product);
        $service = app(TransactionalStock::class);
        $ids = $service->reserve($reservation['line']);
        $this->assertSame($ids, $service->reserve($reservation['line']));
        $this->assertSame(0, app(StockLedger::class)->snapshot($product->id)['available']);
        $sale = $this->sale($product);
        $this->reject(fn () => $service->consumeSale($sale));
        $this->reject(fn () => app(InventoryOperations::class)->adjust($this->actor, $this->outlet, $product->public_id, 'remove', ['type' => 'lost', 'quantity' => 1, 'reason' => 'Synthetic']));
        $this->reject(fn () => app(InventoryOperations::class)->archive($this->actor, $this->outlet, $product->public_id, 'archive'));
        $service->release($reservation['reservation']);
        $service->release($reservation['reservation']);
        $this->reject(fn () => $service->reserve($reservation['line']));
        $movement = $service->consumeSale($sale);
        $this->assertSame($movement, $service->consumeSale($sale));
        $this->assertSame(0, $product->fresh()->qty);
        $this->assertSame(1, $product->fresh()->sold_qty);
        $this->assertSame(2, DB::table('stock_movements')->count());
        app(InventoryOperations::class)->archive($this->actor, $this->outlet, $product->public_id, 'archive');
        $this->assertTrue($product->fresh()->isDeleted);
    }

    public function test_tracked_reserved_sale_uses_held_units_preserves_history_and_release_cannot_restore_stock(): void
    {
        $product = $this->product(true);
        $this->acquire($product);
        $this->imeis($product);
        $unit = StockUnit::where('product_id', $product->id)->firstOrFail();
        $reservation = $this->reservation($product, 1, ProductVariantKey::forUnit($unit));
        $service = app(TransactionalStock::class);
        $service->reserve($reservation['line']);
        $this->reject(fn () => $this->imeis($product, $unit));
        $sale = $this->sale($product, 1, $reservation['order']);
        $service->consumeSale($sale, $reservation['line']);
        $service->release($reservation['reservation']);
        $service->consumeSale($sale, $reservation['line']);
        $this->assertSame(0, $product->fresh()->qty);
        $this->assertSame('sold', $unit->fresh()->status);
        $this->assertSame($sale, $unit->fresh()->sale_id);
        $this->assertSame(0, DB::table('active_imeis')->count());
        $this->assertSame(2, DB::table('product_imeis')->where('status', 'sold')->where('sale_id', $sale)->count());
        $this->assertSame(0, app(StockImporter::class)->reconcile($product->id)['available']);
    }

    public function test_expiration_boundary_and_cod_holds_are_distinct(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $a = $this->reservation($product);
        $b = $this->reservation($product);
        $service = app(TransactionalStock::class);
        $service->reserve($a['line']);
        $service->reserve($b['line']);
        DB::table('reservations')->where('id', $a['reservation'])->update(['reservation_expires_at' => now()]);
        DB::table('reservations')->where('id', $b['reservation'])->update(['state' => 'held_cod', 'reservation_expires_at' => now()->subDay()]);
        $this->reject(fn () => $service->reserve($a['line']));
        $this->reject(fn () => $service->release($b['reservation'], true));
        $service->release($a['reservation'], true);
        $this->assertSame(1, app(StockLedger::class)->snapshot($product->id)['held']);
        $this->assertSame('expired', DB::table('reservations')->where('id', $a['reservation'])->value('state'));
        $service->reserve($b['line']);
    }

    public function test_adjustment_retires_incomplete_unit_and_prevents_negative_stock(): void
    {
        $product = $this->product(true);
        $this->acquire($product);
        $unit = StockUnit::where('product_id', $product->id)->firstOrFail();
        $service = app(InventoryOperations::class);
        $service->adjust($this->actor, $this->outlet, $product->public_id, 'damage', ['type' => 'damaged', 'quantity' => 1, 'unit_id' => $unit->public_id, 'reason' => 'Synthetic damage']);
        $this->assertSame('damaged', $unit->fresh()->status);
        $this->assertSame(0, $product->fresh()->qty);
        $this->reject(fn () => $service->adjust($this->actor, $this->outlet, $product->public_id, 'again', ['type' => 'damaged', 'quantity' => 1, 'unit_id' => $unit->public_id, 'reason' => 'Synthetic']));
        $service->adjust($this->actor, $this->outlet, $product->public_id, 'in', ['type' => 'correction_in', 'quantity' => 1, 'reason' => 'Synthetic count']);
        $this->assertSame([1, 2], StockUnit::orderBy('id')->pluck('unit_no')->all());
        $this->assertSame(1, app(StockImporter::class)->reconcile($product->id)['on_hand']);
    }

    public function test_enclosing_transaction_rollback_undoes_stock_claims_holds_events_and_retry_key(): void
    {
        $product = $this->product(true);
        $events = DB::table('domain_events')->count();
        DB::beginTransaction();
        $this->acquire($product, 1, 'rollback-receipt');
        $this->imeis($product);
        $reservation = $this->reservation($product);
        app(TransactionalStock::class)->reserve($reservation['line']);
        DB::rollBack();
        foreach (['stock_units', 'stock_acquisitions', 'stock_movements', 'active_imeis', 'product_imeis', 'reservation_allocations', 'idempotency_requests'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
        $this->assertSame($events, DB::table('domain_events')->count());
        $this->assertSame(0, $product->fresh()->qty);
        $this->acquire($product, 1, 'rollback-receipt');
        $this->assertSame(1, $product->fresh()->qty);
    }

    public function test_snapshot_and_movement_discrepancies_block_operations_without_repair(): void
    {
        $product = $this->product(true);
        $this->acquire($product);
        DB::table('products')->where('id', $product->id)->update(['qty' => 2]);
        $this->reject(fn () => $this->acquire($product));
        $this->assertSame(2, $product->fresh()->qty);
        DB::table('products')->where('id', $product->id)->update(['qty' => 1]);
        DB::table('stock_movements')->where('product_id', $product->id)->update(['stock_after' => 9]);
        $this->reject(fn () => app(StockImporter::class)->reconcile($product->id));
    }
}
