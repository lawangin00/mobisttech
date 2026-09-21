# Google Integration and Deployment Readiness

## OAuth applications

Use separate Google Cloud projects/clients for development and production when required by policy. Configure accurate `mobiST Technologies` branding, approved homepage/privacy/terms domains, only exact HTTPS production callbacks, and the approved business/domain identity. Store client IDs/secrets as server deployment secrets; never expose them through Admin UI, JavaScript, logs, Git or evidence.

Gmail requests only `https://www.googleapis.com/auth/gmail.send`. Google currently classifies it as a sensitive scope, so production publishing/verification must be completed as applicable before go-live. Testing-mode authorization is not final architecture because test-user grants, including offline refresh tokens, can expire after seven days. Do not broaden scope to read/modify/full mailbox. Official references:

- https://developers.google.com/workspace/gmail/api/auth/scopes
- https://developers.google.com/identity/protocols/oauth2/web-server
- https://support.google.com/cloud/answer/15549945
- https://support.google.com/cloud/answer/13463073

Drive requests `https://www.googleapis.com/auth/drive.file`, which Google recommends as a narrow per-file scope and currently treats as non-sensitive. The backend verifies the selected account through Drive About, then creates the app-managed rclone configuration and proves create/read/delete access to its own validation object. Official reference: https://developers.google.com/workspace/drive/api/guides/api-specific-auth.

## Environment configuration

Provision blank environment-specific values shown in `backend/.env.example`. Development and production clients may differ. A fresh server shows Not Connected until the application OAuth client is provisioned and an Admin completes consent. A server move restores approved deployment secrets and, if authorized, private integration state; otherwise the Admin reconnects without a code change.

The normal Gmail flow is Admin Settings -> Integrations -> Gmail -> Connect -> Google consent -> callback -> backend test email -> Connected. The normal Drive flow is Admin Settings -> Integrations -> Google Drive -> Connect -> Google consent -> private rclone config -> write/read/delete validation -> Connected.

## rclone runtime and private state

`tools/deploy/Provision-Rclone.ps1` and `tools/deploy/provision-rclone.sh` detect rclone, optionally install it when an operator explicitly chooses installation, and prepare a private configuration location. `RCLONE_BINARY` and `RCLONE_CONFIG_PATH` are portable configuration values; application code contains no user-profile or Windows-only path.

The Laravel backend is the only rclone caller. Frontends receive safe status/operations only. The private rclone config and encrypted database tokens stay outside Git/public storage. Linux permissions are 0700/0600; the Windows script restricts the private directory to the current service/operator account. Production service ownership and backup restore of these secrets must be verified under MT-7.4.

## Backup behavior

`Backup Now` creates an encrypted logical business backup through Laravel, queues the same backend job used by the scheduler, uploads only to `mobisttech-drive:Mobisttech/Backups/<environment>/<date>/`, records checksum/size/outcome, and removes the local temporary archive. Admin-selected daily/weekly scheduling and retention use the same service. Retention deletes only expired new Mobisttech remote objects recorded by this repository. Disconnect removes app authorization/configuration but never deletes archives.

Previous-project `mobist-drive:` and its archives are excluded. No historical backup migration is part of this remediation.
