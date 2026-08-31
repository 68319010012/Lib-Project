<!-- Shared confirm dialog — replaces window.confirm() (unstyled, shows the
     raw hostname, can't be themed) with something that matches the rest of
     the app. Included once in header.php so every authenticated page has it;
     see assets/js/confirm-modal.js for the showConfirmModal() promise API.
     Centered icon + title + optional subject + message, matching the
     reset-result modal's layout so confirmations read as one consistent
     dialog rather than a wall of left-aligned text. -->
<!-- z-[200], deliberately above the z-[100] every page-level modal uses. This
     dialog is included from header.php, i.e. near the TOP of the document,
     while the modals that open it (#member-edit-modal, #reset-result-modal)
     live further down the page. At equal z-index the browser falls back to DOM
     order and the later element wins, so a confirm raised from inside the edit
     modal rendered BEHIND it — "ตั้งเป็นแอดมิน" looked like it did nothing.
     200 rather than a tidier 110/120 because styles.css is PRE-BUILT Tailwind
     with no compile step at deploy time: the only z utilities that exist in it
     are 60/80/90/95/100/200, and a class that isn't in the file silently
     resolves to no z-index at all — which would have made this worse, not
     better. Ties with the toast container (also 200), and that is correct:
     toast.js appends it to <body> last, so toasts stay above this dialog. -->
<div id="confirm-modal" class="ntc-modal hidden fixed inset-0 z-[200] bg-black/50 flex items-center justify-center px-gutter">
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
