<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class H01WebsiteAuditBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_H01_WEBSITE_AUDIT_E2E_ENABLED') === '1', 403);
        $receipt = base_path('../.local/h01-website-audit-browser-receipt.json');
        $mode = getenv('MT75_H01_WEBSITE_AUDIT_FIXTURE_ACTION');
        abort_unless(in_array($mode, ['seed', 'cleanup'], true), 403);
        $owner = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->firstOrFail();
        if ($mode === 'seed') {
            abort_unless(! is_file($receipt)
                && DB::table('identity_audit_events')->where('action', 'website_h01_browser_event')->doesntExist(), 409);
            $id = DB::table('identity_audit_events')->insertGetId([
                'realm' => 'admin', 'account_id' => $owner->id, 'action' => 'website_h01_browser_event',
                'reference' => 'H01-SYNTHETIC-PRIVATE-NOT-IN-UI',
                'actor_name_snapshot' => $owner->name, 'actor_role_snapshot' => 'H01 synthetic', 'created_at' => now(),
            ]);
            file_put_contents($receipt, json_encode(['id' => $id, 'actor' => $owner->id], JSON_THROW_ON_ERROR));
            $this->command?->info('H01 Website audit isolated browser event seeded.');

            return;
        }
        abort_unless(is_file($receipt), 409);
        $row = json_decode(file_get_contents($receipt), true, flags: JSON_THROW_ON_ERROR);
        abort_unless((int) $row['actor'] === $owner->id, 409);
        $record = DB::table('identity_audit_events')->where('id', $row['id'])->firstOrFail();
        abort_unless($record->realm === 'admin' && (int) $record->account_id === $owner->id
            && $record->action === 'website_h01_browser_event' && $record->reference === 'H01-SYNTHETIC-PRIVATE-NOT-IN-UI', 409);
        DB::table('identity_audit_events')->where('id', $row['id'])->delete();
        unlink($receipt);
        $this->command?->info('H01 Website audit exact synthetic event cleaned.');
    }
}
