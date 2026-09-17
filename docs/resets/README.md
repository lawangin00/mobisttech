# MT-3.8 Guarded Data Reset Services

MT-3.8 adds backend-only reset authority for Transactional Data Reset, Business Data Reset, and Factory Reset.

The service requires exact reset permission, recent Admin re-authentication, a current dry-run preview, typed level confirmation, zero unresolved dependency/operational barriers, and an automatic verified encrypted backup before any deletion.

Reset scopes are explicit domain sets backed by `ResetDomains` and the existing exhaustive `ResetRetention` classifier. Transactional/business levels allow approved domain selection; Factory Reset requires the complete approved factory scope and preserves the minimum access/system bootstrap.

Preview output records per-domain record/file counts, preservation groups, dependency barriers, nullable selected-scope cycle breaks, schema/code hashes, and the required confirmation text. A changed scope/count/hash makes the preview stale and blocks execution.

Deletion uses FK-aware child-before-parent ordering. Retained child references, unsafe payment states, active stock holds, open cash sessions, unknown tables, non-nullable FK cycles, and unapproved retention actions block execution rather than weakening integrity.

Private objects referenced by selected rows are encrypted and verified before database deletion. Source cleanup happens only after the DB transaction commits; cleanup failures remain retryable as `cleanup_pending` without rerunning destructive DB deletion.

`reset_operations`, backup evidence, audit evidence, Admin/bootstrap identities, permissions, canonical business profile, integrations, master bootstrap data, and recovery evidence survive as applicable. No `migrate:fresh`, indiscriminate truncation, schema drop, arbitrary SQL, shell command, or public reset route was introduced.

Destructive execution is enabled only in the `testing` environment. Production/cutover authorization remains a separate HOLD-controlled gate. UI/admin workflow remains assigned to MT-4.7.
