<?php
// Ports app.py's admin_report_dashboard() + templates/report_dashboard.html,
// extended with the fuller filter/KPI/chart set from the report-system
// redesign. Every aggregate query below goes through src/reports/aggregate.php
// so this report, monthly.php, and department.php can never disagree on how
// "total events"/"unique students"/"department breakdown" etc. are computed
// for the same filters — the numbers here must always match what the CSV/
// Excel export and the other reports would show for the same period.
//
// Presentation follows the dataviz skill's method: form before color (single
// values → stat tiles, not decorative rings; the one genuine percentage gets
// the one meter), one sequential brand hue for every magnitude bar (never a
// rainbow across nominal categories — that's identity-channel spend on data
// the bar length already shows), neutral ink for stat-tile values (color is
// reserved for marks and delta direction), and a single hero figure instead
// of six same-sized numbers competing for attention.

// Tiny inline SVG trend line for a stat tile's sparkline — pure presentation
// over an already-computed series (no new aggregation).
function render_dashboard_sparkline(array $values, string $color, bool $isPdfExport = false): string
{
    if ($isPdfExport || !$values) {
        return '';
    }
    $max = max($values) ?: 1;
    $w = 72;
    $h = 24;
    $n = count($values);
    $points = [];
    foreach ($values as $i => $v) {
        $x = $n > 1 ? ($i / ($n - 1)) * $w : $w / 2;
        $y = $h - ($v / $max) * ($h - 2) - 1;
        $points[] = round($x, 1) . ',' . round($y, 1);
    }
    $pointsAttr = htmlspecialchars(implode(' ', $points));
    $colorAttr = htmlspecialchars($color);
    return "<svg class=\"sparkline\" viewBox=\"0 0 $w $h\" preserveAspectRatio=\"none\">"
        . "<polyline points=\"$pointsAttr\" fill=\"none\" stroke=\"$colorAttr\" stroke-width=\"2\" stroke-linecap=\"round\" stroke-linejoin=\"round\" /></svg>";
}

// Ring gauge for the one genuine percentage in this report (a department's
// share of total traffic) — replaces the earlier horizontal meter bar at the
// user's request to match a SaaS-dashboard reference; track and fill are two
// steps of the same brand-blue ramp, same "one sequential hue" rule the bar
// version followed.
function render_dashboard_ring(float $pct, string $color): string
{
    $pct = max(0, min(100, $pct));
    $r = 30;
    $stroke = 8;
    $size = ($r + $stroke) * 2;
    $c = $size / 2;
    $circumference = 2 * M_PI * $r;
    $offset = $circumference * (1 - $pct / 100);
    $colorAttr = htmlspecialchars($color);
    return "<svg width=\"$size\" height=\"$size\" viewBox=\"0 0 $size $size\">"
        . "<circle cx=\"$c\" cy=\"$c\" r=\"$r\" fill=\"none\" stroke=\"#dbeafe\" stroke-width=\"$stroke\" />"
        . "<circle cx=\"$c\" cy=\"$c\" r=\"$r\" fill=\"none\" stroke=\"$colorAttr\" stroke-width=\"$stroke\" "
        . 'stroke-dasharray="' . round($circumference, 2) . '" stroke-dashoffset="' . round($offset, 2) . '" '
        . "stroke-linecap=\"round\" transform=\"rotate(-90 $c $c)\" />"
        . '</svg>';
}

// ---------------------------------------------------------------------
// เนื้อหาของไฟล์ PDF สำหรับรายงานแดชบอร์ด
//
// สร้างแยกจากหน้าจอทั้งก้อน ไม่ใช่เอา HTML ของหน้าจอมาซ่อนบางส่วนด้วย CSS
// อย่างที่รายงานอื่นทำ เหตุผลคือ mPDF ไม่รองรับ flexbox/grid/SVG และหน้าจอ
// ของรายงานนี้สร้างจากสามอย่างนั้นเกือบทั้งหมด การไล่ซ่อนทีละคลาสเคยทำให้
// ได้ไฟล์ที่มีหน้าว่าง 50-300 หน้า และเหลือส่วนที่หลุดมาแบบไม่มีสไตล์
//
// ที่นี่ใช้เฉพาะสิ่งที่ mPDF วาดได้แน่นอน: <table>, inline style และรูป PNG
// ที่ GD วาดมาให้แล้ว
// ---------------------------------------------------------------------
// ชนิดไอคอนและสีของการ์ดตัวเลขสำคัญ — ไล่ตามภาพต้นแบบ: น้ำเงิน เขียว ส้ม ม่วง
const DASH_KPI_TINTS = [
    ['people', [232, 238, 253], [79, 110, 247]],
    ['people', [230, 247, 236], [34, 197, 94]],
    ['person', [254, 243, 226], [246, 167, 35]],
    ['clock',  [243, 232, 253], [168, 85, 247]],
];

// จานสีของกราฟวงกลมแผนกวิชา เรียงตามภาพต้นแบบ
const DASH_DEPT_PALETTE = [
    [10, 111, 184], [16, 163, 127], [34, 197, 94], [234, 179, 8], [245, 158, 11],
    [249, 115, 22], [168, 85, 247], [147, 51, 234], [126, 34, 206],
];

function dashboard_kpi_card(int $index, string $label, string $value, string $unit, ?float $delta): string
{
    [$iconKind, $tint, $accent] = DASH_KPI_TINTS[$index % count(DASH_KPI_TINTS)];

    // แถวบอกการเปลี่ยนแปลง: ขึ้นเป็นเขียว ลงเป็นแดง เท่าเดิมเป็นเทา
    //
    // ไม่ใช้อักขระลูกศร (▲ ▼) เพราะฟอนต์ที่ฝังใน PDF มีแต่ Sarabun กับ
    // IBM Plex Sans Thai ซึ่งไม่มีรูปทรงพวกนี้ ตัวที่ไม่มีจะพิมพ์ออกมาเป็น
    // กล่องสี่เหลี่ยมว่าง — ใช้สีของคำบอกทิศทางแทน
    if ($delta === null) {
        $deltaHtml = '<div class="dx-delta flat">เทียบช่วงก่อนหน้าไม่ได้</div>';
    } else {
        $flat = abs($delta) < 0.05;
        $dir = $flat ? 'flat' : ($delta > 0 ? 'up' : 'down');
        $word = $flat ? 'เท่าเดิม' : ($delta > 0 ? 'เพิ่มขึ้น' : 'ลดลง');
        $deltaHtml = '<div class="dx-delta ' . $dir . '"><b>' . $word . ' ' . abs(round($delta, 1)) . '%</b>'
            . '<span> จากช่วงก่อนหน้า</span></div>';
    }

    return '<td class="dx-kpi-cell">'
        . '<table class="dx-kpi"><tr>'
        . '<td class="dx-kpi-icon"><img width="33" src="' . render_pdf_icon_tile($iconKind, $tint, $accent) . '"></td>'
        . '<td class="dx-kpi-text">'
        . '<div class="dx-kpi-label">' . htmlspecialchars($label) . '</div>'
        . '<div class="dx-kpi-value">' . htmlspecialchars($value)
        . '<span class="dx-kpi-unit"> ' . htmlspecialchars($unit) . '</span></div>'
        . $deltaHtml
        . '</td></tr></table></td>';
}

function dashboard_panel_open(string $title): string
{
    return '<td class="dx-panel-cell">'
        . '<table class="dx-panel"><tr><td class="dx-panel-body">'
        . '<div class="dx-panel-title">' . htmlspecialchars($title) . '</div>';
}

function dashboard_panel_close(): string
{
    return '</td></tr></table></td>';
}

// รวมแผนกที่มีสัดส่วนน้อยเข้าเป็น "อื่นๆ" — กราฟวงกลมที่มีสิบกว่าชิ้นอ่านไม่ออก
// ชิ้นท้ายๆ จะบางจนมองไม่เห็นและคำอธิบายก็ยาวเกินความสูงของกล่อง
function dashboard_group_departments(array $breakdown, int $keep = 9): array
{
    $slices = [];
    foreach (array_slice($breakdown, 0, $keep) as $i => $d) {
        $slices[] = [
            'label' => $d['name'],
            'value' => $d['count'],
            'color' => DASH_DEPT_PALETTE[$i % count(DASH_DEPT_PALETTE)],
        ];
    }
    $rest = array_slice($breakdown, $keep);
    if ($rest) {
        $slices[] = [
            'label' => 'อื่นๆ',
            'value' => array_sum(array_column($rest, 'count')),
            'color' => [176, 184, 196],
        ];
    }
    return $slices;
}

