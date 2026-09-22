<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

/** W04: an enabled-looking external channel cannot create an order or milestone payment without a verified adapter. */
final class W04UnavailableChannelTransactionGateTest extends TestCase
{
    use DatabaseTransactions;

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
