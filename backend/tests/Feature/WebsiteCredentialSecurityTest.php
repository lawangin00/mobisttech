<?php

namespace Tests\Feature;

use App\Cms\WebsiteSecretSettings;
use App\Models\Admin;
use App\Operations\ConfigurationRecovery;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

final class WebsiteCredentialSecurityTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        config(['backups.key_id' => 'synthetic-key-2026']);
    }

    public function test_allowlisted_credential_mask_rotation_and_recovery_guard(): void
    {
        $actor = new Admin;
        $actor->forceFill(['name' => 'Website config operator', 'email' => 'w07-secret-owner@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => ['website.payment-credentials.manage', 'config.integrations-backups.manage']])->save();
        $service = app(WebsiteSecretSettings::class);
        $this->assertCount(6, $service->metadata($actor));
        $key = 'payments.card.api_secret';
        $first = $service->replace($actor, $key, 'synthetic-one');
        $this->assertSame('********', $first['masked_value']);
        $this->assertSame(1, $first['version']);
        $row = DB::table('site_secret_settings')->where('key', $key)->firstOrFail();
        $this->assertSame('synthetic-one', Crypt::decryptString($row->ciphertext));
        $this->assertStringNotContainsString('synthetic-one', json_encode($service->metadata($actor)));
        $snapshot = app(ConfigurationRecovery::class)->capture($actor);
        $manifest = DB::table('configuration_recovery_snapshots')->where('public_id', $snapshot['public_id'])->value('manifest');
        $this->assertStringNotContainsString('synthetic-one', $manifest);
        $this->assertStringNotContainsString($row->ciphertext, $manifest);
        app(ConfigurationRecovery::class)->verify($actor, $snapshot['public_id'], 'synthetic-key-2026');
        $next = $service->replace($actor, $key, 'synthetic-two');
        $this->assertSame(2, $next['version']);
        $this->assertNotNull($next['rotated_at']);
        $this->reject(fn () => app(ConfigurationRecovery::class)->verify($actor, $snapshot['public_id'], 'synthetic-key-2026'));
        // A syntactically valid envelope from another active key must also fail closed.
        $foreignEnvelope = (new Encrypter(str_repeat('m', 32), 'aes-256-cbc'))->encryptString('foreign-value');
        DB::table('site_secret_settings')->where('key', $key)->update(['ciphertext' => $foreignEnvelope]);
        $this->reject(fn () => $service->metadata($actor));
        $this->reject(fn () => $service->replace($actor, $key, 'must-not-overwrite'));
        $this->assertSame($foreignEnvelope, DB::table('site_secret_settings')->where('key', $key)->value('ciphertext'));
        DB::table('site_secret_settings')->where('key', $key)->update(['ciphertext' => 'invalid-ciphertext']);
        $this->reject(fn () => $service->metadata($actor));
        $this->reject(fn () => $service->replace($actor, $key, 'should-not-save'));
        $this->assertSame('invalid-ciphertext', DB::table('site_secret_settings')->where('key', $key)->value('ciphertext'));
    }
}
