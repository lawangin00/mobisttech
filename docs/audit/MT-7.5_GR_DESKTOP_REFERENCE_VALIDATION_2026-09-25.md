# MT-7.5 25/G-R Desktop mobiST POS Reference Factual Validation

Date: 25-Sep-2026 PKT  
Stable gate: `25/G-R`  
Status: **IN PROGRESS / FACTUAL EVIDENCE BLOCKED**  
Owner decision: `D08=B` — private factual draft only; no public publication.

## Reference integrity

The four preserved seed/reference files still match the canonical hashes recorded in `docs/software-publishing/RECONCILIATION.md`:

- `SOFTWARE_OVERVIEW.md` — `6667c6163dafcce8df802c50822142d733c3fb09b88f5b01fb0543a28e958a7d`
- `PRIVACY_POLICY.md` — `9fe1b40854a793de8d34751ac83038bf96e7961c69f8acf710755f677197533c`
- `TERMS_OF_SERVICE.md` — `65ea5fec790b3ec4312118c1dfddf07210fe3cf88c3fbc54f8ba9c8861ca964c`
- `FAQ.md` — `8f795afae2eb36909d003344cd56be5d77eaa287493f1fe3c39e4de494b5239c`

These files are preserved inputs, not independent proof of the product facts they contain.

## Independent evidence boundary checked

The only explicitly authorized external local source for this Mobisttech project is the protected legacy `C:\mobiST\mobiST-POS` repository, which was inspected read-only at commit `6dc645f60dd9dfd7296a2a77640a6078f3274706`. Its pre-existing dirty working tree was not modified.

That repository is **not** the separately shipped Windows desktop product required by this gate:

- its README identifies a Laravel application served on local HTTP port 8000, with PHP/Composer/Node/Vite runtime and browser/LAN access;
- its backup documentation uses externally configured `rclone` and `mobist-drive:mobiST-Backups`, not evidence of the reference set's claimed integrated desktop `drive.file` OAuth/token-storage behavior;
- its authentication model is Administrator / Super Administrator, not the reference set's claimed Owner / Manager / Salesperson desktop model;
- no authorized evidence in the bound Mobisttech project proves the claimed desktop installer, EULA/manual bundle, Windows x86/x64 acceptance, Version 1.0.0 release date, 7-Day Trial, 1/5/10-year/Lifetime device-bound licensing, hardware recovery/reissue, uninstall semantics or separately shipped desktop release artifact.

The protected legacy web POS therefore cannot be substituted as desktop-product acceptance evidence.

## Claim disposition

| Reference claim family | Current disposition | Required independent evidence |
|---|---|---|
| Product identity / separately shipped desktop release | UNVERIFIED | Exact desktop release/artifact identity and acceptance evidence |
| Version `1.0.0` / 6-Sep-2026 release facts | UNVERIFIED | Verified release manifest/tag/build/version/date |
| Windows 11 x64 / Windows 10 x64+x86 installer/runtime | UNVERIFIED | Installer/build matrix and accepted OS/architecture tests |
| Offline/local-first operational database | UNVERIFIED for the separate desktop product | Desktop runtime/data-store acceptance evidence |
| 7-Day Trial + 1/5/10-year/Lifetime device-bound licensing | UNVERIFIED | Desktop licensing acceptance ledger and release implementation evidence |
| Hardware-change/reinstall/reissue behavior | UNVERIFIED | Desktop licensing/recovery acceptance evidence |
| Shop-owned Google Drive `drive.file` OAuth behavior | UNVERIFIED | Desktop integration implementation + accepted OAuth/backup evidence |
| Local backup/guarded restore | UNVERIFIED for the separate desktop product | Desktop recovery acceptance evidence |
| Owner/Manager/Salesperson roles | UNVERIFIED | Desktop role/permission acceptance evidence |
| Sales/inventory/returns/repair/reporting/document sharing claims | UNVERIFIED as desktop release facts | Desktop release acceptance/manual evidence |
| Bundled User Manual / installer EULA | UNVERIFIED | Actual release package/installer evidence |
| Privacy/Terms/FAQ factual text | PRIVATE REVIEW ONLY | Verified underlying desktop facts plus later G-L owner/legal review |

## Publication decision

D08=B is already binding: **no public mobiST POS Software Product may be published from these references.** A real-product CMS draft must contain only independently verified desktop facts. Because the required desktop release evidence is not available inside the bound Mobisttech project or its explicitly authorized read-only legacy source, creating a factual real-product CMS draft now would require inventing or self-validating facts and is therefore correctly blocked.

Synthetic Software Product fixtures used by G-S remain valid workflow evidence but are not real-product factual acceptance.

## Exact unblock

Provide or explicitly select authoritative evidence for the separately shipped desktop mobiST POS release: exact release repository/artifact/checkpoint plus version/date, installer/OS-architecture acceptance, local-data/offline behavior, licensing/trial acceptance, Google Drive implementation/backup acceptance, role model and bundled manual/EULA facts. Public publication still requires a later explicit decision beyond D08=B and applicable G-L approval.

**Result: 25/G-R remains OPEN [E,H]. No factual claim was fabricated and no product was published.**
