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

  <main class="admin-main flex-1 md:ml-64 pt-28 md:pt-16 flex flex-col min-h-screen">
    <header class="admin-hero-pattern pt-8 pb-12 px-gutter">
      <div class="rise-in max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
          <h1 class="text-white font-headline-xl text-headline-xl mb-2">ภาพรวมการใช้งานห้องสมุด</h1>
          <p id="ad-subtitle" class="text-on-primary-container font-body-lg text-body-lg">กำลังโหลดข้อมูลการเข้าใช้…</p>
        </div>
        <div class="flex items-center gap-3 bg-white/10 backdrop-blur-md p-2 rounded-xl border border-white/20">
          <div class="px-4 py-2">
            <p id="view-select-label" class="text-[10px] text-white/60 font-label-caps mb-1 uppercase">ช่วงเวลา</p>
            <select id="view-select" aria-labelledby="view-select-label" class="bg-transparent text-white border-none focus:ring-0 font-bold p-0 text-body-md cursor-pointer">
              <option value="month" class="text-text-primary">เดือนนี้</option>
              <option value="week" class="text-text-primary">7 วันล่าสุด</option>
              <option value="today" class="text-text-primary">วันนี้</option>
            </select>
          </div>
          <div class="h-10 w-[1px] bg-white/20"></div>
          <a class="flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg hover:bg-primary-container transition-all active:scale-95" href="/admin/reports/print" aria-label="ศูนย์รายงาน">
            <span class="material-symbols-outlined">print</span>
            <span class="font-label-caps">พิมพ์รายงาน</span>
          </a>
        </div>
      </div>
    </header>

    <!-- KPI cards live just below the hero and overlap it only slightly, so
         they read as elevated cards on the white page instead of blending
         into the dark-blue band. -->
    <div class="max-w-7xl mx-auto w-full px-gutter -mt-6 relative z-10">
      <div class="admin-kpi-grid rise-in-group grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="kpi-icon w-12 h-12 rounded-full bg-primary/10 dark:bg-primary-fixed-dim/15 flex items-center justify-center text-primary dark:text-primary-fixed-dim mb-4">
            <span class="material-symbols-outlined">group</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">จำนวนครั้งที่เข้าใช้
            <button type="button" class="kpi-info" data-kpi-note="kpi-total-note" aria-expanded="false" aria-controls="kpi-total-note" aria-label="คำอธิบาย จำนวนครั้งที่เข้าใช้"><span class="material-symbols-outlined" aria-hidden="true">info</span></button>
          </p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <p id="kpi-total" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</p>
          <p id="kpi-total-note" class="kpi-note hidden">จำนวนครั้งที่มีการเข้าใช้ห้องสมุดทั้งหมดในช่วงเวลาที่เลือก</p>
        </div>
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="kpi-icon w-12 h-12 rounded-full bg-secondary-container/10 dark:bg-secondary-fixed-dim/15 flex items-center justify-center text-secondary dark:text-secondary-fixed-dim mb-4">
            <span class="material-symbols-outlined">person_search</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">ผู้ใช้บริการ (ไม่ซ้ำคน)
            <button type="button" class="kpi-info" data-kpi-note="kpi-unique-note" aria-expanded="false" aria-controls="kpi-unique-note" aria-label="คำอธิบาย ผู้ใช้บริการ (ไม่ซ้ำคน)"><span class="material-symbols-outlined" aria-hidden="true">info</span></button>
          </p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <p id="kpi-unique" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</p>
          <p id="kpi-unique-note" class="kpi-note hidden">จำนวนผู้เข้าใช้ที่ไม่ซ้ำกัน นับแต่ละคนเพียง 1 ครั้ง ไม่ว่าจะเข้ามากี่รอบ</p>
        </div>
        <div class="lift-on-hover bg-white dark:bg-dm-surface rounded-xl p-6 shadow-card border border-outline-variant/30 dark:border-dm-border">
          <div class="kpi-icon w-12 h-12 rounded-full bg-accent-stats/10 dark:bg-accent-stats/25 flex items-center justify-center text-accent-stats mb-4">
            <span class="material-symbols-outlined">calendar_today</span>
          </div>
          <p class="text-on-surface-variant dark:text-dm-text-secondary font-label-caps uppercase text-xs mb-1">เฉลี่ยการเข้าใช้ต่อวัน
            <button type="button" class="kpi-info" data-kpi-note="kpi-avg-note" aria-expanded="false" aria-controls="kpi-avg-note" aria-label="คำอธิบาย เฉลี่ยการเข้าใช้ต่อวัน"><span class="material-symbols-outlined" aria-hidden="true">info</span></button>
          </p>
          <div class="skeleton kpi-skeleton h-8 w-20"></div>
          <p id="kpi-avg" class="hidden kpi-value text-headline-lg font-headline-lg text-primary dark:text-primary-fixed-dim font-bold font-label-code">0</p>
          <p id="kpi-avg-note" class="kpi-note hidden">จำนวนครั้งที่เข้าใช้ห้องสมุดโดยเฉลี่ยในแต่ละวัน</p>
        </div>
        <div class="lift-on-hover bg-primary-container rounded-xl p-6 shadow-stamp border border-primary relative overflow-hidden">
          <div class="absolute top-0 right-0 p-4">
            <div class="w-3 h-3 bg-white rounded-full stamp-pulse"></div>
          </div>
          <div class="kpi-icon w-12 h-12 rounded-full bg-white/20 flex items-center justify-center text-white mb-4">
            <span class="material-symbols-outlined">meeting_room</span>
          </div>
          <p class="text-on-primary-container font-label-caps uppercase text-xs mb-1">ผู้ที่อยู่ในห้องสมุดขณะนี้
            <button type="button" class="kpi-info on-blue" data-kpi-note="kpi-inside-note" aria-expanded="false" aria-controls="kpi-inside-note" aria-label="คำอธิบาย ผู้ที่อยู่ในห้องสมุดขณะนี้"><span class="material-symbols-outlined" aria-hidden="true">info</span></button>
          </p>
          <div class="skeleton kpi-skeleton h-8 w-20 bg-white/20"></div>
          <p id="kpi-inside" class="hidden kpi-value text-headline-lg font-headline-lg text-white font-bold font-label-code">0</p>
          <p id="kpi-inside-note" class="kpi-note on-blue hidden">จำนวนผู้ที่เช็กอินแล้วและยังไม่ได้เช็กเอาต์ออก</p>
        </div>
      </div>
    </div>

    <div class="admin-kpi-clear max-w-7xl mx-auto w-full px-gutter pt-12 pb-12 grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- วางไว้บนสุดของส่วนเนื้อหา เพราะเดิมไม่มีช่องนี้อยู่เลย ข้อความประกาศ
           ถูกเขียนตายไว้ในหน้านักศึกษา เจ้าหน้าที่จึงหาที่แก้ไม่เจอ -->
      <section class="lg:col-span-3 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <div class="mb-6">
          <h2 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">ประกาศถึงนักศึกษา</h2>
          <p class="text-on-surface-variant dark:text-dm-text-secondary text-body-md">ข้อความที่พิมพ์ไว้ตรงนี้จะขึ้นบนหน้าหลักของนักศึกษาทุกคน</p>
        </div>
        <form id="announcement-form" class="ann-form">
          <label for="announcement-input" class="ann-label">ข้อความประกาศ</label>
          <textarea id="announcement-input" class="ann-input" rows="3" maxlength="500"
            placeholder="เช่น ห้องสมุดปิดปรับปรุงวันศุกร์ที่ 28 ส.ค. 2569"></textarea>
          <div class="ann-row">
            <label class="ann-toggle" for="announcement-enabled">
              <input type="checkbox" id="announcement-enabled">
              <span>แสดงประกาศนี้บนหน้านักศึกษา</span>
            </label>
            <span class="ann-count" id="announcement-count" aria-live="polite">0 / 500</span>
          </div>
          <p id="announcement-error" role="alert" class="ann-error hidden"></p>
          <div class="ann-actions">
            <p class="ann-meta" id="announcement-meta"></p>
            <button type="submit" id="announcement-save" class="ann-save">บันทึกประกาศ</button>
          </div>
        </form>
      </section>

      <!-- แถวโดนัท: ตอบ "ผู้ใช้เป็นใคร" ก่อนกราฟที่ตอบ "ใช้เมื่อไร" ด้านล่าง
           สามกล่องเรียงเท่ากันบนจอกว้าง และซ้อนกันเองบนมือถือ -->
      <section class="lg:col-span-3 dash-donut-row">
        <div class="dash-panel">
          <div class="dash-panel-head">
            <h2>สัดส่วนผู้ใช้ตามระดับชั้น</h2>
            <p>นับผู้ใช้ไม่ซ้ำคนในช่วงเวลาที่เลือก</p>
          </div>
          <div id="donut-level" class="dash-panel-body"></div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <h2>สัดส่วนเพศของผู้ใช้บริการ</h2>
            <p>นับผู้ใช้ไม่ซ้ำคนในช่วงเวลาที่เลือก</p>
          </div>
          <div id="donut-gender" class="dash-panel-body"></div>
        </div>

        <div class="dash-panel">
          <div class="dash-panel-head">
            <h2>สัดส่วนการเข้าใช้ตามแผนกวิชา</h2>
            <p>นับเป็นจำนวนครั้ง แผนกที่เหลือรวมเป็น “อื่นๆ”</p>
          </div>
          <div id="donut-dept" class="dash-panel-body"></div>
        </div>
      </section>

      <section class="lg:col-span-2 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <div class="mb-8">
          <h2 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">แนวโน้มการเข้าใช้</h2>
          <p class="text-on-surface-variant dark:text-dm-text-secondary text-body-md">จำนวนการเช็คอิน/เช็คเอาต์รายวันในช่วงเวลาที่เลือก — เลื่อนเมาส์หรือแตะแท่งกราฟเพื่อดูตัวเลขรายวัน คลิกเพื่อดูรายละเอียดของวันนั้น</p>
        </div>
        <div class="trend-chart" id="trend-chart"></div>
        <div id="trend-detail" class="mt-4 rounded-lg bg-surface-container-low dark:bg-dm-bg px-4 py-3 text-body-md font-bold text-primary dark:text-primary-fixed-dim" aria-live="polite">แตะหรือคลิกแท่งกราฟเพื่อดูจำนวนคนเข้าใช้ในแต่ละวัน</div>
      </section>

      <section class="bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <h2 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim mb-6">สรุปสั้น</h2>
        <div class="p-4 bg-surface-container-low dark:bg-dm-bg rounded-xl border border-dashed border-outline-variant dark:border-dm-border flex items-center gap-4">
          <span class="material-symbols-outlined text-primary dark:text-primary-fixed-dim text-3xl">lightbulb</span>
          <p class="text-sm text-on-surface-variant dark:text-dm-text-secondary italic" id="peak-text">กำลังคำนวณช่วงเวลาที่มีคนใช้มากที่สุด…</p>
        </div>
      </section>

      <section class="lg:col-span-3 bg-white dark:bg-dm-surface rounded-2xl shadow-sm border border-outline-variant/30 dark:border-dm-border p-8">
        <div class="mb-8">
          <h2 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim">ช่วงเวลาและวันที่คนเข้าใช้มากที่สุด</h2>
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
  <script src="/assets/js/admin-announcement.js?v=<?= ntc_asset_v('assets/js/admin-announcement.js') ?>"></script>
</body>
</html>
