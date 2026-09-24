<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

final class W07CredentialE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        DB::transaction(function (): void {
            $owner = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->value('id');
            $key = 'payments.card.api_secret';
            $row = DB::table('site_secret_settings')->where('key', $key)->lockForUpdate()->first();
            $events = $owner ? DB::table('identity_audit_events')->where('realm', 'admin')->where('account_id', $owner)
                ->where('action', 'website_credential_replaced')->where('reference', $key)->lockForUpdate()->get() : collect();
            if (! $row) {
                abort_unless($events->isEmpty(), 409, 'Unexpected W07 credential audit without fixture.');

                return;
            }
            abort_unless($owner && (int) $row->version === 1 && $row->updated_by_user_id === null
                && Crypt::decryptString($row->ciphertext) === 'mt75-w07-browser-envelope'
                && $events->count() === 1, 409, 'W07 credential fixture ownership or value mismatch.');
            DB::table('site_secret_settings')->where('id', $row->id)->delete();
            DB::table('identity_audit_events')->where('id', $events[0]->id)->delete();
        });
    }
}
