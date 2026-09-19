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

    public function test_full_access_edits_unassigned_outlet_only_after_explicit_password_confirmation(): void
    {
        $assigned = $this->outlet('Owner assigned', '061');
        $unassigned = $this->outlet('Other outlet', '062');
        $owner = $this->member('unassigned-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($assigned);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $limited = $this->member('unassigned-limited@example.invalid', ['shops.enter', 'shop.profile']);
        $limited->shops()->attach($assigned);
        $uri = '/internal/admin/outlet-management/'.$unassigned->public_id.'/profile';
        $input = ['version' => 1, 'name' => 'Approved unassigned edit',
            'business_address' => 'Synthetic owner-approved contact'];
        $guest = $this->client();
        $this->send($guest, 'GET', $uri)->assertUnauthorized();
        $ownerClient = $this->client();
        $this->login($ownerClient, $owner->email)->assertOk();
        $this->send($ownerClient, 'GET', $uri)->assertOk()->assertJsonPath('data.outlet_code', '062');
        $this->send($ownerClient, 'PATCH', $uri, $input)->assertForbidden();
        $this->send($ownerClient, 'POST', '/internal/admin/auth/confirm-password',
            ['password' => 'SyntheticPass123!'])->assertOk();
        $this->send($ownerClient, 'PATCH', $uri, [...$input, 'outlet_code' => '099'])->assertUnprocessable();
        $this->send($ownerClient, 'PATCH', $uri, $input)->assertOk()
            ->assertJsonPath('data.name', 'Approved unassigned edit')->assertJsonPath('data.version', 2);
        $this->assertSame('062', $unassigned->fresh()->outlet_code);
        $this->assertFalse($owner->shops()->whereKey($unassigned->id)->exists());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'outlet_profile_updated')->where('outlet_id', $unassigned->id)->count());
        $denied = $this->client();
        $this->login($denied, $limited->email)->assertOk();
        $this->send($denied, 'GET', $uri)->assertForbidden();
        $this->send($denied, 'PATCH', $uri, [...$input, 'version' => 2])->assertForbidden();
        $unassigned->forceFill(['archived_at' => now()])->save();
        $this->send($ownerClient, 'GET', $uri)->assertForbidden();
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

    public function test_closed_cash_history_archive_retains_immutable_records_and_forbids_operational_access(): void
    {
        $active = $this->outlet('Active fallback', '061');
        $historical = $this->outlet('Historical cash outlet', '062');
        $owner = $this->member('archive-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($active);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $operator = $this->member('archive-operator@example.invalid', ['shops.enter', 'shop.sales', 'shop.cash']);
        $operator->shops()->attach([$historical->id, $active->id]);
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $base = '/internal/admin/outlet-management/'.$historical->public_id;
        $this->send($client, 'GET', $base.'/history')->assertStatus(409);
        $sessionId = DB::table('cash_sessions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'outlet_id' => $historical->id,
            'opened_by_admin_id' => $owner->id, 'business_date' => now()->toDateString(),
            'status' => 'open', 'opening_cash' => '100.00', 'opened_at' => now()->subHour(),
        ]);
        $this->send($client, 'POST', $base.'/archive', ['version' => 1])->assertStatus(409);
        $snapshot = json_encode(['contract' => 'synthetic-closed-cash-history', 'amount' => '100.00'], JSON_THROW_ON_ERROR);
        DB::table('cash_sessions')->where('id', $sessionId)->update([
            'status' => 'closed', 'open_outlet_guard' => null,
            'expected_cash' => '100.00', 'actual_cash' => '100.00', 'variance_amount' => '0.00',
            'closing_snapshot' => $snapshot, 'snapshot_sha256' => hash('sha256', $snapshot),
            'closed_at' => now(), 'closed_by_admin_id' => $owner->id,
        ]);
        $this->send($client, 'POST', $base.'/archive', ['version' => 1])->assertOk()
            ->assertJsonPath('data.status', 'archived')->assertJsonPath('data.outlet_code', '062');
        $history = $this->send($client, 'GET', $base.'/history')->assertOk()
            ->assertJsonPath('data.cash_session_count', 1)
            ->assertJsonPath('data.cash_sessions.0.status', 'closed')
            ->assertJsonPath('data.cash_sessions.0.actual_cash', '100.00');
        $this->assertSame('100.00', (string) DB::table('cash_sessions')->where('id', $sessionId)->value('actual_cash'));
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('outlet_id', $historical->id)->where('action', 'outlet_archived')->count());
        $this->assertTrue($operator->shops()->whereKey($historical->id)->exists());
        $this->assertNull($historical->fresh()->legacy_password);
        $operatorClient = $this->client();
        $this->login($operatorClient, $operator->email)->assertOk();
        // Authorization matrix: valid fallback remains usable; the archive never re-enters the chooser.
        $this->send($operatorClient, 'GET', $base.'/history')->assertForbidden();
        $this->send($operatorClient, 'GET', '/internal/admin/pos/workspace/sales')->assertOk();
        $this->send($operatorClient, 'GET', '/internal/admin/outlets')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $active->public_id);
        $this->send($operatorClient, 'POST', '/internal/admin/outlets/select',
            ['outlet_id' => $historical->public_id])->assertNotFound();
        $this->send($operatorClient, 'POST', '/internal/admin/outlets/select',
            ['outlet_id' => $active->public_id])->assertOk();
        $archivedOnly = $this->member('archive-only@example.invalid', ['shops.enter', 'shop.sales']);
        $archivedOnly->shops()->attach($historical);
        $archivedOnlyClient = $this->client();
        $this->login($archivedOnlyClient, $archivedOnly->email)->assertForbidden();
        $this->send($client, 'POST', $base.'/archive', ['version' => 2])->assertStatus(409);
        $this->assertSame($sessionId, (int) DB::table('cash_sessions')->where('outlet_id', $historical->id)->value('id'));
        // A stale pre-archive model/actor must never reopen cash on this archived outlet.
        try {
            app(\App\Cash\CashSessionOperations::class)->open($operator, $historical, 'd03-archived-no-reopen', ['opening_cash' => '10.00']);
            $this->fail('An archived outlet unexpectedly accepted a cash session.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('cash_sessions')->where('outlet_id', $historical->id)->where('status', 'open')->count());
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

    public function test_protected_pos_audit_view_is_read_only_filtered_and_never_exposes_payloads(): void
    {
        $first = $this->outlet('Audit North', '071');
        $second = $this->outlet('Audit South', '072');
        $owner = $this->member('audit-owner@example.invalid', ['shops.enter', 'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($first);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $limited = $this->member('audit-limited@example.invalid', ['shops.enter', 'team-members.full-access.assign', 'admin.business-profile.manage']);
        $limited->shops()->attach($first);
        $operator = $this->member('audit-operator@example.invalid', ['shops.enter', 'shop.sales']);
        $operator->shops()->attach($first);
        $url = '/internal/admin/pos/audit';
        $this->get($url)->assertUnauthorized();
        foreach ([$limited, $operator] as $unprivileged) {
            $client = $this->client(); $this->login($client, $unprivileged->email)->assertOk();
            $this->send($client, 'GET', $url)->assertForbidden();
            $this->send($client, 'GET', $url.'?q=private')->assertForbidden();
        }
        foreach ([[$first, 'Synthetic North Event', 'POST', 'north-secret'], [$second, 'Synthetic South Event', 'GET', 'south-secret']] as [$outlet, $action, $method, $secret]) {
            DB::table('pos_audit_logs')->insert(['actor_type' => 'admin', 'actor_id' => $owner->id,
                'actor_name' => 'Synthetic Auditor', 'actor_email' => $owner->email, 'outlet_id' => $outlet->id,
                'action' => $action, 'method' => $method, 'path' => '/internal/admin/pos/safe',
                'payload' => json_encode(['password' => $secret]), 'ip_address' => '198.51.100.42',
                'user_agent' => 'private device metadata', 'status_code' => 200, 'created_at' => now(), 'updated_at' => now()]);
        }
        $client = $this->client(); $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', $url.'?outlet='.$first->public_id.'&method=POST&q=North')->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertInertia(fn (Assert $page) => $page->component('pos-audit-viewer')
                ->has('records', 1)->where('records.0.action', 'Synthetic North Event')
                ->where('records.0.outlet_code', '071')->where('records.0.method', 'POST')
                ->missing('records.0.payload')->missing('records.0.ip_address')
                ->missing('records.0.user_agent'));
        $this->send($client, 'GET', $url.'?outlet='.$second->public_id.'&method=GET')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('pos-audit-viewer')->has('records', 1)
                ->where('records.0.action', 'Synthetic South Event'));
        $this->send($client, 'GET', $url.'?outlet='.Str::uuid())->assertNotFound();
        $this->send($client, 'GET', $url.'?outlet='.$first->public_id.'&method=TRACE')->assertUnprocessable();
        $this->send($client, 'GET', $url.'?include_payload=1')->assertUnprocessable();
        $this->send($client, 'GET', $url.'?q='.str_repeat('x', 101))->assertUnprocessable();
        $this->send($client, 'POST', $url)->assertStatus(405);
        $this->assertSame(2, DB::table('pos_audit_logs')->where('actor_id', $owner->id)->count());
        for ($i = 0; $i < 61; $i++) {
            DB::table('pos_audit_logs')->insert(['actor_type' => 'admin', 'actor_id' => $owner->id,
                'actor_name' => 'Synthetic Paged', 'actor_email' => $owner->email,
                'outlet_id' => $first->id, 'action' => 'Synthetic Page '.$i,
                'method' => 'POST', 'path' => '/internal/admin/pos/safe',
                'payload' => json_encode(['password' => '[REDACTED]']), 'status_code' => 200,
                'created_at' => now(), 'updated_at' => now()]);
        }
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('records', 60)->where('total', 63)->where('last_page', 2));
        $this->send($client, 'GET', $url.'?page=2')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->has('records', 3)->where('current_page', 2)->where('total', 63));
    }

    public function test_protected_portal_preferences_validate_and_persist_only_registered_keys(): void
    {
        $owner = $this->member('pref-owner@example.invalid', ['config.portal-presentation.manage',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $owner->shops()->attach($this->outlet('Preferences Owner Outlet', '059'));
        $limited = $this->member('pref-limited@example.invalid', ['config.portal-presentation.manage']);
        $url = '/internal/admin/pos/portal-preferences';
        $guest = $this->client();
        $this->send($guest, 'GET', $url)->assertUnauthorized();
        $this->send($guest, 'PUT', $url, [])->assertUnauthorized();
        $denied = $this->client(); $this->login($denied, $limited->email)->assertOk();
        $this->send($denied, 'GET', $url)->assertForbidden();
        $this->send($denied, 'PUT', $url, [])->assertForbidden();
        $client = $this->client(); $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('pos-portal-preferences')->where('values.invoice_page_length', '15')
            ->where('values.inventory_page_length', '10')->where('values.auto_focus_search', true)
            ->where('values.remember_search', false)->has('options.invoice_search_category', 8));
        $values = app(\App\Pos\PortalPreferences::class)->current();
        $values['invoice_page_length'] = '50'; $values['inventory_page_length'] = '100';
        $values['invoice_search_category'] = 'customer_name'; $values['remember_search'] = true;
        $this->send($client, 'PUT', $url, [...$values, 'outlet_id' => 'unauthorized'])->assertUnprocessable();
        // Identity limiter is 5/min by path+IP; keep guest, denied, invalid and successful writes below cap.
        $this->assertFalse(in_array('500', app(\App\Pos\PortalPreferences::class)->catalogue($owner)['options']['invoice_page_length'], true));
        $this->assertSame(0, DB::table('pos_settings')->where('group', 'portal')->count());
        $this->send($client, 'PUT', $url, $values)->assertOk()
            ->assertJsonPath('data.invoice_page_length', '50')->assertJsonPath('data.remember_search', true);
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('values.invoice_page_length', '50')->where('values.remember_search', true));
        $this->assertSame(9, DB::table('pos_settings')->where('group', 'portal')->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'pos_portal_preferences_updated')->count());
    }

    public function test_master_data_admin_ui_and_mutations_respect_role_protected_category_and_history(): void
    {
        $outlet = $this->outlet('Master options outlet', '081');
        $owner = $this->member('master-ui-owner@example.invalid', ['shops.enter', 'config.master-data.manage']);
        $owner->shops()->attach($outlet);
        $denied = $this->member('master-ui-sales@example.invalid', ['shops.enter', 'shop.sales']);
        $denied->shops()->attach($outlet);
        $url = '/internal/admin/pos/master-data';
        $guest = $this->client(); $this->send($guest, 'GET', $url)->assertUnauthorized();
        $sales = $this->client(); $this->login($sales, $denied->email)->assertOk();
        $this->send($sales, 'GET', $url)->assertForbidden();
        $this->send($sales, 'GET', '/internal/admin/pos/workspace/master-data')->assertForbidden();
        $client = $this->client(); $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/master-data')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('view.workspace.key', 'master-data'));
        $this->send($client, 'GET', $url)->assertOk()->assertJsonPath('data.lists.product_brand', 'Brands');
        $created = $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'create',
            'label' => 'Synthetic Managed P02 Brand'])->assertOk();
        $id = $created->json('data.id');
        $this->assertSame('synthetic_managed_p02_brand', DB::table('pos_master_data_options')->where('id', $id)->value('code'));
        $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'deactivate', 'id' => $id])->assertOk();
        $this->assertSame(0, (int) DB::table('pos_master_data_options')->where('id', $id)->value('is_active'));
        $this->send($client, 'GET', $url)->assertOk()->assertJsonFragment(['code' => 'synthetic_managed_p02_brand', 'is_active' => false]);
        $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'delete', 'id' => $id])->assertOk();
        $this->assertFalse(DB::table('pos_master_data_options')->where('id', $id)->exists());
    }

    public function test_p02_http_option_editor_keeps_stable_code_and_denies_referenced_deletion(): void
    {
        $outlet = $this->outlet('P02 Edit Outlet', '082');
        $actor = $this->member('p02-edit@example.invalid', ['shops.enter', 'config.master-data.manage']);
        $actor->shops()->attach($outlet);
        $client = $this->client(); $this->login($client, $actor->email)->assertOk();
        $url = '/internal/admin/pos/master-data';
        $id = $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'create', 'label' => 'P02 Original'])->assertOk()->json('data.id');
        $code = DB::table('pos_master_data_options')->where('id', $id)->value('code');
        $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'update', 'id' => $id,
            'label' => 'P02 Edited', 'sort_order' => 37])->assertOk();
        $this->assertSame($code, DB::table('pos_master_data_options')->where('id', $id)->value('code'));
        $this->assertSame(37, (int) DB::table('pos_master_data_options')->where('id', $id)->value('sort_order'));
        $this->send($client, 'GET', $url)->assertOk()->assertJsonFragment(['code' => $code, 'label' => 'P02 Edited', 'sort_order' => 37]);
        DB::table('pos_master_data_usages')->insert(['master_data_option_id' => $id, 'usage_type' => 'product',
            'usage_id' => 'synthetic-p02-history', 'usage_field' => 'brand']);
        $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'delete', 'id' => $id])->assertUnprocessable();
        $this->assertSame('P02 Edited', DB::table('pos_master_data_options')->where('id', $id)->value('label'));
        $this->send($client, 'GET', $url)->assertOk()->assertJsonFragment(['code' => $code, 'usages_count' => 1]);
        $this->send($client, 'POST', $url, ['list' => 'product_brand', 'action' => 'update', 'id' => $id,
            'label' => 'Injected', 'code' => 'rewritten'])->assertUnprocessable();
        $protected = DB::table('pos_master_data_options')->where('list_key', 'product_category')->value('id');
        $this->send($client, 'POST', $url, ['list' => 'product_category', 'action' => 'delete', 'id' => $protected])->assertUnprocessable();
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $actor->id)
            ->where('action', 'master_data_update')->count());
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
