<?php

namespace App\Identity;

use Illuminate\Support\Facades\DB;

final class IdentityAudit
{
    public static function record(string $realm, ?int $id, string $action, ?string $reference = null): void
    {
        DB::table('identity_audit_events')->insert(['realm' => $realm, 'account_id' => $id, 'action' => $action,
            'reference' => $reference, 'created_at' => now()]);
    }
}
