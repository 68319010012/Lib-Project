<?php
// Ports app.py's admin_report_department() + templates/report_department.html,
// extended with filters/ranking chart/MoM comparison/level cross-tab/
// drill-down per the report-system redesign.
function handle_report_department(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();

    $month = trim((string) ($_GET['month'] ?? ''));
    $filters = [
        'level' => trim((string) ($_GET['level'] ?? '')),
        'semester' => trim((string) ($_GET['semester'] ?? '')),
        'academic_year' => trim((string) ($_GET['academic_year'] ?? '')),
        // Selecting a department here doesn't narrow the ranking table (that
        // would defeat the point of a ranking) — it only switches on the
        // per-department trend panel below. Applied separately, not via
        // build_filter_clause, for that reason.
    ];
    $selectedDept = trim((string) ($_GET['department'] ?? ''));

    if ($month !== '') {
        [$startDate, $endDate] = month_bounds($month);
        $periodLabel = thai_month_label($month);
    } else {
        // Unscoped by date, same as the original report — academic_year
        // (already a filter) is normally how this report's users narrow
        // "which period", not a date range.
        $startDate = '1970-01-01';
        $endDate = date('Y-m-d');
        $periodLabel = 'ทุกช่วงเวลา';
    }

    $deptBreakdown = aggregate_department_breakdown($conn, $startDate, $endDate, $filters);
    $overall = aggregate_checkin_period($conn, $startDate, $endDate, $filters);
    $avgPerPerson = $overall['unique_students']
        ? round($overall['total_events'] / $overall['unique_students'], 1)
        : 0;

    $selectedDeptRow = null;
    if ($selectedDept !== '') {
        foreach ($deptBreakdown as $row) {
            if ($row['name'] === $selectedDept) {
                $selectedDeptRow = $row;
                break;
            }
        }
    }

    // Always-on month-over-month per-department comparison — current
    // calendar month vs previous, independent of the report's own period
    // filter above (mirrors compare.php's dual-bar department panel).
    $thisMonth = date('Y-m');
    [$momCurStart, $momCurEnd] = month_bounds($thisMonth);
    [$momPrevStart, $momPrevEnd] = month_bounds(previous_month($thisMonth));
    $momCurrent = aggregate_department_breakdown($conn, $momCurStart, $momCurEnd, $filters);
    $momPrevious = aggregate_department_breakdown($conn, $momPrevStart, $momPrevEnd, $filters);
    $momPrevByName = [];
    foreach ($momPrevious as $row) {
        $momPrevByName[$row['name']] = $row['count'];
    }
    $momRows = [];
    foreach (array_slice($momCurrent, 0, 8) as $row) {
        $momRows[] = ['name' => $row['name'], 'current' => $row['count'], 'previous' => $momPrevByName[$row['name']] ?? 0];
    }
    $momMax = 0;
    foreach ($momRows as $r) {
        $momMax = max($momMax, $r['current'], $r['previous']);
    }

    // Level cross-tab — always shown when there's level data, scoped to the
    // selected department if one is picked (so it reads as "level split for
    // department X" instead of the whole college's level split, which the
    // ranking table above already implies).
    $levelFilters = $filters;
    if ($selectedDept !== '') {
        $levelFilters['department'] = $selectedDept;
    }
    $levelBreakdown = aggregate_breakdown_by($conn, 'level', $startDate, $endDate, $levelFilters);

    // Per-department daily trend, only when a department is selected —
    // scoped to the current month (not the report's own "all-time" default)
    // so the chart stays at a readable day-by-day granularity.
    $deptTrend = null;
    if ($selectedDept !== '') {
        $trendFilters = $filters;
        $trendFilters['department'] = $selectedDept;
        [$trendStart, $trendEnd] = $month !== '' ? [$startDate, $endDate] : month_bounds($thisMonth);
        $deptTrend = aggregate_daily_trend($conn, $trendStart, $trendEnd, $trendFilters);
    }

    $levels = distinct_student_values($conn, 'level');
    $semesters = distinct_student_values($conn, 'semester');
    $academicYears = distinct_student_values($conn, 'academic_year');
    $departments = distinct_student_values($conn, 'department');

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['อันดับ', 'แผนกวิชา', 'จำนวนการเข้าใช้', 'ผู้ใช้ไม่ซ้ำ', 'ร้อยละ'];
        $exportRows = array_map(fn($d) => [$d['rank'], $d['name'], $d['count'], $d['unique'], "{$d['pct']}%"], $deptBreakdown);
        export_response('รายงานสรุปตามแผนกวิชา', [['รายงานสรุปตามแผนกวิชา', $headers, $exportRows]], $format, [
            'ชื่อรายงาน' => 'รายงานสรุปตามแผนกวิชา',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'ช่วงเวลา' => $periodLabel,
            'ระดับชั้น' => $filters['level'] ?: 'ทั้งหมด',
            'ภาคเรียน' => $filters['semester'] ?: 'ทั้งหมด',
            'ปีการศึกษา' => $filters['academic_year'] ?: 'ทั้งหมด',
        ]);
    }

    ob_start();
    ?>
