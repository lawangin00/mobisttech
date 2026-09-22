<?php

namespace App\Commerce;

use LogicException;

final class PaymentProviders
{
    private const EXTERNAL_GATEWAYS = ['jazzcash', 'easypaisa', 'card'];

    /** @var array<string, PaymentProvider> */
    private array $adapters = [];

    public function register(string $gateway, PaymentProvider $provider): void
    {
        // A second registration must never silently replace an existing verifier.
        // The Website's approved checkout/payment callback set is deliberately closed.
        if (! in_array($gateway, self::EXTERNAL_GATEWAYS, true) || isset($this->adapters[$gateway])) {
            throw new LogicException('Invalid or duplicate external payment provider registration.');
        }
        $this->adapters[$gateway] = $provider;
    }

    public function assertAvailable(string $gateway): array
    {
        $configuration = config('commerce.providers.'.$gateway);
        if (! is_array($configuration) || ! ($configuration['enabled'] ?? false)
            || ($gateway !== 'cod' && (! isset($this->adapters[$gateway]) || trim((string) ($configuration['merchant'] ?? '')) === ''))) {
            throw new LogicException('provider_unavailable');
        }

        return $configuration;
    }

    public function checkoutChannels(): array
    {
        $labels = [
            'cod' => 'Cash on Delivery',
            'jazzcash' => 'JazzCash',
            'easypaisa' => 'Easypaisa',
            'card' => 'Credit / Debit Card',
        ];

        return collect($labels)->map(function (string $label, string $gateway) {
            try {
                $this->assertAvailable($gateway);
                $available = true;
            } catch (LogicException) {
                $available = false;
            }

            return [
                'code' => $gateway,
                'label' => $label,
                'available' => $available,
                'kind' => $gateway === 'cod' ? 'cash_on_delivery' : 'hosted_or_provider',
            ];
        })->values()->all();
    }

    public function initiate(string $gateway, array $intent): array
    {
        $this->assertAvailable($gateway);
        if ($gateway === 'cod') {
            throw new LogicException('COD does not use an external payment initiation.');
        }

        return $this->adapters[$gateway]->initiate($intent);
    }

    public function verify(string $gateway, array $payload): array
    {
        $configuration = $this->assertAvailable($gateway);
        if ($gateway === 'cod') {
            throw new LogicException('COD does not accept provider callbacks.');
        }
        $event = $this->adapters[$gateway]->verify($payload);
        $required = ['event_id', 'transaction_reference', 'order_reference', 'amount', 'currency', 'status', 'payload_hash'];
        if (array_diff($required, array_keys($event)) || array_diff(array_keys($event), $required)
            || ! in_array($event['status'], ['paid', 'failed', 'unknown'], true)
            || $event['currency'] !== 'PKR' || ! preg_match('/\A[0-9a-f]{64}\z/', $event['payload_hash'])) {
            throw new LogicException('Provider adapter returned an invalid verified event.');
        }
        if (($configuration['merchant'] ?? '') === '') {
            throw new LogicException('provider_unavailable');
        }

        return $event + ['merchant' => $configuration['merchant'], 'mode' => $configuration['mode']];
    }
}
