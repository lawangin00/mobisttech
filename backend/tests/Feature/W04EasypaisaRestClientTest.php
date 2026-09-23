<?php

namespace Tests\Feature;

use App\Commerce\EasypaisaRestClient;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

final class W04EasypaisaRestClientTest extends TestCase
{
    private function settings(): array
    {
        return ['enabled' => true, 'mode' => 'sandbox', 'username' => 'fixture-user',
            'password' => 'fixture-pass', 'store_id' => '43', 'account_num' => '654123987'];
    }

    public function test_disabled_or_missing_credentials_never_contact_gateway(): void
    {
        Http::fake();
        foreach ([[], ['enabled' => true, 'mode' => 'sandbox']] as $settings) {
            try {
                (new EasypaisaRestClient($settings))->initiateMa('order-1', '1.23', '03001234567', 'buyer@example.invalid');
                $this->fail('Unavailable integration reached gateway.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        Http::assertNothingSent();
    }

    public function test_documented_ma_and_inquiry_payloads_keep_payment_unverified(): void
    {
        Http::fake([
            '*/initiate-ma-transaction' => Http::response(['orderId' => 'order-1', 'storeId' => 43,
                'transactionId' => 'synthetic-tx', 'responseCode' => '0000', 'responseDesc' => 'SUCCESS']),
            '*/inquire-transaction' => Http::response(['orderId' => 'order-1', 'storeId' => 43,
                'transactionStatus' => 'PENDING', 'responseCode' => '0000']),
        ]);
        $service = new EasypaisaRestClient($this->settings());
        $this->assertSame('synthetic-tx', $service->initiateMa('order-1', '1.23', '03001234567', 'buyer@example.invalid')['transactionId']);
        $this->assertSame('PENDING', $service->inquire('order-1')['transactionStatus']);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/initiate-ma-transaction')
            && $request->hasHeader('Credentials', base64_encode('fixture-user:fixture-pass'))
            && $request['transactionType'] === 'MA' && $request['transactionAmount'] === '1.23');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/inquire-transaction')
            && $request['accountNum'] === '654123987' && $request['orderId'] === 'order-1');
    }

    public function test_mismatched_response_and_invalid_input_fail_closed(): void
    {
        Http::fake(['*' => Http::response(['orderId' => 'unrelated', 'storeId' => 43, 'responseCode' => '0000'])]);
        $service = new EasypaisaRestClient($this->settings());
        foreach (['0300123456', '13001234567'] as $mobile) {
            try {
                $service->initiateMa('order-1', '1.23', $mobile, 'buyer@example.invalid');
                $this->fail('Invalid mobile accepted.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
        Http::assertNothingSent();
        $this->expectException(LogicException::class);
        $service->inquire('order-1');
    }
}
