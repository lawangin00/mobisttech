<?php

namespace Database\Seeders;

use App\Cms\WebsiteModePublication;
use App\Inventory\StockLedger;
use App\Models\Admin;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebsiteStorefrontE2eStateSeeder extends Seeder
{
    public function run(): void
    {
        $mode = getenv('WEBSITE_E2E_MODE') ?: null;
        if ($mode) {
            $actor = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
            $draft = app(WebsiteModePublication::class)->saveDraft($actor, $mode);
            app(WebsiteModePublication::class)->publish($actor, $draft['id']);
        }

        if (getenv('WEBSITE_E2E_ACTION') === 'acquire-alpha') {
            DB::transaction(function () {
                $productId = DB::table('product_listings')
                    ->where('external_source', 'website-e2e')
                    ->where('slug', 'mt51-alpha-phone')
                    ->value('product_id');
                $product = Product::whereKey($productId)->lockForUpdate()->firstOrFail();
                app(StockLedger::class)->movement($product, 'restock', 1, 'website_e2e', null, 'MT-5.1 POS freshness acceptance');
            });
        }
    }
}
