<?php

namespace App\Integrations;

use App\Business\BusinessProfile;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class IntegrationManager
{
    public function __construct(private GoogleOAuthClient $oauth, private GmailApi $gmail, private RcloneGateway $rclone, private BusinessProfile $businessProfile) {}

    public function statuses(Admin $actor): array
    {
        $this->authorize($actor);

        return DB::table('integration_connections')->orderBy('provider')->get()->map(fn ($row) => $this->safe($row))->all();
    }

    public function connect(Admin $actor, string $provider): array
    {
        $this->authorize($actor);
        $this->provider($provider);
        abort_unless((bool) config('services.google.enabled'), 409, 'External integrations are disabled.');
        $business = $this->businessProfile->current();
        if ($provider === 'google_drive' && $this->rclone->detectExisting()) {
            $connection = DB::table('integration_connections')->where('provider', $provider)->firstOrFail();
            DB::table('integration_connections')->where('id', $connection->id)->update(['status' => 'connected', 'account' => $business['business_email'],
                'configuration' => json_encode(['remote' => RcloneGateway::REMOTE, 'namespace' => 'mobiST Tech/Backups/', 'managed' => false], JSON_THROW_ON_ERROR),
                'authorized_by_admin_id' => $actor->id, 'authorized_at' => now(), 'last_success_at' => now(), 'last_error_summary' => null, 'version' => $connection->version + 1, 'updated_at' => now()]);
            $this->event($provider, 'connect_existing', 'connected', $actor, 'Validated existing mobisttech-drive remote.');

            return ['status' => $this->safe(DB::table('integration_connections')->where('provider', $provider)->firstOrFail())];
        }
        $state = Str::random(64);
        $verifier = rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        DB::transaction(function () use ($actor, $provider, $state, $verifier) {
            DB::table('integration_oauth_states')->where('expires_at', '<', now())->delete();
            DB::table('integration_oauth_states')->insert(['provider' => $provider, 'state_sha256' => hash('sha256', $state),
                'encrypted_code_verifier' => Crypt::encryptString($verifier), 'admin_id' => $actor->id, 'expires_at' => now()->addMinutes(10),
                'created_at' => now(), 'updated_at' => now()]);
            DB::table('integration_connections')->where('provider', $provider)->update(['status' => 'connecting', 'last_error_summary' => null, 'updated_at' => now()]);
        });
        $this->event($provider, 'connect_started', 'connecting', $actor, null);

        return ['authorization_url' => $this->oauth->authorizationUrl($provider, $state, $challenge)];
    }

    public function callback(Admin $actor, string $provider, array $input): array
    {
        $this->authorize($actor);
        $this->provider($provider);
        if (array_diff(array_keys($input), ['state', 'code', 'error']) || ! is_string($input['state'] ?? null)) {
            throw ValidationException::withMessages(['oauth' => 'Invalid OAuth callback.']);
        }
        if (isset($input['error'])) {
            $this->fail($provider, $actor, 'Google authorization was not completed.');
            throw ValidationException::withMessages(['oauth' => 'Google authorization was not completed.']);
        }
        if (! is_string($input['code'] ?? null) || strlen($input['code']) > 4096) {
            throw ValidationException::withMessages(['oauth' => 'Invalid OAuth authorization code.']);
        }
        $state = DB::transaction(function () use ($actor, $provider, $input) {
            $row = DB::table('integration_oauth_states')->where('state_sha256', hash('sha256', $input['state']))->lockForUpdate()->first();
            abort_unless($row && $row->provider === $provider && (int) $row->admin_id === $actor->id && ! $row->consumed_at && now()->lt($row->expires_at), 422, 'OAuth state is invalid or expired.');
            DB::table('integration_oauth_states')->where('id', $row->id)->update(['consumed_at' => now(), 'updated_at' => now()]);

            return $row;
        });
        try {
            $tokens = $this->oauth->exchange($provider, $input['code'], Crypt::decryptString($state->encrypted_code_verifier));
            abort_unless(is_string($tokens['refresh_token'] ?? null), 422, 'Google did not provide durable offline authorization. Reconnect and approve access.');
            $connection = DB::table('integration_connections')->where('provider', $provider)->firstOrFail();
            $business = $this->businessProfile->current();
            if ($provider === 'gmail') {
                abort_unless($this->oauth->gmailAccount($tokens['access_token']) === strtolower($business['business_email']), 422, 'The approved Gmail account was not authorized.');
                abort_unless(in_array('https://www.googleapis.com/auth/gmail.send', explode(' ', $tokens['scope'] ?? ''), true), 422, 'The required Gmail send scope was not granted.');
                DB::table('integration_connections')->where('id', $connection->id)->update(['status' => 'connecting', 'account' => $business['business_email'],
                    'scopes' => json_encode(['https://www.googleapis.com/auth/gmail.send'], JSON_THROW_ON_ERROR),
                    'encrypted_credentials' => Crypt::encryptString(json_encode($tokens, JSON_THROW_ON_ERROR)), 'authorized_by_admin_id' => $actor->id,
                    'authorized_at' => now(), 'last_error_summary' => null, 'version' => $connection->version + 1, 'updated_at' => now()]);
                $this->gmail->send($business['business_email'], $business['business_name'].' Gmail connection test',
                    'The '.$business['business_name'].' Gmail API integration is connected.', '<p>The <strong>'.e($business['business_name']).'</strong> Gmail API integration is connected.</p>', true);
            } else {
                $config = $this->oauth->config('google_drive');
                abort_unless($this->oauth->driveAccount($tokens['access_token']) === strtolower($business['business_email']), 422, 'The approved Google Drive account was not authorized.');
                abort_unless(in_array('https://www.googleapis.com/auth/drive.file', explode(' ', $tokens['scope'] ?? ''), true), 422, 'The required Google Drive file scope was not granted.');
                $rcloneTokens = ['access_token' => $tokens['access_token'], 'token_type' => $tokens['token_type'],
                    'refresh_token' => $tokens['refresh_token'], 'expiry' => $tokens['expires_at']];
                $this->rclone->configure($rcloneTokens, $config['client_id'], $config['client_secret']);
                abort_unless($this->rclone->validate(), 422, 'Google Drive read/write/delete validation failed.');
                DB::table('integration_connections')->where('id', $connection->id)->update(['account' => $business['business_email'],
                    'scopes' => json_encode($config['scopes'], JSON_THROW_ON_ERROR), 'encrypted_credentials' => Crypt::encryptString(json_encode($tokens, JSON_THROW_ON_ERROR)),
                    'configuration' => json_encode(['remote' => RcloneGateway::REMOTE, 'namespace' => 'mobiST Tech/Backups/', 'managed' => true], JSON_THROW_ON_ERROR),
                    'authorized_by_admin_id' => $actor->id, 'authorized_at' => now(), 'last_success_at' => now(), 'last_error_summary' => null,
                    'version' => $connection->version + 1, 'updated_at' => now()]);
            }
            DB::table('integration_connections')->where('provider', $provider)->update(['status' => 'connected', 'updated_at' => now()]);
            $this->event($provider, 'connect_completed', 'connected', $actor, 'Backend validation passed.');

            return $this->safe(DB::table('integration_connections')->where('provider', $provider)->firstOrFail());
        } catch (\Throwable $error) {
            if ($provider === 'google_drive') {
                $this->rclone->disconnectManaged();
            }
            $this->fail($provider, $actor, 'Connection validation failed.');
            throw $error;
        }
    }

    public function test(Admin $actor, string $provider): array
    {
        $this->authorize($actor);
        $this->provider($provider);
        abort_unless((bool) config('services.google.enabled'), 409, 'External integrations are disabled.');
        if ($provider === 'gmail') {
            $business = $this->businessProfile->current();
            $this->gmail->send($business['business_email'], $business['business_name'].' Gmail test', 'The Gmail API test succeeded.', '<p>The Gmail API test succeeded.</p>');
        } else {
            abort_unless($this->rclone->validate((bool) (json_decode(DB::table('integration_connections')->where('provider', $provider)->value('configuration') ?: '{}', true)['managed'] ?? true)), 422, 'Google Drive validation failed.');
            DB::table('integration_connections')->where('provider', $provider)->update(['last_success_at' => now(), 'last_error_summary' => null, 'updated_at' => now()]);
        }
        $this->event($provider, 'test', 'passed', $actor, null);

        return $this->safe(DB::table('integration_connections')->where('provider', $provider)->firstOrFail());
    }

    public function disconnect(Admin $actor, string $provider): array
    {
        $this->authorize($actor);
        $this->provider($provider);
        $row = DB::table('integration_connections')->where('provider', $provider)->lockForUpdate()->firstOrFail();
        $configuration = json_decode($row->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        if ($provider === 'google_drive' && ($configuration['managed'] ?? false)) {
            $this->rclone->disconnectManaged();
        }
        DB::table('integration_connections')->where('id', $row->id)->update(['status' => 'not_connected', 'account' => null, 'scopes' => null,
            'encrypted_credentials' => null, 'authorized_by_admin_id' => null, 'authorized_at' => null, 'last_error_summary' => null,
            'version' => $row->version + 1, 'updated_at' => now()]);
        $this->event($provider, 'disconnect', 'not_connected', $actor, 'Backup archives were not deleted.');

        return $this->safe(DB::table('integration_connections')->where('provider', $provider)->firstOrFail());
    }

    public function backupSettings(Admin $actor, array $input): array
    {
        $this->authorize($actor);
        if (array_diff(array_keys($input), ['schedule', 'retention_days'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected backup setting fields.']);
        }
        $data = validator($input, ['schedule' => ['required', 'in:disabled,daily,weekly'], 'retention_days' => ['required', 'integer', 'min:1', 'max:365']])->validate();
        $row = DB::table('integration_connections')->where('provider', 'google_drive')->lockForUpdate()->firstOrFail();
        $configuration = json_decode($row->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $configuration['schedule'] = $data['schedule'];
        $configuration['retention'] = (int) $data['retention_days'].' days';
        $configuration['retention_days'] = (int) $data['retention_days'];
        DB::table('integration_connections')->where('id', $row->id)->update(['configuration' => json_encode($configuration, JSON_THROW_ON_ERROR),
            'version' => $row->version + 1, 'updated_at' => now()]);
        $this->event('google_drive', 'backup_settings_updated', 'saved', $actor, 'Schedule and retention updated.');

        return $this->safe(DB::table('integration_connections')->where('id', $row->id)->firstOrFail());
    }

    private function authorize(Admin $actor): void
    {
        abort_unless(app(Access::class)->allows($actor, 'admin.integrations.manage'), 403);
    }

    private function provider(string $provider): void
    {
        abort_unless(in_array($provider, ['gmail', 'google_drive'], true), 404);
    }

    private function safe(object $row): array
    {
        $configuration = json_decode($row->configuration ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $backup = $row->provider === 'google_drive' ? DB::table('backup_records')->where('integration_connection_id', $row->id)->latest('id')->first() : null;

        return ['provider' => $row->provider === 'gmail' ? 'Gmail' : 'Google Drive', 'key' => $row->provider,
            'status' => $row->status, 'account' => $row->account, 'remote' => $configuration['remote'] ?? null,
            'backup_destination' => $configuration['namespace'] ?? null, 'last_success_at' => $row->last_success_at,
            'last_error_summary' => $row->last_error_summary, 'backup_schedule' => $configuration['schedule'] ?? 'Not configured',
            'retention_policy' => $configuration['retention'] ?? 'Not configured', 'last_backup_status' => $backup?->status,
            'last_backup_size' => $backup?->size_bytes, 'last_backup_at' => $backup?->completed_at];
    }

    private function event(string $provider, string $action, string $outcome, Admin $actor, ?string $reference): void
    {
        $id = DB::table('integration_connections')->where('provider', $provider)->value('id');
        DB::table('integration_events')->insert(['integration_connection_id' => $id, 'action' => $action, 'outcome' => $outcome,
            'admin_id' => $actor->id, 'safe_reference' => $reference, 'occurred_at' => now()]);
        IdentityAudit::record('admin', $actor->id, 'integration_'.$action, $provider);
    }

    private function fail(string $provider, Admin $actor, string $summary): void
    {
        DB::table('integration_connections')->where('provider', $provider)->update(['status' => 'error', 'encrypted_credentials' => null,
            'last_error_summary' => $summary, 'updated_at' => now()]);
        $this->event($provider, 'connect_failed', 'error', $actor, $summary);
    }
}
