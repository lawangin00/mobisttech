<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Sales\SalesOperations;
use App\Warranty\WarrantyClauses;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class WarrantyClausesTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_defaults_and_published_versions_are_validated_idempotent_and_permission_scoped(): void
    {
        $service = app(WarrantyClauses::class);
        $this->assertSame(0, $service->snapshot()['version']);
        $this->assertCount(3, $service->snapshot()['clauses']);
        $input = ['show' => true, 'title' => 'Approved Warranty Terms', 'clauses' => [
            ['id' => 'coverage', 'enabled' => true, 'order' => 1, 'text' => 'Repair coverage follows the sale-time plan.'],
            ['id' => 'inspection', 'enabled' => false, 'order' => 2, 'text' => 'Inspection clause.'],
        ]];
        $key = (string) Str::uuid();
        $published = $service->publish($this->actor, $key, $input);
        $this->assertSame(1, $published['version']);
        $this->assertEquals($published, $service->publish($this->actor, $key, $input));
        $this->assertEquals($published, $service->snapshot());
        $this->assertSame(1, DB::table('pos_configuration_revisions')->where('domain', WarrantyClauses::DOMAIN)->count());
        $this->reject(fn () => $service->publish($this->actor, $key, [...$input, 'title' => 'Changed']));
        foreach ([[...$input, 'clauses' => [[$input['clauses'][0]], [$input['clauses'][0]]]],
            [...$input, 'title' => '<b>Unsafe</b>'], [...$input, 'extra' => true]] as $bad) {
            $this->reject(fn () => $service->publish($this->actor, (string) Str::uuid(), $bad));
        }
        $admin = new Admin;
        $admin->forceFill(['name' => 'Denied document editor', 'email' => 'denied-docs@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => ['shops.enter']])->save();
        $this->reject(fn () => $service->publish($admin, (string) Str::uuid(), $input));
    }

    public function test_each_invoice_retains_the_clause_version_visible_at_sale_time(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $service = app(WarrantyClauses::class);
        $first = $service->publish($this->actor, (string) Str::uuid(), $this->clauses('First terms'));
        $saleA = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $second = $service->publish($this->actor, (string) Str::uuid(), $this->clauses('Second terms'));
        $saleB = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $snapshotA = json_decode(DB::table('invoices')->where('public_id', $saleA['invoice_id'])->value('warranty_terms_snapshot'), true);
        $snapshotB = json_decode(DB::table('invoices')->where('public_id', $saleB['invoice_id'])->value('warranty_terms_snapshot'), true);
        $this->assertEquals($first, $snapshotA);
        $this->assertEquals($second, $snapshotB);
        $this->assertSame('First terms', $snapshotA['title']);
        $this->assertSame('Second terms', $snapshotB['title']);
    }

    private function clauses(string $title): array
    {
        return ['show' => true, 'title' => $title, 'clauses' => [
            ['id' => 'terms', 'enabled' => true, 'order' => 1, 'text' => $title.' body.'],
        ]];
    }
}
