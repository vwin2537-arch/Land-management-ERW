-- เพิ่มคอลัมน์สำหรับระบบตรวจสอบสิทธิ์ในตาราง villagers
SET @col1 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'villagers' AND COLUMN_NAME = 'verification_status');
SET @sql1 = IF(@col1 = 0, 
    "ALTER TABLE villagers ADD COLUMN verification_status ENUM('pending','verified') DEFAULT 'pending'",
    'SELECT 1');
PREPARE s1 FROM @sql1; EXECUTE s1; DEALLOCATE PREPARE s1;

SET @col2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'villagers' AND COLUMN_NAME = 'verified_at');
SET @sql2 = IF(@col2 = 0, 
    'ALTER TABLE villagers ADD COLUMN verified_at DATETIME DEFAULT NULL',
    'SELECT 1');
PREPARE s2 FROM @sql2; EXECUTE s2; DEALLOCATE PREPARE s2;

SET @col3 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'villagers' AND COLUMN_NAME = 'verified_by');
SET @sql3 = IF(@col3 = 0, 
    'ALTER TABLE villagers ADD COLUMN verified_by INT DEFAULT NULL',
    'SELECT 1');
PREPARE s3 FROM @sql3; EXECUTE s3; DEALLOCATE PREPARE s3;
