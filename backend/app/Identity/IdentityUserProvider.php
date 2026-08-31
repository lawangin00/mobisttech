<?php

namespace App\Identity;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class IdentityUserProvider extends EloquentUserProvider
{
    public function validateCredentials(Authenticatable $user, #[\SensitiveParameter] array $credentials)
    {
        $hash = $user->getAuthPassword();

        return $user->usable() && in_array(password_get_info($hash)['algoName'], ['bcrypt', 'argon2i', 'argon2id'], true)
            && password_verify($credentials['password'], $hash);
    }
}
