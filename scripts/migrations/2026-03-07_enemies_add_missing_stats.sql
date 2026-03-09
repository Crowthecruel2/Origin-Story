-- Add missing player-aligned enemy stats used by GM tools and character comparisons.
-- Compatible with MySQL versions that do not support ADD COLUMN IF NOT EXISTS.

SET @db_name := DATABASE();

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'enemies'
        AND COLUMN_NAME = 'smarts'
    ),
    'SELECT 1',
    'ALTER TABLE enemies ADD COLUMN smarts INT NULL AFTER strength'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'enemies'
        AND COLUMN_NAME = 'social'
    ),
    'SELECT 1',
    'ALTER TABLE enemies ADD COLUMN social INT NULL AFTER smarts'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := (
  SELECT IF(
    EXISTS(
      SELECT 1
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = @db_name
        AND TABLE_NAME = 'enemies'
        AND COLUMN_NAME = 'durability'
    ),
    'SELECT 1',
    'ALTER TABLE enemies ADD COLUMN durability INT NULL AFTER social'
  )
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
