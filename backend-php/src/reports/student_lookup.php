<?php
// New report (not in the original app.py) — the report-system redesign's §6
// "REPORT STUDENT/USER". Admin-only (require_admin(), same as every other
// report here) — per the confirmed decision not to build a separate `staff`
// permission tier in this pass, every report stays gated the same way, which
// already satisfies "not all users should see all students' visit history".
function handle_report_student_lookup(): void
{
    require_login();
    require_admin();

    $conn = get_db_connection();
    $search = trim((string) ($_GET['search'] ?? ''));
    $selectedStudentId = trim((string) ($_GET['student_id'] ?? ''));

    $searchResults = [];
    if ($selectedStudentId === '' && $search !== '') {
        $like = "%$search%";
        $stmt = $conn->prepare(
            'SELECT s.student_id, s.prefix, s.first_name, s.last_name, s.department, s.level, s.year_level
             FROM students s
             WHERE s.student_id LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ? OR s.department LIKE ?
             ORDER BY LENGTH(s.student_id), s.student_id
             LIMIT 50'
        );
        $stmt->execute([$like, $like, $like, $like]);
        $searchResults = $stmt->fetchAll();
    }

    $student = null;
    $history = [];
    $stats = null;
    if ($selectedStudentId !== '') {
        $stmt = $conn->prepare('SELECT * FROM students WHERE student_id = ?');
        $stmt->execute([$selectedStudentId]);
        $student = $stmt->fetch() ?: null;

        if ($student) {
            // Most recent 200 events — a report-page bound, not a hard system
            // limit; avg-duration below is computed only from what's paired
            // within this window.
            $stmt = $conn->prepare(
                'SELECT c.type, c.timestamp
                 FROM checkin_logs c
                 JOIN users u ON u.user_id = c.user_id
                 WHERE u.student_id = ?
                 ORDER BY c.timestamp DESC
                 LIMIT 200'
            );
            $stmt->execute([$selectedStudentId]);
            $history = $stmt->fetchAll();

            $totalVisits = 0;
            $durations = [];
            $lastIn = null;
            foreach (array_reverse($history) as $log) {
                if ($log['type'] === 'in') {
                    $totalVisits++;
                    $lastIn = strtotime($log['timestamp']);
                } elseif ($log['type'] === 'out' && $lastIn !== null) {
                    $durations[] = (strtotime($log['timestamp']) - $lastIn) / 60;
                    $lastIn = null;
                }
            }
            $avgDurationMin = $durations ? (int) round(array_sum($durations) / count($durations)) : null;

            $stats = [
                'total_visits' => $totalVisits,
                'avg_duration_min' => $avgDurationMin,
                'last_visit' => $history ? $history[0]['timestamp'] : null,
            ];
        }
    }

    $format = $_GET['format'] ?? null;
    if ($format === 'csv' || $format === 'excel') {
        if (!$student) {
            json_error('เลือกนักศึกษาก่อนจึงจะดาวน์โหลดได้', 400);
        }
        $headers = ['วันเวลา', 'ประเภท'];
        $exportRows = array_map(fn($log) => [
            str_replace('T', ' ', to_isoformat($log['timestamp'])),
            $log['type'] === 'in' ? 'เช็คอิน' : 'เช็คเอาต์',
        ], $history);
        export_response("ประวัติการใช้ห้องสมุด_{$student['student_id']}", [['ประวัติการใช้ห้องสมุด', $headers, $exportRows]], $format, [
            'ชื่อรายงาน' => 'ประวัติการใช้ห้องสมุดรายบุคคล',
            'วันที่สร้างรายงาน' => date('d/m/Y H:i'),
            'รหัสนักศึกษา' => $student['student_id'],
            'ชื่อ-สกุล' => $student['prefix'] . $student['first_name'] . ' ' . $student['last_name'],
        ]);
    }

    ob_start();
    ?>
<style>
  .search-results { display: flex; flex-direction: column; gap: 8px; margin-top: 16px; }
  .search-results a.result-row {
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    padding: 12px 16px; border: 1px solid var(--outline-variant, #ccc); border-radius: 10px;
    background: var(--surface-white, #fff); text-decoration: none; color: inherit;
  }
  .search-results a.result-row:hover { border-color: var(--primary, #1a2947); }

  /* Type-ahead suggestions. The form still submits and still renders the
     server-side result list, so this only saves a round trip — with JS off,
     or if the request fails, nothing is lost. */
  .ac-wrap { position: relative; }
  .ac-list {
    position: absolute; z-index: 40; left: 0; right: 0; top: 100%; margin-top: 4px;
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc);
    border-radius: 10px; box-shadow: 0 8px 24px -8px rgba(2,6,23,.25);
    max-height: 300px; overflow-y: auto; padding: 4px;
  }
  .ac-list[hidden] { display: none; }
  .ac-item {
    display: block; width: 100%; text-align: left; padding: 8px 10px; border: 0;
    background: transparent; border-radius: 7px; cursor: pointer; font: inherit; color: inherit;
  }
  .ac-item:hover, .ac-item.is-active { background: rgba(30,58,138,.08); }
  .ac-item .n { font-weight: 700; font-size: 13px; color: var(--primary, #1a2947); }
  .ac-item .m { font-size: 11.5px; color: #666; }
  .ac-empty { padding: 10px; font-size: 12.5px; color: #666; }
  .search-results .name { font-weight: 700; font-size: 14px; color: var(--primary, #1a2947); }
  .search-results .meta { font-size: 12px; color: #666; }
  .student-header {
    display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px;
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #ccc); border-radius: 14px;
    padding: 20px 24px; margin-bottom: 18px;
  }
  .student-header h2 { margin: 0 0 4px; font-size: 18px; color: var(--primary, #1a2947); }
  .student-header .sub { font-size: 13px; color: #666; }
  .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin: 0 0 20px; }
  .kpi-card {
    border: 1px solid var(--outline-variant, #ccc); border-radius: 10px; padding: 14px 16px;
    background: var(--surface-white, #fff); box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .kpi-card .label { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #666; margin-bottom: 6px; }
  .kpi-card .value { font-size: 20px; font-weight: bold; color: var(--primary, #1a2947); }
  .type-pill { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
  .type-pill.in { background: #dcfce7; color: #166534; }
  .type-pill.out { background: #f1f5f9; color: #6b6153; }
</style>
    <?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<form class="filter-bar" method="get" autocomplete="off">
  <div class="field ac-wrap" style="min-width:260px;">
    <label for="search">ค้นหารหัสนักศึกษา / ชื่อ / แผนกวิชา</label>
    <input type="text" id="search" name="search" value="<?= htmlspecialchars($search) ?>"
           placeholder="พิมพ์บางส่วนก็พอ เช่น 680011 หรือ สมชาย"
           role="combobox" aria-expanded="false" aria-controls="ac-list" aria-autocomplete="list">
    <div class="ac-list" id="ac-list" role="listbox" hidden></div>
  </div>
  <button type="submit">ค้นหา</button>
  <?php if ($student): ?>
  <a class="reset-link" href="/admin/reports/print/student"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ค้นหาใหม่</a>
  <?php endif; ?>
</form>

<?php if ($selectedStudentId !== '' && !$student): ?>
<div class="empty">ไม่พบนักศึกษารหัส <?= htmlspecialchars($selectedStudentId) ?></div>

<?php elseif ($student): ?>
<div class="student-header">
  <div>
    <h2><?= htmlspecialchars($student['prefix'] . $student['first_name'] . ' ' . $student['last_name']) ?></h2>
    <div class="sub">
      รหัส <?= htmlspecialchars($student['student_id']) ?> ·
      <?= htmlspecialchars($student['department'] ?? '-') ?> ·
      <?= htmlspecialchars($student['level'] ?? '-') ?> ปีที่ <?= htmlspecialchars($student['year_level'] ?? '-') ?>
      <?php if ($student['room']): ?> · ห้อง <?= htmlspecialchars($student['room']) ?><?php endif; ?>
    </div>
  </div>
</div>

<div class="kpi-grid">
  <div class="kpi-card">
    <div class="label">จำนวนครั้งที่เข้าใช้</div>
    <div class="value"><?= $stats['total_visits'] ?></div>
  </div>
  <div class="kpi-card">
    <div class="label">ระยะเวลาเฉลี่ยต่อครั้ง</div>
    <div class="value"><?= $stats['avg_duration_min'] !== null ? "{$stats['avg_duration_min']} นาที" : '-' ?></div>
  </div>
  <div class="kpi-card">
    <div class="label">เข้าใช้ล่าสุด</div>
    <div class="value" style="font-size:15px;"><?= $stats['last_visit'] ? htmlspecialchars(str_replace('T', ' ', to_isoformat($stats['last_visit']))) : '-' ?></div>
  </div>
</div>

<div class="panel" style="border:1px solid var(--outline-variant); border-radius:10px; padding:16px 18px; background:var(--surface-white);">
  <h3 style="font-size:14px; margin:0 0 12px; color:#333;">ประวัติการเข้าใช้ (ล่าสุด <?= count($history) ?> รายการ)</h3>
  <?php if ($history): ?>
  <div class="table-wrap"><div class="table-scroll">
  <table>
    <thead><tr><th>วันเวลา</th><th>ประเภท</th></tr></thead>
    <tbody>
      <?php foreach ($history as $log): ?>
      <tr>
        <td><?= htmlspecialchars(str_replace('T', ' ', to_isoformat($log['timestamp']))) ?></td>
        <td><span class="type-pill <?= $log['type'] === 'in' ? 'in' : 'out' ?>"><?= $log['type'] === 'in' ? 'เช็คอิน' : 'เช็คเอาต์' ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div></div>
  <?php else: ?>
  <p class="empty-note">นักศึกษาคนนี้ยังไม่มีประวัติการเช็คชื่อ</p>
  <?php endif; ?>
</div>

<?php elseif ($search !== ''): ?>
<div class="search-results">
  <?php if ($searchResults): ?>
  <?php foreach ($searchResults as $r): ?>
  <a class="result-row" href="/admin/reports/print/student?student_id=<?= urlencode($r['student_id']) ?>">
    <div>
      <div class="name"><?= htmlspecialchars($r['prefix'] . $r['first_name'] . ' ' . $r['last_name']) ?></div>
      <div class="meta">รหัส <?= htmlspecialchars($r['student_id']) ?> · <?= htmlspecialchars($r['department'] ?? '-') ?> · <?= htmlspecialchars($r['level'] ?? '-') ?> ปีที่ <?= htmlspecialchars($r['year_level'] ?? '-') ?></div>
    </div>
    <span class="material-symbols-outlined" style="font-size:18px; color:var(--primary, #1a2947);">chevron_right</span>
  </a>
  <?php endforeach; ?>
  <?php else: ?>
  <div class="empty">ไม่พบนักศึกษาที่ตรงกับคำค้นหา "<?= htmlspecialchars($search) ?>"</div>
  <?php endif; ?>
</div>
<?php else: ?>
<p class="filter-note" style="margin-top:16px;">พิมพ์รหัสนักศึกษา ชื่อ หรือแผนกวิชาเพียงบางส่วน รายชื่อจะขึ้นให้เลือกทันที ไม่ต้องพิมพ์ให้ครบ</p>
<?php endif; ?>

<?php // Not emitted for the PDF export: $content is handed straight to mPDF,
      // which has no JS engine and can spill an unrecognised block onto the
      // page as literal text. Hiding .filter-bar covers the markup, not this. ?>
<?php if (($_GET['format'] ?? '') !== 'pdf'): ?>
<script>
(function () {
  var input = document.getElementById('search');
  var list  = document.getElementById('ac-list');
  if (!input || !list) return;

  var items = [], active = -1, timer = null, seq = 0;

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function close() {
    list.hidden = true; list.innerHTML = ''; items = []; active = -1;
    input.setAttribute('aria-expanded', 'false');
  }

  function go(studentId) {
    window.location.href = '/admin/reports/print/student?student_id=' + encodeURIComponent(studentId);
  }

  function highlight(i) {
    var nodes = list.querySelectorAll('.ac-item');
    nodes.forEach(function (n) { n.classList.remove('is-active'); });
    if (i >= 0 && nodes[i]) { nodes[i].classList.add('is-active'); nodes[i].scrollIntoView({ block: 'nearest' }); }
    active = i;
  }

  function render(rows) {
    if (!rows.length) {
      list.innerHTML = '<div class="ac-empty">ไม่พบนักศึกษาที่ตรงกับคำค้นหานี้</div>';
      list.hidden = false; items = []; active = -1;
      input.setAttribute('aria-expanded', 'true');
      return;
    }
    // Capped at 8: this is a picker, not the result list — the form still
    // submits for the full set.
    items = rows.slice(0, 8);
    list.innerHTML = items.map(function (r, i) {
      var name = (r.prefix || '') + r.first_name + ' ' + r.last_name;
      var meta = 'รหัส ' + r.student_id + ' · ' + (r.department || '-') +
                 ' · ' + (r.level || '-') + ' ปีที่ ' + (r.year_level || '-');
      return '<button type="button" class="ac-item" role="option" data-i="' + i + '">' +
             '<span class="n">' + esc(name) + '</span><br><span class="m">' + esc(meta) + '</span></button>';
    }).join('');
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
    list.querySelectorAll('.ac-item').forEach(function (btn) {
      btn.addEventListener('mousedown', function (e) {   // before blur
        e.preventDefault();
        go(items[Number(btn.dataset.i)].student_id);
      });
    });
  }

  function search(term) {
    var mine = ++seq;
    fetch('/admin/members?search=' + encodeURIComponent(term), { credentials: 'include' })
      .then(function (r) { return r.ok ? r.json() : []; })
      .then(function (rows) {
        // Drop a slow response that a newer keystroke has already superseded.
        if (mine !== seq) return;
        render(Array.isArray(rows) ? rows : []);
      })
      .catch(function () { if (mine === seq) close(); });
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    var term = input.value.trim();
    if (term.length < 2) { close(); return; }
    timer = setTimeout(function () { search(term); }, 250);
  });

  input.addEventListener('keydown', function (e) {
    if (list.hidden || !items.length) return;
    if (e.key === 'ArrowDown') { e.preventDefault(); highlight((active + 1) % items.length); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); highlight((active - 1 + items.length) % items.length); }
    else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); go(items[active].student_id); }
    else if (e.key === 'Escape') { close(); }
  });

  input.addEventListener('blur', function () { setTimeout(close, 120); });
})();
</script>
<?php endif; ?>
    <?php
    $content = ob_get_clean();

    $subtitle = $student
        ? 'ประวัติการใช้ห้องสมุด — ' . $student['prefix'] . $student['first_name'] . ' ' . $student['last_name']
        : 'ค้นหาประวัติการใช้ห้องสมุดรายบุคคล';

    render_report_layout('รายงานผู้ใช้บริการ', $subtitle, $content, $extraStyle, $student ? [
        'csv' => "/admin/reports/print/student?student_id=$selectedStudentId&format=csv",
        'excel' => "/admin/reports/print/student?student_id=$selectedStudentId&format=excel",
    ] : []);
}
