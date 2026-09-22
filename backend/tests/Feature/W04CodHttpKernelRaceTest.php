<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Authenticated HTTP-kernel contention using two independent Laravel/PHP processes. */
final class W04CodHttpKernelRaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
    }

    public function test_two_authenticated_http_workers_create_distinct_drafts_and_publish_once(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mobisttech_test', DB::connection()->getDatabaseName());
        $domain = 'website.payments.cod';
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', $domain)->count());
        $actors = [];
        $jobs = [];
        try {
            for ($i = 0; $i < 2; $i++) {
                $actor = $this->actor();
                $actors[] = $actor;
                $client = $this->client('127.0.0.'.(40 + $i));
                $this->send($client, 'POST', '/internal/admin/auth/login', [
                    'email' => $actor[0]->email, 'password' => 'SyntheticHttpRace123!',
                ])->assertOk();
                $this->send($client, 'POST', '/internal/admin/outlets/select', [
                    'outlet_id' => $actor[1]->public_id,
                ])->assertOk();
                $this->send($client, 'GET', '/internal/admin/website/payment-channels')
                    ->assertOk()->assertJsonPath('data.0.available', true);
                $jobs[] = [
                    'cookies' => $client['cookies'], 'csrf' => $client['tokens']['XSRF-TOKEN-admin'],
                    'ip' => $client['ip'], 'uri' => '/internal/admin/website/payment-settings/drafts',
                    'body' => ['cod_enabled' => $i === 0],
                ];
            }
            $drafts = $this->race($jobs);
            $this->assertSame([201, 201], array_column($drafts, 'status'));
            $versions = array_map('intval', array_column(array_column($drafts, 'data'), 'version'));
            sort($versions);
            $this->assertSame([1, 2], $versions);
            $latest = $drafts[0]['data']['version'] > $drafts[1]['data']['version']
                ? $drafts[0]['data']['id'] : $drafts[1]['data']['id'];
            foreach ($jobs as &$job) {
                $job['uri'] .= '/'.$latest.'/publish';
                $job['body'] = [];
            }
            unset($job);
            $publishes = $this->race($jobs);
            $this->assertSame([200, 409], array_column($publishes, 'status'));
            $this->assertSame(1, DB::table('site_configuration_revisions')
                ->where('domain', $domain)->where('state', 'published')->count());
            $this->assertSame((int) $latest, (int) DB::table('site_configuration_revisions')
                ->where('domain', $domain)->where('state', 'published')->value('id'));
        } finally {
            // A publisher can publish another synthetic Admin's draft. Remove all
            // test-created revisions before deleting either referenced account.
            $ownedIds = array_map(static fn (array $pair): int => $pair[0]->id, $actors);
            DB::table('site_configuration_revisions')->where('domain', $domain)
                ->whereIn('created_by_admin_id', $ownedIds)->delete();
            foreach ($actors as [$admin, $outlet]) {
                DB::table('identity_audit_events')->where('realm', 'admin')
                    ->where('account_id', $admin->id)->delete();
                DB::table('account_sessions')->where('guard', 'admin')
                    ->where('account_id', $admin->id)->delete();
                DB::table('sessions')->where('user_id', $admin->id)->delete();
                $admin->shops()->detach($outlet->id);
                $admin->delete();
                $outlet->delete();
            }
        }
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', $domain)->count());
    }

    private function race(array $jobs): array
    {
        $lock = 'mobisttech.website.payments.cod.revision';
        $this->assertSame(1, (int) DB::selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired);
        $paths = [];
        $workers = [];
        try {
            try {
                foreach ($jobs as $job) {
                    $path = sys_get_temp_dir().'/w04-http-'.Str::uuid().'.json';
                    $job['marker'] = $path.'.ready';
                    file_put_contents($path, json_encode($job, JSON_THROW_ON_ERROR));
                    $paths[] = $path;
                    $worker = new Process([PHP_BINARY, base_path('tests/Support/W04CodHttpRaceWorker.php'),
                        $path], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
                            'DB_DATABASE' => 'mobisttech_test', 'DB_URL' => '', 'SESSION_DRIVER' => 'database']);
                    $worker->setTimeout(20);
                    $worker->start();
                    $workers[] = $worker;
                }
                $deadline = microtime(true) + 6;
                while (count(array_filter(array_map(static fn ($p) => $p.'.ready', $paths), 'is_file')) !== 2
                    && microtime(true) < $deadline) {
                    usleep(25000);
                }
                $this->assertCount(2, array_filter(array_map(static fn ($p) => $p.'.ready', $paths), 'is_file'));
            } finally {
                DB::selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
            }
            $result = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $result[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            usort($result, static fn ($a, $b) => $a['status'] <=> $b['status']);

            return $result;
        } finally {
            foreach ($paths as $path) {
                foreach ([$path, $path.'.ready'] as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }
        }
    }

    private function actor(): array
    {
        $admin = new Admin;
        $admin->forceFill(['name' => 'W04 HTTP race '.Str::uuid(),
            'email' => Str::uuid().'@example.invalid',
            'password' => Hash::make('SyntheticHttpRace123!'),
            'permissions' => ['shops.enter', 'website.payments.manage', 'website.publish'],
            'auth_version' => 1])->save();
        $code = (string) random_int(100, 999);
        while (Outlet::where('outlet_code', $code)->exists()) {
            $code = (string) random_int(100, 999);
        }
        $outlet = new Outlet;
        $outlet->forceFill(['name' => 'W04 HTTP race outlet', 'public_id' => (string) Str::uuid(),
            'outlet_code' => $code])->save();
        $admin->shops()->attach($outlet);

        return [$admin->fresh(), $outlet];
    }

    private function client(string $ip): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'ip' => $ip];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(array &$client, string $method, string $uri, array $body = [])
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => 'Synthetic HTTP concurrency', 'REMOTE_ADDR' => $client['ip']];
        if (isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($body));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }
}
