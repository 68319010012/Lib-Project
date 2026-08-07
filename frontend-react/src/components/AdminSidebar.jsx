import { useNavigate } from 'react-router-dom'
import ThemeToggle from './ThemeToggle'
import { apiFetch } from '../api'

const NAV_ITEMS = [
  { to: '/admin/dashboard', icon: 'monitoring', label: 'ภาพรวม' },
  { to: '/admin/members-management', icon: 'group', label: 'สมาชิก' },
  { to: '/admin/attendance-logs', icon: 'history', label: 'ประวัติการเข้าใช้' },
]

// Shared left nav across the 3 admin pages — mirrors the near-identical
// <aside>/<nav> markup repeated in admin_dashboard.html, members_management.html,
// and attendance_logs.html.
export default function AdminSidebar({ active }) {
  const navigate = useNavigate()

  async function handleLogout() {
    await apiFetch('/logout', { method: 'POST' })
    navigate('/login')
  }

  return (
    <aside className="hidden md:flex flex-col h-screen w-64 fixed left-0 top-0 bg-surface-white dark:bg-dm-surface border-r border-outline-variant dark:border-dm-border shadow-sm py-6 px-4 z-50">
      <div className="flex items-center gap-3 mb-10 px-2">
        <div className="w-10 h-10 bg-primary flex items-center justify-center rounded-lg">
          <span className="material-symbols-outlined text-on-primary">library_books</span>
        </div>
        <div>
          <h1 className="font-bold text-primary dark:text-primary-fixed-dim leading-tight">ห้องสมุด NNTC</h1>
          <p className="text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">
            พอร์ทัลเจ้าหน้าที่
          </p>
        </div>
      </div>
      <nav className="flex-1 space-y-2">
        {NAV_ITEMS.map((item) => (
          <a
            key={item.to}
            href={item.to}
            className={
              item.to === active
                ? 'flex items-center gap-3 bg-primary-container text-on-primary-container font-bold rounded-lg px-4 py-2'
                : 'flex items-center gap-3 text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 transition-colors'
            }
          >
            <span className="material-symbols-outlined">{item.icon}</span>
            <span className="font-body-md">{item.label}</span>
          </a>
        ))}
      </nav>
      <div className="mt-auto pt-6 border-t border-outline-variant dark:border-dm-border space-y-2">
        <ThemeToggle className="w-full flex items-center gap-3 text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 transition-colors group" label="โหมดมืด/สว่าง" />
        <button
          type="button"
          onClick={handleLogout}
          className="w-full flex items-center gap-3 text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 transition-colors group"
        >
          <span className="material-symbols-outlined group-hover:text-error">logout</span>
          <span className="font-body-md">ออกจากระบบ</span>
        </button>
      </div>
    </aside>
  )
}
