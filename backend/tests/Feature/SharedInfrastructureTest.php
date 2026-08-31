<?php

namespace Tests\Feature;

use App\Infrastructure\PrivateObjects;
use App\Infrastructure\VersionedCache;
use Aws\Command;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use League\Flysystem\UnableToWriteFile;
use LogicException;
use Tests\Fixtures\InfrastructureJob;
use Tests\TestCase;

class SharedInfrastructureTest extends TestCase
{
    public function test_database_queue_dispatches_after_commit_and_discards_rolled_back_jobs(): void
    {
        $queue = 'mt21-'.Str::uuid();
        $domain = 'mt21-'.Str::uuid();
        try {
            DB::beginTransaction();
            Queue::connection('database')->pushOn($queue, new InfrastructureJob($domain));
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
            DB::rollBack();
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
            DB::beginTransaction();
            Queue::connection('database')->pushOn($queue, new InfrastructureJob($domain));
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
            DB::commit();
            $this->assertSame(1, DB::table('jobs')->where('queue', $queue)->count());
            $this->artisan('queue:work', ['connection' => 'database', '--queue' => $queue, '--once' => true, '--sleep' => 0, '--tries' => 1])->assertSuccessful();
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
            $this->assertSame(1, DB::table('publication_versions')->where('domain', $domain)->value('version'));
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::table('jobs')->where('queue', $queue)->delete();
            DB::table('publication_versions')->where('domain', $domain)->delete();
        }
    }

    public function test_worker_failure_retries_then_preserves_terminal_failure_evidence(): void
    {
        $queue = 'mt21-'.Str::uuid();
        $domain = 'mt21-'.Str::uuid();
        try {
            Queue::connection('database')->pushOn($queue, new InfrastructureJob($domain, true));
            $options = ['connection' => 'database', '--queue' => $queue, '--once' => true, '--sleep' => 0, '--tries' => 2, '--backoff' => 0];
            $this->artisan('queue:work', $options)->assertSuccessful();
            $this->assertSame(1, DB::table('jobs')->where('queue', $queue)->value('attempts'));
            $this->assertSame(0, DB::table('failed_jobs')->where('queue', $queue)->count());
            $this->artisan('queue:work', $options)->assertSuccessful();
            $this->assertSame(0, DB::table('jobs')->where('queue', $queue)->count());
            $this->assertSame(1, DB::table('failed_jobs')->where('queue', $queue)->count());
            $this->assertFalse(DB::table('publication_versions')->where('domain', $domain)->exists());
        } finally {
            DB::table('jobs')->where('queue', $queue)->delete();
            DB::table('failed_jobs')->where('queue', $queue)->delete();
        }
    }

