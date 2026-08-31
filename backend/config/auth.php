<?php

use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\SuperAdmin;
use App\Models\WebsiteAdmin;

return [
    'defaults' => ['guard' => 'customer', 'passwords' => 'customer'],
    'guards' => [
        'customer' => ['driver' => 'session', 'provider' => 'customers'],
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
        'superadmin' => ['driver' => 'session', 'provider' => 'superadmins'],
        'website_admin' => ['driver' => 'session', 'provider' => 'website_admins'],
    ],
    'providers' => [
        'customers' => ['driver' => 'identity', 'model' => CustomerAccount::class],
        'admins' => ['driver' => 'identity', 'model' => Admin::class],
        'superadmins' => ['driver' => 'identity', 'model' => SuperAdmin::class],
        'website_admins' => ['driver' => 'identity', 'model' => WebsiteAdmin::class],
    ],
    'passwords' => [
        'customer' => ['provider' => 'customers', 'table' => 'password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'admin' => ['provider' => 'admins', 'table' => 'admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'superadmin' => ['provider' => 'superadmins', 'table' => 'super_admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
        'website_admin' => ['provider' => 'website_admins', 'table' => 'site_admin_password_reset_tokens', 'expire' => 60, 'throttle' => 60],
    ],
    'password_timeout' => 10800,
];
