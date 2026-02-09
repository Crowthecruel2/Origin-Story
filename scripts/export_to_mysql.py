from __future__ import annotations

import argparse
import json
import re
import sys
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable


def read_json(path: Path) -> Any:
    return json.loads(path.read_text(encoding="utf-8-sig"))


_WARGAME_JS_RE = re.compile(r"const\s+WARGAME_DATA\s*=\s*(\{.*\});\s*\Z", re.S)


def read_wargame_js(path: Path) -> dict[str, Any]:
    text = path.read_text(encoding="utf-8-sig")
    match = _WARGAME_JS_RE.search(text)
    if not match:
        raise ValueError(f"Could not find WARGAME_DATA object in {path}")
    return json.loads(match.group(1))


def sql_str(value: Any) -> str:
    if value is None:
        return "NULL"
    s = str(value)
    s = s.replace("\\", "\\\\").replace("\x00", "\\0").replace("\n", "\\n").replace("\r", "\\r").replace("\t", "\\t")
    s = s.replace("'", "''")
    return f"'{s}'"


def sql_json(value: Any) -> str:
    # MySQL JSON columns accept a quoted JSON string literal.
    return sql_str(json.dumps(value, ensure_ascii=False, separators=(",", ":")))


def chunked(rows: list[tuple[str, ...]], n: int) -> Iterable[list[tuple[str, ...]]]:
    for i in range(0, len(rows), n):
        yield rows[i : i + n]


def emit_inserts(out: list[str], table: str, columns: list[str], rows: list[tuple[str, ...]], batch: int = 200) -> None:
    if not rows:
        return
    cols = ",".join(f"`{c}`" for c in columns)
    for part in chunked(rows, batch):
        values = ",\n  ".join("(" + ",".join(r) + ")" for r in part)
        out.append(f"INSERT INTO `{table}` ({cols}) VALUES\n  {values};\n")


