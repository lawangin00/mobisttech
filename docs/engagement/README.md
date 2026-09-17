# MT-3.9 Customer Engagement Services

MT-3.9 adds backend-owned customer engagement primitives without exposing new HTTP contracts ahead of MT-3.4/MT-5.2.

Implemented scope:

- Account-owned opt-in back-in-stock and price-drop email subscriptions using verified Customer Account and Product identities.
- Versioned notification preferences, explicit consent, owned/token unsubscribe, hourly rate limits and durable event deduplication.
- Delivery attempts preserve canonical payload/hash, retry state and truthful sent/failed/suppressed outcomes.
- External delivery is disabled by default. Acceptance uses a safe fake delivery gateway only; no real message was sent.
- Product price comes from the canonical Product sale price and availability comes from StockLedger, not listing cache fields.
- Wishlist/save-for-later supports isolated account or opaque guest ownership and explicit guest-to-account merge.
- Wishlist history retains product name/price snapshots and reports unavailable products without deleting saved history.
- Published Website capability mode gates new subscriptions, wishlist discovery/writes and notification delivery.
- Admin engagement reporting is aggregate-only and requires `website.engagement.manage`.

No production notification provider, public REST endpoint, UI, background scheduler or real customer data migration is introduced by this point.

The shared test harness now clears the testing cache at every test boundary. This removes cross-test named-rate-limiter leakage discovered by the full regression gate without changing production rate-limit behavior.
