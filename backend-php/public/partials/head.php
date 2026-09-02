<?php
// Cache-busting query string for /assets/* files: the deployed environment
// has swapped backends mid-session more than once (Docker <-> XAMPP) on the
// same http://localhost:8080 origin, and each time the browser kept serving
// a stale cached JS/CSS bundle from the previous backend — changes that were
// definitely on disk didn't show up until a hard refresh. Keying on filemtime
// means the URL itself changes whenever a file changes, so normal (non-hard)
// refreshes always pick up the latest version.
function ntc_asset_v(string $relPath): string
{
    $file = __DIR__ . '/../' . ltrim($relPath, '/');
    return file_exists($file) ? (string) filemtime($file) : '1';
}
?>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' - ' : '' ?>ห้องสมุด NTC</title>
<!-- Favicon, PWA icon และ apple-touch-icon ทั้งหมดสร้างจากไฟล์เดียวกันคือ
     assets/img/logo-badge.png (ตราวิทยาลัย) ซึ่งเป็นโลโก้ที่แสดงบน header
     ทุกหน้า เพื่อให้แท็บเบราว์เซอร์ ผลค้นหา และแอปที่ติดตั้งบนมือถือเห็น
     เป็นแบรนด์เดียวกัน -->
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/favicon-32.png?v=<?= ntc_asset_v('assets/img/favicon-32.png') ?>" />
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/favicon-16.png?v=<?= ntc_asset_v('assets/img/favicon-16.png') ?>" />
<!-- เบราว์เซอร์เก่าและ crawler บางตัวขอ /favicon.ico ที่รากเสมอโดยไม่อ่าน
     <link> ข้างบน จึงต้องมีไฟล์นั้นรออยู่ด้วย -->
<link rel="alternate icon" href="/favicon.ico" />

<!-- Installable app (PWA). With these three the browser offers "เพิ่มไปยังหน้าจอ
     โฮม" / "ติดตั้งแอป", and the installed copy opens without the browser's
     address bar — the same site, but it looks and launches like an app.
     theme-color paints the phone's status bar to match the header navy. -->
<link rel="manifest" href="/manifest.webmanifest?v=<?= ntc_asset_v('manifest.webmanifest') ?>" />
<meta name="theme-color" content="#1a2947" />
<!-- iOS reads none of the manifest's icon list; it uses this one. -->
<link rel="apple-touch-icon" href="/assets/img/pwa/apple-touch-icon.png?v=<?= ntc_asset_v('assets/img/pwa/apple-touch-icon.png') ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
<meta name="apple-mobile-web-app-title" content="ห้องสมุด NTC" />
<meta name="mobile-web-app-capable" content="yes" />
<!-- Fonts (Noto Sans Thai, IBM Plex Mono, Material Symbols Outlined) are
     self-hosted via @font-face rules at the top of styles.css — no external
     Google Fonts request, so the site works offline / behind restrictive
     networks and doesn't depend on fonts.googleapis.com being reachable. -->
<link rel="stylesheet" href="/assets/css/styles.css?v=<?= ntc_asset_v('assets/css/styles.css') ?>" />
<script>
  // Anti-flash: set the dark/light class before paint. Same precedence as
  // frontend-react's index.html: 'nntc-theme-mode' ('auto'|'light'|'dark',
  // set by assets/js/theme.js) -> legacy 'nntc-theme' key -> system preference.
  (function () {
    try {
      var mode = localStorage.getItem('nntc-theme-mode');
      var dark;
      if (mode === 'dark') {
        dark = true;
      } else if (mode === 'light') {
        dark = false;
      } else if (mode === 'auto') {
        var hour = new Date().getHours();
        dark = hour >= 18 || hour < 6;
      } else {
        var legacy = localStorage.getItem('nntc-theme');
        dark = legacy ? legacy === 'dark' : window.matchMedia('(prefers-color-scheme: dark)').matches;
      }
      document.documentElement.classList.toggle('dark', dark);
      document.documentElement.classList.toggle('light', !dark);
    } catch (e) {}
  })();
</script>
<script>
  // Registered on 'load' so fetching and starting the worker never competes
  // with the first render. Registration is best-effort: it needs a secure
  // context, so it simply does nothing over plain http on a phone (localhost
  // counts as secure, which is why it still works on the dev server).
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js').catch(function () {});
    });
  }
</script>
