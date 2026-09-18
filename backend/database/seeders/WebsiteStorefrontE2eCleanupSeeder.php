<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebsiteStorefrontE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $ids = DB::table('product_listings')->where('external_source', 'website-e2e')->pluck('product_id')->all();
            $publicIds = $ids ? DB::table('products')->whereIn('id', $ids)->pluck('public_id')->all() : [];
            if ($ids) {
                DB::table('stock_movements')->whereIn('product_id', $ids)->delete();
                DB::table('product_listings')->where('external_source', 'website-e2e')->delete();
                if ($publicIds) {
                    DB::table('domain_events')->whereIn('aggregate_id', $publicIds)->delete();
                }
                DB::table('products')->whereIn('id', $ids)->delete();
            }

            $adminId = DB::table('admins')->where('email', 'e2e-platform@example.invalid')->value('id');
            if ($adminId) {
                $revisionIds = DB::table('site_configuration_revisions')
                    ->where('domain', 'website.mode')->where('created_by_admin_id', $adminId)->pluck('id')->all();
                if ($revisionIds) {
                    DB::table('website_operating_profiles')->whereIn('revision_id', $revisionIds)->delete();
                    DB::table('site_configuration_revisions')->whereIn('id', $revisionIds)->delete();
                }
            }
        });
    }
}
