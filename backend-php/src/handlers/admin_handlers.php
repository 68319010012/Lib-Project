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

    // 'approved' by default so the page opens on the working roster, but any
    // single status — or 'all' — can be asked for. The retire sweep moves
    // accounts to 'retired' on its own, and an account the admin cannot see is
    // an account the admin cannot put back.
    $status = trim((string) ($_GET['status'] ?? 'approved'));
    $conditions = [];
    $params = [];
    if ($status !== 'all') {
        if (!in_array($status, ['pending', 'approved', 'retired'], true)) {
            $status = 'approved';
        }
        $conditions[] = 'u.account_status = ?';
        $params[] = $status;
    }
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
    // 'all' with no other filter leaves $conditions empty, and "WHERE" with
    // nothing after it is a syntax error.
    $whereClause = $conditions ? implode(' AND ', $conditions) : '1';

    // เรียงตามรหัสนักศึกษา ไม่ใช่ชื่อ ก-ฮ: เจ้าหน้าที่ค้นจากรหัสที่อยู่ในเอกสาร
    // LENGTH() มาก่อนเพราะ student_id เป็น VARCHAR — ถ้ารหัสยาวไม่เท่ากัน
    // การเรียงแบบข้อความล้วนจะวางรหัสสั้นกว่าไว้ผิดที่ (9 ตกไปอยู่หลัง 10)
    // last_visit was a correlated subquery: one MAX() over checkin_logs per
    // row returned, so its cost grew with the membership. At today's size both
    // forms are far below the request's own overhead, so this is not what made
    // the page feel slow (that was the response size — see admin-members.js);
    // it is a scaling fix, aggregating once into a derived table that
    // idx_checkin_logs_user_time already covers instead of scanning per row.
    $sql = "SELECT u.user_id, u.username, u.role, u.account_status,
                   s.student_id, s.prefix, s.gender, s.first_name, s.last_name,
                   s.department, s.level, s.year_level, s.room,
                   lv.last_visit
            FROM users u
            JOIN students s ON s.student_id = u.student_id
            LEFT JOIN (
                SELECT user_id, MAX(timestamp) AS last_visit
                FROM checkin_logs
                GROUP BY user_id
            ) lv ON lv.user_id = u.user_id
            WHERE $whereClause
            ORDER BY LENGTH(s.student_id), s.student_id";

    $conn = get_db_connection();
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['last_visit'] = to_isoformat($row['last_visit']);
    }
    unset($row);

    // The page used to label the count of the CURRENT result set "สมาชิกทั้งหมด",
    // so the number moved every time a filter changed and the real membership
    // total was nowhere on the page. These three are each a different question
    // and the page now shows them as three different numbers:
    //   total   — every student account, whatever the filters say
    //   active  — accounts that can actually log in right now
    //   roster  — students imported from the college roster, i.e. the ceiling
    //             `total` is working towards (0 until a roster is imported)
    // Counted over the same population the list draws from — users JOIN
    // students — not over `users` alone. handle_admin_member_role() can set
    // role='student' on an account created by scripts/create_admin.php, whose
    // student_id is NULL; such a row has no `students` match, so it can never
    // appear in the list. Counting it in `total` would put a headline number
    // on the page that the list is structurally unable to reach.
    $totals = $conn->query(
        "SELECT
            COUNT(*) AS total,
            SUM(u.account_status = 'approved') AS active,
            (SELECT COUNT(*) FROM students) AS roster
         FROM users u
         JOIN students s ON s.student_id = u.student_id
         WHERE u.role = 'student'"
    )->fetch();

    json_response([
        'rows' => $rows,
        'total' => (int) $totals['total'],
        'active' => (int) $totals['active'],
        'roster' => (int) $totals['roster'],
    ]);
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
                   s.gender, c.type, c.timestamp
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

// --- Member editing -------------------------------------------------------
//
// Three separate endpoints on purpose. Editing a profile, changing what
// someone is allowed to do, and destroying records are three different levels
// of consequence; sharing one code path would let a stray field in a request
// body cross between them. Each re-reads its target from the database rather
// than trusting anything the form said about it.

// Resolves the target of an admin action, or ends the request with the right
// error — so a caller can treat the return value as "this user exists".
function admin_target_user(PDO $conn, int $userId): array
{
    if ($userId <= 0) {
        json_error('user_id ไม่ถูกต้อง', 400);
    }
    $stmt = $conn->prepare('SELECT user_id, username, role, student_id, account_status FROM users WHERE user_id = ?');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if ($user === false) {
        json_error('ไม่พบผู้ใช้นี้', 404);
    }
    return $user;
}

// The system must never reach a state where nobody can administer it. Demoting,
// retiring and deleting can each produce that, so all three ask this first.
function admin_count_other_admins(PDO $conn, int $excludingUserId): int
{
    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM users
         WHERE role = 'admin' AND account_status = 'approved' AND user_id <> ?"
    );
    $stmt->execute([$excludingUserId]);
    return (int) $stmt->fetchColumn();
}

