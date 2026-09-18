<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PosShellE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $emails = ['e2e-sales@example.invalid', 'e2e-inventory@example.invalid', 'e2e-operations@example.invalid'];
            $adminIds = DB::table('admins')->whereIn('email', $emails)->pluck('id')->all();
            $roleIds = DB::table('roles')->whereIn('slug', ['e2e-salesperson', 'e2e-inventory-manager', 'e2e-operations-manager'])->pluck('id')->all();
            $outletIds = DB::table('outlets')->whereIn('outlet_code', ['E41', 'E42'])->pluck('id')->all();

            if ($adminIds) {
                DB::table('identity_audit_events')->where('realm', 'admin')->whereIn('account_id', $adminIds)->delete();
                DB::table('account_sessions')->where('guard', 'admin')->whereIn('account_id', $adminIds)->delete();
                DB::table('admin_roles')->whereIn('admin_id', $adminIds)->delete();
                DB::table('outlet_admins')->whereIn('admin_id', $adminIds)->delete();
                DB::table('admins')->whereIn('id', $adminIds)->delete();
            }
            if ($roleIds) {
                DB::table('admin_roles')->whereIn('role_id', $roleIds)->delete();
                DB::table('role_permissions')->whereIn('role_id', $roleIds)->delete();
                DB::table('roles')->whereIn('id', $roleIds)->delete();
            }
            if ($outletIds) {
                DB::table('outlet_admins')->whereIn('outlet_id', $outletIds)->delete();
                DB::table('outlets')->whereIn('id', $outletIds)->delete();
            }
        });
    }
}
