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
