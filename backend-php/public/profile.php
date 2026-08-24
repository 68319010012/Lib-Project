<?php
$requireAdmin = false;
require __DIR__ . '/partials/guard.php';
?>
<!doctype html>
<html lang="th">
<head>
<?php $pageTitle = 'โปรไฟล์'; include __DIR__ . '/partials/head.php'; ?>
</head>
<body class="bg-surface dark:bg-dm-bg font-body-md text-on-surface dark:text-inverse-on-surface min-h-screen flex flex-col transition-colors duration-200">
  <?php $variant = 'student'; include __DIR__ . '/partials/header.php'; ?>

  <?php $studentActive = 'profile'; include __DIR__ . '/partials/student-sidebar.php'; ?>

  <main class="student-main flex-1 lg:ml-64 pt-28 lg:pt-16">
    <!-- py-12 (auto height) on mobile so a 2-line heading never gets pushed
         into the white card's -mt overlap zone below; fixed h-[240px] only
         once the heading is guaranteed to fit on one line (md:+). -->
    <section class="hero-pattern py-12 md:h-[240px] md:py-0 flex items-center px-gutter">
      <div class="rise-in max-w-4xl mx-auto w-full text-white">
        <h1 class="font-headline-xl text-headline-xl mb-2">ข้อมูลผู้ใช้</h1>
        <p class="text-body-lg font-body-lg opacity-80">ตรวจสอบข้อมูลนักศึกษาและข้อมูลการศึกษาของคุณ</p>
      </div>
    </section>

    <div class="max-w-4xl mx-auto px-gutter -mt-8 md:-mt-16 mb-12 relative z-10">
      <div class="rise-in bg-surface-white dark:bg-dm-surface rounded-xl shadow-card overflow-hidden border border-outline-variant/30 dark:border-dm-border">
        <div class="p-8">
          <div class="space-y-10">
            <div class="grid md:grid-cols-2 gap-8">
              <div class="space-y-2">
                <label for="profile-student-id" class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary flex items-center gap-2">
                  <span class="material-symbols-outlined text-[16px]">lock</span>
                  รหัสนักศึกษา
                </label>
                <input id="profile-student-id" class="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-label-code text-label-code" readonly type="text" value="…" />
              </div>
              <div class="space-y-2">
                <label for="profile-display-name" class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary flex items-center gap-2">
                  <span class="material-symbols-outlined text-[16px]">lock</span>
                  ชื่อ-สกุล
                </label>
                <input id="profile-display-name" class="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md" readonly type="text" value="…" />
              </div>
            </div>

            <hr class="border-outline-variant/30 dark:border-dm-border" />

            <div class="space-y-6">
              <h2 class="text-headline-md font-headline-md text-primary dark:text-primary-fixed-dim flex items-center gap-2">
                <span class="material-symbols-outlined">school</span>
                ข้อมูลการศึกษา
              </h2>
              <div class="grid md:grid-cols-3 gap-6">
                <div class="space-y-2">
                  <label for="profile-department" class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">แผนกวิชา</label>
                  <input id="profile-department" class="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md" readonly type="text" value="-" />
                </div>
                <div class="space-y-2">
                  <label for="profile-level" class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">ระดับชั้น</label>
                  <input id="profile-level" class="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md" readonly type="text" value="-" />
                </div>
                <div class="space-y-2">
                  <label for="profile-year-level" class="text-label-caps font-label-caps text-on-surface-variant dark:text-dm-text-secondary">ชั้นปี</label>
                  <input id="profile-year-level" class="w-full px-4 py-3 rounded-lg border border-outline-variant dark:border-dm-border bg-surface-container-low text-text-secondary dark:bg-dm-surface-alt dark:text-dm-text-secondary cursor-not-allowed font-body-md" readonly type="text" value="-" />
                </div>
              </div>
            </div>

            <hr class="border-outline-variant/30 dark:border-dm-border" />

            <!-- การเปลี่ยนรหัสผ่านย้ายไปหน้า /change-password แล้ว หน้านี้เหลือ
                 เฉพาะข้อมูลผู้ใช้ ที่นี่จึงเหลือแค่ทางเข้าไปหน้านั้น -->
            <a href="/change-password" class="profile-security-link flex items-center gap-4 rounded-xl border border-outline-variant dark:border-dm-border px-5 py-4 hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
              <span class="material-symbols-outlined text-primary dark:text-primary-fixed-dim text-3xl flex-shrink-0">key</span>
              <span class="min-w-0 flex-1">
                <span class="block font-bold text-on-surface dark:text-inverse-on-surface">เปลี่ยนรหัสผ่าน</span>
                <span class="block text-body-md text-on-surface-variant dark:text-dm-text-secondary">ตั้งรหัสผ่านใหม่สำหรับเข้าสู่ระบบของคุณ</span>
              </span>
              <span class="material-symbols-outlined text-on-surface-variant dark:text-dm-text-secondary flex-shrink-0">chevron_right</span>
            </a>
          </div>
        </div>
      </div>

      <div class="mt-8">
        <div class="bg-tertiary-fixed dark:bg-dm-surface p-6 rounded-xl border border-tertiary-container/10 dark:border-dm-border flex items-start gap-4">
          <span class="material-symbols-outlined text-tertiary-container dark:text-tertiary-fixed-dim text-4xl">info</span>
          <div>
            <h3 class="font-bold text-on-tertiary-fixed dark:text-inverse-on-surface mb-1">ข้อมูลที่แก้ไขไม่ได้</h3>
            <p class="text-on-tertiary-fixed-variant dark:text-dm-text-secondary text-body-md">
              รหัสนักศึกษา ชื่อ-สกุล แผนกวิชา ระดับชั้น และชั้นปี มาจากทะเบียนของวิทยาลัย ไม่สามารถแก้ไขได้ในหน้านี้
              หากข้อมูลไม่ถูกต้อง กรุณาติดต่อฝ่ายทะเบียน
            </p>
          </div>
        </div>
      </div>
    </div>
  </main>

  <footer class="bg-surface-container-highest dark:bg-dm-surface w-full py-8 border-t border-outline-variant dark:border-dm-border">
    <div class="flex flex-col md:flex-row justify-between items-center px-gutter w-full max-w-7xl mx-auto gap-4">
      <span class="text-label-caps font-label-caps font-bold text-primary dark:text-primary-fixed-dim">ห้องสมุด NTC</span>
    </div>
  </footer>

  <script src="/assets/js/api.js?v=<?= ntc_asset_v('assets/js/api.js') ?>"></script>
  <script src="/assets/js/theme.js?v=<?= ntc_asset_v('assets/js/theme.js') ?>"></script>
  <script src="/assets/js/toast.js?v=<?= ntc_asset_v('assets/js/toast.js') ?>"></script>
  <script src="/assets/js/header.js?v=<?= ntc_asset_v('assets/js/header.js') ?>"></script>
  <script src="/assets/js/confirm-modal.js?v=<?= ntc_asset_v('assets/js/confirm-modal.js') ?>"></script>
  <!-- renderPager(): the history modal pages server-side now, but the pager
       strip it draws is the same one the admin tables use. -->
  <script src="/assets/js/pagination.js?v=<?= ntc_asset_v('assets/js/pagination.js') ?>"></script>
  <script src="/assets/js/history-modal.js?v=<?= ntc_asset_v('assets/js/history-modal.js') ?>"></script>
  <script src="/assets/js/student-sidebar.js?v=<?= ntc_asset_v('assets/js/student-sidebar.js') ?>"></script>
  <script src="/assets/js/profile.js?v=<?= ntc_asset_v('assets/js/profile.js') ?>"></script>
</body>
</html>
