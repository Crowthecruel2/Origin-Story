-- Non-destructive migration for existing live databases.
-- Ensures rules_sections supports hierarchical section codes and richer HTML content.
-- Safe to run multiple times on MySQL 8+.

SET @db_name := DATABASE();

SET @has_section_code := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'rules_sections'
    AND COLUMN_NAME = 'section_code'
);
SET @sql := IF(
  @has_section_code = 0,
  'ALTER TABLE rules_sections ADD COLUMN section_code VARCHAR(32) NOT NULL DEFAULT ''1'' AFTER id',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_section_number := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'rules_sections'
    AND COLUMN_NAME = 'section_number'
);
SET @sql := IF(
  @has_section_number = 0,
  'ALTER TABLE rules_sections ADD COLUMN section_number INT NOT NULL DEFAULT 1 AFTER section_code',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_rules_html := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'rules_sections'
    AND COLUMN_NAME = 'rules_html'
);
SET @sql := IF(
  @has_rules_html = 1,
  'ALTER TABLE rules_sections MODIFY COLUMN rules_html MEDIUMTEXT NOT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_sort_index := (
  SELECT COUNT(*)
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = @db_name
    AND TABLE_NAME = 'rules_sections'
    AND INDEX_NAME = 'idx_rules_sections_sort'
);
SET @sql := IF(
  @has_sort_index = 0,
  'CREATE INDEX idx_rules_sections_sort ON rules_sections(section_code, section_number, title)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

