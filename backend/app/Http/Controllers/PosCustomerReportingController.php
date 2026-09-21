<?php

namespace App\Http\Controllers;

use App\Documents\CanonicalDocuments;
use App\Identity\Access;
use App\Models\Admin;
use App\Models\Outlet;
use App\Pos\DashboardReportPreferences;
use App\Pos\PortalPreferences;
use App\Pos\PosConfiguration;
use App\Pos\PosHistoryListing;
use App\Pos\PosWarrantyIntakeSearch;
use App\Reporting\OperationalReports;
use App\Warranty\ClaimOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosCustomerReportingController extends Controller
{
    public function index(Request $request, string $area, OperationalReports $reports)
    {
        [$actor, $outlet] = $this->context($request);
        $this->authorizeArea($actor, $outlet, $area);

        $invoices = [];
        $customers = [];
        $claims = [];
        $saleCandidates = [];
        $report = null;
        $pagination = null;

        if ($area === 'invoices') {
            $listing = app(PosHistoryListing::class)->page($request, 'invoices',
                DB::table('invoices as i')->where('i.outlet_id', $outlet->id));
            $pagination = $listing['pagination'];
            $invoices = collect($listing['rows'])->map(fn ($row) => [
                'id' => $row->public_id, 'number' => $row->invoice_number, 'customer_id' => $row->customer_id,
                'customer_name' => $row->customer_name, 'customer_phone' => $row->customer_phone,
                'customer_email' => $row->customer_email, 'customer_cnic' => $row->customer_cnic,
                'salesperson_name' => $row->salesperson_name, 'final_bill' => (string) $row->final_bill,
                'currency' => $row->currency, 'created_at' => $row->created_at,
            ])->all();
            $customerIds = collect($invoices)->pluck('customer_id')->filter()->unique()->values();
            if ($customerIds->isNotEmpty()) {
                $customers = DB::table('customers')->whereIn('id', $customerIds)->whereNull('archived_at')
                    ->orderBy('display_name')->get(['id', 'public_id', 'display_name', 'email', 'mobile', 'version'])
                    ->map(function ($row) use ($invoices) {
                        $history = collect($invoices)->where('customer_id', $row->id)->values()->all();

                        return [
                            'id' => $row->public_id, 'name' => $row->display_name, 'email' => $row->email,
                            'mobile' => $row->mobile, 'version' => (int) $row->version, 'invoices' => $history,
                        ];
                    })->all();
            }
        }

        if (in_array($area, ['warranty', 'claims'], true)) {
            $claimQuery = DB::table('claims as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
                ->join('products as p', 'p.id', '=', 'c.product_id')->where('c.outlet_id', $outlet->id)
                ->whereColumn('i.outlet_id', 'c.outlet_id')->whereColumn('p.outlet_id', 'c.outlet_id');
            if (in_array($area, ['claims', 'warranty'], true)) {
                $listing = app(PosHistoryListing::class)->page($request, $area, $claimQuery);
                $pagination = $listing['pagination'];
                $claimRows = collect($listing['rows']);
            }
            $claims = $claimRows->map(fn ($row) => [
                'id' => $row->public_id, 'number' => $row->claim_number, 'status' => $row->status,
                'version' => (int) $row->version, 'quantity' => (int) $row->quantity,
                'received_at' => $row->received_at, 'expected_completion_at' => $row->expected_completion_at,
                'invoice_id' => $row->invoice_id, 'invoice_number' => $row->invoice_number,
                'customer_name' => $row->customer_name, 'customer_phone' => $row->customer_phone,
                'product_name' => $row->product_name,
            ])->all();

            $saleCandidates = DB::table('sales as s')->join('invoices as i', 'i.id', '=', 's.invoice_id')
                ->join('products as p', 'p.id', '=', 's.product_id')->where('s.outlet_id', $outlet->id)
                ->orderByDesc('s.id')->limit(100)->get([
                    's.id', 's.public_id', 's.quantity', 's.returned_quantity', 's.invoice_detail_snapshot',
                    'i.public_id as invoice_id', 'i.invoice_number', 'i.customer_name', 'p.name as product_name', 'p.track_imei',
                ])->map(function ($row) {
                    $snapshot = json_decode($row->invoice_detail_snapshot, true) ?: [];
                    $units = $row->track_imei ? DB::table('stock_units')->where('sale_id', $row->id)
                        ->where('status', 'sold')->get(['public_id', 'unit_code'])
                        ->map(fn ($unit) => ['id' => $unit->public_id, 'code' => $unit->unit_code])->all() : [];

                    return [
                        'sale_id' => $row->public_id, 'invoice_id' => $row->invoice_id, 'invoice_number' => $row->invoice_number,
                        'customer_name' => $row->customer_name, 'product_name' => $snapshot['name'] ?? $row->product_name,
                        'quantity' => (int) $row->quantity, 'returned_quantity' => (int) $row->returned_quantity,
                        'track_imei' => (bool) $row->track_imei, 'units' => $units,
                        'warranty_type' => $snapshot['warranty_type'] ?? null,
                        'warranty_unit' => $snapshot['warranty_unit'] ?? null,
                        'warranty_duration' => $snapshot['warranty_duration'] ?? null,
                    ];
                })->all();
        }

        if ($area === 'reports') {
            $report = $reports->summary($actor, $outlet, $request->query('from'), $request->query('to'));
        }

        return response()->json(['data' => [
            'area' => $area, 'outlet' => ['id' => $outlet->public_id, 'name' => $outlet->name],
            'can_send_documents' => app(Access::class)->allows($actor, 'shop.documents.send', $outlet),
            'invoice_default_format' => $area === 'invoices'
                ? (app(PosConfiguration::class)->documentPresentation('invoice')['settings']['default_output_format'] === 'thermal' ? 'thermal80' : 'a4')
                : null,
            'invoices' => $invoices, 'customers' => $customers, 'claims' => $claims,
            'sale_candidates' => $saleCandidates, 'report' => $report, 'pagination' => $pagination,
            'report_presentation' => $area === 'reports' ? ['sections' => app(DashboardReportPreferences::class)->current()] : null,
            'warranty_intake_category' => $area === 'claims' ? app(PortalPreferences::class)->current()['warranty_search_category'] : null,
        ]]);
    }

    public function searchWarrantyIntake(Request $request, PosWarrantyIntakeSearch $search)
    {
        [$actor, $outlet] = $this->context($request);
        $this->authorizeArea($actor, $outlet, 'claims');

        return response()->json(['data' => ['sale_candidates' => $search->search($request, $outlet)]]);
    }

    public function claim(Request $request, string $claim, ClaimOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->view($actor, $outlet, $claim)]);
    }

    public function openClaim(Request $request, ClaimOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->open($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function updateClaim(Request $request, string $claim, ClaimOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->update($actor, $outlet, $claim, $this->key($request), $request->all())]);
    }

    public function documentPreview(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);
        $format = (string) $request->query('format', 'a4');

        return response()->json(['data' => $service->preview($actor, $outlet, $type, $document, $format)]);
    }

    public function documentPrint(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);
        $format = (string) $request->query('format', 'a4');

        return response()->json(['data' => $service->printable($actor, $outlet, $type, $document, $format)]);
    }

    public function documentPdf(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);
        $format = (string) $request->query('format', 'a4');
        $data = $service->savePdf($actor, $outlet, $type, $document, $format);
        $data['pdf_base64'] = base64_encode($data['pdf']);
        unset($data['pdf']);

        return response()->json(['data' => $data]);
    }

    public function emailDraft(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->emailDraft($actor, $outlet, $type, $document)]);
    }

    public function emailSend(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $request->validate([
            'to' => 'required|email:rfc|max:255', 'subject' => 'required|string|max:500',
            'message' => 'required|string|max:10000', 'resend' => 'sometimes|boolean',
        ]);

        return response()->json(['data' => $service->sendEmail(
            $actor, $outlet, $type, $document, $this->key($request),
            ['to' => $data['to'], 'subject' => $data['subject'], 'message' => $data['message']],
            (bool) ($data['resend'] ?? false),
        )]);
    }

    public function whatsapp(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $request->validate([
            'phone' => 'nullable|string|max:40', 'message' => 'nullable|string|max:5000',
            'requires_attachment' => 'sometimes|boolean',
        ]);
        $input = array_filter($data, fn ($value) => $value !== null);
        $prepared = $service->prepareWhatsapp($actor, $outlet, $type, $document, $this->key($request), $input);
        if (is_array($prepared['attachment'] ?? null)) {
            $prepared['attachment']['pdf_base64'] = base64_encode($prepared['attachment']['pdf']);
            unset($prepared['attachment']['pdf']);
        }

        return response()->json(['data' => $prepared]);
    }

    public function whatsappOpened(Request $request, string $attempt, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->markWhatsappOpened($actor, $outlet, $attempt)]);
    }

    public function deliveryHistory(Request $request, string $type, string $document, CanonicalDocuments $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->history($actor, $outlet, $type, $document)]);
    }

    public function report(Request $request, OperationalReports $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->summary(
            $actor, $outlet, $request->query('from'), $request->query('to'),
        )]);
    }

    public function reportCsv(Request $request, OperationalReports $service)
    {
        [$actor, $outlet] = $this->context($request);
        $report = $service->summary($actor, $outlet, $request->query('from'), $request->query('to'));
        $rows = [
            ['section', 'metric', 'value'],
        ];
        foreach (['sales', 'payments', 'categories', 'activity'] as $section) {
            foreach (($report[$section] ?? []) as $metric => $value) {
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                }
                $rows[] = [$section, $metric, (string) $value];
            }
        }
        $stream = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($stream, $row);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response()->json(['data' => ['filename' => 'pos-report.csv', 'csv' => $csv]]);
    }

    private function authorizeArea(Admin $actor, Outlet $outlet, string $area): void
    {
        $permission = match ($area) {
            'invoices' => 'shop.invoices',
            'warranty' => 'shop.warranty',
            'claims' => 'shop.claims',
            'reports' => 'reports.view',
            default => null,
        };
        abort_unless($permission && app(Access::class)->allows($actor, $permission, $outlet), 403);
    }

    private function context(Request $request): array
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $outletId = (int) $request->session()->get('active_outlet_id', 0);
        $outlet = $actor->shops()->whereKey($outletId)->where('outlets.status', false)
            ->whereNull('outlets.archived_at')->first();
        abort_unless($outlet instanceof Outlet, 403);

        return [$actor->fresh(), $outlet->fresh()];
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key is required.']);
        }

        return $key;
    }
}
