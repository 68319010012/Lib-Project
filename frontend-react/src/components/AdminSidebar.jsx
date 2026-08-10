import { useState } from 'react'

const NAV_ITEMS = [
  { to: '/admin/dashboard', icon: 'monitoring', label: 'ภาพรวม' },
  { to: '/admin/members-management', icon: 'group', label: 'สมาชิก' },
  { to: '/admin/attendance-logs', icon: 'history', label: 'ประวัติการเข้าใช้' },
]

// Shared left nav across the 3 admin pages — mirrors the near-identical
// <aside>/<nav> markup repeated in admin_dashboard.html, members_management.html,
// and attendance_logs.html.
//
// Section nav only — account controls (theme, logout, profile) live in the
// shared <AppHeader variant="admin"> that every admin page renders above
// this component, so this file doesn't duplicate them. The <aside> sits
// below that fixed header (`top-16`); on mobile, where the aside is hidden,
// a slim hamburger strip (also `top-16`, right under AppHeader) opens the
// same nav as a drawer instead.
export default function AdminSidebar({ active }) {
  const [mobileOpen, setMobileOpen] = useState(false)

  const navLinks = (onNavigate) => (
    <nav className="flex-1 space-y-2">
      {NAV_ITEMS.map((item) => (
        <a
          key={item.to}
          href={item.to}
          onClick={onNavigate}
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
  )

  return (
    <>
      {/* Desktop sidebar — sits below the fixed AppHeader (top-16) instead of
          at the very top of the viewport. */}
      <aside className="hidden md:flex flex-col h-[calc(100vh-4rem)] w-64 fixed left-0 top-16 bg-surface-white dark:bg-dm-surface border-r border-outline-variant dark:border-dm-border shadow-sm py-6 px-4 z-40">
        <p className="px-2 mb-4 text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">
          พอร์ทัลเจ้าหน้าที่
        </p>
        {navLinks()}
      </aside>

      {/* Mobile: a slim hamburger strip right under AppHeader (which already
          shows the brand + account menu on every breakpoint) opens this nav
          as a drawer instead of duplicating a second full top bar. */}
      <div className="md:hidden fixed top-16 left-0 right-0 z-40 h-12 bg-surface-white dark:bg-dm-surface border-b border-outline-variant dark:border-dm-border shadow-sm flex items-center justify-between px-4">
        <p className="text-xs uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลเจ้าหน้าที่</p>
        <button
          type="button"
          aria-label="เปิดเมนู"
          onClick={() => setMobileOpen(true)}
          className="w-9 h-9 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg"
        >
          <span className="material-symbols-outlined">menu</span>
        </button>
      </div>

      {mobileOpen && (
        <div className="md:hidden fixed inset-0 z-[60] bg-black/50" onClick={() => setMobileOpen(false)}>
          <div
            className="absolute top-0 left-0 h-screen w-72 max-w-[80vw] bg-surface-white dark:bg-dm-surface shadow-lg py-6 px-4 flex flex-col"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="flex items-center justify-between mb-10 px-2">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 bg-primary flex items-center justify-center rounded-lg">
                  <span className="material-symbols-outlined text-on-primary">local_library</span>
                </div>
                <div>
                  <h1 className="font-bold text-primary dark:text-primary-fixed-dim leading-tight">NTC Library</h1>
                  <p className="text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">
                    พอร์ทัลเจ้าหน้าที่
                  </p>
                </div>
              </div>
              <button
                type="button"
                aria-label="ปิดเมนู"
                onClick={() => setMobileOpen(false)}
                className="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg"
              >
                <span className="material-symbols-outlined">close</span>
              </button>
            </div>
            {navLinks(() => setMobileOpen(false))}
          </div>
        </div>
      )}
    </>
  )
}
