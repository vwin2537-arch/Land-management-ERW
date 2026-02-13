-- ============================================================
-- Migration: เพิ่มตาราง + ฟิลด์สำหรับระบบแบบฟอร์มราชการ
-- Run this on existing database
-- ============================================================

SET NAMES utf8mb4;
USE land_management;

-- 1. สร้างตาราง household_members (สมาชิกครอบครัว/ครัวเรือน - อส.6-2)
CREATE TABLE IF NOT EXISTS household_members (
    member_id       INT AUTO_INCREMENT PRIMARY KEY,
    villager_id     INT NOT NULL,
    prefix          VARCHAR(20),
    first_name      VARCHAR(100) NOT NULL,
    last_name       VARCHAR(100) NOT NULL,
    id_card_number  VARCHAR(13),
    relationship    VARCHAR(50),
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (villager_id) REFERENCES villagers(villager_id) ON DELETE CASCADE,
    INDEX idx_villager (villager_id)
) ENGINE=InnoDB;

-- 2. เพิ่มฟิลด์คุณสมบัติ (qualification) ใน villagers
-- ใช้ IF NOT EXISTS pattern เพื่อรันซ้ำได้อย่างปลอดภัย
SET @dbname = 'land_management';
SET @tablename = 'villagers';

-- Check and add columns one by one (safe for re-run)
SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qualification_status';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qualification_status ENUM(''pending'',''passed'',''failed'') DEFAULT ''pending'' AFTER notes', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_thai_nationality';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_thai_nationality TINYINT(1) DEFAULT 1 AFTER qualification_status', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_continuous_residence';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_continuous_residence TINYINT(1) DEFAULT 1 AFTER qual_thai_nationality', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_no_other_land';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_no_other_land TINYINT(1) DEFAULT 1 AFTER qual_continuous_residence', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_no_court_order';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_no_court_order TINYINT(1) DEFAULT 1 AFTER qual_no_other_land', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_no_forest_crime';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_no_forest_crime TINYINT(1) DEFAULT 1 AFTER qual_no_court_order', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qual_no_revoked_rights';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qual_no_revoked_rights TINYINT(1) DEFAULT 1 AFTER qual_no_forest_crime', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qualification_date';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qualification_date DATE AFTER qual_no_revoked_rights', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='qualification_notes';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE villagers ADD COLUMN qualification_notes TEXT AFTER qualification_date', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. เพิ่มฟิลด์ remark_risk / watershed_class ใน land_plots
SET @tablename = 'land_plots';

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='remark_risk';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE land_plots ADD COLUMN remark_risk ENUM(''not_risky'',''risky'',''risky_case'',''not_risky_case'') DEFAULT ''not_risky'' AFTER notes', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @col_exists FROM information_schema.columns 
WHERE table_schema=@dbname AND table_name=@tablename AND column_name='watershed_class';
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE land_plots ADD COLUMN watershed_class VARCHAR(20) AFTER remark_risk', 
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Done!
SELECT 'Migration completed successfully!' as result;
