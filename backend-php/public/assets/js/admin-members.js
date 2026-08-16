// Port of frontend-react/src/pages/MembersManagementPage.jsx.

function populateMembersSelect(select, options, placeholder) {
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

let membersRows = null;
let membersPage = 1;

// Every filter change re-queries, so the page always restarts at 1 there;
// only the pager itself moves it.
function setMembersRows(rows) {
  membersRows = rows;
  membersPage = 1;
  renderMembersRows();
}

function renderMembersRows() {
  const tbody = document.getElementById('members-tbody');
  const countEl = document.getElementById('members-count');
  const totalEl = document.getElementById('members-total-badge');
  const pagerEl = document.getElementById('members-pager');
  if (membersRows === null) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">กำลังโหลด…</td></tr>';
    countEl.textContent = 'พบ 0 รายการ';
    totalEl.textContent = '–';
    pagerEl.innerHTML = '';
    return;
  }
  totalEl.textContent = membersRows.length;
  countEl.textContent = `พบ ${membersRows.length} รายการ`;
  if (membersRows.length === 0) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="7">ไม่พบสมาชิกตามเงื่อนไขนี้</td></tr>';
    pagerEl.innerHTML = '';
    return;
  }

  const pageState = paginateRows(membersRows, membersPage);
  membersPage = pageState.page;

  tbody.innerHTML = pageState.rows
    .map((r) => {
      const lastVisit = r.last_visit
        ? new Date(r.last_visit).toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : 'ยังไม่เคยเข้าใช้';
      const fullName = `${r.prefix || ''}${r.first_name} ${r.last_name}`;
      return `
        <tr class="hover:bg-surface-container-low/50 dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-label-code text-primary dark:text-primary-fixed-dim">${r.student_id}</td>
          <td class="px-6 py-4 font-bold text-on-surface dark:text-inverse-on-surface">${fullName}</td>
          <td class="px-6 py-4 text-on-surface-variant dark:text-dm-text-secondary">${r.department || '-'}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${r.level || '-'}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${r.year_level || '-'}</td>
          <td class="px-6 py-4 text-right text-on-surface-variant dark:text-dm-text-secondary text-sm">${lastVisit}</td>
          <td class="px-6 py-4 text-right">
            <button type="button" class="reset-password-btn text-xs font-bold text-primary dark:text-primary-fixed-dim hover:underline" data-user-id="${r.user_id}" data-name="${fullName}">รีเซ็ตรหัสผ่าน</button>
          </td>
        </tr>
      `;
    })
    .join('');

  tbody.querySelectorAll('.reset-password-btn').forEach((btn) => {
    btn.addEventListener('click', () => resetMemberPassword(btn.dataset.userId, btn.dataset.name));
  });

  renderPager(pagerEl, pageState, (p) => {
    membersPage = p;
    renderMembersRows();
  });
}

// No email/phone on file for students (see schema.sql), so a self-service
// "forgot password" email flow isn't possible — the admin resets it here
// instead and reads the generated temp password out to the student in
// person. Server always generates the password (never accepts one typed
// here) so it can't end up weak or guessable.
async function resetMemberPassword(userId, name) {
  if (!window.confirm(`ยืนยันรีเซ็ตรหัสผ่านของ "${name}" ?\nระบบจะสุ่มรหัสผ่านชั่วคราวให้ใหม่ ใช้เมื่อนักศึกษาลืมรหัสผ่านเท่านั้น`)) {
    return;
  }
  try {
    const data = await apiFetch('/admin/members/reset-password', { method: 'POST', body: JSON.stringify({ user_id: Number(userId) }) });
    showToast(`รีเซ็ตรหัสผ่านของ "${name}" สำเร็จ — รหัสผ่านชั่วคราว: ${data.temp_password} (แจ้งนักศึกษาให้เปลี่ยนรหัสผ่านหลังเข้าสู่ระบบ)`, { type: 'success', duration: 0 });
  } catch (err) {
    showToast(err.message || 'รีเซ็ตรหัสผ่านไม่สำเร็จ', { type: 'error' });
  }
}

async function loadMembers() {
  const params = new URLSearchParams();
  const search = document.getElementById('members-search').value.trim();
  const department = document.getElementById('members-department').value.trim();
  const level = document.getElementById('members-level').value;
  const yearLevel = document.getElementById('members-year-level').value;
  if (search) params.set('search', search);
  if (department) params.set('department', department);
  if (level) params.set('level', level);
  if (yearLevel) params.set('year_level', yearLevel);

  setMembersRows(null);
  const data = await apiFetch(`/admin/members?${params.toString()}`);
  setMembersRows(data);
}

document.addEventListener('DOMContentLoaded', () => {
  populateMembersSelect(document.getElementById('members-department'), DEPARTMENTS, 'ทั้งหมด');

  const levelSelect = document.getElementById('members-level');
  const yearSelect = document.getElementById('members-year-level');
  function refreshYearOptions() {
    const options = levelSelect.value ? YEAR_OPTIONS[levelSelect.value] : ['1', '2', '3'];
    const current = yearSelect.value;
    populateMembersSelect(yearSelect, options, 'ทุกชั้นปี');
    if (options.includes(current)) yearSelect.value = current;
  }
  levelSelect.addEventListener('change', () => {
    refreshYearOptions();
    loadMembers();
  });
  refreshYearOptions();

  // No filter button — every field re-queries as soon as it changes.
  // Search is debounced (300ms) so it doesn't fire a request per keystroke;
  // Enter still fires immediately for anyone used to pressing it.
  let searchDebounce;
  document.getElementById('members-search').addEventListener('input', () => {
    clearTimeout(searchDebounce);
    searchDebounce = setTimeout(loadMembers, 300);
  });
  document.getElementById('members-search').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      clearTimeout(searchDebounce);
      loadMembers();
    }
  });
  document.getElementById('members-department').addEventListener('change', loadMembers);
  yearSelect.addEventListener('change', loadMembers);

  loadMembers();
});
