<?php
$requireAdmin = true;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'ทำเนียบสมาชิก'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-background dark:bg-dm-bg font-body-md text-on-background dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
  <?php $variant = 'admin'; include __DIR__ . '/partials/header.php'; ?>
  <?php $active = 'admin-members'; include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="md:ml-64 pt-28 md:pt-16 flex-grow flex flex-col min-h-screen">
    <!-- py-10 (auto height) on mobile so the wrapping subtitle never gets
         pushed into the search-card's -mt overlap zone below; fixed
         h-[240px] only once it's guaranteed to fit (md:+). -->
    <header class="admin-hero-pattern text-on-primary py-10 md:h-[240px] md:py-0 flex items-end pb-8 md:pb-20 px-gutter">
      <div class="max-w-7xl w-full mx-auto relative z-10 flex flex-col md:flex-row justify-between items-end gap-4">
        <div>
          <h2 class="font-headline-xl text-headline-xl mb-2">ทำเนียบสมาชิก</h2>
          <p class="font-body-lg text-body-lg text-on-primary/80">รายชื่อนักศึกษาที่ได้รับการอนุมัติ บัญชีจะถูกสร้างขึ้นอัตโนมัติเมื่อนักศึกษาสมัครสมาชิก</p>
        </div>
        <div class="hidden md:flex gap-4 mb-2">
          <div class="bg-primary-container/40 p-4 rounded-xl border border-white/10 backdrop-blur-sm">
            <p class="text-label-caps font-label-caps opacity-70">สมาชิกทั้งหมด</p>
            <p class="text-headline-md font-label-code" id="members-total-badge">–</p>
          </div>
        </div>
      </div>
    </header>

    <section class="max-w-7xl w-full mx-auto px-gutter -mt-6 md:-mt-10 relative z-20 py-8 flex-grow">
      <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-md border border-outline-variant/30 dark:border-dm-border flex flex-col lg:flex-row gap-6 items-stretch lg:items-end mb-8">
        <div class="w-full lg:flex-1 relative">
          <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-outline">search</span>
          <input id="members-search" class="w-full pl-12 pr-4 py-3 bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-transparent font-body-md" placeholder="ค้นหาด้วยรหัสนักศึกษา ชื่อ หรือชื่อผู้ใช้..." type="text" />
        </div>
        <div class="flex flex-col sm:flex-row gap-4 w-full lg:w-auto">
          <div class="flex flex-col gap-1 w-full sm:w-40 sm:shrink-0">
            <label class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">แผนกวิชา</label>
            <select id="members-department" class="w-full bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary"></select>
          </div>
          <div class="flex flex-col gap-1 w-full sm:w-40 sm:shrink-0">
            <label class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">ระดับชั้น</label>
            <select id="members-level" class="w-full bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary">
              <option value="">ทุกระดับชั้น</option>
              <option value="ปวช.">ปวช.</option>
              <option value="ปวส.">ปวส.</option>
            </select>
          </div>
          <div class="flex flex-col gap-1 w-full sm:w-40 sm:shrink-0">
            <label class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary ml-1">ชั้นปี</label>
            <select id="members-year-level" class="w-full bg-surface-container-low dark:bg-dm-bg dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg py-2.5 px-3 text-body-md focus:ring-primary focus:border-primary"></select>
          </div>
        </div>
      </div>

      <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-sm border border-outline-variant dark:border-dm-border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-surface-container-low dark:bg-dm-bg border-b border-outline-variant dark:border-dm-border">
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">รหัสนักศึกษา</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">ชื่อ-สกุล</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim">แผนกวิชา</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-center">ระดับชั้น</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-center">ชั้นปี</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-right">เข้าใช้ล่าสุด</th>
                <th class="px-6 py-4 text-label-caps font-label-caps text-primary dark:text-primary-fixed-dim text-right">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30 dark:divide-dm-border" id="members-tbody">
              <tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="7">กำลังโหลด…</td></tr>
            </tbody>
          </table>
        </div>
        <div class="px-6 py-4 bg-surface-container-low dark:bg-dm-bg">
          <p class="text-sm text-on-surface-variant dark:text-dm-text-secondary" id="members-count">พบ 0 รายการ</p>
          <div class="pager" id="members-pager"></div>
        </div>
      </div>
    </section>

    <footer class="w-full py-8 bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border mt-auto">
      <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
        <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">พอร์ทัลเจ้าหน้าที่ห้องสมุด NTC</span>
        <p class="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-sm">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </footer>
  </main>

  <!-- Reset-password result — a large centered modal, not the old cramped
       corner toast: the admin has to read this temp password aloud to the
       student, so it gets big monospace type, click-to-select, and a copy
       button instead of being buried in a one-line toast sentence. -->
  <div id="reset-result-modal" class="hidden fixed inset-0 z-[100] bg-black/50 flex items-center justify-center px-gutter">
    <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-md w-full p-8">
      <div class="flex flex-col items-center text-center">
        <div class="w-12 h-12 rounded-full bg-status-success/10 flex items-center justify-center text-status-success mb-4">
          <span class="material-symbols-outlined text-4xl">lock_reset</span>
        </div>
        <h3 class="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim mb-1">รีเซ็ตรหัสผ่านสำเร็จ</h3>
        <p id="reset-result-name" class="text-body-md text-text-secondary dark:text-dm-text-secondary mb-6"></p>

        <p class="text-label-caps font-label-caps text-secondary mb-2">รหัสผ่านชั่วคราว</p>
        <div class="w-full bg-surface-container-low dark:bg-dm-bg rounded-lg py-4 px-4 mb-3">
          <span id="reset-result-password" class="font-label-code text-headline-lg text-primary dark:text-primary-fixed-dim tracking-widest select-all"></span>
        </div>
        <button type="button" id="reset-result-copy" class="inline-flex items-center gap-2 text-sm font-bold text-primary dark:text-primary-fixed-dim hover:underline mb-6">
          <span class="material-symbols-outlined text-lg">content_copy</span>
          <span id="reset-result-copy-label">คัดลอกรหัสผ่าน</span>
        </button>

        <p class="text-xs text-text-secondary dark:text-dm-text-secondary mb-6">แจ้งรหัสนี้ให้นักศึกษา และให้เปลี่ยนรหัสผ่านใหม่หลังเข้าสู่ระบบ</p>

        <button type="button" id="reset-result-close" class="w-full h-11 rounded-full bg-primary text-white font-bold text-sm hover:brightness-95 transition-all">เข้าใจแล้ว</button>
      </div>
    </div>
  </div>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/confirm-modal.js?v=<?= ntc_asset_v('assets/js/confirm-modal.js') ?>"></script>
  <script src="/assets/js/history-modal.js?v=<?= ntc_asset_v('assets/js/history-modal.js') ?>"></script>
  <script src="/assets/js/admin-sidebar.js?v=<?= ntc_asset_v('assets/js/admin-sidebar.js') ?>"></script>
  <script src="/assets/js/constants.js?v=<?= ntc_asset_v('assets/js/constants.js') ?>"></script>
  <script src="/assets/js/pagination.js?v=<?= ntc_asset_v('assets/js/pagination.js') ?>"></script>
  <script src="/assets/js/admin-members.js?v=<?= ntc_asset_v('assets/js/admin-members.js') ?>"></script>
</body>
</html>
