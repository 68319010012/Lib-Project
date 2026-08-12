<?php
// Shared left nav across the 3 admin pages — port of
// frontend-react/src/components/AdminSidebar.jsx. Include with $active set
// to the current page's filename (e.g. 'admin-dashboard.php') beforehand.
$active = $active ?? '';
$navItems = [
    ['href' => '/admin-dashboard.php', 'icon' => 'monitoring', 'label' => 'ภาพรวม'],
    ['href' => '/admin-members.php', 'icon' => 'group', 'label' => 'สมาชิก'],
    ['href' => '/admin-active.php', 'icon' => 'sensors', 'label' => 'กำลังใช้งานอยู่'],
    ['href' => '/admin-logs.php', 'icon' => 'history', 'label' => 'ประวัติการเข้าใช้'],
];
function admin_nav_class(string $href, string $active): string
{
    return $href === '/' . $active
        ? 'flex items-center gap-3 bg-primary-container text-on-primary-container font-bold rounded-lg px-4 py-2'
        : 'flex items-center gap-3 text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg rounded-lg px-4 py-2 transition-colors';
}
?>
<!-- Desktop sidebar — sits below the fixed AppHeader (top-16). -->
<aside class="hidden md:flex flex-col h-[calc(100vh-4rem)] w-64 fixed left-0 top-16 bg-surface-white dark:bg-dm-surface border-r border-outline-variant dark:border-dm-border shadow-sm py-6 px-4 z-40">
  <p class="px-2 mb-4 text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลเจ้าหน้าที่</p>
  <nav class="flex-1 space-y-2">
    <?php foreach ($navItems as $item): ?>
      <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= admin_nav_class($item['href'], $active) ?>">
        <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
        <span class="font-body-md"><?= $item['label'] ?></span>
      </a>
    <?php endforeach; ?>
  </nav>
</aside>

<!-- Mobile: slim hamburger strip right under AppHeader, opens the same nav as a drawer. -->
<div class="md:hidden fixed top-16 left-0 right-0 z-40 h-12 bg-surface-white dark:bg-dm-surface border-b border-outline-variant dark:border-dm-border shadow-sm flex items-center justify-between px-4">
  <p class="text-xs uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลเจ้าหน้าที่</p>
  <button type="button" id="admin-mobile-menu-btn" aria-label="เปิดเมนู" class="w-9 h-9 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg">
    <span class="material-symbols-outlined">menu</span>
  </button>
</div>

<div id="admin-mobile-drawer" class="hidden md:hidden fixed inset-0 z-[60] bg-black/50">
  <div class="absolute top-0 left-0 h-screen w-72 max-w-[80vw] bg-surface-white dark:bg-dm-surface shadow-lg py-6 px-4 flex flex-col">
    <div class="flex items-center justify-between mb-10 px-2">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 bg-primary flex items-center justify-center rounded-lg">
          <span class="material-symbols-outlined text-on-primary">local_library</span>
        </div>
        <div>
          <h1 class="font-bold text-primary dark:text-primary-fixed-dim leading-tight">NTC Library</h1>
          <p class="text-[10px] uppercase tracking-widest text-outline dark:text-dm-text-secondary font-bold">พอร์ทัลเจ้าหน้าที่</p>
        </div>
      </div>
      <button type="button" id="admin-mobile-menu-close" aria-label="ปิดเมนู" class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant dark:text-dm-text-secondary hover:bg-surface-container-high dark:hover:bg-dm-bg">
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <nav class="flex-1 space-y-2">
      <?php foreach ($navItems as $item): ?>
        <a href="<?= htmlspecialchars($item['href']) ?>" class="<?= admin_nav_class($item['href'], $active) ?>">
          <span class="material-symbols-outlined"><?= $item['icon'] ?></span>
          <span class="font-body-md"><?= $item['label'] ?></span>
        </a>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
