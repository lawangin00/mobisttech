#!/usr/bin/env python3
"""Focused static guard: policy routing and existing 27-row finite MT-7.5 index."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def check() -> None:
    policy = (ROOT / 'docs/PROJECT_EXECUTION_EFFICIENCY_POLICY.md').read_text(encoding='utf-8')
    agents = (ROOT / 'AGENTS.md').read_text(encoding='utf-8')
    registry = (ROOT / 'docs/AI_PROJECT_COMMAND_REGISTRY.md').read_text(encoding='utf-8')
    ci = (ROOT / 'docs/CI_EXECUTION_POLICY.md').read_text(encoding='utf-8')
    ledger = (ROOT / 'docs/PROJECT_IMPLEMENTATION_STATUS.md').read_text(encoding='utf-8')
    fast = (ROOT / 'docs/audit/MT-7.5_FAST_TRACK_REVIEW.md').read_text(encoding='utf-8')
    for name, content in [('AGENTS', agents), ('REGISTRY', registry), ('CI', ci), ('LEDGER', ledger)]:
        assert 'PROJECT_EXECUTION_EFFICIENCY_POLICY.md' in content, f'{name} policy binding missing'
    for required in ('EVERY current and future stage', 'focused tests', 'LOOP_GUARD',
                     'unowned', 'full regression', 'HOLD', 'first genuinely pending'):
        assert required.lower() in policy.lower(), f'Policy rule missing: {required}'
    assert 'run_isolated_test.py' in policy and 'run_isolated_test.py' in ci
    assert '27 stable IDs' in ledger and 'H-02' in ledger
    rows = re.findall(r'^\|\s*(\d{2})/([A-Z][0-9]{2}|G-[A-Z])\s*\|([^\n]*)$', fast, re.M)
    ids = [int(number) for number, _, _ in rows]
    keys = [family for _, family, _ in rows]
    assert ids == list(range(1, 28)), 'Expected exactly 27 priority-ordered stable closure rows'
    assert len(set(keys)) == 27, 'Duplicate family/cross-gate ID'
    complete = sum(row.rsplit('|', 2)[-2].strip() == 'DONE' for _, _, row in rows)
    assert 0 <= complete <= 27, 'Invalid closure count'
    assert all(row.rsplit('|', 2)[-2].strip().startswith(('DONE', 'OPEN')) for _, _, row in rows), 'Invalid gate status'
    assert '17/W04' in fast and 'H-02' in fast and '27/Q01' in fast
    assert 'LOOP_GUARD' in registry and 'full joined regression' in ci.lower()
    print(f'MOBISTTECH_PROJECT_WIDE_EXECUTION_POLICY_PASS: 27 stable gates, baseline {complete} DONE, {27 - complete} OPEN; cooperative lock only')


if __name__ == '__main__':
    check()