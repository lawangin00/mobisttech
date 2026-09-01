<?php

return [
    'reservation_minutes' => (int) env('COMMERCE_RESERVATION_MINUTES', 20),
    'providers' => [
        // COD is collected by an authorized operator and never uses an external callback.
        'cod' => ['enabled' => true, 'merchant' => 'mobisttech', 'mode' => 'internal'],
        // Activation requires authentic contracts, credentials and provider-specific fixtures under H-02.
        'jazzcash' => ['enabled' => false, 'merchant' => '', 'mode' => 'sandbox'],
        'easypaisa' => ['enabled' => false, 'merchant' => '', 'mode' => 'sandbox'],
        'card' => ['enabled' => false, 'merchant' => '', 'mode' => 'sandbox'],
    ],
];
