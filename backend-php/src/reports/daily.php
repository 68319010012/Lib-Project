<?php
// Ports app.py's admin_report_daily() + templates/report_daily.html.
function handle_report_daily(): void
{
    require_login();
    require_admin();

    $date = $_GET['date'] ?? date('Y-m-d');

    $conn = get_db_connection();
    $stmt = $conn->prepare(
        'SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
                c.type, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE DATE(c.timestamp) = ?
         ORDER BY s.student_id, c.timestamp'
    );
    $stmt->execute([$date]);
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
        // time_out keeps the LAST "out" (unconditional overwrite) — matches app.py exactly.
        if ($log['type'] === 'in' && $byStudent[$sid]['time_in'] === null) {
            $byStudent[$sid]['time_in'] = $timeStr;
        }
        if ($log['type'] === 'out') {
            $byStudent[$sid]['time_out'] = $timeStr;
        }
    }

    $rows = array_values($byStudent);
    usort($rows, fn($a, $b) => $a['student_id'] <=> $b['student_id']);

    ob_start();
    ?>
<div class="meta">จำนวนนักศึกษาที่เช็คชื่อ: <?= count($rows) ?> คน</div>
<?php if ($rows): ?>
<div class="table-wrap"><div class="table-scroll">
<table>
  <thead>
    <tr>
      <th>ลำดับ</th>
      <th>รหัสนักศึกษา</th>
      <th>ชื่อ-สกุล</th>
      <th>เพศ</th>
      <th>แผนกวิชา</th>
      <th>ระดับชั้น/ปี</th>
      <th>เวลาเข้า</th>
      <th>เวลาออก</th>
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
      <td><?= htmlspecialchars($row['time_in'] ?? '-') ?></td>
      <td><?= htmlspecialchars($row['time_out'] ?? '-') ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php else: ?>
<p class="empty">ไม่มีข้อมูลการเช็คชื่อในวันที่เลือก</p>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    render_report_layout('รายงานประจำวัน', "รายงานเช็คชื่อประจำวันที่ $date", $content);
}
