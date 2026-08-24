<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'เข้าสู่ระบบ'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="login-body">

  <!-- พื้นหลังห้องสมุดเลื่อนอัตโนมัติ (crossfade + ซูมช้าแบบ Ken Burns).
       สไลด์ถูกสร้างโดย assets/js/login-bg.js จากรูปใน assets/img/login/.
       ถ้ายังไม่มีรูป จะใช้เฉดไล่สีพรีเมียมที่ค่อยๆ เลื่อนแทน. -->
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
    <section class="login-glass rise-in">
      <h1 class="login-title">เข้าสู่ระบบ</h1>

      <p id="login-error" role="alert" aria-live="assertive" class="login-error hidden"></p>

      <form class="login-form" id="login-form">
        <div class="login-field">
          <label for="login-username">รหัสนักศึกษา</label>
          <div class="login-input-wrap">
            <span class="material-symbols-outlined login-input-icon" aria-hidden="true">person</span>
            <input
              id="login-username" autocomplete="username"
              placeholder="รหัสนักศึกษา หรือชื่อผู้ใช้แอดมิน"
              required type="text"
            />
          </div>
        </div>

        <div class="login-field">
          <label for="login-password">รหัสผ่าน</label>
          <div class="login-input-wrap">
            <span class="material-symbols-outlined login-input-icon" aria-hidden="true">lock</span>
            <input
              id="login-password" autocomplete="current-password"
              class="has-toggle"
              placeholder="••••••••"
              required type="password"
            />
            <button type="button" id="login-password-toggle" class="login-pw-toggle" aria-controls="login-password" aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
              <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
            </button>
          </div>
        </div>

        <div class="login-row">
          <label class="login-remember">
            <input type="checkbox" id="login-remember" />
            <span>จำรหัสนักศึกษาไว้</span>
          </label>
        </div>

        <button id="login-submit" class="login-submit" type="submit">
          <span>เข้าสู่ระบบ</span>
          <span class="material-symbols-outlined" aria-hidden="true">arrow_forward</span>
        </button>

        <p class="login-signup">
          ยังไม่มีบัญชี? <a href="/signup">สมัครสมาชิก</a>
        </p>
      </form>
    </section>
  </main>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/login-bg.js?v=<?= ntc_asset_v('assets/js/login-bg.js') ?>"></script>
  <script src="/assets/js/login.js?v=<?= ntc_asset_v('assets/js/login.js') ?>"></script>
</body>
</html>
