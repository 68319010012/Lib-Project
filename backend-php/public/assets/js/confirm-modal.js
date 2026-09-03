// Promise-based replacement for window.confirm() — see partials/confirm-modal.php.
//
// Usage:
//   const ok = await showConfirmModal('message', {
//     title, subject, confirmLabel, danger, icon,
//   });
//
// `subject` is the thing being acted on (e.g. a student's name) — shown as
// its own emphasized line so the title stays a short, clean question instead
// of a long sentence with the name buried mid-string. `icon` overrides the
// default (danger -> warning triangle, otherwise a question mark).

let confirmModalResolve = null;

function showConfirmModal(message, { title = 'ยืนยันการทำรายการ', subject = '', confirmLabel = 'ยืนยัน', danger = false, icon } = {}) {
  return new Promise((resolve) => {
    confirmModalResolve = resolve;

    document.getElementById('confirm-modal-title').textContent = title;
    document.getElementById('confirm-modal-message').textContent = message;

    const subjectEl = document.getElementById('confirm-modal-subject');
    subjectEl.textContent = subject;
    subjectEl.classList.toggle('hidden', !subject);

    // Icon + its circular background pick up the danger/normal color pair.
    const iconEl = document.getElementById('confirm-modal-icon');
    iconEl.textContent = icon || (danger ? 'warning' : 'help');
    // Danger uses the app's error red (matching the red confirm button);
    // non-danger uses the primary theme color — never the off-theme amber.
    const iconWrap = document.getElementById('confirm-modal-icon-wrap');
    iconWrap.className = `w-12 h-12 rounded-full flex items-center justify-center mb-4 ${
      danger ? 'bg-error/10 text-error' : 'bg-primary/10 text-primary dark:text-primary-fixed-dim'
    }`;

    const okBtn = document.getElementById('confirm-modal-ok');
    okBtn.textContent = confirmLabel;
    okBtn.classList.toggle('bg-error', danger);
    okBtn.classList.toggle('bg-primary', !danger);

    document.getElementById('confirm-modal').classList.remove('hidden');
  });
}

function resolveConfirmModal(result) {
  document.getElementById('confirm-modal').classList.add('hidden');
  const resolve = confirmModalResolve;
  confirmModalResolve = null;
  if (resolve) resolve(result);
}

// ยกกล่องยืนยันขึ้นบนสุดจากฝั่ง JS ไม่ใช่จาก class ใน partials/confirm-modal.php
//
// กล่องนี้ถูก include จาก header.php ซึ่งอยู่ "ต้น" เอกสาร ส่วนโมดัลที่เรียกมัน
// (#member-edit-modal, #reset-result-modal) อยู่ท้ายเอกสาร พอ z-index เท่ากัน
// เบราว์เซอร์ตัดสินด้วยลำดับใน DOM ตัวที่อยู่ท้ายกว่าจึงทับ — กล่องยืนยันไปอยู่
// ข้างหลัง ต้องปิดกล่องแก้ไขก่อนถึงจะเห็น
//
// ของเดิมแก้ด้วยการใส่ z-[200] ใน partials/confirm-modal.php ซึ่งถูกต้องแต่
// "เปราะ": ไฟล์ .php เป็นไฟล์ที่ deploy อาจตกหล่นได้ (workflow อัปโหลดเฉพาะไฟล์
// ที่เปลี่ยนนับจาก since_ref ที่กรอก) และมันตกหล่นมาแล้วจริง ๆ — JS/CSS ขึ้นครบ
// แต่ partial ตัวนี้ยังเป็นของเก่าบนเว็บจริง บัคจึงกลับมาทั้งที่โค้ดในรีโปถูกแล้ว
//
// สองบรรทัดนี้ทำให้ผลลัพธ์ไม่ขึ้นกับว่า partial เวอร์ชันไหนถูกอัปโหลด:
//   1) ย้ายมาเป็นลูกตัวสุดท้ายของ <body> — ชนะเรื่องลำดับ DOM เสมอ
//   2) ตั้ง zIndex เป็น inline style — ชนะทุก class ที่ติดมากับ markup เก่า
// ทำครั้งเดียวตอนโหลดหน้า ไม่ใช่ทุกครั้งที่เปิดกล่อง เพราะการ appendChild ซ้ำจะ
// ถอด-ใส่ element ใหม่ ซึ่งจะรีเซ็ต transition ที่กำลังเล่นอยู่
//
// 200 เท่ากับ toast container โดยตั้งใจ: toast.js ต่อท้าย <body> ทีหลังกว่านี้
// (ตอนมี toast ตัวแรก) toast จึงยังลอยอยู่เหนือกล่องยืนยันตามเดิม
function hoistConfirmModal(modal) {
  if (modal.parentElement !== document.body || modal.nextElementSibling) {
    document.body.appendChild(modal);
  }
  modal.style.zIndex = '200';
}

document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('confirm-modal');
  if (!modal) return;
  hoistConfirmModal(modal);
  document.getElementById('confirm-modal-ok').addEventListener('click', () => resolveConfirmModal(true));
  document.getElementById('confirm-modal-cancel').addEventListener('click', () => resolveConfirmModal(false));
  modal.addEventListener('click', (e) => {
    if (e.target === modal) resolveConfirmModal(false);
  });
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && !modal.classList.contains('hidden')) resolveConfirmModal(false);
  });
});
