<?php
// Ports app.py's admin_report_dashboard() + templates/report_dashboard.html,
// extended with the fuller filter/KPI/chart set from the report-system
// redesign. Every aggregate query below goes through src/reports/aggregate.php
// so this report, monthly.php, and department.php can never disagree on how
// "total events"/"unique students"/"department breakdown" etc. are computed
// for the same filters — the numbers here must always match what the CSV/
// Excel export and the other reports would show for the same period.
//
// Presentation follows the dataviz skill's method: form before color (single
// values → stat tiles, not decorative rings; the one genuine percentage gets
// the one meter), one sequential brand hue for every magnitude bar (never a
// rainbow across nominal categories — that's identity-channel spend on data
// the bar length already shows), neutral ink for stat-tile values (color is
// reserved for marks and delta direction), and a single hero figure instead
// of six same-sized numbers competing for attention.

// Tiny inline SVG trend line for a stat tile's sparkline — pure presentation
// over an already-computed series (no new aggregation).
function render_dashboard_sparkline(array $values, string $color, bool $isPdfExport = false): string
{
    if ($isPdfExport || !$values) {
        return '';
    }
    $max = max($values) ?: 1;
    $w = 72;
    $h = 24;
    $n = count($values);
    $points = [];
    foreach ($values as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $w : $w / 2;
        $y = $h - ($v / $max) * ($h - 2) - 1;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $pointsAttr = htmlspecialchars(implode(' ', $points));
    $colorAttr = htmlspecialchars($color);
    return "<svg class=\"sparkline\" viewBox=\"0 0 $w $h\" preserveAspectRatio=\"none\">"
        . "<polyline points=\"$pointsAttr\" fill=\"none\" stroke=\"$colorAttr\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" /></svg>";
}

// Ring gauge for the one genuine percentage in this report (a department's
// share of total traffic) — replaces the earlier horizontal meter bar at the
// user's request to match a SaaS-dashboard reference; track and fill are two
// steps of the same brand-blue ramp, same "one sequential hue" rule the bar
// version followed.
function render_dashboard_ring(float $pct, string $color): string
{
    $pct = max(0, min(100, $pct));
    $r = 30;
    $stroke = 8;
    $size = ($r + $stroke) * 2;
    $c = $size / 2;
    $circumference = 2 * M_PI * $r;
    $offset = $circumference * (1 - $pct / 100);
    $colorAttr = htmlspecialchars($color);
    return "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 $size $size\">"
        . "<circle cx=\"$c\" cy=\"$c\" r=\"$r\" fill=\"none\" stroke=\"#dbeafe\" stroke-width=\"$stroke\" />"
        . "<circle cx=\"$c\" cy=\"$c\" r=\"$r\" fill=\"none\" stroke=\"$colorAttr\" stroke-width=\"$stroke\" "
        . 'stroke-dasharray="' . round($circumference, 2) . '" stroke-dashoffset="' . round($offset, 2) . '" '
        . "stroke-linecap=\"round\" transform=\"rotate(-90 $c $c)\" />"
        . '</svg>';
}

// Average minutes-per-visit within [startDate, endDate] — pairs each 'in' log
// with the next 'out' log for the same user (checkin_handlers.php's toggle
// guarantees logs strictly alternate in/out per user, so simple adjacency
// pairing is correct). An 'in' with no matching 'out' in range (still checked
// in, or checked out after the range) is dropped rather than guessed at.
// Self-contained here (not in aggregate.php) since no other report needs it.
function aggregate_avg_session_minutes(PDO $conn, string $startDate, string $endDate, array $filters = []): ?float
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT c.user_id, c.type, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         ORDER BY c.user_id, c.log_id"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $openIn = [];
    $minutes = [];
    foreach ($stmt->fetchAll() as $row) {
        $uid = $row['user_id'];
        if ($row['type'] === 'in') {
            $openIn[$uid] = $row['timestamp'];
        } elseif ($row['type'] === 'out' && isset($openIn[$uid])) {
            $diff = (strtotime($row['timestamp']) - strtotime($openIn[$uid])) / 60;
            if ($diff > 0) {
                $minutes[] = $diff;
            }
            unset($openIn[$uid]);
        }
    }

    return $minutes ? array_sum($minutes) / count($minutes) : null;
}

// "1 ชม. 45 นาที" / "45 นาที" (< 1 hour) / "-" (no data) — matches the report's
// all-Thai number formatting elsewhere instead of a decimal-hours figure.
function format_minutes_thai(?float $minutes): string
{
    if ($minutes === null) {
        return '-';
    }
    $total = (int) round($minutes);
    $h = intdiv($total, 60);
    $m = $total % 60;
    if ($h === 0) {
        return "{$m} นาที";
    }
    return "{$h} ชม. {$m} นาที";
}

