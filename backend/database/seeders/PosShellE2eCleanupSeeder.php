<?php

namespace Database\Seeders;

use App\Pos\PortalPreferences;
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
            $d03Outlet = DB::table('outlets')->where('outlet_code', 'E43')->where('name', 'E2E D03 Closed Cash Outlet')->first();
            if ($d03Outlet) {
                $d03Cash = DB::table('cash_sessions')->where('outlet_id', $d03Outlet->id)->get();
                abort_unless($d03Cash->count() === 1 && $d03Cash[0]->status === 'closed'
                    && str_contains((string) $d03Cash[0]->closing_snapshot, 'MT75-D03-CLOSED-CASH')
                    && ! DB::table('cash_entries')->where('outlet_id', $d03Outlet->id)->exists(), 409);
                DB::table('cash_sessions')->where('id', $d03Cash[0]->id)->delete();
            }
            $outletIds = DB::table('outlets')->whereIn('outlet_code', ['E41', 'E42', 'E43'])->pluck('id')->all();
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('outlets')->where('outlet_code', '908')->where('name', 'MT75 P02 Variant Outlet')->pluck('id')->all()]));
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('outlets')->where('outlet_code', '909')
                ->where('name', 'MT75 P02 Linked Outlet')->pluck('id')->all()]));
            $outletIds = array_values(array_unique([...$outletIds, ...DB::table('identity_audit_events')->where('realm', 'admin')->whereIn('account_id', $adminIds)->where('action', 'outlet_created')->whereNotNull('outlet_id')->pluck('outlet_id')->all()]));

            $prefOwnerId = DB::table('admins')->where('email', 'e2e-pref-owner@example.invalid')->value('id');
            if ($prefOwnerId && DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $prefOwnerId)->where('action', 'pos_portal_preferences_updated')->exists()) {
                $keys = array_map(fn ($key) => 'portal.'.$key, array_keys(app(PortalPreferences::class)->current()));
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
            if ($intakeInvoices) {
                DB::table('sales')->whereIn('invoice_id', $intakeInvoices)->whereIn('outlet_id', $outletIds)->delete();
            }
            if ($intakeInvoices) {
                DB::table('invoices')->whereIn('id', $intakeInvoices)->delete();
            }
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
            $variantOutlet = DB::table('outlets')->where('outlet_code', '908')->where('name', 'MT75 P02 Variant Outlet')->value('id');
            // Remove only source-qualified P02 synthetic import rows before its 908 outlet/options.
            $importRun = DB::table('migration_runs')->where('input_manifest_hash', str_repeat('e', 64))
                ->where('code_hash', str_repeat('f', 64))->where('schema_hash', str_repeat('a', 64))
                ->where('target_identity', 'mobisttech_test')->where('status', 'product_rehearsal')->first();
            if ($importRun) {
                $importProduct = DB::table('products')->where('name', 'MT75 P02 Imported Phone')
                    ->where('product_code', 'MST-MOB-908-000807')->first();
                $importBrand = DB::table('pos_master_data_options')->where('list_key', 'product_brand')
                    ->where('code', 'mt75_p02_imported_brand')->where('label', 'MT75 P02 Imported Brand')->first();
                abort_unless($importProduct && $importBrand && (int) $importProduct->outlet_id === (int) $variantOutlet
                    && (int) $importProduct->brand_master_data_id === (int) $importBrand->id
                    && $importProduct->brand === 'MT75 Original Imported Brand'
                    && ! DB::table('stock_units')->where('product_id', $importProduct->id)->exists()
                    && ! DB::table('sales')->where('product_id', $importProduct->id)->exists()
                    && ! DB::table('product_listings')->where('product_id', $importProduct->id)->exists(), 409);
                $maps = DB::table('migration_identity_map')->where('run_id', $importRun->id)->get();
                abort_unless($maps->count() === 4 && $maps->where('source_repository', 'pos')->count() === 4
                    && $maps->where('source_table', 'products')->where('source_primary_key', '807')
                        ->where('target_id', (string) $importProduct->id)->count() === 1
                    && $maps->where('source_table', 'pos_master_data_options')->where('source_primary_key', '802')
                        ->where('target_id', (string) $importBrand->id)->count() === 1, 409);

                abort_unless(! DB::table('migration_quarantine')->where('run_id', $importRun->id)->exists()
                    && ! DB::table('migration_reconciliation')->where('run_id', $importRun->id)->exists()
                    && ! DB::table('migration_source_history')->where('run_id', $importRun->id)->exists(), 409);
                DB::table('pos_master_data_usages')->where('usage_type', 'product')
                    ->where('usage_id', (string) $importProduct->id)->delete();
                DB::table('domain_events')->where('aggregate_type', 'product')
                    ->where('aggregate_id', $importProduct->public_id)->delete();
                DB::table('products')->where('id', $importProduct->id)->delete();
                abort_unless(! DB::table('pos_master_data_usages')->where('master_data_option_id', $importBrand->id)->exists(), 409);
                DB::table('pos_master_data_options')->where('id', $importBrand->id)->delete();
                DB::table('migration_identity_map')->where('run_id', $importRun->id)->delete();
                DB::table('migration_runs')->where('id', $importRun->id)->delete();
            }
            if ($variantOutlet) {
                $variantProduct = DB::table('products')->where('outlet_id', $variantOutlet)->where('name', 'MT75 P02 Variant Phone')->first();
                if ($variantProduct) {
                    abort_unless($variantProduct->category === 'mobile_phone' && ! DB::table('stock_units')->where('product_id', $variantProduct->id)->exists()
                        && ! DB::table('product_listings')->where('product_id', $variantProduct->id)->exists()
                        && ! DB::table('sales')->where('product_id', $variantProduct->id)->exists(), 409);
                    DB::table('pos_master_data_usages')->where('usage_type', 'product')->where('usage_id', (string) $variantProduct->id)->delete();
                    DB::table('domain_events')->where('aggregate_type', 'product')->where('aggregate_id', $variantProduct->public_id)->delete();
                    DB::table('products')->where('id', $variantProduct->id)->delete();
                }
                foreach (['product_category' => ['mobile_phone', 'Mobiles'], 'product_subcategory' => ['mobile_phone_mt75_variant', 'MT75 P02 Smartphone'],
                    'product_brand' => ['mt75_p02_variant_brand', 'MT75 P02 Variant Brand'], 'device_ram_gb' => ['ram_8gb', '8 GB'],
                    'device_storage_gb' => ['storage_128gb', '128 GB'], 'device_sim_configuration' => ['mt75_p02_dual_sim', 'MT75 P02 Dual SIM']] as $list => [$code, $label]) {
                    $option = DB::table('pos_master_data_options')->where('list_key', $list)->where('code', $code)->where('label', $label)->first();
                    abort_unless($option && ! DB::table('pos_master_data_usages')->where('master_data_option_id', $option->id)->exists(), 409);
                    DB::table('pos_master_data_options')->where('id', $option->id)->delete();
                }
            }
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
            // This category belongs to canonical fresh-owner bootstrap, not the linked-history fixture.
            // Keep it intact for remaining synthetic tests and the final guarded fresh-owner cleanup.
            if ($linkedOption) {
                $accessories = DB::table('pos_master_data_options')->where('list_key', 'product_category')
                    ->where('code', 'accessory')->get();
                abort_unless($accessories->count() === 1 && $accessories[0]->label === 'Accessories'
                    && (bool) $accessories[0]->is_active && $accessories[0]->archived_at === null
                    && json_decode($accessories[0]->metadata ?? '[]', true, flags: JSON_THROW_ON_ERROR) === [], 409,
                    'Canonical accessory category must be retained by linked-history cleanup.');
            }
            DB::table('pos_audit_logs')->whereIn('outlet_id', $outletIds)->whereIn('action', ['MT75 E2E North Audit', 'MT75 E2E South Audit'])->where('actor_email', 'e2e-protected-owner@example.invalid')->delete();
            if ($adminIds) {
                // Scoped only to this known disposable D05 owner; preserve all other recovery history.
                $testOwner = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')
                    ->where('public_id', '0d055c06-65d1-4d49-a4d5-527597c0de05')->first();
                if ($testOwner && in_array((int) $testOwner->id, array_map('intval', $adminIds), true)) {
                    DB::table('owner_offline_recovery_codes')->where('admin_id', $testOwner->id)->delete();
                }
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
        if ($photoPaths) {
            Storage::disk('local')->delete($photoPaths);
        }
    }
}
