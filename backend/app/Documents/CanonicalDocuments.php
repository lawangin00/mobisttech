<?php

namespace App\Documents;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Integrations\GmailApi;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use RuntimeException;
use Throwable;

final class CanonicalDocuments
{
    public const VERSION = 1;

    private const TEMPLATE_KEYS = [
        'invoice_whatsapp', 'warranty_whatsapp', 'invoice_email_subject', 'invoice_email_body',
        'warranty_email_subject', 'warranty_email_body',
    ];

    private const PLACEHOLDERS = [
        'customer_name', 'invoice_number', 'claim_number', 'total_amount', 'business_name', 'outlet_name', 'document_date',
    ];

    public function __construct(private GmailApi $gmail) {}

    public function preview(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $format = 'a4'): array
    {
        $render = $this->renderAuthorized($actor, $outlet, $type, $documentId, $format);

        return $this->publicRender($render) + ['html' => $render['html'], 'action' => 'preview'];
    }

    public function printable(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $format = 'a4'): array
    {
        $render = $this->renderAuthorized($actor, $outlet, $type, $documentId, $format);

        return $this->publicRender($render) + ['html' => $render['html'], 'action' => 'print'];
    }

    public function savePdf(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $format = 'a4'): array
    {
        $render = $this->renderAuthorized($actor, $outlet, $type, $documentId, $format);

        return $this->publicRender($render) + ['pdf' => $render['pdf'], 'action' => 'save_pdf'];
    }

    /** An explicitly labelled reconstruction, NEVER the originally issued PDF or a proof of settlement. */
    public function reconstructArchivedInvoice(Admin $actor, Outlet $outlet, string $documentId): array
    {
        abort_unless(app(\App\Identity\OutletLifecycleAdministration::class)->canManage($actor), 403);
        abort_unless($outlet->fresh()->archived_at !== null, 409, 'Outlet is not archived.');
        $invoice = DB::table('invoices')->where('public_id', $documentId)
            ->where('outlet_id', $outlet->id)->firstOrFail();
        $business = json_decode((string) $invoice->business_snapshot, true);
        abort_unless(is_string($invoice->invoice_number) && trim($invoice->invoice_number) !== ''
            && $invoice->created_at !== null && is_array($business)
            && is_string($business['business_name'] ?? null) && trim($business['business_name']) !== '',
            409, 'Historical invoice lacks a complete original invoice identity or business snapshot.');
        $saleQuery = DB::table('sales')->where('invoice_id', $invoice->id)->where('outlet_id', $outlet->id);
        $count = (clone $saleQuery)->count();
        abort_unless($count > 0 && $count <= 30
            && DB::table('sales')->where('invoice_id', $invoice->id)->count() === $count,
            409, 'Historical PDF reconstruction needs a bounded complete same-outlet sale-line set.');
        foreach ((clone $saleQuery)->get(['invoice_detail_snapshot']) as $sale) {
            $snapshot = json_decode((string) $sale->invoice_detail_snapshot, true);
            abort_unless(is_array($snapshot) && ($snapshot['contract'] ?? null) === 'sale-line.v1'
                && is_string($snapshot['name'] ?? null) && trim($snapshot['name']) !== '',
                409, 'Historical invoice item snapshot is missing or uses an unsupported contract.');
        }
        $payload = $this->invoice($outlet, $documentId);
        $lines = ['RECONSTRUCTED COPY - NOT THE ORIGINAL ISSUED PDF',
            'Historical review only; source records may be transliterated.', ...$this->lines($payload)];
        abort_unless(count($lines) <= 55, 409, 'Historical PDF exceeds the safe single-page reconstruction limit.');
        $pdf = $this->pdf($lines, 'a4');
        return ['filename' => 'reconstructed-'.preg_replace('/[^A-Za-z0-9._-]+/', '-',
                (string) ($invoice->invoice_number ?: $invoice->public_id)).'-a4.pdf',
            'pdf_base64' => base64_encode($pdf), 'document_sha256' => hash('sha256', $pdf),
            'document_version' => self::VERSION, 'reconstruction_only' => true,
            'original_issued_pdf_preserved' => false, 'format' => 'a4'];
    }

