// Port of frontend-react/src/pages/DashboardPage.jsx.

let historyRows = [];
let closingTime = '17:00';
// ห้องสมุดเปิดจันทร์-ศุกร์ (ตั้งค่าได้ที่ LIBRARY_OPEN_DAYS ฝั่งเซิร์ฟเวอร์)
// ค่าตั้งต้นเป็น "เปิด" เพื่อไม่ให้ปุ่มถูกปิดไว้ก่อนระหว่างรอ /library-info
// ตอบกลับ — ถ้าวันนี้ปิดจริง เซิร์ฟเวอร์ก็ยังปฏิเสธการเช็คอินอยู่ดี
let libraryOpenToday = true;
let closedMessage = 'วันนี้ห้องสมุดปิดทำการ';
const STAMP_HINT_OPEN = 'กดปุ่มด้านบนเพื่อบันทึกการเข้า-ออกห้องสมุด NTC';
// ต้องตรงกับ CHECKIN_COOLDOWN_SECONDS ใน src/handlers/checkin_handlers.php
// ฝั่งนี้แค่บอกให้เห็นล่วงหน้าว่ายังกดไม่ได้ ตัวที่บังคับจริงคือเซิร์ฟเวอร์
const STAMP_COOLDOWN_MS = 10000;
let cooldownUntil = 0;
let cooldownTimerId = null;
let historyLoaded = false;
let busy = false;
let elapsedTimerId = null;
let reminderWatcherId = null;
let historyRefreshId = null;
let reminderNotifiedKey = null;
let selectedHours = null;

// Slow background refresh, deliberately nowhere near the 1s elapsed tick: this
// only has to notice a state change that happened server-side, not animate one.
const HISTORY_REFRESH_MS = 60000;

function formatClock(totalSeconds) {
  const hrs = Math.floor(totalSeconds / 3600);
  const mins = Math.floor((totalSeconds % 3600) / 60);
  const secs = totalSeconds % 60;
  return [hrs, mins, secs].map((n) => n.toString().padStart(2, '0')).join(':');
}

function formatHM(date) {
  return date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' });
}

function closingDateFor(base, closing) {
  const [h, m] = closing.split(':').map(Number);
  const d = new Date(base);
  d.setHours(h, m, 0, 0);
  return d;
}

