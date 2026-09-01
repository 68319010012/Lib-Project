// อัดวิดีโอสาธิตจริงจากเว็บ (localhost:8000) ตาม demo-video/script/script.md
// แต่ละฉากจะ "ค้าง" อยู่นานเท่ากับความยาวเสียงพากย์ของฉากนั้นพอดี (มิลลิวินาที
// จาก ffprobe) เพื่อให้เอาเสียงพากย์มาต่อเรียงกันแล้วตรงกับวิดีโอโดยไม่ต้อง
// จูนมือทีหลัง — ดูเหตุผลเต็มใน demo-video/script/README ถ้ามี

const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const BASE = 'http://localhost:8000';
const OUT_DIR = path.join(__dirname, '..', 'recordings', 'raw');
fs.mkdirSync(OUT_DIR, { recursive: true });

// มิลลิวินาที ตรงกับไฟล์เสียงที่สร้างไว้แล้วใน demo-video/voice/
const DUR = {
  p03_login: 14088,
  p04_dashboard: 8808,
  p05_checkin: 12072,
  p06_checkout: 10896,
  p07_history: 9216,
  p09_adminlogin: 5112,
  p10_admindash: 14328,
  p11_members: 10560,
  p12_active: 7224,
  p13_logs: 9768,
  p14_reports: 14928,
  p15_summary: 18096,
};

async function holdFor(startedAt, key) {
  const target = DUR[key];
  const elapsed = Date.now() - startedAt;
  const remaining = Math.max(400, target - elapsed);
  await new Promise((r) => setTimeout(r, remaining));
}

async function recordStudentSide() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    recordVideo: { dir: OUT_DIR, size: { width: 1920, height: 1080 } },
  });
  const page = await context.newPage();

  // ---- PART 3: เข้าสู่ระบบ ----
  let t = Date.now();
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.waitForSelector('input[placeholder*="รหัสนักศึกษา"]');
  await page.locator('input[placeholder*="รหัสนักศึกษา"]').pressSequentially('demostudent', { delay: 70 });
  await page.locator('input[type="password"]').pressSequentially('Demo@2569', { delay: 70 });
  await page.waitForTimeout(400);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 8000 });
  await holdFor(t, 'p03_login');

  // ---- PART 4: หน้าหลักของนักศึกษา ----
  t = Date.now();
  await page.waitForSelector('text=ยังไม่ได้เช็คอินวันนี้', { timeout: 5000 });
  await page.mouse.move(960, 400);
  await page.mouse.move(960, 540, { steps: 10 });
  await holdFor(t, 'p04_dashboard');

  // ---- PART 5: เช็คอิน ----
  t = Date.now();
  await page.click('#stamp-btn');
  await page.waitForSelector('#checkin-modal:not(.hidden)');
  await page.waitForTimeout(500);
  await page.click('[data-modal-tab="hours"]');
  await page.waitForSelector('#modal-hour-buttons button');
  await page.waitForTimeout(400);
  await page.locator('#modal-hour-buttons button').nth(1).click();
  await page.waitForTimeout(500);
  await page.click('#modal-confirm');
  await page.waitForSelector('#checkin-modal', { state: 'hidden' });
  await page.waitForSelector('text=อยู่ในห้องสมุดตอนนี้', { timeout: 8000 });
  await holdFor(t, 'p05_checkin');

  // ---- PART 6: เช็คเอาต์ ----
  t = Date.now();
  await page.click('#stamp-btn');
  await page.waitForSelector('text=เช็คเอาต์แล้วเมื่อ', { timeout: 8000 });
  await holdFor(t, 'p06_checkout');

  // ---- PART 7: ประวัติการเข้าใช้ของตัวเอง ----
  t = Date.now();
  await page.click('#account-menu-btn');
  await page.waitForSelector('#account-menu-dropdown:not(.hidden)');
  await page.waitForTimeout(400);
  await page.click('#account-menu-history');
  await holdFor(t, 'p07_history');

  await page.waitForTimeout(300);
  const video = page.video();
  await context.close();
  const dest = path.join(OUT_DIR, 'student_side.webm');
  await video.saveAs(dest);
  await browser.close();
  console.log('บันทึกวิดีโอฝั่งนักศึกษาแล้ว:', dest);
}

