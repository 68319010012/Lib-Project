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
