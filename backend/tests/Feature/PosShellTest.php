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

class PosShellTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_sales_team_member_gets_role_landing_and_direct_route_permissions_hold(): void
    {
        $outlet = $this->outlet('Sales outlet', '041');
        $member = $this->member('sales@example.invalid', ['shops.enter', 'shop.sales']);
        $member->shops()->attach($outlet);
        $client = $this->client();

        $this->login($client, $member->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('pos-shell')
                ->where('shell.identity.name', 'Synthetic Team Member')
                ->where('shell.active_outlet.name', 'Sales outlet')
                ->has('shell.navigation', 1)
                ->where('shell.navigation.0.key', 'sales')
                ->where('view.kind', 'home'));

        $this->send($client, 'GET', '/internal/admin/pos/workspace/sales')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('view.kind', 'workspace')
                ->where('view.workspace.key', 'sales'));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/inventory')->assertForbidden();

        $session = DB::table('account_sessions')->where('guard', 'admin')
            ->where('account_id', $member->id)->whereNull('revoked_at')->firstOrFail();
        $before = (string) $session->last_human_activity;
        $this->send($client, 'GET', '/internal/admin/account', [], true, [
            'HTTP_X_MOBIST_BACKGROUND' => '1',
        ])->assertOk()->assertJsonPath('data.session_policy.inactivity_minutes', 30)

            ->assertJsonPath('data.session_policy.warning_minutes', 5);
        $after = (string) DB::table('account_sessions')->where('id', $session->id)->value('last_human_activity');
        $this->assertSame($before, $after);
    }

    public function test_multiple_outlets_require_explicit_selection_before_workspace_access(): void
    {
        $first = $this->outlet('First outlet', '042');
        $second = $this->outlet('Second outlet', '043');
        $member = $this->member('inventory@example.invalid', ['shops.enter', 'shop.inventory']);
        $member->shops()->attach([$first->id, $second->id]);
        $client = $this->client();

        $this->login($client, $member->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.active_outlet', null)
                ->has('shell.outlets', 2)
                ->where('shell.navigation.0.key', 'inventory'));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/inventory')->assertForbidden();

        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $first->public_id,
        ])->assertOk()->assertJsonPath('data.outlet_id', $first->public_id);
        $this->send($client, 'GET', '/internal/admin/pos/workspace/inventory')->assertOk()

            ->assertInertia(fn (Assert $page) => $page
                ->where('shell.active_outlet.id', $first->public_id)
                ->where('view.workspace.key', 'inventory'));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/sales')->assertForbidden();
    }

    public function test_configuration_only_team_member_has_no_pos_operational_navigation(): void
    {
        $member = $this->member('config@example.invalid', ['website.content.manage']);
        $client = $this->client();
        $this->login($client, $member->email)->assertOk();

        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('shell.navigation', 0)
                ->has('shell.outlets', 0)
                ->where('shell.active_outlet', null));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/reports')->assertForbidden();
    }

    private function member(string $email, array $permissions): Admin
    {
        $member = new Admin;
        $member->forceFill([
            'name' => 'Synthetic Team Member',
            'email' => $email,
            'password' => Hash::make('SyntheticPass123!'),

            'auth_version' => 1,
            'permissions' => $permissions,
            'job_title' => 'Synthetic role',
        ])->save();

        return $member;
    }

    private function outlet(string $name, string $code): Outlet
    {
        $outlet = new Outlet;
        $outlet->forceFill([
            'name' => $name,
            'outlet_code' => $code,
            'public_id' => (string) Str::uuid(),
        ])->save();

        return $outlet;
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic POS desktop'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $email)
    {
        return $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $email,
            'password' => 'SyntheticPass123!',
        ]);
    }

    private function send(
        array &$client,
        string $method,
        string $uri,
        array $data = [],
        bool $csrf = true,
        array $extraServer = [],
    ) {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
            ...$extraServer,
        ];
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
