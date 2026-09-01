<?php

namespace App\Migration;

use App\Catalog\ProductDefinitions;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use App\Support\PosMasterDataRegistry;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class ProductImporter
{
    public function import(int $runId, string $source, string $table, array $row, string $timezone): array
    {
        if (! in_array($source.'.'.$table, ['pos.pos_master_data_options', 'pos.pos_master_data_usages', 'pos.products', 'pos.stock_units', 'website.products'], true)) {
            throw new InvalidArgumentException('Unsupported product migration table.');
        }
        $run = DB::table('migration_runs')->where('id', $runId)->first();
        if (! $run || $run->status !== 'product_rehearsal' || $run->target_identity !== DB::connection()->getDatabaseName()) {
            throw new InvalidArgumentException('Explicit target product rehearsal run required.');
        }
        ksort($row);
        $digest = hash('sha256', json_encode(['source_timezone' => $timezone, 'row' => $row], JSON_THROW_ON_ERROR));
        try {
            [$target, $values] = SourceRow::decode($source, $table, $row, $timezone);

            return DB::transaction(function () use ($runId, $source, $table, $row, $digest, $target, $values) {
                DB::table('migration_runs')->where('id', $runId)->lockForUpdate()->firstOrFail();
                $identity = ['source_repository' => $source, 'source_table' => $table, 'source_primary_key' => (string) $row['id'], 'target_table' => $target];
                $existing = DB::table('migration_identity_map')->where($identity)->first();
                if ($existing) {
                    if ($existing->row_digest !== $digest) {
                        throw new InvalidArgumentException('Changed source row requires reconciliation.');
                    }

                    return ['outcome' => 'already_imported', 'target_table' => $target, 'target_id' => (int) $existing->target_id];
                }
                $schema = json_decode(file_get_contents(base_path('../docs/schema/TARGET_SCHEMA.json')), true, flags: JSON_THROW_ON_ERROR);
                foreach ($schema['tables'][$target]['foreign_keys'] as $fk) {
                    if (count($fk['columns']) !== 1 || $target === 'product_listings') {
                        continue;
                    }
                    $column = $fk['columns'][0];
                    if (($values[$column] ?? null) !== null) {
                        $sourceTable = $fk['table'] === 'outlets' ? 'users' : $fk['table'];
                        $values[$column] = $this->mapped('pos', $sourceTable, $values[$column], $fk['table']);
                    }
                }
                if ($target === 'pos_master_data_options') {
                    $this->option($values);
                    foreach (['created', 'updated'] as $kind) {
                        $type = $values[$kind.'_by_type'];
                        $actorId = $values[$kind.'_by_id'];
                        if (($type === null) !== ($actorId === null)) {
                            throw new InvalidArgumentException('Incomplete source actor identity.');
                        }
                        if ($type !== null) {
                            $actorTable = match ($type) {
                                'admin' => 'admins', 'superadmin' => 'super_admins', default => throw new InvalidArgumentException('Unknown source actor realm.')
                            };
                            $values[$kind.'_by_type'] = 'admin';
                            $values[$kind.'_by_id'] = $this->mappedAdmin($actorTable, $actorId);
                        }
                    }
                }
                if ($target === 'products') {
                    if (! array_key_exists($values['category'], Product::CATEGORY_LABELS) || ! is_string($values['product_code']) || ! preg_match('/\AMST-(MOB|TAB|ACC)-\d{3}-\d{6,}\z/', $values['product_code'])) {
                        throw new InvalidArgumentException('Protected category or existing product identifier requires reconciliation.');
                    }
                    if ($values['qty'] < 0 || $values['sold_qty'] < 0 || ($values['category'] === 'accessory' && $values['track_imei'])) {
                        throw new InvalidArgumentException('Invalid source product stock mode/counters.');
                    }
                    $codeOutlet = explode('-', $values['product_code'])[2];
                    if ($codeOutlet !== DB::table('outlets')->where('id', $values['outlet_id'])->value('outlet_code')) {
                        throw new InvalidArgumentException('Product identifier disagrees with mapped outlet.');
                    }
                    if (! in_array($values['warranty_type'], ['no_warranty', 'shop_warranty', 'brand_warranty'], true)
                        || ($values['ram_gb'] !== null && ($values['ram_gb'] < 1 || $values['ram_gb'] > 2048))
                        || ($values['storage_gb'] !== null && ($values['storage_gb'] < 1 || $values['storage_gb'] > 8192))) {
                        throw new InvalidArgumentException('Invalid source product attributes.');
                    }
                    $this->optionReferences($values, ProductDefinitions::PRODUCT_OPTIONS);
                    if ($values['category_master_data_id'] && PosMasterDataOption::findOrFail($values['category_master_data_id'])->code !== $values['category']) {
                        throw new InvalidArgumentException('Category relationship disagrees with source category.');
                    }
                    if ($values['subcategory_master_data_id'] && app(PosInventoryMasterData::class)->subcategoryParentCode(PosMasterDataOption::findOrFail($values['subcategory_master_data_id'])) !== $values['category']) {
                        throw new InvalidArgumentException('Subcategory parent mismatch.');
                    }
                }
                if ($target === 'stock_units') {
                    $product = Product::findOrFail($values['product_id']);
                    if (! $product->track_imei || $product->category === 'accessory' || $values['unit_no'] < 1 || ! array_key_exists($values['status'], StockUnit::STOCK_STATUSES)
                        || ! is_string($values['unit_code']) || $values['unit_code'] !== sprintf('%s-U%04d', $product->product_code, $values['unit_no'])) {
                        throw new InvalidArgumentException('Source unit definition or identifier mismatch.');
                    }
                    $this->optionReferences($values, ProductDefinitions::UNIT_OPTIONS);
                    foreach (['stock_acquisition_id' => 'stock_acquisitions', 'invoice_id' => 'invoices', 'sale_id' => 'sales'] as $field => $related) {
                        if ($values[$field]) {
                            $parent = DB::table($related)->where('id', $values[$field])->first();
                            if (($parent->outlet_id ?? $product->outlet_id) !== $product->outlet_id || ($parent->product_id ?? $product->id) !== $product->id) {
                                throw new InvalidArgumentException('Historical unit reference crosses product or outlet.');
                            }
                        }
                    }
                }
                if ($target === 'product_listings') {
                    $metadata = json_decode($values['source_metadata'] ?? 'null', true, flags: JSON_THROW_ON_ERROR);
                    if ($values['external_source'] !== 'mobist-pos' || ! is_array($metadata) || ! isset($metadata['shop_id'], $metadata['category'])) {
                        throw new InvalidArgumentException('Listing lacks verified POS contract identity.');
                    }
                    $productId = $this->mapped('pos', 'products', $values['external_id'], 'products');
                    $outletId = $this->mapped('pos', 'users', $metadata['shop_id'], 'outlets');
                    $product = Product::findOrFail($productId);
                    if ($product->outlet_id !== $outletId || $product->category !== $values['category'] || $metadata['category'] !== $product->category
                        || (isset($metadata['sku']) && $metadata['sku'] !== $product->sku)) {
                        throw new InvalidArgumentException('Listing product/outlet/category/contract mismatch.');
                    }
                    $values['product_id'] = $productId;
                }
                if ($target === 'pos_master_data_usages') {
                    $usageTable = match ($values['usage_type']) {
                        'product' => 'products', 'stock_unit' => 'stock_units', 'stock_acquisition' => 'stock_acquisitions', default => throw new InvalidArgumentException('Unreconciled historical usage type.')
                    };
                    $values['usage_id'] = (string) $this->mapped('pos', $usageTable, $values['usage_id'], $usageTable);
                }
                if (in_array($target, ['products', 'stock_units', 'product_listings'], true)) {
                    $values['public_id'] = (string) Str::uuid();
                }
                $id = DB::table($target)->insertGetId($values);
                DB::table('migration_identity_map')->insert([...$identity, 'target_id' => (string) $id, 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'imported']);
                if ($target === 'product_listings') {
                    DB::table('migration_identity_map')->insert([...$identity, 'target_table' => 'products', 'target_id' => (string) $values['product_id'], 'run_id' => $runId, 'row_digest' => $digest, 'outcome' => 'verified_source_link']);
                }

                return ['outcome' => 'imported', 'target_table' => $target, 'target_id' => $id];
            }, 3);
        } catch (QueryException|InvalidArgumentException|\JsonException|ValidationException $error) {
            $reason = $error instanceof QueryException ? 'Target identity collision or relationship constraint.' : $error->getMessage();
            DB::table('migration_quarantine')->insert(['run_id' => $runId, 'source_repository' => $source, 'source_table' => $table,
                'source_primary_key' => (string) ($row['id'] ?? 'unknown'), 'reason' => $reason, 'evidence_reference' => 'sha256:'.$digest]);

            return ['outcome' => 'quarantined', 'reason' => $reason];
        }
    }

    private function mapped(string $source, string $table, mixed $id, string $target): int
    {
        if ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id)) {
            throw new InvalidArgumentException('Invalid parent source identity.');
        }
        $mapped = DB::table('migration_identity_map')->where(['source_repository' => $source, 'source_table' => $table,
            'source_primary_key' => (string) $id, 'target_table' => $target])->value('target_id');
        if (! $mapped || ! DB::table($target)->where('id', $mapped)->exists()) {
            throw new InvalidArgumentException('Unresolved source relationship; import its parent first.');
        }

        return (int) $mapped;
    }

    private function mappedAdmin(string $sourceTable, mixed $id): int
    {
        if ((! is_string($id) && ! is_int($id)) || ! ctype_digit((string) $id)) {
            throw new InvalidArgumentException('Invalid legacy Admin identity.');
        }
        $mapped = DB::table('admin_identity_mappings')->where(['source_repository' => 'pos', 'source_table' => $sourceTable,
            'source_primary_key' => (string) $id])->value('admin_id');
        if (! $mapped || ! DB::table('admins')->where('id', $mapped)->exists()) {
            throw new InvalidArgumentException('Legacy administrative actor requires explicit verified Admin mapping.');
        }

        return (int) $mapped;
    }

    private function optionReferences(array $values, array $fields): void
    {
        foreach ($fields as $field => [$list, $raw]) {
            if ($values[$field] && PosMasterDataOption::findOrFail($values[$field])->list_key !== $list) {
                throw new InvalidArgumentException('Master-data relationship belongs to the wrong list.');
            }
        }
    }

    private function option(array $values): void
    {
        if (! array_key_exists($values['list_key'], PosMasterDataRegistry::LISTS) || ! preg_match('/\A[a-z][a-z0-9]*(?:_[a-z0-9]+)*\z/', $values['code'])
            || $values['sort_order'] > 100000 || trim($values['label']) === '' || strip_tags($values['label']) !== $values['label'] || preg_match('/[\x00-\x1F\x7F]/u', $values['label'])) {
            throw new InvalidArgumentException('Invalid managed option contract.');
        }
        if ($values['list_key'] === 'product_category' && (! array_key_exists($values['code'], Product::CATEGORY_LABELS) || $values['archived_at'] !== null)) {
            throw new InvalidArgumentException('Protected category cannot be invented or archived.');
        }
        $meta = json_decode($values['metadata'] ?? '{}', true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($meta)) {
            throw new InvalidArgumentException('Managed metadata must be an object.');
        }
        $valid = match ($values['list_key']) {
            'device_ram_gb', 'device_storage_gb' => is_int($meta['value'] ?? null) && $meta['value'] >= 1 && $meta['value'] <= ($values['list_key'] === 'device_ram_gb' ? 2048 : 8192),
            'unit_color' => is_string($meta['hex'] ?? null) && preg_match('/\A#[0-9a-fA-F]{6}\z/', $meta['hex']),
            'device_sim_configuration' => in_array($meta['imei_slots'] ?? null, [1, 2], true),
            'product_subcategory' => array_key_exists($meta['parent_category_code'] ?? '', Product::CATEGORY_LABELS),
            'acquisition_source_type' => in_array($meta['party_kind'] ?? null, ['business', 'individual'], true),
            default => true,
        };
        if (! $valid) {
            throw new InvalidArgumentException('Invalid managed option metadata.');
        }
    }
}
