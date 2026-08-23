<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'เข้าสู่ระบบ'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-background dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">

  <div class="relative">
    <div class="login-theme-menu" id="theme-menu">
      <button
        type="button"
        id="theme-menu-btn"
        aria-label="ตั้งค่าธีมสี"
        title="ตั้งค่าธีมสี"
        aria-haspopup="true"
        class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors flex-shrink-0"
      >
        <span class="material-symbols-outlined text-xl tm-current-icon">routine</span>
      </button>
      <div id="theme-menu-dropdown" class="hidden absolute right-0 mt-2 w-52 bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl border border-outline-variant dark:border-dm-border overflow-hidden text-text-primary dark:text-inverse-on-surface z-50">
        <button type="button" data-mode="auto" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-surface-container-low dark:hover:bg-dm-bg">
          <span class="material-symbols-outlined text-lg">routine</span>
          อัตโนมัติ (ตามเวลา)
          <span class="material-symbols-outlined text-base ml-auto tm-check hidden">check</span>
        </button>
        <button type="button" data-mode="light" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-surface-container-low dark:hover:bg-dm-bg">
          <span class="material-symbols-outlined text-lg">light_mode</span>
          สว่าง
          <span class="material-symbols-outlined text-base ml-auto tm-check hidden">check</span>
        </button>
        <button type="button" data-mode="dark" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm transition-colors hover:bg-surface-container-low dark:hover:bg-dm-bg">
          <span class="material-symbols-outlined text-lg">dark_mode</span>
          มืด
          <span class="material-symbols-outlined text-base ml-auto tm-check hidden">check</span>
        </button>
      </div>
    </div>

    <?php include __DIR__ . '/partials/lib-banner.php'; ?>
  </div>

  <main class="login-main flex-grow relative px-gutter mt-5 pb-16">
    <div class="max-w-xl mx-auto">
      <div class="rise-in bg-surface-white dark:bg-dm-surface rounded-xl shadow-lg overflow-hidden border border-outline-variant/30 dark:border-dm-border transition-all-200">
        <div class="p-8 md:p-12">
          <p id="login-error" role="alert" aria-live="assertive" class="hidden mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md"></p>

          <form class="space-y-6" id="login-form">
            <div>
              <label id="login-username-label" for="login-username" class="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                ชื่อผู้ใช้
              </label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">person</span>
                <input
                  id="login-username" autocomplete="username"
                  class="w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                  placeholder="รหัสนักศึกษา หรือชื่อผู้ใช้แอดมิน"
                  required
                  type="text"
                />
              </div>
            </div>
            <div>
              <label for="login-password" class="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">รหัสผ่าน</label>
              <div class="relative pw-field">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">lock</span>
                <input
                  id="login-password" autocomplete="current-password"
                  class="pw-input w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                  placeholder="••••••••"
                  required
                  type="password"
                />
                <button type="button" id="login-password-toggle" class="pw-toggle" aria-controls="login-password" aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                  <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                </button>
              </div>
            </div>
            <button
              id="login-submit"
              class="w-full h-14 bg-secondary text-on-secondary font-headline-md rounded-lg stamp-shadow hover:scale-[1.02] active:scale-[0.98] transition-all-200 flex items-center justify-center gap-2 group disabled:opacity-60"
              type="submit"
            >
              <span>เข้าสู่ระบบห้องสมุด</span>
              <span class="material-symbols-outlined group-hover:translate-x-1 transition-transform">arrow_forward</span>
            </button>
            <div id="login-signup-link" class="text-center pt-4">
              <p class="text-body-md text-on-surface-variant dark:text-dm-text-secondary">
                ยังไม่มีบัญชี?
                <a class="text-primary dark:text-primary-fixed-dim font-bold hover:underline" href="/signup">สมัครสมาชิก</a>
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <footer class="login-page-footer bg-surface-container-highest dark:bg-dm-surface py-8 mt-auto border-t border-outline-variant dark:border-dm-border">
    <div class="max-w-7xl mx-auto px-gutter flex flex-col md:flex-row justify-between items-center gap-6">
      <div class="footer-brand flex flex-col items-center md:items-start">
        <span class="font-label-caps text-label-caps font-bold text-primary dark:text-primary-fixed-dim mb-2">ห้องสมุด NTC</span>
        <p class="font-body-md text-body-md text-on-surface-variant dark:text-dm-text-secondary text-center md:text-left">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </div>
  </footer>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/login.js?v=<?= ntc_asset_v('assets/js/login.js') ?>"></script>
</body>
</html>
