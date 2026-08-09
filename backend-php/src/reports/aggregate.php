<?php
// Shared period-aggregation helpers, ported from app.py's _aggregate_checkin_period()
// and friends. Used by executive.php and compare.php so period-over-period
// comparisons all use the same counting logic (report_dashboard.php has its
// own inline version of this query and isn't changed to use it, to avoid
// touching working code unrelated to this port).

// Builds WHERE-clause fragments (no leading AND) + matching params for the
// standard student/department filter set shared by every report — single
// source of truth so daily/monthly/department/dashboard/semester all filter
// department/level/year_level/semester/academic_year identically (also what
// makes department.php's drill-down links into daily.php/monthly.php work:
// same filter names, same meaning, on both ends).
function build_filter_clause(array $filters): array
{
    $columnByKey = [
        'department' => 's.department',
        'level' => 's.level',
        'year_level' => 's.year_level',
        'semester' => 's.semester',
        'academic_year' => 's.academic_year',
    ];
    $clauses = [];
    $params = [];
    foreach ($columnByKey as $key => $column) {
        if (!empty($filters[$key])) {
            $clauses[] = "$column = ?";
            $params[] = $filters[$key];
        }
    }
    return [$clauses, $params];
}

// Distinct non-empty values for a students column, sorted — powers filter
// dropdowns (department/level/semester/academic_year) so the options always
// match real data instead of a hardcoded, easily-stale list. $column is
// whitelisted (never actually caller-controlled — every call site below
// passes a literal) but checked anyway before it's interpolated.
function distinct_student_values(PDO $conn, string $column): array
{
    $allowed = ['department', 'level', 'year_level', 'semester', 'academic_year'];
    if (!in_array($column, $allowed, true)) {
        throw new InvalidArgumentException("distinct_student_values: unsupported column $column");
    }
    $stmt = $conn->query("SELECT DISTINCT $column FROM students WHERE $column IS NOT NULL AND $column <> '' ORDER BY $column");
    return array_column($stmt->fetchAll(), $column);
}

function aggregate_checkin_period(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.student_id, s.department, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));
    $rows = $stmt->fetchAll();

    $dayCounts = [];
    $deptCounts = [];
    $studentIds = [];
    foreach ($rows as $row) {
        $dayKey = substr($row['timestamp'], 0, 10);
        $dayCounts[$dayKey] = ($dayCounts[$dayKey] ?? 0) + 1;
        $dept = $row['department'] ?: 'ไม่ระบุแผนก';
        $deptCounts[$dept] = ($deptCounts[$dept] ?? 0) + 1;
        $studentIds[$row['student_id']] = true;
    }

    $daysWithData = count($dayCounts);
    $totalEvents = count($rows);

    $busiestDay = null;
    foreach ($dayCounts as $key => $count) {
        if ($busiestDay === null || $count > $busiestDay['count']) {
            $busiestDay = ['day' => $key, 'count' => $count];
        }
    }

    return [
        'total_events' => $totalEvents,
        'unique_students' => count($studentIds),
        'avg_daily' => $daysWithData ? (int) round($totalEvents / $daysWithData) : 0,
        'busiest_day' => $busiestDay,
        'dept_counts' => $deptCounts,
    ];
}

// % change from previous -> current, or null if there's no previous-period
// baseline to compare against (avoids a division by zero reading as 0%).
function pct_delta(int $current, int $previous): ?float
{
    if (!$previous) {
        return null;
    }
    return round((($current - $previous) / $previous) * 100, 1);
}

// 'YYYY-MM' -> ['YYYY-MM-01', 'YYYY-MM-DD' (last day)].
function month_bounds(string $month): array
{
    [$year, $mon] = array_map('intval', explode('-', $month));
    $lastDay = (int) date('t', mktime(0, 0, 0, $mon, 1, $year));
    return [sprintf('%s-01', $month), sprintf('%s-%02d', $month, $lastDay)];
}

// 'YYYY-MM' -> "เดือน<ชื่อเดือนไทย> <ปี พ.ศ.>", e.g. "เดือนสิงหาคม 2569" — used
// to build the auto-generated summary paragraph's $periodLabel without
// hand-writing Thai month names at every call site.
function thai_month_label(string $month): string
{
    static $names = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];
    [$year, $mon] = array_map('intval', explode('-', $month));
    $name = $names[$mon] ?? $month;
    return "เดือน$name " . ($year + 543);
}

