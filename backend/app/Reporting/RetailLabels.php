<?php

namespace App\Reporting;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Support\Facades\DB;

final class RetailLabels
{
    public function product(IdentityAccount $actor, Outlet $outlet, string $productId): array
    {
        $this->authorize($actor, $outlet);
        $product = Product::where('public_id', $productId)->where('outlet_id', $outlet->id)->firstOrFail();

        return [
            'contract' => 'retail-label.v1', 'kind' => 'product', 'outlet_id' => $outlet->public_id,
            'product_id' => $product->public_id, 'product_code' => $product->product_code, 'name' => $product->name,
            'category' => $product->categoryDisplay(), 'sale_price' => (string) $product->sale_price,
            'barcode_value' => $product->product_code, 'qr_value' => 'mobist:product:'.$product->public_id,
        ];
    }

    public function unit(IdentityAccount $actor, Outlet $outlet, string $unitId): array
    {
        $this->authorize($actor, $outlet);
        $unit = StockUnit::where('public_id', $unitId)->firstOrFail();
        $product = Product::whereKey($unit->product_id)->where('outlet_id', $outlet->id)->firstOrFail();
        $imeis = DB::table('product_imeis')->where('stock_unit_id', $unit->id)->orderBy('slot_no')->pluck('imei')->all();

        return [
            'contract' => 'retail-label.v1', 'kind' => 'unit', 'outlet_id' => $outlet->public_id,
            'product_id' => $product->public_id, 'product_code' => $product->product_code, 'name' => $product->name,
            'unit_id' => $unit->public_id, 'unit_code' => $unit->unit_code, 'sale_price' => (string) $product->sale_price,
            'imeis' => $imeis, 'barcode_value' => $unit->unit_code,
            'qr_value' => 'mobist:unit:'.$unit->public_id,
        ];
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet): void
    {
        abort_unless(app(Access::class)->allows($actor, 'shop.labels', $outlet), 403);
    }
}
