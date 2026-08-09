<?php
// Ports app.py's admin_report_monthly() + templates/report_monthly.html.
function handle_report_monthly(): void
{
    require_login();
    require_admin();

    $month = $_GET['month'] ?? date('Y-m');

    $conn = get_db_connection();
    $stmt = $conn->prepare(
        "SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level,
                COUNT(CASE WHEN c.type = 'in' THEN 1 END) AS checkin_count,
                MAX(c.timestamp) AS last_checkin
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE DATE_FORMAT(c.timestamp, '%Y-%m') = ?
         GROUP BY s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level
         ORDER BY s.student_id"
    );
    $stmt->execute([$month]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$row) {
        $row['gender'] = gender_from_prefix($row['prefix']);
        // PDO already returns DATETIME as "Y-m-d H:i:s" — same format Python's
        // strftime('%Y-%m-%d %H:%M:%S') produced, so no reformatting needed.
        $row['last_checkin'] = $row['last_checkin'] ?? '-';
    }
    unset($row);

    ob_start();
    ?>
<div class="meta">จำนวนนักศึกษาที่มีการเช็คชื่อ: <?= count($rows) ?> คน</div>
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
      <th>จำนวนครั้งที่เช็คอิน</th>
      <th>เช็คอินล่าสุด</th>
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
<p class="empty">ไม่มีข้อมูลการเช็คชื่อในเดือนที่เลือก</p>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    render_report_layout('รายงานสรุปรายเดือน', "รายงานสรุปการเช็คชื่อประจำเดือน $month", $content);
}
