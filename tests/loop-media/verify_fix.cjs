const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

const CASES = [
  { n: 'sugus', url: 'http://172.81.98.55/ai/Chat.html?id=home-126' },
  { n: 'kiss', url: 'https://www.kisss.ai/ai/Chat.html?id=home-130', map: ['www.kisss.ai', '111.90.150.230'] },
];

(async () => {
  const out = [];
  for (const cse of CASES) {
    const args = cse.map ? [`--host-resolver-rules=MAP ${cse.map[0]} ${cse.map[1]}`] : [];
    const b = await chromium.launch({
      executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
      headless: true, args,
    });
    const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', ignoreHTTPSErrors: true });
    const p = await ctx.newPage();
    const errs = [];
    p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    const r = { site: cse.n };
    try {
      await p.goto(cse.url, { waitUntil: 'domcontentloaded', timeout: 60000 });
      await p.waitForTimeout(4500);
      const st = () => p.evaluate(() => {
        const item = document.querySelector('#pfp-carousel .carousel-item');
        const v = document.querySelector('#pfp-carousel video.fy-pfp-loop');
        const wrap = document.getElementById('fy-scenario');
        const panel = document.querySelector('#profile-partial-turbo');
        return {
          vm: item ? item.classList.contains('fy-mode-video') : null,
          paused: v ? v.paused : null,
          vo: v ? getComputedStyle(v).opacity : null,
          scenario: !!wrap,
          scenarioParentIsPanel: wrap && panel ? wrap.parentElement === panel || wrap.parentElement.parentElement === panel : null,
          scenarioInsideImageRow: wrap ? !!wrap.closest('a[href*="Image.html"]') : null,
          guestQuotaHidden: (() => { const g = document.getElementById('guest-chat-quota'); return g ? g.hidden : null; })(),
        };
      });
      r.init = await st();
      await p.locator('#pfp-carousel #nextBtn, #nextBtn').first().click({ force: true }).catch(() => {});
      await p.waitForTimeout(1800);
      r.afterNext = await st();
      await p.locator('#pfp-carousel #prevBtn, #prevBtn').first().click({ force: true }).catch(() => {});
      await p.waitForTimeout(1200);
      r.afterPrev = await st();
      await p.screenshot({ path: `D:/ever/douyin/douyin/tests/loop-media/fix_${cse.n}.png` });
    } catch (e) { r.error = String(e).slice(0, 120); }
    r.errs = errs.slice(0, 2);
    out.push(r);
    await b.close();
  }
  console.log(JSON.stringify(out, null, 1));
})();
