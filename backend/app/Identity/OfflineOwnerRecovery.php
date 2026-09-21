<?php

namespace App\Identity;

use App\Models\Admin;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Account-bound, single-use OFFLINE owner recovery. No external messages or actual issuance by default. */
final class OfflineOwnerRecovery
{
    private const CODE_COUNT = 8;

    public function rotate(Admin $actor, string $currentPassword): array
    {
        $this->enabled();

        return DB::transaction(function () use ($actor, $currentPassword) {
            $owner = Admin::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->isBoundOwner($owner), 403, 'Bound protected owner required.');
            abort_unless(Hash::check($currentPassword, $owner->password), 403, 'Confirm the current password.');
            $batch = (string) Str::uuid();
            DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
                ->whereNull('used_at')->whereNull('revoked_at')->update(['revoked_at' => now()]);
            $codes = [];
            for ($index = 0; $index < self::CODE_COUNT; $index++) {
                $digits = strtoupper(bin2hex(random_bytes(18)));
                $code = implode('-', str_split($digits, 6));
                DB::table('owner_offline_recovery_codes')->insert([
                    'admin_id' => $owner->id, 'batch_id' => $batch,
                    'code_digest' => $this->digest($code), 'issued_at' => now(),
                ]);
                $codes[] = $code;
            }
            IdentityAudit::record('admin', $owner->id, 'owner_offline_codes_rotated', 'batch:'.$batch);

            return ['codes' => $codes, 'count' => self::CODE_COUNT,
                'instruction' => 'Store these one-time codes offline; they cannot be viewed again.'];
        }, 3);
    }

    public function redeem(string $email, string $code, string $newPassword): void
    {
        $this->enabled();
        try {
            // Reject malformed codes without looking up account membership or showing any secret.
            $normalized = strtoupper(trim($code));
            if (! preg_match('/\A(?:[A-F0-9]{6}-){5}[A-F0-9]{6}\z/D', $normalized)) {
                $this->invalidCode();
            }
            DB::transaction(function () use ($email, $normalized, $newPassword) {
                $owner = Admin::where('public_id', config('identity.offline_owner_recovery.owner_admin_public_id'))
                    ->lockForUpdate()->first();
                if (! $owner || ! $this->isBoundOwner($owner)
                    || ! hash_equals(strtolower((string) $owner->email), strtolower(trim($email)))) {
                    $this->invalidCode();
                }
                $record = DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
                    ->where('code_digest', $this->digest($normalized))
                    ->whereNull('used_at')->whereNull('revoked_at')->lockForUpdate()->first();
                if (! $record) {
                    $this->invalidCode();
                }
                DB::table('owner_offline_recovery_codes')->where('id', $record->id)
                    ->update(['used_at' => now()]);
                $owner->forceFill(['password' => Hash::make($newPassword),
                    'remember_token' => Str::random(60), 'auth_version' => (int) $owner->auth_version + 1])->save();
                DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $owner->id)
                    ->update(['revoked_at' => now()]);
                DB::table('admin_password_reset_tokens')->where('email', $owner->email)->delete();
                IdentityAudit::record('admin', $owner->id, 'owner_offline_code_consumed', 'batch:'.$record->batch_id);
                event(new PasswordReset($owner));
            }, 3);
        } catch (ValidationException $error) {
            // Failed attempts are audited outside the rolled-back redemption transaction;
            // never log submitted email, code, password or an account lookup result.
            IdentityAudit::record('admin', null, 'owner_offline_recovery_denied');
            throw $error;
        }
    }

    private function isBoundOwner(Admin $actor): bool
    {
        $id = config('identity.offline_owner_recovery.owner_admin_public_id');

        return is_string($id) && Str::isUuid($id) && $actor->public_id === $id
            && $actor->usable() && $actor->hasPermission('team-members.full-access.assign')
            && $actor->hasPermission('admin.business-profile.manage')
            && $actor->roles()->where('roles.is_protected', true)
                ->whereNull('roles.archived_at')->exists();
    }

    private function enabled(): void
    {
        abort_unless(config('identity.offline_owner_recovery.enabled') === true
            && Str::isUuid((string) config('identity.offline_owner_recovery.owner_admin_public_id')),
            503, 'Offline owner recovery is not enrolled.');
    }

    private function digest(string $code): string
    {
        // 144 bits of CSPRNG entropy; no reversibly encrypted or plaintext code is stored.
        return hash('sha256', 'mobisttech-offline-owner-v1|'.$code);
    }

    private function invalidCode(): never
    {
        throw ValidationException::withMessages(['code' => 'Invalid or already used recovery code.']);
    }
}
