<?php

namespace Database\Seeders;

use App\Cms\WebsiteModePublication;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteStorefrontE2eSeeder extends Seeder
{
    public function run(): void
    {
        $this->clearProducts();

        $outlet = Outlet::where('outlet_code', 'E41')->firstOrFail();
        $names = [
            ['mt51-alpha-phone', 'MT51 Alpha Phone', 'mobile_phone', 1],
            ['mt51-beta-phone', 'MT51 Beta Phone', 'mobile_phone', 2],
            ['mt51-zero-stock', 'MT51 Zero Stock', 'mobile_phone', 0],
            ['mt51-accessory-01', 'MT51 Accessory 01', 'accessory', 1],
            ['mt51-accessory-02', 'MT51 Accessory 02', 'accessory', 1],
            ['mt51-accessory-03', 'MT51 Accessory 03', 'accessory', 1],
            ['mt51-accessory-04', 'MT51 Accessory 04', 'accessory', 1],
            ['mt51-accessory-05', 'MT51 Accessory 05', 'accessory', 1],
            ['mt51-accessory-06', 'MT51 Accessory 06', 'accessory', 1],
            ['mt51-accessory-07', 'MT51 Accessory 07', 'accessory', 1],
            ['mt51-accessory-08', 'MT51 Accessory 08', 'accessory', 1],
            ['mt51-accessory-09', 'MT51 Accessory 09', 'accessory', 1],
            ['mt51-accessory-10', 'MT51 Accessory 10', 'accessory', 1],
            ['mt51-accessory-11', 'MT51 Accessory 11', 'accessory', 1],
        ];

        foreach ($names as $index => [$slug, $name, $category, $qty]) {
            $publicId = (string) Str::uuid();
            $productId = DB::table('products')->insertGetId([
                'name' => $name,
                'price' => sprintf('%.2f', 50000 + ($index * 1000)),
                'outlet_id' => $outlet->id,
                'description' => 'Synthetic MT-5.1 public storefront fixture for '.$name.'.',
                'qty' => $qty,
                'sold_qty' => 0,
                'isDeleted' => false,
                'track_imei' => false,
                'purchase_price' => '123.45',
                'sale_price' => sprintf('%.2f', 50000 + ($index * 1000)),
                'category' => $category,
                'brand' => 'MT51 Brand',
                'model' => $category === 'mobile_phone' ? 'Model '.($index + 1) : null,
                'sim_configuration' => $category === 'mobile_phone' ? 'dual_physical' : 'not_applicable',
                'warranty_type' => 'no_warranty',
                'public_id' => $publicId,
                'version' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('product_listings')->insert([
                'external_source' => 'website-e2e',
                'external_id' => $slug,
                'slug' => $slug,
                'name' => $name,
                'brand' => 'MT51 Brand',
                'model' => $category === 'mobile_phone' ? 'Model '.($index + 1) : null,
                'description' => 'Public description for '.$name.'.',
                'warranty_summary' => 'No warranty',
                'is_online' => true,
                'category' => $category,
                'public_id' => (string) Str::uuid(),
                'version' => 1,
                'product_id' => $productId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->publishMode('hybrid');
        DB::table('publication_versions')->updateOrInsert(['domain' => 'catalogue'], ['version' => 1]);
    }

    private function publishMode(string $mode): void
    {
        $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
        $draft = app(WebsiteModePublication::class)->saveDraft($actor, $mode);
        app(WebsiteModePublication::class)->publish($actor, $draft['id']);
    }

    private function clearProducts(): void
    {
        $ids = array_values(array_unique([
            ...DB::table('product_listings')->where('external_source', 'website-e2e')->pluck('product_id')->all(),
            ...DB::table('products')->where('name', 'like', 'MT51 %')->pluck('id')->all(),
        ]));
        if (! $ids) {
            return;
        }
        $publicIds = DB::table('products')->whereIn('id', $ids)->pluck('public_id')->all();
        DB::table('stock_movements')->whereIn('product_id', $ids)->delete();
        DB::table('product_listings')->where('external_source', 'website-e2e')->delete();
        if ($publicIds) {
            DB::table('domain_events')->whereIn('aggregate_id', $publicIds)->delete();
        }
        DB::table('products')->whereIn('id', $ids)->delete();
    }
}
