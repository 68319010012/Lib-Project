// Port of frontend-react/src/pages/LoginPage.jsx.
const ROLE_LABEL_TH = { student: 'นักศึกษา', admin: 'แอดมิน' };
let selectedRole = 'student';

function setRole(role) {
  selectedRole = role;
  document.querySelectorAll('[data-role-tab]').forEach((btn) => {
    const active = btn.dataset.roleTab === role;
    btn.classList.toggle('bg-surface-white', active);
    btn.classList.toggle('dark:bg-dm-surface', active);
    btn.classList.toggle('text-primary', active);
    btn.classList.toggle('dark:text-primary-fixed-dim', active);
    btn.classList.toggle('shadow-sm', active);
    btn.classList.toggle('border', active);
    btn.classList.toggle('border-outline-variant', active);
    btn.classList.toggle('dark:border-dm-border', active);
    btn.classList.toggle('text-on-surface-variant', !active);
    btn.classList.toggle('dark:text-dm-text-secondary', !active);
  });
  const usernameLabel = document.getElementById('login-username-label');
  const usernameInput = document.getElementById('login-username');
  if (role === 'admin') {
    usernameLabel.textContent = 'ชื่อผู้ใช้แอดมิน';
    usernameInput.placeholder = 'ชื่อผู้ใช้แอดมิน';
  } else {
    usernameLabel.textContent = 'ชื่อผู้ใช้ (รหัสนักศึกษา)';
    usernameInput.placeholder = 'ชื่อผู้ใช้บัญชีของคุณ';
  }
  document.getElementById('login-signup-link').classList.toggle('hidden', role !== 'student');
}

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

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-role-tab]').forEach((btn) => {
    btn.addEventListener('click', () => setRole(btn.dataset.roleTab));
  });

  const form = document.getElementById('login-form');
  const submitBtn = document.getElementById('login-submit');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showLoginError('');
    submitBtn.disabled = true;
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    try {
      const data = await apiPostJson('/login', { username, password });
      if (data.role !== selectedRole) {
        showLoginError(`บัญชีนี้เป็นบัญชี${ROLE_LABEL_TH[data.role] || data.role} — กำลังพาไปยังหน้าที่ถูกต้อง`);
      }
      window.location.href = data.role === 'admin' ? '/admin-dashboard' : '/dashboard';
    } catch (err) {
      showLoginError(err.message);
      submitBtn.disabled = false;
    }
  });
});