    public function emailDraft(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId): array
    {
        $this->authorizeSend($actor, $outlet, $type);
        $render = $this->render($outlet, $type, $documentId, 'a4');
        [$subject, $subjectRevision] = $this->renderTemplate($type.'_email_subject', $render['payload']);
        [$body, $bodyRevision] = $this->renderTemplate($type.'_email_body', $render['payload']);

        return $this->publicRender($render) + [
            'to' => $render['payload']['customer_email'], 'subject' => $subject, 'message' => $body,
            'attachment' => ['filename' => $render['filename'], 'sha256' => $render['sha256']],
            'template_revisions' => [$subjectRevision, $bodyRevision],
        ];
    }

    public function updateTemplate(IdentityAccount $actor, string $key, string $text): array
    {
        abort_unless(app(Access::class)->allows($actor, 'config.documents.manage'), 403);
        if (! in_array($key, self::TEMPLATE_KEYS, true)) {
            throw ValidationException::withMessages(['template_key' => 'Unknown document template.']);
        }
        $this->validateTemplate($key, $text);

        return DB::transaction(function () use ($actor, $key, $text) {
            $latest = DB::table('document_template_revisions')->where('template_key', $key)->orderByDesc('version')->lockForUpdate()->firstOrFail();
            $version = (int) $latest->version + 1;
            $id = DB::table('document_template_revisions')->insertGetId([
                'public_id' => (string) Str::uuid(), 'template_key' => $key, 'document_type' => $latest->document_type,
                'channel' => $latest->channel, 'template_part' => $latest->template_part, 'version' => $version,
                'template_text' => $text, 'created_by_admin_id' => $actor->id, 'created_at' => now(),
            ]);

            return $this->templatePayload(DB::table('document_template_revisions')->where('id', $id)->firstOrFail());
        }, 3);
    }

    public function sendEmail(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $key, array $input = [], bool $resend = false): array
    {
        $this->authorizeSend($actor, $outlet, $type);
        $this->fields($input, ['to', 'subject', 'message']);
        $render = $this->render($outlet, $type, $documentId, 'a4');
        $draft = $this->emailDraft($actor, $outlet, $type, $documentId);
        $to = isset($input['to']) ? mb_strtolower(trim((string) $input['to'])) : $draft['to'];
        $subject = isset($input['subject']) ? trim((string) $input['subject']) : $draft['subject'];
        $message = isset($input['message']) ? trim((string) $input['message']) : $draft['message'];
        Validator::make(['to' => $to, 'subject' => $subject, 'message' => $message], [
            'to' => 'required|email:rfc|max:255', 'subject' => 'required|string|max:500', 'message' => 'required|string|max:10000',
        ])->validate();
        if (preg_match('/[\r\n]/', $subject)) {
            throw ValidationException::withMessages(['subject' => 'Email subject cannot contain header line breaks.']);
        }
        $request = ['to' => $to, 'subject' => $subject, 'message' => $message, 'document_sha256' => $render['sha256'],
            'template_revisions' => $draft['template_revisions'], 'intentional_resend' => $resend];
        [$attempt, $created] = $this->attempt($actor, $outlet, $type, $documentId, 'email', $key, $request, $render, $resend,
            $to, $subject, $draft['template_revisions']);
        if (! $created) {
            return $this->attemptPayload($attempt);
        }
        try {
            $provider = $this->gmail->sendDocument($to, $subject, $message, nl2br(e($message)), $render['filename'], $render['pdf']);
            DB::table('document_delivery_attempts')->where('id', $attempt->id)->update([
                'state' => 'sent', 'provider_reference' => $provider, 'completed_at' => now(), 'updated_at' => now(),
            ]);
        } catch (Throwable $error) {
            $safe = $this->safeFailure($error);
            DB::table('document_delivery_attempts')->where('id', $attempt->id)->update([
                'state' => 'failed', 'failure_summary' => $safe, 'completed_at' => now(), 'updated_at' => now(),
            ]);
            throw new RuntimeException($safe, 0, $error);
        }

        return $this->attemptPayload(DB::table('document_delivery_attempts')->where('id', $attempt->id)->firstOrFail());
    }

