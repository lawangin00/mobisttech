<?php

// CI-only browser teardown diagnostic: print table names/counts, never row values.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$backend = dirname(__DIR__, 2);
require $backend.'/vendor/autoload.php';
$app = require $backend.'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
$db = DB::selectOne('SELECT DATABASE() AS name, @@port AS port');
if (getenv('CI') !== 'true' || ! $app->environment('testing') || $db->name !== 'mobisttech_test' || (int) $db->port !== 13306) {
    throw new RuntimeException('Disposable CI test database is required for fixture residue diagnostic.');
}
$canonical = ['cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions', 'migrations',
    'business_profiles', 'integration_connections', 'permission_definitions', 'roles', 'role_permissions', 'document_template_revisions'];
$residue = [];
foreach (Schema::getFacadeRoot()->getTables(schema: $db->name) as $entry) {
    $table = $entry['name'];
    if (in_array($table, $canonical, true)) {
        continue;
    }
    $count = DB::table($table)->count();
    if ($count !== 0) {
        $residue[] = $table.':'.$count;
    }
}
echo 'CI_DISPOSABLE_RESIDUAL_TABLES='.($residue ? implode(',', $residue) : 'none').PHP_EOL;
