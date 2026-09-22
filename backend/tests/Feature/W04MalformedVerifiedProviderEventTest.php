<?php

namespace Tests\Feature;

use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use LogicException;
use Tests\TestCase;

/** An adapter's verified-event boundary rejects malformed values before receipt mutation. */
final class W04MalformedVerifiedProviderEventTest extends TestCase
{
    public function test_malformed_adapter_events_fail_closed_without_exposing_a_provider_credential(): void
    {
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new class implements PaymentProvider
        {
            public function initiate(array $intent): array
            {
                throw new LogicException('No synthetic payment initiation is required.');
            }

            public function verify(array $payload): array
            {
                return $payload;
            }
        });
        config()->set('commerce.providers.jazzcash', [
            'enabled' => true,
            'merchant' => 'synthetic-only-merchant',
            'mode' => 'test',
        ]);

        $valid = [
            'event_id' => 'synthetic-event',
            'transaction_reference' => 'synthetic-transaction',
            'order_reference' => 'synthetic-order',
            'amount' => '200.02',
            'currency' => 'PKR',
            'status' => 'paid',
            'payload_hash' => str_repeat('a', 64),
        ];
        $this->assertSame('synthetic-event', $registry->verify('jazzcash', $valid)['event_id']);

        foreach (['event_id', 'transaction_reference', 'order_reference', 'amount', 'currency', 'status', 'payload_hash'] as $field) {
            foreach ([null, [], '', '   '] as $bad) {
                $malformed = $valid;
                $malformed[$field] = $bad;
                try {
                    $registry->verify('jazzcash', $malformed);
                    $this->fail('Malformed verified event accepted: '.$field);
                } catch (LogicException $exception) {
                    $this->assertSame('Provider adapter returned an invalid verified event.', $exception->getMessage());
                }
            }
        }

        $this->assertSame('synthetic-only-merchant', config('commerce.providers.jazzcash.merchant'));
        $this->assertSame('test', config('commerce.providers.jazzcash.mode'));
    }
}
