<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosMasterDataVariantE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $actor = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
            abort_unless($actor && ! DB::table('outlets')->where('outlet_code', '908')->exists(), 409);
            $outlet = DB::table('outlets')->insertGetId(['public_id' => (string) Str::uuid(), 'name' => 'MT75 P02 Variant Outlet', 'outlet_code' => '908', 'status' => false, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('outlet_admins')->insert(['outlet_id' => $outlet, 'admin_id' => $actor]);
            $options = [
                ['product_category', 'mobile_phone', 'Mobiles', []],
                ['product_subcategory', 'mobile_phone_mt75_variant', 'MT75 P02 Smartphone', ['parent_category_code' => 'mobile_phone']],
                ['product_brand', 'mt75_p02_variant_brand', 'MT75 P02 Variant Brand', []],
                ['device_ram_gb', 'ram_8gb', '8 GB', ['value' => 8]],
                ['device_storage_gb', 'storage_128gb', '128 GB', ['value' => 128]],
                ['device_sim_configuration', 'mt75_p02_dual_sim', 'MT75 P02 Dual SIM', ['imei_slots' => 2]],
            ];
            foreach ($options as [$list, $code, $label, $metadata]) {
                abort_unless(! DB::table('pos_master_data_options')->where('list_key', $list)->where('code', $code)->exists(), 409, 'Synthetic variant option must not overwrite existing options.');
                DB::table('pos_master_data_options')->insert(['list_key' => $list, 'code' => $code, 'label' => $label, 'metadata' => json_encode($metadata), 'sort_order' => 15, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        });
    }
}
