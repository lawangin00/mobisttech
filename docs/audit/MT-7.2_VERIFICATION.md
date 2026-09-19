# MT-7.2 Verification

MT-7.2 - Security, performance and resilience audit is complete.

## Security and resilience

- Focused audit suite: PASS 85 tests / 2,342 assertions across identity/session/RBAC, Gmail isolation, document authorization/truth, POS and Website payment negative paths, MySQL concurrency, queue/cache outage handling, private storage, guarded reset/recovery, CMS and API boundaries.
- Full backend regression after all MT-7.2 changes: PASS 253 tests / 7,673 assertions.
- Testing schema contains zero columns matching prohibited PAN/CVV/PIN/stripe-data/OAuth/provider-secret field-name patterns.
- Tracked high-entropy secret-value scan: NONE.
- Backend/Website frontend forbidden secret/card-data term scan: NONE.
- Recent final closure window Laravel testing ERROR/WARNING scan: NONE.

## Website performance

Initial hybrid mobile LCP was 2,524 ms, 24 ms above the fixed 2,500 ms budget. The budget was not changed.

Verified cause: the homepage independently fetched the business profile even though the canonical root storefront context already contained that projection. Home now reuses `readStorefrontContext()`.

Post-remediation Playwright mobile production evidence:
- hybrid: LCP 1,952 ms; CLS 0; no qualifying >=16 ms interaction event; TTFB 1,881 ms.
- digital_only: LCP 1,272 ms; CLS 0; no qualifying >=16 ms interaction event; TTFB 1,228 ms.
- commerce_only: LCP 1,944 ms; CLS 0; no qualifying >=16 ms interaction event; TTFB 1,873 ms.

Mobile Lighthouse 13.5.0:
- hybrid Performance 94; LCP 1,515 ms; CLS 0.
- digital_only Performance 95; LCP 1,508 ms; CLS 0.
- commerce_only Performance 94; LCP 1,507 ms; CLS 0.

Full Website production regression after remediation: PASS 10/10.

## Browser and Windows lifecycle resilience

Default POS/admin Playwright initially exposed two independent harness/lifecycle conditions rather than product authorization defects:

1. A force-stopped Vite process could leave `backend/public/hot`, making later built-asset Laravel runs point at dead HMR port 15173. `mobiST Control` now removes the marker whenever canonical Backend Vite is confirmed offline, and the default Playwright setup removes it because that suite intentionally uses built assets. Focused login/brand acceptance and live Control Start/Stop marker lifecycle passed.
2. The brand browser test left an authenticated `e2e-sales` desktop session active. Later tests correctly hit the real POS second-desktop device limit. The brand test now guarantees logout in `afterEach`, preserving the security policy rather than weakening it.

Corrected full default POS/admin Playwright: PASS 10/10 with canonical teardown.

## Build, style and platform gates

- Backend TypeScript: PASS.
- Backend Vite production build: PASS.
- Website TypeScript: PASS.
- Website ESLint: PASS.
- Website Next.js 16.3.3 optimized production build: PASS.
- mobiST Control console acceptance build: PASS.
- mobiST Control WinForms build: PASS.
- Full Laravel Pint: PASS 240 files after normalization of the exact three style-only findings from the prior scan.
- Composer validation: valid. The only warning is absence of a project license, which is intentionally unresolved pending explicit owner choice.
- Current exact testing MySQL verifier: PASS at `mobisttech_test:13306`, MySQL 8.4.11, UTC/strict, 170 tables / 2,104 columns / 364 FKs / 817 indexes, schema SHA-256 `e092af61d6a36c10df55e04782bf59c101f0beae11029d5842c531f6adee70a3`, zero unexpected business rows, 316 canonical seed rows.

## Runtime/schema correction found by audit

Four additive target-local migrations were pending at audit start (customer engagement, recovery guards, guarded reset and API indexes). Static and pretend review proved their `up()` paths additive-only. They were applied only to target `mobisttech_local`; all current local migrations are now Ran and `foundation:check` passed. Protected source databases/repositories were not touched.

## Dependency, notice and legal/privacy audit

- Removed inherited Laravel-starter `license: MIT` from `backend/composer.json`; dependency/framework metadata must not be represented as the mobiST application license.
- Root application license remains an explicit owner decision. No proprietary/open-source choice was fabricated.
- `NOTICE.md` and `docs/audit/MT-7.2_DEPENDENCY_LICENSE_NOTICE_AUDIT.md` record the current third-party notice/distribution posture, including Sharp/Apache-2.0, libvips LGPL-3.0-or-later and caniuse-lite CC-BY-4.0 obligations.
- `docs/audit/MT-7.2_LEGAL_PRIVACY_AUDIT.md` records actual data/payment/session/publication facts. Policy publication remains gated by owner approval, verified factual review and zero unresolved decisions; Cookie Policy remains conditional.

## Final residue and isolation

Canonical cleanup seeders were rerun idempotently. Exact testing residue checks are all zero for:
- POS E2E admins, roles and outlets;
- MT-5.2 customer user/loyalty fixture;
- MT-5.1 Website products/listings;
- MT-5.4/MT-5.5 digital service/page/software fixtures;
- migration runs/maps/quarantine;
- reset operations and backup/restore rehearsals.

Aggregate checked residue total: 0.

Final runtime state:
- `backend/public/hot`: absent.
- target listeners 18080/15173/13000: absent.
- protected POS source repo: clean at `6dc645f60dd9dfd7296a2a77640a6078f3274706`.
- protected Website source repo: clean at `5d7177d457a448c392b0cf3d8c244ee33a715220`.

## Result

MT-7.2 security, performance and resilience acceptance is complete with no threshold waiver, no security-policy weakening, no live provider activation and no protected-source mutation. Q01 remains In Progress only because MT-7.3 and MT-7.5 are still pending.