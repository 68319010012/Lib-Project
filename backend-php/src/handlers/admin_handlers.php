<?php
// Ports app.py's /admin/members and /admin/reports (JSON only — the 5
// print/* HTML routes live in src/reports/).

function handle_admin_members(): void
{
    require_login();
    require_admin();

    $search = trim((string) ($_GET['search'] ?? ''));
    $department = trim((string) ($_GET['department'] ?? ''));
    $level = trim((string) ($_GET['level'] ?? ''));
    $yearLevel = trim((string) ($_GET['year_level'] ?? ''));

    $conditions = ["u.account_status = 'approved'"];
    $params = [];
    if ($search !== '') {
        $conditions[] = '(s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR u.username LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like, $like);
    }
    if ($department !== '') {
        $conditions[] = 's.department = ?';
        $params[] = $department;
    }
    if ($level !== '') {
        $conditions[] = 's.level = ?';
        $params[] = $level;
    }
    if ($yearLevel !== '') {
        $conditions[] = 's.year_level = ?';
        $params[] = $yearLevel;
    }
    $whereClause = implode(' AND ', $conditions);

    $sql = "SELECT u.user_id, u.username, s.student_id, s.prefix, s.first_name, s.last_name,
                   s.department, s.level, s.year_level, s.room,
                   (SELECT MAX(c.timestamp) FROM checkin_logs c WHERE c.user_id = u.user_id) AS last_visit
            FROM users u
            JOIN students s ON s.student_id = u.student_id
            WHERE $whereClause
            ORDER BY s.first_name, s.last_name";

    $conn = get_db_connection();
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['last_visit'] = to_isoformat($row['last_visit']);
    }
    json_response($rows);
}

// Whoever's most recent checkin_logs row is 'in' is still inside — same
// "latest log per user" join auto_checkout_sweep() uses in checkin_handlers.php.
function handle_admin_active_now(): void
{
    require_login();
    require_admin();

    $sql = "SELECT u.user_id, u.username, s.student_id, s.prefix, s.first_name, s.last_name,
                   s.department, s.level, s.year_level, c.timestamp AS checked_in_at, c.planned_checkout_at
            FROM checkin_logs c
            INNER JOIN (
                SELECT user_id, MAX(log_id) AS max_log_id FROM checkin_logs GROUP BY user_id
            ) latest ON c.user_id = latest.user_id AND c.log_id = latest.max_log_id
            JOIN users u ON u.user_id = c.user_id
            JOIN students s ON s.student_id = u.student_id
            WHERE c.type = 'in'
            ORDER BY c.timestamp ASC";

    $conn = get_db_connection();
    $rows = $conn->query($sql)->fetchAll();
    foreach ($rows as &$row) {
        $row['checked_in_at'] = to_isoformat($row['checked_in_at']);
        $row['planned_checkout_at'] = $row['planned_checkout_at'] ? to_isoformat($row['planned_checkout_at']) : null;
    }
    json_response($rows);
}

// Admin manually ends someone's session — e.g. a student left without
// checking out. Writes a normal 'out' row tagged checkout_source =
// 'admin_forced' (schema already reserved this value; nothing previously
// wrote it) so it's distinguishable from the student's own checkout and
// from auto_checkout_sweep()'s planned-checkout expiry in reports/logs.
function handle_admin_force_checkout(): void
{
    require_login();
    require_admin();

    $body = request_body();
    $userId = (int) ($body['user_id'] ?? 0);
    if ($userId <= 0) {
        json_error('user_id ไม่ถูกต้อง', 400);
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT type FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();
    if ($last === false || $last['type'] !== 'in') {
        json_error('ผู้ใช้นี้ไม่ได้เช็คอินอยู่ในขณะนี้', 400);
    }

    $conn->prepare("INSERT INTO checkin_logs (user_id, type, checkout_source) VALUES (?, 'out', 'admin_forced')")
        ->execute([$userId]);

    json_response(['message' => 'บันทึกเช็คเอาต์ให้ผู้ใช้นี้แล้ว']);
}

// Admin-initiated password reset — this system has no email/phone on file
// (see schema.sql) so a self-service "forgot password" email flow isn't
// possible; the admin resets it here instead and reads the temp password
// out to the student in person.
function handle_admin_reset_password(): void
{
    require_login();
    require_admin();

    $body = request_body();
    $userId = (int) ($body['user_id'] ?? 0);
    if ($userId <= 0) {
        json_error('user_id ไม่ถูกต้อง', 400);
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT user_id FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch() === false) {
        json_error('ไม่พบผู้ใช้นี้', 404);
    }

    $tempPassword = generate_temp_password();
    $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
    $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([$hash, $userId]);

    json_response(['message' => 'รีเซ็ตรหัสผ่านสำเร็จ', 'temp_password' => $tempPassword]);
}

function handle_admin_reports(): void
{
    require_login();
    require_admin();

    $date = $_GET['date'] ?? null;
    $month = $_GET['month'] ?? null;
    $academicYear = $_GET['academic_year'] ?? null;

    $conditions = [];
    $params = [];
    if ($date) {
        $conditions[] = 'DATE(c.timestamp) = ?';
        $params[] = $date;
    }
    if ($month) {
        $conditions[] = "DATE_FORMAT(c.timestamp, '%Y-%m') = ?";
        $params[] = $month;
    }
    if ($academicYear) {
        $conditions[] = 's.academic_year = ?';
        $params[] = $academicYear;
    }
    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
                   c.type, c.timestamp
            FROM checkin_logs c
            JOIN users u ON u.user_id = c.user_id
            JOIN students s ON s.student_id = u.student_id
            $whereClause
            ORDER BY c.timestamp DESC";

    $conn = get_db_connection();
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['timestamp'] = to_isoformat($row['timestamp']);
    }
    json_response($rows);
}
