<?php

namespace App\Warranty;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockUnit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class ClaimOperations
{
    public const STATUSES = ['received', 'diagnosing', 'awaiting_parts', 'repaired', 'replaced', 'rejected', 'ready_for_collection', 'delivered', 'closed'];

    public const ACTIVE = ['received', 'diagnosing', 'awaiting_parts', 'repaired', 'replaced', 'ready_for_collection'];

    private const TRANSITIONS = [
        'received' => ['diagnosing', 'rejected'],
        'diagnosing' => ['awaiting_parts', 'repaired', 'replaced', 'rejected'],
        'awaiting_parts' => ['diagnosing', 'repaired', 'replaced', 'rejected'],
        'repaired' => ['ready_for_collection'],
        'replaced' => ['ready_for_collection'],
        'rejected' => ['ready_for_collection', 'closed'],
        'ready_for_collection' => ['delivered'],
        'delivered' => ['closed'],
        'closed' => [],
    ];

    public function open(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['sale_id', 'stock_unit_id', 'quantity', 'issue_description', 'received_condition', 'accessories_received', 'assigned_to', 'expected_completion_at']);
        $data = Validator::make($input, ['sale_id' => 'required|uuid', 'stock_unit_id' => 'nullable|uuid', 'quantity' => 'sometimes|integer|min:1|max:10000',
            'issue_description' => 'required|string|max:3000', 'received_condition' => 'nullable|string|max:150',
            'accessories_received' => 'nullable|string|max:500', 'assigned_to' => 'nullable|string|max:150',
            'expected_completion_at' => 'nullable|date_format:Y-m-d H:i:s'])->validate();
        if (trim($data['issue_description']) === '') {
            throw ValidationException::withMessages(['issue_description' => 'Issue description is required.']);
        }

        return $this->mutate($actor, $outlet, 'open', $key, $input, function () use ($actor, $outlet, $data) {
            $sale = DB::table('sales')->where('public_id', $data['sale_id'])->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $invoice = DB::table('invoices')->where('id', $sale->invoice_id)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $product = Product::whereKey($sale->product_id)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $warranty = $this->warranty($sale, $invoice, $product);
            $quantity = (int) ($data['quantity'] ?? 1);
            $unit = null;
            if ($product->track_imei) {
                $unit = StockUnit::where('public_id', $data['stock_unit_id'] ?? '')->where('product_id', $product->id)
                    ->where('invoice_id', $invoice->id)->where('sale_id', $sale->id)->where('status', 'sold')->lockForUpdate()->firstOrFail();
                if (DB::table('stock_unit_lineage')->where('source_unit_id', $unit->id)->exists()) {
                    throw new LogicException('A returned or transferred sale occurrence cannot open a warranty claim.');
                }
                $quantity = 1;
            } elseif (! empty($data['stock_unit_id'])) {
                throw new LogicException('Quantity stock cannot submit a physical-unit identity.');
            }
            $active = (int) DB::table('claims')->where('sale_id', $sale->id)->whereIn('status', self::ACTIVE)->lockForUpdate()->sum('quantity');
            $claimable = max(0, $sale->quantity - $sale->returned_quantity - $active);
            if ($quantity > $claimable) {
                throw new LogicException('Claim quantity exceeds the purchased quantity still eligible for an active claim.');
            }
            $expected = $this->date($data['expected_completion_at'] ?? null);
            if ($expected && $expected->lt(CarbonImmutable::now('UTC')->startOfDay())) {
                throw ValidationException::withMessages(['expected_completion_at' => 'Expected completion cannot be before today.']);
            }
            $received = CarbonImmutable::now('UTC');
            $public = (string) Str::uuid();
            $id = DB::table('claims')->insertGetId(['public_id' => $public, 'claim_number' => $this->number($outlet), 'product_id' => $product->id,
                'invoice_id' => $invoice->id, 'sale_id' => $sale->id, 'stock_unit_id' => $unit?->id, 'outlet_id' => $outlet->id,
                'handled_by_admin_id' => $actor->id, 'handled_by_name' => $actor->name,
                'quantity' => $quantity, 'status' => 'received', 'issue_description' => trim($data['issue_description']),
                'received_condition' => $this->nullable($data['received_condition'] ?? null), 'accessories_received' => $this->nullable($data['accessories_received'] ?? null),
                'assigned_to' => $this->nullable($data['assigned_to'] ?? null), 'received_at' => $received,
                'expected_completion_at' => $expected, 'activity_log' => json_encode([], JSON_THROW_ON_ERROR),
                'warranty_snapshot' => json_encode($warranty, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'warranty_expires_at' => $warranty['expires_at'], 'created_at' => $received, 'updated_at' => $received]);
            $this->event($id, 1, 'received', 'Warranty job received from customer.', $actor, $received);

            return $this->payload(DB::table('claims')->where('id', $id)->firstOrFail());
        });
    }

