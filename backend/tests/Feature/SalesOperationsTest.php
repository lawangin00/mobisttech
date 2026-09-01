<?php

namespace Tests\Feature;

use App\Models\StockUnit;
use App\Sales\SalesOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class SalesOperationsTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_sale_uses_server_prices_allocates_exact_discount_and_replays_once(): void
    {
        $first = $this->product();
        $second = $this->product();
        $this->acquire($first, 2);
        $this->acquire($second, 1);
        $key = (string) Str::uuid();
        $input = ['new_customer' => true, 'customer_name' => 'Synthetic buyer', 'customer_phone' => '03000000000',
            'discount' => '0.03', 'discount_reason' => 'Approved synthetic discount', 'lines' => [
                ['product_id' => $first->public_id, 'quantity' => 2], ['product_id' => $second->public_id, 'quantity' => 1],
            ]];
        $result = app(SalesOperations::class)->sell($this->actor, $this->outlet, $key, $input);
        $this->assertEquals($result, app(SalesOperations::class)->sell($this->actor, $this->outlet, $key, $input));
        $this->assertSame('600.06', $result['total_bill']);
        $this->assertSame('600.03', $result['final_bill']);
        $this->assertSame('0.03', DB::table('sales')->sum('discount_allocated'));
        $this->assertSame('600.03', DB::table('sales')->sum('net_total_price'));
        $this->assertSame(1, DB::table('invoices')->count());
        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(1, DB::table('monetary_adjustments')->count());
        $this->assertSame(0, $first->fresh()->qty);
        $this->assertSame(2, $first->fresh()->sold_qty);
        $this->reject(fn () => app(SalesOperations::class)->sell($this->actor, $this->outlet, $key, [...$input, 'discount' => '0.04']));
    }

    public function test_quantity_return_is_bounded_compensating_and_does_not_create_a_refund(): void
    {
        $product = $this->product();
        $this->acquire($product, 3);
        $sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'customer_name' => 'Guest', 'discount' => '0.02', 'discount_reason' => 'Synthetic',
            'lines' => [['product_id' => $product->public_id, 'quantity' => 3]],
        ]);
        $saleId = $sale['sale_ids'][0];
        $key = (string) Str::uuid();
        $input = ['invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic accepted return', 'lines' => [[
            'sale_id' => $saleId, 'quantity' => 2, 'condition' => 'opened', 'disposition' => 'sellable',
        ]]];
        $returned = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, $key, $input);
        $this->assertEquals($returned, app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, $key, $input));
        $this->assertSame('400.03', $returned['refund_due']);
        $this->assertSame('not_created', $returned['refund_status']);
        $this->assertSame(2, DB::table('sales')->where('public_id', $saleId)->value('returned_quantity'));
        $this->assertSame(2, $product->fresh()->qty);
        $this->assertSame(1, $product->fresh()->sold_qty);
        $this->assertSame('600.04', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('final_bill'));
        $this->assertSame(0, DB::table('refunds')->count());
        $this->reject(fn () => app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), $input));
    }

    public function test_serialized_return_creates_a_forward_successor_and_restores_imei_only_when_sellable(): void
    {
        $product = $this->product(true);
        $this->acquire($product);
        $this->imeis($product);
        $origin = StockUnit::where('product_id', $product->id)->firstOrFail();
        $sale = app(SalesOperations::class)->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
        ]);
        $returned = app(SalesOperations::class)->acceptReturn($this->actor, $this->outlet, (string) Str::uuid(), [
            'invoice_id' => $sale['invoice_id'], 'reason' => 'Synthetic serialized return', 'lines' => [[
                'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'stock_unit_id' => $origin->public_id,
                'condition' => 'opened', 'disposition' => 'sellable',
            ]],
        ]);
        $line = DB::table('return_lines')->where('public_id', $returned['return_line_ids'][0])->firstOrFail();
        $successor = StockUnit::findOrFail($line->successor_stock_unit_id);
        $this->assertGreaterThan($origin->id, $successor->id);
        $this->assertSame('sold', $origin->fresh()->status);
        $this->assertSame('in_stock', $successor->status);
        $this->assertSame(2, DB::table('active_imeis')->where('stock_unit_id', $successor->id)->count());
        $this->assertSame('customer_return', DB::table('stock_unit_lineage')->where('source_unit_id', $origin->id)->value('reason'));
        $this->assertSame(1, $product->fresh()->qty);
    }
}
