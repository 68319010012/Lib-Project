<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'สมัครสมาชิก'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="login-body is-signup">

  <!-- Same auto-sliding, blue-graded library backdrop as the login page. -->
  <div class="login-bg" id="login-bg" aria-hidden="true">
    <div class="login-bg-base"></div>
    <div class="login-bg-grade" id="login-bg-grade"></div>
    <div class="login-bg-overlay"></div>
  </div>

  <div class="login-theme-menu">
    <button
      type="button"
      id="theme-toggle-btn"
      aria-label="สลับธีมสว่าง/มืด"
      title="สลับธีมสว่าง/มืด"
      class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors flex-shrink-0"
    >
      <span class="material-symbols-outlined text-xl tm-current-icon">light_mode</span>
    </button>
  </div>

  <main class="login-shell">
    <section class="login-glass signup-card rise-in">
      <h1 class="login-title">สร้างบัญชี</h1>

      <form class="signup-form" id="signup-form">
        <div class="signup-field">
          <label for="signup-student-id">รหัสนักศึกษา</label>
          <input id="signup-student-id" autocomplete="username" placeholder="6XXXXXXX" required type="text" />
        </div>

        <div class="signup-row cols-3">
          <div class="signup-field">
            <label for="signup-prefix">คำนำหน้า</label>
            <select id="signup-prefix" required>
              <option value="นาย">นาย</option>
              <option value="นาง">นาง</option>
              <option value="นางสาว">นางสาว</option>
            </select>
          </div>
          <div class="signup-field">
            <label for="signup-first-name">ชื่อ</label>
            <input id="signup-first-name" autocomplete="given-name" placeholder="ชื่อ" required type="text" />
          </div>
          <div class="signup-field">
            <label for="signup-last-name">นามสกุล</label>
            <input id="signup-last-name" autocomplete="family-name" placeholder="นามสกุล" required type="text" />
          </div>
        </div>

        <div class="signup-row cols-2">
          <div class="signup-field">
            <label for="signup-gender">เพศ</label>
            <select id="signup-gender" required>
              <option value="male">ชาย</option>
              <option value="female">หญิง</option>
            </select>
          </div>
          <div class="signup-field">
            <label for="signup-department">แผนกวิชา</label>
            <select id="signup-department" required></select>
          </div>
        </div>

        <div class="signup-row cols-2">
          <div class="signup-field">
            <label for="signup-level">ระดับชั้น</label>
            <select id="signup-level" required>
              <option value="ปวช.">ปวช.</option>
              <option value="ปวส.">ปวส.</option>
            </select>
          </div>
          <div class="signup-field">
            <label for="signup-year-level">ชั้นปีที่</label>
            <select id="signup-year-level" required></select>
          </div>
        </div>

        <div class="signup-row cols-2">
          <div class="signup-field pw">
            <label for="signup-password">รหัสผ่าน</label>
            <div class="pw-wrap">
              <span class="material-symbols-outlined pw-icon" aria-hidden="true">lock</span>
              <input id="signup-password" autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร" minlength="8" required type="password" />
            </div>
          </div>
          <div class="signup-field pw">
            <label for="signup-confirm-password">ยืนยันรหัสผ่าน</label>
            <div class="pw-wrap">
              <span class="material-symbols-outlined pw-icon" aria-hidden="true">verified_user</span>
              <input id="signup-confirm-password" autocomplete="new-password" placeholder="กรอกรหัสผ่านอีกครั้ง" minlength="8" required type="password" />
            </div>
          </div>
        </div>

        <p class="signup-hint">ใช้รหัสนักศึกษาเป็นชื่อผู้ใช้สำหรับเข้าสู่ระบบ ไม่ต้องตั้งชื่อผู้ใช้เอง</p>

        <button id="signup-submit" class="signup-submit" type="submit">
          <span>สมัครสมาชิก</span>
          <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>

        <p class="login-signup">มีบัญชีอยู่แล้ว? <a href="/login">เข้าสู่ระบบ</a></p>
      </form>
    </section>
  </main>

  <div id="signup-result-modal" class="hidden fixed inset-0 z-[95] bg-black/50 flex items-center justify-center px-gutter py-8">
    <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-sm w-full p-8 text-center">
      <div id="signup-result-icon" class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
        <span class="material-symbols-outlined text-4xl" id="signup-result-icon-glyph"></span>
      </div>
      <h3 id="signup-result-title" role="alert" aria-live="assertive" class="font-headline-md text-headline-md text-text-primary dark:text-inverse-on-surface mb-2"></h3>
      <p id="signup-result-message" class="text-body-md text-on-surface-variant dark:text-dm-text-secondary mb-6"></p>
      <button type="button" id="signup-result-close" class="w-full h-12 rounded-lg bg-primary text-white font-headline-md text-headline-md">ตกลง</button>
    </div>
  </div>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/login-bg.js?v=<?= ntc_asset_v('assets/js/login-bg.js') ?>"></script>
  <script src="/assets/js/constants.js?v=<?= ntc_asset_v('assets/js/constants.js') ?>"></script>
  <script src="/assets/js/signup.js?v=<?= ntc_asset_v('assets/js/signup.js') ?>"></script>
</body>
</html>
