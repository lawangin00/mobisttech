<?php

namespace Tests\Feature;

use App\Inventory\TransactionalStock;
use App\Models\Admin;
use App\Payments\PosPaymentOperations;
use App\Repairs\PaidRepairOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PaidRepairOperationsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_disable_blocks_new_intake_but_existing_job_and_history_remain_operable(): void
    {
        $service = app(PaidRepairOperations::class);
        $enabled = $service->configure($this->actor, $this->outlet, 'repair-enable-'.Str::uuid(), ['enabled' => true]);
        $this->assertTrue($enabled['enabled']);
        $job = $service->open($this->actor, $this->outlet, 'repair-open-'.Str::uuid(), $this->intake('IMEI-PAID-001'));
        $this->assertSame('received', $job['status']);
        $disabled = $service->configure($this->actor, $this->outlet, 'repair-disable-'.Str::uuid(), ['enabled' => false, 'version' => 1]);
        $this->assertFalse($disabled['enabled']);
        $this->reject(fn () => $service->open($this->actor, $this->outlet, 'repair-blocked-'.Str::uuid(), $this->intake('IMEI-PAID-002')));
        $updated = $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-diagnose-'.Str::uuid(), [
            'status' => 'diagnosing', 'diagnosis' => 'Charging circuit requires paid repair.',
        ]);
        $this->assertSame('diagnosing', $updated['status']);
        $this->assertSame(1, DB::table('repair_jobs')->count());
        $this->assertGreaterThanOrEqual(2, DB::table('repair_events')->where('repair_job_id', DB::table('repair_jobs')->value('id'))->count());
        $this->assertSame(0, DB::table('claims')->count(), 'Paid repair must remain distinct from warranty claims.');

        $unauthorized = new Admin;
        $unauthorized->forceFill(['name' => 'No repair grant', 'email' => 'no-repair@example.invalid',
            'password' => 'SyntheticPass123!', 'permissions' => []])->save();
        $unauthorized->shops()->attach($this->outlet);
        $this->reject(fn () => $service->view($unauthorized, $this->outlet, $job['repair_id']));
    }

    public function test_archived_outlet_denies_paid_repair_configuration_replays_and_new_intake(): void
    {
        $service = app(PaidRepairOperations::class);
        $configureKey = (string) Str::uuid();
        $input = ['enabled' => true];
        $service->configure($this->actor, $this->outlet, $configureKey, $input);
        $jobKey = (string) Str::uuid();
        $intake = $this->intake('D03-PAID-ARCHIVE');
        $job = $service->open($this->actor, $this->outlet, $jobKey, $intake);
        $beforeJob = DB::table('repair_jobs')->where('public_id', $job['repair_id'])->firstOrFail();
        $beforeSettings = DB::table('repair_settings')->where('outlet_id', $this->outlet->id)->firstOrFail();
        $beforeEvents = DB::table('repair_events')->where('repair_job_id', $beforeJob->id)->get();
        // Synthetic forced archived state only: actual repair-bearing outlet remains ineligible.
        $this->outlet->forceFill(['archived_at' => now()])->save();
        foreach ([
            fn () => $service->configure($this->actor, $this->outlet, $configureKey, $input),
            fn () => $service->open($this->actor, $this->outlet, $jobKey, $intake),
            fn () => $service->configure($this->actor, $this->outlet, (string) Str::uuid(), ['enabled' => false]),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Archived outlet accepted paid repair mutation or completed replay.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertEquals($beforeSettings, DB::table('repair_settings')->where('outlet_id', $this->outlet->id)->firstOrFail());
        $this->assertEquals($beforeJob, DB::table('repair_jobs')->where('id', $beforeJob->id)->firstOrFail());
        $this->assertEquals($beforeEvents, DB::table('repair_events')->where('repair_job_id', $beforeJob->id)->get());
        $this->assertSame(1, DB::table('repair_jobs')->where('outlet_id', $this->outlet->id)->count());
    }

    public function test_approved_estimate_owns_parts_payment_and_full_lifecycle(): void
    {
        $service = app(PaidRepairOperations::class);
        $service->configure($this->actor, $this->outlet, 'repair-enable-'.Str::uuid(), ['enabled' => true]);
        $part = $this->product();
        $this->acquire($part, 1);
        $job = $service->open($this->actor, $this->outlet, 'repair-open-'.Str::uuid(), $this->intake('SERIAL-PAID-100'));
        $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-diagnose-'.Str::uuid(), [
            'status' => 'diagnosing', 'diagnosis' => 'Replace charging flex and perform labor.',
        ]);
        $estimate = $service->estimate($this->actor, $this->outlet, $job['repair_id'], 'repair-estimate-'.Str::uuid(), [
            'lines' => [
                ['type' => 'part', 'product_id' => $part->public_id, 'description' => 'Charging flex', 'quantity' => 1, 'unit_price' => '9999.99'],
                ['type' => 'labor', 'description' => 'Repair labor', 'quantity' => 1, 'unit_price' => '50.00'],
            ],
        ]);
        $this->assertSame('200.02', $estimate['parts_total'], 'Part price must come from server catalogue, not client input.');
        $this->assertSame('250.02', $estimate['grand_total']);
        $approved = $service->decideEstimate($this->actor, $this->outlet, $job['repair_id'], $estimate['estimate_id'],
            'repair-approve-'.Str::uuid(), true);
        $this->assertSame('approved', $approved['status']);

        $reservation = $this->reservation($part);
        app(TransactionalStock::class)->reserve($reservation['line']);
        $this->reject(fn () => $service->consumeParts($this->actor, $this->outlet, $job['repair_id'], 'repair-held-'.Str::uuid()));
        app(TransactionalStock::class)->release($reservation['reservation']);
        $parts = $service->consumeParts($this->actor, $this->outlet, $job['repair_id'], 'repair-parts-'.Str::uuid());
        $this->assertCount(1, $parts['parts']);
        $this->assertSame(0, $part->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'repair_part')->count());
        $replayed = $service->consumeParts($this->actor, $this->outlet, $job['repair_id'], 'repair-parts-'.Str::uuid());
        $this->assertSame($parts, $replayed);
        $this->assertSame(1, DB::table('repair_part_consumptions')->count());

        $payments = app(PosPaymentOperations::class);
        $destination = $payments->createDestination($this->actor, $this->outlet, 'repair-destination-'.Str::uuid(), [
            'method' => 'bank_transfer', 'display_name' => 'Repair settlement account',
        ]);
        $wrong = ['payments' => [['method' => 'bank_transfer', 'destination_id' => $destination['destination_id'], 'amount' => '249.02']]];
        $this->reject(fn () => $payments->collectRepair($this->actor, $this->outlet, $job['repair_id'], 'repair-underpay-'.Str::uuid(), $wrong));
        $this->assertNull(DB::table('repair_jobs')->where('public_id', $job['repair_id'])->value('invoice_id'));
        $correct = ['payments' => [['method' => 'bank_transfer', 'destination_id' => $destination['destination_id'], 'amount' => '250.02']]];
        $paymentKey = 'repair-pay-'.Str::uuid();
        $paid = $payments->collectRepair($this->actor, $this->outlet, $job['repair_id'], $paymentKey, $correct);
        $this->assertSame('250.02', $paid['paid_amount']);
        $paidReplay = $payments->collectRepair($this->actor, $this->outlet, $job['repair_id'], $paymentKey, $correct);
        $this->assertSame('250.02', $paidReplay['paid_amount']);
        $this->assertSame(1, DB::table('repair_payment_links')->count());
        $this->assertSame(1, DB::table('pos_tender_allocations')->count());
        $invoice = DB::table('invoices')->where('public_id', $paid['invoice_id'])->firstOrFail();
        $this->assertSame('250.02', $invoice->final_bill);

        $ready = $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-ready-'.Str::uuid(), [
            'status' => 'ready_for_collection',
        ]);
        $this->assertSame('ready_for_collection', $ready['status']);
        $delivered = $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-deliver-'.Str::uuid(), [
            'status' => 'delivered',
        ]);
        $this->assertSame('delivered', $delivered['status']);
        $closed = $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-close-'.Str::uuid(), [
            'status' => 'closed',
        ]);
        $this->assertSame('closed', $closed['status']);
        $this->assertNull(DB::table('repair_jobs')->where('public_id', $job['repair_id'])->value('active_identifier'));
        $this->assertGreaterThanOrEqual(7, count($closed['events']));
    }

    public function test_rejected_estimate_remains_immutable_when_revised_estimate_is_approved(): void
    {
        $service = app(PaidRepairOperations::class);
        $service->configure($this->actor, $this->outlet, 'repair-enable-'.Str::uuid(), ['enabled' => true]);
        $job = $service->open($this->actor, $this->outlet, 'repair-open-'.Str::uuid(), $this->intake('SERIAL-HISTORY-001'));
        $service->update($this->actor, $this->outlet, $job['repair_id'], 'repair-diagnose-'.Str::uuid(), [
            'status' => 'diagnosing', 'diagnosis' => 'Labor-only paid repair.',
        ]);
        $first = $service->estimate($this->actor, $this->outlet, $job['repair_id'], 'repair-estimate-a-'.Str::uuid(), [
            'notes' => 'First proposal', 'lines' => [['type' => 'labor', 'description' => 'Initial labor', 'quantity' => 1, 'unit_price' => '100.00']],
        ]);
        $firstSha = DB::table('repair_estimates')->where('public_id', $first['estimate_id'])->value('snapshot_sha256');
        $service->decideEstimate($this->actor, $this->outlet, $job['repair_id'], $first['estimate_id'],
            'repair-reject-'.Str::uuid(), false);
        $second = $service->estimate($this->actor, $this->outlet, $job['repair_id'], 'repair-estimate-b-'.Str::uuid(), [
            'notes' => 'Revised proposal', 'lines' => [['type' => 'labor', 'description' => 'Revised labor', 'quantity' => 1, 'unit_price' => '150.00']],
        ]);
        $service->decideEstimate($this->actor, $this->outlet, $job['repair_id'], $second['estimate_id'],
            'repair-approve-'.Str::uuid(), true);
        $history = $service->view($this->actor, $this->outlet, $job['repair_id']);
        $this->assertCount(2, $history['estimates']);
        $this->assertSame('rejected', $history['estimates'][0]['status']);
        $this->assertSame('100.00', $history['estimates'][0]['grand_total']);
        $this->assertSame('approved', $history['estimates'][1]['status']);
        $this->assertSame('150.00', $history['estimates'][1]['grand_total']);
        $this->assertSame($firstSha, DB::table('repair_estimates')->where('public_id', $first['estimate_id'])->value('snapshot_sha256'));
    }

    private function intake(string $identifier): array
    {
        return [
            'customer_name' => 'Paid Repair Customer', 'customer_phone' => '03005556666',
            'device_label' => 'Synthetic Phone', 'identifier_type' => str_starts_with($identifier, 'IMEI') ? 'imei' : 'serial',
            'identifier_value' => $identifier, 'issue_description' => 'Device requires out-of-warranty paid service.',
            'received_condition' => 'Used', 'accessories_received' => 'Handset only',
        ];
    }
}
