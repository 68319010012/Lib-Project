<?php
// Session cookie bootstrap. Must run before any output/session_start().
// No CORS needed: the PHP app serves both the pages and the API from the
// same origin (see public/assets/js/api.js), so the browser never sends
// cross-origin credentialed requests here.
function bootstrap_session(): void
{
    $appEnv = env('APP_ENV', 'dev');

    // secure requires HTTPS, which prod serves over; dev runs on plain http://localhost.
    $isProd = $appEnv === 'prod';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isProd,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Mirrors app.py's @login_required.
function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        json_error('กรุณาเข้าสู่ระบบ', 401);
    }
}

// Mirrors app.py's @admin_required.
function require_admin(): void
{
    if (($_SESSION['role'] ?? null) !== 'admin') {
        json_error('ต้องใช้สิทธิ์แอดมิน', 403);
    }
}
