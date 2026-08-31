<?php

namespace App\Identity;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

abstract class IdentityAccount extends Authenticatable
{
    protected static function booted(): void
    {
        static::creating(function ($account) {
            $account->public_id ??= (string) Str::uuid();
        });
    }

    public function usable(): bool
    {
        return $this->archived_at === null && ! (bool) $this->getAttribute('status');
    }
}