    public function test_redis_outage_uses_master_without_replaying_callback(): void
    {
        $domain = 'mt21-'.Str::uuid();
        config(['infrastructure.derived_cache_store' => 'redis']);
        // Reserve a loopback port without accepting connections; no remote service or source runtime is used.
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error, STREAM_SERVER_BIND);
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        config(['database.redis.cache.port' => (int) substr(strrchr($address, ':'), 1)]);
        app('redis')->purge('cache');
        $calls = 0;
        try {
            DB::table('publication_versions')->insert(['domain' => $domain, 'version' => 3]);
            $value = app(VersionedCache::class)->remember($domain, 'one', function () use (&$calls) {
                $calls++;

                return ['price' => '12.34'];
            });
            $this->assertSame(['price' => '12.34'], $value);
            $this->assertSame(1, $calls);
        } finally {
            fclose($socket);
            app('redis')->purge('cache');
            DB::table('publication_versions')->where('domain', $domain)->delete();
        }
    }

    public function test_master_version_change_bypasses_old_cache_and_rollback_preserves_version(): void
    {
        $domain = 'mt21-'.Str::uuid();
        config(['infrastructure.derived_cache_store' => 'array']);
        try {
            DB::table('publication_versions')->insert(['domain' => $domain, 'version' => 1]);
            $cache = app(VersionedCache::class);
            $this->assertSame('old', $cache->remember($domain, 'one', fn () => 'old'));
            $this->assertSame('old', $cache->remember($domain, 'one', fn () => 'unused'));
            DB::beginTransaction();
            DB::table('publication_versions')->where('domain', $domain)->increment('version');
            DB::rollBack();
            $this->assertSame('old', $cache->remember($domain, 'one', fn () => 'unused'));
            DB::table('publication_versions')->where('domain', $domain)->increment('version');
            $this->assertSame('new', $cache->remember($domain, 'one', fn () => 'new'));
        } finally {
            DB::table('publication_versions')->where('domain', $domain)->delete();
            Cache::store('array')->flush();
        }
    }

    public function test_private_object_roundtrip_stays_outside_public_storage(): void
    {
        $key = 'acquisitions/'.Str::uuid().'.json';
        $payload = '{"fixture":"synthetic only"}';
        try {
            app(PrivateObjects::class)->put($key, $payload, hash('sha256', $payload));
            $this->assertSame($payload, app(PrivateObjects::class)->get($key));
            $this->assertFalse(Storage::disk('public')->exists($key));
            $this->get('/storage/'.$key)->assertNotFound();
            try {
                app(PrivateObjects::class)->put($key, 'replacement', hash('sha256', 'replacement'));
                $this->fail('Private object overwrite accepted.');
            } catch (LogicException) {
                $this->assertSame($payload, app(PrivateObjects::class)->get($key));
            }
        } finally {
            Storage::disk('local')->delete($key);
        }
    }

    public function test_private_object_rejects_paths_and_bad_digests_before_writing(): void
    {
        foreach (['../secret.json', 'C:\\mobiST\\file.jpg', 'media/../../file.jpg', 'https://example.com/a.jpg', 'media/'.Str::uuid().'.php'] as $key) {
            try {
                app(PrivateObjects::class)->put($key, 'synthetic', hash('sha256', 'synthetic'));
                $this->fail('Unsafe object key accepted.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
        $this->expectException(InvalidArgumentException::class);
        app(PrivateObjects::class)->put('media/'.Str::uuid().'.json', 'synthetic', str_repeat('0', 64));
    }

    public function test_s3_network_access_is_disabled_in_isolated_application(): void
    {
        config(['infrastructure.private_disk' => 's3']);
        $this->expectException(LogicException::class);
        app(PrivateObjects::class)->get('media/'.Str::uuid().'.json');
    }

    public function test_real_s3_adapter_scopes_private_requests_and_propagates_failures_without_network(): void
    {
        $handler = new MockHandler;
        $handler->append(new Result(['ETag' => 'synthetic']));
        $disk = Storage::build([
            ...config('filesystems.disks.s3'), 'key' => 'synthetic-key', 'secret' => 'synthetic-secret',
            'region' => 'us-east-1', 'bucket' => 'mobisttech-test', 'endpoint' => 'http://127.0.0.1:19000',
            'root' => 'mobisttech/test/private', 'use_path_style_endpoint' => true, 'handler' => $handler,
        ]);
        $this->assertTrue($disk->put('media/synthetic.json', '{}', ['visibility' => 'private']));
        $command = $handler->getLastCommand();
        $this->assertSame('mobisttech-test', $command['Bucket']);
        $this->assertSame('mobisttech/test/private/media/synthetic.json', $command['Key']);
        $this->assertSame('private', $command['ACL']);
        $handler->append(new AwsException('Synthetic service unavailable', new Command('PutObject'), ['code' => 'ServiceUnavailable']));
        $this->expectException(UnableToWriteFile::class);
        $disk->put('media/failure.json', '{}', ['visibility' => 'private']);
    }

    public function test_website_configuration_has_no_database_or_object_store_credentials(): void
    {
        $example = file_get_contents(base_path('../website/.env.example'));
        $this->assertDoesNotMatchRegularExpression('/(?:DB_|DATABASE_URL|MYSQL_|REDIS_|AWS_|S3_)/', $example);
        $package = json_decode(file_get_contents(base_path('../website/package.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach (['mysql', 'mysql2', 'pg', '@prisma/client', 'aws-sdk', 'ioredis'] as $dependency) {
            $this->assertArrayNotHasKey($dependency, $package['dependencies']);
        }
    }
}
