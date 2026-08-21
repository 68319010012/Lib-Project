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

    // Beyond the three headline counts: how long a visit actually lasts, when
    // the room fills up, how the month moved week to week, and who is coming.
    // All of it is already computed for other reports — an executive summary
    // that answers only "how many" leaves the obvious follow-up questions to a
    // second document.
    $avgSessionMinutes = aggregate_avg_session_minutes($conn, $startDate, $endDate);
    $prevAvgSession = aggregate_avg_session_minutes($conn, $prevStart, $prevEnd);
    $sessionDelta = ($avgSessionMinutes !== null && $prevAvgSession)
        ? pct_delta((int) round($avgSessionMinutes), (int) round($prevAvgSession))
        : null;

    $hourly = aggregate_hourly($conn, $startDate, $endDate);
    $peakHour = $hourly['peak_hour'];
    $weekly = aggregate_weekly($conn, $startDate, $endDate);
    $levelRows = aggregate_breakdown_by($conn, 'level', $startDate, $endDate);
    $yearRows = aggregate_breakdown_by($conn, 'year_level', $startDate, $endDate);
    $gender = aggregate_gender_breakdown($conn, $startDate, $endDate);

    // Visits per student says whether a rise came from more people or the same
    // people coming back — the two need different responses.
    $visitsPerStudent = $agg['unique_students']
        ? round($agg['total_events'] / $agg['unique_students'], 1)
        : 0;

    $deltaClass = fn(?float $d) => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
    $deltaArrow = fn(?float $d) => $d === null ? '—' : ($d > 0 ? '↑' : ($d < 0 ? '↓' : '—'));

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
                    ['เวลาเฉลี่ยที่อยู่ในห้องสมุด', format_minutes_thai($avgSessionMinutes), $sessionDelta ?? '-'],
                    ['ช่วงเวลาที่คนใช้มากที่สุด', $peakHour ? sprintf('%02d:00 น. (%d รายการ)', $peakHour['hour'], $peakHour['count']) : '-', '-'],
                    ['เข้าใช้เฉลี่ยต่อคน', $visitsPerStudent, '-'],
                ],
            ],
            [
                'แผนกที่เข้าใช้มากที่สุด', ['อันดับ', 'แผนกวิชา', 'จำนวนรายการ'],
                array_map(fn($d, $i) => [$i + 1, $d['name'], $d['count']], $topDepts, array_keys($topDepts)),
            ],
            [
                'แนวโน้มรายสัปดาห์', ['สัปดาห์', 'จำนวนรายการ'],
                array_map(fn($w) => ['สัปดาห์ที่ ' . $w['week'], $w['count']], $weekly),
            ],
            [
                'แยกตามระดับชั้น', ['ระดับชั้น', 'จำนวนรายการ', 'จำนวนคน'],
                array_map(fn($r) => [$r['name'], $r['count'], $r['unique']], $levelRows),
            ],
            [
                'แยกตามชั้นปี', ['ชั้นปี', 'จำนวนรายการ', 'จำนวนคน'],
                array_map(fn($r) => ['ปีที่ ' . $r['name'], $r['count'], $r['unique']], $yearRows),
            ],
            [
                'สัดส่วนเพศผู้เข้าใช้', ['เพศ', 'จำนวนคน', 'สัดส่วน (%)'],
                [
                    ['ชาย', $gender['male'], $gender['male_pct']],
                    ['หญิง', $gender['female'], $gender['female_pct']],
                    ['ไม่ระบุ', $gender['unknown'], $gender['unknown_pct']],
                ],
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

  .section-head { font-size: 14px; margin: 18px 0 8px; color: #333; }
  /* table-layout:fixed because an auto-layout table grows to fit its content
     no matter what width:100% says — three of them side by side each wanted
     ~640px inside a ~440px column and pushed the page into a horizontal
     scroll. Fixed layout divides the column instead, and the cells wrap. */
  .mini-table { width: 100%; table-layout: fixed; border-collapse: collapse; margin-bottom: 4px; }
  .mini-table th, .mini-table td { overflow-wrap: anywhere; }
  .mini-table th {
    text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 4px 6px; font-weight: 700;
  }
  .mini-table td { font-size: 12.5px; padding: 4px 6px; border-bottom: 1px solid #eef2f7; }
  .mini-table .num { text-align: right; font-family: 'IBM Plex Mono', monospace; }
  /* Three side-by-side breakdowns on screen; they stack on a phone and the PDF
     stylesheet floats them (see layout.php) rather than using flex, which mPDF
     ignores. */
  .split-row { display: flex; flex-wrap: wrap; gap: 18px; }
  .split-col { flex: 1 1 210px; min-width: 200px; }

  @page { size: A4 portrait; margin: 14mm; }
  @media print { .month-filter { display: none; } }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="month-filter" method="get">
  <label for="month">เดือน</label>
  <?= render_month_select($month) ?>
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
  <div class="headline-card">
    <div class="label">เวลาเฉลี่ยที่อยู่ในห้องสมุด</div>
    <div class="value"><?= htmlspecialchars(format_minutes_thai($avgSessionMinutes)) ?></div>
    <?php if ($sessionDelta !== null): ?>
    <div class="delta <?= $deltaClass($sessionDelta) ?>"><?= $deltaArrow($sessionDelta) ?> <?= abs($sessionDelta) ?>% จากเดือนก่อน</div>
    <?php else: ?>
    <div class="delta flat">ต่อการเข้าใช้ 1 ครั้ง</div>
    <?php endif; ?>
  </div>
  <div class="headline-card">
    <div class="label">ช่วงเวลาที่คนใช้มากที่สุด</div>
    <div class="value"><?= $peakHour ? sprintf('%02d:00', $peakHour['hour']) : '-' ?></div>
    <div class="delta flat"><?= $peakHour ? $peakHour['count'] . ' รายการในช่วงนี้' : 'ไม่มีข้อมูล' ?></div>
  </div>
  <div class="headline-card">
    <div class="label">เข้าใช้เฉลี่ยต่อคน</div>
    <div class="value"><?= $visitsPerStudent ?></div>
    <div class="delta flat">ครั้ง / คน ตลอดเดือน</div>
  </div>
</div>

<?php if ($weekly): ?>
<h3 class="section-head">แนวโน้มรายสัปดาห์</h3>
<table class="mini-table">
  <thead><tr><th>สัปดาห์</th><th class="num">จำนวนรายการ</th><th class="num">สัดส่วน</th></tr></thead>
  <tbody>
    <?php $weekMax = max(array_column($weekly, 'count')) ?: 1; ?>
    <?php foreach ($weekly as $w): ?>
    <tr>
      <td>สัปดาห์ที่ <?= (int) $w['week'] ?></td>
      <td class="num"><?= number_format($w['count']) ?></td>
      <td class="num"><?= $agg['total_events'] ? round($w['count'] / $agg['total_events'] * 100, 1) : 0 ?>%</td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<div class="split-row">
  <?php if ($levelRows): ?>
  <div class="split-col">
    <h3 class="section-head">แยกตามระดับชั้น</h3>
    <table class="mini-table">
      <thead><tr><th>ระดับชั้น</th><th class="num">รายการ</th><th class="num">คน</th></tr></thead>
      <tbody>
        <?php foreach ($levelRows as $r): ?>
        <tr><td><?= htmlspecialchars($r['name']) ?></td><td class="num"><?= number_format($r['count']) ?></td><td class="num"><?= number_format($r['unique']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($yearRows): ?>
  <div class="split-col">
    <h3 class="section-head">แยกตามชั้นปี</h3>
    <table class="mini-table">
      <thead><tr><th>ชั้นปี</th><th class="num">รายการ</th><th class="num">คน</th></tr></thead>
      <tbody>
        <?php foreach ($yearRows as $r): ?>
        <tr><td>ปีที่ <?= htmlspecialchars($r['name']) ?></td><td class="num"><?= number_format($r['count']) ?></td><td class="num"><?= number_format($r['unique']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($gender['total']): ?>
  <div class="split-col">
    <h3 class="section-head">สัดส่วนเพศผู้เข้าใช้</h3>
    <table class="mini-table">
      <thead><tr><th>เพศ</th><th class="num">คน</th><th class="num">สัดส่วน</th></tr></thead>
      <tbody>
        <tr><td>ชาย</td><td class="num"><?= number_format($gender['male']) ?></td><td class="num"><?= $gender['male_pct'] ?>%</td></tr>
        <tr><td>หญิง</td><td class="num"><?= number_format($gender['female']) ?></td><td class="num"><?= $gender['female_pct'] ?>%</td></tr>
        <?php if ($gender['unknown']): ?>
        <tr><td>ไม่ระบุ</td><td class="num"><?= number_format($gender['unknown']) ?></td><td class="num"><?= $gender['unknown_pct'] ?>%</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
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

    // Redrawn as PNGs by GD because mPDF cannot measure the on-screen table
    // bars and rank rows — see layout.php's render_pdf_bar_chart().
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
    // No department chart here on purpose: this report's .rank-list already
    // renders in the PDF with real styling (layout.php's $pdfStyle), so a bar
    // image of the same three rows would only say it twice.

    render_report_layout('สรุปสำหรับผู้บริหาร', "สรุปสำหรับผู้บริหาร — เดือน $month", $content, $extraStyle, [
        'csv' => "/admin/reports/print/executive?month=$month&format=csv",
        'excel' => "/admin/reports/print/executive?month=$month&format=excel",
    ], $pdfCharts);
}
