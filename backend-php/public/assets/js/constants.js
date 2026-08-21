// Canonical department list — shared by the signup form and the admin
// members-management filter so both stay in sync with one list.
// Port of frontend-react/src/constants.js.
const DEPARTMENTS = [
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

const YEAR_OPTIONS = { 'ปวช.': ['1', '2', '3'], 'ปวส.': ['1', '2'] };

// Mirrors valid_prefixes() in src/constants.php — the API only accepts these
// three, so the admin edit form must not offer a fourth. signup.php still
// hardcodes the same three as <option> tags in its markup; if a prefix is ever
// added, all three places change together.
const PREFIXES = ['นาย', 'นาง', 'นางสาว'];
