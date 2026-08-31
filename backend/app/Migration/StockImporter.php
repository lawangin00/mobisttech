<?php

namespace App\Migration;

use App\Inventory\StockLedger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/** Offline staged migration; never a live stock API. Import parents first, then reconcile before activation. */
final class StockImporter
{
    public function import(int $runId, string $table, array $row, string $timezone): array
    {
        if (! in_array($table, ['stock_acquisitions', 'stock_movements', 'product_imeis'], true)) {
            throw new InvalidArgumentException('Unsupported stock source table.');
        }
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run || $run->status !== 'stock_rehearsal' || $run->target_identity !== DB::connection()->getDatabaseName()) {
            throw new InvalidArgumentException('Explicit target stock rehearsal run required.');
        }
        ksort($row);
        $digest = hash('sha256', json_encode(['source_timezone' => $timezone, 'row' => $row], JSON_THROW_ON_ERROR));
        try {
            [$target, $values] = SourceRow::decode('pos', $table, $row, $timezone);

            return DB::transaction(function () use ($runId, $table, $row, $digest, $target, $values) {
                DB::table('migration_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
                $identity = ['source_repository' => 'pos', 'source_table' => $table, 'source_primary_key' => (string) $row['id'], 'target_table' => $target];
                $existing = DB::table('migration_identity_map')->where($identity)->first();
                if ($existing) {
                    if ($existing->row_digest !== $digest) {
                        throw new InvalidArgumentException('Changed source row requires reconciliation.');
                    }

                    return ['outcome' => 'already_imported', 'target_id' => (int) $existing->target_id];
                }
                foreach (['product_id' => ['products', 'products'], 'outlet_id' => ['users', 'outlets'], 'stock_unit_id' => ['stock_units', 'stock_units'],
                    'invoice_id' => ['invoices', 'invoices'], 'sale_id' => ['sales', 'sales'], 'source_type_master_data_id' => ['pos_master_data_options', 'pos_master_data_options']] as $field => [$sourceTable, $targetTable]) {
                    if (($values[$field] ?? null) !== null) {
                        $values[$field] = $this->mapped($sourceTable, $values[$field], $targetTable);
                    }
                }
                $product = app(StockLedger::class)->lock($values['product_id']);
                if (app(StockLedger::class)->holds($product->id)->isNotEmpty()) {
                    throw new InvalidArgumentException('Offline stock import cannot change held inventory.');
                }
                if (isset($values['outlet_id']) && $values['outlet_id'] !== $product->outlet_id) {
                    throw new InvalidArgumentException('Stock history crosses outlet.');
                }
                foreach (['stock_unit_id' => 'stock_units', 'sale_id' => 'sales', 'invoice_id' => 'invoices'] as $field => $parentTable) {
                    if (($values[$field] ?? null) !== null) {
                        $parent = DB::table($parentTable)->where('id', $values[$field])->firstOrFail();
                        if (($parent->product_id ?? $product->id) !== $product->id || ($parent->outlet_id ?? $product->outlet_id) !== $product->outlet_id) {
                            throw new InvalidArgumentException('Stock relationship crosses product or outlet.');
                        }
                    }
                }
                if ($target === 'stock_acquisitions') {
                    if ($values['quantity'] < 1) {
                        throw new InvalidArgumentException('Invalid acquisition quantity.');
                    }
                    if ($values['source_type_master_data_id']) {
                        $option = DB::table('pos_master_data_options')->where('id', $values['source_type_master_data_id'])->firstOrFail();
                        if ($option->list_key !== 'acquisition_source_type' || $option->code !== $values['source_type']) {
                            throw new InvalidArgumentException('Buying-source relationship mismatch.');
                        }
                    }
                    // No source file paths are accepted as target object identities; resolve private object migration separately.
                    if ($values['cnic_front_path'] !== null || $values['cnic_back_path'] !== null) {
                        throw new InvalidArgumentException('Private acquisition documents require verified object migration before row import.');
                    }
                }
                if ($target === 'stock_movements') {
                    if ($values['stock_before'] < 0 || $values['stock_after'] < 0 || $values['stock_after'] !== $values['stock_before'] + $values['quantity_change']) {
                        throw new InvalidArgumentException('Stock movement arithmetic mismatch.');
                    }
                    if (($values['reference_type'] === null) !== ($values['reference_id'] === null)) {
                        throw new InvalidArgumentException('Incomplete stock movement reference.');
                    }
                    if ($values['reference_type'] !== null) {
                        [$sourceTable, $targetTable] = match ($values['reference_type']) {
                            'App\\Models\\StockAcquisition' => ['stock_acquisitions', 'stock_acquisitions'],
                            'App\\Models\\Sale' => ['sales', 'sales'], 'App\\Models\\Invoice' => ['invoices', 'invoices'],
                            'website_order' => ['website_orders', 'reservations'],
                            default => throw new InvalidArgumentException('Unresolved stock movement reference type.')
                        };
                        $values['reference_id'] = $this->mapped($sourceTable, $values['reference_id'], $targetTable);
                        $parent = DB::table($targetTable)->where('id', $values['reference_id'])->firstOrFail();
                        if (($parent->outlet_id ?? $product->outlet_id) !== $product->outlet_id || ($parent->product_id ?? $product->id) !== $product->id) {
                            throw new InvalidArgumentException('Stock movement reference crosses product or outlet.');
                        }
                    }
                }
                if ($target === 'product_imeis') {
                    if (! $product->track_imei || ! in_array($values['status'], ['in_stock', 'sold'], true) || ! $values['stock_unit_id']
                        || $values['slot_no'] < 1 || $values['slot_no'] > 2 || trim($values['imei']) !== $values['imei'] || $values['imei'] === '' || strlen($values['imei']) > 50) {
                        throw new InvalidArgumentException('IMEI identity, state or physical unit requires reconciliation.');
                    }
                    $unit = DB::table('stock_units')->where('id', $values['stock_unit_id'])->firstOrFail();
                    if (($values['status'] === 'sold' && $unit->status === 'in_stock') || $unit->sale_id !== $values['sale_id'] || $unit->invoice_id !== $values['invoice_id']) {
                        throw new InvalidArgumentException('IMEI financial history disagrees with its physical occurrence.');
                    }
                    if ($unit->unit_no !== $values['device_no'] || ($values['status'] === 'in_stock' && ($unit->status !== 'in_stock' || $values['sale_id'] || $values['invoice_id'] || $values['sold_at']))) {
                        throw new InvalidArgumentException('IMEI history disagrees with its physical unit.');
                    }
                    if ($values['status'] === 'in_stock') {
                        DB::table('active_imeis')->insert(['imei' => $values['imei'], 'stock_unit_id' => $values['stock_unit_id'], 'slot_no' => $values['slot_no']]);
                    }
                }
                $id = DB::table($target)->insertGetId($values);
                DB::table('migration_identity_map')->insert([...$identity, 'target_id' => (string) $id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);

                return ['outcome' => 'imported', 'target_id' => $id];
            }, 3);
        } catch (QueryException|InvalidArgumentException|\JsonException $error) {
            $reason = $error instanceof QueryException ? 'Target stock identity collision or relationship constraint.' : $error->getMessage();
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => 'pos', 'source_table' => $table,
                'source_primary_key' => (string) ($row['id'] ?? 'unknown'), 'reason' => $reason, 'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => $reason];
        }
    }

    public function reconcile(int $productId): array
    {
        return DB::transaction(function () use ($productId) {
            $stock = app(StockLedger::class);
            $product = $stock->lock($productId);
            $snapshot = $stock->snapshot($productId);
            $movements = DB::table('stock_movements')->where('product_id', $productId)->orderBy('created_at')->orderBy('id')->get();
            $previous = null;
            foreach ($movements as $movement) {
                if ($movement->outlet_id !== $product->outlet_id || $movement->stock_before + $movement->quantity_change !== $movement->stock_after
                    || ($previous !== null && $movement->stock_before !== $previous)) {
                    throw new InvalidArgumentException('Movement chain requires reconciliation; no synthetic repair is allowed.');
                }
                $previous = $movement->stock_after;
            }
            if ($previous !== null && $previous !== $product->qty) {
                throw new InvalidArgumentException('Final movement balance disagrees with stock snapshot.');
            }
            unset($snapshot['units']);

            return [...$snapshot, 'movements' => $movements->count(), 'opening_balance' => $movements->first()?->stock_before,
                'movement_coverage' => $movements->isEmpty() ? 'no_history_requires_baseline_approval' : 'reconciled_recorded_history'];
        }, 3);
    }

    private function mapped(string $sourceTable, mixed $id, string $target): int
    {
        $mapped = DB::table('migration_identity_map')->where(['source_repository' => 'pos', 'source_table' => $sourceTable,
            'source_primary_key' => (string) $id, 'target_table' => $target])->value('target_id');
        if (! $mapped || ! DB::table($target)->where('id', $mapped)->exists()) {
            throw new InvalidArgumentException('Import the explicitly mapped stock parent first.');
        }

        return (int) $mapped;
    }
}
