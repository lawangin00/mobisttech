<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class SharedSchemaTest extends TestCase
{
    use DatabaseTransactions;

    public function test_every_manifest_column_and_foreign_key_exists_on_mysql(): void
    {
        $spec = json_decode(file_get_contents(base_path('../docs/schema/TARGET_SCHEMA.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($spec['tables'] as $name => $table) {
            $columns = collect(Schema::getColumns($name))->keyBy('name');
            $identityColumns = match ($name) {
                'users', 'admins', 'super_admins' => ['public_id', 'auth_version'],
                'account_sessions' => ['revoked_at'],
                default => [],
            };
            $expectedColumns = match ($name) {
                'sales' => ['id', 'public_id', 'version', 'product_id', 'outlet_id', 'sale_date', 'sale_price', 'invoice_id', 'quantity', 'returned_quantity',
                    'total_price', 'created_at', 'updated_at', 'purchase_price', 'discount_allocated', 'net_total_price', 'profit', 'invoice_detail_options', 'invoice_detail_snapshot'],
                'return_lines' => ['id', 'public_id', 'return_id', 'invoice_id', 'sale_id', 'stock_unit_id', 'successor_stock_unit_id', 'quantity', 'unit_price',
                    'discount_amount', 'net_amount', 'purchase_amount', 'currency', 'sale_snapshot', 'snapshot_sha256', 'condition', 'disposition', 'accepted_at'],
                default => [...array_column($table['columns'], 'name'), ...$identityColumns],
            };
            $this->assertSame($expectedColumns, $columns->keys()->all(), $name);
            foreach ($table['columns'] as $c) {
                $actual = $columns[$c['name']];
                $expectedType = match ($c['type']) {
                    'string' => 'varchar('.($c['length'] ?? 255).')',
                    'char' => 'char('.($c['length'] ?? 255).')',
                    'uuid' => 'char(36)',
                    'bigInteger' => 'bigint'.(($c['unsigned'] ?? false) ? ' unsigned' : ''),
                    'integer' => 'int'.(($c['unsigned'] ?? false) ? ' unsigned' : ''),
                    'smallInteger' => 'smallint'.(($c['unsigned'] ?? false) ? ' unsigned' : ''),
                    'tinyInteger' => 'tinyint'.(($c['unsigned'] ?? false) ? ' unsigned' : ''),
                    'boolean' => 'tinyint(1)',
                    'dateTime' => 'datetime(6)',
                    'decimal' => 'decimal('.$c['total'].','.$c['places'].')',
                    'enum' => "enum('".implode("','", $c['allowed'])."')",
                    default => strtolower($c['type']),
                };
                $this->assertSame($expectedType, $actual['type'], $name.'.'.$c['name']);
                if (! isset($c['storedAs'])) {
                    $this->assertSame($c['nullable'], $actual['nullable'], $name.'.'.$c['name']);
                }
                if ($c['type'] === 'decimal') {
                    $this->assertSame('decimal('.$c['total'].','.$c['places'].')', $actual['type'], $name.'.'.$c['name']);
                }
                if ($c['type'] === 'dateTime') {
                    $this->assertSame('datetime(6)', $actual['type']);
                }
                if ($c['binary'] ?? false) {
                    $this->assertSame('utf8mb4_bin', $actual['collation']);
                }
            }
            $fks = Schema::getForeignKeys($name);
            $this->assertCount(count($table['foreign_keys']) + ($name === 'return_lines' ? 3 : 0), $fks, $name);
            foreach ($fks as $relation) {
                $this->assertSame('restrict', strtolower($relation['on_delete']), $name);
            }
            foreach ($table['foreign_keys'] as $expected) {
                $actual = collect($fks)->first(fn ($fk) => $fk['columns'] === $expected['columns']);
                $this->assertNotNull($actual, $name);
                $this->assertSame($expected['table'], $actual['foreign_table']);
                $this->assertSame($expected['references'], $actual['foreign_columns']);
            }
            $indexes = Schema::getIndexes($name);
            foreach ($table['indexes'] as $expected) {
                $this->assertTrue(collect($indexes)->contains(fn ($index) => $index['columns'] === $expected['columns']
                    && ($expected['kind'] !== 'unique' || $index['unique'])
                    && ($expected['kind'] !== 'primary' || $index['primary'])), $name.': '.implode(',', $expected['columns']));
            }
        }
    }

    public function test_source_manifest_accounts_for_every_final_column_and_preserves_namespaces(): void
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(58, $manifest['source_tables']);
        $this->assertSame(735, $manifest['source_columns']);
        foreach ($manifest['tables'] as $table) {
            $source = json_decode(file_get_contents(base_path('../docs/schema/source_'.$table['source'].'.json')), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame(array_column($source['tables'][$table['table']]['columns'], 'name'), array_column($table['columns'], 'source_column'));
            foreach ($table['columns'] as $column) {
                [$targetTable, $targetColumn] = explode('.', $column['destination']);
                $this->assertTrue(Schema::hasColumn($targetTable, $targetColumn), $column['destination']);
            }
        }
        foreach (['pos_settings', 'site_settings', 'pos_configuration_revisions', 'site_configuration_revisions', 'legacy_integration_requests', 'legacy_integration_events'] as $name) {
            $this->assertTrue(Schema::hasTable($name));
        }
        $this->assertFalse(Schema::hasColumn('product_listings', 'stock_quantity'));
        $this->assertFalse(Schema::hasColumn('product_listings', 'online_price'));
    }

    public function test_money_keeps_exact_large_values_and_rejects_overflow_and_negative_stock(): void
    {
        $outlet = $this->outlet('001');
        $product = $this->product($outlet);
        DB::table('products')->where('id', $product)->update(['price' => '99999999999999999.99']);
        $this->assertSame('99999999999999999.99', DB::table('products')->where('id', $product)->value('price'));
        $this->rejects(fn () => DB::table('products')->where('id', $product)->update(['price' => '100000000000000000.00']), 1264);
        $this->rejects(fn () => DB::table('products')->where('id', $product)->update(['qty' => -1]), 3819);
        $this->assertSame(0, DB::table('products')->where('id', $product)->value('qty'));
    }

    public function test_cross_outlet_references_and_financial_history_deletion_are_rejected(): void
    {
        $first = $this->outlet('001');
        $second = $this->outlet('002');
        $product = $this->product($first);
        $invoice = DB::table('invoices')->insertGetId(['outlet_id' => $first, 'total_bill' => '25.50', 'final_bill' => '25.50', 'public_id' => (string) Str::uuid()]);
        $sale = ['public_id' => (string) Str::uuid(), 'product_id' => $product, 'outlet_id' => $second, 'sale_date' => '2026-08-31', 'sale_price' => '25.50',
            'invoice_id' => $invoice, 'quantity' => 1, 'total_price' => '25.50', 'net_total_price' => '25.50', 'profit' => '25.50'];
        $this->rejects(fn () => DB::table('sales')->insert($sale), 1452);
        $sale['outlet_id'] = $first;
        DB::table('sales')->insert($sale);
        $this->rejects(fn () => DB::table('invoices')->where('id', $invoice)->delete(), 1451);
        $this->rejects(fn () => DB::table('products')->where('id', $product)->delete(), 1451);
        $this->assertSame('25.50', DB::table('invoices')->where('id', $invoice)->value('final_bill'));
    }

    public function test_active_imei_is_unique_but_historical_reentry_is_allowed(): void
    {
        $p = $this->product($this->outlet('001'));
        $a = DB::table('stock_units')->insertGetId(['product_id' => $p, 'unit_no' => 1, 'public_id' => (string) Str::uuid()]);
        $b = DB::table('stock_units')->insertGetId(['product_id' => $p, 'unit_no' => 2, 'public_id' => (string) Str::uuid()]);
        $imei = '123456789012345';
        DB::table('active_imeis')->insert(['imei' => $imei, 'stock_unit_id' => $a, 'slot_no' => 1]);
        $this->rejects(fn () => DB::table('active_imeis')->insert(['imei' => $imei, 'stock_unit_id' => $b, 'slot_no' => 1]), 1062);
        DB::table('product_imeis')->insert(['product_id' => $p, 'stock_unit_id' => $a, 'imei' => $imei, 'status' => 'sold']);
        DB::table('active_imeis')->where('imei', $imei)->delete();
        DB::table('active_imeis')->insert(['imei' => $imei, 'stock_unit_id' => $b, 'slot_no' => 1]);
        $this->assertSame($b, DB::table('active_imeis')->where('imei', $imei)->value('stock_unit_id'));
        $this->assertSame(1, DB::table('product_imeis')->where('imei', $imei)->count());
    }

    public function test_payment_replay_and_multiple_current_holds_are_database_conflicts(): void
    {
        $order = $this->order('MT21-A');
        $other = $this->order('MT21-B');
        $payment = ['order_id' => $order, 'gateway' => 'cod', 'amount' => '12.34', 'merchant' => 'synthetic', 'mode' => 'test', 'attempt_key' => 'one', 'transaction_reference' => 'REF-A', 'public_id' => (string) Str::uuid()];
        DB::table('payments')->insert($payment);
        $payment['order_id'] = $other;
        $payment['public_id'] = (string) Str::uuid();
        $this->rejects(fn () => DB::table('payments')->insert($payment), 1062);
        $payment['transaction_reference'] = 'ref-a';
        DB::table('payments')->insert($payment); // Binary provider identifiers do not merge case-distinct receipts.
        $outlet = $this->outlet('001');
        $hold = ['order_id' => $order, 'outlet_id' => $outlet, 'website_order_number' => 'MT21-A', 'reservation_reference' => (string) Str::uuid(), 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000', 'state' => 'held_cod'];
        $id = DB::table('reservations')->insertGetId($hold);
        $hold['attempt'] = 2;
        $hold['reservation_reference'] = (string) Str::uuid();
        $this->rejects(fn () => DB::table('reservations')->insert($hold), 1062);
        DB::table('reservations')->where('id', $id)->update(['state' => 'released']);
        DB::table('reservations')->insert($hold);
        $this->assertSame(2, DB::table('reservations')->where('order_id', $order)->count());
    }

    public function test_source_qualified_identity_and_one_canonical_listing_constraints(): void
    {
        $run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('a', 64), 'code_hash' => str_repeat('b', 64), 'schema_hash' => str_repeat('c', 64), 'target_identity' => 'synthetic-test', 'status' => 'testing', 'started_at' => now()]);
        $mapping = ['source_repository' => 'website', 'source_table' => 'products', 'source_primary_key' => '7', 'target_table' => 'products', 'target_id' => '17', 'run_id' => $run, 'row_digest' => str_repeat('d', 64), 'outcome' => 'verified'];
        DB::table('migration_identity_map')->insert($mapping);
        $this->rejects(fn () => DB::table('migration_identity_map')->insert($mapping), 1062);
        $mapping['target_table'] = 'product_listings';
        DB::table('migration_identity_map')->insert($mapping);
        $this->assertSame(2, DB::table('migration_identity_map')->where('run_id', $run)->count());
        $product = $this->product($this->outlet('001'));
        $listing = ['product_id' => $product, 'slug' => 'synthetic-one', 'name' => 'Synthetic', 'public_id' => (string) Str::uuid()];
        DB::table('product_listings')->insert($listing);
        $listing['slug'] = 'synthetic-two';
        $listing['public_id'] = (string) Str::uuid();
        $this->rejects(fn () => DB::table('product_listings')->insert($listing), 1062);
    }

    public function test_active_unit_allocation_is_unique_and_cannot_switch_products(): void
    {
        $outlet = $this->outlet('001');
        $product = $this->product($outlet);
        $order = $this->order('MT21-ALLOC');
        $item = DB::table('order_items')->insertGetId(['order_id' => $order, 'item_type' => 'mobile', 'title' => 'Synthetic', 'product_id' => $product, 'outlet_id' => $outlet]);
        $reservation = DB::table('reservations')->insertGetId(['order_id' => $order, 'outlet_id' => $outlet, 'website_order_number' => 'MT21-ALLOC', 'reservation_reference' => (string) Str::uuid(), 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000']);
        $line = DB::table('reservation_lines')->insertGetId(['reservation_id' => $reservation, 'order_item_id' => $item, 'product_id' => $product, 'outlet_id' => $outlet, 'website_order_item_id' => '1', 'product_external_id' => '1', 'outlet_external_id' => '001', 'variant_key' => 'synthetic', 'quantity' => 1, 'unit_price' => '25.50', 'line_total' => '25.50']);
        $unit = DB::table('stock_units')->insertGetId(['product_id' => $product, 'unit_no' => 1, 'public_id' => (string) Str::uuid()]);
        $allocation = ['reservation_line_id' => $line, 'product_id' => $product, 'stock_unit_id' => $unit];
        $id = DB::table('reservation_allocations')->insertGetId($allocation);
        $this->rejects(fn () => DB::table('reservation_allocations')->insert($allocation), 1062);
        DB::table('reservation_allocations')->where('id', $id)->update(['released_at' => now()]);
        DB::table('reservation_allocations')->insert($allocation);
        $allocation['product_id'] = $this->product($outlet);
        $allocation['stock_unit_id'] = null;
        $this->rejects(fn () => DB::table('reservation_allocations')->insert($allocation), 1452);
    }

    public function test_publication_version_and_durable_event_roll_back_together(): void
    {
        $domain = 'mt21-'.Str::uuid();
        $event = ['id' => (string) Str::uuid(), 'aggregate_type' => 'publication', 'aggregate_id' => $domain, 'aggregate_version' => 2, 'event_type' => 'publication.changed', 'payload' => '{}', 'operation_key' => 'mt21-'.Str::uuid()];
        DB::table('publication_versions')->insert(['domain' => $domain, 'version' => 1]);
        try {
            DB::transaction(function () use ($domain, $event) {
                DB::table('publication_versions')->where('domain', $domain)->increment('version');
                DB::table('domain_events')->insert($event);
                throw new \RuntimeException('Synthetic pre-commit failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic pre-commit failure', $e->getMessage());
        }
        $this->assertSame(1, DB::table('publication_versions')->where('domain', $domain)->value('version'));
        $this->assertFalse(DB::table('domain_events')->where('id', $event['id'])->exists());
        DB::transaction(function () use ($domain, $event) {
            DB::table('publication_versions')->where('domain', $domain)->increment('version');
            DB::table('domain_events')->insert($event);
        });
        $this->assertSame(2, DB::table('publication_versions')->where('domain', $domain)->value('version'));
        $this->assertTrue(DB::table('domain_events')->where('id', $event['id'])->exists());
        $event['id'] = (string) Str::uuid();
        $this->rejects(fn () => DB::table('domain_events')->insert($event), 1062);
    }

    private function outlet(string $code): int
    {
        return DB::table('outlets')->insertGetId(['name' => 'Synthetic outlet', 'outlet_code' => $code, 'public_id' => (string) Str::uuid()]);
    }

    private function product(int $outlet): int
    {
        return DB::table('products')->insertGetId(['name' => 'Synthetic product', 'outlet_id' => $outlet, 'price' => '25.50', 'public_id' => (string) Str::uuid()]);
    }

    private function order(string $number): int
    {
        return DB::table('orders')->insertGetId(['order_number' => $number, 'order_type' => 'mobile', 'customer_name' => 'Synthetic', 'customer_mobile' => '03000000000', 'public_id' => (string) Str::uuid()]);
    }

    private function rejects(callable $operation, int $mysqlCode): void
    {
        try {
            $operation();
            $this->fail('MySQL accepted an invalid state.');
        } catch (QueryException $exception) {
            $this->assertSame($mysqlCode, $exception->errorInfo[1]);
        }
    }
}
