<?php

namespace App\Models;

use App\Support\PosMasterDataRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class PosMasterDataOption extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'archived_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PosMasterDataOption $option) {
            app(PosMasterDataRegistry::class)->definition((string) $option->list_key);
        });

        static::updating(function (PosMasterDataOption $option) {
            if ($option->isDirty('list_key') || $option->isDirty('code')) {
                throw new LogicException('Master-data list keys and internal codes are immutable after creation.');
            }

            $definition = app(PosMasterDataRegistry::class)->definition((string) $option->list_key);
            if ($option->isDirty('archived_at') && $option->archived_at !== null && ! $definition['allow_archive']) {
                throw new LogicException('This protected master-data list cannot be archived through the generic lifecycle.');
            }
        });

        static::deleting(function (PosMasterDataOption $option) {
            $definition = app(PosMasterDataRegistry::class)->definition((string) $option->list_key);
            if (! $definition['allow_delete']) {
                throw new LogicException('This protected master-data list cannot be permanently deleted.');
            }
            if ($option->usages()->exists()) {
                throw new LogicException('Referenced master-data options cannot be deleted; deactivate or archive them instead.');
            }
        });
    }

    public function usages(): HasMany
    {
        return $this->hasMany(PosMasterDataUsage::class, 'master_data_option_id');
    }
}
