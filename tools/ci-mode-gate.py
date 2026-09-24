#!/usr/bin/env python3
"""Fail-closed, source-bound CI request gate for mobiST Tech.

Application pushes never start CI. Only an explicit, audited CI-request commit
may start hosted acceptance; routine LOCAL/DIRECT_LOCAL tests remain local.
"""
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


def meaningful(request, field):
    value = request.get(field)
    return isinstance(value, str) and len(value.strip()) >= 8


def allowed(state, event, request=None):
    assert state.get('schema_version') == 1
    assert state.get('global_mode') in ('GITHUB', 'LOCAL', 'RDC')
    exceptions = state.get('exceptions')
    assert isinstance(exceptions, dict)
    assert all(value in ('GITHUB', 'LOCAL', 'RDC') for value in exceptions.values())
    # A workflow_dispatch event is not proof of owner authorization. Fail
    # before dependency installation, browser tests or any other expensive job.
    if event != 'push' or not isinstance(request, dict):
        return False
    assert request.get('project_id') == PROJECT_ID
    assert request.get('reason') in ('stage', 'milestone', 'necessary')
    assert request.get('gate') in ('website', 'full', 'all', 'verify')
    assert meaningful(request, 'stage_id') and meaningful(request, 'requested_at_utc')
    if request['gate'] == 'verify':
        assert request.get('stage_id') == 'MT-7.5' and request.get('focus') in FOCUSED_SCOPES, 'Unrecognized isolated verification scope'
    surface = request.get('execution_surface', 'NORMAL_CHAT')
    assert surface in ('NORMAL_CHAT', 'LOCAL_WORK')
    mode = exceptions.get(PROJECT_ID, state['global_mode'])
    local_route = surface == 'LOCAL_WORK' or mode in ('LOCAL', 'RDC')
    if local_route:
        if request['reason'] not in ('milestone', 'necessary'):
            return False
        # Legacy requests without an explicit route cannot authorize fresh LOCAL CI.
        if request.get('execution_surface') not in ('NORMAL_CHAT', 'LOCAL_WORK'):
            return False
        # A request must point to prior authorization, genuinely distinct hosted
        # need and recorded local test evidence. The controller must verify the
        # referenced ledger before writing this source-bound request commit.
        if not all(meaningful(request, key) for key in
                   ('authorization_ref', 'local_evidence', 'hosted_only_need')):
            return False
        if len(request['hosted_only_need'].strip()) < 20:
            return False
    return True


def self_test():
    gh = {'schema_version': 1, 'global_mode': 'GITHUB', 'exceptions': {}}
    local = {'schema_version': 1, 'global_mode': 'LOCAL', 'exceptions': {}}
    request = {'project_id': PROJECT_ID, 'reason': 'stage', 'gate': 'all',
               'stage_id': 'MT-7.5', 'requested_at_utc': '2026-09-24T00:00:00Z',
               'execution_surface': 'NORMAL_CHAT'}
    milestone = {**request, 'reason': 'milestone',
                 'authorization_ref': 'ledger:MT-7.5:approval',
                 'local_evidence': 'ledger:MT-7.5:local-pass',
                 'hosted_only_need': 'Independent clean Linux acceptance milestone'}
    assert allowed(gh, 'push', request)
    assert not allowed(local, 'push', request)
    assert not allowed(local, 'workflow_dispatch', milestone)
    assert not allowed(gh, 'workflow_dispatch', milestone)
    assert not allowed(local, 'push', {**request, 'reason': 'milestone'})
    assert not allowed(local, 'push', {**milestone, 'hosted_only_need': 'repeat local tests'})
    assert allowed(local, 'push', milestone)
    assert allowed(local, 'push', {**milestone, 'reason': 'necessary'})
    assert not allowed(gh, 'push', {**request, 'execution_surface': 'LOCAL_WORK'})
    assert allowed(gh, 'push', {**milestone, 'execution_surface': 'LOCAL_WORK'})
    assert not allowed(local, 'push', {key: value for key, value in milestone.items()
                                      if key != 'execution_surface'})
    assert not allowed({**gh, 'exceptions': {PROJECT_ID: 'LOCAL'}}, 'push', request)
    assert allowed({**local, 'exceptions': {PROJECT_ID: 'GITHUB'}}, 'push', request)
    for invalid in ({**request, 'project_id': 'wrong'},
                    {**request, 'gate': 'wrong'},
                    {**request, 'gate': 'verify', 'focus': 'unrecognized'}):
        try:
            allowed(gh, 'push', invalid)
        except AssertionError:
            pass
        else:
            raise AssertionError('Invalid CI request accepted')
    print('CI_LOCAL_MANUAL_BYPASS_FIXTURES=PASS')


def git(*args):
    return subprocess.check_output(['git', *args], text=True).strip()


def read_request():
    head = git('rev-parse', 'HEAD')
    assert head == os.environ['GITHUB_SHA'], 'Not exact triggering commit'
    parent = git('rev-parse', 'HEAD^')
    changed = git('diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD').splitlines()
    assert len(changed) == 1 and changed[0].startswith(REQUEST_ROOT) and changed[0].endswith('.json'), 'CI request must be only changed file in its commit'
    path = Path(changed[0])
    assert path.is_file() and not path.is_symlink()
    request = json.loads(path.read_text(encoding='utf-8'))
    assert request.get('source_commit') == parent, 'Request source must match exact parent commit'
    assert request.get('requested_at_utc') and request.get('stage_id'), 'Incomplete CI request'
    print('CI_REQUEST_PATH=' + str(path))
    print('CI_REQUEST_SOURCE_SHA=' + parent)
    return request


def canonical_mode():
    headers = {'User-Agent': 'mobisttech-explicit-ci-gate', 'Accept': 'application/vnd.github+json', 'Cache-Control': 'no-cache'}
    api_request = urllib.request.Request(STATE_API, headers=headers)
    try:
        with urllib.request.urlopen(api_request, timeout=15) as response:
            payload = json.load(response)
        state = json.loads(base64.b64decode(payload['content']).decode('utf-8'))
        print('CI_MODE_STATE_SOURCE=canonical-api')
        return state
    except urllib.error.HTTPError as error:
        if error.code != 403:
            raise
        raw_url = STATE_RAW + '?request=' + os.environ['GITHUB_SHA']
        raw_request = urllib.request.Request(raw_url, headers={'User-Agent': headers['User-Agent'], 'Cache-Control': 'no-cache'})
        with urllib.request.urlopen(raw_request, timeout=15) as response:
            assert response.status == 200, 'Canonical raw state was not available'
            state = json.load(response)
        print('CI_MODE_STATE_SOURCE=canonical-raw-after-api-403')
        return state


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return
    event = os.environ['GITHUB_EVENT_NAME']
    assert event == 'push', 'Unapproved manual CI dispatch; use an audited CI-request commit'
    request = read_request()
    selected = allowed(canonical_mode(), event, request)
    assert selected, 'CI request blocked by effective execution mode or missing justification'
    focus = request['focus'] if request['gate'] == 'verify' else 'full'
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
        output.write('run_tests=true\n')
        output.write('run_scope=' + focus + '\n')
    print('CI_REQUEST_RUN_TESTS=true')
    print('CI_REQUEST_RUN_SCOPE=' + focus)


if __name__ == '__main__':
    main()