// กราฟวงกลมสำหรับ "หน้าจอ" — SVG กับข้อความจริง ไม่ใช่รูป PNG
//
// ไฟล์ PDF ใช้รูปที่ GD วาด เพราะ mPDF วาด SVG ไม่ได้ แต่บนจอการฝังตัวหนังสือ
// ลงในรูปมีปัญหาที่แก้ไม่ได้: ตัวหนังสือย่อตามความกว้างของกล่อง พอเปิดบน
// มือถือที่กล่องกว้าง ~360px ตัวหนังสือในคำอธิบายจะเหลือราว 7px ซึ่งอ่านไม่ออก
//
// หน้าตาถูกจัดให้ตรงกับฝั่ง PDF ทั้งสี ขนาดสัดส่วน และลำดับคอลัมน์
function render_dashboard_donut_html(array $slices, string $centerValue, string $centerUnit, string $centerTop = '', string $unit = 'ครั้ง'): string
{
    $total = 0.0;
    foreach ($slices as $s) {
        $total += max(0.0, (float) $s['value']);
    }

    $size = 168;
    $stroke = 34;
    $r = ($size - $stroke) / 2;
    $circumference = 2 * M_PI * $r;
    $c = $size / 2;

    $svg = '<svg class="dxd-svg" viewBox="0 0 ' . $size . ' ' . $size . '" role="img" aria-label="'
        . htmlspecialchars($total > 0 ? 'กราฟวงกลมแสดงสัดส่วน' : 'ไม่มีข้อมูล') . '">'
        . '<circle cx="' . $c . '" cy="' . $c . '" r="' . $r . '" fill="none" stroke="#e8ecf3" stroke-width="' . $stroke . '"/>';

    $offset = 0.0;
    foreach ($slices as $s) {
        $value = max(0.0, (float) $s['value']);
        if ($total <= 0 || $value <= 0) {
            continue;
        }
        $len = ($value / $total) * $circumference;
        $rgb = sprintf('rgb(%d,%d,%d)', $s['color'][0], $s['color'][1], $s['color'][2]);
        $svg .= '<circle cx="' . $c . '" cy="' . $c . '" r="' . $r . '" fill="none"'
            . ' stroke="' . $rgb . '" stroke-width="' . $stroke . '"'
            . ' stroke-dasharray="' . round($len, 2) . ' ' . round($circumference - $len, 2) . '"'
            . ' stroke-dashoffset="' . round(-$offset, 2) . '"'
            // หมุนให้ชิ้นแรกเริ่มที่ 12 นาฬิกา ซึ่งเป็นจุดที่สายตาเริ่มอ่านวงกลม
            . ' transform="rotate(-90 ' . $c . ' ' . $c . ')"><title>'
            . htmlspecialchars($s['label'] . ': ' . number_format($value)) . '</title></circle>';
        $offset += $len;
    }
    $svg .= '</svg>';

    $center = '<div class="dxd-center">';
    if ($centerTop !== '') {
        $center .= '<span class="dxd-center-top">' . htmlspecialchars($centerTop) . '</span>';
    }
    $center .= '<span class="dxd-center-value">' . htmlspecialchars($centerValue) . '</span>'
        . '<span class="dxd-center-unit">' . htmlspecialchars($centerUnit) . '</span></div>';

    $rows = '';
    if (!$slices || $total <= 0) {
        $rows = '<li class="dxd-empty">ไม่มีข้อมูลในช่วงเวลาที่เลือก</li>';
    } else {
        foreach ($slices as $s) {
            $pct = $total > 0 ? round((float) $s['value'] / $total * 100, 1) : 0;
            $rgb = sprintf('rgb(%d,%d,%d)', $s['color'][0], $s['color'][1], $s['color'][2]);
            $rows .= '<li>'
                . '<span class="dxd-dot" style="background:' . $rgb . '"></span>'
                . '<span class="dxd-label" title="' . htmlspecialchars($s['label']) . '">' . htmlspecialchars($s['label']) . '</span>'
                . '<span class="dxd-pct">' . $pct . '%</span>'
                . '<span class="dxd-count">(' . number_format((float) $s['value']) . ' ' . htmlspecialchars($unit) . ')</span>'
                . '</li>';
        }
    }

    return '<div class="dxd">'
        . '<div class="dxd-chart">' . $svg . $center . '</div>'
        . '<ul class="dxd-legend">' . $rows . '</ul>'
        . '</div>';
}

// กราฟแท่งรายชั่วโมงสำหรับหน้าจอ — div ธรรมดา ป้ายแกนเป็นข้อความจริง
function render_dashboard_hours_html(array $hours): string
{
    $max = 1;
    foreach ($hours as $h) {
        $max = max($max, (int) $h['count']);
    }
    $bars = '';
    foreach ($hours as $h) {
        $pct = $h['count'] > 0 ? max(2, (int) round($h['count'] / $max * 100)) : 0;
        $label = sprintf('%02d:00 น. — %s ครั้ง', $h['hour'], number_format($h['count']));
        $bars .= '<div class="dxb-slot" title="' . htmlspecialchars($label) . '">'
            . '<div class="dxb-bar" style="height:' . $pct . '%"></div>'
            . '</div>';
    }
    // ป้ายแกนทุก 2 ชั่วโมง เท่ากับที่กราฟฝั่ง PDF ทำ
    $ticks = '';
    foreach ($hours as $i => $h) {
        $ticks .= '<span>' . ($i % 2 === 0 ? sprintf('%02d', $h['hour']) : '') . '</span>';
    }
    return '<div class="dxb"><div class="dxb-bars">' . $bars . '</div>'
        . '<div class="dxb-axis">' . $ticks . '</div></div>';
}

