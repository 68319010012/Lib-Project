import { useEffect, useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import { apiFetch } from '../api'
import { useAuth } from '../context/AuthContext'

function formatClock(seconds) {
  const hrs = Math.floor(seconds / 3600)
  const mins = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60
  return [hrs, mins, secs].map((n) => n.toString().padStart(2, '0')).join(':')
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const { user, refresh } = useAuth()
  const [history, setHistory] = useState([])
  const [busy, setBusy] = useState(false)
  const [now, setNow] = useState(() => Date.now())

  const last = history[0]
  const isCheckedIn = !!last && last.type === 'in'
  const checkedInSince = isCheckedIn ? new Date(last.timestamp) : null

  useEffect(() => {
    if (!isCheckedIn) return undefined
    const id = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(id)
  }, [isCheckedIn])

  async function loadHistory() {
    const rows = await apiFetch('/me/history?limit=20')
    setHistory(rows)
  }

  useEffect(() => {
    loadHistory()
  }, [])

  const elapsed = useMemo(() => {
    if (!checkedInSince) return '--:--:--'
    const diff = Math.max(0, Math.floor((now - checkedInSince.getTime()) / 1000))
    return formatClock(diff)
  }, [checkedInSince, now])

  async function handleStamp() {
    setBusy(true)
    try {
      await apiFetch('/checkin', { method: 'POST' })
      await loadHistory()
    } catch (err) {
      alert(err.message)
    } finally {
      setBusy(false)
    }
  }

  async function handleLogout() {
    await apiFetch('/logout', { method: 'POST' })
    await refresh()
    navigate('/login')
  }

  const displayName = user ? `${user.prefix || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username : ''

  return (
    <div className="bg-surface dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex flex-col overflow-x-hidden transition-colors duration-200">
      <header className="bg-primary dark:bg-primary-container shadow-md fixed top-0 z-50 w-full">
        <div className="flex justify-between items-center w-full px-gutter h-16 max-w-7xl mx-auto text-on-primary">
          <div className="flex items-center gap-4">
            <span className="text-headline-md font-headline-md font-bold text-on-primary">NNTC Library</span>
            <nav className="hidden md:flex items-center gap-6 ml-8">
              <Link className="text-on-primary border-b-2 border-secondary-container pb-1 font-label-caps text-label-caps" to="/dashboard">
                หน้าหลัก
              </Link>
              <Link className="text-on-primary/70 hover:text-on-primary transition-colors font-label-caps text-label-caps" to="/profile">
                โปรไฟล์
              </Link>
            </nav>
          </div>
          <div className="flex items-center gap-4">
            <ThemeToggle className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors" iconClassName="text-xl" />
            <button className="text-on-primary/80 hover:text-on-primary font-label-caps text-label-caps" type="button" onClick={handleLogout}>
              ออกจากระบบ
            </button>
            <div className="flex items-center gap-3">
              <div className="text-right hidden sm:block">
                <p className="font-bold text-sm">{displayName || '...'}</p>
                <p className="text-xs opacity-75">รหัส: {user?.student_id || user?.username || '...'}</p>
              </div>
            </div>
          </div>
        </div>
      </header>

      <main className="flex-grow pt-16">
        <section className="relative bg-primary-container h-64 overflow-hidden flex flex-col justify-center">
          <div className="absolute inset-0 book-spine-pattern opacity-20" />
          <div className="relative z-10 max-w-7xl mx-auto px-gutter w-full">
            <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
              <div>
                <div className="inline-flex items-center gap-2 bg-surface-container-highest/20 text-on-primary px-3 py-1 rounded-full mb-4 border border-on-primary/20">
                  <span className={`w-2.5 h-2.5 rounded-full ${isCheckedIn ? 'bg-status-success' : 'bg-outline'}`} />
                  <span className="text-label-caps font-label-caps">
                    {isCheckedIn ? 'อยู่ในห้องสมุดตอนนี้' : 'ยังไม่ได้เช็คอินวันนี้'}
                  </span>
                </div>
                <h1 className="text-headline-xl font-headline-xl text-on-primary mb-2">
                  ยินดีต้อนรับ{displayName ? ` ${displayName}` : ''}
                </h1>
                <p className="text-on-primary/80 font-body-lg text-body-lg">
                  {[user?.department, user?.student_id ? `รหัส: ${user.student_id}` : null].filter(Boolean).join(' • ')}
                </p>
              </div>
            </div>
          </div>
        </section>

        <div className="max-w-7xl mx-auto px-gutter -mt-16 relative z-20 pb-12">
          <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <div className="lg:col-span-8 flex flex-col gap-8">
              <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-md p-8 flex flex-col items-center justify-center text-center relative overflow-hidden">
                <div className="absolute top-0 right-0 p-4 opacity-5 pointer-events-none">
                  <span className="material-symbols-outlined text-9xl">library_books</span>
                </div>
                {isCheckedIn && (
                  <div className="mb-6">
                    <p className="text-label-caps font-label-caps text-secondary mb-1">เช็คอินตั้งแต่</p>
                    <span className="text-headline-xl font-label-code text-primary tracking-widest">{elapsed}</span>
                  </div>
                )}
                <div className="relative mb-8">
                  {isCheckedIn && (
                    <div className="absolute -inset-4 rounded-full stamp-pulse bg-status-success/20" />
                  )}
                  <button
                    className={`relative w-48 h-48 rounded-full text-on-primary shadow-xl hover:scale-105 active:scale-95 transition-all duration-300 flex flex-col items-center justify-center gap-2 group disabled:opacity-60 ${
                      isCheckedIn ? 'bg-secondary' : 'bg-status-success'
                    }`}
                    type="button"
                    disabled={busy}
                    onClick={handleStamp}
                  >
                    <span className="material-symbols-outlined text-6xl group-hover:rotate-12 transition-transform">
                      {isCheckedIn ? 'logout' : 'sync_alt'}
                    </span>
                    <span className="font-headline-md text-headline-md">{isCheckedIn ? 'เช็คเอาต์' : 'เช็คอิน'}</span>
                  </button>
                </div>
                <p className="text-body-md text-text-secondary dark:text-dm-text-secondary max-w-sm mx-auto">
                  กดปุ่มด้านบนเพื่อบันทึกการเข้า-ออกห้องสมุด NNTC
                </p>
              </div>

              <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-md p-6">
                <div className="flex items-center justify-between mb-6">
                  <h3 className="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim">
                    ประวัติการเข้าใช้ล่าสุด
                  </h3>
                </div>
                <div className="space-y-4">
                  {history.length === 0 ? (
                    <p className="text-body-md text-text-secondary dark:text-dm-text-secondary">ยังไม่มีประวัติการเช็คอิน</p>
                  ) : (
                    history.map((row, i) => {
                      const isIn = row.type === 'in'
                      const formatted = new Date(row.timestamp).toLocaleString('th-TH', {
                        day: '2-digit',
                        month: 'short',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })
                      return (
                        <div
                          key={i}
                          className={`flex items-center justify-between p-4 rounded-lg bg-surface-container-low dark:bg-dm-bg border-l-4 ${
                            isIn ? 'border-status-success' : 'border-secondary'
                          }`}
                        >
                          <div className="flex items-center gap-4">
                            <div
                              className={`w-10 h-10 rounded-full flex items-center justify-center ${
                                isIn ? 'bg-status-success/10 text-status-success' : 'bg-secondary/10 text-secondary'
                              }`}
                            >
                              <span className="material-symbols-outlined">{isIn ? 'login' : 'logout'}</span>
                            </div>
                            <div>
                              <p className="font-bold text-text-primary dark:text-inverse-on-surface">
                                {isIn ? 'เช็คอิน' : 'เช็คเอาต์'}
                              </p>
                              <p className="text-xs text-text-secondary dark:text-dm-text-secondary">{formatted}</p>
                            </div>
                          </div>
                        </div>
                      )
                    })
                  )}
                </div>
              </div>
            </div>

            <div className="lg:col-span-4 flex flex-col gap-6">
              <div className="grid grid-cols-2 gap-4">
                <Link
                  className="bg-surface-white dark:bg-dm-surface p-4 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col items-center gap-2 group"
                  to="/profile"
                >
                  <div className="w-12 h-12 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center group-hover:bg-primary group-hover:text-on-primary transition-colors">
                    <span className="material-symbols-outlined">person_edit</span>
                  </div>
                  <span className="text-label-caps font-label-caps text-[10px]">โปรไฟล์</span>
                </Link>
                <button
                  className="bg-surface-white dark:bg-dm-surface p-4 rounded-xl shadow-sm hover:shadow-md transition-all flex flex-col items-center gap-2 group"
                  type="button"
                  onClick={handleLogout}
                >
                  <div className="w-12 h-12 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center group-hover:bg-primary group-hover:text-on-primary transition-colors">
                    <span className="material-symbols-outlined">logout</span>
                  </div>
                  <span className="text-label-caps font-label-caps text-[10px]">ออกจากระบบ</span>
                </button>
              </div>
              <div className="bg-primary-container rounded-xl shadow-md p-6 text-on-primary">
                <h4 className="font-headline-md text-headline-md mb-2">ประกาศจากเจ้าหน้าที่</h4>
                <p className="text-body-md opacity-80 mb-4">ห้องสมุดจะปิดทำการในสุดสัปดาห์นี้เพื่อตรวจนับครุภัณฑ์</p>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer className="bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border w-full py-8 mt-auto">
        <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
          <div className="flex flex-col md:flex-row items-center gap-6">
            <span className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NNTC</span>
            <p className="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-sm">
              © 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์
            </p>
          </div>
        </div>
      </footer>
    </div>
  )
}
