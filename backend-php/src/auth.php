<?php
// Session cookie bootstrap + CORS. Must run before any output/session_start().
function bootstrap_cors_and_session(): void
{
    $appEnv = env('APP_ENV', 'dev');
    $frontendOrigin = env('FRONTEND_ORIGIN', 'http://localhost:5173');

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && $origin === $frontendOrigin) {
        header("Access-Control-Allow-Origin: $origin");
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        exit;
    }

    // prod: real cross-origin React static site needs SameSite=None + Secure.
    // dev: React dev server is proxied same-origin via Vite (see frontend-react/vite.config.js),
    // so Lax + non-secure works over plain http://localhost.
    $isProd = $appEnv === 'prod';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isProd,
        'httponly' => true,
        'samesite' => $isProd ? 'None' : 'Lax',
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
