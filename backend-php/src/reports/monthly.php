<?php
// Ports app.py's admin_report_monthly() + templates/report_monthly.html,
// extended with filters/MoM+WoW comparisons/department ranking/executive
// summary per the report-system redesign. Comparison numbers reuse the same
// aggregate.php helpers executive.php already uses, so this report and
// executive.php can never disagree about the same month's totals.
function handle_report_monthly(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();
    $month = $_GET['month'] ?? date('Y-m');

    $filters = [
        'department' => trim((string) ($_GET['department'] ?? '')),
        'level' => trim((string) ($_GET['level'] ?? '')),
        'semester' => trim((string) ($_GET['semester'] ?? '')),
        'academic_year' => trim((string) ($_GET['academic_year'] ?? '')),
    ];

    [$startDate, $endDate] = month_bounds($month);
    [$prevStart, $prevEnd] = month_bounds(previous_month($month));

    $agg = aggregate_checkin_period($conn, $startDate, $endDate, $filters);
    $prevAgg = aggregate_checkin_period($conn, $prevStart, $prevEnd, $filters);
    $weekly = aggregate_weekly($conn, $startDate, $endDate, $filters);
    $deptBreakdown = aggregate_department_breakdown($conn, $startDate, $endDate, $filters);

    $totalDelta = pct_delta($agg['total_events'], $prevAgg['total_events']);
    $uniqueDelta = pct_delta($agg['unique_students'], $prevAgg['unique_students']);
    $deltaClass = fn(?float $d) => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
    $deltaArrow = fn(?float $d) => $d === null ? '—' : ($d > 0 ? '↑' : ($d < 0 ? '↓' : '—'));

    $summarySentence = $agg['total_events']
        ? build_summary_sentence(thai_month_label($month), $agg, $agg['busiest_day']
            ? ['day' => date('d/m', strtotime($agg['busiest_day']['day'])), 'count' => $agg['busiest_day']['count']]
            : null, $deptBreakdown, null)
        : null;

    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $conditions = array_merge(["DATE_FORMAT(c.timestamp, '%Y-%m') = ?"], $filterClauses);
    $params = array_merge([$month], $filterParams);
    $stmt = $conn->prepare(
        "SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
                COUNT(CASE WHEN c.type = 'in' THEN 1 END) AS checkin_count,
                MAX(c.timestamp) AS last_checkin
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE " . implode(' AND ', $conditions) . '
         GROUP BY s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level
         ORDER BY LENGTH(s.student_id), s.student_id'
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['gender'] = gender_from_prefix($row['prefix']);
        $row['last_checkin'] = $row['last_checkin'] ?? '-';
    }
    unset($row);

    $departments = distinct_student_values($conn, 'department');
    $levels = distinct_student_values($conn, 'level');
    $semesters = distinct_student_values($conn, 'semester');
    $academicYears = distinct_student_values($conn, 'academic_year');

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-สกุล', 'เพศ', 'แผนกวิชา', 'ระดับชั้น/ปี', 'จำนวนครั้งที่เช็คอิน', 'เช็คอินล่าสุด'];
        $exportRows = [];
        foreach ($rows as $i => $row) {
            $exportRows[] = [
                $i + 1, $row['student_id'], $row['prefix'] . $row['first_name'] . ' ' . $row['last_name'],
                $row['gender'], $row['department'], "{$row['level']} ปีที่ {$row['year_level']}",
                (int) $row['checkin_count'], $row['last_checkin'],
            ];
        }
        $deptSections = [
            'อันดับ/แผนกวิชา/จำนวนการเข้าใช้/ผู้ใช้ไม่ซ้ำ/ร้อยละ',
            ['อันดับ', 'แผนกวิชา', 'จำนวนการเข้าใช้', 'ผู้ใช้ไม่ซ้ำ', 'ร้อยละ'],
            array_map(fn($d) => [$d['rank'], $d['name'], $d['count'], $d['unique'], "{$d['pct']}%"], $deptBreakdown),
        ];
        export_response("รายงานสรุปรายเดือน_$month", [
            ['รายงานสรุปรายเดือน', $headers, $exportRows],
            $deptSections,
        ], $format, [
            'ชื่อรายงาน' => 'รายงานสรุปรายเดือน',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'เดือนที่กรอง' => $month,
            'แผนกวิชา' => $filters['department'] ?: 'ทั้งหมด',
            'ระดับชั้น' => $filters['level'] ?: 'ทั้งหมด',
            'ภาคเรียน' => $filters['semester'] ?: 'ทั้งหมด',
            'ปีการศึกษา' => $filters['academic_year'] ?: 'ทั้งหมด',
        ]);
    }

    ob_start();
    ?>
