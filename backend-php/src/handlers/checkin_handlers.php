<?php
// Ports app.py's /checkin. Auto-toggle reads the last log ordered by log_id
// DESC (not timestamp) — must stay that way to match tie-break behavior on
// same-timestamp inserts.
// เวลาขั้นต่ำระหว่างการกดสองครั้งของคนเดียวกัน
//
// สั้นพอที่คนกดผิดแล้วอยากแก้ทันทีจะไม่ต้องรอนาน แต่ยาวพอให้การกดรัวๆ
// กลายเป็นแถวขยะในฐานข้อมูลไม่ได้ ที่ผ่านมามีการเข้าใช้ที่เข้าและออกใน
// วินาทีเดียวกันโผล่ในประวัติจริงมาแล้ว
function checkin_cooldown_seconds(): int
{
    return max(0, (int) env('CHECKIN_COOLDOWN_SECONDS', '10'));
}

function handle_checkin(): void
{
    require_login();
    $userId = $_SESSION['user_id'];
    $body = request_body();

    $conn = get_db_connection();
    $stmt = $conn->prepare('SELECT type, timestamp FROM checkin_logs WHERE user_id = ? ORDER BY log_id DESC LIMIT 1');
    $stmt->execute([$userId]);
    $last = $stmt->fetch();

    $cooldown = checkin_cooldown_seconds();
    if ($last !== false && $cooldown > 0) {
        // max(0) กันกรณีแถวล่าสุดมีเวลาอยู่ในอนาคต ซึ่งเกิดได้จาก
        // auto_checkout_sweep() ที่บันทึกเวลาปิดตามกำหนด ไม่ใช่เวลาที่รัน
        $elapsed = max(0, time() - strtotime($last['timestamp']));
        if ($elapsed < $cooldown) {
            $wait = $cooldown - $elapsed;
            json_error("กดถี่เกินไป กรุณารออีก {$wait} วินาทีแล้วลองใหม่", 429);
        }
    }

    $nextType = ($last !== false && $last['type'] === 'in') ? 'out' : 'in';
    $plannedCheckoutAt = null;

    if ($nextType === 'in') {
        if (!library_is_open_on()) {
            json_error(library_closed_today_message(), 400);
        }

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
        // $plannedCheckoutAt is null both when no duration/time was chosen
        // ("จนกว่าจะปิด") AND can't distinguish that from "closing time already
        // passed" on its own — check separately so this path rejects a closed
        // library the same way the duration/checkout_time paths already do
        // (compute_planned_checkout()'s clamp-to-closing only fires when a
        // duration/time was actually given).
        if ($plannedCheckoutAt === null && closing_datetime(new DateTime()) <= new DateTime()) {
            json_error('ห้องสมุดปิดแล้ว ไม่สามารถเช็คอินได้', 400);
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
    json_response([
        'closing_time' => library_closing_time(),
        'open_days' => library_open_days(),
        'open_days_label' => library_open_days_label(),
        'is_open_today' => library_is_open_on(),
        'closed_message' => library_closed_today_message(),
    ]);
}

// Closes out anyone whose visit is over but who never pressed the button.
// Called once per request (see public/index.php) instead of a background
// scheduler — a single INSERT...SELECT is cheap enough that a real cron/queue
// isn't needed, same reasoning as rate_limit.php's DB-backed limiter.
//
// Two things this has to get right, both of which it previously got wrong:
//
//  * WHO. The old filter was `planned_checkout_at IS NOT NULL`, which skipped
//    everyone who picked "จนกว่าจะปิด" (that choice stores NULL). They were
//    never swept at all and sat on the "กำลังใช้งานอยู่" list indefinitely —
//    16 of them locally, the oldest 32 hours stale. They close out at the
//    library's closing time on the day they checked in.
//
//  * WHEN. The row used to take DEFAULT CURRENT_TIMESTAMP, i.e. the moment the
//    sweep happened to run, which is whenever the next person touched the API.
//    A student who left at 15:00 got stamped 15:48 (worst observed locally),
//    and the last visitor of the day would carry until someone opened the site
//    the next morning. The stamp is now the time the visit was actually due to
//    end. It is still an estimate — nobody can know when they truly walked out
//    — but checkout_source='auto' marks exactly which rows are estimated, so
//    reports can tell them from real 'manual' ones.
function auto_checkout_sweep(): void
{
    $conn = get_db_connection();

    // GREATEST(..., c.timestamp) guards against a checkout landing before its
    // own check-in, which closing-time rows could otherwise do if
    // LIBRARY_CLOSING_TIME is ever moved earlier than an existing visit.
    // The expression appears in both the SELECT and the WHERE, and db.php runs
    // with EMULATE_PREPARES off — native prepares can't reuse one placeholder
    // across two positions, so each gets its own name bound to the same value.
    $dueAt = fn(string $param) =>
        "GREATEST(COALESCE(c.planned_checkout_at, TIMESTAMP(DATE(c.timestamp), $param)), c.timestamp)";

    $stmt = $conn->prepare(
        "INSERT INTO checkin_logs (user_id, type, timestamp, checkout_source)
         SELECT c.user_id, 'out', {$dueAt(':closing_select')}, 'auto'
         FROM checkin_logs c
         INNER JOIN (
             SELECT user_id, MAX(log_id) AS max_log_id FROM checkin_logs GROUP BY user_id
         ) latest ON c.user_id = latest.user_id AND c.log_id = latest.max_log_id
         WHERE c.type = 'in' AND {$dueAt(':closing_where')} <= NOW()"
    );
    $closingTime = library_closing_time() . ':00';
    $stmt->execute([':closing_select' => $closingTime, ':closing_where' => $closingTime]);
}
