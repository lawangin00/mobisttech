<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** W04: the Website accepts hosted-card redirects, never locally submitted card details. */
final class W04RawCardSubmissionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_crafted_card_fields_are_rejected_before_creating_an_order_or_payment(): void
    {
        $baseline = [
            DB::table('orders')->count(),
            DB::table('payments')->count(),
            DB::table('idempotency_requests')->count(),
        ];
        $input = [
            'customer_name' => 'Synthetic buyer',
            'customer_mobile' => '03001112222',
            'gateway' => 'card',
            'lines' => [['product_id' => '00000000-0000-4000-8000-000000000001', 'quantity' => 1]],
        ];
        $service = app(OrderTransactions::class);
        $scope = 'guest:'.str_repeat('a', 64);

        foreach (['card_number', 'card_cvv', 'card_expiry'] as $index => $field) {
            try {
                $service->checkout($scope, null, 'w04-raw-card-'.str_repeat((string) $index, 24),
                    [...$input, $field => 'synthetic-card-data']);
                $this->fail('Crafted card field was accepted: '.$field);
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('input', $exception->errors());
            }
        }

        $this->assertSame($baseline, [
            DB::table('orders')->count(),
            DB::table('payments')->count(),
            DB::table('idempotency_requests')->count(),
        ]);
    }
}
