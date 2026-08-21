<?php
// Front controller. Every endpoint is a static path (no dynamic segments),
// so a flat "METHOD path" => handler map is enough; no regex router needed.
// (Originally every route traced back to API_CONTRACT.md/app.py 1:1; the
// report-system redesign added a few PHP-only report routes beyond that.)

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');

// PHP's default timezone (Europe/Berlin on this XAMPP install) otherwise
// disagrees with MySQL's NOW()/CURRENT_TIMESTAMP, which follow the system
// clock (Asia/Bangkok) — a multi-hour gap that makes any PHP-computed
// DateTime compared against a DB timestamp (e.g. planned_checkout_at vs
// NOW() in auto_checkout_sweep()) wrong. Must run before any DateTime use.
date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Bangkok'));

require __DIR__ . '/../src/constants.php';
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/db.php';
require __DIR__ . '/../src/auth.php';
require __DIR__ . '/../src/rate_limit.php';
require __DIR__ . '/../src/handlers/auth_handlers.php';
require __DIR__ . '/../src/handlers/checkin_handlers.php';
require __DIR__ . '/../src/handlers/profile_handlers.php';
require __DIR__ . '/../src/handlers/admin_handlers.php';
require __DIR__ . '/../src/reports/layout.php';
require __DIR__ . '/../src/reports/export.php';
require __DIR__ . '/../src/reports/select.php';
require __DIR__ . '/../src/reports/daily.php';
require __DIR__ . '/../src/reports/monthly.php';
require __DIR__ . '/../src/reports/department.php';
require __DIR__ . '/../src/reports/dashboard.php';
require __DIR__ . '/../src/reports/aggregate.php';
require __DIR__ . '/../src/reports/executive.php';
require __DIR__ . '/../src/reports/compare.php';
require __DIR__ . '/../src/reports/semester.php';
require __DIR__ . '/../src/reports/student_lookup.php';

bootstrap_session();

// Replaces app.py's APScheduler background jobs — see auto_checkout_sweep()/
// retire_expired_accounts_sweep() for why running these inline per-request
// (instead of a real cron) is fine at this scale.
auto_checkout_sweep();
retire_expired_accounts_sweep();

$routes = [
    'GET /' => 'handle_root_redirect',
    'POST /register' => 'handle_register',
    'POST /login' => 'handle_login',
    'POST /logout' => 'handle_logout',
    'POST /checkin' => 'handle_checkin',
    'POST /checkin/extend' => 'handle_checkin_extend',
    'GET /library-info' => 'handle_library_info',
    'GET /me' => 'handle_me',
    'POST /profile/change-password' => 'handle_change_password',
    'GET /me/history' => 'handle_my_history',
    'GET /me/visits' => 'handle_my_visits',
    'GET /admin/members' => 'handle_admin_members',
    'GET /admin/active-now' => 'handle_admin_active_now',
    'POST /admin/checkin/force-checkout' => 'handle_admin_force_checkout',
    'POST /admin/members/reset-password' => 'handle_admin_reset_password',
    'POST /admin/members/update' => 'handle_admin_member_update',
    'POST /admin/members/role' => 'handle_admin_member_role',
    'POST /admin/members/delete' => 'handle_admin_member_delete',
    'GET /admin/reports' => 'handle_admin_reports',
    'GET /admin/reports/print' => 'handle_report_select',
    'GET /admin/reports/print/daily' => 'handle_report_daily',
    'GET /admin/reports/print/monthly' => 'handle_report_monthly',
    'GET /admin/reports/print/department' => 'handle_report_department',
    'GET /admin/reports/print/dashboard' => 'handle_report_dashboard',
    'GET /admin/reports/print/executive' => 'handle_report_executive',
    'GET /admin/reports/print/compare' => 'handle_report_compare',
    'GET /admin/reports/print/semester' => 'handle_report_semester',
    'GET /admin/reports/print/student' => 'handle_report_student_lookup',
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
