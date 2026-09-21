<?php

namespace Tests\Feature;

use App\Addendum\MoneySnapshot;
use App\Cash\CashSessionOperations;
use App\Documents\CanonicalDocuments;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockUnit;
use App\Pos\PortalPreferences;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

    public function test_fresh_protected_owner_can_sign_in_before_first_outlet_without_shop_access(): void
    {
        $owner = $this->member('fresh-bootstrap-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $unassigned = $this->member('fresh-bootstrap-operator@example.invalid', ['shops.enter', 'shop.sales']);
        $unprotected = $this->member('fresh-bootstrap-unprotected@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $this->assertSame(0, $owner->shops()->count());
        $operatorClient = $this->client();
        $this->login($operatorClient, $unassigned->email)->assertForbidden();
        $unprotectedClient = $this->client();
        $this->login($unprotectedClient, $unprotected->email)->assertForbidden();

        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shell.active_outlet', null)
                ->has('shell.outlets', 0)->has('shell.navigation', 0)
                ->where('shell.can_manage_outlets', true));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/sales')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/inventory')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/outlet-management')->assertOk();
        $created = $this->send($client, 'POST', '/internal/admin/outlet-management', [
            'name' => 'Fresh synthetic first outlet', 'business_address' => 'Test-only address',
        ])->assertCreated();
        $newId = $created->json('data.id');
        $this->assertMatchesRegularExpression('/^[0-9]{3}$/', $created->json('data.outlet_code'));
        $this->assertTrue($owner->shops()->where('outlets.public_id', $newId)->exists());
        $this->assertNull(Outlet::where('public_id', $newId)->value('legacy_password'));
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'outlet_created')->where('outlet_id',
                Outlet::where('public_id', $newId)->value('id'))->count());
        $freshOperatorClient = $this->client();
        $this->login($freshOperatorClient, $unassigned->email)->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/sales')->assertForbidden();
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $newId])->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('shell.active_outlet.id', $newId));
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
            app(CashSessionOperations::class)->open($operator, $historical, 'd03-archived-no-reopen', ['opening_cash' => '10.00']);
            $this->fail('An archived outlet unexpectedly accepted a cash session.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(0, DB::table('cash_sessions')->where('outlet_id', $historical->id)->where('status', 'open')->count());
        // Even a transaction holding a pre-archive outlet model cannot reuse cash history for tender/refund.
        try {
            DB::transaction(fn () => app(CashSessionOperations::class)->transactionSession($historical, false));
            $this->fail('Archived outlet unexpectedly offered a cash session to a payment workflow.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame(1, DB::table('cash_sessions')->where('outlet_id', $historical->id)->where('status', 'closed')->count());
    }

    public function test_archived_stock_history_is_owner_only_read_only_and_excludes_private_acquisition_details(): void
    {
        $outlet = $this->outlet('Synthetic stock archive candidate', '067');
        $fallback = $this->outlet('Stock history active fallback', '068');
        $owner = $this->member('d03-stock-history-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($fallback);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $limited = $this->member('d03-stock-history-limited@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $limited->shops()->attach([$outlet->id, $fallback->id]);
        $operator = $this->member('d03-stock-history-operator@example.invalid', ['shops.enter', 'shop.inventory']);
        $operator->shops()->attach([$outlet->id, $fallback->id]);
        $product = new Product;
        $product->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic historical stock',
            'outlet_id' => $outlet->id, 'category' => 'accessory', 'price' => '20.00', 'qty' => 1])->save();
        $unit = new StockUnit;
        $unit->forceFill(['product_id' => $product->id, 'unit_no' => 1, 'status' => 'in_stock'])->save();
        $movementId = DB::table('stock_movements')->insertGetId(['product_id' => $product->id,
            'outlet_id' => $outlet->id, 'type' => 'acquisition', 'quantity_change' => 1,
            'stock_before' => 0, 'stock_after' => 1, 'created_at' => now()]);
        $acquisitionId = DB::table('stock_acquisitions')->insertGetId(['product_id' => $product->id,
            'outlet_id' => $outlet->id, 'source_type' => 'supplier', 'quantity' => 1,
            'seller_name' => 'DO-NOT-EXPOSE-SELLER', 'seller_phone' => '03009999999']);
        $transferPublic = (string) Str::uuid();
        $transferId = DB::table('stock_transfers')->insertGetId([
            'public_id' => $transferPublic, 'transfer_number' => 'D03-ARCH-'.Str::random(10),
            'source_outlet_id' => $outlet->id, 'destination_outlet_id' => $fallback->id,
            'created_by_admin_id' => $owner->id, 'status' => 'received', 'version' => 2,
            'notes' => 'PRIVATE-TRANSFER-NOTES']);
        $destinationProduct = new Product;
        $destinationProduct->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Transfer destination product',
            'outlet_id' => $fallback->id, 'category' => 'accessory', 'price' => '20.00', 'qty' => 1])->save();
        $lineSnapshot = json_encode(['contract' => 'synthetic-transfer-line', 'private' => 'PRIVATE-LINE-SNAPSHOT'], JSON_THROW_ON_ERROR);
        $transferLinePublic = (string) Str::uuid();
        $transferLineId = DB::table('stock_transfer_lines')->insertGetId([
            'public_id' => $transferLinePublic, 'stock_transfer_id' => $transferId,
            'source_outlet_id' => $outlet->id, 'destination_outlet_id' => $fallback->id,
            'source_product_id' => $product->id, 'destination_product_id' => $destinationProduct->id,
            'tracked_serialized' => false, 'quantity' => 1, 'received_quantity' => 1,
            'product_snapshot' => $lineSnapshot, 'snapshot_sha256' => hash('sha256', $lineSnapshot)]);
        $receiptSnapshot = json_encode(['contract' => 'synthetic-receipt', 'private' => 'PRIVATE-RECEIPT-SNAPSHOT'], JSON_THROW_ON_ERROR);
        $receiptPublic = (string) Str::uuid();
        $receiptId = DB::table('stock_transfer_receipts')->insertGetId([
            'public_id' => $receiptPublic, 'stock_transfer_id' => $transferId,
            'destination_outlet_id' => $fallback->id, 'processed_by_admin_id' => $owner->id,
            'transfer_version_before' => 1, 'transfer_version_after' => 2,
            'notes' => 'PRIVATE-RECEIPT-NOTES', 'processed_at' => now(),
            'receipt_snapshot' => $receiptSnapshot, 'snapshot_sha256' => hash('sha256', $receiptSnapshot)]);
        $receiptLineSnapshot = json_encode(['private' => 'PRIVATE-RECEIPT-LINE'], JSON_THROW_ON_ERROR);
        $receiptLineId = DB::table('stock_transfer_receipt_lines')->insertGetId([
            'stock_transfer_receipt_id' => $receiptId, 'stock_transfer_id' => $transferId,
            'stock_transfer_line_id' => $transferLineId, 'received_quantity' => 1,
            'line_snapshot' => $receiptLineSnapshot, 'snapshot_sha256' => hash('sha256', $receiptLineSnapshot)]);
        $stocktakeId = DB::table('stocktake_sessions')->insertGetId([
            'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet->id,
            'session_number' => 'D03-STOCKTAKE-'.Str::random(10),
            'kind' => 'full', 'status' => 'counting', 'version' => 1,
            'notes' => 'PRIVATE-UNAPPROVED-STOCKTAKE-NOTES',
            'started_by_admin_id' => $owner->id, 'started_at' => now()]);
        $websiteOrderId = DB::table('orders')->insertGetId([
            'public_id' => (string) Str::uuid(), 'order_number' => 'D03-WEB-'.Str::random(12),
            'order_type' => 'mobile', 'customer_name' => 'PRIVATE-WEBSITE-CUSTOMER',
            'customer_mobile' => '03001112222', 'fulfillment_status' => 'pending']);
        $websiteLineId = DB::table('order_items')->insertGetId([
            'order_id' => $websiteOrderId, 'item_type' => 'mobile', 'title' => 'Synthetic ordered unit',
            'quantity' => 1, 'product_id' => $product->id, 'outlet_id' => $outlet->id]);
        $reservationId = DB::table('reservations')->insertGetId([
            'order_id' => $websiteOrderId, 'outlet_id' => $outlet->id,
            'website_order_number' => 'D03-RES-'.Str::random(10),
            'reservation_reference' => (string) Str::uuid(), 'state' => 'active',
            'customer_name' => 'PRIVATE-RESERVATION-CUSTOMER', 'customer_mobile' => '03003334444']);
        $tradeId = DB::table('trade_ins')->insertGetId([
            'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet->id,
            'product_id' => $product->id, 'created_by_admin_id' => $owner->id,
            'seller_name' => 'PRIVATE-TRADE-SELLER', 'seller_cnic' => '42501-0000000-1',
            'seller_phone' => '03005556666', 'seller_address' => 'PRIVATE-TRADE-ADDRESS',
            'device_condition' => 'used', 'diagnostics' => json_encode(['private' => 'PRIVATE-TRADE-DIAGNOSTICS']),
            'valuation_amount' => '100.00', 'settlement_mode' => 'purchase', 'status' => 'pending']);
        $ownerClient = $this->client();
        $this->login($ownerClient, $owner->email)->assertOk();
        $path = '/internal/admin/outlet-management/'.$outlet->public_id.'/history';
        $this->send($ownerClient, 'GET', $path)->assertStatus(409);
        $this->assertNull($outlet->fresh()->archived_at);
        // Do NOT make this product-bearing outlet eligible for real service archival.
        // Synthetic direct archived-state read-path fixture leaves the production fail-closed rule unchanged.
        $outlet->forceFill(['archived_at' => now()])->save();
        $beforeProduct = DB::table('products')->where('id', $product->id)->firstOrFail();
        $beforeTransfer = DB::table('stock_transfers')->where('id', $transferId)->firstOrFail();
        $beforeLine = DB::table('stock_transfer_lines')->where('id', $transferLineId)->firstOrFail();
        $beforeReceipt = DB::table('stock_transfer_receipts')->where('id', $receiptId)->firstOrFail();
        $beforeReceiptLine = DB::table('stock_transfer_receipt_lines')->where('id', $receiptLineId)->firstOrFail();
        $beforeUnit = DB::table('stock_units')->where('id', $unit->id)->firstOrFail();
        $beforeMovement = DB::table('stock_movements')->where('id', $movementId)->firstOrFail();
        $response = $this->send($ownerClient, 'GET', $path)->assertOk()
            ->assertJsonPath('data.stock_history.product_count', 1)
            ->assertJsonPath('data.stock_history.unit_count', 1)
            ->assertJsonPath('data.stock_history.movement_count', 1)
            ->assertJsonPath('data.stock_history.acquisition_count', 1)
            ->assertJsonPath('data.stock_history.transfer_count', 1)
            ->assertJsonPath('data.stock_history.transfers.0.id', $transferPublic)
            ->assertJsonPath('data.stock_history.transfers.0.source_id', $outlet->public_id)
            ->assertJsonPath('data.stock_history.transfers.0.destination_id', $fallback->public_id)
            ->assertJsonPath('data.stock_history.transfers.0.status', 'received')
            ->assertJsonPath('data.stock_history.transfers.0.line_count', 1)
            ->assertJsonPath('data.stock_history.transfers.0.receipt_count', 1)
            ->assertJsonPath('data.stock_history.transfers.0.unresolved_quantity', 0)
            ->assertJsonPath('data.stock_history.transfers.0.requires_review', false)
            ->assertJsonPath('data.stock_history.transfers.0.lines.0.id', $transferLinePublic)
            ->assertJsonPath('data.stock_history.transfers.0.lines.0.received_quantity', 1)
            ->assertJsonPath('data.stock_history.transfers.0.receipts.0.id', $receiptPublic)
            ->assertJsonPath('data.stock_history.transfers.0.receipts.0.received_quantity', 1)
            ->assertJsonPath('data.stock_history.products.0.id', $product->public_id)
            ->assertJsonPath('data.stock_history.products.0.quantity', 1)
            ->assertJsonPath('data.stock_history.movements.0.stock_after', 1)
            ->assertJsonPath('data.stock_reconciliation.negative_stock_product_count', 0)
            ->assertJsonPath('data.stock_reconciliation.latest_movement_disagreement_count', 0)
            ->assertJsonPath('data.stock_reconciliation.tracked_unit_disagreement_count', 0)
            ->assertJsonPath('data.stock_reconciliation.untracked_product_with_units_count', 1)
            ->assertJsonPath('data.stock_reconciliation.positive_stock_without_movement_count', 0)
            ->assertJsonPath('data.stock_reconciliation.physical_count_verified', false)
            ->assertJsonPath('data.stock_reconciliation.requires_manual_reconciliation', true)
            ->assertJsonPath('data.obligations.on_hand_quantity', 1)
            ->assertJsonPath('data.obligations.unresolved_transfer_quantity', 0)
            ->assertJsonPath('data.obligations.active_custody_hold_quantity', 0)
            ->assertJsonPath('data.obligations.unapproved_stocktakes', 1)
            ->assertJsonPath('data.obligations.website_order_line_count', 1)
            ->assertJsonPath('data.obligations.website_orders_for_review', 1)
            ->assertJsonPath('data.obligations.active_website_reservations', 1)
            ->assertJsonPath('data.obligations.trade_in_count', 1)
            ->assertJsonPath('data.obligations.pending_trade_ins', 1)
            ->assertJsonPath('data.obligations.website_return_count', 0)
            ->assertJsonPath('data.obligations.requires_manual_review', true)
            ->assertJsonPath('data.obligations.archive_eligibility', 'not_approved_for_business_history');
        $this->assertStringNotContainsString('DO-NOT-EXPOSE-SELLER', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE-TRANSFER-NOTES', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE-UNAPPROVED-STOCKTAKE-NOTES', $response->getContent());
        foreach (['PRIVATE-WEBSITE-CUSTOMER', '03001112222', 'PRIVATE-RESERVATION-CUSTOMER',
            '03003334444', 'PRIVATE-TRADE-SELLER', '42501-0000000-1',
            '03005556666', 'PRIVATE-TRADE-ADDRESS', 'PRIVATE-TRADE-DIAGNOSTICS'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        foreach (['PRIVATE-LINE-SNAPSHOT', 'PRIVATE-RECEIPT-SNAPSHOT', 'PRIVATE-RECEIPT-NOTES',
            'PRIVATE-RECEIPT-LINE'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        $this->assertStringNotContainsString('03009999999', $response->getContent());
        $this->assertStringNotContainsString('unit_code', $response->getContent());
        $limitedClient = $this->client();
        $this->login($limitedClient, $limited->email)->assertOk();
        $this->send($limitedClient, 'GET', $path)->assertForbidden();
        $operatorClient = $this->client();
        $this->login($operatorClient, $operator->email)->assertOk();
        $this->send($operatorClient, 'GET', $path)->assertForbidden();
        $this->assertEquals($beforeTransfer, DB::table('stock_transfers')->where('id', $transferId)->firstOrFail());
        $this->assertEquals($beforeLine, DB::table('stock_transfer_lines')->where('id', $transferLineId)->firstOrFail());
        $this->assertEquals($beforeReceipt, DB::table('stock_transfer_receipts')->where('id', $receiptId)->firstOrFail());
        $this->assertEquals($beforeReceiptLine, DB::table('stock_transfer_receipt_lines')->where('id', $receiptLineId)->firstOrFail());
        $this->assertSame(hash('sha256', $lineSnapshot), $beforeLine->snapshot_sha256);
        $this->assertSame(hash('sha256', $receiptSnapshot), $beforeReceipt->snapshot_sha256);
        $this->assertSame(hash('sha256', $receiptLineSnapshot), $beforeReceiptLine->snapshot_sha256);
        $this->assertEquals($beforeUnit, DB::table('stock_units')->where('id', $unit->id)->firstOrFail());
        $this->assertEquals($beforeMovement, DB::table('stock_movements')->where('id', $movementId)->firstOrFail());
        $this->assertSame(1, DB::table('stock_acquisitions')->where('id', $acquisitionId)->count());
        $this->assertSame('counting', DB::table('stocktake_sessions')->where('id', $stocktakeId)->value('status'));
        $this->assertSame('pending', DB::table('orders')->where('id', $websiteOrderId)->value('fulfillment_status'));
        $this->assertSame($outlet->id, (int) DB::table('order_items')->where('id', $websiteLineId)->value('outlet_id'));
        $this->assertSame('active', DB::table('reservations')->where('id', $reservationId)->value('state'));
        $this->assertSame('pending', DB::table('trade_ins')->where('id', $tradeId)->value('status'));
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'outlet_archived')->count());
        // Synthetic only: deliberately corrupt a snapshot without altering its immutable movement;
        // the archived-history READ must reveal disagreement, never silently mark stock settled.
        DB::table('products')->where('id', $product->id)->update(['qty' => 2, 'track_imei' => true]);
        try {
            $this->send($ownerClient, 'GET', $path)->assertOk()
                ->assertJsonPath('data.stock_reconciliation.latest_movement_disagreement_count', 1)
                ->assertJsonPath('data.stock_reconciliation.tracked_unit_disagreement_count', 1)
                ->assertJsonPath('data.stock_reconciliation.untracked_product_with_units_count', 0)
                ->assertJsonPath('data.stock_reconciliation.physical_count_verified', false)
                ->assertJsonPath('data.stock_reconciliation.archive_eligibility', 'not_approved_for_business_history');
            $this->assertEquals($beforeMovement, DB::table('stock_movements')->where('id', $movementId)->firstOrFail());
        } finally {
            DB::table('products')->where('id', $product->id)->update(['qty' => 1, 'track_imei' => false]);
        }
        $this->assertEquals($beforeProduct, DB::table('products')->where('id', $product->id)->firstOrFail());

    }

    public function test_archived_transfer_history_reconciles_in_transit_both_outlets_without_reassignment(): void
    {
        $source = $this->outlet('D03 unresolved transfer source', '077');
        $destination = $this->outlet('D03 unresolved transfer destination', '078');
        $fallback = $this->outlet('D03 transfer history owner fallback', '079');
        $owner = $this->member('d03-transfer-history-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($fallback);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $sourceProduct = new Product;
        $sourceProduct->forceFill(['name' => 'Synthetic in-transit source',
            'outlet_id' => $source->id, 'category' => 'accessory', 'price' => '30.00', 'qty' => 1])->save();
        $destinationProduct = new Product;
        $destinationProduct->forceFill(['name' => 'Synthetic in-transit destination',
            'outlet_id' => $destination->id, 'category' => 'accessory', 'price' => '30.00', 'qty' => 0])->save();
        $public = (string) Str::uuid();
        $transferId = DB::table('stock_transfers')->insertGetId([
            'public_id' => $public, 'transfer_number' => 'D03-IN-'.Str::random(10),
            'source_outlet_id' => $source->id, 'destination_outlet_id' => $destination->id,
            'status' => 'in_transit', 'created_by_admin_id' => $owner->id,
            'notes' => 'PRIVATE-TRANSFER-IN-TRANSIT']);
        $snapshot = json_encode(['contract' => 'synthetic-transit', 'private' => 'DO-NOT-EXPOSE-TRANSIT'], JSON_THROW_ON_ERROR);
        $lineId = DB::table('stock_transfer_lines')->insertGetId([
            'public_id' => (string) Str::uuid(), 'stock_transfer_id' => $transferId,
            'source_outlet_id' => $source->id, 'destination_outlet_id' => $destination->id,
            'source_product_id' => $sourceProduct->id, 'destination_product_id' => $destinationProduct->id,
            'tracked_serialized' => false, 'quantity' => 1, 'received_quantity' => 0,
            'rejected_quantity' => 0, 'product_snapshot' => $snapshot,
            'snapshot_sha256' => hash('sha256', $snapshot)]);
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        foreach ([$source, $destination] as $outlet) {
            $this->send($client, 'GET', '/internal/admin/outlet-management/'.$outlet->public_id.'/history')->assertStatus(409);
            $this->send($client, 'POST', '/internal/admin/outlet-management/'.$outlet->public_id.'/archive',
                ['version' => 1])->assertStatus(409);
            $this->assertNull($outlet->fresh()->archived_at);
        }
        // Synthetic archived-state inspection only: production explicitly blocks both sides.
        $source->forceFill(['archived_at' => now()])->save();
        $destination->forceFill(['archived_at' => now()])->save();
        foreach ([$source, $destination] as $outlet) {
            $response = $this->send($client, 'GET', '/internal/admin/outlet-management/'.$outlet->public_id.'/history')->assertOk()
                ->assertJsonPath('data.stock_history.transfer_count', 1)
                ->assertJsonPath('data.stock_history.transfers.0.id', $public)
                ->assertJsonPath('data.stock_history.transfers.0.status', 'in_transit')
                ->assertJsonPath('data.stock_history.transfers.0.source_id', $source->public_id)
                ->assertJsonPath('data.stock_history.transfers.0.destination_id', $destination->public_id)
                ->assertJsonPath('data.stock_history.transfers.0.line_count', 1)
                ->assertJsonPath('data.stock_history.transfers.0.receipt_count', 0)
                ->assertJsonPath('data.stock_history.transfers.0.unresolved_quantity', 1)
                ->assertJsonPath('data.stock_history.transfers.0.requires_review', true)
                ->assertJsonPath('data.obligations.unresolved_transfer_quantity', 1)
                ->assertJsonPath('data.obligations.requires_manual_review', true);
            $this->assertStringNotContainsString('PRIVATE-TRANSFER-IN-TRANSIT', $response->getContent());
            $this->assertStringNotContainsString('DO-NOT-EXPOSE-TRANSIT', $response->getContent());
        }
        $this->assertSame($source->id, (int) DB::table('stock_transfers')->where('id', $transferId)->value('source_outlet_id'));
        $this->assertSame($destination->id, (int) DB::table('stock_transfers')->where('id', $transferId)->value('destination_outlet_id'));
        $this->assertSame(0, DB::table('stock_transfer_receipts')->where('stock_transfer_id', $transferId)->count());
        $this->assertSame(hash('sha256', $snapshot), DB::table('stock_transfer_lines')->where('id', $lineId)->value('snapshot_sha256'));
        $this->assertSame(0, DB::table('identity_audit_events')->whereIn('outlet_id', [$source->id, $destination->id])
            ->where('action', 'outlet_archived')->count());
    }

    public function test_archived_invoice_and_claim_summaries_preserve_hashes_and_hide_customer_case_details(): void
    {
        $outlet = $this->outlet('Synthetic historical invoice claim', '070');
        $fallback = $this->outlet('Historical invoice active fallback', '071');
        $owner = $this->member('d03-invoice-claim-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($fallback);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $limited = $this->member('d03-invoice-claim-limited@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $limited->shops()->attach($fallback);
        $product = new Product;
        $product->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Synthetic retained invoice product',
            'outlet_id' => $outlet->id, 'category' => 'accessory', 'price' => '200.00', 'qty' => 0])->save();
        $invoicePublicId = (string) Str::uuid();
        $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => $invoicePublicId, 'invoice_number' => 'D03-INV-'.Str::random(12),
            'total_bill' => '200.00', 'final_bill' => '200.00', 'currency' => 'PKR',
            'customer_name' => 'PRIVATE-CUSTOMER-NAME', 'customer_phone' => '03008888888',
            'customer_cnic' => '42501-0000000-0', 'created_at' => now(),
            'business_snapshot' => json_encode(['business_name' => 'Synthetic Original Invoicing Entity',
                'outlet_name' => 'Synthetic historical invoice claim'], JSON_THROW_ON_ERROR)]);
        $saleId = DB::table('sales')->insertGetId(['outlet_id' => $outlet->id,
            'product_id' => $product->id, 'invoice_id' => $invoiceId, 'public_id' => (string) Str::uuid(),
            'sale_date' => now()->toDateString(), 'sale_price' => '200.00', 'quantity' => 1,
            'total_price' => '200.00', 'net_total_price' => '200.00',
            'invoice_detail_snapshot' => json_encode(['contract' => 'sale-line.v1',
                'name' => 'Original synthetic archived item'], JSON_THROW_ON_ERROR)]);
        $claimPublicId = (string) Str::uuid();
        $claimId = DB::table('claims')->insertGetId(['outlet_id' => $outlet->id,
            'product_id' => $product->id, 'invoice_id' => $invoiceId, 'sale_id' => $saleId,
            'public_id' => $claimPublicId, 'claim_number' => 'D03-CLM-'.Str::random(12),
            'quantity' => 1, 'status' => 'closed', 'issue_description' => 'PRIVATE-ISSUE-DESCRIPTION',
            'internal_notes' => 'PRIVATE-CASE-NOTES']);
        $snapshot = json_encode(['contract' => 'd03-test-claim.v1', 'note' => 'PRIVATE-CLAIM-SNAPSHOT'], JSON_THROW_ON_ERROR);
        $eventId = DB::table('claim_events')->insertGetId(['claim_id' => $claimId,
            'public_id' => (string) Str::uuid(), 'sequence' => 1, 'status' => 'closed',
            'note' => 'PRIVATE-CLAIM-EVENT-NOTE', 'occurred_at' => now(),
            'snapshot' => $snapshot, 'snapshot_sha256' => hash('sha256', $snapshot)]);
        // All rows are rollback-only synthetic obligations; production archival still refuses them.
        $returnId = DB::table('returns')->insertGetId(['invoice_id' => $invoiceId,
            'actor_type' => Admin::class, 'actor_id' => $owner->id, 'reason' => 'Synthetic unrefunded return',
            'status' => 'accepted', 'idempotency_key' => 'd03-return-'.Str::uuid(),
            'public_id' => (string) Str::uuid()]);
        $returnSnapshot = json_encode(['contract' => 'd03-return.v1'], JSON_THROW_ON_ERROR);
        $returnLineId = DB::table('return_lines')->insertGetId(['return_id' => $returnId,
            'invoice_id' => $invoiceId, 'sale_id' => $saleId, 'public_id' => (string) Str::uuid(),
            'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable',
            'unit_price' => '40.00', 'discount_amount' => '0.00', 'net_amount' => '40.00',
            'purchase_amount' => '20.00', 'currency' => 'PKR', 'sale_snapshot' => $returnSnapshot,
            'snapshot_sha256' => hash('sha256', $returnSnapshot)]);
        DB::table('sales')->where('id', $saleId)->update(['returned_quantity' => 1]);
        $supplierId = DB::table('suppliers')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => (string) Str::uuid(), 'supplier_code' => 'D03-SUP-'.Str::random(8),
            'name' => 'PRIVATE-SUPPLIER-NAME', 'tax_identifier' => 'PRIVATE-SUPPLIER-TAX',
            'created_by_admin_id' => $owner->id, 'updated_by_admin_id' => $owner->id]);
        $poId = DB::table('purchase_orders')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => (string) Str::uuid(), 'supplier_id' => $supplierId,
            'supplier_code_snapshot' => 'D03', 'supplier_name_snapshot' => 'PRIVATE-SUPPLIER-NAME',
            'order_number' => 'D03-PO-'.Str::random(12), 'status' => 'partially_received',
            'ordered_at' => now(), 'created_by_admin_id' => $owner->id]);
        $poLineId = DB::table('purchase_order_lines')->insertGetId([
            'public_id' => (string) Str::uuid(), 'purchase_order_id' => $poId,
            'outlet_id' => $outlet->id, 'product_id' => $product->id, 'ordered_quantity' => 5,
            'received_quantity' => 2, 'ordered_unit_cost' => '10.00', 'planned_landed_unit_cost' => '11.00']);
        $repairId = DB::table('repair_jobs')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => (string) Str::uuid(), 'repair_number' => 'D03-REPAIR-'.Str::random(10),
            'customer_name' => 'PRIVATE-REPAIR-CUSTOMER', 'device_label' => 'Test device',
            'issue_description' => 'PRIVATE-REPAIR-ISSUE',
            'identifier_type' => 'serial', 'identifier_value' => 'PRIVATE-REPAIR-SERIAL',
            'status' => 'ready_for_collection', 'handled_by_admin_id' => $owner->id,
            'handled_by_name' => 'Synthetic owner', 'received_at' => now()]);
        $poSnapshot = json_encode(['contract' => 'd03-po-event.v1', 'private' => 'PRIVATE-PO-EVENT'], JSON_THROW_ON_ERROR);
        $poEventId = DB::table('purchase_order_events')->insertGetId([
            'purchase_order_id' => $poId, 'sequence' => 1, 'event' => 'partially_received',
            'actor_admin_id' => $owner->id, 'actor_name_snapshot' => 'PRIVATE-PO-ACTOR',
            'actor_role_snapshot' => 'PRIVATE-PO-ROLE', 'outlet_name_snapshot' => 'PRIVATE-PO-OUTLET',
            'snapshot' => $poSnapshot, 'snapshot_sha256' => hash('sha256', $poSnapshot),
            'occurred_at' => now()]);
        $repairSnapshot = json_encode(['contract' => 'd03-repair-event.v1', 'private' => 'PRIVATE-REPAIR-EVENT'], JSON_THROW_ON_ERROR);
        $repairEventId = DB::table('repair_events')->insertGetId([
            'repair_job_id' => $repairId, 'sequence' => 1, 'event_type' => 'status_changed',
            'status' => 'ready_for_collection', 'actor_admin_id' => $owner->id,
            'actor_name' => 'PRIVATE-REPAIR-ACTOR', 'snapshot' => $repairSnapshot,
            'snapshot_sha256' => hash('sha256', $repairSnapshot), 'created_at' => now()]);
        $activeClaimPublic = (string) Str::uuid();
        $activeClaimId = DB::table('claims')->insertGetId(['outlet_id' => $outlet->id,
            'product_id' => $product->id, 'invoice_id' => $invoiceId, 'sale_id' => $saleId,
            'public_id' => $activeClaimPublic, 'claim_number' => 'D03-ACT-'.Str::random(12),
            'quantity' => 1, 'status' => 'ready_for_collection',
            'issue_description' => 'PRIVATE-ACTIVE-WARRANTY-ISSUE']);
        $activeSnapshot = json_encode(['contract' => 'd03-active-claim.v1',
            'note' => 'PRIVATE-ACTIVE-WARRANTY-EVENT'], JSON_THROW_ON_ERROR);
        $activeEventId = DB::table('claim_events')->insertGetId(['claim_id' => $activeClaimId,
            'public_id' => (string) Str::uuid(), 'sequence' => 1, 'status' => 'ready_for_collection',
            'note' => 'PRIVATE-ACTIVE-WARRANTY-NOTE', 'occurred_at' => now(),
            'snapshot' => $activeSnapshot, 'snapshot_sha256' => hash('sha256', $activeSnapshot)]);
        $ownerClient = $this->client();
        $this->login($ownerClient, $owner->email)->assertOk();
        $uri = '/internal/admin/outlet-management/'.$outlet->public_id.'/history';
        $this->send($ownerClient, 'GET', $uri)->assertStatus(409);
        $docUri = $uri.'/invoices/'.$invoicePublicId.'/retrieve';
        $pdfUri = $uri.'/invoices/'.$invoicePublicId.'/reconstructed-pdf';
        $docRequest = ['password' => 'SyntheticPass123!', 'purpose' => 'Retained invoice review'];
        $this->send($ownerClient, 'POST', $docUri, $docRequest)->assertStatus(409);
        $this->send($ownerClient, 'POST', $pdfUri, $docRequest)->assertStatus(409);
        $claimDocUri = $uri.'/claims/'.$claimPublicId.'/retrieve';
        $this->send($ownerClient, 'POST', $claimDocUri, $docRequest)->assertStatus(409);
        // Product/invoice/claim-bearing real outlet still MUST NOT be archived by the service.
        $outlet->forceFill(['archived_at' => now()])->save();
        $priorInvoice = DB::table('invoices')->where('id', $invoiceId)->firstOrFail();
        $priorClaim = DB::table('claims')->where('id', $claimId)->firstOrFail();
        $priorEvent = DB::table('claim_events')->where('id', $eventId)->firstOrFail();
        $response = $this->send($ownerClient, 'GET', $uri)->assertOk()
            ->assertJsonPath('data.sales_claim_history.invoice_count', 1)
            ->assertJsonPath('data.sales_claim_history.sale_line_count', 1)
            ->assertJsonPath('data.sales_claim_history.claim_count', 2)
            ->assertJsonPath('data.sales_claim_history.claim_event_count', 2)
            ->assertJsonPath('data.sales_claim_history.invoices.0.id', $invoicePublicId)
            ->assertJsonPath('data.sales_claim_history.invoices.0.final_bill', '200.00')
            ->assertJsonPath('data.sales_claim_history.invoices.0.sale_line_count', 1)
            ->assertJsonPath('data.sales_claim_history.invoices.0.sales.0.returned_quantity', 1)
            ->assertJsonPath('data.sales_claim_history.invoices.0.return_count', 1)
            ->assertJsonPath('data.sales_claim_history.invoices.0.returns.0.channel', 'pos')
            ->assertJsonPath('data.sales_claim_history.invoices.0.returns.0.line_count', 1)
            ->assertJsonPath('data.sales_claim_history.invoices.0.refund_count', 0)
            ->assertJsonPath('data.sales_claim_history.claims.0.id', $activeClaimPublic)
            ->assertJsonPath('data.sales_claim_history.claims.0.status', 'ready_for_collection')
            ->assertJsonPath('data.sales_claim_history.claims.0.event_count', 1)
            ->assertJsonPath('data.sales_claim_history.claims.0.requires_review', true)
            ->assertJsonPath('data.sales_claim_history.claims.0.events.0.snapshot_sha256', hash('sha256', $activeSnapshot))
            ->assertJsonPath('data.sales_claim_history.claims.1.id', $claimPublicId)
            ->assertJsonPath('data.sales_claim_history.claims.1.invoice_id', $invoicePublicId)
            ->assertJsonPath('data.sales_claim_history.claims.1.status', 'closed')
            ->assertJsonPath('data.sales_claim_history.claims.1.event_count', 1)
            ->assertJsonPath('data.sales_claim_history.claims.1.events.0.snapshot_sha256',
                hash('sha256', $snapshot))
            ->assertJsonPath('data.sales_claim_history.claims.1.requires_review', false)
            ->assertJsonPath('data.obligations.pos_return_count', 1)
            ->assertJsonPath('data.obligations.pos_returns_unmatched_count', 1)
            ->assertJsonPath('data.obligations.pos_returns_over_refunded_count', 0)
            ->assertJsonPath('data.obligations.pos_return_amount', '40.00')
            ->assertJsonPath('data.obligations.pos_refunds_recorded', '0')
            ->assertJsonPath('data.obligations.pos_refund_difference', '40.00')
            ->assertJsonPath('data.obligations.open_purchase_orders', 1)
            ->assertJsonPath('data.obligations.unreceived_purchase_order_quantity', 3)
            ->assertJsonPath('data.obligations.active_paid_repairs', 1)
            ->assertJsonPath('data.obligations.active_unbilled_paid_repairs', 1)
            ->assertJsonPath('data.obligations.open_warranty_claims', 1)
            ->assertJsonPath('data.obligations.requires_manual_review', true)
            ->assertJsonPath('data.obligations.archive_eligibility', 'not_approved_for_business_history')
            ->assertJsonPath('data.procurement_repair_history.supplier_count', 1)
            ->assertJsonPath('data.procurement_repair_history.purchase_order_count', 1)
            ->assertJsonPath('data.procurement_repair_history.repair_count', 1)
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.status', 'partially_received')
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.supplier_id',
                DB::table('suppliers')->where('id', $supplierId)->value('public_id'))
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.line_count', 1)
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.unreceived_quantity', 3)
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.event_count', 1)
            ->assertJsonPath('data.procurement_repair_history.purchase_orders.0.events.0.snapshot_sha256',
                hash('sha256', $poSnapshot))
            ->assertJsonPath('data.procurement_repair_history.repairs.0.status', 'ready_for_collection')
            ->assertJsonPath('data.procurement_repair_history.repairs.0.requires_review', true)
            ->assertJsonPath('data.procurement_repair_history.repairs.0.event_count', 1)
            ->assertJsonPath('data.procurement_repair_history.repairs.0.events.0.snapshot_sha256',
                hash('sha256', $repairSnapshot));
        foreach (['PRIVATE-CUSTOMER-NAME', '03008888888', 'PRIVATE-ISSUE-DESCRIPTION',
            'PRIVATE-CASE-NOTES', 'PRIVATE-CLAIM-SNAPSHOT', 'PRIVATE-CLAIM-EVENT-NOTE', 'PRIVATE-SUPPLIER-NAME', 'PRIVATE-SUPPLIER-TAX',
            'PRIVATE-REPAIR-CUSTOMER', 'PRIVATE-REPAIR-SERIAL', 'PRIVATE-REPAIR-ISSUE', 'PRIVATE-PO-EVENT', 'PRIVATE-PO-ACTOR', 'PRIVATE-PO-ROLE',
            'PRIVATE-PO-OUTLET', 'PRIVATE-REPAIR-EVENT', 'PRIVATE-REPAIR-ACTOR', 'PRIVATE-ACTIVE-WARRANTY-ISSUE',
            'PRIVATE-ACTIVE-WARRANTY-EVENT', 'PRIVATE-ACTIVE-WARRANTY-NOTE'] as $private) {
            $this->assertStringNotContainsString($private, $response->getContent());
        }
        // Sensitive original customer fields are only available on explicitly password-confirmed,
        // per-invoice retrieval; the broad GET summary never includes them.
        $this->assertStringNotContainsString('42501-0000000-0', $response->getContent());
        $this->send($ownerClient, 'GET', $docUri)->assertStatus(405);
        $this->send($ownerClient, 'POST', $docUri, ['password' => 'incorrect',
            'purpose' => 'Retained invoice review'])->assertForbidden();
        $this->send($ownerClient, 'POST', $docUri, ['password' => 'SyntheticPass123!',
            'purpose' => 'short'])->assertStatus(422);
        $this->send($ownerClient, 'POST', $uri.'/invoices/'.Str::uuid().'/retrieve', $docRequest)
            ->assertNotFound();
        $document = $this->send($ownerClient, 'POST', $docUri, $docRequest)->assertOk()
            ->assertJsonPath('data.invoice_id', $invoicePublicId)
            ->assertJsonPath('data.outlet_id', $outlet->public_id)
            ->assertJsonPath('data.customer_name', 'PRIVATE-CUSTOMER-NAME')
            ->assertJsonPath('data.customer_phone', '03008888888')
            ->assertJsonPath('data.customer_cnic', '42501-0000000-0')
            ->assertJsonPath('data.line_count', 1)
            ->assertJsonPath('data.lines_truncated', false)
            ->assertJsonPath('data.lines.0.id', DB::table('sales')->where('id', $saleId)->value('public_id'))
            ->assertJsonPath('data.lines.0.product_id', $product->public_id)
            ->assertJsonPath('data.lines.0.returned_quantity', 1)
            ->assertJsonPath('data.retrieval_mode', 'original_database_record_read_only');
        $this->assertStringContainsString('no-store', (string) $document->headers->get('Cache-Control'));
        $this->assertSame(1, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'archived_invoice_document_retrieved')
            ->where('reference', 'invoice:'.$invoicePublicId.';purpose:Retained invoice review')->count());
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $invoiceId)->firstOrFail());
        $this->assertSame(1, DB::table('sales')->where('id', $saleId)->where('returned_quantity', 1)->count());
        $this->send($ownerClient, 'GET', $pdfUri)->assertStatus(405);
        $this->send($ownerClient, 'POST', $pdfUri, ['password' => 'incorrect',
            'purpose' => 'Retained invoice review'])->assertForbidden();
        $reconstruction = $this->send($ownerClient, 'POST', $pdfUri, $docRequest)->assertOk()
            ->assertJsonPath('data.reconstruction_only', true)
            ->assertJsonPath('data.original_issued_pdf_preserved', false)
            ->assertJsonPath('data.document_version', 1)
            ->assertJsonPath('data.format', 'a4');
        $pdfBytes = base64_decode($reconstruction->json('data.pdf_base64'), true);
        $this->assertNotFalse($pdfBytes);
        $this->assertStringStartsWith('%PDF-1.4', $pdfBytes);
        $this->assertStringContainsString('RECONSTRUCTED COPY - NOT THE ORIGINAL ISSUED PDF', $pdfBytes);
        $this->assertStringContainsString('Original synthetic archived item', $pdfBytes);
        $this->assertStringContainsString('Synthetic Original Invoicing Entity', $pdfBytes);
        $this->assertStringContainsString('PRIVATE-CUSTOMER-NAME', $pdfBytes);
        $this->assertSame(hash('sha256', $pdfBytes), $reconstruction->json('data.document_sha256'));
        $this->assertStringStartsWith('reconstructed-', $reconstruction->json('data.filename'));
        $this->assertStringContainsString('no-store', (string) $reconstruction->headers->get('Cache-Control'));
        // Reconstruction is the ONLY separate archived PDF path; normal operational documents stay denied.
        try {
            app(CanonicalDocuments::class)
                ->savePdf($owner, $outlet, 'invoice', $invoicePublicId);
            $this->fail('Normal operational PDF must not grant archived-outlet access.');
        } catch (HttpException $error) {
            $this->assertSame(403, $error->getStatusCode());
        }
        $this->assertSame(1, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'archived_invoice_pdf_reconstructed')
            ->where('reference', 'invoice:'.$invoicePublicId.';purpose:Retained invoice review')->count());
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $invoiceId)->firstOrFail());
        $originalSaleSnapshot = DB::table('sales')->where('id', $saleId)->value('invoice_detail_snapshot');
        DB::table('sales')->where('id', $saleId)->update(['invoice_detail_snapshot' => null]);
        $this->send($ownerClient, 'POST', $pdfUri, $docRequest)->assertStatus(409);
        DB::table('sales')->where('id', $saleId)->update(['invoice_detail_snapshot' => $originalSaleSnapshot]);
        DB::table('sales')->where('id', $saleId)->update(['invoice_detail_snapshot' => json_encode([
            'contract' => 'unverified-old-contract', 'name' => 'Untrusted arbitrary item'], JSON_THROW_ON_ERROR)]);
        try {
            app(CanonicalDocuments::class)
                ->reconstructArchivedInvoice($owner, $outlet, $invoicePublicId);
            $this->fail('Unsupported sale snapshot contract must not be rendered as an original copy.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        DB::table('sales')->where('id', $saleId)->update(['invoice_detail_snapshot' => $originalSaleSnapshot]);
        $originalBusinessSnapshot = DB::table('invoices')->where('id', $invoiceId)->value('business_snapshot');
        DB::table('invoices')->where('id', $invoiceId)->update(['business_snapshot' => null]);
        try {
            app(CanonicalDocuments::class)
                ->reconstructArchivedInvoice($owner, $outlet, $invoicePublicId);
            $this->fail('Missing business snapshot must not produce a reconstructed PDF.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        DB::table('invoices')->where('id', $invoiceId)->update(['business_snapshot' => $originalBusinessSnapshot]);
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $invoiceId)->firstOrFail());
        $this->assertSame(1, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'archived_invoice_pdf_reconstructed')->count());
        $unrelatedArchived = $this->outlet('Synthetic unrelated historical records', '098');
        $unrelatedArchived->forceFill(['archived_at' => now()])->save();
        $otherHistory = '/internal/admin/outlet-management/'.$unrelatedArchived->public_id.'/history';
        $this->send($ownerClient, 'POST', $otherHistory.'/invoices/'.$invoicePublicId.'/retrieve', $docRequest)
            ->assertNotFound();
        $this->send($ownerClient, 'POST', $otherHistory.'/invoices/'.$invoicePublicId.'/reconstructed-pdf', $docRequest)
            ->assertNotFound();
        $this->send($ownerClient, 'POST', $otherHistory.'/claims/'.$claimPublicId.'/retrieve', $docRequest)
            ->assertNotFound();
        $this->send($ownerClient, 'POST', $claimDocUri, ['password' => 'wrong-password',
            'purpose' => 'Retained warranty case review'])->assertForbidden();
        $this->send($ownerClient, 'POST', $claimDocUri, ['password' => 'SyntheticPass123!',
            'purpose' => 'short'])->assertStatus(422);
        $this->send($ownerClient, 'POST', $uri.'/claims/'.Str::uuid().'/retrieve', $docRequest)
            ->assertNotFound();
        $claimDocument = $this->send($ownerClient, 'POST', $claimDocUri, $docRequest)->assertOk()
            ->assertJsonPath('data.outlet_id', $outlet->public_id)
            ->assertJsonPath('data.claim_id', $claimPublicId)
            ->assertJsonPath('data.invoice_id', $invoicePublicId)
            ->assertJsonPath('data.issue_description', 'PRIVATE-ISSUE-DESCRIPTION')
            ->assertJsonPath('data.internal_notes', 'PRIVATE-CASE-NOTES')
            ->assertJsonPath('data.event_count', 1)
            ->assertJsonPath('data.events_truncated', false)
            ->assertJsonPath('data.events.0.note', 'PRIVATE-CLAIM-EVENT-NOTE')
            ->assertJsonPath('data.events.0.snapshot_sha256', hash('sha256', $snapshot))
            ->assertJsonPath('data.retrieval_mode', 'original_database_record_read_only');
        $this->assertStringContainsString('no-store', (string) $claimDocument->headers->get('Cache-Control'));
        $this->assertArrayNotHasKey('snapshot', $claimDocument->json('data.events.0'));
        $this->assertSame(1, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'archived_claim_document_retrieved')
            ->where('reference', 'claim:'.$claimPublicId.';purpose:Retained invoice review')->count());
        $this->assertEquals($priorClaim, DB::table('claims')->where('id', $claimId)->firstOrFail());
        $this->assertEquals($priorEvent, DB::table('claim_events')->where('id', $eventId)->firstOrFail());
        $limitedClient = $this->client();
        $this->login($limitedClient, $limited->email)->assertOk();
        $this->send($limitedClient, 'GET', $uri)->assertForbidden();
        $this->send($limitedClient, 'POST', $docUri, $docRequest)->assertForbidden();
        $this->send($limitedClient, 'POST', $pdfUri, $docRequest)->assertForbidden();
        $this->send($limitedClient, 'POST', $claimDocUri, $docRequest)->assertForbidden();
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $invoiceId)->firstOrFail());
        $this->assertEquals($priorClaim, DB::table('claims')->where('id', $claimId)->firstOrFail());
        $this->assertEquals($priorEvent, DB::table('claim_events')->where('id', $eventId)->firstOrFail());
        $this->assertSame(1, DB::table('return_lines')->where('id', $returnLineId)->count());
        $this->assertSame(1, DB::table('purchase_order_lines')->where('id', $poLineId)
            ->where('received_quantity', 2)->count());
        $this->assertSame('ready_for_collection', DB::table('repair_jobs')->where('id', $repairId)->value('status'));
        $this->assertSame(hash('sha256', $poSnapshot), DB::table('purchase_order_events')->where('id', $poEventId)->value('snapshot_sha256'));
        $this->assertSame(hash('sha256', $repairSnapshot), DB::table('repair_events')->where('id', $repairEventId)->value('snapshot_sha256'));
        $this->assertSame('ready_for_collection', DB::table('claims')->where('id', $activeClaimId)->value('status'));
        $this->assertSame(hash('sha256', $activeSnapshot), DB::table('claim_events')->where('id', $activeEventId)->value('snapshot_sha256'));
        // MySQL may canonicalize JSON on storage; the signed hash belongs to the original written snapshot bytes.
        $this->assertSame(hash('sha256', $snapshot), $priorEvent->snapshot_sha256);
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'outlet_archived')->count());
        // Synthetic anomaly: aggregate outstanding amount nets to zero although two returns
        // remain individually mismatched; production POS service cannot create this over-refund.
        $otherReturn = DB::table('returns')->insertGetId(['invoice_id' => $invoiceId,
            'actor_type' => Admin::class, 'actor_id' => $owner->id, 'reason' => 'Synthetic offset probe',
            'status' => 'accepted', 'idempotency_key' => 'd03-offset-'.Str::uuid(),
            'public_id' => (string) Str::uuid()]);
        DB::table('return_lines')->insert(['return_id' => $otherReturn, 'invoice_id' => $invoiceId,
            'sale_id' => $saleId, 'public_id' => (string) Str::uuid(), 'quantity' => 1,
            'condition' => 'opened', 'disposition' => 'sellable', 'unit_price' => '50.00',
            'discount_amount' => '0.00', 'net_amount' => '50.00', 'purchase_amount' => '20.00',
            'currency' => 'PKR', 'sale_snapshot' => $returnSnapshot,
            'snapshot_sha256' => hash('sha256', $returnSnapshot)]);
        $paymentDestination = DB::table('pos_payment_destinations')->insertGetId([
            'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'method' => 'card',
            'display_name' => 'Synthetic unmatched historical tender',
            'created_by_admin_id' => $owner->id]);
        $tenderSnapshot = json_encode(['contract' => 'd03-offset-tender'], JSON_THROW_ON_ERROR);
        $originalTender = DB::table('pos_tender_allocations')->insertGetId([
            'public_id' => (string) Str::uuid(), 'invoice_id' => $invoiceId,
            'outlet_id' => $outlet->id, 'payment_destination_id' => $paymentDestination,
            'sequence' => 1, 'method' => 'card', 'amount' => '100.00',
            'destination_snapshot' => $tenderSnapshot, 'snapshot_sha256' => hash('sha256', $tenderSnapshot),
            'reconciliation_state' => 'pending', 'created_by_admin_id' => $owner->id]);
        $refundSnapshot = json_encode(['contract' => 'd03-offset-refund'], JSON_THROW_ON_ERROR);
        DB::table('pos_refund_allocations')->insert([
            'public_id' => (string) Str::uuid(), 'return_id' => $otherReturn,
            'invoice_id' => $invoiceId, 'outlet_id' => $outlet->id,
            'original_tender_allocation_id' => $originalTender,
            'refund_destination_id' => $paymentDestination, 'amount' => '90.00',
            'original_method' => 'card', 'refund_method' => 'card',
            'requested_by_admin_id' => $owner->id, 'original_tender_snapshot' => $tenderSnapshot,
            'refund_destination_snapshot' => $refundSnapshot,
            'snapshot_sha256' => hash('sha256', $refundSnapshot), 'recorded_at' => now()]);
        $offsetResponse = $this->send($ownerClient, 'GET', $uri)->assertOk()
            ->assertJsonPath('data.obligations.pos_return_count', 2)
            ->assertJsonPath('data.obligations.pos_return_amount', '90.00')
            ->assertJsonPath('data.obligations.pos_refunds_recorded', '90.00')
            ->assertJsonPath('data.obligations.pos_refund_difference', '0.00')
            ->assertJsonPath('data.obligations.pos_returns_unmatched_count', 2)
            ->assertJsonPath('data.obligations.pos_returns_over_refunded_count', 1)
            ->assertJsonPath('data.obligations.requires_manual_review', true)
            ->assertJsonPath('data.obligations.archive_eligibility', 'not_approved_for_business_history');
        $this->assertEquals($priorInvoice, DB::table('invoices')->where('id', $invoiceId)->firstOrFail());
        $this->assertEquals($priorEvent, DB::table('claim_events')->where('id', $eventId)->firstOrFail());
        $this->assertStringNotContainsString('PRIVATE-CUSTOMER-NAME', $offsetResponse->getContent());

    }

    public function test_archived_invoice_and_claim_direct_retrieval_caps_large_original_record_sets(): void
    {
        $outlet = $this->outlet('D03 bounded historical documents', '099');
        $fallback = $this->outlet('D03 bounded document fallback', '100');
        $owner = $this->member('d03-bounded-documents-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($fallback);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $product = new Product;
        $product->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'D03 bounded test product',
            'outlet_id' => $outlet->id, 'category' => 'accessory', 'price' => '1.00', 'qty' => 0])->save();
        $invoicePublicId = (string) Str::uuid();
        $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => $invoicePublicId, 'invoice_number' => 'D03-CAP-'.Str::random(12),
            'total_bill' => '101.00', 'final_bill' => '101.00', 'currency' => 'PKR',
            'customer_name' => 'PRIVATE-BOUND-CUSTOMER', 'created_at' => now(),
            'business_snapshot' => json_encode(['business_name' => 'Synthetic bounded source'], JSON_THROW_ON_ERROR)]);
        $sales = [];
        for ($index = 0; $index < 101; $index++) {
            $sales[] = ['public_id' => (string) Str::uuid(), 'outlet_id' => $outlet->id,
                'product_id' => $product->id, 'invoice_id' => $invoiceId, 'quantity' => 1,
                'sale_date' => now()->toDateString(), 'sale_price' => '1.00',
                'total_price' => '1.00', 'net_total_price' => '1.00'];
        }
        DB::table('sales')->insert($sales);
        $claimPublicId = (string) Str::uuid();
        $claimId = DB::table('claims')->insertGetId(['outlet_id' => $outlet->id,
            'public_id' => $claimPublicId, 'claim_number' => 'D03-CAP-'.Str::random(12),
            'product_id' => $product->id, 'invoice_id' => $invoiceId,
            'quantity' => 1, 'status' => 'closed', 'issue_description' => 'PRIVATE-BOUND-ISSUE']);
        $events = [];
        for ($sequence = 1; $sequence <= 101; $sequence++) {
            $snapshot = json_encode(['contract' => 'd03-bounded-claim', 'sequence' => $sequence], JSON_THROW_ON_ERROR);
            $events[] = ['public_id' => (string) Str::uuid(), 'claim_id' => $claimId,
                'sequence' => $sequence, 'status' => 'closed', 'note' => 'PRIVATE-BOUND-NOTE',
                'occurred_at' => now(), 'snapshot' => $snapshot,
                'snapshot_sha256' => hash('sha256', $snapshot)];
        }
        DB::table('claim_events')->insert($events);
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $path = '/internal/admin/outlet-management/'.$outlet->public_id.'/history';
        $input = ['password' => 'SyntheticPass123!', 'purpose' => 'Historical records audit review'];
        $this->send($client, 'POST', $path.'/invoices/'.$invoicePublicId.'/retrieve', $input)->assertStatus(409);
        $outlet->forceFill(['archived_at' => now()])->save(); // Synthetic-only bypass, not production archival.
        $summary = $this->send($client, 'GET', $path)->assertOk();
        $this->assertStringNotContainsString('PRIVATE-BOUND-CUSTOMER', $summary->getContent());
        $this->assertStringNotContainsString('PRIVATE-BOUND-ISSUE', $summary->getContent());
        $invoice = $this->send($client, 'POST', $path.'/invoices/'.$invoicePublicId.'/retrieve', $input)->assertOk()
            ->assertJsonPath('data.invoice_id', $invoicePublicId)
            ->assertJsonPath('data.line_count', 101)
            ->assertJsonPath('data.lines_truncated', true)
            ->assertJsonPath('data.customer_name', 'PRIVATE-BOUND-CUSTOMER');
        $this->send($client, 'POST', $path.'/invoices/'.$invoicePublicId.'/reconstructed-pdf', $input)
            ->assertStatus(409);
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'archived_invoice_pdf_reconstructed')->count());
        $this->assertCount(100, $invoice->json('data.lines'));
        $this->assertSame($sales[0]['public_id'], $invoice->json('data.lines.0.id'));
        $claim = $this->send($client, 'POST', $path.'/claims/'.$claimPublicId.'/retrieve', $input)->assertOk()
            ->assertJsonPath('data.claim_id', $claimPublicId)
            ->assertJsonPath('data.event_count', 101)
            ->assertJsonPath('data.events_truncated', true)
            ->assertJsonPath('data.issue_description', 'PRIVATE-BOUND-ISSUE');
        $this->assertCount(100, $claim->json('data.events'));
        $this->assertSame($events[0]['public_id'], $claim->json('data.events.0.id'));
        $this->assertSame(101, DB::table('sales')->where('invoice_id', $invoiceId)->count());
        $this->assertSame(101, DB::table('claim_events')->where('claim_id', $claimId)->count());
        $this->assertSame(0, DB::table('identity_audit_events')->where('outlet_id', $outlet->id)
            ->where('action', 'outlet_archived')->count());
    }

    public function test_archived_promotion_claims_cover_scoped_and_global_invoice_links_without_leaking_secrets(): void
    {
        $archived = $this->outlet('D03 historical promotions', '087');
        $fallback = $this->outlet('D03 promotion owner fallback', '088');
        $owner = $this->member('d03-promotions-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($fallback);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id, ['assigned_at' => now()]);
        $limited = $this->member('d03-promotions-limited@example.invalid', ['shops.enter', 'shop.inventory']);
        $limited->shops()->attach($fallback);
        $product = new Product;
        $product->forceFill(['public_id' => (string) Str::uuid(), 'outlet_id' => $archived->id,
            'name' => 'D03 promotion product', 'category' => 'accessory', 'price' => '20.00', 'qty' => 0])->save();
        $promotion = function (?int $outletId, string $label) use ($owner): int {
            return DB::table('promotions')->insertGetId(['public_id' => (string) Str::uuid(),
                'outlet_id' => $outletId, 'name' => $label, 'mode' => 'coupon', 'code' => $label,
                'discount_type' => 'fixed', 'discount_value' => '1.00', 'status' => 'inactive',
                'created_by_admin_id' => $owner->id, 'created_at' => now(), 'updated_at' => now()]);
        };
        $directId = $promotion($archived->id, 'D03DIRECT');
        $linkedId = $promotion(null, 'D03LINKED');
        $globalId = $promotion(null, 'D03GLOBAL');
        $outsideId = $promotion($fallback->id, 'D03OUTSIDE');
        DB::table('promotion_products')->insert(['promotion_id' => $linkedId, 'product_id' => $product->id]);
        $invoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $archived->id,
            'public_id' => (string) Str::uuid(), 'total_bill' => '10.00', 'final_bill' => '10.00']);
        $outsideInvoiceId = DB::table('invoices')->insertGetId(['outlet_id' => $fallback->id,
            'public_id' => (string) Str::uuid(), 'total_bill' => '10.00', 'final_bill' => '10.00']);
        $orderId = DB::table('orders')->insertGetId(['public_id' => (string) Str::uuid(),
            'order_number' => 'D03-PROMO-'.Str::random(10), 'order_type' => 'mobile',
            'customer_name' => 'PRIVATE-PROMOTION-ORDER-CUSTOMER',
            'customer_mobile' => '03001234567']);
        DB::table('order_items')->insert(['order_id' => $orderId, 'outlet_id' => $archived->id,
            'product_id' => $product->id, 'item_type' => 'mobile', 'title' => 'Synthetic promotion order line',
            'quantity' => 1]);
        $private = json_encode(['private' => 'DO-NOT-EXPOSE-PROMO-CUSTOMER'], JSON_THROW_ON_ERROR);
        $claim = function (int $campaign, string $status, ?int $invoiceId = null,
            string $channel = 'pos', ?int $orderId = null) use ($private): string {
            $publicId = (string) Str::uuid();
            DB::table('promotion_claims')->insert(['public_id' => $publicId, 'promotion_id' => $campaign,
                'channel' => $channel, 'owner_key' => hash('sha256', $publicId),
                'customer_key' => hash('sha256', 'DO-NOT-EXPOSE-PROMO-CUSTOMER'),
                'invoice_id' => $invoiceId, 'order_id' => $orderId,
                'discount_amount' => '1.00', 'status' => $status,
                'snapshot' => $private, 'snapshot_sha256' => hash('sha256', $private),
                'released_at' => $status === 'released' ? now() : null,
                'release_reason' => $status === 'released' ? 'Synthetic release' : null,
                'created_at' => now()]);

            return $publicId;
        };
        $directClaim = $claim($directId, 'active');
        $linkedClaim = $claim($linkedId, 'released');
        $globalClaim = $claim($globalId, 'active', $invoiceId);
        $websiteClaim = $claim($globalId, 'active', null, 'website', $orderId);
        $claim($outsideId, 'active', $outsideInvoiceId);
        // A globally linked campaign must not leak claims assigned to a DIFFERENT outlet.
        $outsideLinkedClaim = $claim($linkedId, 'active', $outsideInvoiceId);
        $outsideScopedClaim = $claim($directId, 'active', $outsideInvoiceId);
        $outsideOrderId = DB::table('orders')->insertGetId(['public_id' => (string) Str::uuid(),
            'order_number' => 'D03-OTHER-'.Str::random(10), 'order_type' => 'mobile',
            'customer_name' => 'PRIVATE-OTHER-ORDER-CUSTOMER', 'customer_mobile' => '03001234567']);
        $outsideProduct = new Product;
        $outsideProduct->forceFill(['public_id' => (string) Str::uuid(), 'outlet_id' => $fallback->id,
            'name' => 'Other outlet product', 'category' => 'accessory', 'price' => '20.00', 'qty' => 0])->save();
        DB::table('order_items')->insert(['order_id' => $outsideOrderId, 'outlet_id' => $fallback->id,
            'product_id' => $outsideProduct->id, 'item_type' => 'mobile',
            'title' => 'Other outlet order item', 'quantity' => 1]);
        $outsideWebsiteClaim = $claim($linkedId, 'active', null, 'website', $outsideOrderId);
        $verifiedSnapshot = json_encode(['contract' => 'promotion-claim.v1', 'discount_amount' => '1.00'], JSON_THROW_ON_ERROR);
        $wrongAmountSnapshot = json_encode(['contract' => 'promotion-claim.v1', 'discount_amount' => '9.00'], JSON_THROW_ON_ERROR);
        DB::table('promotion_claims')->where('public_id', $websiteClaim)
            ->update(['snapshot' => $verifiedSnapshot, 'snapshot_sha256' => hash('sha256', $verifiedSnapshot)]);
        DB::table('promotion_claims')->where('public_id', $globalClaim)
            ->update(['snapshot' => $wrongAmountSnapshot, 'snapshot_sha256' => hash('sha256', $wrongAmountSnapshot)]);
        $before = DB::table('promotion_claims')->whereIn('public_id', [$directClaim, $linkedClaim, $globalClaim, $websiteClaim])
            ->orderBy('id')->get()->all();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $path = '/internal/admin/outlet-management/'.$archived->public_id.'/history';
        $this->send($client, 'GET', $path)->assertStatus(409);
        $archived->forceFill(['archived_at' => now()])->save(); // Synthetic test only; real business archive remains blocked.
        $response = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.promotion_campaign_count', 2)
            ->assertJsonPath('data.obligations.promotion_claim_count', 3)
            ->assertJsonPath('data.obligations.requires_manual_review', true)
            ->assertJsonPath('data.promotion_history.campaign_count', 2)
            ->assertJsonPath('data.promotion_history.claim_count', 3)
            ->assertJsonPath('data.promotion_history.active_claim_count', 3)
            ->assertJsonPath('data.promotion_history.released_claim_count', 0)
            ->assertJsonPath('data.promotion_history.unbound_claim_count', 1)
            ->assertJsonPath('data.promotion_history.claims_truncated', false)
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 0)
            ->assertJsonPath('data.promotion_history.active_claims_with_release_event', 0)
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 3)
            ->assertJsonPath('data.promotion_history.missing_financial_references', 2)
            ->assertJsonPath('data.promotion_history.mismatched_financial_references', 0)
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 0)
            ->assertJsonPath('data.promotion_history.snapshot_contract_or_amount_mismatches', 2)
            ->assertJsonPath('data.promotion_history.claims.0.id', $websiteClaim)
            ->assertJsonPath('data.promotion_history.claims.0.channel', 'website')
            ->assertJsonPath('data.promotion_history.claims.0.order_id', DB::table('orders')->where('id', $orderId)->value('public_id'))
            ->assertJsonPath('data.promotion_history.claims.1.id', $globalClaim)
            ->assertJsonPath('data.promotion_history.claims.2.id', $directClaim)
            ->assertJsonPath('data.promotion_history.requires_review', true)
            ->assertJsonPath('data.promotion_history.archive_eligibility', 'not_approved_for_business_history');
        $this->assertStringNotContainsString('DO-NOT-EXPOSE-PROMO-CUSTOMER', $response->getContent());
        $this->assertStringNotContainsString('PRIVATE-PROMOTION-ORDER-CUSTOMER', $response->getContent());
        $this->assertStringNotContainsString('D03OUTSIDE', $response->getContent());
        $this->assertStringNotContainsString($outsideLinkedClaim, $response->getContent());
        $this->assertStringNotContainsString($outsideScopedClaim, $response->getContent());
        $this->assertStringNotContainsString($outsideWebsiteClaim, $response->getContent());
        $this->assertStringNotContainsString('PRIVATE-OTHER-ORDER-CUSTOMER', $response->getContent());
        $this->assertStringNotContainsString($linkedClaim, $response->getContent());

        // The claimed event is independent retained evidence, never original JSON byte proof.
        $websiteClaimInternalId = DB::table('promotion_claims')->where('public_id', $websiteClaim)->value('id');
        $claimEventId = DB::table('promotion_events')->insertGetId([
            'promotion_id' => $globalId, 'promotion_claim_id' => $websiteClaimInternalId,
            'event_type' => 'claimed', 'snapshot' => $verifiedSnapshot,
            'snapshot_sha256' => hash('sha256', $verifiedSnapshot), 'created_at' => now(),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 2);
        DB::table('promotion_events')->where('id', $claimEventId)->update([
            'snapshot' => $wrongAmountSnapshot,
            'snapshot_sha256' => hash('sha256', $wrongAmountSnapshot),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 3);
        DB::table('promotion_events')->where('id', $claimEventId)->update([
            'snapshot' => $verifiedSnapshot, 'snapshot_sha256' => hash('sha256', $verifiedSnapshot),
            'promotion_id' => $directId,
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 3);
        DB::table('promotion_events')->where('id', $claimEventId)->update(['promotion_id' => $globalId]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 2);

        // The release row must retain a matching released event and reason. Synthetic only.
        $reason = 'Synthetic manual release';
        DB::table('promotion_claims')->where('public_id', $directClaim)->update([
            'status' => 'released', 'released_at' => now(), 'release_reason' => $reason,
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 1)
            ->assertJsonPath('data.promotion_history.active_claims_with_release_event', 0);
        $directInternalId = DB::table('promotion_claims')->where('public_id', $directClaim)->value('id');
        $releasePayload = json_encode(['claim' => json_decode($private, true, flags: JSON_THROW_ON_ERROR),
            'reason' => $reason], JSON_THROW_ON_ERROR);
        $releaseEventId = DB::table('promotion_events')->insertGetId([
            'promotion_id' => $directId, 'promotion_claim_id' => $directInternalId,
            'event_type' => 'released', 'snapshot' => $releasePayload,
            'snapshot_sha256' => hash('sha256', $releasePayload), 'created_at' => now(),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 0);
        $wrongReleasePayload = json_encode(['claim' => json_decode($private, true, flags: JSON_THROW_ON_ERROR),
            'reason' => 'Wrong synthetic release reason'], JSON_THROW_ON_ERROR);
        DB::table('promotion_events')->where('id', $releaseEventId)->update(['snapshot' => $wrongReleasePayload]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 1);
        $wrongClaimPayload = json_encode(['claim' => ['private' => 'Synthetic divergent claim'],
            'reason' => $reason], JSON_THROW_ON_ERROR);
        DB::table('promotion_events')->where('id', $releaseEventId)->update(['snapshot' => $wrongClaimPayload]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 1);
        DB::table('promotion_events')->where('id', $releaseEventId)->update([
            'snapshot' => $releasePayload, 'promotion_id' => $globalId,
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 1);
        DB::table('promotion_events')->where('id', $releaseEventId)->update(['promotion_id' => $directId]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 0);
        DB::table('promotion_claims')->where('public_id', $directClaim)->update([
            'status' => 'active', 'released_at' => null, 'release_reason' => null,
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.active_claims_with_release_event', 1);
        DB::table('promotion_events')->where('id', $releaseEventId)->delete();
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.active_claims_with_release_event', 0)
            ->assertJsonPath('data.promotion_history.released_claims_without_matching_release_event', 0);

        // Deliberately divergent internal booking; this does not simulate a provider transaction.
        $adjustmentSnapshot = json_encode(['synthetic' => 'internal-financial-reference'], JSON_THROW_ON_ERROR);
        DB::table('monetary_adjustments')->insert([
            ['id' => (string) Str::uuid(), 'invoice_id' => $invoiceId, 'order_id' => null,
                'kind' => 'promotion', 'treatment' => 'discount', 'amount' => '2.00', 'currency' => 'PKR',
                'source_reference' => $globalClaim, 'snapshot' => $adjustmentSnapshot,
                'snapshot_sha256' => hash('sha256', $adjustmentSnapshot), 'created_at' => now()],
            ['id' => (string) Str::uuid(), 'invoice_id' => null, 'order_id' => $orderId,
                'kind' => 'promotion', 'treatment' => 'discount', 'amount' => '1.00', 'currency' => 'PKR',
                'source_reference' => $websiteClaim, 'snapshot' => $adjustmentSnapshot,
                'snapshot_sha256' => hash('sha256', $adjustmentSnapshot), 'created_at' => now()],
        ]);
        $divergent = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.missing_financial_references', 0)
            ->assertJsonPath('data.promotion_history.mismatched_financial_references', 1)
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 2)
            ->assertJsonPath('data.promotion_history.snapshot_contract_or_amount_mismatches', 2);
        $this->assertStringNotContainsString($outsideLinkedClaim, $divergent->getContent());
        DB::table('monetary_adjustments')->where('source_reference', $globalClaim)->update(['amount' => '1.00']);
        $matched = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.missing_financial_references', 0)
            ->assertJsonPath('data.promotion_history.mismatched_financial_references', 0)
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 2)
            ->assertJsonPath('data.promotion_history.snapshot_contract_or_amount_mismatches', 2);
        $this->assertStringNotContainsString($outsideLinkedClaim, $matched->getContent());
        // Replace synthetic malformed finance snapshots with the actual canonical v1
        // digest contract. The original promotion claim snapshot remains untouched.
        $financialSnapshots = [];
        foreach ([$globalClaim, $websiteClaim] as $claimPublic) {
            $row = DB::table('monetary_adjustments')->where('source_reference', $claimPublic)->firstOrFail();
            $snapshot = MoneySnapshot::adjustment($row->id, 'promotion',
                '1.00', 'Synthetic booked promotion', $claimPublic);
            DB::table('monetary_adjustments')->where('id', $row->id)->update([
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'snapshot_sha256' => MoneySnapshot::digest($snapshot),
            ]);
            $financialSnapshots[$claimPublic] = $snapshot;
        }
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 0)
            ->assertJsonPath('data.promotion_history.missing_financial_references', 0)
            ->assertJsonPath('data.promotion_history.mismatched_financial_references', 0);
        $originalDigest = MoneySnapshot::digest($financialSnapshots[$globalClaim]);
        DB::table('monetary_adjustments')->where('source_reference', $globalClaim)->update([
            'snapshot' => json_encode([...$financialSnapshots[$globalClaim],
                'reason' => 'Synthetic tampered text'], JSON_THROW_ON_ERROR),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 1);
        DB::table('monetary_adjustments')->where('source_reference', $globalClaim)->update([
            'snapshot' => json_encode($financialSnapshots[$globalClaim], JSON_THROW_ON_ERROR),
            'snapshot_sha256' => str_repeat('0', 64),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 1);
        DB::table('monetary_adjustments')->where('source_reference', $globalClaim)->update([
            'snapshot_sha256' => $originalDigest,
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.financial_snapshot_digest_mismatches', 0);
        // A matching claim must not conceal an unrelated outlet-owned discount.
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.unmatched_financial_adjustments', 0);
        $insertSyntheticAdjustment = function (int $invoiceId, string $source): string {
            $id = (string) Str::uuid();
            $snapshot = MoneySnapshot::adjustment($id, 'promotion', '1.00',
                'Synthetic archived financial reconciliation', $source);
            DB::table('monetary_adjustments')->insert([
                'id' => $id, 'invoice_id' => $invoiceId, 'order_id' => null,
                'kind' => 'promotion', 'treatment' => 'discount', 'amount' => '1.00',
                'currency' => 'PKR', 'source_reference' => $source,
                'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'snapshot_sha256' => MoneySnapshot::digest($snapshot), 'created_at' => now(),
            ]);

            return $id;
        };
        $orphanAdjustment = $insertSyntheticAdjustment($invoiceId, (string) Str::uuid());
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.unmatched_financial_adjustments', 1);
        $outsideAdjustment = $insertSyntheticAdjustment($outsideInvoiceId, (string) Str::uuid());
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.unmatched_financial_adjustments', 1);
        DB::table('monetary_adjustments')->whereIn('id',
            [$orphanAdjustment, $outsideAdjustment])->delete();
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.unmatched_financial_adjustments', 0);
        $this->assertEquals($before, DB::table('promotion_claims')
            ->whereIn('public_id', [$directClaim, $linkedClaim, $globalClaim, $websiteClaim])->orderBy('id')->get()->all());
        for ($index = 0; $index < 51; $index++) {
            $claim($directId, 'active');
        }
        $bounded = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claim_count', 54)
            ->assertJsonPath('data.promotion_history.active_claim_count', 54)
            ->assertJsonPath('data.promotion_history.unbound_claim_count', 52)
            ->assertJsonPath('data.promotion_history.claims_without_matching_claimed_event', 53)
            ->assertJsonPath('data.promotion_history.claims_truncated', true);
        $this->assertCount(50, $bounded->json('data.promotion_history.claims'));
        $this->assertStringNotContainsString('DO-NOT-EXPOSE-PROMO-CUSTOMER', $bounded->getContent());
        // A shared website order does NOT establish which outlet earned the order-level claim.
        // Report only an ambiguous count, never the other outlet's claim ID or snapshot.
        $sharedOrderId = DB::table('orders')->insertGetId(['public_id' => (string) Str::uuid(),
            'order_number' => 'D03-SHARED-'.Str::random(10), 'order_type' => 'mobile',
            'customer_name' => 'PRIVATE-SHARED-ORDER-CUSTOMER', 'customer_mobile' => '03001234567']);
        DB::table('order_items')->insert([
            ['order_id' => $sharedOrderId, 'outlet_id' => $archived->id, 'product_id' => $product->id,
                'item_type' => 'mobile', 'title' => 'Archived line', 'quantity' => 1],
            ['order_id' => $sharedOrderId, 'outlet_id' => $fallback->id, 'product_id' => $outsideProduct->id,
                'item_type' => 'mobile', 'title' => 'Other outlet line', 'quantity' => 1],
        ]);
        $ambiguousClaim = $claim($globalId, 'active', null, 'website', $sharedOrderId);
        $sharedReport = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.promotion_history.claim_count', 54)
            ->assertJsonPath('data.promotion_history.shared_order_claims_held_for_review', 1)
            ->assertJsonPath('data.promotion_history.requires_review', true)
            ->assertJsonPath('data.promotion_history.claims_truncated', true);
        $this->assertCount(50, $sharedReport->json('data.promotion_history.claims'));
        $this->assertStringNotContainsString($ambiguousClaim, $sharedReport->getContent());
        $this->assertStringNotContainsString('PRIVATE-SHARED-ORDER-CUSTOMER', $sharedReport->getContent());
        // No provider calls: synthetic internal payment/refund statuses, isolated DB only.
        $originalSinglePaymentCount = DB::table('payments')->count();
        $singlePaymentId = DB::table('payments')->insertGetId([
            'order_id' => $orderId, 'status' => 'unknown', 'amount' => '1.00',
            'gateway' => 'easypaisa', 'merchant' => 'test-merchant', 'mode' => 'sandbox',
            'attempt_key' => 'D03-single-'.Str::random(12), 'public_id' => (string) Str::uuid(),
            'reconciliation_required_at' => now(),
        ]);
        $sharedPaymentId = DB::table('payments')->insertGetId([
            'order_id' => $sharedOrderId, 'status' => 'unknown', 'amount' => '1.00',
            'gateway' => 'easypaisa', 'merchant' => 'test-merchant', 'mode' => 'sandbox',
            'attempt_key' => 'D03-shared-'.Str::random(12), 'public_id' => (string) Str::uuid(),
            'reconciliation_required_at' => now(),
        ]);
        foreach ([$singlePaymentId, $sharedPaymentId] as $paymentId) {
            DB::table('refunds')->insert([
                'payment_id' => $paymentId, 'amount' => '1.00', 'status' => 'pending',
                'operation_key' => 'D03-refund-'.Str::random(12), 'provider' => 'manual',
                'public_id' => (string) Str::uuid(),
            ]);
        }
        $financeReport = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_payments_for_reconciliation', 1)
            ->assertJsonPath('data.obligations.website_refunds_for_reconciliation', 1)
            ->assertJsonPath('data.obligations.website_shared_orders_held_for_financial_review', 1)
            ->assertJsonPath('data.obligations.requires_manual_review', true);
        $this->assertStringNotContainsString('test-merchant', $financeReport->getContent());
        $this->assertSame($originalSinglePaymentCount + 2, DB::table('payments')->count());
        // The synthetic shared-order payment/refund cannot be silently assigned to either outlet.
        $this->assertSame(2, DB::table('refunds')->whereIn('payment_id', [$singlePaymentId, $sharedPaymentId])->count());
        DB::table('payments')->where('id', $singlePaymentId)->update([
            'status' => 'paid', 'reconciliation_required_at' => null, 'completed_at' => now(),
        ]);
        DB::table('refunds')->where('payment_id', $singlePaymentId)->update([
            'status' => 'completed', 'completed_at' => now(),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_payments_for_reconciliation', 0)
            ->assertJsonPath('data.obligations.website_refunds_for_reconciliation', 0)
            ->assertJsonPath('data.obligations.website_shared_orders_held_for_financial_review', 1)
            ->assertJsonPath('data.obligations.website_paid_provider_payments_without_matching_recorded_receipt', 1);
        // A synthetic paid flag does not establish the retained provider-callback trail.
        $transaction = 'SYNTHETIC-PAID-'.Str::random(12);
        DB::table('payments')->where('id', $singlePaymentId)
            ->update(['transaction_reference' => $transaction]);
        $receiptId = DB::table('payment_receipts')->insertGetId([
            'payment_id' => $singlePaymentId, 'gateway' => 'easypaisa',
            'merchant' => 'test-merchant', 'mode' => 'sandbox',
            'event_id' => 'D03-synthetic-'.Str::random(12),
            'transaction_reference' => $transaction, 'amount' => '2.00',
            'currency' => 'PKR', 'payload_hash' => str_repeat('a', 64),
            'outcome' => 'paid', 'received_at' => now(), 'verified_at' => now(),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_paid_provider_payments_without_matching_recorded_receipt', 1);
        DB::table('payment_receipts')->where('id', $receiptId)->update(['amount' => '1.00']);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_paid_provider_payments_without_matching_recorded_receipt', 0);
        DB::table('payment_receipts')->where('id', $receiptId)->update(['outcome' => 'failed']);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_paid_provider_payments_without_matching_recorded_receipt', 1);
        DB::table('payment_receipts')->where('id', $receiptId)->update(['outcome' => 'paid']);
        DB::table('payments')->where('id', $sharedPaymentId)
            ->update(['status' => 'paid', 'completed_at' => now(), 'reconciliation_required_at' => null]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_paid_provider_payments_without_matching_recorded_receipt', 0)
            ->assertJsonPath('data.obligations.website_shared_orders_held_for_financial_review', 1);
        $this->assertStringNotContainsString($transaction, $financeReport->getContent());
        // A completed status with no retained refund proof must not silently clear review.
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 1);
        DB::table('refunds')->where('payment_id', $singlePaymentId)->update([
            'provider' => 'manual_verified', 'evidence_hash' => str_repeat('a', 64),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 0);
        DB::table('refunds')->where('payment_id', $singlePaymentId)->update(['evidence_hash' => 'bad-digest']);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 1);
        DB::table('refunds')->where('payment_id', $singlePaymentId)->update([
            'provider' => 'easypaisa', 'evidence_hash' => str_repeat('a', 64),
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 1);
        DB::table('refunds')->where('payment_id', $singlePaymentId)->update([
            'provider_reference' => 'SYNTHETIC-REFUND-REFERENCE',
        ]);
        $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 0);
        // Shared-order completed refunds are reserved for joint financial attribution.
        DB::table('refunds')->where('payment_id', $sharedPaymentId)->update([
            'status' => 'completed', 'completed_at' => now(),
        ]);
        $sharedRefundReport = $this->send($client, 'GET', $path)->assertOk()
            ->assertJsonPath('data.obligations.website_completed_refunds_missing_recorded_evidence', 0)
            ->assertJsonPath('data.obligations.website_shared_orders_held_for_financial_review', 1);
        $this->assertStringNotContainsString('SYNTHETIC-REFUND-REFERENCE', $sharedRefundReport->getContent());

        $other = $this->client();
        $this->login($other, $limited->email)->assertOk();
        $this->send($other, 'GET', $path)->assertForbidden();
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
            $client = $this->client();
            $this->login($client, $unprivileged->email)->assertOk();
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
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
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
        $outlet = $this->outlet('Preferences Owner Outlet', '059');
        $owner->shops()->attach($outlet);
        $limited = $this->member('pref-limited@example.invalid', ['config.portal-presentation.manage']);
        $url = '/internal/admin/pos/portal-preferences';
        $guest = $this->client();
        $this->send($guest, 'GET', $url)->assertUnauthorized();
        $this->send($guest, 'PUT', $url, [])->assertUnauthorized();
        $denied = $this->client();
        $this->login($denied, $limited->email)->assertOk();
        $this->send($denied, 'GET', $url)->assertForbidden();
        $this->send($denied, 'PUT', $url, [])->assertForbidden();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('pos-portal-preferences')->where('values.invoice_page_length', '15')
            ->where('values.inventory_page_length', '10')->where('values.auto_focus_search', true)
            ->where('values.remember_search', false)->where('values.navigation', [])
            ->has('options.invoice_search_category', 8)->has('navigation', 9));
        $values = app(PortalPreferences::class)->current();
        $values['invoice_page_length'] = '50';
        $values['inventory_page_length'] = '100';
        $values['invoice_search_category'] = 'customer_name';
        $values['invoice_density'] = 'compact';
        $values['remember_search'] = true;
        $values['navigation'] = ['invoices' => ['label' => 'Customer documents', 'visible' => false, 'order' => 140]];
        $this->send($client, 'PUT', $url, [...$values, 'outlet_id' => 'unauthorized'])->assertUnprocessable();
        $this->send($client, 'PUT', $url, [...$values, 'navigation' => [
            'sales' => ['label' => 'Sales', 'visible' => false, 'order' => 100],
        ]])->assertUnprocessable();
        // Identity limiter is 5/min by path+IP; keep guest, denied, invalid and successful writes below cap.
        $this->assertFalse(in_array('500', app(PortalPreferences::class)->catalogue($owner)['options']['invoice_page_length'], true));
        $this->assertSame(0, DB::table('pos_settings')->where('group', 'portal')->count());
        $this->send($client, 'PUT', $url, $values)->assertOk()
            ->assertJsonPath('data.invoice_page_length', '50')->assertJsonPath('data.remember_search', true)
            ->assertJsonPath('data.invoice_density', 'compact')
            ->assertJsonPath('data.navigation.invoices.label', 'Customer documents')
            ->assertJsonPath('data.navigation.invoices.visible', false);
        $this->send($client, 'GET', $url)->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('values.invoice_page_length', '50')->where('values.remember_search', true)
            ->where('values.navigation.invoices.visible', false));
        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('shell.navigation', fn ($navigation) => collect($navigation)->where('key', 'invoices')->isEmpty()));
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $outlet->public_id])->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/invoices')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('view.workspace.key', 'invoices'));
        $this->assertSame(13, DB::table('pos_settings')->where('group', 'portal')->count());
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
        $guest = $this->client();
        $this->send($guest, 'GET', $url)->assertUnauthorized();
        $sales = $this->client();
        $this->login($sales, $denied->email)->assertOk();
        $this->send($sales, 'GET', $url)->assertForbidden();
        $this->send($sales, 'GET', '/internal/admin/pos/workspace/master-data')->assertForbidden();
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
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
        $client = $this->client();
        $this->login($client, $actor->email)->assertOk();
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

    public function test_offline_owner_recovery_is_disabled_and_unbound_by_default(): void
    {
        $outlet = $this->outlet('D05 disabled recovery', '101');
        $owner = $this->member('d05-disabled-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($outlet);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        config(['identity.offline_owner_recovery.enabled' => false,
            'identity.offline_owner_recovery.owner_admin_public_id' => '']);
        $client = $this->client();
        $this->login($client, $owner->email)->assertOk();
        $uri = '/internal/admin/auth/offline-owner-recovery';
        $this->send($client, 'POST', $uri.'/rotate', ['current_password' => 'SyntheticPass123!'])
            ->assertStatus(503);
        $this->send($client, 'POST', $uri.'/redeem', ['email' => $owner->email,
            'code' => 'AAAAAA-BBBBBB-CCCCCC-DDDDDD-EEEEEE-FFFFFF',
            'password' => 'AnotherPass123!', 'password_confirmation' => 'AnotherPass123!'])
            ->assertStatus(503);
        $this->assertSame(0, DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)->count());
    }

    public function test_offline_owner_recovery_codes_are_one_time_owner_bound_rotated_and_revoke_sessions(): void
    {
        $outlet = $this->outlet('D05 isolated owner recovery', '102');
        $owner = $this->member('d05-bound-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($outlet);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $limited = $this->member('d05-recovery-operator@example.invalid', ['shops.enter']);
        $limited->shops()->attach($outlet);
        config(['identity.offline_owner_recovery.enabled' => true,
            'identity.offline_owner_recovery.owner_admin_public_id' => $owner->public_id]);
        $uri = '/internal/admin/auth/offline-owner-recovery';
        $ownerClient = $this->client();
        $this->login($ownerClient, $owner->email)->assertOk();
        $limitedClient = $this->client();
        $this->login($limitedClient, $limited->email)->assertOk();
        $this->send($limitedClient, 'POST', $uri.'/rotate',
            ['current_password' => 'SyntheticPass123!'])->assertForbidden();
        $this->send($ownerClient, 'POST', $uri.'/rotate',
            ['current_password' => 'wrong-password'])->assertForbidden();
        $issued = $this->send($ownerClient, 'POST', $uri.'/rotate',
            ['current_password' => 'SyntheticPass123!'])->assertOk()->json('data.codes');
        $this->assertCount(8, $issued);
        $this->assertCount(8, array_unique($issued));
        $this->assertMatchesRegularExpression('/\A(?:[A-F0-9]{6}-){5}[A-F0-9]{6}\z/D', $issued[0]);
        $this->assertStringNotContainsString($issued[0], json_encode(DB::table('owner_offline_recovery_codes')
            ->where('admin_id', $owner->id)->get()->all(), JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString($issued[0], json_encode(DB::table('identity_audit_events')
            ->where('account_id', $owner->id)->get()->all(), JSON_THROW_ON_ERROR));
        $this->assertSame(8, DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
            ->whereNull('revoked_at')->count());
        $rotated = $this->send($ownerClient, 'POST', $uri.'/rotate',
            ['current_password' => 'SyntheticPass123!'])->assertOk();
        $freshCodes = $rotated->json('data.codes');
        $this->assertCount(8, $freshCodes);
        $this->assertSame(8, DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
            ->whereNotNull('revoked_at')->count());
        $this->assertStringContainsString('no-store', (string) $rotated->headers->get('Cache-Control'));
        $emailToken = Password::broker('admin')->createToken($owner); // Synthetic only; never delivered.
        $this->assertNotEmpty($emailToken);
        $this->assertSame(1, DB::table('admin_password_reset_tokens')->where('email', $owner->email)->count());
        $reset = ['email' => $owner->email, 'password' => 'UpdatedOfflinePass123!',
            'password_confirmation' => 'UpdatedOfflinePass123!'];
        $this->send($limitedClient, 'POST', $uri.'/redeem', [...$reset, 'code' => $issued[0]])
            ->assertUnprocessable();
        $this->send($limitedClient, 'POST', $uri.'/redeem',
            [...$reset, 'email' => $limited->email, 'code' => $freshCodes[0]])->assertUnprocessable();
        $guestClient = $this->client();
        $valid = $this->send($guestClient, 'POST', $uri.'/redeem',
            [...$reset, 'code' => $freshCodes[0]])->assertOk();
        $this->assertStringContainsString('no-store', (string) $valid->headers->get('Cache-Control'));
        $this->assertTrue(Hash::check('UpdatedOfflinePass123!', $owner->fresh()->password));
        $this->assertSame(0, DB::table('admin_password_reset_tokens')->where('email', $owner->email)->count());
        $this->assertSame(2, (int) $owner->fresh()->auth_version);
        $this->assertSame(1, DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
            ->whereNotNull('used_at')->count());
        $this->assertSame(0, DB::table('account_sessions')->where('guard', 'admin')
            ->where('account_id', $owner->id)->whereNull('revoked_at')->count());
        $this->send($ownerClient, 'GET', '/internal/admin/account')->assertUnauthorized();
        $this->send($guestClient, 'POST', $uri.'/redeem',
            [...$reset, 'code' => $freshCodes[0]])->assertUnprocessable();
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'owner_offline_code_consumed')->count());
        $this->assertSame(3, DB::table('identity_audit_events')->where('action', 'owner_offline_recovery_denied')->count());
        $this->assertSame(2, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'owner_offline_codes_rotated')->count());
        $this->assertTrue(Hash::check('SyntheticPass123!', $limited->fresh()->password));
    }

    public function test_offline_owner_codes_revoke_on_normal_password_change_and_pages_stay_bound(): void
    {
        $outlet = $this->outlet('D05 password change', '103');
        $owner = $this->member('d05-change-owner@example.invalid', ['shops.enter',
            'team-members.full-access.assign', 'admin.business-profile.manage']);
        $owner->shops()->attach($outlet);
        $owner->roles()->attach(Role::where('name', 'Full Access')->firstOrFail()->id,
            ['assigned_at' => now()]);
        $other = $this->member('d05-change-staff@example.invalid', ['shops.enter']);
        $other->shops()->attach($outlet);
        config(['identity.offline_owner_recovery.enabled' => true,
            'identity.offline_owner_recovery.owner_admin_public_id' => $owner->public_id]);
        $ownerClient = $this->client();
        $this->login($ownerClient, $owner->email)->assertOk();
        $staffClient = $this->client();
        $this->login($staffClient, $other->email)->assertOk();
        $this->send($ownerClient, 'GET', '/internal/admin/manage-account')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('offline_owner_recovery_available', true));
        $this->send($staffClient, 'GET', '/internal/admin/manage-account')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('offline_owner_recovery_available', false));
        $this->send($staffClient, 'GET', '/internal/admin/reset-password')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('offline_owner_recovery_available', true));
        $codes = $this->send($ownerClient, 'POST', '/internal/admin/auth/offline-owner-recovery/rotate',
            ['current_password' => 'SyntheticPass123!'])->assertOk()->json('data.codes');
        $this->send($ownerClient, 'PATCH', '/internal/admin/auth/password',
            ['current_password' => 'SyntheticPass123!', 'password' => 'OwnerChangedPass123!',
                'password_confirmation' => 'OwnerChangedPass123!'])->assertOk();
        $this->assertTrue(Hash::check('OwnerChangedPass123!', $owner->fresh()->password));
        $this->assertSame(8, DB::table('owner_offline_recovery_codes')->where('admin_id', $owner->id)
            ->whereNotNull('revoked_at')->whereNull('used_at')->count());
        $guest = $this->client();
        $this->send($guest, 'POST', '/internal/admin/auth/offline-owner-recovery/redeem',
            ['email' => $owner->email, 'code' => $codes[0], 'password' => 'BadResetPass123!',
                'password_confirmation' => 'BadResetPass123!'])->assertUnprocessable();
        $this->assertTrue(Hash::check('OwnerChangedPass123!', $owner->fresh()->password));
        $this->assertSame(0, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'owner_offline_code_consumed')->count());
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
