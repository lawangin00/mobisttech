<?php

namespace Database\Seeders;

use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerWebsiteE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('MT-5.2 customer E2E cleanup is restricted to mobisttech_test.');
        }

        DB::transaction(function () {
            $userId = DB::table('users')->where('email', CustomerWebsiteE2eSeeder::EMAIL)->value('id');
            if ($userId) {
                $customerId = DB::table('customers')->where('website_user_id', $userId)->value('id');
                $orderIds = DB::table('orders')->where('user_id', $userId)->pluck('id')->all();
                $itemIds = $orderIds ? DB::table('order_items')->whereIn('order_id', $orderIds)->pluck('id')->all() : [];
                $reservationIds = $orderIds ? DB::table('reservations')->whereIn('order_id', $orderIds)->pluck('id')->all() : [];
                $reservationLineIds = $reservationIds ? DB::table('reservation_lines')->whereIn('reservation_id', $reservationIds)->pluck('id')->all() : [];
                $paymentIds = $orderIds ? DB::table('payments')->whereIn('order_id', $orderIds)->pluck('id')->all() : [];
                if ($itemIds) {
                    DB::table('product_reviews')->whereIn('order_item_id', $itemIds)->delete();
                }
                if ($reservationLineIds) {
                    DB::table('reservation_allocations')->whereIn('reservation_line_id', $reservationLineIds)->delete();
                }
                if ($reservationIds) {
                    DB::table('reservation_lines')->whereIn('reservation_id', $reservationIds)->delete();
                }
                if ($paymentIds) {
                    DB::table('payment_receipts')->whereIn('payment_id', $paymentIds)->delete();
                }
                if ($orderIds) {
                    DB::table('reservations')->whereIn('order_id', $orderIds)->delete();
                    DB::table('payments')->whereIn('order_id', $orderIds)->delete();
                    DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
                    DB::table('orders')->whereIn('id', $orderIds)->delete();
                }
                DB::table('idempotency_requests')->where('actor_scope', CustomerAccount::class.':'.$userId)->delete();
                DB::table('wishlist_items')->where('customer_account_id', $userId)->delete();
                DB::table('product_notification_subscriptions')->where('customer_account_id', $userId)->delete();
                DB::table('notification_preferences')->where('customer_account_id', $userId)->delete();
                DB::table('notification_rate_buckets')->where('customer_account_id', $userId)->delete();
                DB::table('account_sessions')->where('guard', 'customer')->where('account_id', $userId)->delete();
                DB::table('identity_audit_events')->where('realm', 'customer')->where('account_id', $userId)->delete();
                if ($customerId) {
                    DB::table('loyalty_claim_lots')->whereIn('loyalty_claim_id', DB::table('loyalty_claims')->where('customer_id', $customerId)->pluck('id'))->delete();
                    DB::table('loyalty_entries')->where('customer_id', $customerId)->delete();
                    DB::table('loyalty_claims')->where('customer_id', $customerId)->delete();
                    DB::table('loyalty_earn_lots')->whereIn('account_id', DB::table('loyalty_accounts')->where('customer_id', $customerId)->pluck('id'))->delete();
                    DB::table('loyalty_accounts')->where('customer_id', $customerId)->delete();
                    DB::table('customers')->where('id', $customerId)->delete();
                }
                DB::table('users')->where('id', $userId)->delete();
            }
            DB::table('loyalty_configurations')->where('public_id', '00000000-0000-4000-8000-000000005201')->delete();
        });
    }
}
