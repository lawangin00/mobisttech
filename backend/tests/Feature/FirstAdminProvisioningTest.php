<?php

namespace Tests\Feature;

use App\Identity\FirstAdminProvisioning;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class FirstAdminProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fresh_owner_setup_is_one_time_role_scoped_and_has_no_business_records(): void
    {
        $this->assertSame(0, Admin::count());
        $this->assertSame(0, Outlet::count());
        $service = app(FirstAdminProvisioning::class);
        $owner = $service->create('Fresh Owner', 'FIRST-OWNER@EXAMPLE.INVALID', 'SyntheticStrong123!Pass');
        $this->assertSame('first-owner@example.invalid', $owner->email);
        $this->assertTrue(Hash::check('SyntheticStrong123!Pass', $owner->fresh()->password));
        $this->assertNotSame('SyntheticStrong123!Pass', $owner->fresh()->password);
        $this->assertTrue($owner->fresh()->usable());
        $this->assertSame([], $owner->fresh()->permissions);
        $this->assertTrue($owner->hasPermission('shops.enter'));
        $this->assertTrue($owner->hasPermission('team-members.full-access.assign'));
        $this->assertTrue($owner->hasPermission('admin.business-profile.manage'));
        $this->assertSame(['Full Access'], $owner->roleNames());
        $this->assertSame(0, $owner->shops()->count());
        $this->assertSame(1, DB::table('identity_audit_events')->where('account_id', $owner->id)
            ->where('action', 'initial_admin_provisioned')->count());
        $this->assertSame(['accessory', 'mobile_phone', 'tablet'], DB::table('pos_master_data_options')
            ->where('list_key', 'product_category')->orderBy('code')->pluck('code')->all());
        $this->assertSame(['individual_seller', 'other_business', 'shop_dealer', 'supplier', 'wholesaler'],
            DB::table('pos_master_data_options')->where('list_key', 'acquisition_source_type')
                ->orderBy('code')->pluck('code')->all());
        foreach (['outlets', 'products', 'users', 'sales', 'invoices', 'orders', 'payments'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        try {
            $service->create('Second Owner', 'second-owner@example.invalid', 'SyntheticStrong123!Pass');
            $this->fail('A second initial Admin was allowed.');
        } catch (RuntimeException $error) {
            $this->assertSame(1, Admin::count());
        }
    }

    public function test_bootstrap_refuses_an_outlet_without_admin_and_weak_password(): void
    {
        $outlet = new Outlet;
        $outlet->forceFill(['name' => 'Unsafe existing outlet', 'outlet_code' => '991',
            'public_id' => (string) Str::uuid()])->save();
        try {
            app(FirstAdminProvisioning::class)->create('Fresh Owner', 'new-owner@example.invalid', 'SyntheticStrong123!Pass');
            $this->fail('Existing business data permitted initial Admin provisioning.');
        } catch (RuntimeException $error) {
            $this->assertSame(0, Admin::count());
            $this->assertSame(1, Outlet::count());
        }
        $outlet->delete();
        try {
            app(FirstAdminProvisioning::class)->create('Fresh Owner', 'new-owner@example.invalid', 'weak');
            $this->fail('Weak password accepted.');
        } catch (ValidationException $error) {
            $this->assertSame(0, Admin::count());
        }
    }

    public function test_noninteractive_cli_does_not_create_an_admin_or_accept_a_password_argument(): void
    {
        $this->assertSame(1, Artisan::call('setup:first-admin', ['--no-interaction' => true]));
        $this->assertSame(0, Admin::count());
        $this->assertStringContainsString('interactive private terminal', Artisan::output());
    }
}
