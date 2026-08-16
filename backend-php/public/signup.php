<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'สมัครสมาชิก'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-surface dark:bg-dm-bg font-body-md text-on-surface dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">

  <nav class="bg-primary shadow-md z-50">
    <div class="flex justify-between items-center w-full px-gutter h-16 max-w-7xl mx-auto">
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 bg-surface-white rounded-lg flex items-center justify-center">
          <span class="material-symbols-outlined text-primary text-2xl">menu_book</span>
        </div>
        <h1 class="text-headline-md font-headline-md font-bold text-on-primary">ห้องสมุด NTC</h1>
      </div>
      <div class="flex items-center gap-3">
        <div class="relative" id="theme-menu">
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
        <a class="text-on-primary/80 hover:text-on-primary transition-colors font-body-md" href="/login.php">เข้าสู่ระบบ</a>
      </div>
    </div>
  </nav>

  <main class="flex-grow relative">
    <section class="bg-gradient-to-br from-primary to-primary-container h-80 relative overflow-hidden flex items-center signup-hero-section">
      <div class="absolute inset-0 signup-hero-pattern opacity-20"></div>
      <div class="max-w-7xl mx-auto w-full px-gutter z-10">
        <div class="max-w-2xl">
          <h2 class="text-headline-xl font-headline-xl text-on-primary mb-2 signup-hero-title">สร้างบัญชีของคุณ</h2>
          <p class="text-on-primary/70 text-body-lg">สมัครสมาชิกระบบห้องสมุดวิทยาลัยเทคนิคนครนายก เพื่อใช้งานเช็คชื่อเข้า-ออกห้องสมุด</p>
        </div>
      </div>
    </section>

    <section class="max-w-7xl mx-auto px-gutter -mt-24 mb-16 relative z-20">
      <div class="bg-surface-white dark:bg-dm-surface shadow-card rounded-xl overflow-hidden border border-outline-variant/30 dark:border-dm-border max-w-3xl mx-auto">
        <div class="p-8 md:p-12">
          <p id="signup-error" class="hidden mb-6 rounded-lg bg-error-container text-on-error-container px-4 py-3 text-body-md"></p>
          <p id="signup-success" class="hidden mb-6 rounded-lg bg-status-success/10 text-status-success px-4 py-3 text-body-md"></p>

          <form class="space-y-8" id="signup-form">
            <div>
              <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">รหัสนักศึกษา</label>
              <input
                id="signup-student-id"
                class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-label-code text-primary dark:text-primary-fixed-dim"
                placeholder="6XXXXXXX"
                required
                type="text"
              />
            </div>

            <div class="grid grid-cols-1 md:grid-cols-[140px_1fr_1fr] gap-6">
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">คำนำหน้า</label>
                <select id="signup-prefix" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" required>
                  <option value="">--</option>
                  <option value="นาย">นาย</option>
                  <option value="นาง">นาง</option>
                  <option value="นางสาว">นางสาว</option>
                </select>
              </div>
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">ชื่อ</label>
                <input id="signup-first-name" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" placeholder="ชื่อ" required type="text" />
              </div>
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">นามสกุล</label>
                <input id="signup-last-name" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" placeholder="นามสกุล" required type="text" />
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">เพศ</label>
                <select id="signup-gender" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" required>
                  <option value="">-- เลือกเพศ --</option>
                  <option value="male">ชาย</option>
                  <option value="female">หญิง</option>
                </select>
              </div>
            </div>

            <div>
              <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">แผนกวิชา</label>
              <select id="signup-department" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" required></select>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">ระดับชั้น</label>
                <select id="signup-level" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" required>
                  <option value="">-- เลือกระดับชั้น --</option>
                  <option value="ปวช.">ปวช.</option>
                  <option value="ปวส.">ปวส.</option>
                </select>
              </div>
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">ชั้นปีที่</label>
                <select id="signup-year-level" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 font-body-md" required></select>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">รหัสผ่าน</label>
                <div class="relative">
                  <input id="signup-password" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 pl-10" placeholder="••••••••" required type="password" />
                  <span class="material-symbols-outlined absolute left-3 top-3 text-outline text-xl">lock</span>
                </div>
              </div>
              <div>
                <label class="block text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary mb-2">ยืนยันรหัสผ่าน</label>
                <div class="relative">
                  <input id="signup-confirm-password" class="w-full h-12 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-primary px-4 pl-10" placeholder="••••••••" required type="password" />
                  <span class="material-symbols-outlined absolute left-3 top-3 text-outline text-xl">verified_user</span>
                </div>
              </div>
            </div>

            <p class="text-xs text-on-surface-variant dark:text-dm-text-secondary -mt-2">ใช้รหัสนักศึกษาเป็นชื่อผู้ใช้สำหรับเข้าสู่ระบบ ไม่ต้องตั้งชื่อผู้ใช้เอง</p>

            <div class="pt-6">
              <button id="signup-submit" class="w-full h-14 bg-secondary text-on-secondary font-headline-md rounded-full stamp-shadow flex items-center justify-center gap-3 transition-all duration-200 disabled:opacity-60" type="submit">
                สมัครสมาชิก
                <span class="material-symbols-outlined">arrow_forward</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </main>

  <footer class="bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border py-8 mt-auto">
    <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
      <div class="flex flex-col items-center md:items-start">
        <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim mb-1">ห้องสมุด NTC</span>
        <p class="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-center md:text-left">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </div>
  </footer>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/constants.js?v=<?= ntc_asset_v('assets/js/constants.js') ?>"></script>
  <script src="/assets/js/signup.js?v=<?= ntc_asset_v('assets/js/signup.js') ?>"></script>
</body>
</html>
