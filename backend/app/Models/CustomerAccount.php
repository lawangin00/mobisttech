<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class CustomerAccount extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('customer', fn (Builder $query) => $query->where('is_admin', false)->whereNull('archived_at'));
    }
}
