const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

(async () => {
  const b = await chromium.launch({
    executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
    headless: true,
    args: ['--host-resolver-rules=MAP www.kisss.ai 111.90.150.230'],
  });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', ignoreHTTPSErrors: true });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  const r = {};
  await p.goto('https://www.kisss.ai/ai/Chat.html?id=home-130', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(4500);
  const st = () => p.evaluate(() => {
    const c = document.querySelector('#pfp-carousel .carousel');
    const item = c && c.querySelector('.carousel-item');
    const v = document.querySelector('#pfp-carousel video.fy-pfp-loop');
    const dots = Array.from(document.querySelectorAll('#pfp-carousel .carousel-dots .dot'));
    return {
      dots: dots.length,
      act: dots.map(d => d.classList.contains('active')),
      vm: item ? item.classList.contains('fy-mode-video') : null,
      src: v ? !!v.getAttribute('src') : null,
      paused: v ? v.paused : null,
      vo: v ? getComputedStyle(v).opacity : null,
    };
  });
  r.init = await st();
  const dots = p.locator('#pfp-carousel .carousel-dots .dot');
  await dots.nth(1).click({ force: true });
  await p.waitForTimeout(2200);
  r.video = await st();
  await dots.nth(0).click({ force: true });
  await p.waitForTimeout(900);
  r.photo = await st();
  r.errs = errs.slice(0, 2);
  console.log(JSON.stringify(r, null, 1));
  await b.close();
})();
