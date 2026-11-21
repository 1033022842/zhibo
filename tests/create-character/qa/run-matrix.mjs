// 独立浏览器验收：宽度矩阵 + 逐屏截图/标题/滚动/CTA/溢出/重复挂载 + 破图 + 游客创建请求计数。
// 用法:
//   node tests/create-character/qa/run-matrix.mjs --tag baseline
//   node tests/create-character/qa/run-matrix.mjs --tag v3 --base http://127.0.0.1:8017
// 仅访问本地 127.0.0.1；公网请求全部 abort；/api/* 走 serve.mjs 本地 mock。
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'file:///D:/ever/bmserv/node_modules/playwright-core/index.mjs';

const HERE = path.dirname(fileURLToPath(import.meta.url));
const REPO = path.resolve(HERE, '../../..');
const arg = (k, d) => { const i = process.argv.indexOf('--' + k); return i > 0 ? process.argv[i + 1] : d; };
const TAG = arg('tag', 'run');
const BASE = arg('base', 'http://127.0.0.1:8017');
const URL_ENTRY = `${BASE}/ai/createCharacter.html`;
const DEEP = `${URL_ENTRY}?g=female&s=realistic&e=black&a=30&p=average&b=average&bu=athletic&hac=blue&ha=bob&n=Valerie+Wright&knk=master-slave&knk=discipline&knk=chains&step=7`;
const OUT = path.join(HERE, 'evidence', `${new Date().toISOString().replace(/[:.]/g, '-')}_${TAG}`);
const CHROME = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe';
const LOG = path.join(HERE, 'logs', 'requests.jsonl');
fs.mkdirSync(path.join(OUT, 'shots'), { recursive: true });

const VPS = [
  { name: '320x844', width: 320, height: 844, dsf: 2, mobile: true },
  { name: '360x844', width: 360, height: 844, dsf: 2, mobile: true },
  { name: '390x844', width: 390, height: 844, dsf: 3, mobile: true },
  { name: '430x844', width: 430, height: 844, dsf: 3, mobile: true },
  { name: 'desktop-1440x900', width: 1440, height: 900, dsf: 1, mobile: false },
];

const NEXT_RE = /^(next|continue|start|start now|bring (her|him) to life|create|finish|done|sign up for free)$/i;

function metrics() {
  const vis = el => { const s = getComputedStyle(el); const r = el.getBoundingClientRect(); return s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.01 && r.width > 1 && r.height > 1; };
  const r2 = r => [Math.round(r.x), Math.round(r.y), Math.round(r.width), Math.round(r.height)];
  const titles = Array.from(document.querySelectorAll('[data-testid="screen-title"],.cc-step-title,.cc-screen-title,.cc-entry-title,.wizard-title,main h1,main h2,h1,h2'))
    .filter(vis).map(e => (e.textContent || '').trim().replace(/\s+/g, ' ')).filter(Boolean);
  const btns = Array.from(document.querySelectorAll('button,a[role="button"],[data-testid="cta"],.cc-fab'))
    .filter(vis).map(e => ({ text: (e.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 60), disabled: !!e.disabled || e.getAttribute('aria-disabled') === 'true', rect: r2(e.getBoundingClientRect()), cls: e.className && String(e.className).slice(0, 80), tid: e.getAttribute('data-testid') || '' }));
  const overflow = Array.from(document.querySelectorAll('body *')).filter(vis).map(e => ({ e, r: e.getBoundingClientRect() }))
    .filter(x => x.r.right > innerWidth + 1 || x.r.left < -1)
    .slice(0, 8).map(x => ({ tag: x.e.tagName.toLowerCase(), cls: String(x.e.className || '').slice(0, 50), tid: x.e.getAttribute('data-testid') || '', rect: r2(x.r), text: (x.e.textContent || '').trim().slice(0, 40) }));
  const broken = Array.from(document.images).filter(i => i.complete && i.naturalWidth === 0).map(i => (i.currentSrc || i.src || '').slice(0, 160));
  const fixedBars = Array.from(document.querySelectorAll('body *')).filter(vis).filter(e => ['fixed', 'sticky'].includes(getComputedStyle(e).position)).map(e => ({ cls: String(e.className || '').slice(0, 50), rect: r2(e.getBoundingClientRect()) })).filter(x => x.rect[3] > 30);
  const mount = {
    stepNodes: document.querySelectorAll('.cc-step').length,
    visibleSteps: Array.from(document.querySelectorAll('.cc-step')).filter(vis).length,
    ccMain: document.querySelectorAll('#ccMain').length,
    wizardRoots: document.querySelectorAll('[data-testid="wizard"],.cc-wizard,.wizard-root').length,
    duplicateTitle: titles.length > 1 && new Set(titles).size < titles.length,
  };
  return {
    url: location.href,
    title: titles[0] || '',
    titles: titles.slice(0, 3),
    vw: innerWidth, vh: innerHeight,
    docScrollW: document.documentElement.scrollWidth,
    bodyScrollW: document.body.scrollWidth,
    horizontalOverflow: document.documentElement.scrollWidth > innerWidth + 1,
    pageScrollH: Math.max(document.documentElement.scrollHeight, document.body.scrollHeight),
    overflow, broken, btns, fixedBars, mount,
  };
}

