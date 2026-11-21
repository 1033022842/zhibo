// 验收者独立复核：按真实交互（先选风格→Start）走 8 屏，量溢出/CTA/破图/创建请求。
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const BASE = process.env.BASE || 'http://127.0.0.1:8017/ai/createCharacter.html'
const MODE = process.env.MODE || 'female'   // female | male | prompt
const OUT = path.resolve('tests/create-character/qa/evidence/lead-check')
fs.mkdirSync(OUT, { recursive: true })
const CHROME = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'

const browser = await chromium.launch({ executablePath: CHROME, headless: true })
const rows = []
let fail = 0

for (const vp of [
  { name: '320', width: 320, height: 844 },
  { name: '390', width: 390, height: 844 },
  { name: '1440', width: 1440, height: 900 },
]) {
  const ctx = await browser.newContext({ viewport: { width: vp.width, height: vp.height }, deviceScaleFactor: 1 })
  const page = await ctx.newPage()
  const calls = []
  const errs = []
  page.on('pageerror', e => errs.push(String(e).slice(0, 160)))
  page.on('request', r => { if (/customRole(Create|Regenerate|Save|ImageStatus)/.test(r.url())) calls.push(r.url()) })
  await page.route('**/*', route => {
    const u = new URL(route.request().url())
    if (u.hostname === '127.0.0.1' || u.hostname === 'localhost') return route.continue()
    return route.abort()
  })
  await page.goto(BASE + (MODE === 'male' ? '?g=male' : ''), { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]', { timeout: 15000 })
  if (MODE === 'prompt') {
    await page.click('#cc8Root [data-act="prompt"]')
    await page.waitForSelector('#cc8Prompt', { timeout: 8000 })
    await page.fill('#cc8PromptName', 'Ava')
    await page.click('#cc8Root [data-act="prompt-surprise"]')
    await page.waitForTimeout(200)
    await page.click('#cc8Root [data-act="prompt-continue"]')
    await page.waitForTimeout(500)
    const m = await page.evaluate(() => ({
      screen: Number(document.getElementById('cc8Root').getAttribute('data-cc8-screen') || 0),
      placeholderCards: document.querySelectorAll('#cc8Root .cc8-ph').length,
      title: (document.querySelector('.cc8-sub') || {}).textContent || '',
      broken: Array.from(document.images).filter(im => im.complete && im.naturalWidth === 0).length,
      overflow: document.documentElement.scrollWidth > innerWidth,
    }))
    await page.screenshot({ path: path.join(OUT, `prompt_${vp.name}.png`) })
    const ok = m.screen === 8 && m.placeholderCards === 0 && m.broken === 0 && !m.overflow
    if (!ok) { fail++; console.log('FAIL prompt', vp.name, JSON.stringify(m)) }
    rows.push({ viewport: vp.name, mode: 'prompt', ...m })
    await ctx.close()
    continue
  }

  const snap = async (i) => {
    const m = await page.evaluate(() => {
      const root = document.getElementById('cc8Root')
      const cta = document.querySelector('#cc8Root [data-act="cta"]')
      const r = cta ? cta.getBoundingClientRect() : null
      const cs = cta ? getComputedStyle(cta) : null
      return {
        screen: Number(root.getAttribute('data-cc8-screen') || 0),
        title: (document.querySelector('.cc8-top-title') || {}).textContent || '',
        overflow: document.documentElement.scrollWidth > innerWidth,
        scrollW: document.documentElement.scrollWidth,
        cta: r ? { text: (cta.textContent || '').trim(), w: Math.round(r.width), h: Math.round(r.height), bottom: Math.round(r.bottom), visible: r.bottom <= innerHeight + 1 && r.top >= -1 && cs.display !== 'none' } : null,
        broken: Array.from(document.images).filter(im => im.complete && im.naturalWidth === 0).length,
        screens: document.querySelectorAll('#cc8Root .cc8-screen').length,
      }
    })
    m.i = i
    m.viewport = vp.name
    await page.screenshot({ path: path.join(OUT, `${vp.name}_s${i}.png`) })
    rows.push(m)
    const ok = !m.overflow && m.broken === 0 && m.screens <= 1 && (!m.cta || m.cta.visible)
    if (!ok) { fail++; console.log('FAIL', vp.name, i, JSON.stringify(m)) }
    return m
  }

  await snap(0)
  // 首屏选 Realistic，再点 Start
  await page.click('#cc8Root [data-act="style"][data-style="Realistic"]')
  await page.waitForTimeout(200)
  for (let i = 1; i <= 8; i++) {
    let cta = await page.$('#cc8Root [data-act="cta"]:not([disabled])')
    if (!cta) {
      // 未满足必选项：按本屏规则补选（族裔 / 名字）
      const pick = await page.$('#cc8Root [data-act="pick"]')
      if (pick) { await pick.click(); await page.waitForTimeout(250) }
      cta = await page.$('#cc8Root [data-act="cta"]:not([disabled])')
      if (!cta) {
        const name = await page.$('#cc8Root [data-input="name"]')
        if (name) { await name.fill('Ava'); await page.waitForTimeout(150) }
      }
      cta = await page.$('#cc8Root [data-act="cta"]:not([disabled])')
    }
    if (!cta) { console.log('NO CTA at', vp.name, i); fail++; break }
    await cta.click()
    await page.waitForTimeout(320)
    const m = await snap(i)
    if (m.screen !== i + 1 && m.screen !== 8) {
      // 第 8 屏 CTA 进生成层，screen 仍是 8
      console.log('SCREEN MISMATCH', vp.name, i, m.screen)
    }
    if (i === 8) break
  }
  if (calls.length) { fail++; console.log('CREATION CALLS (guest)', vp.name, calls) }
  if (errs.length) console.log('PAGE ERRORS', vp.name, errs.slice(0, 3))
  await ctx.close()
}

await browser.close()
fs.writeFileSync(path.join(OUT, 'result.json'), JSON.stringify(rows, null, 2))
console.log(`\nCHECKPOINTS=${rows.length} FAIL=${fail}`)
