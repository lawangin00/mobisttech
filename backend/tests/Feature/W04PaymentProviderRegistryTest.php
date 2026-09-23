<?php

namespace Tests\Feature;

use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use LogicException;
use Tests\TestCase;

final class W04PaymentProviderRegistryTest extends TestCase
{
    public function test_only_three_declared_external_channels_can_register_adapters(): void
    {
        $providers = new PaymentProviders;
        $adapter = $this->adapter('approved');

        foreach (['cod', 'bank_transfer', 'wallet', 'card_extra', 'jazzcash_fake', '', 'JAZZCASH'] as $gateway) {
            try {
                $providers->register($gateway, $adapter);
                $this->fail('Unapproved payment channel accepted an adapter.');
            } catch (LogicException $exception) {
                $this->assertSame('Invalid or duplicate external payment provider registration.', $exception->getMessage());
            }
        }

        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $providers->register($gateway, $adapter);
        }
        $this->assertSame(['cod', 'jazzcash', 'easypaisa', 'card'], array_column($providers->checkoutChannels(), 'code'));
    }

    public function test_duplicate_registration_cannot_replace_first_provider_or_its_verifier(): void
    {
        $providers = new PaymentProviders;
        $first = $this->adapter('approved');
        $providers->register('jazzcash', $first);

        try {
            $providers->register('jazzcash', $this->adapter('untrusted'));
            $this->fail('A later adapter silently replaced the registered verifier.');
        } catch (LogicException $exception) {
            $this->assertSame('Invalid or duplicate external payment provider registration.', $exception->getMessage());
        }

        config()->set('commerce.providers.jazzcash', [
            'enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test',
        ]);
        $this->assertSame(['reference' => 'approved', 'redirect_url' => 'https://example.invalid/hosted'],
            $providers->initiate('jazzcash', []));
        $this->assertSame('approved', $providers->verify('jazzcash', [])['event_id']);
    }

    public function test_verified_adapter_event_rejects_oversized_references_before_receipt_write(): void
    {
        $providers = new PaymentProviders;
        $providers->register('jazzcash', new class implements PaymentProvider
        {
            public function initiate(array $intent): array
            {
                return [];
            }

            public function verify(array $payload): array
            {
                return ['event_id' => $payload['event_id'], 'transaction_reference' => $payload['transaction_reference'],
                    'order_reference' => $payload['order_reference'], 'amount' => '1.00', 'currency' => 'PKR',
                    'status' => 'paid', 'payload_hash' => str_repeat('a', 64)];
            }
        });
        config()->set('commerce.providers.jazzcash', ['enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test']);
        foreach (['event_id', 'transaction_reference', 'order_reference'] as $field) {
            $payload = ['event_id' => 'evt', 'transaction_reference' => 'tx', 'order_reference' => 'order'];
            $payload[$field] = str_repeat('x', 256);
            try {
                $providers->verify('jazzcash', $payload);
                $this->fail('Oversized verified provider reference passed boundary validation.');
            } catch (LogicException $e) {
                $this->assertSame('Provider adapter returned an invalid verified event.', $e->getMessage());
            }
        }
    }

    private function adapter(string $marker): PaymentProvider
    {
        return new class($marker) implements PaymentProvider
        {
            public function __construct(private string $marker) {}

            public function initiate(array $intent): array
            {
                return ['reference' => $this->marker, 'redirect_url' => 'https://example.invalid/hosted'];
            }

            public function verify(array $payload): array
            {
                return [
                    'event_id' => $this->marker,
                    'transaction_reference' => 'synthetic-transaction',
                    'order_reference' => 'synthetic-order',
                    'amount' => '1.00',
                    'currency' => 'PKR',
                    'status' => 'paid',
                    'payload_hash' => str_repeat('a', 64),
                ];
            }
        };
    }
}
