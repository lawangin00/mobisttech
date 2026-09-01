<?php

use App\Models\Admin;
use App\Models\CustomerAccount;

return [
    'defaults' => ['guard' => 'customer', 'passwords' => 'customer'],
    'guards' => [
        'customer' => ['driver' => 'session', 'provider' => 'customers'],
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
    ],
    'providers' => [
        'customers' => ['driver' => 'identity', 'model' => CustomerAccount::class],
        'admins' => ['driver' => 'identity', 'model' => Admin::class],
    ],
    'passwords' => [
        'customer' => ['provider' => 'customers', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'admin' => ['provider' => 'admins', 'table' => 'admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    ],
    'password_timeout' => 10800,
];
