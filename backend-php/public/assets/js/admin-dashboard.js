// Port of frontend-react/src/pages/AdminDashboardPage.jsx.

const WEEKDAY_LABELS = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.'];
const DEPT_COLORS = ['bg-primary', 'bg-secondary', 'bg-accent-stats', 'bg-status-success', 'bg-outline'];

let allRows = null;
let currentView = 'month';

function isoWeekdayIndex(date) {
  return (date.getDay() + 6) % 7; // 0=Mon .. 6=Sun
}

function filterRows(rows, view) {
  const now = new Date();
  if (view === 'today') {
    const todayStr = now.toISOString().slice(0, 10);
    return rows.filter((r) => r.timestamp.slice(0, 10) === todayStr);
  }
  if (view === 'week') {
    const cutoff = new Date(now.getTime() - 7 * 24 * 3600 * 1000);
    return rows.filter((r) => new Date(r.timestamp) >= cutoff);
  }
  return rows;
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
  const dayKeys = Object.keys(dayBuckets).sort();
  const avgDaily = dayKeys.length ? Math.round(total / dayKeys.length) : 0;

  const lastByUser = {};
  filtered.forEach((r) => {
    const prev = lastByUser[r.student_id];
    if (!prev || new Date(r.timestamp) > new Date(prev.timestamp)) lastByUser[r.student_id] = r;
  });
  const currentlyInside = Object.values(lastByUser).filter((r) => r.type === 'in').length;

  const maxDay = dayKeys.length ? Math.max(...dayKeys.map((k) => dayBuckets[k])) : 0;
  const trendBars = dayKeys.map((day) => ({
    day,
    count: dayBuckets[day],
    pct: maxDay ? Math.max(4, Math.round((dayBuckets[day] / maxDay) * 100)) : 4,
  }));

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
  trendContainer.innerHTML = '';
  if (stats.trendBars.length) {
    stats.trendBars.forEach((bar) => {
      const el = document.createElement('div');
      el.title = `${bar.day}: ${bar.count} ครั้ง`;
      el.className = 'flex-1 bg-primary/20 dark:bg-primary-fixed-dim/40 hover:bg-primary/40 dark:hover:bg-primary-fixed-dim/60 rounded-t transition-all cursor-help relative group';
      el.style.height = `${bar.pct}%`;
      trendContainer.appendChild(el);
    });
    document.getElementById('trend-range-start').textContent = stats.dayKeys[0];
    document.getElementById('trend-range-end').textContent = stats.dayKeys[stats.dayKeys.length - 1];
    document.getElementById('trend-range-start').classList.remove('invisible');
    document.getElementById('trend-range-end').classList.remove('invisible');
  } else {
    trendContainer.innerHTML = '<p class="text-body-md text-text-secondary dark:text-dm-text-secondary m-auto">ไม่มีข้อมูลในช่วงเวลานี้</p>';
    document.getElementById('trend-range-start').classList.add('invisible');
    document.getElementById('trend-range-end').classList.add('invisible');
  }

  const deptContainer = document.getElementById('dept-bars');
  if (stats.deptEntries.length) {
    deptContainer.innerHTML = stats.deptEntries
      .map(
        (entry, i) => `
        <div class="space-y-2">
          <div class="flex justify-between items-center text-sm font-bold">
            <span>${entry.dept}</span>
            <span class="font-label-code">${entry.pct}%</span>
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
  heatmapContainer.innerHTML = stats.heatCells
    .map(
      (cell) =>
        `<div title="${WEEKDAY_LABELS[cell.day]} ${cell.hour}:00 — ${cell.count} ครั้ง" class="heatmap-cell hover:ring-2 hover:ring-primary/50 transition-all cursor-pointer ${cell.shade}"></div>`,
    )
    .join('');
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
