# MT-7.5 W04 remaining settings authority boundary (24-Sep-2026 PKT)

Status: IN PROGRESS, decision boundary only; not a payment-settings implementation or W04 closure.

## Verified current target

- `backend/routes/website-payment-admin.php` exposes masked protected status and separate COD draft/publish routes. `WebsitePaymentAdministration::saveDraft()` strictly permits only the boolean `cod_enabled`; published COD revision does not configure external providers or register an adapter.
- Historical source W04 crosswalk already disposes 52/52 files and 16/16 routes; this checkpoint must not recount them or mislabel partial Admin status as full settings parity.
- Exact-source clean hosted run `35936492171` on `4aeeed3` passed backend, Chromium and final cleanup/clean-worktree gates. This accepts the implemented synthetic/raw-card/checkout gates only; no merchant-owned credential, real provider request/callback/refund or settlement was tested.

## First remaining authorization boundary

Before implementing an Admin nonsecret settings editor beyond `cod_enabled`, agree its exact permissible fields, allowed per-channel labels/instructions/COD bounds, owner-vs-delegate write authority, revision/rollback semantics and impact on existing orders. Provider-specific merchant metadata, credential field allowlists and activation remain separately gated by an approved real vendor contract and explicitly bound payment-owner identity. Do not store secrets in nonsecret revisions, infer owner enrollment from Full Access, enable default-OFF external channels or invent a processor-specific schema. Source `MT-7.5_W04_CREDENTIAL_LIFECYCLE_DESIGN.md` records these dependencies.

Next action: obtain/verify the narrow nonsecret Admin settings/owner authority scope before a settings schema/UI mutation; continue a separately evidenced independent gap only if found. Genuine provider H-02 stays HOLD. W04 / MT-7.5 remain 15/27 DONE, 12 OPEN.