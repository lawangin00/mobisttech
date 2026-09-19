<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PosMasterDataLinkedE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            abort_unless(! DB::table('pos_master_data_options')->where('list_key', 'product_category')
                ->where('code', 'accessory')->exists(), 409, 'Scoped test expects an empty accessory category baseline.');
            abort_unless(! DB::table('outlets')->where('outlet_code', '909')->exists(), 409);
            $ownerId = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
            abort_unless($ownerId, 409);
            $outletId = DB::table('outlets')->insertGetId([
                'public_id' => (string) Str::uuid(), 'name' => 'MT75 P02 Linked Outlet',
                'outlet_code' => '909', 'status' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('outlet_admins')->insert(['outlet_id' => $outletId, 'admin_id' => $ownerId]);
            DB::table('pos_master_data_options')->insert([
                'list_key' => 'product_category', 'code' => 'accessory', 'label' => 'Accessories',
                'sort_order' => 30, 'is_active' => true, 'metadata' => '{}', 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }
}