# MT-7.2 Legal / Privacy Fact Audit

## Product/data facts checked against implementation

- Identity realms are Admin and Customer only; Team Members share the Admin realm with explicit RBAC/outlet assignments.
- Admin inactivity is 30 minutes with no remember-login; Customer inactivity is 120 minutes with optional capped remember behavior.
- Required application cookies/session/CSRF/device state exist. No non-essential analytics/tracking SDK or tracker was found in the current Website/backend frontend source; therefore Cookie Policy remains conditional, not automatically required/published.
- Customer/POS operational data can include contact/address information and domain-specific identifiers; trade-in/customer workflows can hold sensitive identity fields. Public Website projections are allowlisted and private operational fields are excluded.
- Gmail uses backend-only OAuth with `gmail.send`; encrypted credentials/tokens are not projected. Google Drive/rclone is backend-only.
- Website checkout is fixed to COD/JazzCash/Easypaisa/hosted tokenized card capability. External providers remain unavailable until authentic configuration/adapter readiness. POS tenders are separate.
- Card PAN/CVV/PIN/stripe data are prohibited; schema audit found zero columns matching prohibited secret/card-data names and frontend source contains no access/refresh/client-secret/PAN/CVV/PIN/stripe-data terms.
- Reset/retention implementation preserves evidence/bootstrap according to transactional/business/factory policy and requires permission, recent auth, verified backup and dependency checks.
- Private object storage is not publicly routed; unsafe paths/digests are rejected.

## Policy publication truth

`WebsiteCms` keeps policy records draft/pending by default. Publication requires all of: explicit `owner_approved`, factual review `verified`, and no unresolved decisions. Cookie Policy is modeled as conditional. Software Product publication requires complete Overview/Privacy/Terms content and preserves release/revision history.

The repository contains reference/seed legal material, but reference text and E2E policy fixtures are not treated as owner/legal approval for production. Current owner/legal approval and any remaining product-specific final wording remain release decisions for MT-7.5/FINAL-AUDIT; MT-7.2 does not fabricate approval.

## Result

Implementation facts and publication gates are consistent with the approved privacy/payment/delivery/retention boundaries. No contradiction requiring a public-policy rewrite was verified in this audit; unresolved owner/legal approvals remain explicitly unresolved.