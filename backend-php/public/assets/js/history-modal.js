// Full check-in/check-out history — markup in partials/history-modal.php,
// reached from AppHeader's avatar menu and the dashboard's "ดูทั้งหมด".
//
// Reads /me/visits, not /me/history: the raw log is one row per event, so a
// single trip to the library arrived as two separate cards and the reader had
// to pair them up by eye. The server pairs them (handle_my_visits) and this
// renders one entry per visit.
//
// Two things drive the shape of this file:
//
//   Finding a specific day. Paging ten visits at a time is fine for "what did
//   I do last week" and useless for "the Tuesday in June" — by then the list
//   is hundreds of visits long. So the range is a filter sent to the server
//   (from/to), not something applied to a page that has already been fetched,
//   and the pager reports real totals because the server counts the range.
//
//   Reading a row at a glance. Three equal columns of text made every visit
//   look the same. A visit is really a date, a span, and how long it lasted,
//   so it is drawn that way: the day as one block, the two times on a track
//   between them, the duration on the end — with the month as a heading over
//   each run of rows, so scrolling has landmarks instead of being 40 lines of
//   identical stripes.

const HISTORY_PAGE_SIZE = 10;

let historyPage = 1;
let historyRange = { from: '', to: '' };
// 'all' | '7' | '30' | 'month' | 'custom' -- whatever the select is showing.
let historyPreset = 'all';
// Every fetch takes a ticket. Changing the range fires a request and typing
// into the other date field fires another straight after; without this the
// slower of the two can land last and repaint the list with the range the
// student already moved on from.
let historyRequestId = 0;
// Where focus was when the dialog opened, so closing it puts focus back on
// the control that opened it instead of dropping it at the top of the page.
let historyLastFocus = null;

