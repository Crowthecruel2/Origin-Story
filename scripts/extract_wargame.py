from __future__ import annotations

import json
import re
import sys
from dataclasses import dataclass
from pathlib import Path
from typing import Any

try:
    from pypdf import PdfReader
except ModuleNotFoundError as exc:  # pragma: no cover
    if exc.name != "pypdf":
        raise
    import sys as _sys

    _py = _sys.executable
    raise SystemExit(
        "Missing dependency: pypdf\n\n"
        "Install it for the Python interpreter you're running:\n"
        f"  {_py} -m pip install -r scripts/requirements.txt\n"
    )


def slugify(value: str) -> str:
    value = (value or "").strip().lower()
    value = re.sub(r"[^a-z0-9]+", "-", value)
    value = re.sub(r"-{2,}", "-", value).strip("-")
    return value or "unknown"


def _despace_pdf_line(line: str) -> str:
    """
    Fix common PDF text extraction where letters are separated by single spaces,
    and *words* are separated by 2+ spaces. Example:
      'C h i l d r e n  o f  C h a n g e' -> 'Children of Change'

    If the line does not look like that pattern, it is returned with whitespace normalized.
    """

    line = (line or "").replace("\u00A0", " ").replace("\u202F", " ").replace("\t", " ")
    if not line.strip():
        return ""

    # Detect "letter-spaced" lines:
    # - word boundaries shown as 2+ spaces (best case), OR
    # - the entire line is single letters separated by spaces (common for headings like "W e a p o n s")
    looks_letter_spaced = bool(re.search(r"[A-Za-z] [A-Za-z]", line))
    has_word_gaps = bool(re.search(r" {2,}", line))
    # 3+ letter tokens, e.g. "H i t" -> "Hit"
    is_all_letter_tokens = bool(re.fullmatch(r"(?:[A-Za-z]\s+){2,}[A-Za-z]", line.strip()))

    if looks_letter_spaced and (has_word_gaps or is_all_letter_tokens):
        placeholder = "\u0000"
        if has_word_gaps:
            line = re.sub(r" {2,}", placeholder, line)
            line = line.replace(" ", "")
            line = line.replace(placeholder, " ")
        else:
            # Single-word heading: just remove all inter-letter whitespace.
            line = re.sub(r"\s+", "", line)

    line = re.sub(r"\s+", " ", line).strip()
    return line


def normalize_page_text(text: str) -> list[str]:
    lines = []
    for raw in (text or "").splitlines():
        cooked = _despace_pdf_line(raw)
        if cooked:
            lines.append(cooked)
    return lines


_STARTING_RE = re.compile(r"^Starting\s+(.+?)\s*=\s*(\d+)\s*$", re.IGNORECASE)


def parse_starting_resource(line: str) -> dict[str, Any] | None:
    m = _STARTING_RE.match(line.strip())
    if not m:
        return None
    return {"name": m.group(1).strip(), "amount": int(m.group(2))}


def split_sections(lines: list[str]) -> dict[str, list[str]]:
    """
    Coarse section splitter for unit pages. Keeps unknown content in 'body'.
    """

    sections: dict[str, list[str]] = {"body": []}
    current = "body"

    def switch(name: str) -> None:
        nonlocal current
        if name not in sections:
            sections[name] = []
        current = name

    for line in lines:
        low = line.lower()
        if low == "weapons":
            switch("weapons")
            continue
        if low.startswith("resistances:"):
            switch("resistances")
        elif low.startswith("immunities:"):
            switch("immunities")
        elif low == "abilities:":
            switch("abilities")
            continue
        sections[current].append(line)
    return sections


@dataclass
class Faction:
    id: str
    name: str
    starting: dict[str, Any] | None
    overview: list[str]
    command_abilities: list[str]
    source_pages: list[int]


@dataclass
class Unit:
    id: str
    name: str
    faction_id: str
    starting_energy: int | None
    header_numbers: list[str]
    sections: dict[str, list[str]]
    raw: str
    source_page: int


