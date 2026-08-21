// Port of frontend-react/src/pages/MembersManagementPage.jsx, extended with
// admin editing: profile fields, account status, role, and delete.
//
// Every guard here is a second copy of one the API already enforces
// (admin_handlers.php). Disabling a button the server would refuse anyway is a
// courtesy — it explains the rule before the click instead of after — never the
// thing that makes the rule true.

function populateMembersSelect(select, options, placeholder) {
  select.innerHTML = '';
  if (placeholder !== null) {
    const opt0 = document.createElement('option');
    opt0.value = '';
    opt0.textContent = placeholder;
    select.appendChild(opt0);
  }
  options.forEach((value) => {
    const opt = document.createElement('option');
    opt.value = value;
    opt.textContent = value;
    select.appendChild(opt);
  });
}

let membersRows = null;
let membersPage = 1;
// The signed-in admin's own user_id, so their row can say why it can't be
// demoted or deleted rather than only failing on submit.
let currentUserId = null;
let editingMember = null;

const STATUS_LABEL = { approved: 'ใช้งานได้', pending: 'รออนุมัติ', retired: 'ระงับ' };

// Every filter change re-queries, so the page always restarts at 1 there;
// only the pager itself moves it.
function setMembersRows(rows) {
  membersRows = rows;
  membersPage = 1;
  renderMembersRows();
}

function memberBadges(row) {
  const badges = [];
  if (row.role === 'admin') {
    badges.push('<span class="text-xs font-bold text-primary dark:text-primary-fixed-dim">แอดมิน</span>');
  }
  // 'approved' is the normal case and every row would carry the same chip, so
  // only the exceptions are labelled.
  if (row.account_status !== 'approved') {
    badges.push(`<span class="text-xs font-bold text-warning">${escapeHtml(STATUS_LABEL[row.account_status] || row.account_status)}</span>`);
  }
  return badges.length ? ` ${badges.join(' ')}` : '';
}

