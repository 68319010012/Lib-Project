// Port of frontend-react/src/pages/DashboardPage.jsx.

let historyRows = [];
let closingTime = '17:00';
let busy = false;
let elapsedTimerId = null;
let reminderWatcherId = null;
let reminderNotifiedKey = null;
let selectedHours = null;

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

function getPlannedCheckoutAt() {
  const last = getLast();
  return isCheckedIn() && last.planned_checkout_at ? new Date(last.planned_checkout_at) : null;
}

async function loadHistory() {
  historyRows = await apiFetch('/me/history?limit=20');
  render();
}

function render() {
  const last = getLast();
  const checkedIn = isCheckedIn();
  const checkedInSince = checkedIn ? new Date(last.timestamp) : null;
  const plannedCheckoutAt = getPlannedCheckoutAt();

  // Status pill.
  document.getElementById('status-dot').classList.toggle('bg-status-success', checkedIn);
  document.getElementById('status-dot').classList.toggle('bg-outline', !checkedIn);
  document.getElementById('status-ping').classList.toggle('hidden', !checkedIn);
  document.getElementById('status-text').textContent = checkedIn ? 'อยู่ในห้องสมุดตอนนี้' : 'ยังไม่ได้เช็คอินวันนี้';

  // Elapsed timer.
  document.getElementById('elapsed-wrap').classList.toggle('hidden', !checkedIn);
  document.getElementById('stamp-pulse-ring').classList.toggle('hidden', !checkedIn);

  // Stamp button.
  const stampBtn = document.getElementById('stamp-btn');
  stampBtn.classList.toggle('bg-secondary', checkedIn);
  stampBtn.classList.toggle('bg-status-success', !checkedIn);
  document.getElementById('stamp-icon').textContent = checkedIn ? 'logout' : 'sync_alt';
  document.getElementById('stamp-label').textContent = checkedIn ? 'เช็คเอาต์' : 'เช็คอิน';

  // Planned checkout + extend buttons.
  const plannedWrap = document.getElementById('planned-checkout-wrap');
  plannedWrap.classList.toggle('hidden', !(checkedIn && plannedCheckoutAt));
  if (checkedIn && plannedCheckoutAt) {
    document.getElementById('planned-checkout-time').textContent = `ตั้งใจออก: ${formatHM(plannedCheckoutAt)}`;
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
  if (!checkedInSince || !plannedCheckoutAt) return;
  function tick() {
    const minutesLeft = Math.ceil((plannedCheckoutAt.getTime() - Date.now()) / 60000);
    const key = plannedCheckoutAt.toISOString();
    if (minutesLeft <= 20 && minutesLeft > 0 && reminderNotifiedKey !== key) {
      reminderNotifiedKey = key;
      showReminder(minutesLeft);
      if (navigator.vibrate) navigator.vibrate(200);
    }
  }
  tick();
  reminderWatcherId = setInterval(tick, 30000);
}

function showReminder(minutesLeft) {
  document.getElementById('reminder-text').textContent = `อีก ${minutesLeft} นาทีจะถึงเวลาที่ตั้งไว้ ต้องการขอเวลาเพิ่มไหม?`;
  document.getElementById('reminder-banner').classList.remove('hidden');
}

function hideReminder() {
  document.getElementById('reminder-banner').classList.add('hidden');
}

function setBusy(next) {
  busy = next;
  document.getElementById('stamp-btn').disabled = busy;
}

async function performCheckin(body) {
  setBusy(true);
  try {
    const data = await apiPostJson('/checkin', body);
    reminderNotifiedKey = null;
    hideReminder();
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
  document.getElementById('modal-panel-time').classList.toggle('hidden', tab !== 'time');
  document.getElementById('modal-panel-hours').classList.toggle('hidden', tab !== 'hours');
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
  document.getElementById('checkin-modal').classList.remove('hidden');
  selectedHours = null;
  setModalTab('time');
  setModalError('');
  document.getElementById('modal-hours-warning').classList.add('hidden');

  const bounds = timeInputBounds(closingTime);
  const timeInput = document.getElementById('modal-checkout-time');
  timeInput.value = '';
  timeInput.min = bounds.min;
  timeInput.max = bounds.max;
  document.getElementById('modal-time-open').classList.toggle('hidden', !bounds.isOpen);
  document.getElementById('modal-time-closed').classList.toggle('hidden', bounds.isOpen);
  document.getElementById('modal-time-hint').textContent = `กรอกเวลาระหว่าง ${bounds.min} - ${bounds.max} น. (เกินเวลานี้ระบบจะปรับให้ออกตอนปิดแทน)`;

  renderHourButtons();
}

function closeCheckinModal() {
  document.getElementById('checkin-modal').classList.add('hidden');
}

function confirmModal() {
  const activeTab = document.getElementById('modal-panel-time').classList.contains('hidden') ? 'hours' : 'time';
  if (activeTab === 'time') {
    const bounds = timeInputBounds(closingTime);
    if (!bounds.isOpen) {
      setModalError('ห้องสมุดปิดแล้ว ไม่สามารถเช็คอินได้');
      return;
    }
    const checkoutTimeValue = document.getElementById('modal-checkout-time').value;
    if (!checkoutTimeValue) {
      setModalError('กรุณากรอกเวลาที่จะออก');
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
    .then((data) => { closingTime = data.closing_time; })
    .catch(() => {});

  loadHistory();

  document.getElementById('stamp-btn').addEventListener('click', handleStamp);
  document.getElementById('dashboard-view-history').addEventListener('click', openHistoryModal);
  document.querySelectorAll('[data-modal-tab]').forEach((btn) => {
    btn.addEventListener('click', () => setModalTab(btn.dataset.modalTab));
  });
  document.getElementById('modal-checkout-time').addEventListener('change', () => setModalError(''));
  document.getElementById('modal-checkin-until-closing').addEventListener('click', checkinUntilClosing);
  document.getElementById('modal-cancel').addEventListener('click', closeCheckinModal);
  document.getElementById('modal-confirm').addEventListener('click', confirmModal);
  document.getElementById('checkin-modal').addEventListener('click', (e) => {
    if (e.target.id === 'checkin-modal') closeCheckinModal();
  });
  document.getElementById('reminder-dismiss').addEventListener('click', hideReminder);
  document.querySelectorAll('[data-extend]').forEach((btn) => {
    btn.addEventListener('click', () => extendCheckout(Number(btn.dataset.extend)));
  });
});
