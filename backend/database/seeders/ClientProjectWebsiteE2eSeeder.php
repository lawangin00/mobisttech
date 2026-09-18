<?php

namespace Database\Seeders;

use App\Digital\ClientProjectServices;
use App\Models\Admin;
use App\Models\CustomerAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ClientProjectWebsiteE2eSeeder extends Seeder
{
    public const SERVICE_SLUG = 'mt55-client-project';

    public function run(): void
    {
        if (DB::connection()->getDatabaseName() !== 'mobisttech_test') {
            throw new \RuntimeException('MT-5.5 client-project E2E seeding is restricted to mobisttech_test.');
        }

        app(ClientProjectWebsiteE2eCleanupSeeder::class)->run();
        $actor = Admin::where('email', 'e2e-digital-operations@example.invalid')->firstOrFail();
        $customer = CustomerAccount::where('email', CustomerWebsiteE2eSeeder::EMAIL)->firstOrFail();
        $serviceId = DB::table('digital_services')->insertGetId([
            'slug' => self::SERVICE_SLUG, 'name' => 'MT55 Client Project Service',
            'short_description' => 'Synthetic client-project browser fixture', 'price_type' => 'quote',
            'is_active' => true, 'sort_order' => 550, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $requestId = DB::table('service_requests')->insertGetId([
            'reference' => 'MT55-E2E-REQUEST', 'digital_service_id' => $serviceId,
            'customer_name' => $customer->name, 'customer_mobile' => $customer->mobile,
            'customer_email' => $customer->email, 'requirements' => 'Synthetic MT-5.5 project journey.',
            'preferred_contact' => 'email', 'status' => 'qualified',
            'public_id' => '00000000-0000-4000-8000-000000005501', 'version' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $projects = app(ClientProjectServices::class);
        $project = $projects->createProject($actor, '00000000-0000-4000-8000-000000005501', [
            'title' => 'MT55 Client Project', 'customer_account_public_id' => $customer->public_id,
            'assigned_admin_public_id' => $actor->public_id,
        ]);
        $project = $projects->transition($actor, $project['public_id'], [
            'version' => $project['version'], 'status' => 'discussion',
        ]);
        $proposal = $projects->createProposal($actor, $project['public_id'], [
            'version' => $project['version'], 'title' => 'MT55 Approved Scope',
            'scope' => 'Deterministic browser acceptance scope.', 'deliverables' => ['Portal delivery', 'Handover notes'],
            'amount' => '10000.00', 'valid_until' => now()->addDays(14)->toIso8601String(),
            'milestones' => [[
                'kind' => 'final', 'label' => 'Final payment', 'amount' => '10000.00',
                'due_at' => now()->addDays(7)->toIso8601String(),
            ]],
        ]);
        $projects->approveProposal($actor, $proposal['public_id']);
        $current = $projects->adminProject($actor, $project['public_id']);
        foreach (['in_progress', 'review', 'delivered'] as $status) {
            $current = $projects->transition($actor, $project['public_id'], [
                'version' => $current['version'], 'status' => $status,
            ]);
        }

        $projects->uploadReference($customer, $project['public_id'], [
            'name' => 'mt55-client-reference.txt', 'contents' => 'MT55 private client reference',
        ]);
        $projects->uploadDelivery($actor, $project['public_id'], [
            'name' => 'mt55-delivery.txt', 'contents' => 'MT55 private delivery',
        ], $proposal['public_id']);

        DB::table('service_requests')->where('id', $requestId)->update(['status' => 'converted', 'updated_at' => now()]);
    }
}
