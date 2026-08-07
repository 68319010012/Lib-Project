<?php
// Ports app.py's admin_report_dashboard() + templates/report_dashboard.html.
// Undocumented in API_CONTRACT.md but real code in app.py — ported anyway.
function handle_report_dashboard(): void
{
    require_login();
    require_admin();

    $month = $_GET['month'] ?? date('Y-m');

    $conn = get_db_connection();
    $stmt = $conn->prepare(
        "SELECT s.student_id, s.department, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE DATE_FORMAT(c.timestamp, '%Y-%m') = ?"
    );
    $stmt->execute([$month]);
    $rows = $stmt->fetchAll();

    $totalEvents = count($rows);
    $uniqueStudents = count(array_unique(array_column($rows, 'student_id')));

    // day_counts/dept_counts insertion order follows the (unordered — no
    // ORDER BY in this query) SQL result order, exactly like the Python dict
    // version. Don't "fix" this by sorting rows first.
    $dayCounts = [];
    $deptCounts = [];
    foreach ($rows as $row) {
        $dayKey = date('d', strtotime($row['timestamp']));
        $dayCounts[$dayKey] = ($dayCounts[$dayKey] ?? 0) + 1;
        $dept = $row['department'] ?: 'ไม่ระบุแผนก';
        $deptCounts[$dept] = ($deptCounts[$dept] ?? 0) + 1;
    }

    $daysWithData = count($dayCounts);
    $avgDaily = $daysWithData ? (int) round($totalEvents / $daysWithData) : 0;

    [$year, $mon] = array_map('intval', explode('-', $month));
    $daysInMonth = (int) date('t', mktime(0, 0, 0, $mon, 1, $year));

    $dailyTrend = [];
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $key = sprintf('%02d', $d);
        $dailyTrend[] = ['day' => $key, 'count' => $dayCounts[$key] ?? 0];
    }
    $maxDaily = 0;
    foreach ($dailyTrend as $d) {
        $maxDaily = max($maxDaily, $d['count']);
    }
    foreach ($dailyTrend as &$d) {
        $d['pct'] = $maxDaily ? (int) round(($d['count'] / $maxDaily) * 100) : 0;
    }
    unset($d);

    // Stable sort (PHP usort is stable since 8.0) descending by count, so ties
    // keep dept_counts insertion order — matches Python's sorted() (also stable).
    $deptList = [];
    foreach ($deptCounts as $name => $count) {
        $deptList[] = ['name' => $name, 'count' => $count];
    }
    usort($deptList, fn($a, $b) => $b['count'] <=> $a['count']);
    $deptBreakdown = array_slice($deptList, 0, 8);
    $maxDept = $deptBreakdown ? $deptBreakdown[0]['count'] : 0;
    foreach ($deptBreakdown as &$dept) {
        $dept['pct'] = $maxDept ? (int) round(($dept['count'] / $maxDept) * 100) : 0;
    }
    unset($dept);

    // First-encountered-max-wins tie-break, matching Python's max(dict.items(), key=...)
    // exactly (a stable/reverse sort here could pick a different tied day).
    $busiestDay = null;
    foreach ($dayCounts as $key => $count) {
        if ($busiestDay === null || $count > $busiestDay['count']) {
            $busiestDay = ['day' => $key, 'count' => $count];
        }
    }

    ob_start();
    ?>