function previous_month(string $month): string
{
    [$year, $mon] = array_map('intval', explode('-', $month));
    if ($mon === 1) {
        return ($year - 1) . '-12';
    }
    return sprintf('%d-%02d', $year, $mon - 1);
}

// dept_counts array (name => count) -> sorted list of {name, count} ranked
// by count desc, top $limit only.
function ranked_breakdown(array $counts, int $limit = 8): array
{
    $list = [];
    foreach ($counts as $name => $count) {
        $list[] = ['name' => $name, 'count' => $count];
    }
    usort($list, fn($a, $b) => $b['count'] <=> $a['count']);
    return array_slice($list, 0, $limit);
}

// Hour-of-day (0-23) event counts within [startDate, endDate] — all 24 hours
// present (0 for empty ones, so the chart never skips a bar), plus the
// busiest hour (null if the whole range has zero events).
function aggregate_hourly(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT HOUR(c.timestamp) AS hr, COUNT(*) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY hr"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $counts = array_fill(0, 24, 0);
    foreach ($stmt->fetchAll() as $row) {
        $counts[(int) $row['hr']] = (int) $row['cnt'];
    }

    $hours = [];
    foreach ($counts as $hr => $count) {
        $hours[] = ['hour' => $hr, 'count' => $count];
    }

    $peakHour = null;
    foreach ($hours as $h) {
        if ($h['count'] > 0 && ($peakHour === null || $h['count'] > $peakHour['count'])) {
            $peakHour = $h;
        }
    }

    return ['hours' => $hours, 'peak_hour' => $peakHour];
}

// Buckets events into "week N of the month" (ceil(day/7): days 1-7 = week 1,
// 8-14 = week 2, ...) rather than ISO week numbers — matches the
// "สัปดาห์ที่ 1/2/3/4" framing the report needs, and stays aligned to a
// single month's boundaries instead of spilling into neighboring months.
// Only weeks with at least one event are returned.
function aggregate_weekly(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT DAY(c.timestamp) AS d, COUNT(*) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY d"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $weekCounts = [];
    foreach ($stmt->fetchAll() as $row) {
        $week = (int) ceil(((int) $row['d']) / 7);
        $weekCounts[$week] = ($weekCounts[$week] ?? 0) + (int) $row['cnt'];
    }
    ksort($weekCounts);

    $weeks = [];
    foreach ($weekCounts as $week => $count) {
        $weeks[] = ['week' => $week, 'count' => $count];
    }
    return $weeks;
}

// Per-day event counts across [startDate, endDate] inclusive, zero-filled
// for days with no data (so a trend chart never silently skips a day).
// Reused by dashboard.php and monthly.php. Callers should keep the range to
// a month/semester scale — this walks it one day at a time in PHP.
function aggregate_daily_trend(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT DATE(c.timestamp) AS d, COUNT(*) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY d"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));
    $countByDate = [];
    foreach ($stmt->fetchAll() as $row) {
        $countByDate[$row['d']] = (int) $row['cnt'];
    }

    $trend = [];
    $cursor = new DateTime($startDate);
    $end = new DateTime($endDate);
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $trend[] = ['date' => $key, 'count' => $countByDate[$key] ?? 0];
        $cursor->modify('+1 day');
    }

    $max = 0;
    foreach ($trend as $t) {
        $max = max($max, $t['count']);
    }
    foreach ($trend as &$t) {
        $t['pct'] = $max ? (int) round(($t['count'] / $max) * 100) : 0;
    }
    unset($t);

    return $trend;
}

// Per-department breakdown — event count, unique students, % of total
// (always sums to ~100% since it's derived from the same $totalEvents), and
// rank. The "แผนกวิชา | จำนวนการเข้าใช้ | ผู้ใช้ไม่ซ้ำ | ร้อยละ | อันดับ" table
// shape shared by dashboard/monthly/department reports.
function aggregate_department_breakdown(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.department, COUNT(*) AS event_count, COUNT(DISTINCT s.student_id) AS unique_count
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.department"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));
    $rows = $stmt->fetchAll();

    $totalEvents = 0;
    foreach ($rows as $row) {
        $totalEvents += (int) $row['event_count'];
    }

    $breakdown = [];
    foreach ($rows as $row) {
        $breakdown[] = [
            'name' => $row['department'] ?: 'ไม่ระบุแผนก',
            'count' => (int) $row['event_count'],
            'unique' => (int) $row['unique_count'],
            'pct' => $totalEvents ? round(($row['event_count'] / $totalEvents) * 100, 1) : 0,
        ];
    }
    usort($breakdown, fn($a, $b) => $b['count'] <=> $a['count']);
    foreach ($breakdown as $i => &$row) {
        $row['rank'] = $i + 1;
    }
    unset($row);

    return $breakdown;
}

