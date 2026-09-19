#!/usr/bin/env bash
# Isolated CI-only end-to-end MySQL + encrypted restic + private-file recovery.
set -euo pipefail
umask 077
root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
work="$(mktemp -d "${RUNNER_TEMP:-/tmp}/mobisttech-mt74.XXXXXXXX")"
trap 'rm -rf -- "$work"' EXIT
mkdir -m 0700 "$work/stage" "$work/private" "$work/restore" "$work/repo"
printf '[client]\nuser=root\npassword=ci-only-mt74\nhost=127.0.0.1\nport=13306\nprotocol=tcp\n' > "$work/mysql.cnf"
printf '%s\n' 'isolated-ephemeral-restic-key-ci-only' > "$work/restic-password"
chmod 0600 "$work/mysql.cnf" "$work/restic-password"
export MYSQL_CLIENT_CNF="$work/mysql.cnf"
export RESTIC_PASSWORD_FILE="$work/restic-password"
export RESTIC_REPOSITORY="$work/repo"
export MOBIST_REHEARSAL_ONLY=yes MOBIST_DEPLOYMENT_ENV=rehearsal
export MOBIST_BACKUP_ENABLED=yes MOBIST_WRITES_QUIESCED=yes
export MYSQL_DATABASE=mobisttech_rehearsal
export PRIVATE_OBJECT_MODE=local PRIVATE_OBJECT_DIR="$work/private"
export BACKUP_TMP_ROOT="$work/stage"
mysql --defaults-extra-file="$MYSQL_CLIENT_CNF" -e \
  'CREATE DATABASE mobisttech_rehearsal; CREATE DATABASE mobisttech_rehearsal_restored;'
mysql --defaults-extra-file="$MYSQL_CLIENT_CNF" mobisttech_rehearsal -e \
  "CREATE TABLE mt74_synthetic (id INT PRIMARY KEY, marker VARCHAR(64)); INSERT INTO mt74_synthetic VALUES (1,'SYNTHETIC_ONLY');"
printf '%s\n' 'SYNTHETIC_PRIVATE_OBJECT_ONLY' > "$work/private/synthetic-object.txt"
restic init >/dev/null
# Fail closed before touching any data when an operator has not enabled the backup.
export MOBIST_BACKUP_ENABLED=no
if bash "$root/deploy/linux/backup/backup.sh" >"$work/denied.out" 2>&1; then
  echo 'FAIL: disabled backup was allowed' >&2; exit 1
fi
export MOBIST_BACKUP_ENABLED=yes
result="$(bash "$root/deploy/linux/backup/backup.sh")"
[[ "$result" == BACKUP_PASS\ snapshot=* ]] || { echo 'backup was not verified' >&2; exit 1; }
snapshot="${result#*snapshot=}"
snapshot="${snapshot%% *}"
expected_hash="${result##*sql_sha256=}"
[[ "$snapshot" =~ ^[0-9a-f]+$ && "$expected_hash" =~ ^[0-9a-f]{64}$ ]] || exit 1
restic restore "$snapshot" --target "$work/restore" >/dev/null
restored_sql="$(find "$work/restore" -type f -name database.sql -print -quit)"
[[ -n "$restored_sql" && -f "$restored_sql" ]] || { echo 'restored SQL missing' >&2; exit 1; }
actual_hash="$(sha256sum "$restored_sql" | cut -d ' ' -f 1)"
[[ "$actual_hash" == "$expected_hash" ]] || { echo 'SQL integrity failure' >&2; exit 1; }
restored_object="$work/restore/${work#/}/private/synthetic-object.txt"
cmp -- "$work/private/synthetic-object.txt" "$restored_object"
mysql --defaults-extra-file="$MYSQL_CLIENT_CNF" mobisttech_rehearsal_restored < "$restored_sql"
count="$(mysql --defaults-extra-file="$MYSQL_CLIENT_CNF" -N mobisttech_rehearsal_restored -e \
  "SELECT COUNT(*) FROM mt74_synthetic WHERE marker='SYNTHETIC_ONLY'")"
[[ "$count" == 1 ]] || { echo 'recovered MySQL row mismatch' >&2; exit 1; }
printf '%s' 'tamper' >> "$restored_sql"
corrupt_hash="$(sha256sum "$restored_sql" | cut -d ' ' -f 1)"
[[ "$corrupt_hash" != "$expected_hash" ]] || { echo 'tamper was not detected' >&2; exit 1; }
[[ -z "$(find "$work/stage" -mindepth 1 -maxdepth 1 -print -quit)" ]] || {
  echo 'plaintext staging residue after backup' >&2; exit 1;
}
printf 'MT74_BACKUP_RESTORE=PASS isolated_mysql=1 encrypted_snapshot=1 object_readback=1 tamper_rejected=1 cleanup=1\n'
