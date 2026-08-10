import { useEffect, useRef, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { apiFetch } from '../api'
import { useAuth } from '../context/AuthContext'
import ThemeMenu from './ThemeMenu'
import HistoryModal from './HistoryModal'

// Single top navbar shared by every authenticated page (student and admin
// alike) — logo doubles as the "home" link (no separate "หน้าหลัก" text
// link), and the account menu (avatar) replaces the old separate "โปรไฟล์"
// link + logout button with one dropdown: history / profile / logout.
export default function AppHeader({ variant = 'student' }) {
  const navigate = useNavigate()
  const { user, refresh } = useAuth()
  const [menuOpen, setMenuOpen] = useState(false)
  const [historyOpen, setHistoryOpen] = useState(false)
  const menuRef = useRef(null)

  useEffect(() => {
    function onClick(e) {
      if (menuRef.current && !menuRef.current.contains(e.target)) setMenuOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  async function handleLogout() {
    await apiFetch('/logout', { method: 'POST' })
    await refresh()
    navigate('/login')
  }

  const homeHref = variant === 'admin' ? '/admin/dashboard' : '/dashboard'
  const displayName = user ? `${user.prefix || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username : ''

  return (
    <>
      <header className="bg-primary shadow-md fixed top-0 z-50 w-full">
        <div className="flex justify-between items-center w-full px-gutter h-16 max-w-7xl mx-auto text-on-primary gap-2">
          <a href={homeHref} className="flex items-center gap-2 min-w-0 hover:opacity-90 transition-opacity">
            <span className="material-symbols-outlined text-2xl flex-shrink-0">local_library</span>
            <span className="text-headline-md font-headline-md font-bold whitespace-nowrap">NTC Library</span>
          </a>

          <div className="flex items-center gap-2 sm:gap-3 flex-shrink-0" ref={menuRef}>
            <ThemeMenu onOpen={() => setMenuOpen(false)} />

            <div className="relative">
              <button
                type="button"
                onClick={() => setMenuOpen((v) => !v)}
                aria-haspopup="true"
                aria-expanded={menuOpen}
                aria-label="เมนูบัญชี"
                className="flex items-center gap-1.5 rounded-full hover:bg-white/10 pl-1 pr-2 py-1 transition-colors"
              >
                <span className="material-symbols-outlined text-[34px] leading-none">account_circle</span>
                <span className="hidden md:block text-left leading-tight max-w-[140px]">
                  <span className="block text-xs font-bold truncate">{displayName || '...'}</span>
                </span>
                <span className="material-symbols-outlined text-lg">{menuOpen ? 'expand_less' : 'expand_more'}</span>
              </button>

              {menuOpen && (
                <div className="absolute right-0 mt-2 w-60 bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl border border-outline-variant dark:border-dm-border overflow-hidden text-text-primary dark:text-inverse-on-surface z-50">
                  <div className="px-4 py-3 border-b border-outline-variant dark:border-dm-border">
                    <p className="font-bold text-sm truncate">{displayName || '...'}</p>
                    <p className="text-xs text-text-secondary dark:text-dm-text-secondary truncate">
                      รหัส: {user?.student_id || user?.username || '...'}
                    </p>
                  </div>

                  {variant === 'student' && (
                    <button
                      type="button"
                      onClick={() => {
                        setMenuOpen(false)
                        setHistoryOpen(true)
                      }}
                      className="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors"
                    >
                      <span className="material-symbols-outlined text-lg">history</span>
                      ประวัติการเข้าใช้
                    </button>
                  )}

                  <a
                    href={variant === 'admin' ? '/admin/members-management' : '/profile'}
                    className="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors"
                  >
                    <span className="material-symbols-outlined text-lg">person</span>
                    {variant === 'admin' ? 'ข้อมูลสมาชิก' : 'ข้อมูลผู้ใช้'}
                  </a>

                  <button
                    type="button"
                    onClick={handleLogout}
                    className="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-error hover:bg-error-container/30 transition-colors border-t border-outline-variant dark:border-dm-border"
                  >
                    <span className="material-symbols-outlined text-lg">logout</span>
                    ออกจากระบบ
                  </button>
                </div>
              )}
            </div>
          </div>
        </div>
      </header>

      {historyOpen && <HistoryModal onClose={() => setHistoryOpen(false)} />}
    </>
  )
}
