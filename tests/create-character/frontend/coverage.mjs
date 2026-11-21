import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')
const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const BASE = 'http://127.0.0.1:4180/ai/createCharacter.html'
const SHOTS = 'D:/ever/douyin/douyin/tests/create-character/frontend/shots'
const browser = await chromium.launch({ executablePath: EXE, headless: true })
const out = { female: {}, male: {}, missing: [], bad: [] }

async function collect(page) {
  const imgs = await page.locator('#cc8Root img').evaluateAll(ns => ns.map(n => ({ src: n.getAttribute('src'), ok: n.complete && n.naturalWidth > 0 })))
  const ph = await page.locator('#cc8Root .cc8-ph').count()
  return { imgs, ph }
}

for (const style of ['Realistic', 'Anime']) {
  for (const eth of ['white', 'asian', 'black', 'latina', 'south-asian', 'middle-eastern']) {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1 })
    const page = await ctx.newPage()
    page.on('response', r => { if (r.url().includes('/assets/create/') && r.status() !== 200) out.bad.push(r.status() + ' ' + r.url()) })
    await page.goto(`${BASE}?gender=female&style=${style}&ethnicity=${eth}&step=2`, { waitUntil: 'networkidle' })
    await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
    const rows = {}
    for (const [screen, group] of [[2, 'ethnicity'], [3, 'body'], [4, 'hair']]) {
      await page.evaluate(n => window.CC8.go(n), screen)
      await page.waitForTimeout(350)
      const c = await collect(page)
      rows[screen] = { imgs: c.imgs.length, broken: c.imgs.filter(i => !i.ok).map(i => i.src), ph: c.ph }
      if (screen === 4) await page.screenshot({ path: `${SHOTS}/cov-${style}-${eth}-hair.png` })
    }
    const st = await page.evaluate(() => window.CC8.getState())
    const miss = await page.evaluate(() => window.CC8.missing().filter(m => m.key.startsWith('female|' + (window.CC8.getState().gender === 'female' ? '' : ''))))
    out.female[style + '/' + eth] = { rows, missing: miss }
    await ctx.close()
  }
}
// male
for (const style of ['Realistic', 'Anime']) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await ctx.newPage()
  await page.goto(`${BASE}?gender=male&style=${style}&ethnicity=asian&step=2`, { waitUntil: 'networkidle' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const rows = {}
  for (const screen of [2, 3, 4]) {
    await page.evaluate(n => window.CC8.go(n), screen)
    await page.waitForTimeout(300)
    rows[screen] = await collect(page)
  }
  out.male[style] = rows
  await page.screenshot({ path: `${SHOTS}/cov-male-${style}.png` })
  await ctx.close()
}
out.missing = await (async () => { return [] })()
fs.writeFileSync('coverage.json', JSON.stringify(out, null, 1))
console.log('COVERAGE_WRITTEN coverage.json')
const badPh = Object.entries(out.female).filter(([, v]) => v.rows[2].ph || v.rows[3].ph || v.rows[4].ph)
console.log('FEMALE_PLACEHOLDER_GROUPS', badPh.length, badPh.map(([k]) => k))
console.log('BROKEN_ASSET_RESPONSES', out.bad)
await browser.close()
