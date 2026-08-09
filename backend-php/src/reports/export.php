<?php
// CSV/Excel export shared by every report except dashboard.php (dashboard is
// a print-only 1-pager, not a row-per-record report). The PHP API
// deliberately has zero Composer dependencies (see composer.json) — CSV is
// native PHP, and "Excel" is an HTML table served as .xls (Excel opens it
// fine, showing a one-time "format doesn't match extension" prompt) rather
// than a real .xlsx via a PhpSpreadsheet-style library.
//
// $sections is a list of [title, headers[], rows[][]] tuples — usually just
// one, but department/executive/compare-style reports can pass more than one
// table under a single download. $meta is an optional ['label' => value, ...]
// map (report title, generated-at, applied filters) prepended as its own
// section above the data — satisfies the "export must carry title/date/
// filters" requirement in one place instead of every report hand-building it.
function export_response(string $filenameBase, array $sections, string $format, array $meta = []): void
{
    if ($meta) {
        $metaRows = [];
        foreach ($meta as $label => $value) {
            $metaRows[] = [$label, $value];
        }
        array_unshift($sections, ['ข้อมูลรายงาน', ['รายการ', 'ค่า'], $metaRows]);
    }

    if ($format === 'csv') {
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filenameBase.csv\"");
        echo "\xEF\xBB\xBF"; // BOM so Excel opens Thai text as UTF-8 instead of mis-decoding it
        $out = fopen('php://output', 'w');
        foreach ($sections as $i => $section) {
            [$title, $headers, $rows] = $section;
            if ($i > 0) {
                fputcsv($out, []);
            }
            if (count($sections) > 1) {
                fputcsv($out, [$title]);
            }
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }

    if ($format === 'excel') {
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filenameBase.xls\"");
        echo "\xEF\xBB\xBF";
        echo '<html><head><meta charset="UTF-8"></head><body>';
        foreach ($sections as $section) {
            [$title, $headers, $rows] = $section;
            if (count($sections) > 1) {
                echo '<h3>' . htmlspecialchars($title) . '</h3>';
            }
            echo '<table border="1"><thead><tr>';
            foreach ($headers as $h) {
                echo '<th>' . htmlspecialchars((string) $h) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($row as $cell) {
                    echo '<td>' . htmlspecialchars((string) $cell) . '</td>';
                }
                echo '</tr>';
            }
            echo '</tbody></table><br>';
        }
        echo '</body></html>';
        exit;
    }
}
