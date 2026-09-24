<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Remove only the COD revisions created by this isolated Playwright acceptance run. */
final class W04CodPolicyE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing')
            && (getenv('CI') === 'true' || getenv('MT75_FIRST_OUTLET_E2E_ENABLED') === '1')
            && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);

        $admin = DB::table('admins')->where('email', 'e2e-protected-owner@example.invalid')->first();
        abort_unless($admin !== null, 409, 'Synthetic COD browser owner is missing.');

        DB::transaction(function () use ($admin): void {
            $revisions = DB::table('site_configuration_revisions')
                ->where('domain', 'website.payments.cod')->lockForUpdate()->get();
            foreach ($revisions as $revision) {
                $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
                if ((int) $revision->created_by_admin_id !== (int) $admin->id
                    || ($revision->published_by_admin_id !== null
                        && (int) $revision->published_by_admin_id !== (int) $admin->id)
                    || ! in_array($revision->state, ['draft', 'published', 'superseded'], true)
                    || ! is_array($snapshot) || array_keys($snapshot) !== ['cod_enabled']
                    || ! is_bool($snapshot['cod_enabled'])) {
                    throw new RuntimeException('Unexpected COD revision; refuse synthetic cleanup.');
                }
            }
            if ($revisions->isEmpty()) {
                return;
            }
            DB::table('site_configuration_revisions')->whereIn('id', $revisions->pluck('id')->all())->delete();
            DB::table('identity_audit_events')->where('realm', 'admin')
                ->where('account_id', $admin->id)
                ->whereIn('action', ['website_cod_policy_draft_saved', 'website_cod_policy_published'])
                ->delete();
        });
    }
}