// Male/female/unspecified split among unique visitors (not the whole roster)
// in [startDate, endDate] — "unspecified" covers roster-imported students,
// since import_students.php doesn't collect gender (only self-signup does).
// Self-contained here (not aggregate.php) since no other report needs it.
function aggregate_gender_breakdown(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.gender, COUNT(DISTINCT s.student_id) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.gender"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $counts = ['male' => 0, 'female' => 0, 'unknown' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['gender'] ?: 'unknown'] = (int) $row['cnt'];
    }
    $total = array_sum($counts);

    $pct = fn(int $n) => $total ? round($n / $total * 100, 1) : 0;
    return [
        'male' => $counts['male'], 'male_pct' => $pct($counts['male']),
        'female' => $counts['female'], 'female_pct' => $pct($counts['female']),
        'unknown' => $counts['unknown'], 'unknown_pct' => $pct($counts['unknown']),
        'total' => $total,
    ];
}

// Signed delta badge (↑/↓ + %), colored by direction — "more library usage" is
// the good direction here, matching the up=green/down=amber pairing monthly.php
// and executive.php already use.
// $compact drops the "เทียบเดือนก่อน" suffix — the narrow stat tiles don't
// have room for it (it was wrapping one word per line; the hero tile is wide
// enough to keep it in full).
function render_dashboard_delta(?float $delta, bool $compact = false): string
{
    if ($delta === null) {
        return '';
    }
    $cls = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
    $arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '—');
    $suffix = $compact ? '' : ' เทียบเดือนก่อน';
    return '<span class="delta ' . $cls . '" title="เทียบกับเดือนก่อนหน้า">' . $arrow . ' ' . abs($delta) . '%' . $suffix . '</span>';
}

