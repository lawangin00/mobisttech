<?php

namespace Tests\Feature;

use Tests\TestCase;

/** W04: the masked payment-status surfaces are not credential-write endpoints. */
final class W04PaymentSettingsReadOnlyHttpTest extends TestCase
{
    public function test_payment_status_endpoints_reject_all_mutation_verbs_without_enabling_providers(): void
    {
        $originalProviders = config('commerce.providers');
        $attemptedWrite = [
            'provider' => 'jazzcash',
            'enabled' => true,
            'merchant' => 'synthetic-do-not-store',
            'credentials' => 'synthetic-do-not-store',
        ];

        foreach (['/internal/admin/website/payment-settings', '/internal/admin/website/payment-channels'] as $path) {
            foreach (['postJson', 'putJson', 'patchJson', 'deleteJson'] as $verb) {
                $this->{$verb}($path, $attemptedWrite)->assertStatus(405);
                $this->assertSame($originalProviders, config('commerce.providers'));
            }
        }
    }
}
