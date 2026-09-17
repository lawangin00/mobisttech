<?php

return [
    // Destructive reset execution is accepted only on the disposable acceptance target.
    // Production/cutover enablement remains a separate HOLD-controlled authorization.
    'execution_environments' => ['testing'],
    'preview_ttl_minutes' => 10,
    'confirmation' => [
        'transactional' => 'TRANSACTIONAL DATA RESET',
        'business' => 'BUSINESS DATA RESET',
        'factory' => 'FACTORY RESET',
    ],
];
