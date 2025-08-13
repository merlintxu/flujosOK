-- Migration: 005_add_call_foreign_keys.sql
-- Description: ensure FK constraints towards calls
-- Date: 2025-08-13

SET @db_name := DATABASE();

-- call_recordings FK
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='call_recordings'
              AND COLUMN_NAME='call_id' AND REFERENCED_TABLE_NAME='calls');
SET @sql := IF(@fk IS NULL,
    'ALTER TABLE call_recordings ADD CONSTRAINT call_recordings_call_fk FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE;',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- transcriptions FK
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='transcriptions'
              AND COLUMN_NAME='call_id' AND REFERENCED_TABLE_NAME='calls');
SET @sql := IF(@fk IS NULL,
    'ALTER TABLE transcriptions ADD CONSTRAINT transcriptions_call_fk FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE;',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- call_analysis FK
SET @fk := (SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=@db_name AND TABLE_NAME='call_analysis'
              AND COLUMN_NAME='call_id' AND REFERENCED_TABLE_NAME='calls');
SET @sql := IF(@fk IS NULL,
    'ALTER TABLE call_analysis ADD CONSTRAINT call_analysis_call_fk FOREIGN KEY (call_id) REFERENCES calls(id) ON DELETE CASCADE;',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
