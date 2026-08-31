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

/* ---------------------------------------------------------------------
   ติดตั้งเป็นแอป

   ทุกอย่างฝั่งเซิร์ฟเวอร์ครบมาตั้งแต่แรกแล้ว (manifest, service worker,
   ไอคอน, HTTPS) แต่ผู้ใช้ยังหา "ที่กดโหลด" ไม่เจอ เพราะเบราว์เซอร์ซ่อน
   ทางติดตั้งไว้ในที่ที่คนไม่เปิด: Chrome บนคอมเป็นไอคอนเล็ก ๆ ในแถบ
   ที่อยู่เว็บ, Chrome บนแอนดรอยด์อยู่ในเมนูสามจุด ส่วน Safari บน iPhone
   ไม่มีทางติดตั้งอัตโนมัติเลย ต้องกดแชร์แล้วเลือก "เพิ่มไปยังหน้าจอโฮม"
   เอง — ไม่มีเบราว์เซอร์ไหนจะเด้งบอกให้

   เมนูบัญชีจึงมีรายการติดตั้งของตัวเอง แต่โผล่เฉพาะตอนที่กดแล้วได้ผลจริง
   --------------------------------------------------------------------- */
(function initInstallApp() {
  let deferredPrompt = null;

  // เปิดจากไอคอนบนหน้าจอโฮมอยู่แล้ว ไม่ต้องชวนติดตั้งซ้ำ
  function alreadyInstalled() {
    return window.matchMedia('(display-mode: standalone)').matches
      || window.matchMedia('(display-mode: window-controls-overlay)').matches
      || navigator.standalone === true;
  }

  // iOS ไม่ยิง beforeinstallprompt ให้เลยไม่ว่าเว็บจะพร้อมแค่ไหน จึงต้องดักเอง
  // แล้วบอกวิธีทำด้วยมือแทน ตัวเช็ค maxTouchPoints คือ iPad รุ่นใหม่ที่ปลอมตัว
  // เป็น Mac ใน userAgent
  function isIOS() {
    const ua = navigator.userAgent;
    return /iPhone|iPad|iPod/.test(ua)
      || (/Macintosh/.test(ua) && navigator.maxTouchPoints > 1);
  }

  function showIOSHelp() {
    if (document.getElementById('install-help')) return;
    const el = document.createElement('div');
    el.id = 'install-help';
    el.className = 'install-help';
    el.innerHTML = `
      <div class="install-help-card" role="dialog" aria-modal="true" aria-labelledby="install-help-title">
        <h2 class="install-help-title" id="install-help-title">ติดตั้งบน iPhone / iPad</h2>
        <p class="install-help-lead">Safari ไม่มีปุ่มติดตั้งอัตโนมัติ ทำตามสามขั้นนี้ครั้งเดียวจบ</p>
        <ol class="install-help-steps">
          <li><span class="material-symbols-outlined">ios_share</span> แตะปุ่ม <b>แชร์</b> ที่แถบล่างของ Safari</li>
          <li><span class="material-symbols-outlined">add_box</span> เลื่อนลงแล้วเลือก <b>เพิ่มไปยังหน้าจอโฮม</b></li>
          <li><span class="material-symbols-outlined">check_circle</span> แตะ <b>เพิ่ม</b> มุมขวาบน</li>
        </ol>
        <button type="button" class="install-help-close">เข้าใจแล้ว</button>
      </div>`;
    const close = () => el.remove();
    el.addEventListener('click', (e) => { if (e.target === el) close(); });
    el.querySelector('.install-help-close').addEventListener('click', close);
    document.addEventListener('keydown', function esc(e) {
      if (e.key === 'Escape') { close(); document.removeEventListener('keydown', esc); }
    });
    document.body.appendChild(el);
  }

  function wire() {
    const btn = document.getElementById('account-menu-install');
    if (!btn || alreadyInstalled()) return;

    const reveal = () => { btn.classList.remove('hidden'); btn.classList.add('flex'); };

    if (isIOS()) {
      reveal();
      btn.addEventListener('click', showIOSHelp);
      return;
    }

    window.addEventListener('beforeinstallprompt', (e) => {
      // ถ้าไม่กัน Chrome จะไปเด้งแถบของตัวเองแทน แล้วเราจะเรียก prompt() ไม่ได้
      e.preventDefault();
      deferredPrompt = e;
      reveal();
    });

    btn.addEventListener('click', async () => {
      if (!deferredPrompt) return;
      deferredPrompt.prompt();
      await deferredPrompt.userChoice;
      // ใช้ได้ครั้งเดียวต่อหนึ่ง event ถ้าผู้ใช้ปฏิเสธ เบราว์เซอร์จะยิงมาใหม่เอง
      deferredPrompt = null;
      btn.classList.add('hidden');
      btn.classList.remove('flex');
    });

    window.addEventListener('appinstalled', () => {
      deferredPrompt = null;
      btn.classList.add('hidden');
      btn.classList.remove('flex');
      if (typeof showToast === 'function') showToast('ติดตั้งแอปเรียบร้อยแล้ว', { type: 'success' });
    });
  }

  document.addEventListener('DOMContentLoaded', wire);
})();
