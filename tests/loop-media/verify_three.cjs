const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

const SITES = [
  { n: 'heygirl', base: 'http://172.81.99.11/ai', id: 'home-130' },
  { n: 'kelymi', base: 'http://172.81.99.42/ai', id: 'home-130' },
  { n: 'kiss', base: 'https://127.0.0.1/ai', id: 'home-130', host: 'www.kisss.ai' },
];

(async () => {
  const b = await chromium.launch({
    executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
    headless: true,
  });
  const out = [];
  for (const s of SITES) {
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', ignoreHTTPSErrors: true });
    if (s.host) await ctx.setExtraHTTPHeaders({ Host: s.host });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    const r = { site: s.n };
    try {
      await p.goto(`${s.base}/Chat.html?id=${s.id}`, { waitUntil: 'domcontentloaded', timeout: 60000 });
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
      // 首页 hover 播完不打断
      await p.goto(`${s.base}/Home.html`, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await p.waitForTimeout(3500);
      const card = p.locator('.c-card-loop').first();
      if (await card.count()) {
        await card.hover({ force: true });
        await p.waitForTimeout(1600);
        r.hover = await p.evaluate(() => {
          const v = document.querySelector('.c-card-loop');
          return v ? { on: v.classList.contains('is-on'), paused: v.paused, t: +v.currentTime.toFixed(2), loop: v.loop } : null;
        });
        await p.mouse.move(5, 5);
        await p.waitForTimeout(900);
        r.afterOut = await p.evaluate(() => {
          const v = document.querySelector('.c-card-loop');
          return v ? { paused: v.paused, t: +v.currentTime.toFixed(2) } : null;
        });
      }
    } catch (e) {
      r.error = String(e).slice(0, 140);
    }
    r.errs = errs.slice(0, 2);
    out.push(r);
    await ctx.close();
  }
  console.log(JSON.stringify(out, null, 1));
  await b.close();
})();
