import { useEffect, useMemo, useState } from 'react'
import AdminSidebar from '../components/AdminSidebar'
import { API_BASE, apiFetch } from '../api'

const WEEKDAY_LABELS = ['จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.', 'อา.']
const DEPT_COLORS = ['bg-primary', 'bg-secondary', 'bg-accent-stats', 'bg-status-success', 'bg-outline']

function isoWeekdayIndex(date) {
  return (date.getDay() + 6) % 7 // 0=Mon .. 6=Sun
}

function filterRows(allRows, view) {
  const now = new Date()
  if (view === 'today') {
    const todayStr = now.toISOString().slice(0, 10)
    return allRows.filter((r) => r.timestamp.slice(0, 10) === todayStr)
  }
  if (view === 'week') {
    const cutoff = new Date(now.getTime() - 7 * 24 * 3600 * 1000)
    return allRows.filter((r) => new Date(r.timestamp) >= cutoff)
  }
  return allRows
}

export default function AdminDashboardPage() {
  const [allRows, setAllRows] = useState(null)
  const [view, setView] = useState('month')

  useEffect(() => {
    function load() {
      const month = new Date().toISOString().slice(0, 7)
      apiFetch(`/admin/reports?month=${month}`).then(setAllRows)
    }
    load()
    // "อยู่ในห้องสมุดตอนนี้" is computed client-side from this snapshot, so
    // without polling it goes stale the moment someone checks in/out after
    // this tab loaded — poll instead of only fetching once on mount.
    const id = setInterval(load, 20000)
    return () => clearInterval(id)
  }, [])

  const stats = useMemo(() => {
    if (!allRows) return null
    const rows = filterRows(allRows, view)
    const total = rows.length
    const uniqueUsers = new Set(rows.map((r) => r.student_id)).size

    const dayBuckets = {}
    rows.forEach((r) => {
      const day = r.timestamp.slice(0, 10)
      dayBuckets[day] = (dayBuckets[day] || 0) + 1
    })
    const dayKeys = Object.keys(dayBuckets).sort()
    const avgDaily = dayKeys.length ? Math.round(total / dayKeys.length) : 0

    const lastByUser = {}
    rows.forEach((r) => {
      const prev = lastByUser[r.student_id]
      if (!prev || new Date(r.timestamp) > new Date(prev.timestamp)) lastByUser[r.student_id] = r
    })
    const currentlyInside = Object.values(lastByUser).filter((r) => r.type === 'in').length

    const maxDay = dayKeys.length ? Math.max(...dayKeys.map((k) => dayBuckets[k])) : 0
    const trendBars = dayKeys.map((day) => ({
      day,
      count: dayBuckets[day],
      pct: maxDay ? Math.max(4, Math.round((dayBuckets[day] / maxDay) * 100)) : 4,
    }))

    const deptCounts = {}
    rows.forEach((r) => {
      const dept = r.department || 'ไม่ระบุแผนก'
      deptCounts[dept] = (deptCounts[dept] || 0) + 1
    })
    const deptEntries = Object.entries(deptCounts)
      .sort((a, b) => b[1] - a[1])
      .slice(0, 5)
      .map(([dept, count]) => ({ dept, count, pct: total ? Math.round((count / total) * 100) : 0 }))

    const counts = Array.from({ length: 7 }, () => Array(24).fill(0))
    rows.forEach((r) => {
      const d = new Date(r.timestamp)
      counts[isoWeekdayIndex(d)][d.getHours()]++
    })
    const maxCell = Math.max(1, ...counts.flat())
    let peak = { count: -1, day: 0, hour: 0 }
    const heatCells = []
    for (let day = 0; day < 7; day++) {
      for (let hour = 0; hour < 24; hour++) {
        const count = counts[day][hour]
        if (count > peak.count) peak = { count, day, hour }
        const ratio = count / maxCell
        let shade = 'bg-surface-container dark:bg-dm-border'
        if (ratio > 0.75) shade = 'bg-primary dark:bg-primary-fixed-dim'
        else if (ratio > 0.5) shade = 'bg-primary/70 dark:bg-primary-fixed-dim/70'
        else if (ratio > 0.25) shade = 'bg-primary/40 dark:bg-primary-fixed-dim/50'
        else if (ratio > 0) shade = 'bg-primary/20 dark:bg-primary-fixed-dim/35'
        heatCells.push({ day, hour, count, shade })
      }
    }

    return {
      total,
      uniqueUsers,
      avgDaily,
      currentlyInside,
      dayKeys,
      trendBars,
      deptEntries,
      heatCells,
      peak,
    }
  }, [allRows, view])

  return (
    <div className="bg-background dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex transition-colors duration-200">
      <AdminSidebar active="/admin/dashboard" />
      <main className="flex-1 md:ml-64 flex flex-col min-h-screen">
        <header className="admin-hero-pattern pt-8 pb-32 px-gutter">
          <div className="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
              <h2 className="text-white font-headline-xl text-headline-xl mb-2">ภาพรวมการใช้งานห้องสมุด</h2>
              <p className="text-on-primary-container font-body-lg text-body-lg">
                {!stats
                  ? 'กำลังโหลดข้อมูลการเข้าใช้…'
                  : stats.total
                    ? `มีการเข้าใช้ ${stats.total.toLocaleString()} ครั้ง จากนักศึกษา ${stats.uniqueUsers.toLocaleString()} คน ในช่วงเวลาที่เลือก`
                    : 'ไม่มีข้อมูลการเข้าใช้ในช่วงเวลาที่เลือก'}
              </p>
            </div>
            <div className="flex items-center gap-3 bg-white/10 backdrop-blur-md p-2 rounded-xl border border-white/20">
              <div className="px-4 py-2">
                <p className="text-[10px] text-white/60 font-label-caps mb-1 uppercase">ช่วงเวลา</p>
                <select
                  className="bg-transparent text-white border-none focus:ring-0 font-bold p-0 text-body-md cursor-pointer"
                  value={view}
                  onChange={(e) => setView(e.target.value)}
                >
                  <option value="month">เดือนนี้</option>
                  <option value="week">7 วันล่าสุด</option>
                  <option value="today">วันนี้</option>
                </select>
              </div>
              <div className="h-10 w-[1px] bg-white/20" />
              <a
                className="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-container transition-all active:scale-95"
                href={`${API_BASE}/admin/reports/print`}
                target="_blank"
                rel="noreferrer"
              >
                <span className="material-symbols-outlined">print</span>
                <span className="font-label-caps">พิมพ์รายงาน</span>
              </a>
            </div>
          </div>

          <div className="max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-12 relative z-10 translate-y-16">
            <div className="bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
              <div className="w-12 h-12 rounded-full bg-primary/10 dark:bg-primary-fixed-dim/15 flex items-center justify-center text-primary dark:text-primary-fixed-dim mb-4">
                <span className="material-symbols-outlined">group</span>
              </div>
              <p className="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">จำนวนรายการทั้งหมด</p>
              <h3 className="text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">
                {stats ? stats.total.toLocaleString() : '–'}
              </h3>
            </div>
            <div className="bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
              <div className="w-12 h-12 rounded-full bg-secondary-container/10 dark:bg-secondary-fixed-dim/15 flex items-center justify-center text-secondary dark:text-secondary-fixed-dim mb-4">
                <span className="material-symbols-outlined">person_search</span>
              </div>
              <p className="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">ผู้ใช้งานไม่ซ้ำคน</p>
              <h3 className="text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">
                {stats ? stats.uniqueUsers.toLocaleString() : '–'}
              </h3>
            </div>
            <div className="bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
              <div className="w-12 h-12 rounded-full bg-accent-stats/10 dark:bg-accent-stats/25 flex items-center justify-center text-accent-stats mb-4">
                <span className="material-symbols-outlined">calendar_today</span>
              </div>
              <p className="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">เฉลี่ยต่อวัน</p>
              <h3 className="text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">
                {stats ? stats.avgDaily.toLocaleString() : '–'}
              </h3>
            </div>
            <div className="bg-primary-container rounded-xl p-6 shadow-stamp border border-primary relative overflow-hidden">
              <div className="absolute top-0 right-0 p-4">
                <div className="w-3 h-3 bg-white rounded-full stamp-pulse" />
              </div>
              <div className="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white mb-4">
                <span className="material-symbols-outlined">meeting_room</span>
              </div>
              <p className="text-on-primary-container font-label-caps uppercase text-xs mb-1">อยู่ในห้องสมุดตอนนี้ (โดยประมาณ)</p>
              <h3 className="text-headline-lg font-headline-lg text-white font-bold font-label-code">
                {stats ? stats.currentlyInside.toLocaleString() : '–'}
              </h3>
            </div>
          </div>
        </header>

        <div className="max-w-7xl mx-auto w-full px-gutter pt-24 pb-12 grid grid-cols-1 lg:grid-cols-3 gap-8">
          <section className="lg:col-span-2 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
            <div className="mb-8">
              <h4 className="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">แนวโน้มการเข้าใช้</h4>
              <p className="text-on-surface-variant dark:text-dm-text-secondary text-body-md">จำนวนการเช็คอิน/เช็คเอาต์รายวันในช่วงเวลาที่เลือก</p>
            </div>
            <div className="h-64 flex items-end justify-between gap-1 px-2 relative">
              {!stats ? (
                <p className="text-body-md text-text-secondary dark:text-dm-text-secondary m-auto">กำลังโหลด…</p>
              ) : stats.trendBars.length ? (
                stats.trendBars.map((bar) => (
                  <div
                    key={bar.day}
                    title={`${bar.day}: ${bar.count} ครั้ง`}
                    className="flex-1 bg-primary/20 dark:bg-primary-fixed-dim/40 hover:bg-primary/40 dark:hover:bg-primary-fixed-dim/60 rounded-t transition-all cursor-help relative group"
                    style={{ height: `${bar.pct}%` }}
                  />
                ))
              ) : (
                <p className="text-body-md text-text-secondary dark:text-dm-text-secondary m-auto">ไม่มีข้อมูลในช่วงเวลานี้</p>
              )}
            </div>
            <div className="flex justify-between mt-4 text-xs font-label-caps text-outline dark:text-dm-text-secondary uppercase tracking-wider">
              {stats && stats.dayKeys.length ? (
                <>
                  <span>{stats.dayKeys[0]}</span>
                  <span>{stats.dayKeys[stats.dayKeys.length - 1]}</span>
                </>
              ) : null}
            </div>
          </section>

          <section className="bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
            <h4 className="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim mb-6">แผนกที่เข้าใช้มากที่สุด</h4>
            <div className="space-y-6">
              {!stats ? (
                <p className="text-body-md text-text-secondary dark:text-dm-text-secondary">กำลังโหลด…</p>
              ) : stats.deptEntries.length ? (
                stats.deptEntries.map((entry, i) => (
                  <div className="space-y-2" key={entry.dept}>
                    <div className="flex justify-between items-center text-sm font-bold">
                      <span>{entry.dept}</span>
                      <span className="font-label-code">{entry.pct}%</span>
                    </div>
                    <div className="w-full bg-surface-container dark:bg-dm-border rounded-full h-2">
                      <div className={`${DEPT_COLORS[i % DEPT_COLORS.length]} h-2 rounded-full`} style={{ width: `${entry.pct}%` }} />
                    </div>
                  </div>
                ))
              ) : (
                <p className="text-body-md text-text-secondary">ไม่มีข้อมูลในช่วงเวลานี้</p>
              )}
            </div>
            <div className="mt-10 p-4 bg-surface-container-low dark:bg-dm-bg rounded-xl border border-dashed border-outline-variant dark:border-dm-border flex items-center gap-4">
              <span className="material-symbols-outlined text-primary dark:text-primary-fixed-dim text-3xl">lightbulb</span>
              <p className="text-sm text-on-surface-variant dark:text-dm-text-secondary italic">
                {!stats
                  ? 'กำลังคำนวณช่วงเวลาที่มีคนใช้มากที่สุด…'
                  : stats.peak.count > 0
                    ? `ช่วงที่มีคนใช้มากที่สุด: วัน${WEEKDAY_LABELS[stats.peak.day]} ประมาณ ${stats.peak.hour}:00 น. (${stats.peak.count} ครั้ง)`
                    : 'ข้อมูลยังไม่เพียงพอที่จะระบุช่วงเวลาที่มีคนใช้มากที่สุด'}
              </p>
            </div>
          </section>

          <section className="lg:col-span-3 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
            <div className="mb-8">
              <h4 className="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">การเข้าใช้ตามวันและเวลา</h4>
              <p className="text-on-surface-variant dark:text-dm-text-secondary text-body-md">
                ความหนาแน่นของการเข้าใช้ในช่วงเวลาที่เลือก (แถว = วันในสัปดาห์, คอลัมน์ = ชั่วโมง)
              </p>
            </div>
            <div className="overflow-x-auto pb-4">
              <div className="min-w-[900px] grid grid-cols-[auto_1fr] gap-4">
                <div className="grid grid-rows-7 gap-1 text-[10px] text-outline dark:text-dm-text-secondary font-label-caps pt-6">
                  {WEEKDAY_LABELS.map((label) => (
                    <span key={label}>{label}</span>
                  ))}
                </div>
                <div>
                  <div className="flex justify-between text-[10px] text-outline dark:text-dm-text-secondary font-label-caps mb-2 px-1">
                    <span>0น.</span>
                    <span>4น.</span>
                    <span>8น.</span>
                    <span>12น.</span>
                    <span>16น.</span>
                    <span>20น.</span>
                    <span>24น.</span>
                  </div>
                  <div className="grid grid-flow-col grid-rows-7 gap-1" style={{ gridTemplateColumns: 'repeat(24, minmax(0, 1fr))' }}>
                    {stats &&
                      stats.heatCells.map((cell) => (
                        <div
                          key={`${cell.day}-${cell.hour}`}
                          title={`${WEEKDAY_LABELS[cell.day]} ${cell.hour}:00 — ${cell.count} ครั้ง`}
                          className={`heatmap-cell hover:ring-2 hover:ring-primary/50 transition-all cursor-pointer ${cell.shade}`}
                        />
                      ))}
                  </div>
                </div>
              </div>
            </div>
            <div className="flex justify-end items-center gap-2 mt-4 text-[10px] text-outline dark:text-dm-text-secondary font-label-caps uppercase">
              <span>น้อย</span>
              <div className="w-3 h-3 bg-surface-container dark:bg-dm-border rounded-sm" />
              <div className="w-3 h-3 bg-primary/20 dark:bg-primary-fixed-dim/35 rounded-sm" />
              <div className="w-3 h-3 bg-primary/40 dark:bg-primary-fixed-dim/50 rounded-sm" />
              <div className="w-3 h-3 bg-primary/70 dark:bg-primary-fixed-dim/70 rounded-sm" />
              <div className="w-3 h-3 bg-primary dark:bg-primary-fixed-dim rounded-sm" />
              <span>มาก</span>
            </div>
          </section>
        </div>

        <footer className="w-full py-8 mt-auto bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border">
          <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
            <span className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NNTC</span>
            <p className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary opacity-80">
              © 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์
            </p>
          </div>
        </footer>
      </main>
    </div>
  )
}