// ---------------------------------------------------------------------
// เนื้อหาแดชบอร์ด ใช้ร่วมกันทั้งหน้าจอ การสั่งพิมพ์ และไฟล์ PDF
//
// เดิมหน้าจอกับไฟล์ PDF มีเนื้อหาคนละชุด แก้ฝั่งหนึ่งแล้วอีกฝั่งยังเป็นของเก่า
// ตอนนี้เหลือชุดเดียว ต่างกันแค่ CSS: ฝั่ง PDF อยู่ใน $pdfStyle (layout.php)
// ฝั่งจออยู่ใน $extraStyle ของรายงานนี้ เพราะ mPDF กับเบราว์เซอร์คิดขนาด
// ตัวอักษรและความกว้างตารางไม่เหมือนกัน
//
// จัดหน้าด้วย <table> ซ้อนกันแทน div เพราะ mPDF ไม่มี flexbox — ตารางคือวิธี
// เดียวที่มันจัดของสองกล่องให้อยู่ข้างกันได้อย่างแน่นอน
//
// กราฟทุกตัวเป็นรูป PNG ที่ GD วาด ไม่ใช่ SVG หรือ div: mPDF วาดกราฟจาก CSS
// สมัยใหม่ไม่ได้ (เคยได้ไฟล์ที่มีหน้าว่างพ่วงมา 50-300 หน้า) และการใช้รูป
// เดียวกันทั้งสองฝั่งเป็นสิ่งที่รับประกันว่าสิ่งที่เห็นบนจอกับในไฟล์ตรงกันจริง
// ---------------------------------------------------------------------
function render_dashboard_body(array $c): string
{
    $agg = $c['agg'];
    // กราฟบนจอเป็น SVG กับข้อความจริง ส่วนในไฟล์ PDF เป็นรูปที่ GD วาด
    // เพราะ mPDF วาด SVG ไม่ได้ — หน้าตาถูกจัดให้ตรงกันทั้งสองฝั่ง
    $forPdf = !empty($c['for_pdf']);

    $out = '<div class="dx-head">'
        . '<div class="dx-org">วิทยาลัยเทคนิคนครนายก</div>'
        . '<div class="dx-title">ภาพรวมการใช้งานห้องสมุด</div>'
        . '<div class="dx-sub">สรุปข้อมูลการเข้าใช้ห้องสมุด — ' . htmlspecialchars($c['periodLabel']) . '</div>'
        . '</div>';

    if ($agg['total_events'] === 0) {
        return $out . '<div class="dx-empty">ไม่มีข้อมูลการเช็คชื่อในช่วงเวลาที่เลือก</div>';
    }

    // ---- การ์ดตัวเลขสำคัญ 4 ใบ ----
    $avgMin = $c['avgSessionMinutes'];
    $avgHours = $avgMin !== null ? round($avgMin / 60, 2) : null;

    $out .= '<table class="dx-kpi-row"><tr>'
        . dashboard_kpi_card(0, 'จำนวนการเข้าใช้ทั้งหมด', number_format($agg['total_events']), 'ครั้ง', $c['totalDelta'])
        . dashboard_kpi_card(1, 'ผู้ใช้ไม่ซ้ำ', number_format($agg['unique_students']), 'คน', $c['uniqueDelta'])
        . dashboard_kpi_card(2, 'เฉลี่ยการเข้าใช้ต่อวัน', number_format($agg['avg_daily']), 'ครั้ง', null)
        . dashboard_kpi_card(3, 'เวลาใช้งานเฉลี่ย', $avgHours !== null ? number_format($avgHours, 2) : '-', 'ชม.', null)
        . '</tr></table>';

    // ---- แถวที่ 1: ระดับชั้น | แผนกวิชา ----
    // ทั้งสามวงนับเป็น "ครั้ง" เท่ากันหมด ตัวเลขกลางวงจึงตรงกับการ์ดใบแรกและ
    // เทียบกันได้ ถ้าวงหนึ่งนับคนอีกวงนับครั้ง คนอ่านจะเข้าใจว่าข้อมูลขัดกันเอง
    $lv = $c['levelVisits'];
    $levelSlices = [];
    if ($lv['ปวช.'] > 0) {
        $levelSlices[] = ['label' => 'ปวช.', 'value' => $lv['ปวช.'], 'color' => [10, 111, 184]];
    }
    if ($lv['ปวส.'] > 0) {
        $levelSlices[] = ['label' => 'ปวส.', 'value' => $lv['ปวส.'], 'color' => [16, 163, 127]];
    }
    if ($lv['other'] > 0) {
        $levelSlices[] = ['label' => 'ไม่ระบุระดับชั้น', 'value' => $lv['other'], 'color' => [102, 110, 124]];
    }

    $totalText = number_format($agg['total_events']);
    $out .= '<table class="dx-row"><tr>'
        . dashboard_panel_open('สัดส่วนผู้ใช้ตามประเภท')
        . ($forPdf
            ? '<img class="dx-chart" src="' . render_pdf_donut_chart($levelSlices, $totalText, 'ครั้ง', 1400, 470, ['unit' => 'ครั้ง', 'diameter' => 380, 'scale' => 2.1]) . '">'
            : render_dashboard_donut_html($levelSlices, $totalText, 'ครั้ง', '', 'ครั้ง'))
        . dashboard_panel_close()
        . dashboard_panel_open('สัดส่วนการเข้าใช้ตามแผนก')
        . ($forPdf
            ? '<img class="dx-chart" src="' . render_pdf_donut_chart(
                dashboard_group_departments($c['deptBreakdown']),
                $totalText,
                'ครั้ง',
                1400,
                470,
                ['unit' => 'ครั้ง', 'center_top' => 'รวม', 'diameter' => 350, 'scale' => 1.95]
            ) . '">'
            : render_dashboard_donut_html(dashboard_group_departments($c['deptBreakdown']), $totalText, 'ครั้ง', 'รวม', 'ครั้ง'))
        . dashboard_panel_close()
        . '</tr></table>';

    // ---- แถวที่ 2: ช่วงเวลาที่ใช้มากที่สุด | เพศ ----
    $gv = $c['genderVisits'];
    $genderSlices = [];
    if ($gv['male'] > 0) {
        $genderSlices[] = ['label' => 'ชาย', 'value' => $gv['male'], 'color' => [10, 111, 184]];
    }
    if ($gv['female'] > 0) {
        $genderSlices[] = ['label' => 'หญิง', 'value' => $gv['female'], 'color' => [219, 39, 119]];
    }
    if ($gv['unknown'] > 0) {
        $genderSlices[] = ['label' => 'ไม่ระบุ', 'value' => $gv['unknown'], 'color' => [102, 110, 124]];
    }

    $peak = $c['hourly']['peak_hour'];
    $peakText = $peak ? sprintf('%02d:00 - %02d:00 น.', $peak['hour'], $peak['hour'] + 1) : '-';
    $peakCount = $peak ? number_format($peak['count']) : '-';

    $out .= '<table class="dx-row"><tr>'
        . dashboard_panel_open('ช่วงเวลาที่มีการใช้งานมากที่สุด')
        . '<table class="dx-peak"><tr>'
        . '<td class="dx-peak-info">'
        . '<img class="dx-peak-icon" width="44" src="' . render_pdf_icon_tile('clock', [232, 238, 253], [10, 111, 184]) . '">'
        . '<div class="dx-peak-range">' . htmlspecialchars($peakText) . '</div>'
        . '<div class="dx-peak-note">มีการเข้าใช้สูงสุด</div>'
        . '<div class="dx-peak-count">' . $peakCount . '<span class="dx-peak-unit"> ครั้ง</span></div>'
        . '</td>'
        . '<td class="dx-peak-chart">'
        . ($forPdf
            ? '<img class="dx-chart" src="' . render_pdf_bar_chart(
                array_map(static fn ($h) => sprintf('%02d', $h['hour']), $c['hourly']['hours']),
                array_column($c['hourly']['hours'], 'count'),
                'vertical',
                420,
                null,
                1400
            ) . '">'
            : render_dashboard_hours_html($c['hourly']['hours']))
        . '</td></tr></table>'
        . dashboard_panel_close()
        . dashboard_panel_open('สัดส่วนผู้ใช้ตามเพศ')
        . ($forPdf
            ? '<img class="dx-chart" src="' . render_pdf_donut_chart($genderSlices, $totalText, 'ครั้ง', 1400, 470, ['unit' => 'ครั้ง', 'center_top' => 'รวม', 'diameter' => 380, 'scale' => 2.1]) . '">'
            : render_dashboard_donut_html($genderSlices, $totalText, 'ครั้ง', 'รวม', 'ครั้ง'))
        . dashboard_panel_close()
        . '</tr></table>';

    return $out;
}

// Average minutes-per-visit within [startDate, endDate] — pairs each 'in' log
// with the next 'out' log for the same user (checkin_handlers.php's toggle
// guarantees logs strictly alternate in/out per user, so simple adjacency
// pairing is correct). An 'in' with no matching 'out' in range (still checked
// in, or checked out after the range) is dropped rather than guessed at.
// Self-contained here (not in aggregate.php) since no other report needs it.
function aggregate_avg_session_minutes(PDO $conn, string $startDate, string $endDate, array $filters = []): ?float
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT c.user_id, c.type, c.timestamp
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         ORDER BY c.user_id, c.log_id"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $openIn = [];
    $minutes = [];
    foreach ($stmt->fetchAll() as $row) {
        $uid = $row['user_id'];
        if ($row['type'] === 'in') {
            $openIn[$uid] = $row['timestamp'];
        } elseif ($row['type'] === 'out' && isset($openIn[$uid])) {
            $diff = (strtotime($row['timestamp']) - strtotime($openIn[$uid])) / 60;
            if ($diff > 0) {
                $minutes[] = $diff;
            }
            unset($openIn[$uid]);
        }
    }

    return $minutes ? array_sum($minutes) / count($minutes) : null;
}

// "1 ชม. 45 นาที" / "45 นาที" (< 1 hour) / "-" (no data) — matches the report's
// all-Thai number formatting elsewhere instead of a decimal-hours figure.
function format_minutes_thai(?float $minutes): string
{
    if ($minutes === null) {
        return '-';
    }
    $total = (int) round($minutes);
    $h = intdiv($total, 60);
    $m = $total % 60;
    if ($h === 0) {
        return "{$m} นาที";
    }
    return "{$h} ชม. {$m} นาที";
}

// Male/female/unspecified split among unique visitors (not the whole roster)
// in [startDate, endDate] — "unspecified" covers roster-imported students,
// since import_students.php doesn't collect gender (only self-signup does).
// Self-contained here (not aggregate.php) since no other report needs it.
// จำนวน "ครั้ง" ที่เข้าใช้ แยกตามระดับชั้นและตามเพศ
//
// ต่างจาก aggregate_level_breakdown()/aggregate_gender_breakdown() ที่นับ "คน"
// ไม่ซ้ำ — กราฟวงกลมทั้งสามวงบนหน้าแดชบอร์ดนับเป็นครั้งเหมือนกันหมด ตัวเลข
// ตรงกลางวงจึงเท่ากับการ์ด "จำนวนการเข้าใช้ทั้งหมด" และเทียบกันได้ ถ้าวงหนึ่ง
// นับคนอีกวงนับครั้ง คนอ่านจะเข้าใจว่าข้อมูลขัดกันเอง
function aggregate_visits_by(PDO $conn, string $column, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.$column AS k, COUNT(*) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.$column"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $out = [];
    foreach ($stmt->fetchAll() as $row) {
        $out[trim((string) $row['k'])] = (int) $row['cnt'];
    }
    return $out;
}

function aggregate_level_visits(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    $raw = aggregate_visits_by($conn, 'level', $startDate, $endDate, $filters);
    $out = ['ปวช.' => 0, 'ปวส.' => 0, 'other' => 0];
    foreach ($raw as $level => $count) {
        $key = isset($out[$level]) ? $level : 'other';
        $out[$key] += $count;
    }
    return $out;
}

