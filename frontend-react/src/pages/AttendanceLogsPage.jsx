import { useEffect, useMemo, useState } from 'react'
import AdminSidebar from '../components/AdminSidebar'
import { API_BASE, apiFetch } from '../api'

export default function AttendanceLogsPage() {
  const [allRows, setAllRows] = useState(null)
  const [search, setSearch] = useState('')
  const [actionFilter, setActionFilter] = useState('')
  const [dateFilter, setDateFilter] = useState('')

  async function loadReports(params, { silent = false } = {}) {
    if (!silent) setAllRows(null)
    const data = await apiFetch(`/admin/reports?${params.toString()}`)
    setAllRows(data)
  }

  useEffect(() => {
    const params = new URLSearchParams({ month: new Date().toISOString().slice(0, 7) })
    loadReports(params)
    // Poll so newly-arrived check-ins/check-outs show up without a manual
    // reload — silent so the table doesn't flash back to "loading" each time.
    const id = setInterval(() => loadReports(params, { silent: true }), 20000)
    return () => clearInterval(id)
  }, [])

  const filteredRows = useMemo(() => {
    if (!allRows) return []
    const q = search.toLowerCase()
    return allRows.filter((r) => {
      if (actionFilter && r.type !== actionFilter) return false
      if (!q) return true
      const haystack = `${r.first_name} ${r.last_name} ${r.student_id} ${r.department || ''}`.toLowerCase()
      return haystack.includes(q)
    })
  }, [allRows, search, actionFilter])

  const stats = useMemo(() => {
    const rows = allRows || []
    const lastByUser = {}
    rows.forEach((r) => {
      const prev = lastByUser[r.student_id]
      if (!prev || new Date(r.timestamp) > new Date(prev.timestamp)) lastByUser[r.student_id] = r
    })
    const currentlyInside = Object.values(lastByUser).filter((r) => r.type === 'in').length

    const byUser = {}
    rows.forEach((r) => {
      ;(byUser[r.student_id] = byUser[r.student_id] || []).push(r)
    })
    const durations = []
    Object.values(byUser).forEach((events) => {
      const sorted = [...events].sort((a, b) => new Date(a.timestamp) - new Date(b.timestamp))
      let openIn = null
      sorted.forEach((ev) => {
        if (ev.type === 'in') {
          openIn = ev.timestamp
        } else if (ev.type === 'out' && openIn) {
          durations.push((new Date(ev.timestamp) - new Date(openIn)) / 60000)
          openIn = null
        }
      })
    })
    const avgMinutes = durations.length ? Math.round(durations.reduce((a, b) => a + b, 0) / durations.length) : 0

    return { total: rows.length, currentlyInside, avgMinutes, hasDurations: durations.length > 0 }
  }, [allRows])

  function handleDateApply() {
    if (!dateFilter) return
    loadReports(new URLSearchParams({ date: dateFilter }))
  }

  function handleDateClear() {
    setDateFilter('')
    loadReports(new URLSearchParams({ month: new Date().toISOString().slice(0, 7) }))
  }

  return (
    <div className="flex min-h-screen text-text-primary dark:text-inverse-on-surface bg-background dark:bg-dm-bg transition-colors duration-200">
      <AdminSidebar active="/admin/attendance-logs" />
      <main className="md:ml-64 flex-1 flex flex-col min-h-screen">
        <section className="bg-gradient-to-br from-primary to-primary-container pt-12 pb-24 px-gutter relative overflow-hidden">
          <div className="absolute inset-0 book-spine-pattern opacity-20" />
          <div className="max-w-7xl mx-auto relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
            <div>
              <h2 className="text-on-primary font-headline-xl text-headline-xl mb-2">ประวัติการเช็คชื่อทั้งหมด</h2>
              <p className="text-on-primary/80 font-body-lg text-body-lg max-w-2xl">
                รายการเช็คอินและเช็คเอาต์ทุกครั้งของห้องสมุด กรองตามวันที่และแผนกวิชาได้
              </p>
            </div>
            <div className="flex gap-4">
              <a
                className="bg-secondary-container text-on-secondary-container font-bold px-6 py-3 rounded-lg flex items-center gap-2 hover:scale-105 transition-all shadow-lg stamp-shadow"
                href={`${API_BASE}/admin/reports/print`}
                target="_blank"
                rel="noreferrer"
              >
                <span className="material-symbols-outlined">print</span>
                <span>พิมพ์รายงาน</span>
              </a>
            </div>
          </div>
        </section>

        <div className="max-w-7xl mx-auto w-full px-gutter -mt-12 relative z-20 pb-20">
          <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <div className="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
              <span className="p-2 bg-primary/10 dark:bg-primary-fixed-dim/15 text-primary dark:text-primary-fixed-dim rounded-lg w-fit mb-4">
                <span className="material-symbols-outlined">person_add</span>
              </span>
              <p className="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">รายการทั้งหมด</p>
              <p className="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim">
                {allRows ? stats.total.toLocaleString() : '–'}
              </p>
            </div>
            <div className="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
              <span className="p-2 bg-accent-stats/10 text-accent-stats rounded-lg w-fit mb-4">
                <span className="material-symbols-outlined">group</span>
              </span>
              <p className="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">อยู่ในห้องสมุดตอนนี้ (โดยประมาณ)</p>
              <p className="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim">
                {allRows ? stats.currentlyInside.toLocaleString() : '–'}
              </p>
            </div>
            <div className="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
              <span className="p-2 bg-secondary/10 dark:bg-secondary-fixed-dim/15 text-secondary dark:text-secondary-fixed-dim rounded-lg w-fit mb-4">
                <span className="material-symbols-outlined">schedule</span>
              </span>
              <p className="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">ระยะเวลาเฉลี่ยต่อครั้ง</p>
              <p className="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim">
                {allRows && stats.hasDurations ? `${stats.avgMinutes} นาที` : '–'}
              </p>
            </div>
          </div>

          <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border overflow-hidden">
            <div className="p-6 border-b border-outline-variant/30 dark:border-dm-border bg-surface-container-low dark:bg-dm-bg flex flex-wrap gap-4 items-center">
              <div className="flex-1 min-w-[260px] relative">
                <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary">search</span>
                <input
                  className="w-full pl-12 pr-4 py-3 bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent"
                  placeholder="ค้นหาด้วยชื่อ รหัสนักศึกษา หรือแผนกวิชา..."
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>
              <select
                className="bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 font-body-md text-body-md focus:ring-2 focus:ring-primary"
                value={actionFilter}
                onChange={(e) => setActionFilter(e.target.value)}
              >
                <option value="">ทุกประเภท</option>
                <option value="in">เช็คอิน</option>
                <option value="out">เช็คเอาต์</option>
              </select>
              <input
                className="bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 font-body-md text-body-md focus:ring-2 focus:ring-primary"
                type="date"
                value={dateFilter}
                onChange={(e) => setDateFilter(e.target.value)}
              />
              <button
                className="bg-surface-white dark:bg-dm-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 hover:bg-surface-container-high dark:hover:bg-dm-bg transition-colors font-bold text-primary dark:text-primary-fixed-dim"
                type="button"
                onClick={handleDateApply}
              >
                กรองตามวันที่
              </button>
              <button
                className="bg-surface-white dark:bg-dm-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 hover:bg-surface-container-high dark:hover:bg-dm-bg transition-colors font-bold dark:text-inverse-on-surface"
                type="button"
                onClick={handleDateClear}
              >
                เดือนนี้
              </button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead className="bg-surface-container-high dark:bg-dm-bg">
                  <tr>
                    <th className="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">วันเวลา</th>
                    <th className="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">ชื่อนักศึกษา</th>
                    <th className="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">รหัสนักศึกษา</th>
                    <th className="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">แผนกวิชา</th>
                    <th className="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">ประเภท</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant/30 dark:divide-dm-border">
                  {allRows === null && (
                    <tr>
                      <td className="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colSpan={5}>
                        กำลังโหลด…
                      </td>
                    </tr>
                  )}
                  {allRows !== null && filteredRows.length === 0 && (
                    <tr>
                      <td className="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colSpan={5}>
                        ไม่พบรายการที่ตรงกัน
                      </td>
                    </tr>
                  )}
                  {allRows !== null &&
                    filteredRows.map((r, i) => {
                      const isIn = r.type === 'in'
                      const initials = `${(r.first_name || '?')[0]}${(r.last_name || '?')[0]}`.toUpperCase()
                      const formatted = new Date(r.timestamp)
                        .toLocaleString('th-TH', {
                          year: 'numeric',
                          month: '2-digit',
                          day: '2-digit',
                          hour: '2-digit',
                          minute: '2-digit',
                          second: '2-digit',
                        })
                        .replace(',', '')
                      return (
                        <tr key={i} className="hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
                          <td className="px-6 py-4 font-label-code text-label-code text-text-primary dark:text-inverse-on-surface whitespace-nowrap">
                            {formatted}
                          </td>
                          <td className="px-6 py-4">
                            <div className="flex items-center gap-3">
                              <div className="w-8 h-8 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center font-bold text-xs">
                                {initials}
                              </div>
                              <span className="font-body-md text-body-md font-bold dark:text-inverse-on-surface">
                                {r.prefix || ''}
                                {r.first_name} {r.last_name}
                              </span>
                            </div>
                          </td>
                          <td className="px-6 py-4 font-label-code text-label-code text-text-secondary dark:text-dm-text-secondary">
                            {r.student_id}
                          </td>
                          <td className="px-6 py-4 font-body-md text-body-md dark:text-inverse-on-surface">{r.department || '-'}</td>
                          <td className="px-6 py-4">
                            <span
                              className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold ${
                                isIn ? 'bg-status-success/10 text-status-success' : 'bg-warning/10 text-warning'
                              }`}
                            >
                              <span className={`w-1.5 h-1.5 rounded-full ${isIn ? 'bg-status-success' : 'bg-warning'}`} />
                              {isIn ? 'เช็คอิน' : 'เช็คเอาต์'}
                            </span>
                          </td>
                        </tr>
                      )
                    })}
                </tbody>
              </table>
            </div>
            <div className="p-6 bg-surface-container-low dark:bg-dm-bg">
              <p className="text-label-caps font-label-caps text-text-secondary dark:text-dm-text-secondary">พบ {filteredRows.length} รายการ</p>
            </div>
          </div>
        </div>

        <footer className="w-full py-8 mt-auto bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border">
          <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
            <p className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NNTC</p>
            <p className="text-body-md font-body-md text-on-surface-variant dark:text-dm-text-secondary">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
          </div>
        </footer>
      </main>
    </div>
  )
}
