<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Role;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_outlet_profile_edit_is_scoped_versioned_audited_and_cannot_change_identity(): void
    {
        $assigned = $this->outlet('Assigned Profile', '049');
        $other = $this->outlet('Other Profile', '050');
        $editor = $this->member('profile-editor@example.invalid', ['shops.enter', 'shop.profile']);
        $editor->shops()->attach($assigned);
        $client = $this->client();
        $this->login($client, $editor->email)->assertOk();
        $uri = '/internal/admin/pos/outlet-profile';
        $this->send($client, 'GET', '/internal/admin/pos/workspace/profile')->assertOk();
        $this->send($client, 'GET', $uri)->assertOk()->assertJsonPath('data.outlet_code', '049')
            ->assertJsonPath('data.name', 'Assigned Profile');
        $input = ['version' => 1, 'name' => 'Assigned Updated', 'business_legal_name' => 'Synthetic Shop',
            'business_phone' => '+92 300 1234567', 'business_whatsapp' => '03001234567',
            'business_address' => 'Synthetic address', 'business_hours' => '09:00 - 18:00'];
        $this->send($client, 'PATCH', $uri, [...$input, 'outlet_code' => '051'])->assertUnprocessable();
        $this->send($client, 'PATCH', $uri, [...$input, 'business_phone' => '12345'])->assertUnprocessable();
        $this->send($client, 'PATCH', $uri, $input)->assertOk()->assertJsonPath('data.name', 'Assigned Updated')
            ->assertJsonPath('data.business_phone', '03001234567')->assertJsonPath('data.version', 2);
        $this->assertSame('049', $assigned->fresh()->outlet_code);
        $this->assertSame('Other Profile', $other->fresh()->name);
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $editor->id)
            ->where('action', 'outlet_profile_updated')->where('outlet_id', $assigned->id)->count());
        $this->send($client, 'PATCH', $uri, $input)->assertStatus(409);
        $this->send($client, 'PATCH', $uri, [...$input, 'version' => 2, 'outlet_id' => $other->public_id])->assertUnprocessable();
        $noPermission = $this->member('profile-denied@example.invalid', ['shops.enter', 'shop.sales']);
        $noPermission->shops()->attach($assigned);
        $denied = $this->client();
        $this->login($denied, $noPermission->email)->assertOk();
        $this->send($denied, 'GET', $uri)->assertForbidden();
        $this->send($denied, 'PATCH', $uri, [...$input, 'version' => 2])->assertForbidden();
        $this->send($denied, 'GET', '/internal/admin/pos/workspace/profile')->assertForbidden();
        $assigned->forceFill(['archived_at' => now()])->save();
        $this->send($client, 'GET', $uri)->assertForbidden();
        $this->send($client, 'PATCH', $uri, [...$input, 'version' => 2])->assertForbidden();
    }

    public function test_protected_full_access_outlet_create_archive_and_direct_permission_barriers(): void
    {
        $existing = $this->outlet('Existing Protected Outlet', '051');
        $owner = $this->member('outlet-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($existing);
        $role = Role::where('name', 'Full Access')->firstOrFail();
        $owner->roles()->attach($role->id, ['assigned_at' => now()]);
        $limited = $this->member('outlet-limited@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $limited->shops()->attach($existing);
        $limitedClient = $this->client();
        $this->login($limitedClient, $limited->email)->assertOk();
        $uri = '/internal/admin/outlet-management';
        $this->send($limitedClient, 'GET', $uri)->assertForbidden();
        $this->send($limitedClient, 'GET', $uri.'/data')->assertForbidden();
        $this->send($limitedClient, 'POST', $uri, ['name' => 'Denied', 'business_address' => 'Denied'])->assertForbidden();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', $uri)->assertOk();
        $this->send($client, 'GET', $uri.'/data')->assertOk();
        $this->send($client, 'POST', $uri, ['name' => 'Injected', 'business_address' => 'Address',
            'outlet_code' => '999'])->assertUnprocessable();
        $made = $this->send($client, 'POST', $uri,
            ['name' => 'New Protected Outlet', 'business_address' => 'Synthetic Karachi'])->assertCreated();
        $id = $made->json('data.id');
        $code = $made->json('data.outlet_code');
        $this->assertMatchesRegularExpression('/^[0-9]{3}$/', $code);
        $this->assertNotSame('051', $code);
        $this->assertTrue($owner->shops()->where('outlets.public_id', $id)->exists());
        $this->assertNull(Outlet::where('public_id', $id)->value('legacy_password'));
        $this->send($client, 'POST', $uri.'/'.$id.'/archive', ['version' => 2])->assertStatus(409);
        $linked = DB::table('suppliers')->insertGetId(['public_id' => (string) Str::uuid(),
            'outlet_id' => Outlet::where('public_id', $id)->value('id'), 'supplier_code' => 'P01-LINK',
            'name' => 'Synthetic retained supplier', 'created_by_admin_id' => $owner->id,
            'updated_by_admin_id' => $owner->id]);
        $this->send($client, 'POST', $uri.'/'.$id.'/archive', ['version' => 1])->assertStatus(409);
        $this->assertNull(Outlet::where('public_id', $id)->value('archived_at'));
        DB::table('suppliers')->where('id', $linked)->delete();

        $this->send($limitedClient, 'POST', $uri.'/'.$id.'/archive', ['version' => 1])->assertForbidden();
        $this->send($client, 'POST', $uri.'/'.$id.'/archive', ['version' => 1])->assertOk()
            ->assertJsonPath('data.status', 'archived')->assertJsonPath('data.outlet_code', $code);
        $this->assertNotNull(Outlet::where('public_id', $id)->value('archived_at'));
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'outlet_archived')->where('outlet_id', Outlet::where('public_id', $id)->value('id'))->count());
        $this->send($client, 'POST', $uri, ['name' => 'Second Outlet', 'business_address' => 'Synthetic'])->assertCreated()
            ->assertJsonMissing(['outlet_code' => $code]);
        $this->assertSame('051', $existing->fresh()->outlet_code);
    }

    public function test_account_and_recovery_pages_use_only_the_existing_admin_realm(): void
    {
        $guest = $this->client();
        $this->send($guest, 'GET', '/internal/admin/manage-account')->assertUnauthorized();
        $this->send($guest, 'GET', '/internal/admin/forgot-password')->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('admin-recovery')->where('mode', 'request'));
        $resetPage = $this->send($guest, 'GET', '/internal/admin/reset-password?email=x@example.invalid&token=untrusted')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin-recovery')->where('mode', 'reset')->missing('token'));
        $this->assertStringContainsString('no-store', (string) $resetPage->headers->get('Cache-Control'));
        config(['identity.recovery_delivery_enabled' => false]);
        $this->send($guest, 'POST', '/internal/admin/auth/forgot-password', ['email' => 'x@example.invalid'])->assertStatus(503);
        $outlet = $this->outlet('Account outlet', '057');
        $actor = $this->member('account-page@example.invalid', ['shops.enter', 'shop.sales']);
        $actor->shops()->attach($outlet);
        $user = $this->client();
        $this->login($user, $actor->email)->assertOk();
        $this->send($user, 'GET', '/internal/admin/manage-account')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin-account')
                ->where('identity.email', $actor->email)->missing('identity.password'));
    }

    public function test_admin_photo_is_private_self_scoped_mime_limited_and_audited(): void
    {
        Storage::fake('local');
        $outlet = $this->outlet('Photo outlet', '059');
        $owner = $this->member('photo-owner@example.invalid', ['shops.enter', 'shop.sales']);
        $other = $this->member('photo-other@example.invalid', ['shops.enter', 'shop.sales']);
        $owner->shops()->attach($outlet);
        $other->shops()->attach($outlet);
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $url = '/internal/admin/manage-account/photo';
        $this->send($client, 'GET', $url)->assertNotFound();
        $this->uploadPhoto($client, $url, new UploadedFile(public_path('icon-192.png'), 'profile.png', 'image/png', null, true), ['admin_id' => $other->public_id])->assertUnprocessable();
        $this->uploadPhoto($client, $url, UploadedFile::fake()->createWithContent('malicious.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))->assertUnprocessable();
        $this->assertNull($owner->fresh()->profile_photo);
        $this->uploadPhoto($client, $url, new UploadedFile(public_path('icon-192.png'), 'profile.png', 'image/png', null, true))->assertOk()->assertJsonPath('data.has_photo', true);
        $path = $owner->fresh()->profile_photo;
        $this->assertStringStartsWith('admin-profile-photos/'.$owner->public_id.'/', $path);
        Storage::disk('local')->assertExists($path);
        $this->send($client, 'GET', $url)->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
        $otherClient = $this->client();
        $this->login($otherClient, $other->email)->assertOk();
        $this->send($otherClient, 'GET', $url)->assertNotFound();
        $this->send($otherClient, 'DELETE', $url)->assertOk()->assertJsonPath('data.has_photo', false);
        Storage::disk('local')->assertExists($path);
        $this->send($client, 'DELETE', $url)->assertOk()->assertJsonPath('data.has_photo', false);
        $this->assertNull($owner->fresh()->profile_photo);
        Storage::disk('local')->assertMissing($path);
        $this->send($client, 'GET', $url)->assertNotFound();
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)->where('action', 'admin_profile_photo_updated')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)->where('action', 'admin_profile_photo_removed')->count());
    }

    private function uploadPhoto(array &$client, string $url, UploadedFile $file, array $fields = [])
    {
        return $this->call('POST', $url, $fields, $client['cookies'], ['profile_photo' => $file], [
            'HTTP_ACCEPT' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'],
            'HTTP_X_CSRF_TOKEN' => $client['tokens']['XSRF-TOKEN-admin'],
        ]);
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
