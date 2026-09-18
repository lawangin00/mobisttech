<?php

namespace Database\Seeders;

use App\Identity\CustomerIdentity;
use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerWebsiteE2eSeeder extends Seeder
{
    public const EMAIL = 'mt52-customer@example.invalid';

    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('MT-5.2 customer E2E seeding is restricted to mobisttech_test.');
        }

        app(CustomerWebsiteE2eCleanupSeeder::class)->run();

        DB::transaction(function () {
            $listing = DB::table('product_listings')->where('external_source', 'website-e2e')
                ->where('slug', 'mt51-alpha-phone')->firstOrFail();
            $product = DB::table('products')->where('id', $listing->product_id)->firstOrFail();

            $account = new CustomerAccount;
            $account->forceFill([
                'public_id' => (string) Str::uuid(),
                'name' => 'MT52 Customer',
                'email' => self::EMAIL,
                'mobile' => '03005200001',
                'password' => Hash::make('SyntheticPass123!'),
                'email_verified_at' => now(),
                'is_admin' => false,
                'auth_version' => 1,
            ])->save();
            $customer = app(CustomerIdentity::class)->forAccount($account);

            $adminId = DB::table('admins')->where('email', 'e2e-platform@example.invalid')->value('id');
            if (! $adminId) {
                throw new \RuntimeException('MT-5.2 customer E2E requires the platform E2E admin fixture.');
            }
            DB::table('loyalty_configurations')->insert([
                'public_id' => '00000000-0000-4000-8000-000000005201',
                'version' => 5201,
                'enabled' => true,
                'earn_basis_amount' => '100.00',
                'earn_points' => 10,
                'redemption_value' => '1.00',
                'min_redeem_points' => 5,
                'max_redeem_points' => 100,
                'daily_redeem_points' => 100,
                'expiry_days' => 30,
                'snapshot' => '{}',
                'snapshot_sha256' => hash('sha256', '{}'),
                'created_by_admin_id' => $adminId,
                'created_at' => now(),
            ]);
            DB::table('loyalty_accounts')->insert([
                'customer_id' => $customer->id, 'balance_points' => 42, 'version' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $orderId = DB::table('orders')->insertGetId([
                'public_id' => '00000000-0000-4000-8000-000000005202',
                'order_number' => 'MT52-E2E-ORDER',
                'order_type' => 'commerce',
                'status' => 'confirmed',
                'fulfillment_status' => 'completed',
                'payment_status' => 'paid',
                'customer_name' => $account->name,
                'customer_mobile' => $account->mobile,
                'customer_email' => $account->email,
                'city' => 'Karachi',
                'delivery_address' => 'Synthetic MT-5.2 address',
                'subtotal' => '50000.00',
                'total' => '50000.00',
                'currency' => 'PKR',
                'user_id' => $account->id,
                'customer_id' => $customer->id,
                'owner_scope_hash' => hash('sha256', $account::class.':'.$account->id),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('order_items')->insert([
                'order_id' => $orderId, 'item_type' => 'product', 'title' => $product->name,
                'quantity' => 1, 'unit_price' => '50000.00', 'line_total' => '50000.00',
                'product_id' => $product->id, 'outlet_id' => $product->outlet_id,
                'outlet_external_id' => (string) $product->outlet_id,
                'pos_variant_key' => 'standard', 'external_reference' => $product->public_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}
