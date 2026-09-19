<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PosShellE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        $photoPaths = DB::transaction(function () {
            $emails = ['e2e-sales@example.invalid', 'e2e-inventory@example.invalid', 'e2e-operations@example.invalid', 'e2e-mt43@example.invalid', 'e2e-platform@example.invalid', 'e2e-digital-operations@example.invalid', 'e2e-reset@example.invalid', 'e2e-protected-owner@example.invalid', 'e2e-audit-owner@example.invalid', 'e2e-pref-owner@example.invalid', 'e2e-history@example.invalid'];
            $adminIds = DB::table('admins')->whereIn('email', $emails)->pluck('id')->all();
            $photos = DB::table('admins')->whereIn('email', $emails)->get(['public_id', 'profile_photo'])
                ->filter(fn ($row) => is_string($row->profile_photo)
                    && preg_match('/^admin-profile-photos\/'.preg_quote($row->public_id, '/').'\/[a-f0-9-]{36}\.(jpg|png|webp)$/D', $row->profile_photo) === 1)
                ->pluck('profile_photo')->all();
            $roleIds = DB::table('roles')->whereIn('slug', ['e2e-salesperson', 'e2e-inventory-manager', 'e2e-operations-manager', 'e2e-mt43-manager', 'e2e-platform-administrator', 'e2e-digital-operations-manager', 'e2e-reset-administrator'])->pluck('id')->all();
            $outletIds = DB::table('outlets')->whereIn('outlet_code', ['E41', 'E42'])->pluck('id')->all();
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('identity_audit_events')->where('realm', 'admin')->whereIn('account_id', $adminIds)->where('action', 'outlet_created')->whereNotNull('outlet_id')->pluck('outlet_id')->all()]));

            $prefOwnerId = DB::table('admins')->where('email', 'e2e-pref-owner@example.invalid')->value('id');
            if ($prefOwnerId && DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $prefOwnerId)->where('action', 'pos_portal_preferences_updated')->exists()) {
                $keys = array_map(fn ($key) => 'portal.'.$key, array_keys(app(\App\Pos\PortalPreferences::class)->current()));
                abort_unless(DB::table('pos_settings')->where('group', 'portal')->count() === count($keys), 409);
                DB::table('pos_settings')->whereIn('key', $keys)->where('group', 'portal')->delete();
            }
            DB::table('invoices')->whereIn('outlet_id', $outletIds)
                ->where('invoice_number', 'like', 'MT75-HIST-%')
                ->where('customer_name', 'like', 'MT75 Synthetic Customer %')->delete();
            DB::table('pos_audit_logs')->whereIn('outlet_id', $outletIds)->whereIn('action', ['MT75 E2E North Audit', 'MT75 E2E South Audit'])->where('actor_email', 'e2e-protected-owner@example.invalid')->delete();
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
            return $photos;
        });
        if ($photoPaths) { Storage::disk('local')->delete($photoPaths); }
    }
}
