#!/usr/bin/env python3
"""Source-bound mobiST Tech CI gate: LOCAL routine and unscoped manual CI fail closed."""
import argparse
import base64
import json
import os
from pathlib import Path
import subprocess
import urllib.error
import urllib.request

PROJECT_ID = '282dba2f-a2d9-47e8-aa8d-e499fbe1706c'
STATE_API = 'https://api.github.com/repos/lawangin00/references/contents/UNIVERSAL_EXECUTION_MODE.json?ref=main'
STATE_RAW = 'https://raw.githubusercontent.com/lawangin00/references/refs/heads/main/UNIVERSAL_EXECUTION_MODE.json'
REQUEST_ROOT = '.github/ci-requests/'
FOCUSED_SCOPES = ('W01-customer', 'W01-identity', 'P02-variants', 'W03-commerce')


def present(request, field):
    return isinstance(request.get(field), str) and bool(request[field].strip())


def allowed(state, event, request=None):
    assert state.get('schema_version') == 1
    assert state.get('global_mode') in ('GITHUB', 'LOCAL', 'RDC')
    exceptions = state.get('exceptions')
    assert isinstance(exceptions, dict)
    assert all(value in ('GITHUB', 'LOCAL', 'RDC') for value in exceptions.values())
    # A manual event does not establish authorization even when actor is the owner.
    if event != 'push' or not isinstance(request, dict):
        return False
    assert request.get('project_id') == PROJECT_ID
    assert request.get('reason') in ('stage', 'milestone', 'necessary')
    assert request.get('gate') in ('website', 'full', 'all', 'verify')
    assert present(request, 'stage_id') and present(request, 'requested_at_utc')
    if request['gate'] == 'verify':
        assert request['stage_id'] == 'MT-7.5' and request.get('focus') in FOCUSED_SCOPES
    surface = request.get('execution_surface', 'NORMAL_CHAT')
    assert surface in ('NORMAL_CHAT', 'LOCAL_WORK')
    mode = exceptions.get(PROJECT_ID, state['global_mode'])
    if surface == 'LOCAL_WORK' or mode in ('LOCAL', 'RDC'):
        if request['reason'] not in ('milestone', 'necessary'):
            return False
        # No legacy metadata-free request may authorize NEW LOCAL hosted work.
        if request.get('execution_surface') not in ('NORMAL_CHAT', 'LOCAL_WORK'):
            return False
        if not all(present(request, field) for field in
                   ('authorization_ref', 'local_evidence', 'hosted_only_need')):
            return False
        if len(request['hosted_only_need'].strip()) < 20:
            return False
    return True


def self_test():
    gh = {'schema_version': 1, 'global_mode': 'GITHUB', 'exceptions': {}}
    local = {'schema_version': 1, 'global_mode': 'LOCAL', 'exceptions': {}}
    req = {'project_id': PROJECT_ID, 'reason': 'stage', 'gate': 'all',
           'stage_id': 'MT-7.5', 'requested_at_utc': '2026-09-24T00:00:00Z',
           'execution_surface': 'NORMAL_CHAT'}
    approved = {**req, 'reason': 'milestone',
                'authorization_ref': 'ledger:MT-7.5:approved-milestone',
                'local_evidence': 'ledger:MT-7.5:local-PASS',
                'hosted_only_need': 'Independent clean Linux milestone verification'}
    assert allowed(gh, 'push', req)
    assert not allowed(local, 'push', req)
    assert not allowed(local, 'workflow_dispatch', approved)
    assert not allowed(gh, 'workflow_dispatch', approved)
    assert not allowed(local, 'push', {**req, 'reason': 'milestone'})
    assert not allowed(local, 'push', {**approved, 'hosted_only_need': 'repeat local tests'})
    assert allowed(local, 'push', approved)
    assert allowed(local, 'push', {**approved, 'reason': 'necessary'})
    assert not allowed(gh, 'push', {**req, 'execution_surface': 'LOCAL_WORK'})
    assert allowed(gh, 'push', {**approved, 'execution_surface': 'LOCAL_WORK'})
    assert not allowed(local, 'push', {k: v for k, v in approved.items() if k != 'execution_surface'})
    assert not allowed({**gh, 'exceptions': {PROJECT_ID: 'LOCAL'}}, 'push', req)
    assert allowed({**local, 'exceptions': {PROJECT_ID: 'GITHUB'}}, 'push', req)
    for invalid in ({**req, 'project_id': 'wrong'}, {**req, 'gate': 'wrong'},
                    {**req, 'gate': 'verify', 'focus': 'wrong'}):
        try:
            allowed(gh, 'push', invalid)
        except AssertionError:
            pass
        else:
            raise AssertionError('Invalid request accepted')
    print('CI_LOCAL_MANUAL_BYPASS_FIXTURES=PASS')


def git(*args):
    return subprocess.check_output(['git', *args], text=True).strip()


def read_request():
    head = git('rev-parse', 'HEAD')
    assert head == os.environ['GITHUB_SHA'], 'Not exact triggering commit'
    parent = git('rev-parse', 'HEAD^')
    changed = git('diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD').splitlines()
    assert len(changed) == 1 and changed[0].startswith(REQUEST_ROOT) and changed[0].endswith('.json')
    path = Path(changed[0])
    assert path.is_file() and not path.is_symlink()
    req = json.loads(path.read_text(encoding='utf-8'))
    assert req.get('source_commit') == parent, 'Request source must equal its parent commit'
    assert present(req, 'requested_at_utc') and present(req, 'stage_id')
    print('CI_REQUEST_PATH=' + str(path))
    print('CI_REQUEST_SOURCE_SHA=' + parent)
    return req


def canonical_mode():
    headers = {'User-Agent': 'mobisttech-explicit-ci-gate', 'Accept': 'application/vnd.github+json', 'Cache-Control': 'no-cache'}
    api_request = urllib.request.Request(STATE_API, headers=headers)
    try:
        with urllib.request.urlopen(api_request, timeout=15) as response:
            payload = json.load(response)
        return json.loads(base64.b64decode(payload['content']).decode('utf-8'))
    except urllib.error.HTTPError as error:
        if error.code != 403:
            raise
        raw_url = STATE_RAW + '?request=' + os.environ['GITHUB_SHA']
        raw_request = urllib.request.Request(raw_url, headers={'User-Agent': headers['User-Agent'], 'Cache-Control': 'no-cache'})
        with urllib.request.urlopen(raw_request, timeout=15) as response:
            assert response.status == 200
            return json.load(response)


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return
    event = os.environ['GITHUB_EVENT_NAME']
    assert event == 'push', 'Unapproved manual CI dispatch: use a justified CI request commit'
    req = read_request()
    assert allowed(canonical_mode(), event, req), 'CI blocked: mode or authorization mismatch'
    focus = req['focus'] if req['gate'] == 'verify' else 'full'
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
        output.write('run_tests=true\nrun_scope=' + focus + '\n')
    print('CI_REQUEST_RUN_TESTS=true')
    print('CI_REQUEST_RUN_SCOPE=' + focus)


if __name__ == '__main__':
    main()
