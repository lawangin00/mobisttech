<?php

namespace App\Inventory;

use App\Catalog\CatalogChanged;
use App\Catalog\ProductDefinitions;
use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class StockTransferOperations
{
    public function __construct(private StockLedger $stock) {}

    public function create(IdentityAccount $actor, Outlet $sourceOutlet, string $key, array $input): array
    {
        $this->fields($input, ['destination_outlet_id', 'notes', 'lines']);
        $data = Validator::make($input, [
            'destination_outlet_id' => 'required|uuid',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:100',
            'lines.*.source_product_id' => 'required|uuid',
            'lines.*.destination_product_id' => 'required|uuid',
            'lines.*.quantity' => 'nullable|integer|min:1|max:10000',
            'lines.*.unit_ids' => 'nullable|array|min:1|max:1000',
            'lines.*.unit_ids.*' => 'required|uuid|distinct:strict',
        ])->validate();

        return $this->mutate($actor, $sourceOutlet, 'transfer.create', $key, $data, function (Admin $fresh) use ($sourceOutlet, $data) {
            $source = Outlet::whereKey($sourceOutlet->id)->lockForUpdate()->firstOrFail();
            $destination = Outlet::where('public_id', $data['destination_outlet_id'])->lockForUpdate()->firstOrFail();
            // Authorization before the lock may have observed an outlet that was subsequently archived.
            abort_if($source->status || $source->archived_at !== null || $destination->status
                || $destination->archived_at !== null, 403, 'A stock transfer requires open outlets.');
            abort_if($source->id === $destination->id, 422, 'A stock transfer requires two different outlets.');

            $pairs = collect($data['lines'])->map(function (array $line) use ($source, $destination) {
                $sourceProduct = Product::where('public_id', $line['source_product_id'])->where('outlet_id', $source->id)
                    ->where('isDeleted', false)->firstOrFail();
                $destinationProduct = Product::where('public_id', $line['destination_product_id'])->where('outlet_id', $destination->id)
                    ->where('isDeleted', false)->firstOrFail();

                return [$sourceProduct->id, $destinationProduct->id];
            });
            $this->lockProducts($pairs->flatten()->all());
            $seenPairs = [];
            $seenUnits = [];
            $prepared = [];
            foreach ($data['lines'] as $line) {
                $sourceProduct = Product::where('public_id', $line['source_product_id'])->where('outlet_id', $source->id)
                    ->where('isDeleted', false)->firstOrFail();
                $destinationProduct = Product::where('public_id', $line['destination_product_id'])->where('outlet_id', $destination->id)
                    ->where('isDeleted', false)->firstOrFail();
                $pair = $sourceProduct->id.':'.$destinationProduct->id;
                abort_if(isset($seenPairs[$pair]), 422, 'A transfer may contain a product pair only once.');
                $seenPairs[$pair] = true;
                $this->assertCompatible($sourceProduct, $destinationProduct);
                $sourceSnapshot = $this->stock->snapshot($sourceProduct->id);
                $unitIds = array_values($line['unit_ids'] ?? []);
                if ($sourceProduct->track_imei) {
                    abort_if(array_key_exists('quantity', $line) && $line['quantity'] !== null, 422, 'Serialized transfer lines use unit_ids, not caller quantity.');
                    abort_if($unitIds === [], 422, 'Serialized transfer lines require at least one source unit.');
                    $units = StockUnit::where('product_id', $sourceProduct->id)->whereIn('public_id', $unitIds)->orderBy('id')->lockForUpdate()->get();
                    abort_if($units->count() !== count($unitIds) || $units->contains(fn ($unit) => $unit->status !== 'in_stock')
                        || $units->contains(fn ($unit) => ! $sourceSnapshot['units']->contains('id', $unit->id)), 409,
                        'Every serialized transfer unit must be current, complete and available at the source outlet.');
                    foreach ($units as $unit) {
                        abort_if(isset($seenUnits[$unit->id]), 422, 'A physical unit may appear only once in a transfer.');
                        $seenUnits[$unit->id] = true;
                    }
                    $quantity = $units->count();
                } else {
                    abort_if($unitIds !== [], 422, 'Quantity transfer lines do not accept serialized unit identities.');
                    abort_if(! array_key_exists('quantity', $line) || $line['quantity'] === null, 422, 'Quantity transfer lines require quantity.');
                    $quantity = (int) $line['quantity'];
                    abort_if($quantity > $sourceSnapshot['available'], 409, 'Insufficient currently available source stock for this transfer.');
                    $units = collect();
                }
                $snapshot = $this->productPairSnapshot($sourceProduct, $destinationProduct);
                $prepared[] = compact('sourceProduct', 'destinationProduct', 'quantity', 'units', 'snapshot');
            }

            $transferPublicId = (string) Str::uuid();
            $transferId = DB::table('stock_transfers')->insertGetId([
                'public_id' => $transferPublicId,
                'transfer_number' => $this->number($source),
                'source_outlet_id' => $source->id,
                'destination_outlet_id' => $destination->id,
                'status' => 'draft',
                'notes' => $this->nullable($data['notes'] ?? null),
                'version' => 1,
                'created_by_admin_id' => $fresh->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($prepared as $item) {
                $json = json_encode($item['snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                $lineId = DB::table('stock_transfer_lines')->insertGetId([
                    'public_id' => (string) Str::uuid(),
                    'stock_transfer_id' => $transferId,
                    'source_outlet_id' => $source->id,
                    'destination_outlet_id' => $destination->id,
                    'source_product_id' => $item['sourceProduct']->id,
                    'destination_product_id' => $item['destinationProduct']->id,
                    'tracked_serialized' => $item['sourceProduct']->track_imei,
                    'quantity' => $item['quantity'],
                    'product_snapshot' => $json,
                    'snapshot_sha256' => hash('sha256', $json),
                    'version' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($item['units'] as $unit) {
                    DB::table('stock_transfer_units')->insert([
                        'stock_transfer_line_id' => $lineId,
                        'source_stock_unit_id' => $unit->id,
                        'source_stock_unit_public_id_snapshot' => $unit->public_id,
                        'status' => 'planned',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            IdentityAudit::record('admin', $fresh->id, 'stock_transfer_created', 'stock-transfer:'.$transferPublicId, $source->id);

            return [
                'transfer_id' => $transferPublicId,
                'transfer_number' => DB::table('stock_transfers')->where('id', $transferId)->value('transfer_number'),
                'status' => 'draft',
                'version' => 1,
                'line_count' => count($prepared),
            ];
        }, 'shop.transfers.dispatch');
    }

    public function dispatch(IdentityAccount $actor, Outlet $sourceOutlet, string $transferId, string $key, array $input): array
    {
        $this->fields($input, ['transfer_version']);
        $data = Validator::make($input, ['transfer_version' => 'required|integer|min:1'])->validate();

        return $this->mutate($actor, $sourceOutlet, 'transfer.dispatch', $key, [$transferId, $data], function (Admin $fresh) use ($sourceOutlet, $transferId, $data) {
            $transfer = DB::table('stock_transfers')->where('public_id', $transferId)->where('source_outlet_id', $sourceOutlet->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status === 'draft', 409, 'Only a draft transfer may be dispatched.');
            abort_if((int) $transfer->version !== (int) $data['transfer_version'], 409, 'Transfer version changed.');
            $lines = DB::table('stock_transfer_lines')->where('stock_transfer_id', $transfer->id)->orderBy('id')->lockForUpdate()->get();
            abort_if($lines->isEmpty(), 409, 'A transfer requires at least one line.');
            $this->lockProducts($lines->flatMap(fn ($line) => [$line->source_product_id, $line->destination_product_id])->all());

            foreach ($lines as $line) {
                $sourceProduct = Product::whereKey($line->source_product_id)->where('outlet_id', $transfer->source_outlet_id)
                    ->where('isDeleted', false)->firstOrFail();
                $destinationProduct = Product::whereKey($line->destination_product_id)->where('outlet_id', $transfer->destination_outlet_id)
                    ->where('isDeleted', false)->firstOrFail();
                $this->assertLineDefinition($line, $sourceProduct, $destinationProduct);
                $snapshot = $this->stock->snapshot($sourceProduct->id);
                if ($line->tracked_serialized) {
                    $planned = DB::table('stock_transfer_units')->where('stock_transfer_line_id', $line->id)->orderBy('source_stock_unit_id')
                        ->lockForUpdate()->get();
                    abort_if($planned->count() !== (int) $line->quantity || $planned->contains(fn ($unit) => $unit->status !== 'planned'), 409,
                        'Serialized transfer plan changed before dispatch.');
                    $availableIds = $snapshot['units']->pluck('id');
                    foreach ($planned as $plannedUnit) {
                        abort_if(! $availableIds->contains($plannedUnit->source_stock_unit_id), 409, 'A selected serialized unit is no longer available for dispatch.');
                        $unit = StockUnit::whereKey($plannedUnit->source_stock_unit_id)->where('product_id', $sourceProduct->id)
                            ->where('status', 'in_stock')->lockForUpdate()->firstOrFail();
                        $unitSnapshot = $this->unitSnapshot($sourceProduct, $unit);
                        $json = json_encode($unitSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                        DB::table('inventory_custody_holds')->insert([
                            'operation_id' => $transfer->public_id,
                            'product_id' => $sourceProduct->id,
                            'destination_product_id' => $destinationProduct->id,
                            'stock_unit_id' => $unit->id,
                            'quantity' => 1,
                            'created_at' => now(),
                        ]);
                        DB::table('stock_transfer_units')->where('id', $plannedUnit->id)->update([
                            'status' => 'in_transit',
                            'source_unit_snapshot' => $json,
                            'snapshot_sha256' => hash('sha256', $json),
                            'updated_at' => now(),
                        ]);
                    }
                } else {
                    abort_if((int) $line->quantity > $snapshot['available'], 409, 'Quantity stock is no longer available for dispatch.');
                    DB::table('inventory_custody_holds')->insert([
                        'operation_id' => $transfer->public_id,
                        'product_id' => $sourceProduct->id,
                        'destination_product_id' => $destinationProduct->id,
                        'quantity' => $line->quantity,
                        'created_at' => now(),
                    ]);
                }
                $this->stock->snapshot($sourceProduct->id);
            }

            $version = (int) $transfer->version + 1;
            DB::table('stock_transfers')->where('id', $transfer->id)->update([
                'status' => 'in_transit',
                'version' => $version,
                'dispatched_by_admin_id' => $fresh->id,
                'dispatched_at' => now(),
                'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $fresh->id, 'stock_transfer_dispatched', 'stock-transfer:'.$transfer->public_id, $sourceOutlet->id);

            return ['transfer_id' => $transfer->public_id, 'status' => 'in_transit', 'version' => $version];
        }, 'shop.transfers.dispatch');
    }

    public function receive(IdentityAccount $actor, Outlet $destinationOutlet, string $transferId, string $key, array $input): array
    {
        $this->fields($input, ['transfer_version', 'notes', 'lines']);
        $data = Validator::make($input, [
            'transfer_version' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:100',
            'lines.*.line_id' => 'required|uuid|distinct:strict',
            'lines.*.receive_quantity' => 'nullable|integer|min:0|max:10000',
            'lines.*.reject_quantity' => 'nullable|integer|min:0|max:10000',
            'lines.*.receive_unit_ids' => 'nullable|array|max:1000',
            'lines.*.receive_unit_ids.*' => 'required|uuid|distinct:strict',
            'lines.*.reject_unit_ids' => 'nullable|array|max:1000',
            'lines.*.reject_unit_ids.*' => 'required|uuid|distinct:strict',
        ])->validate();

        return $this->mutate($actor, $destinationOutlet, 'transfer.receive', $key, [$transferId, $data], function (Admin $fresh) use ($destinationOutlet, $transferId, $data) {
            $transfer = DB::table('stock_transfers')->where('public_id', $transferId)->where('destination_outlet_id', $destinationOutlet->id)
                ->lockForUpdate()->firstOrFail();
            abort_unless(in_array($transfer->status, ['in_transit', 'partially_received'], true) && $transfer->completed_at === null, 409,
                'Only an unresolved in-transit transfer may be received or rejected.');
            abort_if((int) $transfer->version !== (int) $data['transfer_version'], 409, 'Transfer version changed.');

            $allLines = DB::table('stock_transfer_lines')->where('stock_transfer_id', $transfer->id)->orderBy('id')->lockForUpdate()->get();
            $this->lockProducts($allLines->flatMap(fn ($line) => [$line->source_product_id, $line->destination_product_id])->all());
            $byPublic = $allLines->keyBy('public_id');
            $results = [];
            foreach ($data['lines'] as $action) {
                abort_if(! $byPublic->has($action['line_id']), 404, 'Transfer line is not part of this transfer.');
                $line = $byPublic->get($action['line_id']);
                $sourceProduct = Product::whereKey($line->source_product_id)->where('outlet_id', $transfer->source_outlet_id)->firstOrFail();
                $destinationProduct = Product::whereKey($line->destination_product_id)->where('outlet_id', $transfer->destination_outlet_id)->firstOrFail();
                $this->assertLineDefinition($line, $sourceProduct, $destinationProduct);
                $remaining = (int) $line->quantity - (int) $line->received_quantity - (int) $line->rejected_quantity;
                abort_if($remaining < 1, 409, 'Transfer line is already fully resolved.');

                if ($line->tracked_serialized) {
                    abort_if(array_key_exists('receive_quantity', $action) && $action['receive_quantity'] !== null
                        || array_key_exists('reject_quantity', $action) && $action['reject_quantity'] !== null, 422,
                        'Serialized receipt lines use source unit identities, not caller quantities.');
                    $receiveIds = array_values($action['receive_unit_ids'] ?? []);
                    $rejectIds = array_values($action['reject_unit_ids'] ?? []);
                    abort_if(array_intersect($receiveIds, $rejectIds), 422, 'A serialized unit cannot be received and rejected in the same receipt.');
                    $actionIds = array_values(array_unique(array_merge($receiveIds, $rejectIds)));
                    abort_if($actionIds === [], 422, 'A serialized receipt must resolve at least one in-transit unit.');
                    $units = DB::table('stock_transfer_units')->where('stock_transfer_line_id', $line->id)
                        ->whereIn('source_stock_unit_public_id_snapshot', $actionIds)->orderBy('id')->lockForUpdate()->get();
                    abort_if($units->count() !== count($actionIds) || $units->contains(fn ($unit) => $unit->status !== 'in_transit'), 409,
                        'A selected serialized transfer unit is unknown or already resolved.');
                    $receive = count($receiveIds);
                    $reject = count($rejectIds);
                    $movementIds = [];
                    $successors = [];
                    foreach ($units as $transferUnit) {
                        $accepted = in_array($transferUnit->source_stock_unit_public_id_snapshot, $receiveIds, true);
                        if ($accepted) {
                            $moved = $this->receiveSerializedUnit($transfer, $line, $transferUnit, $sourceProduct, $destinationProduct);
                            $movementIds = array_merge($movementIds, $moved['movement_ids']);
                            $successors[] = $moved['successor'];
                        } else {
                            $this->releaseUnitHold($transfer, $line, $transferUnit);
                            DB::table('stock_transfer_units')->where('id', $transferUnit->id)->update([
                                'status' => 'rejected', 'resolved_at' => now(), 'updated_at' => now(),
                            ]);
                        }
                    }
                } else {
                    abort_if(array_key_exists('receive_unit_ids', $action) && $action['receive_unit_ids'] !== null
                        || array_key_exists('reject_unit_ids', $action) && $action['reject_unit_ids'] !== null, 422,
                        'Quantity receipt lines do not accept serialized unit identities.');
                    $receive = (int) ($action['receive_quantity'] ?? 0);
                    $reject = (int) ($action['reject_quantity'] ?? 0);
                    abort_if($receive + $reject < 1 || $receive + $reject > $remaining, 422, 'Quantity receipt exceeds or does not resolve the in-transit balance.');
                    $movementIds = [];
                    $successors = [];
                    if ($receive > 0) {
                        $movementIds[] = $this->stock->movement($sourceProduct, 'transfer_out', -$receive, 'stock_transfer_line', $line->id,
                            'Transfer '.$transfer->transfer_number.' dispatched to '.$destinationOutlet->name);
                        $movementIds[] = $this->stock->movement($destinationProduct, 'transfer_in', $receive, 'stock_transfer_line', $line->id,
                            'Transfer '.$transfer->transfer_number.' received from source outlet');
                    }
                    $this->reduceQuantityHold($transfer, $line, $receive + $reject);
                }
                abort_if($receive + $reject > $remaining, 409, 'Receipt action exceeds unresolved transfer quantity.');
                $newReceived = (int) $line->received_quantity + $receive;
                $newRejected = (int) $line->rejected_quantity + $reject;
                DB::table('stock_transfer_lines')->where('id', $line->id)->update([
                    'received_quantity' => $newReceived,
                    'rejected_quantity' => $newRejected,
                    'version' => $line->version + 1,
                    'updated_at' => now(),
                ]);
                $this->stock->snapshot($sourceProduct->id);
                $this->stock->snapshot($destinationProduct->id);
                $results[] = [
                    'line_id' => $line->public_id,
                    'received_quantity' => $receive,
                    'rejected_quantity' => $reject,
                    'cumulative_received_quantity' => $newReceived,
                    'cumulative_rejected_quantity' => $newRejected,
                    'movement_ids' => $movementIds,
                    'successors' => $successors,
                ];
            }

            $updatedLines = DB::table('stock_transfer_lines')->where('stock_transfer_id', $transfer->id)->orderBy('id')->lockForUpdate()->get();
            $total = (int) $updatedLines->sum('quantity');
            $receivedTotal = (int) $updatedLines->sum('received_quantity');
            $rejectedTotal = (int) $updatedLines->sum('rejected_quantity');
            $remainingTotal = $total - $receivedTotal - $rejectedTotal;
            abort_if($remainingTotal < 0, 409, 'Transfer receipt totals are inconsistent.');
            $completed = $remainingTotal === 0;
            $status = $completed
                ? ($receivedTotal === 0 ? 'rejected' : ($rejectedTotal === 0 ? 'received' : 'partially_received'))
                : (($receivedTotal + $rejectedTotal) > 0 ? 'partially_received' : 'in_transit');
            $versionBefore = (int) $transfer->version;
            $versionAfter = $versionBefore + 1;
            $processedAt = now();
            DB::table('stock_transfers')->where('id', $transfer->id)->update([
                'status' => $status,
                'version' => $versionAfter,
                'completed_at' => $completed ? $processedAt : null,
                'updated_at' => now(),
            ]);

            $receiptPublicId = (string) Str::uuid();
            $receiptSnapshot = [
                'contract' => 'stock-transfer-receipt.v1',
                'transfer_id' => $transfer->public_id,
                'transfer_number' => $transfer->transfer_number,
                'transfer_version_before' => $versionBefore,
                'transfer_version_after' => $versionAfter,
                'status' => $status,
                'remaining_quantity' => $remainingTotal,
                'lines' => $results,
                'processed_at' => $processedAt->utc()->format('Y-m-d\TH:i:s.u\Z'),
            ];
            $receiptJson = json_encode($receiptSnapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $receiptId = DB::table('stock_transfer_receipts')->insertGetId([
                'public_id' => $receiptPublicId,
                'stock_transfer_id' => $transfer->id,
                'destination_outlet_id' => $destinationOutlet->id,
                'processed_by_admin_id' => $fresh->id,
                'transfer_version_before' => $versionBefore,
                'transfer_version_after' => $versionAfter,
                'notes' => $this->nullable($data['notes'] ?? null),
                'receipt_snapshot' => $receiptJson,
                'snapshot_sha256' => hash('sha256', $receiptJson),
                'processed_at' => $processedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach ($results as $result) {
                $line = $byPublic->get($result['line_id']);
                $lineJson = json_encode([
                    'contract' => 'stock-transfer-receipt-line.v1',
                    'receipt_id' => $receiptPublicId,
                    ...$result,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                DB::table('stock_transfer_receipt_lines')->insert([
                    'stock_transfer_receipt_id' => $receiptId,
                    'stock_transfer_id' => $transfer->id,
                    'stock_transfer_line_id' => $line->id,
                    'received_quantity' => $result['received_quantity'],
                    'rejected_quantity' => $result['rejected_quantity'],
                    'line_snapshot' => $lineJson,
                    'snapshot_sha256' => hash('sha256', $lineJson),
                ]);
            }
            IdentityAudit::record('admin', $fresh->id, 'stock_transfer_receipt_processed', 'stock-transfer:'.$transfer->public_id, $destinationOutlet->id);

            return [
                'transfer_id' => $transfer->public_id,
                'receipt_id' => $receiptPublicId,
                'status' => $status,
                'version' => $versionAfter,
                'remaining_quantity' => $remainingTotal,
            ];
        }, 'shop.transfers.receive');
    }

    public function details(IdentityAccount $actor, string $transferId): array
    {
        $transfer = DB::table('stock_transfers')->where('public_id', $transferId)->firstOrFail();
        $source = Outlet::findOrFail($transfer->source_outlet_id);
        $destination = Outlet::findOrFail($transfer->destination_outlet_id);
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && (
            app(Access::class)->allows($fresh, 'shop.transfers.dispatch', $source)
            || app(Access::class)->allows($fresh, 'shop.transfers.receive', $destination)
        ), 403);
        $lines = DB::table('stock_transfer_lines')->where('stock_transfer_id', $transfer->id)->orderBy('id')->get()->map(function ($line) {
            $units = DB::table('stock_transfer_units')->where('stock_transfer_line_id', $line->id)->orderBy('id')->get()->map(fn ($unit) => [
                'source_unit_id' => $unit->source_stock_unit_public_id_snapshot,
                'successor_unit_id' => $unit->successor_stock_unit_id ? StockUnit::whereKey($unit->successor_stock_unit_id)->value('public_id') : null,
                'status' => $unit->status,
            ])->all();

            return [
                'line_id' => $line->public_id,
                'source_product_id' => Product::whereKey($line->source_product_id)->value('public_id'),
                'destination_product_id' => Product::whereKey($line->destination_product_id)->value('public_id'),
                'tracked_serialized' => (bool) $line->tracked_serialized,
                'quantity' => (int) $line->quantity,
                'received_quantity' => (int) $line->received_quantity,
                'rejected_quantity' => (int) $line->rejected_quantity,
                'version' => (int) $line->version,
                'units' => $units,
            ];
        })->all();

        return [
            'transfer_id' => $transfer->public_id,
            'transfer_number' => $transfer->transfer_number,
            'source_outlet_id' => $source->public_id,
            'destination_outlet_id' => $destination->public_id,
            'status' => $transfer->status,
            'version' => (int) $transfer->version,
            'completed_at' => $transfer->completed_at,
            'lines' => $lines,
        ];
    }

    private function receiveSerializedUnit(object $transfer, object $line, object $transferUnit, Product $sourceProduct, Product $destinationProduct): array
    {
        $sourceUnit = StockUnit::whereKey($transferUnit->source_stock_unit_id)->where('product_id', $sourceProduct->id)
            ->where('status', 'in_stock')->lockForUpdate()->firstOrFail();
        $snapshot = $this->unitSnapshot($sourceProduct, $sourceUnit);
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        abort_if(! $transferUnit->snapshot_sha256 || ! hash_equals($transferUnit->snapshot_sha256, hash('sha256', $json)), 409,
            'Serialized source identity changed after dispatch; reconcile before receipt.');

        $identifiers = collect($snapshot['identifiers']);
        $sourceUnit->forceFill(['status' => 'transferred_out', 'version' => $sourceUnit->version + 1])->save();
        DB::table('product_imeis')->where('stock_unit_id', $sourceUnit->id)->where('status', 'in_stock')->update([
            'status' => 'transferred_out',
            'updated_at' => now(),
        ]);
        DB::table('active_imeis')->where('stock_unit_id', $sourceUnit->id)->delete();
        CatalogChanged::record('stock_unit', $sourceUnit->public_id, $sourceUnit->version);

        $number = (int) StockUnit::where('product_id', $destinationProduct->id)->orderByDesc('unit_no')->lockForUpdate()->value('unit_no');
        $successor = new StockUnit;
        $successor->forceFill([
            'product_id' => $destinationProduct->id,
            'unit_no' => $number + 1,
            'stock_acquisition_id' => null,
            'purchase_price' => $sourceUnit->purchase_price,
            'status' => 'in_stock',
            'version' => 1,
            'color' => $sourceUnit->color,
            'condition' => $sourceUnit->condition,
            'pta_status' => $sourceUnit->pta_status,
            'carrier_lock_status' => $sourceUnit->carrier_lock_status,
            'mdm_status' => $sourceUnit->mdm_status,
            'notes' => $sourceUnit->notes,
            'color_master_data_id' => $sourceUnit->color_master_data_id,
            'condition_master_data_id' => $sourceUnit->condition_master_data_id,
            'pta_status_master_data_id' => $sourceUnit->pta_status_master_data_id,
            'carrier_lock_master_data_id' => $sourceUnit->carrier_lock_master_data_id,
            'mdm_status_master_data_id' => $sourceUnit->mdm_status_master_data_id,
        ])->save();
        $master = app(PosInventoryMasterData::class);
        foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
            if ($successor->$field) {
                $master->syncUsage(null, PosMasterDataOption::findOrFail($successor->$field), 'stock_unit', $successor->id, $raw);
            }
        }
        foreach ($identifiers as $identifier) {
            DB::table('product_imeis')->insert([
                'product_id' => $destinationProduct->id,
                'stock_unit_id' => $successor->id,
                'device_no' => $successor->unit_no,
                'slot_no' => $identifier['slot'],
                'imei' => $identifier['imei'],
                'status' => 'in_stock',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('active_imeis')->insert([
                'imei' => $identifier['imei'],
                'stock_unit_id' => $successor->id,
                'slot_no' => $identifier['slot'],
            ]);
        }
        DB::table('stock_unit_lineage')->insert([
            'source_unit_id' => $sourceUnit->id,
            'successor_unit_id' => $successor->id,
            'reason' => 'transfer',
            'operation_id' => $transfer->public_id,
            'created_at' => now(),
        ]);
        CatalogChanged::record('stock_unit', $successor->public_id, $successor->version);

        $sourceMovement = $this->stock->movement($sourceProduct, 'transfer_out', -1, 'stock_transfer_line', $line->id,
            'Transfer '.$transfer->transfer_number.' serialized dispatch', $sourceUnit->id);
        $destinationMovement = $this->stock->movement($destinationProduct, 'transfer_in', 1, 'stock_transfer_line', $line->id,
            'Transfer '.$transfer->transfer_number.' serialized receipt', $successor->id);
        $this->releaseUnitHold($transfer, $line, $transferUnit);
        DB::table('stock_transfer_units')->where('id', $transferUnit->id)->update([
            'successor_stock_unit_id' => $successor->id,
            'status' => 'received',
            'resolved_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'movement_ids' => [$sourceMovement, $destinationMovement],
            'successor' => [
                'source_unit_id' => $sourceUnit->public_id,
                'successor_unit_id' => $successor->public_id,
                'origin_stock_acquisition_id' => $sourceUnit->stock_acquisition_id,
            ],
        ];
    }

    private function releaseUnitHold(object $transfer, object $line, object $transferUnit): void
    {
        $hold = DB::table('inventory_custody_holds')->where('operation_id', $transfer->public_id)
            ->where('product_id', $line->source_product_id)->where('destination_product_id', $line->destination_product_id)
            ->where('stock_unit_id', $transferUnit->source_stock_unit_id)->whereNull('released_at')->lockForUpdate()->firstOrFail();
        DB::table('inventory_custody_holds')->where('id', $hold->id)->update(['released_at' => now()]);
    }

    private function reduceQuantityHold(object $transfer, object $line, int $quantity): void
    {
        $hold = DB::table('inventory_custody_holds')->where('operation_id', $transfer->public_id)
            ->where('product_id', $line->source_product_id)->where('destination_product_id', $line->destination_product_id)
            ->whereNull('stock_unit_id')->whereNull('released_at')->lockForUpdate()->firstOrFail();
        abort_if($quantity < 1 || $quantity > (int) $hold->quantity, 409, 'Quantity custody balance changed.');
        $remaining = (int) $hold->quantity - $quantity;
        DB::table('inventory_custody_holds')->where('id', $hold->id)->update($remaining > 0
            ? ['quantity' => $remaining]
            : ['released_at' => now()]);
    }

    private function unitSnapshot(Product $product, StockUnit $unit): array
    {
        $history = DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')
            ->orderBy('slot_no')->lockForUpdate()->get(['slot_no', 'imei']);
        $claims = DB::table('active_imeis')->where('stock_unit_id', $unit->id)->orderBy('slot_no')->lockForUpdate()->get();
        abort_if($history->count() !== $product->requiredImeiSlots() || $claims->count() !== $history->count()
            || $history->contains(fn ($row) => ! $claims->contains(fn ($claim) => (int) $claim->slot_no === (int) $row->slot_no && $claim->imei === $row->imei)), 409,
            'Serialized transfer requires complete matching active IMEI identity.');

        return [
            'contract' => 'stock-transfer-unit.v1',
            'source_unit_id' => $unit->public_id,
            'source_unit_code' => $unit->unit_code,
            'source_unit_version' => (int) $unit->version,
            'source_unit_no' => (int) $unit->unit_no,
            'source_stock_acquisition_id' => $unit->stock_acquisition_id,
            'purchase_price' => (string) $unit->purchase_price,
            'color' => $unit->color,
            'condition' => $unit->condition,
            'pta_status' => $unit->pta_status,
            'carrier_lock_status' => $unit->carrier_lock_status,
            'mdm_status' => $unit->mdm_status,
            'master_data' => [
                'color' => $unit->color_master_data_id,
                'condition' => $unit->condition_master_data_id,
                'pta_status' => $unit->pta_status_master_data_id,
                'carrier_lock_status' => $unit->carrier_lock_master_data_id,
                'mdm_status' => $unit->mdm_status_master_data_id,
            ],
            'identifiers' => $history->map(fn ($row) => ['slot' => (int) $row->slot_no, 'imei' => $row->imei])->all(),
        ];
    }

    private function productPairSnapshot(Product $source, Product $destination): array
    {
        return [
            'contract' => 'stock-transfer-product-pair.v1',
            'source' => $this->productDefinition($source),
            'destination' => $this->productDefinition($destination),
            'definition_fingerprint' => $this->definitionFingerprint($source),
        ];
    }

    private function assertLineDefinition(object $line, Product $source, Product $destination): void
    {
        $stored = json_decode($line->product_snapshot, true, flags: JSON_THROW_ON_ERROR);
        abort_if(! isset($stored['definition_fingerprint'])
            || ! hash_equals($stored['definition_fingerprint'], $this->definitionFingerprint($source))
            || ! hash_equals($stored['definition_fingerprint'], $this->definitionFingerprint($destination)), 409,
            'A transfer product definition changed after planning; reconcile the transfer before continuing.');
        $this->assertCompatible($source, $destination);
    }

    private function assertCompatible(Product $source, Product $destination): void
    {
        abort_if($source->outlet_id === $destination->outlet_id || $source->isDeleted || $destination->isDeleted
            || ! hash_equals($this->definitionFingerprint($source), $this->definitionFingerprint($destination)), 422,
            'Source and destination products must represent the same active stock definition in different outlets.');
    }

    private function productDefinition(Product $product): array
    {
        return [
            'product_id' => $product->public_id,
            'product_code' => $product->product_code,
            'name' => $product->name,
            'category' => $product->category,
            'subcategory_master_data_id' => $product->subcategory_master_data_id,
            'brand_master_data_id' => $product->brand_master_data_id,
            'model' => $product->model,
            'ram_master_data_id' => $product->ram_master_data_id,
            'storage_master_data_id' => $product->storage_master_data_id,
            'sim_master_data_id' => $product->sim_master_data_id,
            'track_imei' => (bool) $product->track_imei,
            'imei_slots' => $product->requiredImeiSlots(),
        ];
    }

    private function definitionFingerprint(Product $product): string
    {
        $definition = $this->productDefinition($product);
        unset($definition['product_id'], $definition['product_code']);

        return hash('sha256', json_encode($definition, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
    }

    private function lockProducts(array $ids): Collection
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids, SORT_NUMERIC);
        $products = Product::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        abort_if($products->count() !== count($ids), 409, 'A transfer product disappeared while locking stock.');

        return $products;
    }

    private function mutate(IdentityAccount $actor, Outlet $scopeOutlet, string $operation, string $key, mixed $payload, callable $callback, string $permission): array
    {
        return DB::transaction(function () use ($actor, $scopeOutlet, $operation, $key, $payload, $callback, $permission) {
            // Deny unauthorized actors before reading transfer details; repeat authorization under the archive-shared locks.
            $this->authorize($actor, $scopeOutlet, $permission);
            // Lock both affected outlets in stable ID order before final authorization or idempotent replay.
            // Dispatch/receive affect both locations even when only one is the actor's permission scope.
            if ($operation === 'transfer.create') {
                $otherOutletId = Outlet::where('public_id', $payload['destination_outlet_id'])->value('id');
                abort_unless($otherOutletId !== null, 404);
            } else {
                $transfer = DB::table('stock_transfers')->where('public_id', $payload[0])->firstOrFail();
                abort_unless((int) ($operation === 'transfer.dispatch' ? $transfer->source_outlet_id : $transfer->destination_outlet_id)
                    === (int) $scopeOutlet->id, 404);
                $otherOutletId = $operation === 'transfer.dispatch' ? $transfer->destination_outlet_id : $transfer->source_outlet_id;
            }
            $ids = array_values(array_unique([(int) $scopeOutlet->id, (int) $otherOutletId]));
            sort($ids, SORT_NUMERIC);
            $locked = [];
            foreach ($ids as $id) {
                $outlet = Outlet::whereKey($id)->lockForUpdate()->firstOrFail();
                abort_if($outlet->status || $outlet->archived_at !== null, 403, 'A stock transfer requires open outlets.');
                $locked[$id] = $outlet;
            }
            $fresh = $this->authorize($actor, $locked[(int) $scopeOutlet->id], $permission);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identity = ['actor_scope' => $fresh::class.':'.$fresh->getKey(), 'operation' => $operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([
                ...$identity,
                'request_hash' => $digest,
                'status' => 'processing',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed',
                'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
                'resource_type' => 'stock_transfer',
                'updated_at' => now(),
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

    private function number(Outlet $source): string
    {
        $date = now()->utc()->format('Y-m-d');
        DB::table('document_sequences')->insertOrIgnore([
            'namespace' => 'stock-transfer',
            'outlet_id' => $source->id,
            'business_date' => $date,
            'next_sequence' => 1,
        ]);
        $row = DB::table('document_sequences')->where([
            'namespace' => 'stock-transfer',
            'outlet_id' => $source->id,
            'business_date' => $date,
        ])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('TR-%s-%s-%05d', $source->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected stock-transfer fields; stock identity, custody and state are server controlled.']);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
