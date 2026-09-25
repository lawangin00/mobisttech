<?php

namespace Database\Seeders;

use App\Cms\WebsiteCms;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Only the exact W07 synthetic admin PNG uploads, never an unknown media library row. */
final class W07MediaE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        $owner = Admin::where('email', 'e2e-platform@example.invalid')->firstOrFail();
        $rows = DB::table('site_media_assets')->where('original_name', 'like', 'mt75-w07-media-%')->get();
        $names = ['mt75-w07-media-first.png', 'mt75-w07-media-second.png', 'mt75-w07-media-brand.png'];
        foreach ($rows as $row) {
            abort_unless(in_array($row->original_name, $names, true) && (int) $row->uploaded_by_admin_id === (int) $owner->id
                && $row->disk === 'local' && $row->mime_type === 'image/png'
                && preg_match('/\Acms\/[0-9a-f-]+\.png\z/', $row->path), 409,
                'W07 media fixture owner, filename, MIME, or storage path mismatch.');
            if ($row->status === 'active') {
                app(WebsiteCms::class)->deleteUnusedMedia($owner, (int) $row->id);
            } else {
                abort_unless($row->status === 'retired', 409, 'Unknown media lifecycle state.');
            }
            // Tombstone path already denies public access; remove only verified test-owned bytes.
            Storage::disk('local')->delete($row->path);
            DB::table('site_media_assets')->where('id', $row->id)->where('status', 'retired')->delete();
        }
        // Media mutation APIs bump cms.media. Release only this derived cache marker when
        // the disposable test schema has no media assets or usages left; never mask residue.
        if (! DB::table('site_media_assets')->exists() && ! DB::table('site_media_usages')->exists()) {
            DB::table('publication_versions')->where('domain', 'cms.media')->delete();
        }
    }
}
