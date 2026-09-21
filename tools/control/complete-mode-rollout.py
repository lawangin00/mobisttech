#!/usr/bin/env python3
"""One-time authorized, fail-closed Mobisttech project-control reconciliation.

Changes ONLY the existing full CI workflow and project-name prose in project
Markdown. Original owner Goal/Preferences and their source copies stay byte-identical.
No application code, product branding, database, roadmap point/status or remote is changed.
"""
from __future__ import annotations

import json
import os
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
ID = '282dba2f-a2d9-47e8-aa8d-e499fbe1706c'
EXPECTED_CI_BLOB = '6aa7370ffe951f89ecf937ddc5e5fe2cd9ce627a'
CI_PATH = ROOT / '.github/workflows/ci.yml'
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
    assert git('hash-object', str(CI_PATH)) == EXPECTED_CI_BLOB, 'CI workflow changed; reconcile safely before writing'
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

    # Only project display-name prose; NEVER replace the mobiST Technologies
    # business name, repo URL, program symbols, original user source or binaries.
    excluded = {
        ROOT / 'docs/PROJECT_GOAL.md', ROOT / 'docs/PROJECT_PREFERENCES.md',
    }
    candidates = [ROOT / 'README.md', ROOT / 'AGENTS.md', *sorted((ROOT / 'docs').rglob('*.md'))]
    edited = []
    for path in candidates:
        if path in excluded or 'source' in path.relative_to(ROOT).parts or 'archive' in path.relative_to(ROOT).parts:
            continue
        before = path.read_text(encoding='utf-8')
        after = DISPLAY_NAME.sub('Mobisttech', before)
        if before != after:
            path.write_text(after, encoding='utf-8', newline='')
            edited.append(str(path.relative_to(ROOT)))
    assert (ROOT / 'docs/PROJECT_IDENTITY.json').read_text(encoding='utf-8').find('"project_name": "Mobisttech"') >= 0
    assert git('diff', '--check') == ''
    changed = git('diff', '--name-only').splitlines()
    assert '.github/workflows/ci.yml' in changed
    allowed = set(edited + ['.github/workflows/ci.yml'])
    assert set(changed) <= allowed, 'Unexpected file changed'
    print('FULL_CI_MODE_ROLLOUT=READY')
    print('DISPLAY_NAME_EDITED_FILES=' + str(len(edited)))
    for path in edited:
        print('DISPLAY_NAME_FILE=' + path)
    print('CI_MODE_ROLLOUT_CHANGED=' + ','.join(changed))


if __name__ == '__main__':
    main()
