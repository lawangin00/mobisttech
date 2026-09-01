# MT-2.18 Unified Admin Identity and Google Integrations

This checkpoint implements the approved superseding requirement preserved at `docs/PROJECT_REQUIREMENTS_UNIFIED_ADMIN_GOOGLE_v1.0.md`. Full governance reconciliation is in `RECONCILIATION.md`; Google/rclone production obligations are in `GOOGLE_PRODUCTION_READINESS.md`.

Runtime identity now has exactly two credential realms: Customer and Admin. One Admin password/reset/session works across POS operations and Website administration. Every POS, Website, integration, backup and outlet capability is an explicit permission/assignment on that Admin. Removed Superadmin/Website-admin routes and providers cannot authenticate. Historical source identities require `AdminIdentityReconciler`; matching email never merges permissions.

The singleton business profile is the current authority for `mobiST Technologies`, `mobisttech@gmail.com`, and `https://mobisttech.com`. The public API and Website consume it; sales retain a canonical sale-time snapshot. Gmail sends proper multipart text/HTML MIME with the canonical From/Reply-To identity. Password recovery sends only a secure expiring link and never an existing password.

Google integrations are backend controlled. OAuth state and PKCE are single-use; access/refresh tokens are encrypted server-side. Gmail uses only `gmail.send`, verifies the approved account and requires a successful test email before Connected. Drive uses `drive.file`, verifies the approved account, prepares private rclone configuration and requires write/read/delete cleanup before Connected. Safe Admin status never returns raw credentials.

Google Drive supports Test, Connect/Reconnect/Disconnect, Backup Now, daily/weekly/disabled schedule and retention. Backups are encrypted locally, uploaded under the new mobiST Tech namespace, recorded, and locally removed. Frontends never execute rclone. The existing Windows `mobisttech-drive:` passed a live temporary read/write/delete check; the old `mobist-drive:` and archives were untouched.

Real Gmail OAuth consent/test email is not claimed because application OAuth secrets were not supplied or requested. A clean environment remains Not Connected. Google production publishing/verification and authentic deployment consent remain required before go-live.
