# Canonical mobiST Brand

This directory is the only master/reference Brand source for the target repository.

- `Logo/` and `Wordmark/` contain the approved runtime-source artwork copied byte-for-byte from the protected legacy evidence.
- `Canva/` is retained as reference/source material only and must never be referenced by runtime code.
- `design-tokens.css` is the canonical shared typography/color/spacing token source. The preferred font family is Instrument Sans with a system UI fallback chain; legacy compiled WOFF/TTF build output is not a brand master.
- `runtime-manifest.json` records master hashes and generated backend/Website runtime derivatives.
- `scripts/generate-brand-assets.ps1` regenerates framework-specific vectors, print/watermark PNGs, app/touch icons and favicons from this directory.

Runtime copies are allowed only where an application framework requires them. Duplicate master Brand Kits are not.
