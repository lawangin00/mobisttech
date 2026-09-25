<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GADigitalAttributionBrowserSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('testing') && DB::connection()->getDatabaseName() === 'mobisttech_test'
            && getenv('MT75_GA_ATTRIBUTION_E2E') === '1', 403);
        $action = getenv('MT75_GA_ATTRIBUTION_ACTION');
        abort_unless(in_array($action, ['verify', 'cleanup'], true), 403);

        $serviceId = DB::table('digital_services')->where('slug', 'mt54-web-development')->value('id');
        abort_unless($serviceId, 409, 'Synthetic attribution service fixture is missing.');
        $request = DB::table('service_requests')->where('digital_service_id', $serviceId)
            ->where('customer_name', 'GA Attribution Browser')->where('customer_mobile', '03001112223')->first();

        if ($action === 'verify') {
            abort_unless($request, 409, 'Expected GA attribution browser enquiry is missing.');
            $detail = DB::table('service_request_details')->where('service_request_id', $request->id)->firstOrFail();
            abort_unless($detail->source === 'service_page' && $detail->campaign === 'service_mt54-web-development',
                409, 'GA attribution source/campaign persistence mismatch.');
            abort_unless(! str_contains((string) $detail->source, '@') && ! str_contains((string) $detail->campaign, '@'),
                409, 'GA attribution unexpectedly contains identifying data.');
            $this->command?->info('GA CTA -> enquiry source/campaign persistence verified.');

            return;
        }

        if (! $request) {
            $this->command?->info('GA attribution browser enquiry already absent.');

            return;
        }
        $fingerprint = DB::table('service_request_details')->where('service_request_id', $request->id)->value('submission_fingerprint');
        DB::transaction(function () use ($request, $fingerprint) {
            DB::table('service_request_files')->where('service_request_id', $request->id)->delete();
            DB::table('service_request_events')->where('service_request_id', $request->id)->delete();
            DB::table('service_request_selections')->where('service_request_id', $request->id)->delete();
            DB::table('service_request_details')->where('service_request_id', $request->id)->delete();
            DB::table('idempotency_requests')->where('resource_type', 'service_request')->where('resource_id', $request->id)->delete();
            DB::table('service_requests')->where('id', $request->id)->delete();
            if (is_string($fingerprint) && $fingerprint !== '') {
                DB::table('service_request_rate_buckets')->where('submission_fingerprint', $fingerprint)->delete();
            }
        });
        $this->command?->info('GA exact-owned attribution enquiry cleaned.');
    }
}
