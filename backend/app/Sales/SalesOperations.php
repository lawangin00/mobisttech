<?php

namespace App\Sales;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Catalog\ProductDefinitions;
use App\Identity\Access;
use App\Identity\IdentityAccount;
use App\Identity\IdentityAudit;
use App\Inventory\StockLedger;
use App\Inventory\TransactionalStock;
use App\Migration\SourceRow;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Models\SuperAdmin;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** The single POS sale/return authority. Payment collection and refunds remain separate owners. */
final class SalesOperations
{
    public function __construct(private TransactionalStock $transactionalStock, private StockLedger $stock) {}

    public function sell(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['customer_id', 'new_customer', 'customer_name', 'customer_phone', 'customer_cnic', 'customer_info', 'discount', 'discount_reason', 'lines']);
        $data = Validator::make($input, [
            'customer_id' => 'nullable|uuid', 'new_customer' => 'sometimes|boolean', 'customer_name' => 'nullable|string|max:255',
            'customer_phone' => 'nullable|string|max:40', 'customer_cnic' => ['nullable', 'regex:/\A\d{5}-\d{7}-\d\z/'],
            'customer_info' => 'nullable|string|max:2000', 'discount' => 'required', 'discount_reason' => 'nullable|string|max:500',
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|uuid', 'lines.*.quantity' => 'required|integer|min:1|max:10000',
        ])->validate();
        $discount = SourceRow::money($data['discount']);

