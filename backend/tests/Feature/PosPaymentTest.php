<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use App\Payments\PosPaymentOperations;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PosPaymentTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_split_tender_is_exact_atomic_idempotent_and_keeps_website_channels_fixed(): void
    {
        $product = $this->product();
        $this->acquire($product, 4);
        $cash = $this->destination('cash', 'Quaidabad Cash Drawer');
        $wallet = $this->destination('mobile_wallet', 'Easypaisa Shop Wallet', ['masked_identifier' => '*******4567']);
        $card = $this->destination('card', 'HBL POS Terminal 01', ['masked_identifier' => 'TERM-01']);
        $key = 'mt220-split-'.Str::uuid();
        $input = ['sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]], 'payments' => [
            ['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '30.02', 'cash_tendered' => '50.02'],
            ['method' => 'mobile_wallet', 'destination_id' => $wallet['destination_id'], 'amount' => '20.00', 'transaction_reference' => 'EP-001'],
            ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '150.00', 'transaction_reference' => 'APP-001', 'reconciliation_reference' => 'RRN-001'],
        ]];
        $sale = $this->service()->sell($this->actor, $this->outlet, $key, $input);
        $this->assertSame('200.02', $sale['paid_amount']);
        $this->assertSame('20.00', $sale['cash_change_total']);
        $this->assertCount(3, $sale['payments']);
        $this->assertEquals($sale, $this->service()->sell($this->actor, $this->outlet, $key, $input));
        $changedReplay = $input;
        $changedReplay['payments'][0]['cash_tendered'] = '51.02';
        $this->reject(fn () => $this->service()->sell($this->actor, $this->outlet, $key, $changedReplay));
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(3, DB::table('pos_tender_allocations')->count());
        $this->assertSame('200.02', DB::table('invoices')->value('final_bill'));
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_keys(config('commerce.providers')));
        $this->assertArrayNotHasKey('bank_transfer', config('commerce.providers'));

        $bad = $input;
        $bad['payments'][2]['amount'] = '149.99';
        $this->reject(fn () => $this->service()->sell($this->actor, $this->outlet, 'mt220-under-'.Str::uuid(), $bad));
        $this->assertSame(1, DB::table('invoices')->count());
        $this->reject(fn () => $this->service()->sell($this->actor, $this->outlet, 'mt220-total-'.Str::uuid(), [
            'sale' => [...$input['sale'], 'final_bill' => '1.00'], 'payments' => $input['payments'],
        ]));
        $sensitive = $input;
        $sensitive['payments'][2]['transaction_reference'] = '4111 1111 1111 1111';
        $this->reject(fn () => $this->service()->sell($this->actor, $this->outlet, 'mt220-pan-'.Str::uuid(), $sensitive));
        $this->assertSame(1, DB::table('invoices')->count());
    }

    public function test_destination_scope_state_masking_and_management_permissions_are_enforced(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $this->reject(fn () => $this->destination('mobile_wallet', 'Unsafe Wallet', ['masked_identifier' => '03001234567']));
        $active = $this->destination('cash', 'Primary Cash Drawer');
        $inactive = $this->destination('card', 'Inactive Terminal', ['active' => false]);
        $future = $this->destination('card', 'Future Terminal', ['effective_from' => now()->addDay()->toIso8601String()]);
        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other outlet', 'outlet_code' => '028'])->save();
        $this->actor->shops()->attach($other);
        $otherDestination = $this->service()->createDestination($this->actor, $other, (string) Str::uuid(), [
            'method' => 'card', 'display_name' => 'Other Terminal', 'masked_identifier' => 'TERM-OTHER',
        ]);
        foreach ([$inactive, $future, $otherDestination] as $destination) {
            $this->reject(fn () => $this->service()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
                'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
                'payments' => [['method' => 'card', 'destination_id' => $destination['destination_id'], 'amount' => '200.02']],
            ]));
        }
        $limited = new Admin;
        $limited->forceFill(['name' => 'Sales only', 'email' => 'sales-only@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.sales']])->save();
        $limited->shops()->attach($this->outlet);
        $this->reject(fn () => $this->service()->createDestination($limited, $this->outlet, (string) Str::uuid(), [
            'method' => 'cash', 'display_name' => 'Unauthorized drawer',
        ]));
        $sold = $this->service()->sell($limited, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'cash', 'destination_id' => $active['destination_id'], 'amount' => '200.02', 'cash_tendered' => '200.02']],
        ]);
        $this->assertSame('200.02', $sold['paid_amount']);
    }

    public function test_settlement_keeps_customer_payment_fee_and_net_receipt_separate(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $card = $this->destination('card', 'Meezan POS Terminal', ['masked_identifier' => 'TERM-02']);
        $sale = $this->service()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '200.02',
                'transaction_reference' => 'APP-200', 'reconciliation_reference' => 'RRN-200']],
        ]);
        $allocation = $sale['payments'][0];
        $key = (string) Str::uuid();
        $input = ['settlement_version' => 0, 'fee_amount' => '3.00', 'adjustment_amount' => '-0.50',
            'received_net_amount' => '196.52', 'external_reference' => 'SET-001', 'notes' => 'Synthetic settlement'];
        $settlement = $this->service()->reconcile($this->actor, $this->outlet, $allocation['allocation_id'], $key, $input);
        $this->assertSame('confirmed', $settlement['state']);
        $this->assertSame('200.02', $settlement['gross_amount']);
        $this->assertSame('3.00', $settlement['fee_amount']);
        $this->assertSame('196.52', $settlement['expected_net_amount']);
        $this->assertSame('200.02', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('final_bill'));
        $this->assertSame('200.02', DB::table('pos_tender_allocations')->where('public_id', $allocation['allocation_id'])->value('amount'));
        $this->assertEquals($settlement, $this->service()->reconcile($this->actor, $this->outlet, $allocation['allocation_id'], $key, $input));
        $event = DB::table('pos_settlement_events')->firstOrFail();
        $this->assertSame(hash('sha256', $event->event_snapshot), $event->snapshot_sha256);
        $this->reject(fn () => $this->service()->reconcile($this->actor, $this->outlet, $allocation['allocation_id'], $key, [...$input, 'fee_amount' => '4.00']));
    }

    public function test_pos_refunds_preserve_original_tender_and_require_override_reason_and_approval(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $card = $this->destination('card', 'Refund Card Terminal', ['masked_identifier' => 'TERM-03']);
        $cash = $this->destination('cash', 'Refund Cash Drawer');
        $sale = $this->service()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '200.02']],
        ]);
        $return = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic POS refund',
            'lines' => [['sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable']],
        ]);
        $originalTender = $sale['payments'][0]['allocation_id'];
        $same = $this->service()->recordRefund($this->actor, $this->outlet, (string) Str::uuid(), [
            'return_id' => $return['return_id'], 'original_tender_id' => $originalTender,
            'refund_destination_id' => $card['destination_id'], 'amount' => '100.00',
        ]);
        $this->assertFalse($same['override']);
        $this->assertSame('card', $same['refund_method']);
        $this->reject(fn () => $this->service()->recordRefund($this->actor, $this->outlet, (string) Str::uuid(), [
            'return_id' => $return['return_id'], 'original_tender_id' => $originalTender,
            'refund_destination_id' => $cash['destination_id'], 'amount' => '100.02', 'override' => true,
        ]));
        $override = $this->service()->recordRefund($this->actor, $this->outlet, (string) Str::uuid(), [
            'return_id' => $return['return_id'], 'original_tender_id' => $originalTender,
            'refund_destination_id' => $cash['destination_id'], 'amount' => '100.02', 'override' => true,
            'override_reason' => 'Customer requested cash after terminal reversal failed', 'approved_by_admin_id' => $this->actor->public_id,
            'transaction_reference' => 'MANUAL-REF-1',
        ]);
        $this->assertTrue($override['override']);
        $this->assertSame('cash', $override['refund_method']);
        $this->assertSame(2, DB::table('pos_refund_allocations')->count());
        $this->assertSame(0, DB::table('refunds')->count(), 'Website/provider refund authority must remain untouched for POS refund recording.');
        $row = DB::table('pos_refund_allocations')->where('public_id', $override['refund_id'])->firstOrFail();
        $this->assertStringContainsString('"method":"card"', $row->original_tender_snapshot);
        $this->assertStringContainsString('"method":"cash"', $row->refund_destination_snapshot);
        $this->assertSame(64, strlen($row->snapshot_sha256));
        $this->reject(fn () => $this->service()->recordRefund($this->actor, $this->outlet, (string) Str::uuid(), [
            'return_id' => $return['return_id'], 'original_tender_id' => $originalTender,
            'refund_destination_id' => $card['destination_id'], 'amount' => '0.01',
        ]));
    }

    private function destination(string $method, string $name, array $extra = []): array
    {
        return $this->service()->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => $method, 'display_name' => $name, ...$extra,
        ]);
    }

    private function service(): PosPaymentOperations
    {
        return app(PosPaymentOperations::class);
    }
}
