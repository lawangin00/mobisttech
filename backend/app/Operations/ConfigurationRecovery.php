<?php

namespace App\Operations;

use App\Backups\RecoveryManifest;
use App\Identity\Access;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ConfigurationRecovery
{
    public function __construct(private RecoveryManifest $recovery, private AuditTrail $audit) {}

    public function capture(Admin $actor): array
    {
        $this->authorize($actor);
        $manifest = [
            'contract' => 'mobisttech-config-recovery.v1',
            'key_id' => $this->recovery->keyId(),
            'schema_sha256' => $this->recovery->schemaSha256(),
            'code_sha256' => $this->recovery->codeSha256(),
            'pos_revisions' => $this->revisions('pos_configuration_revisions'),
            'site_revisions' => $this->revisions('site_configuration_revisions'),
            'pos_settings' => $this->settings('pos_settings'),
            'site_settings' => $this->settings('site_settings'),
            'site_secrets' => $this->siteSecrets(),
            'integrations' => $this->integrations(),
        ];
        $manifestSha = $this->recovery->sha($manifest);
        $publicId = (string) Str::uuid();
        DB::table('configuration_recovery_snapshots')->insert([
            'public_id' => $publicId, 'scope' => 'shared', 'key_id' => $manifest['key_id'],
            'schema_sha256' => $manifest['schema_sha256'], 'code_sha256' => $manifest['code_sha256'],
            'manifest_sha256' => $manifestSha, 'manifest' => json_encode($manifest, JSON_THROW_ON_ERROR),
            'created_by_admin_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->audit->admin($actor, 'configuration_recovery_captured', 'SYSTEM', '/recovery/configuration', ['snapshot_id' => $publicId]);

        return $this->safe($publicId, $manifest, $manifestSha, null);
    }

    public function verify(Admin $actor, string $publicId, string $providedKeyId): array
    {
        $this->authorize($actor);
        $row = DB::table('configuration_recovery_snapshots')->where('public_id', $publicId)->firstOrFail();
        $manifest = json_decode($row->manifest, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($row->manifest_sha256, $this->recovery->sha($manifest)), 409, 'Configuration recovery manifest is invalid.');
        abort_unless(hash_equals($row->key_id, $providedKeyId) && hash_equals($row->key_id, $this->recovery->keyId()), 409, 'Recovery key identity does not match.');
        abort_unless(hash_equals($row->schema_sha256, $this->recovery->schemaSha256()), 409, 'Target schema differs from the recovery snapshot.');
        abort_unless(hash_equals($row->code_sha256, $this->recovery->codeSha256()), 409, 'Target code/lock state differs from the recovery snapshot.');
        abort_unless($this->sameSecretState($manifest['site_secrets'] ?? [], $this->siteSecrets()), 409, 'Secret state changed after the recovery snapshot.');
        abort_unless($this->sameSecretState($manifest['integrations'] ?? [], $this->integrations()), 409, 'Integration credential state changed after the recovery snapshot.');

        DB::table('configuration_recovery_snapshots')->where('id', $row->id)->update(['verified_at' => now(), 'updated_at' => now()]);
        $this->audit->admin($actor, 'configuration_recovery_verified', 'SYSTEM', '/recovery/configuration', ['snapshot_id' => $publicId]);

        return $this->safe($publicId, $manifest, $row->manifest_sha256, now()->format('Y-m-d\TH:i:s.u\Z'));
    }

    private function revisions(string $table): array
    {
        return DB::table($table)->where('state', 'published')->orderBy('domain')->orderBy('version')->get()
            ->map(fn ($row) => [
                'domain' => $row->domain,
                'version' => (int) $row->version,
                'snapshot' => json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR),
            ])->all();
    }

    private function settings(string $table): array
    {
        return DB::table($table)->orderBy('key')->get()->map(fn ($row) => [
            'key' => $row->key,
            'value' => $row->value,
        ])->all();
    }

    private function siteSecrets(): array
    {
        return DB::table('site_secret_settings')->orderBy('key')->get()->map(fn ($row) => [
            'key' => $row->key,
            'version' => (int) $row->version,
            'rotated_at' => $row->rotated_at,
            'ciphertext_sha256' => hash('sha256', (string) $row->ciphertext),
        ])->all();
    }

    private function integrations(): array
    {
        return DB::table('integration_connections')->orderBy('provider')->get()->map(fn ($row) => [
            'provider' => $row->provider,
            'version' => (int) $row->version,
            'status' => $row->status,
            'account' => $row->account,
            'scopes' => $row->scopes ? json_decode($row->scopes, true, flags: JSON_THROW_ON_ERROR) : null,
            'configuration' => $row->configuration ? json_decode($row->configuration, true, flags: JSON_THROW_ON_ERROR) : null,
            'credential_sha256' => $row->encrypted_credentials ? hash('sha256', $row->encrypted_credentials) : null,
        ])->all();
    }

    private function sameSecretState(array $expected, array $actual): bool
    {
        return hash_equals($this->recovery->sha($expected), $this->recovery->sha($actual));
    }

    private function safe(string $publicId, array $manifest, string $manifestSha, ?string $verifiedAt): array
    {
        return [
            'public_id' => $publicId,
            'scope' => 'shared',
            'key_id' => $manifest['key_id'],
            'schema_sha256' => $manifest['schema_sha256'],
            'code_sha256' => $manifest['code_sha256'],
            'manifest_sha256' => $manifestSha,
            'pos_domains' => array_values(array_unique(array_column($manifest['pos_revisions'], 'domain'))),
            'site_domains' => array_values(array_unique(array_column($manifest['site_revisions'], 'domain'))),
            'pos_setting_count' => count($manifest['pos_settings']),
            'site_setting_count' => count($manifest['site_settings']),
            'secret_reference_count' => count($manifest['site_secrets']) + count($manifest['integrations']),
            'verified_at' => $verifiedAt,
        ];
    }

    private function authorize(Admin $actor): void
    {
        abort_unless(app(Access::class)->allows($actor, 'config.integrations-backups.manage'), 403);
    }
}