        return $this->mutate($actor, $outlet, 'sale', $key, $input, function () use ($actor, $outlet, $data, $discount) {
            $customer = $this->customer($data);
            $lines = collect($data['lines'])->sortBy('product_id')->values();
            if ($lines->pluck('product_id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Each product must appear once in a sale.']);
            }
            $prepared = [];
            $gross = '0.00';
            foreach ($lines as $line) {
                $product = Product::where('public_id', $line['product_id'])->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
                $unit = SourceRow::money((string) $product->sale_price);
                $lineGross = bcmul($unit, (string) $line['quantity'], 2);
                $gross = bcadd($gross, $lineGross, 2);
                $prepared[] = compact('product', 'unit', 'lineGross') + ['quantity' => (int) $line['quantity']];
            }
            if (bccomp($discount, $gross, 2) > 0 || (bccomp($discount, '0.00', 2) > 0 && trim((string) ($data['discount_reason'] ?? '')) === '')) {
                throw ValidationException::withMessages(['discount' => 'Discount cannot exceed gross and requires a reason.']);
            }
            $allocations = $this->allocate($prepared, $gross, $discount);
            $invoiceId = DB::table('invoices')->insertGetId([
                'outlet_id' => $outlet->id, 'total_bill' => $gross, 'discount' => $discount, 'final_bill' => bcsub($gross, $discount, 2),
                'customer_id' => $customer?->id, 'customer_name' => $customer?->display_name ?? trim((string) ($data['customer_name'] ?? '')) ?: null,
                'customer_phone' => $customer?->mobile ?? ($data['customer_phone'] ?? null), 'customer_cnic' => $data['customer_cnic'] ?? null,
                'customer_info' => $data['customer_info'] ?? null, 'salesperson_admin_id' => $actor instanceof SuperAdmin ? null : $actor->id,
                'salesperson_name' => $actor->name, 'business_snapshot' => json_encode(['contract' => 'outlet-business-at-sale.v1', 'outlet_id' => $outlet->public_id,
                    'outlet_code' => $outlet->outlet_code, 'name' => $outlet->name, 'business_name' => $outlet->business_name,
                    'business_legal_name' => $outlet->business_legal_name, 'business_email' => $outlet->business_email, 'business_phone' => $outlet->business_phone,
                    'business_whatsapp' => $outlet->business_whatsapp, 'business_address' => $outlet->business_address,
                    'business_hours' => $outlet->business_hours, 'business_identifiers' => $outlet->business_identifiers], JSON_THROW_ON_ERROR),
                'warranty_terms_snapshot' => json_encode(['contract' => 'product-warranty-at-sale.v1'], JSON_THROW_ON_ERROR),
                'invoice_number' => $this->number($outlet), 'public_id' => (string) Str::uuid(), 'currency' => 'PKR', 'created_at' => now(), 'updated_at' => now(),
            ]);
            if (bccomp($discount, '0.00', 2) > 0) {
                app(FinancialReferences::class)->adjustment('invoice', $invoiceId, MoneySnapshot::adjustment((string) Str::uuid(), 'manual_discount', $discount, trim($data['discount_reason'])));
            }
            $saleIds = [];
            foreach ($prepared as $index => $line) {
                $net = bcsub($line['lineGross'], $allocations[$index], 2);
                $cost = bcmul(SourceRow::money((string) $line['product']->purchase_price), (string) $line['quantity'], 2);
                $sale = DB::table('sales')->insertGetId(['public_id' => (string) Str::uuid(), 'product_id' => $line['product']->id, 'outlet_id' => $outlet->id,
                    'sale_date' => now()->toDateString(), 'sale_price' => $line['unit'], 'invoice_id' => $invoiceId, 'quantity' => $line['quantity'],
                    'total_price' => $line['lineGross'], 'purchase_price' => SourceRow::money((string) $line['product']->purchase_price),
                    'discount_allocated' => $allocations[$index], 'net_total_price' => $net, 'profit' => bcsub($net, $cost, 2),
                    'invoice_detail_snapshot' => json_encode(['contract' => 'sale-line.v1', 'product_id' => $line['product']->public_id,
                        'product_code' => $line['product']->product_code, 'name' => $line['product']->name,
                        'warranty_type' => $line['product']->warranty_type, 'warranty_unit' => $line['product']->warranty_unit,
                        'warranty_duration' => $line['product']->warranty_duration], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
                $this->transactionalStock->consumeSale($sale);
                $saleIds[] = DB::table('sales')->where('id', $sale)->value('public_id');
            }
            $invoice = DB::table('invoices')->where('id', $invoiceId)->firstOrFail();

            return ['invoice_id' => $invoice->public_id, 'invoice_number' => $invoice->invoice_number, 'total_bill' => $gross,
                'discount' => $discount, 'final_bill' => $invoice->final_bill, 'sale_ids' => $saleIds];
        });
    }

    public function acceptReturn(IdentityAccount $actor, Outlet $outlet, string $key, array $input): array
    {
        $this->fields($input, ['invoice_id', 'reason', 'lines']);
        $data = Validator::make($input, ['invoice_id' => 'required|uuid', 'reason' => 'required|string|max:1000', 'lines' => 'required|array|min:1|max:100',
            'lines.*.sale_id' => 'required|uuid', 'lines.*.quantity' => 'required|integer|min:1|max:10000', 'lines.*.stock_unit_id' => 'nullable|uuid',
            'lines.*.condition' => 'required|string|max:100', 'lines.*.disposition' => 'required|in:sellable,damaged,quarantined'])->validate();

        return $this->mutate($actor, $outlet, 'return', $key, $input, function () use ($actor, $outlet, $data, $key) {
            $invoice = DB::table('invoices')->where('public_id', $data['invoice_id'])->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
            $returnId = DB::table('returns')->insertGetId(['invoice_id' => $invoice->id, 'order_id' => $invoice->order_id,
                'actor_type' => $actor::class, 'actor_id' => $actor->id, 'reason' => trim($data['reason']), 'status' => 'accepted',
                'idempotency_key' => $key, 'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            $due = '0.00';
            $ids = [];
            foreach (collect($data['lines'])->sortBy('sale_id')->values() as $line) {
                $sale = DB::table('sales')->where('public_id', $line['sale_id'])->where('invoice_id', $invoice->id)->lockForUpdate()->firstOrFail();
                $product = $this->stock->lock($sale->product_id);
                if ($product->outlet_id !== $outlet->id || $sale->returned_quantity + $line['quantity'] > $sale->quantity) {
                    throw new LogicException('Return exceeds the original outlet sale quantity.');
                }
                $unit = null;
                if ($product->track_imei) {
                    if ((int) $line['quantity'] !== 1) {
                        throw new LogicException('A serialized return line represents exactly one physical unit.');
                    }
                    $unit = StockUnit::where('public_id', $line['stock_unit_id'] ?? '')->where('product_id', $product->id)
                        ->where('invoice_id', $invoice->id)->where('sale_id', $sale->id)->where('status', 'sold')->lockForUpdate()->firstOrFail();
                } elseif (! empty($line['stock_unit_id'])) {
                    throw new LogicException('Quantity stock cannot submit a physical-unit identity.');
                }
                $lineGross = bcmul($sale->sale_price, (string) $line['quantity'], 2);
                $discount = $this->portion($sale->id, $sale->discount_allocated, $sale->quantity, $line['quantity'], $sale->returned_quantity + $line['quantity'] === $sale->quantity);
                $net = bcsub($lineGross, $discount, 2);
                $purchase = bcmul($sale->purchase_price, (string) $line['quantity'], 2);
                $snapshot = ['contract' => 'sale-return.v1', 'invoice_id' => $invoice->public_id, 'sale_id' => $sale->public_id,
                    'quantity' => (int) $line['quantity'], 'unit_price' => $sale->sale_price, 'discount_amount' => $discount,
                    'net_amount' => $net, 'purchase_amount' => $purchase, 'currency' => 'PKR'];
                $public = (string) Str::uuid();
                $returnLine = DB::table('return_lines')->insertGetId(['public_id' => $public, 'return_id' => $returnId, 'invoice_id' => $invoice->id,
                    'sale_id' => $sale->id, 'stock_unit_id' => $unit?->id, 'quantity' => $line['quantity'], 'unit_price' => $sale->sale_price,
                    'discount_amount' => $discount, 'net_amount' => $net, 'purchase_amount' => $purchase, 'currency' => 'PKR',
                    'sale_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'snapshot_sha256' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)),
                    'condition' => trim($line['condition']), 'disposition' => $line['disposition'], 'accepted_at' => now()]);
                $successor = $unit ? $this->returnedUnit($unit, $line['disposition'], $public) : null;
                if ($successor) {
                    DB::table('return_lines')->where('id', $returnLine)->update(['successor_stock_unit_id' => $successor->id]);
                }
                if ($product->sold_qty < $line['quantity']) {
                    throw new LogicException('Product sold counter cannot absorb this return.');
                }
                $product->sold_qty -= (int) $line['quantity'];
                $this->stock->movement($product, 'customer_return', $line['disposition'] === 'sellable' ? (int) $line['quantity'] : 0,
                    'return_line', $returnLine, 'Accepted customer return; disposition '.$line['disposition'], $successor?->id);
                $this->stock->snapshot($product->id);
                DB::table('sales')->where('id', $sale->id)->update(['returned_quantity' => $sale->returned_quantity + $line['quantity'], 'version' => $sale->version + 1, 'updated_at' => now()]);
                $due = bcadd($due, $net, 2);
                $ids[] = $public;
            }
            $return = DB::table('returns')->where('id', $returnId)->firstOrFail();

            return ['return_id' => $return->public_id, 'status' => 'accepted', 'refund_due' => $due, 'currency' => 'PKR',
                'refund_status' => 'not_created', 'return_line_ids' => $ids];
        });
    }

    private function mutate(IdentityAccount $actor, Outlet $outlet, string $operation, string $key, array $input, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $input, $callback) {
            $fresh = $actor->fresh();
            abort_unless($fresh && app(Access::class)->allows($fresh, 'shop.sales', $outlet->fresh()), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($input), JSON_THROW_ON_ERROR));
            $identity = ['actor_scope' => $actor::class.':'.$actor->id, 'operation' => 'sales.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback();
            IdentityAudit::record($actor instanceof SuperAdmin ? 'superadmin' : 'admin', $actor->id, $operation === 'sale' ? 'sale_created' : 'return_accepted', ($operation === 'sale' ? 'invoice:' : 'return:').($response[$operation === 'sale' ? 'invoice_id' : 'return_id']));
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed', 'response' => json_encode($response, JSON_THROW_ON_ERROR),
                'resource_type' => $operation, 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function customer(array $data): ?object
    {
        if (! empty($data['customer_id'])) {
            return DB::table('customers')->where('public_id', $data['customer_id'])->whereNull('archived_at')->lockForUpdate()->firstOrFail();
        }
        if (! ($data['new_customer'] ?? false)) {
            return null;
        }
        if (trim((string) ($data['customer_name'] ?? '')) === '') {
            throw ValidationException::withMessages(['customer_name' => 'A new operational customer requires a name.']);
        }
        $id = DB::table('customers')->insertGetId(['display_name' => trim($data['customer_name']), 'mobile' => $data['customer_phone'] ?? null,
            'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);

        return DB::table('customers')->where('id', $id)->firstOrFail();
    }

    private function number(Outlet $outlet): string
    {
        $date = now()->toDateString();
        DB::table('document_sequences')->insertOrIgnore(['namespace' => 'pos_invoice', 'outlet_id' => $outlet->id, 'business_date' => $date, 'next_sequence' => 1]);
        $row = DB::table('document_sequences')->where(['namespace' => 'pos_invoice', 'outlet_id' => $outlet->id, 'business_date' => $date])->lockForUpdate()->firstOrFail();
        DB::table('document_sequences')->where('id', $row->id)->update(['next_sequence' => $row->next_sequence + 1]);

        return sprintf('INV-%s-%s-%06d', $outlet->outlet_code, str_replace('-', '', $date), $row->next_sequence);
    }

    private function allocate(array $lines, string $gross, string $discount): array
    {
        $remaining = $this->cents($discount);
        $grossCents = $this->cents($gross);
        $result = [];
        foreach ($lines as $index => $line) {
            $share = $index === array_key_last($lines) ? $remaining : intdiv($this->cents($line['lineGross']) * $this->cents($discount), max(1, $grossCents));
            $result[] = $this->money($share);
            $remaining -= $share;
        }

        return $result;
    }

    private function portion(int $saleId, string $total, int $quantity, int $returned, bool $final): string
    {
        if ($final) {
            $allocated = DB::table('return_lines')->where('sale_id', $saleId)->pluck('discount_amount')
                ->reduce(fn (int $sum, string $amount) => $sum + $this->cents($amount), 0);

            return $this->money($this->cents($total) - $allocated);
        }

        return $this->money(intdiv($this->cents($total) * $returned, $quantity));
    }

    private function returnedUnit(StockUnit $source, string $disposition, string $operation): StockUnit
    {
        if (DB::table('stock_unit_lineage')->where('source_unit_id', $source->id)->exists()) {
            throw new LogicException('A physical sale occurrence was already returned or transferred.');
        }
        $successor = new StockUnit;
        $successor->forceFill(['product_id' => $source->product_id, 'unit_no' => StockUnit::where('product_id', $source->product_id)->orderByDesc('unit_no')->lockForUpdate()->value('unit_no') + 1,
            'color' => $source->color, 'condition' => $source->condition, 'pta_status' => $source->pta_status, 'carrier_lock_status' => $source->carrier_lock_status,
            'mdm_status' => $source->mdm_status, 'purchase_price' => $source->purchase_price, 'status' => $disposition === 'sellable' ? 'in_stock' : ($disposition === 'damaged' ? 'damaged' : 'adjusted_out'),
            'notes' => 'Customer return successor of '.$source->unit_code, 'color_master_data_id' => $source->color_master_data_id,
            'condition_master_data_id' => $source->condition_master_data_id, 'pta_status_master_data_id' => $source->pta_status_master_data_id,
            'carrier_lock_master_data_id' => $source->carrier_lock_master_data_id, 'mdm_status_master_data_id' => $source->mdm_status_master_data_id])->save();
        DB::table('stock_unit_lineage')->insert(['source_unit_id' => $source->id, 'successor_unit_id' => $successor->id, 'reason' => 'customer_return', 'operation_id' => $operation, 'created_at' => now()]);
        foreach (DB::table('product_imeis')->where('stock_unit_id', $source->id)->orderBy('slot_no')->get() as $imei) {
            DB::table('product_imeis')->insert(['product_id' => $source->product_id, 'stock_unit_id' => $successor->id, 'device_no' => $successor->unit_no,
                'slot_no' => $imei->slot_no, 'imei' => $imei->imei, 'status' => $disposition === 'sellable' ? 'in_stock' : 'sold', 'created_at' => now(), 'updated_at' => now()]);
            if ($disposition === 'sellable') {
                DB::table('active_imeis')->insert(['imei' => $imei->imei, 'stock_unit_id' => $successor->id, 'slot_no' => $imei->slot_no]);
            }
        }
        foreach (ProductDefinitions::UNIT_OPTIONS as $field => [$list, $raw]) {
            if ($successor->$field) {
                app(PosInventoryMasterData::class)->syncUsage(null, PosMasterDataOption::findOrFail($successor->$field), 'stock_unit', $successor->id, $raw);
            }
        }

        return $successor;
    }

    private function cents(string $money): int
    {
        return (int) str_replace('.', '', SourceRow::money($money));
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected sales fields.']);
        }
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
}
