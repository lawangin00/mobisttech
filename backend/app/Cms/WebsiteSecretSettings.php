<?php

namespace App\Cms;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Allowlisted Website provider credential envelopes; never a provider-activation switch. */
final class WebsiteSecretSettings
{
    private const DEFINITIONS = [
        'payments.jazzcash.api_secret' => 'JazzCash API secret',
        'payments.jazzcash.integrity_salt' => 'JazzCash integrity salt',
        'payments.easypaisa.api_secret' => 'Easypaisa API secret',
        'payments.easypaisa.hash_key' => 'Easypaisa hash key',
        'payments.card.api_secret' => 'Card provider API secret',
        'payments.card.webhook_secret' => 'Card provider webhook secret',
    ];

    public function metadata(Admin $actor): array
    {
        $this->authorize($actor);
        $records = DB::table('site_secret_settings')->whereIn('key', array_keys(self::DEFINITIONS))
            ->get()->keyBy('key');

        return collect(self::DEFINITIONS)->map(function (string $label, string $key) use ($records): array {
            $row = $records->get($key);
            if ($row) {
                $this->assertActiveKey($row->ciphertext);
            }

            return [
                'key' => $key, 'label' => $label, 'configured' => $row !== null,
                'masked_value' => $row ? '********' : null,
                'version' => $row ? (int) $row->version : 0,
                'rotated_at' => $row?->rotated_at,
            ];
        })->values()->all();
    }

    public function replace(Admin $actor, string $key, string $value): array
    {
        $this->authorize($actor);
        abort_unless(isset(self::DEFINITIONS[$key]), 404, 'Unknown Website credential.');
        if ($value === '' || trim($value) === '' || strlen($value) > 8192) {
            throw ValidationException::withMessages(['value' => 'Credential must contain 1 to 8192 nonblank bytes.']);
        }

        DB::transaction(function () use ($actor, $key, $value): void {
            $old = DB::table('site_secret_settings')->where('key', $key)->lockForUpdate()->first();
            if ($old) {
                // Never overwrite ciphertext whose active encryption key cannot open it.
                $this->assertActiveKey($old->ciphertext);
            }
            $next = [
                'ciphertext' => Crypt::encryptString($value), 'version' => ($old ? (int) $old->version : 0) + 1,
                'rotated_at' => $old ? now() : null, 'updated_at' => now(),
            ];
            if ($old) {
                DB::table('site_secret_settings')->where('id', $old->id)->update($next);
            } else {
                DB::table('site_secret_settings')->insert(['key' => $key, 'created_at' => now()] + $next);
            }
            // Audit only an allowlisted key identity and actor, never request or secret contents.
            IdentityAudit::record('admin', $actor->id, 'website_credential_replaced', $key);
        });

        return collect($this->metadata($actor))->firstWhere('key', $key);
    }

    private function assertActiveKey(string $ciphertext): void
    {
        try {
            Crypt::decryptString($ciphertext);
        } catch (Throwable) {
            abort(409, 'Website credential cannot be opened by the active encryption key.');
        }
    }

    private function authorize(Admin $actor): void
    {
        abort_unless(app(Access::class)->allows($actor, 'website.payment-credentials.manage'), 403);
    }
}
