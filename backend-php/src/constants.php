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
