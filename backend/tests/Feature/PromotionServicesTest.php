<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use App\Commerce\PaymentProvider;
use App\Commerce\PaymentProviders;
use App\Models\CustomerAccount;
use App\Promotions\PromotionServices;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PromotionServicesTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private CustomerAccount $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->customer = new CustomerAccount;
        $this->customer->forceFill([
            'name' => 'Promotion buyer', 'email' => 'promo@example.invalid', 'mobile' => '03002223333',
            'password' => 'SyntheticPass123!', 'is_admin' => false,
        ])->save();
        DB::table('customers')->insert([
            'website_user_id' => $this->customer->id, 'display_name' => $this->customer->name,
            'email' => $this->customer->email, 'mobile' => $this->customer->mobile,
            'public_id' => (string) Str::uuid(),
        ]);
        $revision = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.mode', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['mode' => 'hybrid']), 'published_at' => now(),
        ]);
        DB::table('website_operating_profiles')->updateOrInsert(['id' => 1], [
            'mode' => 'hybrid', 'version' => 1, 'revision_id' => $revision, 'published_at' => now(),
        ]);
    }

    public function test_pos_and_website_use_same_coupon_math_and_return_keeps_snapshot(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $this->coupon($product->public_id, 'SAVE10', 'percentage', '10.00', 10);

        $sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, 'promo-pos-'.Str::uuid(), [
            'discount' => '0.00', 'promotion_codes' => ['save10'],
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame('20.00', $sale['discount']);
        $this->assertSame('180.02', $sale['final_bill']);
        $this->assertCount(1, $sale['promotion_claim_ids']);
        $this->assertSame('promotion', DB::table('monetary_adjustments')->where('invoice_id', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('id'))->value('kind'));

        $returned = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, 'promo-return-'.Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Promotion snapshot return',
            'lines' => [[
                'sale_id' => $sale['sale_ids'][0], 'quantity' => 1,
                'condition' => 'opened', 'disposition' => 'sellable',
            ]],
        ]);
        $this->assertSame('180.02', $returned['refund_due']);

        $web = app(OrderTransactions::class)->checkout($this->scope(), $this->customer, 'promo-web-'.Str::uuid(), [
            'customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'city' => 'Karachi',
            'delivery_address' => 'Promotion test address', 'gateway' => 'cod',
            'coupon_codes' => ['SAVE10'], 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame('180.02', $web['amount']);
        $this->assertCount(1, $web['promotion_claim_ids']);
    }

    public function test_usage_limit_releases_on_cancel_and_retry_keeps_one_claim(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $this->coupon($product->public_id, 'FIX50', 'fixed', '50.00', 1);

        $first = app(OrderTransactions::class)->checkout($this->scope(), $this->customer, 'coupon-first-'.Str::uuid(), $this->checkout($product->public_id, 'cod'));
        $this->assertSame('150.02', $first['amount']);
        $this->assertSame(1, DB::table('promotion_claims')->where('status', 'active')->count());

        $this->reject(fn () => app(OrderTransactions::class)->checkout(
            $this->scope(), $this->customer, 'coupon-blocked-'.Str::uuid(), $this->checkout($product->public_id, 'cod')
        ));
        app(OrderTransactions::class)->cancel($this->scope(), $this->customer, $first['order_id'], 'coupon-cancel-'.Str::uuid());
        $this->assertSame(0, DB::table('promotion_claims')->where('status', 'active')->count());

        $this->fakeProvider();
        $retryable = app(OrderTransactions::class)->checkout($this->scope(), $this->customer, 'coupon-retry-'.Str::uuid(), $this->checkout($product->public_id, 'jazzcash'));
        $this->assertSame('150.02', $retryable['amount']);
        $init = app(OrderTransactions::class)->initiate($retryable['payment_id']);
        $failed = app(OrderTransactions::class)->callback('jazzcash', $this->gatewayEvent('PROMO-F', $init['reference'], '150.02', 'failed'));
        $this->assertSame('failed', $failed['payment_status']);
        $claimId = DB::table('promotion_claims')->where('status', 'active')->value('public_id');
        $retry = app(OrderTransactions::class)->retry($this->scope(), $this->customer, $retryable['order_id'], 'coupon-retry-payment-'.Str::uuid(), 'jazzcash');
        $this->assertSame('150.02', $retry['amount']);
        $this->assertSame($claimId, DB::table('promotion_claims')->where('status', 'active')->value('public_id'));
        $this->assertSame(1, DB::table('promotion_claims')->where('status', 'active')->count());
    }

    public function test_archived_outlet_blocks_promotion_configuration(): void
    {
        $product = $this->product();
        $this->outlet->forceFill(['archived_at' => now(), 'version' => $this->outlet->version + 1])->save();

        $this->reject(fn () => $this->coupon($product->public_id, 'ARCHIVED10', 'percentage', '10.00', 1));
        $this->assertSame(0, DB::table('promotions')->count());
        $this->assertSame(0, DB::table('promotion_events')->count());
    }

    private function coupon(string $productId, string $code, string $type, string $value, int $usageLimit): void
    {
        app(PromotionServices::class)->configure($this->actor, null, [
            'name' => $code.' synthetic promotion', 'outlet_id' => $this->outlet->public_id,
            'mode' => 'coupon', 'code' => $code, 'discount_type' => $type,
            'discount_value' => $value, 'min_subtotal' => '0.00', 'usage_limit' => $usageLimit,
            'customer_required' => false, 'stackable' => false, 'priority' => 100,
            'status' => 'active', 'product_ids' => [$productId], 'categories' => [],
        ]);
    }

    private function checkout(string $productId, string $gateway): array
    {
        return [
            'customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'city' => 'Karachi',
            'delivery_address' => 'Promotion test address', 'gateway' => $gateway,
            'coupon_codes' => ['FIX50'], 'lines' => [['product_id' => $productId, 'quantity' => 1]],
        ];
    }

    private function fakeProvider(): void
    {
        config()->set('commerce.providers.jazzcash', [
            'enabled' => true, 'merchant' => 'synthetic-merchant', 'mode' => 'test',
        ]);
        $registry = new PaymentProviders;
        $registry->register('jazzcash', new PromotionFakeProvider);
        $this->app->instance(PaymentProviders::class, $registry);
    }

    private function gatewayEvent(string $event, string $reference, string $amount, string $status): array
    {
        $payload = [
            'event_id' => $event, 'transaction_reference' => 'TX-'.$event,
            'order_reference' => $reference, 'amount' => $amount, 'currency' => 'PKR',
            'status' => $status, 'payload_hash' => hash('sha256', $event.'|'.$reference.'|'.$amount.'|'.$status),
        ];
        $payload['signature'] = hash_hmac('sha256', json_encode($payload, JSON_THROW_ON_ERROR), 'promotion-secret');

        return $payload;
    }

    private function scope(): string
    {
        return $this->customer::class.':'.$this->customer->id;
    }
}

final class PromotionFakeProvider implements PaymentProvider
{
    public function initiate(array $intent): array
    {
        return ['reference' => 'GW-'.$intent['payment_id'], 'redirect_url' => 'https://gateway.example.invalid/'.$intent['payment_id']];
    }

    public function verify(array $payload): array
    {
        $signature = $payload['signature'] ?? '';
        $unsigned = array_diff_key($payload, ['signature' => true]);
        if (! hash_equals(hash_hmac('sha256', json_encode($unsigned, JSON_THROW_ON_ERROR), 'promotion-secret'), $signature)) {
            throw new LogicException('Invalid promotion test signature.');
        }

        return $unsigned;
    }
}
