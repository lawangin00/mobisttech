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

class CashSessionTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_exact_closing_consumes_mt220_cash_and_keeps_non_cash_settlement_distinct(): void
    {
        $session = $this->cash()->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '100.00']);
        $product = $this->product();
        $this->acquire($product, 3);
        $cash = $this->destination('cash', 'Main Cash Drawer');
        $card = $this->destination('card', 'HBL POS Terminal 01', ['masked_identifier' => 'TERM-01']);
        $sale = $this->payments()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [
                ['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '50.00', 'cash_tendered' => '70.00'],
                ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '150.02', 'transaction_reference' => 'APP-CASH-1'],
            ],
        ]);
        $this->payments()->reconcile($this->actor, $this->outlet, $sale['payments'][1]['allocation_id'], (string) Str::uuid(), [
            'settlement_version' => 0, 'fee_amount' => '3.00', 'adjustment_amount' => '-0.50',
            'received_net_amount' => '146.52', 'external_reference' => 'SET-CASH-1',
        ]);
        $return = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic cash-session refund',
            'lines' => [['sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable']],
        ]);
        $this->payments()->recordRefund($this->actor, $this->outlet, (string) Str::uuid(), [
            'return_id' => $return['return_id'], 'original_tender_id' => $sale['payments'][0]['allocation_id'],
            'refund_destination_id' => $cash['destination_id'], 'amount' => '20.02',
        ]);

        $cashIn = $this->entry($session['session_id'], 'cash_in', '25.00', 'Float top-up');
        $expense = $this->entry($session['session_id'], 'expense', '10.00', 'Courier expense');
        $payout = $this->entry($session['session_id'], 'payout', '5.00', 'Petty payout');
        foreach ([$cashIn, $expense, $payout] as $entry) {
            $this->cash()->reviewEntry($this->actor, $this->outlet, $session['session_id'], $entry['entry_id'], (string) Str::uuid(), [
                'session_version' => $this->version($session['session_id']), 'decision' => 'approved',
            ]);
        }

        $open = $this->cash()->session($this->actor, $this->outlet, $session['session_id']);
        $summary = $open['summary'];
        $this->assertSame('50.00', $summary['cash_sales']);
        $this->assertSame('20.02', $summary['cash_refunds']);
        $this->assertSame('25.00', $summary['approved_cash_in']);
        $this->assertSame('10.00', $summary['expenses']);
        $this->assertSame('5.00', $summary['payouts']);
        $this->assertSame('139.98', $summary['expected_cash']);
        $this->assertCount(1, $summary['non_cash_destinations']);
        $nonCash = $summary['non_cash_destinations'][0];
        $this->assertSame('150.02', $nonCash['gross_expected_receipts']);
        $this->assertSame('3.00', $nonCash['merchant_fees']);
        $this->assertSame('-0.50', $nonCash['adjustments']);
        $this->assertSame('146.52', $nonCash['expected_net']);
        $this->assertSame('146.52', $nonCash['received_net']);
        $this->assertSame('0.00', $nonCash['settlement_variance']);

        $closeKey = (string) Str::uuid();
        $closeInput = ['session_version' => $this->version($session['session_id']), 'actual_cash' => '140.98', 'variance_reason' => 'Synthetic overage'];
        $closed = $this->cash()->close($this->actor, $this->outlet, $session['session_id'], $closeKey, $closeInput);
        $this->assertSame('closed', $closed['status']);
        $this->assertSame('1.00', $closed['closing']['variance_amount']);
        $this->assertSame('139.98', $closed['closing']['expected_cash']);
        $this->assertEquals($closed, $this->cash()->close($this->actor, $this->outlet, $session['session_id'], $closeKey, $closeInput));
        $this->reject(fn () => $this->cash()->close($this->actor, $this->outlet, $session['session_id'], $closeKey, [...$closeInput, 'actual_cash' => '141.98']));

        $this->payments()->updateDestination($this->actor, $this->outlet, $card['destination_id'], (string) Str::uuid(), [
            'version' => 1, 'display_name' => 'Renamed Terminal',
        ]);
        $history = $this->cash()->session($this->actor, $this->outlet, $session['session_id']);
        $this->assertSame('HBL POS Terminal 01', $history['closing']['non_cash_destinations'][0]['display_name']);
        $this->assertSame(64, strlen($history['snapshot_sha256']));
        $this->reject(fn () => $this->entry($session['session_id'], 'cash_in', '1.00', 'Too late'));
    }

    public function test_pending_entries_permissions_and_variance_approval_are_scoped(): void
    {
        $session = $this->cash()->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '100.00']);
        $limited = new Admin;
        $limited->forceFill(['name' => 'Cash operator', 'email' => 'cash-operator@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.cash']])->save();
        $limited->shops()->attach($this->outlet);

        $entry = $this->cash()->recordEntry($limited, $this->outlet, $session['session_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'type' => 'expense', 'amount' => '10.00', 'reason' => 'Pending expense',
        ]);
        $this->assertSame('pending', $entry['status']);
        $this->assertSame('100.00', $this->cash()->session($limited, $this->outlet, $session['session_id'])['summary']['expected_cash']);
        $this->reject(fn () => $this->cash()->close($limited, $this->outlet, $session['session_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'actual_cash' => '100.00',
        ]));
        $this->reject(fn () => $this->cash()->reviewEntry($limited, $this->outlet, $session['session_id'], $entry['entry_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'decision' => 'approved',
        ]));
        $this->cash()->reviewEntry($this->actor, $this->outlet, $session['session_id'], $entry['entry_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'decision' => 'approved',
        ]);
        $this->assertSame('90.00', $this->cash()->session($limited, $this->outlet, $session['session_id'])['summary']['expected_cash']);
        $this->reject(fn () => $this->cash()->close($limited, $this->outlet, $session['session_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'actual_cash' => '91.00', 'variance_reason' => 'Operator overage',
        ]));
        $closed = $this->cash()->close($limited, $this->outlet, $session['session_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'actual_cash' => '90.00',
        ]);
        $this->assertSame('0.00', $closed['closing']['variance_amount']);

        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other cash outlet', 'outlet_code' => '031'])->save();
        $limited->shops()->attach($other);
        $this->reject(fn () => $this->cash()->session($limited, $other, $session['session_id']));
    }

    public function test_cash_requires_open_session_but_non_cash_can_remain_unassigned(): void
    {
        $session = $this->cash()->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '0.00']);
        $this->cash()->close($this->actor, $this->outlet, $session['session_id'], (string) Str::uuid(), [
            'session_version' => $this->version($session['session_id']), 'actual_cash' => '0.00',
        ]);
        $product = $this->product();
        $this->acquire($product, 2);
        $cash = $this->destination('cash', 'Closed Drawer');
        $card = $this->destination('card', 'Standalone Card', ['masked_identifier' => 'TERM-04']);
        $this->reject(fn () => $this->payments()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '200.02', 'cash_tendered' => '200.02']],
        ]));
        $sale = $this->payments()->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '200.02']],
        ]);
        $this->assertNull(DB::table('pos_tender_allocations')->where('public_id', $sale['payments'][0]['allocation_id'])->value('cash_session_id'));
    }

    public function test_cash_migration_refuses_populated_financial_history_rollback(): void
    {
        $this->cash()->open($this->actor, $this->outlet, (string) Str::uuid(), ['opening_cash' => '10.00']);
        $migration = require database_path('migrations/2026_09_02_140000_add_cash_session_services.php');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cash-session rollback requires empty cash sessions.');
        $migration->down();
    }

    private function entry(string $sessionId, string $type, string $amount, string $reason): array
    {
        return $this->cash()->recordEntry($this->actor, $this->outlet, $sessionId, (string) Str::uuid(), [
            'session_version' => $this->version($sessionId), 'type' => $type, 'amount' => $amount, 'reason' => $reason,
        ]);
    }

    private function version(string $sessionId): int
    {
        return (int) DB::table('cash_sessions')->where('public_id', $sessionId)->value('version');
    }

    private function destination(string $method, string $name, array $extra = []): array
    {
        return $this->payments()->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => $method, 'display_name' => $name, ...$extra,
        ]);
    }

    private function payments(): PosPaymentOperations
    {
        return app(PosPaymentOperations::class);
    }

    private function cash(): CashSessionOperations
    {
        return app(CashSessionOperations::class);
    }
}
