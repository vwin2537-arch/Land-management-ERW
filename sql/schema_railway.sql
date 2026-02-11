-- ============================================================
-- Schema for Railway deployment (uses 'railway' database)
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- Railway auto-creates 'railway' database, no need to CREATE DATABASE

-- 1. users
CREATE TABLE IF NOT EXISTS users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    role          ENUM('admin','officer','viewer') NOT NULL DEFAULT 'officer',
    phone         VARCHAR(15),
    email         VARCHAR(100),
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    last_login    DATETIME,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. villagers
CREATE TABLE IF NOT EXISTS villagers (
    villager_id    INT AUTO_INCREMENT PRIMARY KEY,
    id_card_number VARCHAR(13) UNIQUE NOT NULL,
    prefix         VARCHAR(20),
    first_name     VARCHAR(100) NOT NULL,
    last_name      VARCHAR(100) NOT NULL,
    birth_date     DATE,
    phone          VARCHAR(15),
    address        TEXT,
    village_no     VARCHAR(10),
    village_name   VARCHAR(100),
    sub_district   VARCHAR(100),
    district       VARCHAR(100),
    province       VARCHAR(100),
    photo_path     VARCHAR(500),
    notes          TEXT,
    created_by     INT,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_id_card (id_card_number),
    INDEX idx_name (first_name, last_name),
    INDEX idx_village (village_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. land_plots
CREATE TABLE IF NOT EXISTS land_plots (
    plot_id          INT AUTO_INCREMENT PRIMARY KEY,
    plot_code        VARCHAR(20) UNIQUE NOT NULL,
    villager_id      INT NOT NULL,
    park_name        VARCHAR(100),
    zone             VARCHAR(50),
    area_rai         DECIMAL(10,2) DEFAULT 0,
    area_ngan        DECIMAL(10,2) DEFAULT 0,
    area_sqwa        DECIMAL(10,2) DEFAULT 0,
    land_use_type    ENUM('agriculture','residential','garden','livestock','mixed','other') DEFAULT 'agriculture',
    crop_type        VARCHAR(200),
    latitude         DECIMAL(10,7),
    longitude        DECIMAL(10,7),
    polygon_coords   JSON,
    occupation_since YEAR,
    has_document     TINYINT(1) DEFAULT 0,
    document_type    VARCHAR(50),
    status           ENUM('surveyed','pending_review','temporary_permit','must_relocate','disputed') DEFAULT 'pending_review',
    survey_date      DATE,
    surveyed_by      INT,
    plot_image_path  VARCHAR(500),
    notes            TEXT,
    created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (villager_id) REFERENCES villagers(villager_id) ON DELETE CASCADE,
    FOREIGN KEY (surveyed_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_plot_code (plot_code),
    INDEX idx_villager (villager_id),
    INDEX idx_status (status),
    INDEX idx_coords (latitude, longitude),
    INDEX idx_park (park_name),
    INDEX idx_zone (zone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. cases
CREATE TABLE IF NOT EXISTS cases (
    case_id       INT AUTO_INCREMENT PRIMARY KEY,
    case_number   VARCHAR(20) UNIQUE NOT NULL,
    plot_id       INT,
    villager_id   INT NOT NULL,
    case_type     ENUM('complaint','request_use','trespass_report','renewal','other') NOT NULL,
    subject       VARCHAR(200) NOT NULL,
    description   TEXT,
    priority      ENUM('high','medium','low') DEFAULT 'medium',
    status        ENUM('new','in_progress','awaiting_approval','closed','rejected') DEFAULT 'new',
    assigned_to   INT,
    resolution    TEXT,
    resolved_date DATE,
    created_by    INT,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (plot_id) REFERENCES land_plots(plot_id) ON DELETE SET NULL,
    FOREIGN KEY (villager_id) REFERENCES villagers(villager_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_case_number (case_number),
    INDEX idx_case_status (status),
    INDEX idx_case_type (case_type),
    INDEX idx_case_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. documents
CREATE TABLE IF NOT EXISTS documents (
    doc_id        INT AUTO_INCREMENT PRIMARY KEY,
    related_type  ENUM('villager','plot','case') NOT NULL,
    related_id    INT NOT NULL,
    file_name     VARCHAR(255) NOT NULL,
    file_path     VARCHAR(500) NOT NULL,
    file_type     VARCHAR(10),
    file_size     INT,
    doc_category  ENUM('id_copy','map','photo','permit','survey_form','boundary_image','other') NOT NULL,
    description   VARCHAR(200),
    uploaded_by   INT,
    uploaded_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_related (related_type, related_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. report_templates
CREATE TABLE IF NOT EXISTS report_templates (
    template_id   INT AUTO_INCREMENT PRIMARY KEY,
    template_name VARCHAR(100) NOT NULL,
    template_code VARCHAR(30) UNIQUE NOT NULL,
    description   TEXT,
    page_size     ENUM('A4','A3','legal','letter') DEFAULT 'A4',
    orientation   ENUM('portrait','landscape') DEFAULT 'portrait',
    is_active     TINYINT(1) DEFAULT 1,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. activity_logs
CREATE TABLE IF NOT EXISTS activity_logs (
    log_id      INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    action      ENUM('create','update','delete','export','login','logout') NOT NULL,
    table_name  VARCHAR(50),
    record_id   INT,
    description VARCHAR(255),
    old_value   JSON,
    new_value   JSON,
    ip_address  VARCHAR(45),
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- Seed Data
-- ============================================================

-- Admin User (password: admin123)
INSERT IGNORE INTO users (username, password_hash, full_name, role, is_active) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ผู้ดูแลระบบ', 'admin', 1);

-- Report Templates
INSERT IGNORE INTO report_templates (template_name, template_code, description, orientation) VALUES
('บัญชีรายชื่อราษฎร', 'RPT_VILLAGER_LIST', 'ทะเบียนรายชื่อราษฎรทั้งหมดพร้อมที่อยู่', 'landscape'),
('ทะเบียนแปลงที่ดินทำกิน', 'RPT_PLOT_REGISTRY', 'รายแปลง: รหัส เจ้าของ พื้นที่ สถานะ', 'landscape'),
('รายงานสำรวจรายแปลง', 'RPT_PLOT_SURVEY', 'ข้อมูลแปลงเดี่ยว + รูปแปลง + แผนที่', 'portrait'),
('สรุปพื้นที่ตามโซน', 'RPT_ZONE_SUMMARY', 'จำนวนแปลง + พื้นที่รวม แยกตามโซน', 'portrait'),
('สรุปพื้นที่ตามประเภทการใช้', 'RPT_LANDUSE_SUMMARY', 'เกษตร อยู่อาศัย สวน เลี้ยงสัตว์', 'portrait'),
('รายงานสถานะคำร้อง', 'RPT_CASE_STATUS', 'สรุปรายเดือน/ปี แยกตามสถานะ', 'landscape'),
('รายงานคำร้องรายเรื่อง', 'RPT_CASE_DETAIL', 'รายละเอียดคำร้อง + ผลดำเนินการ', 'portrait'),
('รายงานสรุปผู้บริหาร', 'RPT_EXECUTIVE', 'ภาพรวม: ราษฎร แปลง คำร้อง พื้นที่', 'portrait'),
('บัญชีเอกสารสิทธิ์', 'RPT_DOCUMENT_LIST', 'ราษฎรที่มี/ไม่มีเอกสาร แยกประเภท', 'landscape'),
('รายงานกิจกรรมประจำเดือน', 'RPT_ACTIVITY_LOG', 'Log การทำงานของเจ้าหน้าที่', 'landscape');
