// ทำซ้ำบัค "กดลบบัญชีแล้วกล่องยืนยันไปอยู่หลังโมดัลแก้ไข" ที่ผู้ใช้เจอบนเว็บจริง
// ใช้จอมือถือเพราะผู้ใช้ส่งภาพหน้าจอแคบมา
const { chromium } = require('playwright');

(async () => {
  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const page = await ctx.newPage();

  await page.goto('http://localhost:8000/login', { waitUntil: 'load' });
  await page.fill('input[placeholder*="รหัสนักศึกษา"]', 'demoadmin');
  await page.fill('input[type="password"]', 'DemoAdmin@2569');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin-dashboard', { timeout: 10000 });

  await page.goto('http://localhost:8000/admin-members', { waitUntil: 'load' });
  await page.waitForSelector('#members-tbody tr, .member-card', { timeout: 10000 });

  await page.locator('button:has-text("แก้ไข")').first().click();
  await page.waitForSelector('#member-edit-modal:not(.hidden)', { timeout: 5000 });

  await page.click('#member-edit-delete-btn');
  await page.waitForTimeout(600);

  const info = await page.evaluate(() => {
    const c = document.getElementById('confirm-modal');
    const m = document.getElementById('member-edit-modal');
    const cs = getComputedStyle(c);
    const ms = getComputedStyle(m);
    const r = c.getBoundingClientRect();
    // ใครถูกวาดอยู่บนสุดตรงกลางกล่องยืนยัน — นี่คือคำตอบว่าใครทับใคร
    const topEl = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2);
    const okBtn = document.getElementById('confirm-modal-ok');
    const br = okBtn.getBoundingClientRect();
    const topAtOk = document.elementFromPoint(br.left + br.width / 2, br.top + br.height / 2);
    return {
      confirmHidden: c.classList.contains('hidden'),
      confirmZ: cs.zIndex,
      confirmPos: cs.position,
      editHidden: m.classList.contains('hidden'),
      editZ: ms.zIndex,
      editPos: ms.position,
      topElementAtConfirmCenter: topEl ? (topEl.id || topEl.className || topEl.tagName) : null,
      topElementAtOkButton: topAtOk ? (topAtOk.id || topAtOk.className || topAtOk.tagName) : null,
      okButtonClickable: topAtOk === okBtn || okBtn.contains(topAtOk),
      confirmParent: c.parentElement
        ? `${c.parentElement.tagName}#${c.parentElement.id}.${c.parentElement.className}`
        : null,
    };
  });

  console.log(JSON.stringify(info, null, 2));
  await page.screenshot({ path: require('path').join(__dirname, 'repro_confirm.png') });
  await browser.close();
})();
