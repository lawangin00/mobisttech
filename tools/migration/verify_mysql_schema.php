<?php

// Inspect only the disposable target test schema. No DDL, row data or credentials are emitted.
$root = dirname(__DIR__, 2);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): void {
    fwrite(STDERR, 'FAIL: '.$error->getMessage().PHP_EOL);
    exit(1);
});
$db = Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@session.time_zone AS timezone, @@session.sql_mode AS sql_mode, VERSION() AS version');
if ($db->db !== 'mobisttech_test' || (int) $db->port !== 13306 || ! $app->environment('testing')) {
    throw new RuntimeException('Exact disposable target test schema required.');
}
$mode = $argv[1] ?? '';
if (! in_array($mode, ['addendum', 'identity', 'shared', 'runtime'], true)) {
    throw new RuntimeException('Expected addendum, identity, shared or runtime verification mode.');
}
$schema = Illuminate\Support\Facades\Schema::getFacadeRoot();
$spec = json_decode(file_get_contents($root.'/docs/schema/TARGET_SCHEMA.json'), true, flags: JSON_THROW_ON_ERROR);
$runtime = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations'];
$expected = $mode !== 'runtime' ? array_merge($runtime, array_keys($spec['tables'])) : $runtime;
if (in_array($mode, ['identity', 'addendum'], true)) {
    $expected = array_merge($expected, ['admin_password_reset_tokens', 'super_admin_password_reset_tokens', 'site_admin_password_reset_tokens', 'identity_audit_events']);
}
if ($mode === 'addendum') {
    $expected = array_merge($expected, ['website_operating_profiles', 'stock_unit_lineage', 'inventory_custody_holds', 'acquisition_source_references', 'monetary_adjustments', 'project_milestone_identities', 'order_item_milestones']);
}
$actual = array_column($schema->getTables(schema: $db->db), 'name');
sort($expected);
sort($actual);
if ($expected !== $actual) {
    throw new RuntimeException('MySQL table set differs from expected checkpoint: expected '.count($expected).', actual '.count($actual).'.');
}
$definitions = [];
$counts = ['tables' => count($actual), 'columns' => 0, 'foreign_keys' => 0, 'indexes' => 0];
foreach ($actual as $table) {
    $columns = $schema->getColumns($table);
    $counts['columns'] += count($columns);
    $counts['foreign_keys'] += count($schema->getForeignKeys($table));
    $counts['indexes'] += count($schema->getIndexes($table));
    $create = (array) Illuminate\Support\Facades\DB::selectOne('SHOW CREATE TABLE `'.$table.'`');
    $definitions[$table] = preg_replace('/ AUTO_INCREMENT=\d+/', '', $create['Create Table']);
    if (! str_contains($definitions[$table], 'ENGINE=InnoDB')) {
        throw new RuntimeException('Non-transactional table found.');
    }
    if (! in_array($table, $runtime, true) && Illuminate\Support\Facades\DB::table($table)->count() !== 0) {
        throw new RuntimeException('Unexpected business rows remain in disposable schema.');
    }
}
if (! str_contains($db->sql_mode, 'STRICT_TRANS_TABLES') || $db->timezone !== '+00:00') {
    throw new RuntimeException('Strict SQL and UTC session are required.');
}
echo json_encode(['mode' => $mode, 'result' => 'PASS', 'database' => $db->db, 'port' => (int) $db->port,
    'mysql' => $db->version, 'timezone' => $db->timezone, 'strict' => true, 'counts' => $counts,
    'schema_sha256' => hash('sha256', json_encode($definitions, JSON_UNESCAPED_SLASHES)),
    'business_rows' => 0], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
