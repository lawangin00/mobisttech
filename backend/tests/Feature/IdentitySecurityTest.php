<?php

namespace Tests\Feature;

use App\Identity\Access;
use App\Identity\RecoveryNotification;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\SuperAdmin;
use App\Models\User;
use App\Models\WebsiteAdmin;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentitySecurityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_registration_rejects_role_injection_and_rotates_the_session(): void
    {
        $client = $this->client();
        $before = $client['cookies']['mobist_customer_session'];
        $data = ['name' => 'Synthetic', 'email' => 'synthetic@example.invalid', 'mobile' => '03000000001', 'password' => 'SyntheticPass123!', 'password_confirmation' => 'SyntheticPass123!'];
        $this->send($client, 'POST', '/api/v1/auth/register', [...$data, 'is_admin' => true])->assertUnprocessable();
        $this->send($client, 'POST', '/api/v1/auth/register', $data)->assertCreated()->assertJsonPath('data.email', $data['email']);
        $this->assertNotSame($before, $client['cookies']['mobist_customer_session']);
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertNull($user->admin_role);
        $this->assertSame(1, DB::table('customers')->where('website_user_id', $user->id)->count());
        $this->send($client, 'GET', '/api/v1/account')->assertOk()->assertJsonMissingPath('data.password')->assertHeader('Cache-Control', 'no-store, private');
        $this->send($client, 'GET', '/api/v1/account?user_id=999')->assertUnprocessable();
    }

    public function test_csrf_is_enforced_even_with_same_origin_fetch_header(): void
    {
        $client = $this->client();
        $this->send($client, 'POST', '/api/v1/auth/login', ['email' => 'missing@example.invalid', 'password' => 'password'], false, ['HTTP_SEC_FETCH_SITE' => 'same-origin'])->assertStatus(419);
        $this->send($client, 'POST', '/api/v1/auth/login', ['email' => 'missing@example.invalid', 'password' => 'password'], true, ['HTTP_ORIGIN' => 'https://evil.invalid'])->assertForbidden();
        $this->send($client, 'POST', '/api/v1/auth/login', ['email' => 'missing@example.invalid', 'password' => 'password'], false,
            ['HTTP_X_XSRF_TOKEN' => $client['cookies']['XSRF-TOKEN']])->assertUnprocessable();
        $this->call('POST', '/api/v1/auth/login', [], [], [], ['CONTENT_TYPE' => 'application/json'], '{broken')->assertStatus(400);
    }

    public function test_customer_and_cms_sessions_and_equal_email_pos_accounts_are_isolated(): void
    {
        $customer = $this->account(CustomerAccount::class, 'shared@example.invalid');
        $super = $this->account(SuperAdmin::class, 'shared@example.invalid');
        $cms = $this->account(WebsiteAdmin::class, 'cms@example.invalid', ['is_admin' => true, 'admin_role' => 'manager']);
        $customerClient = $this->client();
        $this->login($customerClient, 'customer', $customer->email)->assertOk();
        $this->send($customerClient, 'GET', '/internal/superadmin/account')->assertUnauthorized();
        $superClient = $this->client('superadmin');
        $this->login($superClient, 'superadmin', $super->email)->assertOk();
        $this->send($superClient, 'GET', '/api/v1/account')->assertUnauthorized();
        $cmsClient = $this->client('website_admin');
        $this->login($cmsClient, 'website_admin', $customer->email)->assertUnprocessable();
        $this->login($cmsClient, 'website_admin', $cms->email)->assertOk();
        $this->login($customerClient, 'customer', $cms->email)->assertUnprocessable();
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_customer_ownership_does_not_follow_email_or_guest_order_contact(): void
    {
        $a = $this->account(CustomerAccount::class, 'a@example.invalid');
        $b = $this->account(CustomerAccount::class, 'b@example.invalid');
        $uuid = (string) Str::uuid();
        DB::table('orders')->insert(['public_id' => $uuid, 'order_number' => 'MT22-OWN', 'order_type' => 'mobile', 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000001', 'customer_email' => $b->email, 'user_id' => $a->id]);
        $this->assertSame($a->id, app(Access::class)->ownOrder($a, $uuid)->user_id);
        try {
            app(Access::class)->ownOrder($b, $uuid);
            $this->fail('Contact-based ownership granted.');
        } catch (HttpException $error) {
            $this->assertSame(404, $error->getStatusCode());
        }
        DB::table('orders')->where('public_id', $uuid)->update(['user_id' => null]);
        $this->expectException(HttpException::class);
        app(Access::class)->ownOrder($a, $uuid);
    }

    public function test_complete_website_role_matrix_and_pos_default_grants(): void
    {
        $expected = [
            'owner' => ['admin-users', 'audit-log', 'content', 'orders', 'services', 'service-requests', 'project-quotes', 'settings', 'website.content.manage', 'website.navigation.manage', 'website.branding.manage', 'website.theme.manage', 'website.payments.manage', 'website.payment-credentials.manage', 'website.integrations.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
            'manager' => ['audit-log', 'content', 'orders', 'services', 'service-requests', 'project-quotes', 'settings', 'website.content.manage', 'website.navigation.manage', 'website.branding.manage', 'website.theme.manage', 'website.payments.manage', 'website.integrations.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
            'content_editor' => ['content', 'services', 'settings', 'website.content.manage', 'website.seo.manage', 'website.media.manage', 'website.publish'],
            'operations' => ['orders', 'service-requests', 'project-quotes'],
        ];
        $access = app(Access::class);
        foreach ($expected as $role => $allowed) {
            $user = (new WebsiteAdmin)->forceFill(['is_admin' => true, 'admin_role' => $role]);
            foreach ($expected['owner'] as $permission) {
                $this->assertSame(in_array($permission, $allowed, true), $access->allows($user, $permission), $role.':'.$permission);
            }
            foreach (array_keys(Admin::PERMISSIONS) as $permission) {
                $this->assertFalse($access->allows($user, $permission));
            }
        }
        $this->assertFalse((new WebsiteAdmin)->forceFill(['is_admin' => true])->canAdmin('admin-users'));
        $admin = new Admin;
        foreach (array_keys(Admin::OPERATIONAL_PERMISSIONS) as $permission) {
            $this->assertTrue($admin->hasPermission($permission));
        }
        foreach (array_keys(Admin::CONFIGURATION_PERMISSIONS) as $permission) {
            $this->assertFalse($admin->hasPermission($permission));
        }
        foreach ($expected['owner'] as $permission) {
            $this->assertFalse($access->allows(new SuperAdmin, $permission));
        }
    }

    public function test_reset_tokens_are_scoped_single_use_and_revoke_existing_sessions(): void
    {
        $customer = $this->account(CustomerAccount::class, 'same@example.invalid');
        $super = $this->account(SuperAdmin::class, 'same@example.invalid');
        $client = $this->client();
        $this->login($client, 'customer', $customer->email)->assertOk();
        $customerToken = Password::broker('customer')->createToken($customer);
        $superToken = Password::broker('superadmin')->createToken($super);
        $this->assertNotSame($customerToken, DB::table('password_reset_tokens')->where('email', $customer->email)->value('token'));
        $payload = ['email' => $customer->email, 'token' => $superToken, 'password' => 'UpdatedPass123!', 'password_confirmation' => 'UpdatedPass123!'];
        $this->send($client, 'POST', '/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $payload['token'] = $customerToken;
        $this->send($client, 'POST', '/api/v1/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('UpdatedPass123!', $customer->fresh()->password));
        $this->assertTrue(Hash::check('SyntheticPass123!', $super->fresh()->password));
        $this->send($client, 'GET', '/api/v1/account')->assertUnauthorized();
        $client = $this->client();
        $this->send($client, 'POST', '/api/v1/auth/reset-password', $payload)->assertUnprocessable();
        $this->assertTrue(Password::broker('superadmin')->tokenExists($super, $superToken));
    }

    public function test_recovery_is_non_enumerating_and_does_not_log_tokens_when_delivery_is_disabled(): void
    {
        $user = $this->account(CustomerAccount::class, 'recover@example.invalid');
        Notification::fake();
        $client = $this->client();
        $this->send($client, 'POST', '/api/v1/auth/forgot-password', ['email' => $user->email])->assertStatus(503);
        Notification::assertNothingSent();
        config(['identity.recovery_delivery_enabled' => true, 'mail.default' => 'smtp']);
        $first = $this->send($client, 'POST', '/api/v1/auth/forgot-password', ['email' => $user->email])->assertOk();
        $second = $this->send($client, 'POST', '/api/v1/auth/forgot-password', ['email' => 'absent@example.invalid'])->assertOk();
        $this->assertSame($first->json(), $second->json());
        Notification::assertSentTo($user, RecoveryNotification::class);
        $token = Notification::sent($user, RecoveryNotification::class)->first()->token;
        $this->assertTrue(Password::broker('customer')->tokenExists($user, $token));
        $this->travel(61)->minutes();
        $this->assertFalse(Password::broker('customer')->tokenExists($user, $token));
    }

    public function test_login_is_rate_limited_and_disabled_accounts_cannot_authenticate(): void
    {
        $user = $this->account(CustomerAccount::class, 'blocked@example.invalid', ['archived_at' => now()]);
        $client = $this->client();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($client, 'customer', $user->email)->assertUnprocessable();
        }
        $this->login($client, 'customer', $user->email)->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_pos_device_limits_replacement_and_logout_keep_other_realms_alive(): void
    {
        $super = $this->account(SuperAdmin::class, 'devices@example.invalid');
        $desktop = $this->client('superadmin');
        $this->login($desktop, 'superadmin', $super->email)->assertOk();
        $oldDesktop = $desktop;
        $this->login($desktop, 'superadmin', $super->email)->assertOk();
        $this->send($oldDesktop, 'GET', '/internal/superadmin/account')->assertUnauthorized();
        $otherDesktop = $this->client('superadmin');
        $this->login($otherDesktop, 'superadmin', $super->email)->assertForbidden();
        $mobile = $this->client('superadmin', 'Synthetic Android mobile');
        $this->login($mobile, 'superadmin', $super->email)->assertOk();
        $this->send($mobile, 'GET', '/internal/superadmin/account', [], true, ['REMOTE_ADDR' => '203.0.113.9'])->assertUnauthorized();
        $this->send($desktop, 'GET', '/internal/superadmin/account')->assertOk();
        $this->send($desktop, 'POST', '/internal/superadmin/auth/logout')->assertOk();
        $this->send($desktop, 'GET', '/internal/superadmin/account')->assertUnauthorized();
        $this->assertSame(0, DB::table('account_sessions')->where('guard', 'superadmin')->where('account_id', $super->id)->whereNull('revoked_at')->count());
    }

    public function test_outlet_assignment_and_permissions_are_rechecked_and_cannot_be_injected(): void
    {
        $admin = $this->account(Admin::class, 'operator@example.invalid');
        $outlet = DB::table('outlets')->insertGetId(['name' => 'Assigned', 'public_id' => (string) Str::uuid(), 'outlet_code' => '001']);
        $other = DB::table('outlets')->insertGetId(['name' => 'Other', 'public_id' => (string) Str::uuid(), 'outlet_code' => '002']);
        DB::table('outlet_admins')->insert(['outlet_id' => $outlet, 'admin_id' => $admin->id]);
        $client = $this->client('admin');
        $this->login($client, 'admin', $admin->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/outlets')->assertOk()->assertJsonCount(1, 'data')
            ->assertExactJson(['data' => [['id' => DB::table('outlets')->where('id', $outlet)->value('public_id'), 'name' => 'Assigned']]]);
        $uuid = DB::table('outlets')->where('id', $other)->value('public_id');
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $uuid])->assertNotFound();
        $uuid = DB::table('outlets')->where('id', $outlet)->value('public_id');
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $uuid, 'operator_admin_id' => 999])->assertUnprocessable();
        DB::table('outlet_admins')->where('admin_id', $admin->id)->delete();
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $uuid])->assertNotFound();
        $admin->forceFill(['permissions' => []])->save();
        $this->send($client, 'GET', '/internal/admin/outlets')->assertForbidden();
        $admin->forceFill(['status' => 1])->save();
        $this->send($client, 'GET', '/internal/admin/account')->assertUnauthorized();
    }

    public function test_password_change_revokes_other_devices_and_remember_cookie_cannot_restore_access(): void
    {
        $user = $this->account(CustomerAccount::class, 'password@example.invalid');
        $first = $this->client();
        $second = $this->client();
        $this->send($first, 'POST', '/api/v1/auth/login', ['email' => $user->email, 'password' => 'SyntheticPass123!', 'remember' => true])->assertOk();
        $remember = array_filter($first['cookies'], fn ($name) => str_starts_with($name, 'remember_customer'), ARRAY_FILTER_USE_KEY);
        $this->assertCount(1, $remember);
        $remembered = $this->client();
        $remembered['cookies'] = array_merge($remembered['cookies'], $remember);
        $this->send($remembered, 'GET', '/api/v1/account')->assertOk();
        $this->login($second, 'customer', $user->email)->assertOk();
        $this->send($first, 'PATCH', '/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'UpdatedPass123!', 'password_confirmation' => 'UpdatedPass123!'])->assertUnprocessable();
        $this->send($first, 'PATCH', '/api/v1/auth/password', ['current_password' => 'SyntheticPass123!', 'password' => 'UpdatedPass123!', 'password_confirmation' => 'UpdatedPass123!'])->assertOk();
        $this->send($second, 'GET', '/api/v1/account')->assertUnauthorized();
        $replay = $this->client();
        $replay['cookies'] = array_merge($replay['cookies'], $remember);
        $this->send($replay, 'GET', '/api/v1/account')->assertUnauthorized();
    }

    public function test_cross_realm_cookie_renaming_and_csrf_replay_do_not_authenticate(): void
    {
        $user = $this->account(CustomerAccount::class, 'cookie@example.invalid');
        $client = $this->client();
        $this->login($client, 'customer', $user->email)->assertOk();
        $other = $this->client('superadmin');
        $other['cookies']['mobist_superadmin_session'] = $client['cookies']['mobist_customer_session'];
        $other['tokens']['XSRF-TOKEN-superadmin'] = $client['tokens']['XSRF-TOKEN'];
        $this->send($other, 'GET', '/internal/superadmin/account')->assertUnauthorized();
        $other['tokens']['XSRF-TOKEN-superadmin'] = $client['tokens']['XSRF-TOKEN'];
        $this->login($other, 'superadmin', $user->email)->assertStatus(419);
    }

    public function test_production_session_cookies_are_secure_host_only_and_admin_host_is_separate(): void
    {
        $this->app->instance('env', 'production');
        config(['identity.customer_origin' => 'https://store.example.invalid', 'identity.admin_origin' => 'https://admin.example.invalid']);
        try {
            foreach (['customer', 'admin', 'superadmin', 'website_admin'] as $realm) {
                $url = $realm === 'customer' ? 'https://store.example.invalid/api/v1' : 'https://admin.example.invalid/internal/'.$realm;
                $response = $this->getJson($url.'/auth/csrf-cookie')->assertOk();
                $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'mobist_'.$realm.'_session');
                $this->assertTrue($cookie->isSecure());
                $this->assertTrue($cookie->isHttpOnly());
                $this->assertNull($cookie->getDomain());
                $this->assertSame('lax', $cookie->getSameSite());
            }
            $this->getJson('https://store.example.invalid/internal/admin/auth/csrf-cookie')->assertForbidden();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_logout_does_not_sign_out_another_realm_in_the_same_cookie_jar(): void
    {
        $customer = $this->account(CustomerAccount::class, 'logout@example.invalid');
        $super = $this->account(SuperAdmin::class, 'logout@example.invalid');
        $client = $this->client();
        $this->login($client, 'customer', $customer->email)->assertOk();
        $this->send($client, 'GET', '/internal/superadmin/auth/csrf-cookie')->assertOk();
        $this->login($client, 'superadmin', $super->email)->assertOk();
        $this->send($client, 'POST', '/internal/superadmin/auth/logout')->assertOk();
        $this->send($client, 'GET', '/internal/superadmin/account')->assertUnauthorized();
        $this->send($client, 'GET', '/api/v1/account')->assertOk()->assertJsonPath('data.id', $customer->public_id);
    }

    public function test_existing_argon_hash_can_authenticate_without_requiring_a_password_reset(): void
    {
        $user = $this->account(CustomerAccount::class, 'argon@example.invalid');
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash('SyntheticPass123!', PASSWORD_ARGON2ID)]);
        $client = $this->client();
        $this->login($client, 'customer', $user->email)->assertOk();
    }

    private function account(string $model, string $email, array $extra = [])
    {
        $user = new $model;
        $user->forceFill(['name' => 'Synthetic', 'email' => $email, 'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1, ...$extra])->save();

        return $user;
    }

    private function client(string $realm = 'customer', string $agent = 'Synthetic desktop'): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'token' => null, 'agent' => $agent];
        $path = $realm === 'customer' ? '/api/v1' : '/internal/'.$realm;
        $response = $this->send($client, 'GET', $path.'/auth/csrf-cookie')->assertOk();
        $client['token'] = $response->json('data.csrf_token');

        return $client;
    }

    private function login(array &$client, string $realm, string $email)
    {
        $prefix = $realm === 'customer' ? '/api/v1' : '/internal/'.$realm;

        return $this->send($client, 'POST', $prefix.'/auth/login', ['email' => $email, 'password' => 'SyntheticPass123!']);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $headers = [])
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'], ...$headers];
        $cookieName = preg_match('#^/internal/([^/]+)/#', $uri, $match) ? 'XSRF-TOKEN-'.$match[1] : 'XSRF-TOKEN';
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
