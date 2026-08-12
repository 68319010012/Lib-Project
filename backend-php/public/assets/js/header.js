// Shared top navbar behavior — port of frontend-react/src/components/AppHeader.jsx.
// Markup lives in partials/header.php (rendered with $variant = 'student'|'admin').
// Auth guarding itself happens server-side (partials/guard.php); this only
// fetches /me to display the account name/id and wires the dropdown + logout.

function initAppHeader() {
  const menuBtn = document.getElementById('account-menu-btn');
  const dropdown = document.getElementById('account-menu-dropdown');
  const wrapper = document.getElementById('account-menu');
  if (!menuBtn || !dropdown || !wrapper) return;

  menuBtn.addEventListener('click', (e) => {
    e.stopPropagation();
    const willOpen = dropdown.classList.contains('hidden');
    dropdown.classList.toggle('hidden');
    menuBtn.querySelector('.account-menu-caret').textContent = willOpen ? 'expand_less' : 'expand_more';
  });
  document.addEventListener('mousedown', (e) => {
    if (!wrapper.contains(e.target)) {
      dropdown.classList.add('hidden');
      menuBtn.querySelector('.account-menu-caret').textContent = 'expand_more';
    }
  });

  const historyBtn = document.getElementById('account-menu-history');
  if (historyBtn) {
    historyBtn.addEventListener('click', () => {
      dropdown.classList.add('hidden');
      openHistoryModal();
    });
  }

  const logoutBtn = document.getElementById('account-menu-logout');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
      try {
        await apiFetch('/logout', { method: 'POST' });
      } finally {
        window.location.href = '/login.php';
      }
    });
  }

  apiGet('/me')
    .then((user) => {
      if (!user) return;
      const displayName = `${user.prefix || ''}${user.first_name || ''} ${user.last_name || ''}`.trim() || user.username;
      document.querySelectorAll('.account-display-name').forEach((el) => {
        el.textContent = displayName;
      });
      const idEl = document.getElementById('account-student-id');
      if (idEl) idEl.textContent = `รหัส: ${user.student_id || user.username || '...'}`;
    })
    .catch(() => {});
}

document.addEventListener('DOMContentLoaded', initAppHeader);
