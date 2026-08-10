import { useEffect, useState } from 'react'
import { apiFetch } from '../api'

// Full check-in/check-out history — reached from AppHeader's avatar menu
// instead of living inline on the dashboard page (see DashboardPage's
// compact "เข้าใช้ล่าสุด" line, which replaced the old full-list section).
export default function HistoryModal({ onClose }) {
  const [history, setHistory] = useState(null)

  useEffect(() => {
    apiFetch('/me/history?limit=50')
      .then(setHistory)
      .catch(() => setHistory([]))
  }, [])

  return (
    <div
      className="fixed inset-0 z-[95] bg-black/50 flex items-start sm:items-center justify-center px-gutter py-8"
      onClick={onClose}
    >
      <div
        className="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-lg w-full max-h-[80vh] flex flex-col"
        onClick={(e) => e.stopPropagation()}
      >
        <div className="flex items-center justify-between p-6 border-b border-outline-variant dark:border-dm-border flex-shrink-0">
          <h3 className="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim">ประวัติการเข้าใช้</h3>
          <button
            type="button"
            onClick={onClose}
            aria-label="ปิด"
            className="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container-low dark:hover:bg-dm-bg text-on-surface-variant dark:text-dm-text-secondary"
          >
            <span className="material-symbols-outlined">close</span>
          </button>
        </div>
        <div className="p-6 overflow-y-auto space-y-3">
          {history === null && <p className="text-body-md text-text-secondary dark:text-dm-text-secondary">กำลังโหลด…</p>}
          {history !== null && history.length === 0 && (
            <p className="text-body-md text-text-secondary dark:text-dm-text-secondary">ยังไม่มีประวัติการเช็คอิน</p>
          )}
          {history !== null &&
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
                      <p className="font-bold text-text-primary dark:text-inverse-on-surface">{isIn ? 'เช็คอิน' : 'เช็คเอาต์'}</p>
                      <p className="text-xs text-text-secondary dark:text-dm-text-secondary">{formatted}</p>
                    </div>
                  </div>
                </div>
              )
            })}
        </div>
      </div>
    </div>
  )
}
