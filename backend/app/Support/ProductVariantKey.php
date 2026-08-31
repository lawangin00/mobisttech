<?php

namespace App\Support;

use App\Models\StockUnit;

class ProductVariantKey
{
    public static function forUnit(StockUnit $unit): string
    {
        return self::fromValues(
            $unit->color,
            $unit->condition,
            $unit->pta_status,
            $unit->carrier_lock_status,
            $unit->mdm_status,
        );
    }

    public static function fromValues(
        ?string $color,
        ?string $condition,
        ?string $ptaStatus,
        ?string $carrierLockStatus,
        ?string $mdmStatus,
    ): string {
        $groupKey = implode('|', [
            strtolower(trim((string) $color)),
            (string) $condition,
            (string) $ptaStatus,
            (string) $carrierLockStatus,
            (string) $mdmStatus,
        ]);

        return substr(hash('sha256', $groupKey), 0, 16);
    }
}