async function clickNext(page) {
  const cands = await page.$$('button,a[role="button"],[data-testid="cta"],.cc-fab');
  for (const el of cands.reverse()) {
    const ok = await el.evaluate((e, re) => {
      const s = getComputedStyle(e); const r = e.getBoundingClientRect();
      const text = (e.textContent || '').trim().replace(/[\u2190-\u21FF\u2026!]+/g, '').replace(/\s+/g, ' ').trim();
      const vis = s.display !== 'none' && s.visibility !== 'hidden' && Number(s.opacity) > 0.01 && r.width > 1 && r.height > 1;
      return vis && !e.disabled && r.bottom > 0 && r.top < innerHeight && new RegExp(re, 'i').test(text);
    }, NEXT_RE.source).catch(() => false);
    if (ok) {
      await el.scrollIntoViewIfNeeded().catch(() => {});
      await el.click({ timeout: 3000 }).catch(async () => { await el.evaluate(e => e.click()); });
      return true;
    }
  }
  // 没有 Next：点当前屏第一个可选项，推进选择流程
  const opts = await page.$$('[data-testid="option"],.cc-card,.cc-opt,.option-card,[role="radio"]');
  for (const el of opts) {
    const pick = await el.evaluate(e => {
      const s = getComputedStyle(e); const r = e.getBoundingClientRect();
      const vis = s.display !== 'none' && s.visibility !== 'hidden' && r.width > 1 && r.height > 1 && r.top < innerHeight && r.bottom > 0;
      const sel = /selected|active|checked/.test(e.className) || e.getAttribute('aria-checked') === 'true' || e.getAttribute('aria-selected') === 'true';
      return vis && !sel && !e.disabled;
    }).catch(() => false);
    if (pick) { await el.click({ timeout: 3000 }).catch(() => {}); return 'select'; }
  }
  return false;
}

async function runViewport(browser, vp, opts = {}) {
  const ctx = await browser.newContext({
    viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: vp.dsf,
    isMobile: vp.mobile, hasTouch: vp.mobile,
    extraHTTPHeaders: { 'x-qa-scenario': opts.scenario || 'ok' },
  });
  const page = await ctx.newPage();
  const errs = [];
  page.on('pageerror', e => errs.push('pageerror: ' + String(e).slice(0, 200)));
  page.on('console', m => { if (m.type() === 'error') errs.push('console: ' + m.text().slice(0, 200)); });
  const apiCalls = [];
  await page.route('**/*', route => {
    const u = new URL(route.request().url());
    if (u.hostname === '127.0.0.1' || u.hostname === 'localhost') {
      if (u.pathname.startsWith('/api/')) apiCalls.push({ method: route.request().method(), path: u.pathname, post: (route.request().postData() || '').slice(0, 500) });
      return route.continue();
    }
    return route.abort();
  });

  const screens = [];
  try {
    await page.goto(opts.deep ? DEEP : URL_ENTRY, { waitUntil: 'load', timeout: 30000 });
    await page.waitForTimeout(1200);
    for (let i = 0; i < 12; i++) {
      try { await page.waitForFunction(() => Array.from(document.images).every(im => im.complete), null, { timeout: 5000 }); } catch {}
      await page.waitForTimeout(350);
      const m = await page.evaluate(metrics);
      m.index = i;
      m.shot = `shots/${vp.name}_s${i}.png`;
      await page.screenshot({ path: path.join(OUT, m.shot), fullPage: false });
      screens.push(m);
      if (i >= 11) break;
      const stateOf = () => page.evaluate(() => document.body.innerText.replace(/\s+/g, ' ').slice(0, 1200) + '|' + document.body.scrollHeight);
      const before = await stateOf();
      const act = await clickNext(page);
      if (!act) break;
      await page.waitForTimeout(act === 'select' ? 500 : 900);
      const after = await stateOf();
      if (after === before) break;
    }
  } catch (e) {
    errs.push('fatal: ' + String(e).slice(0, 300));
    await page.screenshot({ path: path.join(OUT, `shots/${vp.name}_fatal.png`) }).catch(() => {});
  }
  await ctx.close();
  return { viewport: vp.name, size: [vp.width, vp.height], deep: !!opts.deep, screens, errs, apiCalls };
}

