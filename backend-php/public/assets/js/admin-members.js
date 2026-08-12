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

function renderMembersRows(rows) {
  const tbody = document.getElementById('members-tbody');
  const countEl = document.getElementById('members-count');
  const totalEl = document.getElementById('members-total-badge');
  if (rows === null) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">กำลังโหลด…</td></tr>';
    countEl.textContent = 'พบ 0 รายการ';
    totalEl.textContent = '–';
    return;
  }
  totalEl.textContent = rows.length;
  countEl.textContent = `พบ ${rows.length} รายการ`;
  if (rows.length === 0) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">ไม่พบสมาชิกตามเงื่อนไขนี้</td></tr>';
    return;
  }
  tbody.innerHTML = rows
    .map((r) => {
      const lastVisit = r.last_visit
        ? new Date(r.last_visit).toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : 'ยังไม่เคยเข้าใช้';
      return `
        <tr class="hover:bg-surface-container-low/50 dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-label-code text-primary dark:text-primary-fixed-dim">${r.student_id}</td>
          <td class="px-6 py-4 font-bold text-on-surface dark:text-inverse-on-surface">${r.prefix || ''}${r.first_name} ${r.last_name}</td>
          <td class="px-6 py-4 text-on-surface-variant dark:text-dm-text-secondary">${r.department || '-'}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${r.level || '-'}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${r.year_level || '-'}</td>
          <td class="px-6 py-4 text-right text-on-surface-variant dark:text-dm-text-secondary text-sm">${lastVisit}</td>
        </tr>
      `;
    })
    .join('');
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

  renderMembersRows(null);
  const data = await apiFetch(`/admin/members?${params.toString()}`);
  renderMembersRows(data);
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
