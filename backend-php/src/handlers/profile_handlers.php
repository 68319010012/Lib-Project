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
//
// The pairing subquery is shared by every query this endpoint runs (the page,
// the range summary, and the count behind the pager), so it lives here once
// instead of being pasted three times and drifting apart.
function my_visits_paired_sql(): string
{
    return "SELECT
                log_id,
                type,
                timestamp AS checkin_at,
                planned_checkout_at,
                LEAD(type)            OVER (ORDER BY log_id) AS next_type,
                LEAD(timestamp)       OVER (ORDER BY log_id) AS next_timestamp,
                LEAD(checkout_source) OVER (ORDER BY log_id) AS next_source
            FROM checkin_logs
            WHERE user_id = :uid";
}

// Reads one YYYY-MM-DD query parameter, or null if it is absent or is not a
// real calendar date.
//
// Deliberately not a 400: the value comes from an <input type="date">, so a
// malformed one means a hand-edited URL or a browser quirk, and answering
// with the student's whole history is more use to the person staring at the
// modal than an error that empties it with no way back. 2026-02-31 counts as
// malformed — createFromFormat would roll it forward to March 3rd, and a
// filter that quietly moves the day you asked for is worse than one that
// ignores it.
function visits_date_param(string $key): ?string
{
    $raw = trim((string) ($_GET[$key] ?? ''));
    if ($raw === '') {
        return null;
    }
    // '!' zeroes the time fields, so anything that parses is a pure date.
    $parsed = DateTime::createFromFormat('!Y-m-d', $raw);
    // getLastErrors() returns false (nothing wrong) on PHP 8.2+ and an array
    // of zero counts before that; both shapes have to read as clean.
    $errors = DateTime::getLastErrors();
    $rolledOver = is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
    if ($parsed === false || $rolledOver) {
        return null;
    }
    return $parsed->format('Y-m-d');
}

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

    $from = visits_date_param('from');
    $to = visits_date_param('to');
    // A backwards range is someone filling the second picker before the
    // first, not a request to be shown nothing. Swap instead of returning
    // an empty list they have to work out how to escape.
    if ($from !== null && $to !== null && $from > $to) {
        [$from, $to] = [$to, $from];
    }

    // The date filter is applied to the PAIRED rows, never inside the
    // subquery. LEAD() has to see the whole log to work: a visit starting on
    // the last day of the range keeps its check-out row outside the range,
    // and narrowing first would hide that row and report a finished visit as
    // one the student never came back from.
    //
    // Both ends cover the whole day. checkin_at is a DATETIME, so the upper
    // bound is "< the day after" rather than "<= the day", which would cut
    // off every visit that started after midnight on the last day.
    $dateWhere = '';
    $dateParams = [];
    if ($from !== null) {
        $dateWhere .= ' AND paired.checkin_at >= :from_at';
        $dateParams[':from_at'] = $from . ' 00:00:00';
    }
    if ($to !== null) {
        $dateWhere .= ' AND paired.checkin_at < :to_at';
        $dateParams[':to_at'] = (new DateTime($to))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    }

    $conn = get_db_connection();
    $paired = my_visits_paired_sql();

    $stmt = $conn->prepare(
        "SELECT checkin_at, planned_checkout_at, next_type, next_timestamp, next_source
         FROM ({$paired}) paired
         WHERE paired.type = 'in'{$dateWhere}
         ORDER BY paired.log_id DESC
         LIMIT :row_limit OFFSET :row_offset"
    );
    $stmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':row_limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':row_offset', $offset, PDO::PARAM_INT);
    foreach ($dateParams as $name => $value) {
        $stmt->bindValue($name, $value);
    }
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

    // Totals for the whole filtered range, not for the page. The pager needs
    // the count to say "page 3 of 12" instead of only offering a Next button
    // that gives no idea how far back the list goes, and the summary line
    // answers "how much was I here this month" — the actual reason someone
    // sets a date range.
    //
    // Minutes add up over CLOSED visits only: an open one has no end yet, and
    // measuring it against "now" would make the total creep up on every
    // reload of the same range.
    $summaryStmt = $conn->prepare(
        "SELECT
             COUNT(*) AS total_visits,
             SUM(CASE WHEN paired.next_type = 'out' THEN 1 ELSE 0 END) AS closed_visits,
             SUM(CASE WHEN paired.next_type = 'out'
                      THEN TIMESTAMPDIFF(SECOND, paired.checkin_at, paired.next_timestamp)
                 END) AS total_seconds
         FROM ({$paired}) paired
         WHERE paired.type = 'in'{$dateWhere}"
    );
    $summaryStmt->bindValue(':uid', $_SESSION['user_id'], PDO::PARAM_INT);
    foreach ($dateParams as $name => $value) {
        $summaryStmt->bindValue($name, $value);
    }
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch() ?: [];

    $totalVisits = (int) ($summary['total_visits'] ?? 0);
    $closedVisits = (int) ($summary['closed_visits'] ?? 0);
    $totalSeconds = (int) ($summary['total_seconds'] ?? 0);

    // Unfiltered on purpose, so the two date pickers can clamp themselves to
    // the span the student actually has rows in. Being free to pick a month
    // from before they enrolled only produces an empty list to back out of.
    $boundsStmt = $conn->prepare(
        "SELECT DATE(MIN(timestamp)) AS first_date, DATE(MAX(timestamp)) AS last_date
         FROM checkin_logs
         WHERE user_id = ? AND type = 'in'"
    );
    $boundsStmt->execute([$_SESSION['user_id']]);
    $bounds = $boundsStmt->fetch() ?: [];

    // An object, not the bare array this used to return: the page alone can't
    // tell the modal how many visits the range holds. assets/js/history-modal.js
    // is the only caller.
    json_response([
        'visits' => $visits,
        'total' => $totalVisits,
        'summary' => [
            'visits' => $totalVisits,
            'closed_visits' => $closedVisits,
            'total_seconds' => $totalSeconds,
            // Averaged over closed visits to match total_seconds. Dividing by
            // every visit would pull the average down by one whole trip for
            // each one still open.
            'avg_seconds' => $closedVisits > 0 ? (int) round($totalSeconds / $closedVisits) : 0,
        ],
        'range' => ['from' => $from, 'to' => $to],
        'bounds' => [
            'first' => $bounds['first_date'] ?? null,
            'last' => $bounds['last_date'] ?? null,
        ],
    ]);
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
