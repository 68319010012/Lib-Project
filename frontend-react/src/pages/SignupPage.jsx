import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import ThemeToggle from '../components/ThemeToggle'
import { apiPostJson } from '../api'
import { useAuth } from '../context/AuthContext'
import { DEPARTMENTS, YEAR_OPTIONS } from '../constants'

const initialForm = {
  student_id: '',
  prefix: '',
  gender: '',
  first_name: '',
  last_name: '',
  department: '',
  level: '',
  year_level: '',
  password: '',
  confirm_password: '',
}

export default function SignupPage() {
  const navigate = useNavigate()
  const { refresh } = useAuth()
  const [form, setForm] = useState(initialForm)
  const [error, setError] = useState('')
  const [success, setSuccess] = useState(false)
  const [submitting, setSubmitting] = useState(false)

  function update(field, value) {
    setForm((f) => ({
      ...f,
      [field]: value,
      // level -> year_level cascade: reset year_level whenever level changes.
      ...(field === 'level' ? { year_level: '' } : {}),
    }))
  }

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setSuccess(false)

    if (form.password !== form.confirm_password) {
      setError('รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน')
      return
    }

    setSubmitting(true)
    try {
      const data = await apiPostJson('/register', {
        student_id: form.student_id.trim(),
        prefix: form.prefix,
        gender: form.gender,
        first_name: form.first_name.trim(),
        last_name: form.last_name.trim(),
        department: form.department,
        level: form.level,
        year_level: form.year_level,
        password: form.password,
      })
      setSuccess(true)
      await refresh()
      navigate(data.role === 'admin' ? '/admin/dashboard' : '/dashboard')
    } catch (err) {
      setError(err.message)
      setSubmitting(false)
    }
  }

  const yearOptions = YEAR_OPTIONS[form.level] || []

  return (
    <div className="bg-surface dark:bg-dm-bg font-body-md text-on-surface dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
      <ThemeToggle className="fixed top-4 right-4 z-[60] w-11 h-11 rounded-full bg-surface-white/90 dark:bg-dm-surface/90 backdrop-blur border border-outline-variant dark:border-dm-border shadow-md flex items-center justify-center text-primary dark:text-primary-fixed-dim hover:scale-105 active:scale-95 transition-all" />

      <nav className="bg-primary shadow-md z-50">
        <div className="flex justify-between items-center w-full px-gutter h-16 max-w-7xl mx-auto">
          <div className="flex items-center gap-4">
            <div className="w-10 h-10 bg-surface-white rounded-lg flex items-center justify-center">
              <span className="material-symbols-outlined text-primary text-2xl">menu_book</span>
            </div>
            <h1 className="text-headline-md font-headline-md font-bold text-on-primary">ห้องสมุด NNTC</h1>
          </div>
          <Link className="text-on-primary/80 hover:text-on-primary transition-colors font-body-md" to="/login">
            เข้าสู่ระบบ
          </Link>
        </div>
      </nav>

      <main className="flex-grow relative">
        <section className="bg-gradient-to-br from-primary to-primary-container h-80 relative overflow-hidden flex items-center">
          <div className="absolute inset-0 signup-hero-pattern opacity-20" />
          <div className="max-w-7xl mx-auto w-full px-gutter z-10">
            <div className="max-w-2xl">
              <h2 className="text-headline-xl font-headline-xl text-on-primary mb-2">สร้างบัญชีของคุณ</h2>
              <p className="text-on-primary/70 text-body-lg">
                สมัครสมาชิกระบบห้องสมุดวิทยาลัยเทคนิคนครนายก เพื่อใช้งานเช็คชื่อเข้า-ออกห้องสมุด
              </p>
            </div>
          </div>
        </section>

        <section className="max-w-7xl mx-auto px-gutter -mt-24 mb-16 relative z-20">
          <div className="bg-surface-white dark:bg-dm-surface shadow-card rounded-xl overflow-hidden border border-outline-variant/30 dark:border-dm-border max-w-3xl mx-auto">
            <div className="p-8 md:p-12">
              {error && (
                <p className="mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md">
                  {error}
                </p>
              )}
              {success && (
                <p className="mb-6 rounded-lg bg-status-success/10 text-status-success px-4 py-3 text-body-md">
                  สร้างบัญชีสำเร็จ กำลังพาไปยังหน้าหลัก...
                </p>
              )}

              <form className="space-y-8" onSubmit={handleSubmit}>
                <div>
                  <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                    รหัสนักศึกษา
                  </label>
                  <input
                    className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-label-code text-primary dark:text-primary-fixed-dim"
                    placeholder="6XXXXXXX"
                    required
                    type="text"
                    value={form.student_id}
                    onChange={(e) => update('student_id', e.target.value)}
                  />
                </div>

                <div className="grid grid-cols-1 md:grid-cols-[140px_1fr_1fr] gap-6">
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      คำนำหน้า
                    </label>
                    <select
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      required
                      value={form.prefix}
                      onChange={(e) => update('prefix', e.target.value)}
                    >
                      <option value="">--</option>
                      <option value="นาย">นาย</option>
                      <option value="นาง">นาง</option>
                      <option value="นางสาว">นางสาว</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      ชื่อ
                    </label>
                    <input
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      placeholder="ชื่อ"
                      required
                      type="text"
                      value={form.first_name}
                      onChange={(e) => update('first_name', e.target.value)}
                    />
                  </div>
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      นามสกุล
                    </label>
                    <input
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      placeholder="นามสกุล"
                      required
                      type="text"
                      value={form.last_name}
                      onChange={(e) => update('last_name', e.target.value)}
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      เพศ
                    </label>
                    <select
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      required
                      value={form.gender}
                      onChange={(e) => update('gender', e.target.value)}
                    >
                      <option value="">-- เลือกเพศ --</option>
                      <option value="male">ชาย</option>
                      <option value="female">หญิง</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                    แผนกวิชา
                  </label>
                  <select
                    className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                    required
                    value={form.department}
                    onChange={(e) => update('department', e.target.value)}
                  >
                    <option value="">-- เลือกแผนกวิชา --</option>
                    {DEPARTMENTS.map((d) => (
                      <option key={d} value={d}>
                        {d}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      ระดับชั้น
                    </label>
                    <select
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      required
                      value={form.level}
                      onChange={(e) => update('level', e.target.value)}
                    >
                      <option value="">-- เลือกระดับชั้น --</option>
                      <option value="ปวช.">ปวช.</option>
                      <option value="ปวส.">ปวส.</option>
                    </select>
                  </div>
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      ชั้นปีที่
                    </label>
                    <select
                      className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md"
                      required
                      value={form.year_level}
                      onChange={(e) => update('year_level', e.target.value)}
                    >
                      <option value="">
                        {yearOptions.length ? '-- เลือกชั้นปี --' : '-- เลือกระดับชั้นก่อน --'}
                      </option>
                      {yearOptions.map((y) => (
                        <option key={y} value={y}>
                          {y}
                        </option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      รหัสผ่าน
                    </label>
                    <div className="relative">
                      <input
                        className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 pl-10"
                        placeholder="••••••••"
                        required
                        type="password"
                        value={form.password}
                        onChange={(e) => update('password', e.target.value)}
                      />
                      <span className="material-symbols-outlined absolute left-3 top-3 text-outline text-xl">lock</span>
                    </div>
                  </div>
                  <div>
                    <label className="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                      ยืนยันรหัสผ่าน
                    </label>
                    <div className="relative">
                      <input
                        className="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 pl-10"
                        placeholder="••••••••"
                        required
                        type="password"
                        value={form.confirm_password}
                        onChange={(e) => update('confirm_password', e.target.value)}
                      />
                      <span className="material-symbols-outlined absolute left-3 top-3 text-outline text-xl">
                        verified_user
                      </span>
                    </div>
                  </div>
                </div>

                <p className="text-xs text-on-surface-variant dark:text-dm-text-secondary -mt-2">
                  ใช้รหัสนักศึกษาเป็นชื่อผู้ใช้สำหรับเข้าสู่ระบบ ไม่ต้องตั้งชื่อผู้ใช้เอง
                </p>

                <div className="pt-6">
                  <button
                    className="w-full h-14 bg-secondary text-on-secondary font-headline-md rounded-full stamp-shadow flex items-center justify-center gap-3 transition-all duration-200 disabled:opacity-60"
                    type="submit"
                    disabled={submitting}
                  >
                    {submitting ? (
                      <>
                        <span className="material-symbols-outlined animate-spin">sync</span> กำลังส่งข้อมูล...
                      </>
                    ) : (
                      <>
                        สมัครสมาชิก
                        <span className="material-symbols-outlined">arrow_forward</span>
                      </>
                    )}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </section>
      </main>

      <footer className="bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border py-8 mt-auto">
        <div className="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
          <div className="flex flex-col items-center md:items-start">
            <span className="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim mb-1">
              ห้องสมุด NNTC
            </span>
            <p className="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-center md:text-left">
              © 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์
            </p>
          </div>
        </div>
      </footer>
    </div>
  )
}
