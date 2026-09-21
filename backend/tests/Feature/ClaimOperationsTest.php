<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\StockUnit;
use App\Sales\SalesOperations;
use App\Warranty\ClaimOperations;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ClaimOperationsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-09-01 10:00:00');
        $this->inventoryFixture();
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_archived_outlet_denies_claim_open_update_and_replays_without_changing_history(): void
    {
        $product = $this->warrantiedProduct();
        $this->acquire($product, 2);
        $sale = $this->sell($product);
        $service = app(ClaimOperations::class);
        $openKey = (string) Str::uuid();
        $openInput = ['sale_id' => $sale['sale_ids'][0],
            'issue_description' => 'D03 synthetic historical claim'];
        $claim = $service->open($this->actor, $this->outlet, $openKey, $openInput);
        $updateKey = (string) Str::uuid();
        $updateInput = $this->update('diagnosing');
        $claim = $service->update($this->actor, $this->outlet,
            $claim['claim_id'], $updateKey, $updateInput);
        $savedClaim = DB::table('claims')->where('public_id', $claim['claim_id'])->firstOrFail();
        $savedEvents = DB::table('claim_events')->where('claim_id', $savedClaim->id)->orderBy('sequence')->get();
        $this->assertCount(2, $savedEvents);
        // Synthetic forced archived state: production archive MUST still reject linked products/invoices.
        $this->outlet->forceFill(['archived_at' => now()])->save();
        foreach ([$openKey, (string) Str::uuid()] as $key) {
            try {
                $service->open($this->actor, $this->outlet, $key, $openInput);
                $this->fail('Archived outlet accepted a new claim or completed open replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        foreach ([$updateKey, (string) Str::uuid()] as $key) {
            try {
                $service->update($this->actor, $this->outlet,
                    $claim['claim_id'], $key, $updateInput);
                $this->fail('Archived outlet accepted a claim update or completed replay.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        try {
            $service->view($this->actor, $this->outlet, $claim['claim_id']);
            $this->fail('Archived claim leaked through operational view.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertEquals($savedClaim, DB::table('claims')->where('id', $savedClaim->id)->firstOrFail());
        $this->assertEquals($savedEvents, DB::table('claim_events')->where('claim_id', $savedClaim->id)
            ->orderBy('sequence')->get());
        foreach ($savedEvents as $event) {
            $this->assertSame(hash('sha256', $event->snapshot), $event->snapshot_sha256);
        }
    }

    public function test_claim_uses_sale_time_warranty_snapshot_and_exact_inclusive_expiry_boundary(): void
    {
        $product = $this->warrantiedProduct(false, 0, 1);
        $this->acquire($product, 3);
        $sale = $this->sell($product, 3);
        $product->forceFill(['warranty_type' => 'no_warranty', 'warranty_duration' => null])->save();
        CarbonImmutable::setTestNow('2026-09-02 10:00:00');
        $claim = app(ClaimOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'quantity' => 2, 'issue_description' => 'Synthetic covered issue',
        ]);
        $this->assertSame('received', $claim['status']);
        $this->assertSame('shop_warranty', $claim['warranty']['type']);
        $this->assertFalse($claim['warranty']['legacy_product_fallback']);
        $this->assertSame('2026-09-02 10:00:00.000000', $claim['warranty']['expires_at']);
        $this->assertSame('canonical-business-at-sale.v2', $claim['business']['contract']);
        $this->assertSame('mobisttech@gmail.com', $claim['business']['business_email']);
        $this->assertSame($this->actor->name, $claim['handled_by_name']);
        CarbonImmutable::setTestNow('2026-09-02 10:00:01');
        $this->reject(fn () => app(ClaimOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Expired synthetic issue',
        ]));
        $this->assertSame(1, DB::table('claims')->count());
    }

    public function test_lifecycle_is_transitioned_idempotently_with_append_only_event_snapshots_and_historical_output(): void
    {
        $product = $this->warrantiedProduct();
        $this->acquire($product);
        $sale = $this->sell($product);
        $service = app(ClaimOperations::class);
        $key = (string) Str::uuid();
        $input = ['sale_id' => $sale['sale_ids'][0], 'issue_description' => 'Synthetic issue', 'assigned_to' => 'Bench A'];
        $claim = $service->open($this->actor, $this->outlet, $key, $input);
        $this->assertEquals($claim, $service->open($this->actor, $this->outlet, $key, $input));
        $this->reject(fn () => $service->open($this->actor, $this->outlet, $key, [...$input, 'issue_description' => 'Changed']));
        $this->reject(fn () => $service->update($this->actor, $this->outlet, $claim['claim_id'], (string) Str::uuid(), $this->update('delivered')));
        foreach (['diagnosing', 'repaired', 'ready_for_collection', 'delivered', 'closed'] as $status) {
            $claim = $service->update($this->actor, $this->outlet, $claim['claim_id'], (string) Str::uuid(), $this->update($status));
        }
        $this->assertSame('closed', $claim['status']);
        $this->assertNotNull($claim['resolved_at']);
        $this->assertNotNull($claim['delivered_at']);
        $this->assertCount(6, $claim['activity_log']);
        $this->assertSame(6, DB::table('claim_events')->count());
        foreach (DB::table('claim_events')->orderBy('sequence')->get() as $event) {
            $this->assertSame($event->snapshot_sha256, hash('sha256', $event->snapshot));
        }
        $this->reject(fn () => $service->update($this->actor, $this->outlet, $claim['claim_id'], (string) Str::uuid(), $this->update('received')));
        $this->assertSame($claim, $service->view($this->actor, $this->outlet, $claim['claim_id']));
    }

    public function test_returned_quantity_and_active_claims_bound_quantity_and_permission(): void
    {
        $product = $this->warrantiedProduct();
        $this->acquire($product, 3);
        $sale = $this->sell($product, 3);
        app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic return', 'lines' => [[
                'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'condition' => 'opened', 'disposition' => 'sellable',
            ]],
        ]);
        $service = app(ClaimOperations::class);
        $service->open($this->actor, $this->outlet, (string) Str::uuid(), ['sale_id' => $sale['sale_ids'][0], 'quantity' => 2, 'issue_description' => 'Remaining quantity']);
        $this->reject(fn () => $service->open($this->actor, $this->outlet, (string) Str::uuid(), ['sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Excess']));
        $admin = new Admin;
        $admin->forceFill(['name' => 'Denied claims', 'email' => 'denied-claims@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => ['shops.enter']])->save();
        $admin->shops()->attach($this->outlet);
        $this->reject(fn () => $service->open($admin, $this->outlet, (string) Str::uuid(), ['sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Denied']));
    }

    public function test_serialized_claim_requires_the_exact_unreturned_sold_occurrence_and_one_active_job(): void
    {
        $product = $this->warrantiedProduct(true);
        $this->acquire($product, 2);
        $units = StockUnit::where('product_id', $product->id)->orderBy('id')->get();
        $this->imeis($product, $units[0], [1 => 'claim-imei-a1', 2 => 'claim-imei-a2']);
        $this->imeis($product, $units[1], [1 => 'claim-imei-b1', 2 => 'claim-imei-b2']);
        $sale = $this->sell($product, 2);
        $service = app(ClaimOperations::class);
        $claim = $service->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'stock_unit_id' => $units[0]->public_id, 'quantity' => 99, 'issue_description' => 'Handset issue',
        ]);
        $this->assertSame(1, $claim['quantity']);
        $this->assertSame(['claim-imei-a1', 'claim-imei-a2'], $claim['imeis']);
        $this->reject(fn () => $service->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'stock_unit_id' => $units[0]->public_id, 'issue_description' => 'Duplicate active job',
        ]));
        app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Returned other handset', 'lines' => [[
                'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'stock_unit_id' => $units[1]->public_id, 'condition' => 'opened', 'disposition' => 'sellable',
            ]],
        ]);
        $this->reject(fn () => $service->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'stock_unit_id' => $units[1]->public_id, 'issue_description' => 'Returned handset job',
        ]));
    }

    private function warrantiedProduct(bool $tracked = false, int $unit = 0, int $duration = 30)
    {
        $product = $this->product($tracked);
        $product->forceFill(['warranty_type' => 'shop_warranty', 'warranty_unit' => $unit, 'warranty_duration' => $duration])->save();

        return $product;
    }

    private function sell($product, int $quantity = 1): array
    {
        return app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => $quantity]],
        ]);
    }

    private function update(string $status): array
    {
        return ['status' => $status, 'assigned_to' => 'Bench A', 'diagnosis' => 'Synthetic diagnosis', 'resolution' => 'Synthetic resolution',
            'internal_notes' => 'Synthetic internal note', 'customer_satisfied' => true, 'follow_up_required' => false];
    }
}