const browser = await chromium.launch({ executablePath: CHROME, headless: true });
const report = { tag: TAG, base: BASE, chrome: CHROME, startedAt: new Date().toISOString(), runs: [] };

// 1) 全宽度普通入口
const tasks = VPS.map(vp => runViewport(browser, vp));
// 2) 390 深链 + 游客创建请求场景（顺序执行，读同一份服务端日志）
for (const r of await Promise.all(tasks)) report.runs.push(r);

const deep = await runViewport(browser, VPS[2], { deep: true });
report.deepLink = deep;
report.runs.push(deep);

// 3) 游客点击最终 CTA 是否触发创建请求（服务端日志为准）
const logStart = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').length : 0;
const guest = await runViewport(browser, VPS[2], { scenario: 'ok' });
report.guest = guest;
report.runs.push(guest);
const logLines = fs.existsSync(LOG) ? fs.readFileSync(LOG, 'utf8').split('\n').slice(Math.max(0, logStart - 1)).filter(Boolean).map(l => { try { return JSON.parse(l); } catch { return null; } }).filter(Boolean) : [];
report.guestCreateCalls = logLines.filter(l => l.kind === 'api' && /customRole(Create|Regenerate)/.test(l.path));
report.guestApiAll = logLines.filter(l => l.kind === 'api');

await browser.close();
report.finishedAt = new Date().toISOString();

const lines = [];
lines.push(`# 浏览器验收 ${TAG}`, '', `- base: ${BASE}`, `- chrome: ${CHROME}`, `- 时间: ${report.startedAt}`, '');
for (const r of report.runs) {
  lines.push(`## ${r.viewport}${r.deep ? ' / 深链' : ''}`, '', '| # | 标题 | 横向溢出 | docScrollW | 破图 | 可见步骤/总步骤 | CTA |', '|---|---|---|---|---|---|---|');
  for (const s of r.screens) {
    const cta = s.btns.filter(b => /next|continue|bring|create|sign|finish|done/i.test(b.text)).map(b => `${b.text}${b.disabled ? '(disabled)' : ''}@${b.rect.join(',')}`).join('; ') || '-';
    lines.push(`| ${s.index} | ${s.title || '(无标题)'} | ${s.horizontalOverflow ? 'YES' : 'no'} | ${s.docScrollW} | ${s.broken.length} | ${s.mount.visibleSteps}/${s.mount.stepNodes} | ${cta} |`);
  }
  if (r.errs.length) lines.push('', '错误: ' + [...new Set(r.errs)].slice(0, 5).join(' | '));
  lines.push('');
}
lines.push('## 深链', '', '```json', JSON.stringify(report.deepLink?.screens?.map(s => ({ i: s.index, title: s.title, body: s.titles, overflow: s.horizontalOverflow, broken: s.broken.length })), null, 1), '```', '');
lines.push('## 游客创建请求计数', '', `customRoleCreate/Regenerate 调用: ${report.guestCreateCalls.length}；全部 API 调用: ${report.guestApiAll.length}`, '');
fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 1));
fs.writeFileSync(path.join(OUT, 'report.md'), lines.join('\n'));
console.log(`OUT=${OUT}`);
console.log(lines.join('\n'));
