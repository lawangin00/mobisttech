<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class TeamMemberAdministration
{
    public function catalogue(Admin $actor): array
    {
        $this->require($actor, 'team-members.view');

        return Role::query()->whereNull('archived_at')->orderBy('name')->get()->map(fn (Role $role) => [
            'id' => $role->public_id, 'name' => $role->name, 'system' => $role->is_system,
            'protected' => $role->is_protected, 'permissions' => $role->permissionCodes(),
        ])->all();
    }

    public function members(Admin $actor): array
    {
        $this->require($actor, 'team-members.view');
        $outlets = $actor->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')->pluck('outlets.id')->all();

        return Admin::query()->whereHas('shops', fn ($query) => $query->whereIn('outlets.id', $outlets))
            ->with(['roles:id,public_id,name', 'shops:id,public_id,name'])->orderBy('name')->get()->map(fn (Admin $member) => [
                'id' => $member->public_id, 'name' => $member->name, 'email' => $member->email,
                'job_title' => $member->job_title, 'status' => $member->usable() ? 'active' : 'disabled',
                'roles' => $member->roles->map(fn ($role) => ['id' => $role->public_id, 'name' => $role->name])->all(),
                'outlets' => $member->shops->map(fn ($outlet) => ['id' => $outlet->public_id, 'name' => $outlet->name])->all(),
            ])->all();
    }

    public function createMember(Admin $actor, array $data): Admin
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $this->require($actor, 'team-members.manage');
            [$roles, $outlets] = $this->validateAssignments($actor, $data['role_ids'], $data['outlet_ids']);
            abort_if(Admin::where('email', $data['email'])->exists(), 422, 'A Team Member with this email already exists.');
            $member = new Admin;
            $member->forceFill([
                'name' => trim($data['name']), 'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']), 'job_title' => $this->nullableTrim($data['job_title'] ?? null),
                'status' => ! ($data['active'] ?? true), 'permissions' => [], 'auth_version' => 1,
            ])->save();
            $this->syncAssignments($member, $actor, $roles, $outlets);
            $this->audit($actor, $member, 'team_member_created', null, $this->snapshot($member->fresh()));

            return $member->fresh();
        });
    }

    public function updateMember(Admin $actor, Admin $member, array $data): Admin
    {
        return DB::transaction(function () use ($actor, $member, $data) {
            $actor = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $member = Admin::query()->lockForUpdate()->findOrFail($member->id);
            $this->require($actor, 'team-members.manage');
            abort_if($actor->is($member), 403, 'A Team Member cannot change their own authority.');
            $before = $this->snapshot($member);
            $roleIds = $data['role_ids'] ?? $member->roles()->pluck('roles.public_id')->all();
            $outletIds = $data['outlet_ids'] ?? $member->shops()->pluck('outlets.public_id')->all();
            [$roles, $outlets] = $this->validateAssignments($actor, $roleIds, $outletIds);
            $changes = [];
            if (array_key_exists('name', $data)) {
                $changes['name'] = trim($data['name']);
            }
            if (array_key_exists('job_title', $data)) {
                $changes['job_title'] = $this->nullableTrim($data['job_title']);
            }
            if (array_key_exists('active', $data)) {
                $this->require($actor, 'team-members.security.manage');
                $changes['status'] = ! $data['active'];
                if (! $data['active']) {
                    $changes['auth_version'] = $member->auth_version + 1;
                    $changes['remember_token'] = Str::random(60);
                }
            }
            if ($changes) {
                $member->forceFill($changes)->save();
            }
            $this->syncAssignments($member, $actor, $roles, $outlets);
            if (($data['active'] ?? true) === false) {
                DB::table('account_sessions')->where('guard', 'admin')->where('account_id', $member->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
            }
            $this->audit($actor, $member, 'team_member_updated', $before, $this->snapshot($member->fresh()));

            return $member->fresh();
        });
    }

    public function createRole(Admin $actor, array $data): Role
    {
        return DB::transaction(function () use ($actor, $data) {
            $actor = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $this->require($actor, 'team-members.roles.manage');
            $permissions = $this->validateDelegatedPermissions($actor, $data['permissions']);
            abort_if(Role::where('name', $data['name'])->orWhere('slug', Str::slug($data['name']))->exists(), 422, 'Role name already exists.');
            $role = new Role;
            $role->forceFill(['public_id' => (string) Str::uuid(), 'name' => trim($data['name']), 'slug' => Str::slug($data['name']),
                'is_system' => false, 'is_protected' => false, 'created_by_admin_id' => $actor->id])->save();
            $this->syncRolePermissions($role, $permissions);
            $this->audit($actor, null, 'custom_role_created', null, ['role_id' => $role->public_id, 'name' => $role->name, 'permissions' => $permissions]);

            return $role->fresh();
        });
    }

    public function updateRole(Admin $actor, Role $role, array $data): Role
    {
        return DB::transaction(function () use ($actor, $role, $data) {
            $actor = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $this->require($actor, 'team-members.roles.manage');
            abort_if($role->is_system || $role->is_protected, 403, 'Supplied system roles cannot be edited.');
            $before = ['role_id' => $role->public_id, 'name' => $role->name, 'permissions' => $role->permissionCodes()];
            $permissions = array_key_exists('permissions', $data) ? $this->validateDelegatedPermissions($actor, $data['permissions']) : $before['permissions'];
            abort_if(! $permissions && $role->admins()->exists(), 409, 'An assigned role cannot be emptied.');
            if (isset($data['name']) && trim($data['name']) !== $role->name) {
                $name = trim($data['name']);
                abort_if(Role::whereKeyNot($role->id)->where(fn ($query) => $query->where('name', $name)->orWhere('slug', Str::slug($name)))->exists(), 422, 'Role name already exists.');
                $role->forceFill(['name' => $name, 'slug' => Str::slug($name)])->save();
            }
            $this->syncRolePermissions($role, $permissions);
            $after = ['role_id' => $role->public_id, 'name' => $role->name, 'permissions' => $permissions];
            $this->audit($actor, null, 'custom_role_updated', $before, $after);

            return $role->fresh();
        });
    }

    public function deleteRole(Admin $actor, Role $role): void
    {
        DB::transaction(function () use ($actor, $role) {
            $actor = Admin::query()->lockForUpdate()->findOrFail($actor->id);
            $role = Role::query()->lockForUpdate()->findOrFail($role->id);
            $this->require($actor, 'team-members.roles.manage');
            abort_if($role->is_system || $role->is_protected, 403, 'Supplied system roles cannot be deleted.');
            abort_if($role->admins()->exists(), 409, 'Assigned roles cannot be deleted.');
            $before = ['role_id' => $role->public_id, 'name' => $role->name, 'permissions' => $role->permissionCodes()];
            DB::table('role_permissions')->where('role_id', $role->id)->delete();
            $role->forceFill(['archived_at' => now()])->save();
            $this->audit($actor, null, 'custom_role_archived', $before, null);
        });
    }

    private function validateAssignments(Admin $actor, array $rolePublicIds, array $outletPublicIds): array
    {
        abort_if(! $rolePublicIds, 422, 'At least one Role is required.');
        $roles = Role::query()->whereNull('archived_at')->whereIn('public_id', array_unique($rolePublicIds))->lockForUpdate()->get();
        abort_if($roles->count() !== count(array_unique($rolePublicIds)), 422, 'A selected Role is unavailable.');
        $actorPermissions = $actor->effectivePermissions();
        foreach ($roles as $role) {
            $permissions = $role->permissionCodes();
            abort_if(array_diff($permissions, $actorPermissions), 403, 'A Role exceeds the delegation ceiling.');
            if ($role->is_protected) {
                abort_unless($actor->hasPermission('team-members.full-access.assign')
                    && $actor->roles()->where('roles.is_protected', true)->exists(), 403, 'Protected Full Access authority is required.');
            }
        }
        $outlets = Outlet::query()->whereIn('public_id', array_unique($outletPublicIds))->where('status', false)->whereNull('archived_at')->lockForUpdate()->get();
        abort_if($outlets->count() !== count(array_unique($outletPublicIds)), 422, 'A selected outlet is unavailable.');
        $allowed = $this->administrableOutletIds($actor);
        abort_if(array_diff($outlets->pluck('id')->all(), $allowed), 403, 'An outlet exceeds the delegation ceiling.');

        return [$roles, $outlets];
    }

    private function validateDelegatedPermissions(Admin $actor, array $permissions): array
    {
        $permissions = array_values(array_unique($permissions));
        $known = DB::table('permission_definitions')->whereIn('code', $permissions)->pluck('code')->all();
        abort_if(count($known) !== count($permissions), 422, 'A permission is unknown.');
        abort_if(array_diff($permissions, $actor->effectivePermissions()), 403, 'A permission exceeds the delegation ceiling.');
        abort_if(in_array('team-members.full-access.assign', $permissions, true), 403, 'Protected authority cannot be granted through a Custom Role.');

        return $permissions;
    }

    private function administrableOutletIds(Admin $actor): array
    {
        $this->require($actor, 'team-members.outlets.assign');

        return $actor->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')->pluck('outlets.id')->all();
    }

    private function syncAssignments(Admin $member, Admin $actor, $roles, $outlets): void
    {
        $member->roles()->syncWithPivotValues($roles->pluck('id')->all(), ['assigned_by_admin_id' => $actor->id, 'assigned_at' => now()]);
        $member->shops()->sync($outlets->pluck('id')->all());
    }

    private function syncRolePermissions(Role $role, array $permissions): void
    {
        DB::table('role_permissions')->where('role_id', $role->id)->delete();
        if ($permissions) {
            DB::table('role_permissions')->insert(array_map(fn ($permission) => ['role_id' => $role->id, 'permission_code' => $permission], $permissions));
        }
    }

    private function snapshot(Admin $member): array
    {
        return ['name' => $member->name, 'email' => $member->email, 'job_title' => $member->job_title,
            'active' => $member->usable(), 'roles' => $member->roleNames(),
            'permissions' => $member->effectivePermissions(), 'outlets' => $member->shops()->orderBy('outlets.id')->pluck('outlets.public_id')->all()];
    }

    private function audit(Admin $actor, ?Admin $subject, string $action, ?array $before, ?array $after): void
    {
        DB::table('team_member_audit_events')->insert([
            'actor_admin_id' => $actor->id, 'subject_admin_id' => $subject?->id, 'action' => $action,
            'actor_name' => $actor->name, 'actor_role_snapshot' => $actor->roleSnapshot(),
            'subject_name' => $subject?->name, 'subject_role_snapshot' => $subject?->roleSnapshot(),
            'before_state' => $before ? json_encode($before, JSON_THROW_ON_ERROR) : null,
            'after_state' => $after ? json_encode($after, JSON_THROW_ON_ERROR) : null, 'occurred_at' => now(),
        ]);
        IdentityAudit::record('admin', $actor->id, $action, $subject ? 'team-member:'.$subject->public_id : null);
    }

    private function require(Admin $actor, string $permission): void
    {
        abort_unless($actor->usable() && $actor->hasPermission($permission), 403);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
