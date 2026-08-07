import { useEffect, useState } from 'react'
import AdminSidebar from '../components/AdminSidebar'
import { apiFetch } from '../api'

export default function MembersManagementPage() {
  const [search, setSearch] = useState('')
  const [department, setDepartment] = useState('')
  const [level, setLevel] = useState('')
  const [yearLevel, setYearLevel] = useState('')
  const [rows, setRows] = useState(null)

  async function loadMembers() {
    const params = new URLSearchParams()
    if (search.trim()) params.set('search', search.trim())
    if (department.trim()) params.set('department', department.trim())
    if (level) params.set('level', level)
    if (yearLevel) params.set('year_level', yearLevel)
    setRows(null)
    const data = await apiFetch(`/admin/members?${params.toString()}`)
    setRows(data)
  }

  useEffect(() => {
    loadMembers()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  function handleSearchKeyDown(e) {
    if (e.key === 'Enter') loadMembers()
  }

  return (
    <div className="bg-background dark:bg-dm-bg font-body-md text-on-background dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
      <AdminSidebar active="/admin/members-management" />
      <main className="md:ml-64 flex-grow flex flex-col min-h-screen">
        <header className="bg-primary text-on-primary relative overflow-hidden h-[240px] flex items-end pb-20 px-gutter">
          <div className="absolute inset-0 vertical-pattern opacity-10" />
          <div className="max-w-7xl w-full mx-auto relative z-10 flex flex-col md:flex-row justify-between items-end gap-4">
            <div>
              <h2 className="font-headline-xl text-headline-xl mb-2">ทำเนียบสมาชิก</h2>
              <p className="font-body-lg text-body-lg text-on-primary/80">
                รายชื่อนักศึกษาที่ได้รับการอนุมัติ บัญชีจะถูกสร้างขึ้นอัตโนมัติเมื่อนักศึกษาสมัครสมาชิก
              </p>
            </div>
            <div className="hidden md:flex gap-4 mb-2">
              <div className="bg-primary-container/40 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
                <p className="text-label-caps font-label-caps opacity-70">สมาชิกทั้งหมด</p>
                <p className="text-headline-md font-label-code">{rows ? rows.length : '–'}</p>
              </div>
            </div>
          </div>
        </header>

        <section className="max-w-7xl w-full mx-auto px-gutter -mt-10 relative z-20 py-8 flex-grow">
          <div className="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-md border border-outline-variant/30 dark:border-dm-border flex flex-col lg:flex-row gap-6 items-center mb-8">
            <div className="w-full lg:flex-1 relative">
              <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">search</span>
              <input
                className="w-full pl-12 pr-4 py-3 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md"
                placeholder="ค้นหาด้วยรหัสนักศึกษา ชื่อ หรือชื่อผู้ใช้..."
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                onKeyDown={handleSearchKeyDown}
              />
            </div>
            <div className="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
              <div className="flex flex-col gap-1 min-w-[180px]">
                <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">แผนกวิชา</label>
                <input
                  className="bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary"
                  placeholder="ทั้งหมด"
                  type="text"
                  value={department}
                  onChange={(e) => setDepartment(e.target.value)}
                />
              </div>
              <div className="flex flex-col gap-1 min-w-[140px]">
                <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">ระดับชั้น</label>
                <select
                  className="bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary"
                  value={level}
                  onChange={(e) => setLevel(e.target.value)}
                >
                  <option value="">ทุกระดับชั้น</option>
                  <option value="ปวช">ปวช.</option>
                  <option value="ปวส">ปวส.</option>
                </select>
              </div>
              <div className="flex flex-col gap-1 min-w-[140px]">
                <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">ชั้นปี</label>
                <select
                  className="bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary"
                  value={yearLevel}
                  onChange={(e) => setYearLevel(e.target.value)}
                >
                  <option value="">ทุกชั้นปี</option>
                  <option value="1">1</option>
                  <option value="2">2</option>
                  <option value="3">3</option>
                </select>
              </div>
              <button
                className="self-end px-6 py-3 bg-surface-container-high dark:bg-dm-bg hover:bg-surface-container-highest dark:hover:bg-dm-border rounded-lg font-bold text-primary dark:text-primary-fixed-dim flex items-center gap-2 transition-colors h-[46px]"
                type="button"
                onClick={loadMembers}
              >
                <span className="material-symbols-outlined">filter_list</span>
                กรองข้อมูล
              </button>
            </div>
          </div>

          <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-sm border border-outline-variant dark:border-dm-border overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left border-collapse">
                <thead>
                  <tr className="bg-surface-container-low dark:bg-dm-bg border-b border-outline-variant dark:border-dm-border">
                    <th className="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">รหัสนักศึกษา</th>
                    <th className="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">ชื่อ-สกุล</th>
                    <th className="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">แผนกวิชา</th>
                    <th className="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-center">ชั้นปี</th>
                    <th className="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-right">เข้าใช้ล่าสุด</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-outline-variant/30 dark:divide-dm-border">
                  {rows === null && (
                    <tr>
                      <td className="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colSpan={5}>
                        กำลังโหลด…
                      </td>
                    </tr>
                  )}
                  {rows !== null && rows.length === 0 && (
                    <tr>
                      <td className="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colSpan={5}>
                        ไม่พบสมาชิกตามเงื่อนไขนี้
                      </td>
                    </tr>
                  )}
                  {rows !== null &&
                    rows.map((r) => (
                      <tr key={r.user_id} className="hover:bg-surface-container-low/50 dark:hover:bg-dm-bg transition-colors">
                        <td className="px-6 py-4 font-label-code text-primary dark:text-primary-fixed-dim">{r.student_id}</td>
                        <td className="px-6 py-4 font-bold text-on-surface dark:text-inverse-on-surface">
                          {r.prefix || ''}
                          {r.first_name} {r.last_name}
                        </td>
                        <td className="px-6 py-4 text-on-surface-variant dark:text-dm-text-secondary">{r.department || '-'}</td>
                        <td className="px-6 py-4 text-center font-label-code dark:text-inverse-on-surface">{r.year_level || '-'}</td>
                        <td className="px-6 py-4 text-right text-on-surface-variant dark:text-dm-text-secondary text-sm">
                          {r.last_visit
                            ? new Date(r.last_visit).toLocaleString('th-TH', {
                                day: '2-digit',
                                month: 'short',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                              })
                            : 'ยังไม่เคยเข้าใช้'}
                        </td>
                      </tr>
                    ))}
                </tbody>
              </table>
            </div>
            <div className="px-6 py-4 bg-surface-container-low dark:bg-dm-bg">
              <p className="text-sm text-on-surface-variant dark:text-dm-text-secondary">พบ {rows ? rows.length : 0} รายการ</p>
            </div>
          </div>
        </section>

        <footer className="w-full py-8 bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border mt-auto">
          <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
            <span className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">พอร์ทัลเจ้าหน้าที่ห้องสมุด NNTC</span>
            <p className="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-sm">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
          </div>
        </footer>
      </main>
    </div>
  )
}
