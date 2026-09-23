<?php

/**
 * Unregistered Easypaisa REST v4 MA transport configuration.
 * H-02: real provider/merchant activation needs verified callback and sandbox acceptance.
 * Keep this independent from the hosted-payment provider registry.
 */
return [
    'enabled' => env('EASYPAISA_MA_TRANSPORT_ENABLED', 'false') === 'true',
    'mode' => 'sandbox',
    'variant' => 'rest-v4-without-rsa',
    'username' => env('EASYPAISA_MA_USERNAME', ''),
    'password' => env('EASYPAISA_MA_PASSWORD', ''),
    'store_id' => env('EASYPAISA_MA_STORE_ID', ''),
    'account_num' => env('EASYPAISA_MA_ACCOUNT_NUM', ''),
];
