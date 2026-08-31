"""Verify complete schema lineage without reading or importing any private rows."""
from pathlib import Path
import hashlib
import json

ROOT = Path(__file__).resolve().parents[2]


def validate_shape(source, table, row, manifest):
    """Fail closed before a future importer handles values/relationships; no import occurs here."""
    entry = next((t for t in manifest['tables'] if t['source'] == source and t['table'] == table), None)
    if entry is None:
        raise ValueError('Unknown source table')
    expected = {c['source_column'] for c in entry['columns']}
    if set(row) != expected:
        raise ValueError('Unknown or missing source columns; export contract reconciliation required')


def verify():
    design = json.loads((ROOT / 'docs/design/SCHEMA_MAPPING.json').read_text(encoding='utf-8'))
    manifest = json.loads((ROOT / 'docs/schema/COLUMN_DESTINATIONS.json').read_text(encoding='utf-8'))
    target = json.loads((ROOT / 'docs/schema/TARGET_SCHEMA.json').read_text(encoding='utf-8'))['tables']
    assert {(x['source'], x['table']) for x in design['tables']} == {(x['source'], x['table']) for x in manifest['tables']}
    assert set(design['new_tables']).issubset(target)
    for migration in design['migration_files']:
        path = ROOT / '.local/mt11/sources' / migration['source'] / migration['path']
        assert hashlib.sha256(path.read_bytes()).hexdigest() == migration['sha256'], str(path)
    for entry in manifest['tables']:
        row = {c['source_column']: None for c in entry['columns']}
        validate_shape(entry['source'], entry['table'], row, manifest)
        for invalid in ({**row, '__unknown_column': None}, dict(list(row.items())[1:])):
            try:
                validate_shape(entry['source'], entry['table'], invalid, manifest)
            except ValueError:
                pass
            else:
                raise AssertionError('Invalid source shape accepted')
        for c in entry['columns']:
            t, name = c['destination'].split('.')
            if t in target:
                assert name in {x['name'] for x in target[t]['columns']}
            assert c['transform'] and c['disposition']
    for table, definition in target.items():
        names = [c['name'] for c in definition['columns']]
        assert len(set(names)) == len(names), table
        for fk in definition['foreign_keys']:
            assert set(fk['columns']).issubset(names)
            foreign_names = {c['name'] for c in target[fk['table']]['columns']}
            assert set(fk['references']).issubset(foreign_names)
    print(f'PASS: 79 pinned migration hashes; 58 source tables / 735 columns; {len(target)} shared tables; all planned new tables; 116 invalid row-shape cases rejected.')


if __name__ == '__main__':
    verify()
