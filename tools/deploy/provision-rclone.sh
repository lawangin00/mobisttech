#!/usr/bin/env bash
set -euo pipefail

if ! command -v rclone >/dev/null 2>&1; then
  if [[ "${1:-}" != "--install" ]]; then
    echo "rclone is required. Re-run with --install on a supported Debian/Ubuntu host." >&2
    exit 2
  fi
  apt-get update
  apt-get install -y rclone
fi

private_dir="${RCLONE_PRIVATE_DIR:-$(pwd)/backend/storage/app/private/integrations}"
install -d -m 0700 "$private_dir"
config_path="${RCLONE_CONFIG_PATH:-$private_dir/rclone.conf}"
touch "$config_path"
chmod 0600 "$config_path"
rclone version | head -n 1
printf 'Private rclone configuration ready at %s\n' "$config_path"
