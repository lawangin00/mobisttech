"""D04 private source-copy schema/FK preflight; no original repository or target DB access.

Run only after separately staging verified source snapshots inside .local/mt75/d04.
Do not commit copied databases, actual source rows, SQL exports or private metrics.
"""
from __future__ import annotations

import json
import sqlite3
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PRIVATE = ROOT / '.local' / 'mt75' / 'd04'


def verify() -> None:
    if any(path.is_symlink() for path in (ROOT / '.local', ROOT / '.local' / 'mt75', PRIVATE)) or not PRIVATE.is_dir():
        raise ValueError('isolated staging directory is absent or linked')
    if not PRIVATE.resolve().is_relative_to((ROOT / '.local').resolve()):
        raise ValueError('private staging escaped the project')
    manifest = json.loads((ROOT / 'docs' / 'schema' / 'COLUMN_DESTINATIONS.json').read_text(encoding='utf-8'))
    if manifest.get('source_tables') != 58:
        raise ValueError('unexpected pinned source manifest')
    total = 0
    for source in ('pos', 'website'):
        staged = PRIVATE / (source + '.sqlite')
        if staged.is_symlink() or not staged.is_file() or staged.resolve().parent != PRIVATE.resolve():
            raise ValueError('missing or unsafe staged copy')
        if not 1024 <= staged.stat().st_size <= 250_000_000:
            raise ValueError('staged copy outside bounded size')
        with staged.open('rb') as source_file:
            if source_file.read(16) != b'SQLite format 3\x00':
                raise ValueError('invalid source-copy format')
        con = sqlite3.connect(staged.as_uri() + '?mode=ro&immutable=1', uri=True)
        try:
            con.execute('PRAGMA query_only=ON')
            if con.execute('PRAGMA quick_check').fetchone()[0] != 'ok':
                raise ValueError('source-copy integrity failed')
            if con.execute('PRAGMA foreign_key_check').fetchone() is not None:
                raise ValueError('source-copy foreign key violation')
            actual = {row[0] for row in con.execute("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")}
            entries = [entry for entry in manifest['tables'] if entry['source'] == source]
            expected = {entry['table'] for entry in entries}
            if actual != expected | {'migrations'}:
                raise ValueError('source schema tables differ from manifest')
            for entry in entries:
                table = entry['table']
                if not table.isidentifier():
                    raise ValueError('unexpected source table identifier')
                found = {row[1] for row in con.execute('PRAGMA table_info("' + table + '")')}
                pinned = {column['source_column'] for column in entry['columns']}
                if found != pinned:
                    raise ValueError('source schema column mismatch')
                # Private control totals are evaluated locally, never echoed or persisted.
                count = con.execute('SELECT COUNT(*) FROM "' + table + '"').fetchone()[0]
                if not isinstance(count, int) or count < 0:
                    raise ValueError('invalid source row count')
                total += 1
        finally:
            con.close()
    if total != 58:
        raise ValueError('incomplete source map')
    print('D04_SOURCE_COPY_PREFLIGHT_PASS: 2 isolated copies; 58/58 table contracts; SQLite integrity/FKs PASS; no target import.')


if __name__ == '__main__':
    try:
        verify()
    except Exception as error:
        print('D04_SOURCE_COPY_PREFLIGHT_BLOCK: ' + type(error).__name__, file=sys.stderr)
        sys.exit(1)
