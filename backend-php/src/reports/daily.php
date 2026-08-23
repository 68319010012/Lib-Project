<?php
// Ports app.py's admin_report_daily() + templates/report_daily.html, extended
// with filters/summary/duration/sort per the report-system redesign.
function handle_report_daily(): void
{
    require_login();
    require_admin();

    $date = $_GET['date'] ?? date('Y-m-d');
    $search = trim((string) ($_GET['search'] ?? ''));
    $startHourParam = $_GET['start_hour'] ?? '';
    $endHourParam = $_GET['end_hour'] ?? '';

    $filters = [
        'department' => trim((string) ($_GET['department'] ?? '')),
        'level' => trim((string) ($_GET['level'] ?? '')),
    ];

    $conn = get_db_connection();

    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $conditions = array_merge(['DATE(c.timestamp) = ?'], $filterClauses);
    $params = array_merge([$date], $filterParams);
    if ($search !== '') {
        $conditions[] = '(s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?)';
        $like = "%$search%";
        array_push($params, $like, $like, $like);
    }
    $where = implode(' AND ', $conditions);

    $stmt = $conn->prepare(
        "SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
                c.type, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         ORDER BY LENGTH(s.student_id), s.student_id, c.timestamp"
    );
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    $byStudent = [];
    foreach ($logs as $log) {
        $sid = $log['student_id'];
        if (!isset($byStudent[$sid])) {
            $byStudent[$sid] = [
                'student_id' => $sid,
                'prefix' => $log['prefix'],
                'first_name' => $log['first_name'],
                'last_name' => $log['last_name'],
                'gender' => gender_from_prefix($log['prefix']),
                'department' => $log['department'],
                'level' => $log['level'],
                'year_level' => $log['year_level'],
                'time_in' => null,
                'time_out' => null,
            ];
        }
        $timeStr = date('H:i:s', strtotime($log['timestamp']));
        // Asymmetric on purpose: time_in keeps the FIRST "in" of the day,
        // time_out keeps the LAST "out" (unconditional overwrite) — matches
        // app.py exactly. A student who checks in/out more than once in the
        // same day is shown as one row spanning their first-in to last-out,
        // not as separate visits — same simplification the original report
        // already made; not something this redesign changes.
        if ($log['type'] === 'in' && $byStudent[$sid]['time_in'] === null) {
            $byStudent[$sid]['time_in'] = $timeStr;
        }
        if ($log['type'] === 'out') {
            $byStudent[$sid]['time_out'] = $timeStr;
        }
    }

    // Summary strip reflects every filtered event for the day (department/
    // level/search applied above) — computed from the raw $logs, before the
    // per-student pairing above and before the display-only hour-range
    // filter below narrows which rows the table shows.
    $totalEvents = count($logs);
    $firstCheckin = null;
    $lastActivity = null;
    $hourCounts = [];
    foreach ($logs as $log) {
        $t = date('H:i:s', strtotime($log['timestamp']));
        if ($log['type'] === 'in' && ($firstCheckin === null || $t < $firstCheckin)) {
            $firstCheckin = $t;
        }
        if ($lastActivity === null || $t > $lastActivity) {
            $lastActivity = $t;
        }
        $hr = (int) date('H', strtotime($log['timestamp']));
        $hourCounts[$hr] = ($hourCounts[$hr] ?? 0) + 1;
    }
    $peakHour = null;
    foreach ($hourCounts as $hr => $cnt) {
        if ($peakHour === null || $cnt > $peakHour['count']) {
            $peakHour = ['hour' => $hr, 'count' => $cnt];
        }
    }

    $rows = array_values($byStudent);
    usort($rows, fn($a, $b) => $a['student_id'] <=> $b['student_id']);

    foreach ($rows as &$row) {
        if ($row['time_in'] && $row['time_out']) {
            $inSec = strtotime("$date {$row['time_in']}");
            $outSec = strtotime("$date {$row['time_out']}");
            $diffMin = max(0, (int) round(($outSec - $inSec) / 60));
            $row['duration'] = $diffMin >= 60
                ? sprintf('%d ชม. %d นาที', intdiv($diffMin, 60), $diffMin % 60)
                : "{$diffMin} นาที";
            $row['status'] = 'ออกแล้ว';
        } elseif ($row['time_in']) {
            $row['duration'] = '-';
            $row['status'] = 'อยู่ในห้องสมุด';
        } else {
            $row['duration'] = '-';
            $row['status'] = 'ออกแล้ว';
        }
    }
    unset($row);

    // Hour-range is a display-only refinement of the already-paired rows
    // (filters on each student's time_in), not a re-query — re-querying at
    // the raw-log level would break the in/out pairing above.
    if ($startHourParam !== '' || $endHourParam !== '') {
        $sh = $startHourParam !== '' ? (int) $startHourParam : 0;
        $eh = $endHourParam !== '' ? (int) $endHourParam : 23;
        $rows = array_values(array_filter($rows, function ($row) use ($sh, $eh) {
            if (!$row['time_in']) {
                return false;
            }
            $hour = (int) substr($row['time_in'], 0, 2);
            return $hour >= $sh && $hour <= $eh;
        }));
    }

    $departments = distinct_student_values($conn, 'department');
    $levels = distinct_student_values($conn, 'level');

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['ลำดับ', 'รหัสนักศึกษา', 'ชื่อ-สกุล', 'เพศ', 'แผนกวิชา', 'ระดับชั้น/ปี', 'เวลาเข้า', 'เวลาออก', 'ระยะเวลา', 'สถานะ'];
        $exportRows = [];
        foreach ($rows as $i => $row) {
            $exportRows[] = [
                $i + 1, $row['student_id'], $row['prefix'] . $row['first_name'] . ' ' . $row['last_name'],
                $row['gender'], $row['department'], "{$row['level']} ปีที่ {$row['year_level']}",
                $row['time_in'] ?? '-', $row['time_out'] ?? '-', $row['duration'], $row['status'],
            ];
        }
        export_response("รายงานประจำวัน_$date", [['รายงานประจำวัน', $headers, $exportRows]], $format, [
            'ชื่อรายงาน' => 'รายงานประจำวัน',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'วันที่ของรายงาน' => $date,
            'แผนกวิชา' => $filters['department'] ?: 'ทั้งหมด',
            'ระดับชั้น' => $filters['level'] ?: 'ทั้งหมด',
            'คำค้นหา' => $search ?: '-',
        ]);
    }

    ob_start();
    ?>
