import { createRequire } from 'node:module'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')
import fs from 'node:fs'

const SITES = [
  ['heygirl', 'https://heygirl.live/ai/createCharacter.html'],
  ['sugus', 'http://172.81.98.55/ai/createCharacter.html'],
  ['kelymi', 'https://kelymi.autos/ai/createCharacter.html'],
  ['kiss', 'https://kisss.ai/ai/createCharacter.html'],
]
const OUT = 'D:/ever/douyin/douyin/tests/create-character/qa/evidence/online'
fs.mkdirSync(OUT, { recursive: true })

const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})

const waitImgs = page => page.waitForFunction(
  () => [...document.querySelectorAll('.cc8-entry-card img')].length === 2
    && [...document.querySelectorAll('.cc8-entry-card img')].every(i => i.complete && i.naturalWidth > 0),
  { timeout: 20000 },
)
const snap = page => page.evaluate(() => [...document.querySelectorAll('.cc8-entry-card img')]
  .map(i => ({ file: (i.currentSrc || i.src).split('/').pop(), w: i.naturalWidth })))

const report = []
for (const [name, url] of SITES) {
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, ignoreHTTPSErrors: true })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push('pageerror: ' + e.message))
  page.on('response', r => { if (r.status() >= 400 && !/site\/visit/.test(r.url())) errs.push(r.status() + ' ' + r.url().slice(-60)) })
  try {
    await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 60000 })
    await waitImgs(page)
    const female = await snap(page)
    await page.screenshot({ path: `${OUT}/${name}_female.png` })

    await page.click('[data-act="gender"][data-gender="male"]')
    await waitImgs(page)
    const male = await snap(page)
    await page.screenshot({ path: `${OUT}/${name}_male.png` })

    await page.click('#cc8Root [data-act="style"][data-style="Realistic"]')
    await page.waitForSelector('#cc8Root [data-act="cta"]:not([disabled])', { timeout: 10000 })
    const cta = await page.$eval('#cc8Root [data-act="cta"]', el => ({ label: el.textContent.trim(), disabled: el.disabled }))
    await page.click('#cc8Root [data-act="cta"]')
    await page.waitForTimeout(1800)
    const step2 = await page.evaluate(() => {
      const screen = (document.querySelector('#cc8Root [data-screen]') || {}).getAttribute?.('data-screen')
      const bar = document.querySelector('#cc8Root .cc8-step')
      const filled = bar ? bar.querySelectorAll('.is-on, .cc8-on').length : -1
      return { screen, options: document.querySelectorAll('#cc8Root [data-act="pick"], #cc8Root .cc8-opt').length, filled }
    })
    await page.screenshot({ path: `${OUT}/${name}_step2.png` })

    report.push({
      site: name, female, male, cta, step2,
      ok: female.length === 2 && female.every(x => x.w > 0) && male.every(x => x.w > 0)
        && cta.disabled === false && String(step2.screen) === '2',
      errs: errs.slice(0, 3),
    })
  } catch (e) {
    report.push({ site: name, fatal: String(e).slice(0, 160), errs: errs.slice(0, 3) })
  }
  await ctx.close()
}
await browser.close()
console.log(JSON.stringify(report, null, 1))
const bad = report.filter(r => r.fatal || !r.ok)
console.log('ONLINE RESULT:', bad.length ? 'FAIL ' + bad.map(b => b.site).join(',') : 'ALL PASS')
