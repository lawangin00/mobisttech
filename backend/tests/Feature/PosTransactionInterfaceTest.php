<?php

namespace Tests\Feature;

use App\Cash\CashSessionOperations;
use App\Cms\WebsiteModePublication;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\StockUnit;
use App\Payments\PosPaymentOperations;
use App\Services\PosInventoryMasterData;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PosTransactionInterfaceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_inventory_http_contract_covers_safe_catalogue_scanning_definitions_stock_units_labels_and_pagination(): void
    {
        $tracked = $this->product(true);
        $this->acquire($tracked);
        $this->imeis($tracked);
        $active = $this->destination('cash', 'Safe drawer');
        $this->destination('card', 'Future terminal', ['effective_from' => now()->addDay()->toIso8601String()]);
        $this->destination('card', 'Inactive terminal', ['active' => false]);

        for ($i = 0; $i < 20; $i++) {
            $this->product();
        }

        $client = $this->authenticatedClient();
        $catalogue = $this->send($client, 'GET', '/internal/admin/pos/catalogue?page=1')->assertOk();
        $this->assertTrue($catalogue->json('data.has_more'));
        $this->assertCount(20, $catalogue->json('data.products'));
        $catalogue2 = $this->send($client, 'GET', '/internal/admin/pos/catalogue?page=2')->assertOk();
        $this->assertFalse($catalogue2->json('data.has_more'));
        $this->assertNotEmpty($catalogue2->json('data.products'));

        $destinations = $catalogue->json('data.payment_destinations');
        $this->assertCount(1, $destinations);
        $this->assertSame($active['destination_id'], $destinations[0]['public_id']);
        $this->assertArrayNotHasKey('internal_notes', $destinations[0]);
        $this->assertArrayNotHasKey('credentials', $destinations[0]);
        $this->assertArrayNotHasKey('secret', $destinations[0]);

        $unit = StockUnit::where('product_id', $tracked->id)->firstOrFail();
        $lookup = $this->send($client, 'GET', '/internal/admin/pos/lookup?q='.urlencode('Synthetic-IMEI-1'))->assertOk();
        $this->assertSame($tracked->public_id, $lookup->json('data.product.id'));
        $this->assertSame($unit->public_id, $lookup->json('data.unit.id'));
        $this->send($client, 'GET', '/internal/admin/pos/labels/unit/'.$unit->public_id)->assertOk()
            ->assertJsonPath('data.contract', 'retail-label.v1')
            ->assertJsonPath('data.unit_id', $unit->public_id);

        $category = PosMasterDataOption::where('list_key', 'product_category')->where('code', 'accessory')->firstOrFail();
        $created = $this->send($client, 'POST', '/internal/admin/pos/inventory/products', [
            'name' => 'HTTP accessory '.Str::uuid(), 'category' => 'accessory', 'category_master_data_id' => $category->id,
            'brand_master_data_id' => null, 'model' => null, 'purchase_price' => '80.00', 'sale_price' => '120.00',
            'track_imei' => false, 'warranty_type' => 'no_warranty',
        ])->assertOk();
        $productId = $created->json('data.id');

        $source = PosMasterDataOption::where('list_key', 'acquisition_source_type')->where('code', 'supplier')->firstOrFail();
        $this->send($client, 'POST', '/internal/admin/pos/inventory/products/'.$productId.'/acquire', [
            'quantity' => 2, 'unit_purchase_price' => '80.00', 'source_type_master_data_id' => $source->id,
            'business_name' => 'HTTP supplier', 'seller_phone' => '03000000000',
            'seller_address' => 'Synthetic HTTP address', 'reason' => 'HTTP receive',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt42-acquire-'.Str::uuid()])->assertOk();
        $this->send($client, 'POST', '/internal/admin/pos/inventory/products/'.$productId.'/adjust', [
            'type' => 'correction_out', 'quantity' => 1, 'reason' => 'HTTP correction', 'unit_id' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt42-adjust-'.Str::uuid()])->assertOk();

        $condition = PosMasterDataOption::where('list_key', 'unit_condition')->where('is_active', true)->firstOrFail();
        $this->send($client, 'PATCH', '/internal/admin/pos/inventory/units/'.$unit->public_id, [
            'condition_master_data_id' => $condition->id,
        ])->assertOk()->assertJsonPath('data.id', $unit->public_id);
    }

    public function test_fresh_pos_product_requires_explicit_publication_and_stays_stock_authoritative(): void
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $client = $this->authenticatedClient();
        $draft = app(WebsiteModePublication::class)->saveDraft($this->actor, 'hybrid');
        app(WebsiteModePublication::class)->publish($this->actor, $draft['id']);
        $slug = 'mt75-fresh-'.strtolower(Str::random(10));
        $public = '/api/v1/catalogue/products/'.$slug;
        $this->getJson($public)->assertNotFound();
        $url = '/internal/admin/pos/inventory/products/'.$product->public_id.'/website-listing';
        $input = ['slug' => $slug, 'description' => 'Fresh public product description.', 'is_online' => true, 'expected_version' => 0];
        $this->send($client, 'POST', $url, $input)->assertOk()->assertJsonPath('data.version', 1);
        $this->send($client, 'GET', '/internal/admin/pos/catalogue?mode=inventory')->assertOk()
            ->assertJsonPath('data.products.0.website_listing.slug', $slug);
        $visible = $this->getJson($public)->assertOk();
        $visible->assertJsonPath('data.name', $product->name)->assertJsonPath('data.price', '200.02')
            ->assertJsonPath('data.availability.quantity', 2);
        $this->assertStringNotContainsString('purchase_price', $visible->getContent());
        $this->assertStringNotContainsString('100.01', $visible->getContent());
        $this->send($client, 'POST', $url, $input)->assertStatus(409);
        $this->send($client, 'POST', $url, [...$input, 'slug' => '../invalid', 'expected_version' => 1])->assertUnprocessable();
        $this->acquire($product, 1);
        $this->getJson($public)->assertOk()->assertJsonPath('data.availability.quantity', 3);
        $this->send($client, 'POST', $url, [...$input, 'expected_version' => 1, 'is_online' => false])->assertOk()
            ->assertJsonPath('data.version', 2);
        $this->getJson($public)->assertNotFound();
        $this->assertSame(1, DB::table('product_listings')->where('product_id', $product->id)->count());
    }

    public function test_fresh_website_listing_denies_cross_outlet_and_inventory_only_roles(): void
    {
        $product = $this->product();
        $firstOutlet = $this->outlet;
        $client = $this->authenticatedClient();
        $url = '/internal/admin/pos/inventory/products/'.$product->public_id.'/website-listing';
        $payload = ['slug' => 'mt75-denial-'.strtolower(Str::random(8)),
            'description' => 'Synthetic cross-outlet access denial.', 'is_online' => true, 'expected_version' => 0];
        $second = new Outlet;
        $second->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'MT75 second outlet', 'outlet_code' => '026'])->save();
        $this->actor->shops()->attach($second);
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $second->public_id])->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos/catalogue?mode=inventory')->assertOk()
            ->assertJsonPath('data.products', []);
        $this->send($client, 'POST', $url, $payload)->assertNotFound();
        $this->assertSame(0, DB::table('product_listings')->where('product_id', $product->id)->count());
        $this->send($client, 'POST', '/internal/admin/outlets/select', ['outlet_id' => $firstOutlet->public_id])->assertOk();
        $this->actor->forceFill(['permissions' => ['shops.enter', 'shop.inventory']])->save();
        $this->send($client, 'POST', $url, $payload)->assertForbidden();
        $this->assertSame(0, DB::table('product_listings')->where('product_id', $product->id)->count());
    }

    public function test_existing_inventory_definition_requires_current_version_and_preserves_stock(): void
    {
        $product = $this->product(true);
        $originalBrand = $product->brand;
        app(PosInventoryMasterData::class)->update($product->brandMasterOption, ['label' => 'Updated managed brand']);
        $client = $this->authenticatedClient();
        $url = '/internal/admin/pos/inventory/products';
        $input = [
            'product_id' => $product->public_id, 'name' => $product->name,
            'category' => $product->category, 'category_master_data_id' => $product->category_master_data_id,
            'brand_master_data_id' => $product->brand_master_data_id, 'model' => 'Updated device model',
            'purchase_price' => '100.01', 'sale_price' => '230.02',
            'track_imei' => true, 'warranty_type' => 'no_warranty',
        ];
        $this->send($client, 'POST', $url, $input)->assertUnprocessable();
        $this->send($client, 'POST', $url, [...$input, 'expected_version' => $product->version + 1])->assertStatus(409);
        $saved = $this->send($client, 'POST', $url, [...$input, 'expected_version' => $product->version])->assertOk();
        $this->assertSame($product->public_id, $saved->json('data.id'));
        $this->assertSame($product->product_code, $product->fresh()->product_code);
        $this->assertSame('Updated device model', $product->fresh()->model);
        $this->assertSame('230.02', $product->fresh()->sale_price);
        $this->assertSame($originalBrand, $product->fresh()->brand);
        $listing = $this->send($client, 'GET', '/internal/admin/pos/catalogue?mode=inventory&category=product_name&q='.urlencode($product->name))->assertOk();
        $listing->assertJsonPath('data.products.0.brand_snapshot', $originalBrand)
            ->assertJsonPath('data.products.0.brand_display', 'Updated managed brand');
        $this->send($client, 'POST', $url, [...$input, 'expected_version' => $product->version])->assertStatus(409);
        $this->acquire($product);
        $this->send($client, 'POST', $url, [...$input, 'expected_version' => $product->fresh()->version, 'category' => 'accessory'])->assertUnprocessable();
        $this->assertSame('Updated device model', $product->fresh()->model);
    }

    public function test_inventory_portal_preferences_page_search_and_sales_catalogue_remain_independent(): void
    {
        $tracked = $this->product(true);
        $this->acquire($tracked);
        $this->imeis($tracked);
        $base = $this->product();
        $original = $base->getAttributes();
        unset($original['id']);
        $rows = [];
        for ($i = 1; $i <= 110; $i++) {
            $rows[] = [...$original, 'public_id' => (string) Str::uuid(), 'product_code' => null,
                'sku' => null, 'name' => sprintf('MT75 Inventory %03d', $i)];
        }
        DB::table('products')->insert($rows);
        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other outlet inventory',
            'outlet_code' => '087'])->save();
        DB::table('products')->insert([...$original, 'outlet_id' => $other->id,
            'public_id' => (string) Str::uuid(), 'product_code' => null, 'sku' => null,
            'name' => 'MT75 Inventory Other Outlet']);
        $client = $this->authenticatedClient();
        $url = '/internal/admin/pos/catalogue';
        $first = $this->send($client, 'GET', $url.'?mode=inventory')->assertOk();
        $first->assertJsonPath('data.pagination.total', 112)->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.pagination.pages', 12)->assertJsonPath('data.pagination.category', 'all')
            ->assertJsonCount(10, 'data.products');
        $this->send($client, 'GET', $url.'?mode=inventory&page=12')->assertOk()
            ->assertJsonPath('data.pagination.page', 12)->assertJsonCount(2, 'data.products');
        $this->send($client, 'GET', $url.'?mode=inventory&q=MT75%20Inventory%20110&category=product_name')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.products.0.name', 'MT75 Inventory 110');
        $this->send($client, 'GET', $url.'?mode=inventory&q=MT75%20Inventory%20Other%20Outlet&category=product_name')->assertOk()
            ->assertJsonPath('data.pagination.total', 0);
        $this->send($client, 'GET', $url.'?mode=inventory&q=Synthetic-IMEI-1&category=imei')->assertOk()
            ->assertJsonPath('data.pagination.total', 1)->assertJsonPath('data.products.0.id', $tracked->public_id);
        $this->send($client, 'GET', $url.'?mode=inventory&category=bad')->assertUnprocessable();
        $this->send($client, 'GET', $url.'?mode=inventory&outlet_id=087')->assertUnprocessable();
        $this->send($client, 'GET', $url.'?mode=inventory&page=0')->assertUnprocessable();
        $this->send($client, 'GET', $url)->assertOk()->assertJsonCount(20, 'data.products')
            ->assertJsonPath('data.pagination', null);
        foreach (['inventory_page_length' => '25', 'inventory_search_category' => 'sku'] as $key => $value) {
            DB::table('pos_settings')->insert(['key' => 'portal.'.$key, 'value' => $value,
                'group' => 'portal', 'label' => $key, 'input_type' => 'select', 'sort_order' => 200]);
        }
        $this->send($client, 'GET', $url.'?mode=inventory')->assertOk()
            ->assertJsonPath('data.pagination.per_page', 25)->assertJsonPath('data.pagination.category', 'sku')
            ->assertJsonPath('data.pagination.pages', 5)->assertJsonCount(25, 'data.products');
    }

    public function test_sale_quote_completion_split_tender_return_and_refund_stay_server_authoritative(): void
    {
        $product = $this->product();
        $this->acquire($product, 4);
        $cash = $this->destination('cash', 'HTTP cash drawer');
        $card = $this->destination('card', 'HTTP terminal', ['masked_identifier' => 'TERM-42']);
        app(CashSessionOperations::class)->open($this->actor, $this->outlet, 'mt42-cash-'.Str::uuid(), ['opening_cash' => '0.00']);

        $client = $this->authenticatedClient();
        $sale = ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]];
        $payments = [
            ['method' => 'cash', 'destination_id' => $cash['destination_id'], 'amount' => '50.02', 'cash_tendered' => '100.02'],
            ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '150.00', 'transaction_reference' => 'HTTP-APP-42'],
        ];

        $quote = $this->send($client, 'POST', '/internal/admin/pos/sales/quote', $sale + ['payments' => $payments])->assertOk();
        $quote->assertJsonPath('data.payable', '200.02')
            ->assertJsonPath('data.payments_total', '200.02')
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.cash_change', '50.00');

        $short = $payments;
        $short[1]['amount'] = '149.99';
        $this->send($client, 'POST', '/internal/admin/pos/sales/quote', $sale + ['payments' => $short])->assertOk()
            ->assertJsonPath('data.remaining', '0.01');

        $unsafe = ['sale' => $sale, 'payments' => [[
            'method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '200.02',
            'transaction_reference' => 'SAFE-REF', 'card_number' => '4111111111111111',
        ]]];
        $this->send($client, 'POST', '/internal/admin/pos/sales', $unsafe, true,
            ['HTTP_IDEMPOTENCY_KEY' => 'mt42-unsafe-'.Str::uuid()])->assertUnprocessable();

        $key = 'mt42-sale-'.Str::uuid();
        $completed = $this->send($client, 'POST', '/internal/admin/pos/sales', ['sale' => $sale, 'payments' => $payments], true,
            ['HTTP_IDEMPOTENCY_KEY' => $key])->assertOk();
        $completed->assertJsonPath('data.paid_amount', '200.02')
            ->assertJsonPath('data.remaining', '0.00')
            ->assertJsonPath('data.cash_change_total', '50.00');
        $invoiceId = $completed->json('data.invoice_id');
        $saleId = $completed->json('data.sale_ids.0');

        $replay = $this->send($client, 'POST', '/internal/admin/pos/sales', ['sale' => $sale, 'payments' => $payments], true,
            ['HTTP_IDEMPOTENCY_KEY' => $key])->assertOk();
        $this->assertSame($invoiceId, $replay->json('data.invoice_id'));
        $this->assertSame(1, DB::table('invoices')->where('public_id', $invoiceId)->count());

        $invoice = $this->send($client, 'GET', '/internal/admin/pos/invoices/'.$invoiceId)->assertOk();
        $this->assertCount(2, $invoice->json('data.payments'));
        $returned = $this->send($client, 'POST', '/internal/admin/pos/returns', [
            'invoice_id' => $invoiceId, 'reason' => 'HTTP accepted return',
            'lines' => [['sale_id' => $saleId, 'quantity' => 1, 'stock_unit_id' => null, 'condition' => 'returned', 'disposition' => 'sellable']],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt42-return-'.Str::uuid()])->assertOk();
        $returned->assertJsonPath('data.refund_due', '200.02');
        $returnId = $returned->json('data.return_id');
        $tenders = $invoice->json('data.payments');

        $this->send($client, 'POST', '/internal/admin/pos/refunds', [
            'return_id' => $returnId, 'original_tender_id' => $tenders[0]['allocation_id'],
            'refund_destination_id' => $tenders[0]['destination_id'], 'amount' => '50.02',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt42-refund-cash-'.Str::uuid()])->assertOk();
        $this->send($client, 'POST', '/internal/admin/pos/refunds', [
            'return_id' => $returnId, 'original_tender_id' => $tenders[1]['allocation_id'],
            'refund_destination_id' => $tenders[1]['destination_id'], 'amount' => '150.00',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt42-refund-card-'.Str::uuid()])->assertOk();

        $this->assertSame('200.02', DB::table('pos_refund_allocations')->where('return_id',
            DB::table('returns')->where('public_id', $returnId)->value('id'))->pluck('amount')
            ->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00'));
    }

    private function destination(string $method, string $name, array $extra = []): array
    {
        return app(PosPaymentOperations::class)->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => $method, 'display_name' => $name, ...$extra,
        ]);
    }

    private function authenticatedClient(): array
    {
        $client = $this->client();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $this->actor->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $this->outlet->public_id,
        ])->assertOk();

        return $client;
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.2 browser'];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function send(
        array &$client,
        string $method,
        string $uri,
        array $data = [],
        bool $csrf = true,
        array $extraServer = [],
    ) {
        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/json',
            'HTTP_USER_AGENT' => $client['agent'],
            ...$extraServer,
        ];
        if ($csrf && isset($client['tokens']['XSRF-TOKEN-admin'])) {
            $server['HTTP_X_CSRF_TOKEN'] = $client['tokens']['XSRF-TOKEN-admin'];
        }
        $response = $this->call($method, $uri, [], $client['cookies'], [], $server, json_encode($data));
        foreach ($response->headers->getCookies() as $cookie) {
            $client['cookies'][$cookie->getName()] = $cookie->getValue();
            if (str_starts_with($cookie->getName(), 'XSRF-TOKEN')) {
                $client['tokens'][$cookie->getName()] = CookieValuePrefix::remove(
                    app('encrypter')->decrypt($cookie->getValue(), false),
                );
            }
        }

        return $response;
    }
}
