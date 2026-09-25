<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Only the isolated H01 catalogue revisions created after a captured empty baseline. */
class H01CatalogueBrowserCleanupSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_H01_CATALOGUE_E2E_ENABLED') === '1', 403);
        $receipt = base_path('../.local/h01-catalogue-browser-receipt.json');
        $action = getenv('MT75_H01_CATALOGUE_FIXTURE_ACTION');
        abort_unless(in_array($action, ['seed', 'cleanup'], true), 403);
        if ($action === 'seed') {
            abort_unless(! is_file($receipt) && DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->doesntExist()
                && DB::table('site_settings')->where('key', 'cms.presentation.catalogue')->doesntExist(), 409,
                'H01 catalogue browser requires an empty presentation baseline.');
            $listing = DB::table('product_listings')->where('external_source', 'website-e2e')->where('slug', 'mt51-alpha-phone')->firstOrFail();
            $product = DB::table('products')->where('id', $listing->product_id)->firstOrFail();
            abort_unless($product->name === 'MT51 Alpha Phone' && (int) $product->qty === 1
                && $listing->warranty_summary === 'No warranty', 409, 'Only exact synthetic storefront fixtures may be annotated.');
            DB::table('products')->where('id', $product->id)->update(['ram_gb' => 8, 'storage_gb' => 128]);
            DB::table('product_listings')->where('id', $listing->id)->update(['warranty_summary' => 'H01 browser warranty']);
            file_put_contents($receipt, json_encode(['baseline' => (int) DB::table('site_configuration_revisions')->max('id')], JSON_THROW_ON_ERROR));
            $this->command?->info('H01 exact-owned empty Website catalogue presentation baseline verified.');

            return;
        }
        abort_unless(is_file($receipt), 409);
        $baseline = json_decode(file_get_contents($receipt), true, flags: JSON_THROW_ON_ERROR)['baseline'];
        $rows = DB::table('site_configuration_revisions')->where('domain', 'website.presentation')->where('id', '>', $baseline)->get();
        foreach ($rows as $row) {
            $value = json_decode($row->snapshot, true);
            abort_unless(is_array($value) && array_keys($value) === ['catalogue'] && is_array($value['catalogue']), 409,
                'Refusing to delete any non-H01 presentation revision.');
        }
        $ids = $rows->pluck('id')->all();
        $events = DB::table('identity_audit_events')->where('reference', 'like', 'site_configuration_revision:%')->whereIn('reference', array_map(fn ($id) => 'site_configuration_revision:'.$id, $ids))->get();
        abort_unless($events->every(fn ($event) => in_array($event->action,
            ['website_presentation_draft_saved', 'website_presentation_published', 'website_presentation_rolled_back'], true)), 409);
        DB::transaction(function () use ($ids, $events) {
            if ($ids) {
                DB::table('identity_audit_events')->whereIn('id', $events->pluck('id'))->delete();
                DB::table('site_configuration_revisions')->whereIn('id', $ids)->update(['restored_from_revision_id' => null]);
                DB::table('site_configuration_revisions')->whereIn('id', $ids)->delete();
            }
            DB::table('site_settings')->where('key', 'cms.presentation.catalogue')->delete();
            DB::table('publication_versions')->where('domain', 'cms.presentation')->delete();
        });
        unlink($receipt);
        $this->command?->info('H01 only-owned catalogue revisions/settings/identity audit cleaned.');
    }
}
