-- Library Check-in System — Database Schema
-- ตาม PROJECT_CONTEXT.md (Database Schema section)

CREATE TABLE students (
    student_id VARCHAR(20) PRIMARY KEY,
    prefix VARCHAR(20) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    department VARCHAR(100),
    level VARCHAR(10),
    year_level VARCHAR(20),
    room VARCHAR(10),
    semester VARCHAR(10),
    academic_year VARCHAR(10)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('student', 'admin') NOT NULL,
    student_id VARCHAR(20),
    id_card_photo_path VARCHAR(255),
    account_status ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
    FOREIGN KEY (student_id) REFERENCES students(student_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE checkin_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    type ENUM('in', 'out') NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
