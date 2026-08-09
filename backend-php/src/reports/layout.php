<?php
// Ports templates/base_print.html. Jinja's {% extends %}/{% block %} becomes
// plain output buffering: each report file builds its $content (and optional
// $extraStyle) via ob_start()/ob_get_clean(), then calls render_report_layout().
// $exportUrls is an optional ['csv' => url, 'excel' => url] map — omitted by
// dashboard.php (a print-only 1-pager, not a row-per-record report).
function render_report_layout(string $title, string $subtitle, string $content, string $extraStyle = '', array $exportUrls = []): void
{
    header('Content-Type: text/html; charset=utf-8');

    // Shared by every report (was dashboard.php-only before) — its own
    // @page rule, if it sets one via $extraStyle, is injected after this
    // block and wins, same override rule the file header comment already
    // documents for other @page usages.
    $orientation = ($_GET['orientation'] ?? 'portrait') === 'landscape' ? 'landscape' : 'portrait';
    $orientationUrl = function (string $value): string {
        $params = $_GET;
        $params['orientation'] = $value;
        return '?' . http_build_query($params);
    };
    ?>
<!doctype html>
<html lang="th">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($title) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;500;700;800&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
<style>
  :root {
    --primary: #1e3a8a;
    --primary-container: #1e40af;
    --secondary: #2563eb;
    --surface: #f8fafc;
    --surface-white: #ffffff;
    --outline-variant: #cbd5e1;
    --on-surface-variant: #475569;
    --text-secondary: #475569;
  }
  * { box-sizing: border-box; }
  body {
    font-family: 'Noto Sans Thai', 'Tahoma', 'Leelawadee UI', sans-serif;
    margin: 0;
    color: #0f172a;
    background: var(--surface);
  }
  .material-symbols-outlined { font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24; vertical-align: middle; }

  .toolbar {
    display: flex; flex-wrap: wrap; align-items: center; gap: 12px;
    padding: 14px 20px;
    background: var(--surface-white);
    border-bottom: 1px solid var(--outline-variant);
  }
  .toolbar button, .toolbar a {
    font-size: 13px; font-weight: 700;
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px;
    border-radius: 999px;
    text-decoration: none;
    cursor: pointer; border: none;
  }
  .toolbar button { background: var(--primary); color: #fff; }
  .toolbar button:hover { filter: brightness(1.1); }
  .toolbar a { background: var(--surface); color: var(--primary); border: 1px solid var(--outline-variant); }
  .toolbar a:hover { background: #e8f0fe; }

  header.report-head {
    background: linear-gradient(135deg, var(--primary-container) 0%, #0f172a 100%);
    color: #fff;
    padding: 28px 24px 22px;
  }
  header.report-head h1 { font-size: 20px; margin: 0 0 4px; font-weight: 800; }
  header.report-head h2 { font-size: 14px; font-weight: 400; margin: 0; opacity: .85; }

  main.report-body {
    max-width: 1100px;
    margin: 0 auto;
    padding: 24px 20px 48px;
  }

  .meta {
    display: inline-block;
    margin-bottom: 14px;
    font-size: 13px;
    color: var(--on-surface-variant);
    background: var(--surface-white);
    border: 1px solid var(--outline-variant);
    border-radius: 999px;
    padding: 6px 14px;
  }

  .table-wrap {
    background: var(--surface-white);
    border: 1px solid var(--outline-variant);
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 12px rgba(0,0,0,.04);
  }
  .table-scroll { overflow-x: auto; }
  table { width: 100%; border-collapse: collapse; min-width: 640px; }
  th, td { padding: 10px 14px; font-size: 13px; text-align: left; border-bottom: 1px solid var(--outline-variant); }
  th { background: var(--primary); color: #fff; font-weight: 700; white-space: nowrap; }
  tbody tr:nth-child(even) { background: var(--surface); }
  tbody tr:hover { background: #eef4fd; }

  .empty {
    color: var(--text-secondary); font-style: italic;
    background: var(--surface-white); border: 1px dashed var(--outline-variant);
    border-radius: 12px; padding: 20px; text-align: center;
  }
  .empty .empty-cta {
    display: inline-flex; align-items: center; gap: 6px; margin-top: 12px;
    padding: 8px 16px; border-radius: 999px; background: var(--primary); color: #fff;
    font-size: 13px; font-weight: 700; text-decoration: none;
  }

  /* Shared by every report's filter form — was redefined 3x locally
     (dashboard/executive/compare) before this; now one definition. */
  .filter-bar {
    display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px;
    margin-bottom: 18px; background: var(--surface-white); border: 1px solid var(--outline-variant);
    border-radius: 16px; padding: 14px 18px;
  }
  .filter-bar .field { display: flex; flex-direction: column; gap: 4px; }
  .filter-bar label { font-size: 11px; color: var(--on-surface-variant); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
  .filter-bar input, .filter-bar select {
    border: 1px solid var(--outline-variant); border-radius: 8px; padding: 7px 10px; font-size: 13px;
    font-family: inherit; background: var(--surface);
  }
  .filter-bar button[type="submit"] {
    border: none; background: var(--primary); color: #fff; font-weight: 700; font-size: 13px;
    padding: 9px 18px; border-radius: 999px; cursor: pointer; white-space: nowrap;
  }
  .filter-bar button[type="submit"]:hover { filter: brightness(1.1); }
  .filter-bar .reset-link {
    font-size: 12px; color: var(--on-surface-variant); text-decoration: underline;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .filter-note { font-size: 11px; color: var(--text-secondary); font-style: italic; margin: -12px 0 16px; }

  /* Shared by every report's auto-generated executive-summary paragraph
     (see aggregate.php's build_summary_sentence()) — was executive.php-only
     before. */
  .story-box {
    background: linear-gradient(135deg, #eaf1fb, #f1f5f9);
    border: 1px solid #cbd5e1; border-radius: 16px; padding: 20px 24px; margin-bottom: 20px;
  }
  .story-box p { font-size: 15px; line-height: 1.8; color: #1e293b; margin: 0; }
  .story-box b { color: var(--primary); }

  @media (max-width: 640px) {
    header.report-head { padding: 22px 16px 18px; }
    main.report-body { padding: 16px 12px 32px; }
    .toolbar { padding: 12px 16px; }
  }

  /* Reports that don't declare their own @page via $extraStyle get this
     orientation-aware default. (executive.php/compare.php set their own
     fixed orientation via $extraStyle, injected after this block, which
     wins — same override rule as before, just now driven by $_GET here
     too instead of only inside dashboard.php.) */
  @page { size: A4 <?= $orientation ?>; margin: 12mm; }

  .report-foot {
    max-width: 1100px; margin: 0 auto; padding: 0 20px 32px;
    font-size: 11px; color: var(--text-secondary);
    display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px;
  }

  .settings-toggle-btn { background: var(--surface); color: var(--primary); border: 1px solid var(--outline-variant); }
  .settings-panel {
    position: fixed; top: 0; right: 0; height: 100vh; width: min(300px, 90vw);
    background: var(--surface-white); border-left: 1px solid var(--outline-variant);
    box-shadow: -4px 0 20px rgba(0,0,0,.1); z-index: 70; overflow-y: auto;
  }
  .settings-panel[hidden] { display: none; }
  .settings-panel-inner { padding: 20px; }
  .settings-panel h4 { margin: 0 0 16px; font-size: 15px; color: var(--primary); }
  .settings-group { margin-bottom: 20px; }
  .settings-label {
    display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .04em;
    color: var(--on-surface-variant); margin-bottom: 8px; font-weight: 700;
  }
  .settings-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
  .settings-panel .chip {
    font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 999px;
    border: 1px solid var(--outline-variant); color: var(--on-surface-variant); text-decoration: none;
  }
  .settings-panel .chip.active { background: var(--primary); color: #fff; border-color: var(--primary); }
  .settings-panel select {
    width: 100%; padding: 8px 10px; border: 1px solid var(--outline-variant);
    border-radius: 8px; font-size: 13px; background: var(--surface);
  }
  .settings-check { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 5px 0; }
  .settings-panel-close {
    position: absolute; top: 16px; right: 16px; border: none; background: none;
    cursor: pointer; color: var(--on-surface-variant);
  }

  @media print {
    .toolbar { display: none; }
    .settings-panel { display: none; }
    .print-hidden { display: none !important; }
    .filter-bar, .empty .empty-cta { display: none; }
    body { margin: 0; background: #fff; }
    header.report-head { background: #fff !important; color: #111 !important; padding: 0 0 8px; }
    header.report-head h2 { opacity: 1; color: #444; }
    main.report-body { max-width: none; padding: 12px 0; }
    .table-wrap { box-shadow: none; border-radius: 0; }
    th { background: #f0f0f0 !important; color: #111 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .report-foot { max-width: none; padding: 8px 0 0; border-top: 1px solid #ccc; }
  }
</style>
<?= $extraStyle ?>
</head>
<body>
<div class="toolbar">
  <button onclick="window.print()" type="button"><span class="material-symbols-outlined" style="font-size:16px;">print</span> พิมพ์ / บันทึกเป็น PDF</button>
  <?php if (isset($exportUrls['csv'])): ?>
  <a href="<?= htmlspecialchars($exportUrls['csv']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">download</span> ดาวน์โหลด CSV</a>
  <?php endif; ?>
  <?php if (isset($exportUrls['excel'])): ?>
  <a href="<?= htmlspecialchars($exportUrls['excel']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">download</span> ดาวน์โหลด Excel</a>
  <?php endif; ?>
  <button class="settings-toggle-btn" type="button" onclick="document.getElementById('print-settings-panel').hidden = false;">
    <span class="material-symbols-outlined" style="font-size:16px;">tune</span> ตั้งค่าการพิมพ์
  </button>
  <a href="/admin/reports/print"><span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> เลือกเทมเพลตอื่น</a>
</div>

<div id="print-settings-panel" class="settings-panel" hidden>
  <div class="settings-panel-inner">
    <button class="settings-panel-close" type="button" onclick="document.getElementById('print-settings-panel').hidden = true;">
      <span class="material-symbols-outlined">close</span>
    </button>
    <h4>ตั้งค่าก่อนพิมพ์</h4>

    <div class="settings-group">
      <span class="settings-label">ขนาดกระดาษ</span>
      <div class="settings-row">
        <a class="chip <?= $orientation === 'portrait' ? 'active' : '' ?>" href="<?= htmlspecialchars($orientationUrl('portrait')) ?>">แนวตั้ง</a>
        <a class="chip <?= $orientation === 'landscape' ? 'active' : '' ?>" href="<?= htmlspecialchars($orientationUrl('landscape')) ?>">แนวนอน</a>
      </div>
    </div>

    <div class="settings-group">
      <span class="settings-label">ธีมสี</span>
      <select id="theme-select" onchange="applyReportTheme(this.value)">
        <option value="navy">มาตรฐานวิทยาลัย (น้ำเงิน)</option>
        <option value="purple">ม่วง</option>
        <option value="green">เขียว</option>
        <option value="mono">ขาวดำ / ประหยัดหมึก</option>
        <option value="custom">กำหนดเอง...</option>
      </select>
      <div id="custom-theme-row" class="settings-row" style="display:none; margin-top:8px;">
        <label style="font-size:12px; display:flex; align-items:center; gap:4px;">หลัก
          <input type="color" id="custom-primary" value="#1e3a8a" oninput="applyCustomReportTheme()">
        </label>
        <label style="font-size:12px; display:flex; align-items:center; gap:4px;">รอง
          <input type="color" id="custom-secondary" value="#2563eb" oninput="applyCustomReportTheme()">
        </label>
      </div>
    </div>

    <div class="settings-group" id="print-section-toggles">
      <span class="settings-label">สิ่งที่ต้องการพิมพ์</span>
    </div>
  </div>
</div>

<header class="report-head">
  <h1>วิทยาลัยเทคนิคนครนายก</h1>
  <h2><?= htmlspecialchars($subtitle) ?></h2>
</header>
<main class="report-body">
<?= $content ?>
</main>
<footer class="report-foot">
  <span>สร้างรายงานเมื่อ <?= htmlspecialchars(date('d/m/Y H:i')) ?> น.</span>
  <span>จัดทำโดย <?= htmlspecialchars($_SESSION['username'] ?? '-') ?></span>
</footer>

<script>
  var REPORT_THEMES = {
    navy: { primary: '#1e3a8a', secondary: '#2563eb', primaryContainer: '#1e40af' },
    purple: { primary: '#6d28d9', secondary: '#8b5cf6', primaryContainer: '#5b21b6' },
    green: { primary: '#047857', secondary: '#10b981', primaryContainer: '#065f46' },
    mono: { primary: '#374151', secondary: '#6b7280', primaryContainer: '#1f2937' },
  };

  function applyReportTheme(key) {
    document.getElementById('custom-theme-row').style.display = key === 'custom' ? 'flex' : 'none';
    if (key === 'custom') {
      applyCustomReportTheme();
      return;
    }
    var theme = REPORT_THEMES[key];
    if (!theme) return;
    var root = document.documentElement.style;
    root.setProperty('--primary', theme.primary);
    root.setProperty('--secondary', theme.secondary);
    root.setProperty('--primary-container', theme.primaryContainer);
  }

  function applyCustomReportTheme() {
    var root = document.documentElement.style;
    root.setProperty('--primary', document.getElementById('custom-primary').value);
    root.setProperty('--secondary', document.getElementById('custom-secondary').value);
  }

  // Every report tags its optional print sections with
  // data-print-section="<label>" — this builds the checklist from whatever
  // sections the current report actually has, instead of a hardcoded list
  // that would show unchecked boxes for sections a given report lacks.
  (function buildPrintSectionToggles() {
    var host = document.getElementById('print-section-toggles');
    var sections = document.querySelectorAll('[data-print-section]');
    if (!sections.length) {
      host.style.display = 'none';
      return;
    }
    sections.forEach(function (el, i) {
      var label = el.getAttribute('data-print-section');
      var wrap = document.createElement('label');
      wrap.className = 'settings-check';
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.checked = true;
      input.addEventListener('change', function () {
        el.classList.toggle('print-hidden', !input.checked);
      });
      wrap.appendChild(input);
      wrap.appendChild(document.createTextNode(' ' + label));
      host.appendChild(wrap);
    });
  })();
</script>
</body>
</html>
<?php
    exit;
}