function aggregate_gender_visits(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    $raw = aggregate_visits_by($conn, 'gender', $startDate, $endDate, $filters);
    $out = ['male' => 0, 'female' => 0, 'unknown' => 0];
    foreach ($raw as $gender => $count) {
        $key = isset($out[$gender]) && $gender !== '' ? $gender : 'unknown';
        $out[$key] += $count;
    }
    return $out;
}

// สัดส่วนผู้ใช้แยกตามระดับชั้น (ปวช. / ปวส.) นับ "คน" ไม่ซ้ำ ไม่ใช่จำนวนครั้ง
// เพราะคำถามที่กราฟนี้ตอบคือ "ผู้ใช้ห้องสมุดเป็นนักเรียนหรือนักศึกษามากกว่ากัน"
function aggregate_level_breakdown(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.level, COUNT(DISTINCT s.student_id) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.level"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $counts = ['ปวช.' => 0, 'ปวส.' => 0, '' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $level = trim((string) $row['level']);
        $key = isset($counts[$level]) ? $level : '';
        $counts[$key] += (int) $row['cnt'];
    }
    $total = array_sum($counts);
    $pct = fn (int $n) => $total ? round($n / $total * 100, 1) : 0;

    return [
        'vocational' => $counts['ปวช.'], 'vocational_pct' => $pct($counts['ปวช.']),
        'diploma' => $counts['ปวส.'], 'diploma_pct' => $pct($counts['ปวส.']),
        'unknown' => $counts[''], 'unknown_pct' => $pct($counts['']),
        'total' => $total,
    ];
}

function aggregate_gender_breakdown(PDO $conn, string $startDate, string $endDate, array $filters = []): array
{
    [$filterClauses, $filterParams] = build_filter_clause($filters);
    $where = implode(' AND ', array_merge(['DATE(c.timestamp) BETWEEN ? AND ?'], $filterClauses));
    $stmt = $conn->prepare(
        "SELECT s.gender, COUNT(DISTINCT s.student_id) AS cnt
         FROM checkin_logs c
         JOIN users u ON u.user_id = c.user_id
         JOIN students s ON s.student_id = u.student_id
         WHERE $where
         GROUP BY s.gender"
    );
    $stmt->execute(array_merge([$startDate, $endDate], $filterParams));

    $counts = ['male' => 0, 'female' => 0, 'unknown' => 0];
    foreach ($stmt->fetchAll() as $row) {
        $counts[$row['gender'] ?: 'unknown'] = (int) $row['cnt'];
    }
    $total = array_sum($counts);

    $pct = fn(int $n) => $total ? round($n / $total * 100, 1) : 0;
    return [
        'male' => $counts['male'], 'male_pct' => $pct($counts['male']),
        'female' => $counts['female'], 'female_pct' => $pct($counts['female']),
        'unknown' => $counts['unknown'], 'unknown_pct' => $pct($counts['unknown']),
        'total' => $total,
    ];
}

// Signed delta badge (↑/↓ + %), colored by direction — "more library usage" is
// the good direction here, matching the up=green/down=amber pairing monthly.php
// and executive.php already use.
// $compact drops the "เทียบเดือนก่อน" suffix — the narrow stat tiles don't
// have room for it (it was wrapping one word per line; the hero tile is wide
// enough to keep it in full).
function render_dashboard_delta(?float $delta, bool $compact = false): string
{
    if ($delta === null) {
        return '';
    }
    $cls = $delta > 0 ? 'up' : ($delta < 0 ? 'down' : 'flat');
    $arrow = $delta > 0 ? '↑' : ($delta < 0 ? '↓' : '—');
    $suffix = $compact ? '' : ' เทียบเดือนก่อน';
    return '<span class="delta ' . $cls . '" title="เทียบกับเดือนก่อนหน้า">' . $arrow . ' ' . abs($delta) . '%' . $suffix . '</span>';
}

