<?php

namespace App\Identity;

use App\Addendum\MoneySnapshot;
use App\Documents\CanonicalDocuments;
use App\Models\Admin;
use App\Models\Outlet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OutletLifecycleAdministration
{
    public function canManage(Admin $actor): bool
    {
        $fresh = $actor->fresh();

        return $fresh->usable() && $fresh->hasPermission('team-members.full-access.assign')
            && $fresh->hasPermission('admin.business-profile.manage')
            && $fresh->roles()->where('roles.is_protected', true)->whereNull('roles.archived_at')->exists();
    }

    public function catalogue(Admin $actor): array
    {
        abort_unless($this->canManage($actor), 403);

        return Outlet::query()->orderBy('outlet_code')->get()->map(fn (Outlet $outlet) => $this->view($outlet))->all();
    }

    /** Explicit, audited owner-only retrieval of one original archived invoice; never part of the summary. */
    public function archivedInvoiceDocument(Admin $actor, string $outletId, string $invoiceId, string $password, string $purpose): array
    {
        abort_unless($this->canManage($actor), 403);
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && Hash::check($password, $fresh->password),
            403, 'Confirm your current Admin password to retrieve historical invoice data.');
        $outlet = Outlet::where('public_id', $outletId)->firstOrFail();
        abort_unless($outlet->archived_at !== null, 409, 'Outlet is not archived.');
        $invoice = DB::table('invoices')->where('outlet_id', $outlet->id)
            ->where('public_id', $invoiceId)->firstOrFail();
        // Select explicit original fields: no payment credential, provider payload, or unrelated customer lookup.
        $linesQuery = DB::table('sales as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.invoice_id', $invoice->id)->where('s.outlet_id', $outlet->id)
            ->where('p.outlet_id', $outlet->id)->orderBy('s.id');
        $lines = (clone $linesQuery)->limit(100)
            ->get(['s.public_id', 'p.public_id as product_id', 'p.name as product_name',
                's.sale_date', 's.quantity', 's.returned_quantity', 's.sale_price', 's.net_total_price'])
            ->map(fn ($line) => ['id' => $line->public_id, 'product_id' => $line->product_id,
                'product_name' => $line->product_name, 'sale_date' => $line->sale_date,
                'quantity' => (int) $line->quantity, 'returned_quantity' => (int) $line->returned_quantity,
                'unit_price' => (string) $line->sale_price, 'net_amount' => (string) $line->net_total_price])->all();
        IdentityAudit::record('admin', $fresh->id, 'archived_invoice_document_retrieved',
            'invoice:'.$invoice->public_id.';purpose:'.preg_replace('/\s+/u', ' ', trim($purpose)), $outlet->id);

        return ['outlet_id' => $outlet->public_id, 'invoice_id' => $invoice->public_id,
            'invoice_number' => $invoice->invoice_number, 'currency' => $invoice->currency,
            'total_bill' => (string) $invoice->total_bill, 'final_bill' => (string) $invoice->final_bill,
            'customer_name' => $invoice->customer_name, 'customer_phone' => $invoice->customer_phone,
            'customer_cnic' => $invoice->customer_cnic, 'created_at' => $invoice->created_at,
            'line_count' => (clone $linesQuery)->count(), 'lines_truncated' => (clone $linesQuery)->count() > 100,
            'lines' => $lines, 'retrieval_mode' => 'original_database_record_read_only'];
    }

    /** Separately audited reconstruction: this does not recover the originally issued PDF bytes. */
    public function archivedInvoicePdf(Admin $actor, string $outletId, string $invoiceId, string $password, string $purpose): array
    {
        // Reuse the exact password, Full Access, archived-state and same-outlet document checks.
        $this->archivedInvoiceDocument($actor, $outletId, $invoiceId, $password, $purpose);
        $outlet = Outlet::where('public_id', $outletId)->firstOrFail();
        $result = app(CanonicalDocuments::class)
            ->reconstructArchivedInvoice($actor, $outlet, $invoiceId);
        IdentityAudit::record('admin', $actor->id, 'archived_invoice_pdf_reconstructed',
            'invoice:'.$invoiceId.';purpose:'.preg_replace('/\s+/u', ' ', trim($purpose)), $outlet->id);

        return $result;
    }

    /** Per-case password-confirmed warranty history. Never include sensitive case notes in archive indexes. */
    public function archivedClaimDocument(Admin $actor, string $outletId, string $claimId, string $password, string $purpose): array
    {
        abort_unless($this->canManage($actor), 403);
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && Hash::check($password, $fresh->password),
            403, 'Confirm your current Admin password to retrieve historical warranty data.');
        $outlet = Outlet::where('public_id', $outletId)->firstOrFail();
        abort_unless($outlet->archived_at !== null, 409, 'Outlet is not archived.');
        $claim = DB::table('claims as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->join('products as p', 'p.id', '=', 'c.product_id')
            ->where('c.public_id', $claimId)->where('c.outlet_id', $outlet->id)
            ->where('i.outlet_id', $outlet->id)->where('p.outlet_id', $outlet->id)
            ->firstOrFail(['c.*', 'i.public_id as invoice_public_id', 'p.public_id as product_public_id']);
        $eventsQuery = DB::table('claim_events')->where('claim_id', $claim->id)->orderBy('sequence');
        $events = (clone $eventsQuery)->limit(100)
            ->get(['public_id', 'sequence', 'status', 'note', 'occurred_at', 'snapshot_sha256'])
            ->map(fn ($event) => ['id' => $event->public_id, 'sequence' => (int) $event->sequence,
                'status' => $event->status, 'note' => $event->note,
                'occurred_at' => $event->occurred_at, 'snapshot_sha256' => $event->snapshot_sha256])->all();
        IdentityAudit::record('admin', $fresh->id, 'archived_claim_document_retrieved',
            'claim:'.$claim->public_id.';purpose:'.preg_replace('/\s+/u', ' ', trim($purpose)), $outlet->id);

        return ['outlet_id' => $outlet->public_id, 'claim_id' => $claim->public_id,
            'claim_number' => $claim->claim_number, 'invoice_id' => $claim->invoice_public_id,
            'product_id' => $claim->product_public_id, 'quantity' => (int) $claim->quantity,
            'status' => $claim->status, 'issue_description' => $claim->issue_description,
            'diagnosis' => $claim->diagnosis, 'resolution' => $claim->resolution,
            'internal_notes' => $claim->internal_notes, 'received_at' => $claim->received_at,
            'delivered_at' => $claim->delivered_at, 'warranty_expires_at' => $claim->warranty_expires_at,
            'event_count' => (clone $eventsQuery)->count(), 'events_truncated' => (clone $eventsQuery)->count() > 100,
            'events' => $events, 'retrieval_mode' => 'original_database_record_read_only'];
    }

    public function archivedHistory(Admin $actor, string $publicId): array
    {
        abort_unless($this->canManage($actor), 403);
        $outlet = Outlet::where('public_id', $publicId)->firstOrFail();
        abort_unless($outlet->archived_at !== null, 409, 'Outlet is not archived.');
        $promotionHistory = $this->archivedPromotionHistory($outlet);

        return [
            'outlet_code' => $outlet->outlet_code,
            'name' => $outlet->name,
            'archived_at' => $outlet->archived_at->toIso8601String(),
            'cash_sessions' => DB::table('cash_sessions')->where('outlet_id', $outlet->id)
                ->where('status', 'closed')->orderByDesc('business_date')->limit(50)
                ->get(['public_id', 'business_date', 'status', 'expected_cash', 'actual_cash', 'closed_at'])
                ->map(fn ($row) => [
                    'id' => $row->public_id, 'business_date' => $row->business_date,
                    'status' => $row->status, 'expected_cash' => (string) $row->expected_cash,
                    'actual_cash' => (string) $row->actual_cash, 'closed_at' => $row->closed_at,
                ])->all(),
            'obligations' => $this->archivedObligations($outlet, $promotionHistory),
            'promotion_history' => $promotionHistory,
            'stock_reconciliation' => $this->archivedStockReconciliation($outlet),
            'procurement_repair_history' => $this->archivedProcurementRepairs($outlet),
            'cash_session_count' => DB::table('cash_sessions')->where('outlet_id', $outlet->id)->count(),
            'cash_entry_count' => DB::table('cash_entries')->where('outlet_id', $outlet->id)->count(),
            // Owner-only historical invoice/sale and warranty-claim summary, without customer PII,
            // claim narratives, internal notes, payment instruments or event snapshot payloads.
            'sales_claim_history' => [
                'invoice_count' => DB::table('invoices')->where('outlet_id', $outlet->id)->count(),
                'sale_line_count' => DB::table('sales')->where('outlet_id', $outlet->id)->count(),
                'claim_count' => DB::table('claims')->where('outlet_id', $outlet->id)->count(),
                'claim_event_count' => DB::table('claim_events as e')->join('claims as c', 'c.id', '=', 'e.claim_id')
                    ->where('c.outlet_id', $outlet->id)->count(),
                'invoices' => $this->archivedInvoices($outlet),
                'claims' => $this->archivedClaims($outlet),
            ],
            // Protected read-only stock and transfer summaries exclude supplier identities, documents and IMEIs.
            'stock_history' => [
                'product_count' => DB::table('products')->where('outlet_id', $outlet->id)->count(),
                'unit_count' => DB::table('stock_units as u')->join('products as p', 'p.id', '=', 'u.product_id')
                    ->where('p.outlet_id', $outlet->id)->count(),
                'movement_count' => DB::table('stock_movements')->where('outlet_id', $outlet->id)->count(),
                'acquisition_count' => DB::table('stock_acquisitions')->where('outlet_id', $outlet->id)->count(),
                'stocktake_count' => DB::table('stocktake_sessions')->where('outlet_id', $outlet->id)->count(),
                'transfer_count' => DB::table('stock_transfers')->where('source_outlet_id', $outlet->id)
                    ->orWhere('destination_outlet_id', $outlet->id)->count(),
                // Both source and destination are owners of retained transfer history.
                'transfers' => $this->archivedTransfers($outlet),
                'products' => DB::table('products')->where('outlet_id', $outlet->id)->orderBy('id')->limit(50)
                    ->get(['public_id', 'product_code', 'name', 'qty', 'isDeleted'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'code' => $row->product_code,
                        'name' => $row->name, 'quantity' => (int) $row->qty,
                        'product_archived' => (bool) $row->isDeleted])->all(),
                'movements' => DB::table('stock_movements as m')->join('products as p', 'p.id', '=', 'm.product_id')
                    ->where('m.outlet_id', $outlet->id)->where('p.outlet_id', $outlet->id)
                    ->orderByDesc('m.id')->limit(50)
                    ->get(['p.public_id as product_id', 'm.type', 'm.quantity_change', 'm.stock_after', 'm.created_at'])
                    ->map(fn ($row) => ['product_id' => $row->product_id, 'type' => $row->type,
                        'quantity_change' => (int) $row->quantity_change,
                        'stock_after' => (int) $row->stock_after, 'created_at' => $row->created_at])->all(),
            ],
        ];
    }

    /** Database consistency indicators only. Physical stock must be independently counted and signed off. */
    private function archivedStockReconciliation(Outlet $outlet): array
    {
        $products = DB::table('products as p')->where('p.outlet_id', $outlet->id);
        $latestMovement = '(SELECT m.stock_after FROM stock_movements m WHERE m.product_id = p.id AND m.outlet_id = p.outlet_id ORDER BY m.id DESC LIMIT 1)';
        $inStockUnitCount = '(SELECT COUNT(*) FROM stock_units u WHERE u.product_id = p.id AND u.status = \'in_stock\')';

        return [
            'negative_stock_product_count' => (clone $products)->where('p.qty', '<', 0)->count(),
            'latest_movement_disagreement_count' => (clone $products)
                ->whereRaw($latestMovement.' IS NOT NULL AND p.qty <> '.$latestMovement)->count(),
            'tracked_unit_disagreement_count' => (clone $products)->where('p.track_imei', true)
                ->whereRaw('p.qty <> '.$inStockUnitCount)->count(),
            'untracked_product_with_units_count' => (clone $products)->where('p.track_imei', false)
                ->whereRaw($inStockUnitCount.' > 0')->count(),
            'positive_stock_without_movement_count' => (clone $products)->where('p.qty', '>', 0)
                ->whereRaw($latestMovement.' IS NULL')->count(),
            'physical_count_verified' => false,
            'requires_manual_reconciliation' => true,
            'archive_eligibility' => 'not_approved_for_business_history',
        ];
    }

    /** Restricted warranty lineage: immutable audit hashes only, never customer or case narrative. */
    private function archivedClaims(Outlet $outlet): array
    {
        return DB::table('claims as c')->join('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->where('c.outlet_id', $outlet->id)->where('i.outlet_id', $outlet->id)
            ->orderByDesc('c.id')->limit(50)
            ->get(['c.id', 'c.public_id', 'c.claim_number', 'c.status', 'c.quantity',
                'c.warranty_expires_at', 'c.received_at', 'c.delivered_at', 'i.public_id as invoice_id'])
            ->map(function ($claim) {
                $events = DB::table('claim_events')->where('claim_id', $claim->id)
                    ->orderBy('sequence')->limit(50)
                    ->get(['public_id', 'sequence', 'status', 'snapshot_sha256', 'occurred_at'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'sequence' => (int) $row->sequence,
                        'status' => $row->status, 'snapshot_sha256' => $row->snapshot_sha256,
                        'occurred_at' => $row->occurred_at])->all();

                return ['id' => $claim->public_id, 'number' => $claim->claim_number,
                    'status' => $claim->status, 'invoice_id' => $claim->invoice_id,
                    'quantity' => (int) $claim->quantity, 'warranty_expires_at' => $claim->warranty_expires_at,
                    'received_at' => $claim->received_at, 'delivered_at' => $claim->delivered_at,
                    'event_count' => DB::table('claim_events')->where('claim_id', $claim->id)->count(),
                    'requires_review' => $claim->status !== 'closed', 'events' => $events];
            })->all();
    }

    /** Owner-only references: no supplier contacts, supplier prices, device identifiers or repair case notes. */
    private function archivedProcurementRepairs(Outlet $outlet): array
    {
        $id = $outlet->id;

        return [
            'supplier_count' => DB::table('suppliers')->where('outlet_id', $id)->count(),
            'purchase_order_count' => DB::table('purchase_orders')->where('outlet_id', $id)->count(),
            'repair_count' => DB::table('repair_jobs')->where('outlet_id', $id)->count(),
            'purchase_orders' => DB::table('purchase_orders as p')
                ->join('suppliers as s', 's.id', '=', 'p.supplier_id')
                ->where('p.outlet_id', $id)->where('s.outlet_id', $id)
                ->orderByDesc('p.id')->limit(50)
                ->get(['p.id', 'p.public_id', 'p.order_number', 'p.status', 'p.ordered_at',
                    's.public_id as supplier_id'])
                ->map(function ($po) use ($id) {
                    $lineCount = DB::table('purchase_order_lines')->where('purchase_order_id', $po->id)
                        ->where('outlet_id', $id)->count();
                    $outstanding = DB::table('purchase_order_lines')->where('purchase_order_id', $po->id)
                        ->where('outlet_id', $id)
                        ->selectRaw('COALESCE(SUM(GREATEST(ordered_quantity - received_quantity, 0)), 0) as total')
                        ->value('total');
                    $events = DB::table('purchase_order_events')->where('purchase_order_id', $po->id)
                        ->orderBy('sequence')->limit(50)->get(['sequence', 'event', 'snapshot_sha256', 'occurred_at'])
                        ->map(fn ($event) => ['sequence' => (int) $event->sequence, 'event' => $event->event,
                            'snapshot_sha256' => $event->snapshot_sha256, 'occurred_at' => $event->occurred_at])->all();

                    return ['id' => $po->public_id, 'number' => $po->order_number, 'status' => $po->status,
                        'supplier_id' => $po->supplier_id, 'ordered_at' => $po->ordered_at,
                        'line_count' => $lineCount,
                        'receipt_count' => DB::table('purchase_order_receipts')->where('purchase_order_id', $po->id)
                            ->where('outlet_id', $id)->count(),
                        'event_count' => DB::table('purchase_order_events')->where('purchase_order_id', $po->id)->count(),
                        'unreceived_quantity' => (int) $outstanding,
                        'requires_review' => in_array($po->status, ['ordered', 'partially_received'], true),
                        'events' => $events];
                })->all(),
            'repairs' => DB::table('repair_jobs as j')
                ->leftJoin('invoices as i', function ($join) use ($id) {
                    $join->on('i.id', '=', 'j.invoice_id')->where('i.outlet_id', '=', $id);
                })->where('j.outlet_id', $id)->orderByDesc('j.id')->limit(50)
                ->get(['j.id', 'j.public_id', 'j.repair_number', 'j.status',
                    'j.invoice_id', 'i.public_id as invoice_public_id', 'j.received_at', 'j.closed_at'])
                ->map(function ($repair) {
                    $events = DB::table('repair_events')->where('repair_job_id', $repair->id)
                        ->orderBy('sequence')->limit(50)->get(['sequence', 'event_type', 'status', 'snapshot_sha256'])
                        ->map(fn ($event) => ['sequence' => (int) $event->sequence,
                            'event_type' => $event->event_type, 'status' => $event->status,
                            'snapshot_sha256' => $event->snapshot_sha256])->all();

                    return ['id' => $repair->public_id, 'number' => $repair->repair_number,
                        'status' => $repair->status, 'invoice_id' => $repair->invoice_public_id,
                        'received_at' => $repair->received_at, 'closed_at' => $repair->closed_at,
                        'event_count' => DB::table('repair_events')->where('repair_job_id', $repair->id)->count(),
                        'part_consumption_count' => DB::table('repair_part_consumptions')
                            ->where('repair_job_id', $repair->id)->count(),
                        'payment_record_count' => DB::table('repair_payment_links')
                            ->where('repair_job_id', $repair->id)->count(),
                        'requires_review' => ! in_array($repair->status, ['closed', 'cancelled'], true)
                            || ($repair->invoice_id !== null && $repair->invoice_public_id === null),
                        'events' => $events];
                })->all(),
        ];
    }

    /** Owner-only invoice lineage with immutable reference hashes; no customers, destinations or payloads. */
    private function archivedInvoices(Outlet $outlet): array
    {
        return DB::table('invoices')->where('outlet_id', $outlet->id)
            ->orderByDesc('id')->limit(50)
            ->get(['id', 'public_id', 'invoice_number', 'currency', 'final_bill', 'created_at'])
            ->map(function ($invoice) use ($outlet) {
                $sales = DB::table('sales as s')->join('products as p', 'p.id', '=', 's.product_id')
                    ->where('s.invoice_id', $invoice->id)->where('s.outlet_id', $outlet->id)
                    ->where('p.outlet_id', $outlet->id)->orderBy('s.id')->limit(50)
                    ->get(['s.public_id', 'p.public_id as product_id', 's.quantity',
                        's.returned_quantity', 's.net_total_price'])
                    ->map(fn ($line) => ['id' => $line->public_id, 'product_id' => $line->product_id,
                        'quantity' => (int) $line->quantity, 'returned_quantity' => (int) $line->returned_quantity,
                        'net_total_price' => (string) $line->net_total_price])->all();
                $returns = DB::table('returns')->where('invoice_id', $invoice->id)
                    ->orderBy('id')->limit(50)->get(['id', 'public_id', 'status', 'order_id'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'status' => $row->status,
                        'channel' => $row->order_id === null ? 'pos' : 'website',
                        'line_count' => DB::table('return_lines')->where('return_id', $row->id)->count(),
                        'recorded_pos_refund_count' => DB::table('pos_refund_allocations')
                            ->where('invoice_id', $invoice->id)->where('outlet_id', $outlet->id)
                            ->where('return_id', $row->id)->count()])->all();
                $tenders = DB::table('pos_tender_allocations')->where('invoice_id', $invoice->id)
                    ->where('outlet_id', $outlet->id)->orderBy('id')->limit(50)
                    ->get(['public_id', 'method', 'amount', 'reconciliation_state', 'snapshot_sha256'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'method' => $row->method,
                        'amount' => (string) $row->amount, 'reconciliation_state' => $row->reconciliation_state,
                        'snapshot_sha256' => $row->snapshot_sha256])->all();
                $refunds = DB::table('pos_refund_allocations')->where('invoice_id', $invoice->id)
                    ->where('outlet_id', $outlet->id)->orderBy('id')->limit(50)
                    ->get(['public_id', 'original_method', 'refund_method', 'amount', 'is_override', 'snapshot_sha256'])
                    ->map(fn ($row) => ['id' => $row->public_id, 'original_method' => $row->original_method,
                        'refund_method' => $row->refund_method, 'amount' => (string) $row->amount,
                        'is_override' => (bool) $row->is_override, 'snapshot_sha256' => $row->snapshot_sha256])->all();

                return ['id' => $invoice->public_id, 'number' => $invoice->invoice_number,
                    'currency' => $invoice->currency, 'final_bill' => (string) $invoice->final_bill,
                    'created_at' => $invoice->created_at,
                    'sale_line_count' => DB::table('sales')->where('invoice_id', $invoice->id)
                        ->where('outlet_id', $outlet->id)->count(),
                    'return_count' => DB::table('returns')->where('invoice_id', $invoice->id)->count(),
                    'tender_count' => DB::table('pos_tender_allocations')->where('invoice_id', $invoice->id)
                        ->where('outlet_id', $outlet->id)->count(),
                    'refund_count' => DB::table('pos_refund_allocations')->where('invoice_id', $invoice->id)
                        ->where('outlet_id', $outlet->id)->count(),
                    'sales' => $sales, 'returns' => $returns, 'tenders' => $tenders, 'refunds' => $refunds];
            })->all();
    }

    /** Outlet-scoped promotion evidence; claims are not settlement or archival clearance. */
    private function archivedPromotionHistory(Outlet $outlet): array
    {
        // Include direct outlet campaigns, global campaigns linked to this outlet's products,
        // and claims used on this outlet's invoices or website order lines.
        $campaigns = DB::table('promotions as p')
            ->leftJoin('promotion_products as pp', 'pp.promotion_id', '=', 'p.id')
            ->leftJoin('products as product', 'product.id', '=', 'pp.product_id')
            ->where(fn ($q) => $q->where('p.outlet_id', $outlet->id)
                ->orWhere('product.outlet_id', $outlet->id))->select('p.id');
        // Mixed-outlet website orders cannot attribute an order-level claim to one outlet.
        // Hold their claims for manual review without showing cross-outlet claim identities.
        $otherOutletOrderIds = DB::table('order_items')->where(fn ($q) => $q
            ->where('outlet_id', '!=', $outlet->id)->orWhereNull('outlet_id'))->select('order_id');
        $outletOrderIds = DB::table('order_items')->where('outlet_id', $outlet->id)->select('order_id');
        $sharedOrderIds = DB::table('order_items')->where('outlet_id', $outlet->id)
            ->whereIn('order_id', $otherOutletOrderIds)->select('order_id');
        $sharedOrderClaimsHeld = DB::table('promotion_claims')
            ->whereIn('order_id', $sharedOrderIds)->count();
        // A globally scoped campaign may be linked to many outlets. Its unbound claims
        // and claims bound to OTHER outlets must never be attributed or disclosed here.
        $claims = DB::table('promotion_claims as c')->where(function ($q) use ($outlet, $outletOrderIds, $otherOutletOrderIds) {
            $q->whereIn('c.invoice_id', DB::table('invoices')->where('outlet_id', $outlet->id)->select('id'))
                ->orWhere(fn ($website) => $website->whereIn('c.order_id', $outletOrderIds)
                    ->whereNotIn('c.order_id', $otherOutletOrderIds))
                ->orWhere(function ($unbound) use ($outlet) {
                    $unbound->whereNull('c.invoice_id')->whereNull('c.order_id')
                        ->whereIn('c.promotion_id', DB::table('promotions')
                            ->where('outlet_id', $outlet->id)->select('id'));
                });
        });
        $campaignCount = DB::table('promotions')->whereIn('id', $campaigns)->count();
        $claimCount = (clone $claims)->count();
        $lines = (clone $claims)->join('promotions as p', 'p.id', '=', 'c.promotion_id')
            ->leftJoin('invoices as i', 'i.id', '=', 'c.invoice_id')
            ->leftJoin('orders as o', 'o.id', '=', 'c.order_id')
            ->orderByDesc('c.id')->limit(50)
            ->get(['c.public_id', 'p.public_id as promotion_id', 'c.channel', 'c.status',
                'c.discount_amount', 'c.snapshot_sha256', 'c.released_at',
                'i.public_id as invoice_id', 'o.public_id as order_id'])
            ->map(fn ($row) => ['id' => $row->public_id, 'promotion_id' => $row->promotion_id,
                'channel' => $row->channel, 'status' => $row->status,
                'discount_amount' => (string) $row->discount_amount,
                'snapshot_sha256' => $row->snapshot_sha256, 'released_at' => $row->released_at,
                'invoice_id' => $row->invoice_id, 'order_id' => $row->order_id])->all();

        // A claim is booked only when its original invoice/order has a matching internal
        // immutable monetary adjustment. This is NOT evidence of bank/provider settlement.
        $bound = (clone $claims)->where(fn ($q) => $q->whereNotNull('c.invoice_id')
            ->orWhereNotNull('c.order_id'));
        $reference = fn ($query) => $query->selectRaw('1')->from('monetary_adjustments as a')
            ->whereColumn('a.source_reference', 'c.public_id');
        $financialReferenceMissing = (clone $bound)->whereNotExists($reference)->count();
        $financialReferenceMismatch = (clone $bound)->whereExists(fn ($query) => $reference($query)
            ->whereRaw("NOT (a.kind = 'promotion' AND a.treatment = 'discount' AND a.currency = 'PKR'
                AND a.amount = c.discount_amount AND (a.invoice_id <=> c.invoice_id)
                AND (a.order_id <=> c.order_id))"))->count();
        // The database already enforces unique source references; check the opposite
        // direction for promotion adjustments without an original claim/owner match.
        $outletAdjustments = DB::table('monetary_adjustments as a')->where('a.kind', 'promotion')
            ->where(fn ($q) => $q->whereIn('a.invoice_id', DB::table('invoices')
                ->where('outlet_id', $outlet->id)->select('id'))
                ->orWhere(fn ($order) => $order->whereIn('a.order_id', $outletOrderIds)
                    ->whereNotIn('a.order_id', $otherOutletOrderIds)));
        $unmatchedFinancialAdjustments = (clone $outletAdjustments)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('promotion_claims as c')
                ->whereColumn('c.public_id', 'a.source_reference')
                ->whereRaw('(c.invoice_id <=> a.invoice_id) AND (c.order_id <=> a.order_id)'))
            ->count();
        // Financial adjustment v1 has a defined canonical digest; claim JSON does not
        // retain its original serialized bytes. Check ALL attributed bound claims.
        $financialSnapshotMismatches = 0;
        foreach ((clone $bound)->join('monetary_adjustments as a', 'a.source_reference', '=', 'c.public_id')
            ->select(['a.id', 'a.kind', 'a.treatment', 'a.amount', 'a.currency', 'a.source_reference',
                'a.snapshot', 'a.snapshot_sha256', 'a.invoice_id', 'a.order_id',
                'c.invoice_id as claim_invoice_id', 'c.order_id as claim_order_id'])->cursor() as $entry) {
            try {
                $snapshot = json_decode($entry->snapshot, true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($snapshot) || ! is_string($snapshot['reason'] ?? null)) {
                    throw new \UnexpectedValueException('Invalid adjustment snapshot.');
                }
                $canonical = MoneySnapshot::adjustment(
                    $entry->id, $entry->kind, (string) $entry->amount,
                    $snapshot['reason'], $entry->source_reference);
                ksort($snapshot);
                ksort($canonical);
                if ($snapshot !== $canonical
                    || ! hash_equals((string) $entry->snapshot_sha256, MoneySnapshot::digest($canonical))
                    || $entry->kind !== $canonical['kind'] || $entry->treatment !== $canonical['treatment']
                    || $entry->currency !== $canonical['currency'] || (string) $entry->amount !== $canonical['amount']
                    || $entry->invoice_id !== $entry->claim_invoice_id
                    || $entry->order_id !== $entry->claim_order_id) {
                    $financialSnapshotMismatches++;
                }
            } catch (\Throwable $exception) {
                $financialSnapshotMismatches++;
            }
        }
        // Compare retained JSON values only; original serialized claim bytes are unavailable.
        // A missing or divergent claimed event is a historical reconciliation gap.
        $claimsWithoutMatchingClaimedEvent = (clone $claims)->whereNotExists(fn ($query) => $query
            ->selectRaw('1')->from('promotion_events as e')
            ->whereColumn('e.promotion_claim_id', 'c.id')
            ->whereColumn('e.promotion_id', 'c.promotion_id')
            ->where('e.event_type', 'claimed')
            ->whereRaw('JSON_CONTAINS(e.snapshot, c.snapshot) AND JSON_CONTAINS(c.snapshot, e.snapshot)'))
            ->count();
        // Release is a separate, retained event: status alone does not prove the reason/history.
        // Compare JSON values only; MySQL JSON does not preserve original serialized bytes.
        $releasedWithoutEvent = (clone $claims)->where('c.status', 'released')
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('promotion_events as e')
                ->whereColumn('e.promotion_claim_id', 'c.id')
                ->whereColumn('e.promotion_id', 'c.promotion_id')
                ->where('e.event_type', 'released')
                ->whereRaw("JSON_CONTAINS(JSON_EXTRACT(e.snapshot, '$.claim'), c.snapshot)
                    AND JSON_CONTAINS(c.snapshot, JSON_EXTRACT(e.snapshot, '$.claim'))
                    AND LEFT(JSON_UNQUOTE(JSON_EXTRACT(e.snapshot, '$.reason')), 255) = c.release_reason"))
            ->count();
        $activeWithReleaseEvent = (clone $claims)->where('c.status', 'active')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('promotion_events as e')
                ->whereColumn('e.promotion_claim_id', 'c.id')
                ->where('e.event_type', 'released'))->count();
        // JSON field reconciliation is deliberately separate from original byte-hash provenance.
        // A missing v1 contract/amount is a review anomaly, never silently treated as zero.
        $snapshotMismatch = (clone $claims)->whereRaw("(JSON_UNQUOTE(JSON_EXTRACT(c.snapshot, '$.contract')) IS NULL
            OR JSON_UNQUOTE(JSON_EXTRACT(c.snapshot, '$.contract')) <> 'promotion-claim.v1'
            OR JSON_UNQUOTE(JSON_EXTRACT(c.snapshot, '$.discount_amount')) IS NULL
            OR JSON_UNQUOTE(JSON_EXTRACT(c.snapshot, '$.discount_amount')) <> c.discount_amount)")->count();

        return ['campaign_count' => $campaignCount, 'claim_count' => $claimCount,
            'active_claim_count' => (clone $claims)->where('c.status', 'active')->count(),
            'released_claim_count' => (clone $claims)->where('c.status', 'released')->count(),
            'unbound_claim_count' => (clone $claims)->whereNull('c.invoice_id')->whereNull('c.order_id')->count(),
            'claims_truncated' => $claimCount > 50, 'claims' => $lines,
            'missing_financial_references' => $financialReferenceMissing,
            'mismatched_financial_references' => $financialReferenceMismatch,
            'unmatched_financial_adjustments' => $unmatchedFinancialAdjustments,
            'shared_order_claims_held_for_review' => $sharedOrderClaimsHeld,
            'claims_without_matching_claimed_event' => $claimsWithoutMatchingClaimedEvent,
            'released_claims_without_matching_release_event' => $releasedWithoutEvent,
            'active_claims_with_release_event' => $activeWithReleaseEvent,
            'financial_snapshot_digest_mismatches' => $financialSnapshotMismatches,
            'snapshot_contract_or_amount_mismatches' => $snapshotMismatch,
            'requires_review' => $campaignCount > 0 || $claimCount > 0 || $sharedOrderClaimsHeld > 0
                || $financialReferenceMissing > 0 || $financialReferenceMismatch > 0
                || $unmatchedFinancialAdjustments > 0
                || $financialSnapshotMismatches > 0 || $snapshotMismatch > 0
                || $claimsWithoutMatchingClaimedEvent > 0 || $releasedWithoutEvent > 0
                || $activeWithReleaseEvent > 0,
            'archive_eligibility' => 'not_approved_for_business_history'];
    }

    /** Conservative read-only obligation indicators, NOT an archival clearance or settlement decision. */
    private function archivedObligations(Outlet $outlet, array $promotionHistory): array
    {
        $id = $outlet->id;
        // A net-zero outlet refund sum can conceal one under-refunded and another over-refunded return.
        $posReturns = DB::table('returns as r')->join('invoices as i', 'i.id', '=', 'r.invoice_id')
            ->where('i.outlet_id', $id)->whereNull('r.order_id');
        $returnLineDue = '(SELECT COALESCE(SUM(l.net_amount), 0) FROM return_lines l WHERE l.return_id = r.id)';
        $returnRefunded = '(SELECT COALESCE(SUM(f.amount), 0) FROM pos_refund_allocations f WHERE f.return_id = r.id AND f.outlet_id = i.outlet_id)';
        $returnDue = (string) DB::table('return_lines as l')
            ->join('returns as r', 'r.id', '=', 'l.return_id')
            ->join('invoices as i', 'i.id', '=', 'r.invoice_id')
            ->where('i.outlet_id', $id)->whereNull('r.order_id')->sum('l.net_amount');
        $recordedRefunds = (string) DB::table('pos_refund_allocations')->where('outlet_id', $id)->sum('amount');
        $remaining = bcsub($returnDue, $recordedRefunds, 2);
        $counts = [
            'on_hand_quantity' => (int) DB::table('products')->where('outlet_id', $id)
                ->selectRaw('COALESCE(SUM(GREATEST(qty, 0)), 0) as total')->value('total'),
            'active_custody_hold_quantity' => (int) DB::table('inventory_custody_holds as h')
                ->join('products as src', 'src.id', '=', 'h.product_id')
                ->join('products as dst', 'dst.id', '=', 'h.destination_product_id')
                ->whereNull('h.released_at')->where(fn ($q) => $q->where('src.outlet_id', $id)
                ->orWhere('dst.outlet_id', $id))->sum('h.quantity'),
            'unapproved_stocktakes' => DB::table('stocktake_sessions')->where('outlet_id', $id)
                ->where('status', '!=', 'approved')->count(),
            'unresolved_transfer_quantity' => (int) DB::table('stock_transfer_lines as l')
                ->join('stock_transfers as t', 't.id', '=', 'l.stock_transfer_id')
                ->where(fn ($q) => $q->where('t.source_outlet_id', $id)
                    ->orWhere('t.destination_outlet_id', $id))
                ->selectRaw('COALESCE(SUM(GREATEST(l.quantity - l.received_quantity - l.rejected_quantity, 0)), 0) as total')
                ->value('total'),
            'open_cash_sessions' => DB::table('cash_sessions')->where('outlet_id', $id)
                ->where('status', 'open')->count(),
            'pending_cash_entries' => DB::table('cash_entries')->where('outlet_id', $id)
                ->where('status', 'pending')->count(),
            'unsettled_pos_tenders' => DB::table('pos_tender_allocations')->where('outlet_id', $id)
                ->whereIn('reconciliation_state', ['pending', 'variance'])->count(),
            'pos_returns_unmatched_count' => (clone $posReturns)->whereRaw($returnLineDue.' <> '.$returnRefunded)->count(),
            'pos_returns_over_refunded_count' => (clone $posReturns)->whereRaw($returnRefunded.' > '.$returnLineDue)->count(),
            'pos_return_count' => DB::table('returns as r')->join('invoices as i', 'i.id', '=', 'r.invoice_id')
                ->where('i.outlet_id', $id)->whereNull('r.order_id')->count(),
            'website_return_count' => DB::table('returns as r')->join('invoices as i', 'i.id', '=', 'r.invoice_id')
                ->where('i.outlet_id', $id)->whereNotNull('r.order_id')->count(),
            'website_order_line_count' => DB::table('order_items')->where('outlet_id', $id)->count(),
            'website_orders_for_review' => DB::table('orders as o')
                ->join('order_items as oi', 'oi.order_id', '=', 'o.id')->where('oi.outlet_id', $id)
                ->whereNotIn('o.fulfillment_status', ['delivered', 'completed', 'cancelled'])
                ->distinct()->count('o.id'),
            'active_website_reservations' => DB::table('reservations')->where('outlet_id', $id)
                ->whereIn('state', ['active', 'held_cod'])->count(),
            'trade_in_count' => DB::table('trade_ins')->where('outlet_id', $id)->count(),
            'pending_trade_ins' => DB::table('trade_ins')->where('outlet_id', $id)
                ->whereIn('status', ['pending', 'approved'])->count(),
            'open_warranty_claims' => DB::table('claims')->where('outlet_id', $id)
                ->where('status', '!=', 'closed')->count(),
            'open_purchase_orders' => DB::table('purchase_orders')->where('outlet_id', $id)
                ->whereIn('status', ['ordered', 'partially_received'])->count(),
            'unreceived_purchase_order_quantity' => (int) DB::table('purchase_order_lines as l')
                ->join('purchase_orders as p', 'p.id', '=', 'l.purchase_order_id')
                ->where('p.outlet_id', $id)->whereIn('p.status', ['ordered', 'partially_received'])
                ->selectRaw('COALESCE(SUM(GREATEST(l.ordered_quantity - l.received_quantity, 0)), 0) as total')
                ->value('total'),
            'active_paid_repairs' => DB::table('repair_jobs')->where('outlet_id', $id)
                ->whereNotIn('status', ['closed', 'cancelled'])->count(),
            'active_unbilled_paid_repairs' => DB::table('repair_jobs')->where('outlet_id', $id)
                ->whereNotIn('status', ['closed', 'cancelled'])->whereNull('invoice_id')->count(),
            'promotion_campaign_count' => $promotionHistory['campaign_count'],
            'promotion_claim_count' => $promotionHistory['claim_count'],
        ];

        return [...$counts, 'pos_return_amount' => $returnDue,
            'pos_refunds_recorded' => $recordedRefunds, 'pos_refund_difference' => $remaining,
            // A zero difference is NOT proof that website/provider refunds or warranty duties are settled.
            'requires_manual_review' => collect($counts)->contains(fn ($value) => $value > 0)
                || bccomp($remaining, '0.00', 2) !== 0
                || DB::table('invoices')->where('outlet_id', $id)->exists()
                || DB::table('suppliers')->where('outlet_id', $id)->exists()
                || DB::table('repair_jobs')->where('outlet_id', $id)->exists(),
            'archive_eligibility' => 'not_approved_for_business_history'];
    }

    /** Summaries only: no notes, customer details, serialized-unit identities or snapshot payloads. */
    private function archivedTransfers(Outlet $outlet): array
    {
        return DB::table('stock_transfers as t')
            ->join('outlets as src', 'src.id', '=', 't.source_outlet_id')
            ->join('outlets as dst', 'dst.id', '=', 't.destination_outlet_id')
            ->where(fn ($query) => $query->where('t.source_outlet_id', $outlet->id)
                ->orWhere('t.destination_outlet_id', $outlet->id))
            ->orderByDesc('t.id')->limit(50)
            ->get(['t.id', 't.public_id', 't.transfer_number', 't.status',
                'src.public_id as source_id', 'dst.public_id as destination_id',
                't.dispatched_at', 't.completed_at'])
            ->map(function ($transfer) {
                // Aggregate ALL lines, but expose at most the first 50 read-only summaries.
                $totals = DB::table('stock_transfer_lines')->where('stock_transfer_id', $transfer->id)
                    ->selectRaw('COUNT(*) as line_count, COALESCE(SUM(GREATEST(quantity - received_quantity - rejected_quantity, 0)), 0) as unresolved')->first();
                $lines = DB::table('stock_transfer_lines as l')
                    ->join('products as src', 'src.id', '=', 'l.source_product_id')
                    ->join('products as dst', 'dst.id', '=', 'l.destination_product_id')
                    ->where('l.stock_transfer_id', $transfer->id)->orderBy('l.id')->limit(50)
                    ->get(['l.public_id', 'src.public_id as source_product_id',
                        'dst.public_id as destination_product_id', 'l.quantity',
                        'l.received_quantity', 'l.rejected_quantity', 'l.snapshot_sha256']);
                $receipts = DB::table('stock_transfer_receipts')->where('stock_transfer_id', $transfer->id)
                    ->orderBy('id')->limit(50)
                    ->get(['id', 'public_id', 'processed_at', 'snapshot_sha256'])
                    ->map(function ($receipt) {
                        $receiptLines = DB::table('stock_transfer_receipt_lines')
                            ->where('stock_transfer_receipt_id', $receipt->id);

                        return ['id' => $receipt->public_id, 'processed_at' => $receipt->processed_at,
                            'snapshot_sha256' => $receipt->snapshot_sha256,
                            'received_quantity' => (int) (clone $receiptLines)->sum('received_quantity'),
                            'rejected_quantity' => (int) $receiptLines->sum('rejected_quantity')];
                    })->all();
                $unresolved = (int) $totals->unresolved;

                return ['id' => $transfer->public_id, 'number' => $transfer->transfer_number,
                    'status' => $transfer->status, 'source_id' => $transfer->source_id,
                    'destination_id' => $transfer->destination_id,
                    'dispatched_at' => $transfer->dispatched_at, 'completed_at' => $transfer->completed_at,
                    'line_count' => (int) $totals->line_count, 'receipt_count' => DB::table('stock_transfer_receipts')
                        ->where('stock_transfer_id', $transfer->id)->count(),
                    'unresolved_quantity' => (int) $unresolved,
                    'requires_review' => $unresolved > 0 || ! in_array($transfer->status, ['received', 'rejected'], true),
                    'lines' => $lines->map(fn ($line) => [
                        'id' => $line->public_id, 'source_product_id' => $line->source_product_id,
                        'destination_product_id' => $line->destination_product_id,
                        'quantity' => (int) $line->quantity,
                        'received_quantity' => (int) $line->received_quantity,
                        'rejected_quantity' => (int) $line->rejected_quantity,
                        'snapshot_sha256' => $line->snapshot_sha256])->all(),
                    'receipts' => $receipts];
            })->all();
    }

    public function create(Admin $actor, array $input): array
    {
        abort_unless($this->canManage($actor), 403);
        if (array_diff(array_keys($input), ['name', 'business_address'])) {
            throw ValidationException::withMessages(['input' => 'Unexpected outlet fields.']);
        }
        $data = validator($input, ['name' => ['required', 'string', 'max:160'],
            'business_address' => ['required', 'string', 'max:1000']])->validate();
        $name = trim($data['name']);
        $address = trim($data['business_address']);
        if ($name === '' || $address === '') {
            throw ValidationException::withMessages(['name' => 'Outlet name and address are required.']);
        }

        return DB::transaction(function () use ($actor, $name, $address) {
            // One locked root serializes new code allocation; never reuse an archived code.
            DB::table('business_profiles')->where('id', 1)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($actor), 403);
            $used = Outlet::query()->pluck('outlet_code')->all();
            $code = null;
            for ($i = 1; $i <= 999; $i++) {
                $candidate = sprintf('%03d', $i);
                if (! in_array($candidate, $used, true)) {
                    $code = $candidate;
                    break;
                }
            }
            abort_if($code === null, 409, 'No outlet codes remain.');
            $outlet = new Outlet;
            $outlet->forceFill(['public_id' => (string) Str::uuid(), 'outlet_code' => $code,
                'name' => $name, 'business_address' => $address, 'status' => false,
                'archived_at' => null, 'legacy_password' => null, 'legacy_remember_token' => null,
                'version' => 1])->save();
            $actor->shops()->attach($outlet->id);
            IdentityAudit::record('admin', $actor->id, 'outlet_created', 'outlet:'.$outlet->public_id, $outlet->id);

            return $this->view($outlet);
        });
    }

    public function archive(Admin $actor, string $publicId, int $version): array
    {
        abort_unless($this->canManage($actor), 403);

        return DB::transaction(function () use ($actor, $publicId, $version) {
            DB::table('business_profiles')->where('id', 1)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($actor), 403);
            $outlet = Outlet::where('public_id', $publicId)->lockForUpdate()->firstOrFail();
            abort_if($outlet->archived_at !== null || $outlet->status, 409, 'Outlet is not active.');
            abort_if((int) $outlet->version !== $version, 409, 'Outlet changed; reload before archiving.');
            abort_if(Outlet::where('status', false)->whereNull('archived_at')->count() <= 1,
                409, 'Cannot archive the last open outlet.');
            // Fail closed by default. Only completed cash history and immutable audit are exempt;
            // any other present or future linked table, including products, claims and orders,
            // retains the prior archival block until its obligations have a separate proof.
            $linked = DB::select("SELECT TABLE_NAME AS name FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'outlet_id'");
            $retainedHistory = ['outlet_admins', 'identity_audit_events', 'team_member_audit_events',
                'pos_audit_logs', 'cash_sessions', 'cash_entries'];
            foreach ($linked as $row) {
                if (in_array($row->name, $retainedHistory, true)) {
                    continue;
                }
                abort_if(DB::table($row->name)->where('outlet_id', $outlet->id)->exists(),
                    409, 'Outlet has linked business history or outstanding obligations; archival needs a reviewed transition.');
            }
            abort_if(DB::table('cash_sessions')->where('outlet_id', $outlet->id)
                ->where('status', '!=', 'closed')->exists(), 409, 'Close every cash session before archiving.');
            abort_if(DB::table('cash_entries')->where('outlet_id', $outlet->id)
                ->where('status', 'pending')->exists(), 409, 'Resolve pending cash entries before archiving.');
            // Transfers may reference an outlet through nonstandard source/destination columns.
            foreach (['stock_transfers' => ['source_outlet_id', 'destination_outlet_id'],
                'stock_transfer_lines' => ['source_outlet_id', 'destination_outlet_id'],
                'stock_transfer_receipts' => ['destination_outlet_id']] as $table => $columns) {
                foreach ($columns as $column) {
                    abort_if(DB::table($table)->where($column, $outlet->id)->exists(), 409,
                        'Outlet has transfer history or outstanding transfer obligations; archival needs reviewed transition.');
                }
            }
            $outlet->forceFill(['archived_at' => now(), 'version' => $outlet->version + 1])->save();
            IdentityAudit::record('admin', $actor->id, 'outlet_archived', 'outlet:'.$outlet->public_id, $outlet->id);

            return $this->view($outlet);
        });
    }

    private function view(Outlet $outlet): array
    {
        return ['id' => $outlet->public_id, 'outlet_code' => $outlet->outlet_code,
            'name' => $outlet->name, 'business_address' => $outlet->business_address,
            'status' => $outlet->archived_at !== null ? 'archived' : ($outlet->status ? 'disabled' : 'open'),
            'version' => (int) $outlet->version];
    }
}
