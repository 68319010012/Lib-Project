<?php
// Full check-in/check-out history modal — port of
// frontend-react/src/components/HistoryModal.jsx. Hidden by default; opened
// via openHistoryModal() (assets/js/history-modal.js) from the account menu.
?>
<div id="history-modal" class="hidden fixed inset-0 z-[95] bg-black/50 flex items-start sm:items-center justify-center px-gutter py-8">
  <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-lg w-full max-h-[80vh] flex flex-col">
    <div class="flex items-center justify-between p-6 border-b border-outline-variant dark:border-dm-border flex-shrink-0">
      <h3 class="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim">ประวัติการเข้าใช้</h3>
      <button
        type="button"
        id="history-modal-close"
        aria-label="ปิด"
        class="w-8 h-8 rounded-full flex items-center justify-center hover:bg-surface-container-low dark:hover:bg-dm-bg text-on-surface-variant dark:text-dm-text-secondary"
      >
        <span class="material-symbols-outlined">close</span>
      </button>
    </div>
    <div class="p-6 overflow-y-auto space-y-3" id="history-modal-body">
      <p class="text-body-md text-text-secondary dark:text-dm-text-secondary">กำลังโหลด…</p>
    </div>
    <div id="history-modal-pager" class="hidden flex-shrink-0 flex items-center justify-between px-6 py-3 border-t border-outline-variant dark:border-dm-border">
      <button type="button" id="history-modal-prev" class="flex items-center gap-1 text-sm font-bold text-primary dark:text-primary-fixed-dim disabled:opacity-40 disabled:cursor-not-allowed">
        <span class="material-symbols-outlined text-lg">chevron_left</span>
        ก่อนหน้า
      </button>
      <span id="history-modal-page-label" class="text-xs text-text-secondary dark:text-dm-text-secondary"></span>
      <button type="button" id="history-modal-next" class="flex items-center gap-1 text-sm font-bold text-primary dark:text-primary-fixed-dim disabled:opacity-40 disabled:cursor-not-allowed">
        ถัดไป
        <span class="material-symbols-outlined text-lg">chevron_right</span>
      </button>
    </div>
  </div>
</div>
