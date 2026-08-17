// Promise-based replacement for window.confirm() — see partials/confirm-modal.php.
//
// Usage:
//   const ok = await showConfirmModal('message', {
//     title, subject, confirmLabel, danger, icon,
//   });
//
// `subject` is the thing being acted on (e.g. a student's name) — shown as
// its own emphasized line so the title stays a short, clean question instead
// of a long sentence with the name buried mid-string. `icon` overrides the
// default (danger -> warning triangle, otherwise a question mark).

let confirmModalResolve = null;

function showConfirmModal(message, { title = 'ยืนยันการทำรายการ', subject = '', confirmLabel = 'ยืนยัน', danger = false, icon } = {}) {
  return new Promise((resolve) => {
    confirmModalResolve = resolve;

    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;

    const subjectEl = document.getElementById('confirm-modal-subject');
    subjectEl.textContent = subject;
    subjectEl.classList.toggle('hidden', !subject);

    // Icon + its circular background pick up the danger/normal color pair.
    const iconEl = document.getElementById('confirm-modal-icon');
    iconEl.textContent = icon || (danger ? 'warning' : 'help');
    // Danger uses the app's error red (matching the red confirm button);
    // non-danger uses the primary theme color — never the off-theme amber.
    const iconWrap = document.getElementById('confirm-modal-icon-wrap');
    iconWrap.className = `w-12 h-12 rounded-full flex items-center justify-center mb-4 ${
      danger ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary dark:text-primary-fixed-dim'
    }`;

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
