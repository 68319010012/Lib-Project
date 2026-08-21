<?php
// New report (not in the original app.py) — the report-system redesign's
// §5 "รายงานภาคเรียน". Semester/academic_year filter students' *current*
// registration (students.semester/academic_year), not a calendar date
// range — there's no semester start/end-date config in this system (see the
// confirmed-decision note repeated in every filter-note below). A student
// who was in semester X but has since progressed still only ever shows up
// under their current semester value.
function handle_report_semester(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();

    $academicYears = distinct_student_values($conn, 'academic_year');
    $semesters = distinct_student_values($conn, 'semester');
    $departments = distinct_student_values($conn, 'department');

    $academicYear = trim((string) ($_GET['academic_year'] ?? ''));
    if ($academicYear === '' && $academicYears) {
        $academicYear = end($academicYears);
    }
    $semester = trim((string) ($_GET['semester'] ?? ''));
    $filters = [
        'academic_year' => $academicYear,
        'semester' => $semester,
        'department' => trim((string) ($_GET['department'] ?? '')),
    ];

    $wideStart = '1970-01-01';
    $wideEnd = date('Y-m-d');
    $overall = aggregate_checkin_period($conn, $wideStart, $wideEnd, $filters);
    $monthly = aggregate_monthly_totals($conn, $filters);
    $deptBreakdown = aggregate_department_breakdown($conn, $wideStart, $wideEnd, $filters);
    $topDepts = array_slice($deptBreakdown, 0, 8);

    $monthsWithData = count($monthly);
    $avgPerMonth = $monthsWithData ? (int) round($overall['total_events'] / $monthsWithData) : 0;

    $busiestMonth = null;
    foreach ($monthly as $m) {
        if ($busiestMonth === null || $m['cnt'] > $busiestMonth['cnt']) {
            $busiestMonth = $m;
        }
    }

    $periodLabel = 'ปีการศึกษา ' . ($academicYear ?: 'ทั้งหมด') . ($semester ? " ภาคเรียนที่ $semester" : '');
    $summarySentence = $overall['total_events']
        ? build_summary_sentence($periodLabel, $overall,
            $busiestMonth ? ['day' => $busiestMonth['ym'], 'count' => $busiestMonth['cnt']] : null,
            $topDepts, null)
        : null;

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $monthHeaders = ['เดือน', 'จำนวนการเข้าใช้', 'ผู้ใช้ไม่ซ้ำ'];
        $monthRows = array_map(fn($m) => [$m['ym'], (int) $m['cnt'], (int) $m['unique_cnt']], $monthly);
        $deptHeaders = ['อันดับ', 'แผนกวิชา', 'จำนวนการเข้าใช้', 'ผู้ใช้ไม่ซ้ำ', 'ร้อยละ'];
        $deptRows = array_map(fn($d) => [$d['rank'], $d['name'], $d['count'], $d['unique'], "{$d['pct']}%"], $deptBreakdown);
        export_response("รายงานภาคเรียน_{$academicYear}_{$semester}", [
            ['แนวโน้มรายเดือน', $monthHeaders, $monthRows],
            ['แผนกวิชา', $deptHeaders, $deptRows],
        ], $format, [
            'ชื่อรายงาน' => 'รายงานภาคเรียน',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'ปีการศึกษา' => $academicYear ?: 'ทั้งหมด',
            'ภาคเรียน' => $semester ?: 'ทั้งหมด',
            'แผนกวิชา' => $filters['department'] ?: 'ทั้งหมด',
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
  .kpi-card .value { font-size: 21px; font-weight: bold; color: var(--primary, #1e3a8a); }
  .kpi-card .sub { font-size: 11px; color: #888; margin-top: 4px; }
  .panel { border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 16px 18px; background: var(--surface-white, #fff); margin-bottom: 16px; }
  .panel h3 { font-size: 14px; margin: 0 0 12px; color: #333; }
  .month-row { margin-bottom: 10px; }
  .month-row .meta { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
  .month-row .meta .count { font-weight: bold; }
  .month-track { background: #eee; border-radius: 4px; height: 12px; overflow: hidden; }
  .month-fill { background: var(--primary, #1e3a8a); height: 100%; }
  .dept-row { margin-bottom: 10px; }
  .dept-row .dept-meta { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
  .dept-row .dept-meta .count { font-weight: bold; }
  .dept-bar-track { background: #eee; border-radius: 4px; height: 10px; overflow: hidden; }
  .dept-bar-fill { background: var(--secondary, #2563eb); height: 100%; }
  .empty-note { color: #888; font-size: 12px; font-style: italic; }
  @media print { .panel, .kpi-grid, .story-box { break-inside: avoid; } }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="filter-bar" method="get">
  <div class="field">
    <label for="academic_year">ปีการศึกษา</label>
    <select id="academic_year" name="academic_year">
      <option value="">ทั้งหมด</option>
      <?php foreach ($academicYears as $y): ?>
      <option value="<?= htmlspecialchars($y) ?>" <?= $academicYear === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="semester">ภาคเรียน</label>
    <select id="semester" name="semester">
      <option value="">ทั้งหมด</option>
      <?php foreach ($semesters as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $semester === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
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
  <button type="submit">กรองข้อมูล</button>
  <a class="reset-link" href="/admin/reports/print/semester"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>
<p class="filter-note">* ภาคเรียน/ปีการศึกษาอ้างอิงจากข้อมูลการลงทะเบียนปัจจุบันของนักศึกษาแต่ละคน ไม่ใช่ช่วงวันที่ปฏิทินของภาคเรียนนั้น ๆ (ระบบยังไม่มีการตั้งค่าวันเปิด-ปิดภาคเรียน)</p>

<?php if ($overall['total_events'] === 0): ?>
<div class="empty" data-print-section="สถานะไม่มีข้อมูล">
  ยังไม่มีข้อมูลการเช็คชื่อตามเงื่อนไขที่เลือก
  <br>
  <a class="empty-cta" href="/admin/reports/print/semester"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนตัวกรอง</a>
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
    <div class="label">เฉลี่ยต่อเดือน</div>
    <div class="value"><?= $avgPerMonth ?></div>
    <div class="sub">รายการ/เดือนที่มีข้อมูล</div>
  </div>
  <div class="kpi-card">
    <div class="label">เดือนที่ใช้มากที่สุด</div>
    <div class="value" style="font-size:16px;"><?= $busiestMonth ? htmlspecialchars($busiestMonth['ym']) : '-' ?></div>
    <div class="sub"><?= $busiestMonth ? $busiestMonth['cnt'] . ' รายการ' : 'ไม่มีข้อมูล' ?></div>
  </div>
  <div class="kpi-card">
    <div class="label">แผนกที่เข้าใช้มากที่สุด</div>
    <div class="value" style="font-size:15px;"><?= $topDepts ? htmlspecialchars($topDepts[0]['name']) : '-' ?></div>
    <div class="sub"><?= $topDepts ? $topDepts[0]['count'] . ' รายการ' : 'ไม่มีข้อมูล' ?></div>
  </div>
</div>

<?php if ($summarySentence): ?>
<div class="story-box" data-print-section="สรุปสำหรับผู้บริหาร">
  <p><?= $summarySentence ?></p>
</div>
<?php endif; ?>

<div class="panel-grid" data-print-section="กราฟแนวโน้มรายเดือนและแผนกวิชา" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
  <div class="panel">
    <h3>แนวโน้มรายเดือน</h3>
    <?php if ($monthly): ?>
    <?php $maxMonth = max(array_column($monthly, 'cnt')); ?>
    <?php foreach ($monthly as $m): ?>
    <div class="month-row">
      <div class="meta"><span><?= htmlspecialchars($m['ym']) ?></span><span class="count"><?= $m['cnt'] ?></span></div>
      <div class="month-track"><div class="month-fill" style="width: <?= $maxMonth ? round($m['cnt'] / $maxMonth * 100) : 0 ?>%;"></div></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อ</p>
    <?php endif; ?>
  </div>
  <div class="panel">
    <h3>Ranking แผนกวิชา</h3>
    <?php if ($topDepts): ?>
    <?php $maxDept = $topDepts[0]['count']; ?>
    <?php foreach ($topDepts as $dept): ?>
    <div class="dept-row">
      <div class="dept-meta"><span><?= htmlspecialchars($dept['name']) ?></span><span class="count"><?= $dept['count'] ?></span></div>
      <div class="dept-bar-track"><div class="dept-bar-fill" style="width: <?= $maxDept ? round($dept['count'] / $maxDept * 100) : 0 ?>%;"></div></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อ</p>
    <?php endif; ?>
  </div>
</div>

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

    // Redrawn as PNGs by GD — the .month-track/.dept-bar-track pairs on screen
    // are divs mPDF cannot measure. See layout.php.
    $pdfCharts = [];
    if ($monthly) {
        $pdfCharts[] = [
            'title' => 'แนวโน้มรายเดือน',
            'orientation' => 'vertical',
            'height' => 150,
            'labels' => array_column($monthly, 'ym'),
            'values' => array_column($monthly, 'cnt'),
        ];
    }
    if ($topDepts) {
        $pdfCharts[] = [
            'title' => 'Ranking แผนกวิชา',
            'orientation' => 'horizontal',
            'labels' => array_column($topDepts, 'name'),
            'values' => array_column($topDepts, 'count'),
        ];
    }

    render_report_layout('รายงานภาคเรียน', "สรุปผลการใช้ห้องสมุด — $periodLabel", $content, $extraStyle, [
        'csv' => "/admin/reports/print/semester?academic_year=$academicYear&semester=$semester&format=csv",
        'excel' => "/admin/reports/print/semester?academic_year=$academicYear&semester=$semester&format=excel",
    ], $pdfCharts);
}
