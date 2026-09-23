<?php

namespace Tests\Feature;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Commerce\ProductReviews;
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

    public function test_w04_cod_disable_blocks_new_orders_but_preserves_preexisting_collection(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('before-cod-disabled'), $this->checkoutInput($product->public_id, 'cod'));
        $this->assertSame('pending_collection', $order['payment_status']);
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['cod_enabled' => false], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        $this->assertFalse(collect(app(PaymentProviders::class)->checkoutChannels())
            ->firstWhere('code', 'cod')['available']);
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer,
            $this->key('after-cod-disabled'), $this->checkoutInput($product->public_id, 'cod')));
        $this->assertSame(1, DB::table('orders')->count());
        $collected = $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'],
            $this->key('collect-before-cod-disabled'), '200.02', 'COD-EXISTING');
        $this->assertSame('paid', $collected['payment_status']);
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(1, $product->fresh()->qty);
    }

    public function test_checkout_replay_conflict_and_partial_failure_retry_preserve_one_joined_state(): void
    {
        $first = $this->product();
        $second = $this->product();
        $this->acquire($first, 2);
        $key = $this->key('checkout-replay');
        $input = $this->checkoutInput($first->public_id, 'cod');
        $created = $this->service()->checkout($this->scope(), $this->customer, $key, $input);
        $this->assertEquals($created, $this->service()->checkout($this->scope(), $this->customer, $key, $input));
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $key,
            $this->checkoutInput($first->public_id, 'cod', 2)));
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('reservations')->count());
        $this->assertSame(1, DB::table('payments')->count());

        $partialKey = $this->key('checkout-partial');
        $partial = [...$input, 'lines' => [
            ['product_id' => $first->public_id, 'quantity' => 1],
            ['product_id' => $second->public_id, 'quantity' => 1],
        ]];
        $before = [DB::table('orders')->count(), DB::table('order_items')->count(), DB::table('reservations')->count(),
            DB::table('reservation_lines')->count(), DB::table('reservation_allocations')->count(), DB::table('payments')->count(),
            DB::table('idempotency_requests')->count()];
        $this->reject(fn () => $this->service()->checkout($this->scope(), $this->customer, $partialKey, $partial));
        $this->assertSame($before, [DB::table('orders')->count(), DB::table('order_items')->count(), DB::table('reservations')->count(),
            DB::table('reservation_lines')->count(), DB::table('reservation_allocations')->count(), DB::table('payments')->count(),
            DB::table('idempotency_requests')->count()]);

        $this->acquire($second);
        $retried = $this->service()->checkout($this->scope(), $this->customer, $partialKey, $partial);
        $this->assertSame('pending_collection', $retried['payment_status']);
        $this->assertSame(2, DB::table('orders')->count());
        $this->assertSame(2, DB::table('reservations')->count());
        $this->assertSame(3, (int) DB::table('reservation_allocations')->whereNull('released_at')->sum('quantity'));
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

    public function test_archived_outlet_blocks_retry_cancel_and_their_completed_replays(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $fake = $this->fakeProvider();

        $failedOrder = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-failed'),
            $this->checkoutInput($product->public_id, 'jazzcash'));
        $failedIntent = $this->service()->initiate($failedOrder['payment_id']);
        $this->service()->callback('jazzcash', $fake->failed('EVT-ARCHIVE', $failedIntent['reference'], '200.02'));
        $retryKey = $this->key('archive-retry');
        $this->service()->retry($this->scope(), $this->customer, $failedOrder['order_id'], $retryKey, 'jazzcash');

        $cancelOrder = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-cancel-order'),
            $this->checkoutInput($product->public_id, 'cod'));
        $cancelKey = $this->key('archive-cancel');
        $this->service()->cancel($this->scope(), $this->customer, $cancelOrder['order_id'], $cancelKey);
        $counts = [DB::table('orders')->count(), DB::table('reservations')->count(), DB::table('payments')->count(),
            DB::table('reservation_allocations')->count(), DB::table('idempotency_requests')->count()];

        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();
        $attempts = [
            fn () => $this->service()->retry($this->scope(), $this->customer, $failedOrder['order_id'], $retryKey, 'jazzcash'),
            fn () => $this->service()->retry($this->scope(), $this->customer, $failedOrder['order_id'], $this->key('archive-retry-fresh'), 'jazzcash'),
            fn () => $this->service()->cancel($this->scope(), $this->customer, $cancelOrder['order_id'], $cancelKey),
            fn () => $this->service()->cancel($this->scope(), $this->customer, $cancelOrder['order_id'], $this->key('archive-cancel-fresh')),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Archived outlet accepted retry/cancel mutation or completed replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }

        $this->assertSame($counts, [DB::table('orders')->count(), DB::table('reservations')->count(), DB::table('payments')->count(),
            DB::table('reservation_allocations')->count(), DB::table('idempotency_requests')->count()]);
    }

    public function test_website_four_channel_matrix_and_synthetic_provider_replay_security(): void
    {
        $registry = new PaymentProviders;
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'],
            array_column($registry->checkoutChannels(), 'code'));
        $this->assertSame([true, false, false, false],
            array_column($registry->checkoutChannels(), 'available'));
        $product = $this->product();
        $this->acquire($product, 3);
        $fake = new FakePaymentProvider;
        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            config()->set('commerce.providers.'.$gateway, ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
            $registry->register($gateway, $fake);
        }
        $this->app->instance(PaymentProviders::class, $registry);
        $this->assertSame([true, true, true, true], array_column($registry->checkoutChannels(), 'available'));
        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $order = $this->service()->checkout($this->scope(), $this->customer,
                $this->key('w04-'.$gateway), $this->checkoutInput($product->public_id, $gateway));
            $this->assertSame('pending', $order['payment_status']);
            $intent = $this->service()->initiate($order['payment_id']);
            $this->assertSame($intent, $this->service()->initiate($order['payment_id']));
            $this->reject(fn () => $this->service()->callback($gateway,
                $fake->paid('W04-INVALID-'.$gateway, $intent['reference'], '200.03')));
            $event = $fake->paid('W04-PAID-'.$gateway, $intent['reference'], '200.02');
            $this->reject(fn () => $this->service()->callback($gateway, [...$event, 'amount' => '200.03']));
            $this->assertSame('paid', $this->service()->callback($gateway, $event)['payment_status']);
            $this->assertSame('paid', $this->service()->callback($gateway, $event)['payment_status']);
        }
        $this->assertSame(3, DB::table('payment_receipts')->count());
        $this->assertSame(3, DB::table('sales')->count());
        $this->assertSame(0, $product->fresh()->qty);
    }

    public function test_w04_inflight_provider_cancellation_preserves_reference_without_redirect(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = new FakePaymentProvider;
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$order) {
            $this->service()->cancel($this->scope(), $this->customer, $order['order_id'], $this->key('inflight-cancel-done'));
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('inflight-cancel'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $this->assertSame('GW-'.$order['payment_id'], $payment->gateway_order_reference);
        $this->assertSame('cancelled', DB::table('orders')->where('public_id', $order['order_id'])->value('status'));
        $this->assertSame(0, DB::table('sales')->count());
        $paid = $this->service()->callback('jazzcash', $fake->paid('W04-INFLIGHT-CANCEL-PAID',
            $payment->gateway_order_reference, '200.02'));
        $this->assertSame('paid_reconciliation', $paid['payment_status']);
        $this->assertTrue($paid['reconciliation_required']);
        $this->assertSame('cancelled', $paid['order_status']);
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_w04_inflight_reservation_expiry_keeps_reference_for_late_receipt(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = new FakePaymentProvider;
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$order) {
            $paymentId = DB::table('payments')->where('public_id', $order['payment_id'])->value('id');
            DB::table('reservations')->where('website_payment_id', $paymentId)
                ->update(['reservation_expires_at' => now()->subSecond()]);
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('inflight-expire'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $reference = DB::table('payments')->where('public_id', $order['payment_id'])->value('gateway_order_reference');
        $this->assertSame('GW-'.$order['payment_id'], $reference);
        $this->assertSame(1, $this->service()->expireDue());
        $paid = $this->service()->callback('jazzcash', $fake->paid('W04-INFLIGHT-EXPIRE-PAID', $reference, '200.02'));
        $this->assertSame('paid_reconciliation', $paid['payment_status']);
        $this->assertTrue($paid['reconciliation_required']);
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_w04_inflight_provider_disable_denies_new_redirect_but_retains_reference(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $fake = new FakePaymentProvider;
        $config = ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test'];
        config()->set('commerce.providers.jazzcash', $config);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$change, $config) {
            config()->set('commerce.providers.jazzcash', array_replace($config, $change));
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        foreach ([['enabled' => false], ['merchant' => 'rotated-merchant'], ['mode' => 'sandbox']] as $index => $change) {
            config()->set('commerce.providers.jazzcash', $config);
            $order = $this->service()->checkout($this->scope(), $this->customer,
                $this->key('inflight-config-'.$index), $this->checkoutInput($product->public_id, 'jazzcash'));
            $this->reject(fn () => $this->service()->initiate($order['payment_id']));
            $reference = DB::table('payments')->where('public_id', $order['payment_id'])->value('gateway_order_reference');
            $this->assertSame('GW-'.$order['payment_id'], $reference);
            $this->assertSame(0, DB::table('payment_receipts')->count());
            $this->assertSame(0, DB::table('sales')->count());
            config()->set('commerce.providers.jazzcash', $config);
            $this->assertSame($reference, $this->service()->initiate($order['payment_id'])['reference']);
        }
    }

    public function test_w04_inflight_early_verified_callback_does_not_return_redirect_or_duplicate_sale(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = new FakePaymentProvider;
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$order, $fake) {
            // A provider that echoes our public payment ID can call back before it returns its gateway reference.
            $received = $this->service()->callback('jazzcash', $fake->paid('W04-EARLY-PAID', $order['payment_id'], '200.02'));
            $this->assertSame('paid', $received['payment_status']);
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('inflight-early-paid'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $this->assertSame('GW-'.$order['payment_id'], $payment->gateway_order_reference);
        $this->assertSame('paid', $payment->status);
        $this->assertSame('confirmed', DB::table('orders')->where('public_id', $order['order_id'])->value('status'));
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $this->assertSame(1, DB::table('sales')->count());
    }

    public function test_w04_inflight_early_unknown_result_denies_redirect_preserves_hold(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = new FakePaymentProvider;
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$order, $fake) {
            // A provider that echoes our public payment ID can call back before it returns its gateway reference.
            $received = $this->service()->callback('jazzcash', $fake->unknown('W04-EARLY-UNKNOWN', $order['payment_id'], '200.02'));
            $this->assertSame('unknown', $received['payment_status']);
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('inflight-early-unknown'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $this->assertSame('GW-'.$order['payment_id'], $payment->gateway_order_reference);
        $this->assertSame('unknown', $payment->status);
        $this->assertSame('pending', DB::table('orders')->where('public_id', $order['order_id'])->value('status'));
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_w04_inflight_early_failed_result_denies_redirect_and_reconciles_late_paid(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = new FakePaymentProvider;
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class($fake, function () use (&$order, $fake) {
            // A provider that echoes our public payment ID can call back before it returns its gateway reference.
            $received = $this->service()->callback('jazzcash', $fake->failed('W04-EARLY-FAILED', $order['payment_id'], '200.02'));
            $this->assertSame('failed', $received['payment_status']);
        }) implements PaymentProvider {

            public function __construct(private PaymentProvider $delegate, private \Closure $onNetwork) {}

            public function initiate(array $intent): array
            {
                ($this->onNetwork)();

                return $this->delegate->initiate($intent);
            }

            public function verify(array $payload): array
            {
                return $this->delegate->verify($payload);
            }
        });
        $this->app->instance(PaymentProviders::class, $registry);
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('inflight-early-failed'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $this->assertSame('GW-'.$order['payment_id'], $payment->gateway_order_reference);
        $this->assertSame('failed', $payment->status);
        $this->assertSame('cancelled', DB::table('orders')->where('public_id', $order['order_id'])->value('status'));
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
        $this->reject(fn () => $this->service()->initiate($order['payment_id']));
        $late = $this->service()->callback('jazzcash', $fake->paid('W04-EARLY-FAILED-LATE-PAID', $payment->gateway_order_reference, '200.02'));
        $this->assertSame('paid_reconciliation', $late['payment_status']);
        $this->assertTrue($late['reconciliation_required']);
        $this->assertSame(2, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_w04_cancelled_external_order_cannot_start_or_resume_hosted_payment(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $this->fakeProvider();
        foreach ([false, true] as $alreadyInitiated) {
            $order = $this->service()->checkout($this->scope(), $this->customer,
                $this->key('cancel-init-'.(int) $alreadyInitiated), $this->checkoutInput($product->public_id, 'jazzcash'));
            $original = $alreadyInitiated ? $this->service()->initiate($order['payment_id']) : null;
            $this->service()->cancel($this->scope(), $this->customer, $order['order_id'],
                $this->key('cancel-external-'.(int) $alreadyInitiated));
            $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
            $this->reject(fn () => $this->service()->initiate($order['payment_id']));
            $this->assertSame('cancelled', DB::table('orders')->where('public_id', $order['order_id'])->value('status'));
            $this->assertSame($original['reference'] ?? null, DB::table('payments')->where('id', $payment->id)->value('gateway_order_reference'));
        }
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
    }

    public function test_w04_paid_callback_after_customer_cancellation_requires_reconciliation_without_sale(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('cancel-late-receipt'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $intent = $this->service()->initiate($order['payment_id']);
        $this->service()->cancel($this->scope(), $this->customer, $order['order_id'], $this->key('cancel-late-receipt-done'));
        $event = $fake->paid('W04-CANCELLED-LATE-PAID', $intent['reference'], '200.02');
        $result = $this->service()->callback('jazzcash', $event);
        $this->assertSame('paid_reconciliation', $result['payment_status']);
        $this->assertSame('cancelled', $result['order_status']);
        $this->assertTrue($result['reconciliation_required']);
        $this->assertSame($result, $this->service()->callback('jazzcash', $event));
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
    }

    public function test_w04_elapsed_reservation_without_scheduler_blocks_new_or_cached_hosted_payment(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $this->fakeProvider();
        foreach ([false, true] as $alreadyInitiated) {
            $order = $this->service()->checkout($this->scope(), $this->customer,
                $this->key('elapsed-init-'.(int) $alreadyInitiated), $this->checkoutInput($product->public_id, 'jazzcash'));
            $original = $alreadyInitiated ? $this->service()->initiate($order['payment_id']) : null;
            $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
            DB::table('reservations')->where('website_payment_id', $payment->id)
                ->update(['reservation_expires_at' => now()->subSecond()]);
            $this->reject(fn () => $this->service()->initiate($order['payment_id']));
            $this->assertSame($original['reference'] ?? null,
                DB::table('payments')->where('id', $payment->id)->value('gateway_order_reference'));
            $this->assertSame('pending', DB::table('payments')->where('id', $payment->id)->value('status'));
        }
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
    }

    public function test_w04_cached_redirect_is_not_reissued_after_provider_disable_or_merchant_mode_change(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('cached-redirect'), $this->checkoutInput($product->public_id, 'jazzcash'));
        $original = $this->service()->initiate($order['payment_id']);
        $this->assertSame($original, $this->service()->initiate($order['payment_id']));
        $row = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $originalConfig = config('commerce.providers.jazzcash');
        foreach ([['enabled' => false], ['merchant' => 'different-merchant'], ['mode' => 'sandbox']] as $change) {
            config()->set('commerce.providers.jazzcash', array_replace($originalConfig, $change));
            $this->reject(fn () => $this->service()->initiate($order['payment_id']));
            $current = DB::table('payments')->where('id', $row->id)->firstOrFail();
            $this->assertSame($row->gateway_order_reference, $current->gateway_order_reference);
            $this->assertSame($row->gateway_response, $current->gateway_response);
            $this->assertSame('pending', $current->status);
        }
        config()->set('commerce.providers.jazzcash', $originalConfig);
        $this->assertSame($original, $this->service()->initiate($order['payment_id']));
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
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

    public function test_archived_outlet_preserves_verified_callback_as_reconciliation_without_sale(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $fake = $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-callback'),
            $this->checkoutInput($product->public_id, 'jazzcash'));
        $intent = $this->service()->initiate($order['payment_id']);
        $payload = $fake->paid('EVT-ARCHIVED-CALLBACK', $intent['reference'], '200.02');
        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();

        $result = $this->service()->callback('jazzcash', $payload);
        $this->assertSame('paid_reconciliation', $result['payment_status']);
        $this->assertTrue($result['reconciliation_required']);
        $this->assertSame($result, $this->service()->callback('jazzcash', $payload));
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
    }

    public function test_archived_outlet_blocks_payment_initiation_before_provider_reference(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-initiate'),
            $this->checkoutInput($product->public_id, 'jazzcash'));
        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();

        try {
            $this->service()->initiate($order['payment_id']);
            $this->fail('Archived outlet started an external payment intent.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $payment = DB::table('payments')->where('public_id', $order['payment_id'])->firstOrFail();
        $this->assertNull($payment->gateway_order_reference);
        $this->assertNull($payment->gateway_response);
        $this->assertSame('pending', $payment->status);
        $this->assertSame(0, DB::table('payment_receipts')->count());
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
        $this->reject(fn () => $this->service()->retry($this->scope(), $this->customer,
            $order['order_id'], $this->key('unknown-no-retry'), 'jazzcash'));
        $this->assertSame(1, DB::table('payments')->where('public_id', $order['payment_id'])->count());
        $paid = $this->service()->callback('jazzcash', $fake->paid('EVT-U-PAID', $init['reference'], '200.02'));
        $this->assertSame('paid', $paid['payment_status']);
        $this->assertSame('confirmed', $paid['order_status']);
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(2, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertEquals($paid, $this->service()->callback('jazzcash',
            $fake->paid('EVT-U-PAID', $init['reference'], '200.02')));
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame(2, DB::table('payment_receipts')->count());
    }

    public function test_expiry_locks_and_rechecks_active_outlet_before_releasing_reservation(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $this->fakeProvider();
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-expiry'),
            $this->checkoutInput($product->public_id, 'jazzcash'));
        $paymentId = DB::table('payments')->where('public_id', $order['payment_id'])->value('id');
        $reservation = DB::table('reservations')->where('website_payment_id', $paymentId)->firstOrFail();
        DB::table('reservations')->where('id', $reservation->id)->update(['reservation_expires_at' => now()->subSecond()]);
        $allocation = DB::table('reservation_allocations')->where('reservation_line_id',
            DB::table('reservation_lines')->where('reservation_id', $reservation->id)->value('id'))->firstOrFail();

        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();
        try {
            $this->service()->expireDue();
            $this->fail('Archived outlet reservation was expired and released.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('active', DB::table('reservations')->where('id', $reservation->id)->value('state'));
        $this->assertNull(DB::table('reservation_allocations')->where('id', $allocation->id)->value('released_at'));
        $this->assertSame('pending', DB::table('payments')->where('id', $paymentId)->value('status'));
        $this->assertSame('unpaid', DB::table('orders')->where('public_id', $order['order_id'])->value('payment_status'));

        $this->outlet->forceFill(['archived_at' => null, 'version' => $this->outlet->version + 1])->save();
        $this->assertSame(1, $this->service()->expireDue());
        $this->assertSame('expired', DB::table('reservations')->where('id', $reservation->id)->value('state'));
        $this->assertNotNull(DB::table('reservation_allocations')->where('id', $allocation->id)->value('released_at'));
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

    public function test_w04_refund_cannot_apply_a_different_collected_orders_payment_to_a_valid_return(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $first = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('w04-refund-first'), $this->checkoutInput($product->public_id, 'cod'));
        $second = $this->service()->checkout($this->scope(), $this->customer,
            $this->key('w04-refund-second'), $this->checkoutInput($product->public_id, 'cod'));
        $this->service()->collectCod($this->actor, $this->outlet, $first['order_id'],
            $this->key('w04-refund-first-paid'), '200.02', 'COD-FIRST');
        $this->service()->collectCod($this->actor, $this->outlet, $second['order_id'],
            $this->key('w04-refund-second-paid'), '200.02', 'COD-SECOND');
        $invoice = DB::table('invoices')->join('orders', 'orders.id', '=', 'invoices.order_id')
            ->where('orders.public_id', $first['order_id'])->select('invoices.*')->firstOrFail();
        $sale = DB::table('sales')->where('invoice_id', $invoice->id)->firstOrFail();
        $return = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet,
            $this->key('w04-refund-first-return'), ['invoice_id' => $invoice->public_id,
                'reason' => 'Synthetic cross-payment refund boundary',
                'lines' => [['sale_id' => $sale->public_id, 'quantity' => 1,
                    'condition' => 'opened', 'disposition' => 'sellable']]]);
        $this->reject(fn () => $this->service()->manualRefund($this->actor, $return['return_id'],
            $second['payment_id'], $this->key('w04-refund-cross-payment'), '200.02', str_repeat('a', 64)));
        $this->assertSame(0, DB::table('refunds')->count());
        $this->assertNotSame('partial', DB::table('orders')->where('public_id', $second['order_id'])->value('refund_status'));
        $key = $this->key('w04-refund-valid-payment');
        $original = $this->service()->manualRefund($this->actor, $return['return_id'],
            $first['payment_id'], $key, '200.02', str_repeat('b', 64));
        $this->assertSame('completed', $original['status']);
        $this->assertEquals($original, $this->service()->manualRefund($this->actor, $return['return_id'],
            $first['payment_id'], $key, '200.02', str_repeat('b', 64)));
        $this->reject(fn () => $this->service()->manualRefund($this->actor, $return['return_id'],
            $first['payment_id'], $key, '200.02', str_repeat('c', 64)));
        $this->assertSame(1, DB::table('refunds')->count());
        $this->assertSame('refunded', DB::table('orders')->where('public_id', $first['order_id'])->value('refund_status'));
        $this->assertNotSame('refunded', DB::table('orders')->where('public_id', $second['order_id'])->value('refund_status'));
    }

    public function test_archived_outlet_blocks_cod_and_refund_completed_replays_and_fresh_mutations(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-money-order'),
            $this->checkoutInput($product->public_id, 'cod', 2));
        $collectKey = $this->key('archive-collect');
        $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'], $collectKey, '400.04', 'COD-ARCHIVE');
        $invoice = DB::table('invoices')->whereNotNull('order_id')->firstOrFail();
        $sale = DB::table('sales')->where('invoice_id', $invoice->id)->firstOrFail();
        $return = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, $this->key('archive-return'), [
            'invoice_id' => $invoice->public_id, 'reason' => 'Synthetic archive refund',
            'lines' => [['sale_id' => $sale->public_id, 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable']],
        ]);
        $refundKey = $this->key('archive-refund');
        $evidence = str_repeat('d', 64);
        $this->service()->manualRefund($this->actor, $return['return_id'], $order['payment_id'], $refundKey, '200.02', $evidence);
        $counts = [DB::table('payment_receipts')->count(), DB::table('refunds')->count(),
            DB::table('idempotency_requests')->count(), DB::table('identity_audit_events')->count()];

        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();
        $attempts = [
            fn () => $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'], $collectKey, '400.04', 'COD-ARCHIVE'),
            fn () => $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'], $this->key('archive-collect-fresh'), '400.04', 'COD-ARCHIVE-2'),
            fn () => $this->service()->manualRefund($this->actor, $return['return_id'], $order['payment_id'], $refundKey, '200.02', $evidence),
            fn () => $this->service()->manualRefund($this->actor, $return['return_id'], $order['payment_id'], $this->key('archive-refund-fresh'), '200.02', str_repeat('e', 64)),
        ];
        foreach ($attempts as $attempt) {
            try {
                $attempt();
                $this->fail('Archived outlet accepted COD/refund mutation or completed replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertSame($counts, [DB::table('payment_receipts')->count(), DB::table('refunds')->count(),
            DB::table('idempotency_requests')->count(), DB::table('identity_audit_events')->count()]);
    }

    public function test_archived_outlet_blocks_new_product_review(): void
    {
        $product = $this->product();
        DB::table('product_listings')->insert(['external_source' => 'pos', 'external_id' => 'review-'.$product->id,
            'slug' => 'review-'.$product->public_id, 'name' => $product->name, 'category' => $product->category,
            'is_online' => true, 'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $product->id,
            'created_at' => now(), 'updated_at' => now()]);
        $this->acquire($product);
        $order = $this->service()->checkout($this->scope(), $this->customer, $this->key('archive-review-order'),
            $this->checkoutInput($product->public_id, 'cod'));
        $this->service()->collectCod($this->actor, $this->outlet, $order['order_id'],
            $this->key('archive-review-collect'), '200.02', 'COD-REVIEW');
        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();

        try {
            app(ProductReviews::class)->submit($this->customer, ['order_id' => $order['order_id'],
                'product_id' => $product->public_id, 'rating' => 5, 'body' => 'Synthetic archived review']);
            $this->fail('Archived outlet accepted a product review.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('product_reviews')->count());
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
