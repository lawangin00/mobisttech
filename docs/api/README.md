# MT-3.4 Versioned REST API contracts

MT-3.4 publishes Laravel-owned `/api/v1` contracts for the Next.js Website without moving business authority into Next.js.

## Public contracts

- Website operating profile and active capabilities.
- Bounded catalogue, categories and product detail using current `products.sale_price` and authoritative stock snapshots.
- Published managed pages, policies, Digital Services and Software Product overview/privacy/terms/FAQ/release history.
- Progressive Digital Service enquiry creation through the existing lead service.
- Signed payment-provider callback ingress through the existing payment authority.

Public reads use explicit allowlists, ETags, short cache headers, versioned master-backed cache domains, cursor pagination and route rate limits. Draft CMS content, Admin notes, purchase cost, provider payloads, internal database IDs and legacy cached stock/price are not public contract fields.

## Authenticated customer contracts

Customer APIs reuse the existing customer identity session, CSRF, inactivity and ownership controls. They expose cart quote/repricing, checkout, owned order history, payment initiation/retry, project milestone payment/history/files, wishlist, notification preferences/subscriptions and verified-purchase product reviews.

The cart endpoint is a server-authoritative validation/quote contract, not a second persistent cart engine. Checkout/payment writes delegate to `OrderTransactions`; project APIs delegate to `ClientProjectServices`; wishlist/notification APIs delegate to `CustomerEngagement`.

## Mode and history rules

New discovery/creation is denied when its published Website capability is inactive. Common published content also requires a published operating profile. Authenticated owned order/project/payment history remains available across mode switches where the existing historical-resource contract permits it.

## Pagination, freshness and request budgets

Catalogue pagination is cursor based with `limit <= 24`; owned order history uses a bounded cursor with `limit <= 20`. Digital Service and Software release payloads are capped. Public, customer, public-write and provider-callback routes have separate named rate limits. Compound MySQL indexes were added only for API read paths; no new authoritative business tables were introduced.

Catalogue cache invalidation reuses `publication_versions`/`CatalogChanged`; CMS and mode contracts reuse their versioned cache domains. Product reviews remain master-read outside the catalogue cache so moderation changes do not wait for a catalogue bump.

## Exclusions

MT-3.4 adds no POS UI, Admin UI or Next.js Website feature implementation, no second database/cart/order/payment engine, no external provider activation, no source/private-data migration and no production deployment. UI realization remains in MT-4/MT-5.
