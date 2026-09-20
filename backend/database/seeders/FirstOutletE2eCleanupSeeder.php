<?php

namespace Database\Seeders;

use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class FirstOutletE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing')
            && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_FIRST_OUTLET_E2E_ENABLED') === '1', 403);

        DB::transaction(function () {
            $this->cleanupFreshWebsiteCustomer();
            $owner = DB::table('admins')->where('email', 'mt75-fresh-owner@example.invalid')
                ->where('name', 'MT75 Fresh Protected Owner')->first();
            if (! $owner) {
                abort_unless(! DB::table('outlets')->where('name', 'MT75 Fresh First Outlet')->exists(), 409);
                $this->purgeOrphanedFixtureResidue();

                return;
            }

            $outlet = DB::table('outlets')->where('name', 'MT75 Fresh First Outlet')
                ->where('outlet_code', '001')->first();
            if ($outlet) {
                $invoices = DB::table('invoices')->where('outlet_id', $outlet->id)
                    ->where('customer_name', 'MT75 Fresh Customer')->get();
                abort_unless(DB::table('invoices')->where('outlet_id', $outlet->id)->count() === $invoices->count(), 409);
                $invoiceIds = $invoices->pluck('id')->all();
                $customerIds = $invoices->pluck('customer_id')->filter()->all();
                $returnIds = DB::table('returns')->whereIn('invoice_id', $invoiceIds)->pluck('id')->all();
                $tenderIds = DB::table('pos_tender_allocations')->whereIn('invoice_id', $invoiceIds)->pluck('id')->all();
                DB::table('pos_settlement_events')->whereIn('tender_allocation_id', $tenderIds)->delete();
                DB::table('pos_refund_allocations')->whereIn('invoice_id', $invoiceIds)->delete();
                DB::table('return_lines')->whereIn('return_id', $returnIds)->delete();
                DB::table('returns')->whereIn('id', $returnIds)->delete();
                DB::table('pos_tender_allocations')->whereIn('id', $tenderIds)->delete();
                $claimIds = DB::table('claims')->whereIn('invoice_id', $invoiceIds)->pluck('id')->all();
                DB::table('claim_events')->whereIn('claim_id', $claimIds)->delete();
                DB::table('claims')->whereIn('id', $claimIds)->delete();

                $destinations = DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)
                    ->where('display_name', 'MT75 Fresh Bank')->get();
                abort_unless(DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)->count() === $destinations->count(), 409);
                $destinationIds = $destinations->pluck('id')->all();
                DB::table('pos_payment_destinations')->whereIn('id', $destinationIds)->delete();

                $products = DB::table('products')->where('outlet_id', $outlet->id)
                    ->whereIn('name', ['MT75 Fresh Accessory', 'MT75 Fresh Budget Accessory', 'MT75 Fresh Tracked Phone'])->get();
                abort_unless(DB::table('products')->where('outlet_id', $outlet->id)->count() === $products->count(), 409);
                $productIds = $products->pluck('id')->all();
                $productPublicIds = $products->pluck('public_id')->all();
                $acquisitionIds = DB::table('stock_acquisitions')->whereIn('product_id', $productIds)->pluck('id')->all();
                $unitIds = DB::table('stock_units')->whereIn('product_id', $productIds)->pluck('id')->all();
                DB::table('acquisition_source_references')->whereIn('acquisition_id', $acquisitionIds)->delete();
                DB::table('stock_unit_lineage')->whereIn('source_unit_id', $unitIds)
                    ->orWhereIn('successor_unit_id', $unitIds)->delete();
                DB::table('inventory_custody_holds')->whereIn('product_id', $productIds)->delete();
                DB::table('product_imeis')->whereIn('product_id', $productIds)->delete();
                DB::table('active_imeis')->whereIn('stock_unit_id', $unitIds)->delete();
                DB::table('pos_master_data_usages')->where('usage_type', 'stock_unit')
                    ->whereIn('usage_id', array_map('strval', $unitIds))->delete();
                DB::table('stock_units')->whereIn('product_id', $productIds)->delete();
                DB::table('stock_movements')->whereIn('product_id', $productIds)->delete();
                DB::table('pos_master_data_usages')->where('usage_type', 'stock_acquisition')
                    ->whereIn('usage_id', array_map('strval', $acquisitionIds))->delete();
                DB::table('stock_acquisitions')->whereIn('id', $acquisitionIds)->delete();
                DB::table('pos_master_data_usages')->where('usage_type', 'product')->whereIn('usage_id', array_map('strval', $productIds))->delete();
                DB::table('domain_events')->where('aggregate_type', 'product')->whereIn('aggregate_id', $productPublicIds)->delete();
                $listings = DB::table('product_listings')->whereIn('product_id', $productIds)
                    ->where('external_source', 'pos')->get(['public_id']);
                abort_unless(DB::table('product_listings')->whereIn('product_id', $productIds)->count() === $listings->count(), 409);
                DB::table('domain_events')->where('aggregate_type', 'product_listing')
                    ->whereIn('aggregate_id', $listings->pluck('public_id')->all())->delete();
                DB::table('product_listings')->whereIn('product_id', $productIds)->delete();
                DB::table('sales')->whereIn('invoice_id', $invoiceIds)->delete();
                DB::table('products')->whereIn('id', $productIds)->delete();
                DB::table('invoices')->whereIn('id', $invoiceIds)->delete();
                DB::table('customers')->whereIn('id', $customerIds)->where('display_name', 'MT75 Fresh Customer')->delete();
                DB::table('document_sequences')->where('outlet_id', $outlet->id)->delete();
            }

            $modeRevisions = DB::table('site_configuration_revisions')->where('domain', 'website.mode')->get(['id', 'created_by_admin_id']);
            abort_unless($modeRevisions->every(fn ($row) => (int) $row->created_by_admin_id === (int) $owner->id), 409);
            $modeIds = $modeRevisions->pluck('id')->all();
            DB::table('website_operating_profiles')->whereIn('revision_id', $modeIds)->delete();
            DB::table('domain_events')->where('aggregate_type', 'website_mode')
                ->whereIn('operation_key', array_map(fn ($id) => 'website-mode-revalidate:'.$id, $modeIds))->delete();
            DB::table('site_configuration_revisions')->whereIn('id', $modeIds)->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('admin_roles')->where('admin_id', $owner->id)->delete();
            DB::table('outlet_admins')->where('admin_id', $owner->id)->delete();
            DB::table('idempotency_requests')->where('actor_scope', 'App\\Models\\Admin:'.$owner->id)->delete();
            if ($outlet) {
                DB::table('outlets')->where('id', $outlet->id)->delete();
            }
            DB::table('admins')->where('id', $owner->id)->delete();
            abort_unless(! DB::table('pos_master_data_usages')->exists(), 409);
            $masterOptionIds = DB::table('pos_master_data_options')->pluck('id')->map(fn ($id) => (string) $id)->all();
            DB::table('domain_events')->where('aggregate_type', 'master_data')->whereIn('aggregate_id', $masterOptionIds)->delete();
            DB::table('pos_master_data_options')->delete();
            $this->purgeOrphanedFixtureResidue();
        });
    }

    private function cleanupFreshWebsiteCustomer(): void
    {
        $user = DB::table('users')->where('email', 'mt75-new-customer@example.invalid')
            ->where('name', 'MT75 Website Buyer')->first();
        if (! $user) {
            return;
        }
        $orders = DB::table('orders')->where('user_id', $user->id)->get();
        abort_unless($orders->every(fn ($order) => $order->order_type === 'commerce'
            && $order->customer_email === $user->email), 409);
        $orderIds = $orders->pluck('id')->all();
        $reservationIds = DB::table('reservations')->whereIn('order_id', $orderIds)->pluck('id')->all();
        $lineIds = DB::table('reservation_lines')->whereIn('reservation_id', $reservationIds)->pluck('id')->all();
        $paymentIds = DB::table('payments')->whereIn('order_id', $orderIds)->pluck('id')->all();
        DB::table('reservation_allocations')->whereIn('reservation_line_id', $lineIds)->delete();
        DB::table('reservation_lines')->whereIn('id', $lineIds)->delete();
        DB::table('payment_receipts')->whereIn('payment_id', $paymentIds)->delete();
        DB::table('reservations')->whereIn('id', $reservationIds)->delete();
        DB::table('payments')->whereIn('id', $paymentIds)->delete();
        DB::table('order_items')->whereIn('order_id', $orderIds)->delete();
        DB::table('orders')->whereIn('id', $orderIds)->delete();
        DB::table('idempotency_requests')->where('actor_scope', CustomerAccount::class.':'.$user->id)->delete();
        DB::table('account_sessions')->where('guard', 'customer')->where('account_id', $user->id)->delete();
        DB::table('identity_audit_events')->where('realm', 'customer')->where('account_id', $user->id)->delete();
        DB::table('notification_preferences')->where('customer_account_id', $user->id)->delete();
        DB::table('notification_rate_buckets')->where('customer_account_id', $user->id)->delete();
        DB::table('customers')->where('website_user_id', $user->id)->delete();
        DB::table('users')->where('id', $user->id)->delete();
    }

    private function purgeOrphanedFixtureResidue(): void
    {
        foreach (['product' => DB::table('products')->pluck('public_id')->map(fn ($id) => (string) $id)->all(),
            'master_data' => DB::table('pos_master_data_options')->pluck('id')->map(fn ($id) => (string) $id)->all()] as $type => $liveIds) {
            $query = DB::table('domain_events')->where('aggregate_type', $type);
            $liveIds ? $query->whereNotIn('aggregate_id', $liveIds)->delete() : $query->delete();
        }

        $operations = ['inventory.acquire', 'sales.sale', 'pos-payments.destination.create', 'pos-payments.sale'];
        foreach (DB::table('idempotency_requests')->whereIn('operation', $operations)->get(['id', 'actor_scope']) as $request) {
            if (preg_match('/\\AApp\\\\Models\\\\Admin:(\\d+)\\z/', (string) $request->actor_scope, $match)
                && ! DB::table('admins')->where('id', (int) $match[1])->exists()) {
                DB::table('idempotency_requests')->where('id', $request->id)->delete();
            }
        }
    }
}
