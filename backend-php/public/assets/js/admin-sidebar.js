// Mobile hamburger drawer for the admin sidebar — port of the `mobileOpen`
// state in frontend-react/src/components/AdminSidebar.jsx.
document.addEventListener('DOMContentLoaded', () => {
  // Desktop collapse toggle (md+): slides the fixed sidebar off-screen and
  // drops main's left margin so content reflows full-width. State persists in
  // localStorage and is pre-applied to <html> before paint in admin-sidebar.php.
  const toggleBtn = document.getElementById('sidebar-toggle-btn');
  if (toggleBtn) {
    const root = document.documentElement;
    const icon = toggleBtn.querySelector('.material-symbols-outlined');
    const syncIcon = () => {
      if (icon) icon.textContent = root.classList.contains('sidebar-collapsed') ? 'menu' : 'menu_open';
    };
    syncIcon();
    toggleBtn.addEventListener('click', () => {
      const collapsed = root.classList.toggle('sidebar-collapsed');
      try {
        localStorage.setItem('ntc-admin-sidebar-collapsed', collapsed ? '1' : '0');
      } catch (e) {}
      syncIcon();
    });
  }

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
