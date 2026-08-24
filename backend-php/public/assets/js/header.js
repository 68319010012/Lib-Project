// Shared top navbar behavior — port of frontend-react/src/components/AppHeader.jsx.
// Markup lives in partials/header.php (rendered with $variant = 'student'|'admin').
// Auth guarding itself happens server-side (partials/guard.php); this only
// fetches /me to display the account name/id and wires the dropdown + logout.

// ปุ่มขีดสามขีดข้างโลโก้ พับแถบเมนูซ้ายเข้า/ออกบนจอคอม
//
// อยู่ที่นี่เพราะตัวปุ่มอยู่ใน partials/header.php ซึ่งใช้ร่วมกันทั้งฝั่ง
// นักศึกษาและเจ้าหน้าที่ เดิมตรรกะนี้อยู่ใน admin-sidebar.js หน้าฝั่งนักศึกษา
// จึงมีปุ่มไม่ได้เลยเพราะไม่ได้โหลดไฟล์นั้น
//
// ชื่อคีย์ที่ใช้จำสถานะมาจาก data-collapse-key บนตัวปุ่ม สองฝั่งจึงจำแยกกัน
// ส่วนคลาสบน <html> ใช้ชื่อเดียวกัน เพราะแต่ละหน้ามีแถบเมนูได้แค่แบบเดียว
function initSidebarCollapse() {
  const btn = document.getElementById('sidebar-toggle-btn');
  if (!btn) return;
  const root = document.documentElement;
  const icon = btn.querySelector('.material-symbols-outlined');
  const key = btn.dataset.collapseKey || 'ntc-sidebar-collapsed';

  const sync = () => {
    const collapsed = root.classList.contains('sidebar-collapsed');
    if (icon) icon.textContent = collapsed ? 'menu' : 'menu_open';
    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
  };
  sync();

  btn.addEventListener('click', () => {
    const collapsed = root.classList.toggle('sidebar-collapsed');
    try {
      localStorage.setItem(key, collapsed ? '1' : '0');
    } catch (e) {
      /* โหมดส่วนตัวบางเบราว์เซอร์ห้ามเขียน — พับได้อยู่ แค่จำไม่ได้ */
    }
    sync();
  });
}

function initAppHeader() {
  initSidebarCollapse();
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
        window.location.href = '/login';
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