// Local date, not toISOString(): the college runs at UTC+7, and the ISO form
// is UTC, so any check-in before 07:00 would be filed under the previous day
// — which is exactly the kind of off-by-one that makes a date filter look
// broken.
function toDateInputValue(date) {
  const pad = (n) => String(n).padStart(2, '0');
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function formatVisitTime(ts) {
  return new Date(ts).toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

// ระยะเวลาแบบที่คนพูดออกเสียงจริง
//
// รับเป็นวินาที เพราะการเข้าใช้สั้นๆ มีอยู่จริงและเยอะ เดิมทุกครั้งที่ไม่ถึง
// หนึ่งนาทีจะแสดงว่า "ไม่ถึง 1 นาที" เหมือนกันหมด ซึ่งบอกไม่ได้ว่า 3 วินาที
// หรือ 55 วินาที — ต่ำกว่าหนึ่งนาทีจึงบอกเป็นวินาทีไปเลย
function formatDuration(seconds) {
  if (seconds === null || seconds === undefined) return '';
  const total = Math.max(0, Math.round(seconds));
  if (total < 60) return `${total} วินาที`;
  const minutes = Math.floor(total / 60);
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  if (hours === 0) return `${minutes} นาที`;
  if (rest === 0) return `${hours} ชม.`;
  return `${hours} ชม. ${rest} นาที`;
}

// null for a visit with no check-out yet — the caller shows "ยังอยู่", never a
// number measured against the clock right now, which would grow on reload.
function visitSeconds(visit) {
  if (!visit.checkout_at) return null;
  const spanMs = new Date(visit.checkout_at) - new Date(visit.checkin_at);
  return Math.max(0, Math.round(spanMs / 1000));
}

// Who wrote the check-out. A student who never tapped "เช็คเอาต์" should be
// able to see why a time is sitting there anyway, rather than reading it as
// something they did and forgot.
const CHECKOUT_SOURCE_NOTE = {
  auto: 'ระบบบันทึกให้',
  admin_forced: 'เจ้าหน้าที่บันทึกให้',
};

// '' / '' means no filter at all, which is what the "ทั้งหมด" chip sends.
function presetRange(preset) {
  if (preset === 'all') return { from: '', to: '' };
  const today = new Date();
  if (preset === 'month') {
    return {
      from: toDateInputValue(new Date(today.getFullYear(), today.getMonth(), 1)),
      to: toDateInputValue(today),
    };
  }
  const start = new Date(today);
  // "7 วันล่าสุด" counts today as one of the seven, so it goes back six.
  start.setDate(start.getDate() - (Number(preset) - 1));
  return { from: toDateInputValue(start), to: toDateInputValue(today) };
}

// The date fields only exist for "กำหนดเอง". Every other option already says
// what the range is, so showing two empty pickers next to it was three stacked
// rows of controls above a list that had not started yet -- most of a phone
// screen spent on the filter rather than on the history.
function syncRangeControls() {
  const select = document.getElementById('history-range-preset');
  const custom = document.getElementById('history-custom-range');
  if (!select || !custom) return;
  select.value = historyPreset;
  custom.classList.toggle('hidden', historyPreset !== 'custom');
}

// One <li> per visit: the day block, the in/out track, the duration.
function visitRowHtml(visit) {
  const checkinDate = new Date(visit.checkin_at);
  const dayNum = checkinDate.toLocaleDateString('th-TH', { day: 'numeric' });
  const dow = checkinDate.toLocaleDateString('th-TH', { weekday: 'short' });
  const seconds = visitSeconds(visit);
  const note = CHECKOUT_SOURCE_NOTE[visit.checkout_source];

  const outStop = visit.checkout_at
    ? `<span class="visit-stop-time">${escapeHtml(formatVisitTime(visit.checkout_at))} น.</span>
       ${note ? `<span class="visit-note">${escapeHtml(note)}</span>` : ''}`
    : '<span class="visit-stop-time is-open">ยังอยู่ในห้องสมุด</span>';

  return `
    <li class="visit-row${visit.checkout_at ? '' : ' is-open'}">
      <span class="visit-day">
        <span class="visit-day-num">${escapeHtml(dayNum)}</span>
        <span class="visit-day-dow">${escapeHtml(dow)}</span>
      </span>
      <span class="visit-track">
        <span class="visit-stop in">
          <span class="visit-stop-label">เข้า</span>
          <span class="visit-stop-time">${escapeHtml(formatVisitTime(visit.checkin_at))} น.</span>
        </span>
        <span class="visit-line" aria-hidden="true"></span>
        <span class="visit-stop out">
          <span class="visit-stop-label">ออก</span>
          ${outStop}
        </span>
      </span>
      <span class="visit-dur">
        <span class="visit-dur-label">รวม</span>
        <span class="visit-dur-value">${seconds === null ? 'กำลังนับ' : escapeHtml(formatDuration(seconds))}</span>
      </span>
    </li>
  `;
}

// Rows arrive newest-first and already sorted, so a run of the same month is
// always contiguous — grouping is just "start a new heading when the month
// changes", with no need to bucket and re-sort.
function renderVisitList(visits) {
  const body = document.getElementById('history-modal-body');
  if (!body) return;

  if (visits === null) {
    body.innerHTML = '<p class="visit-empty">กำลังโหลด…</p>';
    return;
  }
  if (visits.length === 0) {
    const filtered = historyRange.from || historyRange.to;
    body.innerHTML = filtered
      ? `<div class="visit-empty-state">
           <span class="material-symbols-outlined" aria-hidden="true">event_busy</span>
           <p class="visit-empty-title">ไม่มีการเข้าใช้ในช่วงวันที่นี้</p>
           <p class="visit-empty">ลองขยายช่วงวันที่ หรือกด "ทั้งหมด" เพื่อดูทุกครั้ง</p>
         </div>`
      : `<div class="visit-empty-state">
           <span class="material-symbols-outlined" aria-hidden="true">history</span>
           <p class="visit-empty-title">ยังไม่มีประวัติการเข้าใช้</p>
           <p class="visit-empty">เมื่อเช็คอินครั้งแรก รายการจะมาแสดงที่นี่</p>
         </div>`;
    return;
  }

  const parts = [];
  let openGroup = '';
  visits.forEach((visit) => {
    const date = new Date(visit.checkin_at);
    const groupKey = `${date.getFullYear()}-${date.getMonth()}`;
    if (groupKey !== openGroup) {
      if (openGroup) parts.push('</ul></section>');
      const label = date.toLocaleDateString('th-TH', { month: 'long', year: 'numeric' });
      parts.push(
        `<section>
           <h4 class="visit-group-head">${escapeHtml(label)}</h4>
           <ul class="visit-rows">`
      );
      openGroup = groupKey;
    }
    parts.push(visitRowHtml(visit));
  });
  if (openGroup) parts.push('</ul></section>');

  body.innerHTML = parts.join('');
  // A new page starts at its own top, otherwise page 2 opens halfway down.
  body.scrollTop = 0;
}

function renderHistorySummary(summary) {
  const visits = summary ? summary.visits : 0;
  document.getElementById('history-stat-visits').textContent = `${(visits || 0).toLocaleString('th-TH')} ครั้ง`;
  // An em dash, not "0 นาที", when nothing in the range has finished yet —
  // there is no total to report, which is different from a total of zero.
  document.getElementById('history-stat-total').textContent =
    summary && summary.closed_visits > 0 ? formatDuration(summary.total_seconds) : '—';
  document.getElementById('history-stat-avg').textContent =
    summary && summary.closed_visits > 0 ? formatDuration(summary.avg_seconds) : '—';
}

// Clamp the pickers to the days the student actually has rows in, so there is
// no way to select a month from before they enrolled and land on an empty
// list with nothing explaining why.
function applyHistoryBounds(bounds) {
  if (!bounds) return;
  const fromInput = document.getElementById('history-from');
  const toInput = document.getElementById('history-to');
  [fromInput, toInput].forEach((input) => {
    if (!input) return;
    if (bounds.first) input.min = bounds.first;
    // Today, not bounds.last: a student checking in right now should be able
    // to pick today even though the newest row predates this visit.
    input.max = toDateInputValue(new Date());
  });
}

function renderHistoryPager(total, pageVisits) {
  const foot = document.getElementById('history-modal-pager');
  const container = document.getElementById('history-pager');
  if (!foot || !container) return;
  // renderPager() already hides its controls for a single page but still
  // prints the range text, which is worth keeping; only an empty result
  // leaves nothing to say, and then the whole strip goes.
  foot.classList.toggle('hidden', total === 0);
  renderPager(
    container,
    {
      page: historyPage,
      totalPages: Math.max(1, Math.ceil(total / HISTORY_PAGE_SIZE)),
      total,
      start: (historyPage - 1) * HISTORY_PAGE_SIZE,
      rows: pageVisits,
    },
    (page) => {
      historyPage = page;
      loadHistoryPage();
    }
  );
}

function setHistoryBusy(busy) {
  const body = document.getElementById('history-modal-body');
  if (body) body.setAttribute('aria-busy', busy ? 'true' : 'false');
}

async function loadHistoryPage() {
  const ticket = ++historyRequestId;
  renderVisitList(null);
  setHistoryBusy(true);

  const params = new URLSearchParams({
    limit: String(HISTORY_PAGE_SIZE),
    offset: String((historyPage - 1) * HISTORY_PAGE_SIZE),
  });
  if (historyRange.from) params.set('from', historyRange.from);
  if (historyRange.to) params.set('to', historyRange.to);

  try {
    const data = await apiFetch(`/me/visits?${params.toString()}`);
    if (ticket !== historyRequestId) return; // a newer range already won

    // Narrowing the range while sitting on page 6 can leave the offset past
    // the end of the new result, which comes back as an empty page that looks
    // like "no visits" rather than "you are past the end". Land on the last
    // real page instead. One retry only: the clamp always lands in range.
    const totalPages = Math.max(1, Math.ceil(data.total / HISTORY_PAGE_SIZE));
    if (historyPage > totalPages) {
      historyPage = totalPages;
      loadHistoryPage();
      return;
    }

    applyHistoryBounds(data.bounds);
    renderHistorySummary(data.summary);
    renderVisitList(data.visits);
    renderHistoryPager(data.total, data.visits);
  } catch (_err) {
    if (ticket !== historyRequestId) return;
    const body = document.getElementById('history-modal-body');
    if (body) {
      body.innerHTML = `<div class="visit-empty-state">
          <span class="material-symbols-outlined" aria-hidden="true">cloud_off</span>
          <p class="visit-empty-title">โหลดประวัติไม่สำเร็จ</p>
          <p class="visit-empty">ตรวจสอบการเชื่อมต่อแล้วลองใหม่อีกครั้ง</p>
        </div>`;
    }
    renderHistorySummary(null);
    renderHistoryPager(0, []);
  } finally {
    if (ticket === historyRequestId) setHistoryBusy(false);
  }
}

// Any change of range restarts at page 1 — staying on page 6 of a range that
// no longer has six pages is never what was meant.
function applyHistoryRange(preset, range) {
  historyPreset = preset;
  historyRange = { from: range.from || '', to: range.to || '' };
  document.getElementById('history-from').value = historyRange.from;
  document.getElementById('history-to').value = historyRange.to;
  syncRangeControls();
  historyPage = 1;
  loadHistoryPage();
}

function openHistoryModal() {
  const modal = document.getElementById('history-modal');
  if (!modal) return;
  historyLastFocus = document.activeElement;
  modal.classList.remove('hidden');
  // Opens unfiltered every time. A range left over from the last time the
  // dialog was open is invisible until you look at the control, and "my
  // history is empty" is the wrong first impression to hand someone.
  applyHistoryRange('all', presetRange('all'));
  const closeBtn = document.getElementById('history-modal-close');
  if (closeBtn) closeBtn.focus();
}

function closeHistoryModal() {
  const modal = document.getElementById('history-modal');
  if (!modal || modal.classList.contains('hidden')) return;
  modal.classList.add('hidden');
  // Drop any answer still in flight, so it cannot repaint a closed dialog.
  historyRequestId += 1;
  if (historyLastFocus && typeof historyLastFocus.focus === 'function') {
    historyLastFocus.focus();
  }
  historyLastFocus = null;
}

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('history-modal');
  if (!modal) return;

  modal.addEventListener('click', (e) => {
    if (e.target === modal) closeHistoryModal();
  });
  document.getElementById('history-modal-close').addEventListener('click', closeHistoryModal);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) closeHistoryModal();
  });

  // Choosing "กำหนดเอง" only opens the pickers -- it does not filter yet,
  // because there is no range to filter by until a date is actually picked.
  // Leaving the list alone at that moment is also what lets someone back out
  // by re-selecting another option without having lost their place.
  document.getElementById('history-range-preset').addEventListener('change', (e) => {
    const preset = e.target.value;
    if (preset === 'custom') {
      historyPreset = 'custom';
      syncRangeControls();
      document.getElementById('history-from').focus();
      return;
    }
    applyHistoryRange(preset, presetRange(preset));
  });

  // 'change' rather than 'input': a date field reports every partial value as
  // it is typed, and firing a request at "0002-01-01" on the way to 2026 is
  // three wasted round trips and a list that flickers through nonsense.
  ['history-from', 'history-to'].forEach((id) => {
    document.getElementById(id).addEventListener('change', () => {
      applyHistoryRange('custom', {
        from: document.getElementById('history-from').value,
        to: document.getElementById('history-to').value,
      });
    });
  });
});
