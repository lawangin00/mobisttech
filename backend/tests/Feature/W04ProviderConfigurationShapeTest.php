<?php

namespace Tests\Feature;

use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use LogicException;
use Tests\TestCase;

/** An adapter and enabled flag cannot rescue invalid merchant/environment settings. */
final class W04ProviderConfigurationShapeTest extends TestCase
{
    public function test_all_external_channels_reject_invalid_merchant_or_mode_before_initiation(): void
    {
        $adapter = new class implements PaymentProvider
        {
            public function initiate(array $intent): array
            {
                throw new LogicException('An invalid configuration reached its adapter.');
            }

            public function verify(array $payload): array
            {
                throw new LogicException('An invalid configuration reached its verifier.');
            }
        };

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $registry = new PaymentProviders;
            $registry->register($gateway, $adapter);
            foreach ([
                ['merchant' => [], 'mode' => 'sandbox'],
                ['merchant' => 123, 'mode' => 'sandbox'],
                ['merchant' => '   ', 'mode' => 'sandbox'],
                ['merchant' => 'synthetic-merchant', 'mode' => []],
                ['merchant' => 'synthetic-merchant', 'mode' => null],
                ['merchant' => 'synthetic-merchant', 'mode' => '   '],
                ['merchant' => 'synthetic-merchant', 'mode' => 'production'],
                ['merchant' => 'synthetic-merchant', 'mode' => 'LIVE'],
                ['merchant' => 'synthetic-merchant', 'mode' => 'sandbox '],
                ['merchant' => 'synthetic-merchant', 'mode' => 'sandbox', 'enabled' => 'false'],
                ['merchant' => 'synthetic-merchant', 'mode' => 'sandbox', 'enabled' => 1],
                ['merchant' => 'synthetic-merchant', 'mode' => 'sandbox', 'enabled' => 'true'],
            ] as $invalid) {
                config()->set('commerce.providers.'.$gateway, [...$invalid, 'enabled' => $invalid['enabled'] ?? true]);
                $this->assertFalse(collect($registry->checkoutChannels())->firstWhere('code', $gateway)['available']);
                try {
                    $registry->initiate($gateway, ['payment_id' => 'synthetic-only']);
                    $this->fail('Invalid configuration initiated payment for '.$gateway);
                } catch (LogicException $exception) {
                    $this->assertSame('provider_unavailable', $exception->getMessage());
                }
            }

            config()->set('commerce.providers.'.$gateway, [
                'enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'sandbox',
            ]);
            $this->assertTrue(collect($registry->checkoutChannels())->firstWhere('code', $gateway)['available']);
        }
    }
}
