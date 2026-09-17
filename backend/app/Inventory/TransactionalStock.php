<?php

namespace App\Inventory;

use App\Catalog\CatalogChanged;
use Illuminate\Support\Facades\DB;
use LogicException;

/** Internal adapters, not endpoints. The caller authorizes ownership/payment and owns the entire transaction. */
final class TransactionalStock
{
    public function __construct(private StockLedger $stock) {}

    public function reserve(int $lineId): array
    {
        [$reservation, $line] = $this->line($lineId);
        $this->holding($reservation);
        $product = $this->stock->lock($line->product_id);
        $existing = DB::table('reservation_allocations')->where('reservation_line_id', $lineId)->orderBy('id')->lockForUpdate()->get();
        if ($existing->isNotEmpty()) {
            if ($existing->contains(fn ($row) => $row->released_at !== null) || $existing->sum('quantity') !== $line->quantity) {
                throw new LogicException('A closed or inconsistent allocation cannot be resurrected.');
            }
            $this->stock->snapshot($product->id);

            return $existing->pluck('id')->all();
        }
        $units = $this->stock->select($product, $line->quantity, $line->variant_key);
        $ids = [];
        foreach ($product->track_imei ? $units->all() : [null] as $unit) {
            $ids[] = DB::table('reservation_allocations')->insertGetId(['reservation_line_id' => $lineId, 'product_id' => $product->id,
                'stock_unit_id' => $unit?->id, 'quantity' => $unit ? 1 : $line->quantity, 'active_key' => $unit ? 'stock-unit:'.$unit->id : 'line:'.$lineId,
                'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('reservation_lines')->where('id', $lineId)->update(['allocated_quantity' => $line->quantity, 'updated_at' => now()]);
        $this->changed($product);

        return $ids;
    }

    /** Sale/invoice rows must be created and validated by the owning sales workflow in this same transaction. */
    public function consumeSale(int $saleId, ?int $lineId = null): int
    {
        $this->transaction();
        $reservation = $line = null;
        if ($lineId !== null) {
            [$reservation, $line] = $this->line($lineId);
        }
        $sale = DB::table('sales')->where('id', $saleId)->firstOrFail();
        $invoice = DB::table('invoices')->where('id', $sale->invoice_id)->lockForUpdate()->firstOrFail();
        $sale = DB::table('sales')->where('id', $saleId)->lockForUpdate()->firstOrFail();
        $product = $this->stock->lock($sale->product_id);
        if ($sale->outlet_id !== $product->outlet_id || $invoice->outlet_id !== $product->outlet_id
            || ($line && ($line->product_id !== $product->id || $line->quantity !== $sale->quantity || $invoice->order_id !== $reservation->order_id))) {
            throw new LogicException('Sale stock ownership or reserved quantity mismatch.');
        }
        $existing = DB::table('stock_movements')->where('reference_type', 'sale')->where('reference_id', $saleId)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->product_id !== $product->id || -$existing->quantity_change !== $sale->quantity || $existing->reason !== 'Sale stock; reservation line '.($lineId ?? 'none')) {
                throw new LogicException('Previously consumed sale changed.');
            }

            return $existing->id;
        }
        if ($reservation) {
            $this->holding($reservation);
        }
        $units = $this->stock->select($product, $sale->quantity, $line?->variant_key ?? 'standard', $lineId);
        if ($line) {
            $allocations = DB::table('reservation_allocations')->where('reservation_line_id', $lineId)->whereNull('released_at')->orderBy('id')->lockForUpdate()->get();
            if ($allocations->sum('quantity') !== $sale->quantity) {
                throw new LogicException('Sale requires its complete current allocation.');
            }
            if ($product->track_imei) {
                // Use exactly the held units, never substitute another physical unit of the same variant.
                $snapshot = $this->stock->snapshot($product->id, $lineId);
                $units = $snapshot['units']->whereIn('id', $allocations->pluck('stock_unit_id'));
                if ($units->count() !== $sale->quantity) {
                    throw new LogicException('Reserved units are no longer eligible.');
                }
            }
            DB::table('reservation_allocations')->whereIn('id', $allocations->pluck('id'))->update(['released_at' => now(), 'active_key' => null, 'updated_at' => now()]);
            DB::table('reservation_lines')->where('id', $lineId)->update(['allocated_quantity' => 0, 'updated_at' => now()]);
        }
        foreach ($units as $unit) {
            $this->stock->retire($unit, 'sold', $sale);
        }
        $product->sold_qty += $sale->quantity;
        $movement = $this->stock->movement($product, 'sale', -$sale->quantity, 'sale', $saleId, 'Sale stock; reservation line '.($lineId ?? 'none'));
        $this->stock->snapshot($product->id);

        return $movement;
    }

    public function consumeRepairPart(int $estimateLineId): int
    {
        $this->transaction();
        $line = DB::table('repair_estimate_lines')->where('id', $estimateLineId)->lockForUpdate()->firstOrFail();
        if ($line->line_type !== 'part' || ! $line->product_id) {
            throw new LogicException('Only an approved stock-backed repair part line can consume inventory.');
        }
        $estimate = DB::table('repair_estimates')->where('id', $line->repair_estimate_id)->lockForUpdate()->firstOrFail();
        $approval = DB::table('repair_estimate_approvals')->where('repair_estimate_id', $estimate->id)->lockForUpdate()->first();
        if (! $approval) {
            throw new LogicException('Repair part consumption requires the approved estimate.');
        }
        $job = DB::table('repair_jobs')->where('id', $approval->repair_job_id)->lockForUpdate()->firstOrFail();
        if (! in_array($job->status, ['approved', 'repairing'], true)) {
            throw new LogicException('Repair job is not in a part-consumable state.');
        }
        $product = $this->stock->lock($line->product_id);
        if ($product->outlet_id !== $job->outlet_id || $product->track_imei || $product->isDeleted) {
            throw new LogicException('Repair parts must be active non-serialized stock from the repair outlet.');
        }
        $existing = DB::table('stock_movements')->where('reference_type', 'repair_estimate_line')
            ->where('reference_id', $line->id)->lockForUpdate()->first();
        if ($existing) {
            if ($existing->product_id !== $product->id || -$existing->quantity_change !== $line->quantity || $existing->type !== 'repair_part') {
                throw new LogicException('Previously consumed repair part changed.');
            }

            return $existing->id;
        }
        $this->stock->select($product, (int) $line->quantity);
        $movement = $this->stock->movement($product, 'repair_part', -(int) $line->quantity, 'repair_estimate_line', $line->id,
            'Approved paid repair part consumption');
        $this->stock->snapshot($product->id);

        return $movement;
    }

    public function release(int $reservationId, bool $expire = false): void
    {
        $this->transaction();
        $reservation = DB::table('reservations')->where('id', $reservationId)->lockForUpdate()->firstOrFail();
        if (in_array($reservation->state, ['released', 'expired', 'confirmed'], true)) {
            return;
        }
        if (! in_array($reservation->state, ['active', 'held_cod'], true)) {
            throw new LogicException('Reservation state is not releasable.');
        }
        if ($expire && ($reservation->state !== 'active' || ! $reservation->reservation_expires_at || now()->lt($reservation->reservation_expires_at))) {
            throw new LogicException('Only an overdue active reservation may expire.');
        }
        $lines = DB::table('reservation_lines')->where('reservation_id', $reservationId)->orderBy('product_id')->orderBy('id')->lockForUpdate()->get();
        foreach ($lines->pluck('product_id')->unique() as $productId) {
            $product = $this->stock->lock($productId);
            DB::table('reservation_allocations')->whereIn('reservation_line_id', $lines->pluck('id'))->where('product_id', $productId)->whereNull('released_at')
                ->update(['released_at' => now(), 'active_key' => null, 'updated_at' => now()]);
            $this->stock->snapshot($productId);
            $this->changed($product);
        }
        DB::table('reservation_lines')->where('reservation_id', $reservationId)->update(['allocated_quantity' => 0, 'updated_at' => now()]);
        DB::table('reservations')->where('id', $reservationId)->update(['state' => $expire ? 'expired' : 'released',
            $expire ? 'expired_at' : 'released_at' => now(), 'updated_at' => now()]);
    }

    private function line(int $id): array
    {
        $this->transaction();
        $line = DB::table('reservation_lines')->where('id', $id)->firstOrFail();
        $reservation = DB::table('reservations')->where('id', $line->reservation_id)->lockForUpdate()->firstOrFail();
        $line = DB::table('reservation_lines')->where('id', $id)->lockForUpdate()->firstOrFail();
        $item = DB::table('order_items')->where('id', $line->order_item_id)->firstOrFail();
        if ($line->outlet_id !== $reservation->outlet_id || $item->order_id !== $reservation->order_id
            || $item->product_id !== $line->product_id || $item->outlet_id !== $line->outlet_id || $item->quantity !== $line->quantity) {
            throw new LogicException('Reservation line identity mismatch.');
        }

        return [$reservation, $line];
    }

    private function holding(object $reservation): void
    {
        if (! in_array($reservation->state, ['active', 'held_cod'], true) || ($reservation->state === 'active'
            && (! $reservation->reservation_expires_at || now()->gte($reservation->reservation_expires_at)))) {
            throw new LogicException('Reservation no longer accepts stock operations.');
        }
    }

    private function transaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('The owning order/sale transaction is required.');
        }
    }

    private function changed($product): void
    {
        $product->version++;
        $product->save();
        CatalogChanged::record('product', $product->public_id, $product->version);
    }
}
