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
function render_dashboard_sparkline(array $values, string $color): string
{
    if (!$values) {
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

// Signed delta badge (▲/▼ + %), colored by direction — "more library usage" is
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
    $arrow = $delta > 0 ? '▲' : ($delta < 0 ? '▼' : '—');
    $suffix = $compact ? '' : ' เทียบเดือนก่อน';
    return '<span class="delta ' . $cls . '" title="เทียบกับเดือนก่อนหน้า">' . $arrow . ' ' . abs($delta) . '%' . $suffix . '</span>';
}

function handle_report_dashboard(): void
{
    require_login();
    require_admin();

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

    $orientation = ($_GET['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';

    $agg = aggregate_checkin_period($conn, $startDate, $endDate, $filters);
    $dailyTrend = aggregate_daily_trend($conn, $startDate, $endDate, $filters);
    $hourly = aggregate_hourly($conn, $startDate, $endDate, $filters);
    $weekly = aggregate_weekly($conn, $startDate, $endDate, $filters);
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

    // Carries every active filter (orientation excluded — the print-settings
    // drawer's own links handle that) onto the department drill-down link.
    $filterQueryBase = array_filter([
        'month' => $useCustomRange ? null : $month,
        'start_date' => $useCustomRange ? $startDateParam : null,
        'end_date' => $useCustomRange ? $endDateParam : null,
        'department' => $filters['department'] ?: null,
        'level' => $filters['level'] ?: null,
        'semester' => $filters['semester'] ?: null,
        'academic_year' => $filters['academic_year'] ?: null,
    ]);

    ob_start();
    ?>
<style>
  <?php if ($orientation === 'landscape'): ?>
  .panel-grid { grid-template-columns: 1fr 1fr; }
  .hero-row { grid-template-columns: auto 1fr; }
  <?php endif; ?>

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

  /* ---- Hero row: one dominant number + a lane of smaller stat tiles ----
     Six same-size KPI cards competing for attention was the mistake; a
     dashboard leads with exactly one number (dataviz skill, "figures"). */
  .hero-row {
    display: grid; grid-template-columns: 1fr; gap: 16px; margin: 0 0 18px;
  }
  .hero-tile {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #e2e8f0);
    border-radius: 14px; padding: 22px 26px; box-shadow: 0 2px 10px rgba(0,0,0,.03);
    display: flex; flex-direction: column; justify-content: center;
  }
  .hero-tile .hero-label { font-size: 12px; color: #666; margin-bottom: 6px; font-weight: 600; }
  .hero-tile .hero-value { font-size: 46px; font-weight: 700; color: #0f172a; line-height: 1; }
  .hero-tile .hero-sub { margin-top: 8px; font-size: 12.5px; color: #666; }
  .delta { font-weight: 700; white-space: nowrap; }
  .delta.up { color: #059669; }
  .delta.down { color: #d97706; }
  .delta.flat { color: #888; }

  .stat-tiles {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 14px;
  }
  .stat-tile {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #e2e8f0);
    border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(0,0,0,.03);
    display: flex; align-items: center; justify-content: space-between; gap: 10px; min-width: 0;
  }
  .stat-tile > div:first-child { min-width: 0; }
  .stat-tile-label, .stat-tile-value { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .stat-tile-label { font-size: 11px; color: #666; margin-bottom: 5px; font-weight: 600; }
  .stat-tile-value { font-size: 19px; font-weight: 700; color: #0f172a; }
  .sparkline { flex-shrink: 0; }

  /* The one genuine ratio in this report (a department's share of total
     traffic) gets the one meter — fill and unfilled track are two steps of
     the same brand-blue ramp, per the skill's meter contract, not a fixed
     gray track. */
  .meter-tile {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #e2e8f0);
    border-radius: 14px; padding: 16px 18px; box-shadow: 0 2px 10px rgba(0,0,0,.03);
    grid-column: 1 / -1;
  }
  .meter-tile .meter-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 8px; }
  .meter-tile .meter-label { font-size: 11px; color: #666; font-weight: 600; }
  .meter-tile .meter-value { font-size: 15px; font-weight: 700; color: #0f172a; }
  .meter-track { background: #dbeafe; border-radius: 999px; height: 10px; overflow: hidden; }
  .meter-fill { background: var(--secondary, #2563eb); height: 100%; border-radius: 999px; }

  .panel-grid {
    display: grid;
    grid-template-columns: 1.4fr 1fr;
    gap: 16px;
    align-items: start;
  }
  @media (max-width: 760px) {
    .panel-grid { grid-template-columns: 1fr; }
  }
  .panel {
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 10px;
    padding: 16px 18px;
    background: var(--surface-white, #fff);
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .panel h3 {
    font-size: 12.5px;
    margin: 0 0 14px;
    color: #333;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 120px;
    border-bottom: 1px solid #e1e0d9;
    padding-top: 8px;
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
    font-size: 10px;
    color: #898781;
    margin-top: 4px;
  }

  /* One sequential brand hue for every bar — magnitude across nominal
     categories (departments, weeks) never earns a rainbow; that spends the
     identity channel on information the bar length already shows. */
  .bar-list { display: flex; flex-direction: column; gap: 13px; }
  .bar-list-row .meta { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 5px; }
  .bar-list-row .meta .name { font-weight: 700; color: #0f172a; }
  .bar-list-row .meta .count { color: #898781; }
  .bar-list-row .track { background: #e1e0d9; border-radius: 0 4px 4px 0; height: 20px; overflow: hidden; }
  .bar-list-row .fill {
    height: 100%; border-radius: 0 4px 4px 0; background: var(--secondary, #2563eb);
    display: flex; align-items: center; justify-content: flex-end;
    padding-right: 8px; color: #fff; font-size: 10.5px; font-weight: 700; min-width: 30px;
  }

  .empty-note {
    color: #898781;
    font-size: 12px;
    font-style: italic;
  }
  /* Tightened so a typical month's data reliably fits one A4 page instead
     of spilling a second, mostly-empty sheet. */
  @media print {
    .hero-row { gap: 8px; margin-bottom: 10px; }
    .hero-tile { padding: 12px 16px; }
    .hero-tile .hero-value { font-size: 28px; }
    .hero-tile .hero-label, .hero-tile .hero-sub { font-size: 9px; }
    .stat-tiles { gap: 8px; }
    .stat-tile { padding: 8px 10px; }
    .stat-tile-label { font-size: 8.5px; margin-bottom: 2px; }
    .stat-tile-value { font-size: 14px; }
    .sparkline { width: 44px !important; height: 16px !important; }
    .meter-tile { padding: 8px 10px; }
    .meter-tile .meter-label, .meter-tile .meter-value { font-size: 9.5px; }
    .panel-grid { gap: 8px; grid-template-columns: 1fr 1fr !important; margin-top: 0 !important; }
    .panel { padding: 8px 10px; box-shadow: none; }
    .panel h3 { font-size: 10px; margin-bottom: 6px; }
    .trend-chart { height: 46px; }
    .bar-list { gap: 6px; }
    .bar-list-row .meta { font-size: 9px; margin-bottom: 3px; }
    .bar-list-row .track { height: 14px; }
    .bar-list-row .fill { font-size: 8px; padding-right: 5px; }
    .story-box { padding: 8px 14px; margin-bottom: 8px; }
    .story-box p { font-size: 10.5px; line-height: 1.5; }
    .panel, .hero-tile, .stat-tile, .meter-tile, .story-box { break-inside: avoid; }
  }
</style>
<?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

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

<div class="hero-row" data-print-section="KPI สรุป">
  <div class="hero-tile">
    <div class="hero-label">จำนวนรายการทั้งหมด — <?= htmlspecialchars($periodLabel) ?></div>
    <div class="hero-value"><?= number_format($agg['total_events']) ?></div>
    <div class="hero-sub">
      ครั้งการเช็คอิน/เช็คเอาต์
      <?= $totalDelta !== null ? ' · ' . render_dashboard_delta($totalDelta) : '' ?>
    </div>
  </div>

  <div class="stat-tiles">
    <div class="stat-tile">
      <div>
        <div class="stat-tile-label">นักศึกษาไม่ซ้ำคน</div>
        <div class="stat-tile-value"><?= $agg['unique_students'] ?></div>
        <?php if ($uniqueDelta !== null): ?><div style="margin-top:3px;"><?= render_dashboard_delta($uniqueDelta, true) ?></div><?php endif; ?>
      </div>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb') ?>
    </div>
    <div class="stat-tile">
      <div>
        <div class="stat-tile-label">เฉลี่ยต่อวัน (วันที่มีข้อมูล)</div>
        <div class="stat-tile-value"><?= $agg['avg_daily'] ?> รายการ</div>
      </div>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb') ?>
    </div>
    <div class="stat-tile">
      <div>
        <div class="stat-tile-label">วันที่มีคนใช้มากที่สุด</div>
        <div class="stat-tile-value"><?= $busiestDay ? htmlspecialchars($busiestDay['day']) : '-' ?></div>
      </div>
      <?= render_dashboard_sparkline(array_column($dailyTrend, 'count'), '#2563eb') ?>
    </div>
    <div class="stat-tile">
      <div>
        <div class="stat-tile-label">ช่วงเวลาที่มีคนใช้มากที่สุด</div>
        <div class="stat-tile-value"><?= $hourly['peak_hour'] ? sprintf('%02d:00 น.', $hourly['peak_hour']['hour']) : '-' ?></div>
      </div>
      <?= render_dashboard_sparkline(array_column($hourly['hours'], 'count'), '#2563eb') ?>
    </div>

    <div class="meter-tile">
      <div class="meter-head">
        <span class="meter-label">ส่วนแบ่งแผนกยอดนิยม — <?= $topDepts ? htmlspecialchars($topDepts[0]['name']) : '-' ?></span>
        <span class="meter-value"><?= $topDepts ? $topDepts[0]['pct'] : 0 ?>%</span>
      </div>
      <div class="meter-track"><div class="meter-fill" style="width: <?= $topDepts ? $topDepts[0]['pct'] : 0 ?>%;"></div></div>
    </div>
  </div>
</div>

<?php if ($summarySentence): ?>
<div class="story-box" data-print-section="สรุปสำหรับผู้บริหาร">
  <p><?= $summarySentence ?></p>
</div>
<?php endif; ?>

<div class="panel-grid" data-print-section="กราฟแนวโน้มรายวันและแผนกวิชา">
  <div class="panel">
    <h3>แนวโน้มการเช็คชื่อรายวัน — <?= htmlspecialchars($periodLabel) ?></h3>
    <?php if ($agg['total_events']): ?>
    <div class="trend-chart">
      <?php foreach ($dailyTrend as $d): ?>
      <div class="bar-wrap" title="<?= htmlspecialchars(date('d/m', strtotime($d['date']))) ?>: <?= $d['count'] ?> รายการ">
        <div class="bar" style="height: <?= $d['pct'] > 0 ? $d['pct'] : 2 ?>%;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="trend-labels">
      <span><?= htmlspecialchars(date('d/m', strtotime($dailyTrend[0]['date']))) ?></span>
      <span><?= htmlspecialchars(date('d/m', strtotime($dailyTrend[intdiv(count($dailyTrend), 2)]['date']))) ?></span>
      <span><?= htmlspecialchars(date('d/m', strtotime($dailyTrend[count($dailyTrend) - 1]['date']))) ?></span>
    </div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
      <h3 style="margin:0;">แผนกที่เข้าใช้มากที่สุด</h3>
      <?php if (count($deptBreakdown) > 8): ?>
      <a href="/admin/reports/print/department?<?= htmlspecialchars(http_build_query($filterQueryBase)) ?>" style="font-size:12px; color:var(--primary); font-weight:700; text-decoration:none;">ดูทั้งหมด →</a>
      <?php endif; ?>
    </div>
    <?php if ($topDepts): ?>
    <div class="bar-list">
      <?php foreach ($topDepts as $dept): ?>
      <div class="bar-list-row">
        <div class="meta">
          <span class="name"><?= htmlspecialchars($dept['name']) ?></span>
          <span class="count"><?= $dept['count'] ?> รายการ</span>
        </div>
        <div class="track">
          <div class="fill" style="width: <?= max(12, $topDepts[0]['count'] ? round($dept['count'] / $topDepts[0]['count'] * 100) : 0) ?>%;"><?= $dept['pct'] ?>%</div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel-grid" data-print-section="กราฟช่วงเวลาและรายสัปดาห์" style="margin-top:16px;">
  <div class="panel">
    <h3>การเข้าใช้ตามช่วงเวลา (รายชั่วโมง)</h3>
    <?php if ($agg['total_events']): ?>
    <?php $maxHour = max(1, max(array_column($hourly['hours'], 'count'))); ?>
    <div class="trend-chart hourly-chart">
      <?php foreach ($hourly['hours'] as $h): ?>
      <div class="bar-wrap" title="<?= sprintf('%02d:00', $h['hour']) ?> น.: <?= $h['count'] ?> รายการ">
        <div class="bar" style="height: <?= $h['count'] ? max(2, round($h['count'] / $maxHour * 100)) : 1 ?>%;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="trend-labels"><span>00:00</span><span>12:00</span><span>23:00</span></div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>แนวโน้มรายสัปดาห์ในช่วงเวลานี้</h3>
    <?php if ($weekly): ?>
    <?php $maxWeek = max(array_column($weekly, 'count')); ?>
    <div class="bar-list">
      <?php foreach ($weekly as $w): ?>
      <div class="bar-list-row">
        <div class="meta">
          <span class="name">สัปดาห์ที่ <?= $w['week'] ?></span>
          <span class="count"><?= $w['count'] ?> รายการ</span>
        </div>
        <div class="track">
          <div class="fill" style="width: <?= max(12, $maxWeek ? round($w['count'] / $maxWeek * 100) : 0) ?>%;"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้</p>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

    <?php
    $content = ob_get_clean();

    render_report_layout('รายงานแบบแดชบอร์ด', "สรุปภาพรวมการเช็คชื่อ — $periodLabel", $content, $extraStyle);
}
