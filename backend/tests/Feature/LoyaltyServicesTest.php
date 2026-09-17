<?php

namespace Tests\Feature;

use App\Commerce\OrderTransactions;
use App\Loyalty\LoyaltyServices;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class LoyaltyServicesTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private CustomerAccount $customer;

    private int $customerId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->customer = new CustomerAccount;
        $this->customer->forceFill([
            'name' => 'Loyalty buyer', 'email' => 'loyalty@example.invalid', 'mobile' => '03003334444',
            'password' => 'SyntheticPass123!', 'is_admin' => false,
        ])->save();
        $this->customerId = DB::table('customers')->insertGetId([
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

    public function test_pos_earn_redeem_and_full_return_restore_consistent_balance(): void
    {
        $this->configure();
        $product = $this->product();
        $this->acquire($product, 3);
        $customerPublic = DB::table('customers')->where('id', $this->customerId)->value('public_id');
        $first = app(SalesOperations::class)->sell($this->actor, $this->outlet, 'loyalty-earn-'.Str::uuid(), [
            'customer_id' => $customerPublic, 'discount' => '0.00',
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame(20, $first['loyalty_earned_points']);
        $this->assertSame(20, $this->balance());

        $second = app(SalesOperations::class)->sell($this->actor, $this->outlet, 'loyalty-redeem-'.Str::uuid(), [
            'customer_id' => $customerPublic, 'discount' => '0.00', 'loyalty_points' => 10,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame('10.00', $second['discount']);
        $this->assertSame('190.02', $second['final_bill']);
        $this->assertSame(19, $second['loyalty_earned_points']);
        $this->assertSame(29, $this->balance());
        $this->assertCount(1, $second['loyalty_claim_ids']);
        $invoice = DB::table('invoices')->where('public_id', $second['invoice_id'])->firstOrFail();
        $adjustment = DB::table('monetary_adjustments')->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame('loyalty_redemption', $adjustment->kind);
        $this->assertSame('10.00', $adjustment->amount);
        $returned = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, 'loyalty-return-'.Str::uuid(), [
            'invoice_id' => $second['invoice_id'], 'reason' => 'Full loyalty sale return',
            'lines' => [[
                'sale_id' => $second['sale_ids'][0], 'quantity' => 1,
                'condition' => 'opened', 'disposition' => 'sellable',
            ]],
        ]);
        $this->assertSame('190.02', $returned['refund_due']);
        $this->assertSame(19, $returned['loyalty']['earned_reversed']);
        $this->assertSame(10, $returned['loyalty']['redeemed_restored']);
        $this->assertSame(20, $this->balance());
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'earn_return_reversal')->count());
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'redeem_return_restore')->count());
        $this->assertSame(1, DB::table('loyalty_claims')->where('status', 'active')->count());
    }

    public function test_disabled_loyalty_preserves_active_obligation_and_cancel_restores_points(): void
    {
        $this->configure();
        $product = $this->product();
        $this->acquire($product, 3);
        $this->seedBalance($product->public_id);
        $key = 'loyalty-web-'.Str::uuid();
        $order = app(OrderTransactions::class)->checkout($this->scope(), $this->customer, $key, [
            'customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'city' => 'Karachi', 'delivery_address' => 'Loyalty test address',
            'gateway' => 'cod', 'loyalty_points' => 10,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame('190.02', $order['amount']);
        $this->assertSame(10, $this->balance());
        $claimId = $order['loyalty_claim_ids'][0];

        $this->configure(enabled: false);
        $replay = DB::transaction(fn () => app(LoyaltyServices::class)->claim(
            'website', $this->customerId, hash('sha256', $this->scope().'|'.$key), 10, '200.02', ['200.02']
        ));
        $this->assertSame($claimId, $replay['applications'][0]['claim_id']);
        $this->assertSame(10, $this->balance());

        app(OrderTransactions::class)->cancel($this->scope(), $this->customer, $order['order_id'], 'loyalty-cancel-'.Str::uuid());
        $this->assertSame(20, $this->balance());
        $this->assertSame('released', DB::table('loyalty_claims')->where('public_id', $claimId)->value('status'));
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'redemption_release')->count());
        $this->assertSame(1, DB::table('monetary_adjustments')->where('kind', 'loyalty_redemption')->count());
        $this->reject(fn () => DB::transaction(fn () => app(LoyaltyServices::class)->claim(
            'website', $this->customerId, hash('sha256', 'new-disabled-claim'), 1, '200.02', ['200.02']
        )));
        $this->assertSame(3, DB::table('loyalty_entries')->count());
    }

    public function test_daily_limit_expiry_stacking_and_configuration_permission_are_enforced(): void
    {
        $this->configure(daily: 10, expiry: 1);
        $product = $this->product();
        $this->acquire($product, 4);
        $this->seedBalance($product->public_id);
        $first = app(OrderTransactions::class)->checkout($this->scope(), $this->customer, 'loyalty-daily-'.Str::uuid(), [
            'customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'delivery_address' => 'Daily limit address',
            'gateway' => 'cod', 'loyalty_points' => 10,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $this->assertSame(10, $this->balance());

        $this->reject(fn () => app(OrderTransactions::class)->checkout($this->scope(), $this->customer, 'loyalty-over-daily-'.Str::uuid(), [
            'customer_name' => $this->customer->name, 'customer_mobile' => $this->customer->mobile,
            'customer_email' => $this->customer->email, 'delivery_address' => 'Daily limit address',
            'gateway' => 'cod', 'loyalty_points' => 1,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]));
        $customerPublic = DB::table('customers')->where('id', $this->customerId)->value('public_id');
        $this->reject(fn () => app(SalesOperations::class)->sell($this->actor, $this->outlet, 'loyalty-stack-'.Str::uuid(), [
            'customer_id' => $customerPublic, 'discount' => '1.00', 'discount_reason' => 'Synthetic manual discount',
            'loyalty_points' => 1, 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]));

        $limited = new Admin;
        $limited->forceFill(['name' => 'No loyalty config', 'email' => 'no-loyalty@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['shops.enter']])->save();
        $this->reject(fn () => app(LoyaltyServices::class)->configure($limited, [
            'enabled' => true, 'earn_basis_amount' => '100.00', 'earn_points' => 10, 'redemption_value' => '1.00',
            'min_redeem_points' => 1, 'max_redeem_points' => 100, 'daily_redeem_points' => 10, 'expiry_days' => 1,
        ]));

        DB::table('loyalty_earn_lots')->update(['expires_at' => now()->subSecond()]);
        $this->assertSame(10, app(LoyaltyServices::class)->expireDue());
        $this->assertSame(0, $this->balance());
        $this->assertSame(1, DB::table('loyalty_entries')->where('entry_type', 'expire')->count());

        app(OrderTransactions::class)->cancel($this->scope(), $this->customer, $first['order_id'], 'loyalty-expired-cancel-'.Str::uuid());
        $this->assertSame(0, $this->balance());
        $this->assertSame(2, DB::table('loyalty_entries')->where('entry_type', 'expire')->count());
    }

    private function configure(bool $enabled = true, int $daily = 100, int $expiry = 30): void
    {
        app(LoyaltyServices::class)->configure($this->actor, [
            'enabled' => $enabled, 'earn_basis_amount' => '100.00', 'earn_points' => 10,
            'redemption_value' => '1.00', 'min_redeem_points' => 1, 'max_redeem_points' => 100,
            'daily_redeem_points' => $daily, 'expiry_days' => $expiry,
        ]);
    }

    private function seedBalance(string $productId): array
    {
        $customerPublic = DB::table('customers')->where('id', $this->customerId)->value('public_id');

        return app(SalesOperations::class)->sell($this->actor, $this->outlet, 'loyalty-seed-'.Str::uuid(), [
            'customer_id' => $customerPublic, 'discount' => '0.00',
            'lines' => [['product_id' => $productId, 'quantity' => 1]],
        ]);
    }

    private function balance(): int
    {
        return (int) DB::table('loyalty_accounts')->where('customer_id', $this->customerId)->value('balance_points');
    }

    private function scope(): string
    {
        return $this->customer::class.':'.$this->customer->id;
    }
}
