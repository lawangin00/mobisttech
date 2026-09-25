#!/usr/bin/env python3
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
PATH = ROOT / "docs/audit/MT-7.5_GL_OWNER_LEGAL_INTAKE.json"
data = json.loads(PATH.read_text(encoding="utf-8"))

missing: list[str] = []

def walk(value, path=""):
    if isinstance(value, dict):
        for k, v in value.items():
            p = f"{path}.{k}" if path else k
            if p.startswith("technical_facts_locked"):
                continue
            if p == "historical_owner_input.pos_invoice_clause_urdu":
                continue
            if p == "trading_identity.public_business_name":
                continue
            if v is None:
                missing.append(p)
            elif isinstance(v, dict):
                walk(v, p)

walk(data)

if data.get("trading_identity", {}).get("registered_legal_holder_verified") is not True:
    missing.append("trading_identity.registered_legal_holder_verified=true")
approval = data.get("final_approval", {})
for key in ("exact_final_text_reviewed_by_owner", "exact_final_text_owner_approved"):
    if approval.get(key) is not True:
        missing.append(f"final_approval.{key}=true")

if missing:
    print("HOLD: 26/G-L owner/legal intake incomplete")
    for item in sorted(set(missing)):
        print(f"- {item}")
    raise SystemExit(2)

print("PASS: 26/G-L owner/legal intake complete")
