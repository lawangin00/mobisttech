<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Only the exact synthetic W06 managed-page social image after the owned page's teardown. */
final class W06ManagedSocialMediaE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        $path = DB::transaction(function (): ?string {
            $asset = DB::table('site_media_assets')->where('original_name', 'mt75-w06-managed-social.png')->lockForUpdate()->first();
            if (! $asset) {
                return null;
            }
            $owner = DB::table('admins')->where('id', $asset->uploaded_by_admin_id)->value('email');
            abort_unless($owner === 'e2e-platform@example.invalid'
                && $asset->sha256 === '136d0ff1637f3fb31b4dd4d13d9d774e7810ab30a90d453590042e628973892a'
                && $asset->disk === 'local' && $asset->mime_type === 'image/png'
                && preg_match('/\Acms\/[0-9a-f-]+\.png\z/', $asset->path)
                && ! DB::table('site_managed_pages')->where('social_image_media_id', $asset->id)->exists()
                && ! DB::table('site_media_usages')->where('media_asset_id', $asset->id)->exists(), 409);
            DB::table('site_media_assets')->where('id', $asset->id)->delete();

            return $asset->path;
        });
        if ($path) {
            abort_unless(Storage::disk('local')->delete($path), 500, 'W06 managed social image cleanup failed.');
        }
        // Whichever exact-owned media fixture is cleaned last may release only the derived
        // cms.media cache marker, and only after the disposable schema is truly media-empty.
        if (! DB::table('site_media_assets')->exists() && ! DB::table('site_media_usages')->exists()) {
            DB::table('publication_versions')->where('domain', 'cms.media')->delete();
        }
    }
}
