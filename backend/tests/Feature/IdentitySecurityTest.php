<?php

namespace Tests\Feature;

use App\Identity\Access;
use App\Identity\CustomerProfile;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
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

    public function test_registration_rejects_admin_injection_and_keeps_customer_realm_isolated(): void
    {
        $client = $this->client();
        $before = $client['cookies']['mobist_customer_session'];
        $data = ['name' => 'Synthetic', 'email' => 'synthetic@example.invalid', 'mobile' => '03000000001', 'password' => 'SyntheticPass123!', 'password_confirmation' => 'SyntheticPass123!'];
        $this->send($client, 'POST', '/api/v1/auth/register', [...$data, 'permissions' => ['shop.sales']])->assertUnprocessable();
        $this->send($client, 'POST', '/api/v1/auth/register', $data)->assertCreated();
        $this->assertNotSame($before, $client['cookies']['mobist_customer_session']);
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->send($client, 'GET', '/internal/admin/account')->assertUnauthorized();
        $this->assertSame(1, DB::table('customers')->where('website_user_id', $user->id)->count());
    }

    public function test_only_customer_and_admin_routes_exist_and_same_admin_credential_serves_all_admin_surfaces(): void
    {
        $admin = $this->account(Admin::class, 'admin@example.invalid', ['permissions' => ['admin.integrations.manage', 'website.content.manage']]);
        $client = $this->client('admin');
        $this->login($client, 'admin', $admin->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/account')->assertOk()->assertJsonPath('data.email', $admin->email);
        $this->send($client, 'GET', '/internal/admin/integrations')->assertOk();
        $this->getJson('/internal/superadmin/auth/csrf-cookie')->assertNotFound();
        $this->getJson('/internal/website_admin/auth/csrf-cookie')->assertNotFound();
        $this->assertArrayNotHasKey('superadmin', config('auth.guards'));
        $this->assertArrayNotHasKey('website_admin', config('auth.guards'));
    }

    public function test_permissions_and_outlet_assignments_are_explicit_and_rechecked(): void
    {
        $admin = $this->account(Admin::class, 'operator@example.invalid', ['permissions' => ['shops.enter', 'shop.sales', 'website.content.manage']]);
        $outlet = new Outlet;
        $outlet->forceFill(['name' => 'Assigned', 'public_id' => (string) Str::uuid(), 'outlet_code' => '001'])->save();
        $other = new Outlet;
        $other->forceFill(['name' => 'Other', 'public_id' => (string) Str::uuid(), 'outlet_code' => '002'])->save();
        $admin->shops()->attach($outlet);
        $access = app(Access::class);
        $this->assertTrue($access->allows($admin, 'shop.sales', $outlet));
        $this->assertFalse($access->allows($admin, 'shop.sales', $other));
        $this->assertTrue($access->allows($admin, 'website.content.manage'));
        $this->assertFalse($access->allows($admin, 'website.publish'));
        $admin->shops()->detach($outlet);
        $this->assertFalse($access->allows($admin->fresh(), 'shop.sales', $outlet));
    }

    public function test_admin_reset_is_single_use_and_revokes_existing_admin_sessions(): void
    {
        $admin = $this->account(Admin::class, 'reset-admin@example.invalid');
        $outlet = new Outlet;
        $outlet->forceFill(['name' => 'Assigned', 'public_id' => (string) Str::uuid(), 'outlet_code' => '003'])->save();
        $admin->shops()->attach($outlet);
        $client = $this->client('admin');
        $this->login($client, 'admin', $admin->email)->assertOk();
        $token = Password::broker('admin')->createToken($admin);
        $payload = ['email' => $admin->email, 'token' => $token, 'password' => 'UpdatedPass123!', 'password_confirmation' => 'UpdatedPass123!'];
        $this->send($client, 'POST', '/internal/admin/auth/reset-password', $payload)->assertOk();
        $this->assertTrue(Hash::check('UpdatedPass123!', $admin->fresh()->password));
        $this->send($client, 'GET', '/internal/admin/account')->assertUnauthorized();
        $replay = $this->client('admin');
        $this->send($replay, 'POST', '/internal/admin/auth/reset-password', $payload)->assertUnprocessable();
        $this->assertSame(0, DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $admin->id)->whereNull('revoked_at')->count());
    }

    public function test_csrf_rate_limit_disabled_accounts_and_production_cookie_boundaries_hold(): void
    {
        $client = $this->client();
        $this->send($client, 'POST', '/api/v1/auth/login', ['email' => 'missing@example.invalid', 'password' => 'password'], false)->assertStatus(419);
        $blocked = $this->account(CustomerAccount::class, 'blocked@example.invalid', ['archived_at' => now()]);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->login($client, 'customer', $blocked->email)->assertUnprocessable();
        }
        $this->login($client, 'customer', $blocked->email)->assertStatus(429);
        $this->app->instance('env', 'production');
        config(['identity.customer_origin' => 'https://store.example.invalid', 'identity.admin_origin' => 'https://admin.example.invalid']);
        try {
            foreach (['customer' => 'https://store.example.invalid/api/v1', 'admin' => 'https://admin.example.invalid/internal/admin'] as $realm => $url) {
                $response = $this->getJson($url.'/auth/csrf-cookie')->assertOk();
                $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'mobist_'.$realm.'_session');
                $this->assertTrue($cookie->isSecure());
                $this->assertTrue($cookie->isHttpOnly());
                $this->assertNull($cookie->getDomain());
            }
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_customer_ownership_never_follows_matching_contact_data(): void
    {
        $a = $this->account(CustomerAccount::class, 'a@example.invalid');
        $b = $this->account(CustomerAccount::class, 'b@example.invalid');
        $uuid = (string) Str::uuid();
        DB::table('orders')->insert(['public_id' => $uuid, 'order_number' => 'MT22-OWN', 'order_type' => 'mobile', 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000001', 'customer_email' => $b->email, 'user_id' => $a->id]);
        $this->assertSame($a->id, app(Access::class)->ownOrder($a, $uuid)->user_id);
        $this->expectException(HttpException::class);
        app(Access::class)->ownOrder($b, $uuid);
    }

    public function test_historical_order_capability_is_owner_issued_signed_bounded_and_read_only(): void
    {
        $customer = $this->account(CustomerAccount::class, 'signed-owner@example.invalid');
        $other = $this->account(CustomerAccount::class, 'signed-other@example.invalid');
        $uuid = (string) Str::uuid();
        DB::table('orders')->insert(['public_id' => $uuid, 'order_number' => 'MT-W01-SIGNED', 'order_type' => 'mobile',
            'customer_name' => 'Signed Owner', 'customer_mobile' => '03000000001', 'customer_email' => $customer->email,
            'user_id' => $customer->id, 'status' => 'completed']);

        $orderId = DB::table('orders')->where('public_id', $uuid)->value('id');
        DB::table('website_order_payment_terms')->insert([
            'order_id' => $orderId, 'gateway' => 'cod', 'label' => 'Private saved terms',
            'instructions' => 'Owner-only payment instructions.', 'cod_min_amount' => '100.00',
            'cod_max_amount' => '500.00', 'presentation_version' => 1, 'created_at' => now(),
        ]);
        $ownerClient = $this->client();
        $this->login($ownerClient, 'customer', $customer->email)->assertOk();
        $signed = $this->send($ownerClient, 'GET', '/api/v1/orders/'.$uuid)->assertOk()->json('data.signed_access_url');
        $this->assertSame('Private saved terms', $this->send($ownerClient, 'GET', '/api/v1/orders/'.$uuid)->assertOk()->json('data.payment_terms.label'));
        $this->assertIsString($signed);
        $signedResponse = $this->getJson($signed)->assertOk()->assertJsonPath('data.number', 'MT-W01-SIGNED');
        $this->assertStringContainsString('no-store', (string) $signedResponse->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('payment_terms', $signedResponse->json('data'));
        $this->assertArrayNotHasKey('payments', $signedResponse->json('data'));
        $this->getJson($signed.'&signature=invalid')->assertForbidden();

        $expired = URL::temporarySignedRoute('api.customer.orders.signed', now()->subMinute(), ['order' => $uuid]);
        $this->getJson($expired)->assertForbidden();
        $otherClient = $this->client();
        $this->login($otherClient, 'customer', $other->email)->assertOk();
        $this->send($otherClient, 'GET', '/api/v1/orders/'.$uuid)->assertNotFound();
        $this->postJson(parse_url($signed, PHP_URL_PATH).'?'.parse_url($signed, PHP_URL_QUERY), [])->assertMethodNotAllowed();
    }

    public function test_customer_profile_and_photo_are_private_self_scoped_validated_and_audited(): void
    {
        Storage::fake('local');
        $customer = $this->account(CustomerAccount::class, 'profile-owner@example.invalid', ['mobile' => '03000000011']);
        DB::table('customers')->insert(['website_user_id' => $customer->id, 'display_name' => 'Synthetic', 'mobile' => '03000000011', 'public_id' => (string) Str::uuid()]);
        $client = $this->client();
        $this->login($client, 'customer', $customer->email)->assertOk();
        $this->send($client, 'PATCH', '/api/v1/account/profile', ['name' => 'Updated Customer', 'mobile' => '03000000012'])
            ->assertOk()->assertJsonPath('data.name', 'Updated Customer')->assertJsonMissingPath('data.email');
        $this->send($client, 'PATCH', '/api/v1/account/profile', ['name' => 'Injected', 'mobile' => '03000000013', 'email' => 'admin@example.invalid'])
            ->assertUnprocessable();

        $profiles = app(CustomerProfile::class);
        $request = Request::create('/api/v1/account/photo', 'POST', [], [], [
            'profile_photo' => UploadedFile::fake()->createWithContent('profile.png', base64_decode(
                'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true
            )),
        ]);
        $profiles->upload($customer->fresh(), $request);
        $path = $customer->fresh()->profile_photo_path;
        $this->assertNotNull($path);
        Storage::disk('local')->assertExists($path);
        $response = $profiles->show($customer->fresh());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $profiles->remove($customer->fresh());
        Storage::disk('local')->assertMissing($path);
        $this->assertNull($customer->fresh()->profile_photo_path);
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $customer->id)->where('action', 'customer_profile_updated')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $customer->id)->where('action', 'customer_profile_photo_updated')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $customer->id)->where('action', 'customer_profile_photo_removed')->count());
    }

    private function account(string $model, string $email, array $extra = [])
    {
        $user = new $model;
        $user->forceFill(['name' => 'Synthetic', 'email' => $email, 'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1, ...$extra])->save();

        return $user;
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
        return $this->send($client, 'POST', $realm === 'customer' ? '/api/v1/auth/login' : '/internal/admin/auth/login', ['email' => $email, 'password' => 'SyntheticPass123!']);
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true)
    {
        $cookieName = str_starts_with($uri, '/internal/admin/') ? 'XSRF-TOKEN-admin' : 'XSRF-TOKEN';
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent']];
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
