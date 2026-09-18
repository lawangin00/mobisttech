<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PosShellE2eSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $salesOutlet = $this->outlet('E2E Sales Outlet', 'E41');
            $inventoryOutlet = $this->outlet('E2E Inventory Outlet', 'E42');

            $salesRole = $this->role('E2E Salesperson', 'e2e-salesperson', ['shops.enter', 'shop.sales']);
            $inventoryRole = $this->role('E2E Inventory Manager', 'e2e-inventory-manager', ['shops.enter', 'shop.inventory']);
            $operationsRole = $this->role('E2E Operations Manager', 'e2e-operations-manager', [
                'shops.enter', 'shop.cash', 'shop.cash.approve', 'shop.trade-in', 'shop.repairs', 'shop.payments.reconcile',
            ]);
            $mt43Role = $this->role('E2E MT43 Manager', 'e2e-mt43-manager', [
                'shops.enter', 'shop.sales', 'shop.invoices', 'shop.warranty', 'shop.claims', 'reports.view', 'shop.documents.send',
            ]);

            $sales = $this->member('E2E Salesperson', 'e2e-sales@example.invalid', 'Salesperson');
            $inventory = $this->member('E2E Inventory Manager', 'e2e-inventory@example.invalid', 'Inventory Manager');
            $operations = $this->member('E2E Operations Manager', 'e2e-operations@example.invalid', 'Operations Manager');
            $mt43 = $this->member('E2E MT43 Manager', 'e2e-mt43@example.invalid', 'MT43 Manager');

            $this->assign($sales, $salesRole, [$salesOutlet]);
            $this->assign($inventory, $inventoryRole, [$salesOutlet, $inventoryOutlet]);
            $this->assign($operations, $operationsRole, [$salesOutlet]);
            $this->assign($mt43, $mt43Role, [$salesOutlet]);
        });
    }

    private function member(string $name, string $email, string $jobTitle): Admin
    {
        $member = Admin::where('email', $email)->first() ?? new Admin;
        $member->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('SyntheticPass123!'),
            'job_title' => $jobTitle,
            'permissions' => [],
            'auth_version' => ((int) ($member->auth_version ?? 0)) + 1,
            'status' => false,
            'archived_at' => null,
        ])->save();
        DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $member->id)->delete();

        return $member;
    }

    private function outlet(string $name, string $code): Outlet
    {
        $outlet = Outlet::where('outlet_code', $code)->first() ?? new Outlet;
        $outlet->forceFill([
            'public_id' => $outlet->public_id ?: (string) Str::uuid(),
            'name' => $name,
            'outlet_code' => $code,
            'status' => false,
            'archived_at' => null,
        ])->save();

        return $outlet;
    }

    private function role(string $name, string $slug, array $permissions): Role
    {
        $role = Role::where('slug', $slug)->first() ?? new Role;
        $role->forceFill([
            'public_id' => $role->public_id ?: (string) Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'is_system' => false,
            'is_protected' => false,
            'archived_at' => null,
        ])->save();

        DB::table('role_permissions')->where('role_id', $role->id)->delete();
        foreach ($permissions as $permission) {
            DB::table('role_permissions')->insert([
                'role_id' => $role->id,
                'permission_code' => $permission,
            ]);
        }

        return $role;
    }

    private function assign(Admin $member, Role $role, array $outlets): void
    {
        DB::table('admin_roles')->where('admin_id', $member->id)->delete();
        DB::table('admin_roles')->insert([
            'admin_id' => $member->id,
            'role_id' => $role->id,
            'assigned_by_admin_id' => null,
            'assigned_at' => now(),
        ]);

        DB::table('outlet_admins')->where('admin_id', $member->id)->delete();
        foreach ($outlets as $outlet) {
            DB::table('outlet_admins')->insert([
                'admin_id' => $member->id,
                'outlet_id' => $outlet->id,
            ]);
        }
    }
}
