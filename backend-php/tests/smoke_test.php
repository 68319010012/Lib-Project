<?php
/**
 * Plain-PHP end-to-end smoke test — no PHPUnit/Composer dependency, matching
 * this project's "no framework in the core API" stance. Ports the scenarios
 * from BackEnd/test_app.py, adapted to the CURRENT /register contract (full
 * profile fields required, no roster pre-check) — test_app.py itself is
 * stale against the current app.py (its registered_student fixture only
 * sends student_id/username/password, which the real /register now rejects
 * with 400) and was not used as a literal source.
 *
 * Requires a running PHP dev server (`php -S localhost:8000 -t public`) and
 * a real MySQL DB reachable via .env — same "real DB, not mocks" philosophy
 * as conftest.py. Cleans up every account it creates.
 *
 * Usage: php tests/smoke_test.php [base_url]  (defaults to http://localhost:8000)
 */

require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');
require __DIR__ . '/../src/db.php';

$BASE = $argv[1] ?? 'http://localhost:8000';
// student_id/username share a column (students.student_id VARCHAR(20)), so
// keep this short: "smk" + 8 hex chars = 11 chars.
$STUDENT_USERNAME = 'smk' . bin2hex(random_bytes(4));
$ADMIN_USERNAME = 'smka' . bin2hex(random_bytes(4));
$STUDENT_PASSWORD = 'SmokeTest!1234';
$ADMIN_PASSWORD = 'SmokeAdmin!1234';

$failures = 0;
$passes = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures, $passes;
    if ($condition) {
        $passes++;
        echo "PASS: $label\n";
    } else {
        $failures++;
        echo "FAIL: $label" . ($detail ? " — $detail" : '') . "\n";
    }
}

function http(string $method, string $url, ?array $json = null, string $cookieJar = ''): array
{
    $ch = curl_init($url);
    $headers = [];
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($cookieJar !== '') {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }
    if ($json !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($json, JSON_UNESCAPED_UNICODE));
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$status, json_decode($body, true)];
}

function cleanup(string $studentUsername, string $adminUsername): void
{
    $conn = get_db_connection();
    $conn->prepare(
        'DELETE cl FROM checkin_logs cl JOIN users u ON u.user_id = cl.user_id WHERE u.username IN (?, ?)'
    )->execute([$studentUsername, $adminUsername]);
    $conn->prepare('DELETE FROM users WHERE username IN (?, ?)')->execute([$studentUsername, $adminUsername]);
    $conn->prepare('DELETE FROM students WHERE student_id = ?')->execute([$studentUsername]);
    $conn->prepare('DELETE FROM login_attempts')->execute();
}

$studentJar = tempnam(sys_get_temp_dir(), 'smoke_student_');
$adminJar = tempnam(sys_get_temp_dir(), 'smoke_admin_');

