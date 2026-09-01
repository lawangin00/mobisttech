# Unified Admin and Google Integrations Reconciliation

## Authority and supersession

`docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md` is the byte-preserved approved requirement supplied on 2026-09-01. Its SHA-256 is `76fa2f903fa6e3e07912cfcdb76cb76e10f1ea1d8fd0331591234fd07f5bb458`.

The requirement prospectively supersedes the MT-2.2 target model that implemented separate POS Admin, POS Superadmin and Website-admin credential providers, and it supersedes SMTP/Gmail App Password as the primary email setup. Historical MT-2.2 documentation and verification remain unchanged evidence of what existed at that checkpoint. The source `super_admins`, Website `users.is_admin/admin_role`, old reset tables and source terminology remain migration provenance only; they are not current credential providers or user-facing identity types.

The new dependency-ordered point is `MT-2.18 - Unified Admin identity and Google integrations remediation`, inserted after MT-2.6 and before MT-2.7. MT-2.7 now depends on MT-2.18 because order/payment notifications and administration must not build on superseded identity or email assumptions.

## Structural decisions

| Requirement | Current authority | Decision |
|---|---|---|
| One administrative credential | `admins`, `Admin`, `admin` guard/broker | Retain the strongest existing POS Admin service and expand explicit POS/Website/integration permissions. Remove Superadmin and Website-admin runtime providers/routes/models. |
| No privilege merge by email | `admin_identity_mappings`, `AdminIdentityReconciler` | Quarantine every legacy administrative credential during generic import. Mapping requires exact source identity, target Admin, permission snapshot, source credential digest, reason and verifying Admin. |
| Customer isolation | `users` customer guard and `customers` ownership | Keep the customer realm separate. Dormant historical Website admin columns never grant access. |
| Session/reset security | one Admin guard, broker and reset path | Password reset/change increments `auth_version`, rotates remembered credentials and revokes Admin device sessions. Removed realms return 404. |
| Canonical business identity | singleton `business_profiles` row | `mobiST Technologies`, `mobisttech@gmail.com`, and `https://mobisttech.com` drive the public profile API, Website metadata/contact/footer, invoice snapshots, Gmail sender and reset URLs. Historical snapshots remain unchanged. |
| Gmail | Laravel backend OAuth + Gmail API | Use only `gmail.send`, offline access, state plus PKCE, exact account verification, encrypted tokens, backend MIME send, test-before-connected and safe reconnect/disconnect. SMTP/App Password is not the normal path. |
| Google Drive | Laravel backend OAuth + private rclone runtime/config | Use `drive.file`, exact account verification, private portable config, read/write/delete validation, backend logical backups, schedule/retention, and safe disconnect without deleting archives. |
| Deployment | `tools/deploy` provisioning | Detect or optionally install rclone, prepare OS-private configuration, and keep binary/code/secrets separate on Windows and Linux. |

## Boundary reconciliation

- The existing private Windows `mobisttech-drive:` remote was detected and a temporary object passed write/read/delete cleanup. `mobist-drive:` and all previous-project backup archives remained untouched.
- Google OAuth application client IDs/secrets remain deployment secrets. No real value is committed, returned to the frontend or recorded in evidence.
- An environment without OAuth configuration or authorization remains Not Connected. Authentic Google consent and production verification require approved deployment secrets and Google-side actions; tests use network fakes and do not claim a live Gmail connection.
- The previously generated Gmail App Password is neither requested nor revoked. It remains outside project code/config/evidence as a temporary administrator-held fallback.
- MT-3.1 still owns full document/template migration, MT-3.3 broader backup/integration recovery, MT-4.1/4.4 complete administration shells, and MT-7.4 final production deployment acceptance. MT-2.18 supplies the superseding identity/integration authority those points must consume.

## Roadmap and historical evidence

The Source of Truth and roadmap advance to v1.7. The roadmap adds one point and changes MT-2.7's dependency; no completed point is renumbered or rewritten. The live ledger records MT-2.18 completion separately. The same-basename DOCX is regenerated once from the canonical Markdown and visually verified after all structural edits.
