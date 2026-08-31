<?php

namespace Tests\Feature;

use Illuminate\Http\Client\StrayRequestException;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_public_health_contract_exposes_no_configuration(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertExactJson([
            'data' => ['service' => 'mobisttech-backend', 'status' => 'ok', 'contract' => 'v1'],
        ])->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_inertia_foundation_renders_without_business_authentication(): void
    {
        $this->withoutVite()->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('foundation')
            ->where('application', 'mobiST Tech')
            ->where('scope', 'Application foundation only'));
    }

    public function test_business_and_legacy_transport_routes_are_not_prematurely_exposed(): void
    {
        $this->getJson('/api/v1/orders')->assertNotFound();
        $this->get('/login')->assertNotFound();
        $this->getJson('/api/website/products')->assertNotFound();
        $this->postJson('/api/v1/provider-events/jazzcash', [])->assertNotFound();
    }

    public function test_external_http_is_disabled_in_isolated_environment(): void
    {
        $this->expectException(StrayRequestException::class);
        Http::get('https://example.invalid/provider');
    }
}
