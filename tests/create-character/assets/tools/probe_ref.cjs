/* 参考站公开页面取证探针：只读 DOM 中的图片 ID / 标签 / 布局，用于判断
   某画风/族裔/组是否有独立渲染，不下载也不绕过任何登录。
   用法：node probe_ref.cjs <style> <ethnicity> [gender] */
const { chromium } = require('D:/ever/bmserv/node_modules/playwright-core');

const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';

(async () => {
  const style = process.argv[2] || 'realistic';
  const eth = process.argv[3] || 'white';
  const gender = process.argv[4] || 'female';
  const url = `https://joi.com/generate/character?g=${gender}&s=${style}&e=${eth}&step=2`;
  const b = await chromium.launch({ executablePath: EXE, headless: true });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: UA });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'load', timeout: 60000 }).catch(() => {});
  await p.waitForTimeout(2600);
  const gate = p.getByRole('button', { name: /over 18/i }).first();
  if (await gate.count()) { await gate.click({ timeout: 4000 }).catch(() => {}); await p.waitForTimeout(1200); }
  // 横向滚动所有卡行触发懒加载，再滚纵向
  await p.evaluate(async () => {
    const rows = Array.from(document.querySelectorAll('*')).filter(e => e.scrollWidth > e.clientWidth + 40 && e.clientHeight > 30 && e.clientHeight < 400);
    for (const r of rows) { r.scrollLeft = r.scrollWidth; await new Promise(x => setTimeout(x, 200)); }
    for (let y = 0; y <= document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(x => setTimeout(x, 180)); }
  });
  await p.waitForTimeout(1200);
  const data = await p.evaluate(() => {
    const imgs = Array.from(document.querySelectorAll('img')).map(i => {
      const b = i.getBoundingClientRect();
      return { src: i.currentSrc || i.src, alt: i.alt, box: [Math.round(b.width), Math.round(b.height)], y: Math.round(b.y), x: Math.round(b.x), nat: [i.naturalWidth, i.naturalHeight] };
    }).filter(i => /imagedelivery/.test(i.src));
    const labels = Array.from(document.querySelectorAll('button')).map(e => (e.innerText || '').replace(/\s+/g, ' ').trim()).filter(t => t && t.length < 40);
    return { url: location.href, imgs, labels, h: document.body.scrollHeight };
  });
  const out = { style, eth, gender, url, captured_at: new Date().toISOString(), data };
  console.log(JSON.stringify(out, null, 1).slice(0, 6000));
  await b.close();
})();
