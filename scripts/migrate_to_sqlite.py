from __future__ import annotations

import argparse
import json
import re
import sqlite3
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def read_json(path: Path) -> Any:
    # Some files in this repo include a UTF-8 BOM.
    return json.loads(path.read_text(encoding="utf-8-sig"))


_WARGAME_JS_RE = re.compile(r"const\s+WARGAME_DATA\s*=\s*(\{.*\});\s*\Z", re.S)


def read_wargame_js(path: Path) -> dict[str, Any]:
    text = path.read_text(encoding="utf-8-sig")
    match = _WARGAME_JS_RE.search(text)
    if not match:
        raise ValueError(f"Could not find WARGAME_DATA object in {path}")
    return json.loads(match.group(1))


def dump_json(value: Any) -> str:
    return json.dumps(value, ensure_ascii=False, separators=(",", ":"))


def ensure_parent_dir(path: Path) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)


def apply_schema(conn: sqlite3.Connection, schema_path: Path) -> None:
    conn.executescript(schema_path.read_text(encoding="utf-8"))


def reset_tables(conn: sqlite3.Connection) -> None:
    conn.executescript(
        """
        PRAGMA foreign_keys = OFF;
        DROP TABLE IF EXISTS admin_users;
        DROP TABLE IF EXISTS power_levels;
        DROP TABLE IF EXISTS powers;
        DROP TABLE IF EXISTS power_classes;
        DROP TABLE IF EXISTS items;
        DROP TABLE IF EXISTS rpg_factions;
        DROP TABLE IF EXISTS wargame_units;
        DROP TABLE IF EXISTS wargame_factions;
        DROP TABLE IF EXISTS meta;
        PRAGMA foreign_keys = ON;
        """
    )


