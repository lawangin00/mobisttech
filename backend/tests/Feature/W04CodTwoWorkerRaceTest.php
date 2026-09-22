<?php

namespace Tests\Feature;

use App\Commerce\WebsitePaymentAdministration;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/** Two independent Laravel/PHP processes race on a disposable isolated MySQL schema. */
final class W04CodTwoWorkerRaceTest extends TestCase
{
    public function test_two_application_workers_serialize_drafts_and_single_winner_publication(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mobisttech_test', DB::connection()->getDatabaseName());
        $domain = 'website.payments.cod';
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', $domain)->count(),
            'Use the clean disposable COD revision baseline; do not modify an existing policy.');
        $admin = new Admin;
        $admin->forceFill(['name' => 'W04 isolated race '.Str::uuid(),
            'email' => Str::uuid().'@example.invalid', 'password' => Str::random(40),
            'permissions' => ['website.payments.manage', 'website.publish'], 'auth_version' => 1])->save();
        try {
            $drafts = $this->race('draft', $admin->id, 0);
            $this->assertSame([200, 200], array_column($drafts, 'status'));
            $versions = array_map('intval', array_column(array_column($drafts, 'result'), 'version'));
            sort($versions);
            $this->assertSame([1, 2], $versions);
            $this->assertSame(2, DB::table('site_configuration_revisions')->where('domain', $domain)->count());
            $newest = (int) ($drafts[0]['result']['version'] > $drafts[1]['result']['version']
                ? $drafts[0]['result']['id'] : $drafts[1]['result']['id']);
            $publishes = $this->race('publish', $admin->id, $newest);
            $this->assertSame([200, 409], array_column($publishes, 'status'));
            $this->assertSame(1, DB::table('site_configuration_revisions')->where('domain', $domain)
                ->where('state', 'published')->count());
            $this->assertSame($newest, (int) DB::table('site_configuration_revisions')->where('domain', $domain)
                ->where('state', 'published')->value('id'));
            // Race publishing a latest draft against creation of a newer draft.
            $candidate = app(WebsitePaymentAdministration::class)->saveDraft($admin, ['cod_enabled' => true]);
            $mixed = $this->race('draft', $admin->id, (int) $candidate['id'], 'publish');
            $created = array_values(array_filter($mixed, static fn (array $row): bool => $row['action'] === 'draft'));
            $publication = array_values(array_filter($mixed, static fn (array $row): bool => $row['action'] === 'publish'));
            $this->assertCount(1, $created);
            $this->assertCount(1, $publication);
            $this->assertSame(200, $created[0]['status']);
            $this->assertContains($publication[0]['status'], [200, 409]);
            $this->assertSame(4, (int) DB::table('site_configuration_revisions')->where('domain', $domain)->max('version'));
            $this->assertSame('draft', DB::table('site_configuration_revisions')->where('id', $created[0]['result']['id'])->value('state'));
            $this->assertSame(1, DB::table('site_configuration_revisions')->where('domain', $domain)->where('state', 'published')->count());
            $this->assertSame($publication[0]['status'] === 200 ? (int) $candidate['id'] : $newest,
                (int) DB::table('site_configuration_revisions')->where('domain', $domain)->where('state', 'published')->value('id'));
        } finally {
            DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $admin->id)->delete();
            DB::table('site_configuration_revisions')->where('domain', $domain)
                ->where('created_by_admin_id', $admin->id)->delete();
            $admin->delete();
        }
        $this->assertSame(0, DB::table('site_configuration_revisions')->where('domain', $domain)->count());
    }

    /** Hold the same named lock until both separate Laravel processes reach the service. */
    private function race(string $action, int $adminId, int $draftId, ?string $otherAction = null): array
    {
        $lock = 'mobisttech.website.payments.cod.revision';
        $connection = DB::connection();
        $this->assertSame(1, (int) $connection->selectOne('SELECT GET_LOCK(?, 0) AS acquired', [$lock])->acquired);
        $markers = [];
        $workers = [];
        try {
            for ($index = 0; $index < 2; $index++) {
                $marker = sys_get_temp_dir().'/w04-race-'.Str::uuid().'.ready';
                $markers[] = $marker;
                $worker = new Process([PHP_BINARY, base_path('tests/Support/W04CodRaceWorker.php'),
                    $index === 1 && $otherAction ? $otherAction : $action, (string) $adminId, (string) $draftId, $marker], base_path(), [
                        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql',
                        'DB_DATABASE' => 'mobisttech_test', 'DB_URL' => '',
                    ]);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 6;
            while (count(array_filter($markers, 'is_file')) !== 2 && microtime(true) < $deadline) {
                usleep(25000);
            }
            $this->assertCount(2, array_filter($markers, 'is_file'), 'Both independent workers must reach the barrier.');
        } finally {
            $connection->selectOne('SELECT RELEASE_LOCK(?) AS released', [$lock]);
        }
        try {
            $results = [];
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput());
                $result = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                $this->assertContains($result['status'], [200, 409]);
                $results[] = $result;
            }
            usort($results, static fn (array $a, array $b): int => $a['status'] <=> $b['status']);

            return $results;
        } finally {
            foreach ($markers as $marker) {
                if (is_file($marker)) {
                    unlink($marker);
                }
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop(1);
                }
            }
        }
    }
}
