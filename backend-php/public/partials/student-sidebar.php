<?php
// Left nav for student pages with a sidebar (currently just profile.php) —
// mirrors admin-sidebar.php's mobile hamburger-drawer pattern so students
// have the same way to get back to the dashboard on small screens that
// admins already have (previously this sidebar was desktop-only, leaving
// mobile with no visible nav besides the header logo).
$studentActive = $studentActive ?? '';
$studentNavItems = [
    ['href' => '/dashboard', 'icon' => 'dashboard', 'label' => 'หน้าหลัก'],
    ['href' => '/profile', 'icon' => 'settings', 'label' => 'โปรไฟล์'],
    // หน้าเปลี่ยนรหัสผ่านใช้แถบนี้ด้วย ถ้าไม่มีรายการของตัวเองจะไม่มีอะไร
    // ถูกไฮไลต์เลยตอนอยู่หน้านั้น ซึ่งอ่านเหมือนแถบเมนูเสีย
    ['href' => '/change-password', 'icon' => 'key', 'label' => 'เปลี่ยนรหัสผ่าน'],
];
function student_nav_class(string $href, string $active): string
{
    return $href === '/' . $active
        ? 'bg-primary-container text-on-primary-container font-bold rounded-lg px-4 py-2 flex items-center gap-3'
        : 'text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 flex items-center gap-3 transition-colors';
}
?>
<!-- ใส่สถานะ "พับอยู่" ก่อนหน้าจะถูกวาด แถบเมนูจึงไม่วูบเข้าออกให้เห็นตอนโหลด
     (วิธีเดียวกับ partials/admin-sidebar.php) -->
<script>
  (function () {
    try {
      if (localStorage.getItem('ntc-student-sidebar-collapsed') === '1') {
        document.documentElement.classList.add('sidebar-collapsed');
      }
    } catch (e) {}
  })();
</script>

<!-- Desktop sidebar -->
<aside class="student-sidebar-desktop hidden lg:flex flex-col h-screen w-64 fixed left-0 top-0 bg-surface-white dark:bg-dm-surface border-r border-outline-variant dark:border-dm-border pt-20 pb-6 px-4 z-40">
  <div class="mb-8 px-4">
    <div class="flex items-center gap-3 mb-2">
      <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center">
        <span class="material-symbols-outlined text-on-primary">local_library</span>
      </div>
      <div>
        <h2 class="text-primary dark:text-primary-fixed-dim text-headline-md font-bold leading-tight">NTC Library</h2>
        <p class="text-on-surface-variant dark:text-dm-text-secondary text-label-caps font-label-caps uppercase tracking-wider">พอร์ทัลนักศึกษา</p>
      </div>
    </div>
  </div>
  <nav class="flex-1 space-y-2">
    <?php foreach ($studentNavItems as $item): ?>
      <a class="<?= student_nav_class($item['href'], $studentActive) ?>" href="<?= htmlspecialchars($item['href']) ?>">
        <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
        <span class="font-body-md"><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>

<!-- Mobile: slim hamburger strip right under AppHeader, opens the same nav as a drawer. -->
<div class="lg:hidden fixed top-16 left-0 right-0 z-40 h-12 bg-surface-white dark:bg-dm-surface border-b border-outline-variant dark:border-dm-border shadow-sm flex items-center gap-2 px-4">
  <button type="button" id="student-mobile-menu-btn" aria-label="เปิดเมนู" class="w-9 h-9 -ml-2 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg">
    <span class="material-symbols-outlined">menu</span>
  </button>
  <p class="text-xs uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลนักศึกษา</p>
</div>

<div id="student-mobile-drawer" class="hidden lg:hidden fixed inset-0 z-[60] bg-black/50">
  <div class="absolute top-0 left-0 h-screen w-72 max-w-[80vw] bg-surface-white dark:bg-dm-surface shadow-lg py-6 px-4 flex flex-col">
    <div class="flex items-center justify-between mb-10 px-2">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary flex items-center justify-center rounded-lg">
          <span class="material-symbols-outlined text-on-primary">local_library</span>
        </div>
        <div>
          <h1 class="font-bold text-primary dark:text-primary-fixed-dim leading-tight">NTC Library</h1>
          <p class="text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลนักศึกษา</p>
        </div>
      </div>
      <button type="button" id="student-mobile-menu-close" aria-label="ปิดเมนู" class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <nav class="flex-1 space-y-2">
      <?php foreach ($studentNavItems as $item): ?>
        <a class="<?= student_nav_class($item['href'], $studentActive) ?>" href="<?= htmlspecialchars($item['href']) ?>">
          <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
          <span class="font-body-md"><?= $item['label'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
