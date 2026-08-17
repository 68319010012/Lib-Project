<?php
$requireAdmin = true;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'ภาพรวม'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-background dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex transition-colors duration-200">
  <?php $variant = 'admin'; include __DIR__ . '/partials/header.php'; ?>
  <?php $active = 'admin-dashboard'; include __DIR__ . '/partials/admin-sidebar.php'; ?>

  <main class="flex-1 md:ml-64 pt-28 md:pt-16 flex flex-col min-h-screen">
    <header class="admin-hero-pattern pt-8 pb-32 px-gutter">
      <div class="rise-in max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
          <h2 class="text-white font-headline-xl text-headline-xl mb-2">ภาพรวมการใช้งานห้องสมุด</h2>
          <p id="ad-subtitle" class="text-on-primary-container font-body-lg text-body-lg">กำลังโหลดข้อมูลการเข้าใช้…</p>
        </div>
        <div class="flex items-center gap-3 bg-white/10 backdrop-blur-md p-2 rounded-xl border border-white/20">
          <div class="px-4 py-2">
            <p class="text-[10px] text-white/60 font-label-caps mb-1 uppercase">ช่วงเวลา</p>
            <select id="view-select" class="bg-transparent text-white border-none focus:ring-0 font-bold p-0 text-body-md cursor-pointer">
              <option value="month" class="text-text-primary">เดือนนี้</option>
              <option value="week" class="text-text-primary">7 วันล่าสุด</option>
              <option value="today" class="text-text-primary">วันนี้</option>
            </select>
          </div>
          <div class="h-10 w-[1px] bg-white/20"></div>
          <a class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-container transition-all active:scale-95" href="/admin/reports/print" target="_blank" rel="noreferrer">
            <span class="material-symbols-outlined">print</span>
            <span class="font-label-caps">พิมพ์รายงาน</span>
          </a>
        </div>
      </div>

      <div class="rise-in-group max-w-7xl mx-auto grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-12 relative z-10 translate-y-16">
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="w-12 h-12 rounded-full bg-primary/10 dark:bg-primary-fixed-dim/15 flex items-center justify-center text-primary dark:text-primary-fixed-dim mb-4">
            <span class="material-symbols-outlined">group</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">จำนวนรายการทั้งหมด</p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <h3 id="kpi-total" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</h3>
        </div>
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="w-12 h-12 rounded-full bg-secondary-container/10 dark:bg-secondary-fixed-dim/15 flex items-center justify-center text-secondary dark:text-secondary-fixed-dim mb-4">
            <span class="material-symbols-outlined">person_search</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">ผู้ใช้งานไม่ซ้ำคน</p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <h3 id="kpi-unique" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</h3>
        </div>
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="w-12 h-12 rounded-full bg-accent-stats/10 dark:bg-accent-stats/25 flex items-center justify-center text-accent-stats mb-4">
            <span class="material-symbols-outlined">calendar_today</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">เฉลี่ยต่อวัน</p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <h3 id="kpi-avg" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</h3>
        </div>
        <div class="lift-on-hover bg-primary-container rounded-xl p-6 shadow-stamp border border-primary relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4">
            <div class="w-3 h-3 bg-white rounded-full stamp-pulse"></div>
          </div>
          <div class="w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white mb-4">
            <span class="material-symbols-outlined">meeting_room</span>
          </div>
          <p class="text-on-primary-container font-label-caps uppercase text-xs mb-1">อยู่ในห้องสมุดตอนนี้ (โดยประมาณ)</p>
          <div class="skeleton kpi-skeleton h-8 w-20 bg-white/20"></div>
          <h3 id="kpi-inside" class="hidden kpi-value text-headline-lg font-headline-lg text-white font-bold font-label-code">0</h3>
        </div>
      </div>
    </header>

    <div class="max-w-7xl mx-auto w-full px-gutter pt-24 pb-12 grid grid-cols-1 lg:grid-cols-3 gap-8">
      <section class="lg:col-span-2 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <div class="mb-8">
          <h4 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">แนวโน้มการเข้าใช้</h4>
          <p class="text-on-surface-variant dark:text-dm-text-secondary text-body-md">จำนวนการเช็คอิน/เช็คเอาต์รายวันในช่วงเวลาที่เลือก — แตะหรือคลิกแท่งกราฟเพื่อดูตัวเลขและวันที่</p>
        </div>
        <div class="h-64 flex items-end justify-between gap-1 px-2 relative" id="trend-bars"></div>
        <div class="flex justify-between mt-2 gap-1 px-2 text-[10px] font-label-caps text-outline dark:text-dm-text-secondary uppercase tracking-wider" id="trend-axis"></div>
        <div id="trend-detail" class="mt-4 rounded-lg bg-surface-container-low dark:bg-dm-bg px-4 py-3 text-body-md font-bold text-primary dark:text-primary-fixed-dim" aria-live="polite">แตะหรือคลิกแท่งกราฟด้านบนเพื่อดูจำนวนคนเข้าใช้ในแต่ละวัน</div>
      </section>

      <section class="bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <h4 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim mb-6">แผนกที่เข้าใช้มากที่สุด</h4>
        <div class="space-y-6" id="dept-bars">
          <p class="text-body-md text-text-secondary dark:text-dm-text-secondary">กำลังโหลด…</p>
        </div>
        <div class="mt-10 p-4 bg-surface-container-low dark:bg-dm-bg rounded-xl border border-dashed border-outline-variant dark:border-dm-border flex items-center gap-4">
          <span class="material-symbols-outlined text-primary dark:text-primary-fixed-dim text-3xl">lightbulb</span>
          <p class="text-sm text-on-surface-variant dark:text-dm-text-secondary italic" id="peak-text">กำลังคำนวณช่วงเวลาที่มีคนใช้มากที่สุด…</p>
        </div>
      </section>

      <section class="lg:col-span-3 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <div class="mb-8">
          <h4 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">ช่วงเวลาและวันที่คนเข้าใช้มากที่สุด</h4>
          <p class="text-on-surface-variant dark:text-dm-text-secondary text-body-md">นับเฉพาะการเช็คอิน (การเข้ามาใช้ห้องสมุด) ในช่วงเวลาที่เลือก แท่งเข้มคือช่วงที่คนเยอะที่สุด</p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
          <div>
            <p class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary uppercase mb-6">ช่วงเวลาที่คนเข้าใช้ (ตามชั่วโมง)</p>
            <div class="h-64 flex items-end justify-between gap-1" id="hour-bars"></div>
            <div class="flex justify-between mt-2 gap-1 text-[10px] font-label-caps text-outline dark:text-dm-text-secondary uppercase tracking-wider" id="hour-axis"></div>
            <p class="text-[10px] text-outline dark:text-dm-text-secondary mt-2">หน่วยเป็นชั่วโมง (น.) — แสดงเฉพาะช่วงที่ห้องสมุดมีการเข้าใช้</p>
          </div>
          <div>
            <p class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary uppercase mb-6">วันในสัปดาห์ที่คนเข้าใช้</p>
            <div class="space-y-3" id="weekday-bars"></div>
          </div>
        </div>
      </section>
    </div>

    <footer class="w-full py-8 mt-auto bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border">
      <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
        <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NTC</span>
        <p class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary opacity-80">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </footer>
  </main>

  <div id="day-detail-modal" class="hidden fixed inset-0 z-[95] bg-black/50 flex items-start sm:items-center justify-center px-gutter py-8">
    <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-lg w-full max-h-[80vh] flex flex-col">
      <div class="flex items-center justify-between p-6 border-b border-outline-variant dark:border-dm-border flex-shrink-0">
        <div class="min-w-0">
          <h3 id="day-detail-title" class="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim truncate">รายละเอียดวันที่</h3>
          <p id="day-detail-subtitle" class="text-xs text-text-secondary dark:text-dm-text-secondary mt-0.5"></p>
        </div>
        <button
          type="button"
          id="day-detail-close"
          aria-label="ปิด"
          class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container-low dark:hover:bg-dm-bg text-on-surface-variant dark:text-dm-text-secondary flex-shrink-0"
        >
          <span class="material-symbols-outlined">close</span>
        </button>
      </div>
      <div class="p-6 overflow-y-auto space-y-3 flex-grow" id="day-detail-body">
        <p class="text-body-md text-text-secondary dark:text-dm-text-secondary">เลือกวันจากกราฟด้านบน</p>
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
  <script src="/assets/js/admin-dashboard.js?v=<?= ntc_asset_v('assets/js/admin-dashboard.js') ?>"></script>
</body>
</html>
