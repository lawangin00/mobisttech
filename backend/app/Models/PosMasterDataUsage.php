<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosMasterDataUsage extends Model
{
    protected $guarded = [];

    public function option(): BelongsTo
    {
        return $this->belongsTo(PosMasterDataOption::class, 'master_data_option_id');
    }
}