    public function update(IdentityAccount $actor, Outlet $outlet, string $claimId, string $key, array $input): array
    {
        $this->fields($input, ['status', 'assigned_to', 'diagnosis', 'resolution', 'internal_notes', 'expected_completion_at', 'customer_satisfied', 'follow_up_required', 'follow_up_at', 'follow_up_notes']);
        $data = Validator::make($input, ['status' => 'required|in:'.implode(',', self::STATUSES), 'assigned_to' => 'nullable|string|max:150',
            'diagnosis' => 'nullable|string|max:3000', 'resolution' => 'nullable|string|max:3000', 'internal_notes' => 'nullable|string|max:3000',
            'expected_completion_at' => 'nullable|date_format:Y-m-d H:i:s', 'customer_satisfied' => 'nullable|boolean',
            'follow_up_required' => 'required|boolean', 'follow_up_at' => 'nullable|date_format:Y-m-d H:i:s', 'follow_up_notes' => 'nullable|string|max:2000'])->validate();

        return $this->mutate($actor, $outlet, 'update:'.$claimId, $key, $input, function () use ($actor, $outlet, $claimId, $data) {
            $claim = DB::table('claims')->where('public_id', $claimId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            if ($claim->sale_id) {
                DB::table('sales')->where('id', $claim->sale_id)->lockForUpdate()->firstOrFail();
            } else {
                DB::table('invoices')->where('id', $claim->invoice_id)->lockForUpdate()->firstOrFail();
                DB::table('products')->where('id', $claim->product_id)->lockForUpdate()->firstOrFail();
            }
            if ($data['status'] !== $claim->status && ! in_array($data['status'], self::TRANSITIONS[$claim->status] ?? [], true)) {
                throw new LogicException('Invalid warranty claim status transition.');
            }
            $now = CarbonImmutable::now('UTC');
            $updates = ['status' => $data['status'], 'assigned_to' => $this->nullable($data['assigned_to'] ?? null),
                'diagnosis' => $this->nullable($data['diagnosis'] ?? null), 'resolution' => $this->nullable($data['resolution'] ?? null),
                'internal_notes' => $this->nullable($data['internal_notes'] ?? null), 'expected_completion_at' => $this->date($data['expected_completion_at'] ?? null),
                'customer_satisfied' => $data['customer_satisfied'] ?? null, 'follow_up_required' => (bool) $data['follow_up_required'],
                'follow_up_at' => $data['follow_up_required'] ? $this->date($data['follow_up_at'] ?? null) : null,
                'follow_up_notes' => $data['follow_up_required'] ? $this->nullable($data['follow_up_notes'] ?? null) : null,
                'version' => $claim->version + 1, 'updated_at' => $now];
            if (in_array($data['status'], ['repaired', 'replaced', 'rejected', 'ready_for_collection'], true) && ! $claim->resolved_at) {
                $updates['resolved_at'] = $now;
            }
            if (in_array($data['status'], ['delivered', 'closed'], true) && ! $claim->delivered_at) {
                $updates['delivered_at'] = $now;
            }
            DB::table('claims')->where('id', $claim->id)->update($updates);
            $sequence = (int) DB::table('claim_events')->where('claim_id', $claim->id)->lockForUpdate()->max('sequence') + 1;
            $note = $data['status'] === $claim->status ? 'Warranty job details updated.' : 'Status changed from '.$claim->status.' to '.$data['status'].'.';
            $this->event($claim->id, $sequence, $data['status'], $note, $actor, $now);

            return $this->payload(DB::table('claims')->where('id', $claim->id)->firstOrFail());
        });
    }

    public function view(IdentityAccount $actor, Outlet $outlet, string $claimId): array
    {
        $this->authorize($actor, $outlet);

        return $this->payload(DB::table('claims')->where('public_id', $claimId)->where('outlet_id', $outlet->id)->firstOrFail());
    }

    private function warranty(object $sale, object $invoice, Product $product): array
    {
        $line = $this->json($sale->invoice_detail_snapshot);
        $fallback = ! isset($line['warranty_type'], $line['warranty_unit'], $line['warranty_duration']);
        $type = $fallback ? $product->warranty_type : $line['warranty_type'];
        $unit = (int) ($fallback ? $product->warranty_unit : $line['warranty_unit']);
        $duration = (int) ($fallback ? $product->warranty_duration : $line['warranty_duration']);
        if (! in_array($type, ['shop_warranty', 'brand_warranty'], true) || ! in_array($unit, [0, 1, 2], true) || $duration < 1) {
            throw new LogicException('This sale does not have a valid warranty plan.');
        }
        $starts = CarbonImmutable::parse($invoice->created_at ?? $sale->sale_date, 'UTC');
        $expires = match ($unit) {
            0 => $starts->addDays($duration), 1 => $starts->addMonthsNoOverflow($duration), 2 => $starts->addYears($duration),
        };
        if (CarbonImmutable::now('UTC')->gt($expires)) {
            throw new LogicException('Warranty has expired for this sale.');
        }
        $terms = $this->json($invoice->warranty_terms_snapshot);

        return ['contract' => 'sale-warranty.v1', 'sale_id' => $sale->public_id, 'invoice_id' => $invoice->public_id, 'product_id' => $product->public_id,
            'type' => $type, 'unit' => $unit, 'duration' => $duration, 'starts_at' => $starts->format('Y-m-d H:i:s.u'),
            'expires_at' => $expires->format('Y-m-d H:i:s.u'), 'legacy_product_fallback' => $fallback,
            'terms' => $terms, 'terms_sha256' => hash('sha256', json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))];
    }

