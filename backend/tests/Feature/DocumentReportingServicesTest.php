<?php

namespace Tests\Feature;

use App\Documents\CanonicalDocuments;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Payments\PosPaymentOperations;
use App\Reporting\OperationalReports;
use App\Reporting\RetailLabels;
use App\Warranty\ClaimOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Support\InventoryFixture;
use Tests\TestCase;

class DocumentReportingServicesTest extends TestCase
{
    use DatabaseTransactions, InventoryFixture;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventoryFixture();
    }

    public function test_invoice_and_warranty_use_canonical_historical_snapshots_and_explicit_actions(): void
    {
        [$sale, $product] = $this->saleWithPayments('snapshot@example.invalid');
        $documents = app(CanonicalDocuments::class);
        $preview = $documents->preview($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertSame('preview', $preview['action']);
        $this->assertArrayNotHasKey('pdf', $preview);
        $saved = $documents->savePdf($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertStringStartsWith('%PDF-', $saved['pdf']);
        $this->assertSame($preview['document_sha256'], $saved['document_sha256']);
        DB::table('products')->where('id', $product->id)->update(['name' => 'Changed after sale']);
        DB::table('customers')->where('id', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('customer_id'))
            ->update(['email' => 'changed@example.invalid']);
        $again = $documents->preview($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertSame($preview['document_sha256'], $again['document_sha256']);
        $this->assertSame('snapshot@example.invalid', DB::table('invoices')->where('public_id', $sale['invoice_id'])->value('customer_email'));

        DB::table('products')->where('id', $product->id)->update(['warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30]);
        DB::table('sales')->where('public_id', $sale['sale_ids'][0])->update([
            'invoice_detail_snapshot' => json_encode(['contract' => 'sale-line.v1', 'product_id' => $product->public_id,
                'product_code' => $product->product_code, 'name' => 'Original product', 'warranty_type' => 'shop_warranty',
                'warranty_unit' => 0, 'warranty_duration' => 30], JSON_THROW_ON_ERROR),
        ]);
        $claim = app(ClaimOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Synthetic warranty issue',
        ]);
        $warranty = $documents->preview($this->actor, $this->outlet, 'warranty', $claim['claim_id']);
        $this->assertSame('warranty', $warranty['document_type']);
        $this->assertArrayNotHasKey('pdf', $warranty);
        $this->reject(fn () => $documents->preview($this->actor, $this->outlet, 'warranty', $claim['claim_id'], 'thermal80'));
    }

    public function test_email_delivery_is_fakeable_idempotent_and_whatsapp_is_truthful(): void
    {
        [$sale] = $this->saleWithPayments('delivery@example.invalid');
        Http::fake(['https://gmail.googleapis.com/gmail/v1/users/me/messages/send' => Http::response(['id' => 'gmail-doc-1'])]);
        DB::table('integration_connections')->where('provider', 'gmail')->update([
            'status' => 'connected', 'account' => 'mobisttech@gmail.com',
            'encrypted_credentials' => Crypt::encryptString(json_encode(['access_token' => 'document-access', 'refresh_token' => 'document-refresh',
                'expires_at' => now()->addHour()->format('Y-m-d H:i:s.u')], JSON_THROW_ON_ERROR)),
        ]);
        $documents = app(CanonicalDocuments::class);
        $key = 'email-'.Str::uuid();
        $sent = $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], $key);
        $this->assertSame('sent', $sent['state']);
        $this->assertSame('gmail-doc-1', $sent['provider_reference']);
        $replay = $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], $key);
        $this->assertSame($sent['attempt_id'], $replay['attempt_id']);
        $this->assertSame(1, DB::table('document_delivery_attempts')->where('channel', 'email')->count());
        Http::assertSentCount(1);
        $resend = $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], 'resend-'.Str::uuid(), [], true);
        $this->assertSame('sent', $resend['state']);
        $this->assertTrue($resend['intentional_resend']);
        $this->assertNotSame($sent['attempt_id'], $resend['attempt_id']);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request) {
            $raw = base64_decode(strtr((string) $request['raw'], '-_', '+/'));

            return str_contains($raw, 'Content-Type: application/pdf') && str_contains($raw, 'Content-Disposition: attachment;')
                && str_contains($raw, 'JVBERi0');
        });

        $prepared = $documents->prepareWhatsapp($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], 'wa-'.Str::uuid());
        $this->assertSame('prepared', $prepared['state']);
        $this->assertNotNull($prepared['attachment']);
        $this->assertStringContainsString('Attach the generated PDF', $prepared['operator_instruction']);
        $opened = $documents->markWhatsappOpened($this->actor, $this->outlet, $prepared['attempt_id']);
        $this->assertSame('opened', $opened['state']);
        $this->assertNotSame('sent', $opened['state']);

        $this->reject(fn () => $documents->updateTemplate($this->actor, 'invoice_email_subject', "Bad\nHeader"));
        $this->reject(fn () => $documents->updateTemplate($this->actor, 'invoice_email_body', 'Hello {{unknown_secret}}'));
    }

    public function test_report_counts_sale_once_and_keeps_tenders_fees_and_settlement_separate(): void
    {
        [$sale] = $this->saleWithPayments(null);
        $payments = app(PosPaymentOperations::class);
        $payments->reconcile($this->actor, $this->outlet, $sale['payments'][1]['allocation_id'], (string) Str::uuid(), [
            'settlement_version' => 0, 'fee_amount' => '3.00', 'adjustment_amount' => '0.00',
            'received_net_amount' => '147.02', 'external_reference' => 'MT31-SETTLEMENT',
        ]);
        $report = app(OperationalReports::class)->summary($this->actor, $this->outlet);
        $this->assertSame('200.02', $report['sales']['gross_sales']);
        $this->assertSame('200.02', $report['sales']['net_sales']);
        $this->assertSame('200.02', $report['payments']['pos_tender_total']);
        $this->assertSame('3.00', $report['payments']['provider_fees']);
        $this->assertSame('147.02', $report['payments']['net_settlement']);
        $this->assertSame('50.00', $report['payments']['method_breakdown']['card']);
        $this->assertSame('150.02', $report['payments']['method_breakdown']['bank_transfer']);
    }

    public function test_delivery_failures_and_authorization_do_not_change_finalized_invoice(): void
    {
        [$sale] = $this->saleWithPayments(null);
        $documents = app(CanonicalDocuments::class);
        $invoiceBefore = (array) DB::table('invoices')->where('public_id', $sale['invoice_id'])->firstOrFail();
        $this->reject(fn () => $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], 'missing-email'));
        $this->reject(fn () => $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], 'header-injection',
            ['to' => 'safe@example.invalid', 'subject' => "Bad\nSubject", 'message' => 'Test']));

        DB::table('integration_connections')->where('provider', 'gmail')->update(['status' => 'not_connected', 'encrypted_credentials' => null]);
        try {
            $documents->sendEmail($this->actor, $this->outlet, 'invoice', $sale['invoice_id'], 'gmail-offline', ['to' => 'safe@example.invalid']);
            $this->fail('Disconnected Gmail delivery unexpectedly succeeded.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Gmail is not connected.', $error->getMessage());
        }
        $this->assertSame('failed', DB::table('document_delivery_attempts')->where('idempotency_key', 'gmail-offline')->value('state'));

        $denied = new Admin;
        $denied->forceFill(['name' => 'Denied', 'email' => 'denied-docs@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => []])->save();
        $denied->shops()->attach($this->outlet);
        $this->reject(fn () => $documents->preview($denied, $this->outlet, 'invoice', $sale['invoice_id']));
        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Other outlet', 'outlet_code' => '031'])->save();
        $this->actor->shops()->attach($other);
        $this->reject(fn () => $documents->preview($this->actor, $other, 'invoice', $sale['invoice_id']));
        $invoiceAfter = (array) DB::table('invoices')->where('public_id', $sale['invoice_id'])->firstOrFail();
        $this->assertSame($invoiceBefore['final_bill'], $invoiceAfter['final_bill']);
        $this->assertSame($invoiceBefore['version'], $invoiceAfter['version']);
    }

    public function test_retail_labels_are_outlet_scoped_authorized_identifier_projections(): void
    {
        $product = $this->product(true);
        $this->acquire($product, 1);
        $unit = StockUnit::where('product_id', $product->id)->firstOrFail();
        $this->imeis($product, $unit, [1 => 'MT310000000001', 2 => 'MT310000000002']);
        $labels = app(RetailLabels::class);
        $productLabel = $labels->product($this->actor, $this->outlet, $product->public_id);
        $this->assertSame($product->product_code, $productLabel['barcode_value']);
        $unitLabel = $labels->unit($this->actor, $this->outlet, $unit->public_id);
        $this->assertSame($unit->unit_code, $unitLabel['barcode_value']);
        $this->assertSame(['MT310000000001', 'MT310000000002'], $unitLabel['imeis']);

        $denied = new Admin;
        $denied->forceFill(['name' => 'No labels', 'email' => 'no-labels@example.invalid', 'password' => 'SyntheticPass123!', 'permissions' => []])->save();
        $denied->shops()->attach($this->outlet);
        $this->reject(fn () => $labels->product($denied, $this->outlet, $product->public_id));
        $other = new Outlet;
        $other->forceFill(['public_id' => (string) Str::uuid(), 'name' => 'Label other', 'outlet_code' => '032'])->save();
        $this->actor->shops()->attach($other);
        $this->reject(fn () => $labels->unit($this->actor, $other, $unit->public_id));
    }

    private function saleWithPayments(?string $email): array
    {
        $product = $this->product();
        $this->acquire($product, 2);
        $payments = app(PosPaymentOperations::class);
        $card = $payments->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'card', 'display_name' => 'MT31 Card Terminal', 'masked_identifier' => 'TERM-MT31',
        ]);
        $bank = $payments->createDestination($this->actor, $this->outlet, (string) Str::uuid(), [
            'method' => 'bank_transfer', 'display_name' => 'MT31 Bank', 'masked_identifier' => '****3131',
        ]);
        $sale = $payments->sell($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale' => ['new_customer' => true, 'customer_name' => 'MT31 Customer', 'customer_phone' => '03001234567',
                'customer_email' => $email, 'discount' => '0.00', 'lines' => [['product_id' => $product->public_id, 'quantity' => 1]]],
            'payments' => [
                ['method' => 'card', 'destination_id' => $card['destination_id'], 'amount' => '50.00', 'transaction_reference' => 'MT31-CARD'],
                ['method' => 'bank_transfer', 'destination_id' => $bank['destination_id'], 'amount' => '150.02', 'transaction_reference' => 'MT31-BANK'],
            ],
        ]);

        return [$sale, $product];
    }
}
