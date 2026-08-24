<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'สมัครสมาชิก'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="login-body is-signup">

  <?php include __DIR__ . '/partials/login-bg.php'; ?>

  <main class="login-shell">
    <section class="login-glass signup-card rise-in">
      <a class="signup-back" href="/login"><span class="material-symbols-outlined" aria-hidden="true">arrow_back</span> ย้อนกลับ</a>
      <h1 class="login-title">สร้างบัญชี</h1>

      <div class="login-identity">
        <img class="login-crest" src="/assets/img/ntc-crest.png?v=<?= ntc_asset_v('assets/img/ntc-crest.png') ?>"
             alt="ตราวิทยาลัยเทคนิคนครนายก" width="104" height="104" decoding="async">
        <p class="login-identity-org">วิทยาลัยเทคนิคนครนายก</p>
        <p class="login-identity-sub">ระบบเช็คชื่อเข้า–ออกห้องสมุด</p>
      </div>

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
              <input id="signup-password" class="has-toggle" autocomplete="new-password" placeholder="อย่างน้อย 8 ตัวอักษร" minlength="8" required type="password" />
              <button type="button" class="login-pw-toggle pw-toggle" aria-controls="signup-password" aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
              </button>
            </div>
          </div>
          <div class="signup-field pw">
            <label for="signup-confirm-password">ยืนยันรหัสผ่าน</label>
            <div class="pw-wrap">
              <span class="material-symbols-outlined pw-icon" aria-hidden="true">verified_user</span>
              <input id="signup-confirm-password" class="has-toggle" autocomplete="new-password" placeholder="กรอกรหัสผ่านอีกครั้ง" minlength="8" required type="password" />
              <button type="button" class="login-pw-toggle pw-toggle" aria-controls="signup-confirm-password" aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
              </button>
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

  <!-- No theme.js — same reason as login.php. -->
  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/constants.js?v=<?= ntc_asset_v('assets/js/constants.js') ?>"></script>
  <script src="/assets/js/password-toggle.js?v=<?= ntc_asset_v('assets/js/password-toggle.js') ?>"></script>
  <script src="/assets/js/signup.js?v=<?= ntc_asset_v('assets/js/signup.js') ?>"></script>
</body>
</html>
