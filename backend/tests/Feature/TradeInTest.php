<?php

namespace Tests\Feature;

use App\Inventory\InventoryOperations;
use App\Models\Admin;
use App\Models\StockUnit;
use App\TradeIn\TradeInOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class TradeInTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_purchase_trade_in_approval_receipt_and_private_source_are_atomic(): void
    {
        $product = $this->product(true);
        $created = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(), $this->input());
        $this->assertSame('pending', $created['status']);
        $this->assertSame('*****-*******-1', $created['seller']['cnic']);
        $this->assertSame(2, DB::table('trade_in_identifiers')->whereNull('released_at')->count());

        $approved = $this->trade()->approve($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => 1]);
        $received = $this->trade()->receive($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => $approved['version']]);
        $this->assertSame('received', $received['status']);
        $this->assertSame('purchase', $received['settlement_mode']);
        $this->assertNull($received['monetary_adjustment_id']);
        $this->assertSame(1, DB::table('stock_acquisitions')->count());
        $this->assertSame(2, DB::table('active_imeis')->count());
        $this->assertSame(1, DB::table('acquisition_source_references')->where('kind', 'trade_in')->count());
        $acquisition = DB::table('stock_acquisitions')->where('id', $received['acquisition_id'])->first();
        $this->assertSame('*****-*******-*', $acquisition->seller_cnic);
        $this->assertSame('03*********', $acquisition->seller_phone);
        $this->assertSame(0, DB::table('trade_in_identifiers')->whereNull('released_at')->count());
        $this->assertSame(['created', 'approved', 'received'], DB::table('trade_in_events')->orderBy('sequence')->pluck('event_type')->all());
    }

    public function test_sale_credit_is_one_immutable_tender_effect_and_cannot_over_allocate_invoice(): void
    {
        $product = $this->product(true);
        $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $this->outlet->id, 'total_bill' => '200.02',
            'discount' => '0.00', 'final_bill' => '200.02', 'currency' => 'PKR', 'public_id' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now()]);
        $invoicePublic = DB::table('invoices')->where('id', $invoiceId)->value('public_id');
        $created = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(),
            $this->input(['settlement_mode' => 'sale_credit', 'invoice_id' => $invoicePublic, 'valuation_amount' => '100.00']));
        $approved = $this->trade()->approve($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => 1]);
        $received = $this->trade()->receive($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => $approved['version']]);
        $adjustment = DB::table('monetary_adjustments')->where('id', $received['monetary_adjustment_id'])->first();
        $this->assertSame('trade_in_credit', $adjustment->kind);
        $this->assertSame('tender', $adjustment->treatment);
        $this->assertSame('100.00', $adjustment->amount);
        $this->assertSame($created['trade_in_id'], $adjustment->source_reference);
        $this->assertSame($invoiceId, (int) $adjustment->invoice_id);

        $second = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(),
            $this->input(['imeis' => ['352099001761482', '352099001761490'], 'settlement_mode' => 'sale_credit',
                'invoice_id' => $invoicePublic, 'valuation_amount' => '150.00']));
        $this->reject(fn () => $this->trade()->approve($this->actor, $this->outlet, $second['trade_in_id'], (string) Str::uuid(), ['version' => 1]));
        $this->assertSame('pending', DB::table('trade_ins')->where('public_id', $second['trade_in_id'])->value('status'));
        $this->assertSame(1, DB::table('monetary_adjustments')->where('kind', 'trade_in_credit')->count());
    }

    public function test_cancel_releases_identifier_reservation_without_stock_or_credit(): void
    {
        $product = $this->product(true);
        $created = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(), $this->input());
        $cancelled = $this->trade()->cancel($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(),
            ['version' => 1, 'reason' => 'Seller declined']);
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(0, DB::table('trade_in_identifiers')->whereNull('released_at')->count());
        $this->assertSame(0, DB::table('stock_acquisitions')->count());
        $this->assertSame(0, DB::table('monetary_adjustments')->where('kind', 'trade_in_credit')->count());
        $again = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(), $this->input());
        $this->assertSame('pending', $again['status']);
    }

    public function test_duplicate_active_imei_permission_and_receive_failure_leave_no_partial_intake(): void
    {
        $product = $this->product(true);
        $created = $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(), $this->input());
        $approved = $this->trade()->approve($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => 1]);

        $other = $this->product(true);
        $this->acquire($other);
        $unit = StockUnit::where('product_id', $other->id)->firstOrFail();
        app(InventoryOperations::class)->imeis($this->actor, $this->outlet, $other->public_id, (string) Str::uuid(), [
            'unit_id' => $unit->public_id, 'version' => $unit->version,
            'imeis' => [1 => '352099001761466', 2 => '352099001761474'],
        ]);
        $before = DB::table('stock_acquisitions')->count();
        $this->reject(fn () => $this->trade()->receive($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => $approved['version']]));
        $this->assertSame($before, DB::table('stock_acquisitions')->count());
        $this->assertSame('approved', DB::table('trade_ins')->where('public_id', $created['trade_in_id'])->value('status'));
        $this->assertNull(DB::table('trade_ins')->where('public_id', $created['trade_in_id'])->value('acquisition_id'));

        $limited = new Admin;
        $limited->forceFill(['name' => 'No trade authority', 'email' => 'no-trade@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.inventory']])->save();
        $limited->shops()->attach($this->outlet);
        $this->reject(fn () => $this->trade()->create($limited, $this->outlet, $product->public_id, (string) Str::uuid(),
            $this->input(['imeis' => ['352099001761508', '352099001761516']])));
    }

    public function test_archived_outlet_blocks_new_trade_in_mutations_and_completed_replay(): void
    {
        $product = $this->product(true);
        $key = (string) Str::uuid();
        $created = $this->trade()->create($this->actor, $this->outlet, $product->public_id, $key, $this->input());
        $before = DB::table('trade_ins')->where('public_id', $created['trade_in_id'])->firstOrFail();
        $events = DB::table('trade_in_events')->where('trade_in_id', $before->id)->count();

        DB::table('outlets')->where('id', $this->outlet->id)->update(['archived_at' => now(), 'version' => 2]);

        $this->reject(fn () => $this->trade()->create($this->actor, $this->outlet, $product->public_id, $key, $this->input()));
        $this->reject(fn () => $this->trade()->create($this->actor, $this->outlet, $product->public_id, (string) Str::uuid(),
            $this->input(['imeis' => ['352099001761508', '352099001761516']])));
        $this->reject(fn () => $this->trade()->approve($this->actor, $this->outlet, $created['trade_in_id'], (string) Str::uuid(), ['version' => 1]));

        $after = DB::table('trade_ins')->where('id', $before->id)->firstOrFail();
        $this->assertSame($before->status, $after->status);
        $this->assertSame($before->version, $after->version);
        $this->assertSame($events, DB::table('trade_in_events')->where('trade_in_id', $before->id)->count());
        $this->assertSame(1, DB::table('trade_ins')->count());
    }

    private function input(array $overrides = []): array
    {
        return ['seller_name' => 'Synthetic Seller', 'seller_cnic' => '42101-1234567-1', 'seller_phone' => '03001234567',
            'seller_address' => 'Synthetic private address', 'device_serial' => 'SERIAL-MT213',
            'imeis' => ['352099001761466', '352099001761474'], 'condition' => 'Used - inspected',
            'diagnostics' => ['battery' => 'Healthy', 'display' => 'Pass', 'camera' => 'Pass'],
            'valuation_amount' => '100.00', 'settlement_mode' => 'purchase', 'invoice_id' => null, ...$overrides];
    }

    private function trade(): TradeInOperations
    {
        return app(TradeInOperations::class);
    }
}
