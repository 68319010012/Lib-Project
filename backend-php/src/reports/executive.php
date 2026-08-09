<?php
// Ports app.py's admin_report_executive() + templates/report_executive.html.
function handle_report_executive(): void
{
    require_login();
    require_admin();

    $month = $_GET['month'] ?? date('Y-m');
    [$startDate, $endDate] = month_bounds($month);
    [$prevStart, $prevEnd] = month_bounds(previous_month($month));

    $conn = get_db_connection();
    $agg = aggregate_checkin_period($conn, $startDate, $endDate);
    $prevAgg = aggregate_checkin_period($conn, $prevStart, $prevEnd);

    $topDepts = ranked_breakdown($agg['dept_counts'], 3);
    $busiestDay = null;
    if ($agg['busiest_day']) {
        $busiestDay = [
            'day' => date('d/m', strtotime($agg['busiest_day']['day'])),
            'count' => $agg['busiest_day']['count'],
        ];
    }

    $totalDelta = pct_delta($agg['total_events'], $prevAgg['total_events']);
    $uniqueDelta = pct_delta($agg['unique_students'], $prevAgg['unique_students']);

    $deltaClass = fn(?float $d) => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
    $deltaArrow = fn(?float $d) => $d === null ? '—' : ($d > 0 ? '▲' : ($d < 0 ? '▼' : '—'));

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $sections = [
            [
                'สรุปสำหรับผู้บริหาร', ['ตัวชี้วัด', 'ค่า', '% เทียบเดือนก่อน'],
                [
                    ['จำนวนรายการทั้งหมด', $agg['total_events'], $totalDelta ?? '-'],
                    ['นักศึกษาที่มาใช้บริการ', $agg['unique_students'], $uniqueDelta ?? '-'],
                    ['เฉลี่ยต่อวัน', $agg['avg_daily'], '-'],
                    ['วันที่มีคนใช้มากที่สุด', $busiestDay ? "{$busiestDay['day']} ({$busiestDay['count']} รายการ)" : '-', '-'],
                ],
            ],
            [
                'แผนกที่เข้าใช้มากที่สุด', ['อันดับ', 'แผนกวิชา', 'จำนวนรายการ'],
                array_map(fn($d, $i) => [$i + 1, $d['name'], $d['count']], $topDepts, array_keys($topDepts)),
            ],
        ];
        export_response("สรุปสำหรับผู้บริหาร_$month", $sections, $format, [
            'ชื่อรายงาน' => 'สรุปสำหรับผู้บริหาร',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'เดือนที่กรอง' => $month,
        ]);
    }

    ob_start();
    ?>
