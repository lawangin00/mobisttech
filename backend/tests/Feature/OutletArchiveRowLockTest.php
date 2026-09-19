<?php

namespace Tests\Feature;

use App\Models\Outlet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class OutletArchiveRowLockTest extends TestCase
{
    public function test_two_connections_serialize_outlet_archive_lock_and_reobserve_archived_state(): void
    {
        // Intentionally no DatabaseTransactions: second MySQL connection must see the committed fixture.
        abort_unless(DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) config('database.connections.mysql.port') === 13306, 403,
            'Archive concurrency probe is isolated-test-database only.');
        $id = (string) Str::uuid();
        $outlet = new Outlet;
        $outlet->forceFill(['public_id' => $id, 'name' => 'D03 isolated row-lock probe',
            'status' => false, 'version' => 1])->save();
        config(['database.connections.d03_archive_probe' => config('database.connections.mysql')]);
        $other = DB::connection('d03_archive_probe');
        try {
            $other->statement('SET SESSION innodb_lock_wait_timeout = 1');
            DB::beginTransaction();
            try {
                $first = DB::table('outlets')->where('id', $outlet->id)->lockForUpdate()->firstOrFail();
                $this->assertNull($first->archived_at);
                try {
                    $other->transaction(fn () => $other->table('outlets')
                        ->where('id', $outlet->id)->lockForUpdate()->firstOrFail());
                    $this->fail('Independent writer unexpectedly bypassed the outlet archive row lock.');
                } catch (QueryException $exception) {
                    $this->assertSame(1205, (int) ($exception->errorInfo[1] ?? 0));
                }
                // The same row locked by OutletLifecycleAdministration::archive is committed archived.
                DB::table('outlets')->where('id', $outlet->id)
                    ->update(['archived_at' => now(), 'version' => 2]);
                DB::commit();
            } finally {
                if (DB::transactionLevel() > 0) { DB::rollBack(); }
            }
            $observed = $other->transaction(fn () => $other->table('outlets')
                ->where('id', $outlet->id)->lockForUpdate()->firstOrFail());
            $this->assertNotNull($observed->archived_at);
            $this->assertSame(2, (int) $observed->version);
        } finally {
            $other->disconnect();
            DB::purge('d03_archive_probe');
            DB::table('outlets')->where('public_id', $id)
                ->where('name', 'D03 isolated row-lock probe')->delete();
        }
    }
}
