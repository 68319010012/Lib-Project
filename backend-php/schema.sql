-- Library Check-in System — Database Schema (PHP backend)
-- Identical to BackEnd/schema.sql, plus login_attempts for the PHP rate limiter
-- (Python used Flask-Limiter's in-memory storage instead of a table).

CREATE TABLE IF NOT EXISTS students (
    student_id VARCHAR(20) PRIMARY KEY,
    prefix VARCHAR(20) NOT NULL,
    -- Optional (nullable) — self-reported at signup; not collected by the
    -- roster import script, so imported students start out NULL ("ไม่ระบุ").
    gender ENUM('male', 'female') NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    level VARCHAR(10),
    year_level VARCHAR(20),
    room VARCHAR(10),
    semester VARCHAR(10),
    academic_year VARCHAR(10)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL,
    student_id VARCHAR(20),
    id_card_photo_path VARCHAR(255),
    -- 'retired': student_handlers.php's retire_expired_accounts_sweep() auto-suspends
    -- accounts a full academic year (365 days) after created_at.
    account_status ENUM('pending', 'approved', 'retired') NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS checkin_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type ENUM('in', 'out') NOT NULL,
    -- Set on check-in only (see compute_planned_checkout() in helpers.php);
    -- NULL means "no planned time" (checked in until closing).
    planned_checkout_at DATETIME NULL,
    -- Set on check-out only, describing how the check-out row was created.
    checkout_source ENUM('manual', 'auto', 'admin_forced') NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    -- Every report filters on a timestamp range, and the FK only indexes
    -- user_id, so those queries were full table scans. The composite serves
    -- the per-user history lookups (user_id + newest-first) that the
    -- dashboard and auto-checkout sweep do.
    INDEX idx_checkin_logs_timestamp (timestamp),
    INDEX idx_checkin_logs_user_time (user_id, timestamp)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ค่าตั้งค่าทั่วไปของระบบแบบ key/value ตอนนี้ใช้เก็บประกาศจากเจ้าหน้าที่ที่
-- แสดงบนหน้าหลักของนักศึกษา (คีย์ announcement_text, announcement_enabled)
--
-- src/handlers/announcement_handlers.php สร้างตารางนี้เองด้วย
-- CREATE TABLE IF NOT EXISTS ตอนใช้งานครั้งแรก เพราะการ deploy อัปแต่ไฟล์
-- ไม่ได้รัน .sql ให้ — ประกาศไว้ตรงนี้ด้วยเพื่อให้ schema ยังเป็นภาพรวมที่ครบ
CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(50) PRIMARY KEY,
    setting_value TEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- ชื่อผู้ใช้ของแอดมินที่แก้ล่าสุด เก็บเป็นข้อความ ไม่ใช่ FK ไป users:
    -- ประกาศต้องอยู่ต่อได้แม้บัญชีที่เขียนมันถูกลบไปแล้ว
    updated_by VARCHAR(50) NULL
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- New: backs rate_limit.php's DB-backed replacement for Flask-Limiter.
-- Only FAILED logins land here; see src/rate_limit.php for why successes are
-- not counted (the whole college shares one public IP).
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    -- Which account was being guessed, so the limit can be per-account
    -- instead of locking out everyone behind the same IP.
    username VARCHAR(50) NOT NULL DEFAULT '',
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip, attempted_at),
    INDEX idx_login_attempts_ip_user_time (ip, username, attempted_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
