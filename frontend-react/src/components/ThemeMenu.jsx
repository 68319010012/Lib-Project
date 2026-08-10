import { useEffect, useRef, useState } from 'react'

// "auto" follows time of day (dark 18:00–06:00) instead of just system
// prefers-color-scheme — matches index.html's anti-flash script, which
// reads the same 'nntc-theme-mode' key before paint.
function computeDark(mode) {
  if (mode === 'dark') return true
  if (mode === 'light') return false
  const hour = new Date().getHours()
  return hour >= 18 || hour < 6
}

function applyMode(mode) {
  const dark = computeDark(mode)
  document.documentElement.classList.toggle('dark', dark)
  document.documentElement.classList.toggle('light', !dark)
  localStorage.setItem('nntc-theme-mode', mode)
}

const OPTIONS = [
  { key: 'auto', label: 'อัตโนมัติ (ตามเวลา)', icon: 'routine' },
  { key: 'light', label: 'สว่าง', icon: 'light_mode' },
  { key: 'dark', label: 'มืด', icon: 'dark_mode' },
]

export default function ThemeMenu({ buttonClassName, onOpen }) {
  const [mode, setMode] = useState(() => localStorage.getItem('nntc-theme-mode') || 'auto')
  const [open, setOpen] = useState(false)
  const ref = useRef(null)

  useEffect(() => {
    applyMode(mode)
  }, [mode])

  // Re-evaluate periodically while in auto mode so a tab left open across
  // the 06:00/18:00 boundary flips without needing a refresh.
  useEffect(() => {
    if (mode !== 'auto') return undefined
    const id = setInterval(() => applyMode('auto'), 30 * 60 * 1000)
    return () => clearInterval(id)
  }, [mode])

  useEffect(() => {
    function onClick(e) {
      if (ref.current && !ref.current.contains(e.target)) setOpen(false)
    }
    document.addEventListener('mousedown', onClick)
    return () => document.removeEventListener('mousedown', onClick)
  }, [])

  const current = OPTIONS.find((o) => o.key === mode) || OPTIONS[0]

  return (
    <div className="relative" ref={ref}>
      <button
        type="button"
        onClick={() => {
          setOpen((v) => {
            if (!v && onOpen) onOpen()
            return !v
          })
        }}
        aria-label="ตั้งค่าธีมสี"
        title="ตั้งค่าธีมสี"
        aria-haspopup="true"
        aria-expanded={open}
        className={
          buttonClassName ||
          'w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors flex-shrink-0'
        }
      >
        <span className="material-symbols-outlined text-xl">{current.icon}</span>
      </button>
      {open && (
        <div className="absolute right-0 mt-2 w-52 bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl border border-outline-variant dark:border-dm-border overflow-hidden text-text-primary dark:text-inverse-on-surface z-50">
          {OPTIONS.map((o) => (
            <button
              key={o.key}
              type="button"
              onClick={() => {
                setMode(o.key)
                setOpen(false)
              }}
              className={`w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors ${
                mode === o.key
                  ? 'bg-primary/10 text-primary dark:text-primary-fixed-dim font-bold'
                  : 'hover:bg-surface-container-low dark:hover:bg-dm-bg'
              }`}
            >
              <span className="material-symbols-outlined text-lg">{o.icon}</span>
              {o.label}
              {mode === o.key && <span className="material-symbols-outlined text-base ml-auto">check</span>}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
