<?php

namespace App\TradeIn;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Inventory\StockReceiptWriter;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\PosMasterDataOption;
use App\Models\Product;
use App\Models\StockUnit;
use App\Services\PosInventoryMasterData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class TradeInOperations
{
    public function __construct(private StockReceiptWriter $receipts) {}

    public function create(Admin $actor, Outlet $outlet, string $productId, string $key, array $input): array
    {
        $this->fields($input, ['seller_name', 'seller_cnic', 'seller_phone', 'seller_address', 'device_serial', 'imeis', 'condition', 'diagnostics', 'valuation_amount', 'settlement_mode', 'invoice_id']);
        $input = $this->normalizeSeller($input);
        $data = Validator::make($input, [
            'seller_name' => 'required|string|max:255', 'seller_cnic' => ['required', 'regex:/\A\d{5}-\d{7}-\d\z/'],
            'seller_phone' => ['required', 'regex:/\A03\d{9}\z/'], 'seller_address' => 'required|string|max:1000',
            'device_serial' => 'nullable|string|max:100', 'imeis' => 'required|array|min:1|max:2',
            'imeis.*' => ['required', 'string', 'regex:/\A\d{14,16}\z/', 'distinct:strict'],
            'condition' => 'required|string|max:150', 'diagnostics' => 'required|array|min:1|max:30',
            'diagnostics.*' => 'required|string|max:500', 'valuation_amount' => 'required',
            'settlement_mode' => 'required|in:purchase,sale_credit', 'invoice_id' => 'nullable|uuid',
        ])->validate();

        return $this->mutate($actor, $outlet, 'create', $key, [$productId, $input], function (Admin $fresh) use ($outlet, $productId, $data) {
            $product = Product::where('public_id', $productId)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $slots = $product->requiredImeiSlots();
            if (! $product->track_imei || $slots < 1 || count($data['imeis']) !== $slots) {
                throw ValidationException::withMessages(['imeis' => 'Trade-in requires every IMEI slot for a serialized device.']);
            }
            $amount = SourceRow::money((string) $data['valuation_amount']);
            if (bccomp($amount, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['valuation_amount' => 'Trade-in valuation must be positive.']);
            }
            $invoice = $data['settlement_mode'] === 'sale_credit'
                ? $this->creditInvoice($outlet, (string) ($data['invoice_id'] ?? ''), $amount, null, false) : null;
            if ($data['settlement_mode'] === 'purchase' && ! empty($data['invoice_id'])) {
                throw ValidationException::withMessages(['invoice_id' => 'Purchase treatment cannot reference a sale invoice.']);
            }
            $diagnostics = $data['diagnostics'];
            ksort($diagnostics);
            $public = (string) Str::uuid();
            $now = now();
            $tradeId = DB::table('trade_ins')->insertGetId([
                'public_id' => $public, 'outlet_id' => $outlet->id, 'product_id' => $product->id, 'invoice_id' => $invoice?->id,
                'created_by_admin_id' => $fresh->id, 'seller_name' => trim($data['seller_name']), 'seller_cnic' => $data['seller_cnic'],
                'seller_phone' => $data['seller_phone'], 'seller_address' => trim($data['seller_address']),
                'device_serial' => $this->nullable($data['device_serial'] ?? null), 'device_condition' => trim($data['condition']),
                'diagnostics' => json_encode($diagnostics, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'valuation_amount' => $amount, 'settlement_mode' => $data['settlement_mode'], 'status' => 'pending', 'version' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach (array_values($data['imeis']) as $index => $imei) {
                DB::table('trade_in_identifiers')->insert(['trade_in_id' => $tradeId, 'slot_no' => $index + 1,
                    'identifier' => trim($imei), 'created_at' => $now]);
            }
            $this->event($tradeId, 'created', $fresh, ['contract' => 'trade-in-event.v1', 'status' => 'pending',
                'amount' => $amount, 'settlement_mode' => $data['settlement_mode'], 'product_id' => $product->public_id]);
            IdentityAudit::record('admin', $fresh->id, 'trade_in_created', 'trade-in:'.$public, $outlet->id);

            return $this->result($tradeId);
        });
    }

    public function approve(Admin $actor, Outlet $outlet, string $tradeInId, string $key, array $input): array
    {
        $this->fields($input, ['version']);
        Validator::make($input, ['version' => 'required|integer|min:1'])->validate();

        return $this->mutate($actor, $outlet, 'approve', $key, [$tradeInId, $input], function (Admin $fresh) use ($outlet, $tradeInId, $input) {
            $trade = $this->lock($outlet, $tradeInId, (int) $input['version']);
            if ($trade->status !== 'pending') {
                throw new LogicException('Only a pending trade-in can be approved.');
            }
            $invoice = $trade->settlement_mode === 'sale_credit'
                ? $this->creditInvoice($outlet, DB::table('invoices')->where('id', $trade->invoice_id)->value('public_id'), (string) $trade->valuation_amount, $trade->id, true) : null;
            $snapshot = $this->approvalSnapshot($trade, $invoice);
            $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            DB::table('trade_ins')->where('id', $trade->id)->update(['status' => 'approved', 'version' => $trade->version + 1,
                'approved_by_admin_id' => $fresh->id, 'approval_snapshot' => $json, 'approval_sha256' => hash('sha256', $json),
                'approved_at' => now(), 'updated_at' => now()]);
            $this->event($trade->id, 'approved', $fresh, ['contract' => 'trade-in-event.v1', 'status' => 'approved',
                'valuation_amount' => (string) $trade->valuation_amount, 'settlement_mode' => $trade->settlement_mode]);
            IdentityAudit::record('admin', $fresh->id, 'trade_in_approved', 'trade-in:'.$tradeInId, $outlet->id);

            return $this->result($trade->id);
        });
    }

    public function receive(Admin $actor, Outlet $outlet, string $tradeInId, string $key, array $input): array
    {
        $this->fields($input, ['version']);
        Validator::make($input, ['version' => 'required|integer|min:1'])->validate();

        return $this->mutate($actor, $outlet, 'receive', $key, [$tradeInId, $input], function (Admin $fresh) use ($outlet, $tradeInId, $input) {
            $trade = $this->lock($outlet, $tradeInId, (int) $input['version']);
            if ($trade->status !== 'approved') {
                throw new LogicException('Only an approved trade-in can be received.');
            }
            $product = Product::whereKey($trade->product_id)->where('outlet_id', $outlet->id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
            $identifiers = DB::table('trade_in_identifiers')->where('trade_in_id', $trade->id)->whereNull('released_at')
                ->orderBy('slot_no')->lockForUpdate()->get();
            if (! $product->track_imei || $identifiers->count() !== $product->requiredImeiSlots()) {
                throw new LogicException('Trade-in identifier ownership changed.');
            }
            $invoice = $trade->settlement_mode === 'sale_credit'
                ? $this->creditInvoice($outlet, DB::table('invoices')->where('id', $trade->invoice_id)->value('public_id'), (string) $trade->valuation_amount, $trade->id, true) : null;
            $source = PosMasterDataOption::where('list_key', 'acquisition_source_type')->where('code', 'individual_seller')->lockForUpdate()->firstOrFail();
            $source = app(PosInventoryMasterData::class)->resolveSubmission('acquisition_source_type', $source->id);
            $receipt = $this->receipts->receive($product, $source, 1, (string) $trade->valuation_amount, [
                'business_name' => null, 'seller_name' => $trade->seller_name, 'seller_cnic' => '*****-*******-*',
                'seller_phone' => '03*********', 'seller_address' => 'Private trade-in source; see authorized trade-in record.',
            ], 'Trade-in / buyback intake '.$trade->public_id);
            DB::table('acquisition_source_references')->insert(['id' => (string) Str::uuid(), 'acquisition_id' => $receipt['acquisition_id'],
                'kind' => 'trade_in', 'source_line_id' => $trade->public_id, 'created_at' => now()]);
            $unit = StockUnit::where('stock_acquisition_id', $receipt['acquisition_id'])->where('product_id', $product->id)->lockForUpdate()->firstOrFail();
            foreach ($identifiers as $identifier) {
                DB::table('active_imeis')->insert(['imei' => $identifier->identifier, 'stock_unit_id' => $unit->id, 'slot_no' => $identifier->slot_no]);
                DB::table('product_imeis')->insert(['product_id' => $product->id, 'stock_unit_id' => $unit->id, 'device_no' => $unit->unit_no,
                    'slot_no' => $identifier->slot_no, 'imei' => $identifier->identifier, 'status' => 'in_stock', 'created_at' => now(), 'updated_at' => now()]);
            }
            $adjustment = null;
            if ($invoice) {
                $adjustment = app(FinancialReferences::class)->adjustment('invoice', $invoice->id,
                    MoneySnapshot::adjustment((string) Str::uuid(), 'trade_in_credit', (string) $trade->valuation_amount,
                        'Approved trade-in credit', $trade->public_id));
            }
            DB::table('trade_in_identifiers')->where('trade_in_id', $trade->id)->whereNull('released_at')->update(['released_at' => now()]);
            DB::table('trade_ins')->where('id', $trade->id)->update(['status' => 'received', 'version' => $trade->version + 1,
                'acquisition_id' => $receipt['acquisition_id'], 'monetary_adjustment_id' => $adjustment,
                'received_by_admin_id' => $fresh->id, 'received_at' => now(), 'updated_at' => now()]);
            $this->event($trade->id, 'received', $fresh, ['contract' => 'trade-in-event.v1', 'status' => 'received',
                'acquisition_id' => $receipt['acquisition_id'], 'settlement_mode' => $trade->settlement_mode,
                'monetary_adjustment_id' => $adjustment]);
            IdentityAudit::record('admin', $fresh->id, 'trade_in_received', 'trade-in:'.$tradeInId, $outlet->id);

            return $this->result($trade->id);
        });
    }

    public function cancel(Admin $actor, Outlet $outlet, string $tradeInId, string $key, array $input): array
    {
        $this->fields($input, ['version', 'reason']);
        Validator::make($input, ['version' => 'required|integer|min:1', 'reason' => 'required|string|max:500'])->validate();

        return $this->mutate($actor, $outlet, 'cancel', $key, [$tradeInId, $input], function (Admin $fresh) use ($outlet, $tradeInId, $input) {
            $trade = $this->lock($outlet, $tradeInId, (int) $input['version']);
            if (! in_array($trade->status, ['pending', 'approved'], true)) {
                throw new LogicException('Received or cancelled trade-in history cannot be cancelled.');
            }
            DB::table('trade_in_identifiers')->where('trade_in_id', $trade->id)->whereNull('released_at')->update(['released_at' => now()]);
            DB::table('trade_ins')->where('id', $trade->id)->update(['status' => 'cancelled', 'version' => $trade->version + 1,
                'cancel_reason' => trim($input['reason']), 'cancelled_at' => now(), 'updated_at' => now()]);
            $this->event($trade->id, 'cancelled', $fresh, ['contract' => 'trade-in-event.v1', 'status' => 'cancelled',
                'reason' => trim($input['reason'])]);
            IdentityAudit::record('admin', $fresh->id, 'trade_in_cancelled', 'trade-in:'.$tradeInId, $outlet->id);

            return $this->result($trade->id);
        });
    }

    public function get(Admin $actor, Outlet $outlet, string $tradeInId): array
    {
        $this->authorize($actor, $outlet);
        $trade = DB::table('trade_ins')->where('public_id', $tradeInId)->where('outlet_id', $outlet->id)->firstOrFail();

        return $this->result($trade->id);
    }

    private function lock(Outlet $outlet, string $publicId, int $version): object
    {
        $trade = DB::table('trade_ins')->where('public_id', $publicId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
        if ((int) $trade->version !== $version) {
            throw new LogicException('Trade-in version changed.');
        }

        return $trade;
    }

    private function creditInvoice(Outlet $outlet, string $publicId, string $amount, ?int $tradeId, bool $reserve): object
    {
        $invoice = DB::table('invoices')->where('public_id', $publicId)->where('outlet_id', $outlet->id)->lockForUpdate()->firstOrFail();
        if ($invoice->order_id !== null || DB::table('pos_tender_allocations')->where('invoice_id', $invoice->id)->exists()) {
            throw new LogicException('Trade-in sale credit requires an untendered POS invoice.');
        }
        if (bccomp($amount, (string) $invoice->final_bill, 2) > 0) {
            throw new LogicException('Trade-in credit cannot exceed the invoice payable amount.');
        }
        if ($reserve) {
            $reserved = DB::table('trade_ins')->where('invoice_id', $invoice->id)->whereIn('status', ['approved', 'received']);
            if ($tradeId) {
                $reserved->where('id', '<>', $tradeId);
            }
            $sum = $reserved->lockForUpdate()->get()->reduce(fn (string $carry, object $row) => bcadd($carry, (string) $row->valuation_amount, 2), '0.00');
            if (bccomp(bcadd($sum, $amount, 2), (string) $invoice->final_bill, 2) > 0) {
                throw new LogicException('Approved trade-in credits exceed the invoice payable amount.');
            }
        }

        return $invoice;
    }

    private function approvalSnapshot(object $trade, ?object $invoice): array
    {
        return ['contract' => 'trade-in-approval.v1', 'trade_in_id' => $trade->public_id,
            'product_id' => Product::whereKey($trade->product_id)->value('public_id'),
            'seller_name' => $trade->seller_name, 'seller_cnic_sha256' => hash('sha256', $trade->seller_cnic),
            'device_serial' => $trade->device_serial, 'imeis' => DB::table('trade_in_identifiers')->where('trade_in_id', $trade->id)
                ->orderBy('slot_no')->pluck('identifier')->all(), 'condition' => $trade->device_condition,
            'diagnostics' => json_decode($trade->diagnostics, true, flags: JSON_THROW_ON_ERROR),
            'valuation_amount' => (string) $trade->valuation_amount, 'currency' => 'PKR',
            'settlement_mode' => $trade->settlement_mode, 'invoice_id' => $invoice?->public_id];
    }

    private function result(int $id): array
    {
        $row = DB::table('trade_ins')->where('id', $id)->firstOrFail();

        return ['trade_in_id' => $row->public_id, 'status' => $row->status, 'version' => (int) $row->version,
            'product_id' => Product::whereKey($row->product_id)->value('public_id'), 'settlement_mode' => $row->settlement_mode,
            'valuation_amount' => (string) $row->valuation_amount, 'invoice_id' => $row->invoice_id ? DB::table('invoices')->where('id', $row->invoice_id)->value('public_id') : null,
            'acquisition_id' => $row->acquisition_id, 'monetary_adjustment_id' => $row->monetary_adjustment_id,
            'device_serial' => $row->device_serial, 'condition' => $row->device_condition,
            'imeis' => DB::table('trade_in_identifiers')->where('trade_in_id', $id)->orderBy('slot_no')->pluck('identifier')->all(),
            'seller' => ['name' => $row->seller_name, 'cnic' => '*****-*******-'.substr($row->seller_cnic, -1),
                'phone' => substr($row->seller_phone, 0, 4).'*****'.substr($row->seller_phone, -2)],
            'cancel_reason' => $row->cancel_reason];
    }

    private function event(int $tradeId, string $type, Admin $actor, array $snapshot): void
    {
        $sequence = (int) DB::table('trade_in_events')->where('trade_in_id', $tradeId)->lockForUpdate()->max('sequence') + 1;
        $json = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        DB::table('trade_in_events')->insert(['trade_in_id' => $tradeId, 'sequence' => $sequence, 'event_type' => $type,
            'actor_admin_id' => $actor->id, 'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'created_at' => now()]);
    }

    private function mutate(Admin $actor, Outlet $outlet, string $operation, string $key, mixed $payload, callable $callback): array
    {
        return DB::transaction(function () use ($actor, $outlet, $operation, $key, $payload, $callback) {
            // Serialize every mutation and completed replay with outlet archival.
            $lockedOutlet = Outlet::whereKey($outlet->id)->lockForUpdate()->firstOrFail();
            $fresh = $actor->fresh();
            abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.trade-in', $lockedOutlet), 403);
            Validator::make(['key' => $key], ['key' => 'required|string|max:100'])->validate();
            $digest = hash('sha256', json_encode($this->canonical($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $identity = ['actor_scope' => Admin::class.':'.$fresh->id, 'operation' => 'trade-in.'.$operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $digest, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $digest)) {
                throw new LogicException('Idempotency key was already used for a different trade-in request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $response = $callback($fresh);
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                'response' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), 'resource_type' => 'trade_in', 'updated_at' => now()]);

            return $response;
        }, 3);
    }

    private function authorize(Admin $actor, Outlet $outlet): void
    {
        $fresh = $actor->fresh();
        abort_unless($fresh instanceof Admin && app(Access::class)->allows($fresh, 'shop.trade-in', $outlet->fresh()), 403);
    }

    private function normalizeSeller(array $input): array
    {
        $phone = preg_replace('/\D+/', '', (string) ($input['seller_phone'] ?? ''));
        $cnic = preg_replace('/\D+/', '', (string) ($input['seller_cnic'] ?? ''));
        $input['seller_phone'] = $phone;
        if (strlen($cnic) === 13) {
            $input['seller_cnic'] = substr($cnic, 0, 5).'-'.substr($cnic, 5, 7).'-'.substr($cnic, 12);
        }
        if (isset($input['imeis']) && is_array($input['imeis'])) {
            $input['imeis'] = array_map(fn ($value) => trim((string) $value), array_values($input['imeis']));
        }

        return $input;
    }

    private function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->canonical($item), $value);
        }
        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonical($item);
        }

        return $value;
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected trade-in fields; valuation, settlement and stock identities are server controlled.']);
        }
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