<style>
  .summary-strip {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px;
    margin-bottom: 18px;
  }
  .summary-strip .item {
    border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 12px 14px;
    background: var(--surface-white, #fff);
  }
  .summary-strip .item .label { font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: .04em; margin-bottom: 4px; }
  .summary-strip .item .value { font-size: 18px; font-weight: 700; color: var(--primary, #1e3a8a); }
  th[data-sort] { cursor: pointer; user-select: none; }
  th[data-sort]:after { content: ' ⇅'; opacity: .5; font-size: 10px; }
  .status-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
  .status-pill.in { background: #dcfce7; color: #166534; }
  .status-pill.out { background: #f1f5f9; color: #475569; }
  @media print {
    .summary-strip .item { box-shadow: none; }
  }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="filter-bar" method="get">
  <div class="field">
    <label for="date">วันที่</label>
    <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>">
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
  <div class="field">
    <label for="search">ค้นหาชื่อ/รหัสนักศึกษา</label>
    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ชื่อ หรือ รหัส">
  </div>
  <div class="field">
    <label for="start_hour">ช่วงเวลาเข้า (ชม.)</label>
    <input type="number" id="start_hour" name="start_hour" min="0" max="23" style="width:64px;" value="<?= htmlspecialchars($startHourParam) ?>" placeholder="0">
  </div>
  <div class="field">
    <label for="end_hour">ถึง</label>
    <input type="number" id="end_hour" name="end_hour" min="0" max="23" style="width:64px;" value="<?= htmlspecialchars($endHourParam) ?>" placeholder="23">
  </div>
  <button type="submit">กรองข้อมูล</button>
  <a class="reset-link" href="/admin/reports/print/daily?date=<?= htmlspecialchars($date) ?>"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>

<?php if ($totalEvents === 0): ?>
<div class="empty" data-print-section="สถานะไม่มีข้อมูล">
  ยังไม่มีข้อมูลการเช็คชื่อในวันที่เลือก
  <br>
  <a class="empty-cta" href="/admin/reports/print/daily"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนช่วงเวลา</a>
</div>
<?php else: ?>

<div class="summary-strip" data-print-section="สรุปย่อประจำวัน">
  <div class="item"><div class="label">จำนวนรายการทั้งหมด</div><div class="value"><?= $totalEvents ?></div></div>
  <div class="item"><div class="label">นักศึกษาไม่ซ้ำคน</div><div class="value"><?= count($byStudent) ?></div></div>
  <div class="item"><div class="label">เช็คอินครั้งแรก</div><div class="value"><?= $firstCheckin ? htmlspecialchars(substr($firstCheckin, 0, 5)) : '-' ?></div></div>
  <div class="item"><div class="label">กิจกรรมล่าสุด</div><div class="value"><?= $lastActivity ? htmlspecialchars(substr($lastActivity, 0, 5)) : '-' ?></div></div>
  <div class="item"><div class="label">ช่วงเวลาคนใช้มากที่สุด</div><div class="value"><?= $peakHour ? sprintf('%02d:00', $peakHour['hour']) : '-' ?></div></div>
</div>

<div class="meta">แสดง <?= count($rows) ?> คน (จากทั้งหมด <?= count($byStudent) ?> คนที่เช็คชื่อในวันนี้ตามตัวกรองที่เลือก)</div>
<?php if ($rows): ?>
<div class="table-wrap" data-print-section="ตารางรายชื่อประจำวัน"><div class="table-scroll">
<table id="daily-table">
  <thead>
    <tr>
      <th data-sort="number">ลำดับ</th>
      <th data-sort="text">รหัสนักศึกษา</th>
      <th data-sort="text">ชื่อ-สกุล</th>
      <th data-sort="text">เพศ</th>
      <th data-sort="text">แผนกวิชา</th>
      <th data-sort="text">ระดับชั้น/ปี</th>
      <th data-sort="text">เวลาเข้า</th>
      <th data-sort="text">เวลาออก</th>
      <th data-sort="text">ระยะเวลา</th>
      <th data-sort="text">สถานะ</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $i => $row): ?>
    <tr>
      <td data-value="<?= $i + 1 ?>"><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($row['student_id']) ?></td>
      <td><?= htmlspecialchars($row['prefix'] . $row['first_name'] . ' ' . $row['last_name']) ?></td>
      <td><?= htmlspecialchars($row['gender']) ?></td>
      <td><?= htmlspecialchars($row['department']) ?></td>
      <td><?= htmlspecialchars($row['level']) ?> ปีที่ <?= htmlspecialchars($row['year_level']) ?></td>
      <td><?= htmlspecialchars($row['time_in'] ? substr($row['time_in'], 0, 5) : '-') ?></td>
      <td><?= htmlspecialchars($row['time_out'] ? substr($row['time_out'], 0, 5) : '-') ?></td>
      <td><?= htmlspecialchars($row['duration']) ?></td>
      <td><span class="status-pill <?= $row['status'] === 'อยู่ในห้องสมุด' ? 'in' : 'out' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<script>
  (function () {
    var tbody = document.querySelector('#daily-table tbody');
    if (!tbody) return;
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    var ascByCol = {};
    document.querySelectorAll('#daily-table th[data-sort]').forEach(function (th, colIndex) {
      th.addEventListener('click', function () {
        var asc = ascByCol[colIndex] = !ascByCol[colIndex];
        var sorted = rows.slice().sort(function (a, b) {
          var ac = a.children[colIndex], bc = b.children[colIndex];
          var av = ac.getAttribute('data-value') || ac.textContent.trim();
          var bv = bc.getAttribute('data-value') || bc.textContent.trim();
          var an = parseFloat(av), bn = parseFloat(bv);
          var cmp = (!isNaN(an) && !isNaN(bn) && String(an) === av && String(bn) === bv)
            ? an - bn
            : av.localeCompare(bv, 'th');
          return asc ? cmp : -cmp;
        });
        sorted.forEach(function (tr) { tbody.appendChild(tr); });
      });
    });
  })();
</script>
<?php else: ?>
<p class="empty">ไม่มีนักศึกษาตรงกับตัวกรองที่เลือกในวันนี้</p>
<?php endif; ?>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    render_report_layout('รายงานประจำวัน', "รายงานเช็คชื่อประจำวันที่ $date", $content, $extraStyle, [
        'csv' => "/admin/reports/print/daily?date=$date&format=csv",
        'excel' => "/admin/reports/print/daily?date=$date&format=excel",
    ]);
}
