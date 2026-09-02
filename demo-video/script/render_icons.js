const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const DIR = __dirname;
const OUT = path.join(__dirname, '..', '..', 'backend-php', 'public', 'assets', 'img', 'pwa');

// transparent: มุมนอกวงกลมปล่อยโปร่งใสหรือถมทึบ
//   purpose "any"  -> โปร่งใส เพราะวินโดวส์/เดสก์ท็อปวางไอคอนบนพื้นหลังอะไรก็ได้
//                     ถ้าถมขาวไว้จะเห็นเป็นกล่องสี่เหลี่ยมรอบวงกลม (ที่ผู้ใช้ติมา)
//   purpose "maskable" -> ทึบ เพราะแอนดรอยด์ตัดรูปเอง ต้องมีสีเต็มกรอบ
//   apple-touch-icon   -> ทึบ เพราะ iOS ถมพื้นโปร่งใสด้วยสีดำ
const jobs = [
  { svg: 'icon_any_from_logo.svg', size: 192, out: 'icon-192.png', transparent: true },
  { svg: 'icon_any_from_logo.svg', size: 512, out: 'icon-512.png', transparent: true },
  { svg: 'icon_maskable_from_logo.svg', size: 192, out: 'icon-maskable-192.png', transparent: false },
  { svg: 'icon_maskable_from_logo.svg', size: 512, out: 'icon-maskable-512.png', transparent: false },
  { svg: 'icon_any_from_logo.svg', size: 180, out: 'apple-touch-icon.png', transparent: false },
];

(async () => {
  const browser = await chromium.launch();
  for (const job of jobs) {
    const svgPath = path.join(DIR, job.svg);
    const page = await browser.newPage({ viewport: { width: job.size, height: job.size }, deviceScaleFactor: 1 });
    const svgContent = fs.readFileSync(svgPath, 'utf-8');
    await page.setContent(`<!doctype html><html><head><style>html,body{margin:0;padding:0;}svg{display:block;width:${job.size}px;height:${job.size}px;}</style></head><body>${svgContent}</body></html>`);
    await page.waitForTimeout(50);
    const outPath = path.join(OUT, job.out);
    await page.screenshot({ path: outPath, omitBackground: !!job.transparent });
    await page.close();
    console.log('เขียนแล้ว:', outPath);
  }
  await browser.close();
})();