    public function prepareWhatsapp(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $key, array $input = []): array
    {
        $this->authorizeSend($actor, $outlet, $type);
        $this->fields($input, ['phone', 'message', 'requires_attachment']);
        $render = $this->render($outlet, $type, $documentId, 'a4');
        [$message, $revision] = $this->renderTemplate($type.'_whatsapp', $render['payload']);
        $message = isset($input['message']) ? trim((string) $input['message']) : $message;
        Validator::make(['message' => $message], ['message' => 'required|string|max:5000'])->validate();
        $phone = $this->phone(isset($input['phone']) ? (string) $input['phone'] : (string) ($render['payload']['customer_phone'] ?? ''));
        $attachment = (bool) ($input['requires_attachment'] ?? true);
        $request = ['phone' => $phone, 'message' => $message, 'requires_attachment' => $attachment,
            'document_sha256' => $render['sha256'], 'template_revisions' => [$revision]];
        [$attempt, $created] = $this->attempt($actor, $outlet, $type, $documentId, 'whatsapp', $key, $request, $render, false,
            $phone, null, [$revision]);
        $payload = $this->attemptPayload($attempt) + [
            'phone' => $phone, 'message' => $message,
            'whatsapp_uri' => 'https://wa.me/'.$phone.'?text='.rawurlencode($message),
            'operator_instruction' => $attachment ? 'Attach the generated PDF before sending.' : 'Review the prepared message before sending.',
            'attachment' => $attachment ? ['filename' => $render['filename'], 'sha256' => $render['sha256'], 'pdf' => $render['pdf']] : null,
        ];
        if (! $created && $attempt->state === 'opened') {
            $payload['state'] = 'opened';
        }

        return $payload;
    }

    public function markWhatsappOpened(IdentityAccount $actor, Outlet $outlet, string $attemptId): array
    {
        abort_unless(app(Access::class)->allows($actor, 'shop.documents.send', $outlet), 403);
        $attempt = DB::table('document_delivery_attempts')->where('public_id', $attemptId)->where('outlet_id', $outlet->id)
            ->where('actor_admin_id', $actor->id)->where('channel', 'whatsapp')->firstOrFail();
        if ($attempt->state === 'prepared') {
            DB::table('document_delivery_attempts')->where('id', $attempt->id)->update(['state' => 'opened', 'completed_at' => now(), 'updated_at' => now()]);
        }

        return $this->attemptPayload(DB::table('document_delivery_attempts')->where('id', $attempt->id)->firstOrFail());
    }

    public function history(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId): array
    {
        $this->authorizeRead($actor, $outlet, $type);

        return DB::table('document_delivery_attempts')->where('outlet_id', $outlet->id)->where('document_type', $type)
            ->where('document_public_id', $documentId)->orderBy('id')->get()->map(fn ($row) => $this->attemptPayload($row))->all();
    }

    private function renderAuthorized(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $format): array
    {
        $this->authorizeRead($actor, $outlet, $type);

        return $this->render($outlet, $type, $documentId, $format);
    }

