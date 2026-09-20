<?php

namespace App\Http\Controllers;

use App\Catalog\ProductDefinitions;
use App\Catalog\ProductWebsitePublication;
use App\Identity\Access;
use App\Inventory\InventoryOperations;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\StockUnit;
use App\Payments\PosPaymentOperations;
use App\Pos\PosInventoryListing;
use App\Reporting\RetailLabels;
use App\Sales\SalesOperations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class PosTransactionController extends Controller
{
    private const MASTER_LISTS = [
        'product_category', 'product_subcategory', 'product_brand', 'device_ram_gb',
        'device_storage_gb', 'device_sim_configuration', 'unit_color', 'unit_condition',
        'unit_pta_status', 'unit_carrier_lock_status', 'unit_mdm_status', 'acquisition_source_type',
    ];

    public function catalogue(Request $request)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory', 'shop.sales']);
        $inventory = $request->query('mode') === 'inventory';
        if ($inventory) {
            abort_unless(app(Access::class)->allows($actor, 'shop.inventory', $outlet), 403);
        }
        $q = trim((string) $request->query('q', ''));
        $page = max(1, min(5000, (int) $request->query('page', 1)));
        $paging = null;
        $query = Product::query()->where('outlet_id', $outlet->id)->where('isDeleted', false);
        if ($inventory) {
            $result = app(PosInventoryListing::class)->page($request, $query);
            $rows = collect($result['rows']);
            $paging = $result['paging'];
            $page = $paging['page'];
            $hasMore = $page < $paging['pages'];
        } else {
            if ($q !== '') {
                $matchedProductId = $this->lookupProductId($outlet, $q);
                $query->where(function ($builder) use ($q, $matchedProductId) {
                    $builder->where('name', 'like', '%'.$q.'%')->orWhere('product_code', $q)
                        ->orWhere('public_id', $q);
                    if ($matchedProductId) {
                        $builder->orWhereKey($matchedProductId);
                    }
                });
            }
            $rows = $query->orderBy('name')->skip(($page - 1) * 20)->limit(21)->get();
            $hasMore = $rows->count() > 20;
            $rows = $rows->take(20)->values();
        }
        $products = $rows->map(fn (Product $product) => $this->productPayload($product, $inventory))->all();
        $destinations = [];
        if (app(Access::class)->allows($actor, 'shop.sales', $outlet)) {
            $destinations = DB::table('pos_payment_destinations')->where('outlet_id', $outlet->id)
                ->where('active', true)
                ->where(fn ($b) => $b->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($b) => $b->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                ->orderBy('method')->orderBy('display_name')
                ->get(['public_id', 'method', 'display_name', 'provider_label', 'masked_identifier'])
                ->map(fn ($row) => (array) $row)->all();
        }
        $master = [];
        if (app(Access::class)->allows($actor, 'shop.inventory', $outlet)) {
            $master = DB::table('pos_master_data_options')->whereIn('list_key', self::MASTER_LISTS)
                ->where('is_active', true)->whereNull('archived_at')->orderBy('list_key')->orderBy('sort_order')
                ->get(['id', 'list_key', 'code', 'label', 'metadata'])
                ->map(fn ($row) => ['id' => (int) $row->id, 'list_key' => $row->list_key, 'code' => $row->code,
                    'label' => $row->label, 'metadata' => json_decode($row->metadata ?? '{}', true) ?: []])->all();
        }

        return response()->json(['data' => [
            'outlet' => ['id' => $outlet->public_id, 'name' => $outlet->name],
            'products' => $products, 'page' => $page, 'has_more' => $hasMore, 'pagination' => $paging,
            'payment_destinations' => $destinations, 'master_data' => $master,
            'can_send_documents' => app(Access::class)->allows($actor, 'shop.documents.send', $outlet),
        ]]);
    }

    public function lookup(Request $request)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory', 'shop.sales']);
        $q = trim((string) $request->query('q', ''));
        if ($q === '') {
            throw ValidationException::withMessages(['q' => 'Scan or enter a product code, unit code, QR value or IMEI.']);
        }
        $productId = $this->lookupProductId($outlet, $q);
        abort_unless($productId, 404);
        $product = Product::whereKey($productId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->firstOrFail();
        $unit = $this->lookupUnit($product, $q);

        return response()->json(['data' => ['product' => $this->productPayload($product),
            'unit' => $unit ? $this->unitPayload($unit) : null]]);
    }

    public function saveProduct(Request $request, ProductDefinitions $products)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);
        $input = $request->all();
        $publicId = array_key_exists('product_id', $input) ? (string) $input['product_id'] : null;
        unset($input['product_id']);
        $version = null;
        if ($publicId !== null) {
            $version = Validator::make($input, ['expected_version' => 'required|integer|min:1'])->validate()['expected_version'];
        }
        unset($input['expected_version']);
        $product = $products->save($actor, $outlet, $input, $publicId, $version);

        return response()->json(['data' => $this->productPayload($product)]);
    }

    public function websiteListing(Request $request, string $product, ProductWebsitePublication $publication)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);

        return response()->json(['data' => $publication->save($actor, $outlet, $product, $request->all())]);
    }

    public function acquire(Request $request, string $product, InventoryOperations $inventory)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);
        $result = $inventory->acquire($actor, $outlet, $product, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function imeis(Request $request, string $product, InventoryOperations $inventory)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);
        $result = $inventory->imeis($actor, $outlet, $product, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function adjust(Request $request, string $product, InventoryOperations $inventory)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);
        $result = $inventory->adjust($actor, $outlet, $product, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function unitAttributes(Request $request, string $unit, ProductDefinitions $products)
    {
        [$actor, $outlet] = $this->context($request, ['shop.inventory']);
        $row = $products->unitAttributes($actor, $outlet, $unit, $request->all());

        return response()->json(['data' => $this->unitPayload($row)]);
    }

    public function quote(Request $request)
    {
        [$actor, $outlet] = $this->context($request, ['shop.sales']);
        $input = Validator::make($request->all(), [
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity' => 'required|integer|min:1|max:10000', 'discount' => 'nullable',
            'payments' => 'sometimes|array|max:10', 'payments.*.destination_id' => 'required_with:payments|uuid',
            'payments.*.method' => 'required_with:payments|string', 'payments.*.amount' => 'required_with:payments',
            'payments.*.cash_tendered' => 'nullable',
        ])->validate();
        $gross = '0.00';
        foreach ($input['lines'] as $line) {
            $product = Product::where('public_id', $line['product_id'])->where('outlet_id', $outlet->id)
                ->where('isDeleted', false)->firstOrFail();
            $gross = bcadd($gross, bcmul(SourceRow::money((string) $product->sale_price), (string) $line['quantity'], 2), 2);
        }
        $discount = SourceRow::money((string) ($input['discount'] ?? '0.00'));
        if (bccomp($discount, $gross, 2) > 0) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot exceed the authoritative gross total.']);
        }
        $payable = bcsub($gross, $discount, 2);
        $paid = '0.00';
        $change = '0.00';
        foreach ($input['payments'] ?? [] as $index => $payment) {
            $destination = DB::table('pos_payment_destinations')->where('public_id', $payment['destination_id'])
                ->where('outlet_id', $outlet->id)->where('active', true)
                ->where(fn ($b) => $b->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
                ->where(fn ($b) => $b->whereNull('effective_until')->orWhere('effective_until', '>', now()))
                ->firstOrFail();
            if ($destination->method !== $payment['method']) {
                throw ValidationException::withMessages(['payments.'.$index.'.method' => 'Payment Method does not match the selected destination.']);
            }
            $amount = SourceRow::money((string) $payment['amount']);
            $paid = bcadd($paid, $amount, 2);
            if ($payment['method'] === 'cash') {
                $cash = SourceRow::money((string) ($payment['cash_tendered'] ?? $amount));
                if (bccomp($cash, $amount, 2) < 0) {
                    throw ValidationException::withMessages(['payments.'.$index.'.cash_tendered' => 'Cash tendered is below the Cash allocation.']);
                }
                $change = bcadd($change, bcsub($cash, $amount, 2), 2);
            }
        }

        return response()->json(['data' => ['gross' => $gross, 'discount' => $discount, 'payable' => $payable,
            'payments_total' => $paid, 'remaining' => bcsub($payable, $paid, 2), 'cash_change' => $change,
            'promotion_or_loyalty_recalculated_on_finalize' => (bool) ($request->input('promotion_codes') || $request->input('loyalty_points'))]]);
    }

    public function sell(Request $request, PosPaymentOperations $payments)
    {
        [$actor, $outlet] = $this->context($request, ['shop.sales']);
        $result = $payments->sell($actor, $outlet, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function invoice(Request $request, string $invoice, PosPaymentOperations $payments)
    {
        [$actor, $outlet] = $this->context($request, ['shop.sales', 'shop.invoices']);
        $summary = $payments->invoice($actor, $outlet, $invoice);
        $row = DB::table('invoices')->where('public_id', $invoice)->where('outlet_id', $outlet->id)->firstOrFail();
        $lines = DB::table('sales as s')->join('products as p', 'p.id', '=', 's.product_id')
            ->where('s.invoice_id', $row->id)->orderBy('s.id')
            ->get(['s.public_id as sale_id', 'p.public_id as product_id', 'p.name', 's.quantity', 's.returned_quantity',
                's.sale_price', 's.net_total_price', 'p.track_imei'])
            ->map(function ($line) use ($row) {
                $units = $line->track_imei ? DB::table('stock_units')->where('sale_id',
                    DB::table('sales')->where('public_id', $line->sale_id)->value('id'))
                    ->where('invoice_id', $row->id)->get(['public_id', 'unit_code'])->map(fn ($u) => (array) $u)->all() : [];

                return (array) $line + ['units' => $units];
            })->all();

        return response()->json(['data' => $summary + ['lines' => $lines]]);
    }

    public function acceptReturn(Request $request, SalesOperations $sales)
    {
        [$actor, $outlet] = $this->context($request, ['shop.sales']);
        $result = $sales->acceptReturn($actor, $outlet, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function refund(Request $request, PosPaymentOperations $payments)
    {
        [$actor, $outlet] = $this->context($request, ['shop.sales']);
        $result = $payments->recordRefund($actor, $outlet, $this->key($request), $request->all());

        return response()->json(['data' => $result]);
    }

    public function label(Request $request, string $kind, string $id, RetailLabels $labels)
    {
        [$actor, $outlet] = $this->context($request, ['shop.labels']);
        abort_unless(in_array($kind, ['product', 'unit'], true), 404);
        $payload = $kind === 'product' ? $labels->product($actor, $outlet, $id) : $labels->unit($actor, $outlet, $id);

        return response()->json(['data' => $payload]);
    }

    private function context(Request $request, array $permissions): array
    {
        $actor = Auth::guard('admin')->user();
        abort_unless($actor instanceof Admin, 401);
        $outletId = (int) $request->session()->get('active_outlet_id', 0);
        $outlet = $actor->shops()->whereKey($outletId)->where('outlets.status', false)
            ->whereNull('outlets.archived_at')->first();
        abort_unless($outlet instanceof Outlet, 403);
        $fresh = $actor->fresh();
        abort_unless(collect($permissions)->contains(fn ($permission) => app(Access::class)->allows($fresh, $permission, $outlet->fresh())), 403);

        return [$fresh, $outlet->fresh()];
    }

    private function key(Request $request): string
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));
        if ($key === '') {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency-Key is required for transaction completion.']);
        }

        return $key;
    }

    private function lookupProductId(Outlet $outlet, string $raw): ?int
    {
        $q = trim($raw);
        if (str_starts_with($q, 'mobist:product:')) {
            $q = substr($q, 15);
        }
        if (str_starts_with($q, 'mobist:unit:')) {
            $unit = StockUnit::where('public_id', substr($q, 12))->first();

            return $unit ? Product::whereKey($unit->product_id)->where('outlet_id', $outlet->id)->value('id') : null;
        }
        $id = Product::where('outlet_id', $outlet->id)->where('isDeleted', false)
            ->where(fn ($b) => $b->where('public_id', $q)->orWhere('product_code', $q))->value('id');
        if ($id) {
            return (int) $id;
        }
        $unit = StockUnit::where(fn ($b) => $b->where('public_id', $q)->orWhere('unit_code', $q))->first();
        if ($unit && Product::whereKey($unit->product_id)->where('outlet_id', $outlet->id)->exists()) {
            return (int) $unit->product_id;
        }
        $unitId = DB::table('active_imeis')->where('imei', $q)->value('stock_unit_id');
        if ($unitId) {
            $productId = StockUnit::whereKey($unitId)->value('product_id');
            if ($productId && Product::whereKey($productId)->where('outlet_id', $outlet->id)->exists()) {
                return (int) $productId;
            }
        }

        return null;
    }

    private function lookupUnit(Product $product, string $raw): ?StockUnit
    {
        $q = trim($raw);
        if (str_starts_with($q, 'mobist:unit:')) {
            $q = substr($q, 12);
        }
        $unit = StockUnit::where('product_id', $product->id)
            ->where(fn ($b) => $b->where('public_id', $q)->orWhere('unit_code', $q))->first();
        if ($unit) {
            return $unit;
        }
        $unitId = DB::table('active_imeis')->where('imei', $q)->value('stock_unit_id');

        return $unitId ? StockUnit::whereKey($unitId)->where('product_id', $product->id)->first() : null;
    }

    private function productPayload(Product $product, bool $inventory = false): array
    {
        $units = $product->track_imei ? StockUnit::where('product_id', $product->id)->where('status', 'in_stock')
            ->orderBy('unit_no')->limit(30)->get()->map(fn (StockUnit $unit) => $this->unitPayload($unit))->all() : [];
        $acquisitions = $inventory ? DB::table('stock_acquisitions')->where('product_id', $product->id)
            ->where('outlet_id', $product->outlet_id)->latest('id')->limit(20)
            ->get(['source_type', 'quantity', 'unit_purchase_price', 'acquired_at'])
            ->map(fn ($row) => ['source_type' => $row->source_type, 'quantity' => (int) $row->quantity,
                'unit_purchase_price' => (string) $row->unit_purchase_price, 'acquired_at' => $row->acquired_at])->all() : [];
        $movements = $inventory ? DB::table('stock_movements')->where('product_id', $product->id)
            ->where('outlet_id', $product->outlet_id)->latest('id')->limit(30)
            ->get(['type', 'quantity_change', 'stock_before', 'stock_after', 'created_at'])
            ->map(fn ($row) => ['type' => $row->type, 'quantity_change' => (int) $row->quantity_change,
                'stock_before' => (int) $row->stock_before, 'stock_after' => (int) $row->stock_after,
                'created_at' => $row->created_at])->all() : [];

        return ['id' => $product->public_id, 'code' => $product->product_code, 'name' => $product->name,
            'category' => $product->category, 'model' => $product->model, 'purchase_price' => (string) $product->purchase_price,
            'sale_price' => (string) $product->sale_price, 'qty' => (int) $product->qty, 'track_imei' => (bool) $product->track_imei,
            'version' => (int) $product->version, 'units' => $units,
            'acquisitions' => $acquisitions, 'movements' => $movements,
            'brand_snapshot' => $inventory ? $product->brand : null,
            'brand_display' => $inventory ? $product->brandDisplay() : null,
            'subcategory_display' => $inventory ? $product->subcategoryDisplay() : null,
            'ram_display' => $inventory ? $product->ramMasterOption?->label : null,
            'storage_display' => $inventory ? $product->storageMasterOption?->label : null,
            'sim_display' => $inventory ? $product->simDisplay() : null,
            'category_master_data_id' => $inventory ? $product->category_master_data_id : null,
            'subcategory_master_data_id' => $inventory ? $product->subcategory_master_data_id : null,
            'brand_master_data_id' => $inventory ? $product->brand_master_data_id : null,
            'ram_master_data_id' => $inventory ? $product->ram_master_data_id : null,
            'storage_master_data_id' => $inventory ? $product->storage_master_data_id : null,
            'sim_master_data_id' => $inventory ? $product->sim_master_data_id : null,
            'warranty_type' => $inventory ? $product->warranty_type : null,
            'warranty_unit' => $inventory ? $product->warranty_unit : null,
            'warranty_duration' => $inventory ? $product->warranty_duration : null,
            'website_listing' => $inventory ? DB::table('product_listings')
                ->where('product_id', $product->id)->where('external_source', 'pos')
                ->first(['slug', 'description', 'is_online', 'version']) : null];
    }

    private function unitPayload(StockUnit $unit): array
    {
        $imeis = DB::table('product_imeis')->where('stock_unit_id', $unit->id)->where('status', 'in_stock')
            ->orderBy('slot_no')->pluck('imei')->all();

        return ['id' => $unit->public_id, 'code' => $unit->unit_code, 'status' => $unit->status,
            'version' => (int) $unit->version, 'imeis' => $imeis];
    }
}
