const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

(async () => {
  const b = await chromium.launch({
    executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
    headless: true,
  });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  await p.goto('http://172.81.98.55/ai/Chat.html?id=home-126', { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(4500);

  const r = await p.evaluate(() => {
    const tag = document.querySelector('#send-question-with-token .text-white');
    const btn = document.getElementById('send-question-with-token');
    return {
      tagExists: !!tag,
      tagText: tag ? tag.textContent.trim() : null,
      tagDisplay: tag ? getComputedStyle(tag).display : null,
      btnDisplay: btn ? getComputedStyle(btn).display : null,
      spendHint: (document.querySelector('#js-spend .js-sp-hint') || {}).textContent || null,
    };
  });
  const api = await p.evaluate(async () => {
    const out = {};
    try {
      const c = await fetch('/api/live/consumeOptions', { headers: { Accept: 'application/json' } }).then(r => r.json());
      out.consume = c && c.data;
    } catch (e) { out.consumeErr = String(e); }
    return out;
  });
  r.api = api;
  r.errs = errs.slice(0, 2);
  await p.screenshot({ path: 'D:/ever/douyin/douyin/tests/loop-media/chat_send_btn.png' });
  console.log(JSON.stringify(r, null, 1));
  await b.close();
})();
