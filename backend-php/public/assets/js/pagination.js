// Shared client-side pagination for the admin tables.
//
// Each admin page's loader already fetches its rows in full, so this only
// limits how many end up in the DOM at once. That's the part that actually
// hurt: admin-members renders 1,000+ <tr> and admin-logs 14,000+, which is
// slow to lay out and impossible to read. The JSON payload is unchanged —
// moving to real server-side LIMIT/OFFSET would need API changes on top.

// 10 ไม่ใช่ 15: หน้าจอโทรศัพท์เห็นได้ประมาณนี้พอดีโดยไม่ต้องเลื่อนยาว
// และตรงกับ HISTORY_PAGE_SIZE ในโมดัลประวัติ ทำให้ทุกตารางในระบบนับหน้าเท่ากัน
const PAGE_SIZE = 10;

// Clamps `page` into range and returns the slice plus everything the pager
// needs to describe itself. Clamping (rather than trusting the caller) is
// what keeps the current page valid when rows disappear underneath it —
// e.g. admin-active force-checkouts the last person on the last page.
function paginateRows(rows, page, pageSize = PAGE_SIZE) {
  const total = rows.length;
  const totalPages = Math.max(1, Math.ceil(total / pageSize));
  const current = Math.min(Math.max(1, page), totalPages);
  const start = (current - 1) * pageSize;
  return { rows: rows.slice(start, start + pageSize), page: current, totalPages, total, start };
}

// First, last, and current ±1 — so a 1,400-page log doesn't render 1,400
// buttons. Returns page numbers with the string 'gap' where pages were cut.
function pageWindow(page, totalPages) {
  const wanted = [1, totalPages, page - 1, page, page + 1];
  const list = [...new Set(wanted)].filter((p) => p >= 1 && p <= totalPages).sort((a, b) => a - b);
  const out = [];
  let prev = 0;
  list.forEach((p) => {
    if (prev && p - prev > 1) out.push('gap');
    out.push(p);
    prev = p;
  });
  return out;
}

// `onPage` receives the requested page number; the caller re-renders.
function renderPager(container, state, onPage) {
  if (!container) return;
  const { page, totalPages, total, start, rows } = state;

  // One page of results needs no controls, but the range text is still
  // useful context, so only a genuinely empty table clears the whole strip.
  if (total === 0) {
    container.innerHTML = '';
    return;
  }

  const from = start + 1;
  const to = start + rows.length;
  const parts = [
    `<span class="pager-info">แสดง ${from.toLocaleString()}–${to.toLocaleString()} จาก ${total.toLocaleString()} รายการ</span>`,
  ];

  if (totalPages > 1) {
    parts.push(
      `<button type="button" class="pager-btn" data-page="${page - 1}"${page === 1 ? ' disabled' : ''}>ก่อนหน้า</button>`
    );
    pageWindow(page, totalPages).forEach((p) => {
      if (p === 'gap') {
        parts.push('<span class="pager-gap">…</span>');
        return;
      }
      parts.push(
        `<button type="button" class="pager-btn${p === page ? ' is-current' : ''}" data-page="${p}"${p === page ? ' aria-current="page"' : ''}>${p}</button>`
      );
    });
    parts.push(
      `<button type="button" class="pager-btn" data-page="${page + 1}"${page === totalPages ? ' disabled' : ''}>ถัดไป</button>`
    );
  }

  container.innerHTML = parts.join('');
  container.querySelectorAll('.pager-btn[data-page]').forEach((btn) => {
    if (btn.disabled) return;
    btn.addEventListener('click', () => onPage(Number(btn.dataset.page)));
  });
}
