<?php
// ประกาศจากเจ้าหน้าที่ที่แสดงบนหน้าหลักของนักศึกษา
//
// เดิมข้อความนี้ถูกเขียนตายไว้ใน public/dashboard.php ("ห้องสมุดจะปิดทำการใน
// สุดสัปดาห์นี้เพื่อตรวจนับครุภัณฑ์") เจ้าหน้าที่จึงไม่มีทางแก้ได้เลยถ้าไม่
// แก้โค้ดแล้ว deploy ใหม่ ย้ายมาเก็บในฐานข้อมูลและให้แก้จากหน้าแอดมินแทน
//
// เก็บเป็นประกาศเดียวที่แก้ทับได้ ไม่ใช่รายการหลายประกาศ — นักศึกษาเห็นแค่
// อันล่าสุดบนหน้าหลักอยู่แล้ว การเก็บประวัติทุกฉบับจึงไม่ได้ถูกใช้

const ANNOUNCEMENT_MAX_LENGTH = 500;

// โปรเจคนี้ไม่มีตัวรัน migration — ไฟล์ถูกอัปขึ้นเซิร์ฟเวอร์ผ่าน FTP อย่างเดียว
// จึงสร้างตารางแบบ IF NOT EXISTS ตอนใช้งานครั้งแรกของแต่ละ request แทน
// (แนวทางเดียวกับ auto_checkout_sweep() ที่ทำงานต่อ request แทน cron)
function ensure_settings_table(PDO $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $conn->exec(
        'CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(50) PRIMARY KEY,
            setting_value TEXT NOT NULL,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by VARCHAR(50) NULL
        ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );
    $done = true;
}

// อ่านประกาศปัจจุบัน คืนค่าเริ่มต้น (ว่าง/ปิด) เมื่อยังไม่เคยตั้ง
//
// อ่านก่อน แล้วค่อยสร้างตารางถ้ามันฟ้องว่าไม่มี แทนที่จะยิง CREATE TABLE นำหน้า
// ทุก request: ตารางถูกสร้างไปแล้วตั้งแต่ครั้งแรกสุด การยิง DDL ซ้ำทุกครั้งที่มี
// คนเปิดหน้าหลักจึงเป็นภาระเปล่าและเป็นอีกจุดที่พังได้
//
// error อื่นที่ไม่ใช่ "ไม่มีตาราง" ถูกโยนต่อโดยตั้งใจ ของเดิมกลืนทุกอย่างแล้ว
// คืนค่าว่าง ทำให้ "ต่อฐานข้อมูลไม่ได้" หน้าตาเหมือน "แอดมินยังไม่ได้ตั้งประกาศ"
// เป๊ะ ๆ ประกาศจึงหายเงียบโดยไม่มีใครรู้ว่าระบบมีปัญหา — handle_announcement()
// เป็นคนตัดสินใจว่าจะบอกผู้ใช้อย่างไร
function get_announcement(PDO $conn): array
{
    $blank = ['text' => '', 'enabled' => false, 'updated_at' => null, 'updated_by' => null];
    $sql = "SELECT setting_key, setting_value, updated_at, updated_by
            FROM app_settings
            WHERE setting_key IN ('announcement_text', 'announcement_enabled')";
    try {
        $rows = $conn->query($sql)->fetchAll();
    } catch (PDOException $e) {
        // 42S02 = base table not found. เพิ่งติดตั้งใหม่ ยังไม่มีตาราง
        if ($e->getCode() !== '42S02') {
            throw $e;
        }
        try {
            ensure_settings_table($conn);
        } catch (PDOException $createFailed) {
            // บัญชีฐานข้อมูลไม่มีสิทธิ์ CREATE TABLE — ยังไม่มีประกาศให้แสดงจริง ๆ
            // ไม่ใช่ระบบล่ม จึงคืนค่าว่างแทนที่จะทำให้ทั้งหน้าพัง
            return $blank;
        }
        return $blank;
    }

    $result = $blank;
    foreach ($rows as $row) {
        if ($row['setting_key'] === 'announcement_text') {
            $result['text'] = $row['setting_value'];
            $result['updated_at'] = to_isoformat($row['updated_at']);
            $result['updated_by'] = $row['updated_by'];
        } elseif ($row['setting_key'] === 'announcement_enabled') {
            $result['enabled'] = $row['setting_value'] === '1';
        }
    }
    return $result;
}

