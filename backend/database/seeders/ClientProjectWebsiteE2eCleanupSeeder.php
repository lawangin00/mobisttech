<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ClientProjectWebsiteE2eCleanupSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('MT-5.5 client-project E2E cleanup is restricted to mobisttech_test.');
        }

        $serviceId = DB::table('digital_services')->where('slug', ClientProjectWebsiteE2eSeeder::SERVICE_SLUG)->value('id');
        if (! $serviceId) {
            return;
        }

        DB::transaction(function () use ($serviceId) {
            $projectIds = DB::table('client_projects')->where('digital_service_id', $serviceId)->pluck('id')->all();
            if ($projectIds) {
                $fileRows = DB::table('project_files')->whereIn('client_project_id', $projectIds)->get(['object_key']);
                foreach ($fileRows as $file) {
                    Storage::disk(config('infrastructure.private_disk', 'local'))->delete($file->object_key);
                }
                $proposalIds = DB::table('project_proposals')->whereIn('client_project_id', $projectIds)->pluck('id')->all();
                $quoteIds = $proposalIds ? DB::table('project_proposals')->whereIn('id', $proposalIds)->whereNotNull('quote_id')->pluck('quote_id')->all() : [];
                $milestoneIds = $proposalIds ? DB::table('project_proposal_milestones')->whereIn('project_proposal_id', $proposalIds)->pluck('milestone_id')->all() : [];

                DB::table('project_events')->whereIn('client_project_id', $projectIds)->delete();
                DB::table('project_files')->whereIn('client_project_id', $projectIds)->delete();
                if ($proposalIds) {
                    DB::table('project_proposal_milestones')->whereIn('project_proposal_id', $proposalIds)->delete();
                }
                if ($milestoneIds) {
                    DB::table('project_milestone_identities')->whereIn('id', $milestoneIds)->delete();
                }
                if ($proposalIds) {
                    DB::table('project_proposals')->whereIn('id', $proposalIds)->delete();
                }
                if ($quoteIds) {
                    DB::table('project_quotes')->whereIn('id', $quoteIds)->delete();
                }
                DB::table('client_projects')->whereIn('id', $projectIds)->delete();
            }

            $requestIds = DB::table('service_requests')->where('digital_service_id', $serviceId)->pluck('id')->all();
            if ($requestIds) {
                DB::table('service_request_files')->whereIn('service_request_id', $requestIds)->delete();
                DB::table('service_request_events')->whereIn('service_request_id', $requestIds)->delete();
                DB::table('service_request_selections')->whereIn('service_request_id', $requestIds)->delete();
                DB::table('service_request_details')->whereIn('service_request_id', $requestIds)->delete();
                DB::table('idempotency_requests')->where('resource_type', 'service_request')->whereIn('resource_id', $requestIds)->delete();
                DB::table('service_requests')->whereIn('id', $requestIds)->delete();
            }
            DB::table('digital_service_packages')->where('digital_service_id', $serviceId)->delete();
            DB::table('digital_service_addons')->where('digital_service_id', $serviceId)->delete();
            DB::table('site_page_service_links')->where('digital_service_id', $serviceId)->delete();
            DB::table('digital_services')->where('id', $serviceId)->delete();
        });
    }
}
