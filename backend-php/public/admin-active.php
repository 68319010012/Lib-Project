<?php
$requireAdmin = true;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'กำลังใช้งานอยู่'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="flex min-h-screen text-text-primary dark:text-inverse-on-surface bg-background dark:bg-dm-bg transition-colors duration-200">
  <?php $variant = 'admin'; include __DIR__ . '/partials/header.php'; ?>
  <?php $active = 'admin-active.php'; include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="md:ml-64 pt-28 md:pt-16 flex-1 flex flex-col min-h-screen">
    <section class="bg-gradient-to-br from-primary to-primary-container pt-12 pb-24 px-gutter relative overflow-hidden">
      <div class="absolute inset-0 book-spine-pattern opacity-20"></div>
      <div class="max-w-7xl mx-auto relative z-10">
        <h2 class="text-on-primary font-headline-xl text-headline-xl mb-2">ผู้ใช้งานที่กำลังใช้งานอยู่</h2>
        <p class="text-on-primary/80 font-body-lg text-body-lg max-w-2xl">รายชื่อนักศึกษาที่เช็คอินแล้วยังไม่เช็คเอาต์ ณ ขณะนี้ พร้อมระยะเวลาที่อยู่ในห้องสมุด — อัปเดตอัตโนมัติทุก 20 วินาที</p>
      </div>
    </section>

    <div class="max-w-7xl mx-auto w-full px-gutter -mt-12 relative z-20 pb-20">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
          <span class="p-2 bg-primary/10 dark:bg-primary-fixed-dim/15 text-primary dark:text-primary-fixed-dim rounded-lg w-fit mb-4">
            <span class="material-symbols-outlined">sensors</span>
          </span>
          <p class="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">อยู่ในห้องสมุดตอนนี้</p>
          <p class="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim" id="active-stat-count">–</p>
        </div>
        <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
          <span class="p-2 bg-secondary/10 dark:bg-secondary-fixed-dim/15 text-secondary dark:text-secondary-fixed-dim rounded-lg w-fit mb-4">
            <span class="material-symbols-outlined">schedule</span>
          </span>
          <p class="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">อยู่นานที่สุดตอนนี้</p>
          <p class="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim" id="active-stat-longest">–</p>
        </div>
      </div>

      <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-sm border border-outline-variant dark:border-dm-border overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead class="bg-surface-container-high dark:bg-dm-bg">
              <tr>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">ชื่อนักศึกษา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">รหัสนักศึกษา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">แผนกวิชา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">เช็คอินเมื่อ</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">อยู่มาแล้ว</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider text-right">จัดการ</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30 dark:divide-dm-border" id="active-tbody">
              <tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="6">กำลังโหลด…</td></tr>
            </tbody>
          </table>
        </div>
        <div class="p-6 bg-surface-container-low dark:bg-dm-bg">
          <p class="text-label-caps font-label-caps text-text-secondary dark:text-dm-text-secondary" id="active-count">พบ 0 คน</p>
        </div>
      </div>
    </div>

    <footer class="w-full py-8 mt-auto bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border">
      <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
        <p class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NTC</p>
        <p class="text-body-md font-body-md text-on-surface-variant dark:text-dm-text-secondary">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </footer>
  </main>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/admin-sidebar.js?v=<?= ntc_asset_v('assets/js/admin-sidebar.js') ?>"></script>
  <script src="/assets/js/admin-active.js?v=<?= ntc_asset_v('assets/js/admin-active.js') ?>"></script>
</body>
</html>
