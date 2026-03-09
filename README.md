- Website for my own use. Used for my TTRPG inspired by old 80's superhero comics
- Repo Functions as a obsidian vault.
- Used mostly to share with my friends for feedback

## Data exports

This repo keeps content in JSON/JS for the static site, but you can also generate a local SQLite database for querying.

Generate/update wargame extraction (creates `public/wargame-data.js`):

- `python scripts/extract_wargame.py`

Migrate powers/items/wargame/RPG factions into SQLite (creates `data/brighton.sqlite`):

- `python scripts/migrate_to_sqlite.py --reset`

Export to MySQL (single import file for shared hosts / phpMyAdmin; creates `dist/brighton.mysql.sql`):

- `python scripts/export_to_mysql.py`

## MySQL + PHP (shared host)

This repo includes:

- Website deployables under `public/` (set your web root/docroot to this folder)
- Public JSON endpoints under `public/api/` (used by the site)
- A small admin panel under `public/admin/` (CRUD for powers/items/factions/wargame)
- GM content tools:
  - `public/gm.html` (GM-facing page for lore + enemies)
  - `public/quickstart.html` (DB-backed rules page with search)
  - `public/mobile/index.html` (PWA/mobile-first GM workflow)
  - `public/mobile/timeline.html` (mobile timeline)
  - `public/mobile/enemies.html` (mobile enemies)
  - `public/admin/gm-lore.php` (CRUD for long lore sections)
  - `public/admin/enemies.php` (CRUD for enemy sheets)
  - `public/admin/rules.php` (CRUD for rules sections with rich text)

Setup:

1) Create `config.php` from `config.php.example` and fill in DB credentials
2) Generate/import the MySQL dump:
   - `python scripts/export_to_mysql.py`
   - Import `dist/brighton.mysql.sql` in phpMyAdmin (Import tab)
3) Create your first admin user:
   - Set `admin.setup_token` in `config.php` to a random value
   - Visit `admin/setup.php?token=YOUR_TOKEN` (from the `public/` docroot)
   - After creating the user, remove/blank the token
