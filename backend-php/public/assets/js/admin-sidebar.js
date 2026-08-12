// Mobile hamburger drawer for the admin sidebar — port of the `mobileOpen`
// state in frontend-react/src/components/AdminSidebar.jsx.
document.addEventListener('DOMContentLoaded', () => {
  const openBtn = document.getElementById('admin-mobile-menu-btn');
  const closeBtn = document.getElementById('admin-mobile-menu-close');
  const drawer = document.getElementById('admin-mobile-drawer');
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
