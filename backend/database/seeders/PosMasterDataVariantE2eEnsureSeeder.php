<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/** Reuse verified first-owner defaults; insert only missing, exact P02 test fixtures. */
final class PosMasterDataVariantE2eEnsureSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test', 403);

        $options = [
            ['product_category', 'mobile_phone', 'Mobiles', []],
            ['product_subcategory', 'mobile_phone_mt75_variant', 'MT75 P02 Smartphone', ['parent_category_code' => 'mobile_phone']],
            ['product_brand', 'mt75_p02_variant_brand', 'MT75 P02 Variant Brand', []],
            ['device_ram_gb', 'ram_8gb', '8 GB', ['value' => 8]],
            ['device_storage_gb', 'storage_128gb', '128 GB', ['value' => 128]],
            ['device_sim_configuration', 'mt75_p02_dual_sim', 'MT75 P02 Dual SIM', ['imei_slots' => 2]],
        ];

        DB::transaction(function () use ($options): void {
            $actor = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
            if (! $actor) {
                throw new RuntimeException('Expected synthetic P02 fixture actor is missing.');
            }

            // The canonical first-owner bootstrap already installs category, RAM and storage.
            // Never overwrite an existing option or accept a differently defined option.
            $missing = [];
            foreach ($options as [$list, $code, $label, $metadata]) {
                $rows = DB::table('pos_master_data_options')->where('list_key', $list)->where('code', $code)->lockForUpdate()->get();
                if ($rows->isEmpty()) {
                    $missing[] = [$list, $code, $label, $metadata];

                    continue;
                }
                if ($rows->count() !== 1 || $rows[0]->label !== $label || ! (bool) $rows[0]->is_active
                    || $rows[0]->archived_at !== null
                    || json_decode($rows[0]->metadata ?? '[]', true, flags: JSON_THROW_ON_ERROR) !== $metadata) {
                    throw new RuntimeException('Existing P02 option does not match exact fixture contract: '.$list.'/'.$code);
                }
            }

            $outlet = DB::table('outlets')->where('outlet_code', '908')->lockForUpdate()->first();
            if ($outlet) {
                if ($outlet->name !== 'MT75 P02 Variant Outlet' || (bool) $outlet->status !== false
                    || $outlet->archived_at !== null
                    || DB::table('outlet_admins')->where('outlet_id', $outlet->id)->count() !== 1
                    || DB::table('outlet_admins')->where('outlet_id', $outlet->id)->where('admin_id', $actor)->count() !== 1) {
                    throw new RuntimeException('Existing P02 variant outlet differs from exact disposable browser fixture.');
                }
            } else {
                $id = DB::table('outlets')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'name' => 'MT75 P02 Variant Outlet',
                    'outlet_code' => '908', 'status' => false, 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('outlet_admins')->insert(['outlet_id' => $id, 'admin_id' => $actor]);
            }

            foreach ($missing as [$list, $code, $label, $metadata]) {
                DB::table('pos_master_data_options')->insert([
                    'list_key' => $list, 'code' => $code, 'label' => $label,
                    'metadata' => json_encode($metadata, JSON_THROW_ON_ERROR), 'sort_order' => 15,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
    }
}
