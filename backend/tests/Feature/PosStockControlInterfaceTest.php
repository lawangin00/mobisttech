<?php

namespace Tests\Feature;

use App\Catalog\ProductDefinitions;
use App\Models\Outlet;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PosStockControlInterfaceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_procurement_reorder_stocktake_and_bulk_http_interfaces_use_authoritative_services(): void
    {
        $product = $this->product();
        $client = $this->authenticatedClient();

        $index = $this->send($client, 'GET', '/internal/admin/pos/stock-control')->assertOk();
        $index->assertJsonPath('data.permissions.canProcure', true)
            ->assertJsonPath('data.permissions.canStocktake', true)
            ->assertJsonPath('data.permissions.canBulk', true);

        $supplier = $this->send($client, 'POST', '/internal/admin/pos/stock-control/suppliers', [
            'supplier_code' => 'HTTP45', 'name' => 'HTTP MT45 Supplier',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-supplier'])->assertOk()->json('data.supplier_id');

        $order = $this->send($client, 'POST', '/internal/admin/pos/stock-control/orders', [
            'supplier_id' => $supplier,
            'lines' => [['product_id' => $product->public_id, 'quantity' => 3, 'unit_cost' => '100.00', 'landed_unit_cost' => '105.00']],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-order'])->assertOk();
        $orderId = $order->json('data.purchase_order_id');
        $line = DB::table('purchase_order_lines')->where('purchase_order_id',
            DB::table('purchase_orders')->where('public_id', $orderId)->value('id'))->firstOrFail();

        $this->send($client, 'POST', '/internal/admin/pos/stock-control/orders/'.$orderId.'/receive', [
            'lines' => [['line_id' => $line->public_id, 'quantity' => 1, 'unit_cost' => '100.00', 'landed_unit_cost' => '105.00']],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-receive'])->assertOk()
            ->assertJsonPath('data.status', 'partially_received');
        $this->assertSame(1, $product->fresh()->qty);

        $policy = $this->send($client, 'POST', '/internal/admin/pos/stock-control/reorder/'.$product->public_id, [
            'reorder_threshold' => 2, 'target_stock' => 6, 'active' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-policy-1'])->assertOk();
        $this->send($client, 'POST', '/internal/admin/pos/stock-control/reorder/'.$product->public_id, [
            'version' => $policy->json('data.version'), 'reorder_threshold' => 1, 'target_stock' => 7, 'active' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-policy-2'])->assertOk()->assertJsonPath('data.version', 2);

        $started = $this->send($client, 'POST', '/internal/admin/pos/stock-control/stocktakes', [
            'kind' => 'cycle', 'product_ids' => [$product->public_id],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-stocktake'])->assertOk();
        $stocktakeId = $started->json('data.stocktake_id');
        $session = $this->send($client, 'GET', '/internal/admin/pos/stock-control/stocktakes/'.$stocktakeId)->assertOk();
        $stocktakeLine = $session->json('data.lines.0');
        $count = $this->send($client, 'POST', '/internal/admin/pos/stock-control/stocktakes/'.$stocktakeId.'/lines/'.$stocktakeLine['line_id'], [
            'line_version' => $stocktakeLine['line_version'], 'counted_quantity' => 1,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-count'])->assertOk();
        $count->assertJsonPath('data.status', 'submitted')->assertJsonPath('data.variance', 0);
        $this->send($client, 'POST', '/internal/admin/pos/stock-control/stocktakes/'.$stocktakeId.'/approve', [
            'session_version' => $count->json('data.session_version'),
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-approve'])->assertOk()->assertJsonPath('data.status', 'approved');

        $csv = "row_key,product_id,purchase_price,sale_price\r\nprice-1,{$product->public_id},110.00,210.00\r\n";
        $this->send($client, 'POST', '/internal/admin/pos/stock-control/bulk/preview', [
            'dataset' => 'price', 'format' => 'csv', 'document' => $csv,
        ])->assertOk()->assertJsonPath('data.valid', 1)->assertJsonPath('data.invalid', 0);
        $export = $this->send($client, 'POST', '/internal/admin/pos/stock-control/bulk/export', [
            'dataset' => 'inventory', 'format' => 'csv',
        ])->assertOk();
        $this->assertStringContainsString('product_id,product_code,name,track_imei,on_hand,held,available', $export->json('data.document'));
        $this->assertStringNotContainsString('seller_cnic', $export->json('data.document'));
    }

    public function test_transfer_http_interface_preserves_outlet_scope_partial_receive_and_reject(): void
    {
        $source = $this->product();
        $this->acquire($source, 3);
        [$destination, $target] = $this->destination($source);
        $client = $this->authenticatedClient();

        $created = $this->send($client, 'POST', '/internal/admin/pos/stock-control/transfers', [
            'destination_outlet_id' => $destination->public_id,
            'lines' => [['source_product_id' => $source->public_id, 'destination_product_id' => $target->public_id, 'quantity' => 2]],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-transfer'])->assertOk();
        $transferId = $created->json('data.transfer_id');

        $dispatch = $this->send($client, 'POST', '/internal/admin/pos/stock-control/transfers/'.$transferId.'/dispatch', [
            'transfer_version' => 1,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-dispatch'])->assertOk()->assertJsonPath('data.status', 'in_transit');

        $details = $this->send($client, 'GET', '/internal/admin/pos/stock-control/transfers/'.$transferId)->assertOk();
        $lineId = $details->json('data.lines.0.line_id');

        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $destination->public_id,
        ])->assertOk();

        $partial = $this->send($client, 'POST', '/internal/admin/pos/stock-control/transfers/'.$transferId.'/receive', [
            'transfer_version' => $dispatch->json('data.version'),
            'lines' => [['line_id' => $lineId, 'receive_quantity' => 1, 'reject_quantity' => 0]],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-transfer-receive-1'])->assertOk();
        $partial->assertJsonPath('data.status', 'partially_received')->assertJsonPath('data.remaining_quantity', 1);

        $final = $this->send($client, 'POST', '/internal/admin/pos/stock-control/transfers/'.$transferId.'/receive', [
            'transfer_version' => $partial->json('data.version'),
            'lines' => [['line_id' => $lineId, 'receive_quantity' => 0, 'reject_quantity' => 1]],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt45-transfer-receive-2'])->assertOk();
        $final->assertJsonPath('data.remaining_quantity', 0);
        $this->assertSame(2, $source->fresh()->qty);
        $this->assertSame(1, $target->fresh()->qty);
        $this->assertSame(0, DB::table('inventory_custody_holds')->whereNull('released_at')->count());
    }

    private function destination($source): array
    {
        $outlet = new Outlet;
        $outlet->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'HTTP MT45 Destination', 'outlet_code' => '045'])->save();
        $this->actor->shops()->attach($outlet);
        $product = app(ProductDefinitions::class)->save($this->actor, $outlet, [
            'name' => $source->name, 'category' => $source->category, 'brand_master_data_id' => $source->brand_master_data_id,
            'subcategory_master_data_id' => $source->subcategory_master_data_id, 'model' => $source->model,
            'purchase_price' => $source->purchase_price, 'sale_price' => $source->sale_price, 'track_imei' => false,
            'warranty_type' => 'no_warranty',
        ]);

        return [$outlet, $product];
    }

    private function authenticatedClient(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.5 browser'];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $this->actor->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $this->outlet->public_id])->assertOk();

        return $client;
    }

    private function send(array &$client, string $method, string $uri, array $data = [], bool $csrf = true, array $extraServer = [])
    {
        $server = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json', 'HTTP_USER_AGENT' => $client['agent'], ...$extraServer];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(app('encrypter')->decrypt($cookie->getValue(), false));
            }
        }

        return $response;
    }
}
