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
 * Usage: php import_students.php [file.xlsx ...] [--dry-run] [--allow-unknown]
 *        (defaults to ./Student.xlsx; several workbooks may be given at once,
 *         e.g. the ปวช. and ปวส. halves of one term)
 *
 * The import REFUSES to run when a department or ระดับชั้น in the workbook is
 * not one the rest of the app knows about — see find_unknown_values() for why,
 * and --allow-unknown to override. --dry-run validates and reports only.
 */

require __DIR__ . '/../vendor/autoload.php';
// valid_departments() lives here; require_once because tests/import_normalize_test.php
// loads constants.php and this script together.
require_once __DIR__ . '/../src/constants.php';
require __DIR__ . '/../src/env.php';
load_env(__DIR__ . '/../.env');
require __DIR__ . '/../src/db.php';

use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

const DEPT_RE = '/แผนกวิชา(?P<department>.*?)ระดับชั้น(?P<level>.*?)ชั้นปีที่(?P<year_level>.*?)ห้องที่(?P<room>.*)/su';
const TERM_RE = '/ภาคเรียนที่(?P<semester>.*?)ปีการศึกษา(?P<academic_year>.*?)ครูที่ปรึกษา(?P<advisor>.*)/su';

// The department cell on the paper form is free text, and across the 2569
// roster it arrives in 51 spellings for 14 real departments: programme
// suffixes the check-in system does not model ("(ม.6)", "(ทวิ)", "(SCG)",
// "(ผลิตชิ้นส่วนยานยนต์)"), the old name for one department, and outright
// typos ("เทคโนโลยีธรกิจดิจิทัล" missing its ุ, "(ทิว)" for "(ทวิ)").
// Imported raw, every department report groups by those 51 strings and the
// per-department charts become unreadable. Fold them onto the same 14 names
// valid_departments() in src/constants.php accepts, so the roster, the signup
// form and the admin edit form all speak about departments identically.
const DEPT_ALIASES = [
    'คอมพิวเตอร์' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'เทคโนโลยีธรกิจดิจิทัล' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'การจัดการโลจิสติกส์และซับพลายเซน' => 'การจัดการโลจีสติกส์และซับพลายเซน',
];

// "ช่างไฟฟ้ากำลัง (ม.6)" -> "ช่างไฟฟ้ากำลัง". Drops the programme suffix, the
// stray leading "แผนกวิชา" some sheets repeat, and the fill-in dots, then
// applies DEPT_ALIASES. A name that survives all of that unrecognised is
// returned as-is rather than dropped — a genuinely new department should show
// up in the reports needing a mapping, not vanish from the roster.
function normalize_department(string $raw): string
{
    $name = preg_replace('/[(（].*$/su', '', $raw);
    $name = preg_replace('/^\s*แผนกวิชา/u', '', (string) $name);
    $name = trim(preg_replace('/\s+/u', ' ', (string) $name), " .…");
    return DEPT_ALIASES[$name] ?? $name;
}

// "ปวส (ทวิ)" / "ปวส" -> "ปวส." — valid_year_levels() and the signup form
// both key off the trailing dot, so a roster row without it would never match
// the dropdown the student picks from.
function normalize_level(string $raw): string
{
    $level = preg_replace('/[(（].*$/su', '', $raw);
    $level = trim(preg_replace('/\s+/u', '', (string) $level), " .…");
    if ($level === 'ปวช' || $level === 'ปวส') {
        return $level . '.';
    }
    return $level;
}

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
                'department' => normalize_department(clean($deptMatch['department'])),
                'level' => normalize_level(clean($deptMatch['level'])),
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

// Returns the departments and levels in $records that normalize_department() /
// normalize_level() did NOT recognise, each with a count and a sample student
// so the report can point at a real row in the workbook.
//
// This is the half that DEPT_ALIASES and tests/import_normalize_test.php cannot
// cover: the test pins spellings we have already seen, and the alias table only
// knows names someone thought to add. When the college opens a new department,
// or renames one, its rows arrive under a name nothing here has an opinion
// about — and an import that just accepts it puts a 15th department into the
// roster that no dropdown offers and every per-department report splits out
// separately. Nobody notices for weeks. So the import stops instead.
function find_unknown_values(array $records): array
{
    $validDepartments = valid_departments();
    $validLevels = ['ปวช.', 'ปวส.'];
    $unknown = ['department' => [], 'level' => []];

    foreach ($records as $rec) {
        foreach ([['department', $validDepartments], ['level', $validLevels]] as [$field, $allowed]) {
            $value = $rec[$field];
            if ($value === '' || in_array($value, $allowed, true)) {
                continue;
            }
            if (!isset($unknown[$field][$value])) {
                $unknown[$field][$value] = ['count' => 0, 'sample' => $rec['student_id']];
            }
            $unknown[$field][$value]['count']++;
        }
    }
    return $unknown;
}

// Departments that ARE recognised but matched nobody. On its own this is not an
// error — a small department can genuinely have no students in a given term —
// but paired with an unknown name above it usually means a rename, so it is
// worth printing next to it.
function find_empty_departments(array $records): array
{
    $seen = array_count_values(array_column($records, 'department'));
    return array_values(array_filter(
        valid_departments(),
        fn($dept) => !isset($seen[$dept])
    ));
}

