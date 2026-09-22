<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Only the three approved external gateways may receive public payment callbacks. */
final class W04CallbackRouteAllowlistHttpTest extends TestCase
{
    public function test_cod_bank_transfer_and_unknown_gateways_have_no_callback_route(): void
    {
        foreach (['cod', 'bank-transfer', 'bank_transfer', 'unknown-provider'] as $gateway) {
            $this->postJson('/api/v1/payment-callbacks/'.$gateway, [
                'event_id' => 'synthetic-no-callback',
            ])->assertNotFound();
        }
    }

    public function test_approved_gateway_callback_routes_reject_non_post_verbs(): void
    {
        foreach (['jazzcash', 'easypaisa', 'card'] as $gateway) {
            $url = '/api/v1/payment-callbacks/'.$gateway;
            $this->getJson($url)->assertStatus(405);
            $this->putJson($url, [])->assertStatus(405);
            $this->patchJson($url, [])->assertStatus(405);
            $this->deleteJson($url, [])->assertStatus(405);
        }
    }
}
