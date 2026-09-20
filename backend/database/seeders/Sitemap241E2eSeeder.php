<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Only the disposable MT75 first-outlet test database; never a production seeder. */
final class Sitemap241E2eSeeder extends Seeder
{
    private const PREFIX = 'mt75-sitemap-fixture-';

    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_FIRST_OUTLET_E2E_ENABLED') === '1' && getenv('MT75_SITEMAP241_ENABLED') === '1', 403);
        $action = getenv('MT75_SITEMAP241_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        DB::transaction(function () use ($action) {
            $owner = DB::table('admins')->where('email', 'mt75-fresh-owner@example.invalid')
                ->where('name', 'MT75 Fresh Protected Owner')->first();
            $outlet = DB::table('outlets')->where('name', 'MT75 Fresh First Outlet')
                ->where('outlet_code', '001')->first();
            $existing = DB::table('product_listings')->where('slug', 'like', self::PREFIX.'%')->get();
            if (! $owner || ! $outlet) {
                abort_unless($action === 'cleanup' && $existing->isEmpty(), 409);

                return;
            }
            $ids = [];
            foreach ($existing as $listing) {
                $suffix = substr($listing->slug, strlen(self::PREFIX));
                $product = DB::table('products')->where('id', $listing->product_id)->first();
                abort_unless(ctype_digit($suffix) && (int) $suffix >= 4 && (int) $suffix <= 241
                    && $listing->external_source === 'pos' && $listing->external_id === $listing->slug
                    && $product && (int) $product->outlet_id === (int) $outlet->id
                    && $product->name === 'MT75 Sitemap Fixture '.$suffix
                    && (int) $product->qty === 0 && ! $product->track_imei, 409);
                $ids[] = $product->id;
            }
            if ($action === 'cleanup') {
                abort_unless(count($ids) <= 238 && DB::table('stock_units')->whereIn('product_id', $ids)->doesntExist()
                    && DB::table('order_items')->whereIn('product_id', $ids)->doesntExist()
                    && DB::table('stock_movements')->whereIn('product_id', $ids)->doesntExist()
                    && DB::table('pos_master_data_usages')->where('usage_type', 'product')
                        ->whereIn('usage_id', array_map('strval', $ids))->doesntExist(), 409);
                DB::table('product_listings')->whereIn('product_id', $ids)->delete();
                DB::table('products')->whereIn('id', $ids)->delete();
                abort_unless(DB::table('product_listings')->where('slug', 'like', self::PREFIX.'%')->doesntExist(), 409);
                if ($ids) {
                    abort_unless(DB::table('publication_versions')->where('domain', 'catalogue')->increment('version') === 1, 409);
                }

                return;
            }
            abort_unless($existing->isEmpty() && DB::table('products')->where('name', 'like', 'MT75 Sitemap Fixture %')->doesntExist(), 409);
            $baseline = DB::table('products')->where('outlet_id', $outlet->id)->pluck('name')->sort()->values()->all();
            $expected = ['MT75 Fresh Accessory', 'MT75 Fresh Budget Accessory', 'MT75 Fresh Tracked Phone'];
            sort($expected);
            abort_unless($baseline === $expected && DB::table('product_listings')->count() === 3
                && DB::table('product_listings')->where('is_online', false)->doesntExist(), 409);
            for ($i = 4; $i <= 241; $i++) {
                $suffix = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
                $slug = self::PREFIX.$suffix;
                $name = 'MT75 Sitemap Fixture '.$suffix;
                $productId = DB::table('products')->insertGetId([
                    'name' => $name, 'price' => '120.00', 'outlet_id' => $outlet->id,
                    'qty' => 0, 'sold_qty' => 0, 'track_imei' => false, 'isDeleted' => false,
                    'purchase_price' => '0.00', 'sale_price' => '120.00', 'category' => 'accessory',
                    'warranty_type' => 'no_warranty', 'public_id' => (string) Str::uuid(),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('product_listings')->insert([
                    'external_source' => 'pos', 'external_id' => $slug, 'slug' => $slug,
                    'name' => $name, 'category' => 'accessory', 'is_online' => true,
                    'public_id' => (string) Str::uuid(), 'version' => 1, 'product_id' => $productId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            abort_unless(DB::table('product_listings')->where('slug', 'like', self::PREFIX.'%')->count() === 238, 409);
            // Test-only POS-equivalent publication invalidates previously cached three-item listings.
            abort_unless(DB::table('publication_versions')->where('domain', 'catalogue')->increment('version') === 1, 409);
        });
    }
}
