// Port of frontend-react/src/pages/AttendanceLogsPage.jsx.

let logsAllRows = null;
// Kept across the 20s silent poll so the admin isn't yanked back to page 1
// mid-read; the search/filter handlers reset it explicitly instead.
let logsPage = 1;

// คีย์ระดับวันตามเวลาท้องถิ่น (ไม่ใช่ UTC) เพื่อไม่ให้รายการช่วงดึกตกไปอยู่วันถัดไป
function logsDayKey(ts) {
  const d = new Date(ts);
  return `${d.getFullYear()}-${d.getMonth() + 1}-${d.getDate()}`;
}

function computeLogsStats(rows) {
  const lastByUser = {};
  rows.forEach((r) => {
    const prev = lastByUser[r.student_id];
    if (!prev || new Date(r.timestamp) > new Date(prev.timestamp)) lastByUser[r.student_id] = r;
  });
  const currentlyInside = Object.values(lastByUser).filter((r) => r.type === 'in').length;

  const byUser = {};
  rows.forEach((r) => {
    (byUser[r.student_id] = byUser[r.student_id] || []).push(r);
  });
  const durations = [];
  Object.values(byUser).forEach((events) => {
    const sorted = [...events].sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp));
    let openIn = null;
    sorted.forEach((ev) => {
      if (ev.type === 'in') {
        openIn = ev.timestamp;
      } else if (ev.type === 'out' && openIn) {
        durations.push((new Date(ev.timestamp) - new Date(openIn)) / 60000);
        openIn = null;
      }
    });
  });
  const avgMinutes = durations.length ? Math.round(durations.reduce((a, b) => a + b, 0) / durations.length) : 0;

  return { total: rows.length, currentlyInside, avgMinutes, hasDurations: durations.length > 0 };
}

function getFilteredRows() {
  if (!logsAllRows) return [];
  const search = document.getElementById('logs-search').value.toLowerCase();
  const actionFilter = document.getElementById('logs-action-filter').value;
  return logsAllRows.filter((r) => {
    if (actionFilter && r.type !== actionFilter) return false;
    if (!search) return true;
    const haystack = `${r.first_name} ${r.last_name} ${r.student_id} ${r.department || ''}`.toLowerCase();
    return haystack.includes(search);
  });
}

