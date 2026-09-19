# MT-7.4 — Linux deployment and backup readiness verification

Status: In Progress pending terminal isolated Linux acceptance and closure reconciliation.

## Evidence and boundaries

- Canonical project: mobiST Tech, `282dba2f-a2d9-47e8-aa8d-e499fbe1706c`; only `lawangin00/mobisttech/main` is mutable.
- Artifacts: `deploy/linux/nginx/` renders separate Admin/Customer HTTPS hosts and loopback backend API; `deploy/linux/systemd/` provides Website, queue, scheduler and guarded backup units/timers; `deploy/linux/env/` contains placeholder-only isolated configuration; `deploy/linux/backup/backup.sh` implements encrypted off-host operational snapshot with exact ID/SQL readback and fail-closed guards.
- `docs/deploy/MT-7.4_RUNBOOK.md` specifies release immutability, private/S3 object recovery, separate key custody, write barrier and production provisioning/restore HOLD boundaries. Existing Laravel application-level encrypted backup/recovery is preserved.
- Local static configuration contract, Bash parsing and workflow YAML parsing PASS on Windows (no Linux Nginx executable, MySQL 8.4 rehearsal instance or restic executable on local host). No local live Linux/S3 recovery PASS is claimed.
- Pending: manual GitHub Actions isolated Linux test against disposable MySQL + restic snapshot/restore, actual `nginx -t`, corruption rejection and final clean Git/docs reconciliation. Live production provisioning or data restore is not authorized.
