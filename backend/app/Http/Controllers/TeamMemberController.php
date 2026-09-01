<?php

namespace App\Http\Controllers;

use App\Identity\TeamMemberAdministration;
use App\Models\Admin;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class TeamMemberController extends Controller
{
    public function index(TeamMemberAdministration $service)
    {
        return response()->json(['data' => $service->members(Auth::guard('admin')->user())]);
    }

    public function roles(TeamMemberAdministration $service)
    {
        return response()->json(['data' => $service->catalogue(Auth::guard('admin')->user())]);
    }

    public function store(Request $request, TeamMemberAdministration $service)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:128'], 'job_title' => ['nullable', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'], 'role_ids' => ['required', 'array', 'min:1'], 'role_ids.*' => ['uuid'],
            'outlet_ids' => ['required', 'array'], 'outlet_ids.*' => ['uuid']]);

        return response()->json(['data' => $this->member($service->createMember(Auth::guard('admin')->user(), $data))], 201);
    }

    public function update(Request $request, Admin $member, TeamMemberAdministration $service)
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:255'], 'job_title' => ['nullable', 'string', 'max:120'],
            'active' => ['sometimes', 'boolean'], 'role_ids' => ['sometimes', 'array', 'min:1'], 'role_ids.*' => ['uuid'],
            'outlet_ids' => ['sometimes', 'array'], 'outlet_ids.*' => ['uuid']]);

        return response()->json(['data' => $this->member($service->updateMember(Auth::guard('admin')->user(), $member, $data))]);
    }

    public function storeRole(Request $request, TeamMemberAdministration $service)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'permissions' => ['required', 'array'], 'permissions.*' => ['string', 'max:120']]);
        $role = $service->createRole(Auth::guard('admin')->user(), $data);

        return response()->json(['data' => $this->role($role)], 201);
    }

    public function updateRole(Request $request, Role $role, TeamMemberAdministration $service)
    {
        $data = $request->validate(['name' => ['sometimes', 'string', 'max:120'], 'permissions' => ['sometimes', 'array'], 'permissions.*' => ['string', 'max:120']]);

        return response()->json(['data' => $this->role($service->updateRole(Auth::guard('admin')->user(), $role, $data))]);
    }

    public function destroyRole(Role $role, TeamMemberAdministration $service)
    {
        $service->deleteRole(Auth::guard('admin')->user(), $role);

        return response()->noContent();
    }

    private function member(Admin $member): array
    {
        return ['id' => $member->public_id, 'name' => $member->name, 'email' => $member->email, 'job_title' => $member->job_title,
            'status' => $member->usable() ? 'active' : 'disabled', 'roles' => $member->roleNames(),
            'outlets' => $member->shops()->pluck('outlets.public_id')->all()];
    }

    private function role(Role $role): array
    {
        return ['id' => $role->public_id, 'name' => $role->name, 'system' => $role->is_system,
            'protected' => $role->is_protected, 'permissions' => $role->permissionCodes()];
    }
}
