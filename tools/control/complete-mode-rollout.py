#!/usr/bin/env python3
"""One-time fail-closed Mobisttech CI and visible-name reconciliation.

Original user Goal/Preferences and their source copies remain byte-identical.
Technical repository identifiers, application code, branding, roadmap position
and historical application requirements are not renamed or reset.
"""
from __future__ import annotations
import json
import os
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ID = '282dba2f-a2d9-47e8-aa8d-e499fbe1706c'
CI_PATH = ROOT / '.github/workflows/ci.yml'
EXPECTED_CI_BLOB = '6aa7370ffe951f89ecf937ddc5e5fe2cd9ce627a'
DISPLAY_NAME = re.compile(r'(?<![A-Za-z0-9_])(?:mobiST Tech|MobisTech|MobistTech)(?![A-Za-z0-9_])')


def git(*args: str) -> str:
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def main() -> None:
    assert os.environ.get('GITHUB_REPOSITORY') == 'lawangin00/mobisttech'
    assert os.environ.get('GITHUB_REF') == 'refs/heads/main'
    assert git('rev-parse', 'HEAD') == os.environ.get('GITHUB_SHA')
    assert git('status', '--porcelain') == '', 'Checkout is not pristine'
    identity = json.loads((ROOT / 'docs/PROJECT_IDENTITY.json').read_text(encoding='utf-8'))
    assert identity['project_id'] == ID and identity['project_name'] == 'Mobisttech'
    assert git('hash-object', str(CI_PATH)) == EXPECTED_CI_BLOB, 'CI source changed; do not overwrite'
    ci = CI_PATH.read_text(encoding='utf-8')
    old_head = 'on:\n  workflow_dispatch:\n'
    assert ci.count(old_head) == 1
    ci = ci.replace(old_head, '''on:
  push:
    branches: [main]
    paths:
      - 'backend/**'
      - 'website/**'
      - 'tools/ci/**'
      - 'tools/ci-mode-gate.py'
      - '.github/workflows/ci.yml'
  workflow_dispatch:
''', 1)
    old_job = 'jobs:\n  acceptance:\n    name: Clean checkout acceptance\n'
    assert ci.count(old_job) == 1
    ci = ci.replace(old_job, '''jobs:
  mode:
    name: Resolve effective execution mode (no product tests)
    runs-on: ubuntu-latest
    timeout-minutes: 2
    outputs:
      run_tests: ${{ steps.check.outputs.run_tests }}
    steps:
      - uses: actions/checkout@v7
        with:
          persist-credentials: false
      - name: Verify fixture cases and canonical mode
        id: check
        run: |
          python3 tools/ci-mode-gate.py --self-test
          python3 tools/ci-mode-gate.py

  acceptance:
    name: Clean checkout acceptance
    needs: mode
    if: needs.mode.outputs.run_tests == 'true'
''', 1)
    assert 'workflow_dispatch:' in ci and 'backend/**' in ci and "if: needs.mode.outputs.run_tests == 'true'" in ci
    CI_PATH.write_text(ci, encoding='utf-8', newline='')

    excluded = {ROOT / 'docs/PROJECT_GOAL.md', ROOT / 'docs/PROJECT_PREFERENCES.md'}
    candidates = [ROOT / 'README.md', ROOT / 'AGENTS.md', *sorted((ROOT / 'docs').rglob('*.md'))]
    edited = []
    for path in candidates:
        if path in excluded or 'source' in path.relative_to(ROOT).parts or 'archive' in path.relative_to(ROOT).parts:
            continue
        original = path.read_text(encoding='utf-8')
        updated = DISPLAY_NAME.sub('Mobisttech', original)
        if updated != original:
            path.write_text(updated, encoding='utf-8', newline='')
            edited.append(str(path.relative_to(ROOT)))
    assert '"project_name": "Mobisttech"' in (ROOT / 'docs/PROJECT_IDENTITY.json').read_text(encoding='utf-8')
    # git diff --check on all Markdown falsely fails on old trailing spaces in
    # unchanged text on a renamed line. Verify new CI whitespace separately;
    # each Markdown mutation above changes ONLY the approved name token.
    assert git('diff', '--check', '--', '.github/workflows/ci.yml') == ''
    changed = git('diff', '--name-only').splitlines()
    assert '.github/workflows/ci.yml' in changed
    assert set(changed) <= set(edited + ['.github/workflows/ci.yml']), 'Unexpected file changed'
    print('FULL_CI_MODE_ROLLOUT=READY')
    print('DISPLAY_NAME_EDITED_FILES=' + str(len(edited)))
    for path in edited:
        print('DISPLAY_NAME_FILE=' + path)
    print('CI_MODE_ROLLOUT_CHANGED=' + ','.join(changed))


if __name__ == '__main__':
    main()
