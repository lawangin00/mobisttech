<?php

namespace App\Models;

use App\Support\BusinessIdentifier;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StockUnit extends Model
{
    use HasFactory;

    public const COLOR_OPTIONS = [
        'Black' => '#111111',
        'White' => '#F8FAFC',
        'Blue' => '#2563EB',
        'Light Blue' => '#9CCAF0',
        'Dark Blue' => '#1E3A8A',
        'Sky Blue' => '#7DD3FC',
        'Navy' => '#172554',
        'Green' => '#16A34A',
        'Mint Green' => '#A7F3D0',
        'Dark Green' => '#166534',
        'Teal' => '#0F766E',
        'Red' => '#DC2626',
        'Product Red' => '#C8102E',
        'Pink' => '#EC4899',
        'Rose' => '#F43F5E',
        'Purple' => '#7C3AED',
        'Violet' => '#8B5CF6',
        'Lavender' => '#C4B5FD',
        'Yellow' => '#EAB308',
        'Orange' => '#F97316',
        'Gold' => '#D4A72C',
        'Silver' => '#CBD5E1',
        'Gray' => '#94A3B8',
        'Grey' => '#94A3B8',
        'Graphite' => '#374151',
        'Space Gray' => '#6B7280',
        'Midnight' => '#1F2937',
        'Starlight' => '#E8E2D7',
        'Cream' => '#F5F0DF',
        'Beige' => '#D6C7AD',
        'Brown' => '#92400E',
        'Bronze' => '#A16207',
        'Titanium' => '#A79C8E',
        'Natural Titanium' => '#B9B2A7',
        'Desert Titanium' => '#C2B39D',
        'Black Titanium' => '#353535',
        'White Titanium' => '#D7D4CF',
        'Blue Titanium' => '#6E8394',
        'Deep Purple' => '#5B4B8A',
        'Sierra Blue' => '#9BB5CE',
        'Pacific Blue' => '#2F5D78',
        'Alpine Green' => '#4F6457',
        'Ultramarine' => '#5167C9',
        'Coral' => '#FF7F6A',
        'Aqua' => '#22D3EE',
        'Cyan' => '#0891B2',
        'Lime' => '#84CC16',
    ];

    public const CONDITIONS = [
        'unknown' => 'Unknown',
        'brand_new' => 'Brand New',
        'open_box' => 'Open Box',
        'used' => 'Used / Kit',
        'refurbished' => 'Refurbished',
    ];

    public const PTA_STATUSES = [
        'pta_approved' => 'PTA Approved',
        'non_pta' => 'Non-PTA',
        'patch_approved' => 'Patch Approved',
        'cpid_server_approved' => 'CPID / Server Approved',
        'not_applicable' => 'N/A',
        'unknown' => 'Unknown',
    ];

    public const CARRIER_LOCK_STATUSES = [
        'factory_unlocked' => 'Factory Unlocked',
        'jv_carrier_locked' => 'JV / Carrier Locked',
        'not_applicable' => 'N/A',
        'unknown' => 'Unknown',
    ];

    public const MDM_STATUSES = [
        'no_mdm' => 'No MDM',
        'mdm' => 'MDM',
        'not_applicable' => 'N/A',
        'unknown' => 'Unknown',
    ];

    public const STOCK_STATUSES = [
        'in_stock' => 'In Stock',
        'sold' => 'Sold',
        'damaged' => 'Damaged',
        'lost' => 'Lost',
        'adjusted_out' => 'Adjusted Out',
    ];

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::creating(fn (StockUnit $unit) => $unit->public_id ??= (string) Str::uuid());
        static::created(function (StockUnit $unit) {
            if (blank($unit->unit_code)) {
                $unit->forceFill([
                    'unit_code' => BusinessIdentifier::unit($unit),
                ])->saveQuietly();
            }
        });

        static::updating(function (StockUnit $unit) {
            if ($unit->getOriginal('unit_code') && $unit->isDirty('unit_code')) {
                throw new \LogicException('Existing unit_code is immutable.');
            }
        });
    }

    protected $casts = [
        'unit_no' => 'integer',
        'purchase_price' => 'decimal:2',
        'sold_at' => 'datetime',
    ];

    public static function colorHex(?string $color): ?string
    {
        $color = trim((string) $color);

        if ($color === '') {
            return null;
        }

        foreach (self::COLOR_OPTIONS as $label => $hex) {
            if (strcasecmp($label, $color) === 0) {
                return $hex;
            }
        }

        $normalized = strtolower($color);
        $fallbackTokens = [
            'black' => '#111111',
            'white' => '#F8FAFC',
            'navy' => '#172554',
            'blue' => '#2563EB',
            'green' => '#16A34A',
            'teal' => '#0F766E',
            'red' => '#DC2626',
            'pink' => '#EC4899',
            'purple' => '#7C3AED',
            'violet' => '#8B5CF6',
            'yellow' => '#EAB308',
            'orange' => '#F97316',
            'gold' => '#D4A72C',
            'silver' => '#CBD5E1',
            'gray' => '#94A3B8',
            'grey' => '#94A3B8',
            'graphite' => '#374151',
            'cream' => '#F5F0DF',
            'beige' => '#D6C7AD',
            'brown' => '#92400E',
            'bronze' => '#A16207',
            'titanium' => '#A79C8E',
            'coral' => '#FF7F6A',
            'aqua' => '#22D3EE',
            'cyan' => '#0891B2',
            'lime' => '#84CC16',
        ];

        foreach ($fallbackTokens as $token => $hex) {
            if (str_contains($normalized, $token)) {
                return $hex;
            }
        }

        return '#D8DEE9';
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function colorMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'color_master_data_id');
    }

    public function colorDisplay(): ?string
    {
        return $this->colorMasterOption?->label ?: $this->color;
    }

    public function resolvedColorHex(): ?string
    {
        $managedHex = strtoupper(trim((string) data_get($this->colorMasterOption?->metadata, 'hex')));
        if (preg_match('/\A#[0-9A-F]{6}\z/', $managedHex)) {
            return $managedHex;
        }

        return self::colorHex($this->color);
    }

    public function conditionMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'condition_master_data_id');
    }

    public function ptaStatusMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'pta_status_master_data_id');
    }

    public function carrierLockMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'carrier_lock_master_data_id');
    }

    public function mdmStatusMasterOption()
    {
        return $this->belongsTo(PosMasterDataOption::class, 'mdm_status_master_data_id');
    }

    public function conditionDisplay(): ?string
    {
        return $this->managedStatusDisplay('conditionMasterOption', $this->condition, self::CONDITIONS);
    }

    public function ptaStatusDisplay(): ?string
    {
        return $this->managedStatusDisplay('ptaStatusMasterOption', $this->pta_status, self::PTA_STATUSES);
    }

    public function carrierLockStatusDisplay(): ?string
    {
        return $this->managedStatusDisplay('carrierLockMasterOption', $this->carrier_lock_status, self::CARRIER_LOCK_STATUSES);
    }

    public function mdmStatusDisplay(): ?string
    {
        return $this->managedStatusDisplay('mdmStatusMasterOption', $this->mdm_status, self::MDM_STATUSES);
    }

    private function managedStatusDisplay(string $relation, ?string $rawCode, array $fallbacks): ?string
    {
        if ($this->relationLoaded($relation)) {
            $label = $this->getRelation($relation)?->label;
            if ($label) {
                return $label;
            }
        }

        if ($rawCode === null || $rawCode === '') {
            return null;
        }

        return $fallbacks[$rawCode] ?? $rawCode;
    }
}
