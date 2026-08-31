<?php

return [
    'customer_origin' => env('IDENTITY_CUSTOMER_ORIGIN', 'http://127.0.0.1:13000'),
    'admin_origin' => env('IDENTITY_ADMIN_ORIGIN', 'http://127.0.0.1:18080'),
    // Delivery stays disabled until separately configured; never log reset links.
    'recovery_delivery_enabled' => env('IDENTITY_RECOVERY_DELIVERY_ENABLED', false),
];