<style>
  .kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin: 16px 0 24px;
  }
  .kpi-card {
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 10px;
    padding: 14px 16px;
    background: var(--surface-white, #fafafa);
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .kpi-card .label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #666;
    margin-bottom: 6px;
  }
  .kpi-card .value {
    font-size: 24px;
    font-weight: bold;
    color: #5c101f;
  }
  .kpi-card .sub {
    font-size: 11px;
    color: #888;
    margin-top: 4px;
  }
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
    font-size: 14px;
    margin: 0 0 12px;
    color: #333;
  }
  .trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 2px;
    height: 140px;
    border-bottom: 1px solid #ddd;
    padding-top: 8px;
  }
  .trend-chart .bar-wrap {
    flex: 1;
    display: flex;
    align-items: flex-end;
    height: 100%;
  }
  .trend-chart .bar {
    width: 100%;
    background: #a53d00;
    border-radius: 2px 2px 0 0;
    min-height: 1px;
  }
  .trend-labels {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    color: #888;
    margin-top: 4px;
  }
  .dept-row {
    margin-bottom: 10px;
  }
  .dept-row .dept-meta {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 3px;
  }
  .dept-row .dept-meta .count {
    font-weight: bold;
  }
  .dept-bar-track {
    background: #eee;
    border-radius: 4px;
    height: 10px;
    overflow: hidden;
  }
  .dept-bar-fill {
    background: #5c101f;
    height: 100%;
  }
  .empty-note {
    color: #888;
    font-size: 12px;
    font-style: italic;
  }
  .month-filter {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    background: var(--surface-white, #fff);
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 999px;
    padding: 8px 8px 8px 16px;
    width: fit-content;
  }
  .month-filter label {
    font-size: 12px;
    color: var(--on-surface-variant, #555);
    font-weight: 600;
  }
  .month-filter input {
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 13px;
  }
  .month-filter button {
    border: none;
    background: var(--primary, #5c101f);
    color: #fff;
    font-weight: 700;
    font-size: 13px;
    padding: 8px 16px;
    border-radius: 999px;
    cursor: pointer;
  }
  .month-filter button:hover { filter: brightness(1.1); }
  @media print {
    .kpi-card { background: #fff; }
    .month-filter { display: none; }
  }
</style>
<?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="month-filter" method="get">
  <label for="month">เดือน</label>
  <input type="month" id="month" name="month" value="<?= htmlspecialchars($month) ?>">
  <button type="submit">ดูเดือนอื่น</button>
</form>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="label">จำนวนรายการทั้งหมด</div>
    <div class="value"><?= $totalEvents ?></div>
    <div class="sub">ครั้งการเช็คอิน/เช็คเอาต์</div>
  </div>
  <div class="kpi-card">
    <div class="label">นักศึกษาไม่ซ้ำคน</div>
    <div class="value"><?= $uniqueStudents ?></div>
    <div class="sub">คนที่มีการเข้าใช้</div>
  </div>
  <div class="kpi-card">
    <div class="label">เฉลี่ยต่อวัน</div>
    <div class="value"><?= $avgDaily ?></div>
    <div class="sub">รายการ/วันที่มีข้อมูล</div>
  </div>
  <div class="kpi-card">
    <div class="label">วันที่มีคนใช้มากที่สุด</div>
    <div class="value"><?= $busiestDay ? htmlspecialchars($busiestDay['day']) : '-' ?></div>
    <div class="sub"><?= $busiestDay ? htmlspecialchars($busiestDay['count'] . ' รายการ') : 'ไม่มีข้อมูล' ?></div>
  </div>
</div>

<div class="panel-grid">
  <div class="panel">
    <h3>แนวโน้มการเช็คชื่อรายวัน — <?= htmlspecialchars($month) ?></h3>
    <?php if ($dailyTrend && $totalEvents): ?>
    <div class="trend-chart">
      <?php foreach ($dailyTrend as $d): ?>
      <div class="bar-wrap" title="วันที่ <?= htmlspecialchars($d['day']) ?>: <?= $d['count'] ?> รายการ">
        <div class="bar" style="height: <?= $d['pct'] > 0 ? $d['pct'] : 2 ?>%;"></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="trend-labels">
      <span>1</span>
      <span><?= intdiv(count($dailyTrend), 2) ?></span>
      <span><?= count($dailyTrend) ?></span>
    </div>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
    <?php endif; ?>
  </div>

  <div class="panel">
    <h3>แผนกที่เข้าใช้มากที่สุด</h3>
    <?php if ($deptBreakdown): ?>
    <?php foreach ($deptBreakdown as $dept): ?>
    <div class="dept-row">
      <div class="dept-meta">
        <span><?= htmlspecialchars($dept['name']) ?></span>
        <span class="count"><?= $dept['count'] ?></span>
      </div>
      <div class="dept-bar-track">
        <div class="dept-bar-fill" style="width: <?= $dept['pct'] ?>%;"></div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
    <?php endif; ?>
  </div>
</div>

    <?php
    $content = ob_get_clean();

    render_report_layout('รายงานแบบแดชบอร์ด', "สรุปภาพรวมการเช็คชื่อประจำเดือน $month", $content, $extraStyle);
}
