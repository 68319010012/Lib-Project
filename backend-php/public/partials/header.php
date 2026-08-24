<?php
// Shared top navbar for every authenticated page — port of
// frontend-react/src/components/AppHeader.jsx. Include with $variant set to
// 'student' or 'admin' beforehand (defaults to 'student').
$variant = $variant ?? 'student';
$homeHref = $variant === 'admin' ? '/admin-dashboard' : '/dashboard';
$profileHref = $variant === 'admin' ? '/admin-members' : '/profile';

// ปุ่มพับแถบเมนูซ้ายบนจอคอม สองฝั่งใช้เบรกพอยต์คนละค่าเพราะแถบเมนูของแต่ละฝั่ง
// โผล่คนละความกว้าง: ของเจ้าหน้าที่ที่ 768px ของนักศึกษาที่ 1024px ปุ่มต้อง
// ปรากฏพร้อมแถบที่มันสั่ง ไม่งั้นจะมีปุ่มที่กดแล้วไม่มีอะไรเกิดขึ้น
$sidebarBreakpoint = $variant === 'admin' ? 'md' : 'lg';
$sidebarStateKey = $variant === 'admin' ? 'ntc-admin-sidebar-collapsed' : 'ntc-student-sidebar-collapsed';
?>
<header class="bg-primary shadow-md fixed top-0 z-50 w-full">
  <div class="flex justify-between items-center w-full <?= $variant === 'admin' ? 'px-4 sm:px-5' : 'px-gutter' ?> h-16 text-on-primary gap-2">
    <div class="flex items-center gap-2 min-w-0">
      <button
        type="button"
        id="sidebar-toggle-btn"
        data-collapse-key="<?= htmlspecialchars($sidebarStateKey) ?>"
        aria-label="ย่อ/ขยายเมนูด้านซ้าย"
        title="ย่อ/ขยายเมนูด้านซ้าย"
        aria-expanded="true"
        class="hidden <?= $sidebarBreakpoint ?>:flex w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 items-center justify-center text-on-primary transition-colors flex-shrink-0"
      >
        <span class="material-symbols-outlined text-xl">menu_open</span>
      </button>
      <a href="<?= htmlspecialchars($homeHref) ?>" class="flex items-center gap-2 min-w-0 hover:opacity-90 transition-opacity">
        <span class="material-symbols-outlined text-2xl flex-shrink-0">local_library</span>
        <span class="text-headline-md font-headline-md font-bold whitespace-nowrap">NTC Library</span>
      </a>
    </div>

    <div class="flex items-center gap-2 sm:gap-3 flex-shrink-0">
      <button
        type="button"
        id="theme-toggle-btn"
        aria-label="สลับธีมสว่าง/มืด"
        title="สลับธีมสว่าง/มืด"
        class="w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 flex items-center justify-center text-on-primary transition-colors flex-shrink-0"
      >
        <span class="material-symbols-outlined text-xl tm-current-icon">light_mode</span>
      </button>

      <div class="relative" id="account-menu">
        <button
          type="button"
          id="account-menu-btn"
          aria-haspopup="true"
          aria-label="เมนูบัญชี"
          class="flex items-center gap-1.5 rounded-full hover:bg-white/10 pl-1 pr-2 py-1 transition-colors"
        >
          <span class="material-symbols-outlined text-[34px] leading-none">account_circle</span>
          <span class="hidden md:block text-left leading-tight max-w-[140px]">
            <span class="block text-xs font-bold truncate account-display-name">...</span>
          </span>
          <span class="material-symbols-outlined text-lg account-menu-caret">expand_more</span>
        </button>

        <div id="account-menu-dropdown" class="hidden absolute right-0 mt-2 w-60 bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl border border-outline-variant dark:border-dm-border overflow-hidden text-text-primary dark:text-inverse-on-surface z-50">
          <div class="px-4 py-3 border-b border-outline-variant dark:border-dm-border">
            <p class="font-bold text-sm truncate account-display-name">...</p>
            <p class="text-xs text-text-secondary dark:text-dm-text-secondary truncate" id="account-student-id">รหัส: ...</p>
          </div>

          <a href="<?= htmlspecialchars($profileHref) ?>" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
            <span class="material-symbols-outlined text-lg">person</span>
            <?= $variant === 'admin' ? 'ข้อมูลสมาชิก' : 'ข้อมูลผู้ใช้' ?>
          </a>

          <!-- การเปลี่ยนรหัสผ่านเป็นหน้าของตัวเอง ไม่ได้อยู่ในหน้าโปรไฟล์แล้ว
               จึงต้องมีทางเข้าที่นี่ ไม่งั้นผู้ใช้จะหาไม่เจอ และแอดมินก็ต้องเห็น
               ด้วยเพราะ /profile เป็นหน้าของนักศึกษาเท่านั้น -->
          <a href="/change-password" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
            <span class="material-symbols-outlined text-lg">key</span>
            เปลี่ยนรหัสผ่าน
          </a>

          <?php if ($variant === 'student'): ?>
          <button type="button" id="account-menu-history" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">
            <span class="material-symbols-outlined text-lg">history</span>
            ประวัติการเข้าใช้
          </button>
          <?php endif; ?>

          <button type="button" id="account-menu-logout" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-error hover:bg-error-container/30 transition-colors border-t border-outline-variant dark:border-dm-border">
            <span class="material-symbols-outlined text-lg">logout</span>
            ออกจากระบบ
          </button>
        </div>
      </div>
    </div>
  </div>
</header>

    <?php include __DIR__ . '/confirm-modal.php'; ?>

<?php if ($variant === 'student'): ?>
<?php include __DIR__ . '/history-modal.php'; ?>
<?php endif; ?>
