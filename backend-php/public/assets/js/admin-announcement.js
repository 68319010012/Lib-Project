// ช่องพิมพ์ประกาศของเจ้าหน้าที่ (หน้าภาพรวมแอดมิน)
//
// ประกาศเก็บเป็นข้อความเดียวที่แก้ทับได้ ไม่ใช่รายการหลายฉบับ — หน้านักศึกษา
// แสดงแค่อันเดียวอยู่แล้ว ดูฝั่งเซิร์ฟเวอร์ที่ src/handlers/announcement_handlers.php

const ANN_MAX = 500;

function annSetError(message) {
  const el = document.getElementById('announcement-error');
  el.textContent = message || '';
  el.classList.toggle('hidden', !message);
}

function annUpdateCount() {
  const input = document.getElementById('announcement-input');
  // นับเป็นตัวอักษรแบบเดียวกับ mb_strlen ฝั่ง PHP — [...str] แยกตาม code point
  // ไม่ใช่ .length ที่นับ UTF-16 unit (อีโมจิหนึ่งตัวจะถูกนับเป็นสอง)
  const used = [...input.value].length;
  document.getElementById('announcement-count').textContent = `${used} / ${ANN_MAX}`;
}

function annShowMeta(data) {
  const meta = document.getElementById('announcement-meta');
  if (!data || !data.updated_at) {
    meta.textContent = 'ยังไม่เคยตั้งประกาศ';
    return;
  }
  const when = new Date(data.updated_at).toLocaleString('th-TH', {
    day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
  });
  const who = data.updated_by ? ` โดย ${data.updated_by}` : '';
  meta.textContent = `แก้ไขล่าสุด ${when} น.${who}`;
}

function annApply(data) {
  document.getElementById('announcement-input').value = (data && data.text) || '';
  document.getElementById('announcement-enabled').checked = !!(data && data.enabled);
  annUpdateCount();
  annShowMeta(data);
}

async function annSave(e) {
  e.preventDefault();
  const btn = document.getElementById('announcement-save');
  const text = document.getElementById('announcement-input').value;
  const enabled = document.getElementById('announcement-enabled').checked;

  annSetError('');
  btn.disabled = true;
  try {
    const data = await apiPostJson('/admin/announcement', { text, enabled });
    annApply(data);
    showToast(data.message, { type: 'success' });
  } catch (err) {
    // แสดงทั้งใต้ฟอร์มและเป็น toast: ข้อความอาจถูกพิมพ์อยู่นอกจอเมื่อเลื่อนลง
    annSetError(err.message);
    showToast(err.message, { type: 'error' });
  } finally {
    btn.disabled = false;
  }
}

document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('announcement-form');
  if (!form) return;

  document.getElementById('announcement-input').addEventListener('input', () => {
    annUpdateCount();
    annSetError('');
  });
  form.addEventListener('submit', annSave);

  apiFetch('/announcement')
    .then(annApply)
    .catch(() => { annShowMeta(null); });
});
