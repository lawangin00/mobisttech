<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Cleanup only one independently generated W06 software product in the disposable browser schema. */
final class W06SecondSoftwareE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        $mediaPath = DB::transaction(function (): ?string {
            $product = DB::table('software_products')->whereIn('slug', ['mt75-w06-second', 'mt75-w06-second-renamed'])->lockForUpdate()->first();
            if ($product) {
                $owner = DB::table('admins')->where('id', $product->created_by_admin_id)->first(['email']);
                abort_unless($product->name === 'MT75 W06 Second Software'
                    && $owner && $owner->email === 'e2e-platform@example.invalid', 409);
                DB::table('software_products')->where('id', $product->id)->update([
                    'current_revision_id' => null, 'current_release_id' => null,
                ]);
                DB::table('cms_route_redirects')->where('software_product_id', $product->id)->delete();
                DB::table('software_releases')->where('software_product_id', $product->id)->delete();
                DB::table('software_product_revisions')->where('software_product_id', $product->id)->update([
                    'restored_from_revision_id' => null,
                ]);
                DB::table('software_product_revisions')->where('software_product_id', $product->id)->delete();
                DB::table('software_products')->where('id', $product->id)->delete();
            }
            $asset = DB::table('site_media_assets')->where('original_name', 'mt75-w06-media.png')->lockForUpdate()->first();
            if (! $asset) {
                return null;
            }
            $uploadedBy = DB::table('admins')->where('id', $asset->uploaded_by_admin_id)->value('email');
            abort_unless($uploadedBy === 'e2e-platform@example.invalid'
                && $asset->sha256 === '234d066b0c0e977d7892934d114a3c8ae7404cc00b3481893eb2f592ae9ebfdc'
                && $asset->disk === 'local' && $asset->mime_type === 'image/png' && preg_match('/\Acms\/[0-9a-f-]+\.png\z/', $asset->path), 409);
            DB::table('site_media_assets')->where('id', $asset->id)->delete();

            return $asset->path;
        });
        if ($mediaPath) {
            abort_unless(Storage::disk('local')->delete($mediaPath), 500, 'W06 synthetic image cleanup failed.');
        }
    }
}
