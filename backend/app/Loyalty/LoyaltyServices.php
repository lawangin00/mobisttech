<?php

namespace App\Loyalty;

use App\Addendum\FinancialReferences;
use App\Addendum\MoneySnapshot;
use App\Identity\Access;
use App\Identity\IdentityAudit;
use App\Migration\SourceRow;
use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Throwable;

final class LoyaltyServices
{
    public function configure(Admin $actor, array $input): object
    {
        return DB::transaction(function () use ($actor, $input) {
            $actor = $actor->fresh();
            abort_unless($actor && app(Access::class)->allows($actor, 'config.loyalty.manage'), 403);
            $this->fields($input, ['enabled', 'earn_basis_amount', 'earn_points', 'redemption_value',
                'min_redeem_points', 'max_redeem_points', 'daily_redeem_points', 'expiry_days']);
            $data = Validator::make($input, [
                'enabled' => 'required|boolean', 'earn_basis_amount' => 'required', 'earn_points' => 'required|integer|min:1|max:1000000',
                'redemption_value' => 'required', 'min_redeem_points' => 'required|integer|min:1|max:1000000000',
                'max_redeem_points' => 'nullable|integer|min:1|max:1000000000',
                'daily_redeem_points' => 'nullable|integer|min:1|max:1000000000', 'expiry_days' => 'nullable|integer|min:1|max:3650',
            ])->validate();
            try {
                $basis = SourceRow::money((string) $data['earn_basis_amount']);
                $value = SourceRow::money((string) $data['redemption_value']);
            } catch (Throwable $error) {
                throw ValidationException::withMessages(['money' => $error->getMessage()]);
            }
            if (bccomp($basis, '0.00', 2) <= 0 || bccomp($value, '0.00', 2) <= 0
                || ($data['max_redeem_points'] !== null && $data['max_redeem_points'] < $data['min_redeem_points'])) {
                throw ValidationException::withMessages(['loyalty' => 'Loyalty configuration values are inconsistent.']);
            }
            $version = ((int) DB::table('loyalty_configurations')->max('version')) + 1;
            $snapshot = ['contract' => 'loyalty-configuration.v1', 'version' => $version, 'enabled' => (bool) $data['enabled'],
                'earn_basis_amount' => $basis, 'earn_points' => (int) $data['earn_points'], 'redemption_value' => $value,                'min_redeem_points' => (int) $data['min_redeem_points'], 'max_redeem_points' => $data['max_redeem_points'],
                'daily_redeem_points' => $data['daily_redeem_points'], 'expiry_days' => $data['expiry_days']];
            $snapshot = $this->canonical($snapshot);
            $id = DB::table('loyalty_configurations')->insertGetId(['public_id' => (string) Str::uuid(), 'version' => $version,
                'enabled' => (bool) $data['enabled'], 'earn_basis_amount' => $basis, 'earn_points' => (int) $data['earn_points'],
                'redemption_value' => $value, 'min_redeem_points' => (int) $data['min_redeem_points'],
                'max_redeem_points' => $data['max_redeem_points'], 'daily_redeem_points' => $data['daily_redeem_points'],
                'expiry_days' => $data['expiry_days'], 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'snapshot_sha256' => $this->digest($snapshot), 'created_by_admin_id' => $actor->id, 'created_at' => now()]);
            IdentityAudit::record('admin', $actor->id, 'loyalty_configured', 'loyalty_configuration:'.$version);

            return DB::table('loyalty_configurations')->where('id', $id)->firstOrFail();
        }, 3);
    }