def migrate(repo_root: Path, db_path: Path, reset: bool) -> None:
    schema_path = repo_root / "scripts" / "schema.sql"
    powers_path = repo_root / "powers.json"
    items_path = repo_root / "items.json"
    rpg_factions_path = repo_root / "factions" / "factions.json"
    wargame_js_path = repo_root / "wargame-data.js"

    if not schema_path.exists():
        raise FileNotFoundError(schema_path)
    for required in [powers_path, items_path, rpg_factions_path]:
        if not required.exists():
            raise FileNotFoundError(required)
    if not wargame_js_path.exists():
        raise FileNotFoundError(
            f"{wargame_js_path} not found. Generate it first via: python scripts/extract_wargame.py"
        )

    ensure_parent_dir(db_path)
    conn = sqlite3.connect(str(db_path))
    conn.row_factory = sqlite3.Row
    conn.execute("PRAGMA foreign_keys = ON;")

    try:
        if reset:
            reset_tables(conn)
        apply_schema(conn, schema_path)

        now = datetime.now(timezone.utc).isoformat()
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("generated_at_utc", now))
        conn.execute("INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)", ("repo_root", str(repo_root)))

        # ---- Powers ----
        powers_doc = read_json(powers_path)
        conn.execute(
            "INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)",
            ("powers_schema_version", str(powers_doc.get("schemaVersion", ""))),
        )

        classes = powers_doc.get("classes", [])
        for cls in classes:
            class_name = cls.get("class_name")
            if not class_name:
                continue
            conn.execute("INSERT OR IGNORE INTO power_classes(name) VALUES(?)", (class_name,))

            for p in cls.get("all_class_powers", []) or []:
                pid = p.get("id")
                if not pid:
                    continue
                conn.execute(
                    """
                    INSERT OR REPLACE INTO powers(
                      id,name,class_name,path,description,content,min_level,prerequisites_json,tags_json
                    ) VALUES(?,?,?,?,?,?,?,?,?)
                    """,
                    (
                        pid,
                        p.get("name") or "",
                        p.get("class_name") or class_name,
                        p.get("path"),
                        p.get("description"),
                        p.get("content"),
                        p.get("min_level"),
                        dump_json(p.get("prerequisites") or []),
                        dump_json(p.get("tags") or []),
                    ),
                )

                conn.execute("DELETE FROM power_levels WHERE power_id = ?", (pid,))
                sub = p.get("all_sub_powers") or []
                for idx, s in enumerate(sub):
                    conn.execute(
                        "INSERT INTO power_levels(power_id,idx,level,cost,text) VALUES(?,?,?,?,?)",
                        (
                            pid,
                            idx,
                            s.get("level"),
                            s.get("cost"),
                            s.get("text"),
                        ),
                    )

        # ---- Items ----
        items_doc = read_json(items_path)
        items = items_doc.get("items", [])
        conn.execute(
            "INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)",
            ("items_count_source", str(len(items))),
        )
        conn.execute("DELETE FROM items;")
        for it in items:
            iid = it.get("id")
            if not iid:
                continue
            conn.execute(
                """
                INSERT OR REPLACE INTO items(
                  id,name,from_power,class_name,description,effects,cost,prerequisites_json
                ) VALUES(?,?,?,?,?,?,?,?)
                """,
                (
                    iid,
                    it.get("name") or "",
                    it.get("from_power"),
                    it.get("class_name"),
                    it.get("description"),
                    it.get("effects"),
                    it.get("cost"),
                    dump_json(it.get("prerequisites") or []),
                ),
            )

        # ---- RPG factions ----
        rpg_factions_doc = read_json(rpg_factions_path)
        rf = rpg_factions_doc.get("factions", []) or []
        conn.execute("DELETE FROM rpg_factions;")
        for f in rf:
            slug = f.get("slug")
            if not slug:
                continue
            conn.execute(
                "INSERT OR REPLACE INTO rpg_factions(slug,name,blurb,page) VALUES(?,?,?,?)",
                (slug, f.get("name") or "", f.get("blurb"), f.get("page")),
            )

        # ---- Wargame ----
        wargame = read_wargame_js(wargame_js_path)
        conn.execute(
            "INSERT OR REPLACE INTO meta(key,value) VALUES(?,?)",
            ("wargame_source_pdf", str(wargame.get("source", {}).get("pdf", ""))),
        )

        conn.execute("DELETE FROM wargame_units;")
        conn.execute("DELETE FROM wargame_factions;")

        for f in wargame.get("factions", []) or []:
            starting = f.get("starting") or {}
            conn.execute(
                """
                INSERT OR REPLACE INTO wargame_factions(
                  id,name,starting_name,starting_amount,overview_json,command_abilities_json,source_pages_json
                ) VALUES(?,?,?,?,?,?,?)
                """,
                (
                    f.get("id"),
                    f.get("name") or "",
                    starting.get("name"),
                    starting.get("amount"),
                    dump_json(f.get("overview") or []),
                    dump_json(f.get("commandAbilities") or []),
                    dump_json(f.get("sourcePages") or []),
                ),
            )

        for u in wargame.get("units", []) or []:
            uid = u.get("id")
            if not uid:
                continue
            conn.execute(
                """
                INSERT OR REPLACE INTO wargame_units(
                  id,name,faction_id,starting_energy,header_numbers_json,sections_json,raw,source_page
                ) VALUES(?,?,?,?,?,?,?,?)
                """,
                (
                    uid,
                    u.get("name") or "",
                    u.get("factionId") or "",
                    u.get("startingEnergy"),
                    dump_json(u.get("headerNumbers") or []),
                    dump_json(u.get("sections") or {}),
                    u.get("raw"),
                    u.get("sourcePage"),
                ),
            )

        conn.commit()
    finally:
        conn.close()


def main(argv: list[str]) -> int:
    repo_root = Path(__file__).resolve().parents[1]

    parser = argparse.ArgumentParser(description="Migrate repo JSON/JS content into a SQLite database.")
    parser.add_argument("--db", default=str(repo_root / "data" / "brighton.sqlite"), help="Output SQLite file path.")
    parser.add_argument("--reset", action="store_true", help="Drop and recreate tables (destructive).")
    args = parser.parse_args(argv)

    db_path = Path(args.db).expanduser()
    try:
        migrate(repo_root=repo_root, db_path=db_path, reset=args.reset)
    except Exception as e:
        print(f"Migration failed: {e}", file=sys.stderr)
        return 1

    print(f"OK: wrote {db_path}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
