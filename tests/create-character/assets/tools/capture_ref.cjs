/* 公开参考页取证：抓取卡片图片 ID / alt / 几何，不下载图片、不登录、不做验证码绕过。
   用法: node capture_ref.cjs <gender> <style> <ethnicity> [step]
   产物: ../evidence/ref/<gender>-<style>-<ethnicity>-s<step>.json */
const { chromium } = require('D:/ever/bmserv/node_modules/playwright-core');
const fs = require('node:fs');
const path = require('node:path');

const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const UA = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
const OUT = path.join(__dirname, '..', 'evidence', 'ref');

(async () => {
  const [gender = 'female', style = 'realistic', eth = 'white', step = '2'] = process.argv.slice(2);
  const url = `https://joi.com/generate/character?g=${gender}&s=${style}&e=${eth}&step=${step}`;
  const b = await chromium.launch({ executablePath: EXE, headless: true });
  const ctx = await b.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true, userAgent: UA });
  const p = await ctx.newPage();
  await p.goto(url, { waitUntil: 'load', timeout: 60000 }).catch(() => {});
  await p.waitForTimeout(2600);
  const gate = p.getByRole('button', { name: /over 18/i }).first();
  if (await gate.count()) { await gate.click({ timeout: 4000 }).catch(() => {}); await p.waitForTimeout(1200); }
  await p.evaluate(async () => {
    const rows = Array.from(document.querySelectorAll('*')).filter(e => e.scrollWidth > e.clientWidth + 40 && e.clientHeight > 30 && e.clientHeight < 400);
    for (const r of rows) { r.scrollLeft = r.scrollWidth; await new Promise(x => setTimeout(x, 200)); }
    for (let y = 0; y <= document.body.scrollHeight; y += 500) { window.scrollTo(0, y); await new Promise(x => setTimeout(x, 180)); }
    window.scrollTo(0, 0);
  });
  await p.waitForTimeout(1200);
  const data = await p.evaluate(() => {
    const pick = (i) => {
      const src = i.currentSrc || i.src || '';
      const m = src.match(/imagedelivery\.net\/[^/]+\/([0-9a-f-]+)\/([^/?#]+)/);
      if (!m) return null;
      const r = i.getBoundingClientRect();
      return { id: m[1], variant: m[2], alt: (i.alt || '').trim(), nat: [i.naturalWidth, i.naturalHeight], box: [Math.round(r.width), Math.round(r.height)], x: Math.round(r.x), y: Math.round(r.y) };
    };
    const imgs = Array.from(document.querySelectorAll('img')).map(pick).filter(Boolean);
    const best = {};
    for (const it of imgs) {
      if (it.variant.includes('width=32')) continue;
      const prev = best[it.id];
      if (!prev || it.nat[0] > prev.nat[0]) best[it.id] = it;
    }
    const cards = Array.from(document.querySelectorAll('button')).map(e => {
      const img = e.querySelector('img'); const r = e.getBoundingClientRect();
      return { label: (e.innerText || '').replace(/\s+/g, ' ').trim(), alt: img ? img.alt : '', box: [Math.round(r.width), Math.round(r.height)], x: Math.round(r.x), y: Math.round(r.y) };
    }).filter(c => c.label || c.alt);
    return { url: location.href, scrollH: document.body.scrollHeight, images: Object.values(best), cards };
  });
  fs.mkdirSync(OUT, { recursive: true });
  const file = path.join(OUT, `${gender}-${style}-${eth}-s${step}.json`);
  fs.writeFileSync(file, JSON.stringify({ gender, style, eth, step, captured_at: new Date().toISOString(), url, data }, null, 1));
  const byY = {};
  for (const im of data.images) (byY[im.y] ||= []).push(`${im.alt || '?'}=${im.id.slice(0, 8)}`);
  console.log(file, 'images', data.images.length);
  for (const y of Object.keys(byY).sort((a, b) => a - b)) console.log(' y' + y, byY[y].join(' '));
  await b.close();
})();
