<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\CustomerAccount;
use Illuminate\Support\Facades\DB;

final class IdentityAudit
{
    public static function record(string $realm, ?int $id, string $action, ?string $reference = null, ?int $outletId = null): void
    {
        $actor = $id ? ($realm === 'admin' ? Admin::find($id) : CustomerAccount::find($id)) : null;
        DB::table('identity_audit_events')->insert(['realm' => $realm, 'account_id' => $id, 'action' => $action,
            'outlet_id' => $outletId, 'reference' => $reference, 'actor_name_snapshot' => $actor?->name,
            'actor_role_snapshot' => $actor instanceof Admin ? $actor->roleSnapshot() : ($actor ? 'Customer' : null),
            'created_at' => now()]);
    }
}