// Generalized version of aggregate_department_breakdown() for grouping by
// any single students column — used by department.php's "แยกตามระดับชั้น"
// cross-tab (aggregate_department_breakdown() itself is left alone since
// dashboard.php/monthly.php already depend on its exact department-only shape).
function aggregate_breakdown_by(PDO $conn, string $column, string $startDate, string $endDate, array $filters = []): array
{
    $allowed = ['department', 'level', 'year_level', 'semester', 'academic_year'];
    if (!in_array($column, $allowed, true)) {
        throw new InvalidArgumentException("aggregate_breakdown_by: unsupported column $column");
    }
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.$column AS grp, COUNT(*) AS event_count, COUNT(DISTINCT s.student_id) AS unique_count
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.$column"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));
    $rows = $stmt->fetchAll();

    $totalEvents = 0;
    foreach ($rows as $row) {
        $totalEvents += (int) $row['event_count'];
    }
    $breakdown = [];
    foreach ($rows as $row) {
        $breakdown[] = [
            'name' => $row['grp'] ?: 'ไม่ระบุ',
            'count' => (int) $row['event_count'],
            'unique' => (int) $row['unique_count'],
            'pct' => $totalEvents ? round(($row['event_count'] / $totalEvents) * 100, 1) : 0,
        ];
    }
    usort($breakdown, fn($a, $b) => $b['count'] <=> $a['count']);
    foreach ($breakdown as $i => &$row) {
        $row['rank'] = $i + 1;
    }
    unset($row);
    return $breakdown;
}

// Per-calendar-month event counts with NO date bound — semester.php's case,
// where academic_year/semester describe a student's *current* registration
// rather than a date range (see the confirmed-decision note in
// select.php/dashboard.php's filter-note text), so there's no [start,end] to
// pass. Returns only months that actually have data, sorted ascending.
// (aggregate_daily_trend() isn't reusable here — walking it day-by-day across
// an unbounded range would be unbounded work.)
function aggregate_monthly_totals(PDO $conn, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = $filterClauses ? implode(' AND ', $filterClauses) : '1=1';
    $stmt = $conn->prepare(
        "SELECT DATE_FORMAT(c.timestamp, '%Y-%m') AS ym, COUNT(*) AS cnt, COUNT(DISTINCT s.student_id) AS unique_cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY ym
         ORDER BY ym"
    );
    $stmt->execute($filterParams);
    return $stmt->fetchAll();
}

// Builds the auto-generated "สรุปสำหรับผู้บริหาร" paragraph (HTML) straight
// from server-computed aggregates — never hand-write these numbers in a
// report, always pass what aggregate_checkin_period()/aggregate_hourly()
// already computed, so the paragraph can't drift from the KPI cards next to
// it. $periodLabel is a caller-formatted phrase like "เดือนสิงหาคม 2569" or
// "วันที่ 9 สิงหาคม 2569"; $busiestDay/$peakHour are optional (null when
// there's no data for that dimension).
function build_summary_sentence(string $periodLabel, array $agg, ?array $busiestDay, array $topDepts, ?array $peakHour): string
{
    $html = 'ใน' . htmlspecialchars($periodLabel) . ' ห้องสมุดมีการเช็คชื่อเข้า-ออกรวม '
        . "<b>{$agg['total_events']} รายการ</b> จากนักศึกษา <b>{$agg['unique_students']} คน</b> "
        . "เฉลี่ย <b>{$agg['avg_daily']} รายการต่อวัน</b>";
    if ($busiestDay) {
        $html .= ' โดยวันที่มีผู้ใช้บริการมากที่สุดคือ <b>' . htmlspecialchars((string) $busiestDay['day'])
            . "</b> ({$busiestDay['count']} รายการ)";
    }
    if ($topDepts) {
        $html .= ' แผนกที่เข้าใช้บริการมากที่สุดคือ <b>' . htmlspecialchars($topDepts[0]['name'])
            . "</b> ({$topDepts[0]['count']} รายการ)";
    }
    if ($peakHour) {
        $html .= ' และช่วงเวลาที่มีการใช้งานสูงสุดคือ <b>' . sprintf('%02d:00', $peakHour['hour']) . ' น.</b>';
    }
    return $html;
}