function renderLogs() {
  const statsEl = {
    total: document.getElementById('logs-stat-total'),
    inside: document.getElementById('logs-stat-inside'),
    avg: document.getElementById('logs-stat-avg'),
  };
  if (!logsAllRows) {
    statsEl.total.textContent = '–';
    statsEl.inside.textContent = '–';
    statsEl.avg.textContent = '–';
  } else {
    const stats = computeLogsStats(logsAllRows);
    statsEl.total.textContent = stats.total.toLocaleString();
    statsEl.inside.textContent = stats.currentlyInside.toLocaleString();
    statsEl.avg.textContent = stats.hasDurations ? `${stats.avgMinutes} นาที` : '–';
  }

  const filtered = getFilteredRows();
  const tbody = document.getElementById('logs-tbody');
  const pagerEl = document.getElementById('logs-pager');
  document.getElementById('logs-count').textContent = `พบ ${filtered.length} รายการ`;

  if (logsAllRows === null) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="5">กำลังโหลด…</td></tr>';
    pagerEl.innerHTML = '';
    return;
  }
  if (filtered.length === 0) {
    tbody.innerHTML = '<tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="5">ไม่พบรายการที่ตรงกัน</td></tr>';
    pagerEl.innerHTML = '';
    return;
  }

  const pageState = paginateRows(filtered, logsPage);
  logsPage = pageState.page;

  // วันเดียวกันซ้ำอยู่ทุกแถวจนกวาดตาแล้วแยกไม่ออกว่ารายการไหนคือวันไหน —
  // ยกวันที่ขึ้นไปเป็นหัวข้อคั่น แล้วในแถวเหลือแค่เวลา
  const countByDay = {};
  filtered.forEach((r) => {
    const k = logsDayKey(r.timestamp);
    countByDay[k] = (countByDay[k] || 0) + 1;
  });

  let lastDay = null;
  tbody.innerHTML = pageState.rows
    .map((r) => {
      const isIn = r.type === 'in';
      const initials = `${(r.first_name || '?')[0]}${(r.last_name || '?')[0]}`.toUpperCase();
      const when = new Date(r.timestamp);
      const dayKey = logsDayKey(r.timestamp);
      let header = '';
      if (dayKey !== lastDay) {
        lastDay = dayKey;
        const dayLabel = when.toLocaleDateString('th-TH', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        header = `
        <tr class="logs-day-row">
          <td colspan="5">
            <span class="logs-day-label">${escapeHtml(dayLabel)}</span>
            <span class="logs-day-count">${countByDay[dayKey].toLocaleString()} รายการ</span>
          </td>
        </tr>
      `;
      }
      const time = when.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      return `${header}
        <tr class="logs-row ${isIn ? 'logs-row-in' : 'logs-row-out'} hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-label-code text-label-code text-text-primary dark:text-inverse-on-surface whitespace-nowrap">${time}</td>
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center font-bold text-xs">${escapeHtml(initials)}</div>
              <span class="font-body-md text-body-md font-bold dark:text-inverse-on-surface">${escapeHtml((r.prefix || '') + r.first_name + ' ' + r.last_name)}</span>
            </div>
          </td>
          <td class="px-6 py-4 font-label-code text-label-code text-text-secondary dark:text-dm-text-secondary">${escapeHtml(r.student_id)}</td>
          <td class="px-6 py-4 font-body-md text-body-md dark:text-inverse-on-surface">${escapeHtml(r.department || '-')}</td>
          <td class="px-6 py-4">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ${isIn ? 'bg-status-success/10 text-status-success' : 'bg-warning/10 text-warning'}">
              <span class="material-symbols-outlined logs-type-icon">${isIn ? 'login' : 'logout'}</span>
              ${isIn ? 'เช็คอิน' : 'เช็คเอาต์'}
            </span>
          </td>
        </tr>
      `;
    })
    .join('');

  renderPager(pagerEl, pageState, (p) => {
    logsPage = p;
    renderLogs();
  });
}

async function loadLogs(params, { silent = false } = {}) {
  if (!silent) {
    logsAllRows = null;
    logsPage = 1;
    renderLogs();
  }
  try {
    logsAllRows = await apiFetch(`/admin/reports?${params.toString()}`);
    renderLogs();
  } catch (err) {
    // Leaving logsAllRows null on failure previously stuck the table on
    // "กำลังโหลด…" forever with no sign anything went wrong — the 20s silent
    // poll still retries on its own, so just surface the failure instead.
    if (!silent) showToast(err.message || 'โหลดข้อมูลไม่สำเร็จ', { type: 'error' });
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const now = new Date();
  const localMonth = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  const params = new URLSearchParams({ month: localMonth });
  loadLogs(params);
  // Poll so newly-arrived check-ins/check-outs show up without a manual
  // reload — silent so the table doesn't flash back to "loading" each time.
  setInterval(() => loadLogs(params, { silent: true }), 20000);

  // Narrowing the result set invalidates the current page number — a search
  // that leaves 3 rows has no page 7 to stay on.
  function rerenderFromFirstPage() {
    logsPage = 1;
    renderLogs();
  }
  document.getElementById('logs-search').addEventListener('input', rerenderFromFirstPage);
  document.getElementById('logs-action-filter').addEventListener('change', rerenderFromFirstPage);
  // No apply button — picking a date re-queries immediately.
  document.getElementById('logs-date-filter').addEventListener('change', () => {
    const dateFilter = document.getElementById('logs-date-filter').value;
    if (!dateFilter) return;
    loadLogs(new URLSearchParams({ date: dateFilter }));
  });
  document.getElementById('logs-date-clear').addEventListener('click', () => {
    document.getElementById('logs-date-filter').value = '';
    loadLogs(new URLSearchParams({ month: new Date().toISOString().slice(0, 7) }));
  });
});
