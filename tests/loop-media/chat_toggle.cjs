const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

const BASE = process.env.BASE || 'http://172.81.98.55/ai/Chat.html?id=home-126';

(async () => {
  const b = await chromium.launch({
    executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
    headless: true,
  });
  const ctx = await b.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  await p.goto(BASE, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(4000);

  const state = () => p.evaluate(() => {
    const c = document.querySelector('#pfp-carousel .carousel');
    const item = c && c.querySelector('.carousel-item');
    const v = document.querySelector('#pfp-carousel video.fy-pfp-loop');
    const img = item && item.querySelector('img');
    const dots = Array.from(document.querySelectorAll('#pfp-carousel .carousel-dots .dot'));
    return {
      dots: dots.length,
      dotActive: dots.map(d => d.classList.contains('active')),
      hasVideo: !!v,
      videoMode: item ? item.classList.contains('fy-mode-video') : null,
      src: v ? (v.getAttribute('src') || '').slice(-28) : '',
      paused: v ? v.paused : null,
      t: v ? +v.currentTime.toFixed(2) : null,
      imgVisible: img ? getComputedStyle(img).opacity : null,
      videoOpacity: v ? getComputedStyle(v).opacity : null,
    };
  });

  const out = {};
  out.initial = await state();

  const dots = p.locator('#pfp-carousel .carousel-dots .dot');
  await dots.nth(1).click({ force: true });
  await p.waitForTimeout(2500);
  out.afterVideo = await state();
  await p.screenshot({ path: 'D:/ever/douyin/douyin/tests/loop-media/chat_mode_video.png' });

  await dots.nth(0).click({ force: true });
  await p.waitForTimeout(1200);
  out.afterPhoto = await state();
  await p.screenshot({ path: 'D:/ever/douyin/douyin/tests/loop-media/chat_mode_photo.png' });

  await dots.nth(1).click({ force: true });
  await p.waitForTimeout(2000);
  out.backToVideo = await state();

  out.errs = errs.slice(0, 3);
  console.log(JSON.stringify(out, null, 1));
  await b.close();
})();
