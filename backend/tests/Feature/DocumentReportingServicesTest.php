<?php

namespace Tests\Feature;

use App\Documents\CanonicalDocuments;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\StockUnit;
use App\Payments\PosPaymentOperations;
use App\Pos\PosConfiguration;
use App\Reporting\OperationalReports;
use App\Reporting\RetailLabels;
use App\Warranty\ClaimOperations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function test_published_document_presentation_controls_content_format_and_role_specific_logos(): void
    {
        Storage::fake('public');
        [$sale, $product] = $this->saleWithPayments('presentation@example.invalid');
        $configuration = app(PosConfiguration::class);
        $path = 'dynamic-media/branding/document-logo.png';
        $logoBytes = file_get_contents(public_path('brand/mobist-wordmark-print.png'));
        Storage::disk('public')->put($path, $logoBytes);
        $mediaId = DB::table('pos_media_assets')->insertGetId([
            'disk' => 'public', 'path' => $path, 'original_name' => 'document-logo.png',
            'mime_type' => 'image/png', 'extension' => 'png', 'byte_size' => strlen($logoBytes),
            'width' => 900, 'height' => 300, 'aspect_ratio' => 3,
            'sha256' => hash('sha256', $logoBytes), 'alt_text' => 'Published document logo',
            'status' => 'active', 'uploaded_by_type' => Admin::class, 'uploaded_by_id' => $this->actor->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $documentsDraft = $configuration->draft($this->actor, 'documents', [
            'invoice.show_customer_cnic' => false,
            'invoice.show_salesperson' => true,
            'invoice.thank_you_text' => 'Configured presentation footer',
            'invoice.default_output_format' => 'a4',
            'invoice.footer_alignment' => 'right',
            'warranty.show_customer_cnic' => false,
            'warranty.show_assigned_to' => false,
            'warranty.show_status' => false,
        ]);
        $brandingDraft = $configuration->draft($this->actor, 'branding', [
            'branding.invoice_logo_media_id' => $mediaId,
            'branding.warranty_logo_media_id' => $mediaId,
        ]);
        $documents = app(CanonicalDocuments::class);
        $before = $documents->preview($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertStringContainsString('data-branding-role="invoice_logo"', $before['html']);
        $this->assertStringContainsString('/brand/mobist-wordmark.svg', $before['html']);
        $this->assertStringContainsString('CNIC:', $before['html']);
        $this->assertStringNotContainsString('Configured presentation footer', $before['html']);

        $configuration->publish($this->actor, $documentsDraft['id']);
        $configuration->publish($this->actor, $brandingDraft['id']);
        $invoice = $documents->preview($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertStringContainsString('data-branding-role="invoice_logo"', $invoice['html']);
        $this->assertStringContainsString('Published document logo', $invoice['html']);
        $this->assertStringContainsString('/storage/dynamic-media/branding/document-logo.png', $invoice['html']);
        $this->assertStringNotContainsString('CNIC:', $invoice['html']);
        $this->assertStringContainsString('Salesperson: Synthetic inventory operator', $invoice['html']);
        $this->assertStringContainsString('Configured presentation footer', $invoice['html']);
        $this->assertStringContainsString('data-alignment="right"', $invoice['html']);
        $pdf = $documents->savePdf($this->actor, $this->outlet, 'invoice', $sale['invoice_id']);
        $this->assertStringContainsString('Configured presentation footer', $pdf['pdf']);
        $this->assertStringNotContainsString('CNIC:', $pdf['pdf']);
        $this->assertStringContainsString('/Subtype /Image', $pdf['pdf']);
        $this->assertStringContainsString('/Logo Do', $pdf['pdf']);

        DB::table('products')->where('id', $product->id)->update(['warranty_type' => 'shop_warranty', 'warranty_unit' => 0, 'warranty_duration' => 30]);
        DB::table('sales')->where('public_id', $sale['sale_ids'][0])->update([
            'invoice_detail_snapshot' => json_encode(['contract' => 'sale-line.v1', 'product_id' => $product->public_id,
                'product_code' => $product->product_code, 'name' => 'Presentation product', 'warranty_type' => 'shop_warranty',
                'warranty_unit' => 0, 'warranty_duration' => 30], JSON_THROW_ON_ERROR),
        ]);
        $claim = app(ClaimOperations::class)->open($this->actor, $this->outlet, (string) Str::uuid(), [
            'sale_id' => $sale['sale_ids'][0], 'quantity' => 1, 'issue_description' => 'Presentation warranty issue',
        ]);
        $warranty = $documents->preview($this->actor, $this->outlet, 'warranty', $claim['claim_id']);
        $this->assertStringContainsString('data-branding-role="warranty_logo"', $warranty['html']);
        $this->assertStringContainsString('Published document logo', $warranty['html']);
        $this->assertStringNotContainsString('CNIC:', $warranty['html']);
        $this->assertStringNotContainsString('Handled by:', $warranty['html']);
        $this->assertStringNotContainsString('Status at intake:', $warranty['html']);
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
        $this->assertSame('accessory', $report['categories'][0]['category']);
        $this->assertSame(1, $report['categories'][0]['units_sold']);
        $this->assertSame('200.02', $report['categories'][0]['net_sales']);
        $this->assertSame('76.57', $report['categories'][0]['profit']);
        $future = now()->addDay()->toDateString();
        $filtered = app(OperationalReports::class)->summary($this->actor, $this->outlet, $future, $future);
        $this->assertSame(0, $filtered['categories'][0]['units_sold']);
        $this->assertSame('0.00', $filtered['categories'][0]['net_sales']);
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