function handle_report_dashboard(): void
{
    require_login();
    require_admin();

    // mPDF (the PDF export path — see render_report_pdf() in layout.php)
    // doesn't reliably honor display:none on an inline <svg>, unlike a
    // block-level div wrapping one — CSS alone couldn't suppress these
    // sparklines in the exported PDF, so skip generating them there instead.
    $isPdfExport = ($_GET['format'] ?? '') === 'pdf';

    // แดชบอร์ดออกแบบมาให้จบในกระดาษ A4 แนวนอนหน้าเดียว ตอนนี้ทั้งระบบล็อก
    // แนวนอนแล้ว (render_report_pdf() ใน layout.php) จึงไม่ต้องตั้งค่าอะไรที่นี่

    $conn = get_db_connection();

    // The month <select> and the date-range pair are two ways to say the same
    // thing, and they used to be resolved by "a full range always wins". That
    // made the month box dead in the one state people actually met it in:
    // clicking "สัปดาห์นี้" navigates to a URL carrying start_date/end_date,
    // those land in the form's date inputs, and every later submit still
    // carried them — so choosing a month changed the dropdown and nothing
    // else. It looked broken because it was.
    //
    // An explicitly submitted month now wins instead. The distinction that
    // makes this safe is present-and-non-empty vs absent: the "สัปดาห์นี้"
    // link carries no month key at all, and the select's placeholder option
    // ("— ใช้ช่วงวันที่ด้านล่าง —") submits an empty one, so neither is
    // mistaken for a real choice.
    $monthParam = isset($_GET['month']) ? trim((string) $_GET['month']) : '';
    $startDateParam = trim((string) ($_GET['start_date'] ?? ''));
    $endDateParam = trim((string) ($_GET['end_date'] ?? ''));
    $useCustomRange = $startDateParam !== '' && $endDateParam !== '' && $monthParam === '';

    // A range that no longer applies must not stay filled in, or the form
    // would show a date span that the report on screen is not using — and the
    // next submit would silently resurrect it.
    if ($monthParam !== '') {
        $startDateParam = '';
        $endDateParam = '';
    }
    $month = $monthParam !== '' ? $monthParam : date('Y-m');

    $filters = [
        'department' => trim((string) ($_GET['department'] ?? '')),
        'level' => trim((string) ($_GET['level'] ?? '')),
        'semester' => trim((string) ($_GET['semester'] ?? '')),
        'academic_year' => trim((string) ($_GET['academic_year'] ?? '')),
    ];

    if ($useCustomRange) {
        $startDate = $startDateParam;
        $endDate = $endDateParam;
        $periodLabel = "ช่วงวันที่ $startDate ถึง $endDate";
    } else {
        [$startDate, $endDate] = month_bounds($month);
        $periodLabel = thai_month_label($month);
    }

    // "สัปดาห์นี้" quick-filter chip: Monday..today of the current ISO week —
    // just a pre-computed start_date/end_date link, so it reuses the exact
    // same custom-range code path as manually typed dates (no new branch).
    $todayDate = new DateTime();
    $thisWeekStart = (clone $todayDate)->modify('monday this week')->format('Y-m-d');
    $thisWeekEnd = $todayDate->format('Y-m-d');
    $isThisWeek = $useCustomRange && $startDateParam === $thisWeekStart && $endDateParam === $thisWeekEnd;
    $isThisMonth = !$useCustomRange && $month === date('Y-m');

    // Both quick-filter chips keep whatever department/level/semester/academic_year
    // is already selected — only the timeframe changes.
    $chipCommonParams = array_filter([
        'department' => $filters['department'] ?: null,
        'level' => $filters['level'] ?: null,
        'semester' => $filters['semester'] ?: null,
        'academic_year' => $filters['academic_year'] ?: null,
    ]);
    $thisWeekHref = '/admin/reports/print/dashboard?' . http_build_query(array_merge($chipCommonParams, [
        'start_date' => $thisWeekStart, 'end_date' => $thisWeekEnd,
    ]));
    $thisMonthHref = '/admin/reports/print/dashboard?' . http_build_query(array_merge($chipCommonParams, [
        'month' => date('Y-m'),
    ]));

    // นับเฉพาะแถวที่เป็นการเข้า หนึ่งแถว = มาใช้บริการหนึ่งครั้ง
    $visitFilters = $filters + ['log_type' => 'in'];

    $agg = aggregate_checkin_period($conn, $startDate, $endDate, $visitFilters);
    // ยกเว้นตัวนี้ที่ต้องเห็นทั้งเข้าและออก เพราะมันจับคู่สองแถวเพื่อหาระยะเวลา
    $avgSessionMinutes = aggregate_avg_session_minutes($conn, $startDate, $endDate, $filters);
    $genderBreakdown = aggregate_gender_breakdown($conn, $startDate, $endDate, $visitFilters);
    $levelBreakdown = aggregate_level_breakdown($conn, $startDate, $endDate, $visitFilters);
    $levelVisits = aggregate_level_visits($conn, $startDate, $endDate, $visitFilters);
    $genderVisits = aggregate_gender_visits($conn, $startDate, $endDate, $visitFilters);
    $dailyTrend = aggregate_daily_trend($conn, $startDate, $endDate, $visitFilters);
    $hourly = aggregate_hourly($conn, $startDate, $endDate, $visitFilters);
    $deptBreakdown = aggregate_department_breakdown($conn, $startDate, $endDate, $visitFilters);
    $topDepts = array_slice($deptBreakdown, 0, 8);

    // Month-over-month context for the hero + first stat tile — only when
    // scoped to a calendar month (an arbitrary custom date range has no
    // unambiguous "previous period"). Reuses the exact pct_delta()/
    // previous_month() pattern executive.php and monthly.php already use.
    $totalDelta = null;
    $uniqueDelta = null;
    if (!$useCustomRange) {
        [$prevStart, $prevEnd] = month_bounds(previous_month($month));
        $prevAgg = aggregate_checkin_period($conn, $prevStart, $prevEnd, $visitFilters);
        $totalDelta = pct_delta($agg['total_events'], $prevAgg['total_events']);
        $uniqueDelta = pct_delta($agg['unique_students'], $prevAgg['unique_students']);
    }

    $busiestDay = null;
    foreach ($dailyTrend as $d) {
        if ($d['count'] > 0 && ($busiestDay === null || $d['count'] > $busiestDay['count'])) {
            $busiestDay = ['day' => date('d/m', strtotime($d['date'])), 'count' => $d['count']];
        }
    }

    $summarySentence = $agg['total_events']
        ? build_summary_sentence($periodLabel, $agg, $busiestDay, $topDepts, $hourly['peak_hour'])
        : null;

    $departments = distinct_student_values($conn, 'department');
    $levels = distinct_student_values($conn, 'level');
    $semesters = distinct_student_values($conn, 'semester');
    $academicYears = distinct_student_values($conn, 'academic_year');

    $dashboardContext = [
        'agg' => $agg,
        'filters' => $filters,
        'periodLabel' => $periodLabel,
        'avgSessionMinutes' => $avgSessionMinutes,
        'genderBreakdown' => $genderBreakdown,
        'levelBreakdown' => $levelBreakdown,
        'levelVisits' => $levelVisits,
        'genderVisits' => $genderVisits,
        'dailyTrend' => $dailyTrend,
        'hourly' => $hourly,
        'deptBreakdown' => $deptBreakdown,
        'topDepts' => $topDepts,
        'totalDelta' => $totalDelta,
        'uniqueDelta' => $uniqueDelta,
        'busiestDay' => $busiestDay,
        'summarySentence' => $summarySentence,
    ];

    ob_start();
    ?>
<style>
  /* Tighter than layout.php's default 12mm — this report is meant to read as
     one dense, single-page dashboard (Power BI style), not a multi-page
     document, so print margin is trimmed to reclaim usable width/height. */
  @page { size: A4 landscape; margin: 8mm; }

  /* Slim single-line title bar instead of the shared layout's tall gradient
     hero — overridden here (not in layout.php, which every other report
     still uses as-is) via cascade, since $extraStyle loads after the base
     <style> block. */
  header.report-head {
    background: var(--primary, #1a2947) !important;
    padding: 11px 20px !important;
    display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap;
  }
  header.report-head h1 { font-size: 12.5px !important; margin: 0 !important; font-weight: 700 !important; text-transform: uppercase; letter-spacing: .05em; }
  header.report-head h2 { font-size: 12.5px !important; opacity: .85 !important; font-weight: 400 !important; }

  /* A visibly separate control strip, not another content card — so the
     filters read as "controls" and everything below as "the report". */
  .filter-bar {
    background: var(--surface, #faf9f6) !important;
    border: none !important; border-bottom: 2px solid var(--outline-variant, #e2e8f0) !important;
    border-radius: 0 !important; padding: 10px 2px 16px !important; margin: 0 0 24px !important;
  }

  /* Quick timeframe presets — same chip visual language as the chips in the
     print-settings drawer, just above the detailed filter form instead of
     inside a drawer. */
  .quick-filter-chips { display: flex; align-items: center; gap: 8px; margin: 4px 2px 14px; flex-wrap: wrap; }
  .quick-filter-chips .chip {
    font-size: 12px; font-weight: 700; padding: 6px 14px; border-radius: 999px;
    border: 1px solid var(--outline-variant, #e2e8f0); color: var(--on-surface-variant, #6b6153);
    text-decoration: none;
  }
  .quick-filter-chips .chip.active { background: var(--primary, #1a2947); color: #fff; border-color: var(--primary, #1a2947); }
  .quick-filter-chips .chip-hint { font-size: 11px; color: #666e7c; }
  @media print {
    .quick-filter-chips { display: none !important; }
  }

  /* ---- Single dense dashboard, no tabs ----
     Power-BI-style: every widget visible on one screen/one printed page at
     once, nothing behind a click. A KPI strip of equal-height cards (hero
     included — set apart by a brand-color left border, not a separate
     giant block) replaces the earlier "one huge hero + tab navigation"
     layout, which read as multiple pages stitched together instead of one
     dashboard. */

  /* ---- แดชบอร์ด (dx-*) ฝั่งหน้าจอและการสั่งพิมพ์จากเบราว์เซอร์ ----
     มาร์กอัปชุดเดียวกับไฟล์ PDF (render_dashboard_body) แต่ขนาดตัวอักษรและ
     ระยะห่างคนละชุด เพราะ mPDF กับเบราว์เซอร์คิดขนาดในตารางซ้อนกันไม่เหมือนกัน
     ค่าที่ตั้งไว้ในไฟล์ PDF จึงใหญ่เกินไปเมื่อเบราว์เซอร์เป็นคนวาด */
  main.report-body { max-width: 1440px; }
  .dash-export { max-width: 1440px; margin: 0 auto; }

  .dx-head { text-align: center; margin: 4px 0 18px; }
  .dx-org { font-size: 12px; color: #666e7c; letter-spacing: .02em; }
  .dx-title { font-size: 30px; font-weight: 700; color: #111827; line-height: 1.3; margin-top: 2px; }
  .dx-sub { font-size: 14px; color: #5c6470; margin-top: 2px; }
  .dx-empty {
    border: 1px dashed #d5d0c6; border-radius: 12px; padding: 32px;
    text-align: center; color: #6b6153; background: #fff;
  }

  /* border-spacing แทน gap เพราะโครงเป็น <table> (mPDF ต้องการ) ซึ่งไม่รู้จัก gap */
  /* layout.php ตั้ง table { min-width: 640px } ไว้เป็นกฎกลาง เพื่อให้ตาราง
     ข้อมูลเลื่อนแนวนอนได้บนมือถือแทนที่จะถูกบีบจนอ่านไม่ออก — แต่ที่นี่
     ตารางถูกใช้เป็นโครงจัดหน้า ไม่ใช่ตารางข้อมูล ค่านั้นจึงดันการ์ดให้กว้าง
     640px ทุกใบจนล้นออกนอกจอ ต้องปลดเฉพาะตารางของแดชบอร์ด */
  .dx-kpi-row, .dx-row, .dx-kpi, .dx-panel, .dx-peak { min-width: 0; }
  /* ตารางข้อมูลของ layout.php มีเส้นคั่นใต้ทุกเซลล์ ซึ่งที่นี่กลายเป็นเส้น
     ลอยพาดใต้การ์ดทุกใบ */
  .dx-kpi-row > tbody > tr > td, .dx-row > tbody > tr > td,
  .dx-kpi td, .dx-panel td, .dx-peak td { border: 0; background: none; }
  .dx-kpi-row, .dx-row { display: grid; gap: 14px; width: 100%; }
  .dx-kpi-row > tbody, .dx-row > tbody,
  .dx-kpi-row > tbody > tr, .dx-row > tbody > tr { display: contents; }
  .dx-kpi-row { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .dx-row { grid-template-columns: repeat(2, minmax(0, 1fr)); margin-top: 14px; }
  .dx-kpi-cell, .dx-panel-cell { display: block; width: auto; min-width: 0; }

  .dx-kpi, .dx-panel {
    width: 100%; height: 100%; border-collapse: separate; table-layout: fixed;
    border: 1px solid #e9edf5; border-radius: 14px; background: #fff;
    box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
  }
  /* ให้เนื้อหาชิดบนเสมอ ไม่งั้นการ์ดที่ข้อความสั้นกว่าจะลอยอยู่กลางกล่อง
     ทำให้บรรทัดแรกของแต่ละใบไม่ตรงแนวกัน */
  .dx-kpi-text, .dx-panel-body { vertical-align: top; }
  .dx-kpi-icon { width: 54px; padding: 14px 0 14px 14px; vertical-align: top; }
  .dx-kpi-icon img { width: 40px; height: 40px; display: block; }
  .dx-kpi-text { padding: 14px 14px 14px 10px; vertical-align: top; }
  .dx-kpi-label { font-size: 13px; color: #5c6470; line-height: 1.35; }
  .dx-kpi-value { font-size: 28px; font-weight: 700; color: #111827; line-height: 1.25; margin: 1px 0 3px; }
  .dx-kpi-unit { font-size: 14px; font-weight: 400; color: #5c6470; }
  .dx-delta { font-size: 13px; color: #666e7c; }
  .dx-delta b { font-weight: 700; }
  .dx-delta.up b { color: #16a34a; }
  .dx-delta.down b { color: #dc2626; }
  .dx-delta.flat b { color: #666e7c; }

  .dx-panel-body { padding: 18px 20px 20px; }
  .dx-panel-title { font-size: 19px; font-weight: 700; color: #111827; margin-bottom: 10px; }
  .dx-chart { width: 100%; height: auto; display: block; }


  /* ---- กราฟบนจอ: SVG + ข้อความจริง ----
     ขนาดตัวอักษรอ้างอิงจากแบบที่ต้องการ: ชื่อรายการ 15px เปอร์เซ็นต์ 15px
     จำนวนในวงเล็บ 14px ตัวเลขกลางวง 30px — ตั้งเป็น px จริงไม่ใช่สัดส่วนของรูป
     จึงอ่านออกเท่ากันทุกความกว้างจอ ต่างจากตอนที่ฝังตัวหนังสือไว้ในรูป PNG */
  .dxd { display: flex; align-items: center; gap: 22px; min-width: 0; }
  .dxd-chart { position: relative; width: 168px; height: 168px; flex-shrink: 0; }
  .dxd-svg { width: 100%; height: 100%; display: block; }
  .dxd-center {
    position: absolute; inset: 0; pointer-events: none;
    display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.15;
  }
  .dxd-center-top { font-size: 13px; color: #5c6470; }
  .dxd-center-value { font-size: 30px; font-weight: 700; color: #111827; }
  .dxd-center-unit { font-size: 13px; color: #5c6470; margin-top: 1px; }

  .dxd-legend { list-style: none; margin: 0; padding: 0; flex: 1; min-width: 0; }
  .dxd-legend li {
    display: flex; align-items: center; gap: 10px;
    padding: 5px 0; min-width: 0; line-height: 1.45;
  }
  .dxd-dot { width: 11px; height: 11px; border-radius: 3px; flex-shrink: 0; }
  /* ชื่อยาวๆ ตัดด้วย … แทนที่จะดันตัวเลขตกบรรทัด
     (min-width: 0 จำเป็น — flex item ตั้งต้นที่ auto ซึ่งไม่ยอมหดต่ำกว่าเนื้อหา
     และภาษาไทยไม่มีช่องว่างจึงหดเองไม่ได้) */
  .dxd-label {
    flex: 1; min-width: 0; font-size: 15px; color: #1f2937;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .dxd-pct { flex-shrink: 0; font-size: 15px; color: #1f2937; font-variant-numeric: tabular-nums; text-align: right; min-width: 52px; }
  .dxd-count { flex-shrink: 0; font-size: 14px; color: #666e7c; font-variant-numeric: tabular-nums; text-align: right; min-width: 92px; }
  .dxd-empty { color: #666e7c; font-style: italic; }

  /* กราฟแท่งรายชั่วโมง */
  .dxb { min-width: 0; }
  .dxb-bars { display: flex; align-items: flex-end; gap: 2px; height: 150px; }
  .dxb-slot { flex: 1; min-width: 0; height: 100%; display: flex; align-items: flex-end; }
  .dxb-bar { width: 100%; background: linear-gradient(180deg, #6f9bfb, #0a6fb8); border-radius: 2px 2px 0 0; }
  .dxb-axis {
    display: flex; gap: 2px; margin-top: 5px;
    font-size: 11px; color: #666e7c; font-variant-numeric: tabular-nums;
  }
  .dxb-axis span { flex: 1; min-width: 0; text-align: center; }

  @media screen and (max-width: 700px) {
    .dxd { flex-direction: column; align-items: stretch; gap: 14px; }
    .dxd-chart { align-self: center; }
    .dxd-count { min-width: 84px; }
  }

  .dx-peak { width: 100%; border-collapse: separate; table-layout: fixed; }
  .dx-kpi-label, .dx-kpi-value, .dx-delta, .dx-panel-title { overflow-wrap: anywhere; }
  .dx-peak-info { width: 31%; vertical-align: middle; text-align: center; padding-right: 10px; }
  .dx-peak-info img { width: 44px; height: 44px; display: inline-block; }
  .dx-peak-range { font-size: 18px; font-weight: 700; color: #0a6fb8; margin-top: 8px; white-space: nowrap; }
  .dx-peak-note { font-size: 13px; color: #5c6470; margin-top: 2px; }
  .dx-peak-count { font-size: 26px; font-weight: 700; color: #0a6fb8; margin-top: 2px; }
  .dx-peak-unit { font-size: 13px; font-weight: 400; color: #5c6470; }
  .dx-peak-chart { width: 69%; vertical-align: middle; }

  /* จอแคบ: การ์ดตัวเลขเรียงสองคอลัมน์ กราฟเรียงคอลัมน์เดียว
     ต้องประกาศ display ใหม่ทั้งชุด เพราะโครงเป็นตาราง ไม่ใช่ grid */
  @media screen and (max-width: 900px) {
    .dx-kpi-row { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .dx-row { grid-template-columns: minmax(0, 1fr); }
    .dx-title { font-size: 24px; }
  }
  @media screen and (max-width: 560px) {
    .dx-kpi-row { grid-template-columns: minmax(0, 1fr); }
    .dx-peak, .dx-peak > tbody, .dx-peak > tbody > tr { display: block; }
    .dx-peak-info, .dx-peak-chart { display: block; width: 100%; padding-right: 0; }
    .dx-peak-info { margin-bottom: 10px; }
  }

  /* สั่งพิมพ์จากเบราว์เซอร์: ให้จบในกระดาษแนวนอนหน้าเดียวเหมือนไฟล์ PDF */
  /* ---- สั่งพิมพ์จากเบราว์เซอร์: บีบให้จบใน A4 แนวนอนหน้าเดียว ----
     คนละเส้นทางกับไฟล์ PDF ที่กดดาวน์โหลด (นั่นคือ mPDF ซึ่งมีสไตล์ของตัวเอง
     ใน layout.php และจบหน้าเดียวอยู่แล้ว) การสั่งพิมพ์ใช้เลย์เอาต์หน้าจอ
     ซึ่งออกแบบมาให้อ่านบนจอ ไม่ได้ออกแบบมาให้สูงไม่เกิน 210 มม.

     วัดจริงที่พื้นที่พิมพ์ 1062x733px (A4 แนวนอน ขอบ 8มม. ที่ 96dpi):
     เอกสารสูง 1011px เกินไป 278px และตัวที่กินที่สุดคือคำอธิบายกราฟวงกลม
     แผนกวิชา 10 บรรทัด บรรทัดละ 32px = 320px ไม่ใช่ตัวกราฟ

     ลดที่ขนาดจริงของแต่ละชิ้น ไม่ใช่ย่อทั้งหน้าด้วย transform: scale()
     เพราะการย่อทั้งหน้าทำให้ตัวหนังสือเล็กลงพร้อมกันหมดจนอ่านไม่ออก */
  @media print {
    .dash-export { max-width: none; }
    .dx-kpi-row, .dx-row { gap: 6px; }
    .dx-row { margin-top: 6px; }
    .dx-kpi, .dx-panel { box-shadow: none; }

    /* หัวเรื่อง */
    .report-head { padding: 0 0 4px; }
    .dx-head { margin: 0 0 7px; }
    .dx-org { font-size: 9px; }
    .dx-title { font-size: 19px; line-height: 1.15; }
    .dx-sub { font-size: 10px; }

    /* การ์ดตัวเลข */
    .dx-kpi-label { font-size: 10px; }
    .dx-kpi-value { font-size: 18px; }
    .dx-kpi-unit { font-size: 10px; }
    .dx-delta { font-size: 9px; }
    .dx-kpi-icon { padding: 6px 0 6px 7px; }
    .dx-kpi-icon img { width: 26px; height: 26px; }
    .dx-kpi-text { padding: 6px 8px 6px 6px; }

    /* แผงและกราฟ */
    .dx-panel-body { padding: 6px 9px 8px; }
    .dx-panel-title { font-size: 13px; margin-bottom: 4px; }
    .dxd-chart { width: 118px; height: 118px; }
    .dxd-center-value { font-size: 20px; }
    .dxd-center-unit, .dxd-center-top { font-size: 8px; }
    /* บรรทัดคำอธิบาย: จาก 32px เหลือราว 15px x 10 บรรทัด ประหยัดไปราว 170px
       ซึ่งเป็นก้อนใหญ่ที่สุดของทั้งหน้า */
    .dxd-legend li { padding: 0; line-height: 1.35; gap: 6px; }
    .dxd-label, .dxd-pct, .dxd-count { font-size: 10px; }
    .dxd-dot { width: 8px; height: 8px; }

    /* กราฟรายชั่วโมง */
    .dxb-bars { height: 96px; }
    .dxb-axis { font-size: 8px; }
    .dx-peak-icon { width: 34px; height: 34px; }
    .dx-peak-range { font-size: 13px; }
    .dx-peak-note { font-size: 9px; }
    .dx-peak-count { font-size: 18px; }
    .dx-peak-unit { font-size: 9px; }

    .report-foot { padding: 4px 0 0; font-size: 8px; }
  }

  .delta { font-weight: 700; white-space: nowrap; }
  .delta.up { color: #059669; }
  .delta.down { color: #d97706; }
  .delta.flat { color: #888; }

  .kpi-strip {
    display: grid; grid-template-columns: repeat(auto-fit, minmax(172px, 1fr)); gap: 10px; margin: 0 0 14px;
  }
  .kpi-card {
    background: var(--surface-white, #fff); border: 1px solid var(--outline-variant, #e2e8f0);
    border-radius: 12px; padding: 12px 14px; box-shadow: 0 2px 8px rgba(0,0,0,.03);
    min-height: 78px;
  }
  .kpi-card.hero { border-left: 4px solid var(--primary, #1a2947); }
  /* Label and sparkline share a flex row (not an absolutely-positioned
     overlay) so the label can wrap to a second line instead of being cut
     off with an ellipsis — this went to executives truncated as
     "เวลาเฉลี่ยที่..." before, which read as broken, not just tight. */
  .kpi-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 6px; margin-bottom: 5px; }
  .kpi-card .sparkline { flex-shrink: 0; width: 40px; height: 16px; margin-top: 1px; }
  .kpi-label { font-size: 10.5px; color: #666; font-weight: 600; line-height: 1.3; }
  .kpi-value { font-size: 19px; font-weight: 700; color: #212430; line-height: 1.15; }
  .kpi-card.hero .kpi-value { font-size: 26px; }
  .kpi-sub { display: block; margin-top: 4px; font-size: 10.5px; color: #666; }

  /* The one genuine ratio in this report (a department's share of total
     traffic) gets the one ring gauge, sized to match the other KPI cards'
     height instead of standing out as an oversized block. */
  .kpi-card.ring-card { display: flex; align-items: center; gap: 10px; }
  .meter-ring-wrap { position: relative; flex-shrink: 0; width: 52px; height: 52px; display: flex; align-items: center; justify-content: center; }
  .meter-ring-wrap svg { width: 52px; height: 52px; }
  .meter-ring-value { position: absolute; font-size: 11px; font-weight: 700; color: #212430; }
  .meter-info { min-width: 0; }
  .meter-info .meter-dept-name { font-size: 13px; font-weight: 700; color: #212430; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .meter-info .meter-dept-count { font-size: 10.5px; color: #666; margin-top: 1px; }

  /* Department ranking (Sales-ranking-style reference): a circled position
     number instead of a raw table row — rank 1 gets the solid brand fill,
     the rest a light tint, same "one hue, varying weight" rule as the bars.
     Rows kept short (not just at print size) since this list can run to a
     dozen-plus departments and still needs to share the page with everything
     else above it. */
  .rank-list { display: flex; flex-direction: column; gap: 6px; }
  .rank-row { display: flex; align-items: center; gap: 10px; }
  .rank-badge {
    flex-shrink: 0; width: 20px; height: 20px; border-radius: 999px;
    background: #dbeafe; color: var(--primary, #1a2947);
    font-size: 10px; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
  }
  .rank-row:first-child .rank-badge { background: var(--primary, #1a2947); color: #fff; }
  .rank-name {
    flex: 0 0 140px; width: 140px; font-size: 12px; font-weight: 700; color: #212430;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  }
  .rank-track { flex: 1; height: 8px; background: #e1e0d9; border-radius: 999px; overflow: hidden; }
  .rank-fill { display: block; height: 100%; background: var(--secondary, #0a6fb8); border-radius: 999px; }
  .rank-count { flex-shrink: 0; width: 120px; text-align: right; font-size: 11px; color: #666; }
  @media (max-width: 640px) {
    .rank-row {
      flex-wrap: wrap;
      row-gap: 4px;
      padding-bottom: 6px;
    }
    .rank-name {
      flex: 1 1 auto; width: auto; min-width: 0;
      white-space: normal; overflow: visible; text-overflow: clip;
    }
    /* Second line: the badge already sat first, so an explicit order keeps
       the bar and its count together under the name rather than either one
       drifting up beside it. */
    .rank-track { order: 3; flex: 1 1 60%; }
    .rank-count { order: 4; width: auto; flex: 0 0 auto; }
    .meter-info .meter-dept-name { white-space: normal; overflow: visible; text-overflow: clip; }
  }

  /* Compact 2-across chart strip (daily / hourly) instead of one big
     2-column pair per section — matches the "everything visible at once"
     Power-BI-style brief and leaves enough vertical room for the full
     department list below to still land on the same printed page. The
     earlier "weekly" panel duplicated the semester report's own week-by-week
     template, so it was dropped in favor of the gender breakdown instead. */
  .mini-panel-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin: 0 0 14px;
  }
  @media (max-width: 900px) {
    .mini-panel-row { grid-template-columns: 1fr; }
  }
  .panel, .mini-panel {
    border: 1px solid var(--outline-variant, #ccc);
    border-radius: 10px;
    padding: 12px 14px;
    background: var(--surface-white, #fff);
    box-shadow: 0 2px 10px rgba(0,0,0,.03);
  }
  .panel h3, .mini-panel h3 {
    font-size: 11px;
    margin: 0 0 8px;
    color: #333;
    text-transform: uppercase;
    letter-spacing: .04em;
  }
  .trend-chart {
    display: flex;
    align-items: flex-end;
    gap: 3px;
    height: 80px;
    border-bottom: 1px solid #e1e0d9;
    padding-top: 6px;
  }
  .trend-chart.hourly-chart { gap: 1px; }
  .trend-chart .bar-wrap {
    flex: 1;
    display: flex;
    align-items: flex-end;
    height: 100%;
  }
  .trend-chart .bar {
    width: 100%;
    background: var(--secondary, #0a6fb8);
    border-radius: 4px 4px 0 0;
    min-height: 1px;
  }
  .trend-labels {
    display: flex;
    justify-content: space-between;
    font-size: 9px;
    color: #666e7c;
    margin-top: 4px;
  }

  /* Gender is one of the few breakdowns in this report where the categories
     are genuinely identity-based (exactly male/female/unspecified, not an
     open-ended nominal list like departments) — a real, if small, exception
     to the "one hue" bar rule used everywhere else in this file. */
  .gender-list { display: flex; flex-direction: column; gap: 8px; margin-top: 2px; }
  .gender-row { display: flex; align-items: center; gap: 8px; }
  .gender-row .g-label { flex: 0 0 52px; font-size: 11px; font-weight: 700; color: #212430; }
  .gender-row .g-track { flex: 1; height: 13px; background: #e1e0d9; border-radius: 999px; overflow: hidden; }
  .gender-row .g-fill { height: 100%; border-radius: 999px; }
  .gender-row.male .g-fill { background: #0a6fb8; }
  .gender-row.female .g-fill { background: #db2777; }
  .gender-row.unknown .g-fill { background: #666e7c; }
  .gender-row .g-count { flex: 0 0 92px; text-align: right; font-size: 10.5px; color: #666; }

  .empty-note {
    color: #666e7c;
    font-size: 12px;
    font-style: italic;
  }
  /* Tightened further so the whole single-page dashboard (KPI strip + 3
     mini-charts + full department list + summary) reliably fits one A4
     sheet instead of spilling a second, mostly-empty one. Cards are allowed
     to break across pages only as a last resort (break-inside: avoid on the
     small ones; the department list is intentionally left free to flow —
     forcing a dozen-plus rows to never split would just push the whole
     block onto page 2 instead of filling out page 1). */
  @media print {
    .kpi-strip { grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)) !important; gap: 6px; margin-bottom: 8px; }
    .kpi-card { padding: 7px 9px; min-height: 56px; }
    .kpi-card.hero .kpi-value { font-size: 19px; }
    .kpi-value { font-size: 14px; }
    .kpi-head { margin-bottom: 2px; }
    .kpi-label { font-size: 7.5px; }
    .kpi-sub { font-size: 8px; }
    .kpi-card .sparkline { width: 30px !important; height: 12px !important; }
    .meter-ring-wrap, .meter-ring-wrap svg { width: 34px; height: 34px; }
    .meter-ring-value { font-size: 8px; }
    .meter-info .meter-dept-name { font-size: 10px; }
    .meter-info .meter-dept-count { font-size: 8px; }
    .mini-panel-row { gap: 6px; margin-bottom: 8px; }
    .panel, .mini-panel { padding: 6px 8px; box-shadow: none; }
    .panel h3, .mini-panel h3 { font-size: 8.5px; margin-bottom: 4px; }
    .trend-chart { height: 40px; }
    .trend-labels { font-size: 7px; }
    .gender-list { gap: 4px; }
    .gender-row .g-label { font-size: 8px; flex-basis: 40px; }
    .gender-row .g-track { height: 9px; }
    .gender-row .g-count { font-size: 7.5px; flex-basis: 70px; }
    .rank-list { gap: 3px; }
    .rank-badge { width: 15px; height: 15px; font-size: 8px; }
    .rank-name { font-size: 8px; flex-basis: 90px; width: 90px; }
    .rank-track { height: 7px; }
    .rank-count { font-size: 7px; width: 80px; }
    .story-box { padding: 6px 10px; margin-bottom: 6px; }
    .story-box p { font-size: 9px; line-height: 1.4; }
    .kpi-card, .mini-panel, .panel, .story-box { break-inside: avoid; }
  }
</style>
<?php
    $extraStyle = ob_get_clean();

    ob_start();
    ?>

<div class="quick-filter-chips">
  <a class="chip <?= $isThisWeek ? 'active' : '' ?>" href="<?= htmlspecialchars($thisWeekHref) ?>">สัปดาห์นี้</a>
  <a class="chip <?= $isThisMonth ? 'active' : '' ?>" href="<?= htmlspecialchars($thisMonthHref) ?>">เดือนนี้</a>
  <span class="chip-hint">หรือกำหนดช่วงวันที่เองด้านล่าง</span>
</div>

<form class="filter-bar" method="get">
  <div class="field">
    <label for="month">เดือน</label>
    <?= render_month_select($useCustomRange ? '' : $month, 'month', 'month', 18, '— ใช้ช่วงวันที่ด้านล่าง —') ?>
  </div>
  <div class="field">
    <label for="start_date">จากวันที่ (ถ้าใช้ช่วงวันที่)</label>
    <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDateParam) ?>">
  </div>
  <div class="field">
    <label for="end_date">ถึงวันที่</label>
    <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDateParam) ?>">
  </div>
  <div class="field">
    <label for="academic_year">ปีการศึกษา</label>
    <select id="academic_year" name="academic_year">
      <option value="">ทั้งหมด</option>
      <?php foreach ($academicYears as $y): ?>
      <option value="<?= htmlspecialchars($y) ?>" <?= $filters['academic_year'] === $y ? 'selected' : '' ?>><?= htmlspecialchars($y) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="semester">ภาคเรียน</label>
    <select id="semester" name="semester">
      <option value="">ทั้งหมด</option>
      <?php foreach ($semesters as $s): ?>
      <option value="<?= htmlspecialchars($s) ?>" <?= $filters['semester'] === $s ? 'selected' : '' ?>><?= htmlspecialchars($s) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="department">แผนกวิชา</label>
    <select id="department" name="department">
      <option value="">ทั้งหมด</option>
      <?php foreach ($departments as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $filters['department'] === $d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="field">
    <label for="level">ระดับชั้น</label>
    <select id="level" name="level">
      <option value="">ทั้งหมด</option>
      <?php foreach ($levels as $l): ?>
      <option value="<?= htmlspecialchars($l) ?>" <?= $filters['level'] === $l ? 'selected' : '' ?>><?= htmlspecialchars($l) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <button type="submit">กรองข้อมูล</button>
  <a class="reset-link" href="/admin/reports/print/dashboard"><span class="material-symbols-outlined" style="font-size:14px;">restart_alt</span> ล้างตัวกรอง</a>
</form>
<?php if ($filters['semester'] || $filters['academic_year']): ?>
<p class="filter-note">* ภาคเรียน/ปีการศึกษาอ้างอิงจากข้อมูลการลงทะเบียนปัจจุบันของนักศึกษาแต่ละคน ไม่ใช่ช่วงวันที่ปฏิทินของภาคเรียนนั้น ๆ</p>
<?php endif; ?>

<?php if ($agg['total_events'] === 0): ?>
<div class="empty">
  ยังไม่มีข้อมูลการเช็คชื่อในช่วงเวลานี้
  <br>
  <a class="empty-cta" href="/admin/reports/print/dashboard"><span class="material-symbols-outlined" style="font-size:16px;">event_repeat</span> เปลี่ยนช่วงเวลา</a>
</div>
<?php else: ?>

<?php
// หน้าจอกับไฟล์ที่ส่งออกใช้ตัวสร้างเนื้อหาตัวเดียวกัน เพื่อให้สิ่งที่เห็นบนจอ
// กับสิ่งที่ได้ในไฟล์เป็นอันเดียวกันจริงๆ ไม่ใช่สองชุดที่ต้องคอยไล่แก้ให้ตรงกัน
//
// เดิมหน้าจอมีวิดเจ็ตของตัวเอง (การ์ด 7 ใบพร้อมเส้นกราฟจิ๋ว วงแหวนเปอร์เซ็นต์
// แถบสัดส่วนเพศ และตารางอันดับแผนก) ส่วนไฟล์ PDF มีอีกชุดหนึ่ง ผลคือแก้ฝั่งเดียว
// แล้วอีกฝั่งยังเป็นของเก่า
?>
<div class="dash-export">
<?= render_dashboard_body($dashboardContext) ?>
</div>


<?php // Self-hosted rather than loaded from cdnjs: a CDN script with no SRI hash
      // is an open door — whoever controls that host (or the DNS answer for it)
      // gets to run code inside an authenticated admin's report page. Pinned
      // local copy of html2canvas 1.4.1 (MIT), sha256
      // e87e550794322e574a1fda0c1549a3c70dae5a93d9113417a429016838eab8cb ?>
<script src="/assets/js/vendor/html2canvas.min.js"></script>
<script>
(function () {
  // Separate "save as image" from "print" — the shared toolbar only ever had
  // one button that opens the browser print dialog (useful for PDF, but not
  // what someone reaching for "save image" expects). Injected here rather
  // than into layout.php's shared toolbar markup since this is a
  // dashboard-specific addition, not something every table-style report needs.
  var printBtn = document.querySelector('.toolbar button');
  if (printBtn) {
    var imageBtn = document.createElement('button');
    imageBtn.type = 'button';
    imageBtn.innerHTML = '<span class="material-symbols-outlined" style="font-size:16px;">image</span> บันทึกเป็นรูปภาพ';
    imageBtn.addEventListener('click', function () {
      imageBtn.disabled = true;
      var originalHtml = imageBtn.innerHTML;
      imageBtn.innerHTML = 'กำลังสร้างรูปภาพ…';
      var shotTarget = document.querySelector('.dash-export') || document.body;
      html2canvas(shotTarget, {
        backgroundColor: '#ffffff',
        scale: 2,
        ignoreElements: function (el) {
          return el.classList.contains('toolbar')
            || el.classList.contains('filter-bar')
            || el.classList.contains('quick-filter-chips')
            || el.classList.contains('filter-note');
        },
      }).then(function (canvas) {
        var link = document.createElement('a');
        link.download = 'รายงานแดชบอร์ด-<?= htmlspecialchars(str_replace(' ', '-', $periodLabel)) ?>.png';
        link.href = canvas.toDataURL('image/png');
        link.click();
      }).finally(function () {
        imageBtn.disabled = false;
        imageBtn.innerHTML = originalHtml;
      });
    });
    printBtn.insertAdjacentElement('afterend', imageBtn);
  }
})();
</script>
<?php endif; ?>

    <?php
    $content = ob_get_clean();

    // ไฟล์ PDF ใช้เนื้อหาที่สร้างขึ้นเฉพาะของมันเอง ไม่ใช่ HTML ของหน้าจอ
    // ที่ถูกซ่อนบางส่วนด้วย CSS — ดูเหตุผลที่ render_dashboard_body()
    if ($isPdfExport) {
        $content = render_dashboard_body($dashboardContext + ['for_pdf' => true]);
    }

    render_report_layout('รายงานแบบแดชบอร์ด', "สรุปภาพรวมการเช็คชื่อ — $periodLabel", $content, $extraStyle, [], [], true);
}
