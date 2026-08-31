-- เพิ่มคอลัมน์ users.must_change_password
--
-- ทำไมต้องมี: บัญชีที่ "คนอื่นออกรหัสให้" — สร้างเป็นชุดให้ทั้งห้อง หรือแอดมิน
-- กดปุ่มรีเซ็ตรหัสผ่านให้ — เริ่มต้นด้วยรหัสที่อยู่บนกระดาษและมีคนอื่นอ่านแล้ว
-- ก่อนหน้านี้ไม่มีอะไรผลักให้เจ้าตัวเปลี่ยน กระดาษใบนั้นจึงเป็นกุญแจที่ใช้ได้
-- ไปทั้งปี คอลัมน์นี้คือธงที่บอกว่า "ยังใช้รหัสของคนอื่นอยู่" และ
-- public/partials/guard.php จะกันไม่ให้ไปหน้าอื่นจนกว่าจะตั้งรหัสของตัวเอง
--
-- ---------------------------------------------------------------------------
-- SAFETY
--
-- ปลอดภัยกว่าไฟล์ 2026-08-17 มาก เพราะไม่ได้แก้ข้อมูลเดิมสักแถว มีแต่เพิ่ม
-- คอลัมน์ที่ DEFAULT 0 (= ไม่ถูกบังคับอะไร) บัญชีที่มีอยู่แล้วทุกบัญชีจึงทำงาน
-- เหมือนเดิมทุกประการหลังรันไฟล์นี้
--
--   1. กันรันซ้ำสองชั้น: เช็ค information_schema ก่อนว่ามีคอลัมน์อยู่หรือยัง
--      แล้วค่อยบันทึกชื่อ migration ลง schema_migrations รันกี่รอบก็ได้ผลเท่าเดิม
--
--   2. เขียนด้วย PREPARE แทน `ADD COLUMN IF NOT EXISTS` เพราะไวยากรณ์นั้นมีใน
--      MariaDB แต่ไม่มีใน MySQL 8 — ท่านี้ทำงานได้ทั้งสองแบบ
--
-- ---------------------------------------------------------------------------
-- HOW TO USE
--
--   1. สำรองฐานข้อมูลก่อน (hPanel -> Databases -> Backups หรือแท็บ Export)
--   2. deploy โค้ดก่อน แล้วค่อยรันไฟล์นี้
--
--      สลับลำดับกันก็ไม่พัง: โค้ดทุกจุดที่อ่านคอลัมน์นี้ทนกับการที่ยังไม่มีมัน
--      (handle_login() อ่านจากแถวที่ SELECT * มาอยู่แล้ว ส่วน UPDATE สองที่ดัก
--      SQLSTATE 42S22 แล้วถอยไปเขียนแค่ password_hash) ระหว่างนั้นฟีเจอร์แค่
--      ปิดอยู่ ไม่ใช่เว็บล่ม
--
--   3. รันทั้งไฟล์ในแท็บ SQL ของ phpMyAdmin
--   4. ตรวจผลด้วยคำสั่งท้ายไฟล์ ควรเห็นคอลัมน์ 1 คอลัมน์ และ migration 1 แถว
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS schema_migrations (
    name VARCHAR(191) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET @migration = '2026-08-31-add-must-change-password';

-- 0 = ยังไม่มีคอลัมน์ (ให้เพิ่ม), 1 = มีแล้ว (ไม่ต้องทำอะไร)
SET @have_column = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'must_change_password'
);

SET @ddl = IF(
    @have_column = 0,
    'ALTER TABLE users ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0',
    'DO 0'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO schema_migrations (name) VALUES (@migration);

-- ---------------------------------------------------------------------------
-- ตรวจผล
-- ---------------------------------------------------------------------------

-- ควรได้ 1 แถว: must_change_password / tinyint(1) / NO / 0
SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'users'
  AND COLUMN_NAME = 'must_change_password';

-- ควรมีชื่อ migration นี้อยู่หนึ่งแถว
SELECT name, applied_at FROM schema_migrations ORDER BY applied_at;

-- ทุกบัญชีที่มีอยู่ก่อนต้องเป็น 0 ทั้งหมด (ไม่มีใครถูกบังคับเปลี่ยนรหัสจากไฟล์นี้)
SELECT must_change_password, COUNT(*) AS จำนวนบัญชี FROM users GROUP BY must_change_password;
