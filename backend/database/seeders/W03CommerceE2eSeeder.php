<?php

namespace Database\Seeders;

use App\Identity\CustomerIdentity;
use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class W03CommerceE2eSeeder extends Seeder
{
    public const EMAIL = 'mt75-w03-customer@example.invalid';

    public const ORDER = 'MT75-W03-ORDER';

    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        app(W03CommerceE2eCleanupSeeder::class)->run();

        DB::transaction(function () {
            $outlet = DB::table('outlets')->where('outlet_code', 'E41')->firstOrFail();
            $account = new CustomerAccount;
            $account->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'MT75 W03 Customer',
                'email' => self::EMAIL, 'mobile' => '03007503001', 'password' => Hash::make('SyntheticPass123!'),
                'email_verified_at' => now(), 'is_admin' => false, 'auth_version' => 1])->save();
            $customer = app(CustomerIdentity::class)->forAccount($account);
            $productId = DB::table('products')->insertGetId(['name' => 'MT75 W03 Product', 'price' => '150.00',
                'sale_price' => '150.00', 'purchase_price' => '100.00', 'outlet_id' => $outlet->id, 'qty' => 1,
                'sku' => 'MT75-W03-E2E', 'product_code' => 'MST-E41-W03', 'category' => 'accessory',
                'public_id' => (string) Str::uuid(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $listingId = DB::table('product_listings')->insertGetId(['external_source' => 'w03-e2e',
                'external_id' => 'mt75-w03-e2e', 'slug' => 'mt75-w03-product', 'name' => 'MT75 W03 Product',
                'category' => 'accessory', 'is_online' => true, 'product_id' => $productId,
                'public_id' => (string) Str::uuid(), 'version' => 1, 'created_at' => now(), 'updated_at' => now()]);
            $orderId = DB::table('orders')->insertGetId(['order_number' => self::ORDER, 'order_type' => 'commerce',
                'status' => 'confirmed', 'fulfillment_status' => 'pending', 'payment_status' => 'paid',
                'customer_name' => $account->name, 'customer_mobile' => $account->mobile, 'customer_email' => $account->email,
                'subtotal' => '150.00', 'total' => '150.00', 'currency' => 'PKR', 'user_id' => $account->id,
                'customer_id' => $customer->id, 'public_id' => (string) Str::uuid(), 'version' => 1,
                'created_at' => now(), 'updated_at' => now()]);
            $itemId = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_type' => 'product',
                'product_listing_id' => $listingId, 'product_id' => $productId, 'outlet_id' => $outlet->id,
                'title' => 'MT75 W03 Product', 'quantity' => 1, 'unit_price' => '150.00', 'line_total' => '150.00',
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('product_reviews')->insert(['product_listing_id' => $listingId, 'user_id' => $account->id,
                'order_item_id' => $itemId, 'rating' => 5, 'title' => 'W03 verified purchase',
                'body' => 'Synthetic purchased-product review awaiting moderation.', 'status' => 'pending',
                'created_at' => now(), 'updated_at' => now()]);
        });
    }
}
