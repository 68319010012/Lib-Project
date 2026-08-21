<?php
// Server-side copies of the option lists the signup form offers.
//
// public/assets/js/constants.js holds the same lists for building the
// dropdowns. That copy populates the <select>s; this one decides what the
// API will actually accept — a form control is a convenience, not a
// constraint, and anything can POST /register directly. Keep the two in
// sync when a department is added or renamed.

function valid_prefixes(): array
{
    return ['นาย', 'นาง', 'นางสาว'];
}

function valid_departments(): array
{
    return [
        'การจัดการสำนักงาน',
        'การจัดการโลจีสติกส์และซับพลายเซน',
        'การตลาด',
        'การบัญชี',
        'การโรงแรม',
        'เทคโนโลยีธุรกิจดิจิทัล',
        'ช่างกลโรงงาน',
        'ช่างก่อสร้าง',
        'ช่างยนต์',
        'ช่างอิเล็กทรอนิกส์',
        'ช่างเชื่อมโลหะ',
        'ช่างไฟฟ้ากำลัง',
        'ศิลปกรรม',
        'เทคโนโลยีสารสนเทศ',
    ];
}

// Minimum length for any password the app accepts, whether chosen at signup
// or changed later from the profile page. Counted in CHARACTERS (mb_strlen),
// not bytes: strlen() would let a 4-character Thai password through, since
// each Thai character is 3 UTF-8 bytes and 4 of them already clear 8 bytes.
const MIN_PASSWORD_LENGTH = 8;

// bcrypt only reads the first 72 bytes and silently ignores the rest, so two
// different long passwords sharing a prefix would both unlock the account.
// Refuse rather than truncate. 72 bytes is ~24 Thai characters or 72 ASCII.
const MAX_PASSWORD_BYTES = 72;

// True when the password is an acceptable length; $error receives the reason.
function password_length_ok(string $password, ?string &$error = null): bool
{
    if (mb_strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'รหัสผ่านต้องมีอย่างน้อย ' . MIN_PASSWORD_LENGTH . ' ตัวอักษร';
        return false;
    }
    if (strlen($password) > MAX_PASSWORD_BYTES) {
        $error = 'รหัสผ่านยาวเกินไป (ภาษาไทยไม่เกิน 24 ตัวอักษร หรือภาษาอังกฤษไม่เกิน 72 ตัวอักษร)';
        return false;
    }
    return true;
}

// How long a login survives. The cookie previously used lifetime => 0, i.e.
// "until the browser closes", so quitting the browser on a phone logged the
// student out — the exact thing they were asking not to happen. A month covers
// normal term-time use without keeping a session alive forever.
//
// Always paired with session.gc_maxlifetime in bootstrap_session(): the cookie
// decides how long the BROWSER keeps presenting the session ID, gc_maxlifetime
// decides how long the SERVER keeps the record behind it. Raise one without the
// other and the longer half is decorative.
const SESSION_LIFETIME_SECONDS = 60 * 60 * 24 * 30;

// Mirrors YEAR_OPTIONS in public/assets/js/constants.js.
function valid_year_levels(string $level): array
{
    return $level === 'ปวช.' ? ['1', '2', '3'] : ['1', '2'];
}

// Shared by signup (handle_register) and the admin edit form
// (handle_admin_member_update). Both write the same columns of `students`, so
// both have to agree on what a valid value is — a whitelist only one of them
// enforces is a whitelist with a way around it.
//
// $profile keys: prefix, gender, first_name, last_name, department, level,
// year_level. Values are expected already trimmed. $error receives the first
// reason it failed, ready to hand straight to json_error().
function validate_student_profile(array $profile, ?string &$error = null): bool
{
    $prefix = (string) ($profile['prefix'] ?? '');
    $gender = (string) ($profile['gender'] ?? '');
    $firstName = (string) ($profile['first_name'] ?? '');
    $lastName = (string) ($profile['last_name'] ?? '');
    $department = (string) ($profile['department'] ?? '');
    $level = (string) ($profile['level'] ?? '');
    $yearLevel = (string) ($profile['year_level'] ?? '');

    if ($prefix === '' || $firstName === '' || $lastName === '' || $department === '') {
        $error = 'กรุณากรอกคำนำหน้า ชื่อ นามสกุล และแผนกวิชา';
        return false;
    }
    if (!in_array($prefix, valid_prefixes(), true)) {
        $error = 'คำนำหน้าไม่ถูกต้อง';
        return false;
    }
    if (!in_array($department, valid_departments(), true)) {
        $error = 'แผนกวิชาไม่ถูกต้อง';
        return false;
    }
    // Names are free text, so they get bounds rather than a whitelist.
    // mb_strlen counts Thai characters, not UTF-8 bytes, so a legitimate Thai
    // name isn't rejected for being "too long" at the 100-char column.
    foreach (['ชื่อ' => $firstName, 'นามสกุล' => $lastName] as $label => $value) {
        if (mb_strlen($value) > 100) {
            $error = "$label ยาวเกินไป (ไม่เกิน 100 ตัวอักษร)";
            return false;
        }
        // Belt-and-braces against stored XSS: the admin tables escape on output
        // (escapeHtml in assets/js/api.js), which is the fix that actually
        // matters. Refusing angle brackets here just means a payload never
        // reaches the database to begin with — no Thai or English name needs them.
        if (preg_match('/[<>]/', $value)) {
            $error = "$label มีอักขระที่ไม่อนุญาต";
            return false;
        }
    }
    if (!in_array($gender, ['male', 'female'], true)) {
        $error = 'กรุณาเลือกเพศ';
        return false;
    }
    if (!in_array($level, ['ปวช.', 'ปวส.'], true)) {
        $error = 'ระดับชั้นต้องเป็น ปวช. หรือ ปวส.';
        return false;
    }
    $validYears = valid_year_levels($level);
    if (!in_array($yearLevel, $validYears, true)) {
        $error = "ชั้นปีของ $level ต้องเป็นหนึ่งใน " . implode(', ', $validYears);
        return false;
    }
    return true;
}
