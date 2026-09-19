#!/usr/bin/env python3
"""Read-only deployment template contract; never accesses live environments."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
nginx = (ROOT / 'nginx/mobisttech.conf.template').read_text()
php = (ROOT / 'nginx/mobisttech-laravel.conf.template').read_text()
backend = (ROOT / 'env/backend.production.env.example').read_text()
website = (ROOT / 'env/website.production.env.example').read_text()
backup = (ROOT / 'backup/backup.sh').read_text()
units = {path.name: path.read_text() for path in (ROOT / 'systemd').iterdir() if path.is_file()}


def check(condition: bool, message: str) -> None:
    if not condition:
        raise AssertionError(message)


check(nginx.count('listen 443 ssl;') == 2, 'two separate TLS hosts required')
check('listen 127.0.0.1:18080;' in nginx, 'backend loopback boundary missing')
check('proxy_pass http://127.0.0.1:13000;' in nginx, 'Website loopback boundary missing')
check('__CUSTOMER_FQDN__' in nginx and '__ADMIN_FQDN__' in nginx, 'domain placeholders missing')
check('TLSv1.2 TLSv1.3' in nginx and 'X-Content-Type-Options nosniff' in nginx, 'TLS/header missing')
check('location = /index.php' in php and 'fastcgi_pass unix:__PHP_FPM_SOCKET__;' in php, 'PHP-FPM routing missing')
check('location ~ \\.php$ { return 404; }' in php, 'arbitrary PHP execution permitted')
check('APP_DEBUG=false' in backend and 'SESSION_SECURE_COOKIE=true' in backend, 'production security flags missing')
check('IDENTITY_CUSTOMER_ORIGIN=https://' in backend and 'IDENTITY_ADMIN_ORIGIN=https://' in backend, 'distinct HTTPS origins missing')
check('DB_DATABASE=mobisttech_production' in backend, 'dedicated database missing')
check('PRIVATE_OBJECT_DISK=local' in backend and 'AWS_BUCKET=' in backend, 'private/S3 adapter choices missing')
check('EXTERNAL_INTEGRATIONS_ENABLED=false' in backend, 'provider HOLD not explicit')
check('LARAVEL_API_ORIGIN=http://127.0.0.1:18080' in website, 'server-only backend origin missing')
check(not re.search(r'(?im)^(?:DB_|AWS_|GOOGLE_|APP_KEY|RESTIC_|BACKUP_)', website), 'sensitive backend setting in Website env')
check('MOBIST_BACKUP_ENABLED=no' in (ROOT / 'env/backup.env.example').read_text(), 'backup must default off')
check('MOBIST_REHEARSAL_ONLY' in backup and 'MOBIST_WRITES_QUIESCED' in backup, 'rehearsal and consistency guards required')
check('RESTIC_PASSWORD_FILE' in backup and 'restic dump' in backup and 'sha256sum' in backup, 'encrypted readback missing')
for name in ('mobisttech-website.service','mobisttech-queue.service','mobisttech-scheduler.service','mobisttech-scheduler.timer','mobisttech-backup.service','mobisttech-backup.timer'):
    check(name in units, f'unit missing: {name}')
check('OnCalendar=*-*-* *:*:00' in units['mobisttech-scheduler.timer'], 'scheduler cadence missing')
check('OnCalendar=*-*-* 02:15:00' in units['mobisttech-backup.timer'], 'nightly backup missing')
check('User=mobistbackup' in units['mobisttech-backup.service'], 'backup least-privileged account missing')
check('User=mobisttech' in units['mobisttech-queue.service'], 'queue account missing')
check('EnvironmentFile=/etc/mobisttech/backend.env' in units['mobisttech-scheduler.service'], 'environment isolation missing')
print('MT74_CONFIG_CONTRACT=PASS')
