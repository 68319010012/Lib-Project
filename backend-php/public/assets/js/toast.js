// Toast/snackbar system — port of frontend-react/src/context/ToastContext.jsx.
// showToast(message, {type, duration}) appends a dismissible toast to a fixed
// top-right container, auto-removed after `duration`ms (default 4000; pass
// duration <= 0 to keep it until manually closed).

const TOAST_ICONS = { success: 'check_circle', error: 'error', info: 'info' };
const TOAST_STYLES = {
  success: 'bg-status-success border-status-success/50 text-white',
  error: 'bg-error border-error/50 text-on-error',
  info: 'bg-primary border-primary/50 text-on-primary',
};

function getToastContainer() {
  let el = document.getElementById('toast-container');
  if (!el) {
    el = document.createElement('div');
    el.id = 'toast-container';
    el.className = 'fixed top-4 right-4 z-[200] flex flex-col gap-3 w-[calc(100%-2rem)] max-w-sm pointer-events-none';
    document.body.appendChild(el);
  }
  return el;
}

function showToast(message, { type = 'success', duration = 4000 } = {}) {
  const container = getToastContainer();
  const el = document.createElement('div');
  el.setAttribute('role', 'status');
  el.className = `toast-in pointer-events-auto flex items-start gap-3 rounded-xl shadow-lg border px-4 py-3 ${TOAST_STYLES[type] || TOAST_STYLES.info}`;
  el.innerHTML = `
    <span class="material-symbols-outlined text-xl flex-shrink-0">${TOAST_ICONS[type] || TOAST_ICONS.info}</span>
    <p class="text-sm font-medium flex-1 leading-snug pt-0.5"></p>
    <button type="button" aria-label="ปิด" class="opacity-70 hover:opacity-100 transition-opacity flex-shrink-0">
      <span class="material-symbols-outlined text-lg">close</span>
    </button>
  `;
  el.querySelector('p').textContent = message;
  el.querySelector('button').addEventListener('click', () => el.remove());
  container.appendChild(el);
  if (duration > 0) {
    setTimeout(() => el.remove(), duration);
  }
}
