<?php

namespace App\Inventory;

use App\Catalog\CatalogChanged;
use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class StocktakeOperations
{
    private const REASONS = ['count_error', 'damage', 'loss', 'found_stock', 'misplacement', 'other'];

    public function __construct(private StockLedger $stock) {}

    public function start(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['kind', 'product_ids', 'notes']);
        $data = Validator::make($input, [
            'kind' => 'required|in:full,cycle', 'product_ids' => 'sometimes|array|max:500',
            'product_ids.*' => 'required|uuid|distinct:strict', 'notes' => 'nullable|string|max:2000',
        ])->validate();
        $ids = array_values($data['product_ids'] ?? []);
        abort_if($data['kind'] === 'cycle' && $ids === [], 422, 'A cycle count requires at least one product.');
        abort_if($data['kind'] === 'full' && $ids !== [], 422, 'A full stocktake selects the complete active outlet catalogue server-side.');

        return $this->mutate($actor, $outlet, 'stocktake.start', $key, $data, function (Admin $fresh) use ($outlet, $data, $ids) {
            $query = Product::where('outlet_id', $outlet->id)->where('isDeleted', false);
            if ($data['kind'] === 'cycle') {
                $query->whereIn('public_id', $ids);
            }
            $products = $query->orderBy('id')->lockForUpdate()->get();
            abort_if($products->isEmpty(), 422, 'The stocktake scope contains no active products.');
            abort_if($data['kind'] === 'cycle' && $products->count() !== count($ids), 422, 'Every cycle-count product must be active in the selected outlet.');

            $publicId = (string) Str::uuid();
            $sessionId = DB::table('stocktake_sessions')->insertGetId([
                'public_id' => $publicId, 'outlet_id' => $outlet->id, 'session_number' => $this->number($outlet),
                'kind' => $data['kind'], 'status' => 'counting', 'notes' => $this->nullable($data['notes'] ?? null), 'version' => 1,
                'started_by_admin_id' => $fresh->id, 'started_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($products as $product) {
                $snapshot = $this->stock->snapshot($product->id);
                $baselineAt = now();
                $baselineMovementId = (int) (DB::table('stock_movements')->where('product_id', $product->id)->max('id') ?? 0);
                $lineId = DB::table('stocktake_lines')->insertGetId([
                    'public_id' => (string) Str::uuid(), 'stocktake_session_id' => $sessionId, 'outlet_id' => $outlet->id,
                    'product_id' => $product->id, 'product_public_id_snapshot' => $product->public_id,
                    'product_name_snapshot' => $product->name, 'tracked_serialized' => $product->track_imei,
                    'baseline_quantity' => $snapshot['on_hand'], 'baseline_held_quantity' => $snapshot['held'],
                    'baseline_available_quantity' => $snapshot['available'], 'baseline_product_version' => $product->version,
                    'baseline_movement_id' => $baselineMovementId, 'baseline_at' => $baselineAt, 'current_iteration' => 1, 'version' => 1,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                if ($product->track_imei) {
                    $units = StockUnit::where('product_id', $product->id)->where('status', 'in_stock')->orderBy('id')->lockForUpdate()->get();
                    abort_if($units->count() !== $snapshot['on_hand'], 409, 'Serialized baseline disagrees with authoritative on-hand stock.');
                    foreach ($units as $unit) {
                        $identifiers = DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')
                            ->orderBy('slot_no')->get(['slot_no', 'imei'])->map(fn ($row) => ['slot' => $row->slot_no, 'imei' => $row->imei])->all();
                        $json = json_encode(['contract' => 'stocktake-unit-baseline.v1', 'unit_id' => $unit->public_id,
                            'unit_no' => $unit->unit_no, 'version' => $unit->version, 'identifiers' => $identifiers], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                        DB::table('stocktake_unit_baselines')->insert([
                            'stocktake_line_id' => $lineId, 'stock_unit_id' => $unit->id,
                            'stock_unit_public_id_snapshot' => $unit->public_id, 'stock_unit_version' => $unit->version,
                            'unit_no' => $unit->unit_no, 'identifier_snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json),
                            'captured_at' => $baselineAt,
                        ]);
                    }
                }
            }
            IdentityAudit::record('admin', $fresh->id, 'stocktake_started', 'stocktake:'.$publicId, $outlet->id);

            return ['stocktake_id' => $publicId, 'session_number' => DB::table('stocktake_sessions')->where('id', $sessionId)->value('session_number'),
                'status' => 'counting', 'version' => 1, 'line_count' => $products->count()];
        }, 'shop.stocktake');
    }

    public function countLine(IdentityAccount $actor, Outlet $outlet, string $stocktakeId, string $lineId, string $key, array $input): array
    {
        $this->fields($input, ['line_version', 'counted_quantity', 'unit_ids', 'reason_code', 'reason_notes']);
        $data = Validator::make($input, [
            'line_version' => 'required|integer|min:1', 'counted_quantity' => 'nullable|integer|min:0|max:1000000',
            'unit_ids' => 'nullable|array|max:10000', 'unit_ids.*' => 'required|uuid|distinct:strict',
            'reason_code' => 'nullable|in:'.implode(',', self::REASONS), 'reason_notes' => 'nullable|string|max:2000',
        ])->validate();

        return $this->mutate($actor, $outlet, 'stocktake.count', $key, [$stocktakeId, $lineId, $data], function (Admin $fresh) use ($outlet, $stocktakeId, $lineId, $data) {
            $session = DB::table('stocktake_sessions')->where('public_id', $stocktakeId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'counting', 409, 'Only a counting stocktake accepts line counts.');
            $line = DB::table('stocktake_lines')->where('public_id', $lineId)->where('stocktake_session_id', $session->id)
                ->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_if((int) $line->version !== (int) $data['line_version'], 409, 'Stocktake line version changed.');
            abort_if($line->counted_at !== null, 409, 'This stocktake iteration is already counted.');
            $product = $this->stock->lock($line->product_id);
            $snapshot = $this->stock->snapshot($product->id);
            $countedAt = now();
            $movementDelta = (int) DB::table('stock_movements')->where('product_id', $product->id)
                ->where('id', '>', $line->baseline_movement_id)->sum('quantity_change');
            $expected = (int) $line->baseline_quantity + $movementDelta;
            abort_if($expected < 0 || $expected !== $snapshot['on_hand'], 409, 'Stock changed outside the auditable movement baseline; reconcile before counting.');

            $expectedUnits = [];
            $countedUnits = [];
            if ($line->tracked_serialized) {
                abort_if(array_key_exists('counted_quantity', $data) && $data['counted_quantity'] !== null, 422, 'Serialized stocktake requires unit identities, not a caller quantity.');
                $countedUnits = array_values($data['unit_ids'] ?? []);
                sort($countedUnits, SORT_STRING);
                $current = StockUnit::where('product_id', $product->id)->where('status', 'in_stock')->orderBy('public_id')->lockForUpdate()->get();
                $expectedUnits = $current->pluck('public_id')->all();
                sort($expectedUnits, SORT_STRING);
                abort_if(count($expectedUnits) !== $expected, 409, 'Serialized expected units disagree with the movement baseline.');
                $known = $current->whereIn('public_id', $countedUnits)->pluck('public_id')->all();
                sort($known, SORT_STRING);
                abort_if($known !== $countedUnits, 422, 'Counted serialized units must be current units of this product and outlet; unknown history is never fabricated.');
                $counted = count($countedUnits);
            } else {
                abort_if(array_key_exists('unit_ids', $data) && $data['unit_ids'] !== null, 422, 'Quantity stocktake does not accept serialized unit identities.');
                abort_if(! array_key_exists('counted_quantity', $data) || $data['counted_quantity'] === null, 422, 'Quantity stocktake requires counted_quantity.');
                $counted = (int) $data['counted_quantity'];
            }
            $variance = $counted - $expected;
            abort_if($variance !== 0 && empty($data['reason_code']), 422, 'A stock variance requires an approved reason code.');
            abort_if($line->tracked_serialized && $variance > 0, 422, 'Unrecognized serialized stock must be reconciled through acquisition/correction before recount; stock history cannot be invented.');

            $snapshotPayload = ['contract' => 'stocktake-count.v1', 'stocktake_id' => $stocktakeId, 'line_id' => $lineId,
                'iteration' => (int) $line->current_iteration, 'baseline_quantity' => (int) $line->baseline_quantity,
                'baseline_at' => $line->baseline_at, 'baseline_movement_id' => (int) $line->baseline_movement_id, 'expected_quantity' => $expected, 'counted_quantity' => $counted, 'variance' => $variance,
                'expected_unit_ids' => $expectedUnits, 'counted_unit_ids' => $countedUnits, 'counted_at' => $countedAt->utc()->format('Y-m-d\TH:i:s.u\Z')];
            $json = json_encode($snapshotPayload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $countPublicId = (string) Str::uuid();
            DB::table('stocktake_counts')->insert([
                'public_id' => $countPublicId, 'stocktake_session_id' => $session->id, 'stocktake_line_id' => $line->id,
                'iteration' => $line->current_iteration, 'expected_quantity' => $expected, 'counted_quantity' => $counted, 'variance' => $variance,
                'reason_code' => $variance === 0 ? null : $data['reason_code'], 'reason_notes' => $this->nullable($data['reason_notes'] ?? null),
                'counted_by_admin_id' => $fresh->id, 'count_snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json),
                'counted_at' => $countedAt, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('stocktake_lines')->where('id', $line->id)->update([
                'counted_quantity' => $counted, 'expected_quantity_at_count' => $expected, 'variance' => $variance,
                'reason_code' => $variance === 0 ? null : $data['reason_code'], 'reason_notes' => $this->nullable($data['reason_notes'] ?? null),
                'counted_by_admin_id' => $fresh->id, 'counted_at' => $countedAt, 'version' => $line->version + 1, 'updated_at' => now(),
            ]);
            $submitted = ! DB::table('stocktake_lines')->where('stocktake_session_id', $session->id)->whereNull('counted_at')->exists();
            $sessionVersion = (int) $session->version;
            if ($submitted) {
                $sessionVersion++;
                DB::table('stocktake_sessions')->where('id', $session->id)->update(['status' => 'submitted', 'version' => $sessionVersion, 'updated_at' => now()]);
            }
            IdentityAudit::record('admin', $fresh->id, 'stocktake_line_counted', 'stocktake-line:'.$lineId, $outlet->id);

            return ['stocktake_id' => $stocktakeId, 'line_id' => $lineId, 'count_id' => $countPublicId,
                'iteration' => (int) $line->current_iteration, 'expected_quantity' => $expected, 'counted_quantity' => $counted,
                'variance' => $variance, 'status' => $submitted ? 'submitted' : 'counting', 'session_version' => $sessionVersion,
                'line_version' => $line->version + 1];
        }, 'shop.stocktake');
    }

    public function requestRecount(IdentityAccount $actor, Outlet $outlet, string $stocktakeId, string $key, array $input): array
    {
        $this->fields($input, ['session_version', 'line_id', 'reason']);
        $data = Validator::make($input, ['session_version' => 'required|integer|min:1', 'line_id' => 'required|uuid', 'reason' => 'required|string|max:2000'])->validate();

        return $this->mutate($actor, $outlet, 'stocktake.recount', $key, [$stocktakeId, $data], function (Admin $fresh) use ($outlet, $stocktakeId, $data) {
            $session = DB::table('stocktake_sessions')->where('public_id', $stocktakeId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'submitted', 409, 'Only a fully counted stocktake may request a recount.');
            abort_if((int) $session->version !== (int) $data['session_version'], 409, 'Stocktake session version changed.');
            $line = DB::table('stocktake_lines')->where('public_id', $data['line_id'])->where('stocktake_session_id', $session->id)->lockForUpdate()->firstOrFail();
            $count = DB::table('stocktake_counts')->where('stocktake_line_id', $line->id)->where('iteration', $line->current_iteration)->lockForUpdate()->firstOrFail();
            $prior = json_encode(['contract' => 'stocktake-recount.v1', 'count_id' => $count->public_id, 'iteration' => $count->iteration,
                'expected_quantity' => $count->expected_quantity, 'counted_quantity' => $count->counted_quantity, 'variance' => $count->variance,
                'reason_code' => $count->reason_code, 'count_snapshot_sha256' => $count->snapshot_sha256], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $to = (int) $line->current_iteration + 1;
            $publicId = (string) Str::uuid();
            DB::table('stocktake_recounts')->insert([
                'public_id' => $publicId, 'stocktake_session_id' => $session->id, 'stocktake_line_id' => $line->id,
                'from_iteration' => $line->current_iteration, 'to_iteration' => $to, 'reason' => trim($data['reason']),
                'requested_by_admin_id' => $fresh->id, 'prior_count_snapshot' => $prior, 'snapshot_sha256' => hash('sha256', $prior), 'requested_at' => now(),
            ]);
            DB::table('stocktake_lines')->where('id', $line->id)->update([
                'current_iteration' => $to, 'counted_quantity' => null, 'expected_quantity_at_count' => null, 'variance' => null,
                'reason_code' => null, 'reason_notes' => null, 'counted_by_admin_id' => null, 'counted_at' => null,
                'version' => $line->version + 1, 'updated_at' => now(),
            ]);
            DB::table('stocktake_sessions')->where('id', $session->id)->update(['status' => 'counting', 'version' => $session->version + 1, 'updated_at' => now()]);
            IdentityAudit::record('admin', $fresh->id, 'stocktake_recount_requested', 'stocktake-line:'.$line->public_id, $outlet->id);

            return ['stocktake_id' => $stocktakeId, 'line_id' => $line->public_id, 'recount_id' => $publicId,
                'iteration' => $to, 'status' => 'counting', 'session_version' => $session->version + 1, 'line_version' => $line->version + 1];
        }, 'shop.stocktake.approve');
    }

    public function approve(IdentityAccount $actor, Outlet $outlet, string $stocktakeId, string $key, array $input): array
    {
        $this->fields($input, ['session_version', 'notes']);
        $data = Validator::make($input, ['session_version' => 'required|integer|min:1', 'notes' => 'nullable|string|max:2000'])->validate();

        return $this->mutate($actor, $outlet, 'stocktake.approve', $key, [$stocktakeId, $data], function (Admin $fresh) use ($outlet, $stocktakeId, $data) {
            $session = DB::table('stocktake_sessions')->where('public_id', $stocktakeId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless($session->status === 'submitted', 409, 'Only a fully counted stocktake may be approved.');
            abort_if((int) $session->version !== (int) $data['session_version'], 409, 'Stocktake session version changed.');
            $lines = DB::table('stocktake_lines')->where('stocktake_session_id', $session->id)->orderBy('product_id')->lockForUpdate()->get();
            abort_if($lines->isEmpty() || $lines->contains(fn ($line) => $line->counted_at === null), 409, 'Every stocktake line requires a current count before approval.');

            $approvalLines = [];
            foreach ($lines as $line) {
                $product = $this->stock->lock($line->product_id);
                $snapshot = $this->stock->snapshot($product->id);
                $count = DB::table('stocktake_counts')->where('stocktake_line_id', $line->id)->where('iteration', $line->current_iteration)->lockForUpdate()->firstOrFail();
                $staleCorrection = DB::table('stock_movements')->where('product_id', $product->id)->where('created_at', '>', $count->counted_at)
                    ->whereIn('type', ['correction_in', 'correction_out', 'damaged', 'lost', 'stocktake_adjustment'])->lockForUpdate()->first();
                abort_if($staleCorrection, 409, 'A physical stock correction occurred after counting; recount is required before approval.');
                $variance = (int) $count->variance;
                $movementIds = [];
                if ($line->tracked_serialized) {
                    abort_if($variance > 0, 409, 'Serialized positive variance cannot be approved without authoritative acquisition/correction history.');
                    $countSnapshot = json_decode($count->count_snapshot, true, flags: JSON_THROW_ON_ERROR);
                    $missing = array_values(array_diff($countSnapshot['expected_unit_ids'], $countSnapshot['counted_unit_ids']));
                    abort_if(count($missing) !== abs($variance), 409, 'Serialized variance does not match the immutable count snapshot.');
                    foreach ($missing as $unitPublicId) {
                        $unit = StockUnit::where('public_id', $unitPublicId)->where('product_id', $product->id)->where('status', 'in_stock')->lockForUpdate()->first();
                        abort_if(! $unit || $this->stock->holds($product->id)->contains('stock_unit_id', $unit->id), 409, 'A missing serialized unit changed or became held after counting; recount is required.');
                        $unit->forceFill(['status' => 'adjusted_out', 'version' => $unit->version + 1])->save();
                        DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')->update(['status' => 'adjusted_out', 'updated_at' => now()]);
                        DB::table('active_imeis')->where('stock_unit_id', $unit->id)->delete();
                        CatalogChanged::record('stock_unit', $unit->public_id, $unit->version);
                        $movementIds[] = $this->stock->movement($product, 'stocktake_adjustment', -1, 'stocktake_line', $line->id,
                            'Stocktake '.$session->session_number.' variance: '.$count->reason_code.($count->reason_notes ? ' - '.$count->reason_notes : ''), $unit->id);
                    }
                } elseif ($variance !== 0) {
                    if ($variance < 0) {
                        abort_if(abs($variance) > $snapshot['available'], 409, 'Stocktake reduction would consume held or unavailable stock; recount after the conflict resolves.');
                    }
                    $movementIds[] = $this->stock->movement($product, 'stocktake_adjustment', $variance, 'stocktake_line', $line->id,
                        'Stocktake '.$session->session_number.' variance: '.$count->reason_code.($count->reason_notes ? ' - '.$count->reason_notes : ''));
                }
                $this->stock->snapshot($product->id);
                $approvalLines[] = ['line_id' => $line->public_id, 'product_id' => $line->product_public_id_snapshot,
                    'iteration' => (int) $line->current_iteration, 'count_sha256' => $count->snapshot_sha256,
                    'expected_quantity' => (int) $count->expected_quantity, 'counted_quantity' => (int) $count->counted_quantity,
                    'variance' => $variance, 'movement_ids' => $movementIds];
            }
            $approvedAt = now();
            $snapshot = ['contract' => 'stocktake-approval.v1', 'stocktake_id' => $stocktakeId, 'session_number' => $session->session_number,
                'session_version' => (int) $session->version, 'lines' => $approvalLines, 'approved_at' => $approvedAt->utc()->format('Y-m-d\TH:i:s.u\Z')];
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $approvalPublicId = (string) Str::uuid();
            DB::table('stocktake_approvals')->insert([
                'public_id' => $approvalPublicId, 'stocktake_session_id' => $session->id, 'approved_by_admin_id' => $fresh->id,
                'actor_name_snapshot' => $fresh->name, 'actor_role_snapshot' => $fresh->roleSnapshot(), 'outlet_name_snapshot' => $outlet->name,
                'notes' => $this->nullable($data['notes'] ?? null), 'approval_snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json),
                'approved_at' => $approvedAt, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('stocktake_sessions')->where('id', $session->id)->update([
                'status' => 'approved', 'version' => $session->version + 1, 'approved_by_admin_id' => $fresh->id,
                'approved_at' => $approvedAt, 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $fresh->id, 'stocktake_approved', 'stocktake:'.$stocktakeId, $outlet->id);

            return ['stocktake_id' => $stocktakeId, 'approval_id' => $approvalPublicId, 'status' => 'approved',
                'version' => $session->version + 1, 'adjustment_movements' => array_sum(array_map(fn ($line) => count($line['movement_ids']), $approvalLines))];
        }, 'shop.stocktake.approve');
    }

    public function session(IdentityAccount $actor, Outlet $outlet, string $stocktakeId): array
    {
        $this->authorize($actor, $outlet, 'shop.stocktake');
        $session = DB::table('stocktake_sessions')->where('public_id', $stocktakeId)->where('outlet_id', $outlet->id)->firstOrFail();
        $lines = DB::table('stocktake_lines')->where('stocktake_session_id', $session->id)->orderBy('id')->get()->map(fn ($line) => [
            'line_id' => $line->public_id, 'product_id' => $line->product_public_id_snapshot, 'product_name' => $line->product_name_snapshot,
            'tracked_serialized' => (bool) $line->tracked_serialized, 'baseline_quantity' => (int) $line->baseline_quantity,
            'iteration' => (int) $line->current_iteration, 'expected_quantity' => $line->expected_quantity_at_count === null ? null : (int) $line->expected_quantity_at_count,
            'counted_quantity' => $line->counted_quantity === null ? null : (int) $line->counted_quantity,
            'variance' => $line->variance === null ? null : (int) $line->variance, 'reason_code' => $line->reason_code,
            'line_version' => (int) $line->version,
        ])->all();

        return ['stocktake_id' => $session->public_id, 'session_number' => $session->session_number, 'kind' => $session->kind,
            'status' => $session->status, 'version' => (int) $session->version, 'lines' => $lines];
    }

    private function mutate(IdentityAccount $actor, Outlet $outlet, string $operation, string $key, mixed $payload, callable $callback, string $permission): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $callback, $permission) {
            $fresh = $this->authorize($actor, $outlet, $permission);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $fresh::class.':'.$fresh->getKey(), 'operation' => $operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'resource_type' => 'stocktake', 'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet, string $permission): Admin
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, $permission, $outlet->fresh()), 403);

        return $fresh;
    }

    private function number(Outlet $outlet): string
    {
        $date = now()->utc()->format('Y-m-d');
        DB::table('document_sequences')->insertOrIgnore(['namespace' => 'stocktake', 'outlet_id' => $outlet->id, 'business_date' => $date, 'next_sequence' => 1]);
        $row = DB::table('document_sequences')->where(['namespace' => 'stocktake', 'outlet_id' => $outlet->id, 'business_date' => $date])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('ST-%s-%s-%05d', $outlet->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected stocktake fields; identities, baselines and approval state are server controlled.']);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
