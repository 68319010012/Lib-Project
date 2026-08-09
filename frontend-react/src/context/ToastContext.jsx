import { createContext, useCallback, useContext, useRef, useState } from 'react'

const ToastContext = createContext(null)

const ICONS = { success: 'check_circle', error: 'error', info: 'info' }
const STYLES = {
  success: 'bg-status-success border-status-success/50 text-white',
  error: 'bg-error border-error/50 text-on-error',
  info: 'bg-primary border-primary/50 text-on-primary',
}

// Toast/snackbar system — replaces native alert() (used for checkin errors)
// and the total lack of feedback on success paths (checkin/checkout/extend
// silently just... updated the UI). One provider, one hook, used from
// anywhere via useToast().
export function ToastProvider({ children }) {
  const [toasts, setToasts] = useState([])
  const nextId = useRef(0)

  const dismiss = useCallback((id) => {
    setToasts((prev) => prev.filter((t) => t.id !== id))
  }, [])

  const showToast = useCallback(
    (message, { type = 'success', duration = 4000 } = {}) => {
      const id = ++nextId.current
      setToasts((prev) => [...prev, { id, message, type }])
      if (duration > 0) {
        setTimeout(() => dismiss(id), duration)
      }
      return id
    },
    [dismiss],
  )

  return (
    <ToastContext.Provider value={{ showToast, dismiss }}>
      {children}
      <div className="fixed top-4 right-4 z-[200] flex flex-col gap-3 w-[calc(100%-2rem)] max-w-sm pointer-events-none">
        {toasts.map((t) => (
          <div
            key={t.id}
            role="status"
            className={`toast-in pointer-events-auto flex items-start gap-3 rounded-xl shadow-lg border px-4 py-3 ${STYLES[t.type] || STYLES.info}`}
          >
            <span className="material-symbols-outlined text-xl flex-shrink-0">{ICONS[t.type] || ICONS.info}</span>
            <p className="text-sm font-medium flex-1 leading-snug pt-0.5">{t.message}</p>
            <button
              type="button"
              onClick={() => dismiss(t.id)}
              aria-label="ปิด"
              className="opacity-70 hover:opacity-100 transition-opacity flex-shrink-0"
            >
              <span className="material-symbols-outlined text-lg">close</span>
            </button>
          </div>
        ))}
      </div>
    </ToastContext.Provider>
  )
}

export function useToast() {
  const ctx = useContext(ToastContext)
  if (!ctx) {
    throw new Error('useToast must be used within ToastProvider')
  }
  return ctx
}
