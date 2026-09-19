<?php

namespace Database\Seeders;

use App\Migration\ProductImporter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class PosMasterDataImportedE2eSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test', 403);
        DB::transaction(function () {
            $outlet = DB::table('outlets')->where('outlet_code', '908')->where('name', 'MT75 P02 Variant Outlet')->first();
            $category = DB::table('pos_master_data_options')->where('list_key', 'product_category')->where('code', 'mobile_phone')->first();
            abort_unless($outlet && $category && ! DB::table('products')->where('name', 'MT75 P02 Imported Phone')->exists(), 409);
            $run = DB::table('migration_runs')->insertGetId([
                'input_manifest_hash' => str_repeat('e', 64), 'code_hash' => str_repeat('f', 64),
                'schema_hash' => str_repeat('a', 64), 'target_identity' => 'mobisttech_test',
                'status' => 'product_rehearsal', 'started_at' => now(),
            ]);
            foreach ([['users', '808', 'outlets', $outlet->id], ['pos_master_data_options', '811', 'pos_master_data_options', $category->id]] as [$source, $key, $target, $id]) {
                DB::table('migration_identity_map')->insert(['source_repository' => 'pos', 'source_table' => $source, 'source_primary_key' => $key,
                    'target_table' => $target, 'target_id' => (string) $id, 'run_id' => $run, 'row_digest' => str_repeat('e', 64), 'outcome' => 'imported']);
            }
            $importer = app(ProductImporter::class);
            $brand = $importer->import($run, 'pos', 'pos_master_data_options', $this->sourceRow('pos_master_data_options', [
                'id' => 802, 'list_key' => 'product_brand', 'code' => 'mt75_p02_imported_brand',
                'label' => 'MT75 P02 Imported Brand', 'is_active' => true, 'sort_order' => 12,
            ]), 'UTC');
            abort_unless($brand['outcome'] === 'imported', 409, 'Synthetic source brand import must succeed.');
            $product = $this->sourceRow('products', [
                'id' => 807, 'shop_id' => 808, 'name' => 'MT75 P02 Imported Phone',
                'product_code' => 'MST-MOB-908-000807', 'category' => 'mobile_phone',
                'category_master_data_id' => 811, 'brand_master_data_id' => 802,
                'brand' => 'MT75 Original Imported Brand', 'model' => 'Imported Model I1',
                'price' => '250.02', 'purchase_price' => '110.01', 'sale_price' => '250.02',
                'qty' => 0, 'sold_qty' => 0, 'track_imei' => true,
                'warranty_type' => 'no_warranty',
            ]);
            $result = $importer->import($run, 'pos', 'products', $product, 'UTC');
            abort_unless($result['outcome'] === 'imported', 409, 'Synthetic source product import must succeed.');
        });
    }

    private function sourceRow(string $table, array $values): array
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === 'pos' && $entry['table'] === $table);
        abort_unless($entry, 409);
        $row = [];
        foreach ($entry['columns'] as $column) {
            $type = $column['target_type'];
            $row[$column['source_column']] = $type['nullable'] ? null : match ($type['type']) {
                'boolean' => false, 'integer', 'bigInteger', 'smallInteger' => 1,
                'decimal' => '0.00', default => 'Synthetic',
            };
        }
        return [...$row, ...$values];
    }
}
