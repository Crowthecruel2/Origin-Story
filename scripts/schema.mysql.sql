-- MySQL 8+ schema for Brighton datasets
-- Character set/collation chosen for broad compatibility on shared hosts.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- Drop order matters due to FKs.
DROP TABLE IF EXISTS power_levels;
DROP TABLE IF EXISTS powers;
DROP TABLE IF EXISTS power_classes;
DROP TABLE IF EXISTS items;
DROP TABLE IF EXISTS rpg_factions;
DROP TABLE IF EXISTS wargame_units;
DROP TABLE IF EXISTS wargame_factions;
DROP TABLE IF EXISTS meta;
DROP TABLE IF EXISTS admin_users;

CREATE TABLE admin_users (
  id INT NOT NULL AUTO_INCREMENT,
  username VARCHAR(191) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE meta (
  `key` VARCHAR(191) NOT NULL,
  `value` TEXT NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE power_classes (
  name VARCHAR(191) NOT NULL,
  PRIMARY KEY (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE powers (
  id VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  class_name VARCHAR(191) NOT NULL,
  path TEXT NULL,
  description TEXT NULL,
  content MEDIUMTEXT NULL,
  min_level INT NULL,
  prerequisites_json JSON NOT NULL,
  tags_json JSON NOT NULL,
  PRIMARY KEY (id),
  KEY idx_powers_class (class_name),
  CONSTRAINT fk_powers_class FOREIGN KEY (class_name) REFERENCES power_classes(name)
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE power_levels (
  power_id VARCHAR(191) NOT NULL,
  `idx` INT NOT NULL,
  `level` INT NULL,
  cost INT NULL,
  `text` MEDIUMTEXT NULL,
  PRIMARY KEY (power_id, `idx`),
  CONSTRAINT fk_power_levels_power FOREIGN KEY (power_id) REFERENCES powers(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
  id VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  from_power VARCHAR(255) NULL,
  class_name VARCHAR(191) NULL,
  description TEXT NULL,
  effects MEDIUMTEXT NULL,
  cost VARCHAR(255) NULL,
  prerequisites_json JSON NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rpg_factions (
  slug VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  blurb MEDIUMTEXT NULL,
  page TEXT NULL,
  PRIMARY KEY (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wargame_factions (
  id VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  starting_name VARCHAR(191) NULL,
  starting_amount INT NULL,
  overview_json JSON NOT NULL,
  command_abilities_json JSON NOT NULL,
  source_pages_json JSON NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE wargame_units (
  id VARCHAR(191) NOT NULL,
  name VARCHAR(255) NOT NULL,
  faction_id VARCHAR(191) NOT NULL,
  starting_energy INT NULL,
  header_numbers_json JSON NOT NULL,
  sections_json JSON NOT NULL,
  raw MEDIUMTEXT NULL,
  source_page INT NULL,
  PRIMARY KEY (id),
  KEY idx_wargame_units_faction (faction_id),
  CONSTRAINT fk_wargame_units_faction FOREIGN KEY (faction_id) REFERENCES wargame_factions(id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
