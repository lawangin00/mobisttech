<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebsiteStorefrontE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('Website storefront E2E cleanup is restricted to mobisttech_test.');
        }

        DB::transaction(function () {
            $listings = DB::table('product_listings')->where('external_source', 'website-e2e')->get(['id', 'product_id']);
            $ids = array_values(array_unique([
                ...$listings->pluck('product_id')->all(),
                ...DB::table('products')->where('name', 'like', 'MT51 %')->pluck('id')->all(),
            ]));
            $listingIds = $listings->pluck('id')->all();
            $publicIds = $ids ? DB::table('products')->whereIn('id', $ids)->pluck('public_id')->all() : [];
            if ($ids) {
                $subscriptionIds = DB::table('product_notification_subscriptions')->whereIn('product_id', $ids)->pluck('id')->all();
                if ($subscriptionIds) {
                    DB::table('notification_delivery_attempts')->whereIn('subscription_id', $subscriptionIds)->delete();
                }
                DB::table('product_notification_subscriptions')->whereIn('product_id', $ids)->delete();
                DB::table('wishlist_items')->whereIn('product_id', $ids)->delete();
                if ($listingIds) {
                    DB::table('product_reviews')->whereIn('product_listing_id', $listingIds)->delete();
                }
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

            DB::table('domain_events')->where('aggregate_type', 'website_mode')->where('aggregate_id', 'singleton')->delete();
            DB::table('publication_versions')->whereIn('domain', ['website.mode', 'cms.pages', 'cms.presentation', 'catalogue'])->delete();
        });
    }
}
