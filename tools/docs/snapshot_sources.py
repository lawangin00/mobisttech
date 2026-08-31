"""Read-only fingerprints of protected reference checkouts; never run their code."""
from pathlib import Path
import hashlib
import json
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / 'docs/SOURCE_SNAPSHOT.json'
SOURCES = [Path('C:/mobiST/mobiST-POS'), Path('C:/mobiST/mobiST-Website')]


def git(root, *args):
    # Trust only the two explicitly supplied reference paths for this read call.
    return subprocess.check_output(['git', '--no-optional-locks', '-c', 'safe.directory=' + root.as_posix(), '-C', str(root), *args])


def snapshot(root):
    paths = sorted(p for p in git(root, 'ls-files', '-z').decode('utf-8').split('\0') if p)
    digest = hashlib.sha256()
    missing = []
    for relative in paths:
        path = root / relative
        if path.is_symlink():
            data = str(path.readlink()).encode('utf-8')
        elif path.is_file():
            data = path.read_bytes()
        else:
            missing.append(relative)
            data = b'<MISSING>'
        digest.update(relative.encode('utf-8') + b'\0' + hashlib.sha256(data).digest())
    return {
        'path': str(root),
        'head': git(root, 'rev-parse', 'HEAD').decode().strip(),
        'branch': git(root, 'branch', '--show-current').decode().strip(),
        'origin': git(root, 'remote', 'get-url', 'origin').decode().strip(),
        'status': git(root, 'status', '--porcelain=v1', '--untracked-files=all').decode(),
        'tracked_files': len(paths),
        'tracked_worktree_sha256': digest.hexdigest(),
        'missing_files': missing,
    }


def main():
    results = {'captured_date': '2026-08-31', 'scope': 'Git refs, status, origin and tracked-file bytes only; ignored runtime data not accessed', 'sources': [snapshot(p) for p in SOURCES]}
    if '--check' in sys.argv:
        if json.loads(OUTPUT.read_text(encoding='utf-8')) != results:
            raise SystemExit('Protected source baseline changed; investigate without altering sources')
        print('Protected source snapshots unchanged')
    else:
        if OUTPUT.exists():
            raise SystemExit('Baseline already exists; refuse overwrite')
        OUTPUT.write_text(json.dumps(results, indent=2) + '\n', encoding='utf-8')
        print('Read-only source baseline captured')


if __name__ == '__main__':
    main()
