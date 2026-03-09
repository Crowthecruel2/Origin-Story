-- Non-destructive migration for existing live databases.
-- Adds gm_lore.event_date and updates timeline index.
-- Safe to run multiple times on MySQL 8+.

SET @db_name := DATABASE();

-- Add column only if missing
SET @has_event_date := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'gm_lore'
    AND COLUMN_NAME = 'event_date'
);
SET @sql := IF(
  @has_event_date = 0,
  'ALTER TABLE gm_lore ADD COLUMN event_date DATE NULL AFTER title',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Rebuild index to match new timeline ordering
SET @has_old_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'gm_lore'
    AND INDEX_NAME = 'idx_gm_lore_sort'
);
SET @sql := IF(@has_old_index > 0, 'DROP INDEX idx_gm_lore_sort ON gm_lore', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE INDEX idx_gm_lore_sort ON gm_lore(event_date, sort_order, title);
