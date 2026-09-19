<?php

namespace App\Identity;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OutletLifecycleAdministration
{
    public function canManage(Admin $actor): bool
    {
        $fresh = $actor->fresh();
        return $fresh->usable() && $fresh->hasPermission('team-members.full-access.assign')
            && $fresh->hasPermission('admin.business-profile.manage')
            && $fresh->roles()->where('roles.is_protected', true)->whereNull('roles.archived_at')->exists();
    }

    public function catalogue(Admin $actor): array
    {
        abort_unless($this->canManage($actor), 403);
        return Outlet::query()->orderBy('outlet_code')->get()->map(fn (Outlet $outlet) => $this->view($outlet))->all();
    }

    public function create(Admin $actor, array $input): array
    {
        abort_unless($this->canManage($actor), 403);
        if (array_diff(array_keys($input), ['name', 'business_address'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected outlet fields.']);
        }
        $data = validator($input, ['name' => ['required', 'string', 'max:160'],
            'business_address' => ['required', 'string', 'max:1000']])->validate();
        $name = trim($data['name']);
        $address = trim($data['business_address']);
        if ($name === '' || $address === '') {
            throw ValidationException::withMessages(['name' => 'Outlet name and address are required.']);
        }
        return DB::transaction(function () use ($actor, $name, $address) {
            // One locked root serializes new code allocation; never reuse an archived code.
            DB::table('business_profiles')->where('id', 1)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($actor), 403);
            $used = Outlet::query()->pluck('outlet_code')->all();
            $code = null;
            for ($i = 1; $i <= 999; $i++) {
                $candidate = sprintf('%03d', $i);
                if (! in_array($candidate, $used, true)) { $code = $candidate; break; }
            }
            abort_if($code === null, 409, 'No outlet codes remain.');
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => $code,
                'name' => $name, 'business_address' => $address, 'status' => false,
                'archived_at' => null, 'legacy_password' => null, 'legacy_remember_token' => null,
                'version' => 1])->save();
            $actor->shops()->attach($outlet->id);
            IdentityAudit::record('admin', $actor->id, 'outlet_created', 'outlet:'.$outlet->public_id, $outlet->id);
            return $this->view($outlet);
        });
    }

    public function archive(Admin $actor, string $publicId, int $version): array
    {
        abort_unless($this->canManage($actor), 403);
        return DB::transaction(function () use ($actor, $publicId, $version) {
            DB::table('business_profiles')->where('id', 1)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($actor), 403);
            $outlet = Outlet::where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            abort_if($outlet->archived_at !== null || $outlet->status, 409, 'Outlet is not active.');
            abort_if((int) $outlet->version !== $version, 409, 'Outlet changed; reload before archiving.');
            abort_if(Outlet::where('status', false)->whereNull('archived_at')->count() <= 1,
                409, 'Cannot archive the last open outlet.');
            // Fail closed for linked business obligations. Identity/audit records remain intact.
            $linked = DB::select("SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'outlet_id'");
            $nonBusiness = ['outlet_admins', 'identity_audit_events', 'team_member_audit_events'];
            foreach ($linked as $row) {
                if (in_array($row->name, $nonBusiness, true)) { continue; }
                abort_if(DB::table($row->name)->where('outlet_id', $outlet->id)->exists(),
                    409, 'Outlet has linked business history or outstanding obligations; archival needs a reviewed transition.');
            }
            $outlet->forceFill(['archived_at' => now(), 'version' => $outlet->version + 1])->save();
            IdentityAudit::record('admin', $actor->id, 'outlet_archived', 'outlet:'.$outlet->public_id, $outlet->id);
            return $this->view($outlet);
        });
    }

    private function view(Outlet $outlet): array
    {
        return ['id' => $outlet->public_id, 'outlet_code' => $outlet->outlet_code,
            'name' => $outlet->name, 'business_address' => $outlet->business_address,
            'status' => $outlet->archived_at !== null ? 'archived' : ($outlet->status ? 'disabled' : 'open'),
            'version' => (int) $outlet->version];
    }
}
