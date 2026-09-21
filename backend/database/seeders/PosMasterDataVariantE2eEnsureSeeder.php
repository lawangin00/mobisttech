<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Reuse only an exact, already seeded P02 fixture in the disposable test database. */
final class PosMasterDataVariantE2eEnsureSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);

        $options = [
            ['product_category', 'mobile_phone', 'Mobiles', []],
            ['product_subcategory', 'mobile_phone_mt75_variant', 'MT75 P02 Smartphone', ['parent_category_code' => 'mobile_phone']],
            ['product_brand', 'mt75_p02_variant_brand', 'MT75 P02 Variant Brand', []],
            ['device_ram_gb', 'ram_8gb', '8 GB', ['value' => 8]],
            ['device_storage_gb', 'storage_128gb', '128 GB', ['value' => 128]],
            ['device_sim_configuration', 'mt75_p02_dual_sim', 'MT75 P02 Dual SIM', ['imei_slots' => 2]],
        ];
        $outlet = DB::table('outlets')->where('outlet_code', '908')->first();
        if (! $outlet) {
            // Preserve the original seeder's strict collision guard for a fresh fixture.
            $this->call(PosMasterDataVariantE2eSeeder::class);

            return;
        }

        $actor = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
        if ($outlet->name !== 'MT75 P02 Variant Outlet' || (bool) $outlet->status !== false
            || ! $actor || DB::table('outlet_admins')->where('outlet_id', $outlet->id)->where('admin_id', $actor)->count() !== 1) {
            throw new RuntimeException('Existing P02 variant outlet is not the exact disposable browser fixture.');
        }

        foreach ($options as [$list, $code, $label, $metadata]) {
            $rows = DB::table('pos_master_data_options')->where('list_key', $list)->where('code', $code)->get();
            if ($rows->count() !== 1 || $rows[0]->label !== $label || ! (bool) $rows[0]->is_active
                || json_decode($rows[0]->metadata, true, flags: JSON_THROW_ON_ERROR) !== $metadata) {
                throw new RuntimeException('Existing P02 variant option differs from the exact disposable browser fixture: '.$list.'/'.$code);
            }
        }
    }
}
