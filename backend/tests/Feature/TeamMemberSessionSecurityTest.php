<?php

namespace Tests\Feature;

use App\Identity\TeamMemberAdministration;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Models\Role;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class TeamMemberSessionSecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_admin_and_customer_realms_exist_and_default_roles_are_permission_bundles(): void
    {
        $this->assertArrayHasKey('customer', config('auth.guards'));
        $this->assertArrayHasKey('admin', config('auth.guards'));
        $this->assertArrayNotHasKey('superadmin', config('auth.guards'));
        $this->assertArrayNotHasKey('website_admin', config('auth.guards'));
        $this->assertSame([
            'Cashier', 'Custom Role', 'Customer Support', 'Digital Operations', 'Full Access', 'Inventory Manager',
            'Manager', 'Merchandiser', 'Online Store Editor', 'Sales Associate', 'Service & Warranty', 'Store Manager',
        ], Role::query()->orderBy('name')->pluck('name')->all());
        $this->assertTrue(Role::where('name', 'Full Access')->value('is_protected'));
        $this->assertFalse(Role::where('name', 'Custom Role')->value('is_protected'));
        $expectedPermissions = array_keys(Admin::PERMISSIONS);
        sort($expectedPermissions);
        $this->assertSame($expectedPermissions, DB::table('permission_definitions')->orderBy('code')->pluck('code')->all());
    }

    public function test_delegation_ceiling_blocks_full_access_self_promotion_unknown_permissions_and_outlet_bypass(): void
    {
        [$manager, $assigned] = $this->actor('Manager', 'manager@example.invalid');
        $other = $this->outlet('Other', '902');
        $sales = Role::where('name', 'Sales Associate')->firstOrFail();
        $full = Role::where('name', 'Full Access')->firstOrFail();
        $service = app(TeamMemberAdministration::class);

        $member = $service->createMember($manager, ['name' => 'Ali Khan', 'email' => 'ali@example.invalid', 'password' => 'SyntheticPass123!',
            'job_title' => 'Retail Specialist', 'active' => true, 'role_ids' => [$sales->public_id], 'outlet_ids' => [$assigned->public_id]]);
        $this->assertSame(['Sales Associate'], $member->roleNames());
        $this->assertSame([$assigned->public_id], $member->shops()->pluck('outlets.public_id')->all());

        $this->expectForbidden(fn () => $service->updateMember($manager, $member, ['role_ids' => [$full->public_id]]));
        $this->expectForbidden(fn () => $service->updateMember($manager, $manager, ['role_ids' => [$sales->public_id]]));
        $this->expectForbidden(fn () => $service->updateMember($manager, $member, ['outlet_ids' => [$other->public_id]]));
        $this->expectStatus(422, fn () => $service->createRole($manager, ['name' => 'Injected', 'permissions' => ['invented.superpower']]));
        $this->expectForbidden(fn () => $service->createRole($manager, ['name' => 'Escalated', 'permissions' => ['team-members.full-access.assign']]));
    }

    public function test_custom_roles_are_validated_audited_and_cannot_orphan_assigned_members(): void
    {
        [$manager, $outlet] = $this->actor('Manager', 'role-manager@example.invalid');
        $service = app(TeamMemberAdministration::class);
        $role = $service->createRole($manager, ['name' => 'Quaidabad Sales', 'permissions' => ['shops.enter', 'shop.sales']]);
        $member = $service->createMember($manager, ['name' => 'Sara Khan', 'email' => 'sara@example.invalid', 'password' => 'SyntheticPass123!',
            'role_ids' => [$role->public_id], 'outlet_ids' => [$outlet->public_id]]);

        $this->expectStatus(409, fn () => $service->updateRole($manager, $role, ['permissions' => []]));
        $this->expectStatus(409, fn () => $service->deleteRole($manager, $role));
        $created = DB::table('team_member_audit_events')->where('action', 'team_member_created')->where('subject_admin_id', $member->id)->first();
        $this->assertSame('Manager', $created->actor_role_snapshot);
        $this->assertSame('Quaidabad Sales', $created->subject_role_snapshot);
        $this->assertSame('Sara Khan', $created->subject_name);
    }

    public function test_direct_team_member_api_is_denied_without_permission_even_when_a_menu_would_be_hidden(): void
    {
        $admin = $this->admin('limited@example.invalid', ['shops.enter']);
        $admin->shops()->attach($this->outlet('Limited', '903'));
        $client = $this->client('admin');
        $this->login($client, 'admin', $admin->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/team-members')->assertForbidden();
    }

    public function test_admin_remember_is_rejected_and_background_requests_do_not_extend_true_inactivity(): void
    {
        Carbon::setTestNow('2026-09-01 08:00:00');
        [$admin] = $this->actor('Full Access', 'session-admin@example.invalid');
        $client = $this->client('admin');
        $this->send($client, 'POST', '/internal/admin/auth/login', ['email' => $admin->email, 'password' => 'SyntheticPass123!', 'remember' => true])->assertUnprocessable();
        $this->login($client, 'admin', $admin->email)->assertOk();
        $initial = DB::table('account_sessions')->where('account_id', $admin->id)->value('last_human_activity');

        Carbon::setTestNow('2026-09-01 08:20:00');
        $this->send($client, 'GET', '/internal/admin/account', [], true, ['HTTP_X_MOBIST_BACKGROUND' => '1'])->assertOk();
        $this->assertSame((string) $initial, (string) DB::table('account_sessions')->where('account_id', $admin->id)->value('last_human_activity'));
        Carbon::setTestNow('2026-09-01 08:30:01');
        $this->send($client, 'GET', '/internal/admin/account', [], true, ['HTTP_X_MOBIST_BACKGROUND' => '1'])->assertUnauthorized();
    }

    public function test_customer_has_independent_120_minute_policy_and_remember_cookie_is_capped(): void
    {
        Carbon::setTestNow('2026-09-01 09:00:00');
        $customer = $this->customer('customer-session@example.invalid');
        $client = $this->client();
        $normal = $this->login($client, 'customer', $customer->email)->assertOk()->baseResponse;
        $this->assertFalse(collect($normal->headers->getCookies())->contains(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_customer_')));
        Carbon::setTestNow('2026-09-01 10:59:00');
        $this->send($client, 'GET', '/api/v1/account', [], true, ['HTTP_X_MOBIST_BACKGROUND' => '1'])->assertOk();
        Carbon::setTestNow('2026-09-01 11:00:01');
        $this->send($client, 'GET', '/api/v1/account', [], true, ['HTTP_X_MOBIST_BACKGROUND' => '1'])->assertUnauthorized();

        Carbon::setTestNow('2026-09-01 12:00:00');
        $remembered = $this->client();
        $response = $this->send($remembered, 'POST', '/api/v1/auth/login', ['email' => $customer->email, 'password' => 'SyntheticPass123!', 'remember' => true])->assertOk()->baseResponse;
        $cookie = collect($response->headers->getCookies())->first(fn ($item) => str_starts_with($item->getName(), 'remember_customer_'));
        $this->assertNotNull($cookie);
        $this->assertLessThanOrEqual(30 * 86400 + 5, $cookie->getExpiresTime() - now()->timestamp);
        $this->assertGreaterThan(29 * 86400, $cookie->getExpiresTime() - now()->timestamp);
    }

    public function test_password_change_revokes_old_customer_sessions_and_stale_admin_recent_authentication(): void
    {
        Carbon::setTestNow('2026-09-01 13:00:00');
        $customer = $this->customer('password-customer@example.invalid');
        $first = $this->client();
        $second = $this->client();
        $this->login($first, 'customer', $customer->email)->assertOk();
        $this->login($second, 'customer', $customer->email)->assertOk();
        $this->send($first, 'PATCH', '/api/v1/auth/password', ['current_password' => 'SyntheticPass123!', 'password' => 'UpdatedPass123!', 'password_confirmation' => 'UpdatedPass123!'])->assertOk();
        $this->send($second, 'GET', '/api/v1/account')->assertUnauthorized();

        [$admin] = $this->actor('Full Access', 'recent-admin@example.invalid');
        $adminClient = $this->client('admin');
        $this->login($adminClient, 'admin', $admin->email)->assertOk();
        Carbon::setTestNow('2026-09-01 13:11:00');
        $this->send($adminClient, 'POST', '/internal/admin/roles', ['name' => 'Stale Attempt', 'permissions' => []])->assertForbidden();
        $this->send($adminClient, 'POST', '/internal/admin/auth/confirm-password', ['password' => 'SyntheticPass123!'])->assertOk();
        $this->send($adminClient, 'POST', '/internal/admin/roles', ['name' => 'Recent Role', 'permissions' => ['shops.enter']])->assertCreated();
    }

    public function test_customer_password_reset_revokes_existing_sessions_and_rotates_credentials(): void
    {
        $customer = $this->customer('reset-customer@example.invalid');
        $first = $this->client();
        $second = $this->client();
        $reset = $this->client();
        $this->login($first, 'customer', $customer->email)->assertOk();
        $this->login($second, 'customer', $customer->email)->assertOk();
        $beforeVersion = $customer->fresh()->auth_version;
        $beforeRemember = $customer->fresh()->remember_token;
        $token = Password::broker('customer')->createToken($customer);

        $this->send($reset, 'POST', '/api/v1/auth/reset-password', [
            'email' => $customer->email,
            'token' => $token,
            'password' => 'ResetPass123!',
            'password_confirmation' => 'ResetPass123!',
        ])->assertOk()->assertJsonPath('data.message', 'Password reset. Sign in again.');

        $fresh = $customer->fresh();
        $this->assertSame($beforeVersion + 1, $fresh->auth_version);
        $this->assertNotSame($beforeRemember, $fresh->remember_token);
        $this->send($first, 'GET', '/api/v1/account')->assertUnauthorized();
        $this->send($second, 'GET', '/api/v1/account')->assertUnauthorized();
        $this->send($reset, 'POST', '/api/v1/auth/login', [
            'email' => $customer->email,
            'password' => 'ResetPass123!',
        ])->assertOk();
    }

    public function test_disabled_and_revoked_admin_sessions_fail_promptly(): void
    {
        [$admin] = $this->actor('Full Access', 'disabled-admin@example.invalid');
        $client = $this->client('admin');
        $this->login($client, 'admin', $admin->email)->assertOk();
        $admin->forceFill(['status' => true])->save();
        $this->send($client, 'GET', '/internal/admin/account')->assertUnauthorized();
    }

    private function actor(string $roleName, string $email): array
    {
        $admin = $this->admin($email, []);
        $role = Role::where('name', $roleName)->firstOrFail();
        $admin->roles()->attach($role, ['assigned_at' => now()]);
        $outlet = $this->outlet($roleName.' outlet', (string) random_int(100, 899));
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function admin(string $email, ?array $permissions = null): Admin
    {
        $admin = new Admin;
        $admin->forceFill(['name' => 'Synthetic Team Member', 'email' => $email, 'password' => Hash::make('SyntheticPass123!'),
            'permissions' => $permissions, 'auth_version' => 1])->save();

        return $admin;
    }

    private function customer(string $email): CustomerAccount
    {
        $customer = new CustomerAccount;
        $customer->forceFill(['name' => 'Synthetic Customer', 'email' => $email, 'mobile' => '03'.random_int(100000000, 999999999),
            'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1])->save();

        return $customer;
    }

    private function outlet(string $name, string $code): Outlet
    {
        $outlet = new Outlet;
        $outlet->forceFill(['name' => $name, 'public_id' => (string) Str::uuid(), 'outlet_code' => $code])->save();

        return $outlet;
    }

    private function expectForbidden(callable $callback): void
    {
        $this->expectStatus(403, $callback);
    }

    private function expectStatus(int $status, callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected HTTP '.$status.'.');
        } catch (HttpException $error) {
            $this->assertSame($status, $error->getStatusCode());
        }
    }

    private function client(string $realm = 'customer'): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic desktop'];
        $path = $realm === 'customer' ? '/api/v1' : '/internal/admin';
        $response = $this->send($client, 'GET', $path.'/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $realm, string $email)
    {
        return $this->send($client, 'POST', $realm === 'customer' ? '/api/v1/auth/login' : '/internal/admin/auth/login',
            ['email' => $email, 'password' => 'SyntheticPass123!']);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $extraServer = [])
    {
        $cookieName = str_starts_with($uri, '/internal/admin/') ? 'XSRF-TOKEN-admin' : 'XSRF-TOKEN';
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'], ...$extraServer];
        if ($csrf && isset($client['tokens'][$cookieName])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens'][$cookieName];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }
}
