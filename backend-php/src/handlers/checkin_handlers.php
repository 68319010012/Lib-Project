<?php
// Ports app.py's /checkin. Auto-toggle reads the last log ordered by log_id
// DESC (not timestamp) — must stay that way to match tie-break behavior on
// same-timestamp inserts.
function handle_checkin(): void
{
    require_login();
    $userId = $_SESSION['user_id'];
    $body = request_body();

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT type FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();

    $nextType = ($last !== false && $last['type'] === 'in') ? 'out' : 'in';
    $plannedCheckoutAt = null;

    if ($nextType === 'in') {
        $durationMinutes = null;
        if (array_key_exists('duration_minutes', $body) && $body['duration_minutes'] !== null && $body['duration_minutes'] !== '') {
            if (!is_numeric($body['duration_minutes'])) {
                json_error('duration_minutes ต้องเป็นตัวเลข', 400);
            }
            $durationMinutes = (int) $body['duration_minutes'];
            if ($durationMinutes <= 0) {
                json_error('duration_minutes ต้องมากกว่า 0', 400);
            }
        }

        $checkoutTime = body_str($body, 'checkout_time') ?: null;
        if ($checkoutTime !== null && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $checkoutTime)) {
            json_error('รูปแบบ checkout_time ต้องเป็น HH:MM', 400);
        }

        $plannedCheckoutAt = compute_planned_checkout($durationMinutes, $checkoutTime);
        if ($plannedCheckoutAt !== null && $plannedCheckoutAt <= new DateTime()) {
            json_error('เวลาที่เลือกต้องอยู่ในอนาคต', 400);
        }

        $conn->prepare('INSERT INTO checkin_logs (user_id, type, planned_checkout_at) VALUES (?, ?, ?)')
            ->execute([$userId, 'in', $plannedCheckoutAt?->format('Y-m-d H:i:s')]);
    } else {
        $conn->prepare("INSERT INTO checkin_logs (user_id, type, checkout_source) VALUES (?, 'out', 'manual')")
            ->execute([$userId]);
    }

    $message = $nextType === 'in' ? 'เช็คอินสำเร็จ' : 'เช็คเอาต์สำเร็จ';
    json_response([
        'message' => $message,
        'type' => $nextType,
        'planned_checkout_at' => $plannedCheckoutAt ? to_isoformat($plannedCheckoutAt->format('Y-m-d H:i:s')) : null,
    ]);
}

// Ports app.py's /checkin/extend.
function handle_checkin_extend(): void
{
    require_login();
    $userId = $_SESSION['user_id'];
    $body = request_body();

    if (!isset($body['minutes']) || !is_numeric($body['minutes'])) {
        json_error('minutes ต้องเป็นตัวเลข', 400);
    }
    $extendMinutes = (int) $body['minutes'];
    if ($extendMinutes <= 0) {
        json_error('minutes ต้องมากกว่า 0', 400);
    }

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT log_id, type, planned_checkout_at FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();

    if ($last === false || $last['type'] !== 'in') {
        json_error('ไม่มีสถานะเช็คอินค้างอยู่', 400);
    }
    if ($last['planned_checkout_at'] === null) {
        json_error('เลือกจนกว่าจะปิดไว้ ไม่มีเวลาให้ต่อ', 400);
    }

    $newPlanned = (new DateTime($last['planned_checkout_at']))->modify("+{$extendMinutes} minutes");
    $closing = closing_datetime(new DateTime());
    if ($newPlanned > $closing) {
        $newPlanned = $closing;
    }

    $conn->prepare('UPDATE checkin_logs SET planned_checkout_at = ? WHERE log_id = ?')
        ->execute([$newPlanned->format('Y-m-d H:i:s'), $last['log_id']]);

    json_response([
        'message' => 'ต่อเวลาสำเร็จ',
        'planned_checkout_at' => to_isoformat($newPlanned->format('Y-m-d H:i:s')),
    ]);
}

// Ports app.py's /library-info.
function handle_library_info(): void
{
    require_login();
    json_response(['closing_time' => library_closing_time()]);
}

// Force-checks-out anyone past their planned_checkout_at. Called once per
// request (see public/index.php) instead of a background scheduler — a
// single INSERT...SELECT is cheap enough that a real cron/queue isn't
// needed, same reasoning as rate_limit.php's DB-backed limiter.
function auto_checkout_sweep(): void
{
    $conn = get_db_connection();
    $conn->exec(
        "INSERT INTO checkin_logs (user_id, type, checkout_source)
         SELECT c.user_id, 'out', 'auto'
         FROM checkin_logs c
         INNER JOIN (
             SELECT user_id, MAX(log_id) AS max_log_id FROM checkin_logs GROUP BY user_id
         ) latest ON c.user_id = latest.user_id AND c.log_id = latest.max_log_id
         WHERE c.type = 'in' AND c.planned_checkout_at IS NOT NULL AND c.planned_checkout_at <= NOW()"
    );
}
