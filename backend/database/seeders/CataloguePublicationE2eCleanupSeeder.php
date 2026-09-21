<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Only release the synthetic catalogue cache version after all browser fixtures are gone. */
final class CataloguePublicationE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $database = DB::selectOne('SELECT DATABASE() AS name, @@port AS port');
        abort_unless(getenv('CI') === 'true' && app()->environment('testing')
            && $database->name === 'mobisttech_test' && (int) $database->port === 13306, 403);

        DB::transaction(function (): void {
            $marker = DB::table('publication_versions')->where('domain', 'catalogue')->lockForUpdate()->first();
            if (! $marker) {
                return;
            }

            // A catalogue version is disposable only after synthetic product, option,
            // listing and corresponding catalogue-event rows have all been removed.
            abort_unless(! DB::table('products')->exists()
                && ! DB::table('product_listings')->exists()
                && ! DB::table('pos_master_data_options')->exists()
                && ! DB::table('domain_events')->where('event_type', 'catalogue.changed')->exists(), 409,
                'Catalogue publication marker still has business dependants.');

            DB::table('publication_versions')->where('domain', 'catalogue')->delete();
        });
    }
}