<style>
  .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 4px 0 20px; }
  .kpi-card {
    border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 14px 16px;
    background: var(--surface-white, #fff); box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #666; margin-bottom: 6px; }
  .kpi-card .value { font-size: 22px; font-weight: bold; color: var(--primary, #1e3a8a); }
  .kpi-card .sub { font-size: 11px; color: #888; margin-top: 4px; }

  .rank-bars { display: flex; flex-direction: column; gap: 8px; margin-bottom: 20px; }
  .rank-bars .row { display: flex; align-items: center; gap: 10px; font-size: 12px; }
  .rank-bars .name { width: 160px; flex-shrink: 0; text-align: right; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .rank-bars .track { flex: 1; background: #eee; border-radius: 4px; height: 14px; overflow: hidden; }
  .rank-bars .fill { background: var(--primary, #1e3a8a); height: 100%; }
  .rank-bars .count { width: 40px; font-weight: 700; }
  .rank-bars .links { display: flex; gap: 4px; flex-shrink: 0; }
  .rank-bars .links a { color: var(--on-surface-variant, #666); text-decoration: none; }

  .mom-row { margin-bottom: 12px; }
  .mom-row .dept-name { font-size: 12px; font-weight: 700; margin-bottom: 4px; }
  .mom-row .bar-line { display: flex; align-items: center; gap: 8px; margin-bottom: 2px; }
  .mom-row .bar-track { flex: 1; background: #eee; border-radius: 4px; height: 10px; overflow: hidden; }
  .mom-row .bar-fill { height: 100%; }
  .mom-row .bar-fill.prev { background: #94a3b8; }
  .mom-row .bar-fill.cur { background: var(--primary, #1e3a8a); }
  .mom-row .bar-count { font-size: 11px; width: 26px; text-align: right; flex-shrink: 0; }
  .mom-legend { display: flex; gap: 14px; font-size: 11px; color: #666; margin-bottom: 12px; }
  .mom-legend span { display: flex; align-items: center; gap: 5px; }
  .mom-legend .swatch { width: 10px; height: 10px; border-radius: 3px; }
  .swatch.prev { background: #94a3b8; } .swatch.cur { background: var(--primary, #1e3a8a); }

  .trend-chart { display: flex; align-items: flex-end; gap: 2px; height: 100px; border-bottom: 1px solid #ddd; padding-top: 8px; }
  .trend-chart .bar-wrap { flex: 1; display: flex; align-items: flex-end; height: 100%; }
  .trend-chart .bar { width: 100%; background: var(--secondary, #2563eb); border-radius: 2px 2px 0 0; min-height: 1px; }
  .empty-note { color: #888; font-size: 12px; font-style: italic; }
  .panel { border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 16px 18px; background: var(--surface-white, #fff); margin-bottom: 16px; }
  .panel h3 { font-size: 14px; margin: 0 0 12px; color: #333; }
  @media print { .panel, .kpi-grid, .rank-bars { break-inside: avoid; } }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="filter-bar" method="get">
  <div class="field">
    <label for="month">เดือน (ไม่ใส่ = ทุกช่วงเวลา)</label>
    <?= render_month_select($month, 'month', 'month', 18, '— ทุกช่วงเวลา —') ?>
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
    <label for="level">ระดับชั้น</label>
    <select id="level" name="level">
      <option value="">ทั้งหมด</option>
      <?php foreach ($levels as $l): ?>
      <option value="<?= htmlspecialchars($l) ?>" <?= $filters['level'] === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="department">ดูแนวโน้มของแผนก (ไม่บังคับ)</label>
    <select id="department" name="department">
      <option value="">— ไม่เลือก —</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $selectedDept === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit">กรองข้อมูล</button>
  <a class="reset-link" href="/admin/reports/print/department"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>
<?php if ($filters['semester'] || $filters['academic_year']): ?>
<p class="filter-note">* ภาคเรียน/ปีการศึกษาอ้างอิงจากข้อมูลการลงทะเบียนปัจจุบันของนักศึกษาแต่ละคน ไม่ใช่ช่วงวันที่ปฏิทินของภาคเรียนนั้น ๆ</p>
<?php endif; ?>

<?php if ($overall['total_events'] === 0): ?>
<div class="empty" data-print-section="สถานะไม่มีข้อมูล">
  ไม่มีข้อมูลการเช็คชื่อตามเงื่อนไขที่เลือก
  <br>
  <a class="empty-cta" href="/admin/reports/print/department"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนช่วงเวลา</a>
</div>
<?php else: ?>

<div class="kpi-grid" data-print-section="KPI สรุป">
  <div class="kpi-card">
    <div class="label">จำนวนการเข้าใช้ทั้งหมด</div>
    <div class="value"><?= $overall['total_events'] ?></div>
    <div class="sub"><?= htmlspecialchars($periodLabel) ?></div>
  </div>
  <div class="kpi-card">
    <div class="label">ผู้ใช้ไม่ซ้ำคน</div>
    <div class="value"><?= $overall['unique_students'] ?></div>
    <div class="sub">คนที่มีการเข้าใช้</div>
  </div>
  <div class="kpi-card">
    <div class="label">เฉลี่ยต่อคน</div>
    <div class="value"><?= $avgPerPerson ?></div>
    <div class="sub">ครั้ง/คน ตลอดช่วงเวลานี้</div>
  </div>
  <?php if ($selectedDeptRow): ?>
  <div class="kpi-card">
    <div class="label">สัดส่วนของ <?= htmlspecialchars($selectedDept) ?></div>
    <div class="value"><?= $selectedDeptRow['pct'] ?>%</div>
    <div class="sub">ของการเข้าใช้ทั้งหมด (<?= $selectedDeptRow['count'] ?> รายการ)</div>
  </div>
  <?php endif; ?>
</div>

<div class="panel" data-print-section="กราฟ Ranking แผนกวิชา">
  <h3>Ranking แผนกวิชา</h3>
  <div class="rank-bars">
    <?php $maxCount = $deptBreakdown[0]['count'] ?: 1; ?>
    <?php foreach ($deptBreakdown as $row): ?>
    <div class="row">
      <span class="name"><?= htmlspecialchars($row['name']) ?></span>
      <span class="track"><span class="fill" style="width: <?= round($row['count'] / $maxCount * 100) ?>%;"></span></span>
      <span class="count"><?= $row['count'] ?></span>
      <span class="links">
        <a href="/admin/reports/print/daily?date=<?= date('Y-m-d') ?>&department=<?= urlencode($row['name']) ?>" title="รายงานประจำวัน — <?= htmlspecialchars($row['name']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">today</span></a>
        <a href="/admin/reports/print/monthly?month=<?= $month ?: date('Y-m') ?>&department=<?= urlencode($row['name']) ?>" title="รายงานรายเดือน — <?= htmlspecialchars($row['name']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">calendar_month</span></a>
      </span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="panel" data-print-section="เปรียบเทียบเดือนนี้กับเดือนก่อน">
  <h3>เปรียบเทียบแต่ละแผนกวิชา — เดือนนี้เทียบกับเดือนก่อนหน้า</h3>
  <?php if ($momRows): ?>
  <div class="mom-legend">
    <span><span class="swatch prev"></span>เดือนก่อนหน้า</span>
    <span><span class="swatch cur"></span>เดือนนี้</span>
  </div>
  <?php foreach ($momRows as $r): ?>
  <div class="mom-row">
    <div class="dept-name"><?= htmlspecialchars($r['name']) ?></div>
    <div class="bar-line"><div class="bar-track"><div class="bar-fill prev" style="width: <?= $momMax ? round($r['previous'] / $momMax * 100) : 0 ?>%;"></div></div><div class="bar-count"><?= $r['previous'] ?></div></div>
    <div class="bar-line"><div class="bar-track"><div class="bar-fill cur" style="width: <?= $momMax ? round($r['current'] / $momMax * 100) : 0 ?>%;"></div></div><div class="bar-count"><?= $r['current'] ?></div></div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <p class="empty-note">ไม่มีข้อมูลเปรียบเทียบ</p>
  <?php endif; ?>
</div>

<?php if ($deptTrend !== null): ?>
<div class="panel" data-print-section="แนวโน้มของแผนกที่เลือก">
  <h3>แนวโน้มการเข้าใช้ของ <?= htmlspecialchars($selectedDept) ?></h3>
  <?php $trendTotal = array_sum(array_column($deptTrend, 'count')); ?>
  <?php if ($trendTotal): ?>
  <div class="trend-chart">
    <?php foreach ($deptTrend as $d): ?>
    <div class="bar-wrap" title="<?= htmlspecialchars(date('d/m', strtotime($d['date']))) ?>: <?= $d['count'] ?> รายการ" data-label="<?= htmlspecialchars(date('d/m', strtotime($d['date']))) ?>" data-count="<?= $d['count'] ?>">
      <div class="bar" style="height: <?= $d['pct'] > 0 ? $d['pct'] : 2 ?>%;"></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <p class="empty-note">แผนกนี้ไม่มีข้อมูลการเช็คชื่อในช่วงที่แสดง</p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if (count($levelBreakdown) > 1): ?>
<div class="panel" data-print-section="แยกตามระดับชั้น">
  <h3>แยกตามระดับชั้น<?= $selectedDept !== '' ? ' — ' . htmlspecialchars($selectedDept) : '' ?></h3>
  <div class="table-wrap"><div class="table-scroll">
  <table>
    <thead><tr><th>ระดับชั้น</th><th>จำนวนการเข้าใช้</th><th>ผู้ใช้ไม่ซ้ำ</th><th>ร้อยละ</th></tr></thead>
    <tbody>
      <?php foreach ($levelBreakdown as $row): ?>
      <tr><td><?= htmlspecialchars($row['name']) ?></td><td><?= $row['count'] ?></td><td><?= $row['unique'] ?></td><td><?= $row['pct'] ?>%</td></tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div></div>
</div>
<?php endif; ?>

<div class="panel" data-print-section="ตารางสรุปแผนกวิชา">
  <h3>ตารางสรุปตามแผนกวิชา</h3>
  <div class="table-wrap"><div class="table-scroll">
  <table>
    <thead><tr><th>อันดับ</th><th>แผนกวิชา</th><th>จำนวนการเข้าใช้</th><th>ผู้ใช้ไม่ซ้ำ</th><th>ร้อยละ</th></tr></thead>
    <tbody>
      <?php foreach ($deptBreakdown as $row): ?>
      <tr>
        <td><?= $row['rank'] ?></td><td><?= htmlspecialchars($row['name']) ?></td>
        <td><?= $row['count'] ?></td><td><?= $row['unique'] ?></td><td><?= $row['pct'] ?>%</td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div></div>
</div>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    $subtitle = "รายงานสรุปการเช็คชื่อตามแผนกวิชา — $periodLabel";

    $qs = array_filter([
        'month' => $month ?: null,
        'level' => $filters['level'] ?: null,
        'semester' => $filters['semester'] ?: null,
        'academic_year' => $filters['academic_year'] ?: null,
    ]);
    $qsString = $qs ? '?' . http_build_query($qs) . '&' : '?';
    // The screen charts are divs with percentage heights, which mPDF cannot
    // measure — they are hidden from the PDF (layout.php's $pdfStyle) and
    // redrawn here as flat PNGs by GD instead, so the saved file carries the
    // same picture the page does.
    $pdfCharts = [];
    if ($deptBreakdown) {
        $pdfCharts[] = [
            'title' => 'Ranking แผนกวิชา',
            'orientation' => 'horizontal',
            'labels' => array_column($deptBreakdown, 'name'),
            'values' => array_column($deptBreakdown, 'count'),
        ];
    }
    if ($deptTrend !== null && array_sum(array_column($deptTrend, 'count')) > 0) {
        $pdfCharts[] = [
            'title' => "แนวโน้มการเข้าใช้ของ $selectedDept",
            'orientation' => 'vertical',
            'height' => 150,
            'labels' => array_map(fn($row) => (string) ((int) substr($row['date'], -2)), $deptTrend),
            'values' => array_column($deptTrend, 'count'),
        ];
    }
    if ($momRows) {
        // Shared scale for the same reason as compare.php: the point of the
        // pair is which month is taller.
        $momNames = array_column($momRows, 'name');
        $momScale = (int) max(1, $momMax);
        // half: วางสองเดือนไว้ข้างกัน ไม่ใช่ซ้อนลงมาคนละครึ่งหน้า — กราฟที่ตั้งใจ
        // ให้เทียบกันต้องเห็นพร้อมกันในสายตาเดียว และบนกระดาษแนวนอนกราฟที่แคบลง
        // ครึ่งหนึ่งจะเตี้ยลงตามสัดส่วนด้วย สองใบจึงกินความสูงเท่าใบเดียวเมื่อก่อน
        $pdfCharts[] = [
            'title' => 'เปรียบเทียบแต่ละแผนกวิชา — เดือนก่อนหน้า',
            'orientation' => 'horizontal',
            'labels' => $momNames,
            'values' => array_column($momRows, 'previous'),
            'scale_max' => $momScale,
            'half' => true,
        ];
        $pdfCharts[] = [
            'title' => 'เปรียบเทียบแต่ละแผนกวิชา — เดือนนี้',
            'orientation' => 'horizontal',
            'labels' => $momNames,
            'values' => array_column($momRows, 'current'),
            'scale_max' => $momScale,
            'half' => true,
        ];
    }

    render_report_layout('รายงานสรุปตามแผนกวิชา', $subtitle, $content, $extraStyle, [
        'csv' => "/admin/reports/print/department{$qsString}format=csv",
        'excel' => "/admin/reports/print/department{$qsString}format=excel",
    ], $pdfCharts);
}
