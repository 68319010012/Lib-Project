<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'เข้าสู่ระบบ'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-background dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">

  <button
    type="button"
    id="theme-toggle-btn"
    class="fixed top-4 right-4 z-[60] w-11 h-11 rounded-full bg-surface-white/90 dark:bg-dm-surface/90 backdrop-blur border border-outline-variant dark:border-dm-border shadow-md flex items-center justify-center text-primary dark:text-primary-fixed-dim hover:scale-105 active:scale-95 transition-all"
  >
    <span class="material-symbols-outlined">dark_mode</span>
  </button>

  <?php include __DIR__ . '/partials/lib-banner.php'; ?>

  <main class="flex-grow relative px-gutter mt-5 pb-16">
    <div class="max-w-xl mx-auto">
      <div class="rise-in bg-surface-white dark:bg-dm-surface rounded-xl shadow-lg overflow-hidden border border-outline-variant/30 dark:border-dm-border transition-all-200">
        <div class="p-8 md:p-12">
          <div class="flex mb-8 bg-surface-container-low dark:bg-dm-bg rounded-lg p-1">
            <button type="button" data-role-tab="student" class="flex-1 h-11 rounded-md font-headline-md text-body-md flex items-center justify-center gap-2 transition-all-200 bg-surface-white dark:bg-dm-surface text-primary dark:text-primary-fixed-dim shadow-sm">
              <span class="material-symbols-outlined text-xl">school</span>
              นักศึกษา
            </button>
            <button type="button" data-role-tab="admin" class="flex-1 h-11 rounded-md font-headline-md text-body-md flex items-center justify-center gap-2 transition-all-200 text-on-surface-variant dark:text-dm-text-secondary">
              <span class="material-symbols-outlined text-xl">admin_panel_settings</span>
              แอดมิน
            </button>
          </div>

          <p id="login-error" class="hidden mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md"></p>

          <form class="space-y-6" id="login-form">
            <div>
              <label id="login-username-label" class="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">
                ชื่อผู้ใช้ (รหัสนักศึกษา)
              </label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">person</span>
                <input
                  id="login-username"
                  class="w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                  placeholder="ชื่อผู้ใช้บัญชีของคุณ"
                  required
                  type="text"
                />
              </div>
            </div>
            <div>
              <label class="block font-label-caps text-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">รหัสผ่าน</label>
              <div class="relative">
                <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">lock</span>
                <input
                  id="login-password"
                  class="w-full h-14 pl-12 pr-4 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary outline-none transition-all-200"
                  placeholder="••••••••"
                  required
                  type="password"
                />
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
                <a class="text-primary dark:text-primary-fixed-dim font-bold hover:underline" href="/signup.php">สมัครสมาชิก</a>
              </p>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-surface-container-highest dark:bg-dm-surface py-8 mt-auto border-t border-outline-variant dark:border-dm-border">
    <div class="max-w-7xl mx-auto px-gutter flex flex-col md:flex-row justify-between items-center gap-6">
      <div class="flex flex-col items-center md:items-start">
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
