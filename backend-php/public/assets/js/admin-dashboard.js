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
  // matches the day boundary the trend/heatmap buckets use below.
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

  const counts = Array.from({ length: 7 }, () => Array(24).fill(0));
  filtered.forEach((r) => {
    const d = new Date(r.timestamp);
    counts[isoWeekdayIndex(d)][d.getHours()]++;
  });
  const maxCell = Math.max(1, ...counts.flat());
  let peak = { count: -1, day: 0, hour: 0 };
  const heatCells = [];
  for (let day = 0; day < 7; day++) {
    for (let hour = 0; hour < 24; hour++) {
      const count = counts[day][hour];
      if (count > peak.count) peak = { count, day, hour };
      const ratio = count / maxCell;
      let shade = 'bg-surface-container dark:bg-dm-border';
      if (ratio > 0.75) shade = 'bg-primary dark:bg-primary-fixed-dim';
      else if (ratio > 0.5) shade = 'bg-primary/70 dark:bg-primary-fixed-dim/70';
      else if (ratio > 0.25) shade = 'bg-primary/40 dark:bg-primary-fixed-dim/50';
      else if (ratio > 0) shade = 'bg-primary/20 dark:bg-primary-fixed-dim/35';
      heatCells.push({ day, hour, count, shade });
    }
  }

  return { total, uniqueUsers, avgDaily, currentlyInside, dayKeys, trendBars, deptEntries, heatCells, peak };
}

function selectTrendBar(el, bar) {
  document.querySelectorAll('#trend-bars .trend-bar').forEach((b) => b.classList.remove('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim', 'bg-primary/70', 'dark:bg-primary-fixed-dim/80'));
  el.classList.add('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim', 'bg-primary/70', 'dark:bg-primary-fixed-dim/80');
  document.getElementById('trend-detail').textContent = `${formatThaiDate(bar.day)} — เข้าใช้ ${bar.count.toLocaleString()} ครั้ง`;
}

function selectHeatCell(el, cell) {
  document.querySelectorAll('#heatmap-grid .heatmap-cell').forEach((c) => c.classList.remove('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim'));
  el.classList.add('ring-2', 'ring-primary', 'dark:ring-primary-fixed-dim');
  document.getElementById('heatmap-detail').textContent =
    `วัน${WEEKDAY_LABELS[cell.day]} ช่วง ${String(cell.hour).padStart(2, '0')}:00–${String((cell.hour + 1) % 24).padStart(2, '0')}:00 — เข้าใช้ ${cell.count.toLocaleString()} ครั้ง`;
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
        <div class="space-y-2">
          <div class="flex justify-between items-center text-sm font-bold">
            <span>${entry.dept}</span>
            <span class="font-label-code">${entry.pct}% (${entry.count.toLocaleString()} ครั้ง)</span>
          </div>
          <div class="w-full bg-surface-container dark:bg-dm-border rounded-full h-2">
            <div class="${DEPT_COLORS[i % DEPT_COLORS.length]} h-2 rounded-full" style="width: ${entry.pct}%"></div>
          </div>
        </div>
      `,
      )
      .join('');
  } else {
    deptContainer.innerHTML = '<p class="text-body-md text-text-secondary">ไม่มีข้อมูลในช่วงเวลานี้</p>';
  }

  document.getElementById('peak-text').textContent =
    stats.peak.count > 0
      ? `ช่วงที่มีคนใช้มากที่สุด: วัน${WEEKDAY_LABELS[stats.peak.day]} ประมาณ ${stats.peak.hour}:00 น. (${stats.peak.count} ครั้ง)`
      : 'ข้อมูลยังไม่เพียงพอที่จะระบุช่วงเวลาที่มีคนใช้มากที่สุด';

  const heatmapContainer = document.getElementById('heatmap-grid');
  document.getElementById('heatmap-detail').textContent = 'แตะหรือคลิกช่องในตารางเพื่อดูจำนวนคนเข้าใช้ตามวันและเวลา';
  heatmapContainer.innerHTML = '';
  stats.heatCells.forEach((cell) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    const label = `วัน${WEEKDAY_LABELS[cell.day]} ${cell.hour}:00 — ${cell.count} ครั้ง`;
    btn.title = label;
    btn.setAttribute('aria-label', label);
    btn.className = `heatmap-cell appearance-none border-0 p-0 hover:ring-2 hover:ring-primary/50 focus-visible:ring-2 focus-visible:ring-primary transition-all cursor-pointer outline-none ${cell.shade}`;
    btn.addEventListener('click', () => selectHeatCell(btn, cell));
    heatmapContainer.appendChild(btn);
  });
}

function load() {
  const month = new Date().toISOString().slice(0, 7);
  apiFetch(`/admin/reports?month=${month}`).then((rows) => {
    allRows = rows;
    render();
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
});
