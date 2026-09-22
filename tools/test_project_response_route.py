"""Regression guard for Mobisttech's inherited command-specific output contract."""
from pathlib import Path
root=Path(__file__).resolve().parents[1]
a=(root/'AGENTS.md').read_text(encoding='utf-8')
r=(root/'docs/AI_PROJECT_COMMAND_REGISTRY.md').read_text(encoding='utf-8')
f=(root/'docs/PROJECT_RESPONSE_FORMAT.md').read_text(encoding='utf-8')
assert 'five adjacent lines ONLY for authorized project task' in r
assert '`Next` is read-only and exactly three lines' in r
assert 'Five visible lines ONLY for `Y`/`Proceed`/`Resume`' in a
assert '`Next` is read-only: exactly THREE adjacent visible lines' in f
assert 'retain their specialized universal alias output' in f
assert 'Local Work' in f and 'without markup' in f
print('MOBISTTECH_COMMAND_SPECIFIC_OUTPUT_CONTRACT_PASS')
print('NOTE: This validates project instructions, not a native-chat interception hook.')
