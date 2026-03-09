-- Non-destructive migration for existing live databases.
-- Adds Era fields for timeline styling in gm_lore.
-- Safe to run multiple times on MySQL 8+.

SET @db_name := DATABASE();

SET @has_is_era := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'gm_lore'
    AND COLUMN_NAME = 'is_era'
);
SET @sql := IF(
  @has_is_era = 0,
  'ALTER TABLE gm_lore ADD COLUMN is_era TINYINT(1) NOT NULL DEFAULT 0 AFTER content',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_era_bg := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'gm_lore'
    AND COLUMN_NAME = 'era_bg_color'
);
SET @sql := IF(
  @has_era_bg = 0,
  'ALTER TABLE gm_lore ADD COLUMN era_bg_color VARCHAR(7) NULL AFTER is_era',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_era_text := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'gm_lore'
    AND COLUMN_NAME = 'era_text_color'
);
SET @sql := IF(
  @has_era_text = 0,
  'ALTER TABLE gm_lore ADD COLUMN era_text_color VARCHAR(7) NULL AFTER era_bg_color',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

