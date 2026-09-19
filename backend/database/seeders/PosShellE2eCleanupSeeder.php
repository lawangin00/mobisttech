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
            $emails = ['e2e-sales@example.invalid', 'e2e-inventory@example.invalid', 'e2e-operations@example.invalid', 'e2e-mt43@example.invalid', 'e2e-platform@example.invalid', 'e2e-digital-operations@example.invalid', 'e2e-reset@example.invalid', 'e2e-protected-owner@example.invalid', 'e2e-audit-owner@example.invalid', 'e2e-pref-owner@example.invalid', 'e2e-history@example.invalid', 'e2e-iw-inventory@example.invalid', 'e2e-iw-warranty@example.invalid', 'e2e-transaction@example.invalid', 'e2e-intake@example.invalid'];
            $adminIds = DB::table('admins')->whereIn('email', $emails)->pluck('id')->all();
            $photos = DB::table('admins')->whereIn('email', $emails)->get(['public_id', 'profile_photo'])
                ->filter(fn ($row) => is_string($row->profile_photo)
                    && preg_match('/^admin-profile-photos\/'.preg_quote($row->public_id, '/').'\/[a-f0-9-]{36}\.(jpg|png|webp)$/D', $row->profile_photo) === 1)
                ->pluck('profile_photo')->all();
            $roleIds = DB::table('roles')->whereIn('slug', ['e2e-salesperson', 'e2e-inventory-manager', 'e2e-operations-manager', 'e2e-mt43-manager', 'e2e-platform-administrator', 'e2e-digital-operations-manager', 'e2e-reset-administrator'])->pluck('id')->all();
            $outletIds = DB::table('outlets')->whereIn('outlet_code', ['E41', 'E42'])->pluck('id')->all();
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('outlets')->where('outlet_code', '909')
                ->where('name', 'MT75 P02 Linked Outlet')->pluck('id')->all()]));
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('identity_audit_events')->where('realm', 'admin')->whereIn('account_id', $adminIds)->where('action', 'outlet_created')->whereNotNull('outlet_id')->pluck('outlet_id')->all()]));

            $prefOwnerId = DB::table('admins')->where('email', 'e2e-pref-owner@example.invalid')->value('id');
            if ($prefOwnerId && DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $prefOwnerId)->where('action', 'pos_portal_preferences_updated')->exists()) {
                $keys = array_map(fn ($key) => 'portal.'.$key, array_keys(app(\App\Pos\PortalPreferences::class)->current()));
                abort_unless(DB::table('pos_settings')->where('group', 'portal')->count() === count($keys), 409);
                DB::table('pos_settings')->whereIn('key', $keys)->where('group', 'portal')->delete();
            }
            $intakeInvoices = DB::table('invoices')->whereIn('outlet_id', $outletIds)->where('invoice_number', 'like', 'MT75-INTAKE-%')->where('customer_name', 'like', 'MT75 Intake Customer %')->pluck('id')->all();
            if ($intakeInvoices) {
                abort_unless(DB::table('pos_settings')->where('key', 'portal.warranty_search_category')
                    ->where('value', 'invoice_id')->where('group', 'portal')->exists(), 409);
                DB::table('pos_settings')->where('key', 'portal.warranty_search_category')
                    ->where('value', 'invoice_id')->where('group', 'portal')->delete();
            }
            if ($intakeInvoices) DB::table('sales')->whereIn('invoice_id', $intakeInvoices)->whereIn('outlet_id', $outletIds)->delete();
            if ($intakeInvoices) DB::table('invoices')->whereIn('id', $intakeInvoices)->delete();
            DB::table('products')->whereIn('outlet_id', $outletIds)->where('name', 'MT75 Intake Product')->delete();
            DB::table('claims')->whereIn('outlet_id', $outletIds)
                ->where('claim_number', 'like', 'MT75-IW-CLAIM-%')->delete();
            DB::table('invoices')->whereIn('outlet_id', $outletIds)
                ->where('invoice_number', 'like', 'MT75-IW-INV-%')
                ->where('customer_name', 'like', 'MT75-IW Customer %')->delete();
            DB::table('products')->whereIn('outlet_id', $outletIds)
                ->where('name', 'like', 'MT75-IW Product %')->delete();
            DB::table('invoices')->whereIn('outlet_id', $outletIds)
                ->where('invoice_number', 'like', 'MT75-HIST-%')
                ->where('customer_name', 'like', 'MT75 Synthetic Customer %')->delete();
            // Remove only this synthetic linked-history E2E product and its option usage, never real catalogue rows.
            $linkedOption = DB::table('pos_master_data_options')->where('list_key', 'product_brand')
                ->where('code', 'mt75_p02_linked_brand')->whereIn('label', ['MT75 P02 Linked Brand', 'MT75 P02 Linked Brand Updated'])->first();
            $linkedProduct = DB::table('products')->whereIn('outlet_id', $outletIds)
                ->where('name', 'MT75 P02 Linked Product')->where('brand', 'MT75 P02 Linked Brand')->first();
            if ($linkedProduct) {
                abort_unless($linkedOption && (int) $linkedProduct->brand_master_data_id === (int) $linkedOption->id, 409);
                abort_unless(! DB::table('stock_units')->where('product_id', $linkedProduct->id)->exists()
                    && ! DB::table('sales')->where('product_id', $linkedProduct->id)->exists()
                    && ! DB::table('product_listings')->where('product_id', $linkedProduct->id)->exists(), 409);
                DB::table('pos_master_data_usages')->where('usage_type', 'product')->where('usage_id', (string) $linkedProduct->id)->delete();
                DB::table('domain_events')->where('aggregate_type', 'product')->where('aggregate_id', $linkedProduct->public_id)->delete();
                DB::table('products')->where('id', $linkedProduct->id)->delete();
            }
            if ($linkedOption) {
                abort_unless(! DB::table('pos_master_data_usages')->where('master_data_option_id', $linkedOption->id)->exists(), 409);
                DB::table('domain_events')->where('aggregate_type', 'master_data')->where('aggregate_id', (string) $linkedOption->id)->delete();
                DB::table('pos_master_data_options')->where('id', $linkedOption->id)->delete();
            }
            $p02Option = DB::table('pos_master_data_options')->where('list_key', 'product_brand')
                ->where('code', 'mt75_p02_browser_brand')->whereIn('label', ['MT75 P02 Browser Brand', 'MT75 P02 Browser Brand Edited'])->first();
            if ($p02Option) {
                abort_unless(! DB::table('pos_master_data_usages')->where('master_data_option_id', $p02Option->id)->exists(), 409);
                DB::table('domain_events')->where('aggregate_type', 'master_data')
                    ->where('aggregate_id', (string) $p02Option->id)->delete();
                DB::table('pos_master_data_options')->where('id', $p02Option->id)->delete();
            }
            $syntheticAccessory = DB::table('pos_master_data_options')->where('list_key', 'product_category')
                ->where('code', 'accessory')->where('label', 'Accessories')->first();
            if ($linkedOption && $syntheticAccessory) {
                abort_unless(! DB::table('pos_master_data_usages')->where('master_data_option_id', $syntheticAccessory->id)->exists()
                    && ! DB::table('products')->where('category_master_data_id', $syntheticAccessory->id)->exists(), 409);
                DB::table('pos_master_data_options')->where('id', $syntheticAccessory->id)->delete();
            }
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
