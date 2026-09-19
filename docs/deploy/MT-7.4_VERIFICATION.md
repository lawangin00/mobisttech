# MT-7.4 — Linux deployment and backup readiness verification

Status: Complete. Exact committed Linux rehearsal PASSED; live provisioning, scheduled production backups, real S3 provider, and destructive restore remain separately authorized HOLD items.

## Evidence and boundaries

- Canonical project: mobiST Tech, `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`; only `lawangin00/mobisttech/main` is mutable.
- Artifacts: `deploy/linux/nginx/` renders separate Admin/Customer HTTPS hosts and loopback backend API; `deploy/linux/systemd/` provides Website, queue, scheduler and guarded backup units/timers; `deploy/linux/env/` contains placeholder-only isolated configuration; `deploy/linux/backup/backup.sh` implements encrypted off-host operational snapshot with exact ID/SQL readback and fail-closed guards.
- `docs/deploy/MT-7.4_RUNBOOK.md` specifies release immutability, private/S3 object recovery, separate key custody, write barrier and production provisioning/restore HOLD boundaries. Existing Laravel application-level encrypted backup/recovery is preserved.
- Local static configuration contract, Bash parsing and workflow YAML parsing PASS on Windows (no Linux Nginx executable, MySQL 8.4 rehearsal instance or restic executable on local host). No local live Linux/S3 recovery PASS is claimed.
- Final accepted candidate `5aa6c83fc74a4f115f481df108383b96398a326d` had terminal `completed/success` [workflow run 35438048369](https://github.com/lawangin00/mobisttech/actions/runs/35438048369), job `105883983727`. All steps PASS, including executable Linux backup entrypoint, `nginx -t` on throwaway HTTPS hosts, real ephemeral MySQL 8.4 SQL import into a distinct clean schema, encrypted restic SQL/object readback, hash and tamper rejection, disabled-run refusal and plaintext staging cleanup. No live S3, DNS, certificate, source/customer data or destructive production restore was touched. Documentation-only closure commit does not supersede the accepted candidate.
