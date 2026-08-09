<?php
// Ports app.py's /checkin. Auto-toggle reads the last log ordered by log_id
// DESC (not timestamp) — must stay that way to match tie-break behavior on
// same-timestamp inserts.
function handle_checkin(): void
{
    require_login();
    $userId = $_SESSION['user_id'];

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT type FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();

    $nextType = ($last !== false && $last['type'] === 'in') ? 'out' : 'in';

    $conn->prepare('INSERT INTO checkin_logs (user_id, type) VALUES (?, ?)')->execute([$userId, $nextType]);

    $message = $nextType === 'in' ? 'เช็คอินสำเร็จ' : 'เช็คเอาต์สำเร็จ';
    json_response(['message' => $message, 'type' => $nextType]);
}
