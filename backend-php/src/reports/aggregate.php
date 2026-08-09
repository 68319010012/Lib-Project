<?php
// Shared period-aggregation helpers, ported from app.py's _aggregate_checkin_period()
// and friends. Used by executive.php and compare.php so period-over-period
// comparisons all use the same counting logic (report_dashboard.php has its
// own inline version of this query and isn't changed to use it, to avoid
// touching working code unrelated to this port).

function aggregate_checkin_period(PDO $conn, string $startDate, string $endDate): array
{
    $stmt = $conn->prepare(
        "SELECT s.student_id, s.department, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE DATE(c.timestamp) BETWEEN ? AND ?"
    );
    $stmt->execute([$startDate, $endDate]);
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