// GET /announcement — หน้าหลักนักศึกษาเรียกใช้
//
// ต้องล็อกอินก่อน: ประกาศเป็นเรื่องภายในของห้องสมุด ไม่ใช่ข้อมูลสาธารณะ
function handle_announcement(): void
{
    require_login();
    try {
        json_response(get_announcement(get_db_connection()));
    } catch (PDOException $e) {
        // 500 ไม่ใช่ 200-พร้อมประกาศเปล่า: หน้าเว็บแยกออกว่านี่คือความล้มเหลว
        // ชั่วคราวที่ควรลองใหม่ ไม่ใช่ "ไม่มีประกาศ" (ดู loadAnnouncement()
        // ใน assets/js/dashboard.js)
        json_error('โหลดประกาศไม่สำเร็จ', 500);
    }
}

// POST /admin/announcement — บันทึกประกาศจากหน้าแอดมิน
function handle_admin_announcement_save(): void
{
    require_login();
    require_admin();

    $body = request_body();
    $text = trim(body_str($body, 'text'));
    // ไม่รับ null/ไม่ส่งมา = ปิด เพื่อให้ checkbox ที่ไม่ถูกติ๊กมีความหมายชัดเจน
    $enabled = !empty($body['enabled']);

    if (mb_strlen($text) > ANNOUNCEMENT_MAX_LENGTH) {
        json_error('ประกาศยาวเกินไป (ไม่เกิน ' . ANNOUNCEMENT_MAX_LENGTH . ' ตัวอักษร)', 400);
    }
    // เปิดแสดงประกาศเปล่าจะได้กล่องว่างๆ บนหน้านักศึกษา — ไม่มีประโยชน์
    if ($enabled && $text === '') {
        json_error('กรุณาพิมพ์ข้อความประกาศก่อนเปิดแสดง', 400);
    }
    // กันไม่ให้ค่าที่บันทึกไปโผล่เป็น HTML ถ้าวันหลังมีที่ไหนเรนเดอร์แบบไม่ escape
    // (ฝั่งหน้าเว็บใช้ textContent อยู่แล้ว นี่เป็นด่านที่สอง)
    if (preg_match('/[<>]/', $text)) {
        json_error('ประกาศมีอักขระที่ไม่อนุญาต (< หรือ >)', 400);
    }

    $conn = get_db_connection();
    try {
        ensure_settings_table($conn);
        $sql = 'INSERT INTO app_settings (setting_key, setting_value, updated_at, updated_by)
                VALUES (?, ?, NOW(), ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value),
                                        updated_at = VALUES(updated_at),
                                        updated_by = VALUES(updated_by)';
        $username = (string) ($_SESSION['username'] ?? '');
        $stmt = $conn->prepare($sql);
        $stmt->execute(['announcement_text', $text, $username]);
        $stmt->execute(['announcement_enabled', $enabled ? '1' : '0', $username]);
        // อ่านกลับอยู่ใน try เดียวกัน: get_announcement() โยน PDOException ได้แล้ว
        // ปล่อยไว้ข้างนอกจะกลายเป็น error ที่ไม่มีใครดัก ทั้งที่การบันทึกสำเร็จไปแล้ว
        $saved = get_announcement($conn);
    } catch (PDOException $e) {
        json_error('บันทึกประกาศไม่สำเร็จ กรุณาลองใหม่', 500);
    }

    json_response(['message' => 'บันทึกประกาศแล้ว'] + $saved);
}
