<?php

namespace Tests\Feature;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Addendum\WebsiteCapabilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AddendumBoundaryTest extends TestCase
{
    public function test_internal_creation_gates_require_an_owning_transaction(): void
    {
        $this->assertSame(0, DB::transactionLevel());
        foreach ([fn () => app(WebsiteCapabilities::class)->assertCreationAllowed('checkout.create'), fn () => app(FinancialReferences::class)->adjustment('invoice', 1, [])] as $callback) {
            try {
                $callback();
                $this->fail('Missing transaction accepted');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('transaction', $e->getMessage());
            }
        }
    }

    public function test_money_contract_rejects_float_rounding_overflow_and_missing_external_identity(): void
    {
        $this->assertSame('99999999999999999.99', MoneySnapshot::amount('99999999999999999.99'));
        foreach ([0, 1.01, '1.001', '1', '01.00', '-1.00', '0.00', '1e2', '100000000000000000.00'] as $invalid) {
            try {
                MoneySnapshot::amount($invalid);
                $this->fail('Noncanonical money accepted');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        MoneySnapshot::adjustment((string) Str::uuid(), 'promotion', '1.00', 'Synthetic');
    }

    public function test_no_addendum_business_endpoint_is_exposed(): void
    {
        $paths = collect(app('router')->getRoutes()->getRoutes())->map(fn ($r) => $r->uri());
        foreach (['api/v1/website-profile', 'api/v1/checkout', 'internal/superadmin/data-reset', 'internal/admin/transfers'] as $path) {
            $this->assertFalse($paths->contains($path));
        }
    }
}