    private function mutate(IdentityAccount $actor, Outlet $outlet, string $operation, string $key, array $input, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $input, $callback) {
            // Revalidate under the same outlet row lock used by archival, including completed-key replays.
            $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            $this->authorize($actor, $lockedOutlet);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($input), JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $actor::class.':'.$actor->id, 'operation' => 'warranty.claim.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different claim request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback();
            IdentityAudit::record('admin', $actor->id,
                str_starts_with($operation, 'open') ? 'warranty_claim_opened' : 'warranty_claim_updated', 'claim:'.$response['claim_id'], $outlet->id);
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                'response' => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                'resource_type' => 'claim', 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function event(int $claimId, int $sequence, string $status, string $note, IdentityAccount $actor, CarbonImmutable $at): void
    {
        $claim = (array) DB::table('claims')->where('id', $claimId)->firstOrFail();
        $snapshot = ['contract' => 'claim-event.v1', 'claim_id' => $claim['public_id'], 'sequence' => $sequence, 'status' => $status,
            'note' => $note, 'actor' => ['type' => $actor::class, 'id' => $actor->id, 'name' => $actor->name],
            'occurred_at' => $at->format('Y-m-d H:i:s.u'), 'claim_version' => (int) $claim['version']];
        $encoded = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $eventId = DB::table('claim_events')->insertGetId(['public_id' => (string) Str::uuid(), 'claim_id' => $claimId, 'sequence' => $sequence, 'status' => $status,
            'note' => $note, 'actor_type' => $actor::class, 'actor_id' => $actor->id, 'actor_name' => $actor->name,
            'occurred_at' => $at, 'snapshot' => $encoded, 'snapshot_sha256' => str_repeat('0', 64)]);
        $stored = DB::table('claim_events')->where('id', $eventId)->value('snapshot');
        DB::table('claim_events')->where('id', $eventId)->update(['snapshot_sha256' => hash('sha256', $stored)]);
        $history = DB::table('claim_events')->where('claim_id', $claimId)->orderBy('sequence')->get()->map(fn ($event) => [
            'at' => $event->occurred_at, 'status' => $event->status, 'note' => $event->note, 'actor' => $event->actor_name,
        ])->all();
        DB::table('claims')->where('id', $claimId)->update(['activity_log' => json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)]);
    }

