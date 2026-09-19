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
            // Owner-only historical invoice/sale and warranty-claim summary, without customer PII,
            // claim narratives, internal notes, payment instruments or event snapshot payloads.
            'sales_claim_history' => [
                'invoice_count' => DB::table('invoices')->where('outlet_id', $outlet->id)->count(),
                'sale_line_count' => DB::table('sales')->where('outlet_id', $outlet->id)->count(),
                'claim_count' => DB::table('claims')->where('outlet_id', $outlet->id)->count(),
                'claim_event_count' => DB::table('claim_events as e')->join('claims as c', 'c.id', '=', 'e.claim_id')
                    ->where('c.outlet_id', $outlet->id)->count(),
                'invoices' => DB::table('invoices')->where('outlet_id', $outlet->id)
                    ->orderByDesc('id')->limit(50)
                    ->get(['public_id', 'invoice_number', 'currency', 'final_bill', 'created_at'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'number' => $row->invoice_number,
                        'currency' => $row->currency, 'final_bill' => (string) $row->final_bill,
                        'created_at' => $row->created_at])->all(),
                'claims' => DB::table('claims as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
                    ->where('c.outlet_id', $outlet->id)->where('i.outlet_id', $outlet->id)
                    ->orderByDesc('c.id')->limit(50)
                    ->get(['c.public_id', 'c.claim_number', 'c.status', 'i.public_id as invoice_id'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'number' => $row->claim_number,
                        'status' => $row->status, 'invoice_id' => $row->invoice_id])->all(),
            ],
            // Protected read-only stock and transfer summaries exclude supplier identities, documents and IMEIs.
            'stock_history' => [
                'product_count' => DB::table('products')->where('outlet_id', $outlet->id)->count(),
                'unit_count' => DB::table('stock_units as u')->join('products as p', 'p.id', '=', 'u.product_id')
                    ->where('p.outlet_id', $outlet->id)->count(),
                'movement_count' => DB::table('stock_movements')->where('outlet_id', $outlet->id)->count(),
                'acquisition_count' => DB::table('stock_acquisitions')->where('outlet_id', $outlet->id)->count(),
                'stocktake_count' => DB::table('stocktake_sessions')->where('outlet_id', $outlet->id)->count(),
                'transfer_count' => DB::table('stock_transfers')->where('source_outlet_id', $outlet->id)
                    ->orWhere('destination_outlet_id', $outlet->id)->count(),
                // Both source and destination are historical owners of the same immutable transfer.
                'transfers' => DB::table('stock_transfers as t')
                    ->join('outlets as src', 'src.id', '=', 't.source_outlet_id')
                    ->join('outlets as dst', 'dst.id', '=', 't.destination_outlet_id')
                    ->where(fn ($query) => $query->where('t.source_outlet_id', $outlet->id)
                        ->orWhere('t.destination_outlet_id', $outlet->id))
                    ->orderByDesc('t.id')->limit(50)
                    ->get(['t.public_id', 't.transfer_number', 't.status', 'src.public_id as source_id',
                        'dst.public_id as destination_id', 't.dispatched_at', 't.completed_at'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'number' => $row->transfer_number,
                        'status' => $row->status, 'source_id' => $row->source_id,
                        'destination_id' => $row->destination_id,
                        'dispatched_at' => $row->dispatched_at, 'completed_at' => $row->completed_at])->all(),
                'products' => DB::table('products')->where('outlet_id', $outlet->id)->orderBy('id')->limit(50)
                    ->get(['public_id', 'product_code', 'name', 'qty', 'isDeleted'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'code' => $row->product_code,
                        'name' => $row->name, 'quantity' => (int) $row->qty,
                        'product_archived' => (bool) $row->isDeleted])->all(),
                'movements' => DB::table('stock_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')
                    ->where('m.outlet_id', $outlet->id)->where('p.outlet_id', $outlet->id)
                    ->orderByDesc('m.id')->limit(50)
                    ->get(['p.public_id as product_id', 'm.type', 'm.quantity_change', 'm.stock_after', 'm.created_at'])
                    ->map(fn ($row) => ['product_id' => $row->product_id, 'type' => $row->type,
                        'quantity_change' => (int) $row->quantity_change,
                        'stock_after' => (int) $row->stock_after, 'created_at' => $row->created_at])->all(),
            ],
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
