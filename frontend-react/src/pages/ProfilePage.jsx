import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import { apiFetch, apiPostJson } from '../api'
import { useAuth } from '../context/AuthContext'

export default function ProfilePage() {
  const navigate = useNavigate()
  const { user, refresh } = useAuth()
  const [currentPassword, setCurrentPassword] = useState('')
  const [newPassword, setNewPassword] = useState('')
  const [error, setError] = useState('')
  const [success, setSuccess] = useState('')
  const [saving, setSaving] = useState(false)

  async function handleLogout() {
    await apiFetch('/logout', { method: 'POST' })
    await refresh()
    navigate('/login')
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSuccess('')
    setSaving(true)
    try {
      await apiPostJson('/profile/change-password', {
        current_password: currentPassword,
        new_password: newPassword,
      })
      setSuccess('เปลี่ยนรหัสผ่านสำเร็จ')
      setCurrentPassword('')
      setNewPassword('')
    } catch (err) {
      setError(err.message)
    } finally {
      setSaving(false)
    }
  }

  const displayName = user ? `${user.prefix || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username : '...'

  return (
    <div className="bg-surface dark:bg-dm-bg font-body-md text-on-surface dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
      <nav className="bg-primary shadow-md flex justify-between items-center w-full px-gutter h-16 fixed top-0 left-0 right-0 z-50">
        <span className="text-headline-md font-headline-md font-bold text-on-primary">ห้องสมุด NNTC</span>
        <div className="flex items-center gap-4">
          <ThemeToggle className="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors" iconClassName="text-xl" />
          <button className="text-on-primary/80 hover:text-on-primary font-label-caps text-label-caps" type="button" onClick={handleLogout}>
            ออกจากระบบ
          </button>
        </div>
      </nav>

      <aside className="hidden lg:flex flex-col h-screen w-64 fixed left-0 top-0 bg-surface-white dark:bg-dm-surface border-r border-outline-variant dark:border-dm-border pt-20 pb-6 px-4 z-40">
        <div className="mb-8 px-4">
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
              <span className="material-symbols-outlined text-on-primary">school</span>
            </div>
            <div>
              <h2 className="text-primary dark:text-primary-fixed-dim text-headline-md font-bold leading-tight">NNTC</h2>
              <p className="text-on-surface-variant dark:text-dm-text-secondary text-label-caps font-label-caps uppercase tracking-wider">
                พอร์ทัลนักศึกษา
              </p>
            </div>
          </div>
        </div>
        <nav className="flex-1 space-y-2">
          <a className="text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 flex items-center gap-3 transition-colors" href="/dashboard">
            <span className="material-symbols-outlined">dashboard</span>
            <span className="font-body-md">หน้าหลัก</span>
          </a>
          <a className="bg-primary-container text-on-primary-container font-bold rounded-lg px-4 py-2 flex items-center gap-3" href="/profile">
            <span className="material-symbols-outlined">settings</span>
            <span className="font-body-md">โปรไฟล์</span>
          </a>
        </nav>
      </aside>

      <main className="flex-1 lg:ml-64 pt-16">
        <section className="hero-pattern h-[240px] flex items-center px-gutter">
          <div className="max-w-4xl mx-auto w-full text-white">
            <h1 className="font-headline-xl text-headline-xl mb-2">แก้ไขโปรไฟล์นักศึกษา</h1>
            <p className="text-body-lg font-body-lg opacity-80">ตรวจสอบข้อมูลการศึกษาและเปลี่ยนรหัสผ่านของคุณ</p>
          </div>
        </section>

        <div className="max-w-4xl mx-auto px-gutter -mt-16 mb-12 relative z-10">
          <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-card overflow-hidden border border-outline-variant/30 dark:border-dm-border">
            <div className="p-8">
              {error && (
                <p className="mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md">{error}</p>
              )}
              {success && (
                <p className="mb-6 rounded-lg bg-status-success/10 text-status-success px-4 py-3 text-body-md">{success}</p>
              )}

              <div className="space-y-10">
                <div className="grid md:grid-cols-2 gap-8">
                  <div className="space-y-2">
                    <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary flex items-center gap-2">
                      <span className="material-symbols-outlined text-[16px]">lock</span>
                      รหัสนักศึกษา
                    </label>
                    <input
                      className="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-label-code text-label-code"
                      readOnly
                      type="text"
                      value={user?.student_id || user?.username || '…'}
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary flex items-center gap-2">
                      <span className="material-symbols-outlined text-[16px]">lock</span>
                      ชื่อ-สกุล
                    </label>
                    <input
                      className="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md"
                      readOnly
                      type="text"
                      value={displayName}
                    />
                  </div>
                </div>

                <hr className="border-outline-variant/30 dark:border-dm-border" />

                <div className="space-y-6">
                  <h3 className="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim flex items-center gap-2">
                    <span className="material-symbols-outlined">school</span>
                    ข้อมูลการศึกษา
                  </h3>
                  <div className="grid md:grid-cols-3 gap-6">
                    <div className="space-y-2">
                      <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">แผนกวิชา</label>
                      <input
                        className="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md"
                        readOnly
                        type="text"
                        value={user?.department || '-'}
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">ระดับชั้น</label>
                      <input
                        className="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md"
                        readOnly
                        type="text"
                        value={user?.level || '-'}
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">ชั้นปี</label>
                      <input
                        className="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md"
                        readOnly
                        type="text"
                        value={user?.year_level || '-'}
                      />
                    </div>
                  </div>
                </div>

                <hr className="border-outline-variant/30 dark:border-dm-border" />

                <form className="space-y-6" onSubmit={handleSubmit}>
                  <h3 className="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim flex items-center gap-2">
                    <span className="material-symbols-outlined">security</span>
                    ตั้งค่าความปลอดภัย
                  </h3>
                  <div className="grid md:grid-cols-2 gap-8">
                    <div className="space-y-2">
                      <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">รหัสผ่านปัจจุบัน</label>
                      <input
                        className="w-full px-4 py-3 rounded-lg border border-outline dark:border-dm-border focus:border-primary focus:ring-1 focus:ring-primary bg-surface-white dark:bg-dm-bg dark:text-inverse-on-surface font-body-md transition-all"
                        placeholder="ยืนยันตัวตนก่อนเปลี่ยนรหัสผ่าน"
                        required
                        type="password"
                        value={currentPassword}
                        onChange={(e) => setCurrentPassword(e.target.value)}
                      />
                    </div>
                    <div className="space-y-2">
                      <label className="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">รหัสผ่านใหม่</label>
                      <input
                        className="w-full px-4 py-3 rounded-lg border border-outline dark:border-dm-border focus:border-primary focus:ring-1 focus:ring-primary bg-surface-white dark:bg-dm-bg dark:text-inverse-on-surface font-body-md transition-all"
                        minLength={8}
                        placeholder="อย่างน้อย 8 ตัวอักษร"
                        required
                        type="password"
                        value={newPassword}
                        onChange={(e) => setNewPassword(e.target.value)}
                      />
                    </div>
                  </div>
                  <div className="flex justify-end pt-4">
                    <button
                      className="bg-secondary text-white font-headline-md text-headline-md px-10 py-4 rounded-full shadow-[0px_8px_24px_rgba(165,61,0,0.3)] hover:brightness-110 flex items-center gap-3 disabled:opacity-60"
                      type="submit"
                      disabled={saving}
                    >
                      <span className="material-symbols-outlined">save</span>
                      เปลี่ยนรหัสผ่าน
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>

          <div className="mt-8">
            <div className="bg-tertiary-fixed dark:bg-dm-surface p-6 rounded-xl border border-tertiary-container/10 dark:border-dm-border flex items-start gap-4">
              <span className="material-symbols-outlined text-tertiary-container dark:text-tertiary-fixed-dim text-4xl">info</span>
              <div>
                <h4 className="font-bold text-on-tertiary-fixed dark:text-inverse-on-surface mb-1">ข้อมูลที่แก้ไขไม่ได้</h4>
                <p className="text-on-tertiary-fixed-variant dark:text-dm-text-secondary text-body-md">
                  รหัสนักศึกษา ชื่อ-สกุล แผนกวิชา ระดับชั้น และชั้นปี มาจากทะเบียนของวิทยาลัย ไม่สามารถแก้ไขได้ในหน้านี้
                  หากข้อมูลไม่ถูกต้อง กรุณาติดต่อฝ่ายทะเบียน
                </p>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer className="bg-surface-container-highest dark:bg-dm-surface w-full py-8 border-t border-outline-variant dark:border-dm-border">
        <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
          <span className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NNTC</span>
          <p className="text-on-surface-variant dark:text-dm-text-secondary text-body-md text-center md:text-left">
            © 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์
          </p>
        </div>
      </footer>
    </div>
  )
}
