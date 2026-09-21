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
            // The fresh-owner bootstrap installs this protected category before shared browser tests.
            // Fail closed on any altered baseline; never insert a duplicate or delete the canonical row.
            $accessories = DB::table('pos_master_data_options')->where('list_key', 'product_category')
                ->where('code', 'accessory')->get();
            abort_unless($accessories->count() === 1 && $accessories[0]->label === 'Accessories'
                && (bool) $accessories[0]->is_active && $accessories[0]->archived_at === null
                && json_decode($accessories[0]->metadata ?? '[]', true, flags: JSON_THROW_ON_ERROR) === [], 409,
                'Scoped test requires the exact canonical accessory category baseline.');
            abort_unless(! DB::table('outlets')->where('outlet_code', '909')->exists(), 409);
            $ownerId = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
            abort_unless($ownerId, 409);
            $outletId = DB::table('outlets')->insertGetId([
                'public_id' => (string) Str::uuid(), 'name' => 'MT75 P02 Linked Outlet',
                'outlet_code' => '909', 'status' => false, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('outlet_admins')->insert(['outlet_id' => $outletId, 'admin_id' => $ownerId]);
        });
    }
}
