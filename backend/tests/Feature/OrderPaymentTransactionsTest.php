<?php

namespace Tests\Feature;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Inventory\InventoryOperations;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class OrderPaymentTransactionsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private CustomerAccount $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->customer = new CustomerAccount;
        $this->customer->forceFill(['name' => 'Synthetic customer', 'email' => 'buyer@example.invalid', 'mobile' => '03001112222',
            'password' => 'SyntheticPass123!', 'is_admin' => false])->save();
        DB::table('customers')->insert(['website_user_id' => $this->customer->id, 'display_name' => $this->customer->name,
            'email' => $this->customer->email, 'mobile' => $this->customer->mobile, 'public_id' => (string) Str::uuid()]);
        $this->publishMode('hybrid', 1);
    }

    public function test_cod_checkout_reprices_reserves_collects_once_and_uses_shared_sale_authority(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $key = $this->key('checkout');
        $result = $this->service()->checkout($this->scope(), $this->customer, $key, $this->checkoutInput($product->public_id, 'cod', 2));
        $this->assertSame('400.04', $result['amount']);
        $this->assertSame('pending_collection', $result['payment_status']);
        $this->assertSame(2, DB::table('reservation_allocations')->whereNull('released_at')->value('quantity'));
        $collected = $this->service()->collectCod($this->actor, $this->outlet, $result['order_id'], $this->key('collect'), '400.04', 'COD-001');
        $this->assertSame('paid', $collected['payment_status']);
        $this->assertSame('confirmed', $collected['order_status']);
        $this->assertSame(1, DB::table('invoices')->whereNotNull('order_id')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(2, $product->fresh()->sold_qty);
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $replay = $this->service()->collectCod($this->actor, $this->outlet, $result['order_id'], $this->key('collect'), '400.04', 'COD-001');
        $this->assertEquals($collected, $replay);
        $this->reject(fn () => $this->service()->collectCod($this->actor, $this->outlet, $result['order_id'], $this->key('collect'), '400.03', 'COD-001'));
    }

    public function test_checkout_rejects_client_price_mixed_outlets_duplicate_lines_and_inactive_mode(): void
    {
        $product = $this->product();
        $other = $this->product();
        $this->acquire($product);
        $input = $this->checkoutInput($product->public_id, 'cod');
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $this->key('price'), [...$input, 'total' => '0.01']));
        $input['lines'][] = $input['lines'][0];
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $this->key('duplicate'), $input));
        $otherOutlet = new Outlet;
        $otherOutlet->forceFill(['name' => 'Other', 'outlet_code' => '025', 'public_id' => (string) Str::uuid()])->save();
        $other->forceFill(['outlet_id' => $otherOutlet->id])->save();
        $this->actor->shops()->attach($otherOutlet);
        app(InventoryOperations::class)->acquire($this->actor, $otherOutlet, $other->public_id, $this->key('other-acquire'), $this->acquisitionInput(1));
        $input['lines'] = [['product_id' => $product->public_id, 'quantity' => 1], ['product_id' => $other->public_id, 'quantity' => 1]];
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $this->key('mixed'), $input));
        $this->publishMode('digital_only', 2);
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $this->key('mode'), $this->checkoutInput($product->public_id, 'cod')));
    }

    public function test_archived_outlet_blocks_physical_checkout_and_completed_replay(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $key = $this->key('archive-replay');
        $input = $this->checkoutInput($product->public_id, 'cod');
        $created = $this->service()->checkout($this->scope(), $this->customer, $key, $input);
        $orderCount = DB::table('orders')->count();
        $reservationCount = DB::table('reservations')->count();

        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();
        foreach ([$key, $this->key('archive-fresh')] as $attempt) {
            try {
                $this->service()->checkout($this->scope(), $this->customer, $attempt, $input);
                $this->fail('Archived outlet accepted a physical checkout or completed replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame($orderCount, DB::table('orders')->count());
        $this->assertSame($reservationCount, DB::table('reservations')->count());
        $this->assertSame($created['order_id'], DB::table('orders')->sole()->public_id);
        $this->assertSame(1, DB::table('idempotency_requests')->where('operation', 'commerce.checkout')->count());
    }

    public function test_disabled_provider_fails_before_order_and_verified_callback_is_replay_safe_across_mode_switch(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $this->key('disabled'), $this->checkoutInput($product->public_id, 'jazzcash')));
        $this->assertSame(0, DB::table('orders')->count());
        $fake = $this->fakeProvider();
        $result = $this->service()->checkout($this->scope(), $this->customer, $this->key('gateway'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $init = $this->service()->initiate($result['payment_id']);
        $this->assertSame('GW-'.$result['payment_id'], $init['reference']);
        $this->assertSame($init, $this->service()->initiate($result['payment_id']));
        $this->publishMode('digital_only', 2);
        $payload = $fake->paid('EVT-1', $init['reference'], '200.02');
        $paid = $this->service()->callback('jazzcash', $payload);
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertSame($paid, $this->service()->callback('jazzcash', $payload));
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->reject(fn () => $this->service()->callback('jazzcash', [...$payload, 'amount' => '200.03']));
    }

    public function test_failure_releases_stock_and_late_payment_is_preserved_for_reconciliation_without_sale(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $fake = $this->fakeProvider();
        $failedOrder = $this->service()->checkout($this->scope(), $this->customer, $this->key('failed-order'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $failedInit = $this->service()->initiate($failedOrder['payment_id']);
        $failed = $this->service()->callback('jazzcash', $fake->failed('EVT-F', $failedInit['reference'], '200.02'));
        $this->assertSame('failed', $failed['payment_status']);
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $retry = $this->service()->retry($this->scope(), $this->customer, $failedOrder['order_id'], $this->key('retry'), 'jazzcash');
        $this->assertSame('pending', $retry['payment_status']);
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->reject(fn () => $this->service()->retry($this->scope(), $this->customer, $failedOrder['order_id'], $this->key('retry-again'), 'jazzcash'));

        $lateOrder = $this->service()->checkout($this->scope(), $this->customer, $this->key('late-order'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $lateInit = $this->service()->initiate($lateOrder['payment_id']);
        DB::table('reservations')->where('website_payment_id', DB::table('payments')->where('public_id', $lateOrder['payment_id'])->value('id'))
            ->update(['reservation_expires_at' => now()->subSecond()]);
        $this->assertSame(1, $this->service()->expireDue());
        $this->assertSame('expired', DB::table('orders')->where('public_id', $lateOrder['order_id'])->value('payment_status'));
        $late = $this->service()->callback('jazzcash', $fake->paid('EVT-L', $lateInit['reference'], '200.02'));
        $this->assertSame('paid_reconciliation', $late['payment_status']);
        $this->assertTrue($late['reconciliation_required']);
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(2, $product->fresh()->qty);
    }

    public function test_unknown_provider_result_retains_hold_until_reconciliation(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('unknown-order'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $init = $this->service()->initiate($order['payment_id']);
        $unknown = $this->service()->callback('jazzcash', $fake->unknown('EVT-U', $init['reference'], '200.02'));
        $this->assertSame('unknown', $unknown['payment_status']);
        $this->assertTrue($unknown['reconciliation_required']);
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_project_milestone_payment_preserves_owner_amount_identity_and_history_when_commerce_is_off(): void
    {
        $fake = $this->fakeProvider();
        $quote = DB::table('project_quotes')->insertGetId(['reference' => 'QUOTE-1', 'client_name' => $this->customer->name,
            'customer_mobile' => $this->customer->mobile, 'customer_email' => $this->customer->email, 'title' => 'Synthetic project',
            'amount' => '100.00', 'status' => 'approved', 'public_id' => (string) Str::uuid()]);
        $quotePublic = DB::table('project_quotes')->where('id', $quote)->value('public_id');
        $milestone = app(FinancialReferences::class)->milestone($quote, MoneySnapshot::milestone((string) Str::uuid(), $quotePublic, 1, '100.00', str_repeat('a', 64)));
        $this->publishMode('digital_only', 2);
        $created = $this->service()->milestone($this->customer, $this->key('milestone'), ['milestone_id' => $milestone, 'gateway' => 'jazzcash']);
        $init = $this->service()->initiate($created['payment_id']);
        $paid = $this->service()->callback('jazzcash', $fake->paid('EVT-M', $init['reference'], '100.00'));
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertNotNull(DB::table('project_milestone_identities')->where('id', $milestone)->value('paid_at'));
        $this->assertSame('paid', DB::table('project_quotes')->where('id', $quote)->value('status'));
        $this->reject(fn () => $this->service()->milestone($this->customer, $this->key('milestone-2'), ['milestone_id' => $milestone, 'gateway' => 'jazzcash']));
        $other = new CustomerAccount;
        $other->forceFill(['name' => 'Other', 'email' => 'other@example.invalid', 'mobile' => '03009998888', 'password' => 'SyntheticPass123!', 'is_admin' => false])->save();
        $this->reject(fn () => $this->service()->milestone($other, $this->key('other'), ['milestone_id' => $milestone, 'gateway' => 'jazzcash']));
    }

    public function test_manual_refund_is_bounded_by_collected_payment_and_accepted_return(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('refund-order'), $this->checkoutInput($product->public_id, 'cod', 2));
        $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'], $this->key('refund-collect'), '400.04', 'COD-R');
        $invoice = DB::table('invoices')->whereNotNull('order_id')->firstOrFail();
        $sale = DB::table('sales')->where('invoice_id', $invoice->id)->firstOrFail();
        $return = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, $this->key('return'), [
            'invoice_id' => $invoice->public_id, 'reason' => 'Synthetic accepted return',
            'lines' => [['sale_id' => $sale->public_id, 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable']],
        ]);
        $refund = $this->service()->manualRefund($this->actor, $return['return_id'], $order['payment_id'], $this->key('refund'), '200.02', str_repeat('b', 64));
        $this->assertSame('completed', $refund['status']);
        $this->assertSame('partial', DB::table('orders')->where('public_id', $order['order_id'])->value('refund_status'));
        $this->reject(fn () => $this->service()->manualRefund($this->actor, $return['return_id'], $order['payment_id'], $this->key('refund-too-much'), '200.02', str_repeat('c', 64)));
        $this->assertSame(1, DB::table('refunds')->count());
    }

    private function service(): OrderTransactions
    {
        return app(OrderTransactions::class);
    }

    private function fakeProvider(): FakePaymentProvider
    {
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $fake = new FakePaymentProvider;
        $registry = new PaymentProviders;
        $registry->register('jazzcash', $fake);
        $this->app->instance(PaymentProviders::class, $registry);

        return $fake;
    }

    private function checkoutInput(string $productId, string $gateway, int $quantity = 1): array
    {
        return ['customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'city' => 'Karachi', 'delivery_address' => 'Synthetic address',
            'gateway' => $gateway, 'lines' => [['product_id' => $productId, 'quantity' => $quantity]]];
    }

    private function scope(): string
    {
        return $this->customer::class.':'.$this->customer->id;
    }

    private function key(string $suffix): string
    {
        return 'mt27-'.$suffix.'-'.str_repeat('x', 24);
    }

    private function publishMode(string $mode, int $version): void
    {
        $revision = DB::table('site_configuration_revisions')->insertGetId(['domain' => 'website.mode', 'version' => $version,
            'state' => 'published', 'snapshot' => json_encode(['mode' => $mode]), 'published_at' => now()]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], ['mode' => $mode, 'version' => $version,
            'revision_id' => $revision, 'published_at' => now()]);
    }
}

final class FakePaymentProvider implements PaymentProvider
{
    public function initiate(array $intent): array
    {
        return ['reference' => 'GW-'.$intent['payment_id'], 'redirect_url' => 'https://gateway.example.invalid/pay/'.$intent['payment_id']];
    }

    public function verify(array $payload): array
    {
        $signature = $payload['signature'] ?? '';
        $unsigned = array_diff_key($payload, ['signature' => true]);
        if (! hash_equals(hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), 'synthetic-secret'), $signature)) {
            throw new LogicException('Invalid synthetic provider signature.');
        }

        return $unsigned;
    }

    public function paid(string $event, string $reference, string $amount): array
    {
        return $this->event($event, $reference, $amount, 'paid');
    }

    public function failed(string $event, string $reference, string $amount): array
    {
        return $this->event($event, $reference, $amount, 'failed');
    }

    public function unknown(string $event, string $reference, string $amount): array
    {
        return $this->event($event, $reference, $amount, 'unknown');
    }

    private function event(string $event, string $reference, string $amount, string $status): array
    {
        $payload = ['event_id' => $event, 'transaction_reference' => 'TX-'.$event, 'order_reference' => $reference,
            'amount' => $amount, 'currency' => 'PKR', 'status' => $status,
            'payload_hash' => hash('sha256', $event.'|'.$reference.'|'.$amount.'|'.$status)];
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'synthetic-secret');

        return $payload;
    }
}
