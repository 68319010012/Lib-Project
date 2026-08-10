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
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- New: backs rate_limit.php's DB-backed replacement for Flask-Limiter.
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip VARCHAR(45) NOT NULL,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_ip_time (ip, attempted_at)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