try {
    // --- register ---
    [$status, $data] = http('POST', "$BASE/register", [
        'student_id' => $STUDENT_USERNAME,
        'username' => $STUDENT_USERNAME,
        'password' => $STUDENT_PASSWORD,
        'prefix' => 'นาย',
        'first_name' => 'Smoke',
        'last_name' => 'Test',
        'department' => 'แผนกทดสอบ',
        'level' => 'ปวช',
        'year_level' => '1',
    ], $studentJar);
    check('register -> 201', $status === 201, "got $status");
    check('register -> role student', ($data['role'] ?? null) === 'student');

    // --- duplicate register rejected ---
    [$status] = http('POST', "$BASE/register", [
        'student_id' => $STUDENT_USERNAME, 'username' => $STUDENT_USERNAME, 'password' => $STUDENT_PASSWORD,
        'prefix' => 'นาย', 'first_name' => 'Smoke', 'last_name' => 'Test',
        'department' => 'แผนกทดสอบ', 'level' => 'ปวช', 'year_level' => '1',
    ]);
    check('duplicate register -> 409', $status === 409, "got $status");

    // --- register logs in immediately ---
    [$status, $data] = http('GET', "$BASE/me", null, $studentJar);
    check('/me after register -> 200', $status === 200, "got $status");
    check('/me username matches', ($data['username'] ?? null) === $STUDENT_USERNAME);
    check('/me account_status approved', ($data['account_status'] ?? null) === 'approved');

    // --- checkin requires login ---
    [$status] = http('POST', "$BASE/checkin");
    check('checkin without login -> 401', $status === 401, "got $status");

    // --- checkin toggle ---
    [$status, $data] = http('POST', "$BASE/checkin", null, $studentJar);
    check('checkin #1 -> in', $status === 200 && ($data['type'] ?? null) === 'in', "got $status " . json_encode($data));
    [$status, $data] = http('POST', "$BASE/checkin", null, $studentJar);
    check('checkin #2 -> out', $status === 200 && ($data['type'] ?? null) === 'out', "got $status " . json_encode($data));

    // --- history reflects both events, newest first ---
    [$status, $data] = http('GET', "$BASE/me/history", null, $studentJar);
    check('/me/history -> 200', $status === 200);
    check('/me/history has 2 rows', is_array($data) && count($data) === 2, 'got ' . count($data ?? []));
    check('/me/history newest first (out, in)', ($data[0]['type'] ?? null) === 'out' && ($data[1]['type'] ?? null) === 'in');

    // --- change password: wrong current ---
    [$status] = http('POST', "$BASE/profile/change-password", [
        'current_password' => 'wrong-password', 'new_password' => 'NewPass!1234',
    ], $studentJar);
    check('change-password wrong current -> 401', $status === 401, "got $status");

    // --- change password: too short ---
    [$status] = http('POST', "$BASE/profile/change-password", [
        'current_password' => $STUDENT_PASSWORD, 'new_password' => 'short',
    ], $studentJar);
    check('change-password too short -> 400', $status === 400, "got $status");

    // --- change password: success, old password rejected, new accepted ---
    [$status] = http('POST', "$BASE/profile/change-password", [
        'current_password' => $STUDENT_PASSWORD, 'new_password' => 'NewPass!1234',
    ], $studentJar);
    check('change-password success -> 200', $status === 200, "got $status");

    http('POST', "$BASE/logout", null, $studentJar);
    [$status] = http('POST', "$BASE/login", ['username' => $STUDENT_USERNAME, 'password' => $STUDENT_PASSWORD]);
    check('login with OLD password -> 401', $status === 401, "got $status");
    // Re-login on $studentJar (not a throwaway request) so later checks that need
    // an authenticated-but-non-admin session still have a valid cookie to use.
    [$status] = http('POST', "$BASE/login", ['username' => $STUDENT_USERNAME, 'password' => 'NewPass!1234'], $studentJar);
    check('login with NEW password -> 200', $status === 200, "got $status");

    // --- admin-only routes reject a student ---
    [$status] = http('GET', "$BASE/admin/members", null, $studentJar);
    check('/admin/members as student -> 403', $status === 403, "got $status");

    // --- create a temp admin directly (mirrors conftest.py's temp_admin fixture) ---
    $conn = get_db_connection();
    $conn->prepare("INSERT INTO users (username, password_hash, role, student_id, account_status) VALUES (?, ?, 'admin', NULL, 'approved')")
        ->execute([$ADMIN_USERNAME, password_hash($ADMIN_PASSWORD, PASSWORD_BCRYPT)]);

    [$status] = http('POST', "$BASE/login", ['username' => $ADMIN_USERNAME, 'password' => $ADMIN_PASSWORD], $adminJar);
    check('admin login -> 200', $status === 200, "got $status");

    [$status, $data] = http('GET', "$BASE/admin/members?search=$STUDENT_USERNAME", null, $adminJar);
    check('/admin/members search finds the student', $status === 200 && count($data) === 1 && $data[0]['username'] === $STUDENT_USERNAME);

    [$status, $data] = http('GET', "$BASE/admin/reports", null, $adminJar);
    $studentEntries = array_values(array_filter($data ?? [], fn($row) => $row['student_id'] === $STUDENT_USERNAME));
    check('/admin/reports includes both checkin events', $status === 200 && count($studentEntries) === 2, 'got ' . count($studentEntries));

    // --- print report routes return HTML 200 ---
    foreach (['', '/daily', '/monthly', '/department', '/dashboard'] as $suffix) {
        [$status] = http('GET', "$BASE/admin/reports/print$suffix", null, $adminJar);
        check("print report$suffix -> 200", $status === 200, "got $status");
    }
} finally {
    @unlink($studentJar);
    @unlink($adminJar);
    cleanup($STUDENT_USERNAME, $ADMIN_USERNAME);
}

echo "\n$passes passed, $failures failed\n";
exit($failures > 0 ? 1 : 0);
