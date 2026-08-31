# Source baseline aur migration risks

Date: 2026-08-31. Scope: read-only initialization review; full feature parity audit abhi MT-1.1 mein pending hai.

## Verified references

| Source | Local path | Pinned main commit |
|---|---|---|
| lawangin00/mobiST-POS | C:\mobiST\mobiST-POS | c61e47394e7b3db8a49cfe443c85b63835febc9b |
| lawangin00/mobiST-Website | C:\mobiST\mobiST-Website | 04e7c49518f9f11f60c83ad44f9f4e2fd2539066 |

Read-only live `ls-remote` checks ne dono commits ko remote `main` se match kiya. Dono working trees clean the. Koi fetch/pull, source test/build, migration, provider call, source commit/push ya source runtime mutation nahi ki gayi. Full tracked-file fingerprints `docs/SOURCE_SNAPSHOT.json` mein hain.

Dono repos ki identical control files ke SHA-256:

- `docs/PROJECT_IMPLEMENTATION_STATUS.md`: `d40f3b94647b41050b9eb5483f5459425fe3859a808faba0810608f8f0cd19ae`
- `docs/DYNAMIC_PLATFORM_SOURCE_OF_TRUTH.md`: `cc3cb3f31f7206ee8d9359b7455dee4fe2c3531b34aa14b5e9ff41bb24dbd8f4`
- `docs/DYNAMIC_PLATFORM_GAP_ANALYSIS_AND_ROADMAP.md`: `3a3003c057e49b4d2b5f47da111d481524b372a8eeddabc737be3cfd28b0fac4`

Source ledger aur checked roadmap Dynamic Platform 62/62 complete, final point `DP-X.7 - Dynamic Platform final sign-off and documentation`, aur 31 August FULL-AUDIT reconciliation record karte hain. Historical ledger reports: POS 202 tests / 2,695 assertions; Website 236 / 2,806. Yeh is naye project mein dobara run kiye hue results nahi hain.

## Actual code observations

- Dono Composer manifests Laravel `^13.17` aur PHPUnit `^12.5.12` use karte hain; POS mein Sanctum hai. Source PHP constraints `^8.3` hain. Fresh migration toolchain actual locked package requirements se verify hoga, README ke version claims se nahi.
- Dono applications abhi Laravel/Blade/Vite hain. Existing React/Inertia POS ya Next.js Website ka claim nahi. New frontend conversion required hai.
- POS models/services mein StockUnit, StockMovement, StockAcquisition, ProductImei, Sale, Invoice, Claim, WebsiteOrderAllocation, reservation/confirmation/release, settings, branding, backup aur restore maujood hain.
- Website mein Product, User, Order/OrderItem, Payment, ProductReview, ServiceRequest, ProjectQuote, managed pages/navigation/media, configuration revisions, verified payments, COD collection aur POS sync/outbox services maujood hain.
- Existing architecture separate Laravel databases aur narrow HTTP synchronization use karti hai. Naya Goal is boundary ko aik master MySQL architecture se replace karta hai. Yeh purane sources ko restructure karne ki authorization nahi.
- Dono `mobiST Control Center/MobiSTControlCenter.cs` copies ka SHA-256 identical hai: `bbf54a8db8ef3e871894f9f543555b61007b7dca4fbbb86cd2ea7f19b6a50fcd`. Source Control hard-coded old paths aur Laravel ports 8000/8001 use karta hai. New Next.js lifecycle, safe process ownership aur new paths migrate karne honge.
- Dono roots mein `Brandkit - mobiST` hai. Brand approval, exact duplicates aur runtime dependencies MT-1.1/MT-6.1 mein asset-by-asset verify hongi; folder naming se approval assume nahi karna.

## Evidence discrepancies aur risks

Website README ka current implementation overview aur old roadmap ke original gap-analysis paragraphs initial-stage snapshots hain. Baad ke checked points aur ledger unse newer hain. In stale explanatory sections ko current missing-functionality list na banayein; source files unchanged rakhein aur naye parity register mein actual code se reconcile karein.

Old Dynamic Platform Source ka separate-app requirement old-stage context hai. Naye project mein approved Goal ki single-backend/single-master architecture authoritative hai. Source history ka completed marker new target ki testing ko replace nahi karta.

Identity collisions, duplicate product/order records, private inventory fields, history snapshots, encrypted credential key dependencies, reservation races, provider idempotency, public cache invalidation aur rollback main migration risks hain. Full schema/content/route inventory, exports aur tests abhi nahi hue; MT-1.1/MT-1.2 unhein resolve karenge.

## Protected operational state

Source `.env`, private database contents, API/payment secrets aur customer records is review mein read/export nahi kiye. Source tests/builds write caches kar sakte hain, is liye future characterization sirf isolated copies mein chalegi. Copied jobs/integrations initially disabled/sandboxed honge. Production hosting aur provider holds as-is preserve honge.
