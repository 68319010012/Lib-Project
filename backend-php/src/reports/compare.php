<?php
// Ports app.py's admin_report_compare() + templates/report_compare.html.
function handle_report_compare(): void
{
    require_login();
    require_admin();

    $thisMonth = date('Y-m');
    $monthA = $_GET['month_a'] ?? previous_month($thisMonth);
    $monthB = $_GET['month_b'] ?? $thisMonth;

    $conn = get_db_connection();
    [$startA, $endA] = month_bounds($monthA);
    [$startB, $endB] = month_bounds($monthB);
    $aggA = aggregate_checkin_period($conn, $startA, $endA);
    $aggB = aggregate_checkin_period($conn, $startB, $endB);

    $deltaClass = fn(?float $d) => $d === null ? 'flat' : ($d > 0 ? 'up' : ($d < 0 ? 'down' : 'flat'));
    $deltaArrow = fn(?float $d) => $d === null ? '—' : ($d > 0 ? '▲' : ($d < 0 ? '▼' : '—'));

    $metrics = [
        [
            'label' => 'จำนวนรายการทั้งหมด',
            'a' => $aggA['total_events'],
            'b' => $aggB['total_events'],
            'delta' => pct_delta($aggB['total_events'], $aggA['total_events']),
        ],
        [
            'label' => 'นักศึกษาไม่ซ้ำคน',
            'a' => $aggA['unique_students'],
            'b' => $aggB['unique_students'],
            'delta' => pct_delta($aggB['unique_students'], $aggA['unique_students']),
        ],
        [
            'label' => 'เฉลี่ยต่อวัน',
            'a' => $aggA['avg_daily'],
            'b' => $aggB['avg_daily'],
            'delta' => pct_delta($aggB['avg_daily'], $aggA['avg_daily']),
        ],
    ];

    $deptNames = array_unique(array_merge(array_keys($aggA['dept_counts']), array_keys($aggB['dept_counts'])));
    sort($deptNames);
    $deptCompare = [];
    foreach ($deptNames as $name) {
        $deptCompare[] = [
            'name' => $name,
            'a' => $aggA['dept_counts'][$name] ?? 0,
            'b' => $aggB['dept_counts'][$name] ?? 0,
        ];
    }
    usort($deptCompare, fn($x, $y) => ($y['a'] + $y['b']) <=> ($x['a'] + $x['b']));
    $deptCompare = array_slice($deptCompare, 0, 8);
    $maxDeptCompare = 0;
    foreach ($deptCompare as $row) {
        $maxDeptCompare = max($maxDeptCompare, $row['a'], $row['b']);
    }
    foreach ($deptCompare as &$row) {
        $row['pct_a'] = $maxDeptCompare ? (int) round(($row['a'] / $maxDeptCompare) * 100) : 0;
        $row['pct_b'] = $maxDeptCompare ? (int) round(($row['b'] / $maxDeptCompare) * 100) : 0;
    }
    unset($row);

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        $sections = [
            [
                'เปรียบเทียบตัวชี้วัด', ['ตัวชี้วัด', $monthA, $monthB, '% ผลต่าง'],
                array_map(fn($m) => [$m['label'], $m['a'], $m['b'], $m['delta'] ?? '-'], $metrics),
            ],
            [
                'เปรียบเทียบตามแผนกวิชา', ['แผนกวิชา', $monthA, $monthB],
                array_map(fn($d) => [$d['name'], $d['a'], $d['b']], $deptCompare),
            ],
        ];
        export_response("เปรียบเทียบ_{$monthA}_กับ_{$monthB}", $sections, $format, [
            'ชื่อรายงาน' => 'เปรียบเทียบช่วงเวลา',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'ช่วง A' => $monthA,
            'ช่วง B' => $monthB,
        ]);
    }

    ob_start();
    ?>
