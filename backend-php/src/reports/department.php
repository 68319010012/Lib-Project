<?php
// Ports app.py's admin_report_department() + templates/report_department.html.
function handle_report_department(): void
{
    require_login();
    require_admin();

    $academicYear = $_GET['academic_year'] ?? null;

    $conditions = [];
    $params = [];
    if ($academicYear) {
        $conditions[] = 's.academic_year = ?';
        $params[] = $academicYear;
    }
    $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

    $conn = get_db_connection();
    $stmt = $conn->prepare(
        "SELECT s.department,
                COUNT(DISTINCT s.student_id) AS student_count,
                COUNT(c.log_id) AS checkin_count
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         $whereClause
         GROUP BY s.department
         ORDER BY s.department"
    );
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $headers = ['ลำดับ', 'แผนกวิชา', 'จำนวนนักศึกษาที่เช็คชื่อ (ไม่ซ้ำคน)', 'จำนวนครั้งที่เช็คอินรวม'];
        $exportRows = [];
        foreach ($rows as $i => $row) {
            $exportRows[] = [$i + 1, $row['department'] ?? '', (int) $row['student_count'], (int) $row['checkin_count']];
        }
        export_response('รายงานสรุปตามแผนกวิชา', [['รายงานสรุปตามแผนกวิชา', $headers, $exportRows]], $format);
    }

    ob_start();
    ?>
<?php if ($rows): ?>
<div class="table-wrap"><div class="table-scroll">
<table>
  <thead>
    <tr>
      <th>ลำดับ</th>
      <th>แผนกวิชา</th>
      <th>จำนวนนักศึกษาที่เช็คชื่อ (ไม่ซ้ำคน)</th>
      <th>จำนวนครั้งที่เช็คอินรวม</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($rows as $i => $row): ?>
    <tr>
      <td><?= $i + 1 ?></td>
      <td><?= htmlspecialchars($row['department'] ?? '') ?></td>
      <td><?= (int) $row['student_count'] ?></td>
      <td><?= (int) $row['checkin_count'] ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
</div></div>
<?php else: ?>
<p class="empty">ไม่มีข้อมูลการเช็คชื่อตามเงื่อนไขที่เลือก</p>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    $subtitle = 'รายงานสรุปการเช็คชื่อตามแผนกวิชา';
    if ($academicYear) {
        $subtitle .= " ปีการศึกษา $academicYear";
    }

    $qs = $academicYear ? '?academic_year=' . urlencode($academicYear) . '&' : '?';
    render_report_layout('รายงานสรุปตามแผนกวิชา', $subtitle, $content, '', [
        'csv' => "/admin/reports/print/department{$qs}format=csv",
        'excel' => "/admin/reports/print/department{$qs}format=excel",
    ]);
}
