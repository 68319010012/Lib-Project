// Port of frontend-react/src/pages/LoginPage.jsx.
//
// One set of fields for everyone. The page used to ask which kind of account
// you were before it had seen the username, which is a question the account
// itself already answers: /login looks the user up, and its response carries
// the role that decides where to land. Picking the wrong tab only ever
// produced an error on a login that was otherwise perfectly valid.

function showLoginError(message) {
  const el = document.getElementById('login-error');
  if (!message) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = message;
  el.classList.remove('hidden');
}

// ข้อความผิดพลาดรายช่อง วางไว้ใต้ช่องนั้น คนกรอกจึงเห็นทันทีว่าปัญหาอยู่ตรงไหน
// แทนที่จะเห็นข้อความรวมอยู่บนสุดแล้วต้องไล่หาเอง
function setLoginFieldError(input, message) {
  const box = document.getElementById(`${input.id}-error`);
  const field = input.closest('.login-field');
  if (message) {
    input.setAttribute('aria-invalid', 'true');
    if (field) field.classList.add('is-invalid');
    if (box) {
      box.textContent = message;
      box.hidden = false;
    }
  } else {
    input.removeAttribute('aria-invalid');
    if (field) field.classList.remove('is-invalid');
    if (box) {
      box.textContent = '';
      box.hidden = true;
    }
  }
}

// Where each role's session belongs. Server-side guards (partials/guard.php)
// enforce this independently — a student who types /admin-dashboard is sent
// back regardless of what this function decides — so this is routing, not
// access control.
function landingPathForRole(role) {
  return role === 'admin' ? '/admin-dashboard' : '/dashboard';
}

// "จำรหัสนักศึกษาไว้" stores only the username (never the password) so the
// field is prefilled next time. It is a convenience for the login box, not a
// persistent session — that stays server-side in the PHP session cookie.
const REMEMBER_KEY = 'ntc-remember-username';

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('login-form');
  const submitBtn = document.getElementById('login-submit');
  const usernameInput = document.getElementById('login-username');
  const passwordInput = document.getElementById('login-password');
  const rememberBox = document.getElementById('login-remember');

  // Prefill from a remembered username.
  let remembered = '';
  try {
    remembered = localStorage.getItem(REMEMBER_KEY) || '';
  } catch (_) { /* localStorage blocked — just skip prefill */ }
  if (remembered && usernameInput) {
    usernameInput.value = remembered;
    if (rememberBox) rememberBox.checked = true;
  }

  // โฟกัสอัตโนมัติเฉพาะจอกว้าง — บนมือถือการโฟกัสตั้งแต่เปิดหน้าจะเด้งแป้นพิมพ์
  // ขึ้นมาบังครึ่งจอทันทีทั้งที่ผู้ใช้ยังไม่ได้ตั้งใจจะพิมพ์
  if (window.matchMedia('(min-width: 768px)').matches) {
    (remembered ? passwordInput : usernameInput).focus();
  }

  // ลบข้อความผิดพลาดทันทีที่เริ่มแก้ ไม่ต้องกดส่งอีกรอบถึงจะรู้ว่าหายแล้ว
  [usernameInput, passwordInput].forEach((el) => {
    el.addEventListener('input', () => setLoginFieldError(el, ''));
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoginError('');

    const username = usernameInput.value.trim();
    const password = passwordInput.value;

    setLoginFieldError(usernameInput, username ? '' : 'กรุณากรอกรหัสนักศึกษาหรือชื่อผู้ใช้');
    setLoginFieldError(passwordInput, password ? '' : 'กรุณากรอกรหัสผ่าน');
    if (!username || !password) {
      (username ? passwordInput : usernameInput).focus();
      return;
    }

    // ปิดปุ่มพร้อมขึ้นวงหมุนระหว่างรอคำตอบ ถ้าปิดเฉยๆ ปุ่มจะดูเหมือนค้าง
    // จนคนกดคิดว่าไม่ทำงานแล้วกดซ้ำ
    submitBtn.disabled = true;
    submitBtn.classList.add('is-loading');
    submitBtn.setAttribute('aria-busy', 'true');

    try {
      if (rememberBox && rememberBox.checked) {
        localStorage.setItem(REMEMBER_KEY, username);
      } else {
        localStorage.removeItem(REMEMBER_KEY);
      }
    } catch (_) { /* ignore storage errors */ }

    try {
      const data = await apiPostJson('/login', { username, password });
      // ไม่ปลดสถานะกำลังโหลด เพราะกำลังจะเปลี่ยนหน้าอยู่แล้ว ปลดตอนนี้จะเห็น
      // ปุ่มกระพริบกลับมากดได้เสี้ยววินาทีก่อนหน้าจะเปลี่ยน
      window.location.href = landingPathForRole(data.role);
    } catch (err) {
      showLoginError(err.message);
      submitBtn.disabled = false;
      submitBtn.classList.remove('is-loading');
      submitBtn.removeAttribute('aria-busy');
      passwordInput.focus();
    }
  });
});

// ปุ่มดวงตาข้างช่องรหัสผ่านย้ายไปอยู่ใน assets/js/password-toggle.js แล้ว
// เพราะหน้าสมัครสมาชิกและหน้าเปลี่ยนรหัสผ่านต้องใช้พฤติกรรมเดียวกัน
