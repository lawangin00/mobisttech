<?php

namespace App\Support;

use App\Models\Product;
use App\Models\StockUnit;
use LogicException;

final class BusinessIdentifier
{
    public static function outlet(int|string|null $code, int $shopId): string
    {
        $code = trim((string) $code);

        if ($code !== '') {
            if (! preg_match('/\A\d{3}\z/', $code) || $code === '000') {
                throw new LogicException('OUTLET3 requires an immutable code from 001 through 999.');
            }

            return $code;
        }

        if ($shopId < 1 || $shopId > 999) {
            throw new LogicException('OUTLET3 supports outlet numbers 001 through 999.');
        }

        return sprintf('%03d', $shopId);
    }

    public static function product(Product $product): string
    {
        $outlet = self::outlet($product->shop?->outlet_code, (int) $product->outlet_id);

        return sprintf(
            'MST-%s-%s-%06d',
            self::category($product->category),
            $outlet,
            $product->getKey()
        );
    }

    public static function unit(StockUnit $unit): string
    {
        $product = $unit->product;
        $productCode = $product?->product_code ?: ($product ? self::product($product) : null);

        if (! $productCode) {
            throw new LogicException('A physical unit requires a product business identifier.');
        }

        return sprintf('%s-U%04d', $productCode, $unit->unit_no);
    }

    private static function category(?string $category): string
    {
        return match ($category) {
            'tablet' => 'TAB',
            'accessory' => 'ACC',
            'mobile_phone' => 'MOB',
            default => throw new LogicException('Unsupported protected category.'),
        };
    }
}
