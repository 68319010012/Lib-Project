// Theme system — port of frontend-react/src/components/ThemeMenu.jsx (3-way
// auto/light/dark, localStorage key 'nntc-theme-mode'), now used on every
// page including login/signup. Keep this key and precedence in sync with
// the anti-flash <head> script in partials/head.php (which still falls back
// to the legacy 'nntc-theme' key for browsers that stored it before login/
// signup switched over to this menu).

// "auto" follows time of day (dark 18:00–06:00), matching the anti-flash
// script, not just system prefers-color-scheme.
function computeDark(mode) {
  if (mode === 'dark') return true;
  if (mode === 'light') return false;
  const hour = new Date().getHours();
  return hour >= 18 || hour < 6;
}

function applyMode(mode) {
  const dark = computeDark(mode);
  document.documentElement.classList.toggle('dark', dark);
  document.documentElement.classList.toggle('light', !dark);
  localStorage.setItem('nntc-theme-mode', mode);
}

const THEME_OPTIONS = [
  { key: 'auto', label: 'อัตโนมัติ (ตามเวลา)', icon: 'routine' },
  { key: 'light', label: 'สว่าง', icon: 'light_mode' },
  { key: 'dark', label: 'มืด', icon: 'dark_mode' },
];

function initThemeMenu() {
  const btn = document.getElementById('theme-menu-btn');
  const dropdown = document.getElementById('theme-menu-dropdown');
  const wrapper = document.getElementById('theme-menu');
  if (!btn || !dropdown || !wrapper) return;

  let mode = localStorage.getItem('nntc-theme-mode') || 'auto';
  applyMode(mode);

  function render() {
    const current = THEME_OPTIONS.find((o) => o.key === mode) || THEME_OPTIONS[0];
    btn.querySelector('.tm-current-icon').textContent = current.icon;
    dropdown.querySelectorAll('[data-mode]').forEach((el) => {
      const isActive = el.dataset.mode === mode;
      el.classList.toggle('bg-primary/10', isActive);
      el.classList.toggle('text-primary', isActive);
      el.classList.toggle('dark:text-primary-fixed-dim', isActive);
      el.classList.toggle('font-bold', isActive);
      el.querySelector('.tm-check').classList.toggle('hidden', !isActive);
    });
  }

  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    dropdown.classList.toggle('hidden');
  });
  dropdown.querySelectorAll('[data-mode]').forEach((el) => {
    el.addEventListener('click', () => {
      mode = el.dataset.mode;
      applyMode(mode);
      render();
      dropdown.classList.add('hidden');
    });
  });
  document.addEventListener('mousedown', (e) => {
    if (!wrapper.contains(e.target)) dropdown.classList.add('hidden');
  });

  // Re-evaluate periodically while in auto mode so a tab left open across
  // the 06:00/18:00 boundary flips without needing a refresh.
  setInterval(() => {
    if (mode === 'auto') applyMode('auto');
  }, 30 * 60 * 1000);

  render();
}

document.addEventListener('DOMContentLoaded', () => {
  initThemeMenu();
});
