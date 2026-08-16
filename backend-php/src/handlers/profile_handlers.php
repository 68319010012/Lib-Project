<?php
// Ports app.py's /me, /profile/change-password, /me/history.

function handle_me(): void
{
    require_login();
    $conn = get_db_connection();
    $stmt = $conn->prepare(
        'SELECT u.user_id, u.username, u.role, u.account_status,
                s.student_id, s.prefix, s.first_name, s.last_name,
                s.department, s.level, s.year_level, s.room
         FROM users u
         LEFT JOIN students s ON s.student_id = u.student_id
         WHERE u.user_id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $row = $stmt->fetch();
    json_response($row === false ? null : $row);
}

function handle_change_password(): void
{
    require_login();
    $body = request_body();
    $currentPassword = (string) ($body['current_password'] ?? '');
    $newPassword = (string) ($body['new_password'] ?? '');

    if ($currentPassword === '' || $newPassword === '') {
        json_error('กรุณากรอกรหัสผ่านปัจจุบันและรหัสผ่านใหม่', 400);
    }
    if (strlen($newPassword) < MIN_PASSWORD_LENGTH) {
        json_error('รหัสผ่านใหม่ต้องมีอย่างน้อย ' . MIN_PASSWORD_LENGTH . ' ตัวอักษร', 400);
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT password_hash FROM users WHERE user_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($currentPassword, $user['password_hash'])) {
        json_error('รหัสผ่านปัจจุบันไม่ถูกต้อง', 401);
    }

    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $conn->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')
        ->execute([$newHash, $_SESSION['user_id']]);

    // A password change is usually a response to "someone may have my
    // account" — issue a new session ID so any session that was riding the
    // old one is left holding a dead identifier.
    session_regenerate_id(true);

    json_response(['message' => 'เปลี่ยนรหัสผ่านสำเร็จ']);
}

function handle_my_history(): void
{
    require_login();
    $limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT);
    if ($limit === false) {
        $limit = 20;
    }
    $limit = max(1, min($limit, 100));

    // Used by HistoryModal's pagination (assets/js/history-modal.js) to page
    // through results 10 at a time instead of the old single 50-row fetch.
    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
    if ($offset === false || $offset < 0) {
        $offset = 0;
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT type, timestamp, planned_checkout_at FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT ? OFFSET ?');
    $stmt->bindValue(1, $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['timestamp'] = to_isoformat($row['timestamp']);
        $row['planned_checkout_at'] = to_isoformat($row['planned_checkout_at']);
    }
    json_response($rows);
}
