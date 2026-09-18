<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Payments\PosPaymentOperations;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class PosCustomerReportingInterfaceTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        config(['session.driver' => 'database']);
        $this->inventoryFixture();
        $this->actor->forceFill(['password' => Hash::make('SyntheticPass123!')])->save();
    }

    public function test_mt43_area_permissions_and_sales_document_send_projection_are_role_scoped(): void
    {
        $invoiceMember = $this->member('mt43-invoice@example.invalid', ['shops.enter', 'shop.invoices']);
        $invoiceMember->shops()->attach($this->outlet);
        $client = $this->authenticatedClient($invoiceMember);

        $this->send($client, 'GET', '/internal/admin/pos/workspace/invoices')->assertOk();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/warranty')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/claims')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/workspace/reports')->assertForbidden();
        $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/invoices')->assertOk()
            ->assertJsonPath('data.can_send_documents', false);

        $salesMember = $this->member('mt43-sales@example.invalid', ['shops.enter', 'shop.sales']);
        $salesMember->shops()->attach($this->outlet);
        $salesClient = $this->authenticatedClient($salesMember);
        $this->send($salesClient, 'GET', '/internal/admin/pos/catalogue')->assertOk()
            ->assertJsonPath('data.can_send_documents', false);

        $salesMember->forceFill(['permissions' => ['shops.enter', 'shop.sales', 'shop.documents.send']])->save();
        $this->send($salesClient, 'GET', '/internal/admin/pos/catalogue')->assertOk()
            ->assertJsonPath('data.can_send_documents', true);
    }

    public function test_invoice_customer_documents_delivery_retry_and_report_export_http_contract(): void
    {
        [$sale] = $this->saleWithPayments('mt43-customer@example.invalid');
        $client = $this->authenticatedClient($this->actor);

        $index = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/invoices')->assertOk();
        $index->assertJsonPath('data.can_send_documents', true)
            ->assertJsonPath('data.invoices.0.id', $sale['invoice_id'])
            ->assertJsonPath('data.invoices.0.customer_email', 'mt43-customer@example.invalid')
            ->assertJsonPath('data.customers.0.name', 'MT43 Customer');
        $this->assertCount(1, $index->json('data.customers.0.invoices'));

        $this->assertSame(0, DB::table('document_delivery_attempts')->count());
        $a4 = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/preview?format=a4')->assertOk();
        $a4->assertJsonPath('data.action', 'preview')
            ->assertJsonPath('data.format', 'a4');
        $thermal = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/preview?format=thermal80')->assertOk();
        $thermal->assertJsonPath('data.format', 'thermal80');
        $this->assertSame(0, DB::table('document_delivery_attempts')->count());

        $print = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/print?format=thermal80')->assertOk();
        $print->assertJsonPath('data.action', 'print');
        $pdf = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/pdf?format=a4')->assertOk();
        $pdf->assertJsonPath('data.action', 'save_pdf');
        $this->assertStringStartsWith('JVBERi0', (string) $pdf->json('data.pdf_base64'));
        $this->assertSame($a4->json('data.document_sha256'), $pdf->json('data.document_sha256'));

        $this->connectGmail();
        $draft = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/email-draft')->assertOk();
        $draft->assertJsonPath('data.to', 'mt43-customer@example.invalid');

        $invoiceBefore = (array) DB::table('invoices')->where('public_id', $sale['invoice_id'])->firstOrFail();
        DB::table('integration_connections')->where('provider', 'gmail')->update(['status' => 'not_connected', 'encrypted_credentials' => null]);
        $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/email', [
            'to' => 'mt43-customer@example.invalid', 'subject' => 'MT43 delivery', 'message' => 'Synthetic MT43 delivery', 'resend' => false,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt43-email-fail'])->assertServerError();
        $failed = DB::table('document_delivery_attempts')->where('idempotency_key', 'mt43-email-fail')->firstOrFail();
        $this->assertSame('failed', $failed->state);
        $invoiceAfterFailure = (array) DB::table('invoices')->where('public_id', $sale['invoice_id'])->firstOrFail();
        $this->assertSame($invoiceBefore['version'], $invoiceAfterFailure['version']);
        $this->assertSame($invoiceBefore['final_bill'], $invoiceAfterFailure['final_bill']);

        $this->connectGmail();
        $sent = $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/email', [
            'to' => 'mt43-customer@example.invalid', 'subject' => 'MT43 delivery retry', 'message' => 'Synthetic MT43 retry', 'resend' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt43-email-retry'])->assertOk();
        $sent->assertJsonPath('data.state', 'sent')
            ->assertJsonPath('data.intentional_resend', true);

        $wa = $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/whatsapp', [
            'requires_attachment' => true,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt43-wa'])->assertOk();
        $wa->assertJsonPath('data.state', 'prepared');
        $this->assertStringContainsString('Attach the generated PDF', (string) $wa->json('data.operator_instruction'));
        $this->assertStringStartsWith('https://wa.me/923', (string) $wa->json('data.whatsapp_uri'));

        $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/documents/whatsapp/'.$wa->json('data.attempt_id').'/opened', [])
            ->assertOk()->assertJsonPath('data.state', 'opened');

        $history = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/invoice/'.$sale['invoice_id'].'/history')->assertOk();
        $this->assertCount(3, $history->json('data'));
        $this->assertSame(['failed', 'sent', 'opened'], array_column($history->json('data'), 'state'));

        $report = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/report/summary')->assertOk();
        $report->assertJsonPath('data.sales.invoice_count', 1)
            ->assertJsonPath('data.payments.pos_tender_total', '200.02')
            ->assertJsonPath('data.payments.website_payment_total', '0.00');
        $csv = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/report/csv')->assertOk();
        $csv->assertJsonPath('data.filename', 'pos-report.csv');
        $this->assertStringContainsString('sales,invoice_count,1', str_replace('
', '', (string) $csv->json('data.csv')));
        $this->assertStringContainsString('payments,pos_tender_total,200.02', str_replace('
', '', (string) $csv->json('data.csv')));
    }

    public function test_claim_lifecycle_and_warranty_receipt_are_scoped_and_warranty_is_a4_only(): void
    {
        [$sale, $product] = $this->saleWithPayments('mt43-warranty@example.invalid', true);
        $client = $this->authenticatedClient($this->actor);

        $claimsIndex = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/claims')->assertOk();
        $candidate = collect($claimsIndex->json('data.sale_candidates'))->firstWhere('sale_id', $sale['sale_ids'][0]);
        $this->assertNotNull($candidate);
        $this->assertSame('shop_warranty', $candidate['warranty_type']);
        $this->assertSame($product->name, $candidate['product_name']);

        $opened = $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/claims', [
            'sale_id' => $sale['sale_ids'][0], 'stock_unit_id' => null, 'quantity' => 1,
            'issue_description' => 'Synthetic MT43 warranty issue', 'received_condition' => 'Used',
            'accessories_received' => 'Handset only', 'assigned_to' => 'Bench MT43', 'expected_completion_at' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt43-claim-open'])->assertOk();
        $claimId = $opened->json('data.claim_id');
        $opened->assertJsonPath('data.status', 'received');

        $updated = $this->send($client, 'POST', '/internal/admin/pos/customer-reporting/claims/'.$claimId, [
            'status' => 'diagnosing', 'assigned_to' => 'Bench MT43', 'diagnosis' => 'Synthetic diagnosis',
            'resolution' => null, 'internal_notes' => null, 'expected_completion_at' => null,
            'customer_satisfied' => null, 'follow_up_required' => false, 'follow_up_at' => null, 'follow_up_notes' => null,
        ], true, ['HTTP_IDEMPOTENCY_KEY' => 'mt43-claim-update'])->assertOk();
        $updated->assertJsonPath('data.status', 'diagnosing');
        $this->assertCount(2, $updated->json('data.activity_log'));

        $warranty = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/warranty')->assertOk();
        $warranty->assertJsonPath('data.claims.0.id', $claimId);
        $receipt = $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/warranty/'.$claimId.'/preview?format=a4')->assertOk();
        $receipt->assertJsonPath('data.document_type', 'warranty')
            ->assertJsonPath('data.format', 'a4');
        $this->send($client, 'GET', '/internal/admin/pos/customer-reporting/documents/warranty/'.$claimId.'/preview?format=thermal80')
            ->assertUnprocessable();

        $warrantyOnly = $this->member('mt43-warranty-only@example.invalid', ['shops.enter', 'shop.warranty']);
        $warrantyOnly->shops()->attach($this->outlet);
        $warrantyClient = $this->authenticatedClient($warrantyOnly);
        $this->send($warrantyClient, 'GET', '/internal/admin/pos/customer-reporting/warranty')->assertOk()
            ->assertJsonPath('data.claims.0.id', $claimId);
        $this->send($warrantyClient, 'GET', '/internal/admin/pos/customer-reporting/documents/warranty/'.$claimId.'/preview?format=a4')->assertOk();
        $this->send($warrantyClient, 'GET', '/internal/admin/pos/customer-reporting/claims/'.$claimId)->assertForbidden();
    }

    private function saleWithPayments(?string $email, bool $warranty = false): array
    {
        $product = $this->product();
        if ($warranty) {
            $product->forceFill(['warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30])->save();
        }
        $this->acquire($product, 2);
        $payments = app(PosPaymentOperations::class);
        $card = $payments->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'card', 'display_name' => 'MT43 Card Terminal', 'masked_identifier' => 'TERM-MT43',
        ]);
        $bank = $payments->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'bank_transfer', 'display_name' => 'MT43 Bank', 'masked_identifier' => '****4343',
        ]);
        $sale = $payments->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => [
                'new_customer' => true, 'customer_name' => 'MT43 Customer', 'customer_phone' => '03001234567',
                'customer_email' => $email, 'customer_cnic' => '42101-1234567-1',
                'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]],
            ],
            'payments' => [
                ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '50.00', 'transaction_reference' => 'MT43-CARD'],
                ['method' => 'bank_transfer', 'destination_id' => $bank['destination_id'], 'amount' => '150.02', 'transaction_reference' => 'MT43-BANK'],
            ],
        ]);

        return [$sale, $product];
    }

    private function connectGmail(): void
    {
        Http::fake(['https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-mt43'])]);
        DB::table('integration_connections')->where('provider', 'gmail')->update([
            'status' => 'connected', 'account' => 'mobisttech@gmail.com',
            'encrypted_credentials' => Crypt::encryptString(json_encode([
                'access_token' => 'mt43-access', 'refresh_token' => 'mt43-refresh',
                'expires_at' => now()->addHour()->format('Y-m-d H:i:s.u'),
            ], JSON_THROW_ON_ERROR)),
        ]);
    }

    private function member(string $email, array $permissions): Admin
    {
        $member = new Admin;
        $member->forceFill([
            'name' => 'Synthetic MT43 Team Member', 'email' => $email,
            'password' => Hash::make('SyntheticPass123!'), 'auth_version' => 1,
            'permissions' => $permissions, 'job_title' => 'Synthetic MT43 role',
        ])->save();

        return $member;
    }

    private function authenticatedClient(Admin $actor): array
    {
        $client = $this->client();
        $this->send($client, 'POST', '/internal/admin/auth/login', [
            'email' => $actor->email, 'password' => 'SyntheticPass123!',
        ])->assertOk();
        $this->send($client, 'POST', '/internal/admin/outlets/select', [
            'outlet_id' => $this->outlet->public_id,
        ])->assertOk();

        return $client;
    }

    private function client(): array
    {
        $client = ['cookies' => [], 'tokens' => [], 'agent' => 'Synthetic MT-4.3 browser'];
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
