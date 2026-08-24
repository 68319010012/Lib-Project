<!-- Shared confirm dialog — replaces window.confirm() (unstyled, shows the
     raw hostname, can't be themed) with something that matches the rest of
     the app. Included once in header.php so every authenticated page has it;
     see assets/js/confirm-modal.js for the showConfirmModal() promise API.
     Centered icon + title + optional subject + message, matching the
     reset-result modal's layout so confirmations read as one consistent
     dialog rather than a wall of left-aligned text. -->
<div id="confirm-modal" class="ntc-modal hidden fixed inset-0 z-[100] bg-black/50 flex items-center justify-center px-gutter">
  <div class="bg-surface-white dark:bg-dm-surface rounded-xl shadow-xl max-w-sm w-full p-6">
    <div class="flex flex-col items-center text-center">
      <div id="confirm-modal-icon-wrap" class="w-12 h-12 rounded-full flex items-center justify-center mb-4">
        <span id="confirm-modal-icon" class="material-symbols-outlined text-3xl">help</span>
      </div>
      <h3 id="confirm-modal-title" class="font-headline-md text-headline-md text-primary dark:text-primary-fixed-dim mb-2"></h3>
      <p id="confirm-modal-subject" class="hidden font-bold text-body-lg text-text-primary dark:text-inverse-on-surface mb-2"></p>
      <p id="confirm-modal-message" class="text-body-md text-text-secondary dark:text-dm-text-secondary mb-6"></p>
    </div>
    <div class="flex gap-3">
      <button type="button" id="confirm-modal-cancel" class="flex-1 h-11 rounded-full border border-outline-variant dark:border-dm-border text-text-primary dark:text-inverse-on-surface font-bold text-sm hover:bg-surface-container-low dark:hover:bg-dm-bg transition-colors">ยกเลิก</button>
      <button type="button" id="confirm-modal-ok" class="flex-1 h-11 rounded-full bg-primary text-white font-bold text-sm hover:brightness-95 transition-all">ยืนยัน</button>
    </div>
  </div>
</div>