async function recordAdminSide() {
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1920, height: 1080 },
    recordVideo: { dir: OUT_DIR, size: { width: 1920, height: 1080 } },
  });
  // admin-dashboard.js และ admin-logs.js ต่างคำนวณ "เดือนนี้" จาก new Date()
  // ของเบราว์เซอร์เอง ไม่มีช่องให้เลือกเดือนอื่นในหน้าเว็บจริง — ข้อมูลสาธิต
  // ส่วนใหญ่อยู่ในเดือนสิงหาคม ไม่ใช่เดือนที่อัดจริง จึงปลอมนาฬิกาไว้เฉพาะสอง
  // หน้านี้ (เช็คจาก pathname ในสคริปต์เอง) ให้เห็นข้อมูลสมจริง — ไม่แตะหน้า
  // admin-active ที่คำนวณเวลาที่อยู่มาแล้วจากเวลาจริงเทียบกับเวลาเช็คอินจริง
  await context.addInitScript(() => {
    const FAKE_PATHS = ['/admin-dashboard', '/admin-logs'];
    if (!FAKE_PATHS.includes(location.pathname)) return;
    const FAKE_NOW = new Date('2026-08-28T10:30:00+07:00').getTime();
    class FakeDate extends Date {
      constructor(...args) {
        if (args.length === 0) super(FAKE_NOW);
        else super(...args);
      }
      static now() { return FAKE_NOW; }
    }
    window.Date = FakeDate;
  });
  const page = await context.newPage();

  // ---- PART 9: แอดมินเข้าสู่ระบบ ----
  let t = Date.now();
  await page.goto(`${BASE}/login`, { waitUntil: 'load' });
  await page.waitForSelector('input[placeholder*="รหัสนักศึกษา"]');
  await page.locator('input[placeholder*="รหัสนักศึกษา"]').pressSequentially('demoadmin', { delay: 60 });
  await page.locator('input[type="password"]').pressSequentially('DemoAdmin@2569', { delay: 60 });
  await page.waitForTimeout(300);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/admin-dashboard', { timeout: 8000 });
  await holdFor(t, 'p09_adminlogin');

  // ---- PART 10: ภาพรวมแอดมิน ----
  t = Date.now();
  await page.waitForTimeout(1200);
  await page.mouse.move(600, 500);
  await page.mouse.move(1300, 500, { steps: 15 });
  await page.waitForTimeout(600);
  await page.mouse.wheel(0, 400);
  await holdFor(t, 'p10_admindash');

  // ---- PART 11: ทำเนียบสมาชิก ----
  t = Date.now();
  await page.goto(`${BASE}/admin-members`, { waitUntil: 'load' });
  await page.waitForSelector('#members-tbody tr', { timeout: 8000 });
  const search = page.locator('#members-search, input[placeholder*="ค้นหา"]').first();
  await search.pressSequentially('สาธิต', { delay: 90 });
  await page.waitForTimeout(700);
  await page.locator('#members-tbody button:has-text("แก้ไข")').first().click();
  await page.waitForSelector('#member-edit-modal:not(.hidden)', { timeout: 5000 });
  await holdFor(t, 'p11_members');
  await page.locator('#member-edit-modal button:has-text("ยกเลิก")').first().click().catch(() => {});
  await page.waitForTimeout(300);

  // ---- PART 12: กำลังใช้งานอยู่ ----
  t = Date.now();
  await page.goto(`${BASE}/admin-active`, { waitUntil: 'load' });
  await page.waitForSelector('#active-tbody', { timeout: 8000 });
  await holdFor(t, 'p12_active');

  // ---- PART 13: ประวัติการเข้าใช้ทั้งหมด ----
  t = Date.now();
  await page.goto(`${BASE}/admin-logs`, { waitUntil: 'load' });
  await page.waitForSelector('#logs-tbody tr', { timeout: 8000 });
  await page.waitForTimeout(700);
  await page.mouse.wheel(0, 500);
  await holdFor(t, 'p13_logs');

  // ---- PART 14: ศูนย์รายงาน ----
  t = Date.now();
  await page.goto(`${BASE}/admin/reports/print`, { waitUntil: 'load' });
  await page.waitForTimeout(900);
  // "รายงานแบบแดชบอร์ด" เริ่มที่เดือนนี้ (ก.ย. แทบไม่มีข้อมูล) สลับไปเดือนที่
  // มีประวัติทดสอบหนาแน่นก่อนเปิดดูตัวอย่าง
  await page.selectOption('#dashboard_month', '2026-08');
  await page.waitForTimeout(500);
  await page.locator('form[action="/admin/reports/print/dashboard"] button[type="submit"]').click();
  await page.waitForLoadState('load');
  await holdFor(t, 'p14_reports');

  // ---- PART 15: สรุป (กลับไป admin-dashboard ค้างไว้) ----
  t = Date.now();
  await page.goto(`${BASE}/admin-dashboard`, { waitUntil: 'load' });
  await page.waitForTimeout(1200);
  await holdFor(t, 'p15_summary');

  const video = page.video();
  await context.close();
  const dest = path.join(OUT_DIR, 'admin_side.webm');
  await video.saveAs(dest);
  await browser.close();
  console.log('บันทึกวิดีโอฝั่งแอดมินแล้ว:', dest);
}

(async () => {
  await recordStudentSide();
  await recordAdminSide();
  console.log('อัดวิดีโอครบทั้งสองฝั่งแล้ว');
})();
