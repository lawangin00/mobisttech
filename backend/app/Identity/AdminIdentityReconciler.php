<?php

namespace App\Identity;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminIdentityReconciler
{
    public function map(Admin $verifier, Admin $target, array $source, array $permissions, string $credentialSha256, string $reason): array
    {
        abort_unless(app(Access::class)->allows($verifier, 'config.users-roles.manage'), 403);
        $allowedSources = ['pos.admins', 'pos.super_admins', 'website.users'];
        $qualified = ($source['repository'] ?? '').'.'.($source['table'] ?? '');
        if (! in_array($qualified, $allowedSources, true) || ! preg_match('/\A[1-9]\d*\z/', (string) ($source['primary_key'] ?? ''))) {
            throw ValidationException::withMessages(['source' => 'A supported exact legacy administrative identity is required.']);
        }
        $permissions = array_values(array_unique($permissions));
        sort($permissions);
        if (array_diff($permissions, array_keys(Admin::PERMISSIONS))) {
            throw ValidationException::withMessages(['permissions' => 'An unknown Admin permission was supplied.']);
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/', $credentialSha256) || trim($reason) === '') {
            throw ValidationException::withMessages(['mapping' => 'Credential evidence and a reconciliation reason are required.']);
        }

        return DB::transaction(function () use ($verifier, $target, $source, $permissions, $credentialSha256, $reason) {
            $identity = ['source_repository' => $source['repository'], 'source_table' => $source['table'],
                'source_primary_key' => (string) $source['primary_key']];
            $existing = DB::table('admin_identity_mappings')->where($identity)->lockForUpdate()->first();
            if ($existing) {
                $same = (int) $existing->admin_id === $target->id && hash_equals($existing->source_credential_sha256, $credentialSha256)
                    && json_decode($existing->permission_snapshot, true, flags: JSON_THROW_ON_ERROR) === $permissions;
                abort_unless($same, 409, 'The legacy identity already has a different verified mapping.');

                return ['outcome' => 'already_mapped', 'admin_id' => $target->public_id];
            }
            DB::table('admins')->where('id', $target->id)->lockForUpdate()->firstOrFail();
            DB::table('admin_identity_mappings')->insert([...$identity, 'admin_id' => $target->id,
                'permission_snapshot' => json_encode($permissions, JSON_THROW_ON_ERROR), 'source_credential_sha256' => $credentialSha256,
                'decision' => 'mapped', 'reason' => trim($reason), 'verified_by_admin_id' => $verifier->id,
                'verified_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            IdentityAudit::record('admin', $verifier->id, 'legacy_admin_identity_mapped', $qualified = $source['repository'].'.'.$source['table'].':'.$source['primary_key']);

            return ['outcome' => 'mapped', 'admin_id' => $target->public_id, 'source' => $qualified];
        });
    }
}
