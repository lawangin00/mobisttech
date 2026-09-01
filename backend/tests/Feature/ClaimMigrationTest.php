<?php

namespace Tests\Feature;

use App\Migration\ClaimImporter;
use App\Sales\SalesOperations;
use App\Warranty\ClaimOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class ClaimMigrationTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    private int $run;

    private array $sale;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
        $this->run = DB::table('migration_runs')->insertGetId(['input_manifest_hash' => str_repeat('1', 64), 'code_hash' => str_repeat('2', 64),
            'schema_hash' => str_repeat('3', 64), 'target_identity' => 'mobisttech_test', 'status' => 'claim_rehearsal', 'started_at' => now()]);
        $product = $this->product();
        $product->forceFill(['warranty_type' => 'shop_warranty', 'warranty_unit' => 2, 'warranty_duration' => 2])->save();
        $this->acquire($product, 2);
        $this->sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 2]],
        ]);
        $invoice = DB::table('invoices')->where('public_id', $this->sale['invoice_id'])->firstOrFail();
        $sale = DB::table('sales')->where('public_id', $this->sale['sale_ids'][0])->firstOrFail();
        $this->map('users', 7, 'outlets', $this->outlet->id);
        $this->map('products', 8, 'products', $product->id);
        $this->map('invoices', 9, 'invoices', $invoice->id);
        $this->map('sales', 10, 'sales', $sale->id);
    }

    public function test_claim_history_import_preserves_identifiers_snapshots_activity_timezone_and_replay(): void
    {
        $row = $this->row(['id' => 20, 'product_id' => 8, 'invoice_id' => 9, 'shop_id' => 7, 'sale_id' => 10, 'quantity' => 1,
            'claim_number' => 'MST-CLM-024-20260101-0020', 'status' => 'delivered', 'issue_description' => 'Historic issue',
            'created_at' => '2026-01-01 12:00:00', 'received_at' => '2026-01-01 12:00:00', 'resolved_at' => '2026-01-02 12:00:00',
            'delivered_at' => '2026-01-03 12:00:00', 'activity_log' => [
                ['at' => '2026-01-01T12:00:00+05:00', 'status' => 'received', 'note' => 'Historic receipt', 'actor' => 'Legacy operator'],
                ['at' => '2026-01-03T12:00:00+05:00', 'status' => 'delivered', 'note' => 'Historic delivery', 'actor' => 'Legacy operator'],
            ]]);
        $importer = app(ClaimImporter::class);
        $result = $importer->import($this->run, $row, 'Asia/Karachi');
        $this->assertSame('imported', $result['outcome'], json_encode($result));
        $this->assertSame('already_imported', $importer->import($this->run, $row, 'Asia/Karachi')['outcome']);
        $claim = DB::table('claims')->where('id', $result['target_id'])->firstOrFail();
        $this->assertSame('MST-CLM-024-20260101-0020', $claim->claim_number);
        $this->assertSame('2026-01-01 07:00:00.000000', $claim->received_at);
        $this->assertSame('sale-warranty.v1', json_decode($claim->warranty_snapshot, true)['contract']);
        $this->assertTrue(Str::isUuid($claim->public_id));
        $this->assertSame(2, DB::table('claim_events')->where('claim_id', $claim->id)->count());
        $this->assertSame('2026-01-01 07:00:00.000000', DB::table('claim_events')->where('claim_id', $claim->id)->where('sequence', 1)->value('occurred_at'));

        $legacy = $importer->import($this->run, [...$row, 'id' => 21, 'sale_id' => null, 'claim_number' => null, 'activity_log' => null], 'Asia/Karachi');
        $this->assertSame('imported', $legacy['outcome'], json_encode($legacy));
        $legacyClaim = DB::table('claims')->where('id', $legacy['target_id'])->firstOrFail();
        $this->assertSame('unresolved_historical', json_decode($legacyClaim->warranty_snapshot, true)['eligibility']);
        $updated = app(ClaimOperations::class)->update($this->actor, $this->outlet, $legacyClaim->public_id, (string) Str::uuid(), [
            'status' => 'closed', 'follow_up_required' => false,
        ]);
        $this->assertSame('closed', $updated['status']);
    }

    public function test_changed_bad_history_unresolved_ownership_and_active_overallocation_quarantine_without_partial_claim(): void
    {
        $base = $this->row(['id' => 30, 'product_id' => 8, 'invoice_id' => 9, 'shop_id' => 7, 'sale_id' => 10, 'quantity' => 2,
            'status' => 'received', 'issue_description' => 'Active historic issue', 'received_at' => '2026-01-01 12:00:00']);
        $importer = app(ClaimImporter::class);
        $this->assertSame('imported', $importer->import($this->run, $base, 'UTC')['outcome']);
        foreach ([[...$base, 'issue_description' => 'Changed'], [...$base, 'id' => 31, 'quantity' => 1],
            [...$base, 'id' => 32, 'sale_id' => 999], [...$base, 'id' => 33, 'status' => 'invented'],
            [...$base, 'id' => 34, 'activity_log' => [['at' => 'bad', 'status' => 'received', 'note' => 'Bad time']]],
            [...$base, 'id' => 35, 'extra' => 'unknown']] as $bad) {
            $this->assertSame('quarantined', $importer->import($this->run, $bad, 'UTC')['outcome']);
        }
        $this->assertSame(1, DB::table('claims')->count());
        $this->assertSame(0, DB::table('claim_events')->count());
    }

    private function map(string $source, int $old, string $target, int $id): void
    {
        DB::table('migration_identity_map')->insert(['source_repository' => 'pos', 'source_table' => $source, 'source_primary_key' => (string) $old,
            'target_table' => $target, 'target_id' => (string) $id, 'run_id' => $this->run, 'row_digest' => str_repeat('a', 64), 'outcome' => 'synthetic_parent']);
    }

    private function row(array $overrides): array
    {
        $manifest = json_decode(file_get_contents(base_path('../docs/schema/COLUMN_DESTINATIONS.json')), true);
        $entry = collect($manifest['tables'])->first(fn ($entry) => $entry['source'] === 'pos' && $entry['table'] === 'claims');
        $row = [];
        foreach ($entry['columns'] as $column) {
            $type = $column['target_type'];
            $row[$column['source_column']] = $type['nullable'] ? null : match ($type['type']) {
                'boolean' => false, 'integer', 'bigInteger', 'smallInteger', 'tinyInteger' => 1, 'json' => [], default => 'Synthetic',
            };
        }

        return [...$row, ...$overrides];
    }
}
