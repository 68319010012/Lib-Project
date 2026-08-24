<?php
// หน้าเปลี่ยนรหัสผ่าน — แยกออกจากหน้าโปรไฟล์
//
// เปิดให้ทั้งนักศึกษาและเจ้าหน้าที่ ($requireAdmin = false) เพราะทั้งสองฝ่าย
// ต่างก็มีรหัสผ่านของตัวเอง และ /profile เป็นหน้าของนักศึกษาเท่านั้น ถ้าผูก
// การเปลี่ยนรหัสผ่านไว้กับหน้านั้น เจ้าหน้าที่จะไม่มีทางเปลี่ยนรหัสตัวเองเลย
//
// ฝั่งเซิร์ฟเวอร์ใช้ POST /profile/change-password ของเดิม (handle_change_password
// ใน src/handlers/profile_handlers.php) ไม่ได้สร้าง endpoint ใหม่ — การตรวจรหัส
// ผ่านปัจจุบัน การ hash และการออก session id ใหม่ยังเป็นโค้ดชุดเดิมทั้งหมด
$requireAdmin = false;
require __DIR__ . '/partials/guard.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$backHref = $isAdmin ? '/admin-dashboard' : '/profile';
$backLabel = $isAdmin ? 'กลับไปหน้าภาพรวม' : 'กลับไปหน้าข้อมูลผู้ใช้';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'เปลี่ยนรหัสผ่าน'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-surface dark:bg-dm-bg font-body-md text-on-surface dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
  <?php $variant = $isAdmin ? 'admin' : 'student'; include __DIR__ . '/partials/header.php'; ?>

  <?php if ($isAdmin): ?>
    <?php $active = 'change-password'; include __DIR__ . '/partials/admin-sidebar.php'; ?>
  <?php else: ?>
    <?php $studentActive = 'change-password'; include __DIR__ . '/partials/student-sidebar.php'; ?>
  <?php endif; ?>

  <main class="<?= $isAdmin ? 'admin-main md:ml-64 pt-28 md:pt-16' : 'flex-1 lg:ml-64 pt-28 lg:pt-16' ?>">
    <!-- px-gutter อยู่ที่กล่องชั้นในทั้งหัวเรื่องและการ์ด ทั้งคู่จึงเป็นกล่อง
         cp-measure + px-gutter เหมือนกันเป๊ะ ขอบซ้ายจึงตรงแนวกันจริง
         (ถ้า px-gutter อยู่ที่ <section> หัวเรื่องจะเยื้องซ้ายกว่าการ์ด 24px) -->
    <section class="hero-pattern py-12 md:h-[240px] md:py-0 flex items-center">
      <div class="rise-in cp-measure cp-hero mx-auto w-full px-gutter text-white">
        <h1 class="font-headline-xl text-headline-xl mb-2">เปลี่ยนรหัสผ่าน</h1>
        <p class="text-body-lg font-body-lg opacity-80">เปลี่ยนรหัสผ่านสำหรับเข้าสู่ระบบของคุณ</p>
      </div>
    </section>

    <div class="cp-measure mx-auto px-gutter -mt-8 md:-mt-16 mb-12 relative z-10 w-full">
      <div class="rise-in bg-surface-white dark:bg-dm-surface rounded-xl shadow-card overflow-hidden border border-outline-variant/30 dark:border-dm-border">
        <div class="p-6 sm:p-8">
          <a class="cp-back" href="<?= htmlspecialchars($backHref) ?>">
            <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            <?= htmlspecialchars($backLabel) ?>
          </a>

          <p id="cp-alert" class="cp-alert" role="alert" aria-live="assertive" hidden>
            <span class="material-symbols-outlined" aria-hidden="true">error</span>
            <span class="cp-alert-text"></span>
          </p>
          <p id="cp-success" class="cp-success" role="status" aria-live="polite" hidden>
            <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
            <span>เปลี่ยนรหัสผ่านสำเร็จ ครั้งต่อไปให้เข้าสู่ระบบด้วยรหัสผ่านใหม่</span>
          </p>

          <!-- novalidate: ตรวจเองเพื่อให้ข้อความอยู่ใต้ช่องที่ผิดเป็นภาษาไทย
               แทนฟองเตือนของเบราว์เซอร์ที่ใช้ภาษาตามเครื่องผู้ใช้
               data-min-length มาจาก MIN_PASSWORD_LENGTH ใน src/constants.php
               ตัวเดียวกับที่ฝั่งเซิร์ฟเวอร์ใช้ตรวจ จะได้ไม่มีเลขสองชุดที่ไม่ตรงกัน -->
          <form class="cp-form" id="change-password-form" novalidate data-min-length="<?= (int) MIN_PASSWORD_LENGTH ?>">
            <div class="cp-field">
              <label for="cp-current">รหัสผ่านปัจจุบัน</label>
              <div class="cp-wrap">
                <span class="material-symbols-outlined cp-icon" aria-hidden="true">lock</span>
                <input id="cp-current" type="password" autocomplete="current-password" required
                       placeholder="กรอกรหัสผ่านปัจจุบัน" aria-describedby="cp-current-error" />
                <button type="button" class="cp-toggle pw-toggle" aria-controls="cp-current"
                        aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                  <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                </button>
              </div>
              <p class="cp-error" id="cp-current-error" hidden></p>
            </div>

            <div class="cp-field">
              <label for="cp-new">รหัสผ่านใหม่</label>
              <div class="cp-wrap">
                <span class="material-symbols-outlined cp-icon" aria-hidden="true">lock_reset</span>
                <input id="cp-new" type="password" autocomplete="new-password" required
                       minlength="<?= (int) MIN_PASSWORD_LENGTH ?>"
                       placeholder="กรอกรหัสผ่านใหม่" aria-describedby="cp-new-error cp-rules" />
                <button type="button" class="cp-toggle pw-toggle" aria-controls="cp-new"
                        aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                  <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                </button>
              </div>
              <p class="cp-error" id="cp-new-error" hidden></p>
            </div>

            <div class="cp-field">
              <label for="cp-confirm">ยืนยันรหัสผ่านใหม่</label>
              <div class="cp-wrap">
                <span class="material-symbols-outlined cp-icon" aria-hidden="true">verified_user</span>
                <input id="cp-confirm" type="password" autocomplete="new-password" required
                       placeholder="กรอกรหัสผ่านใหม่อีกครั้ง" aria-describedby="cp-confirm-error" />
                <button type="button" class="cp-toggle pw-toggle" aria-controls="cp-confirm"
                        aria-pressed="false" aria-label="แสดงรหัสผ่าน" title="แสดงรหัสผ่าน">
                  <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                </button>
              </div>
              <p class="cp-error" id="cp-confirm-error" hidden></p>
            </div>

            <div class="cp-rules" id="cp-rules">
              <p class="cp-rules-title">ข้อกำหนดรหัสผ่าน</p>
              <ul>
                <li id="cp-rule-length">
                  <span class="material-symbols-outlined" aria-hidden="true">radio_button_unchecked</span>
                  อย่างน้อย <?= (int) MIN_PASSWORD_LENGTH ?> ตัวอักษร
                </li>
                <li id="cp-rule-match">
                  <span class="material-symbols-outlined" aria-hidden="true">radio_button_unchecked</span>
                  รหัสผ่านใหม่และการยืนยันตรงกัน
                </li>
              </ul>
            </div>

            <div class="cp-actions">
              <button id="cp-submit" class="cp-submit" type="submit">
                <span class="material-symbols-outlined" aria-hidden="true">key</span>
                <span>เปลี่ยนรหัสผ่าน</span>
                <span class="cp-spinner" aria-hidden="true"></span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-surface-container-highest dark:bg-dm-surface w-full py-8 border-t border-outline-variant dark:border-dm-border mt-auto">
    <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
      <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NTC</span>
    </div>
  </footer>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/confirm-modal.js?v=<?= ntc_asset_v('assets/js/confirm-modal.js') ?>"></script>
  <?php if ($isAdmin): ?>
  <script src="/assets/js/admin-sidebar.js?v=<?= ntc_asset_v('assets/js/admin-sidebar.js') ?>"></script>
  <?php else: ?>
  <!-- renderPager() + history-modal: เมนูบัญชีของนักศึกษามีปุ่ม "ประวัติการเข้าใช้"
       ซึ่งเปิดหน้าต่างประวัติในหน้าไหนก็ได้ที่ใส่ header ของนักศึกษา -->
  <script src="/assets/js/pagination.js?v=<?= ntc_asset_v('assets/js/pagination.js') ?>"></script>
  <script src="/assets/js/history-modal.js?v=<?= ntc_asset_v('assets/js/history-modal.js') ?>"></script>
  <script src="/assets/js/student-sidebar.js?v=<?= ntc_asset_v('assets/js/student-sidebar.js') ?>"></script>
  <?php endif; ?>
  <script src="/assets/js/password-toggle.js?v=<?= ntc_asset_v('assets/js/password-toggle.js') ?>"></script>
  <script src="/assets/js/change-password.js?v=<?= ntc_asset_v('assets/js/change-password.js') ?>"></script>
</body>
</html>
