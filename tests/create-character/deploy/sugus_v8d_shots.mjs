import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const BASE = 'http://172.81.98.55/ai/createCharacter.html'
const OUT = 'D:/ever/douyin/douyin/tests/create-character/qa/evidence/online/sugus-v8d'
fs.mkdirSync(OUT, { recursive: true })

const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })
const page = await ctx.newPage()
const errs = []
page.on('pageerror', e => errs.push('pageerror: ' + e.message.slice(0, 120)))
page.on('response', r => { if (r.status() >= 400 && !/site\/visit/.test(r.url())) errs.push(r.status() + ' ' + r.url().slice(-50)) })

const q = 'gender=female&style=Realistic&ethnicity=latina&p=curvy&b=average&bu=athletic&ha=bob&c=brunette&a=30&name=Ava'
const visit = async (step, file) => {
  await page.goto(`${BASE}?step=${step}&${q}`, { waitUntil: 'domcontentloaded', timeout: 60000 })
  await page.waitForTimeout(1200)
  await page.waitForFunction(() => {
    const imgs = [...document.querySelectorAll('#cc8Root img')]
    return imgs.length > 0 && imgs.every(i => i.complete && i.naturalWidth > 0)
  }, { timeout: 25000 }).catch(() => {})
  await page.waitForTimeout(400)
  await page.screenshot({ path: `${OUT}/${file}` })
}
const report = {}

await visit(1, 's1.png')
report.s1 = await page.evaluate(() => {
  const btns = [...document.querySelectorAll('#cc8Root .cc8-entry-actions button')]
  const v = document.querySelector('#cc8Root video')
  return {
    btns: btns.map(b => ({ t: b.textContent.trim(), w: Math.round(b.getBoundingClientRect().width) })),
    videoReady: !!v && v.classList.contains('is-ready'),
    videoT: v ? +v.currentTime.toFixed(2) : null,
  }
})

await visit(3, 's3.png')
report.s3 = await page.evaluate(() => {
  const imgs = [...document.querySelectorAll('#cc8Root .cc8-grid5 img')]
  return { grids: document.querySelectorAll('#cc8Root .cc8-grid5').length, imgs: imgs.length, empty: imgs.filter(i => !i.currentSrc).length, belowFold: document.querySelector('#cc8Root .cc8-grid5:last-of-type') ? 0 : -1 }
})

await visit(4, 's4.png')
report.s4 = await page.evaluate(() => {
  const sw = [...document.querySelectorAll('#cc8Root .cc8-swatch')]
  const box = document.querySelector('#cc8Root .cc8-swatches')
  return { swatches: sw.length, onW: Math.round((sw.find(s => s.getAttribute('aria-pressed') === 'true') || { getBoundingClientRect: () => ({ width: 0 }) }).getBoundingClientRect().width), stripScroll: box ? box.scrollWidth > box.clientWidth : null, hairImgs: document.querySelectorAll('#cc8Root .cc8-hair-grid img').length }
})

await visit(6, 's7.png')
report.s7 = await page.evaluate(() => ({
  groups: [...document.querySelectorAll('#cc8Root .cc8-kink-group span')].map(s => s.textContent.trim()),
  heading: (document.querySelector('#cc8Root .cc8-h2') || {}).textContent,
}))

await visit(7, 's8.png')
report.s8 = await page.evaluate(() => ({ screen: (document.querySelector('#cc8Root [data-screen]') || {}).getAttribute?.('data-screen') }))
await page.click('#cc8Root [data-act="cta"]').catch(() => {})
await page.waitForTimeout(1800)
await page.screenshot({ path: `${OUT}/s8-gen.png` })
report.gen = await page.evaluate(() => {
  const card = document.querySelector('#cc8Root .cc8-gen-card')
  const r = card ? card.getBoundingClientRect() : null
  return { cardTop: r ? Math.round(r.top) : null, cardBottom: r ? Math.round(r.bottom) : null, vh: window.innerHeight, blankBelow: r ? Math.round(window.innerHeight - r.bottom) : null }
})

report.errs = errs.slice(0, 4)
await browser.close()
console.log(JSON.stringify(report, null, 1))
