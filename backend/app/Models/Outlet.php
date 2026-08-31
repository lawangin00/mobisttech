<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// An outlet is a business entity, never an authentication provider.
class Outlet extends Model
{
    protected $guarded = ['*'];

    protected $hidden = ['legacy_password', 'legacy_remember_token'];

    protected function casts(): array
    {
        return ['status' => 'boolean', 'archived_at' => 'datetime'];
    }
}