    private function render(Outlet $outlet, string $type, string $documentId, string $format): array
    {
        if (! in_array($type, ['invoice', 'warranty'], true) || ! in_array($format, ['a4', 'thermal80'], true)
            || ($type === 'warranty' && $format !== 'a4')) {
            throw ValidationException::withMessages(['document' => 'Unsupported document type or format.']);
        }
        $payload = $type === 'invoice' ? $this->invoice($outlet, $documentId) : $this->warranty($outlet, $documentId);
        $lines = $this->lines($payload);
        $pdf = $this->pdf($lines, $format);
        $identifier = $type === 'invoice' ? $payload['invoice_number'] : $payload['claim_number'];

        return ['type' => $type, 'format' => $format, 'version' => self::VERSION, 'payload' => $payload,
            'html' => $this->html($payload, $lines, $format), 'pdf' => $pdf, 'sha256' => hash('sha256', $pdf),
            'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '-', $identifier).'-'.$format.'.pdf'];
    }

    private function invoice(Outlet $outlet, string $documentId): array
    {
        $invoice = DB::table('invoices')->where('public_id', $documentId)->where('outlet_id', $outlet->id)->firstOrFail();
        $business = $this->json($invoice->business_snapshot);
        $lines = DB::table('sales')->where('invoice_id', $invoice->id)->orderBy('id')->get()->map(function ($sale) {
            $snapshot = $this->json($sale->invoice_detail_snapshot);

            return [
                'product_id' => $snapshot['product_id'] ?? null, 'product_code' => $snapshot['product_code'] ?? null,
                'name' => $snapshot['name'] ?? 'Historical item', 'quantity' => (int) $sale->quantity,
                'unit_price' => (string) $sale->sale_price, 'gross' => (string) $sale->total_price,
                'discount' => (string) $sale->discount_allocated, 'net' => (string) $sale->net_total_price,
                'warranty_type' => $snapshot['warranty_type'] ?? null, 'warranty_unit' => $snapshot['warranty_unit'] ?? null,
                'warranty_duration' => $snapshot['warranty_duration'] ?? null,
            ];
        })->all();

        return [
            'document_type' => 'invoice', 'invoice_number' => $invoice->invoice_number, 'claim_number' => null,
            'document_date' => (string) $invoice->created_at, 'customer_name' => $invoice->customer_name,
            'customer_phone' => $invoice->customer_phone, 'customer_email' => $invoice->customer_email,
            'customer_cnic' => $invoice->customer_cnic, 'salesperson_name' => $invoice->salesperson_name,
            'business' => $business, 'outlet_name' => $business['outlet_name'] ?? $outlet->name,
            'currency' => $invoice->currency, 'total_bill' => (string) $invoice->total_bill,
            'discount' => (string) $invoice->discount, 'total_amount' => (string) $invoice->final_bill,
            'lines' => $lines, 'warranty_terms' => $this->json($invoice->warranty_terms_snapshot),
        ];
    }

    private function warranty(Outlet $outlet, string $documentId): array
    {
        $claim = DB::table('claims')->where('public_id', $documentId)->where('outlet_id', $outlet->id)->firstOrFail();
        $invoice = DB::table('invoices')->where('id', $claim->invoice_id)->where('outlet_id', $outlet->id)->firstOrFail();
        $product = DB::table('products')->where('id', $claim->product_id)->firstOrFail();
        $event = DB::table('claim_events')->where('claim_id', $claim->id)->where('sequence', 1)->firstOrFail();
        $eventSnapshot = $this->json($event->snapshot);
        $business = $this->json($invoice->business_snapshot);
        $warranty = $this->json($claim->warranty_snapshot);

        return [
            'document_type' => 'warranty', 'invoice_number' => $invoice->invoice_number, 'claim_number' => $claim->claim_number,
            'document_date' => (string) $claim->received_at, 'customer_name' => $invoice->customer_name,
            'customer_phone' => $invoice->customer_phone, 'customer_email' => $invoice->customer_email,
            'customer_cnic' => $invoice->customer_cnic, 'salesperson_name' => $invoice->salesperson_name,
            'business' => $business, 'outlet_name' => $business['outlet_name'] ?? $outlet->name,
            'product_name' => $product->name, 'product_code' => $product->product_code, 'quantity' => (int) $claim->quantity,
            'issue_description' => $claim->issue_description, 'received_condition' => $claim->received_condition,
            'accessories_received' => $claim->accessories_received, 'received_status' => $eventSnapshot['status'] ?? 'received',
            'handled_by_name' => $eventSnapshot['actor']['name'] ?? $claim->handled_by_name,
            'warranty' => $warranty, 'total_amount' => null, 'lines' => [],
        ];
    }

    private function lines(array $payload): array
    {
        $business = $payload['business'];
        $lines = [(string) ($business['business_name'] ?? 'mobiST Technologies'), (string) $payload['outlet_name']];
        if ($payload['document_type'] === 'invoice') {
            $lines[] = 'Sales Invoice '.$payload['invoice_number'];
            $lines[] = 'Date: '.$payload['document_date'];
            $lines[] = 'Customer: '.($payload['customer_name'] ?: 'Walk-in customer');
            $lines[] = 'Mobile: '.($payload['customer_phone'] ?: '-');
            $lines[] = 'Email: '.($payload['customer_email'] ?: '-');
            $lines[] = 'CNIC: '.($payload['customer_cnic'] ?: '-');
            $lines[] = 'Salesperson: '.($payload['salesperson_name'] ?: '-');
            $lines[] = 'Items:';
            foreach ($payload['lines'] as $line) {
                $lines[] = sprintf('%s x%d @ %s = %s', $line['name'], $line['quantity'], $line['unit_price'], $line['net']);
            }
            $lines[] = 'Gross: PKR '.$payload['total_bill'];
            $lines[] = 'Discount: PKR '.$payload['discount'];
            $lines[] = 'Total: PKR '.$payload['total_amount'];
            foreach ($this->textValues($payload['warranty_terms']) as $term) {
                $lines[] = 'Warranty: '.$term;
            }
        } else {
            $lines[] = 'Warranty Claim Receipt '.$payload['claim_number'];
            $lines[] = 'Invoice: '.($payload['invoice_number'] ?: '-');
            $lines[] = 'Received: '.$payload['document_date'];
            $lines[] = 'Customer: '.($payload['customer_name'] ?: '-');
            $lines[] = 'Mobile: '.($payload['customer_phone'] ?: '-');
            $lines[] = 'Email: '.($payload['customer_email'] ?: '-');
            $lines[] = 'CNIC: '.($payload['customer_cnic'] ?: '-');
            $lines[] = 'Product: '.$payload['product_name'].' x'.$payload['quantity'];
            $lines[] = 'Issue: '.$payload['issue_description'];
            $lines[] = 'Received condition: '.($payload['received_condition'] ?: '-');
            $lines[] = 'Accessories: '.($payload['accessories_received'] ?: '-');
            $lines[] = 'Handled by: '.($payload['handled_by_name'] ?: '-');
            $lines[] = 'Status at intake: '.$payload['received_status'];
            if ($payload['warranty']) {
                $lines[] = 'Warranty type: '.($payload['warranty']['type'] ?? '-');
                $lines[] = 'Warranty expires: '.($payload['warranty']['expires_at'] ?? '-');
                foreach ($this->textValues($payload['warranty']['terms'] ?? []) as $term) {
                    $lines[] = 'Warranty: '.$term;
                }
            }
        }

        return array_map(fn ($line) => mb_substr(trim((string) $line), 0, 180), $lines);
    }

    private function html(array $payload, array $lines, string $format): string
    {
        $width = $format === 'thermal80' ? '80mm' : '210mm';
        $body = implode('', array_map(fn ($line) => '<div>'.e($line).'</div>', $lines));

        return '<article data-contract="canonical-document.v1" data-type="'.e($payload['document_type']).'" style="max-width:'.$width.'">'.$body.'</article>';
    }

    private function pdf(array $lines, string $format): string
    {
        [$width, $height, $x, $y, $size] = $format === 'thermal80' ? [226, 800, 12, 780, 8] : [595, 842, 40, 810, 10];
        $stream = "BT\n/F1 {$size} Tf\n{$x} {$y} Td\n12 TL\n";
        foreach ($lines as $line) {
            $stream .= '('.$this->pdfText($line).") Tj\nT*\n";
        }
        $stream .= "ET\n";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 '.$width.' '.$height.'] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.strlen($stream)." >>\nstream\n".$stream.'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n".$object."\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    private function pdfText(string $value): string
    {
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $value);
        $encoded = $encoded === false ? preg_replace('/[^\x20-\x7E]/', '?', $value) : $encoded;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $encoded);
    }

    private function renderTemplate(string $key, array $payload): array
    {
        $revision = DB::table('document_template_revisions')->where('template_key', $key)->orderByDesc('version')->firstOrFail();
        $this->validateTemplate($key, $revision->template_text);
        $context = $this->context($payload);
        $text = preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($match) => $context[$match[1]] ?? '', $revision->template_text);

        return [$text, ['id' => $revision->public_id, 'key' => $key, 'version' => (int) $revision->version]];
    }

    private function validateTemplate(string $key, string $text): void
    {
        if ($text === '' || mb_strlen($text) > 10000) {
            throw ValidationException::withMessages(['template' => 'Document template length is invalid.']);
        }
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $text, $matches);
        foreach ($matches[1] as $placeholder) {
            if (! in_array($placeholder, self::PLACEHOLDERS, true)) {
                throw ValidationException::withMessages(['template' => 'Unknown document template placeholder: '.$placeholder]);
            }
        }
        $stripped = preg_replace('/\{\{\s*[a-z_]+\s*\}\}/', '', $text);
        if (str_contains($stripped, '{{') || str_contains($stripped, '}}')) {
            throw ValidationException::withMessages(['template' => 'Malformed document template placeholder.']);
        }
        if (str_ends_with($key, '_subject') && preg_match('/[\r\n]/', $text)) {
            throw ValidationException::withMessages(['template' => 'Email subject template cannot contain line breaks.']);
        }
    }

    private function context(array $payload): array
    {
        return [
            'customer_name' => (string) ($payload['customer_name'] ?: 'Customer'),
            'invoice_number' => (string) ($payload['invoice_number'] ?? ''),
            'claim_number' => (string) ($payload['claim_number'] ?? ''),
            'total_amount' => (string) ($payload['total_amount'] ?? ''),
            'business_name' => (string) ($payload['business']['business_name'] ?? 'mobiST Technologies'),
            'outlet_name' => (string) ($payload['outlet_name'] ?? ''),
            'document_date' => (string) ($payload['document_date'] ?? ''),
        ];
    }

    private function attempt(IdentityAccount $actor, Outlet $outlet, string $type, string $documentId, string $channel, string $key,
        array $request, array $render, bool $resend, ?string $recipient, ?string $subject, array $templateRevisions): array
    {
        Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
        $hash = hash('sha256', json_encode($this->canonical($request), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actor, $outlet, $type, $documentId, $channel, $key, $hash, $render, $resend, $recipient, $subject, $templateRevisions) {
            $identity = ['actor_admin_id' => $actor->id, 'channel' => $channel, 'document_type' => $type,
                'document_public_id' => $documentId, 'idempotency_key' => $key];
            $created = DB::table('document_delivery_attempts')->insertOrIgnore([...$identity,
                'public_id' => (string) Str::uuid(), 'outlet_id' => $outlet->id, 'state' => 'prepared', 'request_sha256' => $hash,
                'intentional_resend' => $resend, 'recipient' => $recipient, 'subject' => $subject, 'document_version' => self::VERSION,
                'document_sha256' => $render['sha256'], 'template_revisions' => json_encode($templateRevisions, JSON_THROW_ON_ERROR),
                'prepared_at' => now(), 'created_at' => now(), 'updated_at' => now()]) === 1;
            $attempt = DB::table('document_delivery_attempts')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($attempt->request_sha256, $hash) || $attempt->outlet_id !== $outlet->id
                || ! hash_equals($attempt->document_sha256, $render['sha256'])) {
                throw new LogicException('Document delivery idempotency key was already used for different content.');
            }

            return [$attempt, $created];
        }, 3);
    }

    private function authorizeRead(IdentityAccount $actor, Outlet $outlet, string $type): void
    {
        $permission = match ($type) {
            'invoice' => 'shop.invoices',
            'warranty' => 'shop.warranty',
            default => null,
        };
        abort_unless($permission && app(Access::class)->allows($actor, $permission, $outlet), 403);
    }

    private function authorizeSend(IdentityAccount $actor, Outlet $outlet, string $type): void
    {
        $this->authorizeRead($actor, $outlet, $type);
        abort_unless(app(Access::class)->allows($actor, 'shop.documents.send', $outlet), 403);
    }

    private function publicRender(array $render): array
    {
        return [
            'document_type' => $render['type'], 'format' => $render['format'], 'document_version' => $render['version'],
            'filename' => $render['filename'], 'document_sha256' => $render['sha256'],
        ];
    }

    private function attemptPayload(object $attempt): array
    {
        return ['attempt_id' => $attempt->public_id, 'document_type' => $attempt->document_type, 'channel' => $attempt->channel,
            'state' => $attempt->state, 'recipient' => $attempt->recipient, 'document_sha256' => $attempt->document_sha256,
            'provider_reference' => $attempt->provider_reference, 'failure_summary' => $attempt->failure_summary,
            'intentional_resend' => (bool) $attempt->intentional_resend, 'prepared_at' => $attempt->prepared_at, 'completed_at' => $attempt->completed_at];
    }

    private function phone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value);
        if (preg_match('/\A03\d{9}\z/', $digits)) {
            return '92'.substr($digits, 1);
        }
        if (preg_match('/\A923\d{9}\z/', $digits)) {
            return $digits;
        }
        throw ValidationException::withMessages(['phone' => 'WhatsApp number must be a Pakistani mobile number.']);
    }

    private function safeFailure(Throwable $error): string
    {
        foreach (['Gmail is not connected.', 'Gmail authorization requires reconnection.', 'Invalid email header.', 'Invalid PDF attachment.'] as $safe) {
            if (str_contains($error->getMessage(), $safe)) {
                return $safe;
            }
        }

        return 'Document email delivery failed.';
    }

    private function canonical(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }

        return $value;
    }

    private function json(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return is_array($value) ? $value : json_decode($value, true, flags: JSON_THROW_ON_ERROR);
    }

    private function textValues(mixed $value): array
    {
        $result = [];
        $walk = function (mixed $item) use (&$walk, &$result): void {
            if (is_string($item) && trim($item) !== '') {
                $result[] = trim($item);
            } elseif (is_array($item)) {
                foreach ($item as $child) {
                    $walk($child);
                }
            }
        };
        $walk($value);

        return array_slice(array_values(array_unique($result)), 0, 12);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected document delivery fields.']);
        }
    }

    private function templatePayload(object $revision): array
    {
        return ['template_id' => $revision->public_id, 'template_key' => $revision->template_key, 'version' => (int) $revision->version,
            'document_type' => $revision->document_type, 'channel' => $revision->channel, 'template_part' => $revision->template_part,
            'template_text' => $revision->template_text];
    }
}
