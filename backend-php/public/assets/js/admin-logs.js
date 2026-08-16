// Port of frontend-react/src/pages/AttendanceLogsPage.jsx.

let logsAllRows = null;
// Kept across the 20s silent poll so the admin isn't yanked back to page 1
// mid-read; the search/filter handlers reset it explicitly instead.
let logsPage = 1;

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

  tbody.innerHTML = pageState.rows
    .map((r) => {
      const isIn = r.type === 'in';
      const initials = `${(r.first_name || '?')[0]}${(r.last_name || '?')[0]}`.toUpperCase();
      const formatted = new Date(r.timestamp)
        .toLocaleString('th-TH', { year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit', second: '2-digit' })
        .replace(',', '');
      return `
        <tr class="hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
          <td class="px-6 py-4 font-label-code text-label-code text-text-primary dark:text-inverse-on-surface whitespace-nowrap">${formatted}</td>
          <td class="px-6 py-4">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center font-bold text-xs">${initials}</div>
              <span class="font-body-md text-body-md font-bold dark:text-inverse-on-surface">${r.prefix || ''}${r.first_name} ${r.last_name}</span>
            </div>
          </td>
          <td class="px-6 py-4 font-label-code text-label-code text-text-secondary dark:text-dm-text-secondary">${r.student_id}</td>
          <td class="px-6 py-4 font-body-md text-body-md dark:text-inverse-on-surface">${r.department || '-'}</td>
          <td class="px-6 py-4">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ${isIn ? 'bg-status-success/10 text-status-success' : 'bg-warning/10 text-warning'}">
              <span class="w-1.5 h-1.5 rounded-full ${isIn ? 'bg-status-success' : 'bg-warning'}"></span>
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
  logsAllRows = await apiFetch(`/admin/reports?${params.toString()}`);
  renderLogs();
}

document.addEventListener('DOMContentLoaded', () => {
  const params = new URLSearchParams({ month: new Date().toISOString().slice(0, 7) });
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
