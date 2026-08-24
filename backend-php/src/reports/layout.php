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
function render_pdf_bar_chart(array $labels, array $values, string $orientation = 'vertical', int $verticalHeight = 220, ?int $scaleMax = null, int $width = 720): string
{
    $font = __DIR__ . '/../../fonts/IBMPlexSansThai-Regular.ttf';
    $k = $width / 720;   // ตัวคูณสำหรับขนาดตัวอักษรและระยะขอบ
    $n = count($values);
    // $scaleMax lets a caller pin two charts to one axis (e.g. the same
    // departments in month A and month B). Still folded through max() with the
    // real values so an under-stated ceiling can never clip a bar off the top.
    $max = max(1, $scaleMax ?? 0, ...array_merge($values, [0]));

    if ($orientation === 'horizontal') {
        $rowH = (int) (26 * $k);
        $height = max(60, $n * $rowH + 16);
        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);
        $bar = imagecolorallocate($img, 30, 58, 138);
        $text = imagecolorallocate($img, 15, 23, 42);
        $labelW = (int) (180 * $k);
        $trackW = $width - $labelW - (int) (60 * $k);
        foreach ($values as $i => $v) {
            $y = 8 + $i * $rowH;
            imagettftext($img, 11 * $k, 0, (int) (4 * $k), (int) ($y + 16 * $k), $text, $font, (string) ($labels[$i] ?? ''));
            $barW = $v > 0 ? max(2, (int) round(($v / $max) * $trackW)) : 0;
            imagefilledrectangle($img, $labelW, $y + 4, $labelW + $barW, $y + 18, $bar);
            imagettftext($img, 10, 0, $labelW + $barW + 6, $y + 16, $text, $font, (string) $v);
        }
    } else {
        $height = $verticalHeight;
        $padL = (int) (8 * $k);
        $padB = (int) (26 * $k);
        $padT = (int) (10 * $k);
        $padR = (int) (8 * $k);
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
                imagettftext($img, 9 * $k, 0, (int) $x1, (int) ($height - 8 * $k), $text, $font, (string) ($labels[$i] ?? ''));
            }
        }
    }

    ob_start();
    imagepng($img);
    $data = ob_get_clean();
    imagedestroy($img);
    return 'data:image/png;base64,' . base64_encode($data);
}

// ไอคอนสี่เหลี่ยมมุมมนพร้อมรูปสัญลักษณ์ วาดด้วย GD แล้วคืนเป็น data: URI
//
// วาดเป็นรูปแทนการใช้ฟอนต์ไอคอน เพราะฟอนต์ที่ฝังใน PDF มีแต่ Sarabun กับ
// IBM Plex Sans Thai ซึ่งไม่มีอักขระรูปสัญลักษณ์ — ตัวที่ไม่มีจะกลายเป็น
// กล่องสี่เหลี่ยมว่าง (เช่นเดียวกับที่ ▲ ▼ เคยขึ้นเป็นกล่องในการ์ดตัวเลข)
function render_pdf_icon_tile(string $kind, array $tint, array $accent, int $size = 96): string
{
    $ss = 4;                       // วาดใหญ่แล้วย่อ ขอบมุมมนกับวงกลมจะได้ไม่หยัก
    $big = $size * $ss;
    $img = imagecreatetruecolor($big, $big);
    imagesavealpha($img, true);
    imagefill($img, 0, 0, imagecolorallocatealpha($img, 255, 255, 255, 127));

    $bg = imagecolorallocate($img, $tint[0], $tint[1], $tint[2]);
    $fg = imagecolorallocate($img, $accent[0], $accent[1], $accent[2]);

    // สี่เหลี่ยมมุมมน: สี่เหลี่ยมสองอันไขว้กัน บวกวงกลมที่มุมทั้งสี่
    $r = (int) ($big * 0.28);
    imagefilledrectangle($img, $r, 0, $big - $r, $big, $bg);
    imagefilledrectangle($img, 0, $r, $big, $big - $r, $bg);
    foreach ([[$r, $r], [$big - $r, $r], [$r, $big - $r], [$big - $r, $big - $r]] as [$px, $py]) {
        imagefilledellipse($img, $px, $py, $r * 2, $r * 2, $bg);
    }

    $c = (int) ($big / 2);
    if ($kind === 'people') {
        // สองคนซ้อนกัน
        imagefilledellipse($img, (int) ($c - $big * 0.13), (int) ($c - $big * 0.13), (int) ($big * 0.20), (int) ($big * 0.20), $fg);
        imagefilledellipse($img, (int) ($c + $big * 0.15), (int) ($c - $big * 0.11), (int) ($big * 0.17), (int) ($big * 0.17), $fg);
        imagefilledarc($img, (int) ($c - $big * 0.13), (int) ($c + $big * 0.20), (int) ($big * 0.42), (int) ($big * 0.34), 180, 360, $fg, IMG_ARC_PIE);
        imagefilledarc($img, (int) ($c + $big * 0.16), (int) ($c + $big * 0.21), (int) ($big * 0.34), (int) ($big * 0.28), 180, 360, $fg, IMG_ARC_PIE);
    } elseif ($kind === 'clock') {
        $rad = (int) ($big * 0.30);
        imagesetthickness($img, (int) ($big * 0.055));
        imagearc($img, $c, $c, $rad * 2, $rad * 2, 0, 360, $fg);
        imageline($img, $c, $c, $c, (int) ($c - $rad * 0.55), $fg);
        imageline($img, $c, $c, (int) ($c + $rad * 0.45), $c, $fg);
        imagesetthickness($img, 1);
    } else {
        // คนเดียว
        imagefilledellipse($img, $c, (int) ($c - $big * 0.13), (int) ($big * 0.24), (int) ($big * 0.24), $fg);
        imagefilledarc($img, $c, (int) ($c + $big * 0.22), (int) ($big * 0.46), (int) ($big * 0.36), 180, 360, $fg, IMG_ARC_PIE);
    }

    $out = imagecreatetruecolor($size, $size);
    imagesavealpha($out, true);
    imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
    imagecopyresampled($out, $img, 0, 0, 0, 0, $size, $size, $big, $big);
    imagedestroy($img);

    ob_start();
    imagepng($out);
    $data = ob_get_clean();
    imagedestroy($out);
    return 'data:image/png;base64,' . base64_encode($data);
}

