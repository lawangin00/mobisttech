<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

final class W04AdminPaymentHttpTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_payment_channel_status_requires_admin_login_and_website_payment_settings_permission(): void
    {
        $path = '/internal/admin/website/payment-channels';
        $this->getJson($path)->assertUnauthorized();

        [$restricted, $restrictedOutlet] = $this->admin(['shops.enter', 'website.payment-credentials.manage']);
        $restrictedClient = $this->client();
        $this->send($restrictedClient, 'POST', '/internal/admin/auth/login', [
            'email' => $restricted->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($restrictedClient, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $restrictedOutlet->public_id,
        ])->assertOk();
        $this->send($restrictedClient, 'GET', $path)->assertForbidden();

        [$admin, $outlet] = $this->admin(['shops.enter', 'website.payments.manage']);
        $client = $this->client();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $admin->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $outlet->public_id,
        ])->assertOk();

        config()->set('commerce.providers.jazzcash', [
            'enabled' => true,
            'merchant' => 'w04-private-merchant-marker',
            'mode' => 'sandbox',
            'credentials' => Crypt::encryptString('w04-private-credential-marker'),
        ]);
        $response = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.1.code', 'jazzcash')
            ->assertJsonPath('data.1.enabled', true)
            ->assertJsonPath('data.1.available', false);
        $this->assertSame('private, no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(['code', 'label', 'enabled', 'merchant_configured', 'available'],
            array_keys($response->json('data.1')));
        $this->assertStringNotContainsString('w04-private-merchant-marker', $response->getContent());
        $this->assertStringNotContainsString('w04-private-credential-marker', $response->getContent());
        $this->assertStringNotContainsString('credentials', $response->getContent());
    }

    private function admin(array $permissions): array
    {
        $admin = new Admin;
        $admin->forceFill([
            'name' => 'W04 HTTP Admin', 'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make('SyntheticPass123!'), 'permissions' => $permissions,
            'auth_version' => 1,
        ])->save();
        $outlet = new Outlet;
        $outlet->forceFill([
            'name' => 'W04 HTTP Outlet', 'public_id' => (string) Str::uuid(),
            'outlet_code' => (string) random_int(100, 999),
        ])->save();
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic W04 HTTP client'];
        $response = $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie');
        $response->assertOk();

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
