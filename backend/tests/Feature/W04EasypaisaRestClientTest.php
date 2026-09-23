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
                'accountNum' => '654123987', 'transactionStatus' => 'PENDING', 'paymentMode' => 'MA',
                'transactionAmount' => '1.23', 'responseCode' => '0000']),
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

    public function test_ma_init_without_provider_transaction_id_never_counts_as_accepted(): void
    {
        foreach ([null, '', str_repeat('x', 256)] as $transactionId) {
            Http::fake(['*/initiate-ma-transaction' => Http::response([
                'orderId' => 'order-1', 'storeId' => 43, 'responseCode' => '0000',
                'transactionId' => $transactionId,
            ])]);
            try {
                (new EasypaisaRestClient($this->settings()))->initiateMa(
                    'order-1', '1.23', '03001234567', 'buyer@example.invalid'
                );
                $this->fail('Invalid provider transaction ID was accepted.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('transaction ID', $exception->getMessage());
            }
        }
    }

    public function test_inquiry_rejects_mismatched_account_or_incomplete_provider_status(): void
    {
        foreach ([
            ['accountNum' => 'other', 'transactionStatus' => 'PAID', 'paymentMode' => 'MA', 'transactionAmount' => '1.23'],
            ['accountNum' => '654123987', 'transactionStatus' => 'UNKNOWN', 'paymentMode' => 'MA', 'transactionAmount' => '1.23'],
            ['accountNum' => '654123987', 'transactionStatus' => 'PAID', 'paymentMode' => 'MA', 'transactionAmount' => '1.234'],
            ['accountNum' => '654123987', 'transactionStatus' => 'PAID', 'paymentMode' => 'other', 'transactionAmount' => '1.23'],
        ] as $invalid) {
            Http::fake(['*/inquire-transaction' => Http::response(['orderId' => 'order-1', 'storeId' => 43,
                'responseCode' => '0000', ...$invalid])]);
            try {
                (new EasypaisaRestClient($this->settings()))->inquire('order-1');
                $this->fail('Unbound inquiry response accepted.');
            } catch (LogicException $exception) {
                $this->assertStringContainsString('payment remains unverified', $exception->getMessage());
            }
        }
    }

    public function test_inquiry_is_bound_to_original_ma_amount_without_settling_order(): void
    {
        Http::fake(['*/inquire-transaction' => Http::response(['orderId' => 'order-1', 'storeId' => 43,
            'accountNum' => '654123987', 'transactionStatus' => 'PAID', 'paymentMode' => 'MA',
            'transactionAmount' => '1.23', 'responseCode' => '0000'])]);
        $client = new EasypaisaRestClient($this->settings());
        $this->assertSame(['order_id' => 'order-1', 'amount' => '1.23', 'provider_status' => 'PAID',
            'verified_for_settlement' => false], $client->inquireForPayment('order-1', '1.23'));
        foreach (['1.24', '0.00', '1.234'] as $amount) {
            try {
                $client->inquireForPayment('order-1', $amount);
                $this->fail('Mismatched or invalid original amount was accepted.');
            } catch (LogicException $exception) {
                $this->assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function test_otc_inquiry_cannot_satisfy_ma_payment(): void
    {
        Http::fake(['*/inquire-transaction' => Http::response(['orderId' => 'order-1', 'storeId' => 43,
            'accountNum' => '654123987', 'transactionStatus' => 'PAID', 'paymentMode' => 'OTC',
            'transactionAmount' => '1.23', 'responseCode' => '0000'])]);
        $this->expectException(LogicException::class);
        (new EasypaisaRestClient($this->settings()))->inquireForPayment('order-1', '1.23');
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
