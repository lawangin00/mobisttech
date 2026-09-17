<?php

namespace Tests\Feature;

use App\Cms\WebsiteModePublication;
use App\Digital\DigitalServiceLeads;
use App\Models\Admin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class DigitalServiceLeadsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        Storage::fake('local');
        config(['infrastructure.private_disk' => 'local']);
        $draft = app(WebsiteModePublication::class)->saveDraft($this->actor, 'digital_only');
        app(WebsiteModePublication::class)->publish($this->actor, $draft['id']);
    }

    public function test_authoritative_packages_uploads_consultation_and_replay(): void
    {
        $service = app(DigitalServiceLeads::class);
        $configured = $service->configureService($this->actor, [
            'slug' => 'web-development', 'name' => 'Web Development', 'short_description' => 'Build a Website',
            'price_type' => 'package', 'price' => null, 'is_active' => true,
            'packages' => [['code' => 'starter', 'name' => 'Starter', 'pricing_type' => 'fixed', 'price' => '10000.00']],
            'addons' => [['code' => 'seo', 'name' => 'SEO Setup', 'pricing_type' => 'fixed', 'price' => '2500.00']],
        ]);
        $service->configureConsultation($this->actor, [
            'enabled' => true, 'timezone' => 'Asia/Karachi',
            'weekly_availability' => [['day' => 1, 'start' => '09:00', 'end' => '17:00']],
        ]);
        $input = [
            'service_slug' => 'web-development', 'customer_name' => 'Lead One', 'customer_mobile' => '03001234567',
            'customer_email' => 'lead@example.invalid', 'requirements' => 'Need a business Website',
            'package_public_id' => $configured['packages'][0]['public_id'],
            'addon_public_ids' => [$configured['addons'][0]['public_id']], 'consultation_requested' => true,
            'preferred_timezone' => 'Asia/Karachi', 'preferred_window_start' => '2026-09-21 10:00',
            'preferred_window_end' => '2026-09-21 11:00',
        ];
        $first = $service->submit('lead-one', 'fingerprint-one', $input, [['name' => 'brief.txt', 'contents' => 'project brief']]);
        $replay = $service->submit('lead-one', 'fingerprint-one', $input, [['name' => 'brief.txt', 'contents' => 'project brief']]);
        $this->assertSame($first['public_id'], $replay['public_id']);
        $this->assertSame(1, DB::table('service_requests')->count());
        $requestId = (int) DB::table('service_requests')->value('id');
        $snapshots = DB::table('service_request_selections')->where('service_request_id', $requestId)->orderBy('selection_type')->get();
        $this->assertSame(['2500.00', '10000.00'], $snapshots->pluck('price_snapshot')->sort()->values()->all());
        $file = DB::table('service_request_files')->where('service_request_id', $requestId)->firstOrFail();
        Storage::disk('local')->assertExists($file->object_key);
        $lead = $service->lead($this->actor, $first['public_id']);
        $this->assertSame('requested', $lead['consultation_status']);
        $this->assertArrayNotHasKey('object_key', $lead['reference_files'][0]);
        $this->assertSame(1, count($lead['events']));

        $service->configureService($this->actor, [
            'slug' => 'web-development', 'name' => 'Web Development', 'short_description' => 'Build a Website',
            'price_type' => 'package', 'price' => null, 'is_active' => true,
            'packages' => [['code' => 'starter', 'name' => 'Starter', 'pricing_type' => 'fixed', 'price' => '12000.00']],
            'addons' => [['code' => 'seo', 'name' => 'SEO Setup', 'pricing_type' => 'fixed', 'price' => '3000.00']],
        ]);
        $this->assertSame('10000.00', DB::table('service_request_selections')->where('selection_type', 'package')->value('price_snapshot'));
        $this->reject(fn () => $service->submit('tamper', 'fingerprint-two', $input + ['price' => '1.00']));
        $catalogue = $service->catalogue();
        $this->assertSame('12000.00', $catalogue[0]['packages'][0]['price']);
        $this->assertSame('3000.00', $catalogue[0]['addons'][0]['price']);
    }

    public function test_rate_limit_and_mode_switch_block_new_leads_without_removing_history(): void
    {
        $service = app(DigitalServiceLeads::class);
        $service->configureService($this->actor, [
            'slug' => 'automation', 'name' => 'Automation', 'short_description' => 'Automation service',
            'price_type' => 'quote', 'price' => null, 'is_active' => true,
        ]);
        $base = ['service_slug' => 'automation', 'customer_name' => 'Rate Lead', 'customer_mobile' => '03001112222',
            'requirements' => 'Need automation'];
        for ($i = 1; $i <= 5; $i++) {
            $service->submit('rate-'.$i, 'same-browser', $base + ['campaign' => 'c'.$i]);
        }
        $this->reject(fn () => $service->submit('rate-6', 'same-browser', $base + ['campaign' => 'c6']));
        $this->assertSame(5, DB::table('service_requests')->count());

        $modes = app(WebsiteModePublication::class);
        $draft = $modes->saveDraft($this->actor, 'commerce_only');
        $modes->publish($this->actor, $draft['id']);
        $this->reject(fn () => $service->submit('inactive', 'another-browser', $base));
        $this->assertSame(5, DB::table('service_requests')->count());
        $existing = DB::table('service_requests')->firstOrFail();
        $this->assertSame($existing->public_id, $service->lead($this->actor, $existing->public_id)['public_id']);
        $this->reject(fn () => $service->catalogue());
    }

    public function test_permissions_assignment_follow_up_and_history_are_scoped(): void
    {
        $service = app(DigitalServiceLeads::class);
        $service->configureService($this->actor, [
            'slug' => 'consulting', 'name' => 'Consulting', 'short_description' => 'Consulting service',
            'price_type' => 'starting_from', 'price' => '5000.00', 'is_active' => true,
        ]);
        $lead = $service->submit('pipeline', 'pipeline-browser', [
            'service_slug' => 'consulting', 'customer_name' => 'Pipeline Lead', 'customer_mobile' => '03009990000',
            'requirements' => 'Need advisory support', 'project_type' => 'advisory',
        ]);
        $viewer = $this->adminWith([]);
        $manager = $this->adminWith(['website.digital-leads.manage']);
        $this->reject(fn () => $service->lead($viewer, $lead['public_id']));
        $this->reject(fn () => $service->transition($viewer, $lead['public_id'], ['version' => 1, 'status' => 'contacted']));
        $updated = $service->transition($manager, $lead['public_id'], [
            'version' => 1, 'status' => 'contacted', 'assigned_admin_public_id' => $manager->public_id,
            'follow_up_at' => '2026-09-22T10:00:00+05:00', 'note' => 'Customer requested a follow-up.',
        ]);
        $this->assertSame(2, $updated['version']);
        $this->assertSame('contacted', $updated['status']);
        $this->assertSame($manager->id, $updated['assigned_admin_id']);
        $this->assertCount(2, $updated['events']);
        $this->assertSame('lead_updated', $updated['events'][1]['type']);
        $this->reject(fn () => $service->transition($manager, $lead['public_id'], ['version' => 1, 'status' => 'qualified']));
    }

    private function adminWith(array $permissions): Admin
    {
        $admin = new Admin;
        $admin->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Digital actor '.Str::uuid(),
            'email' => Str::uuid().'@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => $permissions])->save();

        return $admin;
    }
}
