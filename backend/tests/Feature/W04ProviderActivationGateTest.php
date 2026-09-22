<?php

namespace Tests\Feature;

use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use LogicException;
use Tests\TestCase;

/** W04: configuration flags alone must never advertise an unverified external adapter. */
final class W04ProviderActivationGateTest extends TestCase
{
    public function test_enabling_external_configuration_without_an_adapter_never_advertises_a_payment_channel(): void
    {
        $registry = new PaymentProviders;

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            config()->set('commerce.providers.'.$gateway, [
                'enabled' => true,
                'merchant' => 'synthetic-merchant',
                'mode' => 'test',
            ]);
        }

        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_column($registry->checkoutChannels(), 'code'));
        $this->assertSame([true, false, false, false], array_column($registry->checkoutChannels(), 'available'));

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            try {
                $registry->initiate($gateway, ['payment_id' => 'synthetic-only']);
                $this->fail('Unregistered provider initiated a payment: '.$gateway);
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }
        }
    }

    public function test_registering_a_synthetic_adapter_without_an_enabled_merchant_still_fails_closed(): void
    {
        $registry = new PaymentProviders;
        $synthetic = new class implements PaymentProvider
        {
            public function initiate(array $intent): array
            {
                throw new LogicException('A disabled provider must not reach its adapter.');
            }

            public function verify(array $payload): array
            {
                throw new LogicException('A disabled provider must not reach its adapter.');
            }
        };

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $registry->register($gateway, $synthetic);
            config()->set('commerce.providers.'.$gateway, [
                'enabled' => true,
                'merchant' => '',
                'mode' => 'test',
            ]);
        }

        $this->assertSame([true, false, false, false], array_column($registry->checkoutChannels(), 'available'));

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            try {
                $registry->initiate($gateway, ['payment_id' => 'synthetic-only']);
                $this->fail('Provider without a merchant initiated a payment: '.$gateway);
            } catch (LogicException $exception) {
                $this->assertSame('provider_unavailable', $exception->getMessage());
            }
        }
    }
}
