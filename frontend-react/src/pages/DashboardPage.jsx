import { useEffect, useMemo, useRef, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import { apiFetch, apiPostJson } from '../api'
import { useAuth } from '../context/AuthContext'
import { useToast } from '../context/ToastContext'

function formatClock(seconds) {
  const hrs = Math.floor(seconds / 3600)
  const mins = Math.floor((seconds % 3600) / 60)
  const secs = seconds % 60
  return [hrs, mins, secs].map((n) => n.toString().padStart(2, '0')).join(':')
}

function formatHM(date) {
  return date.toLocaleTimeString('th-TH', { hour: '2-digit', minute: '2-digit' })
}

// `base`'s date combined with the library's HH:MM closing time, as a Date.
function closingDateFor(base, closingTime) {
  const [h, m] = closingTime.split(':').map(Number)
  const d = new Date(base)
  d.setHours(h, m, 0, 0)
  return d
}

function toHHMM(date) {
  return `${date.getHours().toString().padStart(2, '0')}:${date.getMinutes().toString().padStart(2, '0')}`
}

// Whether the library is still open right now, and the earliest pickable
// time (next full minute) for the <input type="time"> min attribute.
function timeInputBounds(closingTime) {
  const now = new Date()
  const closing = closingDateFor(now, closingTime)
  return { isOpen: closing > now, min: toHHMM(now), max: closingTime }
}

function buildHourButtons(closingTime) {
  const now = new Date()
  const closing = closingDateFor(now, closingTime)
  return [1, 2, 3, 4, 5, 6].map((h) => ({
    hours: h,
    exceeds: new Date(now.getTime() + h * 3600000) > closing,
  }))
}

export default function DashboardPage() {
  const navigate = useNavigate()
  const { user, refresh } = useAuth()
  const { showToast } = useToast()
  const [history, setHistory] = useState([])
  const [busy, setBusy] = useState(false)
  const [now, setNow] = useState(() => Date.now())
  const [closingTime, setClosingTime] = useState('17:00')

  const [showModal, setShowModal] = useState(false)
  const [modalTab, setModalTab] = useState('time')
  const [checkoutTimeValue, setCheckoutTimeValue] = useState('')
  const [selectedHours, setSelectedHours] = useState(null)
  const [modalError, setModalError] = useState('')

  const [showReminder, setShowReminder] = useState(false)
  const [reminderMinutesLeft, setReminderMinutesLeft] = useState(0)
  const reminderNotifiedKeyRef = useRef(null)

  const last = history[0]
  const isCheckedIn = !!last && last.type === 'in'
  const checkedInSince = isCheckedIn ? new Date(last.timestamp) : null
  const plannedCheckoutAt = isCheckedIn && last.planned_checkout_at ? new Date(last.planned_checkout_at) : null

  useEffect(() => {
    if (!isCheckedIn) return undefined
    const id = setInterval(() => setNow(Date.now()), 1000)
    return () => clearInterval(id)
  }, [isCheckedIn])

  useEffect(() => {
    apiFetch('/library-info')
      .then((data) => setClosingTime(data.closing_time))
      .catch(() => {})
  }, [])

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

  const timeBounds = useMemo(() => (showModal ? timeInputBounds(closingTime) : { isOpen: true, min: '', max: '' }), [showModal, closingTime])
  const hourButtons = useMemo(() => (showModal ? buildHourButtons(closingTime) : []), [showModal, closingTime])

  useEffect(() => {
    if (!showModal) return
    setModalTab('time')
    setSelectedHours(null)
    setModalError('')
    setCheckoutTimeValue('')
  }, [showModal, closingTime])

  // Only relevant while this tab stays open — the server-side auto-checkout
  // sweep (see backend-php/src/handlers/checkin_handlers.php) is the real
  // backstop and works regardless of whether this tab is open.
  useEffect(() => {
    if (!checkedInSince || !plannedCheckoutAt) return undefined
    function tick() {
      const minutesLeft = Math.ceil((plannedCheckoutAt.getTime() - Date.now()) / 60000)
      const key = plannedCheckoutAt.toISOString()
      if (minutesLeft <= 20 && minutesLeft > 0 && reminderNotifiedKeyRef.current !== key) {
        reminderNotifiedKeyRef.current = key
        setReminderMinutesLeft(minutesLeft)
        setShowReminder(true)
        if (navigator.vibrate) navigator.vibrate(200)
      }
    }
    tick()
    const id = setInterval(tick, 30000)
    return () => clearInterval(id)
  }, [checkedInSince, plannedCheckoutAt])

  async function performCheckin(body) {
    setBusy(true)
    try {
      const data = await apiPostJson('/checkin', body)
      reminderNotifiedKeyRef.current = null
      setShowReminder(false)
      await loadHistory()
      showToast(data.message, { type: 'success' })
    } catch (err) {
      showToast(err.message, { type: 'error' })
    } finally {
      setBusy(false)
    }
  }

  async function handleStamp() {
    if (!isCheckedIn) {
      setShowModal(true)
      return
    }
    await performCheckin({})
  }

  function confirmModal() {
    if (modalTab === 'time') {
      if (!timeBounds.isOpen) {
        setModalError('ห้องสมุดปิดแล้ว ไม่สามารถเช็คอินได้')
        return
      }
      if (!checkoutTimeValue) {
        setModalError('กรุณากรอกเวลาที่จะออก')
        return
      }
      setShowModal(false)
      performCheckin({ checkout_time: checkoutTimeValue })
    } else {
      if (!selectedHours) {
        setModalError('กรุณาเลือกจำนวนชั่วโมง')
        return
      }
      setShowModal(false)
      performCheckin({ duration_minutes: selectedHours * 60 })
    }
  }

  function checkinUntilClosing() {
    setShowModal(false)
    performCheckin({})
  }

  async function extendCheckout(minutes) {
    try {
      const data = await apiPostJson('/checkin/extend', { minutes })
      reminderNotifiedKeyRef.current = null
      setShowReminder(false)
      await loadHistory()
      showToast(data.message, { type: 'success' })
    } catch (err) {
      showToast(err.message, { type: 'error' })
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
        <div className="flex justify-between items-center w-full px-gutter h-16 max-w-7xl mx-auto text-on-primary gap-2">
          <div className="flex items-center gap-2 sm:gap-4 min-w-0">
            <span className="text-headline-md font-headline-md font-bold text-on-primary whitespace-nowrap">
              <span className="hidden sm:inline">NNTC Library</span>
              <span className="sm:hidden">NNTC</span>
            </span>
            {/* Always visible (not hidden on mobile) — this is the only way to
                get from /profile back to /dashboard on small screens where the
                sidebar in ProfilePage is also hidden. */}
            <nav className="flex items-center gap-3 sm:gap-6 ml-1 sm:ml-8">
              <Link className="text-on-primary border-b-2 border-secondary-container pb-1 font-label-caps text-label-caps text-xs sm:text-sm whitespace-nowrap" to="/dashboard">
                หน้าหลัก
              </Link>
              <Link className="text-on-primary/70 hover:text-on-primary transition-colors font-label-caps text-label-caps text-xs sm:text-sm whitespace-nowrap" to="/profile">
                โปรไฟล์
              </Link>
            </nav>
          </div>
          <div className="flex items-center gap-2 sm:gap-4 flex-shrink-0">
            <ThemeToggle className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors flex-shrink-0" iconClassName="text-xl" />
            <button
              className="w-9 h-9 sm:w-auto sm:h-auto rounded-full sm:rounded-none bg-white/10 sm:bg-transparent hover:bg-white/20 sm:hover:bg-transparent flex items-center justify-center sm:justify-start text-on-primary/80 hover:text-on-primary font-label-caps text-label-caps transition-colors flex-shrink-0"
              type="button"
              onClick={handleLogout}
              aria-label="ออกจากระบบ"
              title="ออกจากระบบ"
            >
              <span className="material-symbols-outlined text-xl sm:hidden">logout</span>
              <span className="hidden sm:inline">ออกจากระบบ</span>
            </button>
            <div className="flex items-center gap-3">
              <div className="text-right hidden md:block">
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
            <div className="rise-in flex flex-col md:flex-row md:items-end justify-between gap-6">
              <div>
                <div className="inline-flex items-center gap-2 bg-surface-container-highest/20 text-on-primary px-3 py-1 rounded-full mb-4 border border-on-primary/20">
                  <span className={`relative flex w-2.5 h-2.5 rounded-full ${isCheckedIn ? 'bg-status-success' : 'bg-outline'}`}>
                    {isCheckedIn && <span className="absolute inset-0 rounded-full bg-status-success animate-ping" />}
                  </span>
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
          <div className="rise-in-group grid grid-cols-1 lg:grid-cols-12 gap-8">
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

                {isCheckedIn && plannedCheckoutAt && (
                  <div className="flex flex-col items-center gap-2 mt-5">
                    <p className="text-body-md text-text-secondary dark:text-dm-text-secondary">
                      ตั้งใจออก: {formatHM(plannedCheckoutAt)}
                    </p>
                    <div className="flex gap-2">
                      <button
                        className="px-4 py-2 rounded-full bg-surface-container-low dark:bg-dm-bg text-primary dark:text-primary-fixed-dim text-xs font-bold hover:brightness-95 transition-all"
                        type="button"
                        onClick={() => extendCheckout(30)}
                      >
                        ขอเวลาเพิ่ม 30 นาที
                      </button>
                      <button
                        className="px-4 py-2 rounded-full bg-surface-container-low dark:bg-dm-bg text-primary dark:text-primary-fixed-dim text-xs font-bold hover:brightness-95 transition-all"
                        type="button"
                        onClick={() => extendCheckout(60)}
                      >
                        ขอเวลาเพิ่ม 60 นาที
                      </button>
                    </div>
                  </div>
                )}
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
                            isIn ? 'border-status-success' : 'border-warning'
                          }`}
                        >
                          <div className="flex items-center gap-4">
                            <div
                              className={`w-10 h-10 rounded-full flex items-center justify-center ${
                                isIn ? 'bg-status-success/10 text-status-success' : 'bg-warning/10 text-warning'
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
                  className="lift-on-hover bg-surface-white dark:bg-dm-surface p-4 rounded-xl shadow-sm flex flex-col items-center gap-2 group"
                  to="/profile"
                >
                  <div className="w-12 h-12 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center group-hover:bg-primary group-hover:text-on-primary transition-colors">
                    <span className="material-symbols-outlined">person_edit</span>
                  </div>
                  <span className="text-label-caps font-label-caps text-[10px]">โปรไฟล์</span>
                </Link>
                <button
                  className="lift-on-hover bg-surface-white dark:bg-dm-surface p-4 rounded-xl shadow-sm flex flex-col items-center gap-2 group"
                  type="button"
                  onClick={handleLogout}
                >
                  <div className="w-12 h-12 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center group-hover:bg-primary group-hover:text-on-primary transition-colors">
                    <span className="material-symbols-outlined">logout</span>
                  </div>
                  <span className="text-label-caps font-label-caps text-[10px]">ออกจากระบบ</span>
                </button>
              </div>
              <div className="bg-primary-container dark:bg-dm-surface-alt dark:border dark:border-dm-border rounded-xl shadow-md p-6 text-on-primary dark:text-inverse-on-surface">
                <h4 className="font-headline-md text-headline-md mb-2 dark:text-primary-fixed-dim">ประกาศจากเจ้าหน้าที่</h4>
                <p className="text-body-md opacity-80 dark:opacity-100 dark:text-dm-text-secondary mb-4">ห้องสมุดจะปิดทำการในสุดสัปดาห์นี้เพื่อตรวจนับครุภัณฑ์</p>
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

      {showModal && (
        <div className="fixed inset-0 z-[80] bg-black/50 flex items-center justify-center px-gutter" onClick={() => setShowModal(false)}>
          <div
            className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-sm w-full p-6"
            onClick={(e) => e.stopPropagation()}
          >
            <h3 className="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim mb-1">จะอยู่นานแค่ไหน?</h3>
            <p className="text-body-md text-text-secondary dark:text-dm-text-secondary mb-4">เลือกเวลาที่ตั้งใจจะออกจากห้องสมุด</p>

            <div className="flex mb-4 bg-surface-container-low dark:bg-dm-bg rounded-lg p-1">
              {[
                { key: 'time', label: 'เลือกเวลาที่จะออก' },
                { key: 'hours', label: 'เลือกจำนวนชั่วโมง' },
              ].map((tab) => (
                <button
                  key={tab.key}
                  type="button"
                  className={`flex-1 h-10 rounded-md text-xs font-bold transition-all ${
                    modalTab === tab.key
                      ? 'bg-surface-white dark:bg-dm-surface text-primary dark:text-primary-fixed-dim shadow-sm'
                      : 'text-on-surface-variant dark:text-dm-text-secondary'
                  }`}
                  onClick={() => {
                    setModalTab(tab.key)
                    setModalError('')
                  }}
                >
                  {tab.label}
                </button>
              ))}
            </div>

            {modalTab === 'time' ? (
              <div className="mb-4">
                <label className="block text-xs font-bold text-on-surface-variant dark:text-dm-text-secondary mb-2">เวลาที่จะออก</label>
                {!timeBounds.isOpen ? (
                  <p className="text-warning text-sm">ห้องสมุดปิดแล้ว</p>
                ) : (
                  <>
                    <input
                      type="time"
                      className="w-full h-11 px-3 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg [color-scheme:light] dark:[color-scheme:dark]"
                      value={checkoutTimeValue}
                      min={timeBounds.min}
                      max={timeBounds.max}
                      onChange={(e) => {
                        setCheckoutTimeValue(e.target.value)
                        setModalError('')
                      }}
                    />
                    <p className="text-xs text-text-secondary dark:text-dm-text-secondary mt-2">
                      กรอกเวลาระหว่าง {timeBounds.min} - {timeBounds.max} น. (เกินเวลานี้ระบบจะปรับให้ออกตอนปิดแทน)
                    </p>
                  </>
                )}
              </div>
            ) : (
              <div className="mb-4">
                <label className="block text-xs font-bold text-on-surface-variant dark:text-dm-text-secondary mb-2">จำนวนชั่วโมง</label>
                <div className="grid grid-cols-3 gap-2">
                  {hourButtons.map((btn) => (
                    <button
                      key={btn.hours}
                      type="button"
                      className={`h-10 rounded-lg border text-sm font-bold transition-all ${
                        selectedHours === btn.hours
                          ? 'border-primary text-primary dark:border-primary-fixed-dim dark:text-primary-fixed-dim ring-2 ring-primary/30'
                          : 'border-outline-variant dark:border-dm-border text-text-primary dark:text-inverse-on-surface'
                      } ${btn.exceeds ? 'opacity-60' : ''}`}
                      onClick={() => {
                        setSelectedHours(btn.hours)
                        setModalError('')
                      }}
                    >
                      {btn.hours} ชม.
                    </button>
                  ))}
                </div>
                {selectedHours && hourButtons.find((b) => b.hours === selectedHours)?.exceeds && (
                  <p className="text-warning text-xs mt-2">เวลาที่เลือกเกินเวลาปิดห้องสมุด ระบบจะปรับให้ออกตอนปิดแทน</p>
                )}
              </div>
            )}

            {modalError && <p className="text-error text-sm mb-3">{modalError}</p>}

            <button
              className="w-full h-10 text-xs font-bold text-primary dark:text-primary-fixed-dim mb-4 hover:underline"
              type="button"
              onClick={checkinUntilClosing}
            >
              หรือเลือก &quot;จนกว่าจะปิด&quot;
            </button>

            <div className="flex gap-3">
              <button
                className="flex-1 h-11 rounded-full border border-outline-variant dark:border-dm-border text-text-primary dark:text-inverse-on-surface font-bold text-sm"
                type="button"
                onClick={() => setShowModal(false)}
              >
                ยกเลิก
              </button>
              <button
                className="flex-1 h-11 rounded-full bg-status-success text-white font-bold text-sm"
                type="button"
                onClick={confirmModal}
              >
                ยืนยันเช็คอิน
              </button>
            </div>
          </div>
        </div>
      )}

      {showReminder && (
        <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-[90] w-[calc(100%-32px)] max-w-md bg-gradient-to-br from-warning to-warning/80 text-white rounded-2xl shadow-2xl p-4">
          <p className="font-bold text-sm mb-3">อีก {reminderMinutesLeft} นาทีจะถึงเวลาที่ตั้งไว้ ต้องการขอเวลาเพิ่มไหม?</p>
          <div className="flex gap-2">
            <button
              className="flex-1 h-9 rounded-full bg-white/90 text-warning font-bold text-xs"
              type="button"
              onClick={() => extendCheckout(30)}
            >
              ขอเวลาเพิ่ม 30 นาที
            </button>
            <button
              className="flex-1 h-9 rounded-full bg-white/90 text-warning font-bold text-xs"
              type="button"
              onClick={() => extendCheckout(60)}
            >
              ขอเวลาเพิ่ม 60 นาที
            </button>
            <button
              className="h-9 px-3 rounded-full bg-white/20 text-white font-bold text-xs"
              type="button"
              onClick={() => setShowReminder(false)}
            >
              ไม่ต้อง
            </button>
          </div>
        </div>
      )}
    </div>
  )
}
