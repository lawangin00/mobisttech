<?php

return [
    'external_delivery_enabled' => (bool) env('ENGAGEMENT_EXTERNAL_DELIVERY_ENABLED', false),
    'unsubscribe_secret' => env('ENGAGEMENT_UNSUBSCRIBE_SECRET', env('APP_KEY')),
];
