const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const DIR = __dirname;
const OUT = path.join(__dirname, '..', '..', 'backend-php', 'public', 'assets', 'img', 'pwa');

const jobs = [
  { svg: 'icon_source.svg', size: 192, out: 'icon-192.png' },
  { svg: 'icon_source.svg', size: 512, out: 'icon-512.png' },
  { svg: 'icon_maskable.svg', size: 192, out: 'icon-maskable-192.png' },
  { svg: 'icon_maskable.svg', size: 512, out: 'icon-maskable-512.png' },
  { svg: 'icon_apple.svg', size: 180, out: 'apple-touch-icon.png' },
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
    await page.screenshot({ path: outPath, omitBackground: false });
    await page.close();
    console.log('เขียนแล้ว:', outPath);
  }
  await browser.close();
})();
