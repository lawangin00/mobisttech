<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/** W04: an enabled-looking external channel cannot create an order or milestone payment without a verified adapter. */
final class W04UnavailableChannelTransactionGateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_unrecognized_external_mode_rejects_checkout_and_milestone_before_mutation_even_with_adapter(): void
    {
        $registry = new PaymentProviders;
        $adapter = new class implements PaymentProvider
        {
            public function initiate(array $intent): array
            {
                throw new LogicException('An unrecognized payment mode reached the adapter.');
            }

            public function verify(array $payload): array
            {
                throw new LogicException('An unrecognized payment mode reached the verifier.');
            }
        };
        $baseline = [DB::table('orders')->count(), DB::table('payments')->count(),
            DB::table('reservations')->count(), DB::table('idempotency_requests')->count()];
        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $registry->register($gateway, $adapter);
            config()->set('commerce.providers.'.$gateway, ['enabled' => true,
                'merchant' => 'synthetic-merchant', 'mode' => 'production']);
        }
        $this->app->instance(PaymentProviders::class, $registry);
        $service = app(OrderTransactions::class);
        foreach (['jazzcash', 'easypaisa', 'card'] as $index => $gateway) {
            $this->assertFalse(collect($registry->checkoutChannels())->firstWhere('code', $gateway)['available']);
            try {
                $service->checkout('guest:'.str_repeat('a', 64), null,
                    'w04-bad-mode-checkout-'.$index, ['customer_name' => 'Synthetic buyer',
                        'customer_mobile' => '03001112222', 'gateway' => $gateway,
                        'lines' => [['product_id' => '00000000-0000-4000-8000-000000000001', 'quantity' => 1]]]);
                $this->fail('An unrecognized mode created a checkout.');
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }
            try {
                $service->milestone(new CustomerAccount, 'w04-bad-mode-milestone-'.$index,
                    ['milestone_id' => '00000000-0000-4000-8000-000000000001', 'gateway' => $gateway]);
                $this->fail('An unrecognized mode created a milestone payment.');
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }
            $this->assertSame($baseline, [DB::table('orders')->count(), DB::table('payments')->count(),
                DB::table('reservations')->count(), DB::table('idempotency_requests')->count()]);
        }
    }

    public function test_unregistered_external_channels_fail_before_checkout_or_milestone_mutation(): void
    {
        $service = app(OrderTransactions::class);
        $baseline = [
            DB::table('orders')->count(),
            DB::table('payments')->count(),
            DB::table('reservations')->count(),
            DB::table('idempotency_requests')->count(),
            DB::table('payment_receipts')->count(),
        ];
        $checkout = [
            'customer_name' => 'Synthetic buyer',
            'customer_mobile' => '03001112222',
            'lines' => [['product_id' => '00000000-0000-4000-8000-000000000001', 'quantity' => 1]],
        ];

        foreach (['jazzcash', 'easypaisa', 'card'] as $index => $gateway) {
            config()->set('commerce.providers.'.$gateway, [
                'enabled' => true,
                'merchant' => 'synthetic-merchant',
                'mode' => 'test',
            ]);

            try {
                $service->checkout('guest:'.str_repeat('a', 64), null,
                    'w04-unavailable-checkout-'.$index, [...$checkout, 'gateway' => $gateway]);
                $this->fail('Unregistered gateway created a checkout: '.$gateway);
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }

            try {
                $service->milestone(new CustomerAccount, 'w04-unavailable-milestone-'.$index, [
                    'milestone_id' => '00000000-0000-4000-8000-000000000001',
                    'gateway' => $gateway,
                ]);
                $this->fail('Unregistered gateway created a milestone payment: '.$gateway);
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }

            $this->assertSame($baseline, [
                DB::table('orders')->count(),
                DB::table('payments')->count(),
                DB::table('reservations')->count(),
                DB::table('idempotency_requests')->count(),
                DB::table('payment_receipts')->count(),
            ]);
        }
    }
}
