<?php
// Full check-in/check-out history — port of
// frontend-react/src/components/HistoryModal.jsx, since rebuilt around a date
// range. Hidden by default; opened via openHistoryModal()
// (assets/js/history-modal.js) from the account menu and the dashboard's
// "ดูทั้งหมด" button.
//
// A list with visible เข้า/ออก/รวม labels rather than a <table>: the rows are
// grouped under month headings, which a table can only express by breaking
// the header/row relationship, and every value here already carries its own
// label on screen, so a screen reader gets the same three facts per visit
// that the column heads used to supply.
?>
<div id="history-modal" class="ntc-modal hidden fixed inset-0 z-[95] bg-black/50 flex items-start sm:items-center justify-center px-gutter py-6 sm:py-8">
  <div
    role="dialog"
    aria-modal="true"
    aria-labelledby="history-modal-title"
    class="visit-panel bg-surface-white dark:bg-dm-surface rounded-2xl shadow-xl w-full max-w-3xl max-h-[88vh] flex flex-col overflow-hidden"
  >
    <div class="visit-panel-head">
      <div class="visit-panel-heading">
        <span class="visit-panel-icon material-symbols-outlined" aria-hidden="true">history</span>
        <h3 id="history-modal-title">ประวัติการเข้าใช้</h3>
      </div>
      <button
        type="button"
        id="history-modal-close"
        aria-label="ปิด"
        class="visit-panel-close"
      >
        <span class="material-symbols-outlined" aria-hidden="true">close</span>
      </button>
    </div>

    <div class="visit-filter">
      <label class="visit-select-label" for="history-range-preset">ช่วงเวลา</label>
      <select id="history-range-preset" class="visit-select">
        <option value="all">ทั้งหมด</option>
        <option value="7">7 วันล่าสุด</option>
        <option value="30">30 วันล่าสุด</option>
        <option value="month">เดือนนี้</option>
        <option value="custom">กำหนดเอง…</option>
      </select>

      <div class="visit-range hidden" id="history-custom-range">
        <div class="visit-field">
          <label for="history-from">จากวันที่</label>
          <input type="date" id="history-from" />
        </div>
        <span class="visit-range-dash" aria-hidden="true">–</span>
        <div class="visit-field">
          <label for="history-to">ถึงวันที่</label>
          <input type="date" id="history-to" />
        </div>
      </div>
    </div>

    <div class="visit-summary" id="history-summary">
      <div class="visit-stat">
        <span class="visit-stat-label">เข้าใช้</span>
        <span class="visit-stat-value" id="history-stat-visits">–</span>
      </div>
      <div class="visit-stat">
        <span class="visit-stat-label">เวลารวม</span>
        <span class="visit-stat-value" id="history-stat-total">–</span>
      </div>
      <div class="visit-stat">
        <span class="visit-stat-label">เฉลี่ยต่อครั้ง</span>
        <span class="visit-stat-value" id="history-stat-avg">–</span>
      </div>
    </div>

    <div class="visit-list" id="history-modal-body" aria-live="polite" aria-busy="false">
      <p class="visit-empty">กำลังโหลด…</p>
    </div>

    <div id="history-modal-pager" class="visit-panel-foot hidden">
      <div class="pager" id="history-pager"></div>
    </div>
  </div>
</div>
