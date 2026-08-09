<?php
// Ports templates/base_print.html. Jinja's {% extends %}/{% block %} becomes
// plain output buffering: each report file builds its $content (and optional
// $extraStyle) via ob_start()/ob_get_clean(), then calls render_report_layout().
// $exportUrls is an optional ['csv' => url, 'excel' => url] map — omitted by
// dashboard.php (a print-only 1-pager, not a row-per-record report).
function render_report_layout(string $title, string $subtitle, string $content, string $extraStyle = '', array $exportUrls = []): void
{
    header('Content-Type: text/html; charset=utf-8');
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

  @media (max-width: 640px) {
    header.report-head { padding: 22px 16px 18px; }
    main.report-body { padding: 16px 12px 32px; }
    .toolbar { padding: 12px 16px; }
  }

  /* Daily/monthly/department/dashboard reports don't set their own @page —
     this is their default. (executive.php/compare.php declare their own via
     $extraStyle, which is injected after this block and wins.) */
  @page { size: A4 portrait; margin: 12mm; }

  @media print {
    .toolbar { display: none; }
    body { margin: 0; background: #fff; }
    header.report-head { background: #fff !important; color: #111 !important; padding: 0 0 8px; }
    header.report-head h2 { opacity: 1; color: #444; }
    main.report-body { max-width: none; padding: 12px 0; }
    .table-wrap { box-shadow: none; border-radius: 0; }
    th { background: #f0f0f0 !important; color: #111 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
  <a href="/admin/reports/print"><span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> เลือกเทมเพลตอื่น</a>
</div>
<header class="report-head">
  <h1>วิทยาลัยเทคนิคนครนายก</h1>
  <h2><?= htmlspecialchars($subtitle) ?></h2>
</header>
<main class="report-body">
<?= $content ?>
</main>
</body>
</html>
<?php
    exit;
}