function renderMembersRows() {
  const tbody = document.getElementById('members-tbody');
  const countEl = document.getElementById('members-count');
  const totalEl = document.getElementById('members-total-badge');
  const pagerEl = document.getElementById('members-pager');
  if (membersRows === null) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="7">กำลังโหลด…</td></tr>';
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
    .map((r, i) => {
      const lastVisit = r.last_visit
        ? new Date(r.last_visit).toLocaleString('th-TH', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })
        : 'ยังไม่เคยเข้าใช้';
      const fullName = `${r.prefix || ''}${r.first_name} ${r.last_name}`;
      return `
        <tr class="hover:bg-surface-container-low/50 dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-label-code text-primary dark:text-primary-fixed-dim">${escapeHtml(r.student_id)}</td>
          <td class="px-6 py-4 font-bold text-on-surface dark:text-inverse-on-surface">${escapeHtml(fullName)}${memberBadges(r)}</td>
          <td class="px-6 py-4 text-on-surface-variant dark:text-dm-text-secondary">${escapeHtml(r.department || '-')}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${escapeHtml(r.level || '-')}</td>
          <td class="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">${escapeHtml(r.year_level || '-')}</td>
          <td class="px-6 py-4 text-right text-on-surface-variant dark:text-dm-text-secondary text-sm">${escapeHtml(lastVisit)}</td>
          <td class="px-6 py-4 text-right">
            <button type="button" class="edit-member-btn text-xs font-bold text-primary dark:text-primary-fixed-dim hover:underline" data-row-index="${i}">แก้ไข</button>
            <button type="button" class="reset-password-btn text-xs font-bold text-primary dark:text-primary-fixed-dim hover:underline" data-user-id="${escapeHtml(r.user_id)}" data-name="${escapeHtml(fullName)}">รีเซ็ตรหัสผ่าน</button>
          </td>
        </tr>
      `;
    })
    .join('');

  tbody.querySelectorAll('.reset-password-btn').forEach((btn) => {
    btn.addEventListener('click', () => resetMemberPassword(btn.dataset.userId, btn.dataset.name));
  });
  // Index into the page's rows rather than re-reading fields off data-*
  // attributes: the edit form needs eight of them, and round-tripping those
  // through the DOM is how a value quietly becomes a string of "undefined".
  tbody.querySelectorAll('.edit-member-btn').forEach((btn) => {
    btn.addEventListener('click', () => openMemberEdit(pageState.rows[Number(btn.dataset.rowIndex)]));
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
  const ok = await showConfirmModal('ระบบจะสุ่มรหัสผ่านชั่วคราวให้ใหม่ ใช้เมื่อนักศึกษาลืมรหัสผ่านเท่านั้น', {
    title: 'ยืนยันรีเซ็ตรหัสผ่าน',
    subject: name,
    confirmLabel: 'รีเซ็ตรหัสผ่าน',
    danger: true,
    icon: 'lock_reset',
  });
  if (!ok) return;
  try {
    const data = await apiFetch('/admin/members/reset-password', { method: 'POST', body: JSON.stringify({ user_id: Number(userId) }) });
    showResetResultModal(name, data.temp_password);
  } catch (err) {
    showToast(err.message || 'รีเซ็ตรหัสผ่านไม่สำเร็จ', { type: 'error' });
  }
}

// Big centered result popup for the temp password (replaces the old corner
// toast) — the admin reads this code out to the student, so it's shown large
// and copyable rather than crammed into a one-line toast.
function showResetResultModal(name, tempPassword) {
  document.getElementById('reset-result-name').textContent = `ของ "${name}"`;
  document.getElementById('reset-result-password').textContent = tempPassword;
  document.getElementById('reset-result-copy-label').textContent = 'คัดลอกรหัสผ่าน';
  document.getElementById('reset-result-modal').classList.remove('hidden');
}

function hideResetResultModal() {
  document.getElementById('reset-result-modal').classList.add('hidden');
}

// --- Edit member ----------------------------------------------------------

function setMemberEditError(message) {
  const el = document.getElementById('member-edit-error');
  if (!message) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = message;
  el.classList.remove('hidden');
}

// ปวช. runs to year 3, ปวส. to year 2, so the year list has to follow the
// level rather than being fixed — the API rejects the mismatch either way.
function refreshEditYearOptions(keep) {
  const level = document.getElementById('member-edit-level').value;
  const yearSelect = document.getElementById('member-edit-year-level');
  populateMembersSelect(yearSelect, YEAR_OPTIONS[level] || ['1', '2', '3'], null);
  if (keep && Array.from(yearSelect.options).some((o) => o.value === keep)) {
    yearSelect.value = keep;
  }
}

function openMemberEdit(row) {
  if (!row) return;
  editingMember = row;
  setMemberEditError('');

  document.getElementById('member-edit-subject').textContent = `${row.prefix || ''}${row.first_name} ${row.last_name}`;
  document.getElementById('member-edit-student-id').textContent = row.student_id;
  document.getElementById('member-edit-username').textContent = row.username;

  populateMembersSelect(document.getElementById('member-edit-prefix'), PREFIXES, null);
  populateMembersSelect(document.getElementById('member-edit-department'), DEPARTMENTS, null);

  document.getElementById('member-edit-prefix').value = row.prefix || PREFIXES[0];
  // gender is nullable — roster-imported students have never stated one, and
  // the API requires a value on save, so the form opens on a real option and
  // the admin confirms it rather than silently submitting an empty string.
  document.getElementById('member-edit-gender').value = row.gender === 'female' ? 'female' : 'male';
  document.getElementById('member-edit-first-name').value = row.first_name || '';
  document.getElementById('member-edit-last-name').value = row.last_name || '';
  document.getElementById('member-edit-department').value = row.department || DEPARTMENTS[0];
  document.getElementById('member-edit-level').value = row.level === 'ปวส.' ? 'ปวส.' : 'ปวช.';
  refreshEditYearOptions(row.year_level);
  document.getElementById('member-edit-room').value = row.room || '';
  document.getElementById('member-edit-status').value = row.account_status || 'approved';

  const isSelf = Number(row.user_id) === Number(currentUserId);
  const roleBtn = document.getElementById('member-edit-role-btn');
  const deleteBtn = document.getElementById('member-edit-delete-btn');
  document.getElementById('member-edit-role-label').textContent =
    row.role === 'admin' ? 'สิทธิ์ปัจจุบัน: แอดมิน' : 'สิทธิ์ปัจจุบัน: นักศึกษา';
  roleBtn.textContent = row.role === 'admin' ? 'ลดเป็นนักศึกษา' : 'ตั้งเป็นแอดมิน';
  // Styling for the disabled state is a plain rule in styles.css keyed off
  // :disabled — the opacity-50 utility isn't in the prebuilt bundle.
  roleBtn.disabled = isSelf;
  deleteBtn.disabled = isSelf;
  document.getElementById('member-edit-status').disabled = isSelf;
  document.getElementById('member-edit-self-note').classList.toggle('hidden', !isSelf);

  document.getElementById('member-edit-modal').classList.remove('hidden');
}

function closeMemberEdit() {
  document.getElementById('member-edit-modal').classList.add('hidden');
  editingMember = null;
}

async function saveMemberEdit() {
  if (!editingMember) return;
  const saveBtn = document.getElementById('member-edit-save');
  saveBtn.disabled = true;
  setMemberEditError('');
  try {
    await apiFetch('/admin/members/update', {
      method: 'POST',
      body: JSON.stringify({
        user_id: Number(editingMember.user_id),
        prefix: document.getElementById('member-edit-prefix').value,
        gender: document.getElementById('member-edit-gender').value,
        first_name: document.getElementById('member-edit-first-name').value,
        last_name: document.getElementById('member-edit-last-name').value,
        department: document.getElementById('member-edit-department').value,
        level: document.getElementById('member-edit-level').value,
        year_level: document.getElementById('member-edit-year-level').value,
        room: document.getElementById('member-edit-room').value,
        account_status: document.getElementById('member-edit-status').value,
      }),
    });
    closeMemberEdit();
    showToast('บันทึกข้อมูลแล้ว', { type: 'success' });
    loadMembers();
  } catch (err) {
    // Inline, not a toast: the message names the field that was rejected, and
    // it should sit next to the fields rather than in the corner of the screen.
    setMemberEditError(err.message || 'บันทึกไม่สำเร็จ');
  } finally {
    saveBtn.disabled = false;
  }
}

async function toggleMemberRole() {
  if (!editingMember) return;
  const promoting = editingMember.role !== 'admin';
  const name = `${editingMember.prefix || ''}${editingMember.first_name} ${editingMember.last_name}`;
  const ok = await showConfirmModal(
    promoting
      ? 'บัญชีนี้จะเห็นข้อมูลนักศึกษาทุกคน แก้ไขบัญชีอื่น และออกรายงานได้ทั้งหมด'
      : 'บัญชีนี้จะเข้าหน้าแอดมินไม่ได้อีก และเหลือสิทธิ์เท่านักศึกษาทั่วไป',
    {
      title: promoting ? 'ยืนยันตั้งเป็นแอดมิน' : 'ยืนยันลดเป็นนักศึกษา',
      subject: name,
      confirmLabel: promoting ? 'ตั้งเป็นแอดมิน' : 'ลดเป็นนักศึกษา',
      danger: true,
      icon: 'admin_panel_settings',
    }
  );
  if (!ok) return;
  try {
    const data = await apiFetch('/admin/members/role', {
      method: 'POST',
      body: JSON.stringify({ user_id: Number(editingMember.user_id), role: promoting ? 'admin' : 'student' }),
    });
    closeMemberEdit();
    showToast(data.message, { type: 'success' });
    loadMembers();
  } catch (err) {
    setMemberEditError(err.message || 'เปลี่ยนสิทธิ์ไม่สำเร็จ');
  }
}

async function deleteMember() {
  if (!editingMember) return;
  const name = `${editingMember.prefix || ''}${editingMember.first_name} ${editingMember.last_name}`;
  const ok = await showConfirmModal(
    'ประวัติการเข้าใช้ห้องสมุดทั้งหมดของบัญชีนี้จะถูกลบไปด้วย และรายงานย้อนหลังจะนับจำนวนใหม่โดยไม่มีคนนี้ การลบนี้กู้คืนไม่ได้',
    {
      title: 'ยืนยันลบบัญชี',
      subject: name,
      confirmLabel: 'ลบบัญชีถาวร',
      danger: true,
      icon: 'delete_forever',
    }
  );
  if (!ok) return;
  try {
    const data = await apiFetch('/admin/members/delete', {
      method: 'POST',
      body: JSON.stringify({ user_id: Number(editingMember.user_id) }),
    });
    closeMemberEdit();
    showToast(`ลบบัญชีแล้ว (ลบประวัติการเข้าใช้ ${Number(data.deleted_logs).toLocaleString()} รายการ)`, { type: 'success' });
    loadMembers();
  } catch (err) {
    setMemberEditError(err.message || 'ลบบัญชีไม่สำเร็จ');
  }
}

async function loadMembers() {
  const params = new URLSearchParams();
  const search = document.getElementById('members-search').value.trim();
  const department = document.getElementById('members-department').value.trim();
  const level = document.getElementById('members-level').value;
  const yearLevel = document.getElementById('members-year-level').value;
  const status = document.getElementById('members-status').value;
  if (search) params.set('search', search);
  if (department) params.set('department', department);
  if (level) params.set('level', level);
  if (yearLevel) params.set('year_level', yearLevel);
  if (status) params.set('status', status);

  setMembersRows(null);
  const data = await apiFetch(`/admin/members?${params.toString()}`);
  setMembersRows(data);
}

document.addEventListener('DOMContentLoaded', () => {
  populateMembersSelect(document.getElementById('members-department'), DEPARTMENTS, 'ทั้งหมด');

  // Needed before the first row is drawn so the admin's own row can explain
  // itself; a failure here only costs the explanation, not the guard.
  apiFetch('/me')
    .then((me) => { currentUserId = me && me.user_id; })
    .catch(() => {});

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
  document.getElementById('members-status').addEventListener('change', loadMembers);

  // Reset-result modal controls.
  const resetModal = document.getElementById('reset-result-modal');
  document.getElementById('reset-result-close').addEventListener('click', hideResetResultModal);
  resetModal.addEventListener('click', (e) => {
    if (e.target === resetModal) hideResetResultModal();
  });
  document.getElementById('reset-result-copy').addEventListener('click', async () => {
    const pwd = document.getElementById('reset-result-password').textContent;
    const label = document.getElementById('reset-result-copy-label');
    try {
      await navigator.clipboard.writeText(pwd);
      label.textContent = 'คัดลอกแล้ว!';
      setTimeout(() => { label.textContent = 'คัดลอกรหัสผ่าน'; }, 2000);
    } catch (_err) {
      // Clipboard API unavailable (non-HTTPS, old browser) — the password is
      // still visible and click-to-select via .select-all, so this is a
      // convenience, not the only way to get the code.
      showToast('คัดลอกอัตโนมัติไม่สำเร็จ กรุณาแตะที่รหัสเพื่อเลือกแล้วคัดลอกเอง', { type: 'info' });
    }
  });

  // Edit-member modal controls.
  const editModal = document.getElementById('member-edit-modal');
  editModal.addEventListener('click', (e) => {
    if (e.target === editModal) closeMemberEdit();
  });
  document.getElementById('member-edit-close').addEventListener('click', closeMemberEdit);
  document.getElementById('member-edit-cancel').addEventListener('click', closeMemberEdit);
  document.getElementById('member-edit-save').addEventListener('click', saveMemberEdit);
  document.getElementById('member-edit-role-btn').addEventListener('click', toggleMemberRole);
  document.getElementById('member-edit-delete-btn').addEventListener('click', deleteMember);
  document.getElementById('member-edit-level').addEventListener('change', () => refreshEditYearOptions(null));

  loadMembers();
});