def export(repo_root: Path, out_path: Path) -> None:
    schema_path = repo_root / "scripts" / "schema.mysql.sql"
    powers_path = repo_root / "powers.json"
    items_path = repo_root / "items.json"
    rpg_factions_path = repo_root / "factions" / "factions.json"
    wargame_js_path = repo_root / "wargame-data.js"

    if not schema_path.exists():
        raise FileNotFoundError(schema_path)
    for required in [powers_path, items_path, rpg_factions_path, wargame_js_path]:
        if not required.exists():
            raise FileNotFoundError(required)

    powers_doc = read_json(powers_path)
    items_doc = read_json(items_path)
    rpg_factions_doc = read_json(rpg_factions_path)
    wargame = read_wargame_js(wargame_js_path)

    out: list[str] = []
    out.append("-- Generated export for MySQL 8+\n")
    out.append("SET NAMES utf8mb4;\n")
    out.append("SET time_zone = '+00:00';\n")
    out.append("SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';\n\n")
    out.append("START TRANSACTION;\n\n")
    out.append(schema_path.read_text(encoding="utf-8"))
    out.append("\n")

    now = datetime.now(timezone.utc).isoformat()
    meta_rows = [
        (sql_str("generated_at_utc"), sql_str(now)),
        (sql_str("repo_root"), sql_str(str(repo_root))),
        (sql_str("powers_schema_version"), sql_str(str(powers_doc.get("schemaVersion", "")))),
        (sql_str("wargame_source_pdf"), sql_str(str(wargame.get("source", {}).get("pdf", "")))),
    ]
    emit_inserts(out, "meta", ["key", "value"], meta_rows, batch=1000)

    # ---- power_classes + powers + power_levels ----
    class_names: set[str] = set()
    powers_rows: list[tuple[str, ...]] = []
    power_level_rows: list[tuple[str, ...]] = []

    for cls in powers_doc.get("classes", []) or []:
        class_name = cls.get("class_name")
        if class_name:
            class_names.add(class_name)
        for p in cls.get("all_class_powers", []) or []:
            pid = p.get("id")
            if not pid:
                continue
            p_class = p.get("class_name") or class_name or "Unknown"
            class_names.add(p_class)
            powers_rows.append(
                (
                    sql_str(pid),
                    sql_str(p.get("name") or ""),
                    sql_str(p_class),
                    sql_str(p.get("path")),
                    sql_str(p.get("description")),
                    sql_str(p.get("content")),
                    str(int(p.get("min_level"))) if p.get("min_level") is not None else "NULL",
                    sql_json(p.get("prerequisites") or []),
                    sql_json(p.get("tags") or []),
                )
            )
            for idx, s in enumerate(p.get("all_sub_powers") or []):
                power_level_rows.append(
                    (
                        sql_str(pid),
                        str(idx),
                        str(int(s.get("level"))) if s.get("level") is not None else "NULL",
                        str(int(s.get("cost"))) if s.get("cost") is not None else "NULL",
                        sql_str(s.get("text")),
                    )
                )

    class_rows = [(sql_str(n),) for n in sorted(class_names)]
    emit_inserts(out, "power_classes", ["name"], class_rows, batch=500)
    emit_inserts(
        out,
        "powers",
        ["id", "name", "class_name", "path", "description", "content", "min_level", "prerequisites_json", "tags_json"],
        powers_rows,
        batch=200,
    )
    emit_inserts(out, "power_levels", ["power_id", "idx", "level", "cost", "text"], power_level_rows, batch=300)

    # ---- items ----
    items_rows_by_id: dict[str, tuple[str, ...]] = {}
    for it in items_doc.get("items", []) or []:
        iid = it.get("id")
        if not iid:
            continue
        items_rows_by_id[iid] = (
            (
                sql_str(iid),
                sql_str(it.get("name") or ""),
                sql_str(it.get("from_power")),
                sql_str(it.get("class_name")),
                sql_str(it.get("description")),
                sql_str(it.get("effects")),
                sql_str(it.get("cost")),
                sql_json(it.get("prerequisites") or []),
            )
        )
    items_rows = list(items_rows_by_id.values())
    emit_inserts(
        out,
        "items",
        ["id", "name", "from_power", "class_name", "description", "effects", "cost", "prerequisites_json"],
        items_rows,
        batch=200,
    )

    # ---- rpg_factions ----
    rf_rows: list[tuple[str, ...]] = []
    for f in rpg_factions_doc.get("factions", []) or []:
        slug = f.get("slug")
        if not slug:
            continue
        rf_rows.append((sql_str(slug), sql_str(f.get("name") or ""), sql_str(f.get("blurb")), sql_str(f.get("page"))))
    emit_inserts(out, "rpg_factions", ["slug", "name", "blurb", "page"], rf_rows, batch=200)

    # ---- wargame ----
    wg_f_rows: list[tuple[str, ...]] = []
    for f in wargame.get("factions", []) or []:
        starting = f.get("starting") or {}
        wg_f_rows.append(
            (
                sql_str(f.get("id")),
                sql_str(f.get("name") or ""),
                sql_str(starting.get("name")),
                str(int(starting.get("amount"))) if starting.get("amount") is not None else "NULL",
                sql_json(f.get("overview") or []),
                sql_json(f.get("commandAbilities") or []),
                sql_json(f.get("sourcePages") or []),
            )
        )
    emit_inserts(
        out,
        "wargame_factions",
        ["id", "name", "starting_name", "starting_amount", "overview_json", "command_abilities_json", "source_pages_json"],
        wg_f_rows,
        batch=200,
    )

    wg_u_rows: list[tuple[str, ...]] = []
    for u in wargame.get("units", []) or []:
        uid = u.get("id")
        if not uid:
            continue
        wg_u_rows.append(
            (
                sql_str(uid),
                sql_str(u.get("name") or ""),
                sql_str(u.get("factionId") or ""),
                str(int(u.get("startingEnergy"))) if u.get("startingEnergy") is not None else "NULL",
                sql_json(u.get("headerNumbers") or []),
                sql_json(u.get("sections") or {}),
                sql_str(u.get("raw")),
                str(int(u.get("sourcePage"))) if u.get("sourcePage") is not None else "NULL",
            )
        )
    emit_inserts(
        out,
        "wargame_units",
        ["id", "name", "faction_id", "starting_energy", "header_numbers_json", "sections_json", "raw", "source_page"],
        wg_u_rows,
        batch=200,
    )

    out.append("COMMIT;\n")

    out_path.parent.mkdir(parents=True, exist_ok=True)
    out_path.write_text("".join(out), encoding="utf-8")


def main(argv: list[str]) -> int:
    repo_root = Path(__file__).resolve().parents[1]

    parser = argparse.ArgumentParser(description="Export Brighton datasets to a single MySQL .sql file.")
    parser.add_argument("--out", default=str(repo_root / "dist" / "brighton.mysql.sql"), help="Output .sql path.")
    args = parser.parse_args(argv)

    try:
        export(repo_root=repo_root, out_path=Path(args.out).expanduser())
    except Exception as e:
        print(f"Export failed: {e}", file=sys.stderr)
        return 1

    print(f"OK: wrote {args.out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv[1:]))
