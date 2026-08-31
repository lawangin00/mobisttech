<?php

namespace App\Catalog;

use App\Models\Product;
use App\Models\StockUnit;
use App\Support\ProductVariantKey;

// Explicit metadata DTOs, not an availability/checkout API; stock authority is a later service.
final class ProductProjection
{
    public static function publicMetadata(Product $product): array
    {
        return ['id' => $product->public_id, 'product_code' => $product->product_code, 'name' => $product->name,
            'category' => $product->category, 'category_label' => $product->categoryDisplay(),
            'subcategory_code' => $product->subcategoryCode(), 'subcategory_label' => $product->subcategoryDisplay(),
            'category_visible' => $product->categoryVisible(), 'brand' => $product->brandDisplay(), 'model' => $product->model,
            'ram_gb' => $product->ram_gb, 'storage_gb' => $product->storage_gb,
            'sim_configuration' => $product->sim_configuration, 'sim_label' => $product->simDisplay(),
            'sale_price' => $product->sale_price];
    }

    public static function variant(StockUnit $unit): array
    {
        $unit->loadMissing(['colorMasterOption', 'conditionMasterOption', 'ptaStatusMasterOption', 'carrierLockMasterOption', 'mdmStatusMasterOption']);

        return ['key' => ProductVariantKey::forUnit($unit), 'color' => $unit->colorDisplay(), 'color_hex' => $unit->resolvedColorHex(),
            'condition' => $unit->conditionDisplay(), 'pta_status' => $unit->ptaStatusDisplay(),
            'carrier_lock_status' => $unit->carrierLockStatusDisplay(), 'mdm_status' => $unit->mdmStatusDisplay()];
    }
}
