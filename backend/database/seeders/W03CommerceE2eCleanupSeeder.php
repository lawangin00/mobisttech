<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class W03CommerceE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $order = DB::table('orders')->where('order_number', W03CommerceE2eSeeder::ORDER)->first();
            $user = DB::table('users')->where('email', W03CommerceE2eSeeder::EMAIL)->first();
            if ($order) {
                $itemIds = DB::table('order_items')->where('order_id', $order->id)->pluck('id')->all();
                $reviewIds = $itemIds ? DB::table('product_reviews')->whereIn('order_item_id', $itemIds)->pluck('id')->all() : [];
                foreach ($reviewIds as $reviewId) {
                    DB::table('identity_audit_events')->where('reference', 'review:'.$reviewId)->delete();
                }
                DB::table('identity_audit_events')->where('reference', 'order:'.W03CommerceE2eSeeder::ORDER)->delete();
                if ($reviewIds) {
                    DB::table('product_reviews')->whereIn('id', $reviewIds)->delete();
                }
                if ($itemIds) {
                    DB::table('order_items')->whereIn('id', $itemIds)->delete();
                }
                DB::table('orders')->where('id', $order->id)->delete();
            }
            $listing = DB::table('product_listings')->where('external_source', 'w03-e2e')->where('external_id', 'mt75-w03-e2e')->first();
            if ($listing) {
                DB::table('product_listings')->where('id', $listing->id)->delete();
                DB::table('products')->where('id', $listing->product_id)->delete();
            }
            if ($user) {
                $customerId = DB::table('customers')->where('website_user_id', $user->id)->value('id');
                DB::table('account_sessions')->where('guard', 'customer')->where('account_id', $user->id)->delete();
                DB::table('identity_audit_events')->where('realm', 'customer')->where('account_id', $user->id)->delete();
                if ($customerId) {
                    DB::table('customers')->where('id', $customerId)->delete();
                }
                DB::table('users')->where('id', $user->id)->delete();
            }
        });
    }
}
