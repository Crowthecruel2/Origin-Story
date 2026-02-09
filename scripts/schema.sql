PRAGMA foreign_keys = ON;

-- Core metadata (who/when generated, schema version, etc.)
CREATE TABLE IF NOT EXISTS meta (
  key TEXT PRIMARY KEY,
  value TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS admin_users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  last_login_at TEXT
);

-- RPG (Origin Story) powers + items
CREATE TABLE IF NOT EXISTS power_classes (
  name TEXT PRIMARY KEY
);

CREATE TABLE IF NOT EXISTS powers (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  class_name TEXT NOT NULL REFERENCES power_classes(name) ON UPDATE CASCADE,
  path TEXT,
  description TEXT,
  content TEXT,
  min_level INTEGER,
  prerequisites_json TEXT NOT NULL,
  tags_json TEXT NOT NULL
);

-- A power can have multiple "sub powers" / levels, sometimes multiple entries at same level.
CREATE TABLE IF NOT EXISTS power_levels (
  power_id TEXT NOT NULL REFERENCES powers(id) ON DELETE CASCADE,
  idx INTEGER NOT NULL,
  level INTEGER,
  cost INTEGER,
  text TEXT,
  PRIMARY KEY (power_id, idx)
);

CREATE TABLE IF NOT EXISTS items (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  from_power TEXT,
  class_name TEXT,
  description TEXT,
  effects TEXT,
  cost TEXT,
  prerequisites_json TEXT NOT NULL
);

-- RPG factions shown in lore.html
CREATE TABLE IF NOT EXISTS rpg_factions (
  slug TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  blurb TEXT,
  page TEXT
);

-- Wargame content (extracted from PDFs)
CREATE TABLE IF NOT EXISTS wargame_factions (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  starting_name TEXT,
  starting_amount INTEGER,
  overview_json TEXT NOT NULL,
  command_abilities_json TEXT NOT NULL,
  source_pages_json TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS wargame_units (
  id TEXT PRIMARY KEY,
  name TEXT NOT NULL,
  faction_id TEXT NOT NULL REFERENCES wargame_factions(id) ON DELETE CASCADE,
  starting_energy INTEGER,
  header_numbers_json TEXT NOT NULL,
  sections_json TEXT NOT NULL,
  raw TEXT,
  source_page INTEGER
);

CREATE INDEX IF NOT EXISTS idx_powers_class ON powers(class_name);
CREATE INDEX IF NOT EXISTS idx_wargame_units_faction ON wargame_units(faction_id);
