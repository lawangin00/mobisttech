<?php

namespace App\Http\Controllers;

use App\Bulk\BulkDataOperations;
use App\Identity\Access;
use App\Inventory\StocktakeOperations;
use App\Inventory\StockTransferOperations;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Procurement\SupplierProcurement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PosStockControlController extends Controller
{
    public function index(Request $request, SupplierProcurement $procurement)
    {
        [$actor, $outlet] = $this->context($request);
        $access = app(Access::class);
        $canProcure = $access->allows($actor, 'shop.procurement', $outlet);
        $canStocktake = $access->allows($actor, 'shop.stocktake', $outlet);
        $canApprove = $access->allows($actor, 'shop.stocktake.approve', $outlet);
        $canDispatch = $access->allows($actor, 'shop.transfers.dispatch', $outlet);
        $canReceive = $access->allows($actor, 'shop.transfers.receive', $outlet);
        $canBulk = $access->allows($actor, 'shop.inventory', $outlet);

        $products = Product::where('outlet_id', $outlet->id)->where('isDeleted', false)->orderBy('name')
            ->get(['id', 'public_id', 'name', 'product_code', 'qty', 'track_imei'])
            ->map(function (Product $p) use ($outlet) {
                $policy = DB::table('reorder_policies')->where('outlet_id', $outlet->id)->where('product_id', $p->id)->first();

                return ['id' => $p->public_id, 'name' => $p->name, 'code' => $p->product_code,
                    'qty' => (int) $p->qty, 'track_imei' => (bool) $p->track_imei,
                    'reorder_policy' => $policy ? ['version' => (int) $policy->version,
                        'reorder_threshold' => (int) $policy->reorder_threshold, 'target_stock' => (int) $policy->target_stock,
                        'active' => (bool) $policy->is_active] : null];
            })->all();

        $outlets = $actor->shops()->where('outlets.status', false)->whereNull('outlets.archived_at')->orderBy('outlets.name')
            ->get(['outlets.id', 'outlets.public_id', 'outlets.name']);
        $productsByOutlet = [];
        foreach ($outlets as $candidate) {
            $productsByOutlet[$candidate->public_id] = Product::where('outlet_id', $candidate->id)->where('isDeleted', false)
                ->orderBy('name')->get(['public_id', 'name', 'product_code', 'qty', 'track_imei'])
                ->map(fn ($p) => ['id' => $p->public_id, 'name' => $p->name, 'code' => $p->product_code,
                    'qty' => (int) $p->qty, 'track_imei' => (bool) $p->track_imei])->all();
        }

        $suppliers = $canProcure ? DB::table('suppliers')->where('outlet_id', $outlet->id)->whereNull('archived_at')->orderBy('name')
            ->get(['public_id', 'supplier_code', 'name', 'phone', 'email', 'is_active', 'version'])
            ->map(fn ($r) => (array) $r)->all() : [];

        $orders = $canProcure ? DB::table('purchase_orders')->where('outlet_id', $outlet->id)->orderByDesc('id')->limit(50)->get()
            ->map(function ($order) {
                $lines = DB::table('purchase_order_lines as l')->join('products as p', 'p.id', '=', 'l.product_id')
                    ->where('l.purchase_order_id', $order->id)->orderBy('l.id')
                    ->get(['l.public_id as line_id', 'p.public_id as product_id', 'p.name',
                        'l.ordered_quantity', 'l.received_quantity', 'l.ordered_unit_cost', 'l.planned_landed_unit_cost'])
                    ->map(fn ($r) => (array) $r)->all();

                return ['id' => $order->public_id, 'number' => $order->order_number, 'supplier_name' => $order->supplier_name_snapshot,
                    'status' => $order->status, 'expected_at' => $order->expected_at, 'lines' => $lines];
            })->all() : [];

        $stocktakes = ($canStocktake || $canApprove) ? DB::table('stocktake_sessions')->where('outlet_id', $outlet->id)
            ->orderByDesc('id')->limit(30)->get(['public_id', 'session_number', 'kind', 'status', 'version'])
            ->map(fn ($r) => (array) $r)->all() : [];

        $transferRows = [];
        if ($canDispatch || $canReceive) {
            $transferRows = DB::table('stock_transfers as t')
                ->join('outlets as s', 's.id', '=', 't.source_outlet_id')
                ->join('outlets as d', 'd.id', '=', 't.destination_outlet_id')
                ->where(fn ($q) => $q->where('t.source_outlet_id', $outlet->id)->orWhere('t.destination_outlet_id', $outlet->id))
                ->orderByDesc('t.id')->limit(50)
                ->get(['t.public_id', 't.transfer_number', 't.status', 't.version', 's.public_id as source_outlet_id',
                    's.name as source_outlet_name', 'd.public_id as destination_outlet_id', 'd.name as destination_outlet_name'])
                ->map(fn ($r) => (array) $r)->all();
        }

        return response()->json(['data' => [
            'outlet' => ['id' => $outlet->public_id, 'name' => $outlet->name],
            'permissions' => compact('canProcure', 'canStocktake', 'canApprove', 'canDispatch', 'canReceive', 'canBulk'),
            'products' => $products, 'outlets' => $outlets->map(fn ($o) => ['id' => $o->public_id, 'name' => $o->name])->all(),
            'products_by_outlet' => $productsByOutlet, 'suppliers' => $suppliers, 'orders' => $orders,
            'stocktakes' => $stocktakes, 'transfers' => $transferRows,
            'recommendations' => $canProcure ? $procurement->recommendations($actor, $outlet, 100) : [],
        ]]);
    }

    public function supplier(Request $request, SupplierProcurement $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->createSupplier($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function order(Request $request, SupplierProcurement $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->createOrder($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function receiveOrder(Request $request, string $order, SupplierProcurement $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->receive($actor, $outlet, $order, $this->key($request), $request->all())]);
    }

    public function reorder(Request $request, string $product, SupplierProcurement $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->setReorderPolicy($actor, $outlet, $product, $this->key($request), $request->all())]);
    }

    public function stocktakeStart(Request $request, StocktakeOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->start($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function stocktake(Request $request, string $stocktake, StocktakeOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->session($actor, $outlet, $stocktake)]);
    }

    public function stocktakeCount(Request $request, string $stocktake, string $line, StocktakeOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->countLine($actor, $outlet, $stocktake, $line, $this->key($request), $request->all())]);
    }

    public function stocktakeRecount(Request $request, string $stocktake, StocktakeOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->requestRecount($actor, $outlet, $stocktake, $this->key($request), $request->all())]);
    }

    public function stocktakeApprove(Request $request, string $stocktake, StocktakeOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->approve($actor, $outlet, $stocktake, $this->key($request), $request->all())]);
    }

    public function transferCreate(Request $request, StockTransferOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->create($actor, $outlet, $this->key($request), $request->all())]);
    }

    public function transfer(Request $request, string $transfer, StockTransferOperations $service)
    {
        [$actor] = $this->context($request);

        return response()->json(['data' => $service->details($actor, $transfer)]);
    }

    public function transferDispatch(Request $request, string $transfer, StockTransferOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->dispatch($actor, $outlet, $transfer, $this->key($request), $request->all())]);
    }

    public function transferReceive(Request $request, string $transfer, StockTransferOperations $service)
    {
        [$actor, $outlet] = $this->context($request);

        return response()->json(['data' => $service->receive($actor, $outlet, $transfer, $this->key($request), $request->all())]);
    }

    public function bulkPreview(Request $request, BulkDataOperations $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $request->validate(['dataset' => 'required|in:catalogue,price,inventory', 'format' => 'required|in:csv,xlsx',
            'document' => 'required', 'boundary' => 'nullable|string']);

        return response()->json(['data' => $service->preview($actor, $outlet, $data['dataset'], $data['format'], $data['document'], $data['boundary'] ?? 'operational')]);
    }

    public function bulkImport(Request $request, BulkDataOperations $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $request->validate(['dataset' => 'required|in:catalogue,price,inventory', 'format' => 'required|in:csv,xlsx',
            'document' => 'required', 'recovery' => 'required|in:whole_batch,row', 'boundary' => 'nullable|string']);

        return response()->json(['data' => $service->import($actor, $outlet, $data['dataset'], $data['format'], $data['document'],
            $this->key($request), $data['recovery'], $data['boundary'] ?? 'operational')]);
    }

    public function bulkExport(Request $request, BulkDataOperations $service)
    {
        [$actor, $outlet] = $this->context($request);
        $data = $request->validate(['dataset' => 'required|in:catalogue,price,inventory', 'format' => 'required|in:csv,xlsx']);
        $export = $service->export($actor, $outlet, $data['dataset'], $data['format']);

        return response()->json(['data' => ['dataset' => $data['dataset'], 'format' => $data['format'], 'document' => $export]]);
    }

    private function context(Request $request): array
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $outletId = (int) $request->session()->get('active_outlet_id', 0);
        $outlet = $actor->shops()->whereKey($outletId)->where('outlets.status', false)->whereNull('outlets.archived_at')->first();
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
