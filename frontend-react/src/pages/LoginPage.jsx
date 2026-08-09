import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import LibBanner from '../components/LibBanner'
import { apiPostJson } from '../api'
import { useAuth } from '../context/AuthContext'

const ROLE_LABEL_TH = { student: 'นักศึกษา', admin: 'แอดมิน' }

export default function LoginPage() {
  const navigate = useNavigate()
  const { refresh } = useAuth()
  const [role, setRole] = useState('student')
  const [username, setUsername] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [submitting, setSubmitting] = useState(false)

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSubmitting(true)
    try {
      const data = await apiPostJson('/login', { username: username.trim(), password })
      if (data.role !== role) {
        setError(`บัญชีนี้เป็นบัญชี${ROLE_LABEL_TH[data.role] || data.role} — กำลังพาไปยังหน้าที่ถูกต้อง`)
      }
      await refresh()
      navigate(data.role === 'admin' ? '/admin/dashboard' : '/dashboard')
    } catch (err) {
      setError(err.message)
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <div className="bg-background dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
      <ThemeToggle className="fixed top-4 right-4 z-[60] w-11 h-11 rounded-full bg-surface-white/90 dark:bg-dm-surface/90 backdrop-blur border border-outline-variant dark:border-dm-border shadow-md flex items-center justify-center text-primary dark:text-primary-fixed-dim hover:scale-105 active:scale-95 transition-all" />

      <LibBanner />

      <main className="flex-grow relative px-gutter mt-5 pb-16">
        <div className="max-w-xl mx-auto">
          <div className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-lg overflow-hidden border border-outline-variant/30 dark:border-dm-border transition-all-200">
            <div className="p-8 md:p-12">
              <div className="flex mb-8 bg-surface-container-low dark:bg-dm-bg rounded-lg p-1">
                {['student', 'admin'].map((r) => (
                  <button
                    key={r}
                    type="button"
                    onClick={() => setRole(r)}
                    className={`flex-1 h-11 rounded-md font-headline-md text-body-md flex items-center justify-center gap-2 transition-all-200 ${
                      role === r
                        ? 'bg-surface-white dark:bg-dm-surface text-primary dark:text-primary-fixed-dim shadow-sm'
                        : 'text-on-surface-variant dark:text-dm-text-secondary'
                    }`}
                  >
                    <span className="material-symbols-outlined text-xl">
                      {r === 'student' ? 'school' : 'admin_panel_settings'}
                    </span>
                    {ROLE_LABEL_TH[r]}
                  </button>
                ))}
              </div>

              {error && (
                <p className="mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md">
                  {error}
                </p>
              )}

              <form className="space-y-6" onSubmit={handleSubmit}>
                <div>
                  <label className="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                    {role === 'admin' ? 'ชื่อผู้ใช้แอดมิน' : 'ชื่อผู้ใช้ (รหัสนักศึกษา)'}
                  </label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">
                      person
                    </span>
                    <input
                      className="w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                      placeholder={role === 'admin' ? 'ชื่อผู้ใช้แอดมิน' : 'ชื่อผู้ใช้บัญชีของคุณ'}
                      required
                      type="text"
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                    />
                  </div>
                </div>
                <div>
                  <label className="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                    รหัสผ่าน
                  </label>
                  <div className="relative">
                    <span className="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">
                      lock
                    </span>
                    <input
                      className="w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                      placeholder="••••••••"
                      required
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                    />
                  </div>
                </div>
                <button
                  className="w-full h-14 bg-secondary text-on-secondary font-headline-md rounded-lg stamp-shadow hover:scale-[1.02] active:scale-[0.98] transition-all-200 flex items-center justify-center gap-2 group disabled:opacity-60"
                  type="submit"
                  disabled={submitting}
                >
                  <span>เข้าสู่ระบบห้องสมุด</span>
                  <span className="material-symbols-outlined group-hover:translate-x-1 transition-transform">
                    arrow_forward
                  </span>
                </button>
                {role === 'student' && (
                  <div className="text-center pt-4">
                    <p className="text-body-md text-on-surface-variant dark:text-dm-text-secondary">
                      ยังไม่มีบัญชี?{' '}
                      <Link className="text-primary dark:text-primary-fixed-dim font-bold hover:underline" to="/signup">
                        สมัครสมาชิก
                      </Link>
                    </p>
                  </div>
                )}
              </form>
            </div>
          </div>
        </div>
      </main>

      <footer className="bg-surface-container-highest dark:bg-dm-surface py-8 mt-auto border-t border-outline-variant dark:border-dm-border">
        <div className="max-w-7xl mx-auto px-gutter flex flex-col md:flex-row justify-between items-center gap-6">
          <div className="flex flex-col items-center md:items-start">
            <span className="font-label-caps text-label-caps font-bold text-primary dark:text-primary-fixed-dim mb-2">
              ห้องสมุด NNTC
            </span>
            <p className="font-body-md text-body-md text-on-surface-variant dark:text-dm-text-secondary text-center md:text-left">
              © 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์
            </p>
          </div>
        </div>
      </footer>
    </div>
  )
}
