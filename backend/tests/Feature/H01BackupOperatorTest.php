<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class H01BackupOperatorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_original_backup_operator_actions_use_one_protected_local_only_admin_boundary(): void
    {
        config(['session.driver' => 'database', 'services.google.enabled' => false]);
        $outlet = new Outlet;
        $outlet->forceFill(['name' => 'H01 fixture outlet', 'outlet_code' => '083', 'public_id' => (string) Str::uuid()])->save();
        $owner = $this->member('h01-backups-owner@example.invalid', ['shops.enter', 'shop.sales', 'backups.manage']);
        $limited = $this->member('h01-backups-limited@example.invalid', ['shops.enter', 'shop.sales']);
        $owner->shops()->attach($outlet);
        $limited->shops()->attach($outlet);
        $connection = DB::table('integration_connections')->where('provider', 'google_drive')->firstOrFail();
        $root = storage_path('app/private/backups');
        if (! is_dir($root)) {
            mkdir($root, 0700, true);
        }
        $name = 'h01-'.Str::uuid().'.backup';
        $path = $root.DIRECTORY_SEPARATOR.$name;
        $synthetic = 'h01-encrypted-synthetic-backup-'.Str::uuid();
        file_put_contents($path, $synthetic);
        $url = '/internal/admin/integrations/google_drive/backups';
        try {
            $id = DB::table('backup_records')->insertGetId([
                'integration_connection_id' => $connection->id, 'scope' => 'business', 'trigger' => 'manual',
                'requested_by_type' => 'admin', 'requested_by_id' => $owner->id, 'status' => 'completed',
                'filename' => $name, 'path' => $path, 'size_bytes' => strlen($synthetic),
                'remote_status' => 'not_configured', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $remoteId = DB::table('backup_records')->insertGetId([
                'integration_connection_id' => $connection->id, 'scope' => 'business', 'trigger' => 'manual',
                'requested_by_type' => 'admin', 'requested_by_id' => $owner->id, 'status' => 'completed',
                'filename' => 'h01-remote-only.backup', 'path' => null, 'remote_path' => 'mobiST Tech/Backups/testing/h01-remote-only.backup',
                'remote_status' => 'uploaded', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $backupOnly = $this->member('h01-backups-only@example.invalid', ['shops.enter', 'shop.sales', 'backups.manage']);
            $backupOnly->shops()->attach($outlet);
            $backupClient = $this->client();
            $this->login($backupClient, $backupOnly->email)->assertOk();
            $this->send($backupClient, 'GET', '/internal/admin/settings/integrations')->assertOk()
                ->assertInertia(fn (Assert $page) => $page->component('integrations')
                    ->where('can_manage_backups', true)->where('can_manage_integrations', false)
                    ->where('integrations', []));
            $this->send($backupClient, 'GET', $url)->assertOk();
            $this->send($backupClient, 'GET', '/internal/admin/integrations')->assertForbidden();
            $guest = $this->client();
            $this->send($guest, 'GET', $url)->assertUnauthorized();
            $this->send($guest, 'GET', "$url/$id/download")->assertUnauthorized();
            $this->send($guest, 'DELETE', "$url/$id")->assertUnauthorized();
            $operator = $this->client();
            $this->login($operator, $limited->email)->assertOk();
            $this->send($operator, 'GET', $url)->assertForbidden();
            $this->send($operator, 'GET', "$url/$id/download")->assertForbidden();
            $this->send($operator, 'DELETE', "$url/$id")->assertForbidden();
            $client = $this->client();
            $this->login($client, $owner->email)->assertOk();
            $history = $this->send($client, 'GET', $url)->assertOk();
            $this->assertStringContainsString('no-store', (string) $history->headers->get('Cache-Control'));
            $record = collect($history->json('data'))->firstWhere('id', $id);
            $this->assertTrue($record['local_available']);
            $this->assertTrue($record['can_delete_local']);
            $this->assertArrayNotHasKey('path', $record);
            $this->assertArrayNotHasKey('remote_path', $record);
            $remote = collect($history->json('data'))->firstWhere('id', $remoteId);
            $this->assertFalse($remote['local_available']);
            $this->assertFalse($remote['can_delete_local']);
            $download = $this->send($client, 'GET', "$url/$id/download")->assertOk()
                ->assertHeader('Content-Type', 'application/octet-stream')
                ->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertSame($synthetic, $download->getContent());
            $this->assertStringContainsString('attachment;', (string) $download->headers->get('Content-Disposition'));
            $this->send($client, 'GET', "$url/$remoteId/download")->assertNotFound();
            $this->send($client, 'DELETE', "$url/$remoteId")->assertStatus(409);
            $this->assertSame('uploaded', DB::table('backup_records')->where('id', $remoteId)->value('remote_status'));
            $this->send($client, 'DELETE', "$url/$id", [], false)->assertStatus(419);
            $this->assertFileExists($path);
            $this->send($client, 'DELETE', "$url/$id")->assertOk()->assertJsonPath('data.deleted', true);
            $this->assertFileDoesNotExist($path);
            $this->assertNull(DB::table('backup_records')->where('id', $id)->value('path'));
            $this->assertSame('deleted', DB::table('backup_records')->where('id', $id)->value('remote_status'));
            $this->send($client, 'GET', "$url/$id/download")->assertNotFound();
            $this->send($client, 'DELETE', "$url/$id")->assertStatus(404);
            $this->assertSame(1, DB::table('admin_audit_logs')->where('action', 'backup_local_deleted')->count());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    private function member(string $email, array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill(['name' => 'H01 fixture', 'email' => $email,
            'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1,
            'permissions' => $permissions, 'job_title' => 'Fixture role'])->save();

        return $admin;
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic H01 operator'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $email, 'password' => 'SyntheticPass123!',
        ]);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true)
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent']];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }
}
