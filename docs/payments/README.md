# MT-2.20 POS Payment Services

MT-2.20 adds backend authority for walk-in POS tender recording without changing the Website checkout model.

## POS payment model

Supported POS methods are Cash, Card, Mobile Wallet and Bank Transfer. A Payment Method records how the customer paid; a Payment Destination records where the business received or expects to settle the money.

Destinations are outlet-scoped, versioned and independently permissioned from sale entry. They may store a display name, provider/bank label, masked identifier, effective window, active state and internal notes. Ordinary destination metadata does not store provider credentials or unmasked financial account/card identifiers.

## Split tender and cash change

A POS Invoice may contain multiple tender allocations. Server-side exact-money validation requires the allocations to equal the authoritative final Invoice payable before the retail sale transaction commits.

Cash allocations record both the amount applied to the Invoice and optional customer cash tendered. Change returned is derived from those two values; only the allocation amount contributes to expected business cash.

Client-supplied sale totals are rejected by the existing sale authority. Inactive, future, expired or wrong-outlet destinations are rejected. Card-like sensitive values such as PAN/CVV/PIN data are not accepted as ordinary references.

## Settlement and reconciliation

Sale, customer tender and provider settlement remain separate records. Non-cash tender allocations may receive append-only settlement evidence with gross customer payment, merchant fee, signed adjustment, expected net settlement, confirmed received net amount and variance.

Merchant fees and settlement adjustments never rewrite the original Invoice total or customer tender amount. Cash allocations are excluded from provider settlement and remain for the later cash-session/day-closing workflow.

## Refund traceability

Accepted POS returns remain owned by `SalesOperations`. MT-2.20 records the corresponding POS refund allocation against the original tender evidence without using or weakening the Website provider-refund engine.

A refund through a different method or destination requires explicit override metadata, a reason and `shop.payments.refund-override`. Destinations configured to require approval also require an authorized `shop.payments.refund-approve` actor. Original-tender and refund-destination snapshots are retained with audit evidence.

## Website boundary

The Website remains fixed to Cash on Delivery, JazzCash, Easypaisa and Credit / Debit Card. MT-2.20 does not add Website Bank Transfer, Website split tender, multiple Website merchant destinations or provider credential handling.

## Deferred interfaces

This checkpoint implements backend domain authority only. HTTP publication remains assigned to MT-3.4. POS payment/destination UI and later reporting/day-closing consumers remain at their named roadmap points, including MT-2.12, MT-3.1 and MT-4.x interface work.
