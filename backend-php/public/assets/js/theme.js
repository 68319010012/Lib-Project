// Theme system — a single toggle button that flips light <-> dark on one
// click (no dropdown). localStorage key 'nntc-theme-mode' stays 'light' or
// 'dark'; the legacy 'auto' value is still understood on read so older tabs
// and the anti-flash <head> script in partials/head.php keep working.

// Kept for backward compatibility with any stored 'auto' value: auto follows
// time of day (dark 18:00–06:00), matching the anti-flash script.
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

function initThemeToggle() {
  const btn = document.getElementById('theme-toggle-btn');
  if (!btn) return;

  const icon = btn.querySelector('.tm-current-icon');

  function syncIcon() {
    const dark = document.documentElement.classList.contains('dark');
    // Show the current state: sun in light mode, moon in dark mode.
    if (icon) icon.textContent = dark ? 'dark_mode' : 'light_mode';
    btn.title = dark ? 'ธีมมืด — คลิกเพื่อสลับเป็นสว่าง' : 'ธีมสว่าง — คลิกเพื่อสลับเป็นมืด';
  }

  // Resolve whatever is stored (light/dark/legacy-auto) into an explicit state.
  const stored = localStorage.getItem('nntc-theme-mode') || 'auto';
  applyMode(computeDark(stored) ? 'dark' : 'light');
  syncIcon();

  btn.addEventListener('click', () => {
    const nextDark = !document.documentElement.classList.contains('dark');
    applyMode(nextDark ? 'dark' : 'light');
    syncIcon();
  });
}

document.addEventListener('DOMContentLoaded', initThemeToggle);
