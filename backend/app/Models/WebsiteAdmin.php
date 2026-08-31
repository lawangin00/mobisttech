<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

class WebsiteAdmin extends User
{
    protected $table = 'users';

    protected static function booted(): void
    {
        parent::booted();
        static::addGlobalScope('website_admin', fn (Builder $query) => $query->where('is_admin', true)->whereNull('archived_at'));
    }
}
