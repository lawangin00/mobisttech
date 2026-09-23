<?php

namespace App\Commerce;

use Illuminate\Support\Facades\Http;
use LogicException;

/** Easypaisa REST v4 MA/OTC transport; never treats initiation as payment settlement. */
final class EasypaisaRestClient
{
    private const STAGING = 'https://easypaystg.easypaisa.com.pk/easypay-service/rest/v4/';

    public function __construct(private readonly array $settings) {}

    public function initiateMa(string $orderId, string $amount, string $mobileAccount, string $email): array
    {
        if (! preg_match('/\A03[0-9]{9}\z/', $mobileAccount) || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! preg_match('/\A[0-9]+\.[0-9]{2}\z/', $amount) || bccomp($amount, '0.00', 2) <= 0) {
            throw new LogicException('Invalid Easypaisa MA customer or monetary input.');
        }

        return $this->request('initiate-ma-transaction', [
            'orderId' => $this->orderId($orderId), 'storeId' => $this->storeId(),
            'transactionAmount' => $amount, 'transactionType' => 'MA',
            'mobileAccountNo' => $mobileAccount, 'emailAddress' => $email,
        ]);
    }

    public function inquire(string $orderId): array
    {
        $account = $this->settings['account_num'] ?? null;
        if (! is_string($account) || ! preg_match('/\A[0-9]+\z/', $account)) {
            throw new LogicException('Easypaisa inquiry account is not configured.');
        }

        $result = $this->request('inquire-transaction', [
            'orderId' => $this->orderId($orderId), 'storeId' => $this->storeId(), 'accountNum' => $account,
        ]);
        if (($result['accountNum'] ?? null) === null || (string) $result['accountNum'] !== $account
            || ! in_array($result['transactionStatus'] ?? null, ['PAID', 'FAILED', 'PENDING', 'BLOCKED', 'EXPIRED', 'REVERSED'], true)
            || ! in_array($result['paymentMode'] ?? null, ['MA', 'OTC', 'CC'], true)
            || ! isset($result['transactionAmount']) || ! is_numeric($result['transactionAmount'])
            || ! preg_match('/\A[0-9]+(?:\.[0-9]{1,2})?\z/', (string) $result['transactionAmount'])) {
            throw new LogicException('Easypaisa inquiry identity or status is incomplete; payment remains unverified.');
        }

        return $result;
    }

    /** Compare inquiry with the immutable MA intent; never settle a payment here. */
    public function inquireForPayment(string $orderId, string $expectedAmount): array
    {
        if (! preg_match('/\A[0-9]+\.[0-9]{2}\z/', $expectedAmount)
            || bccomp($expectedAmount, '0.00', 2) <= 0) {
            throw new LogicException('Invalid local Easypaisa payment amount.');
        }
        $result = $this->inquire($orderId);
        if ($result['paymentMode'] !== 'MA'
            || bccomp((string) $result['transactionAmount'], $expectedAmount, 2) !== 0) {
            throw new LogicException('Easypaisa inquiry does not match the original MA payment; payment remains unverified.');
        }

        return ['order_id' => $orderId, 'amount' => $expectedAmount,
            'provider_status' => $result['transactionStatus'], 'verified_for_settlement' => false];
    }

    private function request(string $endpoint, array $payload): array
    {
        $username = $this->settings['username'] ?? null;
        $password = $this->settings['password'] ?? null;
        if (($this->settings['enabled'] ?? null) !== true || ($this->settings['mode'] ?? null) !== 'sandbox'
            || ! is_string($username) || trim($username) === '' || ! is_string($password) || $password === '') {
            throw new LogicException('Easypaisa sandbox transport is unavailable.');
        }
        $response = Http::withHeaders(['Credentials' => base64_encode($username.':'.$password)])
            ->acceptJson()->asJson()->timeout(15)->post(self::STAGING.$endpoint, $payload);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new LogicException('Easypaisa transport failed; payment outcome is unverified.');
        }
        $body = $response->json();
        if (($body['orderId'] ?? null) !== $payload['orderId']
            || (string) ($body['storeId'] ?? '') !== (string) $payload['storeId']
            || ($body['responseCode'] ?? null) !== '0000') {
            throw new LogicException('Easypaisa response failed identity or result validation.');
        }

        // A successful MA initiation must carry the provider's own transaction ID.
        // Do not mistake a generic 0000 response for a durable payment attempt.
        if ($endpoint === 'initiate-ma-transaction'
            && (! is_string($body['transactionId'] ?? null)
                || trim($body['transactionId']) === '' || strlen($body['transactionId']) > 255)) {
            throw new LogicException('Easypaisa MA initiation did not return a valid transaction ID.');
        }

        return $body; // Caller must separately establish final status; never mark paid from initiation.
    }

    private function storeId(): string
    {
        $value = $this->settings['store_id'] ?? null;
        if (! is_string($value) || ! preg_match('/\A[1-9][0-9]*\z/', $value)) {
            throw new LogicException('Easypaisa store is not configured.');
        }

        return $value;
    }

    private function orderId(string $value): string
    {
        if ($value === '' || strlen($value) > 100 || ! preg_match('/\A[A-Za-z0-9_-]+\z/', $value)) {
            throw new LogicException('Invalid Easypaisa order ID.');
        }

        return $value;
    }
}
