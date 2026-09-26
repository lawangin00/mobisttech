"""Validate the preserved committed source snapshot after local source retirement."""
from pathlib import Path
import json

ROOT = Path(__file__).resolve().parents[2]
OUTPUT = ROOT / "docs" / "SOURCE_SNAPSHOT.json"
EXPECTED = {
    "c61e47394e7b3db8a49cfe443c85b63835febc9b",
    "04e7c49518f9f11f60c83ad44f9f4e2fd2539066",
}

def main() -> None:
    if not OUTPUT.is_file():
        raise SystemExit("SOURCE_SNAPSHOT_BLOCK: committed snapshot is missing")
    data = json.loads(OUTPUT.read_text(encoding="utf-8"))
    commits = {source.get("head") for source in data.get("sources", [])}
    if commits != EXPECTED:
        raise SystemExit("SOURCE_SNAPSHOT_BLOCK: preserved source heads differ from accepted baseline")
    print("SOURCE_SNAPSHOT_PASS: committed historical snapshot verified; retired local checkouts not required")

if __name__ == "__main__":
    main()
