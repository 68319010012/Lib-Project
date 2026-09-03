// ทดสอบสองอาการที่ผู้ใช้แจ้งจากแอปที่ติดตั้ง (PWA)
//
//   1) พอเลยเวลาที่ตั้งไว้ หน้าจอค้างอยู่ที่ปุ่ม "เช็คเอาต์" สีแดง ทั้งที่
//      เซิร์ฟเวอร์เช็คเอาต์ให้ไปแล้ว
//   2) เตือนแค่ครั้งเดียวแล้วเงียบ อยากให้สั่นถี่ขึ้นเรื่อย ๆ จนถึงเวลาจริง
//
// วิธีทดสอบ: เขียนแถวเช็คอินลงฐานข้อมูลโดยตรง โดยตั้ง planned_checkout_at ให้
// เหลืออีกไม่กี่วินาที แล้วดูหน้าจอจริง ไม่ใช่ดูแต่โค้ด
//
//   node demo-video/repro_reminder.js [วินาทีที่เหลือ]
//
// ต้องมี PHP dev server ที่ localhost:8000 และ MariaDB รันอยู่
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const MYSQL = 'C:/xampp/mysql/bin/mysql.exe';
const USER = '99999999';
const PASS = 'DemoStudent@2569';
const SECONDS_LEFT = Number(process.argv[2] || 75);

function sql(q) {
  return execFileSync(MYSQL, ['-u', 'root', 'library_checkin', '-N', '-e', q], {
    encoding: 'utf8',
  }).trim();
}

const log = (...a) => console.log(...a);

(async () => {
  const userId = sql(`SELECT user_id FROM users WHERE username='${USER}'`);
  if (!userId) throw new Error(`ไม่พบบัญชี ${USER}`);

  // เริ่มจากสถานะสะอาด: ลบประวัติของวันนี้ทิ้ง แล้วใส่เช็คอินที่กำลังจะหมดเวลา
  sql(`DELETE FROM checkin_logs WHERE user_id=${userId} AND DATE(timestamp)=CURDATE()`);
  sql(
    `INSERT INTO checkin_logs (user_id, type, timestamp, planned_checkout_at)
     VALUES (${userId}, 'in', NOW(), NOW() + INTERVAL ${SECONDS_LEFT} SECOND)`
  );
  log(`ตั้งเช็คอินให้เหลืออีก ${SECONDS_LEFT} วินาที (user_id=${userId})`);

  const browser = await chromium.launch({ headless: true });
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } });

  // ดักการสั่นไว้ก่อนสคริปต์ของหน้าจะรัน — headless ไม่มีมอเตอร์สั่นให้เรียกจริง
  await ctx.addInitScript(() => {
    window.__buzz = [];
    navigator.vibrate = (pattern) => {
      window.__buzz.push({ at: Date.now(), pattern });
      return true;
    };
  });

  const page = await ctx.newPage();
  await page.goto('http://localhost:8000/login', { waitUntil: 'load' });
  await page.fill('input[placeholder*="รหัสนักศึกษา"]', USER);
  await page.fill('input[type="password"]', PASS);
  await page.click('button[type="submit"]');
  await page.waitForURL('**/dashboard', { timeout: 10000 });
  await page.waitForSelector('#stamp-btn', { timeout: 10000 });
  await page.waitForTimeout(2000);

  const redNow = () => page.evaluate(() =>
    document.getElementById('stamp-btn').classList.contains('bg-error'));
  const reminderShown = () => page.evaluate(() =>
    !document.getElementById('reminder-banner').classList.contains('hidden'));
  const reminderText = () => page.textContent('#reminder-text');
  const buzzCount = () => page.evaluate(() => window.__buzz.length);

  const results = [];
  const check = (name, pass, detail) => {
    results.push({ name, pass, detail });
    log(`${pass ? 'ผ่าน  ' : 'ไม่ผ่าน'} ${name}${detail ? ' — ' + detail : ''}`);
  };

  check('เริ่มต้นอยู่ในสถานะเช็คอิน (ปุ่มแดง)', await redNow());
  check('กล่องเตือนขึ้นเอง', await reminderShown(), (await reminderText() || '').trim());
  const buzz0 = await buzzCount();
  check('สั่นครั้งแรกทันทีที่เตือน', buzz0 >= 1, `${buzz0} ครั้ง`);

  // ช่วงสองนาทีสุดท้ายตั้งไว้ให้สั่นทุก 20 วินาที — 50 วินาทีจึงต้องได้เพิ่มอีก >= 2
  log('รอ 50 วินาที เพื่อดูจังหวะการสั่นซ้ำ...');
  await page.waitForTimeout(50000);
  const buzz1 = await buzzCount();
  check('สั่นซ้ำถี่ขึ้นระหว่างรอ', buzz1 - buzz0 >= 2, `เพิ่มอีก ${buzz1 - buzz0} ครั้งใน 50 วินาที`);

  const patterns = await page.evaluate(() => window.__buzz.map((b) => b.pattern.length));
  check('จังหวะสั่นช่วงท้ายยาวขึ้น', patterns[patterns.length - 1] >= 7,
    `ท่าสุดท้ายมี ${patterns[patterns.length - 1]} จังหวะ`);

  const txtBefore = (await reminderText() || '').trim();

  // ข้ามเวลาที่ตั้งไว้ แล้วจับเวลาว่าหน้าจอกลับเป็นปุ่มเขียวภายในกี่วินาที
  const waitPast = Math.max(0, SECONDS_LEFT - 52) + 3;
  log(`รออีก ${waitPast} วินาที ให้เลยเวลาที่ตั้งไว้...`);
  await page.waitForTimeout(waitPast * 1000);

  const flipStart = Date.now();
  let flipped = false;
  for (let i = 0; i < 30; i++) {
    if (!(await redNow())) { flipped = true; break; }
    await page.waitForTimeout(1000);
  }
  const flipSecs = Math.round((Date.now() - flipStart) / 1000);

  check('หน้าจอเลิกค้างปุ่มแดงหลังเลยเวลา', flipped, `ใช้เวลา ${flipSecs} วินาที`);
  check('กลับมาไวกว่ารอบ poll ปกติ (60 วินาที)', flipped && flipSecs < 30, `${flipSecs} วินาที`);
  check('กล่องเตือนปิดเองหลังเลยเวลา', !(await reminderShown()));

  await page.screenshot({ path: path.join(__dirname, 'repro_reminder.png') });
  await browser.close();

  const failed = results.filter((r) => !r.pass);
  log(`\n${results.length - failed.length} ผ่าน, ${failed.length} ไม่ผ่าน`);
  log(`ข้อความก่อนหมดเวลา: "${txtBefore}"`);
  if (failed.length) process.exitCode = 1;
})();
