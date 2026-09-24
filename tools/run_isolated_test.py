#!/usr/bin/env python3
"""Cooperative exclusive local test-DB runner. Never removes other processes' locks."""
import argparse
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import uuid

ROOT = Path(__file__).resolve().parents[1]


def execute(database: str, command: list[str], cwd: Path, lock_root: Path) -> int:
    if not re.fullmatch(r'mobisttech_test(?:_[a-zA-Z0-9_]+)?@(?:127\.0\.0\.1|localhost):[0-9]{2,5}', database):
        raise ValueError('Specify disposable test name@host:port; production/ambiguous DB denied')
    name, endpoint = database.split('@', 1)
    host, port = endpoint.rsplit(':', 1)
    database = f'{name}@127.0.0.1:{int(port)}' if host == 'localhost' else f'{name}@{host}:{int(port)}'
    if not command or not cwd.is_dir():
        raise ValueError('A command and existing working directory are required')
    lock_root.mkdir(parents=True, exist_ok=True)
    lock = lock_root / (hashlib.sha256(database.encode()).hexdigest() + '.lock')
    owner_marker = uuid.uuid4().hex
    try:
        lock.mkdir()  # Atomic exclusive acquisition; refuse other/stale locks.
    except FileExistsError:
        raise RuntimeError(f'Test DB {database} already locked or stale: {lock}. Inspect ownership; never auto-remove') from None
    try:
        (lock / 'owner.json').write_text(json.dumps({'pid': os.getpid(), 'database': database,
            'command': command, 'owner': owner_marker}), encoding='utf-8')
        return subprocess.run(command, cwd=cwd, check=False).returncode
    finally:
        owner = lock / 'owner.json'
        if owner.exists() and json.loads(owner.read_text(encoding='utf-8')).get('owner') == owner_marker:
            owner.unlink()
            lock.rmdir()


def self_test() -> None:
    with tempfile.TemporaryDirectory(prefix='mt-test-lock-') as temporary:
        root = Path(temporary)
        database = 'mobisttech_test@127.0.0.1:13306'
        cmd = [sys.executable, '-c', 'print("ISOLATED_TEST_LOCK_CHILD_PASS")']
        assert execute(database, cmd, root, root / 'locks') == 0
        assert not any((root / 'locks').iterdir()), 'Owned lock not released'
        lock = root / 'locks' / (hashlib.sha256(database.encode()).hexdigest() + '.lock')
        lock.mkdir()  # Simulate an already-held lock: never delete it.
        try:
            for alias in (database, 'mobisttech_test@localhost:13306'):
                try:
                    execute(alias, cmd, root, root / 'locks')
                    raise AssertionError('Overlapping runner or loopback alias accepted')
                except RuntimeError as error:
                    assert 'already locked' in str(error)
            assert lock.is_dir(), 'Unowned lock removed'
        finally:
            lock.rmdir()
        try:
            execute('mobisttech@127.0.0.1:13306', cmd, root, root / 'locks')
            raise AssertionError('Production-like database accepted')
        except ValueError:
            pass
    print('MOBISTTECH_ISOLATED_TEST_LOCK_SELF_TEST_PASS')


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--self-test', action='store_true')
    parser.add_argument('--db-id', default='', help='Disposable DB identity: mobisttech_test@127.0.0.1:13306')
    parser.add_argument('--cwd', default='backend', help='Relative working directory inside project')
    parser.add_argument('command', nargs=argparse.REMAINDER, help='-- executable and arguments')
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return 0
    cwd = (ROOT / args.cwd).resolve()
    if not cwd.is_relative_to(ROOT):
        parser.error('Working directory must be within this project')
    command = args.command[1:] if args.command and args.command[0] == '--' else args.command
    try:
        return execute(args.db_id, command, cwd, ROOT / 'backend/storage/framework/testing/.db-locks')
    except (ValueError, RuntimeError) as error:
        print(f'ISOLATED_TEST_PREFLIGHT_BLOCKED: {error}', file=sys.stderr)
        return 2


if __name__ == '__main__':
    sys.exit(main())