<?php

namespace App\Repairs;

use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Inventory\TransactionalStock;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PaidRepairOperations
{
    public const STATUSES = ['received', 'diagnosing', 'awaiting_approval', 'approved', 'repairing', 'ready_for_collection', 'delivered', 'closed', 'cancelled'];

    private const TRANSITIONS = [
        'received' => ['diagnosing', 'cancelled'],
        'diagnosing' => ['awaiting_approval', 'cancelled'],
        'awaiting_approval' => ['diagnosing', 'cancelled'],
        'approved' => ['repairing', 'cancelled'],
        'repairing' => ['ready_for_collection', 'cancelled'],
        'ready_for_collection' => ['delivered'],
        'delivered' => ['closed'],
        'closed' => [], 'cancelled' => [],
    ];

    public function __construct(private TransactionalStock $stock) {}

    public function configure(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['enabled', 'version']);
        $data = Validator::make($input, ['enabled' => 'required|boolean', 'version' => 'nullable|integer|min:1'])->validate();

        return $this->mutate($actor, $outlet, 'configure', $key, $input, function (Admin $fresh) use ($outlet, $data) {
            $row = DB::table('repair_settings')->where('outlet_id', $outlet->id)->lockForUpdate()->first();
            if ($row && isset($data['version']) && (int) $data['version'] !== (int) $row->version) {
                throw new LogicException('Paid repair configuration version changed.');
            }
            if (! $row && isset($data['version'])) {
                throw new LogicException('Paid repair configuration does not have a prior version.');
            }
            if ($row) {
                DB::table('repair_settings')->where('id', $row->id)->update([
                    'enabled' => (bool) $data['enabled'], 'version' => $row->version + 1,
                    'updated_by_admin_id' => $fresh->id, 'updated_at' => now(),
                ]);
            } else {
                DB::table('repair_settings')->insert([
                    'outlet_id' => $outlet->id, 'enabled' => (bool) $data['enabled'], 'version' => 1,
                    'updated_by_admin_id' => $fresh->id, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $setting = DB::table('repair_settings')->where('outlet_id', $outlet->id)->firstOrFail();
            IdentityAudit::record('admin', $fresh->id, 'paid_repair_configuration_updated', 'outlet:'.$outlet->public_id, $outlet->id);

            return ['enabled' => (bool) $setting->enabled, 'version' => (int) $setting->version];
        });
    }

    public function open(Admin $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['customer_id', 'customer_name', 'customer_phone', 'device_label', 'identifier_type', 'identifier_value',
            'issue_description', 'received_condition', 'accessories_received', 'internal_notes']);
        $data = Validator::make($input, [
            'customer_id' => 'nullable|uuid', 'customer_name' => 'nullable|string|max:255', 'customer_phone' => 'nullable|string|max:40',
            'device_label' => 'required|string|max:255', 'identifier_type' => 'required|in:imei,serial,other',
            'identifier_value' => 'required|string|max:150', 'issue_description' => 'required|string|max:3000',
            'received_condition' => 'nullable|string|max:150', 'accessories_received' => 'nullable|string|max:500',
            'internal_notes' => 'nullable|string|max:3000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'open', $key, $input, function (Admin $fresh) use ($outlet, $data) {
            $setting = DB::table('repair_settings')->where('outlet_id', $outlet->id)->lockForUpdate()->first();
            if (! $setting || ! $setting->enabled) {
                throw ValidationException::withMessages(['repair' => 'New paid repair intake is disabled for this outlet.']);
            }
            $customer = empty($data['customer_id']) ? null : DB::table('customers')->where('public_id', $data['customer_id'])
                ->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $name = trim((string) ($customer->display_name ?? $data['customer_name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(['customer_name' => 'Paid repair intake requires a customer name.']);
            }
            $identifier = trim($data['identifier_value']);
            if ($identifier === '') {
                throw ValidationException::withMessages(['identifier_value' => 'A device identifier is required.']);
            }
            $activeIdentifier = $data['identifier_type'].':'.mb_strtolower($identifier);
            if (DB::table('repair_jobs')->where('outlet_id', $outlet->id)->where('active_identifier', $activeIdentifier)->lockForUpdate()->exists()) {
                throw new LogicException('This device already has an active paid repair job.');
            }
            $public = (string) Str::uuid();
            $id = DB::table('repair_jobs')->insertGetId([
                'public_id' => $public, 'outlet_id' => $outlet->id, 'repair_number' => $this->number($outlet),
                'customer_id' => $customer?->id, 'customer_name' => $name,
                'customer_phone' => $this->nullable($customer->mobile ?? $data['customer_phone'] ?? null),
                'device_label' => trim($data['device_label']), 'identifier_type' => $data['identifier_type'],
                'identifier_value' => $identifier, 'active_identifier' => $activeIdentifier,
                'issue_description' => trim($data['issue_description']),
                'received_condition' => $this->nullable($data['received_condition'] ?? null),
                'accessories_received' => $this->nullable($data['accessories_received'] ?? null),
                'internal_notes' => $this->nullable($data['internal_notes'] ?? null), 'status' => 'received',
                'handled_by_admin_id' => $fresh->id, 'handled_by_name' => $fresh->name, 'version' => 1,
                'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->event($id, 'received', $fresh, ['note' => 'Paid repair device received.']);
            IdentityAudit::record('admin', $fresh->id, 'paid_repair_opened', 'repair:'.$public, $outlet->id);

            return $this->payload(DB::table('repair_jobs')->where('id', $id)->firstOrFail());
        });
    }

    public function update(Admin $actor, Outlet $outlet, string $repairId, string $key, array $input): array
    {
        $this->fields($input, ['status', 'diagnosis', 'internal_notes']);
        $data = Validator::make($input, ['status' => 'required|in:'.implode(',', self::STATUSES),
            'diagnosis' => 'nullable|string|max:3000', 'internal_notes' => 'nullable|string|max:3000'])->validate();

        return $this->mutate($actor, $outlet, 'update:'.$repairId, $key, $input, function (Admin $fresh) use ($outlet, $repairId, $data) {
            $job = DB::table('repair_jobs')->where('public_id', $repairId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $target = $data['status'];
            if ($target !== $job->status && ! in_array($target, self::TRANSITIONS[$job->status] ?? [], true)) {
                throw new LogicException('Invalid paid repair status transition.');
            }
            $diagnosis = array_key_exists('diagnosis', $data) ? $this->nullable($data['diagnosis']) : $job->diagnosis;
            if ($target === 'awaiting_approval' && ! $diagnosis) {
                throw ValidationException::withMessages(['diagnosis' => 'Diagnosis is required before requesting estimate approval.']);
            }
            if ($target === 'ready_for_collection') {
                $this->assertApproved($job);
                $this->assertPartsConsumed($job->id);
            }
            if ($target === 'delivered') {
                $this->assertPaid($job);
            }
            if ($target === 'cancelled' && (DB::table('repair_part_consumptions')->where('repair_job_id', $job->id)->exists()
                || DB::table('repair_payment_links')->where('repair_job_id', $job->id)->exists())) {
                throw new LogicException('A repair with consumed parts or collected payment cannot be cancelled.');
            }
            $updates = ['status' => $target, 'diagnosis' => $diagnosis,
                'internal_notes' => array_key_exists('internal_notes', $data) ? $this->nullable($data['internal_notes']) : $job->internal_notes,
                'version' => $job->version + 1, 'updated_at' => now()];
            if ($diagnosis && ! $job->diagnosed_at) {
                $updates['diagnosed_at'] = now();
            }
            if ($target === 'ready_for_collection' && ! $job->ready_at) {
                $updates['ready_at'] = now();
            }
            if ($target === 'delivered' && ! $job->delivered_at) {
                $updates['delivered_at'] = now();
            }
            if ($target === 'closed' && ! $job->closed_at) {
                $updates['closed_at'] = now();
                $updates['active_identifier'] = null;
            }
            if ($target === 'cancelled' && ! $job->cancelled_at) {
                $updates['cancelled_at'] = now();
                $updates['active_identifier'] = null;
            }
            DB::table('repair_jobs')->where('id', $job->id)->update($updates);
            $this->event($job->id, $target === $job->status ? 'updated' : 'status_changed', $fresh,
                ['from' => $job->status, 'to' => $target]);
            IdentityAudit::record('admin', $fresh->id, 'paid_repair_updated', 'repair:'.$repairId, $outlet->id);

            return $this->payload(DB::table('repair_jobs')->where('id', $job->id)->firstOrFail());
        });
    }

    public function estimate(Admin $actor, Outlet $outlet, string $repairId, string $key, array $input): array
    {
        $this->fields($input, ['notes', 'lines']);
        $data = Validator::make($input, [
            'notes' => 'nullable|string|max:3000', 'lines' => 'required|array|min:1|max:100',
            'lines.*.type' => 'required|in:part,labor', 'lines.*.product_id' => 'nullable|uuid',
            'lines.*.description' => 'required|string|max:500', 'lines.*.quantity' => 'required|integer|min:1|max:10000',
            'lines.*.unit_price' => 'nullable',
        ])->validate();

        return $this->mutate($actor, $outlet, 'estimate:'.$repairId, $key, $input, function (Admin $fresh) use ($outlet, $repairId, $data) {
            $job = DB::table('repair_jobs')->where('public_id', $repairId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            if (! in_array($job->status, ['diagnosing', 'awaiting_approval'], true)
                || DB::table('repair_estimate_approvals')->where('repair_job_id', $job->id)->exists()) {
                throw new LogicException('Only an unapproved diagnosed repair can receive an estimate.');
            }
            if (! $job->diagnosis) {
                throw ValidationException::withMessages(['diagnosis' => 'Diagnosis must be recorded before an estimate.']);
            }
            $version = ((int) DB::table('repair_estimates')->where('repair_job_id', $job->id)->lockForUpdate()->max('version')) + 1;
            $prepared = [];
            $parts = $labor = '0.00';
            foreach (array_values($data['lines']) as $index => $line) {
                $type = $line['type'];
                $product = null;
                if ($type === 'part') {
                    $product = Product::where('public_id', $line['product_id'] ?? '')->where('outlet_id', $outlet->id)
                        ->where('isDeleted', false)->lockForUpdate()->firstOrFail();
                    if ($product->track_imei) {
                        throw ValidationException::withMessages(['lines.'.$index.'.product_id' => 'Serialized devices cannot be consumed as repair parts.']);
                    }
                    $unit = SourceRow::money((string) $product->sale_price);
                } else {
                    if (! empty($line['product_id']) || ! array_key_exists('unit_price', $line)) {
                        throw ValidationException::withMessages(['lines.'.$index => 'Labor requires an explicit price and cannot reference stock.']);
                    }
                    $unit = SourceRow::money((string) $line['unit_price']);
                }
                $total = bcmul($unit, (string) $line['quantity'], 2);
                $type === 'part' ? $parts = bcadd($parts, $total, 2) : $labor = bcadd($labor, $total, 2);
                $prepared[] = ['line_no' => $index + 1, 'type' => $type, 'product' => $product,
                    'description' => trim($line['description']), 'quantity' => (int) $line['quantity'],
                    'unit_price' => $unit, 'line_total' => $total];
            }
            $grand = bcadd($parts, $labor, 2);
            $public = (string) Str::uuid();
            $snapshot = $this->canonical(['contract' => 'paid-repair-estimate.v1', 'estimate_id' => $public,
                'repair_id' => $job->public_id, 'version' => $version, 'parts_total' => $parts,
                'labor_total' => $labor, 'grand_total' => $grand, 'currency' => 'PKR',
                'notes' => $this->nullable($data['notes'] ?? null), 'lines' => array_map(fn ($line) => [
                    'line_no' => $line['line_no'], 'type' => $line['type'], 'product_id' => $line['product']?->public_id,
                    'description' => $line['description'], 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'],
                ], $prepared)]);
            $estimateId = DB::table('repair_estimates')->insertGetId([
                'public_id' => $public, 'repair_job_id' => $job->id, 'version' => $version, 'status' => 'proposed',
                'parts_total' => $parts, 'labor_total' => $labor, 'grand_total' => $grand,
                'notes' => $snapshot['notes'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'snapshot_sha256' => $this->digest($snapshot), 'created_by_admin_id' => $fresh->id, 'created_at' => now(),
            ]);
            foreach ($prepared as $line) {
                $lineSnapshot = $this->canonical(['contract' => 'paid-repair-estimate-line.v1', 'estimate_id' => $public,
                    'line_no' => $line['line_no'], 'type' => $line['type'], 'product_id' => $line['product']?->public_id,
                    'description' => $line['description'], 'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'],
                    'line_total' => $line['line_total'], 'currency' => 'PKR']);
                DB::table('repair_estimate_lines')->insert(['repair_estimate_id' => $estimateId, 'line_no' => $line['line_no'],
                    'line_type' => $line['type'], 'product_id' => $line['product']?->id, 'description' => $line['description'],
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit_price'], 'line_total' => $line['line_total'],
                    'snapshot' => json_encode($lineSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'snapshot_sha256' => $this->digest($lineSnapshot)]);
            }
            DB::table('repair_jobs')->where('id', $job->id)->update([
                'status' => 'awaiting_approval', 'version' => $job->version + 1, 'updated_at' => now(),
            ]);
            $this->event($job->id, 'estimate_created', $fresh, ['estimate_id' => $public, 'version' => $version, 'grand_total' => $grand]);
            IdentityAudit::record('admin', $fresh->id, 'paid_repair_estimate_created', 'repair:'.$repairId, $outlet->id);

            return $this->estimatePayload(DB::table('repair_estimates')->where('id', $estimateId)->firstOrFail());
        });
    }

    public function decideEstimate(Admin $actor, Outlet $outlet, string $repairId, string $estimateId, string $key, bool $approved): array
    {
        $payload = ['estimate_id' => $estimateId, 'approved' => $approved];

        return $this->mutate($actor, $outlet, 'estimate-decision:'.$repairId, $key, $payload,
            function (Admin $fresh) use ($outlet, $repairId, $estimateId, $approved) {
                $job = DB::table('repair_jobs')->where('public_id', $repairId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
                $estimate = DB::table('repair_estimates')->where('public_id', $estimateId)->where('repair_job_id', $job->id)->lockForUpdate()->firstOrFail();
                if ($job->status !== 'awaiting_approval' || $estimate->status !== 'proposed') {
                    throw new LogicException('Only a proposed estimate awaiting approval can be decided.');
                }
                if ($approved) {
                    if (DB::table('repair_estimate_approvals')->where('repair_job_id', $job->id)->lockForUpdate()->exists()) {
                        throw new LogicException('Paid repair already has an approved estimate.');
                    }
                    $snapshot = $this->canonical(['contract' => 'paid-repair-estimate-approval.v1', 'repair_id' => $job->public_id,
                        'estimate_id' => $estimate->public_id, 'estimate_version' => (int) $estimate->version,
                        'grand_total' => $estimate->grand_total, 'currency' => 'PKR', 'approved_by' => $fresh->public_id]);
                    DB::table('repair_estimate_approvals')->insert(['repair_job_id' => $job->id, 'repair_estimate_id' => $estimate->id,
                        'approved_by_admin_id' => $fresh->id, 'approval_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                        'snapshot_sha256' => $this->digest($snapshot), 'approved_at' => now()]);
                    DB::table('repair_estimates')->where('id', $estimate->id)->update(['status' => 'approved', 'decided_by_admin_id' => $fresh->id, 'decided_at' => now()]);
                    DB::table('repair_jobs')->where('id', $job->id)->update(['status' => 'approved', 'approved_at' => now(),
                        'version' => $job->version + 1, 'updated_at' => now()]);
                    $this->event($job->id, 'estimate_approved', $fresh, ['estimate_id' => $estimate->public_id, 'grand_total' => $estimate->grand_total]);
                    IdentityAudit::record('admin', $fresh->id, 'paid_repair_estimate_approved', 'repair:'.$repairId, $outlet->id);
                } else {
                    DB::table('repair_estimates')->where('id', $estimate->id)->update(['status' => 'rejected', 'decided_by_admin_id' => $fresh->id, 'decided_at' => now()]);
                    DB::table('repair_jobs')->where('id', $job->id)->update(['status' => 'diagnosing', 'version' => $job->version + 1, 'updated_at' => now()]);
                    $this->event($job->id, 'estimate_rejected', $fresh, ['estimate_id' => $estimate->public_id]);
                    IdentityAudit::record('admin', $fresh->id, 'paid_repair_estimate_rejected', 'repair:'.$repairId, $outlet->id);
                }

                return $this->payload(DB::table('repair_jobs')->where('id', $job->id)->firstOrFail());
            });
    }

    public function consumeParts(Admin $actor, Outlet $outlet, string $repairId, string $key): array
    {
        return $this->mutate($actor, $outlet, 'parts:'.$repairId, $key, ['repair_id' => $repairId], function (Admin $fresh) use ($outlet, $repairId) {
            $job = DB::table('repair_jobs')->where('public_id', $repairId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            if (! in_array($job->status, ['approved', 'repairing'], true)) {
                throw new LogicException('Repair parts can only be consumed after estimate approval.');
            }
            $approval = $this->assertApproved($job);
            $lines = DB::table('repair_estimate_lines')->where('repair_estimate_id', $approval->repair_estimate_id)
                ->where('line_type', 'part')->orderBy('line_no')->lockForUpdate()->get();
            $results = [];
            foreach ($lines as $line) {
                $existing = DB::table('repair_part_consumptions')->where('repair_estimate_line_id', $line->id)->lockForUpdate()->first();
                if ($existing) {
                    $results[] = ['consumption_id' => $existing->public_id, 'quantity' => (int) $existing->quantity];

                    continue;
                }
                $movementId = $this->stock->consumeRepairPart($line->id);
                $public = (string) Str::uuid();
                $snapshot = $this->canonical(['contract' => 'paid-repair-part-consumption.v1', 'repair_id' => $job->public_id,
                    'estimate_line_id' => $line->id, 'product_id' => Product::whereKey($line->product_id)->value('public_id'),
                    'quantity' => (int) $line->quantity, 'stock_movement_id' => $movementId]);
                DB::table('repair_part_consumptions')->insert(['public_id' => $public, 'repair_job_id' => $job->id,
                    'repair_estimate_line_id' => $line->id, 'product_id' => $line->product_id, 'quantity' => $line->quantity,
                    'stock_movement_id' => $movementId, 'consumed_by_admin_id' => $fresh->id,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                    'snapshot_sha256' => $this->digest($snapshot), 'consumed_at' => now()]);
                $results[] = ['consumption_id' => $public, 'quantity' => (int) $line->quantity];
            }
            if ($job->status === 'approved') {
                DB::table('repair_jobs')->where('id', $job->id)->update(['status' => 'repairing', 'version' => $job->version + 1, 'updated_at' => now()]);
            }
            $this->event($job->id, 'parts_consumed', $fresh, ['consumptions' => $results]);
            IdentityAudit::record('admin', $fresh->id, 'paid_repair_parts_consumed', 'repair:'.$repairId, $outlet->id);

            return ['repair_id' => $repairId, 'status' => 'repairing', 'parts' => $results];
        });
    }

    public function view(Admin $actor, Outlet $outlet, string $repairId): array
    {
        $this->authorize($actor, $outlet);
        $job = DB::table('repair_jobs')->where('public_id', $repairId)->where('outlet_id', $outlet->id)->firstOrFail();

        return $this->payload($job);
    }

    private function assertApproved(object $job): object
    {
        $approval = DB::table('repair_estimate_approvals')->where('repair_job_id', $job->id)->lockForUpdate()->first();
        if (! $approval) {
            throw new LogicException('Paid repair does not have an approved estimate.');
        }

        return $approval;
    }

    private function assertPartsConsumed(int $jobId): void
    {
        $approval = DB::table('repair_estimate_approvals')->where('repair_job_id', $jobId)->lockForUpdate()->firstOrFail();
        $partLineIds = DB::table('repair_estimate_lines')->where('repair_estimate_id', $approval->repair_estimate_id)
            ->where('line_type', 'part')->orderBy('id')->lockForUpdate()->pluck('id');
        $consumed = DB::table('repair_part_consumptions')->where('repair_job_id', $jobId)->whereIn('repair_estimate_line_id', $partLineIds)->count();
        if ($consumed !== $partLineIds->count()) {
            throw new LogicException('All approved repair parts must be consumed before collection readiness.');
        }
    }

    private function assertPaid(object $job): void
    {
        $approval = $this->assertApproved($job);
        $estimate = DB::table('repair_estimates')->where('id', $approval->repair_estimate_id)->lockForUpdate()->firstOrFail();
        $paid = DB::table('repair_payment_links')->where('repair_job_id', $job->id)->lockForUpdate()->pluck('amount')
            ->reduce(fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');
        if (bccomp($paid, $estimate->grand_total, 2) !== 0) {
            throw new LogicException('Paid repair cannot be delivered before the approved estimate is fully collected.');
        }
    }

    private function payload(object $job): array
    {
        $estimates = DB::table('repair_estimates')->where('repair_job_id', $job->id)->orderBy('version')->get()
            ->map(fn ($estimate) => $this->estimatePayload($estimate))->all();
        $approval = DB::table('repair_estimate_approvals')->where('repair_job_id', $job->id)->first();
        $payments = DB::table('repair_payment_links')->where('repair_job_id', $job->id)->orderBy('id')->get()->map(function ($link) {
            $tender = DB::table('pos_tender_allocations')->where('id', $link->tender_allocation_id)->firstOrFail();

            return ['allocation_id' => $tender->public_id, 'method' => $tender->method, 'amount' => $link->amount,
                'reconciliation_state' => $tender->reconciliation_state];
        })->all();
        $events = DB::table('repair_events')->where('repair_job_id', $job->id)->orderBy('sequence')->get()
            ->map(fn ($event) => json_decode($event->snapshot, true, flags: JSON_THROW_ON_ERROR))->all();

        return ['repair_id' => $job->public_id, 'repair_number' => $job->repair_number, 'version' => (int) $job->version,
            'outlet_id' => Outlet::whereKey($job->outlet_id)->value('public_id'),
            'customer_id' => $job->customer_id ? DB::table('customers')->where('id', $job->customer_id)->value('public_id') : null,
            'customer_name' => $job->customer_name, 'customer_phone' => $job->customer_phone,
            'device_label' => $job->device_label, 'identifier_type' => $job->identifier_type, 'identifier_value' => $job->identifier_value,
            'issue_description' => $job->issue_description, 'received_condition' => $job->received_condition,
            'accessories_received' => $job->accessories_received, 'diagnosis' => $job->diagnosis,
            'internal_notes' => $job->internal_notes, 'status' => $job->status, 'invoice_id' => $job->invoice_id
                ? DB::table('invoices')->where('id', $job->invoice_id)->value('public_id') : null,
            'approved_estimate_id' => $approval ? DB::table('repair_estimates')->where('id', $approval->repair_estimate_id)->value('public_id') : null,
            'estimates' => $estimates, 'payments' => $payments, 'events' => $events,
            'received_at' => $job->received_at, 'diagnosed_at' => $job->diagnosed_at, 'approved_at' => $job->approved_at,
            'ready_at' => $job->ready_at, 'delivered_at' => $job->delivered_at, 'closed_at' => $job->closed_at,
            'cancelled_at' => $job->cancelled_at];
    }

    private function estimatePayload(object $estimate): array
    {
        $lines = DB::table('repair_estimate_lines')->where('repair_estimate_id', $estimate->id)->orderBy('line_no')->get()
            ->map(fn ($line) => ['line_no' => (int) $line->line_no, 'type' => $line->line_type,
                'product_id' => $line->product_id ? Product::whereKey($line->product_id)->value('public_id') : null,
                'description' => $line->description, 'quantity' => (int) $line->quantity,
                'unit_price' => $line->unit_price, 'line_total' => $line->line_total])->all();

        return ['estimate_id' => $estimate->public_id, 'version' => (int) $estimate->version, 'status' => $estimate->status,
            'parts_total' => $estimate->parts_total, 'labor_total' => $estimate->labor_total,
            'grand_total' => $estimate->grand_total, 'currency' => 'PKR', 'notes' => $estimate->notes,
            'lines' => $lines, 'created_at' => $estimate->created_at, 'decided_at' => $estimate->decided_at];
    }

    private function mutate(Admin $actor, Outlet $outlet, string $operation, string $key, mixed $payload, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $callback) {
            // Prevent concurrent archival and stale completed-key repair replays.
            $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            $fresh = $actor->fresh();
            abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.repairs', $lockedOutlet), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identity = ['actor_scope' => Admin::class.':'.$fresh->id, 'operation' => 'paid-repair.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([
                ...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different paid repair request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'resource_type' => 'paid_repair', 'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    private function event(int $jobId, string $type, Admin $actor, array $data): void
    {
        $job = DB::table('repair_jobs')->where('id', $jobId)->firstOrFail();
        $sequence = ((int) DB::table('repair_events')->where('repair_job_id', $jobId)->lockForUpdate()->max('sequence')) + 1;
        $snapshot = $this->canonical(['contract' => 'paid-repair-event.v1', 'repair_id' => $job->public_id,
            'repair_number' => $job->repair_number, 'sequence' => $sequence, 'event_type' => $type,
            'status' => $job->status, 'job_version' => (int) $job->version,
            'actor' => ['id' => $actor->public_id, 'name' => $actor->name], 'data' => $data,
            'occurred_at' => now()->utc()->format('Y-m-d H:i:s.u')]);
        DB::table('repair_events')->insert(['repair_job_id' => $jobId, 'sequence' => $sequence, 'event_type' => $type,
            'status' => $job->status, 'actor_admin_id' => $actor->id, 'actor_name' => $actor->name,
            'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            'snapshot_sha256' => $this->digest($snapshot), 'created_at' => now()]);
    }

    private function authorize(Admin $actor, Outlet $outlet): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.repairs', $outlet->fresh()), 403);
    }

    private function number(Outlet $outlet): string
    {
        $date = now()->utc()->toDateString();
        DB::table('document_sequences')->insertOrIgnore([
            'namespace' => 'paid_repair', 'outlet_id' => $outlet->id, 'business_date' => $date, 'next_sequence' => 1,
        ]);
        $row = DB::table('document_sequences')->where([
            'namespace' => 'paid_repair', 'outlet_id' => $outlet->id, 'business_date' => $date,
        ])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('MST-RPR-%s-%s-%04d', $outlet->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected paid repair fields; identifiers, amounts and lifecycle state are server controlled.']);
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

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($this->canonical($value), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
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
}
