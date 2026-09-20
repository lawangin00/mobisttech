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
            abort_unless(! $outlet || (! DB::table('products')->where('outlet_id', $outlet->id)->exists()
                && ! DB::table('invoices')->where('outlet_id', $outlet->id)->exists()
                && ! DB::table('sales')->where('outlet_id', $outlet->id)->exists()), 409);

            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $owner->id)->delete();
            DB::table('admin_roles')->where('admin_id', $owner->id)->delete();
            DB::table('outlet_admins')->where('admin_id', $owner->id)->delete();
            if ($outlet) {
                DB::table('outlets')->where('id', $outlet->id)->delete();
            }
            DB::table('admins')->where('id', $owner->id)->delete();
        });
    }
}
