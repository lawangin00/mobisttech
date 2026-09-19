<?php

// Synthetic target-only DDL rehearsal. Never runs against a runtime or source database.
$root = dirname(__DIR__, 2);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

$identity = DB::selectOne('SELECT DATABASE() db, @@port port');
if (! $app->environment('testing') || $identity->db !== 'mobisttech_test' || (int) $identity->port !== 13306) {
    throw new RuntimeException('Exact disposable target schema required.');
}
$extensions = ['acquisition_source_references', 'inventory_custody_holds', 'monetary_adjustments', 'order_item_milestones', 'project_milestone_identities', 'stock_unit_lineage', 'website_operating_profiles'];
$all = array_column(Schema::getTables(schema: $identity->db), 'name');
if (count($all) !== 80 || array_diff($extensions, $all)) {
    throw new RuntimeException('Expected applied MT-2.8 schema.');
}
$runtime = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations'];
foreach (array_diff($all, $runtime) as $table) {
    if (DB::table($table)->exists()) {
        throw new RuntimeException('Refusing rehearsal over existing business rows: '.$table);
    }
}
function definitions(array $names): array
{
    sort($names);
    $result = [];
    foreach ($names as $table) {
        $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
        $result[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create['Create Table']);
    }

    return $result;
}
$original = array_values(array_diff($all, $extensions));
$before = definitions($original);
$extensionBefore = definitions($extensions);
$migration = require $root.'/backend/database/migrations/2026_09_01_100000_add_addendum_foundations.php';
$outlet = $product = null;
try {
    $outlet = DB::table('outlets')->insertGetId(['name' => 'MT28 synthetic lifecycle', 'outlet_code' => '928', 'public_id' => (string) Str::uuid()]);
    $product = DB::table('products')->insertGetId(['name' => 'MT28 synthetic preserved row', 'outlet_id' => $outlet, 'price' => '123.45', 'public_id' => (string) Str::uuid()]);
    $row = (array) DB::table('products')->where('id', $product)->first();
    $migration->down();
    if (count(Schema::getTables(schema: $identity->db)) !== 73 || definitions($original) !== $before) {
        throw new RuntimeException('Rollback changed the original schema.');
    }
    $migration->up();
    if (definitions($original) !== $before || definitions($extensions) !== $extensionBefore || (array) DB::table('products')->where('id', $product)->first() !== $row) {
        throw new RuntimeException('Apply changed original definitions/data or extension shape.');
    }
    // All emptiness checks must precede any drop. Nonempty extensions refuse destructive rollback.
    DB::beginTransaction();
    try {
        $revision = DB::table('site_configuration_revisions')->insertGetId(['domain' => 'website.mode', 'version' => 1, 'state' => 'published', 'snapshot' => '{"mode":"hybrid"}']);
        DB::table('website_operating_profiles')->insert(['id' => 1, 'mode' => 'hybrid', 'version' => 1, 'revision_id' => $revision, 'published_at' => now()]);
        $blocked = false;
        try {
            $migration->down();
        } catch (RuntimeException $e) {
            $blocked = str_contains($e->getMessage(), 'empty extension schema');
        }
        if (! $blocked || definitions($extensions) !== $extensionBefore) {
            throw new RuntimeException('Unsafe partial rollback or missing data guard.');
        }
    } finally {
        DB::rollBack();
    }
    $migration->down();
    $migration->up();
    if (definitions($original) !== $before || definitions($extensions) !== $extensionBefore || (array) DB::table('products')->where('id', $product)->first() !== $row) {
        throw new RuntimeException('Reapply was not reproducible.');
    }
    echo json_encode(['result' => 'PASS', 'database' => $identity->db, 'port' => (int) $identity->port, 'original_tables_unchanged' => count($original),
        'extension_tables' => count($extensions), 'rollback_reapply_cycles' => 2, 'synthetic_original_row_preserved' => true, 'nonempty_rollback_refused_before_any_drop' => true,
        'original_definitions_sha256' => hash('sha256', json_encode($before, JSON_UNESCAPED_SLASHES)),
        'extension_definitions_sha256' => hash('sha256', json_encode($extensionBefore, JSON_UNESCAPED_SLASHES))], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
} finally {
    if ($product !== null) {
        DB::table('products')->where('id', $product)->where('name', 'MT28 synthetic preserved row')->delete();
    }
    if ($outlet !== null) {
        DB::table('outlets')->where('id', $outlet)->where('name', 'MT28 synthetic lifecycle')->delete();
    }
}
