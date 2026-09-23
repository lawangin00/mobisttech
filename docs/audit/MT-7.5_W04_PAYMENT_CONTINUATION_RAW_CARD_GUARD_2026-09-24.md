# MT-7.5 / W04 payment continuation raw-card input guard (24-Sep-2026 PKT)

Status: IN PROGRESS ? hosted test evidence pending; not a completed W04 family gate.

## Scoped finding and correction

Checkout already rejects extra raw-card fields, but authenticated Customer payment retry and hosted initiation previously read only the requested gateway/payment identifier while ignoring arbitrary submitted body keys. These routes now deny any retry body except exactly `gateway`, and any nonempty hosted-initiation body, before invoking an adapter or changing order/payment state. Existing Website continuation sends only `{ gateway }` for retry and `{}` for initiation. No PAN/CVV collection, payment credential storage, gateway activation, external contact or production data operation is introduced.

The existing authenticated W04 checkout fixture now checks synthetic-only raw-card fields on both continuation endpoints, HTTP 422 and unchanged order/payment/reservation/idempotency counts; the existing positive COD checkout remains covered. The synthetic test deliberately uses the literal `synthetic-card-data`, not real card numbers. No local checkout/run has been asserted under GITHUB execution mode. Required: clean hosted PHP style/backend regression and independently observed terminal CI outcome for the exact candidate before marking this subgate accepted.

H-02 genuine provider specifications, credentials, callbacks/refund/settlement and owner-enrollment decisions remain unresolved; MT-7.5/W04 remains In Progress, 15/27 DONE, 12 OPEN.