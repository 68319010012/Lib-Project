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
    if (!password_length_ok($newPassword, $passwordError)) {
        json_error($passwordError, 400);
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

// One row per VISIT, not per event. /me/history returns the raw check-in and
// check-out rows separately, which is what the dashboard's "am I inside?"
// check needs but not what a person reading their own history wants: they
// think in visits ("Tuesday, 09:12 to 11:40"), and pairing two adjacent rows
// by eye across a paginated list is work the server can just do.
//
// LEAD() gives each row the one immediately after it for the same user, so an
// 'in' whose next row is an 'out' is a closed visit and an 'in' with no 'out'
// after it is one still open. That is exact rather than "the next out
// anywhere below", which would mis-pair if two check-ins ever landed in a row.
// Paging is applied to the 'in' rows, so a page is always whole visits.
function handle_my_visits(): void
{
    require_login();
    $limit = filter_var($_GET['limit'] ?? 10, FILTER_VALIDATE_INT);
    if ($limit === false) {
        $limit = 10;
    }
    $limit = max(1, min($limit, 100));

    $offset = filter_var($_GET['offset'] ?? 0, FILTER_VALIDATE_INT);
    if ($offset === false || $offset < 0) {
        $offset = 0;
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare(
        "SELECT checkin_at, planned_checkout_at, next_type, next_timestamp, next_source
         FROM (
             SELECT
                 log_id,
                 type,
                 timestamp AS checkin_at,
                 planned_checkout_at,
                 LEAD(type)            OVER (ORDER BY log_id) AS next_type,
                 LEAD(timestamp)       OVER (ORDER BY log_id) AS next_timestamp,
                 LEAD(checkout_source) OVER (ORDER BY log_id) AS next_source
             FROM checkin_logs
             WHERE user_id = :uid
         ) paired
         WHERE paired.type = 'in'
         ORDER BY paired.log_id DESC
         LIMIT :row_limit OFFSET :row_offset"
    );
    $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':row_limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':row_offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $visits = array_map(function (array $row): array {
        // next_type === 'out' is the only shape that closes a visit. A NULL
        // next_type (this is the newest row) or an 'in' both mean "still
        // inside", and the UI shows those as an open visit rather than
        // inventing a checkout time.
        $closed = $row['next_type'] === 'out';
        return [
            'checkin_at' => to_isoformat($row['checkin_at']),
            'checkout_at' => $closed ? to_isoformat($row['next_timestamp']) : null,
            'checkout_source' => $closed ? $row['next_source'] : null,
            'planned_checkout_at' => to_isoformat($row['planned_checkout_at']),
        ];
    }, $stmt->fetchAll());

    json_response($visits);
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