    public function claim(string $channel, int $customerId, string $ownerKey, int $points, string $maxAmount, array $lineBases): array
    {
        $this->owningTransaction();
        if (! in_array($channel, ['pos', 'website'], true) || ! preg_match('/\A[0-9a-f]{64}\z/', $ownerKey) || $points < 0) {
            throw ValidationException::withMessages(['loyalty' => 'Invalid loyalty claim identity or points.']);
        }
        $maxAmount = SourceRow::money($maxAmount);
        if ($points === 0) {
            return ['points' => 0, 'discount' => '0.00', 'allocations' => array_fill_keys(array_keys($lineBases), '0.00'), 'applications' => []];
        }
        $customer = DB::table('customers')->where('id', $customerId)->lockForUpdate()->firstOrFail();
        $account = $this->account($customerId);
        $this->expireAccount($account);
        $account = DB::table('loyalty_accounts')->where('id', $account->id)->lockForUpdate()->firstOrFail();
        $existing = DB::table('loyalty_claims')->where(['channel' => $channel, 'owner_key' => $ownerKey])->lockForUpdate()->first();
        if ($existing) {
            if ((int) $existing->customer_id !== $customerId || (int) $existing->points !== $points || $existing->status !== 'active') {
                throw new LogicException('Loyalty claim replay changed or was already released.');
            }
            $snapshot = json_decode($existing->snapshot, true, flags: JSON_THROW_ON_ERROR);

            return ['points' => (int) $existing->points, 'discount' => $existing->discount_amount,
                'allocations' => $snapshot['line_allocations'], 'applications' => [['claim_id' => $existing->public_id]]];
        }
        if ($customer->archived_at !== null) {
            throw ValidationException::withMessages(['loyalty' => 'Archived customers cannot create new loyalty redemptions.']);
        }
        $config = $this->current(true);
        if (! $config || ! $config->enabled) {
            throw ValidationException::withMessages(['loyalty' => 'Loyalty redemption is currently disabled.']);
        }
        if ($points < $config->min_redeem_points || ($config->max_redeem_points && $points > $config->max_redeem_points)) {
            throw ValidationException::withMessages(['loyalty' => 'Requested loyalty points are outside the configured redemption limits.']);
        }
        if ($config->daily_redeem_points) {
            $usedToday = (int) DB::table('loyalty_claims')->where('customer_id', $customerId)
                ->where('created_at', '>=', now()->startOfDay())->sum('points');
            if ($usedToday + $points > $config->daily_redeem_points) {
                throw ValidationException::withMessages(['loyalty' => 'Daily loyalty redemption limit would be exceeded.']);
            }
        }
        if ((int) $account->balance_points < $points) {
            throw ValidationException::withMessages(['loyalty' => 'Available loyalty balance is insufficient.']);
        }
        $amount = bcmul((string) $points, $config->redemption_value, 2);
        if (bccomp($amount, '0.00', 2) <= 0 || bccomp($amount, $maxAmount, 2) > 0) {
            throw ValidationException::withMessages(['loyalty' => 'Requested points exceed the eligible order amount.']);
        }
        $bases = [];
        $sum = '0.00';
        foreach ($lineBases as $key => $base) {
            $bases[$key] = SourceRow::money((string) $base);
            $sum = bcadd($sum, $bases[$key], 2);
        }
        if (bccomp($sum, $maxAmount, 2) !== 0) {
            throw new LogicException('Loyalty allocation bases do not match the eligible amount.');
        }
        $allocations = $this->allocate($bases, $maxAmount, $amount);
        $lots = DB::table('loyalty_earn_lots')->where('account_id', $account->id)->where('points_remaining', '>', 0)
            ->orderByRaw('expires_at IS NULL, expires_at, id')->lockForUpdate()->get();
        $remaining = $points;
        $consumed = [];
        foreach ($lots as $lot) {
            if ($remaining < 1) {
                break;
            }
            $take = min($remaining, (int) $lot->points_remaining);
            if ($take < 1) {
                continue;
            }
            DB::table('loyalty_earn_lots')->where('id', $lot->id)->update(['points_remaining' => (int) $lot->points_remaining - $take, 'updated_at' => now()]);
            $consumed[] = ['lot_id' => $lot->id, 'points' => $take];
            $remaining -= $take;
        }
        if ($remaining !== 0) {
            throw new LogicException('Loyalty account balance and earn lots are inconsistent.');
        }
        $publicId = (string) Str::uuid();
        $adjustmentId = (string) Str::uuid();
        $snapshot = $this->canonical(['contract' => 'loyalty-claim.v1', 'id' => $publicId, 'adjustment_id' => $adjustmentId, 'customer_id' => $customer->public_id,
            'config_version' => (int) $config->version, 'channel' => $channel, 'points' => $points, 'discount_amount' => $amount,
            'currency' => 'PKR', 'line_allocations' => $allocations]);
        $claimId = DB::table('loyalty_claims')->insertGetId(['public_id' => $publicId, 'customer_id' => $customerId,
            'account_id' => $account->id, 'monetary_adjustment_id' => $adjustmentId, 'channel' => $channel, 'owner_key' => $ownerKey, 'points' => $points,
            'discount_amount' => $amount, 'status' => 'active', 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
            'snapshot_sha256' => $this->digest($snapshot), 'created_at' => now()]);
        foreach ($consumed as $allocation) {
            DB::table('loyalty_claim_lots')->insert(['loyalty_claim_id' => $claimId, 'loyalty_earn_lot_id' => $allocation['lot_id'],
                'points' => $allocation['points'], 'restored_points' => 0, 'created_at' => now()]);
        }
        DB::table('loyalty_accounts')->where('id', $account->id)->update(['balance_points' => (int) $account->balance_points - $points,
            'version' => (int) $account->version + 1, 'updated_at' => now()]);
        $this->entry($customerId, $account->id, 'redeem', 'debit', $points, 'redeem:'.$publicId,
            loyaltyClaimId: $claimId, snapshot: ['claim_id' => $publicId, 'discount_amount' => $amount]);

        return ['points' => $points, 'discount' => $amount, 'allocations' => $allocations, 'applications' => [['claim_id' => $publicId]]];
    }

