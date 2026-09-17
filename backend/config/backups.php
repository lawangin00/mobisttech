<?php

return [
    'external_jobs_enabled' => (bool) env('BACKUP_EXTERNAL_JOBS_ENABLED', false),
    'key_id' => env('BACKUP_KEY_ID'),
    'namespace' => 'mobiST Tech/Backups/',
    'rehearsal_environments' => ['local', 'testing'],
];
