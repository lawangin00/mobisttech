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
        $this->postJson($draftPath, ['cod_enabled' => false])->assertUnauthorized();

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
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic W04 COD client'];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(array &$client, string $method, string $uri, array $data = [])
    {
        $server = [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
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
