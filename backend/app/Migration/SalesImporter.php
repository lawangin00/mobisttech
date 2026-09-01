<?php

namespace App\Migration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/** Strict offline importer for the verified legacy invoice and sale snapshots. */
final class SalesImporter
{
    public function import(int $runId, string $table, array $row, string $timezone): array
    {
        if (! in_array($table, ['invoices', 'sales'], true)) {
            throw new InvalidArgumentException('Unsupported sales migration table.');
        }
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run || $run->status !== 'sales_rehearsal' || $run->target_identity !== DB::connection()->getDatabaseName()) {
            throw new InvalidArgumentException('Explicit target sales rehearsal run required.');
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
                    if (! hash_equals($existing->row_digest, $digest)) {
                        throw new InvalidArgumentException('Changed source row requires reconciliation.');
                    }

                    return ['outcome' => 'already_imported', 'target_table' => $target, 'target_id' => (int) $existing->target_id];
                }
                if ($target === 'invoices') {
                    $values['outlet_id'] = $this->mapped('users', $values['outlet_id'], 'outlets');
                    if ($values['salesperson_admin_id']) {
                        $values['salesperson_admin_id'] = $this->mapped('admins', $values['salesperson_admin_id'], 'admins');
                    }
                    if (bccomp($values['total_bill'], '0.00', 2) < 0 || bccomp($values['discount'], $values['total_bill'], 2) > 0
                        || bccomp($values['final_bill'], bcsub($values['total_bill'], $values['discount'], 2), 2) !== 0) {
                        throw new InvalidArgumentException('Legacy invoice arithmetic requires reconciliation.');
                    }
                    $values += ['public_id' => (string) Str::uuid(), 'currency' => 'PKR'];
                } else {
                    $values['product_id'] = $this->mapped('products', $values['product_id'], 'products');
                    $values['outlet_id'] = $this->mapped('users', $values['outlet_id'], 'outlets');
                    $values['invoice_id'] = $this->mapped('invoices', $values['invoice_id'], 'invoices');
                    $product = DB::table('products')->where('id', $values['product_id'])->firstOrFail();
                    $invoice = DB::table('invoices')->where('id', $values['invoice_id'])->firstOrFail();
                    $cost = bcmul($values['purchase_price'], (string) $values['quantity'], 2);
                    if ($values['quantity'] < 1 || $product->outlet_id !== $values['outlet_id'] || $invoice->outlet_id !== $values['outlet_id']
                        || bccomp($values['total_price'], bcmul($values['sale_price'], (string) $values['quantity'], 2), 2) !== 0
                        || bccomp($values['net_total_price'], bcsub($values['total_price'], $values['discount_allocated'], 2), 2) !== 0
                        || bccomp($values['profit'], bcsub($values['net_total_price'], $cost, 2), 2) !== 0) {
                        throw new InvalidArgumentException('Legacy sale ownership or arithmetic requires reconciliation.');
                    }
                    $values += ['public_id' => (string) Str::uuid(), 'returned_quantity' => 0];
                }
                $id = DB::table($target)->insertGetId($values);
                DB::table('migration_identity_map')->insert([...$identity, 'target_id' => (string) $id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);

                return ['outcome' => 'imported', 'target_table' => $target, 'target_id' => $id];
            }, 3);
        } catch (QueryException|InvalidArgumentException|\JsonException $error) {
            $reason = $error instanceof QueryException ? 'Target identity collision or relationship constraint.' : $error->getMessage();
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => 'pos', 'source_table' => $table,
                'source_primary_key' => (string) ($row['id'] ?? 'unknown'), 'reason' => $reason, 'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => $reason];
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
}
