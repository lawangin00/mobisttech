<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Models\CustomerAccount;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

/** W04: synthetic signed callbacks must bind to the original gateway and immutable receipt. */
final class W04CrossProviderReceiptBoundaryTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private CustomerAccount $buyer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->buyer = new CustomerAccount;
        $this->buyer->forceFill([
            'name' => 'Synthetic payment buyer', 'email' => 'w04-buyer@example.invalid',
            'mobile' => '03001112222', 'password' => 'SyntheticPass123!', 'is_admin' => false,
        ])->save();
        DB::table('customers')->insert([
            'website_user_id' => $this->buyer->id, 'display_name' => $this->buyer->name,
            'email' => $this->buyer->email, 'mobile' => $this->buyer->mobile,
            'public_id' => (string) Str::uuid(),
        ]);
        $revision = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid'], JSON_THROW_ON_ERROR), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $revision, 'published_at' => now(),
        ]);
    }

    public function test_signed_other_provider_receipt_cannot_collect_order_and_changed_signed_replay_cannot_mutate_sale(): void
    {
        $registry = new PaymentProviders;
        foreach (['jazzcash', 'easypaisa'] as $gateway) {
            config()->set('commerce.providers.'.$gateway, [
                'enabled' => true, 'merchant' => 'synthetic-'.$gateway, 'mode' => 'test',
            ]);
            $registry->register($gateway, new class($gateway) implements PaymentProvider
            {
                public function __construct(private string $gateway) {}

                public function initiate(array $intent): array
                {
                    return ['reference' => 'GW-'.$intent['payment_id'],
                        'redirect_url' => 'https://gateway.example.invalid/pay/'.$intent['payment_id']];
                }

                public function verify(array $payload): array
                {
                    $signature = $payload['signature'] ?? '';
                    $unsigned = array_diff_key($payload, ['signature' => true]);
                    $expected = hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), 'w04-'.$this->gateway);
                    if (! is_string($signature) || ! hash_equals($expected, $signature)) {
                        throw new LogicException('Invalid synthetic provider signature.');
                    }

                    return $unsigned;
                }
            });
        }
        $this->app->instance(PaymentProviders::class, $registry);

        $product = $this->product();
        $this->acquire($product);
        $service = app(OrderTransactions::class);
        $order = $service->checkout($this->buyer::class.':'.$this->buyer->id, $this->buyer,
            'w04-cross-provider-'.str_repeat('x', 24), [
                'customer_name' => $this->buyer->name, 'customer_mobile' => $this->buyer->mobile,
                'customer_email' => $this->buyer->email, 'city' => 'Karachi',
                'delivery_address' => 'Synthetic address', 'gateway' => 'jazzcash',
                'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
            ]);
        $intent = $service->initiate($order['payment_id']);
        $this->assertSame('pending', $order['payment_status']);

        // A valid Easypaisa test signature must not settle a JazzCash payment reference.
        $foreign = $this->signed('easypaisa', 'W04-CROSS-1', $intent['reference'], 'paid');
        $this->reject(fn () => $service->callback('easypaisa', $foreign));
        $this->postJson('/api/v1/payment-callbacks/easypaisa', $foreign)
            ->assertNotFound()->assertJsonPath('error.code', 'api_404');
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $foreign)
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->assertSame(0, DB::table('sales')->count());
        $this->assertSame('pending', DB::table('payments')->where('public_id', $order['payment_id'])->value('status'));
        $this->assertSame('unpaid', DB::table('orders')->where('public_id', $order['order_id'])->value('payment_status'));

        $this->postJson('/api/v1/payment-callbacks/jazzcash',
            $this->signed('jazzcash', 'W04-AMOUNT-1', $intent['reference'], 'paid', '200.03'))
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $valid = $this->signed('jazzcash', 'W04-PAID-1', $intent['reference'], 'paid');
        config()->set('commerce.providers.jazzcash.enabled', false);
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $valid)
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame(0, DB::table('payment_receipts')->count());
        $this->assertSame('pending', DB::table('payments')->where('public_id', $order['payment_id'])->value('status'));
        config()->set('commerce.providers.jazzcash.enabled', true);
        config()->set('commerce.providers.jazzcash.merchant', 'synthetic-rotated-merchant');
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $valid)
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame(0, DB::table('payment_receipts')->count());
        config()->set('commerce.providers.jazzcash.merchant', 'synthetic-jazzcash');
        config()->set('commerce.providers.jazzcash.mode', 'sandbox');
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $valid)
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->assertSame(0, DB::table('payment_receipts')->count());
        config()->set('commerce.providers.jazzcash.mode', 'test');
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $valid)
            ->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->postJson('/api/v1/payment-callbacks/jazzcash', $valid)
            ->assertOk()->assertJsonPath('data.payment_status', 'paid');
        $this->postJson('/api/v1/payment-callbacks/jazzcash',
            $this->signed('jazzcash', 'W04-PAID-1', $intent['reference'], 'failed'))
            ->assertStatus(409)->assertJsonPath('error.code', 'api_409');
        $this->reject(fn () => $service->callback('jazzcash',
            $this->signed('jazzcash', 'W04-PAID-1', $intent['reference'], 'failed')));
        $this->assertSame(1, DB::table('payment_receipts')->count());
        $this->assertSame(1, DB::table('sales')->count());
        $this->assertSame('paid', DB::table('orders')->where('public_id', $order['order_id'])->value('payment_status'));
    }

    private function signed(string $gateway, string $eventId, string $reference, string $status, string $amount = '200.02'): array
    {
        $event = [
            'event_id' => $eventId, 'transaction_reference' => 'TX-'.$eventId,
            'order_reference' => $reference, 'amount' => $amount, 'currency' => 'PKR',
            'status' => $status, 'payload_hash' => hash('sha256', $gateway.'|'.$eventId.'|'.$reference.'|'.$status),
        ];
        $event['signature'] = hash_hmac('sha256', json_encode($event, JSON_THROW_ON_ERROR), 'w04-'.$gateway);

        return $event;
    }
}
