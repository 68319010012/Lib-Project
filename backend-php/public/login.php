<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'เข้าสู่ระบบ'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="login-body">

  <?php include __DIR__ . '/partials/login-bg.php'; ?>

  <main class="login-shell">
    <section class="login-glass rise-in">
      <img class="login-crest" src="/assets/img/ntc-crest.png?v=<?= ntc_asset_v('assets/img/ntc-crest.png') ?>"
           alt="ตราวิทยาลัยเทคนิคนครนายก" width="73" height="73" decoding="async">
      <h1 class="login-title">เข้าสู่ระบบ</h1>
      <p class="login-identity-org">ห้องสมุดวิทยาลัยเทคนิคนครนายก</p>

      <p id="login-error" role="alert" aria-live="assertive" class="login-error hidden"></p>

      <!-- novalidate: ฟองเตือนของเบราว์เซอร์หายไปเองและใช้ภาษาตามเครื่องผู้ใช้
           ซึ่งไม่ใช่ภาษาไทยเสมอ ตรวจเองแล้ววางข้อความไว้ใต้ช่องที่ผิดแทน -->
      <form class="login-form" id="login-form" novalidate>
        <div class="login-field">
          <label for="login-username">รหัสนักศึกษา</label>
          <div class="login-input-wrap">
            <span class="material-symbols-outlined login-input-icon" aria-hidden="true">person</span>
            <input
              id="login-username" autocomplete="username"
              placeholder="รหัสนักศึกษา หรือชื่อผู้ใช้แอดมิน"
              required type="text"
              aria-describedby="login-username-error"
            />
          </div>
          <p class="login-field-error" id="login-username-error" hidden></p>
        </div>

        <div class="login-field">
          <label for="login-password">รหัสผ่าน</label>
          <div class="login-input-wrap">
            <span class="material-symbols-outlined login-input-icon" aria-hidden="true">lock</span>
            <input
              id="login-password" autocomplete="current-password"
              class="has-toggle"
              placeholder="กรอกรหัสผ่าน"
              required type="password"
              aria-describedby="login-password-error"
            />
            <button type="button" id="login-password-toggle" class="login-pw-toggle pw-toggle" aria-controls="login-password" aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
              <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
            </button>
          </div>
          <p class="login-field-error" id="login-password-error" hidden></p>
        </div>

        <div class="login-row">
          <label class="login-remember">
            <input type="checkbox" id="login-remember" />
            <span>จำรหัสนักศึกษาไว้</span>
          </label>
        </div>

        <button id="login-submit" class="login-submit" type="submit">
          <span>เข้าสู่ระบบ</span>
          <span class="material-symbols-outlined login-submit-arrow" aria-hidden="true">arrow_forward</span>
          <span class="login-spinner" aria-hidden="true"></span>
        </button>

        <p class="login-signup">
          ยังไม่มีบัญชี? <a href="/signup">สมัครสมาชิก</a>
        </p>
      </form>
    </section>
  </main>

  <!-- No theme.js: this page has no theme toggle — it is always the light-on-
       photo treatment, so the light/dark switch has nothing to act on here. -->
  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/password-toggle.js?v=<?= ntc_asset_v('assets/js/password-toggle.js') ?>"></script>
  <script src="/assets/js/login.js?v=<?= ntc_asset_v('assets/js/login.js') ?>"></script>
</body>
</html>
