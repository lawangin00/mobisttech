<?php

namespace App\Procurement;

use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Inventory\StockLedger;
use App\Inventory\StockReceiptWriter;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class SupplierProcurement
{
    public function __construct(private StockLedger $stock, private StockReceiptWriter $receipts) {}

    public function createSupplier(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['supplier_code', 'name', 'tax_identifier', 'phone', 'email', 'address', 'notes', 'contacts']);
        $data = Validator::make($input, [
            'supplier_code' => ['required', 'string', 'max:50', 'regex:/\A[A-Za-z0-9][A-Za-z0-9-]*\z/'],
            'name' => 'required|string|max:255', 'tax_identifier' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000', 'contacts' => 'sometimes|array|max:20',
            'contacts.*.name' => 'required|string|max:255', 'contacts.*.job_title' => 'nullable|string|max:120',
            'contacts.*.phone' => 'nullable|string|max:30', 'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.is_primary' => 'sometimes|boolean',
        ])->validate();
        $data['supplier_code'] = strtoupper(trim($data['supplier_code']));
        $data['name'] = trim($data['name']);
        $data['contacts'] = $this->contacts($data['contacts'] ?? []);

        return $this->mutate($actor, $outlet, 'procurement.supplier.create', $key, $data, function (Admin $fresh) use ($outlet, $data) {
            abort_if(DB::table('suppliers')->where('outlet_id', $outlet->id)->where('supplier_code', $data['supplier_code'])->exists(), 422, 'Supplier code already exists in this outlet.');
            $publicId = (string) Str::uuid();
            $id = DB::table('suppliers')->insertGetId([
                'public_id' => $publicId, 'outlet_id' => $outlet->id, 'supplier_code' => $data['supplier_code'], 'name' => $data['name'],
                'tax_identifier' => $this->nullable($data['tax_identifier'] ?? null), 'phone' => $this->nullable($data['phone'] ?? null),
                'email' => $this->nullable($data['email'] ?? null), 'address' => $this->nullable($data['address'] ?? null),
                'notes' => $this->nullable($data['notes'] ?? null), 'is_active' => true, 'version' => 1,
                'created_by_admin_id' => $fresh->id, 'updated_by_admin_id' => $fresh->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->replaceContacts($id, $data['contacts']);
            IdentityAudit::record('admin', $fresh->id, 'supplier_created', 'supplier:'.$publicId, $outlet->id);

            return ['supplier_id' => $publicId, 'version' => 1];
        });
    }

    public function updateSupplier(IdentityAccount $actor, Outlet $outlet, string $supplierId, string $key, array $input): array
    {
        $this->fields($input, ['version', 'name', 'tax_identifier', 'phone', 'email', 'address', 'notes', 'active', 'contacts']);
        $data = Validator::make($input, [
            'version' => 'required|integer|min:1', 'name' => 'sometimes|required|string|max:255',
            'tax_identifier' => 'nullable|string|max:100', 'phone' => 'nullable|string|max:30', 'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:2000', 'notes' => 'nullable|string|max:2000', 'active' => 'sometimes|boolean',
            'contacts' => 'sometimes|array|max:20', 'contacts.*.name' => 'required|string|max:255',
            'contacts.*.job_title' => 'nullable|string|max:120', 'contacts.*.phone' => 'nullable|string|max:30',
            'contacts.*.email' => 'nullable|email|max:255', 'contacts.*.is_primary' => 'sometimes|boolean',
        ])->validate();
        if (isset($data['contacts'])) {
            $data['contacts'] = $this->contacts($data['contacts']);
        }

        return $this->mutate($actor, $outlet, 'procurement.supplier.update', $key, [$supplierId, $data], function (Admin $fresh) use ($outlet, $supplierId, $data) {
            $supplier = DB::table('suppliers')->where('public_id', $supplierId)->where('outlet_id', $outlet->id)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            abort_if((int) $supplier->version !== (int) $data['version'], 409, 'Supplier version changed.');
            $updates = ['version' => $supplier->version + 1, 'updated_by_admin_id' => $fresh->id, 'updated_at' => now()];
            foreach (['name', 'tax_identifier', 'phone', 'email', 'address', 'notes'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $field === 'name' ? trim($data[$field]) : $this->nullable($data[$field]);
                }
            }
            if (array_key_exists('active', $data)) {
                $updates['is_active'] = (bool) $data['active'];
            }
            DB::table('suppliers')->where('id', $supplier->id)->update($updates);
            if (array_key_exists('contacts', $data)) {
                $this->replaceContacts($supplier->id, $data['contacts']);
            }
            IdentityAudit::record('admin', $fresh->id, 'supplier_updated', 'supplier:'.$supplierId, $outlet->id);

            return ['supplier_id' => $supplierId, 'version' => $updates['version']];
        });
    }

    public function createOrder(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['supplier_id', 'expected_at', 'notes', 'lines']);
        $data = Validator::make($input, [
            'supplier_id' => 'required|uuid', 'expected_at' => 'nullable|date', 'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity' => 'required|integer|min:1|max:100000', 'lines.*.unit_cost' => 'required',
            'lines.*.landed_unit_cost' => 'required', 'lines.*.notes' => 'nullable|string|max:1000',
        ])->validate();
        $lines = [];
        foreach ($data['lines'] as $line) {
            $this->fields($line, ['product_id', 'quantity', 'unit_cost', 'landed_unit_cost', 'notes']);
            $line['unit_cost'] = $this->positiveMoney($line['unit_cost']);
            $line['landed_unit_cost'] = $this->positiveMoney($line['landed_unit_cost']);
            $lines[] = $line;
        }
        usort($lines, fn ($a, $b) => strcmp($a['product_id'], $b['product_id']));
        abort_if(count(array_unique(array_column($lines, 'product_id'))) !== count($lines), 422, 'A product may appear once per purchase order.');
        $data['lines'] = $lines;
        $data['expected_at'] = isset($data['expected_at']) ? CarbonImmutable::parse($data['expected_at'])->utc()->format('Y-m-d H:i:s.u') : null;

        return $this->mutate($actor, $outlet, 'procurement.order.create', $key, $data, function (Admin $fresh) use ($outlet, $data) {
            $supplier = DB::table('suppliers')->where('public_id', $data['supplier_id'])->where('outlet_id', $outlet->id)
                ->where('is_active', true)->whereNull('archived_at')->lockForUpdate()->firstOrFail();
            $products = Product::whereIn('public_id', array_column($data['lines'], 'product_id'))->where('outlet_id', $outlet->id)
                ->where('isDeleted', false)->orderBy('id')->lockForUpdate()->get()->keyBy('public_id');
            abort_if($products->count() !== count($data['lines']), 422, 'Every purchase-order product must be active in the selected outlet.');
            $publicId = (string) Str::uuid();
            $primaryContact = DB::table('supplier_contacts')->where('supplier_id', $supplier->id)->where('is_active', true)
                ->orderByDesc('is_primary')->orderBy('id')->first();
            $contactSnapshot = $primaryContact ? json_encode([
                'name' => $primaryContact->name, 'job_title' => $primaryContact->job_title,
                'phone' => $primaryContact->phone, 'email' => $primaryContact->email,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) : null;
            $orderId = DB::table('purchase_orders')->insertGetId([
                'public_id' => $publicId, 'outlet_id' => $outlet->id, 'supplier_id' => $supplier->id,
                'supplier_code_snapshot' => $supplier->supplier_code, 'supplier_name_snapshot' => $supplier->name,
                'supplier_contact_snapshot' => $contactSnapshot,
                'order_number' => $this->number('purchase_order', 'PO', $outlet), 'status' => 'ordered', 'ordered_at' => now(),
                'expected_at' => $data['expected_at'], 'notes' => $this->nullable($data['notes'] ?? null),
                'created_by_admin_id' => $fresh->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($data['lines'] as $line) {
                $product = $products[$line['product_id']];
                DB::table('purchase_order_lines')->insert([
                    'public_id' => (string) Str::uuid(), 'purchase_order_id' => $orderId, 'outlet_id' => $outlet->id, 'product_id' => $product->id,
                    'ordered_quantity' => $line['quantity'], 'received_quantity' => 0, 'ordered_unit_cost' => $line['unit_cost'],
                    'planned_landed_unit_cost' => $line['landed_unit_cost'], 'notes' => $this->nullable($line['notes'] ?? null),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->event($orderId, 'ordered', $fresh, $outlet);
            IdentityAudit::record('admin', $fresh->id, 'purchase_order_created', 'purchase-order:'.$publicId, $outlet->id);

            return ['purchase_order_id' => $publicId, 'order_number' => DB::table('purchase_orders')->where('id', $orderId)->value('order_number'), 'status' => 'ordered'];
        });
    }

    public function receive(IdentityAccount $actor, Outlet $outlet, string $orderId, string $key, array $input): array
    {
        $this->fields($input, ['notes', 'lines']);
        $data = Validator::make($input, [
            'notes' => 'nullable|string|max:2000', 'lines' => 'required|array|min:1|max:100',
            'lines.*.line_id' => 'required|uuid', 'lines.*.quantity' => 'required|integer|min:1|max:100000',
            'lines.*.unit_cost' => 'required', 'lines.*.landed_unit_cost' => 'required',
        ])->validate();
        foreach ($data['lines'] as &$line) {
            $this->fields($line, ['line_id', 'quantity', 'unit_cost', 'landed_unit_cost']);
            $line['unit_cost'] = $this->positiveMoney($line['unit_cost']);
            $line['landed_unit_cost'] = $this->positiveMoney($line['landed_unit_cost']);
        }
        unset($line);
        usort($data['lines'], fn ($a, $b) => strcmp($a['line_id'], $b['line_id']));
        abort_if(count(array_unique(array_column($data['lines'], 'line_id'))) !== count($data['lines']), 422, 'A purchase-order line may appear once per receipt.');

        return $this->mutate($actor, $outlet, 'procurement.order.receive', $key, [$orderId, $data], function (Admin $fresh) use ($outlet, $orderId, $data) {
            $order = DB::table('purchase_orders')->where('public_id', $orderId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->status, ['ordered', 'partially_received'], true), 409, 'Purchase order cannot receive stock in its current state.');
            $supplier = DB::table('suppliers')->where('id', $order->supplier_id)->where('outlet_id', $outlet->id)->firstOrFail();
            $poLines = DB::table('purchase_order_lines')->where('purchase_order_id', $order->id)->orderBy('id')->lockForUpdate()->get()->keyBy('public_id');
            $source = PosMasterDataOption::where('list_key', 'acquisition_source_type')->where('code', 'supplier')->lockForUpdate()->firstOrFail();
            $receiptPublicId = (string) Str::uuid();
            $receiptId = DB::table('purchase_order_receipts')->insertGetId([
                'public_id' => $receiptPublicId, 'purchase_order_id' => $order->id, 'outlet_id' => $outlet->id,
                'receipt_number' => $this->number('purchase_order_receipt', 'PR', $outlet), 'received_at' => now(),
                'received_by_admin_id' => $fresh->id, 'notes' => $this->nullable($data['notes'] ?? null), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $acquisitions = [];
            foreach ($data['lines'] as $inputLine) {
                $line = $poLines[$inputLine['line_id']] ?? abort(422, 'Purchase-order line is unavailable.');
                $remaining = (int) $line->ordered_quantity - (int) $line->received_quantity;
                abort_if($inputLine['quantity'] > $remaining, 409, 'Receipt quantity exceeds the remaining purchase-order quantity.');
                $product = $this->stock->lock($line->product_id);
                abort_if($product->outlet_id !== $outlet->id || $product->isDeleted, 409, 'Receipt product is unavailable in this outlet.');
                $this->stock->snapshot($product->id);
                $written = $this->receipts->receive($product, $source, (int) $inputLine['quantity'], $inputLine['landed_unit_cost'], [
                    'business_name' => $supplier->name, 'seller_name' => null, 'seller_cnic' => null,
                    'seller_phone' => $supplier->phone, 'seller_address' => $supplier->address,
                ], 'Purchase-order receipt '.$order->order_number);
                $receiptLineId = (string) Str::uuid();
                DB::table('purchase_order_receipt_lines')->insert([
                    'public_id' => $receiptLineId, 'purchase_order_id' => $order->id, 'receipt_id' => $receiptId,
                    'purchase_order_line_id' => $line->id, 'acquisition_id' => $written['acquisition_id'], 'quantity' => $inputLine['quantity'],
                    'received_unit_cost' => $inputLine['unit_cost'], 'received_landed_unit_cost' => $inputLine['landed_unit_cost'],
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                DB::table('acquisition_source_references')->insert([
                    'id' => (string) Str::uuid(), 'acquisition_id' => $written['acquisition_id'], 'kind' => 'purchase_order_receipt',
                    'source_line_id' => $receiptLineId, 'created_at' => now(),
                ]);
                DB::table('purchase_order_lines')->where('id', $line->id)->update([
                    'received_quantity' => $line->received_quantity + $inputLine['quantity'], 'updated_at' => now(),
                ]);
                $acquisitions[] = ['line_id' => $line->public_id, 'acquisition_id' => $written['acquisition_id']];
                $this->stock->snapshot($product->id);
            }
            $remaining = (int) DB::table('purchase_order_lines')->where('purchase_order_id', $order->id)
                ->selectRaw('COALESCE(SUM(ordered_quantity - received_quantity), 0) remaining')->value('remaining');
            $status = $remaining === 0 ? 'received' : 'partially_received';
            DB::table('purchase_orders')->where('id', $order->id)->update([
                'status' => $status, 'received_at' => $status === 'received' ? now() : null, 'updated_at' => now(),
            ]);
            $this->event($order->id, $status === 'received' ? 'received' : 'partially_received', $fresh, $outlet);
            IdentityAudit::record('admin', $fresh->id, 'purchase_order_'.$status, 'purchase-order:'.$orderId, $outlet->id);

            return ['purchase_order_id' => $orderId, 'receipt_id' => $receiptPublicId, 'status' => $status, 'acquisitions' => $acquisitions];
        });
    }

    public function cancel(IdentityAccount $actor, Outlet $outlet, string $orderId, string $key, array $input): array
    {
        $this->fields($input, ['reason']);
        $data = Validator::make($input, ['reason' => 'required|string|max:1000'])->validate();

        return $this->mutate($actor, $outlet, 'procurement.order.cancel', $key, [$orderId, $data], function (Admin $fresh) use ($outlet, $orderId, $data) {
            $order = DB::table('purchase_orders')->where('public_id', $orderId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($order->status, ['ordered', 'partially_received'], true), 409, 'Purchase order cannot be cancelled in its current state.');
            DB::table('purchase_orders')->where('id', $order->id)->update([
                'status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by_admin_id' => $fresh->id,
                'cancellation_reason' => trim($data['reason']), 'updated_at' => now(),
            ]);
            $this->event($order->id, 'cancelled', $fresh, $outlet);
            IdentityAudit::record('admin', $fresh->id, 'purchase_order_cancelled', 'purchase-order:'.$orderId, $outlet->id);

            return ['purchase_order_id' => $orderId, 'status' => 'cancelled'];
        });
    }

    public function setReorderPolicy(IdentityAccount $actor, Outlet $outlet, string $productId, string $key, array $input): array
    {
        $this->fields($input, ['version', 'reorder_threshold', 'target_stock', 'active']);
        $data = Validator::make($input, [
            'version' => 'nullable|integer|min:1', 'reorder_threshold' => 'required|integer|min:0|max:1000000',
            'target_stock' => 'required|integer|min:1|max:1000000', 'active' => 'sometimes|boolean',
        ])->validate();
        abort_unless($data['target_stock'] > $data['reorder_threshold'], 422, 'Target stock must exceed the reorder threshold.');

        return $this->mutate($actor, $outlet, 'procurement.reorder.update', $key, [$productId, $data], function (Admin $fresh) use ($outlet, $productId, $data) {
            $product = Product::where('public_id', $productId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $existing = DB::table('reorder_policies')->where('outlet_id', $outlet->id)->where('product_id', $product->id)->lockForUpdate()->first();
            abort_if($existing && (int) ($data['version'] ?? 0) !== (int) $existing->version, 409, 'Reorder policy version changed.');
            abort_if(! $existing && isset($data['version']), 409, 'A new reorder policy has no prior version.');
            $version = $existing ? $existing->version + 1 : 1;
            DB::table('reorder_policies')->updateOrInsert(['outlet_id' => $outlet->id, 'product_id' => $product->id], [
                'public_id' => $existing?->public_id ?? (string) Str::uuid(), 'reorder_threshold' => $data['reorder_threshold'],
                'target_stock' => $data['target_stock'], 'is_active' => $data['active'] ?? true, 'version' => $version,
                'updated_by_admin_id' => $fresh->id, 'created_at' => $existing?->created_at ?? now(), 'updated_at' => now(),
            ]);
            IdentityAudit::record('admin', $fresh->id, 'reorder_policy_updated', 'product:'.$productId, $outlet->id);

            return ['product_id' => $productId, 'version' => $version];
        });
    }

    public function recommendations(IdentityAccount $actor, Outlet $outlet, int $limit = 100): array
    {
        $fresh = $this->authorize($actor, $outlet);
        $limit = max(1, min($limit, 200));

        return DB::transaction(function () use ($fresh, $outlet, $limit) {
            $policies = DB::table('reorder_policies')->join('products', 'products.id', '=', 'reorder_policies.product_id')
                ->where('reorder_policies.outlet_id', $outlet->id)->where('reorder_policies.is_active', true)->where('products.isDeleted', false)
                ->orderBy('products.id')->limit($limit)->get(['reorder_policies.*', 'products.public_id as product_public_id', 'products.name']);
            $rows = [];
            foreach ($policies as $policy) {
                $available = $this->stock->snapshot($policy->product_id)['available'];
                if ($available > $policy->reorder_threshold) {
                    continue;
                }
                $incoming = (int) DB::table('purchase_order_lines')->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
                    ->where('purchase_order_lines.product_id', $policy->product_id)->where('purchase_orders.outlet_id', $outlet->id)
                    ->whereIn('purchase_orders.status', ['ordered', 'partially_received'])
                    ->selectRaw('COALESCE(SUM(ordered_quantity - received_quantity), 0) incoming')->value('incoming');
                $suggested = max((int) $policy->target_stock - $available - $incoming, 0);
                $rows[] = ['product_id' => $policy->product_public_id, 'name' => $policy->name,
                    'stock_status' => $available === 0 ? 'out_of_stock' : 'low_stock', 'available' => $available,
                    'incoming' => $incoming, 'reorder_threshold' => (int) $policy->reorder_threshold,
                    'target_stock' => (int) $policy->target_stock, 'recommended_quantity' => $suggested,
                    'action' => $suggested > 0 ? 'create_purchase_order' : 'covered_by_open_order'];
            }
            IdentityAudit::record('admin', $fresh->id, 'procurement_recommendations_viewed', null, $outlet->id);

            return $rows;
        }, 3);
    }

    public function supplierHistory(IdentityAccount $actor, Outlet $outlet, string $supplierId): array
    {
        $this->authorize($actor, $outlet);
        $supplier = DB::table('suppliers')->where('public_id', $supplierId)->where('outlet_id', $outlet->id)->firstOrFail();

        return DB::table('purchase_orders')->leftJoin('purchase_order_receipts', 'purchase_order_receipts.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('purchase_order_receipt_lines', 'purchase_order_receipt_lines.receipt_id', '=', 'purchase_order_receipts.id')
            ->leftJoin('stock_acquisitions', 'stock_acquisitions.id', '=', 'purchase_order_receipt_lines.acquisition_id')
            ->where('purchase_orders.supplier_id', $supplier->id)->where('purchase_orders.outlet_id', $outlet->id)
            ->orderByDesc('purchase_orders.ordered_at')->orderByDesc('purchase_order_receipts.received_at')->limit(100)
            ->get(['purchase_orders.public_id as purchase_order_id', 'purchase_orders.order_number', 'purchase_orders.status',
                'purchase_orders.expected_at', 'purchase_order_receipts.public_id as receipt_id', 'purchase_order_receipts.received_at',
                'purchase_order_receipt_lines.public_id as receipt_line_id', 'stock_acquisitions.id as acquisition_id',
                'stock_acquisitions.quantity', 'stock_acquisitions.unit_purchase_price'])->map(fn ($row) => (array) $row)->all();
    }

    private function mutate(IdentityAccount $actor, Outlet $outlet, string $operation, string $key, mixed $payload, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $callback) {
            // The archival transaction owns this row lock; protect new writes and cached replays.
            $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            $fresh = $this->authorize($actor, $lockedOutlet);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $fresh::class.':'.$fresh->getKey(), 'operation' => $operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update([
                'status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'resource_type' => 'procurement', 'updated_at' => now(),
            ]);

            return $response;
        }, 3);
    }

    private function authorize(IdentityAccount $actor, Outlet $outlet): Admin
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.procurement', $outlet->fresh()), 403);

        return $fresh;
    }

    private function contacts(array $contacts): array
    {
        $primary = 0;
        foreach ($contacts as &$contact) {
            $this->fields($contact, ['name', 'job_title', 'phone', 'email', 'is_primary']);
            abort_if($this->nullable($contact['phone'] ?? null) === null && $this->nullable($contact['email'] ?? null) === null, 422, 'Each supplier contact requires a phone or email.');
            $contact['name'] = trim($contact['name']);
            $contact['is_primary'] = (bool) ($contact['is_primary'] ?? false);
            $primary += $contact['is_primary'] ? 1 : 0;
        }
        unset($contact);
        abort_if($primary > 1, 422, 'Only one supplier contact may be primary.');

        return $contacts;
    }

    private function replaceContacts(int $supplierId, array $contacts): void
    {
        DB::table('supplier_contacts')->where('supplier_id', $supplierId)->delete();
        foreach ($contacts as $contact) {
            DB::table('supplier_contacts')->insert([
                'public_id' => (string) Str::uuid(), 'supplier_id' => $supplierId, 'name' => $contact['name'],
                'job_title' => $this->nullable($contact['job_title'] ?? null), 'phone' => $this->nullable($contact['phone'] ?? null),
                'email' => $this->nullable($contact['email'] ?? null), 'is_primary' => $contact['is_primary'], 'is_active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function event(int $orderId, string $event, Admin $actor, Outlet $outlet): void
    {
        $order = DB::table('purchase_orders')->where('id', $orderId)->firstOrFail();
        $lines = DB::table('purchase_order_lines')->where('purchase_order_id', $orderId)->orderBy('id')->get([
            'public_id', 'product_id', 'ordered_quantity', 'received_quantity', 'ordered_unit_cost', 'planned_landed_unit_cost',
        ])->map(fn ($line) => (array) $line)->all();
        $sequence = (int) DB::table('purchase_order_events')->where('purchase_order_id', $orderId)->lockForUpdate()->max('sequence') + 1;
        $snapshot = ['contract' => 'purchase-order-event.v1', 'purchase_order_id' => $order->public_id, 'sequence' => $sequence,
            'event' => $event, 'status' => $order->status, 'supplier_id' => $order->supplier_id,
            'supplier_code' => $order->supplier_code_snapshot, 'supplier_name' => $order->supplier_name_snapshot,
            'supplier_contact' => $order->supplier_contact_snapshot ? json_decode($order->supplier_contact_snapshot, true, flags: JSON_THROW_ON_ERROR) : null,
            'expected_at' => $order->expected_at,
            'cancellation_reason' => $order->cancellation_reason,
            'lines' => $lines, 'occurred_at' => now()->utc()->format('Y-m-d\TH:i:s.u\Z')];
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        DB::table('purchase_order_events')->insert([
            'purchase_order_id' => $orderId, 'sequence' => $sequence, 'event' => $event, 'actor_admin_id' => $actor->id,
            'actor_name_snapshot' => $actor->name, 'actor_role_snapshot' => $actor->roleSnapshot(), 'outlet_name_snapshot' => $outlet->name,
            'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'occurred_at' => now(),
        ]);
    }

    private function number(string $namespace, string $prefix, Outlet $outlet): string
    {
        $date = now()->utc()->format('Y-m-d');
        DB::table('document_sequences')->insertOrIgnore(['namespace' => $namespace, 'outlet_id' => $outlet->id, 'business_date' => $date, 'next_sequence' => 1]);
        $row = DB::table('document_sequences')->where(['namespace' => $namespace, 'outlet_id' => $outlet->id, 'business_date' => $date])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('%s-%s-%s-%05d', $prefix, $outlet->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function positiveMoney(mixed $value): string
    {
        $money = SourceRow::money($value);
        abort_unless(bccomp($money, '0.00', 2) === 1, 422, 'Procurement costs must be positive.');

        return $money;
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected procurement fields; identities, quantities and states are server controlled.']);
        }
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
