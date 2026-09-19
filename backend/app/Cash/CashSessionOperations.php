<?php

namespace App\Cash;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class CashSessionOperations
{
    public function open(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['opening_cash', 'business_date']);
        $data = Validator::make($input, ['opening_cash' => 'required', 'business_date' => 'nullable|date'])->validate();

        return $this->mutate($actor, $outlet, 'open', $key, $input, 'shop.cash', function (Admin $fresh) use ($outlet, $data) {
            $lockedOutlet = DB::table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
            // Recheck under the archive's outlet row lock: pre-lock authorization can become stale.
            abort_if($lockedOutlet->status || $lockedOutlet->archived_at !== null, 403, 'Outlet is archived or unavailable.');
            if (DB::table('cash_sessions')->where('outlet_id', $outlet->id)->where('status', 'open')->exists()) {
                throw new LogicException('Outlet already has an open cash session.');
            }
            $opening = SourceRow::money((string) $data['opening_cash']);
            if (bccomp($opening, '0.00', 2) < 0) {
                throw ValidationException::withMessages(['opening_cash' => 'Opening cash cannot be negative.']);
            }
            $public = (string) Str::uuid();
            $now = now();
            DB::table('cash_sessions')->insert([
                'public_id' => $public, 'outlet_id' => $outlet->id, 'open_outlet_guard' => $outlet->id,
                'opened_by_admin_id' => $fresh->id, 'business_date' => $data['business_date'] ?? $now->toDateString(),
                'status' => 'open', 'version' => 1, 'opening_cash' => $opening,
                'opened_at' => $now, 'created_at' => $now, 'updated_at' => $now,
            ]);
            IdentityAudit::record('admin', $fresh->id, 'cash_session_opened', 'cash-session:'.$public, $outlet->id);

            return $this->sessionResult(DB::table('cash_sessions')->where('public_id', $public)->firstOrFail());
        });
    }

    public function recordEntry(Admin $actor, Outlet $outlet, string $sessionId, string $key, array $input): array
    {
        $this->fields($input, ['session_version', 'type', 'amount', 'reason', 'reference']);
        $data = Validator::make($input, [
            'session_version' => 'required|integer|min:1', 'type' => 'required|in:cash_in,expense,payout',
            'amount' => 'required', 'reason' => 'required|string|max:500', 'reference' => 'nullable|string|max:120',
        ])->validate();

        return $this->mutate($actor, $outlet, 'entry.create', $key, ['session_id' => $sessionId, 'input' => $input],
            'shop.cash', function (Admin $fresh) use ($outlet, $sessionId, $data) {
                $session = $this->lockOpenSession($outlet, $sessionId, (int) $data['session_version']);
                $amount = SourceRow::money((string) $data['amount']);
                if (bccomp($amount, '0.00', 2) <= 0) {
                    throw ValidationException::withMessages(['amount' => 'Cash entry amount must be positive.']);
                }
                $public = (string) Str::uuid();
                DB::table('cash_entries')->insert([
                    'public_id' => $public, 'cash_session_id' => $session->id, 'outlet_id' => $outlet->id,
                    'type' => $data['type'], 'amount' => $amount, 'reason' => trim($data['reason']),
                    'reference' => $this->nullable($data['reference'] ?? null), 'status' => 'pending',
                    'created_by_admin_id' => $fresh->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->bumpVersion($session->id);
                IdentityAudit::record('admin', $fresh->id, 'cash_entry_created', 'cash-entry:'.$public, $outlet->id);

                return $this->entryResult(DB::table('cash_entries')->where('public_id', $public)->firstOrFail());
            });
    }

    public function reviewEntry(Admin $actor, Outlet $outlet, string $sessionId, string $entryId, string $key, array $input): array
    {
        $this->fields($input, ['session_version', 'decision', 'notes']);
        $data = Validator::make($input, [
            'session_version' => 'required|integer|min:1', 'decision' => 'required|in:approved,rejected',
            'notes' => 'nullable|string|max:1000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'entry.review', $key, ['session_id' => $sessionId, 'entry_id' => $entryId, 'input' => $input],
            'shop.cash.approve', function (Admin $fresh) use ($outlet, $sessionId, $entryId, $data) {
                $session = $this->lockOpenSession($outlet, $sessionId, (int) $data['session_version']);
                $entry = DB::table('cash_entries')->where('public_id', $entryId)->where('cash_session_id', $session->id)
                    ->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
                if ($entry->status !== 'pending') {
                    throw new LogicException('Cash entry was already reviewed.');
                }
                DB::table('cash_entries')->where('id', $entry->id)->update([
                    'status' => $data['decision'], 'reviewed_by_admin_id' => $fresh->id,
                    'review_notes' => $this->nullable($data['notes'] ?? null), 'reviewed_at' => now(), 'updated_at' => now(),
                ]);
                $this->bumpVersion($session->id);
                IdentityAudit::record('admin', $fresh->id, 'cash_entry_'.$data['decision'], 'cash-entry:'.$entryId, $outlet->id);

                return $this->entryResult(DB::table('cash_entries')->where('id', $entry->id)->firstOrFail());
            });
    }

    public function close(Admin $actor, Outlet $outlet, string $sessionId, string $key, array $input): array
    {
        $this->fields($input, ['session_version', 'actual_cash', 'variance_reason']);
        $data = Validator::make($input, [
            'session_version' => 'required|integer|min:1', 'actual_cash' => 'required',
            'variance_reason' => 'nullable|string|max:1000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'close', $key, ['session_id' => $sessionId, 'input' => $input],
            'shop.cash', function (Admin $fresh) use ($outlet, $sessionId, $data) {
                DB::table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
                $session = $this->lockOpenSession($outlet, $sessionId, (int) $data['session_version']);
                if (DB::table('cash_entries')->where('cash_session_id', $session->id)->where('status', 'pending')->exists()) {
                    throw new LogicException('Pending cash entries must be reviewed before closing.');
                }
                $summary = $this->summaryLocked($session);
                $actual = SourceRow::money((string) $data['actual_cash']);
                if (bccomp($actual, '0.00', 2) < 0) {
                    throw ValidationException::withMessages(['actual_cash' => 'Actual cash cannot be negative.']);
                }
                $variance = bcsub($actual, $summary['expected_cash'], 2);
                $reason = $this->nullable($data['variance_reason'] ?? null);
                $approverId = null;
                if (bccomp($variance, '0.00', 2) !== 0) {
                    if (! $reason) {
                        throw ValidationException::withMessages(['variance_reason' => 'A non-zero cash variance requires a reason.']);
                    }
                    if (! app(Access::class)->allows($fresh, 'shop.cash.approve', $outlet->fresh())) {
                        abort(403, 'Cash variance approval permission is required.');
                    }
                    $approverId = $fresh->id;
                }
                $snapshot = [...$summary, 'contract' => 'cash-closing.v1', 'actual_cash' => $actual,
                    'variance_amount' => $variance, 'variance_reason' => $reason, 'closed_by_admin_id' => $fresh->public_id];
                $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                DB::table('cash_sessions')->where('id', $session->id)->update([
                    'open_outlet_guard' => null, 'status' => 'closed', 'version' => $session->version + 1,
                    'expected_cash' => $summary['expected_cash'], 'actual_cash' => $actual,
                    'variance_amount' => $variance, 'variance_reason' => $reason,
                    'variance_approved_by_admin_id' => $approverId, 'closed_by_admin_id' => $fresh->id,
                    'closing_snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json),
                    'closed_at' => now(), 'updated_at' => now(),
                ]);
                IdentityAudit::record('admin', $fresh->id, 'cash_session_closed', 'cash-session:'.$sessionId, $outlet->id);

                return $this->sessionResult(DB::table('cash_sessions')->where('id', $session->id)->firstOrFail());
            });
    }

    public function session(Admin $actor, Outlet $outlet, string $sessionId): array
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.cash', $outlet->fresh()), 403);
        $session = DB::table('cash_sessions')->where('public_id', $sessionId)->where('outlet_id', $outlet->id)->firstOrFail();

        return $this->sessionResult($session);
    }

    public function activeSessionId(Outlet $outlet, bool $lock = false): ?int
    {
        $query = DB::table('cash_sessions')->where('outlet_id', $outlet->id)->where('status', 'open');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->value('id');
    }

    public function transactionSession(Outlet $outlet, bool $required): ?int
    {
        $lockedOutlet = DB::table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
        // Serialize every tender/refund cash-session lookup with archival and reject stale outlet snapshots.
        abort_if($lockedOutlet->status || $lockedOutlet->archived_at !== null, 403, 'Outlet is archived or unavailable.');
        $sessionId = $this->activeSessionId($outlet, true);
        if ($required && ! $sessionId) {
            throw new LogicException('Cash tender/refund requires an open cash session for this outlet.');
        }

        return $sessionId;
    }

    private function summaryLocked(object $session): array
    {
        $entries = DB::table('cash_entries')->where('cash_session_id', $session->id)->orderBy('id')->lockForUpdate()->get();
        $tenders = DB::table('pos_tender_allocations')->where('cash_session_id', $session->id)->orderBy('id')->lockForUpdate()->get();
        $refunds = DB::table('pos_refund_allocations')->where('cash_session_id', $session->id)->orderBy('id')->lockForUpdate()->get();
        $cashSales = $this->sum($tenders->where('method', 'cash')->pluck('amount'));
        $cashRefunds = $this->sum($refunds->where('refund_method', 'cash')->pluck('amount'));
        $cashIn = $this->sum($entries->where('status', 'approved')->where('type', 'cash_in')->pluck('amount'));
        $expenses = $this->sum($entries->where('status', 'approved')->where('type', 'expense')->pluck('amount'));
        $payouts = $this->sum($entries->where('status', 'approved')->where('type', 'payout')->pluck('amount'));
        $expected = bcadd((string) $session->opening_cash, $cashSales, 2);
        $expected = bcadd($expected, $cashIn, 2);
        $expected = bcsub($expected, $cashRefunds, 2);
        $expected = bcsub($expected, $expenses, 2);
        $expected = bcsub($expected, $payouts, 2);

        return ['session_id' => $session->public_id, 'outlet_id' => (int) $session->outlet_id,
            'business_date' => (string) $session->business_date, 'opening_cash' => (string) $session->opening_cash,
            'cash_sales' => $cashSales, 'approved_cash_in' => $cashIn, 'cash_refunds' => $cashRefunds,
            'expenses' => $expenses, 'payouts' => $payouts, 'expected_cash' => $expected,
            'non_cash_destinations' => $this->nonCashSummary($tenders)];
    }

    private function nonCashSummary($tenders): array
    {
        $result = [];
        foreach ($tenders->where('method', '!=', 'cash')->groupBy('payment_destination_id') as $destinationId => $rows) {
            $first = $rows->first();
            $destination = json_decode($first->destination_snapshot, true, flags: JSON_THROW_ON_ERROR);
            $gross = $this->sum($rows->pluck('amount'));
            $fees = $adjustments = $expectedNet = $receivedNet = $variance = '0.00';
            $settled = 0;
            foreach ($rows as $row) {
                if ((int) $row->settlement_version < 1) {
                    continue;
                }
                $event = DB::table('pos_settlement_events')->where('tender_allocation_id', $row->id)
                    ->orderByDesc('sequence')->first();
                if (! $event) {
                    continue;
                }
                $settled++;
                $fees = bcadd($fees, (string) $event->fee_amount, 2);
                $adjustments = bcadd($adjustments, (string) $event->adjustment_amount, 2);
                $expectedNet = bcadd($expectedNet, (string) $event->expected_net_amount, 2);
                $receivedNet = bcadd($receivedNet, (string) $event->received_net_amount, 2);
                $variance = bcadd($variance, (string) $event->variance_amount, 2);
            }
            $result[] = ['destination_id' => $destination['destination_id'], 'method' => $first->method,
                'display_name' => $destination['display_name'], 'gross_expected_receipts' => $gross,
                'allocation_count' => $rows->count(), 'settled_allocation_count' => $settled,
                'merchant_fees' => $fees, 'adjustments' => $adjustments, 'expected_net' => $expectedNet,
                'received_net' => $receivedNet, 'settlement_variance' => $variance];
        }

        return array_values($result);
    }

    private function sessionResult(object $row): array
    {
        $base = ['session_id' => $row->public_id, 'outlet_id' => Outlet::whereKey($row->outlet_id)->value('public_id'),
            'business_date' => (string) $row->business_date, 'status' => $row->status, 'version' => (int) $row->version,
            'opening_cash' => (string) $row->opening_cash, 'opened_at' => $row->opened_at, 'closed_at' => $row->closed_at];
        if ($row->status === 'closed') {
            return [...$base, 'closing' => json_decode($row->closing_snapshot, true, flags: JSON_THROW_ON_ERROR),
                'snapshot_sha256' => $row->snapshot_sha256];
        }

        return [...$base, 'summary' => $this->summaryLocked($row),
            'entries' => DB::table('cash_entries')->where('cash_session_id', $row->id)->orderBy('id')->get()
                ->map(fn ($entry) => $this->entryResult($entry))->all()];
    }

    private function entryResult(object $row): array
    {
        return ['entry_id' => $row->public_id, 'type' => $row->type, 'amount' => (string) $row->amount,
            'reason' => $row->reason, 'reference' => $row->reference, 'status' => $row->status,
            'created_by_admin_id' => Admin::whereKey($row->created_by_admin_id)->value('public_id'),
            'reviewed_by_admin_id' => $row->reviewed_by_admin_id ? Admin::whereKey($row->reviewed_by_admin_id)->value('public_id') : null,
            'review_notes' => $row->review_notes, 'reviewed_at' => $row->reviewed_at];
    }

    private function lockOpenSession(Outlet $outlet, string $sessionId, int $version): object
    {
        $session = DB::table('cash_sessions')->where('public_id', $sessionId)->where('outlet_id', $outlet->id)
            ->lockForUpdate()->firstOrFail();
        if ($session->status !== 'open') {
            throw new LogicException('Cash session is already closed.');
        }
        if ((int) $session->version !== $version) {
            throw new LogicException('Cash session version changed.');
        }

        return $session;
    }

    private function bumpVersion(int $sessionId): void
    {
        DB::table('cash_sessions')->where('id', $sessionId)->increment('version', 1, ['updated_at' => now()]);
    }

    private function mutate(Admin $actor, Outlet $outlet, string $operation, string $key, mixed $payload, string $permission, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $permission, $callback) {
            $fresh = $actor->fresh();
            abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, $permission, $outlet->fresh()), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identity = ['actor_scope' => Admin::class.':'.$fresh->id, 'operation' => 'cash-sessions.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([
                ...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different cash-session request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'resource_type' => 'cash_session', 'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected cash-session fields.']);
        }
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function sum($values): string
    {
        return $values->reduce(fn (string $sum, $value) => bcadd($sum, (string) $value, 2), '0.00');
    }
}
