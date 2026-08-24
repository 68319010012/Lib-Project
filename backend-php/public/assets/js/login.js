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
  const rememberBox = document.getElementById('login-remember');

  // Prefill from a remembered username.
  try {
    const saved = localStorage.getItem(REMEMBER_KEY);
    if (saved && usernameInput) {
      usernameInput.value = saved;
      if (rememberBox) rememberBox.checked = true;
    }
  } catch (_) { /* localStorage blocked — just skip prefill */ }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoginError('');
    submitBtn.disabled = true;
    const username = usernameInput.value.trim();
    const password = document.getElementById('login-password').value;

    try {
      if (rememberBox && rememberBox.checked) {
        localStorage.setItem(REMEMBER_KEY, username);
      } else {
        localStorage.removeItem(REMEMBER_KEY);
      }
    } catch (_) { /* ignore storage errors */ }

    try {
      const data = await apiPostJson('/login', { username, password });
      window.location.href = landingPathForRole(data.role);
    } catch (err) {
      showLoginError(err.message);
      submitBtn.disabled = false;
    }
  });
});

// ปุ่มดวงตาข้างช่องรหัสผ่าน — สลับ type ระหว่าง password กับ text
//
// สลับที่ตัว input เดิม ไม่ได้สร้าง input ใหม่ ค่าที่พิมพ์ไว้จึงไม่หาย และ
// ตัวจัดการรหัสผ่านของเบราว์เซอร์ยังเห็นเป็นช่องเดิม
document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('login-password');
  const btn = document.getElementById('login-password-toggle');
  if (!input || !btn) return;
  const icon = btn.querySelector('.material-symbols-outlined');

  btn.addEventListener('click', () => {
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
    btn.setAttribute('aria-label', label);
    btn.title = label;
    if (icon) icon.textContent = show ? 'visibility_off' : 'visibility';
    // คืนโฟกัสให้ช่องพิมพ์ พร้อมวางเคอร์เซอร์ไว้ท้ายข้อความเดิม เพื่อให้
    // กดดูแล้วพิมพ์ต่อได้ทันทีโดยไม่ต้องแตะช่องซ้ำ
    const end = input.value.length;
    input.focus();
    try { input.setSelectionRange(end, end); } catch (_) { /* type=text เท่านั้นที่รองรับ */ }
  });
});
