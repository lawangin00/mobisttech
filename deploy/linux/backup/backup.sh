#!/usr/bin/env bash
# Non-destructive encrypted snapshot; enable timer only after explicit deployment approval.
set -euo pipefail
umask 077
fail() { printf 'BACKUP_FAIL: %s\n' "$1" >&2; exit 1; }
for bin in mysqldump restic python3 sha256sum mktemp; do
  command -v "$bin" >/dev/null 2>&1 || fail "missing tool: $bin"
done
[[ "${MOBIST_BACKUP_ENABLED:-no}" == yes ]] || fail 'backup not enabled'
if [[ "${MOBIST_REHEARSAL_ONLY:-no}" == yes ]]; then
  [[ "${MOBIST_DEPLOYMENT_ENV:-}" == rehearsal && "${MYSQL_DATABASE:-}" == mobisttech_rehearsal ]] || fail 'rehearsal identity mismatch'
  [[ "${RESTIC_REPOSITORY:-}" == /* && "${PRIVATE_OBJECT_MODE:-}" == local ]] || fail 'rehearsal requires isolated local repository and objects'
else
  [[ "${MOBIST_DEPLOYMENT_ENV:-}" =~ ^(staging|production)$ ]] || fail 'invalid environment'
  [[ "${MYSQL_DATABASE:-}" =~ ^mobisttech_(staging|production)$ ]] || fail 'database identity mismatch'
  [[ "${RESTIC_REPOSITORY:-}" == s3:* ]] || fail 'an off-host S3 restic repository is required'
fi
[[ -r "${MYSQL_CLIENT_CNF:-/nonexistent}" ]] || fail 'private MySQL client config missing'
[[ -r "${RESTIC_PASSWORD_FILE:-/nonexistent}" ]] || fail 'restic encryption key file missing'
[[ "${MOBIST_WRITES_QUIESCED:-no}" == yes ]] || fail 'consistent write barrier not confirmed'
[[ -d "${BACKUP_TMP_ROOT:-/nonexistent}" ]] || fail 'dedicated private temporary root missing'
[[ "${PRIVATE_OBJECT_MODE:-}" == local || "${PRIVATE_OBJECT_MODE:-}" == s3 ]] || fail 'object mode not declared'
[[ "${BACKUP_TMP_ROOT}" != / && "${BACKUP_TMP_ROOT}" != /tmp ]] || fail 'unsafe staging root'
[[ "${RESTIC_PASSWORD_FILE}" != "${MYSQL_CLIENT_CNF}" ]] || fail 'credential roles must be distinct'
stage="$(mktemp -d "${BACKUP_TMP_ROOT%/}/run.XXXXXXXX")"
trap 'rm -rf -- "$stage"' EXIT
[[ -d "$stage" && "$stage" == "${BACKUP_TMP_ROOT%/}"/run.* ]] || fail 'unsafe staging path'
# No password on command line; the locked-down file is passed to mysqldump as its first argument.
mysqldump --defaults-extra-file="$MYSQL_CLIENT_CNF" --single-transaction --quick \
  --routines --triggers --events --hex-blob --no-tablespaces \
  "$MYSQL_DATABASE" > "$stage/database.sql" || fail 'database dump failed'
[[ -s "$stage/database.sql" ]] || fail 'empty database dump'
if [[ "$PRIVATE_OBJECT_MODE" == local ]]; then
  [[ -d "${PRIVATE_OBJECT_DIR:-/nonexistent}" ]] || fail 'private object directory missing'
  if [[ "${MOBIST_REHEARSAL_ONLY:-no}" != yes ]]; then
    [[ "${PRIVATE_OBJECT_DIR}" == /srv/mobisttech/shared/private* ]] || fail 'unexpected private object root'
  fi
  object_path="$PRIVATE_OBJECT_DIR"
else
  command -v aws >/dev/null 2>&1 || fail 'AWS CLI is required for private S3 source'
  [[ "${APP_PRIVATE_S3_URI:-}" == s3://* ]] || fail 'private source URI missing'
  [[ "${APP_PRIVATE_S3_URI}" != "${RESTIC_REPOSITORY}" ]] || fail 'source and backup destination coincide'
  mkdir -m 0700 "$stage/s3-private"
  aws --profile "${APP_S3_SOURCE_PROFILE:-app-readonly}" s3 sync "$APP_PRIVATE_S3_URI" "$stage/s3-private" --only-show-errors || fail 'private S3 copy failed'
  object_path="$stage/s3-private"
fi
sql_hash="$(sha256sum "$stage/database.sql" | cut -d ' ' -f 1)"
printf 'env=%s\ndatabase=%s\nsql_sha256=%s\nprivate_mode=%s\n' \
  "$MOBIST_DEPLOYMENT_ENV" "$MYSQL_DATABASE" "$sql_hash" "$PRIVATE_OBJECT_MODE" > "$stage/manifest.txt"
# Capture the exact snapshot ID rather than assuming "latest" refers to this run.
restic backup --json --tag "mobisttech-${MOBIST_DEPLOYMENT_ENV}" \
  "$stage/database.sql" "$stage/manifest.txt" "$object_path" > "$stage/restic-result.jsonl" || fail 'encrypted backup failed'
snapshot_id="$(python3 - "$stage/restic-result.jsonl" <<'PY'
import json,sys
rows=[json.loads(line) for line in open(sys.argv[1],encoding='utf-8') if line.strip()]
ids=[r['snapshot_id'] for r in rows if r.get('message_type')=='summary' and r.get('snapshot_id')]
if len(ids)!=1 or not isinstance(ids[0],str): raise SystemExit('exact backup snapshot ID missing')
print(ids[0])
PY
)" || fail 'snapshot evidence missing'
restic check >/dev/null || fail 'repository metadata check failed'
recovered_hash="$(restic dump "$snapshot_id" "$stage/database.sql" | sha256sum | cut -d ' ' -f 1)" \
  || fail 'encrypted snapshot SQL readback failed'
[[ "$sql_hash" == "$recovered_hash" ]] || fail 'SQL hash mismatch after encrypted readback'
# Object readback/recovery drill and retention run separately under the documented HOLD gate.
printf 'BACKUP_PASS snapshot=%s sql_sha256=%s\n' "$snapshot_id" "$sql_hash"
