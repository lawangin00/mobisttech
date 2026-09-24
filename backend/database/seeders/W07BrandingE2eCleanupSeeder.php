<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Remove only the W07 owner-scoped branding revisions and published setting. */
final class W07BrandingE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        DB::transaction(function (): void {
            $owner = DB::table('admins')->where('email', 'e2e-platform@example.invalid')->value('id');
            $asset = DB::table('site_media_assets')->where('original_name', 'mt75-w07-media-brand.png')->first();
            $all = DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->lockForUpdate()->get();
            $rows = $all->filter(function ($row) use ($owner, $asset) {
                $snap = json_decode($row->snapshot, true, flags: JSON_THROW_ON_ERROR);
                if (! array_key_exists('branding', $snap)) {
                    return false;
                }
                abort_unless($owner && (int) $row->created_by_admin_id === (int) $owner
                    && count($snap) === 1 && is_array($snap['branding'])
                    && array_diff(array_keys($snap['branding']), ['main_logo', 'wordmark', 'header_logo', 'footer_logo', 'square_icon', 'favicon', 'social_image']) === [], 409);
                foreach ($snap['branding'] as $id) {
                    abort_unless($id === null
                        || ($asset && (int) $id === (int) $asset->id && (int) $asset->uploaded_by_admin_id === (int) $owner), 409);
                }

                return true;
            });
            $setting = DB::table('site_settings')->where('key', 'cms.presentation.branding')->lockForUpdate()->first();
            if ($setting) {
                abort_unless($owner && (int) $setting->updated_by_admin_id === (int) $owner
                    && $setting->group === 'presentation' && $rows->isNotEmpty(), 409);
            }
            if ($rows->isEmpty()) {
                abort_unless(! $setting, 409);

                return;
            }
            DB::table('site_settings')->where('key', 'cms.presentation.branding')->delete();
            $ids = $rows->pluck('id')->all();
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->update(['restored_from_revision_id' => null]);
            DB::table('site_configuration_revisions')->whereIn('id', $ids)->delete();
        });
    }
}