function handle_report_dashboard(): void
{
    require_login();
    require_admin();

    // mPDF (the PDF export path — see render_report_pdf() in layout.php)
    // doesn't reliably honor display:none on an inline <svg>, unlike a
    // block-level div wrapping one — CSS alone couldn't suppress these
    // sparklines in the exported PDF, so skip generating them there instead.
    $isPdfExport = ($_GET['format'] ?? '') === 'pdf';

    $conn = get_db_connection();

    $month = $_GET['month'] ?? date('Y-m');
    $startDateParam = trim((string) ($_GET['start_date'] ?? ''));
    $endDateParam = trim((string) ($_GET['end_date'] ?? ''));
    $useCustomRange = $startDateParam !== '' && $endDateParam !== '';

    $filters = [
        'department' => trim((string) ($_GET['department'] ?? '')),
        'level' => trim((string) ($_GET['level'] ?? '')),
        'semester' => trim((string) ($_GET['semester'] ?? '')),
        'academic_year' => trim((string) ($_GET['academic_year'] ?? '')),
    ];

    if ($useCustomRange) {
        $startDate = $startDateParam;
        $endDate = $endDateParam;
        $periodLabel = "ช่วงวันที่ $startDate ถึง $endDate";
    } else {
        [$startDate, $endDate] = month_bounds($month);
        $periodLabel = thai_month_label($month);
    }

    // "สัปดาห์นี้" quick-filter chip: Monday..today of the current ISO week —
    // just a pre-computed start_date/end_date link, so it reuses the exact
    // same custom-range code path as manually typed dates (no new branch).
    $todayDate = new DateTime();
    $thisWeekStart = (clone $todayDate)->modify('monday this week')->format('Y-m-d');
    $thisWeekEnd = $todayDate->format('Y-m-d');
    $isThisWeek = $useCustomRange && $startDateParam === $thisWeekStart && $endDateParam === $thisWeekEnd;
    $isThisMonth = !$useCustomRange && $month === date('Y-m');

    // Both quick-filter chips keep whatever department/level/semester/academic_year
    // is already selected — only the timeframe changes.
    $chipCommonParams = array_filter([
        'department' => $filters['department'] ?: null,
        'level' => $filters['level'] ?: null,
        'semester' => $filters['semester'] ?: null,
        'academic_year' => $filters['academic_year'] ?: null,
    ]);
    $thisWeekHref = '/admin/reports/print/dashboard?' . http_build_query(array_merge($chipCommonParams, [
        'start_date' => $thisWeekStart, 'end_date' => $thisWeekEnd,
    ]));
    $thisMonthHref = '/admin/reports/print/dashboard?' . http_build_query(array_merge($chipCommonParams, [
        'month' => date('Y-m'),
    ]));

    $orientation = ($_GET['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

    $agg = aggregate_checkin_period($conn, $startDate, $endDate, $filters);
    $avgSessionMinutes = aggregate_avg_session_minutes($conn, $startDate, $endDate, $filters);
    $genderBreakdown = aggregate_gender_breakdown($conn, $startDate, $endDate, $filters);
    $dailyTrend = aggregate_daily_trend($conn, $startDate, $endDate, $filters);
    $hourly = aggregate_hourly($conn, $startDate, $endDate, $filters);
    $deptBreakdown = aggregate_department_breakdown($conn, $startDate, $endDate, $filters);
    $topDepts = array_slice($deptBreakdown, 0, 8);

    // Month-over-month context for the hero + first stat tile — only when
    // scoped to a calendar month (an arbitrary custom date range has no
    // unambiguous "previous period"). Reuses the exact pct_delta()/
    // previous_month() pattern executive.php and monthly.php already use.
    $totalDelta = null;
    $uniqueDelta = null;
    if (!$useCustomRange) {
        [$prevStart, $prevEnd] = month_bounds(previous_month($month));
        $prevAgg = aggregate_checkin_period($conn, $prevStart, $prevEnd, $filters);
        $totalDelta = pct_delta($agg['total_events'], $prevAgg['total_events']);
        $uniqueDelta = pct_delta($agg['unique_students'], $prevAgg['unique_students']);
    }

    $busiestDay = null;
    foreach ($dailyTrend as $d) {
        if ($d['count'] > 0 && ($busiestDay === null || $d['count'] > $busiestDay['count'])) {
            $busiestDay = ['day' => date('d/m', strtotime($d['date'])), 'count' => $d['count']];
        }
    }

    $summarySentence = $agg['total_events']
        ? build_summary_sentence($periodLabel, $agg, $busiestDay, $topDepts, $hourly['peak_hour'])
        : null;

    $departments = distinct_student_values($conn, 'department');
    $levels = distinct_student_values($conn, 'level');
    $semesters = distinct_student_values($conn, 'semester');
    $academicYears = distinct_student_values($conn, 'academic_year');

    ob_start();
    ?>
<style>
  /* Tighter than layout.php's default 12mm — this report is meant to read as
     one dense, single-page dashboard (Power BI style), not a multi-page
     document, so print margin is trimmed to reclaim usable width/height. */
  @page { size: A4 <?= $orientation ?>; margin: 8mm; }

  /* Slim single-line title bar instead of the shared layout's tall gradient
     hero — overridden here (not in layout.php, which every other report
     still uses as-is) via cascade, since $extraStyle loads after the base
     <style> block. */
  header.report-head {
    background: var(--primary, #1e3a8a) !important;
    padding: 11px 20px !important;
    display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
  }
  header.report-head h1 { font-size: 12.5px !important; margin: 0 !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: .05em; }
  header.report-head h2 { font-size: 12.5px !important; opacity: .85 !important; font-weight: 400 !important; }

  /* A visibly separate control strip, not another content card — so the
     filters read as "controls" and everything below as "the report". */
  .filter-bar {
    background: var(--surface, #f8fafc) !important;
    border: none !important; border-bottom: 2px solid var(--outline-variant, #e2e8f0) !important;
    border-radius: 0 !important; padding: 10px 2px 16px !important; margin: 0 0 24px !important;
  }

  /* Quick timeframe presets — same chip visual language as the print-settings
     drawer's orientation chips, just above the detailed filter form instead
     of inside a drawer. */
  .quick-filter-chips { display: flex; align-items: center; gap: 8px; margin: 4px 2px 14px; flex-wrap: wrap; }
  .quick-filter-chips .chip {
    font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px;
    border: 1px solid var(--outline-variant, #e2e8f0); color: var(--on-surface-variant, #475569);
    text-decoration: none;
  }
  .quick-filter-chips .chip.active { background: var(--primary, #1e3a8a); color: #fff; border-color: var(--primary, #1e3a8a); }
  .quick-filter-chips .chip-hint { font-size: 11px; color: #898781; }
  @media print {
    .quick-filter-chips { display: none !important; }
  }

  /* ---- Single dense dashboard, no tabs ----
     Power-BI-style: every widget visible on one screen/one printed page at
     once, nothing behind a click. A KPI strip of equal-height cards (hero
     included — set apart by a brand-color left border, not a separate
     giant block) replaces the earlier "one huge hero + tab navigation"
     layout, which read as multiple pages stitched together instead of one
     dashboard. */
  .delta { font-weight: 700; white-space: nowrap; }
  .delta.up { color: #059669; }
  .delta.down { color: #d97706; }
  .delta.flat { color: #888; }

  .kpi-strip {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(172px, 1fr)); gap: 10px; margin: 0 0 14px;
  }
  .kpi-card {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #e2e8f0);
    border-radius: 12px; padding: 12px 14px; box-shadow: 0 2px 8px rgba(0,0,0,.03);
    min-height: 78px;
  }
  .kpi-card.hero { border-left: 4px solid var(--primary, #1e3a8a); }
  /* Label and sparkline share a flex row (not an absolutely-positioned
     overlay) so the label can wrap to a second line instead of being cut
     off with an ellipsis — this went to executives truncated as
     "เวลาเฉลี่ยที่..." before, which read as broken, not just tight. */
  .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; margin-bottom: 5px; }
  .kpi-card .sparkline { flex-shrink: 0; width: 40px; height: 16px; margin-top: 1px; }
  .kpi-label { font-size: 10.5px; color: #666; font-weight: 600; line-height: 1.3; }
  .kpi-value { font-size: 19px; font-weight: 700; color: #0f172a; line-height: 1.15; }
  .kpi-card.hero .kpi-value { font-size: 26px; }
  .kpi-sub { display: block; margin-top: 4px; font-size: 10.5px; color: #666; }

  /* The one genuine ratio in this report (a department's share of total
     traffic) gets the one ring gauge, sized to match the other KPI cards'
     height instead of standing out as an oversized block. */
  .kpi-card.ring-card { display: flex; align-items: center; gap: 10px; }
  .meter-ring-wrap { position: relative; flex-shrink: 0; width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; }
  .meter-ring-wrap svg { width: 52px; height: 52px; }
  .meter-ring-value { position: absolute; font-size: 11px; font-weight: 700; color: #0f172a; }
  .meter-info { min-width: 0; }
  .meter-info .meter-dept-name { font-size: 13px; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meter-info .meter-dept-count { font-size: 10.5px; color: #666; margin-top: 1px; }

  /* Department ranking (Sales-ranking-style reference): a circled position
     number instead of a raw table row — rank 1 gets the solid brand fill,
     the rest a light tint, same "one hue, varying weight" rule as the bars.
     Rows kept short (not just at print size) since this list can run to a
     dozen-plus departments and still needs to share the page with everything
     else above it. */
  .rank-list { display: flex; flex-direction: column; gap: 6px; }
  .rank-row { display: flex; align-items: center; gap: 10px; }
  .rank-badge {
    flex-shrink: 0; width: 20px; height: 20px; border-radius: 999px;
    background: #dbeafe; color: var(--primary, #1e3a8a);
    font-size: 10px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
  }
  .rank-row:first-child .rank-badge { background: var(--primary, #1e3a8a); color: #fff; }
  .rank-name {
    flex: 0 0 140px; width: 140px; font-size: 12px; font-weight: 700; color: #0f172a;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .rank-track { flex: 1; height: 8px; background: #e1e0d9; border-radius: 999px; overflow: hidden; }
  .rank-fill { display: block; height: 100%; background: var(--secondary, #2563eb); border-radius: 999px; }
  .rank-count { flex-shrink: 0; width: 120px; text-align: right; font-size: 11px; color: #666; }
  @media (max-width: 640px) {
    .rank-name { flex-basis: 88px; width: 88px; }
    .rank-count { width: 88px; }
  }

  /* Compact 2-across chart strip (daily / hourly) instead of one big
     2-column pair per section — matches the "everything visible at once"
     Power-BI-style brief and leaves enough vertical room for the full
     department list below to still land on the same printed page. The
     earlier "weekly" panel duplicated the semester report's own week-by-week
     template, so it was dropped in favor of the gender breakdown instead. */
  .mini-panel-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 0 0 14px;
  }
  @media (max-width: 900px) {
    .mini-panel-row { grid-template-columns: 1fr; }
  }
  .panel, .mini-panel {
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 10px;
    padding: 12px 14px;
    background: var(--surface-white, #fff);
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .panel h3, .mini-panel h3 {
    font-size: 11px;
    margin: 0 0 8px;
    color: #333;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 80px;
    border-bottom: 1px solid #e1e0d9;
    padding-top: 6px;
  }
  .trend-chart.hourly-chart { gap: 1px; }
  .trend-chart .bar-wrap {
    flex: 1;
    display: flex;
    align-items: flex-end;
    height: 100%;
  }
  .trend-chart .bar {
    width: 100%;
    background: var(--secondary, #2563eb);
    border-radius: 4px 4px 0 0;
    min-height: 1px;
  }
  .trend-labels {
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: #898781;
    margin-top: 4px;
  }

  /* Gender is one of the few breakdowns in this report where the categories
     are genuinely identity-based (exactly male/female/unspecified, not an
     open-ended nominal list like departments) — a real, if small, exception
     to the "one hue" bar rule used everywhere else in this file. */
  .gender-list { display: flex; flex-direction: column; gap: 8px; margin-top: 2px; }
  .gender-row { display: flex; align-items: center; gap: 8px; }
  .gender-row .g-label { flex: 0 0 52px; font-size: 11px; font-weight: 700; color: #0f172a; }
  .gender-row .g-track { flex: 1; height: 13px; background: #e1e0d9; border-radius: 999px; overflow: hidden; }
  .gender-row .g-fill { height: 100%; border-radius: 999px; }
  .gender-row.male .g-fill { background: #2563eb; }
  .gender-row.female .g-fill { background: #db2777; }
  .gender-row.unknown .g-fill { background: #94a3b8; }
  .gender-row .g-count { flex: 0 0 92px; text-align: right; font-size: 10.5px; color: #666; }

  .empty-note {
    color: #898781;
    font-size: 12px;
    font-style: italic;
  }
  /* Tightened further so the whole single-page dashboard (KPI strip + 3
     mini-charts + full department list + summary) reliably fits one A4
     sheet instead of spilling a second, mostly-empty one. Cards are allowed
     to break across pages only as a last resort (break-inside: avoid on the
     small ones; the department list is intentionally left free to flow —
     forcing a dozen-plus rows to never split would just push the whole
     block onto page 2 instead of filling out page 1). */
  @media print {
    .kpi-strip { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important; gap: 6px; margin-bottom: 8px; }
    .kpi-card { padding: 7px 9px; min-height: 56px; }
    .kpi-card.hero .kpi-value { font-size: 19px; }
    .kpi-value { font-size: 14px; }
    .kpi-head { margin-bottom: 2px; }
    .kpi-label { font-size: 7.5px; }
    .kpi-sub { font-size: 8px; }
    .kpi-card .sparkline { width: 30px !important; height: 12px !important; }
    .meter-ring-wrap, .meter-ring-wrap svg { width: 34px; height: 34px; }
    .meter-ring-value { font-size: 8px; }
    .meter-info .meter-dept-name { font-size: 10px; }
    .meter-info .meter-dept-count { font-size: 8px; }
    .mini-panel-row { gap: 6px; margin-bottom: 8px; }
    .panel, .mini-panel { padding: 6px 8px; box-shadow: none; }
    .panel h3, .mini-panel h3 { font-size: 8.5px; margin-bottom: 4px; }
    .trend-chart { height: 40px; }
    .trend-labels { font-size: 7px; }
    .gender-list { gap: 4px; }
    .gender-row .g-label { font-size: 8px; flex-basis: 40px; }
    .gender-row .g-track { height: 9px; }
    .gender-row .g-count { font-size: 7.5px; flex-basis: 70px; }
    .rank-list { gap: 3px; }
    .rank-badge { width: 15px; height: 15px; font-size: 8px; }
    .rank-name { font-size: 8px; flex-basis: 90px; width: 90px; }
    .rank-track { height: 7px; }
    .rank-count { font-size: 7px; width: 80px; }
    .story-box { padding: 6px 10px; margin-bottom: 6px; }
    .story-box p { font-size: 9px; line-height: 1.4; }
    .kpi-card, .mini-panel, .panel, .story-box { break-inside: avoid; }
  }
</style>
<?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<div class="quick-filter-chips">
  <a class="chip <?= $isThisWeek ? 'active' : '' ?>" href="<?= htmlspecialchars($thisWeekHref) ?>">สัปดาห์นี้</a>
  <a class="chip <?= $isThisMonth ? 'active' : '' ?>" href="<?= htmlspecialchars($thisMonthHref) ?>">เดือนนี้</a>
  <span class="chip-hint">หรือกำหนดช่วงวันที่เองด้านล่าง</span>
</div>

<form class="filter-bar" method="get">
  <input type="hidden" name="orientation" value="<?= htmlspecialchars($orientation) ?>">
  <div class="field">
    <label for="month">เดือน</label>
    <input type="month" id="month" name="month" value="<?= htmlspecialchars($useCustomRange ? '' : $month) ?>">
  </div>
  <div class="field">
    <label for="start_date">จากวันที่ (ถ้าใช้ช่วงวันที่)</label>
    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDateParam) ?>">
  </div>
  <div class="field">
    <label for="end_date">ถึงวันที่</label>
    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDateParam) ?>">
  </div>
  <div class="field">
    <label for="academic_year">ปีการศึกษา</label>
    <select id="academic_year" name="academic_year">
      <option value="">ทั้งหมด</option>
      <?php foreach ($academicYears as $y): ?>
      <option value="<?= htmlspecialchars($y) ?>" <?= $filters['academic_year'] === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="semester">ภาคเรียน</label>
    <select id="semester" name="semester">
      <option value="">ทั้งหมด</option>
      <?php foreach ($semesters as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $filters['semester'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="department">แผนกวิชา</label>
    <select id="department" name="department">
      <option value="">ทั้งหมด</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="level">ระดับชั้น</label>
    <select id="level" name="level">
      <option value="">ทั้งหมด</option>
      <?php foreach ($levels as $l): ?>
      <option value="<?= htmlspecialchars($l) ?>" <?= $filters['level'] === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit">กรองข้อมูล</button>
  <a class="reset-link" href="/admin/reports/print/dashboard"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>
<?php if ($filters['semester'] || $filters['academic_year']): ?>
<p class="filter-note">* ภาคเรียน/ปีการศึกษาอ้างอิงจากข้อมูลการลงทะเบียนปัจจุบันของนักศึกษาแต่ละคน ไม่ใช่ช่วงวันที่ปฏิทินของภาคเรียนนั้น ๆ</p>
<?php endif; ?>

<?php if ($agg['total_events'] === 0): ?>
<div class="empty" data-print-section="สถานะไม่มีข้อมูล">
  ยังไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้
  <br>
  <a class="empty-cta" href="/admin/reports/print/dashboard"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนช่วงเวลา</a>
</div>
<?php else: ?>

<div class="kpi-strip" data-print-section="KPI สรุป">
  <div class="kpi-card hero" title="จำนวนนักศึกษาที่มาเข้าห้องสมุดในช่วงเวลานี้ นับคนซ้ำแค่ครั้งเดียวแม้จะเข้ามาหลายรอบ">
    <div class="kpi-head"><span class="kpi-label">นักศึกษาที่เข้าใช้บริการ (ไม่ซ้ำคน)</span></div>
    <div class="kpi-value"><?= number_format($agg['unique_students']) ?></div>
    <?php if ($uniqueDelta !== null): ?><span class="kpi-sub"><?= render_dashboard_delta($uniqueDelta, true) ?></span><?php endif; ?>
  </div>
  <div class="kpi-card" title="จำนวนครั้งการเช็คอิน+เช็คเอาต์รวมกันทั้งหมดในช่วงเวลานี้ (คนเดียวเข้า-ออกหลายครั้งนับหลายครั้ง)">
    <div class="kpi-head">
      <span class="kpi-label">จำนวนรายการทั้งหมด</span>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb', $isPdfExport) ?>
    </div>
    <div class="kpi-value"><?= number_format($agg['total_events']) ?></div>
    <?php if ($totalDelta !== null): ?><span class="kpi-sub"><?= render_dashboard_delta($totalDelta, true) ?></span><?php endif; ?>
  </div>
  <div class="kpi-card" title="จำนวนรายการเช็คอิน+เช็คเอาต์เฉลี่ยต่อวัน นับเฉพาะวันที่มีคนมาใช้จริง">
    <div class="kpi-head">
      <span class="kpi-label">เฉลี่ยต่อวัน</span>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb', $isPdfExport) ?>
    </div>
    <div class="kpi-value"><?= $agg['avg_daily'] ?> รายการ</div>
  </div>
  <div class="kpi-card" title="ระยะเวลาเฉลี่ยที่นักศึกษาอยู่ในห้องสมุดต่อการเข้าใช้ 1 ครั้ง คำนวณจากเวลาเช็คอินถึงเช็คเอาต์จริง">
    <div class="kpi-head"><span class="kpi-label">เวลาเฉลี่ยที่อยู่ในห้องสมุด</span></div>
    <div class="kpi-value"><?= format_minutes_thai($avgSessionMinutes) ?></div>
  </div>
  <div class="kpi-card" title="วันที่มีจำนวนการเช็คอิน+เช็คเอาต์รวมสูงที่สุดในช่วงเวลาที่เลือก">
    <div class="kpi-head">
      <span class="kpi-label">วันที่มีคนใช้มากที่สุด</span>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb', $isPdfExport) ?>
    </div>
    <div class="kpi-value"><?= $busiestDay ? htmlspecialchars($busiestDay['day']) : '-' ?></div>
  </div>
  <div class="kpi-card" title="ชั่วโมงของวันที่มีคนเช็คอิน+เช็คเอาต์รวมกันมากที่สุด (รวมทุกวันในช่วงเวลาที่เลือก)">
    <div class="kpi-head">
      <span class="kpi-label">ช่วงเวลาที่มีคนใช้มากที่สุด</span>
      <?= render_dashboard_sparkline(array_column($hourly['hours'], 'count'), '#2563eb', $isPdfExport) ?>
    </div>
    <div class="kpi-value"><?= $hourly['peak_hour'] ? sprintf('%02d:00 น.', $hourly['peak_hour']['hour']) : '-' ?></div>
  </div>
  <div class="kpi-card ring-card" title="แผนกวิชาที่มีจำนวนการเข้าใช้ห้องสมุดมากที่สุดในช่วงเวลานี้ และสัดส่วน % เทียบกับทุกแผนกรวมกัน">
    <div class="meter-ring-wrap">
      <?= render_dashboard_ring($topDepts ? $topDepts[0]['pct'] : 0, '#2563eb') ?>
      <span class="meter-ring-value"><?= $topDepts ? $topDepts[0]['pct'] : 0 ?>%</span>
    </div>
    <div class="meter-info">
      <div class="kpi-label">แผนกยอดนิยม</div>
      <div class="meter-dept-name"><?= $topDepts ? htmlspecialchars($topDepts[0]['name']) : '-' ?></div>
      <div class="meter-dept-count"><?= $topDepts ? number_format($topDepts[0]['count']) . ' รายการ' : '-' ?></div>
    </div>
  </div>
</div>

<div class="mini-panel-row" data-print-section="กราฟแนวโน้ม">
  <div class="mini-panel">
    <h3>แนวโน้มรายวัน</h3>
    <?php if ($agg['total_events']): ?>
    <div class="trend-chart">
      <?php foreach ($dailyTrend as $d): ?>
      <div class="bar-wrap" title="<?= htmlspecialchars(date('d/m', strtotime($d['date']))) ?>: <?= $d['count'] ?> รายการ" data-label="<?= htmlspecialchars(date('d/m', strtotime($d['date']))) ?>" data-count="<?= $d['count'] ?>">
        <div class="bar" style="height: <?= $d['pct'] > 0 ? $d['pct'] : 2 ?>%;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="trend-labels">
      <span><?= htmlspecialchars(date('d/m', strtotime($dailyTrend[0]['date']))) ?></span>
      <span><?= htmlspecialchars(date('d/m', strtotime($dailyTrend[count($dailyTrend) - 1]['date']))) ?></span>
    </div>
    <p class="trend-detail-text">แตะหรือคลิกแท่งกราฟเพื่อดูจำนวนและวันที่</p>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูล</p>
    <?php endif; ?>
  </div>

  <div class="mini-panel">
    <h3>รายชั่วโมง</h3>
    <?php if ($agg['total_events']): ?>
    <?php $maxHour = max(1, max(array_column($hourly['hours'], 'count'))); ?>
    <div class="trend-chart hourly-chart">
      <?php foreach ($hourly['hours'] as $h): ?>
      <div class="bar-wrap" title="<?= sprintf('%02d:00', $h['hour']) ?> น.: <?= $h['count'] ?> รายการ" data-label="<?= sprintf('%02d:00', $h['hour']) ?> น." data-count="<?= $h['count'] ?>">
        <div class="bar" style="height: <?= $h['count'] ? max(2, round($h['count'] / $maxHour * 100)) : 1 ?>%;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="trend-labels"><span>00:00</span><span>23:00</span></div>
    <p class="trend-detail-text">แตะหรือคลิกแท่งกราฟเพื่อดูจำนวนและช่วงเวลา</p>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูล</p>
    <?php endif; ?>
  </div>

  <div class="mini-panel" title="สัดส่วนเพศของนักศึกษาไม่ซ้ำคนที่เข้าใช้บริการในช่วงเวลานี้ — 'ไม่ระบุ' คือบัญชีที่นำเข้าจากรายชื่อ ยังไม่เคยกรอกเพศเอง">
    <h3>สัดส่วนเพศผู้เข้าใช้</h3>
    <?php if ($genderBreakdown['total'] > 0): ?>
    <div class="gender-list">
      <div class="gender-row male">
        <span class="g-label">ชาย</span>
        <span class="g-track"><span class="g-fill" style="width: <?= $genderBreakdown['male_pct'] ?>%;"></span></span>
        <span class="g-count"><?= $genderBreakdown['male'] ?> คน · <?= $genderBreakdown['male_pct'] ?>%</span>
      </div>
      <div class="gender-row female">
        <span class="g-label">หญิง</span>
        <span class="g-track"><span class="g-fill" style="width: <?= $genderBreakdown['female_pct'] ?>%;"></span></span>
        <span class="g-count"><?= $genderBreakdown['female'] ?> คน · <?= $genderBreakdown['female_pct'] ?>%</span>
      </div>
      <?php if ($genderBreakdown['unknown'] > 0): ?>
      <div class="gender-row unknown">
        <span class="g-label">ไม่ระบุ</span>
        <span class="g-track"><span class="g-fill" style="width: <?= $genderBreakdown['unknown_pct'] ?>%;"></span></span>
        <span class="g-count"><?= $genderBreakdown['unknown'] ?> คน · <?= $genderBreakdown['unknown_pct'] ?>%</span>
      </div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูล</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" data-print-section="แผนกวิชาทั้งหมด" style="margin-bottom:6px;">
  <h3 style="margin:0 0 8px;">แผนกวิชาทั้งหมด (เรียงจากใช้งานมากไปน้อย)</h3>
  <?php if ($deptBreakdown): ?>
  <div class="rank-list">
    <?php foreach ($deptBreakdown as $i => $dept): ?>
    <div class="rank-row">
      <span class="rank-badge"><?= $i + 1 ?></span>
      <span class="rank-name"><?= htmlspecialchars($dept['name']) ?></span>
      <span class="rank-track"><span class="rank-fill" style="width: <?= max(8, $deptBreakdown[0]['count'] ? round($dept['count'] / $deptBreakdown[0]['count'] * 100) : 0) ?>%;"></span></span>
      <span class="rank-count"><?= $dept['count'] ?> รายการ · <?= $dept['pct'] ?>%</span>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้</p>
  <?php endif; ?>
</div>

<?php if ($summarySentence): ?>
<div class="story-box" data-print-section="สรุปสำหรับผู้บริหาร">
  <p><?= $summarySentence ?></p>
</div>
<?php endif; ?>

<?php // Self-hosted rather than loaded from cdnjs: a CDN script with no SRI hash
      // is an open door — whoever controls that host (or the DNS answer for it)
      // gets to run code inside an authenticated admin's report page. Pinned
      // local copy of html2canvas 1.4.1 (MIT), sha256
      // e87e550794322e574a1fda0c1549a3c70dae5a93d9113417a429016838eab8cb ?>
<script src="/assets/js/vendor/html2canvas.min.js"></script>
<script>
(function () {
  // This report's settings drawer doubles as "customize dashboard widgets"
  // (its checkboxes — from layout.php's shared buildPrintSectionToggles(),
  // unmodified — already choose which widgets appear in print output) —
  // relabel just here via JS rather than editing layout.php's shared button/
  // heading text, which every other (non-widget) report also uses.
  var settingsBtn = document.querySelector('.settings-toggle-btn');
  if (settingsBtn) {
    for (var i = settingsBtn.childNodes.length - 1; i >= 0; i--) {
      var node = settingsBtn.childNodes[i];
      if (node.nodeType === Node.TEXT_NODE && node.textContent.trim()) {
        node.textContent = ' ปรับแต่งวิดเจ็ต / ตั้งค่าการพิมพ์';
        break;
      }
    }
  }
  var settingsHeading = document.querySelector('.settings-panel h4');
  if (settingsHeading) settingsHeading.textContent = 'ปรับแต่งวิดเจ็ตแดชบอร์ด';

  // Separate "save as image" from "print" — the shared toolbar only ever had
  // one button that opens the browser print dialog (useful for PDF, but not
  // what someone reaching for "save image" expects). Injected here rather
  // than into layout.php's shared toolbar markup since this is a
  // dashboard-specific addition, not something every table-style report needs.
  var printBtn = document.querySelector('.toolbar button');
  if (printBtn) {
    var imageBtn = document.createElement('button');
    imageBtn.type = 'button';
    imageBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">image</span> บันทึกเป็นรูปภาพ';
    imageBtn.addEventListener('click', function () {
      imageBtn.disabled = true;
      var originalHtml = imageBtn.innerHTML;
      imageBtn.innerHTML = 'กำลังสร้างรูปภาพ…';
      html2canvas(document.body, {
        backgroundColor: '#f8fafc',
        scale: 2,
        ignoreElements: function (el) {
          return el.classList.contains('toolbar') || el.id === 'print-settings-panel';
        },
      }).then(function (canvas) {
        var link = document.createElement('a');
        link.download = 'รายงานแดชบอร์ด-<?= htmlspecialchars(str_replace(' ', '-', $periodLabel)) ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      }).finally(function () {
        imageBtn.disabled = false;
        imageBtn.innerHTML = originalHtml;
      });
    });
    printBtn.insertAdjacentElement('afterend', imageBtn);
  }
})();
</script>
<?php endif; ?>

    <?php
    $content = ob_get_clean();

    // Department breakdown is NOT duplicated as a $pdfCharts image here —
    // the "แผนกวิชาทั้งหมด" .rank-list further down in $content already
    // covers it (all departments, not just the top 8 a bar-chart image
    // would show), and now has real PDF styling (see layout.php's
    // $pdfStyle) instead of rendering as unstyled block text. Two
    // representations of the same numbers was what pushed this report to a
    // second, mostly-redundant page.
    $pdfCharts = [];
    if ($dailyTrend) {
        $pdfCharts[] = [
            'title' => 'แนวโน้มการเช็คชื่อรายวัน',
            'orientation' => 'vertical',
            'height' => 105,
            'labels' => array_map(fn($row) => (string) ((int) substr($row['date'], -2)), $dailyTrend),
            'values' => array_column($dailyTrend, 'count'),
        ];
    }

    render_report_layout('รายงานแบบแดชบอร์ด', "สรุปภาพรวมการเช็คชื่อ — $periodLabel", $content, $extraStyle, [], $pdfCharts);
}
