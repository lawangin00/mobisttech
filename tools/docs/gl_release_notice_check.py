#!/usr/bin/env python3
from __future__ import annotations

import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
AUDIT_ANCHOR = "bf5adbc4fd5e6afaeccf50b6cbbdd8b843d2412c"
LOCKFILES = (
    "backend/composer.lock",
    "backend/package-lock.json",
    "website/package-lock.json",
)


def git_bytes(ref: str, path: str) -> bytes:
    return subprocess.check_output(["git", "-C", str(ROOT), "show", f"{ref}:{path}"])


def fail(message: str) -> None:
    raise SystemExit(f"FAIL: {message}")


for rel in LOCKFILES:
    current = (ROOT / rel).read_bytes()
    baseline = git_bytes(AUDIT_ANCHOR, rel)
    if current != baseline:
        fail(f"dependency lock drift: {rel}")

if (ROOT / "LICENSE").exists():
    fail("root LICENSE exists before exact legal holder/final wording approval")

composer = json.loads((ROOT / "backend/composer.json").read_text(encoding="utf-8"))
if "license" in composer:
    fail("backend/composer.json contains a root application license field")

notice = (ROOT / "NOTICE.md").read_text(encoding="utf-8")
required_notice_phrases = (
    "Owner decision D09=A",
    "proprietary / all rights reserved",
    "exact registered sole-proprietor legal copyright holder remains VERIFY/HOLD",
    "`sharp` and platform Sharp packages",
    "`@img/sharp-libvips-*`",
    "caniuse-lite",
)
for phrase in required_notice_phrases:
    if phrase not in notice:
        fail(f"NOTICE missing required posture: {phrase}")

tracker_patterns = (
    r"googletagmanager",
    r"google-analytics",
    r"\bgtag\s*\(",
    r"\bfbq\s*\(",
    r"segment\.com",
    r"mixpanel",
    r"hotjar",
    r"clarity\s*\(",
    r"plausible",
    r"matomo",
)
scan_roots = (ROOT / "backend/resources/js", ROOT / "website/src")
for scan_root in scan_roots:
    for path in scan_root.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in {".js", ".jsx", ".ts", ".tsx"}:
            continue
        text = path.read_text(encoding="utf-8", errors="ignore")
        for pattern in tracker_patterns:
            if re.search(pattern, text, re.IGNORECASE):
                fail(f"non-essential tracker marker found: {path.relative_to(ROOT)} / {pattern}")

print(f"PASS: G-L release notice preflight at {subprocess.check_output(['git','-C',str(ROOT),'rev-parse','HEAD'], text=True).strip()}")
print(f"AUDIT_ANCHOR={AUDIT_ANCHOR}")
print("DEPENDENCY_LOCK_DRIFT=NONE")
print("ROOT_LICENSE=ABSENT_PENDING_EXACT_HOLDER")
print("COMPOSER_ROOT_LICENSE_FIELD=ABSENT")
print("NOTICE_D09_POSTURE=PASS")
print("NONESSENTIAL_TRACKER_SCAN=NONE")
