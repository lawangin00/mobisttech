<?php

namespace App\Models;

use App\Support\BusinessIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;

    public const CATEGORY_LABELS = [
        'mobile_phone' => 'Mobiles',
        'tablet' => 'Tablets',
        'accessory' => 'Accessories',
    ];

    public const SIM_LABELS = [
        'single_physical' => 'Single SIM',
        'dual_physical' => 'Dual SIM',
        'physical_esim' => 'SIM + eSIM',
        'dual_physical_esim' => 'Dual SIM + eSIM',
        'esim_only' => 'eSIM Only',
        'not_applicable' => 'N/A',
        'unknown' => 'Unknown SIM',
    ];

    protected $guarded = ['*'];

    protected $casts = ['track_imei' => 'boolean', 'isDeleted' => 'boolean', 'price' => 'decimal:2', 'purchase_price' => 'decimal:2', 'sale_price' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(fn (Product $product) => $product->public_id ??= (string) Str::uuid());
        static::created(function (Product $product) {
            if (blank($product->product_code)) {
                $product->forceFill([
                    'product_code' => BusinessIdentifier::product($product),
                ])->saveQuietly();
            }
        });

        static::updating(function (Product $product) {
            if ($product->getOriginal('product_code') && $product->isDirty('product_code')) {
                throw new \LogicException('Existing product_code is immutable.');
            }
        });
    }

    public function shop()
    {
        return $this->belongsTo(Outlet::class, 'outlet_id');
    }

    public function categoryMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'category_master_data_id');
    }

    public function subcategoryMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'subcategory_master_data_id');
    }

    public function brandMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'brand_master_data_id');
    }

    public function ramMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'ram_master_data_id');
    }

    public function storageMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'storage_master_data_id');
    }

    public function simMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'sim_master_data_id');
    }

    public function categoryDisplay(): string
    {
        return $this->categoryMasterOption?->label
            ?: (self::CATEGORY_LABELS[$this->category] ?? (string) $this->category);
    }

    public function categoryVisible(): bool
    {
        if ($this->categoryMasterOption) {
            return $this->categoryMasterOption->archived_at === null && (bool) $this->categoryMasterOption->is_active;
        }

        return true;
    }

    public function subcategoryDisplay(): ?string
    {
        return $this->subcategoryMasterOption?->label;
    }

    public function subcategoryCode(): ?string
    {
        return $this->subcategoryMasterOption?->code;
    }

    public function brandDisplay(): ?string
    {
        return $this->brandMasterOption?->label ?: $this->brand;
    }

    public function simDisplay(): ?string
    {
        if ($this->simMasterOption?->label) {
            return $this->simMasterOption->label;
        }

        $value = $this->sim_configuration;
        if ($value === null || $value === '') {
            return null;
        }

        return self::SIM_LABELS[$value] ?? $value;
    }

    /**
     * Relationship with Product IMEIs
     */
    public function stockUnits()
    {
        return $this->hasMany(StockUnit::class);
    }

    public function inStockUnits()
    {
        return $this->hasMany(StockUnit::class)->where('status', 'in_stock');
    }

    public function requiredImeiSlots(): int
    {
        if (! $this->track_imei) {
            return 0;
        }

        $managedSlots = (int) data_get($this->simMasterOption?->metadata, 'imei_slots');
        if (in_array($managedSlots, [1, 2], true)) {
            return $managedSlots;
        }

        return in_array($this->sim_configuration, [
            'dual_physical',
            'physical_esim',
            'dual_physical_esim',
        ], true) ? 2 : 1;
    }

    public static function generatedDeviceName(
        ?string $brand,
        ?string $model,
        int|string|null $ramGb,
        int|string|null $storageGb,
        ?string $simConfiguration
    ): string {
        $brandModel = trim(implode(' ', array_filter([
            trim((string) $brand),
            trim((string) $model),
        ])));
        $variant = ($ramGb !== null && $ramGb !== '' && $storageGb !== null && $storageGb !== '')
            ? ((int) $ramGb).'/'.((int) $storageGb)
            : '';
        $sim = self::SIM_LABELS[$simConfiguration ?? 'unknown'] ?? 'Unknown SIM';

        return implode(' | ', array_filter([$brandModel, $variant, $sim]));
    }
}
