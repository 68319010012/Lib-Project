// "กำลังใช้งานอยู่" — who's currently checked in, how long they've been in,
// and a way for admin to force-checkout someone who forgot to (writes
// checkout_source='admin_forced', a schema value the app already reserved
// but never actually wrote anywhere before this page).

let activeRows = null;

function formatDurationThai(minutes) {
  if (minutes < 1) return 'เพิ่งเข้ามา';
  const h = Math.floor(minutes / 60);
  const m = minutes % 60;
  if (h === 0) return `${m} นาที`;
  return `${h} ชม. ${m} นาที`;
}

function minutesSince(isoTimestamp) {
  return Math.floor((Date.now() - new Date(isoTimestamp).getTime()) / 60000);
}

function renderActive() {
  const tbody = document.getElementById('active-tbody');
  const countEl = document.getElementById('active-count');
  const statCount = document.getElementById('active-stat-count');
  const statLongest = document.getElementById('active-stat-longest');

  if (activeRows === null) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">กำลังโหลด…</td></tr>';
    countEl.textContent = 'พบ 0 คน';
    statCount.textContent = '–';
    statLongest.textContent = '–';
    return;
  }

  statCount.textContent = activeRows.length.toLocaleString();
  countEl.textContent = `พบ ${activeRows.length.toLocaleString()} คน`;

  if (activeRows.length === 0) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">ไม่มีใครอยู่ในห้องสมุดตอนนี้</td></tr>';
    statLongest.textContent = '–';
    return;
  }

  const durations = activeRows.map((r) => minutesSince(r.checked_in_at));
  statLongest.textContent = formatDurationThai(Math.max(...durations));

  tbody.innerHTML = activeRows
    .map((r, i) => {
      const checkedInTime = new Date(r.checked_in_at).toLocaleString('th-TH', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
      return `
        <tr class="hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-bold dark:text-inverse-on-surface">${r.prefix || ''}${r.first_name} ${r.last_name}</td>
          <td class="px-6 py-4 font-label-code text-label-code text-text-secondary dark:text-dm-text-secondary">${r.student_id}</td>
          <td class="px-6 py-4 font-body-md text-body-md dark:text-inverse-on-surface">${r.department || '-'}</td>
          <td class="px-6 py-4 font-label-code text-label-code text-text-secondary dark:text-dm-text-secondary whitespace-nowrap">${checkedInTime}</td>
          <td class="px-6 py-4">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-status-success/10 text-status-success duration-cell" data-checked-in-at="${r.checked_in_at}">
              <span class="w-1.5 h-1.5 rounded-full bg-status-success"></span>
              ${formatDurationThai(durations[i])}
            </span>
          </td>
          <td class="px-6 py-4 text-right">
            <button type="button" class="force-checkout-btn text-xs font-bold text-error hover:underline" data-user-id="${r.user_id}" data-name="${(r.prefix || '') + r.first_name + ' ' + r.last_name}">
              บังคับเช็คเอาต์
            </button>
          </td>
        </tr>
      `;
    })
    .join('');

  tbody.querySelectorAll('.force-checkout-btn').forEach((btn) => {
    btn.addEventListener('click', () => forceCheckout(btn.dataset.userId, btn.dataset.name));
  });
}

// Recomputes the visible duration text every tick without re-fetching —
// the 20s poll below handles picking up people who arrived/left.
function tickDurations() {
  document.querySelectorAll('.duration-cell').forEach((el) => {
    const minutes = minutesSince(el.dataset.checkedInAt);
    el.lastChild.textContent = ` ${formatDurationThai(minutes)}`;
  });
}

async function loadActive() {
  activeRows = await apiFetch('/admin/active-now');
  renderActive();
}

async function forceCheckout(userId, name) {
  if (!window.confirm(`ยืนยันบังคับเช็คเอาต์ "${name}" ?\nใช้เมื่อผู้ใช้ลืมเช็คเอาต์เท่านั้น`)) {
    return;
  }
  try {
    await apiFetch('/admin/checkin/force-checkout', { method: 'POST', body: JSON.stringify({ user_id: Number(userId) }) });
    showToast('บันทึกเช็คเอาต์แล้ว', { type: 'success' });
    loadActive();
  } catch (err) {
    showToast(err.message || 'เกิดข้อผิดพลาด', { type: 'error' });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  loadActive();
  setInterval(loadActive, 20000);
  setInterval(tickDurations, 1000);
});
