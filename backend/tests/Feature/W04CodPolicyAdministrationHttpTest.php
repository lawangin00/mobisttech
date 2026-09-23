<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/** A nonsecret COD policy can be drafted and published without enabling a merchant adapter. */
final class W04CodPolicyAdministrationHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_permissioned_cod_draft_publish_changes_only_cod_and_retains_masked_read_only_status(): void
    {
        $draftPath = '/internal/admin/website/payment-settings/drafts';
        $statusPath = '/internal/admin/website/payment-channels';
        $originalExternal = config('commerce.providers.jazzcash');
        $guest = $this->client();
        $this->send($guest, 'POST', $draftPath, ['cod_enabled' => false])->assertUnauthorized();

        [$readOnly, $readOnlyOutlet] = $this->admin(['shops.enter', 'website.payment-credentials.manage']);
        $reader = $this->client();
        $this->login($reader, $readOnly, $readOnlyOutlet);
        $this->send($reader, 'POST', $draftPath, ['cod_enabled' => false])->assertForbidden();

        [$manager, $managerOutlet] = $this->admin(['shops.enter', 'website.payments.manage']);
        $client = $this->client();
        $this->login($client, $manager, $managerOutlet);
        $this->send($client, 'POST', $draftPath, [
            'cod_enabled' => false, 'merchant' => 'synthetic-never-store',
        ])->assertStatus(422);
        $this->send($client, 'POST', $draftPath, ['cod_enabled' => 'false'])->assertStatus(422);
        $this->assertSame(0, DB::table('site_configuration_revisions')
            ->where('domain', 'website.payments.cod')->count());

        $draft = $this->send($client, 'POST', $draftPath, ['cod_enabled' => false])
            ->assertCreated()->assertJsonPath('data.cod_enabled', false)->json('data');
        $this->send($client, 'GET', $statusPath)->assertOk()
            ->assertJsonPath('data.0.available', true);
        $this->send($client, 'GET', '/internal/admin/website/payment-settings')->assertOk()
            ->assertSee('website-payment-settings')->assertSee('can_publish');
        $this->send($client, 'POST', $draftPath.'/'.$draft['id'].'/publish')->assertForbidden();

        [$publisher, $publisherOutlet] = $this->admin([
            'shops.enter', 'website.payments.manage', 'website.publish',
        ]);
        $publisherClient = $this->client();
        $this->login($publisherClient, $publisher, $publisherOutlet);
        $this->send($publisherClient, 'POST', $draftPath.'/'.$draft['id'].'/publish')
            ->assertOk()->assertJsonPath('data.cod_enabled', false);
        $this->send($publisherClient, 'GET', $statusPath)->assertOk()
            ->assertJsonPath('data.0.enabled', false)
            ->assertJsonPath('data.0.available', false)
            ->assertJsonPath('data.1.available', false);
        $this->assertSame('published', DB::table('site_configuration_revisions')
            ->where('id', $draft['id'])->value('state'));
        $this->assertSame($originalExternal, config('commerce.providers.jazzcash'));

        $reEnable = $this->send($publisherClient, 'POST', $draftPath, ['cod_enabled' => true])
            ->assertCreated()->json('data');
        $this->send($publisherClient, 'POST', $draftPath.'/'.$draft['id'].'/publish')->assertStatus(409);
        $this->send($publisherClient, 'POST', $draftPath.'/'.$reEnable['id'].'/publish')
            ->assertOk()->assertJsonPath('data.cod_enabled', true);
        $this->send($publisherClient, 'GET', $statusPath)->assertOk()
            ->assertJsonPath('data.0.available', true)
            ->assertJsonPath('data.1.available', false);
        $this->assertSame('superseded', DB::table('site_configuration_revisions')
            ->where('id', $draft['id'])->value('state'));
    }

    public function test_cod_publish_rejects_foreign_domain_and_corrupt_latest_draft(): void
    {
        [$publisher, $outlet] = $this->admin([
            'shops.enter', 'website.payments.manage', 'website.publish',
        ]);
        $client = $this->client();
        $this->login($client, $publisher, $outlet);
        $foreignId = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.payments.synthetic-foreign', 'version' => 1,
            'state' => 'draft', 'snapshot' => json_encode(['cod_enabled' => false]),
            'created_by_admin_id' => $publisher->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $route = '/internal/admin/website/payment-settings/drafts/';
        $this->send($client, 'POST', $route.$foreignId.'/publish')->assertStatus(409);
        $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $foreignId)->value('state'));
        $invalidId = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.payments.cod', 'version' => 1,
            'state' => 'draft', 'snapshot' => json_encode(['cod_enabled' => 'false']),
            'created_by_admin_id' => $publisher->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->send($client, 'POST', $route.$invalidId.'/publish')->assertStatus(409);
        $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $invalidId)->value('state'));
        $this->send($client, 'GET', '/internal/admin/website/payment-channels')->assertOk()
            ->assertJsonPath('data.0.available', true);
    }

    public function test_distinct_mysql_session_lock_blocks_draft_and_publish_without_partial_revision(): void
    {
        [$publisher, $outlet] = $this->admin([
            'shops.enter', 'website.payments.manage', 'website.publish',
        ]);
        $client = $this->client();
        $this->login($client, $publisher, $outlet);
        $lockName = 'mobisttech.website.payments.cod.revision';
        $connectionName = 'w04_cod_lock_holder';
        config(['database.connections.'.$connectionName => config('database.connections.mysql')]);
        $holder = DB::connection($connectionName);
        $draftPath = '/internal/admin/website/payment-settings/drafts';
        try {
            $this->assertSame(1, (int) $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired);
            $this->send($client, 'POST', $draftPath, ['cod_enabled' => false])->assertStatus(409);
            $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', 'website.payments.cod')->count());
            $this->assertSame(1, (int) $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName])->released);
            $draft = $this->send($client, 'POST', $draftPath, ['cod_enabled' => false])->assertCreated()->json('data');
            $this->assertSame(1, (int) $holder->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lockName])->acquired);
            $this->send($client, 'POST', $draftPath.'/'.$draft['id'].'/publish')->assertStatus(409);
            $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $draft['id'])->value('state'));
            $this->assertSame(1, (int) $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName])->released);
            $this->send($client, 'POST', $draftPath.'/'.$draft['id'].'/publish')->assertOk();
            $this->assertSame('published', DB::table('site_configuration_revisions')->where('id', $draft['id'])->value('state'));
        } finally {
            $holder->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lockName]);
            DB::disconnect($connectionName);
        }
    }

    public function test_malformed_draft_is_not_advertised_as_publishable_in_authorized_admin_page(): void
    {
        [$admin, $outlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $client = $this->client();
        $this->login($client, $admin, $outlet);
        $id = DB::table('site_configuration_revisions')->insertGetId([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'draft',
            'snapshot' => json_encode(['cod_enabled' => 'false'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $response = $this->send($client, 'GET', '/internal/admin/website/payment-settings')->assertOk();
        $page = html_entity_decode($response->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('"draft_invalid":true', $page);
        $this->assertStringContainsString('"draft":null', $page);
        $this->send($client, 'POST', '/internal/admin/website/payment-settings/drafts/'.$id.'/publish')->assertStatus(409);
        $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $id)->value('state'));
    }

    public function test_authorized_admin_can_open_and_repair_malformed_published_cod_policy(): void
    {
        [$admin, $outlet] = $this->admin(['shops.enter', 'website.payments.manage', 'website.publish']);
        $client = $this->client();
        $this->login($client, $admin, $outlet);
        DB::table('site_configuration_revisions')->insert([
            'domain' => 'website.payments.cod', 'version' => 1, 'state' => 'published',
            'snapshot' => json_encode(['cod_enabled' => 'true'], JSON_THROW_ON_ERROR),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $page = html_entity_decode($this->send($client, 'GET', '/internal/admin/website/payment-settings')
            ->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('"published_invalid":true', $page);
        $this->assertStringContainsString('"cod_enabled":false', $page);
        $this->assertStringContainsString('"available":false', $page);
        $draft = $this->send($client, 'POST', '/internal/admin/website/payment-settings/drafts',
            ['cod_enabled' => true])->assertCreated()->json('data');
        $this->assertSame(2, $draft['version']);
        $this->send($client, 'POST', '/internal/admin/website/payment-settings/drafts/'.$draft['id'].'/publish')->assertOk();
        $recovered = html_entity_decode($this->send($client, 'GET', '/internal/admin/website/payment-settings')
            ->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);
        $this->assertStringContainsString('"published_invalid":false', $recovered);
        $this->assertStringContainsString('"cod_enabled":true', $recovered);
    }

    private function admin(array $permissions): array
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'W04 COD Admin', 'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make('SyntheticPass123!'), 'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();
        $outlet = new Outlet;
        $outlet->forceFill([
            'name' => 'W04 COD Outlet', 'public_id' => (string) Str::uuid(),
            'outlet_code' => (string) random_int(100, 999),
        ])->save();
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function login(array &$client, Admin $admin, Outlet $outlet): void
    {
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $admin->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $outlet->public_id,
        ])->assertOk();
    }

    private function client(): array
    {
        // Four independent synthetic clients must not share one production 5/min/IP
        // identity throttle bucket. Keep all real throttles and request boundaries on.
        static $nextSyntheticIp = 20;
        $client = [
            'cookies' => [], 'tokens' => [], 'agent' => 'Synthetic W04 COD client',
            'ip' => '127.0.0.'.$nextSyntheticIp++,
        ];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(array &$client, string $method, string $uri, array $data = [])
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'], 'REMOTE_ADDR' => $client['ip'],
        ];
        if (isset($client['tokens']['XSRF-TOKEN-admin'])) {
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