    private function payload(object $claim): array
    {
        $invoice = DB::table('invoices')->where('id', $claim->invoice_id)->firstOrFail();
        $product = DB::table('products')->where('id', $claim->product_id)->firstOrFail();
        $unit = $claim->stock_unit_id ? DB::table('stock_units')->where('id', $claim->stock_unit_id)->firstOrFail() : null;

        return ['claim_id' => $claim->public_id, 'claim_number' => $claim->claim_number, 'version' => (int) $claim->version,
            'invoice_id' => $invoice->public_id, 'invoice_number' => $invoice->invoice_number, 'customer_name' => $invoice->customer_name,
            'customer_phone' => $invoice->customer_phone, 'customer_cnic' => $invoice->customer_cnic,
            'business' => $this->json($invoice->business_snapshot), 'product_id' => $product->public_id, 'product_code' => $product->product_code,
            'product_name' => $product->name, 'stock_unit_id' => $unit?->public_id, 'unit_code' => $unit?->unit_code,
            'unit_color' => $unit?->color, 'unit_condition' => $unit?->condition,
            'quantity' => (int) $claim->quantity, 'status' => $claim->status, 'issue_description' => $claim->issue_description,
            'received_condition' => $claim->received_condition, 'accessories_received' => $claim->accessories_received, 'assigned_to' => $claim->assigned_to,
            'diagnosis' => $claim->diagnosis, 'resolution' => $claim->resolution, 'internal_notes' => $claim->internal_notes,
            'received_at' => $claim->received_at, 'expected_completion_at' => $claim->expected_completion_at,
            'resolved_at' => $claim->resolved_at, 'delivered_at' => $claim->delivered_at, 'customer_satisfied' => $claim->customer_satisfied,
            'follow_up_required' => (bool) $claim->follow_up_required, 'follow_up_at' => $claim->follow_up_at,
            'follow_up_notes' => $claim->follow_up_notes, 'handled_by_name' => $claim->handled_by_name,
            'warranty' => $this->json($claim->warranty_snapshot),
            'activity_log' => $this->json($claim->activity_log), 'imeis' => $unit ? DB::table('product_imeis')->where('stock_unit_id', $unit->id)->orderBy('slot_no')->pluck('imei')->all() : []];
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh && app(Access::class)->allows($fresh, 'shop.claims', $outlet->fresh()), 403);
    }

    private function number(Outlet $outlet): string
    {
        $date = CarbonImmutable::now('UTC')->toDateString();
        DB::table('document_sequences')->insertOrIgnore(['namespace' => 'warranty_claim', 'outlet_id' => $outlet->id, 'business_date' => $date, 'next_sequence' => 1]);
        $row = DB::table('document_sequences')->where(['namespace' => 'warranty_claim', 'outlet_id' => $outlet->id, 'business_date' => $date])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('MST-CLM-%s-%s-%04d', $outlet->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function date(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::createFromFormat('!Y-m-d H:i:s', $value, 'UTC');
    }

    private function json(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_array($value) ? $value : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected warranty claim fields.']);
        }
    }

    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }
}
