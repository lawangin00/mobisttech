# MT-7.5 W04 browser diagnostic — 21 Sep 2026

Status: OPEN. Supplements `docs/audit/MT-7.5_W04_PAYMENT_PARITY.md`; does not close W04 or certify Website browser acceptance.

## Exact-source hosted evidence

- Full CI run `35640923317` checked out `a5f2ac6d3381b6b27e354442f74c778923e2cfca` and passed request validation, clean-hosted Pint (276 files), backend regression (341 tests, 10059 assertions), separate MySQL race/reset (23 tests, 1684 assertions), frontend/backend build and static budget/secret checks.
- Isolated `backend/tests/browser/pos-first-outlet-fresh-owner.spec.ts` ran one browser test and FAILED at line 62: expected `Qty 0` inside the product-name button; that button actually contains only the product name and code. First-outlet creation and inventory product creation reached the assertion; source code shows quantity in a sibling paragraph within the same product-card `div` (`backend/resources/js/components/pos-transaction-workspace.tsx`).
- All remaining POS/Admin and Website browser steps, disposable schema reset after first-outlet acceptance, and final cleanup gate were SKIPPED by the failed step. The isolated test's own cleanup seeders completed and reported `CI_DISPOSABLE_RESIDUAL_TABLES=none`; this is not full-suite cleanup acceptance.

## Bounded correction for next authorized source write

In `backend/tests/browser/pos-first-outlet-fresh-owner.spec.ts`, preserve the entire file and all existing test assertions except the product-card locator. Replace the first `await expect(product).toContainText('Qty 0');` with `await expect(product.locator('..')).toContainText('Qty 0');`, and the subsequent `await expect(page.getByRole('button', { name: /MT75 Fresh Accessory/ })).toContainText('Qty 3');` with `await expect(product.locator('..')).toContainText('Qty 3');`. This scopes both quantities to their own product card and retains exact expected stock counts; do not change production auth, inventory, payment behavior, or relax the assertions.

A complete-file GitHub update containing these two test-only edits was attempted in the chat, but the connector's write safety check BLOCKED it. **No source correction commit exists from that attempt, and neither the isolated browser test nor subsequent W04 browser tests have passed on corrected source.** Reattempt only through an authorized supported editing workflow, verify the exact two-line diff, then run bounded browser acceptance on the resulting commit. Do not treat the diagnosed failure as resolved or promote W04 to DONE.
