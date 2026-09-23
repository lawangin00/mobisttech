<?php

namespace App\Commerce;

use App\Addendum\WebsiteCapabilities;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Inventory\StockLedger;
use App\Inventory\TransactionalStock;
use App\Loyalty\LoyaltyServices;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\CustomerAccount;
use App\Models\Outlet;
use App\Models\Product;
use App\Promotions\PromotionServices;
use App\Sales\SalesOperations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Single authority for Website order, reservation, collection and refund transactions. */
final class OrderTransactions
{
    public function __construct(
        private WebsiteCapabilities $capabilities,
        private PaymentProviders $providers,
        private TransactionalStock $stock,
        private StockLedger $ledger,
        private SalesOperations $sales,
        private PromotionServices $promotions,
        private LoyaltyServices $loyalty,
    ) {}

    public function checkout(string $ownerScope, ?CustomerAccount $customer, string $key, array $input): array
    {
        $this->owner($ownerScope, $customer);
        $this->fields($input, ['customer_name', 'customer_mobile', 'customer_email', 'city', 'delivery_address', 'notes', 'gateway', 'coupon_codes', 'loyalty_points', 'lines']);
        $data = Validator::make($input, [
            'customer_name' => 'required|string|max:255', 'customer_mobile' => 'required|string|max:40',
            'customer_email' => 'nullable|email|max:255', 'city' => 'nullable|string|max:255', 'delivery_address' => 'nullable|string|max:2000',
            'notes' => 'nullable|string|max:2000', 'gateway' => 'required|in:cod,jazzcash,easypaisa,card',
            'coupon_codes' => 'sometimes|array|max:10', 'coupon_codes.*' => 'string|max:80',
            'loyalty_points' => 'sometimes|integer|min:0|max:1000000000',
            'lines' => 'required|array|min:1|max:100', 'lines.*.product_id' => 'required|uuid',
            'lines.*.quantity' => 'required|integer|min:1|max:10000', 'lines.*.variant_key' => 'sometimes|string|max:100',
        ])->validate();
        $provider = $this->providers->assertAvailable($data['gateway']);

        return $this->idempotent($ownerScope, 'commerce.checkout', $key, $input, function () use ($ownerScope, $customer, $data, $provider, $key) {
            $mode = $this->capabilities->assertCreationAllowed('checkout.create');
            $lines = collect($data['lines'])->sortBy('product_id')->values();
            if ($lines->pluck('product_id')->duplicates()->isNotEmpty()) {
                throw ValidationException::withMessages(['lines' => 'Each product may appear once per checkout.']);
            }
            $prepared = [];
            $total = '0.00';
            $outletModel = $this->lockActivePhysicalOutlet($data['lines']);
            $outlet = $outletModel->id;
            foreach ($lines as $line) {
                $product = Product::where('public_id', $line['product_id'])->where('isDeleted', false)->lockForUpdate()->firstOrFail();
                if ($outlet !== $product->outlet_id) {
                    throw ValidationException::withMessages(['lines' => 'A physical checkout must use one outlet.']);
                }
                $variant = $line['variant_key'] ?? 'standard';
                $this->ledger->select($product, (int) $line['quantity'], $variant);
                $unit = SourceRow::money((string) $product->sale_price);
                $lineTotal = bcmul($unit, (string) $line['quantity'], 2);
                $total = bcadd($total, $lineTotal, 2);
                $prepared[] = compact('product', 'variant', 'unit', 'lineTotal') + ['quantity' => (int) $line['quantity']];
            }
            $customerId = $customer ? DB::table('customers')->where('website_user_id', $customer->id)->value('id') : null;
            $loyaltyPoints = (int) ($data['loyalty_points'] ?? 0);
            if ($loyaltyPoints > 0 && ! empty($data['coupon_codes'])) {
                throw ValidationException::withMessages(['loyalty_points' => 'Loyalty redemption cannot be combined with coupon promotions.']);
            }
            if ($loyaltyPoints > 0 && ! $customerId) {
                throw ValidationException::withMessages(['loyalty_points' => 'Loyalty redemption requires a linked operational customer.']);
            }
            $promotion = ['discount' => '0.00', 'allocations' => array_fill_keys(array_keys($prepared), '0.00'), 'applications' => []];
            $loyalty = ['points' => 0, 'discount' => '0.00', 'allocations' => array_fill_keys(array_keys($prepared), '0.00'), 'applications' => []];
            if ($loyaltyPoints === 0) {
                $promotionLines = array_map(fn ($line) => ['product_id' => $line['product']->id, 'product_public_id' => $line['product']->public_id,
                    'category' => $line['product']->category, 'quantity' => $line['quantity'], 'unit_price' => $line['unit'], 'gross' => $line['lineTotal']], $prepared);
                $promotion = $this->promotions->claim('website', $outletModel, hash('sha256', $ownerScope.'|'.$key),
                    $customer ? hash('sha256', 'account:'.$customer->id) : null, $promotionLines, $data['coupon_codes'] ?? []);
            } else {
                $loyalty = $this->loyalty->claim('website', (int) $customerId, hash('sha256', $ownerScope.'|'.$key),
                    $loyaltyPoints, $total, array_column($prepared, 'lineTotal'));
            }
            $gross = $total;
            $discountAmount = $loyaltyPoints > 0 ? $loyalty['discount'] : $promotion['discount'];
            $allocations = $loyaltyPoints > 0 ? $loyalty['allocations'] : $promotion['allocations'];
            $total = bcsub($gross, $discountAmount, 2);
            $number = 'WEB-'.now()->format('Ymd').'-'.strtoupper(Str::random(12));
            $orderId = DB::table('orders')->insertGetId([
                'order_number' => $number, 'order_type' => 'commerce', 'status' => 'pending', 'fulfillment_status' => 'pending',
                'customer_name' => trim($data['customer_name']), 'customer_mobile' => trim($data['customer_mobile']),
                'customer_email' => isset($data['customer_email']) ? mb_strtolower(trim($data['customer_email'])) : null,
                'city' => $data['city'] ?? null, 'delivery_address' => $data['delivery_address'] ?? null, 'notes' => $data['notes'] ?? null,
                'subtotal' => $gross, 'total' => $total, 'currency' => 'PKR', 'payment_status' => 'unpaid',
                'user_id' => $customer?->id, 'customer_id' => $customerId,
                'owner_scope_hash' => hash('sha256', $ownerScope), 'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($loyalty['applications']) {
                $this->loyalty->bind('order', $orderId, $loyalty['applications']);
            } elseif ($promotion['applications']) {
                $this->promotions->bind('order', $orderId, $promotion['applications']);
            }
            $reservationId = DB::table('reservations')->insertGetId([
                'order_id' => $orderId, 'attempt' => 1, 'outlet_id' => $outlet, 'website_order_number' => $number,
                'reservation_reference' => (string) Str::uuid(), 'state' => $data['gateway'] === 'cod' ? 'held_cod' : 'active',
                'currency' => 'PKR', 'customer_name' => trim($data['customer_name']), 'customer_mobile' => trim($data['customer_mobile']),
                'total_amount' => $total, 'reservation_expires_at' => $data['gateway'] === 'cod' ? null : now()->addMinutes(config('commerce.reservation_minutes')),
                'gateway' => $data['gateway'], 'contract_hash' => hash('sha256', json_encode(['mode' => $mode, 'subtotal' => $gross, 'discount' => $discountAmount, 'discount_kind' => $loyaltyPoints > 0 ? 'loyalty' : 'promotion', 'total' => $total, 'currency' => 'PKR'], JSON_THROW_ON_ERROR)),
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($prepared as $index => $line) {
                $lineDiscount = $allocations[$index] ?? '0.00';
                $netLine = bcsub($line['lineTotal'], $lineDiscount, 2);
                $itemId = DB::table('order_items')->insertGetId([
                    'order_id' => $orderId, 'item_type' => 'product', 'title' => $line['product']->name,
                    'quantity' => $line['quantity'], 'unit_price' => $line['unit'], 'line_total' => $netLine,
                    'product_id' => $line['product']->id, 'outlet_id' => $outlet, 'outlet_external_id' => (string) $outlet,
                    'pos_variant_key' => $line['variant'], 'external_reference' => $line['product']->public_id,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $reservationLine = DB::table('reservation_lines')->insertGetId([
                    'reservation_id' => $reservationId, 'order_item_id' => $itemId, 'product_id' => $line['product']->id, 'outlet_id' => $outlet,
                    'website_order_item_id' => (string) $itemId, 'product_external_id' => $line['product']->public_id,
                    'outlet_external_id' => (string) $outlet, 'variant_key' => $line['variant'], 'quantity' => $line['quantity'],
                    'unit_price' => $line['unit'], 'line_total' => $netLine,
                    'source_snapshot' => json_encode(['contract' => 'website-order-line.v2', 'product_id' => $line['product']->public_id,
                        'product_code' => $line['product']->product_code, 'title' => $line['product']->name, 'unit_price' => $line['unit'],
                        'quantity' => $line['quantity'], 'variant_key' => $line['variant'], 'gross_line_total' => $line['lineTotal'],
                        'discount_amount' => $lineDiscount, 'net_line_total' => $netLine], JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $this->stock->reserve($reservationLine);
            }
            $paymentPublic = (string) Str::uuid();
            $intent = ['order_id' => $orderId, 'order_number' => $number, 'payment_id' => $paymentPublic, 'gateway' => $data['gateway'],
                'merchant' => $provider['merchant'], 'mode' => $provider['mode'], 'amount' => $total, 'currency' => 'PKR'];
            $paymentId = DB::table('payments')->insertGetId([
                'order_id' => $orderId, 'gateway' => $data['gateway'], 'status' => $data['gateway'] === 'cod' ? 'pending_collection' : 'pending',
                'amount' => $total, 'currency' => 'PKR', 'public_id' => $paymentPublic, 'merchant' => $provider['merchant'], 'mode' => $provider['mode'],
                'attempt_key' => 'checkout-1', 'intent_hash' => $this->digest($intent), 'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('reservations')->where('id', $reservationId)->update(['website_payment_id' => (string) $paymentId]);
            IdentityAudit::record($customer ? 'customer' : 'guest', $customer?->id, 'website_order_created', 'order:'.$number);

            return ['order_id' => DB::table('orders')->where('id', $orderId)->value('public_id'), 'order_number' => $number,
                'reservation_id' => DB::table('reservations')->where('id', $reservationId)->value('reservation_reference'),
                'payment_id' => $paymentPublic, 'payment_status' => $data['gateway'] === 'cod' ? 'pending_collection' : 'pending',
                'amount' => $total, 'currency' => 'PKR', 'promotion_claim_ids' => array_column($promotion['applications'], 'claim_id'),
                'loyalty_claim_ids' => array_column($loyalty['applications'], 'claim_id')];
        }, fn () => $this->lockActivePhysicalOutlet($data['lines']));
    }

    public function milestone(CustomerAccount $customer, string $key, array $input): array
    {
        $this->fields($input, ['milestone_id', 'gateway']);
        $data = Validator::make($input, ['milestone_id' => 'required|uuid', 'gateway' => 'required|in:jazzcash,easypaisa,card'])->validate();
        $provider = $this->providers->assertAvailable($data['gateway']);
        $scope = $customer::class.':'.$customer->id;

        return $this->idempotent($scope, 'commerce.milestone', $key, $input, function () use ($scope, $customer, $data, $provider) {
            $milestone = DB::table('project_milestone_identities')->where('id', $data['milestone_id'])->lockForUpdate()->firstOrFail();
            $quote = DB::table('project_quotes')->where('id', $milestone->quote_id)->lockForUpdate()->firstOrFail();
            $projectOwnerId = DB::table('project_proposals as pp')->join('client_projects as cp', 'cp.id', '=', 'pp.client_project_id')
                ->where('pp.quote_id', $quote->id)->value('cp.customer_account_id');
            $ownerMatches = $projectOwnerId !== null
                ? (int) $projectOwnerId === (int) $customer->id
                : (($quote->customer_email && hash_equals(mb_strtolower($quote->customer_email), mb_strtolower($customer->email)))
                    || ($quote->customer_mobile && $customer->mobile && hash_equals($quote->customer_mobile, $customer->mobile)));
            if ($milestone->paid_payment_id || $quote->status !== 'approved' || ($quote->expires_at && now()->gte($quote->expires_at)) || ! $ownerMatches) {
                throw new LogicException('Milestone ownership, state or validity check failed.');
            }
            $number = 'PRJ-'.now()->format('Ymd').'-'.strtoupper(Str::random(12));
            $orderId = DB::table('orders')->insertGetId(['order_number' => $number, 'order_type' => 'digital', 'status' => 'pending',
                'customer_name' => $customer->name, 'customer_mobile' => $customer->mobile ?? $quote->customer_mobile,
                'customer_email' => $customer->email, 'project_reference' => $quote->reference, 'subtotal' => $milestone->approved_amount,
                'total' => $milestone->approved_amount, 'currency' => 'PKR', 'payment_status' => 'unpaid', 'user_id' => $customer->id,
                'owner_scope_hash' => hash('sha256', $scope), 'public_id' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
            $item = DB::table('order_items')->insertGetId(['order_id' => $orderId, 'item_type' => 'project_milestone', 'title' => $quote->title,
                'quantity' => 1, 'unit_price' => $milestone->approved_amount, 'line_total' => $milestone->approved_amount,
                'project_quote_id' => $quote->id, 'external_reference' => $milestone->id, 'created_at' => now(), 'updated_at' => now()]);
            DB::table('order_item_milestones')->insert(['order_item_id' => $item, 'milestone_id' => $milestone->id]);
            $public = (string) Str::uuid();
            $intent = ['order_id' => $orderId, 'order_number' => $number, 'payment_id' => $public, 'gateway' => $data['gateway'],
                'merchant' => $provider['merchant'], 'mode' => $provider['mode'], 'amount' => $milestone->approved_amount, 'currency' => 'PKR'];
            DB::table('payments')->insert(['order_id' => $orderId, 'gateway' => $data['gateway'], 'status' => 'pending',
                'amount' => $milestone->approved_amount, 'currency' => 'PKR', 'public_id' => $public, 'merchant' => $provider['merchant'],
                'mode' => $provider['mode'], 'attempt_key' => 'milestone-1', 'intent_hash' => $this->digest($intent),
                'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

            return ['order_id' => DB::table('orders')->where('id', $orderId)->value('public_id'), 'order_number' => $number,
                'payment_id' => $public, 'payment_status' => 'pending', 'amount' => $milestone->approved_amount, 'currency' => 'PKR'];
        });
    }

    /** Provider network call occurs outside a database transaction; its durable intent already exists. */
    public function initiate(string $paymentPublicId): array
    {
        DB::transaction(fn () => $this->lockActivePaymentOutlet($paymentPublicId), 3);
        $payment = DB::table('payments')->where('public_id', $paymentPublicId)->firstOrFail();
        $order = DB::table('orders')->where('id', $payment->order_id)->firstOrFail();
        if ($payment->status !== 'pending' || $payment->gateway === 'cod'
            || $order->status !== 'pending' || $order->payment_status !== 'unpaid') {
            throw new LogicException('Payment is not externally initiable.');
        }
        // An elapsed physical reservation must not issue or reissue a hosted checkout
        // while the independent expiration scheduler has not yet released its stock.
        if ($order->order_type === 'commerce') {
            $reservation = DB::table('reservations')->where('website_payment_id', $payment->id)->first();
            if (! $reservation || $reservation->state !== 'active' || ! $reservation->reservation_expires_at
                || now()->gte($reservation->reservation_expires_at)) {
                throw new LogicException('Payment reservation is no longer active.');
            }
        }
        // A cached hosted URL is a payment continuation, not a bypass of the emergency
        // provider-disable gate or the merchant/environment bound to the original intent.
        $configured = $this->providers->assertAvailable($payment->gateway);
        if ($payment->merchant !== $configured['merchant'] || $payment->mode !== $configured['mode']) {
            throw new LogicException('Payment provider configuration no longer matches the original intent.');
        }
        if ($payment->gateway_order_reference) {
            return $this->safeContinuation($this->sanitize(json_decode($payment->gateway_response ?? '{}', true, flags: JSON_THROW_ON_ERROR)), $payment->gateway_order_reference);
        }
        $intent = ['order_number' => $order->order_number, 'payment_id' => $payment->public_id, 'amount' => $payment->amount, 'currency' => $payment->currency];
        $result = $this->providers->initiate($payment->gateway, $intent);
        if (! isset($result['reference']) || ! is_string($result['reference']) || $result['reference'] === '') {
            throw new LogicException('Provider did not return an initiation reference.');
        }
        // The provider may finish after another request cancels/expires the order. Record
        // its reference for a later verified receipt, but never return a stale hosted URL.
        $continuation = DB::transaction(function () use ($payment, $result) {
            $order = DB::table('orders')->where('id', $payment->order_id)->lockForUpdate()->firstOrFail();
            $locked = DB::table('payments')->where('id', $payment->id)->lockForUpdate()->firstOrFail();
            if ($locked->gateway_order_reference && ! hash_equals($locked->gateway_order_reference, $result['reference'])) {
                throw new LogicException('Payment initiation replay changed its provider reference.');
            }
            // A vendor reference must never resolve to another payment within the
            // same gateway or shadow another local public ID.
            // Otherwise a verified callback could settle an unrelated order.
            $collision = DB::table('payments')->where('id', '!=', $locked->id)
                ->where('gateway', $locked->gateway)
                ->where(fn ($query) => $query->where('gateway_order_reference', $result['reference'])
                    ->orWhere('public_id', $result['reference']))->exists();
            if ($collision) {
                throw new LogicException('Provider reference belongs to another payment.');
            }
            if (! $locked->gateway_order_reference) {
                DB::table('payments')->where('id', $locked->id)->update(['gateway_order_reference' => $result['reference'],
                    'gateway_response' => json_encode($this->sanitize($result), JSON_THROW_ON_ERROR), 'updated_at' => now()]);
            }
            $active = $locked->status === 'pending' && $order->status === 'pending' && $order->payment_status === 'unpaid';
            if ($order->order_type === 'commerce') {
                $reservation = DB::table('reservations')->where('website_payment_id', $payment->id)->lockForUpdate()->first();
                $active = $active && $reservation && $reservation->state === 'active'
                    && $reservation->reservation_expires_at && now()->lt($reservation->reservation_expires_at);
            }

            return ['active' => (bool) $active,
                'response' => $locked->gateway_order_reference
                    ? $this->sanitize(json_decode($locked->gateway_response ?? '{}', true, flags: JSON_THROW_ON_ERROR))
                    : $this->sanitize($result)];
        }, 3);
        // Recheck the provider after the network returns: an emergency disable or
        // merchant/environment rotation must not leak a fresh hosted redirect.
        // The reference has already committed and remains available for reconciliation.
        $currentProvider = $this->providers->assertAvailable($payment->gateway);
        if ($payment->merchant !== $currentProvider['merchant'] || $payment->mode !== $currentProvider['mode']) {
            throw new LogicException('Payment provider configuration changed during initiation.');
        }
        if (! $continuation['active']) {
            throw new LogicException('Payment is no longer externally initiable; provider reference retained for reconciliation.');
        }

        return $this->safeContinuation($continuation['response'], $result['reference']);
    }

    public function retry(string $ownerScope, ?CustomerAccount $customer, string $orderPublicId, string $key, string $gateway): array
    {
        $this->owner($ownerScope, $customer);
        $provider = $this->providers->assertAvailable($gateway);

        return $this->idempotent($ownerScope, 'commerce.payment.retry', $key, compact('orderPublicId', 'gateway'), function () use ($ownerScope, $orderPublicId, $gateway, $provider) {
            $this->capabilities->assertCreationAllowed('checkout.create');
            $this->lockActiveOrderOutlet($ownerScope, $orderPublicId);
            $order = DB::table('orders')->where('public_id', $orderPublicId)->where('owner_scope_hash', hash('sha256', $ownerScope))->lockForUpdate()->firstOrFail();
            if ($order->order_type !== 'commerce' || $order->payment_status !== 'failed') {
                throw new LogicException('Only a definitively failed commerce payment may be retried.');
            }
            $lastPayment = DB::table('payments')->where('order_id', $order->id)->orderByDesc('id')->lockForUpdate()->firstOrFail();
            if ($lastPayment->status !== 'failed') {
                throw new LogicException('Unknown or collected payment results require reconciliation, not a new charge.');
            }
            $prior = DB::table('reservations')->where('order_id', $order->id)->orderByDesc('attempt')->lockForUpdate()->firstOrFail();
            if (! in_array($prior->state, ['released', 'expired'], true)) {
                throw new LogicException('Previous reservation must be closed before retry.');
            }
            $attempt = (int) $prior->attempt + 1;
            $reservationId = DB::table('reservations')->insertGetId(['order_id' => $order->id, 'attempt' => $attempt, 'outlet_id' => $prior->outlet_id,
                'website_order_number' => $order->order_number, 'reservation_reference' => (string) Str::uuid(), 'state' => 'active', 'currency' => 'PKR',
                'customer_name' => $order->customer_name, 'customer_mobile' => $order->customer_mobile, 'total_amount' => $order->total,
                'reservation_expires_at' => now()->addMinutes(config('commerce.reservation_minutes')), 'gateway' => $gateway,
                'contract_hash' => $prior->contract_hash, 'created_at' => now(), 'updated_at' => now()]);
            foreach (DB::table('order_items')->where('order_id', $order->id)->orderBy('product_id')->orderBy('id')->lockForUpdate()->get() as $item) {
                $product = Product::whereKey($item->product_id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
                $priorLine = DB::table('reservation_lines')->where('reservation_id', $prior->id)->where('order_item_id', $item->id)->lockForUpdate()->firstOrFail();
                if ($product->outlet_id !== $prior->outlet_id || bccomp(SourceRow::money((string) $product->sale_price), $item->unit_price, 2) !== 0) {
                    throw new LogicException('Retry requires the same active product price and outlet.');
                }
                $lineId = DB::table('reservation_lines')->insertGetId(['reservation_id' => $reservationId, 'order_item_id' => $item->id,
                    'product_id' => $product->id, 'outlet_id' => $product->outlet_id, 'website_order_item_id' => (string) $item->id,
                    'product_external_id' => $product->public_id, 'outlet_external_id' => (string) $product->outlet_id,
                    'variant_key' => $item->pos_variant_key ?? 'standard', 'quantity' => $item->quantity, 'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total, 'source_snapshot' => $priorLine->source_snapshot,
                    'created_at' => now(), 'updated_at' => now()]);
                $this->stock->reserve($lineId);
            }
            $public = (string) Str::uuid();
            $intent = ['order_id' => $order->id, 'order_number' => $order->order_number, 'payment_id' => $public, 'gateway' => $gateway,
                'merchant' => $provider['merchant'], 'mode' => $provider['mode'], 'amount' => $order->total, 'currency' => 'PKR'];
            $paymentId = DB::table('payments')->insertGetId(['order_id' => $order->id, 'gateway' => $gateway, 'status' => 'pending',
                'amount' => $order->total, 'currency' => 'PKR', 'public_id' => $public, 'merchant' => $provider['merchant'], 'mode' => $provider['mode'],
                'attempt_key' => 'retry-'.$attempt, 'intent_hash' => $this->digest($intent), 'initiated_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('reservations')->where('id', $reservationId)->update(['website_payment_id' => (string) $paymentId]);
            DB::table('orders')->where('id', $order->id)->update(['status' => 'pending', 'fulfillment_status' => 'pending', 'payment_status' => 'unpaid',
                'cancelled_at' => null, 'version' => $order->version + 1, 'updated_at' => now()]);

            return ['order_id' => $order->public_id, 'payment_id' => $public, 'payment_status' => 'pending',
                'reservation_id' => DB::table('reservations')->where('id', $reservationId)->value('reservation_reference'),
                'amount' => $order->total, 'currency' => 'PKR'];
        }, fn () => $this->lockActiveOrderOutlet($ownerScope, $orderPublicId));
    }

    public function callback(string $gateway, array $payload): array
    {
        $event = $this->providers->verify($gateway, $payload);

        return DB::transaction(fn () => $this->applyReceipt($gateway, $event), 3);
    }

    public function collectCod(Admin $actor, Outlet $outlet, string $orderPublicId, string $key, string $amount, string $receiptReference): array
    {
        return $this->idempotent($actor::class.':'.$actor->id, 'commerce.cod.collect', $key,
            compact('orderPublicId', 'amount', 'receiptReference'), function () use ($actor, $outlet, $orderPublicId, $key, $amount, $receiptReference) {
                $lockedOutlet = $this->lockAuthorizedOutlet($actor, $outlet->id, 'shop.sales');
                $order = DB::table('orders')->where('public_id', $orderPublicId)->lockForUpdate()->firstOrFail();
                $reservation = DB::table('reservations')->where('order_id', $order->id)->where('state', 'held_cod')->lockForUpdate()->firstOrFail();
                if ($reservation->outlet_id !== $lockedOutlet->id) {
                    throw new LogicException('COD collection outlet mismatch.');
                }
                $payment = DB::table('payments')->where('order_id', $order->id)->where('gateway', 'cod')->lockForUpdate()->firstOrFail();
                $event = ['event_id' => 'cod:'.$key, 'transaction_reference' => $receiptReference, 'order_reference' => $payment->public_id,
                    'amount' => SourceRow::money($amount), 'currency' => 'PKR', 'status' => 'paid',
                    'payload_hash' => hash('sha256', $actor->id.'|'.$lockedOutlet->id.'|'.$receiptReference.'|'.$amount),
                    'merchant' => $payment->merchant, 'mode' => $payment->mode];
                $result = $this->applyReceipt('cod', $event);
                IdentityAudit::record('admin', $actor->id, 'cod_collected', 'order:'.$order->order_number, $lockedOutlet->id);

                return $result;
            }, fn () => $this->lockAuthorizedOutlet($actor, $outlet->id, 'shop.sales'));
    }

    public function cancel(string $ownerScope, ?CustomerAccount $customer, string $orderPublicId, string $key): array
    {
        $this->owner($ownerScope, $customer);

        return $this->idempotent($ownerScope, 'commerce.cancel', $key, ['order_id' => $orderPublicId], function () use ($ownerScope, $orderPublicId) {
            $this->lockActiveOrderOutlet($ownerScope, $orderPublicId);
            $order = DB::table('orders')->where('public_id', $orderPublicId)->where('owner_scope_hash', hash('sha256', $ownerScope))->lockForUpdate()->firstOrFail();
            if (in_array($order->payment_status, ['paid', 'paid_reconciliation'], true)) {
                throw new LogicException('A collected order requires explicit refund/reconciliation.');
            }
            $reservation = DB::table('reservations')->where('order_id', $order->id)->lockForUpdate()->first();
            if ($reservation) {
                $this->stock->release($reservation->id);
            }
            $this->promotions->releaseOrder($order->id, 'customer_cancelled');
            $this->loyalty->releaseOrder($order->id, 'customer_cancelled');
            DB::table('orders')->where('id', $order->id)->update(['status' => 'cancelled', 'fulfillment_status' => 'cancelled',
                'cancelled_at' => now(), 'version' => $order->version + 1, 'updated_at' => now()]);

            return ['order_id' => $orderPublicId, 'status' => 'cancelled'];
        }, fn () => $this->lockActiveOrderOutlet($ownerScope, $orderPublicId));
    }

    public function expireDue(): int
    {
        $ids = DB::table('reservations')->where('state', 'active')->where('reservation_expires_at', '<=', now())->orderBy('id')->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id) {
                $this->lockActiveReservationOutlet((int) $id);
                $reservation = DB::table('reservations')->where('id', $id)->lockForUpdate()->firstOrFail();
                $this->stock->release($id, true);
                $order = DB::table('orders')->where('id', $reservation->order_id)->lockForUpdate()->firstOrFail();
                DB::table('payments')->where('id', $reservation->website_payment_id)->whereNotIn('status', ['paid', 'paid_reconciliation'])
                    ->update(['status' => 'expired', 'failure_code' => 'reservation_expired', 'updated_at' => now()]);
                $this->promotions->releaseOrder($order->id, 'reservation_expired');
                $this->loyalty->releaseOrder($order->id, 'reservation_expired');
                DB::table('orders')->where('id', $order->id)->whereNotIn('payment_status', ['paid', 'paid_reconciliation'])
                    ->update(['status' => 'cancelled', 'fulfillment_status' => 'cancelled', 'payment_status' => 'expired',
                        'cancelled_at' => now(), 'version' => $order->version + 1, 'updated_at' => now()]);
            }, 3);
        }

        return $ids->count();
    }

    public function manualRefund(Admin $actor, string $returnPublicId, string $paymentPublicId, string $key, string $amount, string $evidenceHash): array
    {
        $input = compact('returnPublicId', 'paymentPublicId', 'amount', 'evidenceHash');

        return $this->idempotent($actor::class.':'.$actor->id, 'commerce.refund', $key, $input, function () use ($actor, $returnPublicId, $paymentPublicId, $key, $amount, $evidenceHash) {
            if (! preg_match('/\A[0-9a-f]{64}\z/', $evidenceHash)) {
                throw ValidationException::withMessages(['evidence' => 'A verified SHA-256 evidence digest is required.']);
            }
            $outlet = $this->lockActiveReturnOutlet($returnPublicId);
            abort_unless(app(Access::class)->allows($actor->fresh(), 'shop.sales', $outlet), 403);
            $refundAmount = SourceRow::money($amount);
            $return = DB::table('returns')->where('public_id', $returnPublicId)->lockForUpdate()->firstOrFail();
            $invoice = DB::table('invoices')->where('id', $return->invoice_id)->lockForUpdate()->firstOrFail();
            abort_unless($invoice->outlet_id === $outlet->id, 404);
            $payment = DB::table('payments')->where('public_id', $paymentPublicId)->where('order_id', $return->order_id)->lockForUpdate()->firstOrFail();
            if (! in_array($payment->status, ['paid', 'paid_reconciliation'], true)) {
                throw new LogicException('Refund requires verified collected payment.');
            }
            $due = DB::table('return_lines')->join('returns', 'returns.id', '=', 'return_lines.return_id')->where('returns.order_id', $return->order_id)
                ->orderBy('return_lines.id')->lockForUpdate()->pluck('return_lines.net_amount')
                ->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            $reserved = DB::table('refunds')->where('payment_id', $payment->id)->whereIn('status', ['pending', 'unknown', 'completed'])
                ->orderBy('id')->lockForUpdate()->pluck('amount')->reduce(fn (string $sum, string $value) => bcadd($sum, $value, 2), '0.00');
            if (bccomp($refundAmount, '0.00', 2) <= 0 || bccomp(bcadd($reserved, $refundAmount, 2), $payment->amount, 2) > 0
                || bccomp(bcadd($reserved, $refundAmount, 2), $due, 2) > 0) {
                throw new LogicException('Refund exceeds collected or accepted-return balance.');
            }
            $public = (string) Str::uuid();
            DB::table('refunds')->insert(['payment_id' => $payment->id, 'return_id' => $return->id, 'amount' => $refundAmount,
                'currency' => 'PKR', 'status' => 'completed', 'operation_key' => $key, 'provider' => 'manual_verified',
                'evidence_hash' => $evidenceHash, 'completed_at' => now(), 'public_id' => $public, 'created_at' => now(), 'updated_at' => now()]);
            $refunded = bcadd($reserved, $refundAmount, 2);
            DB::table('orders')->where('id', $return->order_id)->update(['refund_status' => bccomp($refunded, $payment->amount, 2) === 0 ? 'refunded' : 'partial',
                'refunded_at' => bccomp($refunded, $payment->amount, 2) === 0 ? now() : null, 'updated_at' => now()]);
            IdentityAudit::record('admin', $actor->id, 'manual_refund_verified', 'refund:'.$public, $outlet->id);

            return ['refund_id' => $public, 'status' => 'completed', 'amount' => $refundAmount, 'currency' => 'PKR'];
        }, fn () => $this->lockAuthorizedReturnOutlet($actor, $returnPublicId));
    }

    private function applyReceipt(string $gateway, array $event): array
    {
        $receiptOutlet = $this->lockReceiptOutlet($gateway, $event);
        $payment = DB::table('payments')->where('gateway', $gateway)
            ->where(fn ($q) => $q->where('gateway_order_reference', $event['order_reference'])->orWhere('public_id', $event['order_reference']))
            ->lockForUpdate()->firstOrFail();
        $order = DB::table('orders')->where('id', $payment->order_id)->lockForUpdate()->firstOrFail();
        if ($payment->merchant !== $event['merchant'] || $payment->mode !== $event['mode'] || $payment->currency !== $event['currency']
            || bccomp($payment->amount, SourceRow::money($event['amount']), 2) !== 0) {
            throw new LogicException('Verified receipt does not match its payment intent.');
        }
        $existing = DB::table('payment_receipts')->where(['gateway' => $gateway, 'merchant' => $event['merchant'],
            'mode' => $event['mode'], 'event_id' => $event['event_id']])->lockForUpdate()->first();
        if ($existing) {
            if ($existing->payment_id !== $payment->id || $existing->payload_hash !== $event['payload_hash'] || $existing->outcome !== $event['status']) {
                throw new LogicException('Provider event replay changed.');
            }

            return $this->paymentResult($payment->id);
        }
        DB::table('payment_receipts')->insert(['payment_id' => $payment->id, 'gateway' => $gateway, 'merchant' => $event['merchant'],
            'mode' => $event['mode'], 'event_id' => $event['event_id'], 'transaction_reference' => $event['transaction_reference'],
            'amount' => $payment->amount, 'currency' => 'PKR', 'payload_hash' => $event['payload_hash'], 'outcome' => $event['status'],
            'received_at' => now(), 'verified_at' => now()]);
        if ($receiptOutlet && ($receiptOutlet->status || $receiptOutlet->archived_at !== null)) {
            $paid = $event['status'] === 'paid';
            DB::table('payments')->where('id', $payment->id)->update([
                'status' => $paid ? 'paid_reconciliation' : 'unknown',
                'transaction_reference' => $event['transaction_reference'],
                'failure_code' => $paid ? null : 'inactive_outlet_'.$event['status'],
                'paid_at' => $paid ? now() : null, 'completed_at' => $paid ? now() : null,
                'reconciliation_required_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('orders')->where('id', $order->id)->update([
                'payment_status' => $paid ? 'paid_reconciliation' : 'reconciliation_required',
                'paid_at' => $paid ? now() : null, 'updated_at' => now(),
            ]);

            return $this->paymentResult($payment->id);
        }
        if ($event['status'] === 'failed') {
            if (! in_array($payment->status, ['paid', 'paid_reconciliation'], true)) {
                $reservation = DB::table('reservations')->where('order_id', $order->id)->where('website_payment_id', $payment->id)->lockForUpdate()->first();
                if ($reservation && in_array($reservation->state, ['active', 'held_cod'], true)) {
                    $this->stock->release($reservation->id);
                }
                DB::table('payments')->where('id', $payment->id)->update(['status' => 'failed', 'failure_code' => 'provider_declined', 'updated_at' => now()]);
                DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'failed', 'status' => 'cancelled',
                    'fulfillment_status' => 'cancelled', 'cancelled_at' => now(), 'updated_at' => now()]);
            }

            return $this->paymentResult($payment->id);
        }
        if ($event['status'] === 'unknown') {
            if (! in_array($payment->status, ['paid', 'paid_reconciliation'], true)) {
                DB::table('payments')->where('id', $payment->id)->update(['status' => 'unknown', 'reconciliation_required_at' => now(), 'updated_at' => now()]);
                DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'reconciliation_required', 'updated_at' => now()]);
            }

            return $this->paymentResult($payment->id);
        }
        if (in_array($payment->status, ['paid', 'paid_reconciliation'], true)) {
            throw new LogicException('A different verified event cannot collect an already completed payment.');
        }
        $reservation = DB::table('reservations')->where('order_id', $order->id)->where('website_payment_id', $payment->id)->lockForUpdate()->first();
        $late = $order->status === 'cancelled' || ($reservation && (! in_array($reservation->state, ['active', 'held_cod'], true)
            || ($reservation->state === 'active' && $reservation->reservation_expires_at && now()->gte($reservation->reservation_expires_at))));
        if ($late) {
            if ($reservation && in_array($reservation->state, ['active', 'held_cod'], true)) {
                $this->stock->release($reservation->id, $reservation->state === 'active' && now()->gte($reservation->reservation_expires_at));
            }
            DB::table('payments')->where('id', $payment->id)->update(['status' => 'paid_reconciliation', 'transaction_reference' => $event['transaction_reference'],
                'completed_at' => now(), 'paid_at' => now(), 'reconciliation_required_at' => now(), 'updated_at' => now()]);
            DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid_reconciliation', 'paid_at' => now(), 'updated_at' => now()]);

            return $this->paymentResult($payment->id);
        }
        if ($order->order_type === 'digital') {
            $link = DB::table('order_item_milestones')->join('order_items', 'order_items.id', '=', 'order_item_milestones.order_item_id')
                ->where('order_items.order_id', $order->id)->lockForUpdate()->select('order_item_milestones.*')->firstOrFail();
            $milestone = DB::table('project_milestone_identities')->where('id', $link->milestone_id)->lockForUpdate()->firstOrFail();
            if ($milestone->paid_payment_id && $milestone->paid_payment_id !== $payment->id) {
                throw new LogicException('Milestone was already paid by another payment.');
            }
            DB::table('project_milestone_identities')->where('id', $milestone->id)->update(['paid_payment_id' => $payment->id, 'paid_at' => now()]);
            $paidTotal = DB::table('project_milestone_identities')->where('quote_id', $milestone->quote_id)->whereNotNull('paid_payment_id')
                ->orderBy('sequence')->lockForUpdate()->pluck('approved_amount')->reduce(fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');
            $quoteAmount = DB::table('project_quotes')->where('id', $milestone->quote_id)->value('amount');
            if (bccomp($paidTotal, $quoteAmount, 2) === 0) {
                DB::table('project_quotes')->where('id', $milestone->quote_id)->update(['status' => 'paid', 'paid_at' => now(), 'updated_at' => now()]);
            }
        } else {
            $invoice = $this->sales->finalizeReservedOrder($reservation->id);
            DB::table('orders')->where('id', $order->id)->update(['invoice_number' => DB::table('invoices')->where('id', $invoice)->value('invoice_number')]);
        }
        DB::table('payments')->where('id', $payment->id)->update(['status' => 'paid', 'transaction_reference' => $event['transaction_reference'],
            'paid_at' => now(), 'completed_at' => now(), 'updated_at' => now()]);
        DB::table('orders')->where('id', $order->id)->update(['payment_status' => 'paid', 'status' => 'confirmed', 'paid_at' => now(),
            'confirmed_at' => now(), 'version' => $order->version + 1, 'updated_at' => now()]);

        return $this->paymentResult($payment->id);
    }

    private function paymentResult(int $paymentId): array
    {
        $payment = DB::table('payments')->where('id', $paymentId)->firstOrFail();
        $order = DB::table('orders')->where('id', $payment->order_id)->firstOrFail();

        return ['payment_id' => $payment->public_id, 'order_id' => $order->public_id, 'payment_status' => $payment->status,
            'order_status' => $order->status, 'amount' => $payment->amount, 'currency' => $payment->currency,
            'reconciliation_required' => $payment->reconciliation_required_at !== null];
    }

    private function idempotent(string $scope, string $operation, string $key, array $input, callable $callback, ?callable $beforeReplay = null): array
    {
        Validator::make(['key' => $key], ['key' => ['required', 'string', 'min:16', 'max:128', 'regex:/\A[\x21-\x7E]+\z/']])->validate();
        $hash = $this->digest($this->canonical($input));

        return DB::transaction(function () use ($scope, $operation, $key, $hash, $callback, $beforeReplay) {
            $beforeReplay?->__invoke();
            $identity = ['actor_scope' => $scope, 'operation' => $operation, 'key' => $key];
            DB::table('idempotency_requests')->insertOrIgnore([...$identity, 'request_hash' => $hash, 'status' => 'processing', 'created_at' => now(), 'updated_at' => now()]);
            $request = DB::table('idempotency_requests')->where($identity)->lockForUpdate()->firstOrFail();
            if (! hash_equals($request->request_hash, $hash)) {
                throw new LogicException('Idempotency key was already used for a different request.');
            }
            if ($request->status === 'completed') {
                return json_decode($request->response, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $callback();
            DB::table('idempotency_requests')->where('id', $request->id)->update(['status' => 'completed',
                'response' => json_encode($result, JSON_THROW_ON_ERROR), 'response_expires_at' => now()->addDays(7), 'updated_at' => now()]);

            return $result;
        }, 3);
    }

    private function lockActivePhysicalOutlet(array $lines): Outlet
    {
        $productIds = collect($lines)->pluck('product_id')->unique()->values();
        $products = Product::query()->whereIn('public_id', $productIds)->where('isDeleted', false)
            ->get(['public_id', 'outlet_id']);
        abort_unless($products->count() === $productIds->count(), 404);
        $outletIds = $products->pluck('outlet_id')->unique()->values();
        if ($outletIds->count() !== 1) {
            throw ValidationException::withMessages(['lines' => 'A physical checkout must use one outlet.']);
        }
        $outlet = Outlet::whereKey($outletIds->first())->lockForUpdate()->firstOrFail();
        abort_if($outlet->status || $outlet->archived_at !== null, 403, 'Outlet is not active.');

        return $outlet;
    }

    private function lockActiveOrderOutlet(string $ownerScope, string $orderPublicId): Outlet
    {
        $order = DB::table('orders')->where('public_id', $orderPublicId)
            ->where('owner_scope_hash', hash('sha256', $ownerScope))->firstOrFail();
        abort_unless($order->order_type === 'commerce', 404);
        $outletId = DB::table('reservations')->where('order_id', $order->id)
            ->orderByDesc('attempt')->value('outlet_id');
        abort_unless($outletId !== null, 404);
        $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
        abort_if($outlet->status || $outlet->archived_at !== null, 403, 'Outlet is not active.');

        return $outlet;
    }

    private function lockActiveReservationOutlet(int $reservationId): Outlet
    {
        $outletId = DB::table('reservations')->where('id', $reservationId)->value('outlet_id');
        abort_unless($outletId !== null, 404);
        $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
        abort_if($outlet->status || $outlet->archived_at !== null, 403, 'Outlet is not active.');

        return $outlet;
    }

    private function lockAuthorizedOutlet(Admin $actor, int $outletId, string $permission): Outlet
    {
        $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
        abort_unless(app(Access::class)->allows($actor->fresh(), $permission, $outlet), 403);

        return $outlet;
    }

    private function lockActiveReturnOutlet(string $returnPublicId): Outlet
    {
        $invoiceId = DB::table('returns')->where('public_id', $returnPublicId)->value('invoice_id');
        abort_unless($invoiceId !== null, 404);
        $outletId = DB::table('invoices')->where('id', $invoiceId)->value('outlet_id');
        abort_unless($outletId !== null, 404);
        $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
        abort_if($outlet->status || $outlet->archived_at !== null, 403, 'Outlet is not active.');

        return $outlet;
    }

    private function lockAuthorizedReturnOutlet(Admin $actor, string $returnPublicId): Outlet
    {
        $outlet = $this->lockActiveReturnOutlet($returnPublicId);
        abort_unless(app(Access::class)->allows($actor->fresh(), 'shop.sales', $outlet), 403);

        return $outlet;
    }

    private function lockReceiptOutlet(string $gateway, array $event): ?Outlet
    {
        $payment = DB::table('payments')->where('gateway', $gateway)
            ->where(fn ($q) => $q->where('gateway_order_reference', $event['order_reference'])
                ->orWhere('public_id', $event['order_reference']))->firstOrFail();
        $order = DB::table('orders')->where('id', $payment->order_id)->firstOrFail();
        if ($order->order_type === 'digital') {
            return null;
        }
        $outletId = DB::table('reservations')->where('order_id', $order->id)
            ->where('website_payment_id', $payment->id)->value('outlet_id');
        abort_unless($outletId !== null, 404);

        return Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
    }

    private function lockActivePaymentOutlet(string $paymentPublicId): ?Outlet
    {
        $payment = DB::table('payments')->where('public_id', $paymentPublicId)->firstOrFail();
        $order = DB::table('orders')->where('id', $payment->order_id)->firstOrFail();
        if ($order->order_type === 'digital') {
            return null;
        }
        $outletId = DB::table('reservations')->where('order_id', $order->id)
            ->where('website_payment_id', $payment->id)->value('outlet_id');
        abort_unless($outletId !== null, 404);
        $outlet = Outlet::whereKey($outletId)->lockForUpdate()->firstOrFail();
        abort_if($outlet->status || $outlet->archived_at !== null, 403, 'Outlet is not active.');

        return $outlet;
    }

    private function owner(string $scope, ?CustomerAccount $customer): void
    {
        $valid = $customer ? hash_equals($customer::class.':'.$customer->id, $scope) : preg_match('/\Aguest:[0-9a-f]{64}\z/', $scope);
        if (! $valid) {
            throw new LogicException('Server-derived order owner scope is required.');
        }
    }

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['input' => 'Unexpected commerce fields.']);
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

    private function digest(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** A persisted vendor reference must survive even if its hosted URL is unsafe. */
    private function safeContinuation(array $response, string $expectedReference): array
    {
        if (! isset($response['reference']) || ! is_string($response['reference'])
            || ! hash_equals($expectedReference, $response['reference'])) {
            throw new LogicException('Hosted continuation reference does not match its payment intent.');
        }
        if (! array_key_exists('redirect_url', $response)) {
            throw new LogicException('Payment provider did not return a hosted continuation URL.');
        }
        if (array_key_exists('redirect_url', $response)) {
            $url = $response['redirect_url'];
            if (! is_string($url) || ! filter_var($url, FILTER_VALIDATE_URL)
                || parse_url($url, PHP_URL_SCHEME) !== 'https'
                || ! is_string(parse_url($url, PHP_URL_HOST))
                || parse_url($url, PHP_URL_USER) !== null
                || parse_url($url, PHP_URL_PASS) !== null) {
                throw new LogicException('Payment provider returned an unsafe hosted continuation URL.');
            }
        }

        return $response;
    }

    private function sanitize(array $result): array
    {
        return array_intersect_key($result, array_flip(['reference', 'redirect_url', 'expires_at']));
    }
}
