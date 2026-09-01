<?php

namespace App\Inventory;

use App\Catalog\CatalogChanged;
use App\Catalog\ProductDefinitions;
use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Migration\SourceRow;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use LogicException;

final class InventoryOperations
{
    public function __construct(private StockLedger $stock) {}

    public function acquire(IdentityAccount $actor, Outlet $outlet, string $productId, string $key, array $input): array
    {
        return $this->mutate($actor, $outlet, $productId, 'acquire', $key, $input, function ($product) use ($input) {
            $this->fields($input, ['quantity', 'unit_purchase_price', 'source_type_master_data_id', 'business_name', 'seller_name', 'seller_cnic', 'seller_phone', 'seller_address', 'reason']);
            $input['seller_phone'] = preg_replace('/\D+/', '', (string) ($input['seller_phone'] ?? ''));
            $cnic = preg_replace('/\D+/', '', (string) ($input['seller_cnic'] ?? ''));
            if (strlen($cnic) === 13) {
                $input['seller_cnic'] = substr($cnic, 0, 5).'-'.substr($cnic, 5, 7).'-'.substr($cnic, 12);
            }
            $data = Validator::make($input, ['quantity' => 'required|integer|min:1|max:10000', 'unit_purchase_price' => 'required',
                'source_type_master_data_id' => 'required|integer|min:1', 'business_name' => 'nullable|string|max:255', 'seller_name' => 'nullable|string|max:255',
                'seller_cnic' => ['nullable', 'regex:/\A\d{5}-\d{7}-\d\z/'], 'seller_phone' => ['required', 'regex:/\A03\d{9}\z/'],
                'seller_address' => 'required|string|max:1000', 'reason' => 'required|string|max:500'])->validate();
            $cost = SourceRow::money($data['unit_purchase_price']);
            $master = app(PosInventoryMasterData::class);
            $option = PosMasterDataOption::whereKey($data['source_type_master_data_id'])->lockForUpdate()->firstOrFail();
            $option = $master->resolveSubmission('acquisition_source_type', $option->id);
            $business = ($option->metadata['party_kind'] ?? null) === 'business';
            if (($business && trim($data['business_name'] ?? '') === '') || (! $business && (trim($data['seller_name'] ?? '') === '' || empty($data['seller_cnic'])))) {
                throw ValidationException::withMessages(['source' => 'The buying source requires its business or individual identity.']);
            }
            $id = DB::table('stock_acquisitions')->insertGetId(['product_id' => $product->id, 'outlet_id' => $product->outlet_id,
                'source_type' => $option->code, 'source_type_master_data_id' => $option->id, 'business_name' => $business ? $data['business_name'] : null,
                'seller_name' => $business ? null : $data['seller_name'], 'seller_cnic' => $business ? null : $data['seller_cnic'],
                'seller_phone' => $data['seller_phone'], 'seller_address' => $data['seller_address'], 'quantity' => $data['quantity'],
                'unit_purchase_price' => $cost, 'acquired_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $master->syncUsage(null, $option, 'stock_acquisition', $id, 'source_type');
            $this->blankUnits($product, (int) $data['quantity'], $cost, $id);
            $product->purchase_price = $cost;
            $movement = $this->stock->movement($product, 'restock', (int) $data['quantity'], 'stock_acquisition', $id, $data['reason']);

            return ['acquisition_id' => $id, 'movement_id' => $movement];
        });
    }

    public function adjust(IdentityAccount $actor, Outlet $outlet, string $productId, string $key, array $input): array
    {
        return $this->mutate($actor, $outlet, $productId, 'adjust', $key, $input, function ($product) use ($input) {
            $this->fields($input, ['type', 'quantity', 'reason', 'unit_id']);
            $data = Validator::make($input, ['type' => 'required|in:correction_in,correction_out,damaged,lost',
                'quantity' => 'required|integer|min:1|max:10000', 'reason' => 'required|string|max:500', 'unit_id' => 'nullable|uuid'])->validate();
            $quantity = (int) $data['quantity'];
            $unit = null;
            if ($data['type'] === 'correction_in') {
                $this->blankUnits($product, $quantity, $product->purchase_price, null);
            } else {
                $snapshot = $this->stock->snapshot($product->id);
                if ($product->track_imei) {
                    $unit = StockUnit::where('public_id', $data['unit_id'] ?? '')->where('product_id', $product->id)->where('status', 'in_stock')->lockForUpdate()->firstOrFail();
                    if ($quantity !== 1 || $this->stock->holds($product->id)->contains('stock_unit_id', $unit->id)) {
                        throw new LogicException('A physical adjustment requires one unheld unit.');
                    }
                    $this->stock->retire($unit, match ($data['type']) {
                        'damaged' => 'damaged', 'lost' => 'lost', default => 'adjusted_out'
                    });
                } elseif ($quantity > $snapshot['available']) {
                    throw new LogicException('Adjustment would remove held or unavailable stock.');
                }
                $quantity = -$quantity;
            }

            return ['movement_id' => $this->stock->movement($product, $data['type'], $quantity, null, null, $data['reason'], $unit?->id)];
        });
    }

    public function imeis(IdentityAccount $actor, Outlet $outlet, string $productId, string $key, array $input): array
    {
        return $this->mutate($actor, $outlet, $productId, 'imeis', $key, $input, function ($product) use ($input) {
            $this->fields($input, ['unit_id', 'version', 'imeis']);
            Validator::make($input, ['unit_id' => 'required|uuid', 'version' => 'required|integer|min:1', 'imeis' => 'present|array|max:2', 'imeis.*' => 'required|string|max:50|distinct:strict'])->validate();
            $unit = StockUnit::where('public_id', $input['unit_id'])->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            $values = array_map('trim', $input['imeis']);
            if (! $product->track_imei || $unit->status !== 'in_stock' || $unit->version !== (int) $input['version'] || $this->stock->holds($product->id)->contains('stock_unit_id', $unit->id)
                || array_keys($values) !== ($product->requiredImeiSlots() > 0 ? range(1, $product->requiredImeiSlots()) : [])
                || count(array_unique($values)) !== count($values) || in_array('', $values, true)) {
                throw new LogicException('Unit version, hold or required IMEI slots changed.');
            }
            DB::table('active_imeis')->where('stock_unit_id', $unit->id)->delete();
            DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')->delete();
            $ordered = $values;
            asort($ordered, SORT_STRING);
            foreach ($ordered as $slot => $imei) {
                // The global binary primary key arbitrates duplicates across products/outlets as well as concurrent requests.
                DB::table('active_imeis')->insert(['imei' => $imei, 'stock_unit_id' => $unit->id, 'slot_no' => $slot]);
                DB::table('product_imeis')->insert(['product_id' => $product->id, 'stock_unit_id' => $unit->id, 'device_no' => $unit->unit_no,
                    'slot_no' => $slot, 'imei' => $imei, 'status' => 'in_stock', 'created_at' => now(), 'updated_at' => now()]);
            }
            $unit->version++;
            $unit->save();
            CatalogChanged::record('stock_unit', $unit->public_id, $unit->version);

            return ['unit_id' => $unit->public_id, 'version' => $unit->version];
        });
    }

    public function archive(IdentityAccount $actor, Outlet $outlet, string $productId, string $key): array
    {
        return $this->mutate($actor, $outlet, $productId, 'archive', $key, [], function ($product) {
            if ($product->qty !== 0 || $this->stock->holds($product->id)->isNotEmpty()) {
                throw new LogicException('Stock or holds prevent archiving.');
            }
            $product->forceFill(['isDeleted' => true, 'archived_at' => now(), 'version' => $product->version + 1])->save();
            CatalogChanged::record('product', $product->public_id, $product->version);

            return ['product_id' => $product->public_id];
        });
    }

    public function acquisition(IdentityAccount $actor, Outlet $outlet, int $id): array
    {
        $this->authorize($actor, $outlet);
        $row = DB::table('stock_acquisitions')->where('id', $id)->where('outlet_id', $outlet->id)->firstOrFail();
        $result = (array) $row;
        unset($result['cnic_front_path'], $result['cnic_back_path']);

        return $result;
    }

    private function mutate(IdentityAccount $actor, Outlet $outlet, string $productId, string $operation, string $key, array $input, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $productId, $operation, $key, $input, $callback) {
            $this->authorize($actor, $outlet);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            ksort($input);
            $digest = hash('sha256', json_encode([$productId, $outlet->id, $input], JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $actor::class.':'.$actor->getKey(), 'operation' => 'inventory.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $product = Product::where('public_id', $productId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $this->stock->snapshot($product->id);
            $response = $callback($product);
            $this->stock->snapshot($product->id);
            IdentityAudit::record('admin', $actor->id, 'inventory_'.$operation, 'product:'.$product->public_id, $outlet->id);
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'resource_type' => 'product', 'resource_id' => $product->id, 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh && app(Access::class)->allows($fresh, 'shop.inventory', $outlet->fresh()), 403);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected inventory fields; identities, quantities and states are server controlled.']);
        }
    }

    private function blankUnits(Product $product, int $quantity, string $cost, ?int $acquisition): void
    {
        if (! $product->track_imei) {
            return;
        }
        $number = (int) StockUnit::where('product_id', $product->id)->orderByDesc('unit_no')->lockForUpdate()->value('unit_no');
        $master = app(PosInventoryMasterData::class);
        for ($i = 0; $i < $quantity; $i++) {
            $unit = new StockUnit;
            $unit->forceFill(['product_id' => $product->id, 'unit_no' => ++$number, 'stock_acquisition_id' => $acquisition, 'purchase_price' => $cost, 'status' => 'in_stock']);
            foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
                if ($raw === 'color') {
                    continue;
                }
                $option = $master->optionByCode($list, 'unknown');
                $unit->$field = $option?->id;
                $unit->$raw = 'unknown';
            }
            $unit->save();
            foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
                if ($unit->$field) {
                    $master->syncUsage(null, PosMasterDataOption::findOrFail($unit->$field), 'stock_unit', $unit->id, $raw);
                }
            }
        }
    }
}
