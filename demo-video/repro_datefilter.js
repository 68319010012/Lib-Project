// พิสูจน์บัค "เลือกวันที่แล้วข้อมูลหายไปเอง" ที่ผู้ใช้เจอบนเว็บจริง
// ต้นเหตุ: setInterval ยึด URLSearchParams ตัวแรก (month=เดือนนี้) ไว้ในโคลเชอร์
// พอครบ 20 วินาทีมันจะดึงข้อมูล "เดือนนี้" มาทับผลที่กรองตามวันไว้
// เทสต์นี้จึงต้องรอให้ poll ทำงานอย่างน้อยหนึ่งรอบจริงๆ
const { chromium } = require('playwright');

const TARGET_DATE = process.argv[2] || '2026-08-07';

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

  await page.goto('http://localhost:8000/login', { waitUntil: 'load' });
  await page.fill('input[placeholder*="รหัสนักศึกษา"]', 'demoadmin');
  await page.fill('input[type="password"]', 'DemoAdmin@2569');
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin-dashboard', { timeout: 10000 });

  await page.goto('http://localhost:8000/admin-logs', { waitUntil: 'load' });
  await page.waitForSelector('#logs-tbody', { timeout: 10000 });
  await page.waitForTimeout(1500);

  const countText = () => page.textContent('#logs-count');

  await page.fill('#logs-date-filter', TARGET_DATE);
  await page.dispatchEvent('#logs-date-filter', 'change');
  await page.waitForTimeout(2000);
  const afterPick = await countText();

  // รอให้ poll ยิงอย่างน้อยหนึ่งรอบ (ตั้งไว้ 20s)
  await page.waitForTimeout(22000);
  const afterPoll = await countText();

  const dateStillSet = await page.inputValue('#logs-date-filter');

  console.log(JSON.stringify({
    วันที่ที่เลือก: TARGET_DATE,
    ทันทีหลังเลือก: (afterPick || '').trim(),
    หลัง_poll_22วินาที: (afterPoll || '').trim(),
    ช่องวันที่ยังอยู่: dateStillSet,
    ผลลัพธ์: afterPick === afterPoll ? 'ผ่าน — ข้อมูลไม่หาย' : 'ไม่ผ่าน — ข้อมูลถูกทับ',
  }, null, 2));

  await browser.close();
  if (afterPick !== afterPoll) process.exitCode = 1;
})();
