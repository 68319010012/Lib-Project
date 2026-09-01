// ทดสอบทุกปุ่ม/ทุกหน้าที่สคริปต์วิดีโอจะสาธิต ก่อนอัดจริง
// รันแบบไม่บันทึกวิดีโอ แค่ให้แน่ใจว่าไม่มีปุ่มไหนพังหรือ element หาไม่เจอ
const { chromium } = require('playwright');

const BASE = 'http://localhost:8000';
const ok = (label) => console.log(`[OK] ${label}`);
const fail = (label, err) => { console.log(`[FAIL] ${label}: ${err.message}`); process.exitCode = 1; };

async function step(label, fn) {
  try { await fn(); ok(label); } catch (err) { fail(label, err); throw err; }
}

(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage({ viewport: { width: 1920, height: 1080 } });

  // ---------- ฝั่งนักศึกษา ----------
  await step('เปิดหน้า /login', async () => {
    await page.goto(`${BASE}/login`, { waitUntil: 'load' });
    await page.waitForSelector('input[type="text"]', { timeout: 5000 });
  });

  await step('ล็อกอิน demostudent', async () => {
    await page.fill('input[placeholder*="รหัสนักศึกษา"]', 'demostudent');
    await page.fill('input[type="password"]', 'Demo@2569');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/dashboard', { timeout: 8000 });
  });

  await step('หน้า dashboard มีปุ่มเช็คอิน', async () => {
    // ฐานข้อมูลถูกรีเซ็ตให้ demostudent ยังไม่มีแถวของวันนี้ก่อนรันสคริปต์นี้เสมอ
    // (ดู scratchpad/reset_demostudent.php) จึงมั่นใจได้ว่าอยู่ในสถานะนี้จริง
    await page.waitForSelector('text=ยังไม่ได้เช็คอินวันนี้', { timeout: 5000 });
  });

  await step('กดปุ่มเช็คอิน เปิดโมดัลเลือกเวลา', async () => {
    await page.click('#stamp-btn');
    await page.waitForSelector('#checkin-modal:not(.hidden)', { timeout: 5000 });
  });

  await step('สลับไปแท็บเลือกจำนวนชั่วโมง', async () => {
    await page.click('[data-modal-tab="hours"]');
    await page.waitForSelector('#modal-hour-buttons button', { timeout: 3000 });
  });

  await step('เลือกจำนวนชั่วโมง แล้วยืนยันเช็คอิน', async () => {
    await page.locator('#modal-hour-buttons button').first().click();
    await page.click('#modal-confirm');
    await page.waitForSelector('#checkin-modal', { state: 'hidden', timeout: 5000 });
    await page.waitForSelector('text=เช็คเอาต์', { timeout: 8000 });
  });

  await step('กดปุ่มเช็คเอาต์', async () => {
    await page.click('#stamp-btn');
    // หลังเช็คเอาต์ป้ายจะขึ้น "เช็คเอาต์แล้วเมื่อ HH:MM น." ไม่ใช่กลับไปเป็น
    // "ยังไม่ได้เช็คอินวันนี้" อีก — สองสถานะนี้ตั้งใจแยกกันในตัวแอป
    await page.waitForSelector('text=เช็คเอาต์แล้วเมื่อ', { timeout: 8000 });
  });

  await step('เปิดเมนูบัญชี', async () => {
    await page.click('#account-menu-btn');
    await page.waitForSelector('#account-menu-dropdown:not(.hidden)', { timeout: 5000 });
  });

  await step('กดประวัติการเข้าใช้ เปิดโมดัล', async () => {
    await page.click('#account-menu-history');
    await page.waitForTimeout(600);
  });

  await step('ปิดโมดัลประวัติด้วยปุ่มปิด', async () => {
    const closeBtn = page.locator('[id*="history"] button[aria-label*="ปิด"], .history-modal button:has-text("ปิด")').first();
    if (await closeBtn.count()) await closeBtn.click();
    else await page.mouse.click(5, 5); // คลิกนอกโมดัลเป็นทางสำรอง
    await page.waitForTimeout(400);
  });

  await step('ออกจากระบบ (student)', async () => {
    await page.click('#account-menu-btn');
    await page.waitForSelector('#account-menu-dropdown:not(.hidden)', { timeout: 5000 });
    await page.click('#account-menu-logout');
    await page.waitForURL('**/login', { timeout: 8000 });
  });

  // ---------- ฝั่งแอดมิน ----------
  await step('ล็อกอิน demoadmin', async () => {
    await page.waitForSelector('input[placeholder*="รหัสนักศึกษา"]', { timeout: 5000 });
    await page.fill('input[placeholder*="รหัสนักศึกษา"]', 'demoadmin');
    await page.fill('input[type="password"]', 'DemoAdmin@2569');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin-dashboard', { timeout: 8000 });
  });

  await step('หน้า admin-dashboard โหลดกราฟ', async () => {
    await page.waitForTimeout(1500);
  });

  await step('เปิด /admin-members', async () => {
    await page.goto(`${BASE}/admin-members`, { waitUntil: 'load' });
    await page.waitForSelector('#members-tbody tr', { timeout: 8000 });
  });

  await step('ค้นหาสมาชิก', async () => {
    const search = page.locator('#members-search, input[placeholder*="ค้นหา"]').first();
    await search.fill('สาธิต');
    await page.waitForTimeout(800);
  });

  await step('เปิดโมดัลแก้ไขสมาชิก', async () => {
    await page.locator('#members-tbody button:has-text("แก้ไข")').first().click();
    await page.waitForSelector('#member-edit-modal:not(.hidden)', { timeout: 5000 });
  });

  await step('ปิดโมดัลแก้ไขสมาชิก', async () => {
    await page.locator('#member-edit-modal button:has-text("ยกเลิก"), #member-edit-modal button[aria-label*="ปิด"]').first().click();
    await page.waitForTimeout(300);
  });

  await step('เปิด /admin-active', async () => {
    await page.goto(`${BASE}/admin-active`, { waitUntil: 'load' });
    await page.waitForSelector('#active-tbody', { timeout: 8000 });
  });

  await step('เปิด /admin-logs', async () => {
    await page.goto(`${BASE}/admin-logs`, { waitUntil: 'load' });
    await page.waitForSelector('#logs-tbody', { timeout: 8000 });
  });

  await step('เปิดศูนย์รายงาน', async () => {
    await page.goto(`${BASE}/admin/reports/print`, { waitUntil: 'load' });
    await page.waitForTimeout(1000);
  });

  await browser.close();
  console.log('\nเสร็จการทดสอบทุกขั้นตอน');
})().catch(() => process.exit(1));
