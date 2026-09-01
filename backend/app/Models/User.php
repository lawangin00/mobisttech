<?php

namespace App\Models;

use App\Identity\IdentityAccount;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'mobile', 'profile_photo_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends IdentityAccount
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'auth_version' => 'integer',
            'archived_at' => 'datetime',
        ];
    }
}
