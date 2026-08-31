<?php
// Server-side auth guard, included at the very top of every protected page
// (dashboard.php, profile.php, admin-*.php) before any HTML output. Port of
// frontend-react/src/components/ProtectedRoute.jsx, but done server-side —
// no client fetch-then-redirect flash, and it reuses the exact same session
// mechanism the JSON API already relies on (bootstrap_session() in
// backend-php/src/auth.php).
//
// Admin-only pages must set $requireAdmin = true; before including this file.

require __DIR__ . '/../../src/env.php';
load_env(__DIR__ . '/../../.env');
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Bangkok'));
// bootstrap_session() reads SESSION_LIFETIME_SECONDS from constants.php, so the
// pages that come in through this guard need it loaded too — index.php requires
// it for the JSON API, but that front controller is not in this path.
require __DIR__ . '/../../src/constants.php';
require __DIR__ . '/../../src/helpers.php';
require __DIR__ . '/../../src/auth.php';

bootstrap_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
if (!empty($requireAdmin) && ($_SESSION['role'] ?? null) !== 'admin') {
    header('Location: /dashboard');
    exit;
}

// บัญชีที่เจ้าหน้าที่สร้างให้ หรือเพิ่งถูกรีเซ็ตรหัส ยังใช้รหัสที่คนอื่นรู้อยู่
// (อยู่บนใบแจก หรืออยู่ในมือเจ้าหน้าที่ที่กดรีเซ็ต) จนกว่าเจ้าตัวจะตั้งรหัสของ
// ตัวเอง จึงกันไว้ไม่ให้ไปหน้าอื่นก่อน — ปล่อยผ่านเฉพาะหน้าเปลี่ยนรหัสผ่านเอง
// ไม่งั้นจะวนกลับมาที่ตัวเองไม่รู้จบ
//
// $allowWithoutPasswordChange ให้หน้าไหนก็ตามประกาศยกเว้นตัวเองได้ ตอนนี้มีแค่
// change-password.php ที่ใช้
if (!empty($_SESSION['must_change_password']) && empty($allowWithoutPasswordChange)) {
    header('Location: /change-password?first=1');
    exit;
}
