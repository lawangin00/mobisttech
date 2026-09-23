<?php

namespace Tests\Feature;

use App\Commerce\EasypaisaRestClient;
use App\Commerce\PaymentProviders;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class W04EasypaisaConfigurationTest extends TestCase
{
    public function test_transport_is_off_by_default_and_is_not_a_checkout_adapter(): void
    {
        $this->assertFalse(config('easypaisa.enabled'));
        $this->assertSame('sandbox', config('easypaisa.mode'));
        $this->assertSame('rest-v4-without-rsa', config('easypaisa.variant'));
        $this->assertFalse(collect(app(PaymentProviders::class)->checkoutChannels())
            ->firstWhere('code', 'easypaisa')['available']);
        Http::fake();
        $client = app(EasypaisaRestClient::class);
        $this->expectException(\LogicException::class);
        $client->initiateMa('order-1', '1.23', '03001234567', 'buyer@example.invalid');
    }

    public function test_configured_sandbox_client_is_injected_without_exposing_secrets_in_channel_status(): void
    {
        config()->set('easypaisa', [
            'enabled' => true, 'mode' => 'sandbox', 'variant' => 'rest-v4-without-rsa',
            'username' => 'synthetic-user', 'password' => 'synthetic-secret',
            'store_id' => '43', 'account_num' => '654123987',
        ]);
        Http::fake(['*/initiate-ma-transaction' => Http::response([
            'orderId' => 'order-1', 'storeId' => 43,
            'transactionId' => 'synthetic-tx', 'responseCode' => '0000',
        ])]);
        $result = app(EasypaisaRestClient::class)->initiateMa(
            'order-1', '1.23', '03001234567', 'buyer@example.invalid'
        );
        $this->assertSame('synthetic-tx', $result['transactionId']);
        $channels = json_encode(app(PaymentProviders::class)->checkoutChannels(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('synthetic-secret', $channels);
        $this->assertStringNotContainsString('synthetic-user', $channels);
        $this->assertFalse(collect(app(PaymentProviders::class)->checkoutChannels())
            ->firstWhere('code', 'easypaisa')['available']);
    }
}
