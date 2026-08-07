<?php
// Front controller. All 14 endpoints are static paths (no dynamic segments —
// confirmed against API_CONTRACT.md/app.py), so a flat "METHOD path" => handler
// map is enough; no regex router needed.

require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');

require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/rate_limit.php';
require __DIR__ . '/../src/handlers/auth_handlers.php';
require __DIR__ . '/../src/handlers/checkin_handlers.php';
require __DIR__ . '/../src/handlers/profile_handlers.php';
require __DIR__ . '/../src/handlers/admin_handlers.php';
require __DIR__ . '/../src/reports/layout.php';
require __DIR__ . '/../src/reports/select.php';
require __DIR__ . '/../src/reports/daily.php';
require __DIR__ . '/../src/reports/monthly.php';
require __DIR__ . '/../src/reports/department.php';
require __DIR__ . '/../src/reports/dashboard.php';

bootstrap_cors_and_session();

$routes = [
    'POST /register' => 'handle_register',
    'POST /login' => 'handle_login',
    'POST /logout' => 'handle_logout',
    'POST /checkin' => 'handle_checkin',
    'GET /me' => 'handle_me',
    'POST /profile/change-password' => 'handle_change_password',
    'GET /me/history' => 'handle_my_history',
    'GET /admin/members' => 'handle_admin_members',
    'GET /admin/reports' => 'handle_admin_reports',
    'GET /admin/reports/print' => 'handle_report_select',
    'GET /admin/reports/print/daily' => 'handle_report_daily',
    'GET /admin/reports/print/monthly' => 'handle_report_monthly',
    'GET /admin/reports/print/department' => 'handle_report_department',
    'GET /admin/reports/print/dashboard' => 'handle_report_dashboard',
];

$method = $_SERVER['REQUEST_METHOD'];
$path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
if ($path === '') {
    $path = '/';
}
$key = "$method $path";

if (!isset($routes[$key])) {
    json_error('not found', 404);
}

$routes[$key]();
