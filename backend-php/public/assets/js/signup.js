// Port of frontend-react/src/pages/SignupPage.jsx.

function populateSelect(select, options, placeholder) {
  select.innerHTML = '';
  const opt0 = document.createElement('option');
  opt0.value = '';
  opt0.textContent = placeholder;
  select.appendChild(opt0);
  options.forEach((value) => {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = value;
    select.appendChild(opt);
  });
}

// A message at the top of a long form is easy to miss once the reader has
// scrolled down filling in later fields — a centered popup can't be missed
// regardless of scroll position, and states clearly whether signup
// succeeded or failed and why.
function showSignupResultModal(success, title, message) {
  const modal = document.getElementById('signup-result-modal');
  const icon = document.getElementById('signup-result-icon');
  const glyph = document.getElementById('signup-result-icon-glyph');
  icon.className = `w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 ${success ? 'bg-status-success' : 'bg-error'}`;
  glyph.className = `material-symbols-outlined text-4xl ${success ? 'text-white' : 'text-on-error'}`;
  glyph.textContent = success ? 'check_circle' : 'error';
  document.getElementById('signup-result-title').textContent = title;
  document.getElementById('signup-result-message').textContent = message;
  modal.classList.remove('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
  const departmentSelect = document.getElementById('signup-department');
  populateSelect(departmentSelect, DEPARTMENTS, '-- เลือกแผนกวิชา --');

  const levelSelect = document.getElementById('signup-level');
  const yearSelect = document.getElementById('signup-year-level');

  function refreshYearOptions() {
    const options = YEAR_OPTIONS[levelSelect.value] || [];
    populateSelect(yearSelect, options, options.length ? '-- เลือกชั้นปี --' : '-- เลือกระดับชั้นก่อน --');
  }
  levelSelect.addEventListener('change', refreshYearOptions);
  refreshYearOptions();

  const form = document.getElementById('signup-form');
  const submitBtn = document.getElementById('signup-submit');
  form.addEventListener('submit', async (e) => {
    e.preventDefault();

    const password = document.getElementById('signup-password').value;
    const confirmPassword = document.getElementById('signup-confirm-password').value;
    if (password !== confirmPassword) {
      showSignupResultModal(false, 'สมัครสมาชิกไม่สำเร็จ', 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
      return;
    }

    submitBtn.disabled = true;
    try {
      const data = await apiPostJson('/register', {
        student_id: document.getElementById('signup-student-id').value.trim(),
        prefix: document.getElementById('signup-prefix').value,
        gender: document.getElementById('signup-gender').value,
        first_name: document.getElementById('signup-first-name').value.trim(),
        last_name: document.getElementById('signup-last-name').value.trim(),
        department: departmentSelect.value,
        level: levelSelect.value,
        year_level: yearSelect.value,
        password,
      });
      showSignupResultModal(true, 'สมัครสมาชิกสำเร็จ', 'กำลังพาไปยังหน้าหลัก...');
      setTimeout(() => {
        window.location.href = data.role === 'admin' ? '/admin-dashboard' : '/dashboard';
      }, 1200);
    } catch (err) {
      showSignupResultModal(false, 'สมัครสมาชิกไม่สำเร็จ', err.message);
      submitBtn.disabled = false;
    }
  });

  document.getElementById('signup-result-close').addEventListener('click', () => {
    document.getElementById('signup-result-modal').classList.add('hidden');
  });
});
