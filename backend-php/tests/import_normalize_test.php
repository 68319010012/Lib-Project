<?php
/**
 * Guards normalize_department() / normalize_level() in scripts/import_students.php.
 *
 * The department cell on the อวท. paper form is free text. Across the 2569
 * roster alone it arrives in 51 spellings for 14 real departments, and the
 * import is the only place that folds them together. If that mapping silently
 * stops covering a spelling, the roster gains a 15th "department" that no
 * dropdown offers and that every per-department report groups separately —
 * a failure that shows up as odd-looking charts weeks later, not as an error.
 *
 * Every string in RAW_DEPARTMENTS was observed in the real 1-2569 workbooks.
 * When the college adds or renames a department, add its spellings here and to
 * DEPT_ALIASES / valid_departments() together.
 *
 * Usage: php tests/import_normalize_test.php
 */

require_once __DIR__ . '/../src/constants.php';
require_once __DIR__ . '/../scripts/import_students.php';

const RAW_DEPARTMENTS = [
    'ช่างไฟฟ้ากำลัง', 'ช่างยนต์', 'ช่างกลโรงงาน', 'ช่างอิเล็กทรอนิกส์', 'การบัญชี',
    'ช่างเชื่อมโลหะ', 'ช่างก่อสร้าง', 'การตลาด', 'เทคโนโลยีสารสนเทศ', 'ศิลปกรรม',
    'คอมพิวเตอร์', 'การจัดการสำนักงาน', 'การโรงแรม', 'เทคโนโลยีธุรกิจดิจิทัล',
    'การจัดการโลจิสติกส์และซับพลายเซน',
    // programme suffixes the check-in system does not model
    'ช่างไฟฟ้ากำลัง (ม.6)', 'ช่างไฟฟ้ากำลัง (ทวิ)', 'ช่างไฟฟ้ากำลัง…(ทวิ)',
    'ช่างยนต์ (ทวิ)', 'ช่างยนต์ (ม.6)',
    'ช่างกลโรงงาน (ม.6)', 'ช่างกลโรงงาน (SCG)', 'ช่างกลโรงงาน (ผลิตชิ้นส่วนยานยนต์)',
    'ช่างกลโรงงาน ((ผลิตชิ้นส่วนยานยนต์)',
    'ช่างเชื่อมโลหะ (ม.6)', 'ช่างก่อสร้าง (ม.6)',
    'ช่างอิเล็กทรอนิกส์ (ทวิ)', 'ช่างอิเล็กทรอนิกส์ (.ม.6)',
    'การบัญชี (ทวิ)', 'การบัญชี (ม.6)', 'การตลาด (ม.6)', 'การตลาด (ทวิ)',
    'การจัดการสำนักงาน (ม.6)',
    'การจัดการโลจิสติกส์และซับพลายเซน (ม.6)', 'การจัดการโลจิสติกส์และซับพลายเซน (ทวิ)',
    'การจัดการโลจิสติกส์และซับพลายเซน (ม.6 ทวิ)', 'การจัดการโลจิสติกส์และซับพลายเซน (ม.6ทวิ)',
    'การโรงแรม (ทวิ)', 'การโรงแรม (ม.6)', 'การโรงแรม (ม.6 ทวิ)',
    'เทคโนโลยีสารสนเทศ (ม.6)', 'เทคโนโลยีสารสนเทศ (ทวิ)',
    'ศิลปกรรม (ม.6)', 'คอมพิวเตอร์ (ทวิ)', 'คอมพิวเตอร์ (ม.6)',
    // typos as they appear on the sheets: missing สระอุ, "ทิว" for "ทวิ"
    'เทคโนโลยีธรกิจดิจิทัล', 'เทคโนโลยีธรกิจดิจิทัล(ม.6)', 'เทคโนโลยีธรกิจดิจิทัล(ทวิ)',
    'เทคโนโลยีธรกิจดิจิทัล (ทวิ)', 'เทคโนโลยีธรกิจดิจิทัล (ม.6)',
    'เทคโนโลยีสารสนเทศ (ทิว)',
];

// A few of the 51 that must land on a specific name, not merely on *some*
// valid one — these are the renames and typo fixes, where "valid" is not
// enough to prove the mapping is right.
const DEPARTMENT_EXPECTATIONS = [
    'คอมพิวเตอร์' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'คอมพิวเตอร์ (ม.6)' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'เทคโนโลยีธรกิจดิจิทัล' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'เทคโนโลยีธรกิจดิจิทัล(ทวิ)' => 'เทคโนโลยีธุรกิจดิจิทัล',
    'การจัดการโลจิสติกส์และซับพลายเซน' => 'การจัดการโลจีสติกส์และซับพลายเซน',
    'เทคโนโลยีสารสนเทศ (ทิว)' => 'เทคโนโลยีสารสนเทศ',
    'ช่างกลโรงงาน ((ผลิตชิ้นส่วนยานยนต์)' => 'ช่างกลโรงงาน',
    'ช่างไฟฟ้ากำลัง…(ทวิ)' => 'ช่างไฟฟ้ากำลัง',
];