<style>
  .compare-filter {
    display: flex; flex-wrap: wrap; align-items: center; gap: 14px;
    margin-bottom: 24px; background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc);
    border-radius: 999px; padding: 8px 18px;
  }
  .compare-filter .field { display: flex; align-items: center; gap: 8px; }
  .compare-filter label { font-size: 12px; color: var(--on-surface-variant, #555); font-weight: 600; }
  .compare-filter input { border: 1px solid var(--outline-variant, #ccc); border-radius: 999px; padding: 6px 12px; font-size: 13px; }
  .compare-filter .vs { font-size: 13px; font-weight: 800; color: #1e3a8a; }
  .compare-filter button { border: none; background: #1e3a8a; color: #fff; font-weight: 700; font-size: 13px; padding: 8px 16px; border-radius: 999px; cursor: pointer; }
  .compare-filter button:hover { filter: brightness(1.1); }

  .metric-table {
    width: 100%; border-collapse: collapse; background: var(--surface-white, #fff);
    border: 1px solid var(--outline-variant, #ccc); border-radius: 12px; overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,.03); margin-bottom: 28px;
  }
  .metric-table th, .metric-table td { padding: 14px 18px; text-align: left; font-size: 14px; }
  .metric-table th { background: #1e3a8a; color: #fff; font-size: 12px; text-transform: uppercase; letter-spacing: .03em; }
  .metric-table th.num, .metric-table td.num { text-align: right; font-family: 'IBM Plex Mono', monospace; }
  .metric-table tbody tr:nth-child(even) { background: var(--surface, #f8fafc); }
  .metric-table .delta { font-weight: 700; }
  .metric-table .delta.up { color: #059669; }
  .metric-table .delta.down { color: #d97706; }
  .metric-table .delta.flat { color: #888; }

  .panel {
    border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 18px 20px;
    background: var(--surface-white, #fff); box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .panel h3 { font-size: 14px; margin: 0 0 16px; color: #333; }
  .compare-legend { display: flex; gap: 18px; font-size: 12px; color: #666; margin-bottom: 14px; }
  .compare-legend span { display: flex; align-items: center; gap: 6px; }
  .compare-legend .swatch { width: 12px; height: 12px; border-radius: 3px; }
  .swatch.a { background: #94a3b8; }
  .swatch.b { background: #1e3a8a; }

  .dept-compare-row { margin-bottom: 14px; }
  .dept-compare-row .dept-name { font-size: 13px; font-weight: 700; margin-bottom: 6px; }
  .dept-compare-row .bar-line { display: flex; align-items: center; gap: 8px; margin-bottom: 3px; }
  .dept-compare-row .bar-track { flex: 1; background: #eee; border-radius: 4px; height: 12px; overflow: hidden; }
  .dept-compare-row .bar-fill { height: 100%; }
  .dept-compare-row .bar-fill.a { background: #94a3b8; }
  .dept-compare-row .bar-fill.b { background: #1e3a8a; }
  .dept-compare-row .bar-count { font-family: 'IBM Plex Mono', monospace; font-size: 12px; width: 32px; text-align: right; flex-shrink: 0; }

  .empty-note { color: #888; font-size: 12px; font-style: italic; }
  @page { size: A4 landscape; margin: 12mm; }
  @media print { .compare-filter { display: none; } }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="compare-filter" method="get">
  <div class="field">
    <label for="month_a">ช่วง A</label>
    <input type="month" id="month_a" name="month_a" value="<?= htmlspecialchars($monthA) ?>">
  </div>
  <span class="vs">เทียบกับ</span>
  <div class="field">
    <label for="month_b">ช่วง B</label>
    <input type="month" id="month_b" name="month_b" value="<?= htmlspecialchars($monthB) ?>">
  </div>
  <button type="submit">เปรียบเทียบ</button>
</form>

<table class="metric-table">
  <thead>
    <tr>
      <th>ตัวชี้วัด</th>
      <th class="num"><?= htmlspecialchars($monthA) ?></th>
      <th class="num"><?= htmlspecialchars($monthB) ?></th>
      <th class="num">ผลต่าง</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($metrics as $m): ?>
    <tr>
      <td><?= htmlspecialchars($m['label']) ?></td>
      <td class="num"><?= $m['a'] ?></td>
      <td class="num"><?= $m['b'] ?></td>
      <td class="num">
        <?php if ($m['delta'] === null): ?>
        <span class="delta flat">—</span>
        <?php else: ?>
        <span class="delta <?= $deltaClass($m['delta']) ?>"><?= $deltaArrow($m['delta']) ?> <?= abs($m['delta']) ?>%</span>
        <?php endif; ?>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<div class="panel">
  <h3>เปรียบเทียบตามแผนกวิชา</h3>
  <?php if ($deptCompare): ?>
  <div class="compare-legend">
    <span><span class="swatch a"></span><?= htmlspecialchars($monthA) ?></span>
    <span><span class="swatch b"></span><?= htmlspecialchars($monthB) ?></span>
  </div>
  <?php foreach ($deptCompare as $dept): ?>
  <div class="dept-compare-row">
    <div class="dept-name"><?= htmlspecialchars($dept['name']) ?></div>
    <div class="bar-line">
      <div class="bar-track"><div class="bar-fill a" style="width: <?= $dept['pct_a'] ?>%;"></div></div>
      <div class="bar-count"><?= $dept['a'] ?></div>
    </div>
    <div class="bar-line">
      <div class="bar-track"><div class="bar-fill b" style="width: <?= $dept['pct_b'] ?>%;"></div></div>
      <div class="bar-count"><?= $dept['b'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php else: ?>
  <p class="empty-note">ไม่มีข้อมูลการเช็คชื่อในช่วงที่เลือก</p>
  <?php endif; ?>
</div>

    <?php
    $content = ob_get_clean();

    render_report_layout('เปรียบเทียบช่วงเวลา', "เปรียบเทียบการเช็คชื่อ — $monthA เทียบกับ $monthB", $content, $extraStyle, [
        'csv' => "/admin/reports/print/compare?month_a=$monthA&month_b=$monthB&format=csv",
        'excel' => "/admin/reports/print/compare?month_a=$monthA&month_b=$monthB&format=excel",
    ]);
}
