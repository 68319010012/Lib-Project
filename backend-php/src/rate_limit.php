<?php
// Login throttling, DB-backed (not APCu/memory) so the counter is shared
// across every PHP worker process and works on shared hosting.
//
// Only FAILED logins are recorded, and the per-account limit is keyed on
// (ip, username) rather than ip alone. Both of those matter here: the whole
// college sits behind one public IP on the school wifi, so REMOTE_ADDR is
// effectively "everyone". Counting successes against a shared 5/minute IP
// bucket meant the sixth student to log in during a busy break was refused
// with "เข้าสู่ระบบผิดพลาดหลายครั้งเกินไป" despite typing the right password.
//
// The per-IP ceiling is kept as a backstop against someone spraying many
// usernames from one machine, just set far above what a room full of
// legitimate students produces.

const LOGIN_FAILURE_WINDOW_MINUTES = 1;
// Per (ip, username): stops password guessing against one specific account.
const LOGIN_MAX_FAILURES_PER_ACCOUNT = 5;
// Per ip across all usernames: stops one host working through many accounts.
// Only failures count, so normal use never approaches it.
const LOGIN_MAX_FAILURES_PER_IP = 50;

// True if this login attempt is allowed to proceed. Call before checking the
// password; record the outcome with record_failed_login() if it turns out wrong.
function check_login_rate_limit(PDO $conn, string $ip, string $username = ''): bool
{
    $conn->prepare(
        'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL ' . LOGIN_FAILURE_WINDOW_MINUTES . ' MINUTE)'
    )->execute();

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND username = ? AND attempted_at >= (NOW() - INTERVAL ' . LOGIN_FAILURE_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute([$ip, $username]);
    if ((int) $stmt->fetchColumn() >= LOGIN_MAX_FAILURES_PER_ACCOUNT) {
        return false;
    }

    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE ip = ? AND attempted_at >= (NOW() - INTERVAL ' . LOGIN_FAILURE_WINDOW_MINUTES . ' MINUTE)'
    );
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() < LOGIN_MAX_FAILURES_PER_IP;
}

function record_failed_login(PDO $conn, string $ip, string $username = ''): void
{
    $conn->prepare('INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())')
        ->execute([$ip, $username]);
}
