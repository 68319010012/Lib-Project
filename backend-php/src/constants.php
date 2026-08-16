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
// or changed later from the profile page.
const MIN_PASSWORD_LENGTH = 8;
