<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class H01WebsiteAuditTest extends TestCase
{
    use DatabaseTransactions;

    public function test_website_only_audit_is_permissioned_redacted_searchable_and_bounded(): void
    {
        config(['session.driver' => 'database']);
        $owner = $this->member('h01-website-audit-owner@example.invalid', ['website.audit.view']);
        $limited = $this->member('h01-website-audit-limited@example.invalid', ['reports.view']);
        $path = '/internal/admin/website-audit';
        $guest = $this->client();
        $this->send($guest, 'GET', $path)->assertUnauthorized();
        $viewer = $this->client();
        $this->login($viewer, $limited->email)->assertOk();
        $this->send($viewer, 'GET', $path)->assertForbidden();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        // A real HTTP record and a domain event have distinct evidence, never a fabricated method.
        $now = now();
        DB::table('admin_audit_logs')->insert([
            'actor_name' => 'H01 Owner', 'actor_email' => 'h01-website-audit-owner@example.invalid',
            'action' => 'website_page_published', 'method' => 'POST',
            'path' => '/internal/admin/platform/pages/12/publish',
            'payload' => json_encode(['password' => 'H01-PRIVATE-DO-NOT-DISPLAY'], JSON_THROW_ON_ERROR),
            'status_code' => 200, 'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('identity_audit_events')->insert([
            'realm' => 'admin', 'account_id' => $owner->id, 'action' => 'website_mode_published',
            'actor_name_snapshot' => 'H01 Owner', 'reference' => 'H01-PRIVATE-REFERENCE',
            'created_at' => $now,
        ]);
        DB::table('admin_audit_logs')->insert([
            'actor_name' => 'H01 Owner', 'action' => 'pos_sale', 'method' => 'POST',
            'path' => '/internal/admin/pos/sales', 'payload' => json_encode(['secret' => 'H01-POS-PRIVATE']),
            'status_code' => 200, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $response = $this->send($client, 'GET', $path)->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('website-audit-viewer')->has('records', 2)
                ->where('total', 2)->where('records.0.action', 'website_mode_published')
                ->where('records.0.method', null)->where('records.0.path', null)
                ->where('records.1.action', 'website_page_published')
                ->where('records.1.method', 'POST')->missing('records.1.payload')
                ->missing('records.0.reference'));
        $this->assertStringNotContainsString('H01-PRIVATE', $response->getContent());
        $this->assertStringNotContainsString('H01-POS-PRIVATE', $response->getContent());
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->send($client, 'GET', $path.'?method=POST')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 1)->has('records', 1)->where('records.0.method', 'POST'));
        $this->send($client, 'GET', $path.'?method=DELETE')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 0)->has('records', 0));
        $this->send($client, 'GET', $path.'?q=mode_published')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 1)->has('records', 1));
        $this->send($client, 'GET', $path.'?unexpected=1')->assertUnprocessable();
        $this->send($client, 'GET', $path.'?method=NOT-A-REAL-METHOD')->assertUnprocessable();
        for ($i = 0; $i < 51; $i++) {
            DB::table('identity_audit_events')->insert([
                'realm' => 'admin', 'account_id' => $owner->id, 'action' => 'website_h01_batch',
                'actor_name_snapshot' => 'H01 Owner', 'created_at' => $now->copy()->addSeconds($i + 1),
            ]);
        }
        $this->send($client, 'GET', $path.'?q=website_h01_batch')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 51)->has('records', 50)->where('current_page', 1)->where('last_page', 2));
        $this->send($client, 'GET', $path.'?q=website_h01_batch&page=2')->assertOk()->assertInertia(fn (Assert $page) => $page->where('total', 51)->has('records', 1)->where('current_page', 2));
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
