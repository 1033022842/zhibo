import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')
const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const BASE = 'http://127.0.0.1:4180/ai/createCharacter.html'
const SHOTS = 'D:/ever/douyin/douyin/tests/create-character/frontend/shots'
const browser = await chromium.launch({ executablePath: EXE, headless: true })
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
const page = await ctx.newPage()
const errs = []
page.on('pageerror', e => errs.push(e.message))
await page.route('**/api/**', r => r.fulfill({ status: 200, contentType: 'application/json', body: '{"code":"00000","data":{}}' }))
await page.goto(BASE, { waitUntil: 'networkidle' })
await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')

const log = []
async function step(n, label) {
  const s = await page.evaluate(() => window.CC8.getState())
  const cta = await page.locator('#cc8Cta').innerText()
  log.push({ wanted: n, screen: s.screen, cta, title: await page.locator('.cc8-step h2, .cc8-hero').first().innerText() })
  await page.screenshot({ path: `${SHOTS}/flow-${n}.png` })
  if (s.screen !== n) throw new Error('screen ' + s.screen + ' != ' + n)
}

await step(1)
await page.click('[data-act="style"][data-style="Anime"]')
await page.click('#cc8Cta')
await step(2)
await page.click('[data-act="pick"][data-group="ethnicity"][data-value="middle-eastern"]')
await page.click('#cc8Cta')
await step(3)
await page.click('[data-act="pick"][data-group="body"][data-value="curvy"]')
await page.click('[data-act="pick"][data-group="breast"][data-value="big"]')
await page.click('[data-act="pick"][data-group="butt"][data-value="athletic"]')
await page.click('#cc8Cta')
await step(4)
await page.click('[data-act="pick"][data-group="color"][data-value="blue"]')
await page.click('[data-act="pick"][data-group="hairstyle"][data-value="bob"]')
await page.click('#cc8Cta')
await step(5)
await page.fill('#cc8Name', 'Yara')
await page.click('[data-act="pick"][data-group="ageRange"][data-value="25-34"]')
await page.click('#cc8Cta')
await step(6)
for (const [field, value] of [['relationship', 'Girlfriend'], ['occupation', 'Artist'], ['personality', 'Playful']]) {
  await page.click(`[data-act="open-picker"][data-field="${field}"]`)
  await page.click(`[data-act="modal-pick"][data-value="${value}"]`)
  await page.click('[data-act="modal-save"]')
}
await page.click('#cc8Cta')
await step(7)
await page.click('[data-act="toggle-pref"][data-value="Cuddling"]')
await page.click('#cc8Cta')
await step(8)
const st = await page.evaluate(() => window.CC8.getState())
const payload = await page.evaluate(() => window.CC8.payload())
const checks = {
  genderTitle: (await page.locator('.cc8-top-title').innerText()).includes('girlfriend'),
  ethnicity: st.draft.ethnicity === 'middle-eastern',
  body: st.draft.body === 'curvy' && st.draft.breast === 'big' && st.draft.butt === 'athletic',
  hair: st.draft.color === 'blue' && st.draft.hairstyle === 'bob',
  name: st.draft.name === 'Yara' && st.draft.ageRange === '25-34',
  personality: st.draft.relationship === 'Girlfriend' && st.draft.occupation === 'Artist' && st.draft.personality === 'Playful',
  prefs: st.draft.preferences.includes('Cuddling'),
  payload: payload.gender === 'female' && payload.ethnicity === 'Middle Eastern' && payload.butt_size === 'Athletic' && payload.age_range === '25-34' && payload.appearance_description === undefined,
  cards: await page.locator('.cc8-card').count() >= 8,
  edits: await page.locator('[data-act="edit"]').count() >= 8,
  noOverflow: await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
  noErrors: errs.length === 0
}
console.log(JSON.stringify({ log, checks, payload, missing: await page.evaluate(() => window.CC8.missing()) }, null, 1))
const failed = Object.entries(checks).filter(([, v]) => !v).map(([k]) => k)
console.log(failed.length ? 'FLOW FAIL ' + failed.join(',') : 'FLOW PASS 8/8')
await browser.close()
if (failed.length) process.exitCode = 1