<style>
  .month-filter {
    display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
    margin-bottom: 24px; background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc);
    border-radius: 999px; padding: 8px 8px 8px 16px; width: fit-content;
  }
  .month-filter label { font-size: 12px; color: var(--on-surface-variant, #555); font-weight: 600; }
  .month-filter input { border: 1px solid var(--outline-variant, #ccc); border-radius: 999px; padding: 6px 12px; font-size: 13px; }
  .month-filter button { border: none; background: #1e3a8a; color: #fff; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 999px; cursor: pointer; }
  .month-filter button:hover { filter: brightness(1.1); }

  .headline-grid {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px;
  }
  .headline-card {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc); border-radius: 16px;
    padding: 28px 24px; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,.05);
  }
  .headline-card .label { font-size: 13px; color: #666; font-weight: 700; margin-bottom: 10px; }
  .headline-card .value { font-family: 'IBM Plex Mono', monospace; font-size: 42px; font-weight: 800; color: #1e3a8a; line-height: 1; }
  .headline-card .delta { font-size: 13px; font-weight: 700; margin-top: 10px; }
  .headline-card .delta.up { color: #059669; }
  .headline-card .delta.down { color: #d97706; }
  .headline-card .delta.flat { color: #888; }

  .story-box {
    background: linear-gradient(135deg, #eaf1fb, #f1f5f9);
    border: 1px solid #cbd5e1; border-radius: 16px; padding: 24px 28px; margin-bottom: 28px;
  }
  .story-box p { font-size: 16px; line-height: 1.8; color: #1e293b; margin: 0; }
  .story-box b { color: #1e3a8a; }

  .rank-list { display: flex; flex-direction: column; gap: 10px; }
  .rank-row {
    display: flex; align-items: center; gap: 14px;
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc);
    border-radius: 12px; padding: 14px 18px;
  }
  .rank-num {
    width: 30px; height: 30px; border-radius: 9999px; background: #1e3a8a; color: #fff;
    display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0;
  }
  .rank-row .name { flex: 1; font-weight: 700; font-size: 14px; }
  .rank-row .count { font-family: 'IBM Plex Mono', monospace; font-weight: 700; color: #1e3a8a; font-size: 15px; }

  .empty-note { color: #888; font-size: 13px; font-style: italic; }
  @page { size: A4 portrait; margin: 14mm; }
  @media print { .month-filter { display: none; } }
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

<div class="headline-grid">
  <div class="headline-card">
    <div class="label">จำนวนรายการเช็คชื่อทั้งหมด</div>
    <div class="value"><?= $agg['total_events'] ?></div>
    <?php if ($totalDelta !== null): ?>
    <div class="delta <?= $deltaClass($totalDelta) ?>"><?= $deltaArrow($totalDelta) ?> <?= abs($totalDelta) ?>% จากเดือนก่อน</div>
    <?php endif; ?>
  </div>
  <div class="headline-card">
    <div class="label">นักศึกษาที่มาใช้บริการ</div>
    <div class="value"><?= $agg['unique_students'] ?></div>
    <?php if ($uniqueDelta !== null): ?>
    <div class="delta <?= $deltaClass($uniqueDelta) ?>"><?= $deltaArrow($uniqueDelta) ?> <?= abs($uniqueDelta) ?>% จากเดือนก่อน</div>
    <?php endif; ?>
  </div>
  <div class="headline-card">
    <div class="label">เฉลี่ยต่อวัน</div>
    <div class="value"><?= $agg['avg_daily'] ?></div>
    <div class="delta flat">รายการ / วันที่มีข้อมูล</div>
  </div>
</div>

<div class="story-box">
  <p>
    ในเดือน <b><?= htmlspecialchars($month) ?></b> ห้องสมุดมีการเช็คชื่อเข้า-ออกรวม <b><?= $agg['total_events'] ?> รายการ</b>
    จากนักศึกษา <b><?= $agg['unique_students'] ?> คน</b> เฉลี่ย <b><?= $agg['avg_daily'] ?> รายการต่อวัน</b>
    <?php if ($busiestDay): ?>โดยวันที่มีผู้ใช้บริการมากที่สุดคือ <b><?= htmlspecialchars($busiestDay['day']) ?></b> (<?= $busiestDay['count'] ?> รายการ)<?php endif; ?>
    <?php if ($topDepts): ?> และแผนกที่เข้าใช้บริการมากที่สุดคือ <b><?= htmlspecialchars($topDepts[0]['name']) ?></b> (<?= $topDepts[0]['count'] ?> รายการ)<?php endif; ?>
  </p>
</div>

<h3 style="font-size:15px; margin:0 0 12px; color:#333;">แผนกที่เข้าใช้บริการมากที่สุด</h3>
<?php if ($topDepts): ?>
<div class="rank-list">
  <?php foreach ($topDepts as $i => $dept): ?>
  <div class="rank-row">
    <div class="rank-num"><?= $i + 1 ?></div>
    <div class="name"><?= htmlspecialchars($dept['name']) ?></div>
    <div class="count"><?= $dept['count'] ?> รายการ</div>
  </div>
  <?php endforeach; ?>
</div>
<?php else: ?>
<p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในเดือนนี้</p>
<?php endif; ?>

    <?php
    $content = ob_get_clean();

    render_report_layout('สรุปสำหรับผู้บริหาร', "สรุปสำหรับผู้บริหาร — เดือน $month", $content, $extraStyle, [
        'csv' => "/admin/reports/print/executive?month=$month&format=csv",
        'excel' => "/admin/reports/print/executive?month=$month&format=excel",
    ]);
}
