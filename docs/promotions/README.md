# MT-2.14 Promotion and Coupon Services

MT-2.14 adds one shared server-side promotion authority for POS and Website transactions.

## Implemented scope

- Fixed and percentage promotions with optional maximum discount and minimum eligible subtotal.
- Automatic promotions and explicit coupon codes with validity windows.
- Global or outlet scope, plus product/category applicability.
- Global and per-customer usage limits with transactional claim locking.
- Customer-required promotions and explicit stacking rules with deterministic priority.
- Immutable per-line discount snapshots and append-only promotion audit events.
- POS invoices and Website orders use the same calculation service and PKR money rules.
- Website cancellation/expiry releases active claims; payment retry reuses the original order claim.
- Returns preserve the original sale-line discount allocation rather than recalculating a current promotion.

## Verification

- Focused final suite: 12 tests / 91 assertions passed.
- Full backend suite: 173 tests / 5,781 assertions passed.
- Promotion schema: 124 tables, 1,548 columns, 247 foreign keys, 596 indexes.
- Promotion schema SHA-256: `5a2d4635f7d1eff99f3ee171fbe9c238372e37b9dc2c6504adf6a96be2dfa18a`.
- One-step rollback restored exact MT-2.13 SHA-256 `2053c9a0e7ddaaf26f0dd8b91bb70e03b25deda8b6d3e30e09226e566369c0b0`; reapply restored the identical MT-2.14 hash.
- Composer strict validation/platform requirements, Laravel cache clear, backend TypeScript/Vite build, Website ESLint/typecheck and Next.js production build passed.

No source repository, production database, real payment provider or external messaging action was used by this implementation.
