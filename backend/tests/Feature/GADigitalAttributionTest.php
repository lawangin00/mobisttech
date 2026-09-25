<?php

namespace Tests\Feature;

use App\Cms\WebsiteModePublication;
use App\Digital\ClientProjectServices;
use App\Digital\DigitalServiceLeads;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class GADigitalAttributionTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $mode = app(WebsiteModePublication::class)->saveDraft($this->actor, 'digital_only');
        app(WebsiteModePublication::class)->publish($this->actor, $mode['id']);
        app(DigitalServiceLeads::class)->configureService($this->actor, [
            'slug' => 'ga-attribution-service', 'name' => 'GA Attribution Service',
            'short_description' => 'Synthetic attribution service',
            'price_type' => 'quote', 'price' => null, 'is_active' => true,
        ]);
    }

    public function test_page_cta_attribution_is_coarse_safe_idempotent_and_aggregate_only(): void
    {
        $leads = app(DigitalServiceLeads::class);
        $input = [
            'service_slug' => 'ga-attribution-service',
            'customer_name' => 'GA Attribution Lead',
            'customer_mobile' => '03000000001',
            'requirements' => 'Synthetic conversion attribution proof',
            'source' => 'service_page',
            'campaign' => 'service_ga-attribution-service',
        ];
        $first = $leads->submit('ga-attribution-one', 'ga-attribution-browser-one', $input);
        $replay = $leads->submit('ga-attribution-one', 'ga-attribution-browser-one', $input);
        $this->assertSame($first['public_id'], $replay['public_id']);

        $firstId = DB::table('service_requests')->where('public_id', $first['public_id'])->value('id');
        $detail = DB::table('service_request_details')->where('service_request_id', $firstId)->firstOrFail();
        $this->assertSame('service_page', $detail->source);
        $this->assertSame('service_ga-attribution-service', $detail->campaign);

        $unsafe = $leads->submit('ga-attribution-two', 'ga-attribution-browser-two', [
            ...$input,
            'customer_mobile' => '03000000002',
            'source' => 'https://example.invalid/path?customer=person@example.invalid',
            'campaign' => 'person@example.invalid',
        ]);
        $unsafeId = DB::table('service_requests')->where('public_id', $unsafe['public_id'])->value('id');
        $unsafeDetail = DB::table('service_request_details')->where('service_request_id', $unsafeId)->firstOrFail();
        $this->assertNull($unsafeDetail->source);
        $this->assertNull($unsafeDetail->campaign);
        $this->assertSame(2, DB::table('service_requests')->where('digital_service_id',
            DB::table('digital_services')->where('slug', 'ga-attribution-service')->value('id'))->count());

        $summary = app(ClientProjectServices::class)->conversionSummary(
            $this->actor,
            now()->subMinute()->toIso8601String(),
            now()->addMinute()->toIso8601String()
        );
        $this->assertContains(['source' => 'service_page', 'leads' => 1, 'projects' => 0], $summary['by_source']);
        $this->assertContains(['source' => 'direct', 'leads' => 1, 'projects' => 0], $summary['by_source']);
        $this->assertContains(['campaign' => 'service_ga-attribution-service', 'leads' => 1, 'projects' => 0], $summary['by_campaign']);
        $this->assertContains(['campaign' => 'unattributed', 'leads' => 1, 'projects' => 0], $summary['by_campaign']);

        $encoded = json_encode($summary, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('03000000001', $encoded);
        $this->assertStringNotContainsString('GA Attribution Lead', $encoded);
        $this->assertStringNotContainsString('person@example.invalid', $encoded);
    }
}
