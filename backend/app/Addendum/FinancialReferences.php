<?php

namespace App\Addendum;

use Illuminate\Support\Facades\DB;
use LogicException;

/** Internal append-only references. Callers authorize and own the complete financial transaction. */
final class FinancialReferences
{
    public function adjustment(string $parent, int $parentId, array $snapshot): string
    {
        $this->transaction();
        if (! in_array($parent, ['invoice', 'order'], true)) {
            throw new LogicException('Unknown adjustment owner.');
        }
        $canonical = MoneySnapshot::adjustment($snapshot['id'] ?? '', $snapshot['kind'] ?? '', $snapshot['amount'] ?? null,
            $snapshot['reason'] ?? '', $snapshot['source_reference'] ?? null);
        if ($canonical != $snapshot) {
            throw new LogicException('Unexpected adjustment snapshot fields.');
        }
        $owner = DB::table($parent === 'invoice' ? 'invoices' : 'orders')->where('id', $parentId)->lockForUpdate()->firstOrFail();
        if ($owner->currency !== 'PKR') {
            throw new LogicException('Unsupported financial currency.');
        }
        $existing = DB::table('monetary_adjustments')->where('id', $snapshot['id'])->lockForUpdate()->first();
        $digest = MoneySnapshot::digest($canonical);
        if ($existing) {
            if ($existing->{$parent.'_id'} !== $parentId || $existing->snapshot_sha256 !== $digest
                || $existing->amount !== $canonical['amount'] || $existing->kind !== $canonical['kind'] || $existing->treatment !== $canonical['treatment']
                || $existing->currency !== 'PKR' || $existing->source_reference !== $canonical['source_reference']
                || json_decode($existing->snapshot, true, flags: JSON_THROW_ON_ERROR) != $canonical) {
                throw new LogicException('Immutable adjustment replay changed.');
            }

            return $existing->id;
        }
        // No totals are recalculated here. The owner applies discounts/tenders once, not once per retry.
        DB::table('monetary_adjustments')->insert(['id' => $canonical['id'], $parent.'_id' => $parentId,
            'kind' => $canonical['kind'], 'treatment' => $canonical['treatment'], 'amount' => $canonical['amount'], 'currency' => 'PKR',
            'source_reference' => $canonical['source_reference'], 'snapshot' => json_encode($canonical, JSON_THROW_ON_ERROR),
            'snapshot_sha256' => $digest, 'created_at' => now()]);

        return $canonical['id'];
    }

    public function milestone(int $quoteId, array $snapshot): string
    {
        $this->transaction();
        $canonical = MoneySnapshot::milestone($snapshot['id'] ?? '', $snapshot['quote_id'] ?? '', $snapshot['sequence'] ?? 0,
            $snapshot['approved_amount'] ?? null, $snapshot['approved_scope_sha256'] ?? '');
        if ($canonical != $snapshot) {
            throw new LogicException('Unexpected milestone snapshot fields.');
        }
        $quote = DB::table('project_quotes')->where('id', $quoteId)->lockForUpdate()->firstOrFail();
        if ($quote->public_id !== $canonical['quote_id'] || $quote->currency !== 'PKR') {
            throw new LogicException('Milestone quote identity or currency mismatch.');
        }
        $existing = DB::table('project_milestone_identities')->where('id', $canonical['id'])->lockForUpdate()->first();
        $digest = MoneySnapshot::digest($canonical);
        if ($existing) {
            if ($existing->quote_id !== $quoteId || $existing->snapshot_sha256 !== $digest || $existing->approved_amount !== $canonical['approved_amount']
                || $existing->currency !== 'PKR' || $existing->sequence !== $canonical['sequence']
                || json_decode($existing->approved_snapshot, true, flags: JSON_THROW_ON_ERROR) != $canonical) {
                throw new LogicException('Immutable milestone replay changed.');
            }

            return $existing->id;
        }
        if ($quote->status !== 'approved' || $quote->paid_at !== null || ($quote->expires_at !== null && now()->gte($quote->expires_at))) {
            throw new LogicException('New milestones require a current unpaid approved quote.');
        }
        // Locking reads remain correct even if the owning transaction established an older snapshot.
        $sum = '0.00';
        foreach (DB::table('project_milestone_identities')->where('quote_id', $quoteId)->orderBy('sequence')->lockForUpdate()->get() as $milestone) {
            $sum = bcadd($sum, $milestone->approved_amount, 2);
        }
        if (bccomp(bcadd($sum, $canonical['approved_amount'], 2), $quote->amount, 2) > 0) {
            throw new LogicException('Milestone allocation exceeds the approved quote.');
        }
        DB::table('project_milestone_identities')->insert(['id' => $canonical['id'], 'quote_id' => $quoteId, 'sequence' => $canonical['sequence'],
            'approved_amount' => $canonical['approved_amount'], 'currency' => 'PKR', 'approved_snapshot' => json_encode($canonical, JSON_THROW_ON_ERROR),
            'snapshot_sha256' => $digest, 'created_at' => now()]);

        return $canonical['id'];
    }

    private function transaction(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Financial references require an owning transaction.');
        }
    }
}
