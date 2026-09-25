<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class H01WebsitePerformanceTest extends TestCase
{
    use DatabaseTransactions;

    public function test_combined_report_uses_one_order_set_and_preserves_safe_csv_and_permission_boundaries(): void
    {
        config(['session.driver' => 'database']);
        $owner = $this->member('h01-website-report-owner@example.invalid', ['website.orders.manage', 'website.conversions.view']);
        $limited = $this->member('h01-website-report-limited@example.invalid', ['website.orders.manage']);
        $url = '/internal/admin/website-performance';
        $guest = $this->client();
        $this->send($guest, 'GET', $url)->assertUnauthorized();
        $this->send($guest, 'GET', $url.'/data')->assertUnauthorized();
        $this->send($guest, 'GET', $url.'/orders.csv')->assertUnauthorized();
        $other = $this->client();
        $this->login($other, $limited->email)->assertOk();
        $this->send($other, 'GET', $url)->assertForbidden();
        $this->send($other, 'GET', $url.'/data')->assertForbidden();
        $this->send($other, 'GET', $url.'/orders.csv')->assertForbidden();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page->component('website-performance')->missing('identity.email')->where('identity.name', 'H01 fixture'));
        $commerce = $this->order('commerce', 'paid', '100.00', '=H01-CSV-INJECTION');
        $digital = $this->order('digital', 'paid', '50.00', 'H01 Digital Client');
        $hold = $this->order('commerce', 'paid_reconciliation', '75.00', 'H01 Hold Customer');
        DB::table('order_items')->insert(['order_id' => $commerce, 'item_type' => 'product',
            'title' => 'Synthetic phone', 'quantity' => 2, 'unit_price' => '50.00', 'line_total' => '100.00',
            'outlet_name' => 'H01 Synthetic Outlet', 'created_at' => now(), 'updated_at' => now()]);
        $date = now()->toDateString();
        $params = '?from='.$date.'&to='.$date;
        $data = $this->send($client, 'GET', $url.'/data'.$params)->assertOk()
            ->assertJsonPath('data.orders', 3)->assertJsonPath('data.paid_orders', 2)
            ->assertJsonPath('data.paid_reconciliation_orders', 1)
            ->assertJsonPath('data.paid_commerce_order_total', '100.00')
            ->assertJsonPath('data.paid_digital_order_total', '50.00')
            ->assertJsonPath('data.paid_order_total', '150.00')
            ->assertJsonPath('data.average_paid_order_total', '75.00')
            ->assertJsonPath('data.top_products.0.units', 2)
            ->assertJsonPath('data.top_products.0.line_total_before_order_discounts', '100.00');
        $this->assertStringContainsString('no-store', (string) $data->headers->get('Cache-Control'));
        $csv = $this->send($client, 'GET', $url.'/orders.csv'.$params)->assertOk()->assertJsonPath('data.count', 3);
        $content = $csv->json('data.csv');
        $this->assertStringContainsString('Order,Type,Customer,Mobile,Outlet,', $content);
        $this->assertStringContainsString(',digital,', $content);
        $this->assertStringContainsString("'=H01-CSV-INJECTION", $content);
        $this->assertStringContainsString('H01 Synthetic Outlet', $content);
        $this->send($client, 'GET', $url.'/data?from=2026-01-01&to=2025-01-01')->assertUnprocessable();
        $this->send($client, 'GET', $url.'/data?from=2024-01-01&to=2026-01-01')->assertStatus(422);
        $this->send($client, 'GET', $url.'/data?product=not-uuid')->assertUnprocessable();
        $this->send($client, 'GET', $url.'/data?unknown=1')->assertUnprocessable();
    }

    private function order(string $kind, string $status, string $total, string $name): int
    {
        return DB::table('orders')->insertGetId(['order_number' => 'H01-'.Str::uuid(),
            'order_type' => $kind, 'status' => $status === 'paid' ? 'confirmed' : 'pending',
            'customer_name' => $name, 'customer_mobile' => '03000000000',
            'subtotal' => $total, 'total' => $total, 'currency' => 'PKR',
            'payment_status' => $status, 'public_id' => (string) Str::uuid(),
            'created_at' => now(), 'updated_at' => now()]);
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
