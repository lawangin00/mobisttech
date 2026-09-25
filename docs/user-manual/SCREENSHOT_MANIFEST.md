# MT-7.6 Screenshot Manifest

Status: In Progress
UI source commit for this capture batch: `5cfffba780354fc057eb848a2348e1ff3662363f`
Browser: Microsoft Edge via Playwright, desktop capture at 1440?1000 unless an element-level capture is listed.
Data policy: synthetic/demo data only. Browser-only projections never mutate backend business state. Guarded fixture seeders own and clean their exact rows/files.

## Accepted/captured assets

| ID | File | Pixels | SHA-256 | Route / view | Synthetic authority | Provenance | Evidence |
|---|---|---:|---|---|---|---|---|
| S01 | `S01_admin-sign-in.png` | 1440?1000 | `ac714bf3aeeffb0ccafc5683d6a4659593e07ca6c09f7f52c44e2dfc3f72feab` | /internal/admin/pos/login | Anonymous | PosShell E2E fixture | A1 dedicated terminal PASS + visual QA PASS |
| S02 | `S02_pos-home.png` | 1440?1000 | `7a800e70f28cf35e1a6bb8df6d3b9313c22b4f09611e923f72ac28e38bd697fe` | /internal/admin/pos | E2E Salesperson | PosShell E2E fixture | A1 dedicated terminal PASS + visual QA PASS |
| S03 | `S03_sales-payment-editor.png` | 1440?1000 | `b0c2975480f7927ea56c1400d164c2f81c7e1b93f20ee187012cb3a3b400986f` | /internal/admin/pos/workspace/sales | E2E Transaction Sales | Browser-mocked safe catalogue/quote only | A1 dedicated terminal PASS + visual QA PASS |
| S04 | `S04_split-tender.png` | 1440?1000 | `56f8caf8cfe84dba9bc8e5470238ff3e789767c4c39f4ba0f114535a3e63285e` | /internal/admin/pos/workspace/sales | E2E Transaction Sales | Browser-mocked safe catalogue/quote only | A1 dedicated terminal PASS + visual QA PASS; focused viewport |
| S06 | `S06_inventory.png` | 1440?1000 | `b35805cd09234eb21a38fa65b051911a2c9f2988d895846adaf7c0b847b1aaba` | /internal/admin/pos/workspace/inventory | E2E Inventory Manager | PosShell E2E fixture | A1 dedicated terminal PASS + visual QA PASS |
| S12 | `S12_platform-administration.png` | 1440?1000 | `fefe0dca36d3566d97df6440e80f71ab2ca8b599706f064d58a427779dcecffb` | /internal/admin/platform ? POS configuration | E2E Platform Administrator | Real final Platform response over synthetic DB | A2 terminal PASS + visual QA PASS |
| S13 | `S13_team-members.png` | 1440?1000 | `f0f2250d089122d44e6d69c070b0eb94f98b74f3936d7a08f108f38aee7b2c57` | /internal/admin/platform ? Team & integrations | E2E Platform Administrator | Real final Platform response over synthetic DB | A2 terminal PASS + visual QA PASS |
| S14 | `S14_website-mode.png` | 1440?1000 | `a8a2915f01604385b267f74b308314ffa4f26533ad59ae26d58ef5875c1e8cc8` | /internal/admin/platform ? Website | E2E Platform Administrator | Real final Platform response over synthetic DB | A2 terminal PASS + visual QA PASS |
| S15 | `S15_cms-navigation-footer.png` | 1392?897 | `73e76cfa16ff88bb39db160f37260fdf342b1738937b5532b6b863ca3890dbbc` | /internal/admin/platform ? Website | E2E Platform Administrator | Real final Platform response over synthetic DB | B1 terminal PASS + visual QA PASS |
| S16 | `S16a_website-seo.png` | 1392?260 | `841542ee6de2e8d0d48272ebfcd5e9e96d1d055f03ed80c78d345131b9ee8758` | /internal/admin/platform ? Website | E2E Platform Administrator | Real final Platform response over synthetic DB | B1 terminal PASS + visual QA PASS |
| S16 | `S16b_website-media.png` | 686?1005 | `7396fbe44e7d5c0c101bf9373cb953eac47a10f5b938e3c9f9c2c3fea75dd1f1` | /internal/admin/platform ? Content | E2E Platform Administrator | Real response + browser-only synthetic media projection | B1b terminal PASS + visual QA PASS |
| S17 | `S17_legal-policy.png` | 686?610 | `95edddb53d557bde4a045fc2c0521f031c50824395b6ff7deaedaed15e0aa819` | /internal/admin/platform ? Content | E2E Platform Administrator | Real final Platform response; unapproved policy form only | B1 terminal PASS + visual QA PASS |
| S18 | `S18_website-commerce.png` | 1440?1000 | `355ed27c26297ebd4b82849033359d3a028e7e928116d463210738de7b0af738` | /internal/admin/website-commerce | E2E Protected Owner | Guarded W03 synthetic commerce fixture | Corrected B2 terminal PASS + visual QA PASS |
| S19 | `S19_website-payment-settings.png` | 1440?1000 | `3656dc1b22654b5e23359e7057be3136cadba5e0ff8d330d094b90e005597a04` | /internal/admin/website/payment-settings | E2E W04 Payment Editor | Real fixed-channel settings over synthetic DB | Corrected B2 terminal PASS + visual QA PASS |
| S20 | `S20_digital-operations.png` | 1440?1000 | `ec85f9574011acb34d2eb0cc57aa811a4a30a5589a0e80842f71bd584d9c88b6` | /internal/admin/digital-operations ? Projects | E2E Digital Operations Manager | Browser-only synthetic Digital project/proposal projection | B3 terminal PASS + visual QA PASS |
| S21 | `S21_software-product.png` | 1440?1000 | `ccf3986f640853986726726698f564bce816a29b678581b013f3c9604f2cd03c` | /internal/admin/platform ? Software | E2E Platform Administrator | Real response + browser-only synthetic Software/media projection | B1b terminal PASS + visual QA PASS |
| S22 | `S22_integrations.png` | 1440?1000 | `89ac9301e524c93eed499a709af0ee9ff09f64cbf8880d8ce0333598bdcc3999` | /internal/admin/settings/integrations | E2E Protected Owner | Real final integration status over synthetic DB | Corrected B4 terminal PASS + visual QA PASS |
| S23 | `S23_backup.png` | 848?246 | `6011e5b52b5d2eb88cb10987ab03a52388e461a7d6a722bb39b1cc241899341d` | /internal/admin/settings/integrations ? Backup history | E2E Protected Owner | Guarded exact-owned H01 backup fixture | Corrected B4 terminal PASS + visual QA PASS |
| S24 | `S24a_reset-production-hold.png` | 1392?106 | `c5a3f9a927c0b1c294b6c4fca5cdaefe7aace17edd27e3046b7305c94fe3b03b` | /internal/admin/reset-administration | E2E Reset Administrator | Browser-only safe reset contract; execution disabled | B5 terminal PASS + visual QA PASS |
| S24 | `S24b_reset-dry-run-preview.png` | 1392?452 | `03f8621b985736286e10e6953dc2ac2e78c0d08bea14ccd060ea8195f9a2985c` | /internal/admin/reset-administration | E2E Reset Administrator | Browser-only dry-run preview; no destructive execution | B5 terminal PASS + visual QA PASS |

