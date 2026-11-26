const { createRequire } = require('node:module');
const rq = createRequire('D:/ever/douyin/_clone_probe/drv/');
const { chromium } = rq('playwright-core');

const BASE = process.env.BASE || 'http://172.81.98.55/ai';

(async () => {
  const b = await chromium.launch({
    executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
    headless: true,
  });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' });
  const p = await ctx.newPage();
  const errs = [];
  p.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
  await p.goto(`${BASE}/Chat.html?id=home-126`, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await p.waitForTimeout(3500);

  const quota = () => p.evaluate(() => {
    const el = document.getElementById('guest-chat-quota');
    return el ? el.textContent.replace(/\s+/g, ' ').trim() : null;
  });
  const out = { start: await quota(), turns: [] };

  for (let i = 1; i <= 6; i++) {
    const input = p.locator('#message_body');
    if (!(await input.count())) { out.turns.push({ i, note: 'no input (redirected?)', url: p.url() }); break; }
    await input.fill('hello ' + i);
    await p.keyboard.press('Enter');
    await p.waitForTimeout(6500);
    const info = await p.evaluate(() => {
      const box = document.getElementById('messages_turbo_frame') || document.getElementById('messages');
      const bubbles = box ? box.querySelectorAll('.fy-bubble-ai, .fy-msg-ai') : [];
      const errs = box ? Array.from(box.querySelectorAll('.fy-error, [class*="error"]')).map(e => e.textContent.trim()).slice(-1) : [];
      return { bubbles: bubbles.length, last: bubbles.length ? bubbles[bubbles.length - 1].textContent.trim().slice(0, 60) : '', err: errs[0] || '' };
    });
    out.turns.push({ i, ...info, quota: await quota(), url: p.url().endsWith('Chat.html?id=home-126') ? 'same' : p.url() });
    if (p.url().indexOf('Chat.html') < 0) break;
  }
  out.errs = errs.slice(0, 2);
  await p.screenshot({ path: 'D:/ever/douyin/douyin/tests/loop-media/guest_quota.png' });
  console.log(JSON.stringify(out, null, 1));
  await b.close();
})();
