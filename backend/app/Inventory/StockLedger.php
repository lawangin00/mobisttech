<?php

namespace App\Inventory;

use App\Catalog\CatalogChanged;
use App\Models\Product;
use App\Models\StockUnit;
use App\Support\ProductVariantKey;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Internal stock primitives. Callers own authorization and the enclosing business transaction. */
final class StockLedger
{
    public function lock(int $productId): Product
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Stock operations require an owning transaction.');
        }

        return Product::whereKey($productId)->lockForUpdate()->firstOrFail();
    }

    public function holds(int $productId, ?int $exceptLine = null): Collection
    {
        return DB::table('reservation_allocations')->where('product_id', $productId)->whereNull('released_at')
            ->when($exceptLine !== null, fn ($query) => $query->where('reservation_line_id', '!=', $exceptLine))->orderBy('id')->lockForUpdate()->get();
    }

    public function snapshot(int $productId, ?int $exceptLine = null): array
    {
        $product = $this->lock($productId);
        $units = StockUnit::where('product_id', $productId)->orderBy('id')->lockForUpdate()->get();
        $current = $units->where('status', 'in_stock');
        $invalidClaims = DB::table('active_imeis')->join('stock_units', 'stock_units.id', '=', 'active_imeis.stock_unit_id')
            ->where('stock_units.product_id', $productId)->where('stock_units.status', '!=', 'in_stock')->lockForUpdate()->first();
        $unassignedHistory = DB::table('product_imeis')->where('product_id', $productId)->where('status', 'in_stock')
            ->whereNull('stock_unit_id')->lockForUpdate()->first();
        if ($product->qty < 0 || ($product->track_imei && $current->count() !== $product->qty)
            || (! $product->track_imei && $units->isNotEmpty()) || $invalidClaims || $unassignedHistory) {
            throw new LogicException('Stock snapshot requires reconciliation; cached quantity is not authority.');
        }
        $eligible = $current->filter(fn ($unit) => $this->complete($product, $unit));
        $holds = $this->holds($productId, $exceptLine);
        if ($holds->contains(fn ($hold) => $product->track_imei
            ? $hold->quantity !== 1 || ! $eligible->contains('id', $hold->stock_unit_id)
            : $hold->stock_unit_id !== null) || $holds->sum('quantity') > $product->qty) {
            throw new LogicException('Outstanding holds disagree with physical stock.');
        }
        $availableUnits = $eligible->whereNotIn('id', $holds->pluck('stock_unit_id')->filter());

        return ['product_id' => $productId, 'on_hand' => $product->qty, 'held' => (int) $holds->sum('quantity'),
            'available' => $product->isDeleted ? 0 : ($product->track_imei ? $availableUnits->count() : $product->qty - (int) $holds->sum('quantity')),
            'incomplete_units' => $current->count() - $eligible->count(), 'units' => $availableUnits];
    }

    private function complete(Product $product, StockUnit $unit): bool
    {
        $history = DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')->orderBy('slot_no')->lockForUpdate()->get();
        $claims = DB::table('active_imeis')->where('stock_unit_id', $unit->id)->orderBy('slot_no')->lockForUpdate()->get();
        $required = $product->requiredImeiSlots();
        if ($history->count() !== $claims->count() || $history->contains(fn ($row) => $row->product_id !== $product->id
            || $row->device_no !== $unit->unit_no || ! $claims->contains(fn ($claim) => $claim->slot_no === $row->slot_no && $claim->imei === $row->imei))) {
            throw new LogicException('Active IMEI claims disagree with history.');
        }

        return $history->pluck('slot_no')->all() === ($required > 0 ? range(1, $required) : []);
    }

    public function select(Product $product, int $quantity, string $variant = 'standard', ?int $exceptLine = null): Collection
    {
        if ($quantity < 1 || $product->isDeleted) {
            throw new LogicException('An active product and positive quantity are required.');
        }
        $snapshot = $this->snapshot($product->id, $exceptLine);
        $units = $snapshot['units'];
        if ($product->track_imei && $variant !== 'standard') {
            $units = $units->filter(fn ($unit) => ProductVariantKey::forUnit($unit) === $variant);
        }
        if ((! $product->track_imei && $variant !== 'standard') || $quantity > ($product->track_imei ? $units->count() : $snapshot['available'])) {
            throw new LogicException('Insufficient available stock for this variant.');
        }

        return $product->track_imei ? $units->take($quantity)->values() : collect();
    }

    public function movement(Product $product, string $type, int $delta, ?string $referenceType, ?int $referenceId, string $reason, ?int $unitId = null): int
    {
        if (DB::transactionLevel() < 1 || $product->qty + $delta < 0) {
            throw new LogicException('Invalid stock transaction or negative stock.');
        }
        $before = $product->qty;
        $product->qty += $delta;
        $product->version++;
        $product->save();
        $id = DB::table('stock_movements')->insertGetId(['product_id' => $product->id, 'outlet_id' => $product->outlet_id,
            'stock_unit_id' => $unitId, 'type' => $type, 'quantity_change' => $delta, 'stock_before' => $before, 'stock_after' => $product->qty,
            'reference_type' => $referenceType, 'reference_id' => $referenceId, 'reason' => $reason, 'created_at' => now(), 'updated_at' => now()]);
        CatalogChanged::record('product', $product->public_id, $product->version);

        return $id;
    }

    public function retire(StockUnit $unit, string $status, ?object $sale = null): void
    {
        $unit->forceFill(['status' => $status, 'sale_id' => $sale?->id, 'invoice_id' => $sale?->invoice_id,
            'sold_at' => $sale ? now() : null, 'version' => $unit->version + 1])->save();
        DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')->update([
            'status' => 'sold', 'sale_id' => $sale?->id, 'invoice_id' => $sale?->invoice_id, 'sold_at' => $sale ? now() : null, 'updated_at' => now()]);
        DB::table('active_imeis')->where('stock_unit_id', $unit->id)->delete();
    }
}