def extract(pdf_path: Path) -> tuple[list[Faction], list[Unit]]:
    reader = PdfReader(str(pdf_path))

    factions: list[Faction] = []
    units: list[Unit] = []
    used_unit_ids: set[str] = set()

    current_faction: Faction | None = None

    for page_index, page in enumerate(reader.pages):
        raw_text = page.extract_text() or ""
        lines = normalize_page_text(raw_text)
        if not lines:
            continue

        # Detect a faction overview page: first line is title, second line is "Starting X = N".
        starting = parse_starting_resource(lines[1]) if len(lines) >= 2 else None
        looks_like_faction = (
            len(lines) >= 2
            and starting is not None
            and not any(l.strip().lower() == "weapons" for l in lines[:10])
        )
        if looks_like_faction:
            faction_name = lines[0]
            faction_id = slugify(faction_name)
            overview: list[str] = []
            command_abilities: list[str] = []

            in_command = False
            for l in lines[2:]:
                if l.lower().startswith("command abilities"):
                    in_command = True
                    continue
                if in_command:
                    command_abilities.append(l)
                else:
                    overview.append(l)

            current_faction = Faction(
                id=faction_id,
                name=faction_name,
                starting=starting,
                overview=overview,
                command_abilities=command_abilities,
                source_pages=[page_index + 1],
            )
            factions.append(current_faction)
            continue

        # Detect unit pages: first line is name and there's a "Weapons" header.
        has_weapons = any(l.strip().lower() == "weapons" for l in lines)
        if has_weapons and current_faction is not None:
            name = lines[0]
            base_id = f"{current_faction.id}--{slugify(name)}"
            unit_id = base_id
            if unit_id in used_unit_ids:
                unit_id = f"{base_id}--p{page_index + 1}"
            used_unit_ids.add(unit_id)

            header_numbers: list[str] = []
            starting_energy: int | None = None
            for l in lines[1:12]:
                if l.lower().startswith("starting energy"):
                    m = re.search(r"=\s*(\d+)", l)
                    starting_energy = int(m.group(1)) if m else None
                    break
                if re.fullmatch(r"[+-]?\d+", l) or re.fullmatch(r"\d+d\d+(\s*\+\s*\d+)?", l):
                    header_numbers.append(l)

            sections = split_sections(lines)
            cooked_raw = "\n".join(lines)
            units.append(
                Unit(
                    id=unit_id,
                    name=name,
                    faction_id=current_faction.id,
                    starting_energy=starting_energy,
                    header_numbers=header_numbers,
                    sections=sections,
                    raw=cooked_raw,
                    source_page=page_index + 1,
                )
            )
            continue

        # Attach additional faction pages to current faction (tables/extra rules).
        if current_faction is not None and (lines[0] == current_faction.name):
            current_faction.source_pages.append(page_index + 1)

    return factions, units


def to_js(data: dict[str, Any]) -> str:
    payload = json.dumps(data, ensure_ascii=False, separators=(",", ":"), indent=2)
    return (
        "// Generated by scripts/extract_wargame.py\n"
        "// Do not hand-edit; update the PDF or extraction rules.\n"
        f"window.WARGAME_DATA = {payload};\n"
    )


def main(argv: list[str]) -> int:
    repo_root = Path(__file__).resolve().parents[1]
    pdf_path = repo_root / "2nd Edition Final.pdf"
    out_path = repo_root / "wargame-data.js"

    if len(argv) >= 2:
        pdf_path = Path(argv[1]).resolve()
    if len(argv) >= 3:
        out_path = Path(argv[2]).resolve()

    if not pdf_path.exists():
        print(f"PDF not found: {pdf_path}", file=sys.stderr)
        return 2

    factions, units = extract(pdf_path)

    data: dict[str, Any] = {
        "schemaVersion": 1,
        "source": {
            "pdf": pdf_path.name,
            "pages": len(PdfReader(str(pdf_path)).pages),
        },
        "factions": [
            {
                "id": f.id,
                "name": f.name,
                "starting": f.starting,
                "overview": f.overview,
                "commandAbilities": f.command_abilities,
                "sourcePages": f.source_pages,
            }
            for f in factions
        ],
        "units": [
            {
                "id": u.id,
                "name": u.name,
                "factionId": u.faction_id,
                "startingEnergy": u.starting_energy,
                "headerNumbers": u.header_numbers,
                "sections": u.sections,
                "raw": u.raw,
                "sourcePage": u.source_page,
            }
            for u in units
        ],
    }

    out_path.write_text(to_js(data), encoding="utf-8")
    print(f"Wrote {out_path} with {len(factions)} factions and {len(units)} units.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
