# MT-7.5 W04 — early verified payment event during provider initiation (23-Sep-2026)

Status: synthetic timing/receipt regression; W04 still IN PROGRESS and authentic provider H-02 HOLD unchanged.

- A synthetic provider adapter issued an HMAC-verified `paid` event while its `initiate()` call had not returned a provider reference. The adapter deliberately echoed the local immutable payment public ID, which the current receipt matcher supports. This is NOT proof that any genuine wallet/card provider supports that reference contract.
- The callback completed the owned order exactly once, including one receipt and one POS sale. When the network initiation subsequently returned, the post-network state gate durably stored the provider reference but rejected a stale hosted redirect. Repeating initiation remained rejected, and no duplicate sale was created.
- Focused 1/1 PASS (9 assertions), joined W04/API/OrderPayment 74/74 PASS (1520 assertions), scoped Pint PASS. Verified callback shape is synthetic only; merchant/reference semantics for real provider remain unverified.
- OPEN: if a vendor callback reports only a vendor-generated reference before that reference has been persisted, it cannot be correlated by the current lookup. Any authentic adapter must contractually provide a stable local correlation key or an approved reconciliation/retry path, before H-02 can close.
