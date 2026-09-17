# MT-2.15 Customer Loyalty Services

MT-2.15 adds an optional, disableable loyalty/rewards framework without making reward points authoritative money or accounting balances.

## Service contract

- `LoyaltyServices` owns configuration, earning, redemption, expiry, cancellation/expiry release and accepted-return reversal.
- Configuration is immutable/versioned and requires `config.loyalty.manage`.
- Customer point balances are derived through an immutable loyalty ledger plus expiring earn lots; point balances may become negative after a return when previously earned points were already spent.
- Redemption creates a stable loyalty claim and one immutable `loyalty_redemption` monetary-adjustment snapshot. The monetary adjustment records the sale/order discount; it does not turn points into a second money ledger.
- POS and Website use the same server-side point-to-discount calculation and line allocation.
- Loyalty redemption does not stack with manual discounts or promotions/coupons in this implementation.

## Lifecycle and disablement

New earning and new redemption require the latest loyalty configuration to be enabled. Disabling loyalty does not delete balances, claims, ledger entries, expiry history or immutable monetary adjustments. An already-active order redemption can replay while disabled, and cancellation/expiry can still release that existing obligation.

Accepted returns create compensating reward entries: earned points are reversed proportionally, redeemed points are restored proportionally, and a complete return settles the remaining exact allocation. Due earn lots are expired by an explicit bounded expiry operation. If an expired lot is later restored by a valid cancellation/return, it is immediately eligible to expire again with a distinct audit entry.

## Concurrency and abuse controls

Customer/account locking serializes redemptions before a claim is created. Stable owner keys make retries replay-safe; per-redemption minimum/maximum points, daily redemption caps, exact available balance, linked-customer requirements and no-stacking rules bound abuse. Separate MySQL connections verify same-key replay, competing redemption, earn replay and return-reversal replay.

## Operational boundaries

Historical migration remains separate from operational loyalty services. Reset retention classifies loyalty history/account tables as transactional and immutable loyalty configuration as configuration. No production provider, source repository, private customer data migration or external communication is performed by MT-2.15.