<?php

namespace Database\Seeders;

use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

// Explicit empty-target defaults only; import source options before seeding a migrated target.
class PosMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Product::CATEGORY_LABELS as $code => $label) {
            $this->option('product_category', $code, $label);
        }
        foreach (StockUnit::COLOR_OPTIONS as $label => $hex) {
            $this->option('unit_color', Str::slug($label, '_'), $label, ['hex' => $hex]);
        }
        foreach (['device_ram_gb' => [1, 2, 3, 4, 6, 8, 10, 12, 16, 18, 24, 32, 48, 64], 'device_storage_gb' => [1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048, 4096, 8192]] as $list => $values) {
            foreach ($values as $value) {
                $label = $list === 'device_storage_gb' && $value >= 1024 ? ($value / 1024).' TB ('.$value.' GB)' : $value.' GB';
                $this->option($list, ($list === 'device_ram_gb' ? 'ram_' : 'storage_').$value.'gb', $label, ['value' => $value]);
            }
        }
        foreach (['single_physical' => ['Single Physical SIM', 1], 'dual_physical' => ['Dual Physical SIM', 2], 'physical_esim' => ['Physical SIM + eSIM', 2], 'dual_physical_esim' => ['Dual Physical SIM + eSIM', 2], 'esim_only' => ['eSIM Only', 1]] as $code => [$label, $slots]) {
            $this->option('device_sim_configuration', $code, $label, ['imei_slots' => $slots]);
        }
        foreach (['unit_condition' => StockUnit::CONDITIONS, 'unit_pta_status' => StockUnit::PTA_STATUSES, 'unit_carrier_lock_status' => StockUnit::CARRIER_LOCK_STATUSES, 'unit_mdm_status' => StockUnit::MDM_STATUSES] as $list => $options) {
            foreach ($options as $code => $label) {
                $this->option($list, $code, $label);
            }
        }
        foreach (['wholesaler' => 'Wholesaler', 'supplier' => 'Supplier', 'shop_dealer' => 'Shop', 'individual_seller' => 'Market / Customer', 'other_business' => 'Other Business'] as $code => $label) {
            $this->option('acquisition_source_type', $code, $label, ['party_kind' => $code === 'individual_seller' ? 'individual' : 'business']);
        }
    }

    private function option(string $list, string $code, string $label, array $metadata = []): void
    {
        $order = PosMasterDataOption::where('list_key', $list)->count() * 10 + 10;
        PosMasterDataOption::firstOrCreate(['list_key' => $list, 'code' => $code], ['label' => $label, 'sort_order' => $order, 'metadata' => $metadata, 'is_active' => true]);
    }
}
