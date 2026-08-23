<?php
$requireAdmin = false;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'หน้าหลัก'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-surface dark:bg-dm-bg font-body-md text-text-primary dark:text-inverse-on-surface min-h-screen flex flex-col overflow-x-hidden transition-colors duration-200">
  <?php $variant = 'student'; include __DIR__ . '/partials/header.php'; ?>

  <main class="flex-grow pt-16">
    <h1 class="visually-hidden">หน้าหลักนักศึกษา — เช็คอินและประวัติการเข้าใช้ห้องสมุด</h1>
    <section class="relative hero-pattern overflow-hidden flex flex-col justify-center py-8">
      <div class="relative z-10 max-w-7xl mx-auto px-gutter w-full">
        <!-- ข้อความมาจาก GET /announcement ซึ่งเจ้าหน้าที่แก้ได้จากหน้าภาพรวมของแอดมิน
             ซ่อนไว้ก่อนเสมอ แล้วค่อยเปิดเมื่อมีประกาศจริง จะได้ไม่เห็นหัวข้อลอยๆ
             ที่ไม่มีเนื้อหาระหว่างรอโหลด -->
        <div id="announcement-block" class="rise-in mb-4 hidden">
          <div class="announcement-card">
            <span class="announcement-icon material-symbols-outlined" aria-hidden="true">campaign</span>
            <div class="announcement-body">
              <h2 class="announcement-title">ประกาศจากเจ้าหน้าที่</h2>
              <p id="announcement-text" class="announcement-text"></p>
            </div>
          </div>
        </div>

        <div class="rise-in inline-flex items-center gap-3 bg-surface-container-highest/20 text-on-primary px-4 py-2.5 rounded-full border border-on-primary/20 shadow-lg">
          <span id="status-dot" class="relative flex w-3 h-3 rounded-full bg-warning">
            <span id="status-ping" class="hidden absolute inset-0 rounded-full bg-status-success animate-ping"></span>
          </span>
          <span id="status-text" class="text-body-md font-bold">ยังไม่ได้เช็คอินวันนี้</span>
        </div>
      </div>
    </section>

    <div class="max-w-7xl mx-auto px-gutter pt-8 pb-12">
      <div class="rise-in-group flex flex-col gap-8 max-w-3xl mx-auto">
        <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-md border border-outline-variant dark:border-dm-border p-8 flex flex-col items-center justify-center text-center relative overflow-hidden">
          <div id="elapsed-wrap" class="hidden mb-6 flex flex-col items-center">
            <p id="checkin-clock-text" class="text-body-md font-bold text-text-secondary dark:text-dm-text-secondary mb-3"></p>
            <p class="text-label-caps font-label-caps text-secondary mb-1">อยู่มาแล้ว</p>
            <span id="elapsed-time" class="text-headline-xl font-label-code text-primary tracking-widest">--:--:--</span>
          </div>

          <div class="relative mb-8">
            <div id="stamp-pulse-ring" class="hidden absolute -inset-4 rounded-full stamp-pulse bg-status-success/20"></div>
            <button
              id="stamp-btn"
              type="button"
              class="relative w-48 h-48 rounded-full bg-status-success text-on-primary shadow-xl hover:scale-105 active:scale-95 transition-all duration-300 flex flex-col items-center justify-center gap-2 group disabled:opacity-60"
            >
              <span id="stamp-icon" class="material-symbols-outlined text-6xl group-hover:rotate-12 transition-transform">sync_alt</span>
              <span id="stamp-label" class="font-headline-md text-headline-md">เช็คอิน</span>
            </button>
          </div>
          <p id="stamp-hint" class="text-body-md text-text-secondary dark:text-dm-text-secondary max-w-sm mx-auto">กดปุ่มด้านบนเพื่อบันทึกการเข้า-ออกห้องสมุด NTC</p>

          <div id="planned-checkout-wrap" class="hidden flex flex-col items-center gap-2 mt-5">
            <p id="planned-checkout-time" class="text-body-md text-text-secondary dark:text-dm-text-secondary"></p>
            <div class="flex gap-2">
              <button type="button" data-extend="30" class="px-4 py-2 rounded-full bg-surface-container-low dark:bg-dm-bg text-primary dark:text-primary-fixed-dim text-xs font-bold hover:brightness-95 transition-all">ขอเวลาเพิ่ม 30 นาที</button>
              <button type="button" data-extend="60" class="px-4 py-2 rounded-full bg-surface-container-low dark:bg-dm-bg text-primary dark:text-primary-fixed-dim text-xs font-bold hover:brightness-95 transition-all">ขอเวลาเพิ่ม 60 นาที</button>
            </div>
          </div>
        </div>

        <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-md p-6 flex items-center justify-between gap-4">
          <div class="flex items-center gap-4 min-w-0">
            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary dark:text-primary-fixed-dim flex items-center justify-center flex-shrink-0">
              <span class="material-symbols-outlined">history</span>
            </div>
            <div class="min-w-0">
              <p class="text-label-caps font-label-caps text-text-secondary dark:text-dm-text-secondary mb-0.5">ใช้งานล่าสุด</p>
              <p id="last-used-text" class="font-bold text-text-primary dark:text-inverse-on-surface truncate">กำลังโหลด…</p>
            </div>
          </div>
          <button type="button" id="dashboard-view-history" class="flex-shrink-0 text-xs font-bold text-primary dark:text-primary-fixed-dim hover:underline whitespace-nowrap">ดูทั้งหมด</button>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-surface-container-highest dark:bg-dm-surface border-t border-outline-variant dark:border-dm-border w-full py-8 mt-auto">
    <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
      <div class="flex flex-col md:flex-row items-center gap-6">
        <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NTC</span>
        <p class="text-body-md text-on-surface-variant dark:text-dm-text-secondary text-sm">© 2026 ห้องสมุดวิทยาลัยเทคนิคนครนายก สงวนลิขสิทธิ์</p>
      </div>
    </div>
  </footer>

  <div id="checkin-modal" class="hidden fixed inset-0 z-[80] bg-black/50 flex items-center justify-center px-gutter">
    <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-sm w-full p-6">
      <h3 class="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim mb-1">จะอยู่นานแค่ไหน?</h3>
      <p class="text-body-md text-text-secondary dark:text-dm-text-secondary mb-4">เลือกเวลาที่ตั้งใจจะออกจากห้องสมุด</p>

      <div class="flex mb-4 bg-surface-container-low dark:bg-dm-bg rounded-lg p-1">
        <button type="button" data-modal-tab="time" class="flex-1 h-10 rounded-md text-xs font-bold transition-all bg-surface-white dark:bg-dm-surface text-primary dark:text-primary-fixed-dim shadow-sm">เลือกเวลาที่จะออก</button>
        <button type="button" data-modal-tab="hours" class="flex-1 h-10 rounded-md text-xs font-bold transition-all text-on-surface-variant dark:text-dm-text-secondary">เลือกจำนวนชั่วโมง</button>
      </div>

      <div id="modal-panel-time" class="mb-4">
        <p class="block text-xs font-bold text-on-surface-variant dark:text-dm-text-secondary mb-2">เวลาที่จะออก</p>
        <p id="modal-time-closed" class="hidden text-warning text-sm">ห้องสมุดปิดแล้ว</p>
        <div id="modal-time-open">
          <div class="time-wheel" id="modal-time-wheel">
            <div class="time-wheel-band" aria-hidden="true"></div>
            <div class="time-wheel-col" id="modal-wheel-hour" tabindex="0" role="spinbutton" aria-label="ชั่วโมงที่จะออก"></div>
            <span class="time-wheel-sep" aria-hidden="true">:</span>
            <div class="time-wheel-col" id="modal-wheel-minute" tabindex="0" role="spinbutton" aria-label="นาทีที่จะออก"></div>
          </div>
          <p id="modal-time-hint" class="text-xs text-text-secondary dark:text-dm-text-secondary mt-2"></p>
        </div>
      </div>

      <div id="modal-panel-hours" class="hidden mb-4">
        <p id="modal-hours-label" class="block text-xs font-bold text-on-surface-variant dark:text-dm-text-secondary mb-2">จำนวนชั่วโมง</p>
        <div id="modal-hour-buttons" class="grid grid-cols-3 gap-2" role="group" aria-labelledby="modal-hours-label"></div>
        <p id="modal-hours-warning" class="hidden text-warning text-xs mt-2">เวลาที่เลือกเกินเวลาปิดห้องสมุด ระบบจะปรับให้ออกตอนปิดแทน</p>
      </div>

      <p id="modal-error" role="alert" aria-live="assertive" class="hidden text-error text-sm mb-3"></p>

      <button type="button" id="modal-checkin-until-closing" class="w-full h-10 text-xs font-bold text-primary dark:text-primary-fixed-dim mb-4 hover:underline">
        หรือเลือก "จนกว่าจะปิด"
      </button>

      <div class="flex gap-3">
        <button type="button" id="modal-cancel" class="flex-1 h-11 rounded-full border border-outline-variant dark:border-dm-border text-text-primary dark:text-inverse-on-surface font-bold text-sm">ยกเลิก</button>
        <button type="button" id="modal-confirm" class="flex-1 h-11 rounded-full bg-status-success text-white font-bold text-sm">ยืนยันเช็คอิน</button>
      </div>
    </div>
  </div>

  <div id="reminder-banner" class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-[90] w-[calc(100%-32px)] max-w-md bg-gradient-to-br from-warning to-warning/80 text-white rounded-2xl shadow-2xl p-4">
    <p id="reminder-text" class="font-bold text-sm mb-3"></p>
    <div class="flex gap-2">
      <button type="button" data-extend="30" class="flex-1 h-9 rounded-full bg-white/90 text-warning font-bold text-xs">ขอเวลาเพิ่ม 30 นาที</button>
      <button type="button" data-extend="60" class="flex-1 h-9 rounded-full bg-white/90 text-warning font-bold text-xs">ขอเวลาเพิ่ม 60 นาที</button>
      <button type="button" id="reminder-dismiss" class="h-9 px-3 rounded-full bg-white/20 text-white font-bold text-xs">ไม่ต้อง</button>
    </div>
  </div>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/confirm-modal.js?v=<?= ntc_asset_v('assets/js/confirm-modal.js') ?>"></script>
  <!-- renderPager(): the history modal pages server-side now, but the pager
       strip it draws is the same one the admin tables use. -->
  <script src="/assets/js/pagination.js?v=<?= ntc_asset_v('assets/js/pagination.js') ?>"></script>
  <script src="/assets/js/history-modal.js?v=<?= ntc_asset_v('assets/js/history-modal.js') ?>"></script>
  <script src="/assets/js/dashboard.js?v=<?= ntc_asset_v('assets/js/dashboard.js') ?>"></script>
</body>
</html>
