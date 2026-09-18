<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Payments\PosPaymentOperations;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PosOperationsInterfaceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_operations_shell_accepts_any_operations_permission_without_granting_unrelated_workspaces(): void
    {
        $member = new Admin;
        $member->forceFill([
            'name' => 'Cash approver only',
            'email' => 'mt46-cash-approver@example.invalid',
            'password' => Hash::make('SyntheticPass123!'),
            'auth_version' => 1,
            'permissions' => ['shops.enter', 'shop.cash.approve'],
        ])->save();
        $member->shops()->attach($this->outlet);

        $client = $this->client();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $member->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();

        $this->send($client, 'GET', '/internal/admin/pos')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('shell.navigation', 1)
                ->where('shell.navigation.0.key', 'operations'));

        $this->send($client, 'GET', '/internal/admin/pos/workspace/operations')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('view.workspace.key', 'operations'));
        $this->send($client, 'GET', '/internal/admin/pos/workspace/sales')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/inventory')->assertForbidden();
    }

    public function test_cash_day_closing_and_non_cash_settlement_http_contract_is_exact_scoped_and_idempotent(): void
    {
        $client = $this->authenticatedClient();

        $opened = $this->send($client, 'POST', '/internal/admin/pos/operations/cash/open', [
            'opening_cash' => '100.00',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-cash-open'])->assertOk();
        $sessionId = $opened->json('data.session_id');

        $expense = $this->send($client, 'POST', '/internal/admin/pos/operations/cash/'.$sessionId.'/entries', [
            'session_version' => 1, 'type' => 'expense', 'amount' => '10.00',
            'reason' => 'Synthetic MT-4.6 expense', 'reference' => 'EXP-46',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-expense'])->assertOk();
        $this->assertSame('pending', $expense->json('data.status'));

        $this->send($client, 'POST', '/internal/admin/pos/operations/cash/'.$sessionId.'/entries/'.$expense->json('data.entry_id').'/review', [
            'session_version' => 2, 'decision' => 'approved', 'notes' => 'Approved by synthetic owner',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-expense-review'])->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $product = $this->product();
        $this->acquire($product);
        $card = $this->destination('card', 'MT46 Provider Terminal', ['masked_identifier' => 'TERM-46']);
        $sale = app(PosPaymentOperations::class)->sell($this->actor, $this->outlet, 'mt46-settlement-sale', [
            'sale' => ['discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [[
                'method' => 'card', 'destination_id' => $card['destination_id'],
                'amount' => '200.02', 'transaction_reference' => 'AUTH-46',
            ]],
        ]);
        $allocationId = $sale['payments'][0]['allocation_id'];

        $this->send($client, 'POST', '/internal/admin/pos/operations/settlements/'.$allocationId, [
            'settlement_version' => 0, 'fee_amount' => '3.00', 'adjustment_amount' => '-0.50',
            'received_net_amount' => '196.52', 'external_reference' => 'SET-46', 'notes' => 'Synthetic settlement',
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-settlement'])->assertOk()
            ->assertJsonPath('data.state', 'confirmed')
            ->assertJsonPath('data.expected_net_amount', '196.52')
            ->assertJsonPath('data.variance_amount', '0.00');

        $index = $this->send($client, 'GET', '/internal/admin/pos/operations')->assertOk();
        $index->assertJsonPath('data.cash_session.summary.expected_cash', '90.00')
            ->assertJsonPath('data.settlements.0.reconciliation_state', 'confirmed')
            ->assertJsonPath('data.settlements.0.latest.fee_amount', '3.00')
            ->assertJsonPath('data.settlements.0.latest.adjustment_amount', '-0.50')
            ->assertJsonPath('data.settlements.0.latest.variance_amount', '0.00');
        $this->assertStringNotContainsString('credentials', $index->getContent());
        $this->assertStringNotContainsString('secret', $index->getContent());

        $closeInput = ['session_version' => 3, 'actual_cash' => '90.00', 'variance_reason' => null];
        $first = $this->send($client, 'POST', '/internal/admin/pos/operations/cash/'.$sessionId.'/close', $closeInput, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt46-cash-close',
        ])->assertOk()->json('data');
        $second = $this->send($client, 'POST', '/internal/admin/pos/operations/cash/'.$sessionId.'/close', $closeInput, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt46-cash-close',
        ])->assertOk()->json('data');
        $this->assertEquals($first, $second);
        $this->assertSame('closed', $first['status']);
        $this->assertSame('0.00', $first['closing']['variance_amount']);
        $this->assertSame('90.00', $first['closing']['expected_cash']);
    }

    public function test_trade_in_and_paid_repair_http_contracts_preserve_privacy_history_and_exact_collection(): void
    {
        $client = $this->authenticatedClient();

        $tradeProduct = $this->product(true);
        $trade = $this->send($client, 'POST', '/internal/admin/pos/operations/trade-ins/'.$tradeProduct->public_id, [
            'seller_name' => 'Synthetic MT46 Seller',
            'seller_cnic' => '42101-1234567-1',
            'seller_phone' => '03001234567',
            'seller_address' => 'Synthetic private address',
            'device_serial' => 'SERIAL-MT46',
            'imeis' => ['352099001761466', '352099001761474'],
            'condition' => 'Used - inspected',
            'diagnostics' => ['Battery pass', 'Display pass'],
            'valuation_amount' => '100.00',
            'settlement_mode' => 'purchase',
            'invoice_id' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-trade-create'])->assertOk();
        $tradeId = $trade->json('data.trade_in_id');
        $trade->assertJsonPath('data.seller.cnic', '*****-*******-1')
            ->assertJsonPath('data.seller.phone', '0300*****67')
            ->assertJsonPath('data.status', 'pending');

        $approved = $this->send($client, 'POST', '/internal/admin/pos/operations/trade-ins/'.$tradeId.'/approve', [
            'version' => 1,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-trade-approve'])->assertOk();
        $received = $this->send($client, 'POST', '/internal/admin/pos/operations/trade-ins/'.$tradeId.'/receive', [
            'version' => $approved->json('data.version'),
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-trade-receive'])->assertOk();
        $received->assertJsonPath('data.status', 'received');
        $this->assertSame(1, DB::table('acquisition_source_references')->where('kind', 'trade_in')->count());

        $tradeView = $this->send($client, 'GET', '/internal/admin/pos/operations/trade-ins/'.$tradeId)->assertOk();
        $tradeView->assertJsonPath('data.seller.cnic', '*****-*******-1');
        $this->assertStringNotContainsString('42101-1234567-1', $tradeView->getContent());
        $this->assertStringNotContainsString('03001234567', $tradeView->getContent());

        $enabled = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/configure', [
            'enabled' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-enable'])->assertOk();
        $enabled->assertJsonPath('data.enabled', true);

        $opened = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs', [
            'customer_id' => null,
            'customer_name' => 'Synthetic Repair Customer',
            'customer_phone' => '03005556666',
            'device_label' => 'Synthetic Device MT46',
            'identifier_type' => 'serial',
            'identifier_value' => 'SERIAL-REPAIR-MT46',
            'issue_description' => 'Synthetic paid repair issue',
            'received_condition' => 'Used',
            'accessories_received' => 'Handset only',
            'internal_notes' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-open'])->assertOk();
        $repairId = $opened->json('data.repair_id');

        $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/'.$repairId.'/status', [
            'status' => 'diagnosing', 'diagnosis' => 'Synthetic diagnosis', 'internal_notes' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-diagnose'])->assertOk()
            ->assertJsonPath('data.status', 'diagnosing');

        $estimated = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/'.$repairId.'/estimate', [
            'notes' => 'Synthetic labor estimate',
            'lines' => [[
                'type' => 'labor', 'product_id' => null, 'description' => 'Repair labor',
                'quantity' => 1, 'unit_price' => '50.00',
            ]],
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-estimate'])->assertOk();
        $estimated->assertJsonPath('data.status', 'awaiting_approval')
            ->assertJsonPath('data.estimates.0.grand_total', '50.00');
        $estimateId = $estimated->json('data.estimates.0.estimate_id');

        $approvedRepair = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/'.$repairId.'/estimate/'.$estimateId.'/decision', [
            'approved' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-approve'])->assertOk();
        $approvedRepair->assertJsonPath('data.status', 'approved');

        $destination = $this->destination('bank_transfer', 'MT46 Repair Account');
        $paymentInput = ['payments' => [[
            'method' => 'bank_transfer', 'destination_id' => $destination['destination_id'], 'amount' => '50.00',
            'transaction_reference' => 'RPR-46',
        ]]];
        $paid = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/'.$repairId.'/collect', $paymentInput, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-pay',
        ])->assertOk();
        $paid->assertJsonPath('data.paid_amount', '50.00');
        $replayed = $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/'.$repairId.'/collect', $paymentInput, true, [
            'HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-pay',
        ])->assertOk();
        $this->assertEquals($paid->json('data'), $replayed->json('data'));
        $this->assertSame(1, DB::table('repair_payment_links')->count());

        $this->send($client, 'POST', '/internal/admin/pos/operations/repairs/configure', [
            'enabled' => false, 'version' => $enabled->json('data.version'),
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-disable'])->assertOk()
            ->assertJsonPath('data.enabled', false);

        $history = $this->send($client, 'GET', '/internal/admin/pos/operations/repairs/'.$repairId)->assertOk();
        $history->assertJsonPath('data.repair_id', $repairId)
            ->assertJsonPath('data.payments.0.amount', '50.00');
        $this->assertNotEmpty($history->json('data.events'));

        $this->send($client, 'POST', '/internal/admin/pos/operations/repairs', [
            'customer_id' => null, 'customer_name' => 'Blocked Repair', 'customer_phone' => null,
            'device_label' => 'Blocked Device', 'identifier_type' => 'serial', 'identifier_value' => 'BLOCKED-MT46',
            'issue_description' => 'Should not open while disabled', 'received_condition' => null,
            'accessories_received' => null, 'internal_notes' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt46-repair-blocked'])->assertUnprocessable();

        $index = $this->send($client, 'GET', '/internal/admin/pos/operations')->assertOk();
        $index->assertJsonPath('data.repair_setting.enabled', false);
        $this->assertStringNotContainsString('SERIAL-REPAIR-MT46', $index->getContent());
        $this->assertStringContainsString('MT46', $index->getContent());
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
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.6 browser'];
        $this->send($client, 'GET', '/internal/admin/auth/csrf-cookie')->assertOk();

        return $client;
    }

    private function destination(string $method, string $name, array $extra = []): array
    {
        return app(PosPaymentOperations::class)->createDestination($this->actor, $this->outlet, 'mt46-destination-'.Str::uuid(), [
            'method' => $method, 'display_name' => $name, ...$extra,
        ]);
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
