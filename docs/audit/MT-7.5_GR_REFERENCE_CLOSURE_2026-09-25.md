# MT-7.5 25/G-R mobiST POS Reference Factual Reconciliation Closure

Date: 25-Sep-2026 PKT  
Stable gate: `25/G-R`  
Status: **DONE DEVELOPMENT / PRIVATE FACTUAL RECONCILIATION ONLY**  
Owner decision: `D08=B` — private factual draft/reconciliation allowed; public publication is not authorized.

## Authoritative desktop-product source

The separately shipped Windows desktop product is independently identified by the owner's canonical private GitHub repositories:

- Customer application: `lawangin00/mobist-pos-ims`
- Private paid-license / release operator tooling: `lawangin00/mobist-pos-license-generator`

This is distinct from the protected legacy Laravel/browser source `C:\mobiST\mobiST-POS`, which remains irrelevant as desktop-product evidence.

Authoritative customer-repository evidence inspected read-only on the current default branch:

- `README.md` blob `e04bef4d20a78de6f44f4890a875968c8a7b2e95`
- `docs/PROJECT_IMPLEMENTATION_STATUS.md` blob `e1e4455969431f43ec7a54d295aad562d66bd363`
- `docs/PROJECT_SOURCE_OF_TRUTH.md` blob `a6c65674e44efea05ed12acf75f3f18a45ec8681`

## Reference-source identity

The four preserved Mobisttech reference files are exact content matches of the desktop repository's current canonical customer-facing sources:

| Mobisttech reference | Desktop canonical source | Git blob | Result |
|---|---|---|---|
| `SOFTWARE_OVERVIEW.md` | `docs/customer/SOFTWARE_OVERVIEW.md` | `a01dc85026f62423a2057d984b13b95791fc1d32` | Exact content match |
| `PRIVACY_POLICY.md` | `docs/customer/PRIVACY_POLICY.md` | `40e5a7fa6def25bb95af0e641439c59e4c8164c0` | Exact content match |
| `TERMS_OF_SERVICE.md` | `docs/customer/TERMS_OF_SERVICE.md` | `27209efc79dd0d6116a4c0f14af9fdf7d9e4c13b` | Exact content match |
| `FAQ.md` | `docs/customer/FAQ.md` | `3c942ba980064f5e186021d7b87e8531b58f3c9c` | Exact content match |

The previously preserved SHA-256 hashes in `docs/software-publishing/RECONCILIATION.md` therefore remain valid review-input integrity evidence.

## Desktop factual validation

The desktop implementation/release ledger independently supports the reference families:

- **Product/runtime:** Windows-native .NET 10 / C# / WPF modular monolith, one local EF Core/SQLite operational database, self-contained offline/local-first operation.
- **Roles:** Owner, Manager and Salesperson are implemented with local authentication and least-privilege role boundaries.
- **Retail workflow:** inventory, purchases, sales, split payments, immutable invoices/PDF, explicit WhatsApp/email sharing, returns/exchanges, expenses, dashboard and reporting are completed roadmap points.
- **Backup:** verified local backup/guarded restore plus optional shop-owned Google Drive backup.
- **Google Drive:** installed-desktop browser OAuth, loopback callback, PKCE, least-privilege `drive.file`, DPAPI-protected refresh token, no central mobiST backup database/store.
- **Licensing:** 7-Day Trial; paid 1 Year / 5 Years / 10 Years / Lifetime; stable privacy-limited Device ID; ECDSA P-256 signed offline paid licenses; same-device reactivation; explicit renew and hardware-change reissue/recovery semantics.
- **Private issuer:** `lawangin00/mobist-pos-license-generator` independently identifies issue / renew / reissue commands and confirms that the generator never creates trials.
- **Installer/EULA:** NSIS architecture-aware `mobiST-POS-Setup.exe`, separate installer EULA, Program Files installation, standard uninstall and preserved business/licensing state.
- **Windows matrix:** release acceptance covers Windows 11 x64, Windows 10 x64 and actual Windows 10 x86/32-bit under the documented support/compatibility distinction.
- **Manual:** final offline User Manual is bundled and verified from the installed application.
- **V1 release identity:** FINAL-AUDIT closure on 7-Sep-2026 records exact signed App Version `1.0.0`, Trial Generation `1`, architecture `both`, signed SHA-256 `0AD3FA855CA592775B1968796A514849BE5BDEED39EFD37C8E24A0D38A40A539`, plus clean Windows 11 acceptance and completed 45/45 V1 roadmap.
- **Later maintenance:** the same authoritative ledger records later signed maintenance releases, including completed `1.0.6` work on 16-Sep-2026. Therefore the seed documents' `Version 1.0.0` line is valid as the approved V1 document/release context but must **not** be represented as the current/latest release version without a fresh release-record reconciliation.

## D08=B publication disposition

D08=B is now actionable because the missing independent desktop evidence exists.

Allowed:
- privately reconcile the canonical Overview / Privacy / Terms / FAQ text into the managed Software Product workflow;
- preserve `mobist-pos` as the planned canonical slug;
- preserve historical V1 `1.0.0` release facts when clearly identified as historical;
- use independently verified desktop facts in protected preview/review.

Not authorized:
- public publication of the real mobiST POS product;
- presenting `1.0.0` as the latest/current release;
- inventing a download URL or exposing a private installer;
- publishing a current release date/version without a separately reviewed current release record;
- changing Privacy/Terms contractual meaning without D10 / G-L owner/legal approval;
- changing production domain/OAuth/provider state.

No real Website Software Product was published and no desktop repository/artifact was mutated.

**Result: 25/G-R DONE DEVELOPMENT. MT-7.5 advances to 25/27 DONE, 2 OPEN: 26/G-L and final 27/Q01.**