    public function bind(string $parent, int $parentId, array $applications): void
    {
        $this->owningTransaction();
        if (! in_array($parent, ['invoice', 'order'], true)) {
            throw new LogicException('Unknown loyalty adjustment owner.');
        }
        foreach ($applications as $application) {
            $claim = DB::table('loyalty_claims')->where('public_id', $application['claim_id'] ?? '')->lockForUpdate()->firstOrFail();
            if ($claim->status !== 'active') {
                throw new LogicException('Released loyalty claim cannot be bound.');
            }
            $column = $parent.'_id';
            $other = $parent === 'invoice' ? 'order_id' : 'invoice_id';
            if (($claim->$column !== null && (int) $claim->$column !== $parentId) || $claim->$other !== null) {
                throw new LogicException('Loyalty claim already belongs to another financial owner.');
            }
            if ($claim->$column === null) {
                DB::table('loyalty_claims')->where('id', $claim->id)->update([$column => $parentId]);
            }
            app(FinancialReferences::class)->adjustment($parent, $parentId,
                MoneySnapshot::adjustment($claim->monetary_adjustment_id, 'loyalty_redemption', $claim->discount_amount,
                    'Customer loyalty redemption', $claim->public_id));
        }
    }

    public function releaseOrder(int $orderId, string $reason): int
    {
        $this->owningTransaction();
        $claims = DB::table('loyalty_claims')->where('order_id', $orderId)->where('status', 'active')->orderBy('id')->lockForUpdate()->get();
        $released = 0;
        foreach ($claims as $claim) {
            $account = DB::table('loyalty_accounts')->where('id', $claim->account_id)->lockForUpdate()->firstOrFail();
            $restore = 0;
            foreach (DB::table('loyalty_claim_lots')->where('loyalty_claim_id', $claim->id)->orderBy('loyalty_earn_lot_id')->lockForUpdate()->get() as $allocation) {
                $points = (int) $allocation->points - (int) $allocation->restored_points;
                if ($points < 1) {
                    continue;
                }
                $lot = DB::table('loyalty_earn_lots')->where('id', $allocation->loyalty_earn_lot_id)->lockForUpdate()->firstOrFail();
                DB::table('loyalty_earn_lots')->where('id', $lot->id)->update(['points_remaining' => (int) $lot->points_remaining + $points, 'updated_at' => now()]);
                DB::table('loyalty_claim_lots')->where(['loyalty_claim_id' => $claim->id, 'loyalty_earn_lot_id' => $lot->id])
                    ->update(['restored_points' => (int) $allocation->restored_points + $points]);
                $restore += $points;
            }
            if ($restore > 0) {
                DB::table('loyalty_accounts')->where('id', $account->id)->update(['balance_points' => (int) $account->balance_points + $restore,
                    'version' => (int) $account->version + 1, 'updated_at' => now()]);
                $this->entry($claim->customer_id, $account->id, 'redemption_release', 'credit', $restore, 'release:'.$claim->public_id,
                    loyaltyClaimId: $claim->id, orderId: $orderId, snapshot: ['claim_id' => $claim->public_id, 'reason' => $reason]);
            }
            DB::table('loyalty_claims')->where('id', $claim->id)->update(['status' => 'released', 'released_at' => now(), 'release_reason' => $reason]);
            $this->expireAccount(DB::table('loyalty_accounts')->where('id', $account->id)->lockForUpdate()->firstOrFail());
            $released++;
        }

        return $released;
    }

