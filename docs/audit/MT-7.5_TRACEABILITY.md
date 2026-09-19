# MT-7.5 - Source-to-target parity evidence matrix

Status: Active review; no whole-product parity or production approval is asserted. This matrix supplements, but does not replace, `docs/migration/FEATURE_PARITY_REGISTER.md`. The protected source repositories stay read-only. Each `Evidence` entry identifies existing target artifacts to verify, not a completed parity gate. A source regression result is not target acceptance.

| Source family | Target evidence to verify | Unclosed independent acceptance |
|---|---|---|
| P01 identity/outlets | `IdentitySecurityTest`, `TeamMemberSessionSecurityTest`, `pos-shell.spec.ts` | Cross-role/outlet, imported identity, reset/session and actual UI journey against the source parity gate. |
| P02 catalogue/master data | `ProductMasterDataTest`, `PosTransactionInterfaceTest`, `pos-transaction.spec.ts` | Protected categories, option usage/deletion and original-source mapping comparison. |
| P03 stock/acquisition | `InventoryConcurrencyTest`, `StockMigrationTest`, `pos-stock-control.spec.ts` | Full source movement/IMEI equivalence, UI/stock reconciliation and synthetic migration mapping. |
| P04 sale/returns | `SalesOperationsTest`, `SalesMigrationTest`, `pos-transaction.spec.ts` | Negative sale/return, exact totals, saved Invoice and history cross-outlet output. |
| P05 warranty/claims | `WarrantyClausesTest`, `ClaimOperationsTest`, `pos-customer-reporting.spec.ts` | Expiry boundary, historical clauses, claim document and role UI evidence. |
| P06 reporting/documents | `DocumentReportingServicesTest`, `PosCustomerReportingInterfaceTest`, `pos-customer-reporting.spec.ts` | Thermal/A4 real PDF/print, optional email, assisted WhatsApp, CSV, filters and role-bound totals. |
| P07 POS presentation | `PlatformAdministrationInterfaceTest`, `platform-administration.spec.ts` | Complete original-source settings/media/preview/rollback and rendered UI parity. |
| P08 backup/operations | `OperationalRecoveryTest`, `docs/deploy/MT-7.4_VERIFICATION.md` | Isolated recovery evidence accepted at MT-7.4; authentic live-provider actions remain separately gated. |
| X01 reservations | `OrderPaymentTransactionsTest`, `InventoryConcurrencyTest` | Concurrent expiry/confirm/release and end-to-end original-source reservation equivalence. |
| W01 customer/Admin identity | `IdentitySecurityTest`, `customer.spec.ts`, `platform-administration.spec.ts` | Signed-link, admin profile, historical order, role and password recovery UI parity. |
| W02 public catalogue | `ApiContractTest`, `storefront.spec.ts` | Original category/variant/price/filter/redirect and POS-to-public freshness comparison. |
| W03 cart/orders/reviews | `OrderPaymentTransactionsTest`, `CustomerEngagementTest`, `customer.spec.ts` | Guest/cart recovery, purchase-only review/moderation and admin status/export across UI. |
| W04 fixed Website payments | `OrderPaymentTransactionsTest`, `checkout.spec.ts` | COD + JazzCash + Easypaisa + hosted card exhaustive matrix; authentic gateway acceptance remains HOLD. |
| W05 services/project quotes | `DigitalServiceLeadsTest`, `ClientProjectServicesTest`, `client-project.spec.ts` | Quote ownership/expiry/payment, consent/upload and full project status lifecycle UI. |
| W06 managed CMS/nav/SEO | `WebsiteCmsTest`, `dynamic-content.spec.ts`, `platform-administration.spec.ts` | All source managed sections/navigation and publication/rollback/SEO parity. |
| W07 themes/media/recovery | `WebsiteCmsTest`, `OperationalRecoveryTest`, `platform-administration.spec.ts` | Source settings, safe-media replacement, secret mismatch and responsive visual coverage. |
| W08 integration health/jobs | `UnifiedAdminGoogleIntegrationTest`, `docs/deploy/MT-7.4_VERIFICATION.md` | Non-production readiness accepted; authentic external provider activation remains HOLD. |
| B01 brand | `BrandRuntimeAssetTest`, `brand-assets.spec.ts` (both UI suites) | MT-6.1 completed independently; no reopening without a verified regression. |
| C01 Windows Control | `docs/control/` acceptance and MT-6.3 ledger evidence | MT-6.3 completed independently; no unrelated local/source process changes. |
| Q01 regression/migration | `docs/migration/MT-7.1_VERIFICATION.md`, `docs/audit/MT-7.2_*`, current CI | MT-7.5 final consolidated gates and verified closure of all other families. |
| F01 schema/build | `SharedSchemaTest`, current clean-checkout CI | MT-7.3 completed independently; exact current candidate must remain reproducible. |
| H01 historical docs | `SOURCE_FILE_INVENTORY.json`, `SOURCE_SYMBOL_INVENTORY.json`, current runbooks | Exhaustive original-feature mapping and replacement/retirement evidence; stale source instructions must not regain authority. |

## Cross-requirement gates (not accepted by the matrix above)

| Approved source | Test/implementation evidence to join | Still required |
|---|---|---|
| `PROJECT_REQUIREMENTS_ADDENDUM_v1.1.md` | `REQUIREMENTS_ADDENDUM_v1.1_RECONCILIATION.md`, `AddendumBoundaryTest`, `DigitalServiceLeadsTest`, `ClientProjectServicesTest` | Reconcile every clause against real backend+UI/mode/performance behavior; do not treat a roadmap owner alone as acceptance. |
| `PROJECT_REQUIREMENTS_DOCUMENTS_PAYMENTS_LEGAL_MANUAL_v1.0.md` | `DocumentReportingServicesTest`, `PosPaymentTest`, `CashSessionTest`, `OrderPaymentTransactionsTest`, both browser suites | Confirm explicit Invoice/Warranty document actions; POS split/closing/payment mix vs the fixed four Website channels, refund security and accurate legal links. |
| `PROJECT_REQUIREMENTS_SOFTWARE_PRODUCT_PUBLISHING_v1.0.md` | `WebsiteCmsTest`, `ApiContractTest`, `PlatformAdministrationInterfaceTest`, `platform-administration.spec.ts`, `dynamic-content.spec.ts` | Join signed-in create→preview→publish→release→revision→rollback to public route family in a real browser; audit more than one software record. |
| mobiST POS Overview/Privacy/Terms/FAQ reference set | `docs/reference/mobiST POS-IMS/` plus the reusable Software Product workflow | Validate actual separately shipped Windows POS behavior and document-specific data flows; owner/legal approval before any real public publication. |
| Legal-policy/license choice | `docs/audit/MT-7.2_LEGAL_PRIVACY_AUDIT.md`, current typed CMS publication gates | Root application ownership/license decision, final truthful Privacy/Terms/Warranty/Returns text, effective dates and actual owner/legal sign-off remain unresolved. Do not infer approval from test fixtures. |

**Release boundary:** `MT-7.5` must remain In Progress while any required family/cross-requirement gate lacks fresh evidence or an owner/legal decision is pending. HOLD actions are not silently reclassified as tested: live Gmail consent/send, provider payments/refunds, real-data cutover and production restore/provisioning remain independently gated.
