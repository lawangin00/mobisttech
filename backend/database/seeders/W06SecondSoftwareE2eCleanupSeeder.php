<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Cleanup only one independently generated W06 software product in the disposable browser schema. */
final class W06SecondSoftwareE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && (int) DB::selectOne('SELECT @@port AS port')->port === 13306, 403);
        DB::transaction(function (): void {
            $product = DB::table('software_products')->where('slug', 'mt75-w06-second')->lockForUpdate()->first();
            if (! $product) {
                return;
            }
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
        });
    }
}
