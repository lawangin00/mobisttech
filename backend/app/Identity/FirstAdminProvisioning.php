<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

/** One-time fresh-install owner provisioning; never imports or repairs a legacy account. */
final class FirstAdminProvisioning
{
    private const EMPTY_TABLES = [
        'admins', 'outlets', 'users', 'products', 'stock_units', 'stock_movements',
        'invoices', 'sales', 'claims', 'orders', 'order_items', 'payments',
        'suppliers', 'repair_jobs', 'cash_sessions', 'purchase_orders', 'stocktake_sessions',
    ];

    public function create(string $name, string $email, string $password): Admin
    {
        $data = Validator::make(['name' => trim($name), 'email' => strtolower(trim($email)),
            'password' => $password], [
                'name' => ['required', 'string', 'min:2', 'max:160'],
                'email' => ['required', 'email', 'max:190'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols(), 'max:128'],
            ])->validate();

        return DB::transaction(function () use ($data) {
            // The canonical singleton prevents two concurrent bootstrap operators racing.
            $profile = DB::table('business_profiles')->where('id', 1)->lockForUpdate()->first();
            if (! $profile || DB::table('business_profiles')->count() !== 1) {
                throw new RuntimeException('Fresh business-profile bootstrap is missing or ambiguous.');
            }
            foreach (self::EMPTY_TABLES as $table) {
                if (! Schema::hasTable($table) || DB::table($table)->exists()) {
                    throw new RuntimeException('First Admin setup requires a new, empty business database.');
                }
            }
            $role = Role::where('name', 'Full Access')->where('is_system', true)
                ->where('is_protected', true)->whereNull('archived_at')->lockForUpdate()->first();
            if (! $role || array_diff(['shops.enter', 'team-members.full-access.assign',
                'admin.business-profile.manage'], $role->permissionCodes())) {
                throw new RuntimeException('Required protected Full Access role is not installed.');
            }
            $admin = new Admin;
            $admin->forceFill(['name' => $data['name'], 'email' => $data['email'],
                'password' => Hash::make($data['password']), 'permissions' => [],
                'auth_version' => 1, 'status' => false, 'archived_at' => null])->save();
            $admin->roles()->attach($role->id, ['assigned_by_admin_id' => null, 'assigned_at' => now()]);
            IdentityAudit::record('admin', $admin->id, 'initial_admin_provisioned');

            return $admin;
        }, 3);
    }
}
