import { useEffect, useState } from 'react'

// Port of static/js/api.js's initThemeToggle() — same localStorage key
// ('nntc-theme') and dark/light class toggling on <html>, so it stays in
// sync with the anti-flash script in index.html.
export default function ThemeToggle({ className, iconClassName, label = true }) {
  const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'))

  useEffect(() => {
    setIsDark(document.documentElement.classList.contains('dark'))
  }, [])

  function toggle() {
    const next = !isDark
    document.documentElement.classList.toggle('dark', next)
    document.documentElement.classList.toggle('light', !next)
    localStorage.setItem('nntc-theme', next ? 'dark' : 'light')
    setIsDark(next)
  }

  return (
    <button
      type="button"
      onClick={toggle}
      aria-label={isDark ? 'สลับเป็นโหมดสว่าง' : 'สลับเป็นโหมดมืด'}
      title={isDark ? 'สลับเป็นโหมดสว่าง' : 'สลับเป็นโหมดมืด'}
      className={className}
    >
      <span className={`material-symbols-outlined ${iconClassName || ''}`}>
        {isDark ? 'light_mode' : 'dark_mode'}
      </span>
      {label && typeof label === 'string' ? <span className="font-body-md">{label}</span> : null}
    </button>
  )
}
