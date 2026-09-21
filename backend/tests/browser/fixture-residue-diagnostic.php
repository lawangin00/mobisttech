<?php

// CI-only browser teardown diagnostic: print table names/counts and safe identity-event classes, never row values.
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

// Identify the fixture owner without logging account IDs, addresses, event payloads, or other row data.
// The final schema gate remains authoritative; this diagnostic must never delete or mask residue.
foreach (DB::table('identity_audit_events')->get(['realm', 'action', 'account_id']) as $event) {
    $realm = in_array($event->realm, ['admin', 'customer'], true) ? $event->realm : 'other';
    $action = is_string($event->action)
        && preg_match('/\A[a-z][a-z0-9_.-]{0,79}\z/D', $event->action) === 1 ? $event->action : 'other';
    $accountClass = 'null-account';
    if ($event->account_id !== null) {
        $accountTable = $realm === 'admin' ? 'admins' : ($realm === 'customer' ? 'users' : null);
        if ($accountTable === null) {
            $accountClass = 'unclassified-account';
        } else {
            $account = DB::table($accountTable)->where('id', $event->account_id)->first(['email']);
            $accountClass = ! $account ? 'missing-account'
                : (is_string($account->email) && preg_match('/\A(?:e2e-|mt75-)[^@]+@example\.invalid\z/D', $account->email) === 1
                    ? 'live-synthetic-account' : 'live-other-account');
        }
    }
    echo 'CI_IDENTITY_AUDIT_RESIDUAL_CLASS='.$realm.':'.$action.':'.$accountClass.PHP_EOL;
}