    public function earnInvoice(int $invoiceId): array
    {
        if (DB::transactionLevel() < 1) {
            return DB::transaction(fn () => $this->earnInvoiceLocked($invoiceId), 3);
        }

        return $this->earnInvoiceLocked($invoiceId);
    }

    private function earnInvoiceLocked(int $invoiceId): array
    {
        $invoice = DB::table('invoices')->where('id', $invoiceId)->lockForUpdate()->firstOrFail();
        if (! $invoice->customer_id || bccomp($invoice->final_bill, '0.00', 2) <= 0) {
            return ['points' => 0];
        }
        $eventKey = 'earn:invoice:'.$invoice->public_id;
        $existing = DB::table('loyalty_entries')->where('event_key', $eventKey)->lockForUpdate()->first();
        if ($existing) {
            return ['points' => (int) $existing->points, 'entry_id' => $existing->public_id];
        }
        $config = $this->current(true);
        if (! $config || ! $config->enabled) {
            return ['points' => 0];
        }
        $account = $this->account((int) $invoice->customer_id);
        $this->expireAccount($account);
        $account = DB::table('loyalty_accounts')->where('id', $account->id)->lockForUpdate()->firstOrFail();
        $points = intdiv($this->cents($invoice->final_bill) * (int) $config->earn_points, max(1, $this->cents($config->earn_basis_amount)));
        if ($points < 1) {
            return ['points' => 0];
        }
        $entryId = $this->entry((int) $invoice->customer_id, $account->id, 'earn', 'credit', $points, $eventKey,
            invoiceId: $invoice->id, snapshot: ['invoice_id' => $invoice->public_id, 'config_version' => (int) $config->version,
                'eligible_amount' => $invoice->final_bill]);
        $entry = DB::table('loyalty_entries')->where('id', $entryId)->firstOrFail();
        DB::table('loyalty_earn_lots')->insert(['account_id' => $account->id, 'source_entry_id' => $entryId, 'invoice_id' => $invoice->id,
            'points_earned' => $points, 'points_remaining' => $points,
            'expires_at' => $config->expiry_days ? now()->addDays((int) $config->expiry_days) : null, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('loyalty_accounts')->where('id', $account->id)->update(['balance_points' => (int) $account->balance_points + $points,
            'version' => (int) $account->version + 1, 'updated_at' => now()]);

        return ['points' => $points, 'entry_id' => $entry->public_id];
    }

    public function reverseReturn(int $returnId): array
    {
        if (DB::transactionLevel() < 1) {
            return DB::transaction(fn () => $this->reverseReturnLocked($returnId), 3);
        }

        return $this->reverseReturnLocked($returnId);
    }

    private function reverseReturnLocked(int $returnId): array
    {
        $return = DB::table('returns')->where('id', $returnId)->lockForUpdate()->firstOrFail();
        $invoice = DB::table('invoices')->where('id', $return->invoice_id)->lockForUpdate()->firstOrFail();
        if (! $invoice->customer_id) {
            return ['earned_reversed' => 0, 'redeemed_restored' => 0];
        }
        $account = DB::table('loyalty_accounts')->where('customer_id', $invoice->customer_id)->lockForUpdate()->first();
        if (! $account) {
            return ['earned_reversed' => 0, 'redeemed_restored' => 0];
        }        $returnLines = DB::table('return_lines')->where('return_id', $returnId)->orderBy('id')->lockForUpdate()->get();
        $returnNet = '0.00';
        $returnDiscount = '0.00';
        foreach ($returnLines as $line) {
            $returnNet = bcadd($returnNet, (string) $line->net_amount, 2);
            $returnDiscount = bcadd($returnDiscount, (string) $line->discount_amount, 2);
        }
        $earnedReversed = 0;
        $redeemedRestored = 0;
        $fullReturn = ! DB::table('sales')->where('invoice_id', $invoice->id)
            ->whereColumn('returned_quantity', '<', 'quantity')->exists();
        $earnLot = DB::table('loyalty_earn_lots')->where('invoice_id', $invoice->id)->lockForUpdate()->first();
        $earnEvent = 'return-earn:'.$return->public_id;
        $existingEarn = DB::table('loyalty_entries')->where('event_key', $earnEvent)->lockForUpdate()->first();
        if ($existingEarn) {
            $earnedReversed = (int) $existingEarn->points;
        } elseif ($earnLot) {
            $prior = (int) DB::table('loyalty_entries')->where('invoice_id', $invoice->id)
                ->where('entry_type', 'earn_return_reversal')->sum('points');
            $remaining = max(0, (int) $earnLot->points_earned - $prior);
            $target = $fullReturn ? $remaining : min($remaining,
                intdiv((int) $earnLot->points_earned * $this->cents($returnNet), max(1, $this->cents($invoice->final_bill))));
            if ($target > 0) {
                $availableReduction = min($target, (int) $earnLot->points_remaining);
                if ($availableReduction > 0) {
                    DB::table('loyalty_earn_lots')->where('id', $earnLot->id)->update([
                        'points_remaining' => (int) $earnLot->points_remaining - $availableReduction, 'updated_at' => now(),
                    ]);
                }
                DB::table('loyalty_accounts')->where('id', $account->id)->update([
                    'balance_points' => (int) $account->balance_points - $target,
                    'version' => (int) $account->version + 1, 'updated_at' => now(),
                ]);
                $this->entry((int) $invoice->customer_id, $account->id, 'earn_return_reversal', 'debit', $target, $earnEvent,
                    invoiceId: $invoice->id, returnId: $returnId,
                    snapshot: ['invoice_id' => $invoice->public_id, 'return_id' => $return->public_id, 'full_return' => $fullReturn]);
                $earnedReversed = $target;
                $account = DB::table('loyalty_accounts')->where('id', $account->id)->lockForUpdate()->firstOrFail();
            }
        }

        $claim = DB::table('loyalty_claims')->where('customer_id', $invoice->customer_id)->where('status', 'active')
            ->where(function ($query) use ($invoice) {
                $query->where('invoice_id', $invoice->id);
                if ($invoice->order_id) {
                    $query->orWhere('order_id', $invoice->order_id);
                }
            })->lockForUpdate()->first();
        $redeemEvent = 'return-redeem:'.$return->public_id;
        $existingRedeem = DB::table('loyalty_entries')->where('event_key', $redeemEvent)->lockForUpdate()->first();
        if ($existingRedeem) {
            $redeemedRestored = (int) $existingRedeem->points;
        } elseif ($claim && bccomp($returnDiscount, '0.00', 2) > 0) {
            $prior = (int) DB::table('loyalty_entries')->where('loyalty_claim_id', $claim->id)
                ->where('entry_type', 'redeem_return_restore')->sum('points');
            $remaining = max(0, (int) $claim->points - $prior);
            $target = $fullReturn ? $remaining : min($remaining,
                intdiv((int) $claim->points * $this->cents($returnDiscount), max(1, $this->cents($claim->discount_amount))));
            $toRestore = $target;
            foreach (DB::table('loyalty_claim_lots')->where('loyalty_claim_id', $claim->id)
                ->orderBy('loyalty_earn_lot_id')->lockForUpdate()->get() as $allocation) {
                if ($toRestore < 1) {
                    break;
                }
                $available = (int) $allocation->points - (int) $allocation->restored_points;
                $points = min($toRestore, max(0, $available));
                if ($points < 1) {
                    continue;
                }
                $lot = DB::table('loyalty_earn_lots')->where('id', $allocation->loyalty_earn_lot_id)->lockForUpdate()->firstOrFail();
                DB::table('loyalty_earn_lots')->where('id', $lot->id)->update([
                    'points_remaining' => (int) $lot->points_remaining + $points, 'updated_at' => now(),
                ]);
                DB::table('loyalty_claim_lots')->where([
                    'loyalty_claim_id' => $claim->id, 'loyalty_earn_lot_id' => $lot->id,
                ])->update(['restored_points' => (int) $allocation->restored_points + $points]);
                $toRestore -= $points;
            }
            $restored = $target - $toRestore;
            if ($restored > 0) {
                DB::table('loyalty_accounts')->where('id', $account->id)->update([
                    'balance_points' => (int) $account->balance_points + $restored,
                    'version' => (int) $account->version + 1, 'updated_at' => now(),
                ]);
                $this->entry((int) $invoice->customer_id, $account->id, 'redeem_return_restore', 'credit', $restored, $redeemEvent,
                    loyaltyClaimId: $claim->id, invoiceId: $invoice->id, returnId: $returnId,
                    snapshot: ['claim_id' => $claim->public_id, 'return_id' => $return->public_id, 'full_return' => $fullReturn]);
                $redeemedRestored = $restored;
                $account = DB::table('loyalty_accounts')->where('id', $account->id)->lockForUpdate()->firstOrFail();
            }
        }
        $this->expireAccount($account);

        return ['earned_reversed' => $earnedReversed, 'redeemed_restored' => $redeemedRestored];
    }

    public function expireDue(int $limit = 500): int
    {
        if ($limit < 1 || $limit > 5000) {
            throw ValidationException::withMessages(['limit' => 'Loyalty expiry batch limit must be between 1 and 5000.']);
        }
        $accountIds = DB::table('loyalty_earn_lots')->where('points_remaining', '>', 0)->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())->orderBy('account_id')->limit($limit)->pluck('account_id')->unique()->values();
        $expired = 0;
        foreach ($accountIds as $accountId) {
            $expired += DB::transaction(function () use ($accountId) {
                $account = DB::table('loyalty_accounts')->where('id', $accountId)->lockForUpdate()->first();

                return $account ? $this->expireAccount($account) : 0;
            }, 3);
        }

        return $expired;
    }

    private function current(bool $lock = false): ?object
    {
        $query = DB::table('loyalty_configurations')->orderByDesc('version');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    private function account(int $customerId): object
    {
        DB::table('loyalty_accounts')->insertOrIgnore([
            'customer_id' => $customerId, 'balance_points' => 0, 'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return DB::table('loyalty_accounts')->where('customer_id', $customerId)->lockForUpdate()->firstOrFail();
    }

    private function expireAccount(object $account): int
    {
        $expired = 0;
        $lots = DB::table('loyalty_earn_lots')->where('account_id', $account->id)->where('points_remaining', '>', 0)
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())->orderBy('expires_at')->orderBy('id')->lockForUpdate()->get();
        foreach ($lots as $lot) {
            $points = (int) $lot->points_remaining;
            if ($points < 1) {
                continue;
            }
            DB::table('loyalty_earn_lots')->where('id', $lot->id)->update(['points_remaining' => 0, 'updated_at' => now()]);
            $sequence = 1 + (int) DB::table('loyalty_entries')->where('invoice_id', $lot->invoice_id)->where('entry_type', 'expire')->count();
            $this->entry((int) $account->customer_id, $account->id, 'expire', 'debit', $points, 'expire:lot:'.$lot->id.':'.$sequence,
                invoiceId: $lot->invoice_id, snapshot: ['earn_lot_id' => (int) $lot->id, 'sequence' => $sequence, 'expired_at' => now()->toISOString()]);
            $expired += $points;
        }
        if ($expired > 0) {
            DB::table('loyalty_accounts')->where('id', $account->id)->update([
                'balance_points' => (int) $account->balance_points - $expired,
                'version' => (int) $account->version + 1, 'updated_at' => now(),
            ]);
        }

        return $expired;
    }

    private function entry(int $customerId, int $accountId, string $type, string $direction, int $points, string $eventKey,
        ?int $loyaltyClaimId = null, ?int $invoiceId = null, ?int $orderId = null, ?int $returnId = null, array $snapshot = []): int
    {
        if ($points < 1) {
            throw new LogicException('Loyalty ledger entries require positive points.');
        }
        $existing = DB::table('loyalty_entries')->where('event_key', $eventKey)->lockForUpdate()->first();
        if ($existing) {
            if ((int) $existing->customer_id !== $customerId || (int) $existing->account_id !== $accountId
                || $existing->entry_type !== $type || $existing->direction !== $direction || (int) $existing->points !== $points) {
                throw new LogicException('Loyalty event replay changed.');
            }

            return (int) $existing->id;
        }
        $publicId = (string) Str::uuid();
        $canonical = $this->canonical(['contract' => 'loyalty-entry.v1', 'id' => $publicId, 'entry_type' => $type,
            'direction' => $direction, 'points' => $points, 'event_key' => $eventKey, 'context' => $snapshot]);

        return DB::table('loyalty_entries')->insertGetId([
            'public_id' => $publicId, 'customer_id' => $customerId, 'account_id' => $accountId,
            'loyalty_claim_id' => $loyaltyClaimId, 'invoice_id' => $invoiceId, 'order_id' => $orderId, 'return_id' => $returnId,
            'entry_type' => $type, 'direction' => $direction, 'points' => $points, 'event_key' => $eventKey,
            'snapshot' => json_encode($canonical, JSON_THROW_ON_ERROR), 'snapshot_sha256' => $this->digest($canonical), 'created_at' => now(),
        ]);
    }

    private function allocate(array $bases, string $total, string $discount): array
    {
        $remaining = $this->cents($discount);
        $totalCents = max(1, $this->cents($total));
        $result = [];
        foreach ($bases as $key => $base) {
            $share = $key === array_key_last($bases) ? $remaining
                : intdiv($this->cents($base) * $this->cents($discount), $totalCents);
            $share = min($remaining, $share);
            $result[$key] = $this->money($share);
            $remaining -= $share;
        }
        if ($remaining !== 0) {
            throw new LogicException('Loyalty allocation did not consume the full discount.');
        }

        return $result;
    }

    private function cents(string $money): int
    {
        $money = SourceRow::money($money);

        return (int) str_replace('.', '', $money);
    }

    private function money(int $cents): string
    {
        if ($cents < 0) {
            throw new LogicException('Loyalty money allocation cannot be negative.');
        }

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }

    private function digest(array $snapshot): string
    {
        return hash('sha256', json_encode($this->canonical($snapshot), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
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

    private function fields(array $input, array $allowed): void
    {
        if (array_diff(array_keys($input), $allowed)) {
            throw ValidationException::withMessages(['request' => 'Unexpected loyalty fields. Financial owners and ledger state are server-controlled.']);
        }
    }

    private function owningTransaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Loyalty financial mutations require an owning transaction.');
        }
    }
}
