<?php

namespace Tests\Feature;

use App\Inventory\InventoryOperations;
use App\Inventory\StocktakeOperations;
use App\Inventory\TransactionalStock;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class StocktakeTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_archived_outlet_blocks_stocktake_mutations_replays_and_preserves_count_history(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $service = app(StocktakeOperations::class);
        $input = ['kind' => 'cycle', 'product_ids' => [$product->public_id]];
        $created = $service->start($this->actor, $this->outlet, 'd03-stocktake-start', $input);
        $line = $service->session($this->actor, $this->outlet, $created['stocktake_id'])['lines'][0];
        $countInput = ['line_version' => 1, 'counted_quantity' => 2];
        $count = $service->countLine($this->actor, $this->outlet, $created['stocktake_id'],
            $line['line_id'], 'd03-stocktake-count', $countInput);
        $beforeSession = DB::table('stocktake_sessions')->where('public_id', $created['stocktake_id'])->firstOrFail();
        $beforeLine = DB::table('stocktake_lines')->where('public_id', $line['line_id'])->firstOrFail();
        $beforeCount = DB::table('stocktake_counts')->where('stocktake_line_id', $beforeLine->id)->firstOrFail();
        $beforeKeys = DB::table('idempotency_requests')->where('actor_scope', Admin::class.':'.$this->actor->id)
            ->where('operation', 'like', 'stocktake.%')->count();
        // Synthetic forced archive state; historical stocktake/product outlets remain ineligible for real archival.
        $this->outlet->forceFill(['archived_at' => now()])->save();
        foreach (['d03-stocktake-start', 'd03-stocktake-new'] as $key) {
            try {
                $service->start($this->actor, $this->outlet, $key, $input);
                $this->fail('Archived outlet accepted a stocktake start or completed-key replay.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        foreach (['d03-stocktake-count', 'd03-stocktake-new-count'] as $key) {
            try {
                $service->countLine($this->actor, $this->outlet, $created['stocktake_id'],
                    $line['line_id'], $key, $countInput);
                $this->fail('Archived outlet accepted a stocktake count or completed-key replay.');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        try {
            $service->approve($this->actor, $this->outlet, $created['stocktake_id'],
                'd03-stocktake-approve', ['session_version' => $count['session_version']]);
            $this->fail('Archived outlet accepted stocktake approval.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertEquals($beforeSession, DB::table('stocktake_sessions')->where('id', $beforeSession->id)->firstOrFail());
        $this->assertEquals($beforeLine, DB::table('stocktake_lines')->where('id', $beforeLine->id)->firstOrFail());
        $this->assertEquals($beforeCount, DB::table('stocktake_counts')->where('id', $beforeCount->id)->firstOrFail());
        $this->assertSame($beforeKeys, DB::table('idempotency_requests')->where('actor_scope', Admin::class.':'.$this->actor->id)
            ->where('operation', 'like', 'stocktake.%')->count());
        $this->assertSame(2, (int) $product->fresh()->qty);
        $this->assertSame(0, DB::table('stocktake_approvals')->where('stocktake_session_id', $beforeSession->id)->count());
    }

    public function test_cycle_scope_idempotency_permissions_and_outlet_boundaries_are_enforced(): void
    {
        $product = $this->product();
        $this->acquire($product);
        $service = app(StocktakeOperations::class);
        $input = ['kind' => 'cycle', 'product_ids' => [$product->public_id], 'notes' => 'Synthetic cycle count'];
        $created = $service->start($this->actor, $this->outlet, 'stocktake-start', $input);
        $this->assertEquals($created, $service->start($this->actor, $this->outlet, 'stocktake-start', $input));
        $this->reject(fn () => $service->start($this->actor, $this->outlet, 'stocktake-start', [...$input, 'notes' => 'Changed replay']));
        $session = $service->session($this->actor, $this->outlet, $created['stocktake_id']);
        $this->assertSame('counting', $session['status']);
        $this->assertSame(1, $session['lines'][0]['baseline_quantity']);
        $this->assertSame($product->public_id, $session['lines'][0]['product_id']);

        $limited = new Admin;
        $limited->forceFill(['name' => 'Limited stocktake', 'email' => 'limited-stocktake@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.inventory']])->save();
        $limited->shops()->attach($this->outlet);
        $this->reject(fn () => $service->start($limited, $this->outlet, 'limited', $input));

        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other stocktake outlet', 'outlet_code' => '027'])->save();
        $this->reject(fn () => $service->session($this->actor, $other, $created['stocktake_id']));
    }

    public function test_full_stocktake_selects_active_catalogue_and_count_only_actor_cannot_approve(): void
    {
        $products = [$this->product(), $this->product()];
        $this->acquire($products[0], 2);
        $counter = new Admin;
        $counter->forceFill(['name' => 'Stock counter', 'email' => 'stock-counter@example.invalid', 'password' => 'SyntheticPass123!',
            'permissions' => ['shops.enter', 'shop.stocktake']])->save();
        $counter->shops()->attach($this->outlet);
        $service = app(StocktakeOperations::class);
        $started = $service->start($counter, $this->outlet, 'full-start', ['kind' => 'full']);
        $session = $service->session($counter, $this->outlet, $started['stocktake_id']);
        $this->assertCount(2, $session['lines']);
        $last = null;
        foreach ($session['lines'] as $line) {
            $last = $service->countLine($counter, $this->outlet, $started['stocktake_id'], $line['line_id'], 'full-count-'.$line['line_id'], [
                'line_version' => 1, 'counted_quantity' => $line['baseline_quantity'],
            ]);
        }
        $this->assertSame('submitted', $last['status']);
        $this->reject(fn () => $service->approve($counter, $this->outlet, $started['stocktake_id'], 'counter-approve', ['session_version' => $last['session_version']]));
        $approved = $service->approve($this->actor, $this->outlet, $started['stocktake_id'], 'manager-approve', ['session_version' => $last['session_version']]);
        $this->assertSame(0, $approved['adjustment_movements']);
    }

    public function test_explicit_baseline_reconciles_sale_and_reservation_before_count(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        [$stocktake, $line] = $this->startLine($product);
        $reservation = $this->reservation($product);
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($reservation['line']));
        app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $result = app(StocktakeOperations::class)->countLine($this->actor, $this->outlet, $stocktake, $line, 'baseline-count', [
            'line_version' => 1, 'counted_quantity' => 2,
        ]);
        $this->assertSame(2, $result['expected_quantity']);
        $this->assertSame(0, $result['variance']);
        $this->assertSame('submitted', $result['status']);
        $approval = app(StocktakeOperations::class)->approve($this->actor, $this->outlet, $stocktake, 'baseline-approve', ['session_version' => $result['session_version']]);
        $this->assertSame(0, $approval['adjustment_movements']);
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertSame(1, DB::table('reservation_allocations')->whereNull('released_at')->count());
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'sale')->count());
    }

    public function test_quantity_variance_requires_reason_is_idempotent_and_only_approval_adjusts_stock(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        [$stocktake, $line] = $this->startLine($product);
        $service = app(StocktakeOperations::class);
        $this->reject(fn () => $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'missing-reason', [
            'line_version' => 1, 'counted_quantity' => 2,
        ]));
        $input = ['line_version' => 1, 'counted_quantity' => 2, 'reason_code' => 'loss', 'reason_notes' => 'Synthetic shelf variance'];
        $count = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'count-once', $input);
        $this->assertEquals($count, $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'count-once', $input));
        $this->reject(fn () => $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'count-once', [...$input, 'counted_quantity' => 1]));
        $this->reject(fn () => $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'duplicate-count', $input));
        $this->assertSame(3, $product->fresh()->qty, 'Counting must never mutate authoritative stock.');
        $this->reject(fn () => $service->approve($this->actor, $this->outlet, $stocktake, 'stale-approval', ['session_version' => 1]));
        $approval = $service->approve($this->actor, $this->outlet, $stocktake, 'approve-once', ['session_version' => $count['session_version'], 'notes' => 'Approved synthetic variance']);
        $this->assertEquals($approval, $service->approve($this->actor, $this->outlet, $stocktake, 'approve-once', ['session_version' => $count['session_version'], 'notes' => 'Approved synthetic variance']));
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertSame(1, DB::table('stock_movements')->where('type', 'stocktake_adjustment')->where('quantity_change', -1)->count());
        $row = DB::table('stocktake_approvals')->first();
        $this->assertSame(hash('sha256', $row->approval_snapshot), $row->snapshot_sha256);
    }

    public function test_recount_preserves_prior_count_history_and_replaces_only_the_current_iteration(): void
    {
        $product = $this->product();
        $this->acquire($product);
        [$stocktake, $line] = $this->startLine($product);
        $service = app(StocktakeOperations::class);
        $first = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'first-count', [
            'line_version' => 1, 'counted_quantity' => 0, 'reason_code' => 'count_error', 'reason_notes' => 'Needs recount',
        ]);
        $recount = $service->requestRecount($this->actor, $this->outlet, $stocktake, 'recount', [
            'session_version' => $first['session_version'], 'line_id' => $line, 'reason' => 'Independent recount requested',
        ]);
        $second = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'second-count', [
            'line_version' => $recount['line_version'], 'counted_quantity' => 1,
        ]);
        $this->assertSame(2, DB::table('stocktake_counts')->count());
        $this->assertSame(1, DB::table('stocktake_recounts')->count());
        $this->assertSame([1, 2], DB::table('stocktake_counts')->orderBy('iteration')->pluck('iteration')->map(fn ($v) => (int) $v)->all());
        $approval = $service->approve($this->actor, $this->outlet, $stocktake, 'approve-recount', ['session_version' => $second['session_version']]);
        $this->assertSame(0, $approval['adjustment_movements']);
        $this->assertSame(1, $product->fresh()->qty);
    }

    public function test_serialized_missing_unit_uses_exact_count_snapshot_without_fabricating_history(): void
    {
        $product = $this->product(true);
        $this->acquire($product, 2);
        $units = StockUnit::where('product_id', $product->id)->orderBy('id')->get();
        $this->imeis($product, $units[0], [1 => '356000000000001', 2 => '356000000000002']);
        $this->imeis($product, $units[1], [1 => '356000000000003', 2 => '356000000000004']);
        [$stocktake, $line] = $this->startLine($product);
        $service = app(StocktakeOperations::class);
        $this->reject(fn () => $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'invent-unit', [
            'line_version' => 1, 'unit_ids' => [(string) Str::uuid()], 'reason_code' => 'found_stock',
        ]));
        $count = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'serialized-count', [
            'line_version' => 1, 'unit_ids' => [$units[0]->public_id], 'reason_code' => 'loss', 'reason_notes' => 'Serialized unit not found',
        ]);
        $this->assertSame(-1, $count['variance']);
        $approval = $service->approve($this->actor, $this->outlet, $stocktake, 'serialized-approve', ['session_version' => $count['session_version']]);
        $this->assertSame(1, $approval['adjustment_movements']);
        $this->assertSame(1, $product->fresh()->qty);
        $this->assertSame('adjusted_out', $units[1]->fresh()->status);
        $this->assertSame(2, DB::table('product_imeis')->where('stock_unit_id', $units[1]->id)->where('status', 'adjusted_out')->count());
        $this->assertSame(2, DB::table('active_imeis')->count());
        $this->assertDatabaseHas('stock_movements', ['type' => 'stocktake_adjustment', 'stock_unit_id' => $units[1]->id, 'quantity_change' => -1]);
    }

    public function test_post_count_physical_correction_invalidates_approval_and_requires_recount(): void
    {
        $product = $this->product();
        $this->acquire($product);
        [$stocktake, $line] = $this->startLine($product);
        $service = app(StocktakeOperations::class);
        $count = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'stale-count', [
            'line_version' => 1, 'counted_quantity' => 0, 'reason_code' => 'loss',
        ]);
        app(InventoryOperations::class)->adjust($this->actor, $this->outlet, $product->public_id, 'independent-correction', [
            'type' => 'correction_out', 'quantity' => 1, 'reason' => 'Independent reconciliation after physical count',
        ]);
        $this->reject(fn () => $service->approve($this->actor, $this->outlet, $stocktake, 'stale-after-correction', ['session_version' => $count['session_version']]));
        $this->assertSame(0, $product->fresh()->qty);
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'stocktake_adjustment')->count());
        $this->assertSame('submitted', DB::table('stocktake_sessions')->where('public_id', $stocktake)->value('status'));
    }

    public function test_positive_quantity_variance_is_audited_correction_not_a_silent_counter_overwrite(): void
    {
        $product = $this->product();
        $this->acquire($product);
        [$stocktake, $line] = $this->startLine($product);
        $service = app(StocktakeOperations::class);
        $count = $service->countLine($this->actor, $this->outlet, $stocktake, $line, 'found-count', [
            'line_version' => 1, 'counted_quantity' => 2, 'reason_code' => 'found_stock', 'reason_notes' => 'One verified loose unit found',
        ]);
        $service->approve($this->actor, $this->outlet, $stocktake, 'found-approve', ['session_version' => $count['session_version']]);
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertDatabaseHas('stock_movements', ['type' => 'stocktake_adjustment', 'quantity_change' => 1, 'reference_type' => 'stocktake_line']);
    }

    public function test_multi_line_approval_rolls_back_every_adjustment_when_a_later_line_becomes_held(): void
    {
        $products = [$this->product(), $this->product()];
        foreach ($products as $product) {
            $this->acquire($product);
        }
        $service = app(StocktakeOperations::class);
        $started = $service->start($this->actor, $this->outlet, 'rollback-start', ['kind' => 'cycle', 'product_ids' => array_map(fn ($p) => $p->public_id, $products)]);
        $session = $service->session($this->actor, $this->outlet, $started['stocktake_id']);
        $lines = collect($session['lines'])->keyBy('product_id');
        $first = $service->countLine($this->actor, $this->outlet, $started['stocktake_id'], $lines[$products[0]->public_id]['line_id'], 'rollback-count-1', [
            'line_version' => 1, 'counted_quantity' => 0, 'reason_code' => 'loss',
        ]);
        $second = $service->countLine($this->actor, $this->outlet, $started['stocktake_id'], $lines[$products[1]->public_id]['line_id'], 'rollback-count-2', [
            'line_version' => 1, 'counted_quantity' => 0, 'reason_code' => 'loss',
        ]);
        $reservation = $this->reservation($products[1]);
        DB::transaction(fn () => app(TransactionalStock::class)->reserve($reservation['line']));
        $this->reject(fn () => $service->approve($this->actor, $this->outlet, $started['stocktake_id'], 'rollback-approve', ['session_version' => $second['session_version']]));
        $this->assertSame([1, 1], array_map(fn ($p) => $p->fresh()->qty, $products));
        $this->assertSame(0, DB::table('stock_movements')->where('type', 'stocktake_adjustment')->count());
        $this->assertSame(0, DB::table('stocktake_approvals')->count());
        $this->assertSame('submitted', DB::table('stocktake_sessions')->where('public_id', $started['stocktake_id'])->value('status'));
        $this->assertSame(-1, $first['variance']);
    }

    private function startLine($product): array
    {
        $service = app(StocktakeOperations::class);
        $started = $service->start($this->actor, $this->outlet, (string) Str::uuid(), ['kind' => 'cycle', 'product_ids' => [$product->public_id]]);
        $line = $service->session($this->actor, $this->outlet, $started['stocktake_id'])['lines'][0]['line_id'];

        return [$started['stocktake_id'], $line];
    }
}
