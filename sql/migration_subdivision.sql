-- ============================================================
-- Migration: ระบบแบ่งแปลงที่ดิน (Subdivision)
-- เพิ่มตาราง plot_allocations + parent_plot_id ใน land_plots
-- ============================================================

-- 1. ตาราง plot_allocations (บันทึกการจัดสรร)
CREATE TABLE IF NOT EXISTS plot_allocations (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    plot_id         INT NOT NULL,
    villager_id     INT NOT NULL,
    allocation_type ENUM('owner','heir','section19','community') NOT NULL DEFAULT 'owner',
    allocated_area_rai DECIMAL(10,4) DEFAULT 0,
    member_id       INT DEFAULT NULL,
    notes           TEXT,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plot_id) REFERENCES land_plots(plot_id) ON DELETE CASCADE,
    FOREIGN KEY (villager_id) REFERENCES villagers(villager_id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES household_members(member_id) ON DELETE SET NULL,
    INDEX idx_pa_villager (villager_id),
    INDEX idx_pa_plot (plot_id)
) ENGINE=InnoDB;

-- 2. เพิ่ม parent_plot_id ใน land_plots (ติดตามแปลงต้นทาง)
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'land_plots' AND COLUMN_NAME = 'parent_plot_id');
SET @sql = IF(@col_exists = 0, 
    'ALTER TABLE land_plots ADD COLUMN parent_plot_id INT DEFAULT NULL AFTER plot_id, ADD INDEX idx_parent_plot (parent_plot_id)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. เพิ่ม allocation_type ใน land_plots (ถ้ายังไม่มี)
SET @col_exists2 = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'land_plots' AND COLUMN_NAME = 'allocation_type');
SET @sql2 = IF(@col_exists2 = 0, 
    'ALTER TABLE land_plots ADD COLUMN allocation_type VARCHAR(20) DEFAULT NULL AFTER parent_plot_id',
    'SELECT 1');
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;
