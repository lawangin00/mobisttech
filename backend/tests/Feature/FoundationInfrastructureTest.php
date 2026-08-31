<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FoundationInfrastructureTest extends TestCase
{
    public function test_runtime_tables_use_the_isolated_mysql_test_database(): void
    {
        $connection = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, VERSION() AS version');
        $this->assertSame('mobisttech_test', $connection->db);
        $this->assertSame(13306, (int) $connection->port);
        $this->assertStringStartsWith('8.4.', $connection->version);
        foreach (['sessions', 'jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'] as $table) {
            $this->assertTrue(Schema::hasTable($table));
        }
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('products'));
    }

    public function test_foundation_request_persists_a_database_session(): void
    {
        config(['session.driver' => 'database']);
        $this->withoutVite()->get('/')->assertOk();
        $id = app('session')->getId();
        try {
            $this->assertTrue(DB::table('sessions')->where('id', $id)->exists());
            $this->assertNull(DB::table('sessions')->where('id', $id)->value('user_id'));
        } finally {
            DB::table('sessions')->where('id', $id)->delete();
        }
    }
}
