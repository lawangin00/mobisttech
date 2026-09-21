#!/usr/bin/env python3
"""Fail-closed explicit-request CI gate for Mobisttech.

Normal source pushes trigger NO workflow. A controller creates one request file
in a separate commit for each GitHub-mode stage or approved RDC milestone.
"""
import argparse
import base64
import json
import os
from pathlib import Path
import subprocess
import urllib.request

PROJECT_ID = '282dba2f-a2d9-47e8-aa8d-e499fbe1706c'
STATE_API = 'https://api.github.com/repos/lawangin00/references/contents/UNIVERSAL_EXECUTION_MODE.json?ref=main'
REQUEST_ROOT = '.github/ci-requests/'


def allowed(state, event, request=None):
    assert state.get('schema_version') == 1
    assert state.get('global_mode') in ('GITHUB', 'RDC')
    exceptions = state.get('exceptions')
    assert isinstance(exceptions, dict)
    assert all(value in ('GITHUB', 'RDC') for value in exceptions.values())
    if event == 'workflow_dispatch':
        return True
    assert event == 'push' and isinstance(request, dict)
    assert request.get('project_id') == PROJECT_ID
    assert request.get('reason') in ('stage', 'milestone', 'necessary')
    assert request.get('gate') in ('website', 'full', 'all', 'verify')
    mode = exceptions.get(PROJECT_ID, state['global_mode'])
    return mode == 'GITHUB' or request['reason'] in ('milestone', 'necessary')


def self_test():
    gh = {'schema_version': 1, 'global_mode': 'GITHUB', 'exceptions': {}}
    local = {'schema_version': 1, 'global_mode': 'RDC', 'exceptions': {}}
    request = {'project_id': PROJECT_ID, 'reason': 'stage', 'gate': 'all'}
    assert allowed(gh, 'push', request)
    assert not allowed(local, 'push', request)
    assert allowed(local, 'push', {**request, 'reason': 'milestone'})
    assert allowed(local, 'workflow_dispatch')
    assert not allowed({**gh, 'exceptions': {PROJECT_ID: 'RDC'}}, 'push', request)
    assert allowed({**local, 'exceptions': {PROJECT_ID: 'GITHUB'}}, 'push', request)
    try:
        allowed(local, 'push', {**request, 'project_id': 'wrong'})
    except AssertionError:
        pass
    else:
        raise AssertionError('Cross-project CI request accepted')
    print('CI_MODE_REQUEST_FIXTURES=PASS')


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


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--self-test', action='store_true')
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return
    event = os.environ['GITHUB_EVENT_NAME']
    if event == 'workflow_dispatch':
        selected = True
    else:
        assert event == 'push', 'Unexpected CI event'
        request = read_request()
        api_request = urllib.request.Request(STATE_API, headers={'User-Agent': 'mobisttech-explicit-ci-gate', 'Accept': 'application/vnd.github+json', 'Cache-Control': 'no-cache'})
        with urllib.request.urlopen(api_request, timeout=15) as response:
            payload = json.load(response)
        state = json.loads(base64.b64decode(payload['content']).decode('utf-8'))
        selected = allowed(state, event, request)
    result = 'true' if selected else 'false'
    with open(os.environ['GITHUB_OUTPUT'], 'a', encoding='utf-8') as output:
        output.write('run_tests=' + result + '\n')
    print('CI_REQUEST_RUN_TESTS=' + result)


if __name__ == '__main__':
    main()
