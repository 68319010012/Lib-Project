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

function showSignupMessage(id, message) {
  const el = document.getElementById(id);
  if (!message) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = message;
  el.classList.remove('hidden');
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
    showSignupMessage('signup-error', '');
    showSignupMessage('signup-success', '');

    const password = document.getElementById('signup-password').value;
    const confirmPassword = document.getElementById('signup-confirm-password').value;
    if (password !== confirmPassword) {
      showSignupMessage('signup-error', 'รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน');
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
      showSignupMessage('signup-success', 'สร้างบัญชีสำเร็จ กำลังพาไปยังหน้าหลัก...');
      window.location.href = data.role === 'admin' ? '/admin-dashboard.php' : '/dashboard.php';
    } catch (err) {
      showSignupMessage('signup-error', err.message);
      submitBtn.disabled = false;
    }
  });
});
