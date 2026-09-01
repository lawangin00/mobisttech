<?php

namespace App\Commerce;

use LogicException;

final class PaymentProviders
{
    /** @var array<string, PaymentProvider> */
    private array $adapters = [];

    public function register(string $gateway, PaymentProvider $provider): void
    {
        if (! preg_match('/\A[a-z][a-z0-9_]{1,39}\z/', $gateway) || $gateway === 'cod') {
            throw new LogicException('Invalid external payment provider key.');
        }
        $this->adapters[$gateway] = $provider;
    }

    public function assertAvailable(string $gateway): array
    {
        $configuration = config('commerce.providers.'.$gateway);
        if (! is_array($configuration) || ! ($configuration['enabled'] ?? false)
            || ($gateway !== 'cod' && ! isset($this->adapters[$gateway]))) {
            throw new LogicException('provider_unavailable');
        }

        return $configuration;
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
