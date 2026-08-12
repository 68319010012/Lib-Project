// Mobile hamburger drawer for the student sidebar (profile.php) — same
// pattern as admin-sidebar.js.
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('student-mobile-menu-btn');
  const closeBtn = document.getElementById('student-mobile-menu-close');
  const drawer = document.getElementById('student-mobile-drawer');
  if (!openBtn || !drawer) return;

  openBtn.addEventListener('click', () => drawer.classList.remove('hidden'));
  if (closeBtn) closeBtn.addEventListener('click', () => drawer.classList.add('hidden'));
  drawer.addEventListener('click', (e) => {
    if (e.target === drawer) drawer.classList.add('hidden');
  });
  drawer.querySelectorAll('a').forEach((a) => {
    a.addEventListener('click', () => drawer.classList.add('hidden'));
  });
});
