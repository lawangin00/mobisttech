<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Remove only the explicit W06 synthetic presentation fixture in the disposable browser schema. */
final class W06PresentationE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);

        DB::transaction(function (): void {
            $owner = DB::table('admins')->where('email', 'e2e-platform@example.invalid')->value('id');
            $revisions = DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->lockForUpdate()->get();
            $navigation = DB::table('site_navigation_items')->lockForUpdate()->get();
            $settings = DB::table('site_settings')->whereIn('key', [
                'cms.presentation.navigation', 'cms.presentation.promotion',
            ])->lockForUpdate()->get();

            // The W06 browser starts from a verified zero-presentation baseline. Fail before ANY
            // mutation when another actor's navigation/settings/revision could be affected.
            foreach ($revisions as $row) {
                $snapshot = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                abort_unless($owner && (int) $row->created_by_admin_id === (int) $owner
                    && is_array($snapshot['navigation'] ?? null)
                    && ($snapshot['navigation'][0]['key'] ?? null) === 'mt75-w06-parent'
                    && str_starts_with((string) ($snapshot['promotion']['announcement']['text'] ?? ''), 'W06 Browser '), 409);
            }
            foreach ($navigation as $row) {
                abort_unless($owner && (int) $row->created_by_admin_id === (int) $owner
                    && str_starts_with($row->key, 'mt75-w06-'), 409);
            }
            foreach ($settings as $row) {
                abort_unless($owner && (int) $row->updated_by_admin_id === (int) $owner
                    && $row->group === 'presentation' && $revisions->isNotEmpty(), 409);
            }
            if ($revisions->isEmpty()) {
                abort_unless($navigation->isEmpty() && $settings->isEmpty(), 409);

                return;
            }

            $navIds = $navigation->pluck('id')->all();
            if ($navIds !== []) {
                DB::table('site_navigation_items')->whereIn('id', $navIds)->update(['parent_id' => null]);
                DB::table('site_navigation_items')->whereIn('id', $navIds)->delete();
            }
            DB::table('site_settings')->whereIn('key', [
                'cms.presentation.navigation', 'cms.presentation.promotion',
            ])->delete();
            $ids = $revisions->pluck('id')->all();
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->update(['restored_from_revision_id' => null]);
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->delete();
        });
    }
}
