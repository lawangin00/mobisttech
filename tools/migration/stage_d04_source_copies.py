"""Stage consistent read-only D04 SQLite snapshots without stopping source apps.

This utility never hashes or copies an actively opened database as a raw file.
SQLite's online backup API creates transactionally consistent private snapshots.
No source row, count, hash, path detail, or database bytes are printed.
"""
from __future__ import annotations

import sqlite3
import sys
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
STAGE = ROOT / ".local" / "mt75" / "d04"
SOURCES = {
    "pos": Path(r"C:\mobiST\mobiST-POS\database\database.sqlite"),
    "website": Path(r"C:\mobiST\mobiST-Website\database\database.sqlite"),
}


def _sidecars(database: Path) -> list[Path]:
    return [Path(str(database) + suffix) for suffix in ("-wal", "-shm", "-journal")]


def _connect_read_only(database: Path) -> sqlite3.Connection:
    connection = sqlite3.connect(database.resolve().as_uri() + "?mode=ro", uri=True, timeout=1)
    connection.execute("PRAGMA query_only=ON")
    return connection


def stage() -> None:
    local = (ROOT / ".local").resolve()
    if any(path.is_symlink() for path in (ROOT / ".local", ROOT / ".local" / "mt75", STAGE)):
        raise ValueError("private staging boundary is linked")
    STAGE.mkdir(parents=True, exist_ok=True)
    if not STAGE.resolve().is_relative_to(local):
        raise ValueError("private staging escaped the project")

    completed: list[Path] = []
    try:
        for name, source in SOURCES.items():
            if not source.is_file() or source.is_symlink() or any(path.exists() for path in _sidecars(source)):
                raise ValueError("protected source is absent, linked, or has a live SQLite sidecar")
            destination = STAGE / f"{name}.sqlite"
            temporary = STAGE / f".{name}.sqlite.tmp"
            for path in (destination, temporary):
                if path.exists():
                    if path.is_symlink() or path.resolve().parent != STAGE.resolve():
                        raise ValueError("unsafe existing staged path")
                    path.unlink()

            with _connect_read_only(source) as origin:
                before = origin.execute("PRAGMA data_version").fetchone()[0]
                if origin.execute("PRAGMA quick_check").fetchone()[0] != "ok":
                    raise ValueError("protected source integrity check failed")
                with sqlite3.connect(temporary, timeout=1) as snapshot:
                    origin.backup(snapshot)
                after = origin.execute("PRAGMA data_version").fetchone()[0]
                if before != after:
                    raise ValueError("protected source changed during snapshot")

            with _connect_read_only(temporary) as snapshot:
                if snapshot.execute("PRAGMA quick_check").fetchone()[0] != "ok":
                    raise ValueError("staged snapshot integrity check failed")
            temporary.replace(destination)
            completed.append(destination)
    except Exception:
        for path in list(STAGE.glob("*.sqlite")) + list(STAGE.glob(".*.sqlite.tmp")):
            if not path.is_symlink() and path.resolve().parent == STAGE.resolve():
                path.unlink(missing_ok=True)
        raise

    if len(completed) != 2:
        raise ValueError("incomplete source snapshot set")
    print("D04_SQLITE_BACKUP_STAGE_PASS: 2 consistent private snapshots; source read-only; details suppressed.")


if __name__ == "__main__":
    try:
        stage()
    except Exception as error:
        print("D04_SQLITE_BACKUP_STAGE_BLOCK: " + type(error).__name__, file=sys.stderr)
        sys.exit(1)