// วาดกราฟวงกลมแบบโดนัทเป็นรูป PNG แล้วคืนค่าเป็น data: URI
//
// เหตุผลเดียวกับ render_pdf_bar_chart(): mPDF วาด SVG/CSS ที่ซับซ้อนไม่ได้
// ให้ GD วาดเป็นภาพแบนๆ แล้ว mPDF แค่วางลงไป
//
// $slices = [['label' => 'ปวช.', 'value' => 1084, 'color' => [37, 99, 235]], ...]
// $opt    = ['center_top' => 'รวม', 'unit' => 'ครั้ง', 'legend_width' => 300]
function render_pdf_donut_chart(array $slices, string $centerValue = '', string $centerUnit = '', int $width = 720, int $height = 250, array $opt = []): string
{
    $font = __DIR__ . '/../../fonts/IBMPlexSansThai-Regular.ttf';
    $bold = __DIR__ . '/../../fonts/IBMPlexSansThai-Bold.ttf';
    if (!is_file($bold)) {
        $bold = $font;
    }
    $unit = (string) ($opt['unit'] ?? 'ครั้ง');
    $centerTopLabel = (string) ($opt['center_top'] ?? '');
    $scale = (float) ($opt['scale'] ?? 1.0);

    $total = 0.0;
    foreach ($slices as $s) {
        $total += max(0.0, (float) $s['value']);
    }

    $img = imagecreatetruecolor($width, $height);
    imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
    $ink = imagecolorallocate($img, 30, 41, 59);
    $muted = imagecolorallocate($img, 100, 116, 139);

    $d = (int) min($height - 16, (int) ($opt['diameter'] ?? 210));
    $cx = 12 + $d / 2;
    $cy = $height / 2;

    if ($total > 0) {
        // วาดใหญ่ 4 เท่าแล้วย่อลง เพราะ imagefilledarc ไม่มี anti-alias
        // ขอบวงจะหยักเป็นบันไดถ้าวาดที่ขนาดจริง
        $ss = 4;
        $big = (int) ($d * $ss);
        $pie = imagecreatetruecolor($big, $big);
        imagefill($pie, 0, 0, imagecolorallocate($pie, 255, 255, 255));
        $pc = (int) ($big / 2);

        // คำนวณมุมสะสมก่อน แล้วค่อยปัดเศษทีเดียวที่ขอบแต่ละชิ้น
        // ถ้าปัดความกว้างของแต่ละชิ้นแยกกัน ขอบที่ติดกันจะไม่ตรงกันพอดี
        // และเหลือเส้นขาวบางๆ คั่นระหว่างชิ้น
        $bounds = [-90.0];
        $acc = 0.0;
        foreach ($slices as $s) {
            $acc += max(0.0, (float) $s['value']);
            $bounds[] = -90.0 + ($acc / $total) * 360.0;
        }
        foreach ($slices as $i => $s) {
            $a1 = (int) round($bounds[$i]);
            $a2 = (int) round($bounds[$i + 1]);
            if ($a2 <= $a1) {
                continue;   // ชิ้นที่เล็กจนปัดแล้วกว้าง 0 องศา — วาดแล้วจะได้วงเต็มแทน
            }
            $col = imagecolorallocate($pie, $s['color'][0], $s['color'][1], $s['color'][2]);
            imagefilledarc($pie, $pc, $pc, $big, $big, $a1, $a2, $col, IMG_ARC_PIE);
        }
        // เจาะรูตรงกลางให้เป็นโดนัท เทียบสัดส่วนด้วยความยาวส่วนโค้งง่ายกว่าวงกลมทึบ
        imagefilledellipse($pie, $pc, $pc, (int) ($big * 0.56), (int) ($big * 0.56), imagecolorallocate($pie, 255, 255, 255));

        imagecopyresampled($img, $pie, (int) ($cx - $d / 2), (int) ($cy - $d / 2), 0, 0, (int) $d, (int) $d, $big, $big);
        imagedestroy($pie);
    }

    // ข้อความกลางวง
    $centered = function (string $text, float $size, string $fontFile, int $color, float $baseline) use ($img, $cx) {
        if ($text === '') {
            return;
        }
        $box = imagettfbbox($size, 0, $fontFile, $text);
        imagettftext($img, $size, 0, (int) ($cx - ($box[2] - $box[0]) / 2), (int) $baseline, $color, $fontFile, $text);
    };
    if ($centerTopLabel !== '') {
        $centered($centerTopLabel, 11 * $scale, $font, $muted, $cy - 16 * $scale);
        $centered($centerValue, 23 * $scale, $bold, $ink, $cy + 12 * $scale);
        $centered($centerUnit, 11 * $scale, $font, $muted, $cy + 32 * $scale);
    } else {
        $centered($centerValue, 24 * $scale, $bold, $ink, $cy + 2 * $scale);
        $centered($centerUnit, 11.5 * $scale, $font, $muted, $cy + 24 * $scale);
    }

    // คำอธิบายสัญลักษณ์ วางด้านขวาของวง จัดเป็น 3 คอลัมน์: ชื่อ / เปอร์เซ็นต์ / จำนวน
    $lx = (int) ($cx + $d / 2 + 26);
    $n = max(1, count($slices));
    $rowH = $n > 5 ? max(16, (int) floor(($height - 22) / $n)) : (int) (26 * $scale);
    $labelSize = min(13.5 * $scale, $rowH * 0.56);
    $swatch = (int) max(8, min(12, $rowH * 0.45));

    // จองความกว้างคงที่ให้สองคอลัมน์ขวา ตัวเลขจึงเรียงตรงกันทุกบรรทัด
    $countW = (int) (104 * $scale);
    $pctW = (int) (74 * $scale);
    $countX = $width - 8 - $countW;
    $pctX = $countX - $pctW;

    $ly = (int) ($cy - ($n * $rowH) / 2 + $rowH / 2 + $labelSize * 0.38);

    foreach ($slices as $s) {
        $col = imagecolorallocate($img, $s['color'][0], $s['color'][1], $s['color'][2]);
        // จุดสีเป็นสี่เหลี่ยมมุมมนแบบง่าย — วงกลมเล็กขนาดนี้ GD วาดแล้วขอบหยัก
        imagefilledrectangle($img, $lx, (int) ($ly - $swatch + 1), $lx + $swatch, $ly + 1, $col);

        $pct = $total > 0 ? round((float) $s['value'] / $total * 100, 1) : 0;
        $pctText = $pct . '%';
        $countText = '(' . number_format((float) $s['value']) . ' ' . $unit . ')';

        imagettftext($img, $labelSize, 0, $pctX, $ly, $ink, $font, $pctText);
        imagettftext($img, $labelSize, 0, $countX, $ly, $muted, $font, $countText);

        // ตัดชื่อที่ยาวเกินช่องว่างที่เหลือ แทนที่จะปล่อยให้ทับตัวเลข
        $labelX = $lx + $swatch + 9;
        $maxLabelW = $pctX - $labelX - 8;
        $label = (string) $s['label'];
        $lbox = imagettfbbox($labelSize, 0, $font, $label);
        if ($lbox[2] - $lbox[0] > $maxLabelW) {
            while ($label !== '' && ($lbox[2] - $lbox[0]) > $maxLabelW) {
                $label = mb_substr($label, 0, mb_strlen($label) - 1);
                $lbox = imagettfbbox($labelSize, 0, $font, $label . '…');
            }
            $label .= '…';
        }
        imagettftext($img, $labelSize, 0, $labelX, $ly, $ink, $font, $label);

        $ly += $rowH;
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
function render_report_pdf(string $title, string $subtitle, string $content, string $extraStyle, string $filenameBase, array $pdfCharts = [], bool $ownHeading = false): void
{
    // รายงานทุกฉบับเป็น A4 แนวนอนอย่างเดียว ไม่มีตัวเลือกให้เลือกอีกต่อไป:
    // ตารางและกราฟของรายงานเหล่านี้กว้างกว่าที่กระดาษแนวตั้งรับไหว พอเลือก
    // แนวตั้งจะได้เอกสารที่คอลัมน์ถูกบีบจนอ่านไม่ออกหรือหลุดไปหน้าที่สอง
    // การมีตัวเลือกที่มีคำตอบเดียวที่ใช้ได้จริง คือการให้ผู้ใช้เลือกผิดได้เปล่าๆ
    $fontDir = __DIR__ . '/../../fonts';
    $defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
    $fontDirs = $defaultConfig['fontDir'];
    $defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
    $fontData = $defaultFontConfig['fontdata'];

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4-L',
        'margin_top' => 14, 'margin_bottom' => 14, 'margin_left' => 12, 'margin_right' => 12,
        'fontDir' => array_merge($fontDirs, [$fontDir]),
        'fontdata' => $fontData + [
            // IBM Plex Sans Thai TTFs (backend-php/fonts) so the exported PDF
            // uses the same family as every on-screen page. mPDF needs TTF —
            // it can't read the .woff2 the browser pages load — so these are
            // separate files from public/assets/fonts. Sarabun stays
            // registered as a fallback but is no longer the default.
            'ibmplexsansthai' => ['R' => 'IBMPlexSansThai-Regular.ttf', 'B' => 'IBMPlexSansThai-Bold.ttf'],
            'sarabun' => ['R' => 'Sarabun-Regular.ttf', 'B' => 'Sarabun-Bold.ttf'],
        ],
        'default_font' => 'ibmplexsansthai',
    ]);

    // Deliberately NOT reusing each report's own $extraStyle here. Every
    // report's screen CSS leans on grid/flexbox/SVG/break-inside rules for
    // its charts and KPI cards — mPDF's HTML/CSS engine doesn't support
    // those, and on 3 of the 9 reports (dashboard/executive/compare) it
    // didn't just degrade gracefully, it mis-measured nested unsupported
    // layout into 50-300+ blank pages. A fixed, plain-HTML-only stylesheet
    // covering the handful of class names actually shared across reports
    // (kpi-card, story-box, status/type pills, bar charts) is what's
    // reliable. The charts are not dropped: each report hands its series to
    // render_report_layout() as $pdfCharts and GD draws them as flat PNGs
    // (see render_pdf_bar_chart above), which mPDF only has to place. The
    // div versions of those same charts are hidden below so the PDF shows
    // each set of numbers once. Sparklines and the meter ring stay dropped —
    // they are decoration on a KPI card, not the datum.
    // Nowdoc, not heredoc. A bare <<<CSS interpolates like a double-quoted
    // string, and this block mentions $content and $pdfCharts in its own
    // comments — which PHP happily substituted: the array raised "Array to
    // string conversion", and that warning printed ahead of the %PDF header,
    // corrupting every downloaded file. The string one was worse for being
    // silent, pasting the whole report body inside a CSS comment. Nothing in
    // this stylesheet is meant to be dynamic, so quoting the identifier ends
    // the whole class of bug rather than escaping two sigils.
    $pdfStyle = <<<'CSS'
    * { box-sizing: border-box; page-break-inside: auto; }
    body { font-family: ibmplexsansthai; font-size: 12px; color: #0f172a; }
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
    /* The same swap, one report at a time. Each of these blocks is now drawn
       into the PDF as a GD image by its report's $pdfCharts, so leaving the
       div original visible would print every department twice.
       .trend-chart earns its place here twice over: department.php's copy sits
       outside .mini-panel-row, and a flex row of %-height divs is the exact
       shape that mis-measured a 1-page report into 50-300 blank ones. */
    .trend-chart,
    .rank-bars, .mom-row, .mom-legend,
    .dept-row, .month-row,
    .dept-compare-row, .compare-legend { display: none; }
    .meta { display: inline-block; margin-bottom: 10px; font-size: 11px; color: #475569; border: 1px solid #cbd5e1; border-radius: 10px; padding: 4px 10px; }
    /* float, not inline-block. mPDF promotes an inline-block box back to a
       block once it contains block children — which every KPI card does
       (.kpi-head / .kpi-value are divs) — so the cards stacked one per row and
       ate ~360pt, pushing this report to two pages. The symptom only appeared
       after the PDF font moved from Sarabun to IBM Plex Sans Thai, which is
       wider, but the layout was never actually working; Sarabun was just small
       enough that the rest of the page still fit. Floats mPDF does honour.
       Verified: 3 cards per row, one page. */
    /* 30% (3 ใบต่อแถว) เป็นค่าที่ตั้งไว้ตอนกระดาษยังเป็นแนวตั้ง พอเปลี่ยนเป็น
       แนวนอน กระดาษกว้างขึ้นจาก ~571pt เป็น ~818pt แต่เตี้ยลงจาก ~818 เหลือ
       ~571 การ์ดสามใบต่อแถวจึงกินความสูงที่เหลือน้อยอยู่แล้วไปเปล่าๆ */
    .summary-strip .item, .kpi-strip .kpi-card, .kpi-grid .kpi-card, .card,
    .headline-grid .headline-card {
      float: left; width: 23.5%; border: 1px solid #cbd5e1;
      border-radius: 8px; padding: 5px 7px; margin: 0 1.5% 5px 0;
    }
    /* รายงานสรุปผู้บริหารมีการ์ดหกใบพอดี เรียงแถวเดียวจบบนกระดาษแนวนอน
       ประหยัดความสูงไปราวหนึ่งแถวเต็ม ซึ่งคือส่วนที่ทำให้เกินไปหน้าที่สอง */
    .headline-grid .headline-card { width: 15.4%; }
    /* Without this the section after a strip wraps around the floats. */
    .summary-strip::after, .kpi-strip::after, .kpi-grid::after, .headline-grid::after {
      content: ''; display: block; clear: both;
    }
    .headline-card .label { font-size: 9px; color: #666; }
    .headline-card .value { font-size: 14px; font-weight: bold; color: #1e3a8a; }
    .headline-card .delta { font-size: 8.5px; }
    /* executive.php's ranked department rows are three divs that mPDF puts on
       a line each. Its CSS engine is unreliable with long descendant chains,
       so these stay single-class and lay the parts out with widths instead of
       display:inline — floats it does honour. */
    .rank-list .rank-row { border-bottom: 1px solid #eef2f7; padding: 1px 0; }
    .rank-num { float: left; width: 6%; font-weight: bold; color: #1e3a8a; font-size: 10px; }
    .rank-row .name { float: left; width: 64%; font-size: 10px; font-weight: bold; }
    .rank-row .count { float: left; width: 28%; font-size: 10px; color: #475569; text-align: right; }
    .rank-row::after { content: ''; display: block; clear: both; }
    /* executive.php's three breakdown tables. Same reason as the cards above:
       mPDF has no flexbox, so .split-row would stack them full-width and cost
       a page. */
    .split-col { float: left; width: 31.5%; margin-right: 2%; }
    .split-row::after { content: ''; display: block; clear: both; }
    .split-col .section-head { font-size: 11px; margin: 4px 0 3px; }
    /* หัวข้อย่อยบนหน้าจอเว้น 18px บนล่าง ซึ่งเป็นระยะสำหรับการอ่านบนจอ
       ไม่ใช่สำหรับกระดาษที่ต้องจบในหน้าเดียว */
    .section-head { font-size: 11px; margin: 6px 0 3px; }
    .mini-table th { font-size: 8px; padding: 2px 3px; }
    .mini-table td { font-size: 9px; padding: 2px 3px; }
    .summary-strip .label, .kpi-card .label, .kpi-label { font-size: 9px; color: #666; text-transform: uppercase; }
    .summary-strip .value, .kpi-card .value, .kpi-value { font-size: 14px; font-weight: bold; color: #1e3a8a; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 3px 6px; font-size: 10px; text-align: left; border-bottom: 1px solid #cbd5e1; }
    th { background: #1e3a8a; color: #fff; }
    tbody tr:nth-child(even) { background: #f8fafc; }
    .story-box { background: #eaf1fb; border: 1px solid #cbd5e1; border-radius: 10px; padding: 5px 10px; margin-bottom: 4px; }
    .story-box p { font-size: 10px !important; line-height: 1.5 !important; margin: 0; }
    .status-pill, .type-pill { padding: 2px 8px; border-radius: 999px; font-size: 9px; font-weight: bold; }
    .status-pill.in { background: #dcfce7; color: #166534; }
    .status-pill.out { background: #f1f5f9; color: #475569; }
    .bar-track { background: #eef2f6; border-radius: 4px; height: 8px; }
    .bar-fill { background: #1e3a8a; height: 8px; border-radius: 4px; }
    .bar-fill.b { background: #2563eb; }
    .empty { color: #475569; font-style: italic; border: 1px dashed #cbd5e1; border-radius: 10px; padding: 14px; text-align: center; }
    /* ---- แดชบอร์ด (dx-*) ใช้มาร์กอัปชุดเดียวกับหน้าจอ ต่างกันแค่ CSS ชุดนี้ ----
       ค่าที่ตั้งไว้ชดเชยพฤติกรรมของ mPDF เอง: มันย่อขนาดตัวอักษรที่อยู่ในตาราง
       ซ้อนตาราง ตัวเลขจึงดูใหญ่เกินจริงถ้าอ่านจากโค้ดอย่างเดียว แต่ขนาดที่
       พิมพ์ออกมาจริงตรงกับที่ออกแบบไว้ */
    .dx-head { text-align: center; margin-bottom: 9px; }
    .dx-org { font-size: 9.5px; color: #94a3b8; }
    .dx-title { font-size: 21px; font-weight: bold; color: #111827; }
    .dx-sub { font-size: 10.5px; color: #64748b; }
    .dx-empty { border: 1px dashed #cbd5e1; border-radius: 8px; padding: 20px; text-align: center; color: #475569; }

    .dx-kpi-row, .dx-row { width: 100%; border-collapse: separate; border-spacing: 5px 0; }
    .dx-row { margin-top: 7px; }
    .dx-kpi-cell { width: 25%; vertical-align: top; }
    .dx-panel-cell { width: 50%; vertical-align: top; }
    .dx-kpi, .dx-panel { width: 100%; border: 1px solid #e7ebf3; border-radius: 10px; background: #ffffff; }
    .dx-kpi-icon { width: 44px; padding: 11px 0 11px 11px; vertical-align: top; }
    .dx-kpi-icon img { width: 33px; }
    .dx-kpi-text { padding: 11px 12px 11px 8px; }
    .dx-kpi-label { font-size: 11px; color: #64748b; }
    .dx-kpi-value { font-size: 23px; font-weight: bold; color: #111827; }
    .dx-kpi-unit { font-size: 11px; font-weight: normal; color: #64748b; }
    .dx-delta { font-size: 10px; color: #94a3b8; }
    .dx-delta.up b { color: #16a34a; }
    .dx-delta.down b { color: #dc2626; }
    .dx-delta.flat b { color: #94a3b8; }
    .dx-delta span { color: #94a3b8; }

    .dx-panel-body { padding: 10px 12px 12px; }
    .dx-panel-title { font-size: 21px; font-weight: bold; color: #111827; margin-bottom: 6px; }
    .dx-chart { width: 100%; }

    .dx-peak { width: 100%; }
    .dx-peak-info { width: 31%; vertical-align: middle; text-align: center; padding-right: 8px; }
    .dx-peak-info img { width: 52px; }
    .dx-peak-range { font-size: 29px; font-weight: bold; color: #2563eb; margin-top: 6px; }
    .dx-peak-note { font-size: 18px; color: #64748b; }
    .dx-peak-count { font-size: 38px; font-weight: bold; color: #2563eb; }
    .dx-peak-unit { font-size: 18px; font-weight: normal; color: #64748b; }
    .dx-peak-chart { width: 69%; vertical-align: middle; }

    CSS;

    // ภาพกราฟถูกยืดเต็มความกว้างเนื้อหาด้วย width:100% ความสูงที่พิมพ์ออกมาจึง
    // มาจากสัดส่วนของภาพ ไม่ใช่จากค่า height ที่รายงานส่งมาโดยตรง
    //
    // วาดที่ความกว้าง 1080px แทนค่าเริ่มต้น 720px ด้วยเหตุผลสองข้อพร้อมกัน:
    // กระดาษแนวนอนกว้าง ~774pt ภาพ 720px ที่ถูกยืดขึ้นไปเท่านั้นจะเห็นขอบหยัก
    // และเมื่อความสูงคงเดิม การเพิ่มความกว้างทำให้ภาพแบนลง ความสูงที่พิมพ์จริง
    // ลดจากราว 161pt เหลือราว 107pt ซึ่งคือพื้นที่ที่ทำให้รายงานจบในหน้าเดียว
    //
    // นี่คือการแก้ที่สัดส่วนของภาพจริง ไม่ใช่การย่อทั้งหน้าด้วย transform
    // 'half' => true วางกราฟสองใบข้างกันแทนที่จะซ้อนลงมา ใช้กับกราฟที่ตั้งใจ
    // ให้เทียบกัน (compare.php) — บนกระดาษแนวนอน กราฟที่แคบลงครึ่งหนึ่งจะเตี้ยลง
    // ครึ่งหนึ่งตามสัดส่วนด้วย สองใบจึงกินความสูงเท่าใบเดียวเมื่อก่อน
    // ใช้ float เพราะ mPDF ไม่มี flexbox
    $chartsHtml = '';
    $hasHalf = false;
    foreach ($pdfCharts as $chart) {
        $img = render_pdf_bar_chart(
            $chart['labels'],
            $chart['values'],
            $chart['orientation'] ?? 'vertical',
            $chart['height'] ?? 220,
            $chart['scale_max'] ?? null,
            1080
        );
        $half = !empty($chart['half']);
        $hasHalf = $hasHalf || $half;
        $box = $half ? 'float:left; width:49%; margin:0 1% 6px 0;' : 'margin-bottom:6px;';
        $chartsHtml .= '<div style="' . $box . '">'
            . '<div style="font-size:11px; font-weight:bold; color:#1e3a8a; margin-bottom:3px;">' . htmlspecialchars($chart['title']) . '</div>'
            . '<img src="' . $img . '" style="width:100%;">'
            . '</div>';
    }
    if ($hasHalf) {
        $chartsHtml .= '<div style="clear:both;"></div>';
    }

    $heading = $ownHeading
        ? ''
        : '<h1>วิทยาลัยเทคนิคนครนายก</h1><h2>' . htmlspecialchars($subtitle) . '</h2>';
    $html = '<style>' . $pdfStyle . '</style>'
        . $heading
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
function render_report_layout(string $title, string $subtitle, string $content, string $extraStyle = '', array $exportUrls = [], array $pdfCharts = [], bool $ownPdfHeading = false): void
{
    if (($_GET['format'] ?? '') === 'pdf') {
        // \p{M} keeps Thai combining vowel/tone marks (e.g. ั ่ ้) intact —
        // without it they'd be stripped to underscores since they're not \p{L}.
        $filenameBase = preg_replace('/[^\p{L}\p{N}\p{M}_-]+/u', '_', $title) . '_' . date('Y-m-d');
        render_report_pdf($title, $subtitle, $content, $extraStyle, $filenameBase, $pdfCharts, $ownPdfHeading);
    }

    header('Content-Type: text/html; charset=utf-8');

    // Shared by every report (was dashboard.php-only before) — its own
    // @page rule, if it sets one via $extraStyle, is injected after this
    // block and wins, same override rule the file header comment already
    // documents for other @page usages.
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
    font-family: 'IBM Plex Sans Thai', 'Noto Sans Thai', 'Tahoma', 'Leelawadee UI', sans-serif;
    margin: 0;
    color: #0f172a;
    background: var(--surface);
  }
  /* Form controls do not inherit font-family — the browser gives them its own
     UA default — so every <button>, <select> and <option> on these pages was
     rendering in Arial while the body around them was IBM Plex Sans Thai. The
     app's own pages get this from Tailwind's preflight; these standalone
     report pages have no preflight, so it has to be said here. Thai in Arial
     falls back again to whatever the OS picks, which is how the filter bar
     ended up in a visibly different face from the report under it. */
  button, select, option, input, textarea { font-family: inherit; }
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
  /* The solid fill follows the primary ACTION, not the element type. Saving the
     PDF is what this toolbar is for — it produces a real file with the charts
     drawn server-side by GD — while window.print() is now the secondary "print
     the page as it looks" escape hatch. */
  .toolbar a.primary-action { background: var(--primary); color: #fff; border-color: var(--primary); }
  .toolbar a.primary-action:hover { background: var(--primary); filter: brightness(1.1); }
  .toolbar button.secondary-action { background: var(--surface); color: var(--primary); border: 1px solid var(--outline-variant); }
  .toolbar button.secondary-action:hover { background: #e8f0fe; filter: none; }

  /* Navigation reads as links, not as more buttons: it is the way out of the
     report, not something you came here to do. margin-right:auto pushes the
     actions to the far end so the two groups never look like one long row. */
  .toolbar-nav { display: flex; align-items: center; gap: 4px; margin-right: auto; }
  .toolbar-nav a {
    background: none; border: 0; color: var(--primary);
    padding: 7px 10px; font-size: 13px; font-weight: 700;
  }
  .toolbar-nav a:hover { background: #e8f0fe; }

  .toolbar-actions { display: flex; align-items: center; gap: 10px; }
  .toolbar-more { position: relative; }
  .toolbar-more > summary {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 9px 16px; border-radius: 999px; cursor: pointer;
    font-size: 13px; font-weight: 700;
    background: var(--surface); color: var(--primary);
    border: 1px solid var(--outline-variant);
    list-style: none;
  }
  /* Both are needed: WebKit uses the pseudo-element, everyone else the
     list-style above. Without them the row carries a stray triangle. */
  .toolbar-more > summary::-webkit-details-marker { display: none; }
  .toolbar-more > summary::marker { content: ''; }
  .toolbar-more > summary:hover { background: #e8f0fe; }
  .toolbar-more[open] > summary { background: #e8f0fe; }
  .toolbar-more-menu {
    position: absolute; right: 0; top: calc(100% + 6px); z-index: 30;
    display: flex; flex-direction: column; gap: 2px; min-width: 210px;
    padding: 6px; border-radius: 12px;
    background: var(--surface-white);
    border: 1px solid var(--outline-variant);
    box-shadow: 0 10px 28px rgba(15, 23, 42, .16);
  }
  .toolbar-more-menu a,
  .toolbar-more-menu button {
    justify-content: flex-start; width: 100%;
    background: none; border: 0; color: var(--primary);
    border-radius: 8px; padding: 9px 12px; font-size: 13px; text-align: left;
  }
  .toolbar-more-menu a:hover,
  .toolbar-more-menu button:hover { background: #e8f0fe; filter: none; }

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

  /* Screen-only paging for long report tables. The monthly report is one row
     per student, so on a phone it was 700+ rows in a single scroll. Rows are
     hidden with a class rather than removed from the DOM: printing has to get
     the whole table back, and a report that prints only the visible page is
     not a report. The @media print block below restores them. */
  .is-paged-out { display: none; }
  .report-pager {
    display: flex; align-items: center; justify-content: flex-end; gap: 8px;
    padding: 10px 2px 2px; font-size: 12px; color: var(--on-surface-variant);
  }
  .report-pager .rp-info { margin-right: auto; }
  .report-pager button {
    font-size: 12px; font-weight: 700; padding: 6px 12px; border-radius: 8px;
    border: 1px solid var(--outline-variant); background: var(--surface-white);
    color: var(--primary); cursor: pointer;
  }
  .report-pager button:disabled { opacity: .45; cursor: not-allowed; }
  .report-pager button:hover:not(:disabled) { background: #e8f0fe; }
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

  /* แท่งกราฟใน .trend-chart (dashboard.php, department.php) แสดงค่าผ่าน
     tooltip ของเบราว์เซอร์ (title="") อย่างเดียว ไม่มีการกดเลือก — หน้ารายงาน
     มีไว้สั่งพิมพ์และส่งออก PDF การกดเลือกจึงไม่มีผลอะไรกับไฟล์ที่ได้ */

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
    .toolbar { padding: 10px 14px; gap: 8px; }
    .toolbar-nav { margin-right: 0; flex: 1 0 100%; }
    .toolbar-nav a { padding: 6px 8px; font-size: 12.5px; }
    .toolbar-actions { flex: 1 0 100%; }
    .toolbar-actions .primary-action { flex: 1 1 auto; justify-content: center; }
    /* Anchored to the right edge of a full-width row, the menu would hang off
       the screen; on a phone it spans the row instead. */
    .toolbar-more-menu { left: 0; right: 0; min-width: 0; }
  }

  /* A4 แนวนอนสำหรับทุกฉบับ ตรงกับที่ไฟล์ PDF ใช้ (render_report_pdf())
     สองทางนี้ต้องเป็นกระดาษแผ่นเดียวกัน ไม่งั้นสิ่งที่เห็นตอนสั่งพิมพ์จาก
     เบราว์เซอร์กับไฟล์ที่ดาวน์โหลดจะไม่ตรงกัน */
  @page { size: A4 landscape; margin: 12mm; }

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
    /* Paging is a screen convenience only — the printed report carries every
       row, which is the whole reason someone prints it. */
    .is-paged-out { display: table-row !important; }
    .report-pager { display: none !important; }
    th { background: #f0f0f0 !important; color: #111 !important; }
    /* Every bar, track and pill in these reports IS a background colour — not
       text, not a border — and browsers drop background fills when printing
       unless the reader ticks "Background graphics", which is off by default.
       That is the whole reason the charts print as empty boxes: the markup and
       the layout are fine, the ink just never lands. th carried this exemption
       on its own; the data-bearing fills need it at least as much, since a
       missing table-header tint is cosmetic while a missing bar is the datum.
       Deliberately NOT applied to * — the title bar and filter strip are
       chrome, and forcing those would put a solid dark band across the top of
       every printed page without adding a single number to it. */
    th,
    .trend-chart .bar,
    .bar-track, .bar-fill,
    .dept-bar-track, .dept-bar-fill,
    .month-track, .month-fill,
    .rank-track, .rank-fill, .rank-badge,
    .g-track, .g-fill,
    .swatch, .status-pill, .type-pill {
      -webkit-print-color-adjust: exact; print-color-adjust: exact;
    }
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
  <!-- Every report was a dead end: the only way out was "เลือกเทมเพลตอื่น",
       which leads back to the picker, so anyone who arrived from the
       dashboard had no route home but the browser's own back button. The two
       ways out now lead the row, as quiet links rather than more buttons
       competing with the actions on the right. -->
  <nav class="toolbar-nav" aria-label="ออกจากรายงาน">
    <a href="/admin-dashboard"><span class="material-symbols-outlined" style="font-size:16px;">home</span> หน้าหลัก</a>
    <a href="/admin/reports/print"><span class="material-symbols-outlined" style="font-size:16px;">arrow_back</span> เลือกเทมเพลตอื่น</a>
  </nav>

  <!-- One primary action, everything else behind "เพิ่มเติม". Six equally
       loud pills gave no clue which one people actually came for, and on a
       phone they wrapped into three rows above the report itself. <details>
       does the disclosure with no JS, so it still works in the print/PDF
       pipeline where scripts are stripped. -->
  <div class="toolbar-actions">
    <a class="primary-action" href="<?= htmlspecialchars($pdfUrl) ?>"><span class="material-symbols-outlined" style="font-size:16px;">picture_as_pdf</span> ดาวน์โหลด PDF</a>
    <details class="toolbar-more">
      <summary><span class="material-symbols-outlined" style="font-size:16px;">more_horiz</span> เพิ่มเติม</summary>
      <div class="toolbar-more-menu">
        <button type="button" onclick="window.print()"><span class="material-symbols-outlined" style="font-size:16px;">print</span> พิมพ์หน้านี้</button>
        <?php if (isset($exportUrls['csv'])): ?>
        <a href="<?= htmlspecialchars($exportUrls['csv']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">download</span> ดาวน์โหลด CSV</a>
        <?php endif; ?>
        <?php if (isset($exportUrls['excel'])): ?>
        <a href="<?= htmlspecialchars($exportUrls['excel']) ?>"><span class="material-symbols-outlined" style="font-size:16px;">download</span> ดาวน์โหลด Excel</a>
        <?php endif; ?>
        <button type="button" onclick="document.getElementById('print-settings-panel').hidden = false; this.closest('details').open = false;"><span class="material-symbols-outlined" style="font-size:16px;">tune</span> ตั้งค่าการพิมพ์</button>
      </div>
    </details>
  </div>
</div>

<div id="print-settings-panel" class="settings-panel" hidden>
  <div class="settings-panel-inner">
    <button class="settings-panel-close" type="button" onclick="document.getElementById('print-settings-panel').hidden = true;">
      <span class="material-symbols-outlined">close</span>
    </button>
    <h4>ตั้งค่าก่อนพิมพ์</h4>

    <!-- เดิมตรงนี้มีตัวเลือก "ขนาดกระดาษ" กับ "ธีมสี" ทั้งสองอย่างถูกตัดออก:
         กระดาษล็อกเป็น A4 แนวนอนอย่างเดียวแล้ว (เหตุผลอยู่ที่ render_report_pdf())
         ส่วนธีมสีเปลี่ยนได้เฉพาะบนหน้าจอ ไฟล์ PDF ที่ดาวน์โหลดยังเป็นสีเดิมเสมอ
         เพราะ mPDF ไม่ได้รันสคริปต์ที่เปลี่ยนสี — ตัวเลือกที่ไม่มีผลกับสิ่งที่
         ผู้ใช้กำลังจะเอาไปส่ง คือตัวเลือกที่หลอกให้เข้าใจผิด -->
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
    // The month pickers are <select> now (render_month_select) — matching only
    // input[type=month] here left them without their re-submit, so changing the
    // month quietly did nothing.
    '.compare-filter select, .compare-filter input[type="month"], ' +
    '.month-filter select, .month-filter input[type="month"]'
  ).forEach(function (el) {
    el.addEventListener('change', function () {
      el.form.submit();
    });
  });

  // Long tables get a pager on screen. Threshold matches PAGE_SIZE in
  // assets/js/pagination.js so every table in the app counts pages the same
  // way. mPDF never runs this — the ?format=pdf path renders the PHP output
  // directly — so the exported file is unaffected.
  var REPORT_ROWS_PER_PAGE = 10;
  document.querySelectorAll('.table-wrap table tbody').forEach(function (tbody) {
    var rows = Array.prototype.slice.call(tbody.children).filter(function (el) {
      return el.tagName === 'TR';
    });
    if (rows.length <= REPORT_ROWS_PER_PAGE) return;

    var wrap = tbody.closest('.table-wrap');
    if (!wrap) return;
    var pager = document.createElement('div');
    pager.className = 'report-pager print-hidden';
    wrap.parentNode.insertBefore(pager, wrap.nextSibling);

    var page = 1;
    var totalPages = Math.ceil(rows.length / REPORT_ROWS_PER_PAGE);

    function draw() {
      var start = (page - 1) * REPORT_ROWS_PER_PAGE;
      var end = start + REPORT_ROWS_PER_PAGE;
      rows.forEach(function (tr, i) {
        if (i < start || i >= end) tr.classList.add('is-paged-out');
        else tr.classList.remove('is-paged-out');
      });
      pager.innerHTML =
        '<span class="rp-info">แสดง ' + (start + 1).toLocaleString() + '–' +
        Math.min(end, rows.length).toLocaleString() + ' จาก ' + rows.length.toLocaleString() +
        ' แถว (พิมพ์ออกมาได้ครบทุกแถว)</span>' +
        '<button type="button" data-rp="prev"' + (page === 1 ? ' disabled' : '') + '>ก่อนหน้า</button>' +
        '<span>หน้า ' + page + ' / ' + totalPages + '</span>' +
        '<button type="button" data-rp="next"' + (page === totalPages ? ' disabled' : '') + '>ถัดไป</button>';
      pager.querySelectorAll('button[data-rp]').forEach(function (b) {
        if (b.disabled) return;
        b.addEventListener('click', function () {
          page += b.dataset.rp === 'next' ? 1 : -1;
          draw();
        });
      });
    }
    draw();
  });
</script>
</body>
</html>
<?php
    exit;
}
