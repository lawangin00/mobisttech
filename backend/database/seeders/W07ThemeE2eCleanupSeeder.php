<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** W07 synthetic theme revisions only; refuse to mutate any unknown baseline. */
final class W07ThemeE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        DB::transaction(function (): void {
            $owner = DB::table('admins')->where('email', 'e2e-platform@example.invalid')->value('id');
            $revisions = DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->lockForUpdate()->get();
            $theme = DB::table('site_settings')->where('key', 'cms.presentation.theme')->lockForUpdate()->first();
            foreach ($revisions as $revision) {
                $snapshot = json_decode($revision->snapshot, true, flags: JSON_THROW_ON_ERROR);
                abort_unless($owner && (int) $revision->created_by_admin_id === (int) $owner
                    && is_array($snapshot) && count($snapshot) === 1 && is_array($snapshot['theme'] ?? null)
                    && in_array($snapshot['theme']['primary'] ?? null, ['#005b60', '#008080'], true), 409);
            }
            if ($theme) {
                abort_unless($owner && (int) $theme->updated_by_admin_id === (int) $owner
                    && $theme->group === 'presentation' && $revisions->isNotEmpty(), 409);
            }
            if ($revisions->isEmpty()) {
                abort_unless(! $theme, 409);

                return;
            }
            DB::table('site_settings')->where('key', 'cms.presentation.theme')->delete();
            $ids = $revisions->pluck('id')->all();
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->update(['restored_from_revision_id' => null]);
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->delete();
        });
    }
}
