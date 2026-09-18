<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DynamicWebsiteE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('Dynamic Website E2E cleanup is restricted to mobisttech_test.');
        }

        DB::transaction(function () {
            $serviceId = DB::table('digital_services')->where('slug', 'mt54-web-development')->value('id');
            if ($serviceId) {
                $requestIds = DB::table('service_requests')->where('digital_service_id', $serviceId)->pluck('id')->all();
                if ($requestIds) {
                    $fingerprints = DB::table('service_request_details')->whereIn('service_request_id', $requestIds)->pluck('submission_fingerprint')->all();
                    DB::table('service_request_files')->whereIn('service_request_id', $requestIds)->delete();
                    DB::table('service_request_events')->whereIn('service_request_id', $requestIds)->delete();
                    DB::table('service_request_selections')->whereIn('service_request_id', $requestIds)->delete();
                    DB::table('service_request_details')->whereIn('service_request_id', $requestIds)->delete();
                    DB::table('idempotency_requests')->where('resource_type', 'service_request')->whereIn('resource_id', $requestIds)->delete();
                    DB::table('service_requests')->whereIn('id', $requestIds)->delete();
                    if ($fingerprints) {
                        DB::table('service_request_rate_buckets')->whereIn('submission_fingerprint', $fingerprints)->delete();
                    }
                }
                DB::table('site_page_service_links')->where('digital_service_id', $serviceId)->delete();
                DB::table('digital_service_packages')->where('digital_service_id', $serviceId)->delete();
                DB::table('digital_service_addons')->where('digital_service_id', $serviceId)->delete();
                DB::table('digital_services')->where('id', $serviceId)->delete();
            }
            DB::table('digital_consultation_settings')->where('id', 1)->delete();

            $pageIds = DB::table('site_managed_pages')->whereIn('slug', ['home', 'mt54-case-study'])->pluck('id')->all();
            if ($pageIds) {
                DB::table('site_page_service_links')->whereIn('site_managed_page_id', $pageIds)->delete();
                DB::table('site_managed_pages')->whereIn('id', $pageIds)->update(['current_revision_id' => null]);
                DB::table('site_page_revisions')->whereIn('site_managed_page_id', $pageIds)->delete();
                DB::table('site_managed_pages')->whereIn('id', $pageIds)->delete();
            }

            $policyId = DB::table('cms_policies')->where('policy_type', 'privacy')->value('id');
            if ($policyId) {
                DB::table('cms_policies')->where('id', $policyId)->update(['current_revision_id' => null]);
                DB::table('cms_policy_revisions')->where('cms_policy_id', $policyId)->delete();
                DB::table('cms_policies')->where('id', $policyId)->delete();
            }

            $softwareId = DB::table('software_products')->where('slug', 'mt54-software')->value('id');
            if ($softwareId) {
                DB::table('software_products')->where('id', $softwareId)->update(['current_revision_id' => null, 'current_release_id' => null]);
                DB::table('cms_route_redirects')->where('software_product_id', $softwareId)->delete();
                DB::table('software_releases')->where('software_product_id', $softwareId)->delete();
                DB::table('software_product_revisions')->where('software_product_id', $softwareId)->delete();
                DB::table('software_products')->where('id', $softwareId)->delete();
            }

            DB::table('publication_versions')->whereIn('domain', ['cms.pages', 'cms.policies', 'cms.software', 'digital-services'])->delete();
        });
    }
}
