<?php

namespace App\Models;

use App\Identity\IdentityAccount;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

class SuperAdmin extends IdentityAccount
{
    use HasFactory, Notifiable;

    protected $guarded = ['*'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'auth_version' => 'integer',
            'archived_at' => 'datetime',
            'business_identifiers' => 'array',
        ];
    }
}