function toHHMM(date) {
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`;
}

// --- Scroll-wheel time picker --------------------------------------------
// Replaces the typed HH:MM field. Typing four digits to say "about an hour
// from now" is a lot of keyboard for an answer nobody holds to the minute.
// This is NOT the native <input type="time"> wheel — that one was the
// original complaint and is what the typed field existed to escape. It is an
// in-page list scrolled with the thumb already on the glass, with no OS
// overlay to summon or dismiss.
//
// WHEEL_ITEM_H must match .time-wheel-item's height in assets/css/styles.css:
// the selected value is derived from scrollTop, so if the two disagree the
// value read back is not the row sitting under the centre band.
const WHEEL_ITEM_H = 44;
const WHEEL_VISIBLE_H = 176;

function timeToMinutes(hhmm) {
  const [h, m] = hhmm.split(':').map(Number);
  return h * 60 + m;
}

function minutesToTime(total) {
  return `${String(Math.floor(total / 60)).padStart(2, '0')}:${String(total % 60).padStart(2, '0')}`;
}

function fillWheel(el, values) {
  el.innerHTML = values
    .map((v) => `<div class="time-wheel-item" data-v="${v}">${String(v).padStart(2, '0')}</div>`)
    .join('');
  // Half a wheel of blank space at each end, so the first and last values can
  // reach the centre band like any other row instead of being unselectable.
  const pad = (WHEEL_VISIBLE_H - WHEEL_ITEM_H) / 2;
  el.style.paddingTop = `${pad}px`;
  el.style.paddingBottom = `${pad}px`;
}

function wheelIndex(el) {
  const count = el.children.length;
  if (!count) return -1;
  return Math.min(count - 1, Math.max(0, Math.round(el.scrollTop / WHEEL_ITEM_H)));
}

function paintWheel(el) {
  const idx = wheelIndex(el);
  Array.from(el.children).forEach((item, i) => {
    item.classList.toggle('is-selected', i === idx);
    item.classList.toggle('is-near', Math.abs(i - idx) === 1);
  });
}

function wheelValue(el) {
  const idx = wheelIndex(el);
  return idx < 0 ? null : Number(el.children[idx].dataset.v);
}

function setWheelValue(el, value) {
  const idx = Array.from(el.children).findIndex((item) => Number(item.dataset.v) === value);
  if (idx < 0) return;
  // Instant rather than smooth: a smooth scroll fights scroll-snap and can
  // settle a row away from where it was sent.
  el.scrollTop = idx * WHEEL_ITEM_H;
  paintWheel(el);
}

let wheelBounds = null;

// Pulls the selection back inside the open window once the scroll settles, so
// a time past closing can't be chosen at all — rather than being chosen and
// then rejected by an error message on confirm.
function clampWheels() {
  if (!wheelBounds) return;
  const hourEl = document.getElementById('modal-wheel-hour');
  const minuteEl = document.getElementById('modal-wheel-minute');
  const h = wheelValue(hourEl);
  const m = wheelValue(minuteEl);
  if (h === null || m === null) return;
  const chosen = h * 60 + m;
  const clamped = Math.min(timeToMinutes(wheelBounds.max), Math.max(timeToMinutes(wheelBounds.min), chosen));
  if (clamped === chosen) return;
  setWheelValue(hourEl, Math.floor(clamped / 60));
  setWheelValue(minuteEl, clamped % 60);
}

function scheduleWheelSettle(el) {
  clearTimeout(el.settleTimer);
  el.settleTimer = setTimeout(clampWheels, 120);
}

function wireWheel(el) {
  if (el.dataset.wired) return;
  el.dataset.wired = '1';
  el.addEventListener('scroll', () => {
    paintWheel(el);
    setModalError('');
    scheduleWheelSettle(el);
  });
  // Arrow keys step a row at a time once a column has focus, so the picker is
  // reachable without a pointer.
  el.addEventListener('keydown', (e) => {
    const step = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
    if (!step) return;
    e.preventDefault();
    const next = Math.min(el.children.length - 1, Math.max(0, wheelIndex(el) + step));
    el.scrollTop = next * WHEEL_ITEM_H;
    paintWheel(el);
    scheduleWheelSettle(el);
  });
}

function buildTimeWheels(bounds) {
  wheelBounds = bounds;
  const hourEl = document.getElementById('modal-wheel-hour');
  const minuteEl = document.getElementById('modal-wheel-minute');

  const hours = [];
  for (let h = Number(bounds.min.slice(0, 2)); h <= Number(bounds.max.slice(0, 2)); h++) hours.push(h);
  const minutes = [];
  for (let m = 0; m < 60; m++) minutes.push(m);
  fillWheel(hourEl, hours);
  fillWheel(minuteEl, minutes);
  wireWheel(hourEl);
  wireWheel(minuteEl);

  // Opens on "an hour from now" — the answer most people are about to give —
  // capped at closing time.
  const start = Math.min(timeToMinutes(bounds.max), timeToMinutes(bounds.min) + 60);
  setWheelValue(hourEl, Math.floor(start / 60));
  setWheelValue(minuteEl, start % 60);
}

function readTimeWheels() {
  const h = wheelValue(document.getElementById('modal-wheel-hour'));
  const m = wheelValue(document.getElementById('modal-wheel-minute'));
  return h === null || m === null ? null : minutesToTime(h * 60 + m);
}

function timeInputBounds(closing) {
  const now = new Date();
  const closingDate = closingDateFor(now, closing);
  return { isOpen: closingDate > now, min: toHHMM(now), max: closing };
}

function buildHourButtons(closing) {
  const now = new Date();
  const closingDate = closingDateFor(now, closing);
  return [1, 2, 3, 4, 5, 6].map((h) => ({
    hours: h,
    exceeds: new Date(now.getTime() + h * 3600000) > closingDate,
  }));
}

function getLast() {
  return historyRows[0];
}

function isCheckedIn() {
  const last = getLast();
  return !!last && last.type === 'in';
}

function isSameDay(a, b) {
  return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
}

// จบการใช้งานของวันนี้ไปแล้ว — เช็คอินแล้วและเช็คเอาต์แล้วในวันเดียวกัน
// แยกจาก "ยังไม่ได้เช็คอินวันนี้" เพราะสองอย่างนี้ไม่เหมือนกัน และป้ายสีส้ม
// ที่บอกว่ายังไม่ได้เช็คอินก็ผิดสำหรับคนที่เพิ่งเช็คเอาต์ออกไป
function checkedOutToday() {
  const last = getLast();
  if (!last || last.type !== 'out') return null;
  const at = new Date(last.timestamp);
  return isSameDay(at, new Date()) ? at : null;
}

function getPlannedCheckoutAt() {
  const last = getLast();
  return isCheckedIn() && last.planned_checkout_at ? new Date(last.planned_checkout_at) : null;
}

async function loadHistory() {
  historyRows = await apiFetch('/me/history?limit=20');
  historyLoaded = true;
  render();
}

function isCheckinModalOpen() {
  return !document.getElementById('checkin-modal').classList.contains('hidden');
}

// The dashboard is the one page a student leaves open for hours, and its view
// of "am I checked in?" goes stale on its own: the auto-checkout sweep
// (backend-php/src/handlers/checkin_handlers.php) closes the visit server-side
// and nothing tells this tab, so the pill stays green and the elapsed timer
// keeps climbing past a checkout that already happened.
function refreshHistoryQuietly() {
  // Don't pull the ground out from under an open check-in modal: a re-render
  // mid-typing rebuilds the hour buttons and the closing-time bounds under the
  // student's finger. The interval keeps running; the next tick picks it up.
  if (isCheckinModalOpen()) return;
  // A stamp in flight reloads the history itself when it lands (performCheckin),
  // so refreshing on top of it only races the request it is about to supersede.
  if (busy) return;
  // Silent on failure. This fires unattended every minute, and one toast per
  // failed poll would bury the page whenever the phone is on a flaky connection.
  // The next tick retries, and anything the student actually pressed still
  // reports its own error.
  loadHistory().catch(() => {});
}

function startHistoryAutoRefresh() {
  stopHistoryAutoRefresh();
  historyRefreshId = setInterval(refreshHistoryQuietly, HISTORY_REFRESH_MS);
}

// Nothing navigates away from this page in-place today, but a timer that
// outlives the page it was started for is the kind of leak that only shows up
// once someone adds that navigation.
function stopHistoryAutoRefresh() {
  if (historyRefreshId) clearInterval(historyRefreshId);
  historyRefreshId = null;
}

function render() {
  const last = getLast();
  const checkedIn = isCheckedIn();
  const checkedInSince = checkedIn ? new Date(last.timestamp) : null;
  const plannedCheckoutAt = getPlannedCheckoutAt();

  // ป้ายสถานะ — สามสถานะ: อยู่ในห้องสมุด / ออกไปแล้ววันนี้ / ยังไม่ได้เช็คอิน
  const doneAt = checkedIn ? null : checkedOutToday();
  const dot = document.getElementById('status-dot');
  dot.classList.toggle('bg-status-success', checkedIn);
  dot.classList.toggle('bg-primary', !checkedIn && !!doneAt);
  dot.classList.toggle('bg-warning', !checkedIn && !doneAt);
  document.getElementById('status-ping').classList.toggle('hidden', !checkedIn);
  document.getElementById('status-text').textContent = checkedIn
    ? 'อยู่ในห้องสมุดตอนนี้'
    : doneAt
      ? `เช็คเอาต์แล้วเมื่อ ${formatHM(doneAt)} น.`
      : 'ยังไม่ได้เช็คอินวันนี้';

  // Elapsed timer.
  document.getElementById('elapsed-wrap').classList.toggle('hidden', !checkedIn);
  document.getElementById('stamp-pulse-ring').classList.toggle('hidden', !checkedIn);

  // Stamp button. Green to go in, red to come out — the colour carries the
  // action, so a student glancing at their phone doesn't have to read the
  // label to know which way the press will take them. Checking out was blue
  // (bg-secondary), which read the same as every other button on the page.
  const stampBtn = document.getElementById('stamp-btn');
  stampBtn.classList.toggle('bg-error', checkedIn);
  stampBtn.classList.toggle('bg-status-success', !checkedIn);
  document.getElementById('stamp-icon').textContent = checkedIn ? 'logout' : 'sync_alt';
  document.getElementById('stamp-label').textContent = checkedIn ? 'เช็คเอาต์' : 'เช็คอิน';

  // วันหยุด: ปิดปุ่มเช็คอินไปเลย แทนที่จะปล่อยให้กดแล้วเจอ error จากเซิร์ฟเวอร์
  // คนที่ยังค้างอยู่ในห้องสมุดจากวันทำการยังกดเช็คเอาต์ได้ตามปกติ
  syncStampDisabled();
  paintStampHint();

  // Planned checkout + extend buttons.
  const plannedWrap = document.getElementById('planned-checkout-wrap');
  plannedWrap.classList.toggle('hidden', !(checkedIn && plannedCheckoutAt));
  if (checkedIn && plannedCheckoutAt) {
    document.getElementById('planned-checkout-time').textContent = `ตั้งใจออก: ${formatHM(plannedCheckoutAt)}`;
  }

  // Checked-in-at clock time (elapsed timer above already covers duration).
  if (checkedIn) {
    document.getElementById('checkin-clock-text').textContent = `เช็คอินเมื่อ ${formatHM(checkedInSince)} น.`;
  }

  // Last-used line.
  const lastUsedText = document.getElementById('last-used-text');
  if (last) {
    const label = last.type === 'in' ? 'เช็คอิน' : 'เช็คเอาต์';
    const formatted = new Date(last.timestamp).toLocaleString('th-TH', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
    lastUsedText.textContent = `${label} — ${formatted}`;
  } else {
    lastUsedText.textContent = 'ยังไม่มีประวัติการเช็คอิน';
  }

  restartElapsedTimer(checkedInSince);
  restartReminderWatcher(checkedInSince, plannedCheckoutAt);
}

function restartElapsedTimer(checkedInSince) {
  if (elapsedTimerId) clearInterval(elapsedTimerId);
  elapsedTimerId = null;
  if (!checkedInSince) return;
  function tick() {
    const diff = Math.max(0, Math.floor((Date.now() - checkedInSince.getTime()) / 1000));
    document.getElementById('elapsed-time').textContent = formatClock(diff);
  }
  tick();
  elapsedTimerId = setInterval(tick, 1000);
}

// Only relevant while this tab stays open — the server-side auto-checkout
// sweep (backend-php/src/handlers/checkin_handlers.php) is the real backstop.
function restartReminderWatcher(checkedInSince, plannedCheckoutAt) {
  if (reminderWatcherId) clearInterval(reminderWatcherId);
  reminderWatcherId = null;
  if (!checkedInSince || !plannedCheckoutAt) {
    // ไม่ได้อยู่ในห้องสมุดแล้ว คำเตือนเรื่องเวลาออกจึงไม่มีความหมาย
    hideReminder();
    reminderNotifiedKey = null;
    return;
  }
  function tick() {
    const minutesLeft = Math.ceil((plannedCheckoutAt.getTime() - Date.now()) / 60000);
    const key = plannedCheckoutAt.toISOString();
    if (minutesLeft <= 20 && minutesLeft > 0 && reminderNotifiedKey !== key) {
      reminderNotifiedKey = key;
      showReminder(minutesLeft);
      // จังหวะสั่นสั้น-สั้น-ยาว แยกออกจากการสั่นแจ้งเตือนทั่วไปของเครื่อง
      if (navigator.vibrate) navigator.vibrate([200, 100, 200, 100, 400]);
      playReminderChime();
    }
  }
  tick();
  reminderWatcherId = setInterval(tick, 30000);
}

// เสียงเตือนสร้างจาก oscillator ไม่ได้โหลดไฟล์เสียง: ไม่ต้องมีไฟล์ให้ deploy
// ไม่ต้องแคช และยังดังตอนออฟไลน์
//
// เบราว์เซอร์เปิด AudioContext มาในสถานะ suspended จนกว่าจะมีการแตะหน้าจอ
// สักครั้ง ถ้ารอไปสร้างตอนจะเตือนจริงก็จะเงียบ จึงปลุกไว้ตั้งแต่การแตะครั้งแรก
let reminderAudioCtx = null;

function unlockReminderAudio() {
  try {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return;
    if (!reminderAudioCtx) reminderAudioCtx = new Ctx();
    if (reminderAudioCtx.state === 'suspended') reminderAudioCtx.resume();
  } catch (e) {
    // เครื่องไม่รองรับเสียง — การสั่นและกล่องเตือนยังทำงานตามปกติ
  }
}

function playReminderChime() {
  try {
    const ctx = reminderAudioCtx;
    if (!ctx || ctx.state !== 'running') return;
    const now = ctx.currentTime;
    // สองโน้ตไล่ขึ้น อ่านออกว่าเป็นเสียงแจ้งเตือน ไม่ใช่เสียงผิดพลาด
    [880, 1174.66].forEach((freq, i) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.value = freq;
      const t0 = now + i * 0.18;
      // ไต่ขึ้นแล้วค่อยๆ เบาลง เสียงตัดห้วนๆ จะมีเสียงแตกท้ายโน้ต
      gain.gain.setValueAtTime(0.0001, t0);
      gain.gain.exponentialRampToValueAtTime(0.3, t0 + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.38);
      osc.connect(gain);
      gain.connect(ctx.destination);
      osc.start(t0);
      osc.stop(t0 + 0.42);
    });
  } catch (e) {
    // เสียงเล่นไม่ได้ไม่ใช่เหตุให้คำเตือนหายไป
  }
}

function showReminder(minutesLeft) {
  document.getElementById('reminder-text').textContent = `เหลืออีก ${minutesLeft} นาทีจะถึงเวลาที่ตั้งไว้ ถ้าไม่ขอเวลาเพิ่ม ระบบจะเช็คเอาต์ให้อัตโนมัติ`;
  document.getElementById('reminder-banner').classList.remove('hidden');
}

function hideReminder() {
  document.getElementById('reminder-banner').classList.add('hidden');
}

function cooldownLeftMs() {
  return Math.max(0, cooldownUntil - Date.now());
}

// เริ่มนับหลังบันทึกสำเร็จหนึ่งครั้ง เพื่อไม่ให้กดรัวจนเกิดแถวเข้า-ออกซ้อนกัน
function startCooldown() {
  cooldownUntil = Date.now() + STAMP_COOLDOWN_MS;
  if (cooldownTimerId) clearInterval(cooldownTimerId);
  cooldownTimerId = setInterval(() => {
    if (cooldownLeftMs() === 0) {
      clearInterval(cooldownTimerId);
      cooldownTimerId = null;
    }
    syncStampDisabled();
    paintStampHint();
  }, 250);
  syncStampDisabled();
  paintStampHint();
}

function stampDisabled() {
  return busy || cooldownLeftMs() > 0 || (!libraryOpenToday && !isCheckedIn());
}

// ข้อความใต้ปุ่ม เรียงตามลำดับความสำคัญ: กำลังรอ > วันนี้ปิด > ข้อความปกติ
function paintStampHint() {
  const left = Math.ceil(cooldownLeftMs() / 1000);
  const el = document.getElementById('stamp-hint');
  if (left > 0) {
    el.textContent = `บันทึกแล้ว กดได้อีกครั้งในอีก ${left} วินาที`;
    return;
  }
  el.textContent = !libraryOpenToday && !isCheckedIn() ? closedMessage : STAMP_HINT_OPEN;
}

function syncStampDisabled() {
  document.getElementById('stamp-btn').disabled = stampDisabled();
}

function setBusy(next) {
  busy = next;
  syncStampDisabled();
}

async function performCheckin(body) {
  setBusy(true);
  try {
    const data = await apiPostJson('/checkin', body);
    reminderNotifiedKey = null;
    hideReminder();
    startCooldown();
    await loadHistory();
    showToast(data.message, { type: 'success' });
  } catch (err) {
    showToast(err.message, { type: 'error' });
  } finally {
    setBusy(false);
  }
}

async function extendCheckout(minutes) {
  try {
    const data = await apiPostJson('/checkin/extend', { minutes });
    reminderNotifiedKey = null;
    hideReminder();
    await loadHistory();
    showToast(data.message, { type: 'success' });
  } catch (err) {
    showToast(err.message, { type: 'error' });
  }
}

function handleStamp() {
  if (!isCheckedIn()) {
    if (!libraryOpenToday) {
      showToast(closedMessage, { type: 'error' });
      return;
    }
    openCheckinModal();
    return
  }
  performCheckin({});
}

function setModalError(message) {
  const el = document.getElementById('modal-error');
  if (!message) {
    el.classList.add('hidden');
    el.textContent = '';
    return;
  }
  el.textContent = message;
  el.classList.remove('hidden');
}

function setModalTab(tab) {
  document.querySelectorAll('[data-modal-tab]').forEach((btn) => {
    const active = btn.dataset.modalTab === tab;
    btn.classList.toggle('bg-surface-white', active);
    btn.classList.toggle('dark:bg-dm-surface', active);
    btn.classList.toggle('text-primary', active);
    btn.classList.toggle('dark:text-primary-fixed-dim', active);
    btn.classList.toggle('shadow-sm', active);
    btn.classList.toggle('text-on-surface-variant', !active);
    btn.classList.toggle('dark:text-dm-text-secondary', !active);
  });
  // is-active, not hidden: the panels animate their own height (see
  // .modal-panel in styles.css) so the dialog eases between the two sizes.
  document.getElementById('modal-panel-time').classList.toggle('is-active', tab === 'time');
  document.getElementById('modal-panel-hours').classList.toggle('is-active', tab === 'hours');
  setModalError('');
}

function renderHourButtons() {
  const container = document.getElementById('modal-hour-buttons');
  const buttons = buildHourButtons(closingTime);
  container.innerHTML = '';
  buttons.forEach((btn) => {
    const el = document.createElement('button');
    el.type = 'button';
    el.dataset.hours = btn.hours;
    el.dataset.exceeds = btn.exceeds ? '1' : '';
    el.className = `h-10 rounded-lg border text-sm font-bold transition-all border-outline-variant dark:border-dm-border text-text-primary dark:text-inverse-on-surface${btn.exceeds ? ' opacity-60' : ''}`;
    el.textContent = `${btn.hours} ชม.`;
    el.addEventListener('click', () => {
      selectedHours = btn.hours;
      setModalError('');
      renderHourButtonsSelection();
      document.getElementById('modal-hours-warning').classList.toggle('hidden', !btn.exceeds);
    });
    container.appendChild(el);
  });
}

function renderHourButtonsSelection() {
  document.querySelectorAll('#modal-hour-buttons [data-hours]').forEach((el) => {
    const active = Number(el.dataset.hours) === selectedHours;
    el.classList.toggle('border-primary', active);
    el.classList.toggle('text-primary', active);
    el.classList.toggle('dark:border-primary-fixed-dim', active);
    el.classList.toggle('dark:text-primary-fixed-dim', active);
    el.classList.toggle('ring-2', active);
    el.classList.toggle('ring-primary/30', active);
  });
}

function openCheckinModal() {
  const modal = document.getElementById('checkin-modal');
  modal.classList.remove('hidden');
  selectedHours = null;
  // Opening is not a "switch" — there is no previous panel to glide from, and
  // an animating panel has no height yet, which would leave buildTimeWheels()
  // below setting scrollTop on a zero-height column (wheels land on the wrong
  // value). Suppress the panel animation for this frame only; tab switches
  // after this still animate.
  modal.classList.add('no-panel-anim');
  setModalTab('time');
  setModalError('');
  document.getElementById('modal-hours-warning').classList.add('hidden');

  const bounds = timeInputBounds(closingTime);
  document.getElementById('modal-time-open').classList.toggle('hidden', !bounds.isOpen);
  document.getElementById('modal-time-closed').classList.toggle('hidden', bounds.isOpen);
  document.getElementById('modal-time-hint').textContent = `เลื่อนเลือกเวลาระหว่าง ${bounds.min} - ${bounds.max} น.`;
  // Built here, after the dialog has been un-hidden above: the wheels position
  // themselves by scrollTop, which is pinned at 0 while the box is display:none.
  if (bounds.isOpen) buildTimeWheels(bounds);

  renderHourButtons();

  // Re-enable panel animation once this frame's layout is settled.
  requestAnimationFrame(() => {
    requestAnimationFrame(() => modal.classList.remove('no-panel-anim'));
  });
}

function closeCheckinModal() {
  document.getElementById('checkin-modal').classList.add('hidden');
}

function confirmModal() {
  const activeTab = document.getElementById('modal-panel-time').classList.contains('is-active') ? 'time' : 'hours';
  if (activeTab === 'time') {
    const bounds = timeInputBounds(closingTime);
    if (!bounds.isOpen) {
      setModalError('ห้องสมุดปิดแล้ว ไม่สามารถเช็คอินได้');
      return;
    }
    const checkoutTimeValue = readTimeWheels();
    if (!checkoutTimeValue) {
      setModalError('กรุณาเลือกเวลาที่จะออก');
      return;
    }
    // The wheels already clamp to this window, so this catches only the case
    // where the window moved while the modal sat open: opened before closing
    // time, confirmed after it.
    if (checkoutTimeValue < bounds.min || checkoutTimeValue > bounds.max) {
      setModalError(`กรุณาเลือกเวลาระหว่าง ${bounds.min} - ${bounds.max} น.`);
      return;
    }
    closeCheckinModal();
    performCheckin({ checkout_time: checkoutTimeValue });
  } else {
    if (!selectedHours) {
      setModalError('กรุณาเลือกจำนวนชั่วโมง');
      return;
    }
    closeCheckinModal();
    performCheckin({ duration_minutes: selectedHours * 60 });
  }
}

function checkinUntilClosing() {
  closeCheckinModal();
  performCheckin({});
}

document.addEventListener('DOMContentLoaded', () => {
  apiFetch('/library-info')
    .then((data) => {
      closingTime = data.closing_time;
      libraryOpenToday = data.is_open_today !== false;
      if (data.closed_message) closedMessage = data.closed_message;
      // สองคำขอนี้วิ่งคู่กัน ถ้าประวัติมาถึงก่อน render() รอบนั้นยังไม่รู้ว่า
      // วันนี้ห้องสมุดปิด — วาดใหม่ให้ปุ่มกับข้อความตรงกับความจริง
      if (historyLoaded) render();
    })
    .catch(() => {});

  loadHistory();

  document.getElementById('stamp-btn').addEventListener('click', handleStamp);
  document.getElementById('dashboard-view-history').addEventListener('click', openHistoryModal);
  document.querySelectorAll('[data-modal-tab]').forEach((btn) => {
    btn.addEventListener('click', () => setModalTab(btn.dataset.modalTab));
  });
  document.getElementById('modal-checkin-until-closing').addEventListener('click', checkinUntilClosing);
  document.getElementById('modal-cancel').addEventListener('click', closeCheckinModal);
  document.getElementById('modal-confirm').addEventListener('click', confirmModal);
  document.getElementById('checkin-modal').addEventListener('click', (e) => {
    if (e.target.id === 'checkin-modal') closeCheckinModal();
  });
  document.getElementById('reminder-dismiss').addEventListener('click', hideReminder);
  // แตะพื้นหลังนอกกล่อง = เท่ากับกด "ไม่ต้อง" (กดโดนกล่องเองไม่ปิด)
  document.getElementById('reminder-banner').addEventListener('click', (e) => {
    if (e.target.id === 'reminder-banner') hideReminder();
  });
  // ครั้งเดียวพอ หลังจากนั้น AudioContext ก็พร้อมใช้ไปตลอดอายุหน้า
  ['pointerdown', 'keydown'].forEach((evt) => {
    document.addEventListener(evt, unlockReminderAudio, { once: true, passive: true });
  });
  document.querySelectorAll('[data-extend]').forEach((btn) => {
    btn.addEventListener('click', () => extendCheckout(Number(btn.dataset.extend)));
  });

  startHistoryAutoRefresh();

  // The common stale case is not an idle desktop tab — it is a phone locked or
  // switched away from for an hour, where background timers are throttled or
  // frozen outright. Catch up on the way back in instead of showing a stale
  // status for the rest of the interval.
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') refreshHistoryQuietly();
  });

  // pagehide over beforeunload: it also fires when the page enters the
  // back/forward cache, and pageshow re-arms the timer if the student comes
  // back to a restored page. startHistoryAutoRefresh() clears first, so the
  // pageshow that follows this initial load can't stack a second interval.
  window.addEventListener('pagehide', stopHistoryAutoRefresh);
  window.addEventListener('pageshow', startHistoryAutoRefresh);
});

// ประกาศจากเจ้าหน้าที่ — โหลดแยกจากประวัติ เพราะล้มเหลวคนละเรื่องกัน
// ถ้าดึงไม่ได้ก็แค่ไม่มีประกาศ ไม่ควรมี toast มารบกวนการเช็คอิน
function loadAnnouncement() {
  apiFetch('/announcement')
    .then((data) => {
      const block = document.getElementById('announcement-block');
      const text = (data && data.text ? data.text : '').trim();
      const show = !!(data && data.enabled) && text !== '';
      block.classList.toggle('hidden', !show);
      // textContent ไม่ใช่ innerHTML — ข้อความมาจากช่องพิมพ์ของแอดมิน
      if (show) document.getElementById('announcement-text').textContent = text;
    })
    .catch(() => {});
}

document.addEventListener('DOMContentLoaded', loadAnnouncement);
