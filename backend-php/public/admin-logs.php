<?php
$requireAdmin = true;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'ประวัติการเช็คชื่อ'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="flex min-h-screen font-body-md text-text-primary dark:text-inverse-on-surface bg-background dark:bg-dm-bg transition-colors duration-200">
  <?php $variant = 'admin'; include __DIR__ . '/partials/header.php'; ?>
  <?php $active = 'admin-logs'; include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="admin-main md:ml-64 pt-28 md:pt-16 flex-1 flex flex-col min-h-screen">
    <section class="admin-hero-pattern pt-12 pb-24 px-gutter relative overflow-hidden">
      <div class="max-w-7xl mx-auto relative z-10 flex flex-col md:flex-row md:items-end justify-between gap-6">
        <div>
          <h1 class="text-on-primary font-headline-xl text-headline-xl mb-2">ประวัติการเช็คชื่อทั้งหมด</h1>
          <p class="text-on-primary/80 font-body-lg text-body-lg max-w-2xl">รายการเช็คอินและเช็คเอาต์ทุกครั้งของห้องสมุด กรองตามวันที่และแผนกวิชาได้</p>
        </div>
        <div class="flex gap-4">
          <a class="bg-secondary-container text-on-secondary-container font-bold px-6 py-3 rounded-lg flex items-center gap-2 hover:scale-105 transition-all shadow-lg stamp-shadow" href="/admin/reports/print" aria-label="ศูนย์รายงาน">
            <span class="material-symbols-outlined">print</span>
            <span>พิมพ์รายงาน</span>
          </a>
        </div>
      </div>
    </section>

    <div class="max-w-7xl mx-auto w-full px-gutter -mt-12 relative z-20 pb-20">
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
          <span class="p-2 bg-primary/10 dark:bg-primary-fixed-dim/15 text-primary dark:text-primary-fixed-dim rounded-lg w-fit mb-4">
            <span class="material-symbols-outlined">person_add</span>
          </span>
          <p class="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">รายการทั้งหมด</p>
          <p class="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim" id="logs-stat-total">–</p>
        </div>
        <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
          <span class="p-2 bg-accent-stats/10 text-accent-stats rounded-lg w-fit mb-4">
            <span class="material-symbols-outlined">group</span>
          </span>
          <p class="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">อยู่ในห้องสมุดตอนนี้</p>
          <p class="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim" id="logs-stat-inside">–</p>
        </div>
        <div class="bg-surface-white dark:bg-dm-surface p-6 rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border flex flex-col justify-between">
          <span class="p-2 bg-secondary/10 dark:bg-secondary-fixed-dim/15 text-secondary dark:text-secondary-fixed-dim rounded-lg w-fit mb-4">
            <span class="material-symbols-outlined">schedule</span>
          </span>
          <p class="text-text-secondary dark:text-dm-text-secondary font-label-caps text-label-caps uppercase">ระยะเวลาเฉลี่ยต่อครั้ง</p>
          <p class="text-headline-md font-label-code text-primary dark:text-primary-fixed-dim" id="logs-stat-avg">–</p>
        </div>
      </div>

      <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-sm border border-outline-variant/30 dark:border-dm-border overflow-hidden">
        <div class="p-6 border-b border-outline-variant/30 dark:border-dm-border bg-surface-container-low dark:bg-dm-bg flex flex-wrap gap-4 items-center">
          <div class="admin-search-field flex-1 min-w-[260px] relative">
            <span class="material-symbols-outlined absolute left-4 top-1/2 -translate-y-1/2 text-text-secondary">search</span>
            <input id="logs-search" aria-label="ค้นหาด้วยชื่อ รหัสนักศึกษา หรือแผนกวิชา" class="w-full pl-12 pr-4 py-3 bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg focus:ring-2 focus:ring-primary focus:border-transparent" placeholder="ค้นหาด้วยชื่อ รหัสนักศึกษา หรือแผนกวิชา..." type="text" />
          </div>
          <select id="logs-action-filter" aria-label="กรองตามประเภทการเช็คชื่อ" class="bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 font-body-md text-body-md focus:ring-2 focus:ring-primary">
            <option value="">ทุกประเภท</option>
            <option value="in">เช็คอิน</option>
            <option value="out">เช็คเอาต์</option>
          </select>
          <input id="logs-date-filter" aria-label="กรองตามวันที่" class="bg-surface-white dark:bg-dm-surface dark:text-inverse-on-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 font-body-md text-body-md focus:ring-2 focus:ring-primary" type="date" />
          <button id="logs-date-clear" type="button" class="bg-surface-white dark:bg-dm-surface border border-outline-variant dark:border-dm-border rounded-lg px-4 py-3 hover:bg-surface-container-high dark:hover:bg-dm-bg transition-colors font-bold dark:text-inverse-on-surface">เดือนนี้</button>
        </div>
        <div class="overflow-x-auto">
          <table class="admin-table w-full text-left border-collapse">
            <thead class="bg-surface-container-high dark:bg-dm-bg">
              <tr>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">วันเวลา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">ชื่อนักศึกษา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">รหัสนักศึกษา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">แผนกวิชา</th>
                <th class="px-6 py-4 font-label-caps text-label-caps text-primary dark:text-primary-fixed-dim uppercase tracking-wider">ประเภท</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-outline-variant/30 dark:divide-dm-border" id="logs-tbody">
              <tr><td class="px-6 py-6 text-on-surface-variant dark:text-dm-text-secondary" colspan="5">กำลังโหลด…</td></tr>
            </tbody>
          </table>
        </div>
        <div class="p-6 bg-surface-container-low dark:bg-dm-bg">
          <p class="text-label-caps font-label-caps text-text-secondary dark:text-dm-text-secondary" id="logs-count">พบ 0 รายการ</p>
          <div class="pager" id="logs-pager"></div>
        </div>
      </div>
    </div>

</main>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/confirm-modal.js?v=<?= ntc_asset_v('assets/js/confirm-modal.js') ?>"></script>
  <script src="/assets/js/history-modal.js?v=<?= ntc_asset_v('assets/js/history-modal.js') ?>"></script>
  <script src="/assets/js/admin-sidebar.js?v=<?= ntc_asset_v('assets/js/admin-sidebar.js') ?>"></script>
  <script src="/assets/js/pagination.js?v=<?= ntc_asset_v('assets/js/pagination.js') ?>"></script>
  <script src="/assets/js/admin-logs.js?v=<?= ntc_asset_v('assets/js/admin-logs.js') ?>"></script>
</body>
</html>