## Batch evidence notes

- The first combined Batch A run captured S01/S02/S03/S04/S06 successfully, then failed only on a later Platform-tab harness assertion before S12. The harness was split; a dedicated A1 rerun for exactly S01/S02/S03/S04/S06 subsequently completed terminal PASS. Those captures now have both terminal PASS and visual QA evidence.
- Batch A2 (S12-S14) completed terminal PASS after correcting the harness to open the real POS configuration tab and use the current `Website operating mode` heading.
- Batch B initial run: B1, B3 and B5 passed; B2 had a strict duplicate-text locator and B4 leaked a synthetic server session while switching accounts. The login helper now releases the current synthetic Admin session before account switches; corrected B2+B4 terminal PASS.
- S16b/S21 were recaptured through B1b because the real base fixture had no media/software records. B1b preserves the real current Platform response and injects only safe browser-side media/Software projections; terminal PASS.
- Original PNGs were visually inspected from local thumbnails. No real customer/CNIC/IMEI/payment/provider/OAuth/private-client data is present. S04 is intentionally a focused split-tender viewport; other captures retain the context needed for their procedure.

## Remaining capture IDs

Pending: **S05, S07, S08, S09, S10, S11, S25, S26, S27, S28, S29, S30**.

Final MT-7.6 closure still requires completion of every applicable pending capture/procedure, DOCX/PDF mirrors, content parity and every-page visual QA.
