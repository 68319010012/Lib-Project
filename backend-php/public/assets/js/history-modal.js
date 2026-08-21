// Full check-in/check-out history modal — port of
// frontend-react/src/components/HistoryModal.jsx, extended with pagination
// (10 rows/page instead of a single 50-row fetch) since the list can grow
// large over a school year. Markup lives in partials/history-modal.php
// (hidden by default); this file wires open/close, paging, and the fetch +
// render. Reached from AppHeader's avatar menu.
//
// Reads /me/visits, not /me/history: the raw log is one row per event, so a
// single trip to the library arrived as two separate cards and the reader had
// to pair them up by eye. The server pairs them (handle_my_visits) and this
// renders one table row per visit — date, time in, time out.

const HISTORY_PAGE_SIZE = 10;
let historyPageOffset = 0;

function formatVisitDate(ts) {
  const d = new Date(ts);
  return {
    date: d.toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: '2-digit' }),
    dow: d.toLocaleDateString('th-TH', { weekday: 'short' }),
  };
}

function formatVisitTime(ts) {
  return new Date(ts).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

// Who wrote the check-out. A student who never tapped "เช็คเอาต์" should be
// able to see why a time is sitting there anyway, rather than reading it as
// something they did and forgot.
const CHECKOUT_SOURCE_NOTE = {
  auto: 'ระบบบันทึกให้',
  admin_forced: 'เจ้าหน้าที่บันทึกให้',
};

function renderVisitRows(visits) {
  const body = document.getElementById('history-modal-body');
  if (!body) return;
  if (visits === null) {
    body.innerHTML = '<p class="visit-empty">กำลังโหลด…</p>';
    return;
  }
  if (visits.length === 0) {
    body.innerHTML = '<p class="visit-empty">ยังไม่มีประวัติการเข้าใช้</p>';
    return;
  }

  const rows = visits
    .map((visit) => {
      const { date, dow } = formatVisitDate(visit.checkin_at);
      const note = CHECKOUT_SOURCE_NOTE[visit.checkout_source];
      const checkoutCell = visit.checkout_at
        ? `<span class="visit-cell out">
             <span class="visit-time">${escapeHtml(formatVisitTime(visit.checkout_at))} น.</span>
             ${note ? `<span class="visit-note">${escapeHtml(note)}</span>` : ''}
           </span>`
        : `<span class="visit-cell out open">
             <span class="visit-time">ยังอยู่ในห้องสมุด</span>
           </span>`;
      return `
        <tr>
          <td>
            <span class="visit-cell">
              <span class="visit-date">${escapeHtml(date)}</span>
              <span class="visit-dow">${escapeHtml(dow)}</span>
            </span>
          </td>
          <td>
            <span class="visit-cell in">
              <span class="visit-time">${escapeHtml(formatVisitTime(visit.checkin_at))} น.</span>
            </span>
          </td>
          <td>${checkoutCell}</td>
        </tr>
      `;
    })
    .join('');

  body.innerHTML = `
    <table class="visit-table">
      <thead>
        <tr><th>วันที่</th><th>เช็คอิน</th><th>เช็คเอาต์</th></tr>
      </thead>
      <tbody>${rows}</tbody>
    </table>
  `;
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
  renderVisitRows(null);
  try {
    // One extra row to learn whether a next page exists, without the endpoint
    // needing to count the whole table.
    const visits = await apiFetch(`/me/visits?limit=${HISTORY_PAGE_SIZE + 1}&offset=${historyPageOffset}`);
    const hasMore = visits.length > HISTORY_PAGE_SIZE;
    const pageVisits = hasMore ? visits.slice(0, HISTORY_PAGE_SIZE) : visits;
    renderVisitRows(pageVisits);
    updateHistoryPagerState(pageVisits.length, hasMore);
  } catch (_err) {
    renderVisitRows([]);
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
