<?php

namespace Database\Seeders;

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
            $owner = DB::table('admins')->where('email', 'mt75-fresh-owner@example.invalid')
                ->where('name', 'MT75 Fresh Protected Owner')->first();
            if (! $owner) {
                abort_unless(! DB::table('outlets')->where('name', 'MT75 Fresh First Outlet')->exists(), 409);

                return;
            }

            $outlet = DB::table('outlets')->where('name', 'MT75 Fresh First Outlet')
                ->where('outlet_code', '001')->first();
            abort_unless(! $outlet || (! DB::table('invoices')->where('outlet_id', $outlet->id)->exists()
                && ! DB::table('sales')->where('outlet_id', $outlet->id)->exists()), 409);

            if ($outlet) {
                $products = DB::table('products')->where('outlet_id', $outlet->id)
                    ->where('name', 'MT75 Fresh Accessory')->get();
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
                DB::table('stock_units')->whereIn('product_id', $productIds)->delete();
                DB::table('stock_movements')->whereIn('product_id', $productIds)->delete();
                DB::table('pos_master_data_usages')->where('usage_type', 'stock_acquisition')
                    ->whereIn('usage_id', array_map('strval', $acquisitionIds))->delete();
                DB::table('stock_acquisitions')->whereIn('id', $acquisitionIds)->delete();
                DB::table('pos_master_data_usages')->where('usage_type', 'product')->whereIn('usage_id', array_map('strval', $productIds))->delete();
                DB::table('domain_events')->where('aggregate_type', 'product')->whereIn('aggregate_id', $productPublicIds)->delete();
                DB::table('products')->whereIn('id', $productIds)->delete();
            }

            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('admin_roles')->where('admin_id', $owner->id)->delete();
            DB::table('outlet_admins')->where('admin_id', $owner->id)->delete();
            if ($outlet) {
                DB::table('outlets')->where('id', $outlet->id)->delete();
            }
            DB::table('admins')->where('id', $owner->id)->delete();
            abort_unless(! DB::table('pos_master_data_usages')->exists(), 409);
            DB::table('pos_master_data_options')->delete();
        });
    }
}
