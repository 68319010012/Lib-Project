// Promise-based replacement for window.confirm() — see partials/confirm-modal.php.
// Usage: const ok = await showConfirmModal('message', { title, confirmLabel, danger });

let confirmModalResolve = null;

function showConfirmModal(message, { title = 'ยืนยันการทำรายการ', confirmLabel = 'ยืนยัน', danger = false } = {}) {
  return new Promise((resolve) => {
    confirmModalResolve = resolve;
    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;
    const okBtn = document.getElementById('confirm-modal-ok');
    okBtn.textContent = confirmLabel;
    okBtn.classList.toggle('bg-error', danger);
    okBtn.classList.toggle('bg-primary', !danger);
    document.getElementById('confirm-modal').classList.remove('hidden');
  });
}

function resolveConfirmModal(result) {
  document.getElementById('confirm-modal').classList.add('hidden');
  const resolve = confirmModalResolve;
  confirmModalResolve = null;
  if (resolve) resolve(result);
}

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('confirm-modal');
  if (!modal) return;
  document.getElementById('confirm-modal-ok').addEventListener('click', () => resolveConfirmModal(true));
  document.getElementById('confirm-modal-cancel').addEventListener('click', () => resolveConfirmModal(false));
  modal.addEventListener('click', (e) => {
    if (e.target === modal) resolveConfirmModal(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) resolveConfirmModal(false);
  });
});
