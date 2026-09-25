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
    # The approved two-track contract is enforceable independently of the moving
    # 27-gate denominator. A synthetically DONE W04 never completes external H-02.
    roadmap = (ROOT / 'docs/PROJECT_IMPLEMENTATION_ROADMAP.md').read_text(encoding='utf-8')
    truth = (ROOT / 'docs/PROJECT_SOURCE_OF_TRUTH.md').read_text(encoding='utf-8')
    hold = (ROOT / 'docs/HOLD_PRE_LAUNCH_REGISTER.md').read_text(encoding='utf-8')
    payment_checklist = (ROOT / 'docs/audit/MT-7.5_W04_DEVELOPMENT_ACCEPTANCE_CHECKLIST_2026-09-24.md').read_text(encoding='utf-8')
    assert rows[16][1] == 'W04' and rows[16][2].rsplit('|', 2)[-2].strip() == 'DONE', 'W04 development terminal proof missing'
    assert 'DEVELOPMENT ACCEPTANCE' in roadmap and 'PRE-LAUNCH' in roadmap and 'FINAL-AUDIT' in roadmap
    assert '27-gate count measures development parity only' in roadmap
    assert 'Project complete: 100%' in roadmap and 'PRE-LAUNCH BLOCKED' in roadmap
    assert 'DEVELOPMENT ACCEPTANCE' in truth and 'H-02' in truth and 'docs/HOLD_PRE_LAUNCH_REGISTER.md' in truth
    assert '117/117 PASS' in payment_checklist and 'H-02' in payment_checklist and 'PRE-LAUNCH' in payment_checklist
    assert all(s in hold for s in ('JazzCash', 'Easypaisa', 'hosted/tokenized card', 'PRE-LAUNCH PENDING', 'PRE-LAUNCH BLOCKED', 'live'))
    assert 'H-02' in ledger and '16/27 DONE, 11 OPEN' in ledger
    assert 'LOOP_GUARD' in registry and 'full joined' in ci.lower() and 'regression' in ci.lower()
    for command in ('Shift to LDC', 'Shift to MCP', 'Shift to RDC', 'Shift to Git'):
        assert command in registry and command in agents, f'Execution route missing: {command}'
    assert 'routine verification runs on the authorized local pc in every route' in ci.lower()
    assert 'project_completion' in ci and 'reason: stage' not in ci
    assert 'LDC -> Local MCP Coder -> RDC' in agents
    print(f'MOBISTTECH_PROJECT_WIDE_EXECUTION_POLICY_PASS: 27 stable gates, baseline {complete} DONE, {27 - complete} OPEN; local-first routes enforced')


if __name__ == '__main__':
    check()