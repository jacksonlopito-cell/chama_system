-- ============================================================
-- Migration 004: Complete Kenya Administrative Hierarchy
-- Source: IEBC (Independent Electoral and Boundaries Commission)
-- 47 Counties · 290 Constituencies (sub_counties) · 1,450 Wards
-- Idempotent: safe to run multiple times
-- ============================================================

-- Disable FK checks for clean replacement
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. Clear old data (CASCADE handles wards)
-- ============================================================
DELETE FROM sub_counties;
ALTER TABLE sub_counties AUTO_INCREMENT = 1;
ALTER TABLE wards AUTO_INCREMENT = 1;

-- ============================================================
-- 2. Add UNIQUE constraints (idempotent)
-- ============================================================
-- Prevent duplicate sub-county names within a county
SET @dbname = 'chama_system';
SET @exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
               WHERE CONSTRAINT_SCHEMA = @dbname 
               AND TABLE_NAME = 'sub_counties' 
               AND CONSTRAINT_NAME = 'uq_sub_county_county');
SET @sql = IF(@exists = 0, 
    'ALTER TABLE sub_counties ADD CONSTRAINT uq_sub_county_county UNIQUE (county_id, name)',
    'SELECT "uq_sub_county_county already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Prevent duplicate ward names within a sub-county
SET @exists2 = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                WHERE CONSTRAINT_SCHEMA = @dbname 
                AND TABLE_NAME = 'wards' 
                AND CONSTRAINT_NAME = 'uq_ward_sub_county');
SET @sql2 = IF(@exists2 = 0, 
    'ALTER TABLE wards ADD CONSTRAINT uq_ward_sub_county UNIQUE (sub_county_id, name)',
    'SELECT "uq_ward_sub_county already exists"');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