function report_unknown_values(array $unknown, array $emptyDepartments): void
{
    $labels = ['department' => 'แผนกวิชา', 'level' => 'ระดับชั้น'];
    foreach ($unknown as $field => $values) {
        if (!$values) {
            continue;
        }
        fwrite(STDERR, "\n!! พบ{$labels[$field]}ที่ระบบยังไม่รู้จัก " . count($values) . " ค่า:\n");
        foreach ($values as $value => $info) {
            fwrite(STDERR, sprintf(
                "     [%s]  %d คน  (เช่นรหัส %s)\n",
                $value,
                $info['count'],
                $info['sample']
            ));
        }
    }
    if ($emptyDepartments) {
        fwrite(STDERR, "\n   หมายเหตุ: แผนกที่รู้จักแต่ไม่มีนักเรียนเลยในไฟล์นี้ — "
            . "ถ้าชื่อข้างบนคือแผนกเดียวกันที่เปลี่ยนชื่อ ให้แก้เป็น alias แทนการเพิ่มแผนกใหม่:\n");
        foreach ($emptyDepartments as $dept) {
            fwrite(STDERR, "     [$dept]\n");
        }
    }
    fwrite(STDERR, PHP_EOL
        . "   ยังไม่ได้นำเข้าข้อมูล เพราะถ้าปล่อยผ่าน ชื่อพวกนี้จะกลายเป็นแผนก/ระดับชั้นใหม่" . PHP_EOL
        . "   ที่ไม่มีในเมนูของหน้าสมัครและหน้าแก้ไขข้อมูล และรายงานแยกแผนกจะนับแยกกลุ่ม" . PHP_EOL
        . PHP_EOL
        . "   ต้องทำอย่างใดอย่างหนึ่ง:" . PHP_EOL
        . "     1. ถ้าเป็นชื่อเดิมที่สะกดต่างออกไป (หรือเปลี่ยนชื่อ)" . PHP_EOL
        . "        -> เพิ่มลงใน DEPT_ALIASES ที่หัวไฟล์ scripts/import_students.php" . PHP_EOL
        . "     2. ถ้าเป็นแผนกใหม่จริงๆ" . PHP_EOL
        . "        -> เพิ่มชื่อลงใน valid_departments() ที่ src/constants.php" . PHP_EOL
        . "           และ DEPARTMENTS ที่ public/assets/js/constants.js ให้ตรงกัน" . PHP_EOL
        . "     3. ถ้ารู้อยู่แล้วและตั้งใจให้เข้าไปแบบนี้" . PHP_EOL
        . "        -> รันซ้ำด้วย --allow-unknown" . PHP_EOL
        . PHP_EOL
        . "   เพิ่มชื่อใหม่ลงใน tests/import_normalize_test.php ด้วย จะได้ไม่หลุดอีก" . PHP_EOL
        . PHP_EOL);
}

if (php_sapi_name() === 'cli' && realpath($argv[0]) === __FILE__) {
    $args = array_slice($argv, 1);
    $allowUnknown = in_array('--allow-unknown', $args, true);
    // Parse, validate and report without writing a single row. Safe to run on
    // a new workbook before anyone decides whether to import it.
    $dryRun = in_array('--dry-run', $args, true);
    $paths = array_values(array_filter($args, fn($a) => !str_starts_with($a, '--')));
    if (!$paths) {
        $paths = [__DIR__ . '/../Student.xlsx'];
    }

    $allRecords = [];
    foreach ($paths as $excelPath) {
        if (!is_file($excelPath)) {
            fwrite(STDERR, "Error: file not found: $excelPath\n");
            exit(1);
        }
        $rows = extract_students($excelPath);
        echo 'Parsed ' . count($rows) . ' rows from ' . basename($excelPath) . "\n";
        $allRecords = array_merge($allRecords, $rows);
    }

    $deduped = dedupe_keep_latest_term($allRecords);
    echo count($deduped) . " unique students after keeping latest term per student_id\n";

    $unknown = find_unknown_values($deduped);
    if ($unknown['department'] || $unknown['level']) {
        report_unknown_values($unknown, find_empty_departments($deduped));
        if (!$allowUnknown) {
            exit(1);
        }
        fwrite(STDERR, "   (--allow-unknown: นำเข้าต่อทั้งที่ยังไม่รู้จัก)\n\n");
    } else {
        echo "Departments and levels all recognised\n";
    }

    // Counts per department, so a rename or a missing sheet is visible in the
    // output rather than only in the reports weeks later.
    $byDepartment = array_count_values(array_column($deduped, 'department'));
    ksort($byDepartment);
    foreach ($byDepartment as $dept => $count) {
        // Padded by CHARACTERS, not bytes: printf's %-40s counts bytes and a
        // Thai character is three of them, so every name came out under-padded
        // and the counts never lined up into a column.
        $pad = str_repeat(' ', max(0, 34 - mb_strlen((string) $dept)));
        printf("  %s%s %5d\n", $dept, $pad, $count);
    }

    if ($dryRun) {
        echo "\n--dry-run: ไม่ได้เขียนลงฐานข้อมูล\n";
        exit(0);
    }

    import_to_mysql($deduped);
    echo "Import complete\n";
}
