<?php

return [
    'customer_origin' => env('IDENTITY_CUSTOMER_ORIGIN', 'http://127.0.0.1:13000'),
    'admin_origin' => env('IDENTITY_ADMIN_ORIGIN', 'http://127.0.0.1:18080'),
    // Delivery stays disabled until separately configured; never log reset links.
    'recovery_delivery_enabled' => env('IDENTITY_RECOVERY_DELIVERY_ENABLED', false),
    'sessions' => [
        'admin' => ['inactivity_minutes' => 30, 'warning_minutes' => 5, 'remember_minutes' => 0, 'recent_auth_minutes' => 10],
        'customer' => ['inactivity_minutes' => 120, 'warning_minutes' => 0, 'remember_minutes' => 43200, 'recent_auth_minutes' => 10],
    ],
];
