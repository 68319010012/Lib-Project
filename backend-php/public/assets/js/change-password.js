// หน้าเปลี่ยนรหัสผ่าน (/change-password)
//
// ยิงไปที่ POST /profile/change-password ของเดิม ไม่ได้เพิ่ม endpoint ใหม่
// การตรวจรหัสผ่านปัจจุบันและการ hash ยังทำที่ฝั่งเซิร์ฟเวอร์ทั้งหมด — ที่ตรวจ
// ในไฟล์นี้เป็นการตรวจเบื้องต้นเพื่อบอกผู้ใช้เร็วขึ้นเท่านั้น ไม่ใช่ตัวกันจริง

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('change-password-form');
  if (!form) return;

  const currentEl = document.getElementById('cp-current');
  const newEl = document.getElementById('cp-new');
  const confirmEl = document.getElementById('cp-confirm');
  const submitBtn = document.getElementById('cp-submit');
  const alertEl = document.getElementById('cp-alert');
  const successEl = document.getElementById('cp-success');
  const ruleLength = document.getElementById('cp-rule-length');
  const ruleMatch = document.getElementById('cp-rule-match');

  // ความยาวขั้นต่ำมาจาก MIN_PASSWORD_LENGTH ฝั่ง PHP ผ่าน data-min-length
  // ไม่ได้เขียนเลขซ้ำไว้ในไฟล์นี้ เลขสองที่ที่ไม่ตรงกันจะทำให้ผู้ใช้เจอ
  // ข้อความว่า "ผ่านแล้ว" แต่เซิร์ฟเวอร์ปฏิเสธ
  const MIN_LENGTH = Number(form.dataset.minLength) || 8;

  function showAlert(message) {
    if (!message) {
      alertEl.hidden = true;
      alertEl.querySelector('.cp-alert-text').textContent = '';
      return;
    }
    successEl.hidden = true;
    alertEl.querySelector('.cp-alert-text').textContent = message;
    alertEl.hidden = false;
  }

  function setFieldError(input, message) {
    const box = document.getElementById(`${input.id}-error`);
    const field = input.closest('.cp-field');
    if (message) {
      input.setAttribute('aria-invalid', 'true');
      if (field) field.classList.add('is-invalid');
      if (box) {
        box.textContent = message;
        box.hidden = false;
      }
    } else {
      input.removeAttribute('aria-invalid');
      if (field) field.classList.remove('is-invalid');
      if (box) {
        box.textContent = '';
        box.hidden = true;
      }
    }
  }

  // รายการข้อกำหนดติ๊กถูกตามที่พิมพ์ ผู้ใช้จึงรู้ว่าผ่านหรือยังก่อนกดปุ่ม
  function paintRules() {
    const okLength = newEl.value.length >= MIN_LENGTH;
    const okMatch = newEl.value !== '' && newEl.value === confirmEl.value;
    [[ruleLength, okLength], [ruleMatch, okMatch]].forEach(([li, ok]) => {
      if (!li) return;
      li.classList.toggle('ok', ok);
      const icon = li.querySelector('.material-symbols-outlined');
      if (icon) icon.textContent = ok ? 'check_circle' : 'radio_button_unchecked';
    });
  }

  [currentEl, newEl, confirmEl].forEach((el) => {
    el.addEventListener('input', () => {
      setFieldError(el, '');
      paintRules();
    });
  });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    showAlert('');
    successEl.hidden = true;

    const currentPassword = currentEl.value;
    const newPassword = newEl.value;
    const confirmPassword = confirmEl.value;

    // ตรวจตามลำดับที่ผู้ใช้กรอก แล้วโฟกัสช่องแรกที่ผิด
    let firstBad = null;
    const fail = (el, msg) => {
      setFieldError(el, msg);
      if (!firstBad) firstBad = el;
    };

    setFieldError(currentEl, '');
    setFieldError(newEl, '');
    setFieldError(confirmEl, '');

    if (!currentPassword) fail(currentEl, 'กรุณากรอกรหัสผ่านปัจจุบัน');
    if (!newPassword) {
      fail(newEl, 'กรุณากรอกรหัสผ่านใหม่');
    } else if (newPassword.length < MIN_LENGTH) {
      fail(newEl, `รหัสผ่านใหม่ต้องมีอย่างน้อย ${MIN_LENGTH} ตัวอักษร`);
    }
    if (!confirmPassword) {
      fail(confirmEl, 'กรุณากรอกยืนยันรหัสผ่านใหม่');
    } else if (newPassword && newPassword !== confirmPassword) {
      fail(confirmEl, 'รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน');
    }

    if (firstBad) {
      firstBad.focus();
      return;
    }

    submitBtn.disabled = true;
    submitBtn.classList.add('is-loading');
    submitBtn.setAttribute('aria-busy', 'true');
    try {
      await apiPostJson('/profile/change-password', {
        current_password: currentPassword,
        new_password: newPassword,
      });
      form.reset();
      paintRules();
      successEl.hidden = false;
      if (typeof showToast === 'function') {
        showToast('เปลี่ยนรหัสผ่านสำเร็จ', { type: 'success' });
      }
      // เลื่อนให้เห็นข้อความสำเร็จ เผื่อผู้ใช้กดปุ่มตอนที่หน้าถูกเลื่อนลงไปแล้ว
      successEl.scrollIntoView({ block: 'center', behavior: 'smooth' });
    } catch (err) {
      // เซิร์ฟเวอร์ตอบ 401 เมื่อรหัสผ่านปัจจุบันไม่ถูกต้อง วางข้อความไว้ที่ช่อง
      // นั้นโดยตรงจะช่วยได้มากกว่าขึ้นเป็นแถบรวมด้านบน
      if (err.status === 401) {
        setFieldError(currentEl, err.message || 'รหัสผ่านปัจจุบันไม่ถูกต้อง');
        currentEl.focus();
      } else {
        showAlert(err.message);
      }
    } finally {
      submitBtn.disabled = false;
      submitBtn.classList.remove('is-loading');
      submitBtn.removeAttribute('aria-busy');
    }
  });

  paintRules();
});
