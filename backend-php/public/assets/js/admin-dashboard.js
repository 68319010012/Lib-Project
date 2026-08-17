// Port of frontend-react/src/pages/AdminDashboardPage.jsx.

const WEEKDAY_LABELS = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
const THAI_MONTHS = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
const DEPT_COLORS = ['bg-primary', 'bg-secondary', 'bg-accent-stats', 'bg-status-success', 'bg-outline'];

let allRows = null;
let currentView = 'month';

function isoWeekdayIndex(date) {
  return (date.getDay() + 6) % 7; // 0=Mon .. 6=Sun
}

function toISODate(d) {
  // Local-date (not UTC) slice so "today" lines up with the user's clock —
  // matches the day boundary the trend buckets use below.
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

// Thai-locale label for a single day, e.g. "พ. 12 ส.ค. 2569" — used by the
// click/tap detail panel so a bar's meaning never depends on a hover title.
function formatThaiDate(dayStr) {
  const d = new Date(`${dayStr}T00:00:00`);
  return `วัน${WEEKDAY_LABELS[isoWeekdayIndex(d)]} ${d.getDate()} ${THAI_MONTHS[d.getMonth()]} ${d.getFullYear() + 543}`;
}

// Short axis tick, e.g. "12 ส.ค." — used under each bar.
function formatShortDate(dayStr) {
  const d = new Date(`${dayStr}T00:00:00`);
  return `${d.getDate()} ${THAI_MONTHS[d.getMonth()]}`;
}

function filterRows(rows, view) {
  const now = new Date();
  if (view === 'today') {
    const todayStr = toISODate(now);
    return rows.filter((r) => r.timestamp.slice(0, 10) === todayStr);
  }
  if (view === 'week') {
    const cutoff = new Date(now.getTime() - 7 * 24 * 3600 * 1000);
    return rows.filter((r) => new Date(r.timestamp) >= cutoff);
  }
  return rows;
}

// Every calendar day the current view covers, data or not — this is what
// makes the trend chart show a complete run of days instead of only the
// days that happened to have a check-in (which used to leave gaps and made
// the chart unreadable / not line up with the calendar).
function dateRangeForView(view) {
  const now = new Date();
  if (view === 'today') {
    return [toISODate(now)];
  }
  if (view === 'week') {
    const days = [];
    for (let i = 6; i >= 0; i--) {
      days.push(toISODate(new Date(now.getTime() - i * 24 * 3600 * 1000)));
    }
    return days;
  }
  // month: 1st of the current month through today (the server-side query
  // already scopes /admin/reports to the current month).
  const days = [];
  const cursor = new Date(now.getFullYear(), now.getMonth(), 1);
  while (cursor <= now) {
    days.push(toISODate(cursor));
    cursor.setDate(cursor.getDate() + 1);
  }
  return days;
}

function computeStats(rows, view) {
  const filtered = filterRows(rows, view);
  const total = filtered.length;
  const uniqueUsers = new Set(filtered.map((r) => r.student_id)).size;

  const dayBuckets = {};
  filtered.forEach((r) => {
    const day = r.timestamp.slice(0, 10);
    dayBuckets[day] = (dayBuckets[day] || 0) + 1;
  });

  const dayKeys = dateRangeForView(view);
  const avgDaily = dayKeys.length ? Math.round(total / dayKeys.length) : 0;

  const lastByUser = {};
  filtered.forEach((r) => {
    const prev = lastByUser[r.student_id];
    if (!prev || new Date(r.timestamp) > new Date(prev.timestamp)) lastByUser[r.student_id] = r;
  });
  const currentlyInside = Object.values(lastByUser).filter((r) => r.type === 'in').length;

  const maxDay = Math.max(1, ...dayKeys.map((k) => dayBuckets[k] || 0));
  const trendBars = dayKeys.map((day) => {
    const count = dayBuckets[day] || 0;
    return { day, count, pct: Math.max(4, Math.round((count / maxDay) * 100)) };
  });

  const deptCounts = {};
  filtered.forEach((r) => {
    const dept = r.department || 'ไม่ระบุแผนก';
    deptCounts[dept] = (deptCounts[dept] || 0) + 1;
  });
  const deptEntries = Object.entries(deptCounts)
    .sort((a, b) => b[1] - a[1])
    .slice(0, 5)
    .map(([dept, count]) => ({ dept, count, pct: total ? Math.round((count / total) * 100) : 0 }));

  // Arrival patterns, from check-ins only ('in') — a check-out at 17:00 isn't
  // "usage at 17:00". Two one-dimensional views (hour-of-day and day-of-week)
  // replace the old 7x24 heatmap, which was mostly empty (the library is
  // closed for ~15 of the 24 columns) and gave no readable signal.
  const checkins = filtered.filter((r) => r.type === 'in');
  const totalCheckins = checkins.length;

  const hourCounts = Array(24).fill(0);
  const weekdayCounts = Array(7).fill(0);
  checkins.forEach((r) => {
    const d = new Date(r.timestamp);
    hourCounts[d.getHours()]++;
    weekdayCounts[isoWeekdayIndex(d)]++;
  });

  // Trim the dead night hours: show a continuous run from the first to the
  // last hour that actually has a check-in, so the axis isn't 60% empty.
  // Zero hours *inside* that run still render (short bars) to keep the time
  // axis continuous and honestly spaced.
  const firstHour = hourCounts.findIndex((c) => c > 0);
  const lastHour = firstHour === -1 ? -1 : 23 - [...hourCounts].reverse().findIndex((c) => c > 0);
  const maxHour = Math.max(1, ...hourCounts);
  const hourBars = [];
  for (let h = firstHour; h !== -1 && h <= lastHour; h++) {
    hourBars.push({ hour: h, count: hourCounts[h], pct: Math.max(3, Math.round((hourCounts[h] / maxHour) * 100)) });
  }
  const peakHour = totalCheckins ? hourCounts.indexOf(Math.max(...hourCounts)) : null;

  const maxWeekday = Math.max(1, ...weekdayCounts);
  const weekdayBars = weekdayCounts.map((count, day) => ({
    day,
    count,
    pct: Math.max(2, Math.round((count / maxWeekday) * 100)),
  }));
  const peakDay = totalCheckins ? weekdayCounts.indexOf(Math.max(...weekdayCounts)) : null;

  return { total, uniqueUsers, avgDaily, currentlyInside, dayKeys, trendBars, deptEntries, totalCheckins, hourBars, peakHour, weekdayBars, peakDay };
}

function selectTrendBar(el, bar) {
  document.querySelectorAll('#trend-bars .trend-bar').forEach((b) => b.classList.remove('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim', 'bg-primary/70', 'dark:bg-primary-fixed-dim/80'));
  el.classList.add('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim', 'bg-primary/70', 'dark:bg-primary-fixed-dim/80');
  document.getElementById('trend-detail').textContent = `${formatThaiDate(bar.day)} — เข้าใช้ ${bar.count.toLocaleString()} ครั้ง`;
  openDayModal(bar.day);
}

// Every row (in + out) for one calendar day, grouped by department — powers
// the day-detail popup opened from a trend-bar click.
function departmentBreakdownForDay(day) {
  const rows = allRows.filter((r) => r.timestamp.slice(0, 10) === day);
  const deptCounts = {};
  rows.forEach((r) => {
    const dept = r.department || 'ไม่ระบุแผนก';
    deptCounts[dept] = (deptCounts[dept] || 0) + 1;
  });
  const total = rows.length;
  return Object.entries(deptCounts)
    .sort((a, b) => b[1] - a[1])
    .map(([dept, count]) => ({ dept, count, pct: total ? Math.round((count / total) * 100) : 0 }));
}

// Individual students within one department, deduped from a set of
// checkin_logs rows — the drill-down level under a department breakdown.
function studentsInRowsForDept(rows, dept) {
  const byStudent = {};
  rows
    .filter((r) => (r.department || 'ไม่ระบุแผนก') === dept)
    .forEach((r) => {
      const existing = byStudent[r.student_id];
      if (existing) {
        existing.count++;
        return;
      }
      byStudent[r.student_id] = {
        student_id: r.student_id,
        name: `${r.prefix || ''}${r.first_name} ${r.last_name}`.trim(),
        year_level: r.year_level,
        count: 1,
      };
    });
  return Object.values(byStudent).sort((a, b) => b.count - a.count);
}

function renderDayDeptList(day) {
  const body = document.getElementById('day-detail-body');
  const entries = departmentBreakdownForDay(day);
  const total = entries.reduce((sum, e) => sum + e.count, 0);

  document.getElementById('day-detail-title').textContent = formatThaiDate(day);
  document.getElementById('day-detail-subtitle').textContent = total
    ? `เข้าใช้ทั้งหมด ${total.toLocaleString()} ครั้ง — เลือกแผนกเพื่อดูรายบุคคล`
    : 'ไม่มีข้อมูลการเข้าใช้ในวันนี้';

  if (!entries.length) {
    body.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary">ไม่มีข้อมูลการเข้าใช้ในวันนี้</p>';
    return;
  }

  body.innerHTML = entries
    .map(
      (entry, i) => `
      <button type="button" data-dept-index="${i}" class="day-detail-dept w-full text-left space-y-2 p-3 rounded-lg hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
        <div class="flex justify-between items-center text-sm font-bold">
          <span class="flex items-center gap-1">${escapeHtml(entry.dept)}<span class="material-symbols-outlined text-base text-on-surface-variant dark:text-dm-text-secondary">chevron_right</span></span>
          <span class="font-label-code flex-shrink-0">${entry.pct}% (${entry.count.toLocaleString()} ครั้ง)</span>
        </div>
        <div class="w-full bg-surface-container dark:bg-dm-border rounded-full h-2">
          <div class="${DEPT_COLORS[i % DEPT_COLORS.length]} h-2 rounded-full" style="width: ${entry.pct}%"></div>
        </div>
      </button>
    `,
    )
    .join('');

  body.querySelectorAll('.day-detail-dept').forEach((btn) => {
    const entry = entries[Number(btn.dataset.deptIndex)];
    btn.addEventListener('click', () => renderStudentList({
      students: studentsInRowsForDept(allRows.filter((r) => r.timestamp.slice(0, 10) === day), entry.dept),
      title: entry.dept,
      subtitle: `${formatThaiDate(day)} — พบ {n} คน`,
      onBack: () => renderDayDeptList(day),
    }));
  });
}

// Renders either a department's individual students, or (with no students
// yet resolved) is called by the two drill-down entry points below —
// students/title/subtitle are fully computed by the caller so this stays
// agnostic to "which day" vs "whole period" triggered it.
function renderStudentList({ students, title, subtitle, onBack }) {
  const body = document.getElementById('day-detail-body');

  document.getElementById('day-detail-title').textContent = title;
  document.getElementById('day-detail-subtitle').textContent = subtitle.replace('{n}', students.length.toLocaleString());

  const rowsHtml = students.length
    ? students
        .map(
          (s) => `
        <div class="student-row flex items-center justify-between rounded-lg bg-surface-container-low dark:bg-dm-bg">
          <div class="min-w-0">
            <p class="font-bold text-text-primary dark:text-inverse-on-surface truncate">${escapeHtml(s.name)}</p>
            <p class="text-xs text-text-secondary dark:text-dm-text-secondary">${escapeHtml(s.student_id)}${s.year_level ? ` · ปีที่ ${escapeHtml(s.year_level)}` : ''}</p>
          </div>
          <span class="student-row-count text-xs font-bold text-primary dark:text-primary-fixed-dim flex-shrink-0">${s.count} ครั้ง</span>
        </div>
      `,
        )
        .join('')
    : '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary">ไม่มีข้อมูล</p>';

  const backHtml = onBack
    ? `<button type="button" id="day-detail-back" class="flex items-center gap-1 text-sm font-bold text-primary dark:text-primary-fixed-dim mb-2">
        <span class="material-symbols-outlined text-lg">chevron_left</span> กลับ
      </button>`
    : '';

  body.innerHTML = backHtml + rowsHtml;
  if (onBack) document.getElementById('day-detail-back').addEventListener('click', onBack);
}

function openDayModal(day) {
  const modal = document.getElementById('day-detail-modal');
  if (!modal) return;
  modal.classList.remove('hidden');
  renderDayDeptList(day);
}

// Entry point from the "แผนกที่เข้าใช้มากที่สุด" panel — drills straight into
// a department's individual students across the whole selected view period
// (no per-day list above it, since the panel itself is already period-wide).
function openDeptModal(dept) {
  const modal = document.getElementById('day-detail-modal');
  if (!modal) return;
  modal.classList.remove('hidden');
  const rows = filterRows(allRows, currentView);
  renderStudentList({
    students: studentsInRowsForDept(rows, dept),
    title: dept,
    subtitle: 'ในช่วงเวลาที่เลือก — พบ {n} คน',
    onBack: null,
  });
}

function closeDayModal() {
  const modal = document.getElementById('day-detail-modal');
  if (modal) modal.classList.add('hidden');
}

const WEEKDAY_FULL = ['จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];

function pad2(n) {
  return String(n).padStart(2, '0');
}

// Vertical bars: check-ins by hour of day, over the library's active hours
// only. Single series (magnitude), so one hue; the peak hour is the darker
// full-strength bar (also named in the insight line below) — everything else
// is the same lighter primary. Bars are direct children of the fixed-height
// flex container (so their percentage heights resolve), matching the trend
// chart; exact counts come from the native-title hover.
function renderHourChart(stats) {
  const container = document.getElementById('hour-bars');
  const axis = document.getElementById('hour-axis');
  container.innerHTML = '';
  axis.innerHTML = '';

  if (!stats.hourBars.length) {
    container.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary m-auto">ไม่มีข้อมูลในช่วงเวลานี้</p>';
    return;
  }

  const labelStep = Math.max(1, Math.ceil(stats.hourBars.length / 8));
  stats.hourBars.forEach((bar, i) => {
    const isPeak = bar.hour === stats.peakHour;

    const barEl = document.createElement('div');
    barEl.title = `${pad2(bar.hour)}:00 น. — เช็คอิน ${bar.count.toLocaleString()} ครั้ง`;
    barEl.className = `flex-1 rounded-t transition-all ${isPeak ? 'bg-primary dark:bg-primary-fixed-dim' : 'bg-primary/40 dark:bg-primary-fixed-dim/50'}`;
    barEl.style.height = `${bar.pct}%`;
    container.appendChild(barEl);

    const tick = document.createElement('span');
    tick.className = 'flex-1 text-center truncate';
    if (i === 0 || i === stats.hourBars.length - 1 || i % labelStep === 0) tick.textContent = pad2(bar.hour);
    axis.appendChild(tick);
  });
}

// Horizontal bars: check-ins by weekday. Peak day emphasized (darker fill +
// primary-ink label); exact counts always visible at the row end.
function renderWeekdayChart(stats) {
  const container = document.getElementById('weekday-bars');
  if (!stats.totalCheckins) {
    container.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary">ไม่มีข้อมูลในช่วงเวลานี้</p>';
    return;
  }
  container.innerHTML = stats.weekdayBars
    .map((bar) => {
      const isPeak = bar.day === stats.peakDay;
      const ink = isPeak ? 'text-primary dark:text-primary-fixed-dim' : 'text-text-secondary dark:text-dm-text-secondary';
      const fill = isPeak ? 'bg-primary dark:bg-primary-fixed-dim' : 'bg-primary/40 dark:bg-primary-fixed-dim/50';
      return `
        <div class="flex items-center gap-3" title="วัน${escapeHtml(WEEKDAY_FULL[bar.day])} — เช็คอิน ${bar.count.toLocaleString()} ครั้ง">
          <span class="w-8 text-sm font-bold ${ink} flex-shrink-0">${WEEKDAY_LABELS[bar.day]}</span>
          <div class="flex-1 bg-surface-container dark:bg-dm-border rounded-full h-3 overflow-hidden">
            <div class="${fill} h-3 rounded-full" style="width: ${Math.max(bar.count ? 4 : 0, bar.pct)}%"></div>
          </div>
          <span class="w-8 text-right text-sm font-bold font-label-code ${ink} flex-shrink-0">${bar.count.toLocaleString()}</span>
        </div>`;
    })
    .join('');
}

function render() {
  const subtitle = document.getElementById('ad-subtitle');
  if (!allRows) {
    subtitle.textContent = 'กำลังโหลดข้อมูลการเข้าใช้…';
    return;
  }
  const stats = computeStats(allRows, currentView);

  subtitle.textContent = stats.total
    ? `มีการเข้าใช้ ${stats.total.toLocaleString()} ครั้ง จากนักศึกษา ${stats.uniqueUsers.toLocaleString()} คน ในช่วงเวลาที่เลือก`
    : 'ไม่มีข้อมูลการเข้าใช้ในช่วงเวลาที่เลือก';

  document.getElementById('kpi-total').textContent = stats.total.toLocaleString();
  document.getElementById('kpi-unique').textContent = stats.uniqueUsers.toLocaleString();
  document.getElementById('kpi-avg').textContent = stats.avgDaily.toLocaleString();
  document.getElementById('kpi-inside').textContent = stats.currentlyInside.toLocaleString();
  document.querySelectorAll('.kpi-skeleton').forEach((el) => el.classList.add('hidden'));
  document.querySelectorAll('.kpi-value').forEach((el) => el.classList.remove('hidden'));

  const trendContainer = document.getElementById('trend-bars');
  const trendAxis = document.getElementById('trend-axis');
  trendContainer.innerHTML = '';
  trendAxis.innerHTML = '';
  document.getElementById('trend-detail').textContent = 'แตะหรือคลิกแท่งกราฟด้านบนเพื่อดูจำนวนคนเข้าใช้ในแต่ละวัน';

  if (stats.trendBars.length) {
    // Cap how many axis ticks get text so bars stay readable even with ~30
    // days in view — every bar stays clickable regardless, just the label
    // underneath it is thinned out.
    const labelStep = Math.max(1, Math.ceil(stats.trendBars.length / 8));

    stats.trendBars.forEach((bar, i) => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.title = `${formatThaiDate(bar.day)}: ${bar.count} ครั้ง`;
      btn.setAttribute('aria-label', `${formatThaiDate(bar.day)}: เข้าใช้ ${bar.count} ครั้ง`);
      btn.className =
        'trend-bar appearance-none flex-1 min-w-[3px] bg-primary/20 dark:bg-primary-fixed-dim/40 hover:bg-primary/40 dark:hover:bg-primary-fixed-dim/60 focus-visible:bg-primary/40 rounded-t transition-all cursor-pointer border-0 p-0 outline-none';
      btn.style.height = `${bar.pct}%`;
      btn.addEventListener('click', () => selectTrendBar(btn, bar));
      trendContainer.appendChild(btn);

      const tick = document.createElement('span');
      tick.className = 'flex-1 text-center truncate';
      if (i === 0 || i === stats.trendBars.length - 1 || i % labelStep === 0) {
        tick.textContent = formatShortDate(bar.day);
      }
      trendAxis.appendChild(tick);
    });
  } else {
    trendContainer.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary m-auto">ไม่มีข้อมูลในช่วงเวลานี้</p>';
  }

  const deptContainer = document.getElementById('dept-bars');
  if (stats.deptEntries.length) {
    deptContainer.innerHTML = stats.deptEntries
      .map(
        (entry, i) => `
        <button type="button" data-dept-index="${i}" class="dept-bar-entry w-full text-left space-y-2 group">
          <div class="flex justify-between items-center text-sm font-bold">
            <span class="group-hover:underline">${escapeHtml(entry.dept)}</span>
            <span class="font-label-code">${entry.pct}% (${entry.count.toLocaleString()} ครั้ง)</span>
          </div>
          <div class="w-full bg-surface-container dark:bg-dm-border rounded-full h-2">
            <div class="${DEPT_COLORS[i % DEPT_COLORS.length]} h-2 rounded-full" style="width: ${entry.pct}%"></div>
          </div>
        </button>
      `,
      )
      .join('');
    deptContainer.querySelectorAll('.dept-bar-entry').forEach((btn) => {
      const entry = stats.deptEntries[Number(btn.dataset.deptIndex)];
      btn.addEventListener('click', () => openDeptModal(entry.dept));
    });
  } else {
    deptContainer.innerHTML = '<p class="text-body-md text-text-secondary">ไม่มีข้อมูลในช่วงเวลานี้</p>';
  }

  document.getElementById('peak-text').textContent =
    stats.totalCheckins > 0
      ? `คนเข้าใช้มากที่สุด: วัน${WEEKDAY_FULL[stats.peakDay]} และช่วงเวลา ${pad2(stats.peakHour)}:00 น.`
      : 'ข้อมูลยังไม่เพียงพอที่จะระบุช่วงเวลาที่มีคนใช้มากที่สุด';

  renderHourChart(stats);
  renderWeekdayChart(stats);
}

function load() {
  // Local-date slice, not toISOString() (UTC) — during the 00:00-07:00
  // Thailand window that pair disagree on which month "now" is in.
  const month = toISODate(new Date()).slice(0, 7);
  apiFetch(`/admin/reports?month=${month}`)
    .then((rows) => {
      allRows = rows;
      render();
    })
    .catch((err) => {
      // Previously unhandled: a failed fetch left allRows null forever and
      // the page stuck on "กำลังโหลดข้อมูล…" with no indication anything was
      // wrong. The 20s poll below still retries on its own; this just makes
      // a stalled load visible instead of silently hanging.
      document.getElementById('ad-subtitle').textContent =
        `โหลดข้อมูลไม่สำเร็จ: ${err.message || 'เกิดข้อผิดพลาด'} — กำลังลองใหม่อัตโนมัติ`;
    });
}

document.addEventListener('DOMContentLoaded', () => {
  load();
  // "อยู่ในห้องสมุดตอนนี้" is computed client-side from this snapshot, so
  // without polling it goes stale — poll instead of only fetching once.
  setInterval(load, 20000);

  document.getElementById('view-select').addEventListener('change', (e) => {
    currentView = e.target.value;
    render();
  });

  const dayModal = document.getElementById('day-detail-modal');
  if (dayModal) {
    dayModal.addEventListener('click', (e) => {
      if (e.target === dayModal) closeDayModal();
    });
    document.getElementById('day-detail-close').addEventListener('click', closeDayModal);
  }
});
