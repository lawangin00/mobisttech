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
        if (! is_array($configuration) || ($configuration['enabled'] ?? null) !== true
            || ($gateway !== 'cod' && (! isset($this->adapters[$gateway])
                || ! is_string($configuration['merchant'] ?? null)
                || trim($configuration['merchant']) === ''
                || ! is_string($configuration['mode'] ?? null)
                || ! in_array($configuration['mode'], ['sandbox', 'test', 'live'], true)))) {
            throw new LogicException('provider_unavailable');
        }
        // A published Admin COD policy can turn COD off; it never overrides a disabled
        // deployment setting or activates any external merchant/provider adapter.
        if ($gateway === 'cod' && ! app(WebsitePaymentAdministration::class)->codEnabled()) {
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

        $policy = app(WebsitePaymentPresentation::class)->published();
        $presentation = $policy['channels'];

        return collect($labels)->map(function (string $label, string $gateway) use ($presentation, $policy) {
            try {
                $this->assertAvailable($gateway);
                $available = true;
            } catch (LogicException) {
                $available = false;
            }

            return [
                'code' => $gateway,
                'label' => $presentation[$gateway]['label'],
                'instructions' => $presentation[$gateway]['instructions'],
                ...($gateway === 'cod' ? [
                    'cod_min_amount' => $policy['cod_min_amount'],
                    'cod_max_amount' => $policy['cod_max_amount'],
                ] : []),
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
        if (array_diff($required, array_keys($event)) || array_diff(array_keys($event), $required)) {
            throw new LogicException('Provider adapter returned an invalid verified event.');
        }
        foreach ($required as $field) {
            if (! is_string($event[$field]) || trim($event[$field]) === ''
                || (in_array($field, ['event_id', 'transaction_reference', 'order_reference'], true)
                    && strlen($event[$field]) > 255)) {
                throw new LogicException('Provider adapter returned an invalid verified event.');
            }
        }
        if (! in_array($event['status'], ['paid', 'failed', 'unknown'], true)
            || $event['currency'] !== 'PKR' || ! preg_match('/\A[0-9a-f]{64}\z/', $event['payload_hash'])) {
            throw new LogicException('Provider adapter returned an invalid verified event.');
        }
        if (($configuration['merchant'] ?? '') === '') {
            throw new LogicException('provider_unavailable');
        }

        return $event + ['merchant' => $configuration['merchant'], 'mode' => $configuration['mode']];
    }
}
