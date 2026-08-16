<?php
// Renders one bar chart (vertical, e.g. daily trend, or horizontal, e.g.
// department ranking) to a PNG and returns it as a data: URI. This is the
// PDF export's answer to the divs-with-%-height bar charts every report
// uses on screen — those are exactly what blew a 1-page report up to
// 50-300+ pages when fed to mPDF (see render_report_pdf() below), since
// mPDF's CSS engine can't be trusted with them. A flat raster image has no
// such risk: GD draws it, mPDF just places it, done. Uses the same Sarabun
// TTF as the rest of the PDF so Thai department names render correctly
// (GD's built-in bitmap fonts are Latin-only).
function render_pdf_bar_chart(array $labels, array $values, string $orientation = 'vertical', int $verticalHeight = 220): string
{
    $font = __DIR__ . '/../../fonts/Sarabun-Regular.ttf';
    $n = count($values);
    $max = max(1, ...array_merge($values, [0]));

    if ($orientation === 'horizontal') {
        $width = 720;
        $rowH = 26;
        $height = max(60, $n * $rowH + 16);
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        $bar = imagecolorallocate($img, 30, 58, 138);
        $text = imagecolorallocate($img, 15, 23, 42);
        $labelW = 180;
        $trackW = $width - $labelW - 60;
        foreach ($values as $i => $v) {
            $y = 8 + $i * $rowH;
            imagettftext($img, 11, 0, 4, $y + 16, $text, $font, (string) ($labels[$i] ?? ''));
            $barW = $v > 0 ? max(2, (int) round(($v / $max) * $trackW)) : 0;
            imagefilledrectangle($img, $labelW, $y + 4, $labelW + $barW, $y + 18, $bar);
            imagettftext($img, 10, 0, $labelW + $barW + 6, $y + 16, $text, $font, (string) $v);
        }
    } else {
        $width = 720;
        $height = $verticalHeight;
        $padL = 8;
        $padB = 26;
        $padT = 10;
        $padR = 8;
        $chartW = $width - $padL - $padR;
        $chartH = $height - $padT - $padB;
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        $bar = imagecolorallocate($img, 30, 58, 138);
        $axis = imagecolorallocate($img, 203, 213, 225);
        $text = imagecolorallocate($img, 71, 85, 105);
        imageline($img, $padL, $height - $padB, $width - $padR, $height - $padB, $axis);

        $slot = $n > 0 ? $chartW / $n : $chartW;
        $barW = max(1, $slot - 2);
        $labelStep = max(1, (int) ceil($n / 12));
        foreach ($values as $i => $v) {
            $x1 = $padL + $i * $slot;
            $barH = $v > 0 ? max(1, (int) round(($v / $max) * $chartH)) : 0;
            $y2 = $height - $padB;
            $y1 = $y2 - $barH;
            if ($barH > 0) {
                imagefilledrectangle($img, (int) $x1, $y1, (int) ($x1 + $barW), $y2, $bar);
            }
            if ($i % $labelStep === 0) {
                imagettftext($img, 9, 0, (int) $x1, $height - 8, $text, $font, (string) ($labels[$i] ?? ''));
            }
        }
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode($data);
}

// Renders $content (report-specific HTML built by the caller via
// ob_start()/ob_get_clean()) as a real PDF file via mPDF, instead of the
// browser's own print-to-PDF (window.print()) — a genuine file the OS Save
// dialog can save anywhere, with correct Thai text (Sarabun, bundled under
// ../../fonts, registered as the default font below since mPDF's own bundled
// fonts have no Thai glyphs).
function render_report_pdf(string $title, string $subtitle, string $content, string $extraStyle, string $filenameBase, array $pdfCharts = []): void
{
    $orientation = ($_GET['orientation'] ?? 'portrait') === 'landscape' ? 'L' : 'P';

    $fontDir = __DIR__ . '/../../fonts';
    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-' . ($orientation === 'L' ? 'L' : 'P'),
        'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 12, 'margin_right' => 12,
        'fontDir' => array_merge($fontDirs, [$fontDir]),
        'fontdata' => $fontData + [
            'sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf'],
        ],
        'default_font' => 'sarabun',
    ]);

    // Deliberately NOT reusing each report's own $extraStyle here. Every
    // report's screen CSS leans on grid/flexbox/SVG/break-inside rules for
    // its charts and KPI cards — mPDF's HTML/CSS engine doesn't support
    // those, and on 3 of the 9 reports (dashboard/executive/compare) it
    // didn't just degrade gracefully, it mis-measured nested unsupported
    // layout into 50-300+ blank pages. A fixed, plain-HTML-only stylesheet
    // covering the handful of class names actually shared across reports
    // (kpi-card, story-box, status/type pills, bar charts) is what's
    // reliable — charts/sparklines/heatmaps are dropped from the PDF since
    // mPDF can't render them properly anyway; the data table is what a
    // saved report is actually for.
    $pdfStyle = <<<CSS
    * { box-sizing: border-box; page-break-inside: auto; }
    body { font-family: sarabun; font-size: 12px; color: #0f172a; }
    h1 { font-size: 18px; color: #1e3a8a; margin: 0 0 4px; }
    h2 { font-size: 12px; font-weight: normal; color: #444; margin: 0 0 12px; }
    .meter-ring-wrap, .heatmap-grid, .heatmap-cell,
    .filter-bar, .compare-filter, .month-filter, .toolbar, .settings-panel, .empty .empty-cta,
    .quick-filter-chips, .filter-note, .rank-bars .links { display: none; }
    /* mPDF doesn't reliably honor display:none on an inline <svg> itself
       (only on the block-level divs wrapping one, like .meter-ring-wrap
       above, which does work) — zeroing its box forces it to take no space
       either way. */
    .sparkline { display: none; width: 0 !important; height: 0 !important; }
    /* dashboard.php's ranked department list and gender breakdown (added in
       its later "Power BI style" redesign) were never given PDF rules —
       .kpi-strip .kpi-card above covers the outer cards, but everything
       inside .rank-row/.gender-row rendered as unstyled block text, which
       is what actually pushed this report to 2 pages (not the daily-trend/
       department chart images — the SAME dept data spelled out a second
       time as bare text below them). mPDF has no flexbox, so these use
       fixed-width inline-block columns instead, same trick .kpi-card above
       already relies on. */
    .kpi-head { font-size: 9px; color: #666; margin-bottom: 1px; }
    .kpi-sub { font-size: 9px; }
    .delta.up { color: #059669; } .delta.down { color: #d97706; } .delta.flat { color: #888; }
    .rank-row, .gender-row { display: block; margin-bottom: 1px; }
    .rank-badge { display: inline-block; width: 16px; font-size: 9px; font-weight: bold; color: #1e3a8a; }
    .rank-name, .g-label { display: inline-block; width: 130px; font-size: 9px; font-weight: bold; }
    .rank-track, .g-track {
      display: inline-block; width: 240px; height: 7px; background: #eef2f6;
      border-radius: 4px; vertical-align: middle; overflow: hidden;
    }
    .rank-fill, .g-fill { display: block; height: 7px; background: #2563eb; }
    .gender-row.female .g-fill { background: #db2777; }
    .gender-row.unknown .g-fill { background: #94a3b8; }
    .rank-count, .g-count { display: inline-block; width: 110px; text-align: right; font-size: 9px; color: #666; }
    /* mini-panel-row is the on-screen div/%-height trend+hourly chart —
       exactly the shape that mis-measured into blank pages (see the block
       comment above). render_pdf_bar_chart() draws these as real images
       instead (injected before $content in render_report_pdf()), so the
       original div version is dropped here to avoid a duplicate/blank
       leftover section. */
    .mini-panel-row { display: none; }
    .meta { display: inline-block; margin-bottom: 10px; font-size: 11px; color: #475569; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px 10px; }
    .summary-strip .item, .kpi-strip .kpi-card, .kpi-grid .kpi-card, .card {
      display: inline-block; width: 31.5%; vertical-align: top; border: 1px solid #cbd5e1;
      border-radius: 8px; padding: 6px 8px; margin: 0 1% 6px 0;
    }
    .summary-strip .label, .kpi-card .label, .kpi-label { font-size: 9px; color: #666; text-transform: uppercase; }
    .summary-strip .value, .kpi-card .value, .kpi-value { font-size: 14px; font-weight: bold; color: #1e3a8a; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 6px 8px; font-size: 10px; text-align: left; border-bottom: 1px solid #cbd5e1; }
    th { background: #1e3a8a; color: #fff; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    .story-box { background: #eaf1fb; border: 1px solid #cbd5e1; border-radius: 10px; padding: 6px 12px; margin-bottom: 4px; }
    .story-box p { font-size: 10px !important; line-height: 1.5 !important; margin: 0; }
    .status-pill, .type-pill { padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; }
    .status-pill.in { background: #dcfce7; color: #166534; }
    .status-pill.out { background: #f1f5f9; color: #475569; }
    .bar-track { background: #eef2f6; border-radius: 4px; height: 8px; }
    .bar-fill { background: #1e3a8a; height: 8px; border-radius: 4px; }
    .bar-fill.b { background: #2563eb; }
    .empty { color: #475569; font-style: italic; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 14px; text-align: center; }
    CSS;

    $chartsHtml = '';
    foreach ($pdfCharts as $chart) {
        $img = render_pdf_bar_chart($chart['labels'], $chart['values'], $chart['orientation'] ?? 'vertical', $chart['height'] ?? 220);
        $chartsHtml .= '<div style="margin-bottom:8px;">'
            . '<div style="font-size:12px; font-weight:bold; color:#1e3a8a; margin-bottom:4px;">' . htmlspecialchars($chart['title']) . '</div>'
            . '<img src="' . $img . '" style="width:100%;">'
            . '</div>';
    }

    $html = '<style>' . $pdfStyle . '</style>'
        . '<h1>วิทยาลัยเทคนิคนครนายก</h1><h2>' . htmlspecialchars($subtitle) . '</h2>'
        . $chartsHtml
        . $content
        . '<p style="margin-top:16px; font-size:9px; color:#475569;">สร้างรายงานเมื่อ ' . htmlspecialchars(date('d/m/Y H:i'))
        . ' น. — จัดทำโดย ' . htmlspecialchars($_SESSION['username'] ?? '-') . '</p>';

    // mPDF's border-color parser chokes on CSS custom properties (var(...))
    // in each report's $extraStyle — harmless per-call PHP warnings
    // ("Uninitialized string offset") that would otherwise print into the
    // response body ahead of the PDF and break it. Not silencing errors
    // globally — just around the one call known to trigger this.
    $previousErrorReporting = error_reporting(E_ERROR | E_PARSE);
    try {
        $mpdf->WriteHTML($html);
        // Destination::DOWNLOAD (not INLINE) is what makes the browser show
        // a real "Save As" dialog, the same as the CSV/Excel exports — not
        // open-in-tab, which is what window.print() effectively did before.
        $mpdf->Output("$filenameBase.pdf", \Mpdf\Output\Destination::DOWNLOAD);
    } finally {
        error_reporting($previousErrorReporting);
    }
    exit;
}

// Ports templates/base_print.html. Jinja's {% extends %}/{% block %} becomes
// plain output buffering: each report file builds its $content (and optional
// $extraStyle) via ob_start()/ob_get_clean(), then calls render_report_layout().
// $exportUrls is an optional ['csv' => url, 'excel' => url] map — omitted by
// dashboard.php (a print-only 1-pager, not a row-per-record report).
function render_report_layout(string $title, string $subtitle, string $content, string $extraStyle = '', array $exportUrls = [], array $pdfCharts = []): void
{
    if (($_GET['format'] ?? '') === 'pdf') {
        // \p{M} keeps Thai combining vowel/tone marks (e.g. ั ่ ้) intact —
        // without it they'd be stripped to underscores since they're not \p{L}.
        $filenameBase = preg_replace('/[^\p{L}\p{N}\p{M}_-]+/u', '_', $title) . '_' . date('Y-m-d');
        render_report_pdf($title, $subtitle, $content, $extraStyle, $filenameBase, $pdfCharts);
    }

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
<link rel="stylesheet" href="/assets/css/report-fonts.css">
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
  /* Every filter field re-submits on change (see the script near the end of
     this file) — no report needs a manual "confirm filter" click anymore,
     so the submit button itself is removed, not just left as an unused
     fallback. Covers all 3 class names the 8 reports' filter forms use:
     .filter-bar (6 reports), .compare-filter (compare.php), .month-filter
     (executive.php). */
  .filter-bar button[type="submit"], .compare-filter button[type="submit"], .month-filter button[type="submit"] {
    display: none;
  }
  .filter-bar .reset-link {
    font-size: 12px; color: var(--on-surface-variant); text-decoration: underline;
    display: inline-flex; align-items: center; gap: 4px;
  }
  .filter-note { font-size: 11px; color: var(--text-secondary); font-style: italic; margin: -12px 0 16px; }

  /* Shared by both reports that use a .trend-chart bar chart (dashboard.php,
     department.php) — clicking/tapping a bar updates a detail line below
     the chart (see the script near the end of this file); hovering still
     shows the native title="" tooltip. A per-bar number label was tried
     here too but looked cluttered with ~30 bars in a month view, so this
     stays hover/click-only, not always-on. */
  .trend-chart .bar-wrap { cursor: pointer; }
  .trend-chart .bar-wrap.bar-wrap-active .bar { background: var(--primary, #1e3a8a); }
  .trend-detail-text {
    margin-top: 8px; font-size: 12px; font-weight: 700; color: var(--primary, #1e3a8a);
    background: var(--surface, #f8fafc); border: 1px solid var(--outline-variant, #cbd5e1);
    border-radius: 8px; padding: 6px 10px;
  }

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
  <?php
    $pdfParams = $_GET;
    $pdfParams['format'] = 'pdf';
    $pdfUrl = '?' . http_build_query($pdfParams);
  ?>
  <button onclick="window.print()" type="button"><span class="material-symbols-outlined" style="font-size:16px;">print</span> พิมพ์ / บันทึกเป็น PDF (มีกราฟครบ)</button>
  <a href="<?= htmlspecialchars($pdfUrl) ?>"><span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span> บันทึก PDF แบบข้อมูล (ไม่มีกราฟ)</a>
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

  // Date/month/number/select filters re-submit as soon as they change — the
  // submit button is gone (see the CSS above), so this is the only way
  // these filters apply. Free-text fields (student search) still submit
  // via Enter, same as before — a real HTML form submits on Enter as long
  // as it contains a submit button, even a display:none one.
  document.querySelectorAll(
    '.filter-bar select, .filter-bar input[type="date"], .filter-bar input[type="month"], .filter-bar input[type="number"], ' +
    '.compare-filter input[type="month"], .month-filter input[type="month"]'
  ).forEach(function (el) {
    el.addEventListener('change', function () {
      el.form.submit();
    });
  });

  // Every .trend-chart bar already carries its exact count as an always-
  // visible label (CSS above); clicking/tapping one also writes a full
  // "date: N รายการ" line into that chart's .trend-detail-text, and a
  // native title="" attribute still covers mouse hover. Works for both
  // dashboard.php and department.php's trend charts without per-report JS.
  document.querySelectorAll('.trend-chart .bar-wrap[data-count]').forEach(function (wrap) {
    wrap.addEventListener('click', function () {
      var container = wrap.closest('.mini-panel, .panel');
      var detail = container ? container.querySelector('.trend-detail-text') : null;
      if (!detail) return;
      (container.querySelectorAll('.bar-wrap') || []).forEach(function (b) {
        b.classList.remove('bar-wrap-active');
      });
      wrap.classList.add('bar-wrap-active');
      detail.textContent = wrap.dataset.label + ': ' + wrap.dataset.count + ' รายการ';
    });
  });
</script>
</body>
</html>
<?php
    exit;
}
