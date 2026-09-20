<?php

namespace Tests\Feature;

use App\Cash\CashSessionOperations;
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
        app(CashSessionOperations::class)->open($this->actor, $this->outlet, 'mt220-cash-session-'.Str::uuid(), ['opening_cash' => '0.00']);
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

    public function test_all_four_pos_methods_share_one_invoice_and_keep_cash_closing_and_website_channels_distinct(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $destinations = [
            'cash' => $this->destination('cash', 'Four-way test drawer'),
            'card' => $this->destination('card', 'Four-way test card terminal', ['masked_identifier' => 'TERM-4WAY']),
            'mobile_wallet' => $this->destination('mobile_wallet', 'Four-way test wallet', ['masked_identifier' => '*******8765']),
            'bank_transfer' => $this->destination('bank_transfer', 'Four-way test bank account', ['masked_identifier' => '****4321']),
        ];
        $key = 'mt75-four-way-'.Str::uuid();
        $sale = $this->service()->sell($this->actor, $this->outlet, $key, [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [
                ['method' => 'cash', 'destination_id' => $destinations['cash']['destination_id'], 'amount' => '20.00', 'cash_tendered' => '30.00'],
                ['method' => 'card', 'destination_id' => $destinations['card']['destination_id'], 'amount' => '50.00', 'transaction_reference' => 'MT75-CARD-4WAY'],
                ['method' => 'mobile_wallet', 'destination_id' => $destinations['mobile_wallet']['destination_id'], 'amount' => '60.00', 'transaction_reference' => 'MT75-WALLET-4WAY'],
                ['method' => 'bank_transfer', 'destination_id' => $destinations['bank_transfer']['destination_id'], 'amount' => '70.02', 'transaction_reference' => 'MT75-BANK-4WAY'],
            ],
        ]);
        $this->assertSame('200.02', $sale['paid_amount']);
        $this->assertSame('10.00', $sale['cash_change_total']);
        $this->assertSame(['cash', 'card', 'mobile_wallet', 'bank_transfer'], array_column($sale['payments'], 'method'));
        $this->assertSame(['20.00', '50.00', '60.00', '70.02'], array_column($sale['payments'], 'amount'));
        $this->assertSame(1, DB::table('invoices')->where('public_id', $sale['invoice_id'])->count());
        $this->assertSame(4, DB::table('pos_tender_allocations')->where('invoice_id', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('id'))->count());
        $session = DB::table('cash_sessions')->where('outlet_id', $this->outlet->id)->where('status', 'open')->firstOrFail();
        $summary = app(CashSessionOperations::class)->session($this->actor, $this->outlet, $session->public_id)['summary'];
        $this->assertSame('20.00', $summary['cash_sales']);
        $this->assertSame('20.00', $summary['expected_cash']);
        $this->assertCount(3, $summary['non_cash_destinations']);
        $byMethod = collect($summary['non_cash_destinations'])->keyBy('method');
        $this->assertSame('50.00', $byMethod['card']['gross_expected_receipts']);
        $this->assertSame('60.00', $byMethod['mobile_wallet']['gross_expected_receipts']);
        $this->assertSame('70.02', $byMethod['bank_transfer']['gross_expected_receipts']);
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_keys(config('commerce.providers')));
        $this->assertArrayNotHasKey('bank_transfer', config('commerce.providers'));
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
        // Read-only archive projection checks real, fully recorded POS refunds; never archive this product-bearing outlet.
        $this->actor->roles()->attach(\App\Models\Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $this->outlet->forceFill(['archived_at' => now()])->save();
        $beforeRefund = DB::table('pos_refund_allocations')->where('public_id', $override['refund_id'])->firstOrFail();
        $history = app(\App\Identity\OutletLifecycleAdministration::class)
            ->archivedHistory($this->actor, $this->outlet->public_id);
        $this->assertSame(1, $history['obligations']['pos_return_count']);
        $this->assertSame('200.02', $history['obligations']['pos_return_amount']);
        $this->assertSame('200.02', $history['obligations']['pos_refunds_recorded']);
        $this->assertSame('0.00', $history['obligations']['pos_refund_difference']);
        $this->assertSame(0, $history['obligations']['pos_returns_unmatched_count']);
        $this->assertSame(0, $history['obligations']['pos_returns_over_refunded_count']);
        $historicalInvoice = $history['sales_claim_history']['invoices'][0];
        $this->assertSame($sale['invoice_id'], $historicalInvoice['id']);
        $this->assertSame(1, $historicalInvoice['sale_line_count']);
        $this->assertSame(1, $historicalInvoice['return_count']);
        $this->assertSame(1, $historicalInvoice['tender_count']);
        $this->assertSame(2, $historicalInvoice['refund_count']);
        $this->assertSame('pos', $historicalInvoice['returns'][0]['channel']);
        $this->assertSame(2, $historicalInvoice['returns'][0]['recorded_pos_refund_count']);
        $this->assertSame($override['refund_id'], $historicalInvoice['refunds'][1]['id']);
        $this->assertSame('100.02', $historicalInvoice['refunds'][1]['amount']);
        $this->assertSame($beforeRefund->snapshot_sha256, $historicalInvoice['refunds'][1]['snapshot_sha256']);
        $this->assertStringNotContainsString('MANUAL-REF-1', json_encode($history, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('TERM-03', json_encode($history, JSON_THROW_ON_ERROR));
        $this->assertSame(1, $history['obligations']['unsettled_pos_tenders']);
        $this->assertTrue($history['obligations']['requires_manual_review']);
        $this->assertSame('not_approved_for_business_history', $history['obligations']['archive_eligibility']);
        $this->assertEquals($beforeRefund, DB::table('pos_refund_allocations')->where('id', $beforeRefund->id)->firstOrFail());
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $this->outlet->id)
            ->where('action', 'outlet_archived')->count());

    }

    public function test_archived_outlet_rejects_payment_replays_and_settlement_without_touching_financial_history(): void
    {
        $service = $this->service();
        $product = $this->product();
        $this->acquire($product, 2);
        $destinationKey = (string) Str::uuid();
        $destinationInput = ['method' => 'card', 'display_name' => 'Synthetic archived payment terminal'];
        $destination = $service->createDestination($this->actor, $this->outlet, $destinationKey, $destinationInput);
        $saleKey = (string) Str::uuid();
        $saleInput = ['sale' => ['discount' => '0.00', 'lines' => [
            ['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'card', 'destination_id' => $destination['destination_id'],
                'amount' => '200.02', 'transaction_reference' => 'D03-TEST-CARD']]];
        $sale = $service->sell($this->actor, $this->outlet, $saleKey, $saleInput);
        $allocation = $sale['payments'][0];
        $settlementKey = (string) Str::uuid();
        $settlementInput = ['settlement_version' => 0, 'fee_amount' => '0.00',
            'adjustment_amount' => '0.00', 'received_net_amount' => '200.02'];
        $service->reconcile($this->actor, $this->outlet,
            $allocation['allocation_id'], $settlementKey, $settlementInput);
        $priorDestination = DB::table('pos_payment_destinations')->where('public_id', $destination['destination_id'])->firstOrFail();
        $priorInvoice = DB::table('invoices')->where('public_id', $sale['invoice_id'])->firstOrFail();
        $priorTender = DB::table('pos_tender_allocations')->where('public_id', $allocation['allocation_id'])->firstOrFail();
        $priorSettlement = DB::table('pos_settlement_events')->firstOrFail();
        // A production archive must still reject these linked product/invoice/tender records.
        $this->outlet->forceFill(['archived_at' => now()])->save();
        $attempts = [
            fn () => $service->createDestination($this->actor, $this->outlet, $destinationKey, $destinationInput),
            fn () => $service->sell($this->actor, $this->outlet, $saleKey, $saleInput),
            fn () => $service->reconcile($this->actor, $this->outlet,
                $allocation['allocation_id'], $settlementKey, $settlementInput),
            fn () => $service->updateDestination($this->actor, $this->outlet,
                $destination['destination_id'], (string) Str::uuid(), ['version' => 1, 'display_name' => 'Denied']),
            fn () => $service->reconcile($this->actor, $this->outlet,
                $allocation['allocation_id'], (string) Str::uuid(), $settlementInput),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Archived outlet served cached payment response or accepted a financial write.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertEquals($priorDestination, DB::table('pos_payment_destinations')->where('id', $priorDestination->id)->firstOrFail());
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $priorInvoice->id)->firstOrFail());
        $this->assertEquals($priorTender, DB::table('pos_tender_allocations')->where('id', $priorTender->id)->firstOrFail());
        $this->assertEquals($priorSettlement, DB::table('pos_settlement_events')->where('id', $priorSettlement->id)->firstOrFail());
        $this->assertSame(hash('sha256', $priorSettlement->event_snapshot), $priorSettlement->snapshot_sha256);
        $this->assertSame(1, DB::table('pos_settlement_events')->count());
        $this->assertSame(0, DB::table('pos_refund_allocations')->count());
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
