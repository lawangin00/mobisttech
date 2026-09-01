<?php

namespace App\Migration;

use App\Warranty\ClaimOperations;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Strict offline importer for complete legacy claim rows and their embedded activity history. */
final class ClaimImporter
{
    public function import(int $runId, array $row, string $timezone): array
    {
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run || $run->status !== 'claim_rehearsal' || $run->target_identity !== DB::connection()->getDatabaseName()) {
            throw new InvalidArgumentException('Explicit target claim rehearsal run required.');
        }
        ksort($row);
        $digest = hash('sha256', json_encode(['source_timezone' => $timezone, 'row' => $row], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        try {
            [$target, $values] = SourceRow::decode('pos', 'claims', $row, $timezone);

            return DB::transaction(function () use ($runId, $row, $digest, $target, $values) {
                DB::table('migration_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
                $identity = ['source_repository' => 'pos', 'source_table' => 'claims', 'source_primary_key' => (string) $row['id'], 'target_table' => $target];
                $existing = DB::table('migration_identity_map')->where($identity)->first();
                if ($existing) {
                    if (! hash_equals($existing->row_digest, $digest)) {
                        throw new InvalidArgumentException('Changed source row requires reconciliation.');
                    }

                    return ['outcome' => 'already_imported', 'target_table' => $target, 'target_id' => (int) $existing->target_id];
                }
                $values['product_id'] = $this->mapped('products', $values['product_id'], 'products');
                $values['invoice_id'] = $this->mapped('invoices', $values['invoice_id'], 'invoices');
                $values['outlet_id'] = $this->mapped('users', $values['outlet_id'], 'outlets');
                foreach (['sale_id' => ['sales', 'sales'], 'stock_unit_id' => ['stock_units', 'stock_units'], 'handled_by_admin_id' => ['admins', 'admins']] as $field => [$source, $targetTable]) {
                    if ($values[$field]) {
                        $values[$field] = $this->mapped($source, $values[$field], $targetTable);
                    }
                }
                if (! in_array($values['status'], ClaimOperations::STATUSES, true) || $values['quantity'] < 1) {
                    throw new InvalidArgumentException('Unknown claim status or invalid quantity.');
                }
                $invoice = DB::table('invoices')->where('id', $values['invoice_id'])->lockForUpdate()->firstOrFail();
                $product = DB::table('products')->where('id', $values['product_id'])->lockForUpdate()->firstOrFail();
                if ($invoice->outlet_id !== $values['outlet_id'] || $product->outlet_id !== $values['outlet_id']) {
                    throw new InvalidArgumentException('Claim invoice or product crosses its mapped outlet.');
                }
                $sale = null;
                if ($values['sale_id']) {
                    $sale = DB::table('sales')->where('id', $values['sale_id'])->lockForUpdate()->firstOrFail();
                    if ($sale->invoice_id !== $invoice->id || $sale->product_id !== $product->id || $sale->outlet_id !== $values['outlet_id']) {
                        throw new InvalidArgumentException('Claim sale does not own its invoice, product and outlet.');
                    }
                    if (in_array($values['status'], ClaimOperations::ACTIVE, true)) {
                        $active = (int) DB::table('claims')->where('sale_id', $sale->id)->whereIn('status', ClaimOperations::ACTIVE)->lockForUpdate()->sum('quantity');
                        if ($active + $values['quantity'] > $sale->quantity - $sale->returned_quantity) {
                            throw new InvalidArgumentException('Active historical claim quantity exceeds the remaining sale.');
                        }
                    }
                }
                if ($values['stock_unit_id']) {
                    $unit = DB::table('stock_units')->where('id', $values['stock_unit_id'])->lockForUpdate()->firstOrFail();
                    if ($values['quantity'] !== 1 || $unit->product_id !== $product->id || ($sale && $unit->sale_id !== $sale->id)) {
                        throw new InvalidArgumentException('Historical claim physical unit does not belong to its sale/product.');
                    }
                }
                $this->timestamps($values);
                $events = $this->events($values['activity_log']);
                [$warranty, $expires] = $this->warranty($sale, $invoice, $product);
                $values += ['public_id' => (string) Str::uuid(), 'warranty_snapshot' => json_encode($warranty, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                    'warranty_expires_at' => $expires];
                $id = DB::table('claims')->insertGetId($values);
                foreach ($events as $index => $event) {
                    $snapshot = ['contract' => 'claim-event.v1', 'claim_id' => $values['public_id'], 'sequence' => $index + 1,
                        'status' => $event['status'], 'note' => $event['note'], 'actor' => ['type' => null, 'id' => null, 'name' => $event['actor']],
                        'occurred_at' => $event['at'], 'claim_version' => 1, 'legacy_import' => true];
                    $eventId = DB::table('claim_events')->insertGetId(['public_id' => (string) Str::uuid(), 'claim_id' => $id, 'sequence' => $index + 1,
                        'status' => $event['status'], 'note' => $event['note'], 'actor_name' => $event['actor'], 'occurred_at' => $event['at'],
                        'snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 'snapshot_sha256' => str_repeat('0', 64)]);
                    $stored = DB::table('claim_events')->where('id', $eventId)->value('snapshot');
                    DB::table('claim_events')->where('id', $eventId)->update(['snapshot_sha256' => hash('sha256', $stored)]);
                }
                DB::table('migration_identity_map')->insert([...$identity, 'target_id' => (string) $id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);

                return ['outcome' => 'imported', 'target_table' => $target, 'target_id' => $id];
            }, 3);
        } catch (QueryException|InvalidArgumentException|\JsonException $error) {
            $reason = $error instanceof QueryException ? 'Target identity collision or relationship constraint.' : $error->getMessage();
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => 'pos', 'source_table' => 'claims',
                'source_primary_key' => (string) ($row['id'] ?? 'unknown'), 'reason' => $reason, 'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => $reason];
        }
    }

    private function warranty(?object $sale, object $invoice, object $product): array
    {
        if (! $sale) {
            return [['contract' => 'legacy-claim.v1', 'eligibility' => 'unresolved_historical', 'invoice_id' => $invoice->public_id,
                'product_id' => $product->public_id], null];
        }
        $line = $this->json($sale->invoice_detail_snapshot);
        $fallback = ! isset($line['warranty_type'], $line['warranty_unit'], $line['warranty_duration']);
        $type = $fallback ? $product->warranty_type : $line['warranty_type'];
        $unit = (int) ($fallback ? $product->warranty_unit : $line['warranty_unit']);
        $duration = (int) ($fallback ? $product->warranty_duration : $line['warranty_duration']);
        if (! in_array($type, ['shop_warranty', 'brand_warranty'], true) || ! in_array($unit, [0, 1, 2], true) || $duration < 1) {
            throw new InvalidArgumentException('Historical claim lacks a valid sale-time warranty plan.');
        }
        $starts = CarbonImmutable::parse($invoice->created_at ?? $sale->sale_date, 'UTC');
        $expires = match ($unit) {
            0 => $starts->addDays($duration), 1 => $starts->addMonthsNoOverflow($duration), 2 => $starts->addYears($duration)
        };
        $terms = $this->json($invoice->warranty_terms_snapshot);

        return [['contract' => 'sale-warranty.v1', 'sale_id' => $sale->public_id, 'invoice_id' => $invoice->public_id, 'product_id' => $product->public_id,
            'type' => $type, 'unit' => $unit, 'duration' => $duration, 'starts_at' => $starts->format('Y-m-d H:i:s.u'),
            'expires_at' => $expires->format('Y-m-d H:i:s.u'), 'legacy_product_fallback' => $fallback, 'terms' => $terms,
            'terms_sha256' => hash('sha256', json_encode($terms, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR))], $expires];
    }

    private function events(mixed $value): array
    {
        $events = $this->json($value);
        foreach ($events as $index => &$event) {
            if (! is_array($event) || ! is_string($event['at'] ?? null) || ! is_string($event['status'] ?? null)
                || ! in_array($event['status'], ClaimOperations::STATUSES, true) || ! is_string($event['note'] ?? null)
                || trim($event['note']) === '' || mb_strlen($event['note']) > 4000 || (! is_null($event['actor'] ?? null) && ! is_string($event['actor']))) {
                throw new InvalidArgumentException('Invalid historical claim activity event at sequence '.($index + 1).'.');
            }
            try {
                $event['at'] = CarbonImmutable::parse($event['at'])->utc()->format('Y-m-d H:i:s.u');
            } catch (\Throwable) {
                throw new InvalidArgumentException('Invalid historical claim activity timestamp.');
            }
            $event = ['at' => $event['at'], 'status' => $event['status'], 'note' => trim($event['note']), 'actor' => $event['actor'] ?? null];
        }

        return array_values($events);
    }

    private function timestamps(array $values): void
    {
        $received = $values['received_at'] ?? $values['created_at'];
        foreach (['resolved_at', 'delivered_at'] as $field) {
            if ($received && $values[$field] && strcmp($values[$field], $received) < 0) {
                throw new InvalidArgumentException('Historical claim timestamps are out of order.');
            }
        }
        if ($values['resolved_at'] && $values['delivered_at'] && strcmp($values['delivered_at'], $values['resolved_at']) < 0) {
            throw new InvalidArgumentException('Historical claim delivery precedes resolution.');
        }
    }

    private function mapped(string $table, mixed $id, string $target): int
    {
        if (! ctype_digit((string) $id)) {
            throw new InvalidArgumentException('Invalid parent source identity.');
        }
        $mapped = DB::table('migration_identity_map')->where(['source_repository' => 'pos', 'source_table' => $table,
            'source_primary_key' => (string) $id, 'target_table' => $target])->value('target_id');
        if (! $mapped || ! DB::table($target)->where('id', $mapped)->exists()) {
            throw new InvalidArgumentException('Unresolved source relationship; import its parent first.');
        }

        return (int) $mapped;
    }

    private function json(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        $decoded = is_array($value) ? $value : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new InvalidArgumentException('Historical JSON must decode to an array.');
        }

        return $decoded;
    }
}
