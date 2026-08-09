<?php
/**
 * Import students from Student.xlsx into the `students` table.
 *
 * Source file layout: one sheet per department, each sheet containing repeated
 * attendance-form blocks (one block per ระดับชั้น/ชั้นปีที่/ห้องที่/ภาคเรียน).
 * Each block looks like:
 *
 *     ...แผนกวิชา...ระดับชั้น...ชั้นปีที่...ห้องที่...
 *     ภาคเรียนที่...ปีการศึกษา...ครูที่ปรึกษา...
 *     (boilerplate row)
 *     ลำดับที่ | รหัสประจำตัว | ชื่อ - สกุล | | | ลายมือชื่อ | หมายเหตุ
 *     1 | <id> | <prefix> | <first_name> | <last_name> | | ...
 *     ...
 *
 * Some students appear in multiple blocks across terms (re-recorded roster
 * each semester). We keep only the row with the highest (academic_year,
 * semester), since the check-in system needs each student's current
 * department/year/room.
 *
 * Usage: php import_students.php [path/to/Student.xlsx]  (defaults to ./Student.xlsx)
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');
require __DIR__ . '/../src/db.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

const DEPT_RE = '/แผนกวิชา(?P<department>.*?)ระดับชั้น(?P<level>.*?)ชั้นปีที่(?P<year_level>.*?)ห้องที่(?P<room>.*)/su';
const TERM_RE = '/ภาคเรียนที่(?P<semester>.*?)ปีการศึกษา(?P<academic_year>.*?)ครูที่ปรึกษา(?P<advisor>.*)/su';

// Strip the dot/ellipsis fill-in-the-blank characters used on the paper form.
function clean(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $text = preg_replace('/[.…]{2,}/u', ' ', $value);
    return trim($text, " .…");
}

// Mirrors Python's `int(seq)` try/except: succeeds for ints, floats, and
// plain-integer strings (optional sign/whitespace); fails for null, decimals,
// or non-numeric text. Determines where a student-row block ends.
function looks_like_row_index(mixed $seq): bool
{
    if (is_int($seq) || is_float($seq)) {
        return true;
    }
    if (is_string($seq)) {
        return (bool) preg_match('/^\s*[+-]?\d+\s*$/', $seq);
    }
    return false;
}

// Mirrors Python's str(cell_value) for the id/name cells PhpSpreadsheet may
// hand back as int/float/string.
function cell_to_string(mixed $value): string
{
    if (is_float($value) && $value == (int) $value) {
        return (string) (int) $value;
    }
    return trim((string) $value);
}

function extract_students(string $path): array
{
    $reader = new Xlsx();
    $reader->setReadDataOnly(true);
    $spreadsheet = $reader->load($path);

    $records = [];

    foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
        $rows = $sheet->toArray(null, false, false, false);
        $n = count($rows);
        $i = 0;
        while ($i < $n) {
            $cell = $rows[$i][0] ?? null;
            if (!$cell || !str_contains((string) $cell, 'แผนกวิชา')) {
                $i++;
                continue;
            }

            if (!preg_match(DEPT_RE, (string) $cell, $deptMatch)) {
                $i++;
                continue;
            }
            $info = [
                'department' => clean($deptMatch['department']),
                'level' => clean($deptMatch['level']),
                'year_level' => clean($deptMatch['year_level']),
                'room' => clean($deptMatch['room']),
            ];

            $semester = '';
            $academicYear = '';
            $termCell = $rows[$i + 1][0] ?? null;
            if ($termCell && preg_match(TERM_RE, (string) $termCell, $termMatch)) {
                $semester = clean($termMatch['semester']);
                $academicYear = clean($termMatch['academic_year']);
            }

            $headerRow = null;
            for ($offset = 0; $offset < 5; $offset++) {
                $idx = $i + 1 + $offset;
                if ($idx < $n && ($rows[$idx][0] ?? null) && trim((string) $rows[$idx][0]) === 'ลำดับที่') {
                    $headerRow = $idx;
                    break;
                }
            }
            if ($headerRow === null) {
                $i++;
                continue;
            }

            $r = $headerRow + 1;
            while ($r < $n) {
                $seq = $rows[$r][0] ?? null;
                if (!looks_like_row_index($seq)) {
                    break;
                }
                $studentId = cell_to_string($rows[$r][1] ?? null);
                $records[] = [
                    'student_id' => $studentId,
                    'prefix' => clean($rows[$r][2] ?? null),
                    'first_name' => clean($rows[$r][3] ?? null),
                    'last_name' => clean($rows[$r][4] ?? null),
                    'department' => $info['department'],
                    'level' => $info['level'],
                    'year_level' => $info['year_level'],
                    'room' => $info['room'],
                    'semester' => $semester,
                    'academic_year' => $academicYear,
                ];
                $r++;
            }
            $i = $r;
        }
    }

    return $records;
}

function dedupe_keep_latest_term(array $records): array
{
    $latest = [];
    foreach ($records as $rec) {
        $termKey = [(int) ($rec['academic_year'] ?: 0), (int) ($rec['semester'] ?: 0)];
        $existing = $latest[$rec['student_id']] ?? null;
        if ($existing === null || $termKey > $existing[0]) {
            $latest[$rec['student_id']] = [$termKey, $rec];
        }
    }
    return array_map(fn($entry) => $entry[1], array_values($latest));
}

function import_to_mysql(array $records): void
{
    $conn = get_db_connection();
    $stmt = $conn->prepare(
        'INSERT INTO students
            (student_id, prefix, first_name, last_name, department, level, year_level, room, semester, academic_year)
         VALUES
            (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            prefix=VALUES(prefix), first_name=VALUES(first_name), last_name=VALUES(last_name),
            department=VALUES(department), level=VALUES(level), year_level=VALUES(year_level),
            room=VALUES(room), semester=VALUES(semester), academic_year=VALUES(academic_year)'
    );
    foreach ($records as $rec) {
        $stmt->execute([
            $rec['student_id'], $rec['prefix'], $rec['first_name'], $rec['last_name'],
            $rec['department'], $rec['level'], $rec['year_level'], $rec['room'],
            $rec['semester'], $rec['academic_year'],
        ]);
    }
}

if (php_sapi_name() === 'cli' && realpath($argv[0]) === __FILE__) {
    $excelPath = $argv[1] ?? (__DIR__ . '/../Student.xlsx');
    if (!is_file($excelPath)) {
        fwrite(STDERR, "Error: file not found: $excelPath\n");
        exit(1);
    }

    $allRecords = extract_students($excelPath);
    echo 'Parsed ' . count($allRecords) . " rows from Excel\n";

    $deduped = dedupe_keep_latest_term($allRecords);
    echo count($deduped) . " unique students after keeping latest term per student_id\n";

    import_to_mysql($deduped);
    echo "Import complete\n";
}
