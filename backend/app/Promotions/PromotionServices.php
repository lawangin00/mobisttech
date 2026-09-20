<?php

namespace App\Promotions;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Migration\SourceRow;
use App\Models\Admin;
use App\Models\Outlet;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class PromotionServices
{
    public function configure(Admin $actor, ?string $publicId, array $input): array
    {
        $allowed = ['name', 'outlet_id', 'mode', 'code', 'discount_type', 'discount_value', 'max_discount', 'min_subtotal',
            'starts_at', 'ends_at', 'usage_limit', 'per_customer_limit', 'customer_required', 'stackable', 'priority', 'status', 'product_ids', 'categories'];
        $this->fields($input, $allowed);
        $data = Validator::make($input, [
            'name' => 'required|string|max:255', 'outlet_id' => 'nullable|uuid', 'mode' => 'required|in:automatic,coupon',
            'code' => 'nullable|string|max:80', 'discount_type' => 'required|in:fixed,percentage', 'discount_value' => 'required',
            'max_discount' => 'nullable', 'min_subtotal' => 'nullable', 'starts_at' => 'nullable|date', 'ends_at' => 'nullable|date|after:starts_at',
            'usage_limit' => 'nullable|integer|min:1', 'per_customer_limit' => 'nullable|integer|min:1', 'customer_required' => 'sometimes|boolean',
            'stackable' => 'sometimes|boolean', 'priority' => 'sometimes|integer|min:-100000|max:100000', 'status' => 'required|in:active,inactive',
            'product_ids' => 'sometimes|array|max:500', 'product_ids.*' => 'uuid', 'categories' => 'sometimes|array|max:100',
            'categories.*' => 'string|max:40',
        ])->validate();
        $value = SourceRow::money((string) $data['discount_value']);
        $max = array_key_exists('max_discount', $data) && $data['max_discount'] !== null ? SourceRow::money((string) $data['max_discount']) : null;
        $minimum = array_key_exists('min_subtotal', $data) && $data['min_subtotal'] !== null ? SourceRow::money((string) $data['min_subtotal']) : '0.00';
        if (bccomp($value, '0.00', 2) <= 0 || ($data['discount_type'] === 'percentage' && bccomp($value, '100.00', 2) > 0)
            || ($max !== null && bccomp($max, '0.00', 2) <= 0)) {
            throw ValidationException::withMessages(['discount_value' => 'Promotion value or cap is invalid.']);
        }
        $code = $data['mode'] === 'coupon' ? strtoupper(trim((string) ($data['code'] ?? ''))) : null;
        if (($data['mode'] === 'coupon' && $code === '') || ($data['mode'] === 'automatic' && ! empty($data['code']))) {
            throw ValidationException::withMessages(['code' => 'Coupon mode requires one code; automatic mode cannot have a code.']);
        }

        return DB::transaction(function () use ($actor, $publicId, $data, $value, $max, $minimum, $code) {
            $fresh = $actor->fresh();
            abort_unless($fresh && app(Access::class)->allows($fresh, 'config.promotions.manage'), 403);
            $outlet = ! empty($data['outlet_id']) ? Outlet::where('public_id', $data['outlet_id'])->lockForUpdate()->firstOrFail() : null;
            abort_if($outlet && ($outlet->status || $outlet->archived_at !== null), 403, 'Outlet is not active.');
            $productIds = [];
            foreach (array_values(array_unique($data['product_ids'] ?? [])) as $id) {
                $product = Product::where('public_id', $id)->where('isDeleted', false)->lockForUpdate()->firstOrFail();
                if ($outlet && $product->outlet_id !== $outlet->id) {
                    throw ValidationException::withMessages(['product_ids' => 'Outlet promotion products must belong to that outlet.']);
                }
                $productIds[] = $product->id;
            }
            $categories = array_values(array_unique(array_map(fn ($v) => trim((string) $v), $data['categories'] ?? [])));
            if (in_array('', $categories, true)) {
                throw ValidationException::withMessages(['categories' => 'Promotion categories cannot be blank.']);
            }
            $duplicate = $code ? DB::table('promotions')->where('code', $code)->when($publicId, fn ($q) => $q->where('public_id', '<>', $publicId))->lockForUpdate()->exists() : false;
            if ($duplicate) {
                throw ValidationException::withMessages(['code' => 'Coupon code is already in use.']);
            }            $payload = ['outlet_id' => $outlet?->id, 'name' => trim($data['name']), 'mode' => $data['mode'], 'code' => $code,
                'discount_type' => $data['discount_type'], 'discount_value' => $value, 'max_discount' => $max, 'min_subtotal' => $minimum,
                'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'usage_limit' => $data['usage_limit'] ?? null,
                'per_customer_limit' => $data['per_customer_limit'] ?? null, 'customer_required' => (bool) ($data['customer_required'] ?? false),
                'stackable' => (bool) ($data['stackable'] ?? false), 'priority' => (int) ($data['priority'] ?? 0), 'status' => $data['status'],
                'updated_by_admin_id' => $fresh->id, 'updated_at' => now()];
            if ($publicId) {
                $row = DB::table('promotions')->where('public_id', $publicId)->lockForUpdate()->firstOrFail();
                $payload['version'] = $row->version + 1;
                DB::table('promotions')->where('id', $row->id)->update($payload);
                $promotionId = $row->id;
                $event = 'updated';
            } else {
                $promotionId = DB::table('promotions')->insertGetId([...$payload, 'public_id' => (string) Str::uuid(),
                    'created_by_admin_id' => $fresh->id, 'created_at' => now()]);
                $event = 'configured';
            }
            DB::table('promotion_products')->where('promotion_id', $promotionId)->delete();
            foreach ($productIds as $id) {
                DB::table('promotion_products')->insert(['promotion_id' => $promotionId, 'product_id' => $id]);
            }
            DB::table('promotion_categories')->where('promotion_id', $promotionId)->delete();
            foreach ($categories as $category) {
                DB::table('promotion_categories')->insert(['promotion_id' => $promotionId, 'category' => $category]);
            }
            $row = DB::table('promotions')->where('id', $promotionId)->firstOrFail();
            $snapshot = $this->configSnapshot($row, $productIds, $categories);
            $this->event($promotionId, null, $event, $fresh->id, $snapshot);
            IdentityAudit::record('admin', $fresh->id, 'promotion_'.$event, 'promotion:'.$row->public_id, $outlet?->id);

            return $snapshot;
        }, 3);
    }

    /** Claim applicable automatic promotions plus explicitly supplied coupon codes inside the owner's transaction. */
    public function claim(string $channel, Outlet $outlet, string $ownerKey, ?string $customerKey, array $lines, array $codes = []): array
    {
        if (DB::transactionLevel() < 1 || ! in_array($channel, ['pos', 'website'], true) || ! preg_match('/\A[0-9a-f]{64}\z/', $ownerKey)
            || ($customerKey !== null && ! preg_match('/\A[0-9a-f]{64}\z/', $customerKey))) {
            throw new LogicException('Promotion claim requires a valid owning transaction and identity.');
        }        $normalizedCodes = array_values(array_unique(array_map(fn ($v) => strtoupper(trim((string) $v)), $codes)));
        if (in_array('', $normalizedCodes, true) || count($normalizedCodes) > 10) {
            throw ValidationException::withMessages(['promotion_codes' => 'Coupon codes are invalid.']);
        }
        $prepared = [];
        foreach ($lines as $index => $line) {
            foreach (['product_id', 'product_public_id', 'category', 'quantity', 'unit_price', 'gross'] as $field) {
                if (! array_key_exists($field, $line)) {
                    throw new LogicException('Promotion line contract is incomplete.');
                }
            }
            $gross = SourceRow::money((string) $line['gross']);
            if ((int) $line['quantity'] < 1 || bccomp($gross, '0.00', 2) <= 0) {
                throw new LogicException('Promotion line value is invalid.');
            }
            $prepared[$index] = [...$line, 'gross' => $gross];
        }
        $remaining = array_map(fn ($line) => $line['gross'], $prepared);
        $allocations = array_fill_keys(array_keys($prepared), '0.00');
        $applications = [];
        $selected = [];

        $couponRows = collect();
        if ($normalizedCodes) {
            $couponRows = DB::table('promotions')->whereIn('code', $normalizedCodes)->orderByDesc('priority')->orderBy('id')->lockForUpdate()->get();
            if ($couponRows->count() !== count($normalizedCodes)) {
                throw ValidationException::withMessages(['promotion_codes' => 'One or more coupon codes do not exist.']);
            }
        }
        $automaticRows = DB::table('promotions')->where('mode', 'automatic')->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('outlet_id')->orWhere('outlet_id', $outlet->id))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->orderByDesc('priority')->orderBy('id')->lockForUpdate()->get();
        $candidates = $couponRows->map(fn ($row) => [$row, true])->concat($automaticRows->map(fn ($row) => [$row, false]));

        foreach ($candidates as [$promotion, $explicit]) {
            if (! $this->current($promotion, $outlet)) {
                if ($explicit) {
                    throw ValidationException::withMessages(['promotion_codes' => 'Coupon is inactive, expired or not valid for this outlet.']);
                }

                continue;
            }
            if (($promotion->customer_required || $promotion->per_customer_limit !== null) && $customerKey === null) {
                if ($explicit) {
                    throw ValidationException::withMessages(['promotion_codes' => 'Coupon requires an identified customer.']);
                }

                continue;
            }            if ($selected && (! $promotion->stackable || collect($selected)->contains(fn ($p) => ! $p->stackable))) {
                if ($explicit) {
                    throw ValidationException::withMessages(['promotion_codes' => 'Selected coupons cannot be stacked with another applicable promotion.']);
                }

                continue;
            }
            $productScope = DB::table('promotion_products')->where('promotion_id', $promotion->id)->pluck('product_id')->map(fn ($v) => (int) $v)->all();
            $categoryScope = DB::table('promotion_categories')->where('promotion_id', $promotion->id)->pluck('category')->all();
            $eligible = [];
            $base = '0.00';
            foreach ($prepared as $index => $line) {
                $scoped = (! $productScope && ! $categoryScope) || in_array((int) $line['product_id'], $productScope, true)
                    || in_array((string) $line['category'], $categoryScope, true);
                if ($scoped && bccomp($remaining[$index], '0.00', 2) > 0) {
                    $eligible[] = $index;
                    $base = bcadd($base, $remaining[$index], 2);
                }
            }
            if (! $eligible || bccomp($base, (string) $promotion->min_subtotal, 2) < 0) {
                if ($explicit) {
                    throw ValidationException::withMessages(['promotion_codes' => 'Coupon minimum or product/category conditions are not met.']);
                }

                continue;
            }
            $existing = DB::table('promotion_claims')->where(['promotion_id' => $promotion->id, 'channel' => $channel, 'owner_key' => $ownerKey])->lockForUpdate()->first();
            if ($existing) {
                if ($existing->status !== 'active') {
                    throw new LogicException('Released promotion claim cannot be replayed as active.');
                }
                $snapshot = json_decode($existing->snapshot, true, flags: JSON_THROW_ON_ERROR);
                foreach ($snapshot['line_discounts'] as $productPublic => $amount) {
                    foreach ($prepared as $index => $line) {
                        if ($line['product_public_id'] === $productPublic) {
                            $remaining[$index] = bcsub($remaining[$index], $amount, 2);
                            $allocations[$index] = bcadd($allocations[$index], $amount, 2);
                        }
                    }
                }
                $applications[] = ['claim_id' => $existing->public_id, 'promotion_id' => $promotion->public_id, 'name' => $promotion->name,
                    'amount' => $existing->discount_amount, 'snapshot' => $snapshot];
                $selected[] = $promotion;

                continue;
            }
            $activeCount = DB::table('promotion_claims')->where('promotion_id', $promotion->id)->where('status', 'active')->lockForUpdate()->count();
            if ($promotion->usage_limit !== null && $activeCount >= $promotion->usage_limit) {
                if ($explicit) {
                    throw ValidationException::withMessages(['promotion_codes' => 'Coupon usage limit has been reached.']);
                }

                continue;
            }            if ($promotion->per_customer_limit !== null) {
                $customerCount = DB::table('promotion_claims')->where('promotion_id', $promotion->id)->where('customer_key', $customerKey)
                    ->where('status', 'active')->lockForUpdate()->count();
                if ($customerCount >= $promotion->per_customer_limit) {
                    if ($explicit) {
                        throw ValidationException::withMessages(['promotion_codes' => 'Customer coupon usage limit has been reached.']);
                    }

                    continue;
                }
            }
            $amount = $promotion->discount_type === 'fixed' ? SourceRow::money((string) $promotion->discount_value)
                : bcdiv(bcmul($base, SourceRow::money((string) $promotion->discount_value), 4), '100', 2);
            if ($promotion->max_discount !== null && bccomp($amount, (string) $promotion->max_discount, 2) > 0) {
                $amount = SourceRow::money((string) $promotion->max_discount);
            }
            if (bccomp($amount, $base, 2) > 0) {
                $amount = $base;
            }
            if (bccomp($amount, '0.00', 2) <= 0) {
                continue;
            }
            $shares = $this->allocate($eligible, $remaining, $base, $amount);
            $lineDiscounts = [];
            foreach ($shares as $index => $share) {
                $remaining[$index] = bcsub($remaining[$index], $share, 2);
                $allocations[$index] = bcadd($allocations[$index], $share, 2);
                $lineDiscounts[$prepared[$index]['product_public_id']] = $share;
            }
            $claimPublic = (string) Str::uuid();
            $snapshot = ['contract' => 'promotion-claim.v1', 'promotion_id' => $promotion->public_id, 'name' => $promotion->name,
                'code' => $promotion->code, 'channel' => $channel, 'discount_type' => $promotion->discount_type,
                'discount_value' => SourceRow::money((string) $promotion->discount_value),
                'max_discount' => $promotion->max_discount === null ? null : SourceRow::money((string) $promotion->max_discount),
                'min_subtotal' => SourceRow::money((string) $promotion->min_subtotal), 'stackable' => (bool) $promotion->stackable,
                'priority' => (int) $promotion->priority, 'eligible_base' => $base, 'discount_amount' => $amount,
                'line_discounts' => $lineDiscounts];
            $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $claimId = DB::table('promotion_claims')->insertGetId(['public_id' => $claimPublic, 'promotion_id' => $promotion->id,
                'channel' => $channel, 'owner_key' => $ownerKey, 'customer_key' => $customerKey, 'discount_amount' => $amount,
                'status' => 'active', 'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'created_at' => now()]);
            $this->event($promotion->id, $claimId, 'claimed', null, $snapshot);
            $applications[] = ['claim_id' => $claimPublic, 'promotion_id' => $promotion->public_id, 'name' => $promotion->name,
                'amount' => $amount, 'snapshot' => $snapshot];
            $selected[] = $promotion;
        }        $discount = array_reduce($allocations, fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');

        return ['discount' => $discount, 'allocations' => $allocations, 'applications' => $applications];
    }

    public function bind(string $owner, int $ownerId, array $applications): void
    {
        if (DB::transactionLevel() < 1 || ! in_array($owner, ['invoice', 'order'], true)) {
            throw new LogicException('Promotion binding requires an owning transaction.');
        }
        foreach ($applications as $application) {
            $claim = DB::table('promotion_claims')->where('public_id', $application['claim_id'])->lockForUpdate()->firstOrFail();
            $column = $owner.'_id';
            $other = $owner === 'invoice' ? 'order_id' : 'invoice_id';
            if ($claim->{$other} !== null || ($claim->{$column} !== null && $claim->{$column} !== $ownerId)) {
                throw new LogicException('Promotion claim owner changed.');
            }
            if ($claim->{$column} === null) {
                DB::table('promotion_claims')->where('id', $claim->id)->update([$column => $ownerId]);
                app(FinancialReferences::class)->adjustment($owner, $ownerId, MoneySnapshot::adjustment(
                    (string) Str::uuid(), 'promotion', $claim->discount_amount, 'Promotion: '.$application['name'], $claim->public_id));
            }
        }
    }

    public function releaseOrder(int $orderId, string $reason): void
    {
        if (DB::transactionLevel() < 1 || trim($reason) === '') {
            throw new LogicException('Promotion release requires an owning transaction and reason.');
        }
        foreach (DB::table('promotion_claims')->where('order_id', $orderId)->where('status', 'active')->orderBy('id')->lockForUpdate()->get() as $claim) {
            DB::table('promotion_claims')->where('id', $claim->id)->update(['status' => 'released', 'released_at' => now(),
                'release_reason' => mb_substr(trim($reason), 0, 255)]);
            $snapshot = json_decode($claim->snapshot, true, flags: JSON_THROW_ON_ERROR);
            $this->event($claim->promotion_id, $claim->id, 'released', null, ['claim' => $snapshot, 'reason' => trim($reason)]);
        }
    }

    private function current(object $promotion, Outlet $outlet): bool
    {
        return $promotion->status === 'active' && ($promotion->outlet_id === null || $promotion->outlet_id === $outlet->id)
            && ($promotion->starts_at === null || now()->gte($promotion->starts_at))
            && ($promotion->ends_at === null || now()->lt($promotion->ends_at));
    }

    private function allocate(array $eligible, array $remaining, string $base, string $discount): array
    {
        $discountCents = $this->cents($discount);
        $baseCents = max(1, $this->cents($base));
        $left = $discountCents;
        $shares = [];
        foreach ($eligible as $offset => $index) {
            $share = $offset === array_key_last($eligible) ? $left : intdiv($this->cents($remaining[$index]) * $discountCents, $baseCents);
            $share = min($share, $this->cents($remaining[$index]), $left);
            $shares[$index] = $this->money($share);
            $left -= $share;
        }
        if ($left !== 0) {
            throw new LogicException('Promotion allocation did not reconcile exactly.');
        }

        return $shares;
    }

    private function configSnapshot(object $row, array $productIds, array $categories): array
    {
        return ['contract' => 'promotion-config.v1', 'promotion_id' => $row->public_id, 'outlet_id' => $row->outlet_id,
            'name' => $row->name, 'mode' => $row->mode, 'code' => $row->code, 'discount_type' => $row->discount_type,
            'discount_value' => SourceRow::money((string) $row->discount_value),
            'max_discount' => $row->max_discount === null ? null : SourceRow::money((string) $row->max_discount),
            'min_subtotal' => SourceRow::money((string) $row->min_subtotal), 'starts_at' => $row->starts_at, 'ends_at' => $row->ends_at,
            'usage_limit' => $row->usage_limit, 'per_customer_limit' => $row->per_customer_limit,
            'customer_required' => (bool) $row->customer_required, 'stackable' => (bool) $row->stackable,
            'priority' => (int) $row->priority, 'status' => $row->status, 'version' => (int) $row->version,
            'product_ids' => array_values($productIds), 'categories' => array_values($categories)];
    }

    private function event(int $promotionId, ?int $claimId, string $event, ?int $actorId, array $snapshot): void
    {
        $json = json_encode($this->canonical($snapshot), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        DB::table('promotion_events')->insert(['promotion_id' => $promotionId, 'promotion_claim_id' => $claimId, 'event_type' => $event,
            'actor_admin_id' => $actorId, 'snapshot' => $json, 'snapshot_sha256' => hash('sha256', $json), 'created_at' => now()]);
    }

    private function cents(string $value): int
    {
        [$whole, $fraction] = array_pad(explode('.', SourceRow::money($value), 2), 2, '00');

        return ((int) $whole * 100) + (int) str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private function money(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function fields(array $input, array $allowed): void
    {
        $unknown = array_values(array_diff(array_keys($input), $allowed));
        if ($unknown) {
            throw ValidationException::withMessages(['input' => 'Unknown fields: '.implode(', ', $unknown)]);
        }
    }

    private function canonical(array $value): array
    {
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->canonical($item);
            }
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
