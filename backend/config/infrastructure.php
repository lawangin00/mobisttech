<?php

return [
    // Optional derived cache only. Database queues and sessions do not depend on Redis.
    'derived_cache_store' => env('DERIVED_CACHE_STORE', 'file'),
    'derived_cache_ttl' => 60,
    'private_disk' => env('PRIVATE_OBJECT_DISK', 'local'),
];
