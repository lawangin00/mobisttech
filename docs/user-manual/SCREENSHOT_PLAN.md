# MT-7.6 Safe Screenshot Capture Plan

Status: In Progress  
Purpose: controlled current-product screenshot inventory for `USER_MANUAL.md`.  
Data rule: synthetic/demo data only; no real customer, CNIC, IMEI, payment secret, OAuth token, provider credential, private client file or private source data.

| ID | Manual area | Surface / route | Required view | Minimum authority | Capture status |
|---|---|---|---|---|---|
| S01 | Sign-in | Admin login | Admin sign-in | none | Captured / terminal PASS / visual QA PASS |
| S02 | POS home | /internal/admin/pos | Active outlet + permission-aware navigation | assigned Admin | Captured / terminal PASS / visual QA PASS |
| S03 | Sales | POS / Sales | Cart + authoritative totals/payment editor | shop.sales | Captured / terminal PASS / visual QA PASS |
| S04 | Split tender | POS / Sales | multiple tenders + Remaining | shop.sales | Captured / terminal PASS / visual QA PASS |
| S05 | Invoice | Invoices & customers | historical invoice + Preview/Print/Save/Email/WhatsApp | shop.invoices | Pending |
| S06 | Inventory | POS / Inventory | product/unit/device configuration | shop.inventory | Captured / terminal PASS / visual QA PASS |
| S07 | Procurement | POS procurement interface | supplier/PO/receiving | shop.procurement | Pending |
| S08 | Warranty | POS / Warranty | eligible sale intake | shop.warranty | Pending |
| S09 | Claims | POS / Claims | claim lifecycle/history | shop.claims | Pending |
| S10 | Cash/closing | POS / Operations | cash session/day closing | shop.cash | Pending |
| S11 | Reports | POS / Reports | outlet report + payment mix | reports.view | Pending |
| S12 | Platform Admin | /internal/admin/platform | header + protected configuration cards | scoped Admin | Captured / terminal PASS / visual QA PASS |
| S13 | Team Members | Platform Administration | role/outlet assignment | team-members.manage | Captured / terminal PASS / visual QA PASS |
| S14 | Website mode | Platform Administration | mode draft/preview/publish | website.settings/manage mode permissions | Captured / terminal PASS / visual QA PASS |
| S15 | CMS/navigation | Platform Administration | managed navigation + footer | website.navigation.manage | Captured / terminal PASS / visual QA PASS |
| S16 | Website media/SEO | Platform Administration | media + SEO controls | website.media/seo.manage | Captured (2 PNGs) / terminal PASS / visual QA PASS |
| S17 | Legal policy | Platform Administration | policy draft/review state; no final unapproved text | website.content.manage | Captured / terminal PASS / visual QA PASS |
| S18 | Website commerce | /internal/admin/website-commerce | order/review admin | website.orders.manage | Captured / terminal PASS / visual QA PASS |
| S19 | Payment settings | Website payment settings | four fixed channels, external OFF state | website.payments.manage | Captured / terminal PASS / visual QA PASS |
| S20 | Digital Operations | /internal/admin/digital-operations | leads/projects/proposals | relevant digital permissions | Captured / terminal PASS / visual QA PASS |
| S21 | Software Product | Platform Administration | private software preview/release workflow | content/publish permissions | Captured / terminal PASS / visual QA PASS |
| S22 | Integrations | /internal/admin/settings/integrations | safe masked/disconnected state | integration permission | Captured / terminal PASS / visual QA PASS |
| S23 | Backup | protected backup interface | history/create/download/delete controls | backups.manage | Captured / terminal PASS / visual QA PASS |
| S24 | Data Reset | /internal/admin/reset-administration | dry-run preview + warning | system.reset.preview | Captured (2 PNGs) / terminal PASS / visual QA PASS |
| S25 | Customer Website | /products + product detail | public catalogue/detail | public | Pending |
| S26 | Customer checkout | /cart + /checkout | cart + four-channel choice with external channels OFF where applicable | public/customer | Pending |
| S27 | Customer account | /account | own orders/projects only | synthetic Customer | Pending |
| S28 | Digital Website | /services + /enquiry | published service/enquiry | public | Pending |
| S29 | Software public | /software/{slug} | Overview + releases route | public synthetic/published fixture | Pending |
| S30 | mobiST Control | Windows utility | Status + Start/Stop/Start All controls | local operator | Pending |

## Current capture checkpoint

- **18/30 target IDs captured** as **20 PNG assets**.
- Terminal PASS + visual QA: **all 18 currently captured target IDs** (S01-S04, S06, S12-S24).
- S01/S02/S03/S04/S06 dedicated split rerun: terminal PASS; the earlier unrelated Platform assertion no longer leaves an evidence gap.
- Remaining: **S05, S07, S08, S09, S10, S11, S25, S26, S27, S28, S29, S30**.
- Exact file hashes/provenance are in `SCREENSHOT_MANIFEST.md`.

## Capture rules

1. Use the final canonical repository and current built UI, never protected source/legacy screenshots.
2. Use safe synthetic identities and non-sensitive fixture values.
3. Crop only incidental desktop chrome; do not crop away warnings, active outlet, route context or status needed to understand the procedure.
4. Redact nothing by painting over real data: if a screen contains real/private data, do not capture it. Recreate the view using synthetic data instead.
5. Keep browser zoom at 100% unless a responsive/mobile capture explicitly requires another viewport.
6. Capture current desktop view plus mobile view only where the manual procedure materially differs.
7. Store controlled images under `docs/user-manual/assets/` using `S##_short-description.png`.
8. Record the source commit and route for each accepted screenshot in the final screenshot manifest.
9. Do not show raw provider credentials, OAuth tokens, payment secrets, PAN/CVV/PIN, private customer evidence or private source files.
10. A screenshot is evidence of UI state only; provider/legal/production approval must still come from its authoritative external gate.
