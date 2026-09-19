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

    public function archivedHistory(Admin $actor, string $publicId): array
    {
        abort_unless($this->canManage($actor), 403);
        $outlet = Outlet::where('public_id', $publicId)->firstOrFail();
        abort_unless($outlet->archived_at !== null, 409, 'Outlet is not archived.');
        return [
            'outlet_code' => $outlet->outlet_code,
            'name' => $outlet->name,
            'archived_at' => $outlet->archived_at->toIso8601String(),
            'cash_sessions' => DB::table('cash_sessions')->where('outlet_id', $outlet->id)
                ->where('status', 'closed')->orderByDesc('business_date')->limit(50)
                ->get(['public_id', 'business_date', 'status', 'expected_cash', 'actual_cash', 'closed_at'])
                ->map(fn ($row) => [
                    'id' => $row->public_id, 'business_date' => $row->business_date,
                    'status' => $row->status, 'expected_cash' => (string) $row->expected_cash,
                    'actual_cash' => (string) $row->actual_cash, 'closed_at' => $row->closed_at,
                ])->all(),
            'cash_session_count' => DB::table('cash_sessions')->where('outlet_id', $outlet->id)->count(),
            'cash_entry_count' => DB::table('cash_entries')->where('outlet_id', $outlet->id)->count(),
        ];
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
            // Fail closed by default. Only completed cash history and immutable audit are exempt;
            // any other present or future linked table, including products, claims and orders,
            // retains the prior archival block until its obligations have a separate proof.
            $linked = DB::select("SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'outlet_id'");
            $retainedHistory = ['outlet_admins', 'identity_audit_events', 'team_member_audit_events',
                'pos_audit_logs', 'cash_sessions', 'cash_entries'];
            foreach ($linked as $row) {
                if (in_array($row->name, $retainedHistory, true)) { continue; }
                abort_if(DB::table($row->name)->where('outlet_id', $outlet->id)->exists(),
                    409, 'Outlet has linked business history or outstanding obligations; archival needs a reviewed transition.');
            }
            abort_if(DB::table('cash_sessions')->where('outlet_id', $outlet->id)
                ->where('status', '!=', 'closed')->exists(), 409, 'Close every cash session before archiving.');
            abort_if(DB::table('cash_entries')->where('outlet_id', $outlet->id)
                ->where('status', 'pending')->exists(), 409, 'Resolve pending cash entries before archiving.');
            // Transfers may reference an outlet through nonstandard source/destination columns.
            foreach (['stock_transfers' => ['source_outlet_id', 'destination_outlet_id'],
                'stock_transfer_lines' => ['source_outlet_id', 'destination_outlet_id'],
                'stock_transfer_receipts' => ['destination_outlet_id']] as $table => $columns) {
                foreach ($columns as $column) {
                    abort_if(DB::table($table)->where($column, $outlet->id)->exists(), 409,
                        'Outlet has transfer history or outstanding transfer obligations; archival needs reviewed transition.');
                }
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
