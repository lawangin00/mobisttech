# MT-6.1 Verification

MT-6.1 - Canonical branding and runtime assets is complete.

## Canonical source

- `C:\mobisttech\brand` is the only target master/reference brand root.
- The eight approved master/reference files were copied byte-for-byte from protected legacy evidence. Their SHA-256 hashes match both protected source repositories and the recorded migration inventory.
- `brand/Logo` and `brand/Wordmark` are the only runtime graphic sources. `brand/Canva` is retained reference-only and is not referenced by runtime code.
- `brand/design-tokens.css` is the canonical shared typography/color/spacing token source.
- `brand/runtime-manifest.json` records canonical master hashes and generated backend/Website runtime hashes.
- `scripts/generate-brand-assets.ps1` regenerates framework-specific runtime derivatives from the root brand masters.

## Runtime mapping

Both backend and Website receive generated:
- full wordmark SVG;
- compact mark SVG;
- print wordmark PNG;
- watermark PNG;
- 512px application icon;
- favicon ICO;
- Apple touch icon;
- 192px and 512px PWA icons;
- copied canonical brand token CSS.

Backend Blade/POS login/POS shell and Website metadata/header use these runtime derivatives. Backend and Website CSS import the generated token copy and use the Instrument Sans preference with system UI fallbacks. No legacy compiled WOFF/TTF build output is treated as a brand master.

## Verification

- Brand consistency regression: PASS 2 tests / 55 assertions.
- Backend focused browser brand acceptance: PASS 1/1 after harness-only readiness/selector corrections. It verifies login wordmark, watermark, authenticated POS shell wordmark, favicon and icon delivery.
- Production Website focused browser brand acceptance: PASS 1/1 after a cold webServer readiness retry. It verifies the rendered canonical wordmark plus favicon, touch/PWA icons and direct asset delivery.
- Runtime-only forbidden-reference scan: PASS, no Brandkit/Canva/protected-source path references.
- Tracked duplicate master Brand Kit scan: PASS, none outside the canonical root brand structure.
- Manifest verification: PASS for all canonical masters, design tokens and all backend/Website runtime derivative hashes.
- Protected legacy repositories remain clean, and all eight source artwork hashes still match the canonical copies.
- Exact browser-fixture residue: zero POS E2E users, MT51 products and MT55 project rows.
- Backend production build and Website typecheck/lint/build gates passed for the canonical brand integration; no performance threshold, security boundary or external provider setting was changed.

No duplicate target master Brand Kit remains and the protected source artwork was not modified.