function handle_admin_member_update(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();
    $body = request_body();
    $user = admin_target_user($conn, (int) ($body['user_id'] ?? 0));

    if ($user['student_id'] === null) {
        json_error('บัญชีนี้ไม่มีข้อมูลนักศึกษาให้แก้ไข', 400);
    }

    $profile = [
        'prefix' => body_str($body, 'prefix'),
        'gender' => body_str($body, 'gender'),
        'first_name' => body_str($body, 'first_name'),
        'last_name' => body_str($body, 'last_name'),
        'department' => body_str($body, 'department'),
        'level' => body_str($body, 'level'),
        'year_level' => body_str($body, 'year_level'),
    ];
    // Exactly the rules signup enforces — validate_student_profile() in
    // src/constants.php. An edit form that accepted more than signup does
    // would be a way around the whitelist rather than a convenience.
    if (!validate_student_profile($profile, $profileError)) {
        json_error($profileError, 400);
    }

    $room = body_str($body, 'room');
    if (mb_strlen($room) > 10) {
        json_error('ห้องยาวเกินไป (ไม่เกิน 10 ตัวอักษร)', 400);
    }

    $status = body_str($body, 'account_status');
    if (!in_array($status, ['pending', 'approved', 'retired'], true)) {
        json_error('สถานะบัญชีไม่ถูกต้อง', 400);
    }
    $isSelf = (int) $user['user_id'] === (int) $_SESSION['user_id'];
    // Locking yourself out shouldn't be reachable from a form. Recovering from
    // it means editing `users` by hand in SQL.
    if ($isSelf && $status !== $user['account_status']) {
        json_error('เปลี่ยนสถานะบัญชีของตัวเองไม่ได้', 400);
    }
    if (
        $user['role'] === 'admin' && $status !== 'approved'
        && admin_count_other_admins($conn, (int) $user['user_id']) === 0
    ) {
        json_error('ระงับบัญชีนี้ไม่ได้ — เป็นแอดมินคนสุดท้ายที่ใช้งานได้', 400);
    }

    $conn->beginTransaction();
    try {
        $conn->prepare(
            'UPDATE students SET prefix = ?, gender = ?, first_name = ?, last_name = ?,
                                 department = ?, level = ?, year_level = ?, room = ?
             WHERE student_id = ?'
        )->execute([
            $profile['prefix'], $profile['gender'], $profile['first_name'], $profile['last_name'],
            $profile['department'], $profile['level'], $profile['year_level'],
            $room === '' ? null : $room,
            $user['student_id'],
        ]);
        $conn->prepare('UPDATE users SET account_status = ? WHERE user_id = ?')
            ->execute([$status, $user['user_id']]);
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    json_response(['message' => 'บันทึกข้อมูลแล้ว']);
}

function handle_admin_member_role(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();
    $body = request_body();
    $user = admin_target_user($conn, (int) ($body['user_id'] ?? 0));

    $role = body_str($body, 'role');
    if (!in_array($role, ['student', 'admin'], true)) {
        json_error('สิทธิ์ไม่ถูกต้อง', 400);
    }
    // Editing your own role is either a no-op or a self-demotion, and the
    // second one takes the page away underneath the request that did it.
    if ((int) $user['user_id'] === (int) $_SESSION['user_id']) {
        json_error('เปลี่ยนสิทธิ์ของตัวเองไม่ได้', 400);
    }
    if (
        $user['role'] === 'admin' && $role === 'student'
        && admin_count_other_admins($conn, (int) $user['user_id']) === 0
    ) {
        json_error('ลดสิทธิ์ไม่ได้ — เป็นแอดมินคนสุดท้ายที่ใช้งานได้', 400);
    }

    $conn->prepare('UPDATE users SET role = ? WHERE user_id = ?')->execute([$role, $user['user_id']]);
    json_response([
        'message' => $role === 'admin' ? 'ตั้งเป็นแอดมินแล้ว' : 'เปลี่ยนเป็นนักศึกษาแล้ว',
    ]);
}

function handle_admin_member_delete(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();
    $body = request_body();
    $user = admin_target_user($conn, (int) ($body['user_id'] ?? 0));

    if ((int) $user['user_id'] === (int) $_SESSION['user_id']) {
        json_error('ลบบัญชีของตัวเองไม่ได้', 400);
    }
    if ($user['role'] === 'admin' && admin_count_other_admins($conn, (int) $user['user_id']) === 0) {
        json_error('ลบไม่ได้ — เป็นแอดมินคนสุดท้ายที่ใช้งานได้', 400);
    }

    $deletedLogs = 0;
    $conn->beginTransaction();
    try {
        // checkin_logs.user_id is a plain FK with no ON DELETE rule, so the
        // visit history has to go first or the delete is refused outright.
        // That history is also what every report counts, so deleting an account
        // changes past reports — the UI confirmation says so, and the count
        // comes back below so the admin sees the size of what just happened.
        $logStmt = $conn->prepare('SELECT COUNT(*) FROM checkin_logs WHERE user_id = ?');
        $logStmt->execute([$user['user_id']]);
        $deletedLogs = (int) $logStmt->fetchColumn();

        $conn->prepare('DELETE FROM checkin_logs WHERE user_id = ?')->execute([$user['user_id']]);
        $conn->prepare('DELETE FROM users WHERE user_id = ?')->execute([$user['user_id']]);

        // The students row is the profile, not the account, and the roster
        // import (scripts/import_students.php) can create one with no account
        // attached. Only drop it when this was the last account pointing at it.
        if ($user['student_id'] !== null) {
            $refStmt = $conn->prepare('SELECT COUNT(*) FROM users WHERE student_id = ?');
            $refStmt->execute([$user['student_id']]);
            if ((int) $refStmt->fetchColumn() === 0) {
                $conn->prepare('DELETE FROM students WHERE student_id = ?')->execute([$user['student_id']]);
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    json_response(['message' => 'ลบบัญชีแล้ว', 'deleted_logs' => $deletedLogs]);
}