const LEVEL_EXPECTATIONS = [
    'ปวช' => 'ปวช.',
    'ปวส' => 'ปวส.',
    'ปวส (ทวิ)' => 'ปวส.',
    'ปวส (ม.6)' => 'ปวส.',
    // already normalised input must survive a second pass unchanged
    'ปวช.' => 'ปวช.',
    'ปวส.' => 'ปวส.',
];

$passed = 0;
$failed = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "PASS: $label\n";
    } else {
        $failed++;
        echo "FAIL: $label" . ($detail !== '' ? " — $detail" : '') . "\n";
    }
}

$valid = valid_departments();
$results = [];
foreach (RAW_DEPARTMENTS as $raw) {
    $results[$raw] = normalize_department(clean($raw));
}

$strays = array_unique(array_filter($results, fn($n) => !in_array($n, $valid, true)));
check(
    'every roster spelling maps onto valid_departments()',
    $strays === [],
    $strays ? 'unmapped: ' . implode(', ', $strays) : ''
);

check(
    'the 51 spellings collapse to 14 departments',
    count(array_unique($results)) === 14,
    'got ' . count(array_unique($results)) . ': ' . implode(' | ', array_unique($results))
);

foreach (DEPARTMENT_EXPECTATIONS as $raw => $expected) {
    check("[$raw] -> [$expected]", $results[$raw] === $expected, 'got [' . $results[$raw] . ']');
}

foreach (LEVEL_EXPECTATIONS as $raw => $expected) {
    $got = normalize_level(clean($raw));
    check("level [$raw] -> [$expected]", $got === $expected, "got [$got]");
}

// --- the guard that stops an import carrying a name nobody has mapped -------
//
// The checks above only prove the spellings we already know still work. This
// half proves the import NOTICES one we don't — the case that actually happens
// when the college opens or renames a department, and the case a fixed list of
// expectations can never cover by itself.

function record(string $studentId, string $department, string $level): array
{
    return ['student_id' => $studentId, 'department' => $department, 'level' => $level];
}

$clean = [
    record('69201010001', 'ช่างยนต์', 'ปวช.'),
    record('69301010001', 'การบัญชี', 'ปวส.'),
];
$unknown = find_unknown_values($clean);
check(
    'a roster of known values reports nothing unknown',
    $unknown['department'] === [] && $unknown['level'] === []
);

$withNewDept = array_merge($clean, [
    record('69216020001', 'เทคโนโลยียานยนต์ไฟฟ้า', 'ปวช.'),
    record('69216020002', 'เทคโนโลยียานยนต์ไฟฟ้า', 'ปวช.'),
]);
$unknown = find_unknown_values($withNewDept);
check(
    'an unmapped department is reported',
    isset($unknown['department']['เทคโนโลยียานยนต์ไฟฟ้า'])
);
check(
    'the report counts every row of it, not just the first',
    ($unknown['department']['เทคโนโลยียานยนต์ไฟฟ้า']['count'] ?? 0) === 2,
    'got ' . ($unknown['department']['เทคโนโลยียานยนต์ไฟฟ้า']['count'] ?? 'none')
);
check(
    'the report names a real student so the row can be found in the workbook',
    ($unknown['department']['เทคโนโลยียานยนต์ไฟฟ้า']['sample'] ?? '') === '69216020001'
);

$unknown = find_unknown_values(array_merge($clean, [record('69201010999', 'ช่างยนต์', 'ปริญญาตรี')]));
check('an unmapped ระดับชั้น is reported', isset($unknown['level']['ปริญญาตรี']));

// An empty department alongside an unknown one is the signature of a rename,
// which is fixed with an alias rather than by adding a 15th department.
$empty = find_empty_departments($withNewDept);
check(
    'departments with no students are listed',
    in_array('ศิลปกรรม', $empty, true) && !in_array('ช่างยนต์', $empty, true)
);

// A blank cell is a gap in the paper form, not a new department: the import
// leaves it empty rather than inventing a name, and must not report it.
$unknown = find_unknown_values([record('69201010001', '', '')]);
check(
    'blank department/level are not reported as unknown',
    $unknown['department'] === [] && $unknown['level'] === []
);

echo "\n$passed passed, $failed failed\n";
exit($failed === 0 ? 0 : 1);
