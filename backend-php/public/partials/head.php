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
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg" />
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
