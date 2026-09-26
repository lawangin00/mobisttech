"""Validate the committed legacy-source evidence after local source retirement.

The original local POS/Website checkouts are no longer project dependencies.
This tool validates only the committed characterization evidence already captured
inside the Mobisttech repository; it never accesses retired source paths.
"""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[2]
REQUIRED = [
    ROOT / "docs" / "SOURCE_SNAPSHOT.json",
    ROOT / "docs" / "migration" / "SOURCE_FILE_INVENTORY.json",
    ROOT / "docs" / "migration" / "SOURCE_SYMBOL_INVENTORY.json",
    ROOT / "docs" / "migration" / "CHARACTERIZATION.md",
    ROOT / "docs" / "migration" / "FEATURE_PARITY_REGISTER.md",
]
EXPECTED_COMMITS = {
    "pos": "c61e47394e7b3db8a49cfe443c85b63835febc9b",
    "website": "04e7c49518f9f11f60c83ad44f9f4e2fd2539066",
}

def main() -> None:
    missing = [str(path.relative_to(ROOT)) for path in REQUIRED if not path.is_file()]
    if missing:
        raise SystemExit("LEGACY_SOURCE_EVIDENCE_BLOCK: missing committed evidence: " + ", ".join(missing))
    inventory = json.loads((ROOT / "docs" / "migration" / "SOURCE_FILE_INVENTORY.json").read_text(encoding="utf-8"))
    sources = inventory.get("sources", [])
    commits = {entry.get("source"): entry.get("commit") for entry in sources}
    if commits != EXPECTED_COMMITS:
        raise SystemExit("LEGACY_SOURCE_EVIDENCE_BLOCK: pinned source commits do not match the accepted baseline")
    print("LEGACY_SOURCE_EVIDENCE_PASS: committed inventories/snapshots present; local legacy checkouts not required")

if __name__ == "__main__":
    main()
