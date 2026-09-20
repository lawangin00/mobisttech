# MT-7.5 W02 public catalogue and comparison route parity

Date: 21-Sep-2026 PKT  
Scope: W02 only; protected legacy Website sources were inspected read-only.

## Route disposition

| Legacy public contract | Current canonical disposition | Acceptance |
|---|---|---|
| `/products` | `/products` | Preserved with category, subcategory, name, brand, model, condition, PTA, RAM, storage, price, availability, sort and cursor pagination. |
| `/products/{mobiles|tablets|accessories}` | Permanent redirect to `/products?category={mobile_phone|tablet|accessory}` | Accepted in real Edge alongside filtered results. |
| `/product/{slug}` | Permanent redirect to `/products/{slug}` | Accepted with published detail and unpublished/private 404 behavior. |
| `/mobiles` | Permanent redirect to `/products?category=mobile_phone` | Accepted. |
| `/mobiles/{slug}` | Permanent redirect to `/products/{slug}` | Accepted. |
| `/compare?products=...` | `/compare`, retaining the legacy comma selector and canonical `a,b,c,d` slots | Up to four published products; owner-approved strict same-category policy; live search can reach products beyond the first page. |

Digital-service routes and managed SEO overrides belong to W05 and W06 respectively and are not W02 closure gaps.

## Behavior and evidence reconciliation

| W02 acceptance dimension | Current evidence and result |
|---|---|
| Category, subcategory and device filters | Target-only MySQL HTTP and real Next.js checks cover category, managed subcategory, brand/model, condition/PTA, RAM/storage and the legacy `pta`/`ram`/`storage` aliases. PASS. |
| Search, price, sort and pagination | Name search, bounded price inputs, all supported sort orders, stable cursor pagination, searchable off-first-page comparison choices and the 241-product boundary are covered. PASS. |
| Product and variant detail | Published detail exposes current public price, stock, safe managed device specifications and public variant attributes; unpublished products return 404. PASS. |
| Comparison | Four-slot current and legacy links, duplicate/malformed bounds, device-spec columns, category changes and owner-approved same-category-only selection are covered. PASS. |
| Zero stock and live availability | Published zero-stock products remain visible; `in_stock` and `out_of_stock` use hold-aware StockLedger projections before pagination. COD hold/cancel and stock changes update Laravel and real Next.js results. PASS. |
| POS write freshness | Fresh protected POS creation/publication, sales and reservations were observed through Laravel API and Next.js with catalogue-version/ETag changes and cache invalidation. PASS. |
| Public minimization | Browser and HTTP evidence excludes IMEI, internal stock-unit identifiers, costs, suppliers and private publication data. PASS. |
| Website modes | Product/category/detail/compare, sitemap and robots behavior is covered through `digital_only`, `commerce_only` and `hybrid`. PASS. |
| SEO and legacy discovery | Canonical product/category URLs, absolute Open Graph URLs, Twitter metadata, Offer URL/availability, six permanent redirects, mode-aware robots and a real 241-product sitemap are covered. PASS for W02; W06 retains editable SEO/media controls. |

## Independent closure check

The current route implementation, source route inventory, prior Git-backed HTTP/Edge/build evidence and the final focused Node suite were independently reconciled. On the unchanged application candidate, `catalogue-legacy-filters`, `catalogue-sitemap`, `compare-selection` and `compare-specs` passed **15/15** on 21-Sep-2026.

**Decision: W02 public catalogue/compare is Complete.** The finite MT-7.5 checklist is now **7 DONE / 20 OPEN of 27 (25.93%)**.