<style>
  .compare-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
  .compare-card {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc); border-radius: 14px;
    padding: 20px; text-align: center;
  }
  .compare-card .label { font-size: 12px; color: #666; font-weight: 700; margin-bottom: 8px; }
  .compare-card .value { font-size: 32px; font-weight: 800; color: var(--primary, #1e3a8a); line-height: 1; }
  .compare-card .prev { font-size: 12px; color: #888; margin-top: 6px; }
  .compare-card .delta { font-size: 13px; font-weight: 700; margin-top: 8px; }
  .compare-card .delta.up { color: #059669; }
  .compare-card .delta.down { color: #d97706; }
  .compare-card .delta.flat { color: #888; }
  .dept-row { margin-bottom: 10px; }
  .dept-row .dept-meta { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 3px; }
  .dept-row .dept-meta .count { font-weight: bold; }
  .dept-bar-track { background: #eee; border-radius: 4px; height: 10px; overflow: hidden; }
  .dept-bar-fill { background: var(--primary, #1e3a8a); height: 100%; }
  .empty-note { color: #888; font-size: 12px; font-style: italic; }
  @media print {
    .compare-grid { gap: 10px; margin-bottom: 12px; }
    .compare-card { padding: 12px; }
    .compare-card .value { font-size: 22px; }
    .story-box { padding: 12px 16px; margin-bottom: 12px; }
    .compare-grid, .story-box, .panel { break-inside: avoid; }
  }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="filter-bar" method="get">
  <div class="field">
    <label for="month">เดือน</label>
    <?= render_month_select($month) ?>
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
  <a class="reset-link" href="/admin/reports/print/monthly?month=<?= htmlspecialchars($month) ?>"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>
<?php if ($filters['semester'] || $filters['academic_year']): ?>
<p class="filter-note">* ภาคเรียน/ปีการศึกษาอ้างอิงจากข้อมูลการลงทะเบียนปัจจุบันของนักศึกษาแต่ละคน ไม่ใช่ช่วงวันที่ปฏิทินของภาคเรียนนั้น ๆ</p>
<?php endif; ?>

<?php if ($agg['total_events'] === 0): ?>
<div class="empty" data-print-section="สถานะไม่มีข้อมูล">
  ยังไม่มีข้อมูลการเช็คชื่อในเดือนที่เลือก
  <br>
  <a class="empty-cta" href="/admin/reports/print/monthly"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนช่วงเวลา</a>
</div>
<?php endif; ?>

<div class="compare-grid" data-print-section="เปรียบเทียบเดือนก่อนหน้า">
  <div class="compare-card">
    <div class="label">จำนวนรายการทั้งหมด — <?= htmlspecialchars($month) ?></div>
    <div class="value"><?= $agg['total_events'] ?></div>
    <div class="prev">เดือนก่อนหน้า: <?= $prevAgg['total_events'] ?> รายการ</div>
    <?php if ($totalDelta !== null): ?>
    <div class="delta <?= $deltaClass($totalDelta) ?>"><?= $deltaArrow($totalDelta) ?> <?= abs($totalDelta) ?>%</div>
    <?php endif; ?>
  </div>
  <div class="compare-card">
    <div class="label">นักศึกษาไม่ซ้ำคน</div>
    <div class="value"><?= $agg['unique_students'] ?></div>
    <div class="prev">เดือนก่อนหน้า: <?= $prevAgg['unique_students'] ?> คน</div>
    <?php if ($uniqueDelta !== null): ?>
    <div class="delta <?= $deltaClass($uniqueDelta) ?>"><?= $deltaArrow($uniqueDelta) ?> <?= abs($uniqueDelta) ?>%</div>
    <?php endif; ?>
  </div>
  <div class="compare-card">
    <div class="label">เฉลี่ยต่อวัน</div>
    <div class="value"><?= $agg['avg_daily'] ?></div>
    <div class="prev">รายการ/วันที่มีข้อมูล</div>
  </div>
</div>

<?php if ($summarySentence): ?>
<div class="story-box" data-print-section="สรุปสำหรับผู้บริหาร">
  <p><?= $summarySentence ?></p>
</div>
<?php endif; ?>

<div class="panel-grid" data-print-section="กราฟรายสัปดาห์และแผนกวิชา" style="display:grid; grid-template-columns:1fr 1fr; gap:16px; align-items:start;">
  <div class="panel" style="border:1px solid var(--outline-variant); border-radius:10px; padding:16px 18px; background:var(--surface-white);">
    <h3 style="font-size:14px; margin:0 0 12px; color:#333;">แนวโน้มรายสัปดาห์</h3>
    <?php if ($weekly): ?>
    <?php $maxWeek = max(array_column($weekly, 'count')); ?>
    <?php foreach ($weekly as $w): ?>
    <div class="dept-row">
      <div class="dept-meta"><span>สัปดาห์ที่ <?= $w['week'] ?></span><span class="count"><?= $w['count'] ?></span></div>
      <div class="dept-bar-track"><div class="dept-bar-fill" style="width: <?= $maxWeek ? round($w['count'] / $maxWeek * 100) : 0 ?>%;"></div></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
    <?php endif; ?>
  </div>
  <div class="panel" style="border:1px solid var(--outline-variant); border-radius:10px; padding:16px 18px; background:var(--surface-white);">
    <h3 style="font-size:14px; margin:0 0 12px; color:#333;">แผนกที่เข้าใช้มากที่สุด</h3>
    <?php if ($deptBreakdown): ?>
    <?php $topDepts = array_slice($deptBreakdown, 0, 8); $maxDept = $topDepts[0]['count']; ?>
    <?php foreach ($topDepts as $dept): ?>
    <div class="dept-row">
      <div class="dept-meta"><span><?= htmlspecialchars($dept['name']) ?></span><span class="count"><?= $dept['count'] ?></span></div>
      <div class="dept-bar-track"><div class="dept-bar-fill" style="width: <?= $maxDept ? round($dept['count'] / $maxDept * 100) : 0 ?>%;"></div></div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
    <?php endif; ?>
  </div>
</div>

<div class="panel" style="border:1px solid var(--outline-variant); border-radius:10px; padding:16px 18px; background:var(--surface-white); margin:16px 0;" data-print-section="ตารางสรุปแผนกวิชา">
  <h3 style="font-size:14px; margin:0 0 12px; color:#333;">ตารางสรุปตามแผนกวิชา</h3>
  <?php if ($deptBreakdown): ?>
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
  <?php else: ?>
  <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
  <?php endif; ?>
</div>

<div class="meta">จำนวนนักศึกษาที่มีการเช็คชื่อ: <?= count($rows) ?> คน</div>
<?php if ($rows): ?>
<div class="table-wrap" data-print-section="ตารางรายบุคคลประจำเดือน"><div class="table-scroll">
<table>
  <thead>
    <tr>
      <th>ลำดับ</th><th>รหัสนักศึกษา</th><th>ชื่อ-สกุล</th><th>เพศ</th><th>แผนกวิชา</th><th>ระดับชั้น/ปี</th>
      <th>จำนวนครั้งที่เช็คอิน</th><th>เช็คอินล่าสุด</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $i => $row): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($row['student_id']) ?></td>
      <td><?= htmlspecialchars($row['prefix'] . $row['first_name'] . ' ' . $row['last_name']) ?></td>
      <td><?= htmlspecialchars($row['gender']) ?></td>
      <td><?= htmlspecialchars($row['department']) ?></td>
      <td><?= htmlspecialchars($row['level']) ?> ปีที่ <?= htmlspecialchars($row['year_level']) ?></td>
      <td><?= (int) $row['checkin_count'] ?></td>
      <td><?= htmlspecialchars($row['last_checkin']) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php else: ?>
<p class="empty">ไม่มีนักศึกษาตรงกับตัวกรองที่เลือกในเดือนนี้</p>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    // Redrawn as PNGs by GD — the .dept-bar-track/.dept-bar-fill pairs on
    // screen are divs mPDF cannot measure. See layout.php.
    $pdfCharts = [];
    if ($weekly) {
        $pdfCharts[] = [
            'title' => 'แนวโน้มรายสัปดาห์',
            'orientation' => 'vertical',
            'height' => 150,
            'labels' => array_map(fn($w) => 'ส.' . (int) $w['week'], $weekly),
            'values' => array_column($weekly, 'count'),
        ];
    }
    if ($deptBreakdown) {
        // Same top-8 slice the on-screen panel shows, so the PDF is not a
        // different report from the page it was saved off.
        $pdfTopDepts = array_slice($deptBreakdown, 0, 8);
        $pdfCharts[] = [
            'title' => 'แผนกที่เข้าใช้มากที่สุด',
            'orientation' => 'horizontal',
            'labels' => array_column($pdfTopDepts, 'name'),
            'values' => array_column($pdfTopDepts, 'count'),
        ];
    }

    render_report_layout('รายงานสรุปรายเดือน', "รายงานสรุปการเช็คชื่อประจำเดือน $month", $content, $extraStyle, [
        'csv' => "/admin/reports/print/monthly?month=$month&format=csv",
        'excel' => "/admin/reports/print/monthly?month=$month&format=excel",
    ], $pdfCharts);
}
