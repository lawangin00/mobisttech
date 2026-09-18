<?php

namespace App\Http\Controllers;

use App\Cash\CashSessionOperations;
use App\Identity\Access;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Payments\PosPaymentOperations;
use App\Repairs\PaidRepairOperations;
use App\TradeIn\TradeInOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosOperationsController extends Controller
{
    public function index(Request $request, CashSessionOperations $cash, TradeInOperations $tradeIns)
    {
        [$actor, $outlet] = $this->context($request);
        $access = app(Access::class);
        $canCash = $access->allows($actor, 'shop.cash', $outlet);
        $canCashApprove = $access->allows($actor, 'shop.cash.approve', $outlet);
        $canTradeIn = $access->allows($actor, 'shop.trade-in', $outlet);
        $canRepairs = $access->allows($actor, 'shop.repairs', $outlet);
        $canReconcile = $access->allows($actor, 'shop.payments.reconcile', $outlet);
        abort_unless($canCash || $canCashApprove || $canTradeIn || $canRepairs || $canReconcile, 403);

        $cashSession = null;
        $cashHistory = [];
        if ($canCash || $canCashApprove) {
            $open = DB::table('cash_sessions')->where('outlet_id', $outlet->id)->where('status', 'open')->first();
            if ($open && $canCash) {
                $cashSession = $cash->session($actor, $outlet, $open->public_id);
            } elseif ($open && $canCashApprove) {
                $cashSession = [
                    'session_id' => $open->public_id,
                    'business_date' => (string) $open->business_date,
                    'status' => $open->status,
                    'version' => (int) $open->version,
                    'opening_cash' => (string) $open->opening_cash,
                    'summary' => null,
                    'entries' => DB::table('cash_entries')->where('cash_session_id', $open->id)->orderBy('id')->get()
                        ->map(fn ($entry) => [
                            'entry_id' => $entry->public_id,
                            'type' => $entry->type,
                            'amount' => (string) $entry->amount,
                            'reason' => $entry->reason,
                            'reference' => $entry->reference,
                            'status' => $entry->status,
                            'review_notes' => $entry->review_notes,
                        ])->all(),
                ];
            }
            $cashHistory = DB::table('cash_sessions')->where('outlet_id', $outlet->id)->orderByDesc('id')->limit(20)
                ->get(['public_id', 'business_date', 'status', 'opening_cash', 'expected_cash', 'actual_cash',
                    'variance_amount', 'opened_at', 'closed_at'])
                ->map(fn ($row) => [
                    'session_id' => $row->public_id, 'business_date' => (string) $row->business_date,
                    'status' => $row->status, 'opening_cash' => (string) $row->opening_cash,
                    'expected_cash' => $row->expected_cash === null ? null : (string) $row->expected_cash,
                    'actual_cash' => $row->actual_cash === null ? null : (string) $row->actual_cash,
                    'variance_amount' => $row->variance_amount === null ? null : (string) $row->variance_amount,
                    'opened_at' => $row->opened_at, 'closed_at' => $row->closed_at,
                ])->all();
        }

        $settlements = $canReconcile ? DB::table('pos_tender_allocations')->where('outlet_id', $outlet->id)
            ->where('method', '<>', 'cash')->orderByDesc('id')->limit(100)->get()->map(function ($row) {
                $snapshot = json_decode($row->destination_snapshot, true) ?: [];
                $event = DB::table('pos_settlement_events')->where('tender_allocation_id', $row->id)
                    ->orderByDesc('sequence')->first();

                return [
                    'allocation_id' => $row->public_id,
                    'method' => $row->method,
                    'amount' => (string) $row->amount,
                    'destination_id' => $snapshot['destination_id'] ?? null,
                    'destination_name' => $snapshot['display_name'] ?? 'Payment destination',
                    'reconciliation_state' => $row->reconciliation_state,
                    'settlement_version' => (int) $row->settlement_version,
                    'latest' => $event ? [
                        'fee_amount' => (string) $event->fee_amount,
                        'adjustment_amount' => (string) $event->adjustment_amount,
                        'expected_net_amount' => (string) $event->expected_net_amount,
                        'received_net_amount' => (string) $event->received_net_amount,
                        'variance_amount' => (string) $event->variance_amount,
                        'recorded_at' => $event->recorded_at,
                    ] : null,
                ];
            })->all() : [];

        $serializedProducts = $canTradeIn ? Product::where('outlet_id', $outlet->id)->where('isDeleted', false)
            ->where('track_imei', true)->orderBy('name')->get()
            ->map(fn (Product $product) => [
                'id' => $product->public_id, 'name' => $product->name, 'code' => $product->product_code,
                'required_imei_slots' => $product->requiredImeiSlots(),
            ])->all() : [];

        $tradeRows = [];
        $invoiceCandidates = [];
        if ($canTradeIn) {
            $tradeRows = DB::table('trade_ins')->where('outlet_id', $outlet->id)->orderByDesc('id')->limit(40)
                ->pluck('public_id')->map(fn ($id) => $tradeIns->get($actor, $outlet, $id))->all();
            $invoiceCandidates = DB::table('invoices as i')->where('i.outlet_id', $outlet->id)->whereNull('i.order_id')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('pos_tender_allocations as p')->whereColumn('p.invoice_id', 'i.id'))
                ->orderByDesc('i.id')->limit(40)->get(['i.public_id', 'i.invoice_number', 'i.final_bill', 'i.created_at'])
                ->map(fn ($row) => [
                    'id' => $row->public_id, 'number' => $row->invoice_number,
                    'final_bill' => (string) $row->final_bill, 'created_at' => $row->created_at,
                ])->all();
        }

        $repairSetting = null;
        $repairs = [];
        $repairParts = [];
        $destinations = [];
        if ($canRepairs) {
            $setting = DB::table('repair_settings')->where('outlet_id', $outlet->id)->first();
            $repairSetting = $setting
                ? ['enabled' => (bool) $setting->enabled, 'version' => (int) $setting->version]
                : ['enabled' => false, 'version' => null];
            $repairs = DB::table('repair_jobs')->where('outlet_id', $outlet->id)->orderByDesc('id')->limit(50)
                ->get(['public_id', 'repair_number', 'customer_name', 'customer_phone', 'device_label',
                    'identifier_type', 'identifier_value', 'status', 'version', 'received_at', 'closed_at'])
                ->map(fn ($row) => [
                    'id' => $row->public_id, 'number' => $row->repair_number, 'customer_name' => $row->customer_name,
                    'customer_phone' => $this->maskPhone($row->customer_phone), 'device_label' => $row->device_label,
                    'identifier_type' => $row->identifier_type, 'identifier_value' => $this->maskIdentifier($row->identifier_value),
                    'status' => $row->status, 'version' => (int) $row->version,
                    'received_at' => $row->received_at, 'closed_at' => $row->closed_at,
                ])->all();
            $repairParts = Product::where('outlet_id', $outlet->id)->where('isDeleted', false)
                ->where('track_imei', false)->orderBy('name')->get(['public_id', 'name', 'product_code', 'qty', 'sale_price'])
                ->map(fn ($row) => [
                    'id' => $row->public_id, 'name' => $row->name, 'code' => $row->product_code,
                    'qty' => (int) $row->qty, 'sale_price' => (string) $row->sale_price,
                ])->all();
            $destinations = $this->destinations($outlet);
        }

        return response()->json(['data' => [
            'outlet' => ['id' => $outlet->public_id, 'name' => $outlet->name],
            'permissions' => compact('canCash', 'canCashApprove', 'canTradeIn', 'canRepairs', 'canReconcile'),
            'cash_session' => $cashSession, 'cash_history' => $cashHistory, 'settlements' => $settlements,
            'serialized_products' => $serializedProducts, 'trade_ins' => $tradeRows, 'invoice_candidates' => $invoiceCandidates,
            'repair_setting' => $repairSetting, 'repairs' => $repairs, 'repair_parts' => $repairParts,
            'payment_destinations' => $destinations,
        ]]);
    }

    public function cashOpen(Request $request, CashSessionOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->open($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function cashSession(Request $request, string $session, CashSessionOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->session($actor, $outlet, $session)]);
    }

    public function cashEntry(Request $request, string $session, CashSessionOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->recordEntry($actor, $outlet, $session, $this->key($request), $request->all())]);
    }

    public function cashReview(Request $request, string $session, string $entry, CashSessionOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->reviewEntry($actor, $outlet, $session, $entry, $this->key($request), $request->all())]);
    }

    public function cashClose(Request $request, string $session, CashSessionOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->close($actor, $outlet, $session, $this->key($request), $request->all())]);
    }

    public function settle(Request $request, string $allocation, PosPaymentOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->reconcile($actor, $outlet, $allocation, $this->key($request), $request->all())]);
    }

    public function tradeCreate(Request $request, string $product, TradeInOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->create($actor, $outlet, $product, $this->key($request), $request->all())]);
    }

    public function tradeShow(Request $request, string $tradeIn, TradeInOperations $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $service->get($actor, $outlet, $tradeIn);
        $id = DB::table('trade_ins')->where('public_id', $tradeIn)->where('outlet_id', $outlet->id)->value('id');
        $events = $id ? DB::table('trade_in_events')->where('trade_in_id', $id)->orderBy('sequence')->get()
            ->map(fn ($row) => [
                'sequence' => (int) $row->sequence, 'event_type' => $row->event_type,
                'snapshot' => json_decode($row->snapshot, true) ?: [], 'created_at' => $row->created_at,
            ])->all() : [];

        return response()->json(['data' => $data + ['events' => $events]]);
    }

    public function tradeApprove(Request $request, string $tradeIn, TradeInOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->approve($actor, $outlet, $tradeIn, $this->key($request), $request->all())]);
    }

    public function tradeReceive(Request $request, string $tradeIn, TradeInOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->receive($actor, $outlet, $tradeIn, $this->key($request), $request->all())]);
    }

    public function tradeCancel(Request $request, string $tradeIn, TradeInOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->cancel($actor, $outlet, $tradeIn, $this->key($request), $request->all())]);
    }

    public function repairConfigure(Request $request, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->configure($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function repairOpen(Request $request, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->open($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function repairShow(Request $request, string $repair, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->view($actor, $outlet, $repair)]);
    }

    public function repairUpdate(Request $request, string $repair, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->update($actor, $outlet, $repair, $this->key($request), $request->all())]);
    }

    public function repairEstimate(Request $request, string $repair, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        $service->estimate($actor, $outlet, $repair, $this->key($request), $request->all());

        return response()->json(['data' => $service->view($actor, $outlet, $repair)]);
    }

    public function repairEstimateDecision(Request $request, string $repair, string $estimate, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);
        $approved = $request->boolean('approved');

        return response()->json(['data' => $service->decideEstimate($actor, $outlet, $repair, $estimate, $this->key($request), $approved)]);
    }

    public function repairConsumeParts(Request $request, string $repair, PaidRepairOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        $service->consumeParts($actor, $outlet, $repair, $this->key($request));

        return response()->json(['data' => $service->view($actor, $outlet, $repair)]);
    }

    public function repairCollect(Request $request, string $repair, PosPaymentOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->collectRepair($actor, $outlet, $repair, $this->key($request), $request->all())]);
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

    private function destinations(Outlet $outlet): array
    {
        return DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)->where('active', true)
            ->where(fn ($q) => $q->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($q) => $q->whereNull('effective_until')->orWhere('effective_until', '>', now()))
            ->orderBy('method')->orderBy('display_name')
            ->get(['public_id', 'method', 'display_name', 'provider_label', 'masked_identifier'])
            ->map(fn ($row) => (array) $row)->all();
    }

    private function maskPhone(?string $phone): ?string
    {
        if (! $phone) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return strlen($digits) < 6 ? '***' : substr($digits, 0, 4).'*****'.substr($digits, -2);
    }

    private function maskIdentifier(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        $value = trim($value);
        if (mb_strlen($value) <= 4) {
            return str_repeat('*', mb_strlen($value));
        }

        return str_repeat('*', max(4, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
