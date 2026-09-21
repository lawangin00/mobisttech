#!/usr/bin/env python3
"""Resolve canonical GitHub/RDC mode before a hosted development test.

A workflow_dispatch is an explicitly requested milestone/verification run in either
mode. An ordinary push runs product verification only in effective GITHUB mode.
The gate itself is a short Actions job on each relevant push; a remote lookup
failure fails closed instead of silently certifying an untested GitHub commit.
"""
import argparse
import base64
import json
import os
import urllib.request

PROJECT_ID = "282dba2f-a2d9-47e8-aa8d-e499fbe1706c"
STATE_API = "https://api.github.com/repos/lawangin00/references/contents/UNIVERSAL_EXECUTION_MODE.json?ref=main"


def should_run(state, event, project_id=PROJECT_ID):
    assert state.get("schema_version") == 1, "Unexpected execution-mode schema"
    assert state.get("global_mode") in ("GITHUB", "RDC"), "Invalid global mode"
    exceptions = state.get("exceptions")
    assert isinstance(exceptions, dict), "Invalid exceptions"
    assert all(value in ("GITHUB", "RDC") for value in exceptions.values()), "Invalid exception mode"
    assert event in ("push", "workflow_dispatch"), "Unsupported CI event"
    return event == "workflow_dispatch" or exceptions.get(project_id, state["global_mode"]) == "GITHUB"


def self_test():
    assert should_run({"schema_version": 1, "global_mode": "GITHUB", "exceptions": {}}, "push")
    assert not should_run({"schema_version": 1, "global_mode": "RDC", "exceptions": {}}, "push")
    assert should_run({"schema_version": 1, "global_mode": "RDC", "exceptions": {PROJECT_ID: "GITHUB"}}, "push")
    assert not should_run({"schema_version": 1, "global_mode": "GITHUB", "exceptions": {PROJECT_ID: "RDC"}}, "push")
    assert should_run({"schema_version": 1, "global_mode": "RDC", "exceptions": {}}, "workflow_dispatch")
    try:
        should_run({"schema_version": 1, "global_mode": "WRONG", "exceptions": {}}, "push")
    except AssertionError:
        pass
    else:
        raise AssertionError("Invalid canonical mode did not fail closed")
    print("CI_MODE_GATE_FIXTURES=PASS")


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--self-test", action="store_true")
    args = parser.parse_args()
    if args.self_test:
        self_test()
        return
    event = os.environ["GITHUB_EVENT_NAME"]
    if event == "workflow_dispatch":
        # Milestone/manual verification is required even while local/RDC mode is active.
        selected = True
    else:
        request = urllib.request.Request(STATE_API, headers={"User-Agent": "mobisttech-ci-mode-gate", "Accept": "application/vnd.github+json", "Cache-Control": "no-cache"})
        with urllib.request.urlopen(request, timeout=15) as response:
            payload = json.load(response)
        state = json.loads(base64.b64decode(payload["content"]).decode("utf-8"))
        selected = should_run(state, event)
    value = "true" if selected else "false"
    with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as output:
        output.write("run_tests=" + value + "\n")
    print("CI_MODE_GATE_RUN_TESTS=" + value)


if __name__ == "__main__":
    main()
