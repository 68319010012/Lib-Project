// Full check-in/check-out history modal — port of
// frontend-react/src/components/HistoryModal.jsx, extended with pagination
// (10 rows/page instead of a single 50-row fetch) since the list can grow
// large over a school year. Markup lives in partials/history-modal.php
// (hidden by default); this file wires open/close, paging, and the
// /me/history fetch + render. Reached from AppHeader's avatar menu.

const HISTORY_PAGE_SIZE = 10;
let historyPageOffset = 0;

function formatHistoryTimestamp(ts) {
  return new Date(ts).toLocaleString('th-TH', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

function renderHistoryRows(history) {
  const body = document.getElementById('history-modal-body');
  if (!body) return;
  if (history === null) {
    body.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary">กำลังโหลด…</p>';
    return;
  }
  if (history.length === 0) {
    body.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary">ยังไม่มีประวัติการเช็คอิน</p>';
    return;
  }
  body.innerHTML = history
    .map((row) => {
      const isIn = row.type === 'in';
      return `
        <div class="flex items-center justify-between p-4 rounded-lg bg-surface-container-low dark:bg-dm-bg border-l-4 ${isIn ? 'border-status-success' : 'border-warning'}">
          <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full flex items-center justify-center ${isIn ? 'bg-status-success/10 text-status-success' : 'bg-warning/10 text-warning'}">
              <span class="material-symbols-outlined">${isIn ? 'login' : 'logout'}</span>
            </div>
            <div>
              <p class="font-bold text-text-primary dark:text-inverse-on-surface">${isIn ? 'เช็คอิน' : 'เช็คเอาต์'}</p>
              <p class="text-xs text-text-secondary dark:text-dm-text-secondary">${formatHistoryTimestamp(row.timestamp)}</p>
            </div>
          </div>
        </div>
      `;
    })
    .join('');
}

function updateHistoryPagerState(pageRowCount, hasMore) {
  const pager = document.getElementById('history-modal-pager');
  const prevBtn = document.getElementById('history-modal-prev');
  const nextBtn = document.getElementById('history-modal-next');
  const label = document.getElementById('history-modal-page-label');
  if (!pager) return;
  // No pager at all on a single short page — nothing to page through.
  pager.classList.toggle('hidden', historyPageOffset === 0 && !hasMore);
  prevBtn.disabled = historyPageOffset === 0;
  nextBtn.disabled = !hasMore;
  const pageNum = Math.floor(historyPageOffset / HISTORY_PAGE_SIZE) + 1;
  label.textContent = pageRowCount > 0 ? `หน้า ${pageNum}` : '';
}

async function loadHistoryPage() {
  renderHistoryRows(null);
  try {
    // Fetch one extra row to know whether a next page exists, without
    // changing /me/history's response shape (still a plain array — other
    // callers like dashboard.js rely on that).
    const rows = await apiFetch(`/me/history?limit=${HISTORY_PAGE_SIZE + 1}&offset=${historyPageOffset}`);
    const hasMore = rows.length > HISTORY_PAGE_SIZE;
    const pageRows = hasMore ? rows.slice(0, HISTORY_PAGE_SIZE) : rows;
    renderHistoryRows(pageRows);
    updateHistoryPagerState(pageRows.length, hasMore);
  } catch (_err) {
    renderHistoryRows([]);
    updateHistoryPagerState(0, false);
  }
}

function openHistoryModal() {
  const modal = document.getElementById('history-modal');
  if (!modal) return;
  modal.classList.remove('hidden');
  historyPageOffset = 0;
  loadHistoryPage();
}

function closeHistoryModal() {
  const modal = document.getElementById('history-modal');
  if (modal) modal.classList.add('hidden');
}

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('history-modal');
  if (!modal) return;
  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeHistoryModal();
  });
  const closeBtn = document.getElementById('history-modal-close');
  if (closeBtn) closeBtn.addEventListener('click', closeHistoryModal);

  document.getElementById('history-modal-prev').addEventListener('click', () => {
    historyPageOffset = Math.max(0, historyPageOffset - HISTORY_PAGE_SIZE);
    loadHistoryPage();
  });
  document.getElementById('history-modal-next').addEventListener('click', () => {
    historyPageOffset += HISTORY_PAGE_SIZE;
    loadHistoryPage();
  });
});
