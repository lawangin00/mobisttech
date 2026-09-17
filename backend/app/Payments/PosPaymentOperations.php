<?php

namespace App\Payments;

use App\Cash\CashSessionOperations;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Sales\SalesOperations;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PosPaymentOperations
{
    public const METHODS = ['cash', 'card', 'mobile_wallet', 'bank_transfer'];

    public function __construct(private SalesOperations $sales) {}

    public function createDestination(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['method', 'display_name', 'provider_label', 'masked_identifier', 'active', 'effective_from',
            'effective_until', 'requires_refund_override_approval', 'internal_notes']);
        $data = Validator::make($input, [
            'method' => 'required|in:'.implode(',', self::METHODS), 'display_name' => 'required|string|max:120',            'provider_label' => 'nullable|string|max:120', 'masked_identifier' => 'nullable|string|max:120', 'active' => 'sometimes|boolean',
            'effective_from' => 'nullable|date', 'effective_until' => 'nullable|date|after:effective_from',
            'requires_refund_override_approval' => 'sometimes|boolean', 'internal_notes' => 'nullable|string|max:1000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'destination.create', $key, $input, 'config.payments.manage', function (Admin $fresh) use ($outlet, $data) {
            $public = (string) Str::uuid();
            $id = DB::table('pos_payment_destinations')->insertGetId([
                'public_id' => $public, 'outlet_id' => $outlet->id, 'method' => $data['method'],
                'display_name' => trim($data['display_name']), 'provider_label' => $this->nullable($data['provider_label'] ?? null),
                'masked_identifier' => $this->maskedIdentifier($data['masked_identifier'] ?? null), 'active' => (bool) ($data['active'] ?? true),
                'effective_from' => $this->time($data['effective_from'] ?? null), 'effective_until' => $this->time($data['effective_until'] ?? null),
                'requires_refund_override_approval' => (bool) ($data['requires_refund_override_approval'] ?? true),
                'internal_notes' => $this->nullable($data['internal_notes'] ?? null), 'version' => 1,
                'created_by_admin_id' => $fresh->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $fresh->id, 'pos_payment_destination_created', 'payment-destination:'.$public, $outlet->id);

            return $this->destinationResult(DB::table('pos_payment_destinations')->where('id', $id)->firstOrFail());
        });
    }

    public function updateDestination(Admin $actor, Outlet $outlet, string $destinationId, string $key, array $input): array
    {
        $this->fields($input, ['version', 'display_name', 'provider_label', 'masked_identifier', 'active', 'effective_from',
            'effective_until', 'requires_refund_override_approval', 'internal_notes']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'display_name' => 'sometimes|string|max:120', 'provider_label' => 'nullable|string|max:120',
            'masked_identifier' => 'nullable|string|max:120', 'active' => 'sometimes|boolean', 'effective_from' => 'nullable|date',
            'effective_until' => 'nullable|date', 'requires_refund_override_approval' => 'sometimes|boolean', 'internal_notes' => 'nullable|string|max:1000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'destination.update', $key, ['destination_id' => $destinationId, 'input' => $input],
            'config.payments.manage', function (Admin $fresh) use ($outlet, $destinationId, $data) {
                $row = DB::table('pos_payment_destinations')->where('public_id', $destinationId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
                if ((int) $row->version !== (int) $data['version']) {
                    throw new LogicException('Payment destination version changed.');
                }
                $from = array_key_exists('effective_from', $data) ? $this->time($data['effective_from']) : $row->effective_from;
                $until = array_key_exists('effective_until', $data) ? $this->time($data['effective_until']) : $row->effective_until;
                if ($from && $until && Carbon::parse($until)->lte(Carbon::parse($from))) {
                    throw ValidationException::withMessages(['effective_until' => 'Effective end must be after effective start.']);
                }
                $update = ['version' => $row->version + 1, 'updated_by_admin_id' => $fresh->id, 'updated_at' => now()];
                foreach (['display_name', 'active', 'requires_refund_override_approval'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $update[$field] = $field === 'display_name' ? trim((string) $data[$field]) : (bool) $data[$field];
                    }
                }                foreach (['provider_label', 'internal_notes'] as $field) {
                    if (array_key_exists($field, $data)) {
                        $update[$field] = $this->nullable($data[$field]);
                    }
                }
                if (array_key_exists('masked_identifier', $data)) {
                    $update['masked_identifier'] = $this->maskedIdentifier($data['masked_identifier']);
                }
                if (array_key_exists('effective_from', $data)) {
                    $update['effective_from'] = $from;
                }
                if (array_key_exists('effective_until', $data)) {
                    $update['effective_until'] = $until;
                }
                DB::table('pos_payment_destinations')->where('id', $row->id)->update($update);
                IdentityAudit::record('admin', $fresh->id, 'pos_payment_destination_updated', 'payment-destination:'.$destinationId, $outlet->id);

                return $this->destinationResult(DB::table('pos_payment_destinations')->where('id', $row->id)->firstOrFail());
            });
    }

    public function destinations(Admin $actor, Outlet $outlet, bool $activeOnly = false): array
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'config.payments.manage', $outlet->fresh()), 403);
        $query = DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)->orderBy('method')->orderBy('display_name');
        if ($activeOnly) {
            $query->where('active', true);
        }

        return $query->get()->map(fn ($row) => $this->destinationResult($row))->all();
    }

    public function sell(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['sale', 'payments']);
        Validator::make($input, [
            'sale' => 'required|array', 'payments' => 'required|array|min:1|max:10',
        ])->validate();

        return $this->mutate($actor, $outlet, 'sale', $key, $input, 'shop.sales', function (Admin $fresh) use ($outlet, $key, $input) {
            $requiresCash = collect($input['payments'])->contains(fn ($payment) => is_array($payment) && ($payment['method'] ?? null) === 'cash');
            $cashSessionId = app(CashSessionOperations::class)->transactionSession($outlet, $requiresCash);
            $sale = $this->sales->sell($fresh, $outlet, 'mt220-'.substr(hash('sha256', $key), 0, 48), $input['sale']);
            $invoice = DB::table('invoices')->where('public_id', $sale['invoice_id'])->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            if ($invoice->order_id !== null) {
                throw new LogicException('POS tender service cannot mutate Website order invoices.');
            }
            $payments = collect($input['payments'])->values();
            $prepared = [];
            $total = '0.00';
            foreach ($payments as $index => $payment) {
                if (! is_array($payment)) {
                    throw ValidationException::withMessages(['payments.'.$index => 'Payment allocation must be an object.']);
                }
                $prepared[] = $this->prepareTender($fresh, $outlet, $payment, $index + 1);
                $total = bcadd($total, end($prepared)['amount'], 2);
            }
            if (bccomp($total, SourceRow::money((string) $invoice->final_bill), 2) !== 0) {
                throw new LogicException('Tender allocations must exactly equal the authoritative Invoice payable amount.');
            }
            $allocationResults = [];
            $change = '0.00';
            foreach ($prepared as $row) {
                $allocationResults[] = $this->insertTender($fresh, $invoice, $row, $cashSessionId);
                $change = bcadd($change, $row['change_returned'], 2);
            }
            IdentityAudit::record('admin', $fresh->id, 'pos_sale_tendered', 'invoice:'.$invoice->public_id, $outlet->id);

            return [...$sale, 'payments' => $allocationResults, 'paid_amount' => $total, 'remaining' => '0.00', 'cash_change_total' => $change];
        });
    }

    public function reconcile(Admin $actor, Outlet $outlet, string $allocationId, string $key, array $input): array
    {
        $this->fields($input, ['settlement_version', 'fee_amount', 'adjustment_amount', 'received_net_amount', 'external_reference', 'notes']);
        $data = Validator::make($input, [
            'settlement_version' => 'required|integer|min:0', 'fee_amount' => 'required', 'adjustment_amount' => 'required',
            'received_net_amount' => 'required', 'external_reference' => 'nullable|string|max:120', 'notes' => 'nullable|string|max:1000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'settlement', $key, ['allocation_id' => $allocationId, 'input' => $input],
            'shop.payments.reconcile', function (Admin $fresh) use ($outlet, $allocationId, $data) {
                $allocation = DB::table('pos_tender_allocations')->where('public_id', $allocationId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
                if ($allocation->method === 'cash') {
                    throw new LogicException('Cash tender reconciles through the cash-session/day-closing workflow, not provider settlement.');
                }
                if ((int) $allocation->settlement_version !== (int) $data['settlement_version']) {
                    throw new LogicException('Payment settlement version changed.');
                }
                $gross = SourceRow::money((string) $allocation->amount);
                $fee = SourceRow::money($data['fee_amount']);
                $adjustment = SourceRow::decimal($data['adjustment_amount']);
                $received = SourceRow::money($data['received_net_amount']);
                $expected = bcadd(bcsub($gross, $fee, 2), $adjustment, 2);
                if (bccomp($expected, '0.00', 2) < 0) {
                    throw ValidationException::withMessages(['adjustment_amount' => 'Settlement expected net cannot be negative.']);
                }
                $variance = bcsub($received, $expected, 2);
                $state = bccomp($variance, '0.00', 2) === 0 ? 'confirmed' : 'variance';
                $sequence = (int) $allocation->settlement_version + 1;
                $snapshot = ['contract' => 'pos-settlement.v1', 'allocation_id' => $allocation->public_id, 'method' => $allocation->method,
                    'gross_amount' => $gross, 'fee_amount' => $fee, 'adjustment_amount' => $adjustment, 'expected_net_amount' => $expected,
                    'received_net_amount' => $received, 'variance_amount' => $variance, 'state' => $state,
                    'external_reference' => $this->reference($data['external_reference'] ?? null), 'notes' => $this->nullable($data['notes'] ?? null)];
                $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $public = (string) Str::uuid();
                DB::table('pos_settlement_events')->insert([
                    'public_id' => $public, 'tender_allocation_id' => $allocation->id, 'sequence' => $sequence,
                    'gross_amount' => $gross, 'fee_amount' => $fee, 'adjustment_amount' => $adjustment,
                    'expected_net_amount' => $expected, 'received_net_amount' => $received, 'variance_amount' => $variance,
                    'external_reference' => $snapshot['external_reference'], 'notes' => $snapshot['notes'], 'recorded_by_admin_id' => $fresh->id,
                    'event_snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('pos_tender_allocations')->where('id', $allocation->id)->update([
                    'reconciliation_state' => $state, 'settlement_version' => $sequence, 'updated_at' => now(),
                ]);
                IdentityAudit::record('admin', $fresh->id, 'pos_payment_reconciled', 'tender:'.$allocation->public_id, $outlet->id);

                return ['settlement_id' => $public, 'allocation_id' => $allocation->public_id, 'state' => $state,
                    'settlement_version' => $sequence, 'gross_amount' => $gross, 'fee_amount' => $fee,
                    'adjustment_amount' => $adjustment, 'expected_net_amount' => $expected, 'received_net_amount' => $received,
                    'variance_amount' => $variance, 'currency' => 'PKR'];
            });
    }

    public function recordRefund(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['return_id', 'original_tender_id', 'refund_destination_id', 'amount', 'override',
            'override_reason', 'approved_by_admin_id', 'transaction_reference']);
        $data = Validator::make($input, [
            'return_id' => 'required|uuid', 'original_tender_id' => 'required|uuid', 'refund_destination_id' => 'required|uuid',
            'amount' => 'required', 'override' => 'sometimes|boolean', 'override_reason' => 'nullable|string|max:1000',
            'approved_by_admin_id' => 'nullable|uuid', 'transaction_reference' => 'nullable|string|max:120',
        ])->validate();

        return $this->mutate($actor, $outlet, 'refund', $key, $input, 'shop.sales', function (Admin $fresh) use ($outlet, $data) {
            $return = DB::table('returns')->where('public_id', $data['return_id'])->where('status', 'accepted')->lockForUpdate()->firstOrFail();
            $invoice = DB::table('invoices')->where('id', $return->invoice_id)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            if ($invoice->order_id !== null) {
                throw new LogicException('Website provider refunds remain under Website payment contracts.');
            }
            $original = DB::table('pos_tender_allocations')->where('public_id', $data['original_tender_id'])
                ->where('invoice_id', $invoice->id)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $destination = $this->lockDestination($outlet, $data['refund_destination_id']);
            $this->assertDestinationAvailable($destination);
            $cashSessionId = app(CashSessionOperations::class)->transactionSession($outlet, $destination->method === 'cash');
            $amount = SourceRow::money($data['amount']);
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['amount' => 'Refund amount must be positive.']);
            }
            $returnDue = DB::table('return_lines')->where('return_id', $return->id)->orderBy('id')->lockForUpdate()->pluck('net_amount')
                ->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            $returnRecorded = DB::table('pos_refund_allocations')->where('return_id', $return->id)->orderBy('id')->lockForUpdate()->pluck('amount')
                ->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            $tenderRecorded = DB::table('pos_refund_allocations')->where('original_tender_allocation_id', $original->id)->orderBy('id')->lockForUpdate()->pluck('amount')
                ->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            if (bccomp(bcadd($returnRecorded, $amount, 2), $returnDue, 2) > 0
                || bccomp(bcadd($tenderRecorded, $amount, 2), $original->amount, 2) > 0) {
                throw new LogicException('Refund exceeds accepted-return or original-tender balance.');
            }            $isOverride = (int) $original->payment_destination_id !== (int) $destination->id || $original->method !== $destination->method;
            $requestedOverride = (bool) ($data['override'] ?? false);
            $reason = $this->nullable($data['override_reason'] ?? null);
            $approver = null;
            if ($isOverride) {
                if (! $requestedOverride || ! $reason || ! app(Access::class)->allows($fresh, 'shop.payments.refund-override', $outlet->fresh())) {
                    throw ValidationException::withMessages(['override' => 'A different refund destination/method requires authorized explicit override and reason.']);
                }
                if ($destination->requires_refund_override_approval) {
                    if (empty($data['approved_by_admin_id'])) {
                        throw ValidationException::withMessages(['approved_by_admin_id' => 'This refund override requires approval.']);
                    }
                    $approver = Admin::where('public_id', $data['approved_by_admin_id'])->firstOrFail();
                    if (! app(Access::class)->allows($approver->fresh(), 'shop.payments.refund-approve', $outlet->fresh())) {
                        abort(403, 'Refund override approver is not authorized.');
                    }
                }
            } elseif ($requestedOverride || $reason || ! empty($data['approved_by_admin_id'])) {
                throw ValidationException::withMessages(['override' => 'Override metadata is only valid when refund destination or method differs.']);
            }
            $originalSnapshot = $this->tenderSnapshot($original);
            $destinationSnapshot = $this->destinationSnapshot($destination);
            $snapshot = ['contract' => 'pos-refund-allocation.v1', 'return_id' => $return->public_id, 'invoice_id' => $invoice->public_id,
                'original_tender' => $originalSnapshot, 'refund_destination' => $destinationSnapshot, 'amount' => $amount,
                'is_override' => $isOverride, 'override_reason' => $reason, 'requested_by_admin_id' => $fresh->public_id,
                'approved_by_admin_id' => $approver?->public_id, 'transaction_reference' => $this->reference($data['transaction_reference'] ?? null)];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $public = (string) Str::uuid();
            DB::table('pos_refund_allocations')->insert([
                'public_id' => $public, 'return_id' => $return->id, 'invoice_id' => $invoice->id, 'outlet_id' => $outlet->id,
                'cash_session_id' => $cashSessionId, 'original_tender_allocation_id' => $original->id, 'refund_destination_id' => $destination->id, 'amount' => $amount,
                'original_method' => $original->method, 'refund_method' => $destination->method, 'is_override' => $isOverride,
                'override_reason' => $reason, 'requested_by_admin_id' => $fresh->id, 'approved_by_admin_id' => $approver?->id,
                'transaction_reference' => $snapshot['transaction_reference'],
                'original_tender_snapshot' => json_encode($originalSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'refund_destination_snapshot' => json_encode($destinationSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'snapshot_sha256' => hash('sha256', $json), 'recorded_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $fresh->id, $isOverride ? 'pos_refund_override_recorded' : 'pos_refund_recorded', 'pos-refund:'.$public, $outlet->id);

            return ['refund_id' => $public, 'return_id' => $return->public_id, 'amount' => $amount, 'currency' => 'PKR',
                'original_method' => $original->method, 'refund_method' => $destination->method,
                'refund_destination_id' => $destination->public_id, 'override' => $isOverride,
                'approved_by_admin_id' => $approver?->public_id, 'recorded' => true];
        });
    }

    public function invoice(Admin $actor, Outlet $outlet, string $invoiceId): array
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && (app(Access::class)->allows($fresh, 'shop.invoices', $outlet->fresh())
            || app(Access::class)->allows($fresh, 'shop.sales', $outlet->fresh())), 403);
        $invoice = DB::table('invoices')->where('public_id', $invoiceId)->where('outlet_id', $outlet->id)->firstOrFail();
        $tenders = DB::table('pos_tender_allocations')->where('invoice_id', $invoice->id)->orderBy('sequence')->get()->map(fn ($row) => $this->tenderResult($row))->all();
        $refunds = DB::table('pos_refund_allocations')->where('invoice_id', $invoice->id)->orderBy('id')->get()->map(fn ($row) => [
            'refund_id' => $row->public_id, 'amount' => $row->amount, 'original_method' => $row->original_method,
            'refund_method' => $row->refund_method, 'override' => (bool) $row->is_override,
            'refund_destination_id' => DB::table('pos_payment_destinations')->where('id', $row->refund_destination_id)->value('public_id'),
            'recorded_at' => $row->recorded_at,
        ])->all();

        return ['invoice_id' => $invoice->public_id, 'invoice_number' => $invoice->invoice_number, 'final_bill' => $invoice->final_bill,
            'currency' => $invoice->currency, 'payments' => $tenders, 'refunds' => $refunds];
    }

    private function prepareTender(Admin $actor, Outlet $outlet, array $input, int $sequence): array
    {
        $this->fields($input, ['method', 'destination_id', 'amount', 'transaction_reference', 'reconciliation_reference', 'cash_tendered']);
        $data = Validator::make($input, [
            'method' => 'required|in:'.implode(',', self::METHODS), 'destination_id' => 'required|uuid', 'amount' => 'required',
            'transaction_reference' => 'nullable|string|max:120', 'reconciliation_reference' => 'nullable|string|max:120', 'cash_tendered' => 'nullable',
        ])->validate();
        $destination = $this->lockDestination($outlet, $data['destination_id']);
        $this->assertDestinationAvailable($destination);
        if ($destination->method !== $data['method']) {
            throw ValidationException::withMessages(['method' => 'Payment Method does not match the selected destination.']);
        }
        $amount = SourceRow::money($data['amount']);
        if (bccomp($amount, '0.00', 2) <= 0) {
            throw ValidationException::withMessages(['amount' => 'Tender amount must be positive.']);
        }        $cashTendered = null;
        $change = '0.00';
        if ($data['method'] === 'cash') {
            $cashTendered = SourceRow::money($data['cash_tendered'] ?? $amount);
            if (bccomp($cashTendered, $amount, 2) < 0) {
                throw ValidationException::withMessages(['cash_tendered' => 'Cash tendered cannot be less than the Cash allocation amount.']);
            }
            $change = bcsub($cashTendered, $amount, 2);
        } elseif (array_key_exists('cash_tendered', $data) && $data['cash_tendered'] !== null) {
            throw ValidationException::withMessages(['cash_tendered' => 'Cash tendered is valid only for Cash allocations.']);
        }
        $snapshot = $this->destinationSnapshot($destination);

        return ['sequence' => $sequence, 'method' => $data['method'], 'destination' => $destination, 'amount' => $amount,
            'cash_tendered' => $cashTendered, 'change_returned' => $change,
            'transaction_reference' => $this->reference($data['transaction_reference'] ?? null),
            'reconciliation_reference' => $this->reference($data['reconciliation_reference'] ?? null), 'destination_snapshot' => $snapshot,
            'reconciliation_state' => $data['method'] === 'cash' ? 'cash' : 'pending', 'actor_id' => $actor->id];
    }

    private function insertTender(Admin $actor, object $invoice, array $row, ?int $cashSessionId): array
    {
        $snapshotJson = json_encode($row['destination_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $public = (string) Str::uuid();
        DB::table('pos_tender_allocations')->insert([
            'public_id' => $public, 'invoice_id' => $invoice->id, 'outlet_id' => $invoice->outlet_id,
            'cash_session_id' => $cashSessionId, 'payment_destination_id' => $row['destination']->id, 'sequence' => $row['sequence'], 'method' => $row['method'],
            'amount' => $row['amount'], 'cash_tendered' => $row['cash_tendered'], 'change_returned' => $row['change_returned'],
            'transaction_reference' => $row['transaction_reference'], 'reconciliation_reference' => $row['reconciliation_reference'],
            'destination_snapshot' => $snapshotJson, 'snapshot_sha256' => hash('sha256', $snapshotJson),
            'reconciliation_state' => $row['reconciliation_state'], 'settlement_version' => 0,
            'created_by_admin_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return ['allocation_id' => $public, 'sequence' => $row['sequence'], 'method' => $row['method'],
            'destination_id' => $row['destination']->public_id, 'destination_name' => $row['destination']->display_name,
            'amount' => $row['amount'], 'cash_tendered' => $row['cash_tendered'], 'change_returned' => $row['change_returned'],
            'transaction_reference' => $row['transaction_reference'], 'reconciliation_reference' => $row['reconciliation_reference'],
            'reconciliation_state' => $row['reconciliation_state'], 'settlement_version' => 0];
    }

    private function tenderResult(object $row): array
    {
        $destination = DB::table('pos_payment_destinations')->where('id', $row->payment_destination_id)->firstOrFail();

        return ['allocation_id' => $row->public_id, 'sequence' => (int) $row->sequence, 'method' => $row->method,
            'destination_id' => $destination->public_id, 'destination_name' => $destination->display_name, 'amount' => $row->amount,
            'cash_tendered' => $row->cash_tendered, 'change_returned' => $row->change_returned,
            'transaction_reference' => $row->transaction_reference, 'reconciliation_reference' => $row->reconciliation_reference,
            'reconciliation_state' => $row->reconciliation_state, 'settlement_version' => (int) $row->settlement_version];
    }

    private function tenderSnapshot(object $row): array
    {
        return ['contract' => 'pos-tender-allocation.v1', 'allocation_id' => $row->public_id, 'method' => $row->method,
            'amount' => (string) $row->amount, 'cash_tendered' => $row->cash_tendered, 'change_returned' => (string) $row->change_returned,
            'transaction_reference' => $row->transaction_reference, 'reconciliation_reference' => $row->reconciliation_reference,
            'destination_snapshot' => json_decode($row->destination_snapshot, true, flags: JSON_THROW_ON_ERROR)];
    }

    private function destinationSnapshot(object $row): array
    {
        return ['contract' => 'pos-payment-destination.v1', 'destination_id' => $row->public_id, 'outlet_id' => (int) $row->outlet_id,
            'method' => $row->method, 'display_name' => $row->display_name, 'provider_label' => $row->provider_label,
            'masked_identifier' => $row->masked_identifier, 'effective_from' => $row->effective_from, 'effective_until' => $row->effective_until,
            'requires_refund_override_approval' => (bool) $row->requires_refund_override_approval, 'version' => (int) $row->version];
    }

    private function destinationResult(object $row): array
    {
        return ['destination_id' => $row->public_id, 'outlet_id' => Outlet::whereKey($row->outlet_id)->value('public_id'),
            'method' => $row->method, 'display_name' => $row->display_name, 'provider_label' => $row->provider_label,
            'masked_identifier' => $row->masked_identifier, 'active' => (bool) $row->active,
            'effective_from' => $row->effective_from, 'effective_until' => $row->effective_until,
            'requires_refund_override_approval' => (bool) $row->requires_refund_override_approval,
            'version' => (int) $row->version];
    }

    private function lockDestination(Outlet $outlet, string $publicId): object
    {
        return DB::table('pos_payment_destinations')->where('public_id', $publicId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
    }

    private function assertDestinationAvailable(object $destination): void
    {
        if (! $destination->active
            || ($destination->effective_from && now()->lt(Carbon::parse($destination->effective_from)))
            || ($destination->effective_until && now()->gte(Carbon::parse($destination->effective_until)))) {
            throw ValidationException::withMessages(['destination_id' => 'Payment destination is inactive or outside its effective window.']);
        }
    }

    private function mutate(Admin $actor, Outlet $outlet, string $operation, string $key, mixed $payload, string $permission, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $permission, $callback) {
            $fresh = $actor->fresh();
            abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, $permission, $outlet->fresh()), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identity = ['actor_scope' => Admin::class.':'.$fresh->id, 'operation' => 'pos-payments.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([
                ...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different POS payment request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'resource_type' => 'pos_payment', 'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }        if (array_is_list($value)) {
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
            throw ValidationException::withMessages(['input' => 'Unexpected POS payment fields; sale totals, destination identity and security-sensitive data are server controlled.']);
        }
    }

    private function reference(mixed $value): ?string
    {
        $value = $this->nullable($value);
        if ($value !== null && (mb_strlen($value) > 120 || preg_match('/[\x00-\x1F\x7F]/u', $value))) {
            throw ValidationException::withMessages(['reference' => 'Payment reference contains invalid characters or length.']);
        }
        if ($value !== null) {
            $compact = preg_replace('/[\s-]+/', '', $value);
            if (preg_match('/\A\d{13,19}\z/', (string) $compact)
                || preg_match('/\b(?:pan|cvv|pin|card\s*number)\b/i', $value)) {
                throw ValidationException::withMessages(['reference' => 'Sensitive card data is not allowed in POS payment references.']);
            }
        }

        return $value;
    }

    private function maskedIdentifier(mixed $value): ?string
    {
        $value = $this->nullable($value);
        if ($value !== null) {
            $digits = preg_replace('/\D+/', '', $value);
            if (strlen((string) $digits) >= 8 && ! preg_match('/[*xX]/', $value)) {
                throw ValidationException::withMessages(['masked_identifier' => 'Financial identifiers must be masked before storage.']);
            }
        }

        return $value;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function time(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return Carbon::parse((string) $value)->utc()->format('Y-m-d H:i:s.u');
    }
}
