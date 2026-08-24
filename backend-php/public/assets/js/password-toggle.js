// ปุ่มดวงตาข้างช่องรหัสผ่าน — สลับ type ระหว่าง password กับ text
//
// เดิมโค้ดนี้อยู่ใน login.js ช่องเดียว พอต้องมีปุ่มแบบเดียวกันในหน้าสมัคร
// สมาชิก (2 ช่อง) และหน้าเปลี่ยนรหัสผ่าน (3 ช่อง) การคัดลอกไปวางหมายถึง
// ต้องแก้พฤติกรรมเดียวกันหลายที่ ย้ายมาไว้ไฟล์เดียวที่ทุกหน้าเรียกใช้
//
// วิธีใช้: วางปุ่มไว้ในกล่องเดียวกับช่องพิมพ์ แล้วชี้ไปที่ช่องด้วย aria-controls
//   <button type="button" class="pw-toggle" aria-controls="รหัสของช่อง"
//           aria-pressed="false" aria-label="แสดงรหัสผ่าน">
//     <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
//   </button>
//
// type="button" สำคัญมาก: ปุ่มใน <form> ที่ไม่ระบุ type จะเป็น submit ตาม
// ค่าเริ่มต้นของ HTML กดดูรหัสผ่านแล้วฟอร์มจะถูกส่งทันที

function initPasswordToggle(btn) {
  if (!btn || btn.dataset.pwToggleReady === '1') return;
  const input = document.getElementById(btn.getAttribute('aria-controls') || '');
  if (!input) return;
  btn.dataset.pwToggleReady = '1';
  if (!btn.getAttribute('type')) btn.setAttribute('type', 'button');

  const icon = btn.querySelector('.material-symbols-outlined');

  btn.addEventListener('click', () => {
    // อ่านสถานะจาก type ของช่องจริง ไม่ได้จำไว้ในตัวแปร ค่าที่แสดงกับค่าจริง
    // จึงตรงกันเสมอแม้มีโค้ดอื่นไปเปลี่ยน type
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    btn.setAttribute('aria-pressed', show ? 'true' : 'false');
    const label = show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน';
    btn.setAttribute('aria-label', label);
    btn.title = label;
    if (icon) icon.textContent = show ? 'visibility_off' : 'visibility';

    // คืนโฟกัสให้ช่องพิมพ์ พร้อมวางเคอร์เซอร์ไว้ท้ายข้อความเดิม เพื่อให้
    // กดดูแล้วพิมพ์ต่อได้ทันทีโดยไม่ต้องแตะช่องซ้ำ
    const end = input.value.length;
    input.focus();
    try {
      input.setSelectionRange(end, end);
    } catch (_) {
      /* setSelectionRange ใช้ได้เฉพาะบางชนิดของ input */
    }
  });
}

function initPasswordToggles(root) {
  (root || document).querySelectorAll('button.pw-toggle[aria-controls]').forEach(initPasswordToggle);
}

document.addEventListener('DOMContentLoaded', () => initPasswordToggles());
