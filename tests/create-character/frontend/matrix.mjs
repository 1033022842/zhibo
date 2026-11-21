import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const BASE = 'http://127.0.0.1:4180/ai/createCharacter.html'
const SHOTS = 'D:/ever/douyin/douyin/tests/create-character/frontend/shots'
fs.mkdirSync(SHOTS, { recursive: true })

const results = []
function check(name, ok, detail = '') { results.push({ name, ok }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? ' — ' + detail : '')) }
const json = (route, obj) => route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(obj) })
const browser = await chromium.launch({ executablePath: EXE, headless: true })

/* ---------- summary card editing + focus ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await page.goto(BASE + '?step=7&gender=female&style=Realistic&ethnicity=white&p=slim&b=average&bu=big&ha=bun&hac=black&a=30&name=Ava&rel=Girlfriend&occ=Doctor&per=Playful', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')

  await page.click('[data-act="edit"][data-field="bio"]')
  check('bio opens editor', await page.locator('#cc8Modal').count() === 1)
  await page.fill('#cc8ModalName', 'Mia')
  await page.click('[data-act="modal-save"]')
  let st = await page.evaluate(() => window.CC8.getState())
  check('bio save updates name', st.draft.name === 'Mia', st.draft.name)
  check('bio save returns focus to card', await page.evaluate(() => document.activeElement?.getAttribute('data-field') === 'bio'))
  check('bio summary text updated', (await page.locator('.cc8-card').first().innerText()).includes('Mia'))

  await page.click('[data-act="edit"][data-field="bio"]')
  await page.fill('#cc8ModalName', 'Discarded')
  await page.click('[data-act="modal-cancel"]')
  st = await page.evaluate(() => window.CC8.getState())
  check('bio cancel keeps original', st.draft.name === 'Mia', st.draft.name)
  check('bio cancel returns focus', await page.evaluate(() => document.activeElement?.getAttribute('data-field') === 'bio'))

  await page.click('[data-act="edit"][data-field="relationship"]')
  check('relationship picker lists choices', await page.locator('.cc8-lrow').count() > 5, String(await page.locator('.cc8-lrow').count()))
  await page.click('[data-act="modal-pick"][data-value="Wife"]')
  await page.click('[data-act="modal-save"]')
  st = await page.evaluate(() => window.CC8.getState())
  check('relationship editable and saved', st.draft.relationship === 'Wife', st.draft.relationship)
  check('other fields survive edit', st.draft.name === 'Mia' && st.draft.occupation === 'Doctor' && st.draft.personality === 'Playful', JSON.stringify({ n: st.draft.name, o: st.draft.occupation, p: st.draft.personality }))
  await page.screenshot({ path: SHOTS + '/summary-edited.png' })
  check('summary no page errors', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- free prompt: counters, surprise, presets, continue ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await page.goto(BASE, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.click('[data-act="style"][data-style="Anime"]')
  await page.click('[data-act="prompt"]')
  check('free prompt opens', await page.locator('#cc8Prompt').count() === 1)
  check('two description textareas with 1000 limit', await page.locator('#cc8Prompt textarea[maxlength="1000"]').count() === 2)
  check('counters start at 0/1000', (await page.locator('[data-count-for="appearance_description"]').innerText()) === '0/1000')
  const long = 'A'.repeat(1000)
  await page.fill('#cc8Appearance', long)
  check('appearance counter updates to 1000/1000', (await page.locator('[data-count-for="appearance_description"]').innerText()) === '1000/1000')
  await page.fill('#cc8Personality', 'Soft-spoken but teasing.')
  await page.screenshot({ path: SHOTS + '/prompt.png' })
  await page.click('[data-act="prompt-presets"]')
  check('use presets returns to entry', await page.locator('#cc8Root[data-cc8-screen="1"]').count() === 1)
  await page.click('[data-act="prompt"]')
  check('presets keep typed description', (await page.inputValue('#cc8Appearance')).length === 1000)
  await page.click('[data-act="prompt-surprise"]')
  const surpriseName = await page.inputValue('#cc8PromptName')
  check('surprise fills a name', surpriseName.length > 0, surpriseName)
  await page.click('[data-act="prompt-continue"]')
  const st = await page.evaluate(() => window.CC8.getState())
  const payload = await page.evaluate(() => window.CC8.payload())
  check('continue enters summary with prompt mode', st.screen === 8 && st.draft.promptMode === true, 'screen=' + st.screen)
  check('prompt payload keeps descriptions in dedicated fields', payload.appearance_description.length === 1000 && payload.personality_description.length > 0 && !payload.race, JSON.stringify({ a: payload.appearance_description.length, p: payload.personality_description.length, race: payload.race }))
  check('prompt summary hides unset appearance cards', await page.locator('.cc8-summary .cc8-card-bio').count() === 1 && await page.locator('.cc8-summary .cc8-card-hair, .cc8-summary .cc8-card-eth, .cc8-summary .cc8-appearance-row').count() === 0)
  check('prompt summary shows no Art pending', (await page.locator('.cc8-summary .cc8-ph').count()) === 0)
  await page.screenshot({ path: SHOTS + '/prompt-summary.png' })
  check('prompt no page errors', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- gender drafts stay independent ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await page.goto(BASE, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.click('[data-act="gender"][data-gender="female"]')
  await page.click('[data-act="style"][data-style="Anime"]')
  await page.evaluate(() => { const d = window.CC8.getState().draft; })
  await page.click('[data-act="gender"][data-gender="male"]')
  let st = await page.evaluate(() => window.CC8.getState())
  check('male starts with its own empty draft', st.draft.name === '' && st.draft.style === '' && st.draft.ethnicity === '', JSON.stringify({ n: st.draft.name, s: st.draft.style, e: st.draft.ethnicity }))
  await page.click('[data-act="gender"][data-gender="female"]')
  st = await page.evaluate(() => window.CC8.getState())
  check('female draft retained after switching back', st.draft.style === 'Anime', st.draft.style)
  const saved = await page.evaluate(() => JSON.parse(localStorage.getItem('cc8_drafts_v1')))
  check('both drafts persisted separately', saved.drafts.female.style === 'Anime' && saved.drafts.male.style === '' && saved.activeGender === 'female', JSON.stringify(saved.drafts))
  await ctx.close()
}

/* ---------- legacy draft migration ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await ctx.newPage()
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await ctx.addInitScript(() => {
    localStorage.setItem('cc_profile_v2', JSON.stringify({ category: 'Anime', race: 'Black / Afro', body: 'Curvy', breast: 'Large', hairstyle: 'Bun', hair: 'Blue', name: 'Nia', age: 29, relation: 'Girlfriend', profession: 'Model', personality: 'Playful', kinks: ['Bondage', 'Massage'] }))
    localStorage.setItem('cc_step_v2', '2')
  })
  await page.goto(BASE, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const st = await page.evaluate(() => window.CC8.getState())
  check('legacy migration maps race→ethnicity', st.draft.ethnicity === 'black', st.draft.ethnicity)
  check('legacy migration maps age→range and body', st.draft.ageRange === '25-34' && st.draft.body === 'curvy', JSON.stringify({ a: st.draft.ageRange, b: st.draft.body }))
  check('legacy step 2 → v8 screen 4', st.screen === 4, 'screen=' + st.screen)
  const persisted = await page.evaluate(() => ({ data: JSON.parse(localStorage.getItem('cc8_drafts_v1')), flag: localStorage.getItem('cc8_migrated_v2') }))
  check('migration persisted before marker', persisted.data.drafts.female.name === 'Nia' && persisted.flag === '1')
  await page.screenshot({ path: SHOTS + '/migrated-s4.png' })
  await ctx.close()
}

/* ---------- auth failure on create ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  await ctx.addInitScript(() => localStorage.setItem('live_access_token', 'expired'))
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  await page.route('**/api/**', r => {
    const u = new URL(r.request().url())
    if (u.pathname.endsWith('/customRoleCreate')) return json(r, { code: 'A0101', msg: 'Please sign in' })
    return json(r, { code: '00000', data: {} })
  })
  await page.goto(BASE + '?step=7&gender=female&style=Anime&ethnicity=asian&name=Ava', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.evaluate(() => { window.__loginReq = null; window.FyLogin = { open: q => { window.__loginReq = q }, load: () => {} } })
  await page.click('#cc8Cta')
  await page.waitForTimeout(1200)
  check('auth error clears token', await page.evaluate(() => !localStorage.getItem('live_access_token')))
  check('auth error opens sign-in', await page.evaluate(() => window.__loginReq?.mode === 'signin'), JSON.stringify(await page.evaluate(() => window.__loginReq)))
  check('auth flow no page errors', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- completed status with untrusted image host ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  await ctx.addInitScript(() => localStorage.setItem('live_access_token', 'ok'))
  const page = await ctx.newPage()
  await page.route('**/api/**', r => {
    const u = new URL(r.request().url())
    if (u.pathname.endsWith('/customRoleCreate')) return json(r, { code: '00000', data: { id: 9, image_task_id: 5, prices: { regen: 1, save: 10 }, balance: 50 } })
    if (u.pathname.endsWith('/customRoleImageStatus')) return json(r, { code: '00000', data: { persona_id: 9, status: 'completed', image_url: 'https://evil.example/x.png' } })
    return json(r, { code: '00000', data: {} })
  })
  await page.goto(BASE + '?step=7&gender=female&style=Anime&ethnicity=asian&name=Ava', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.click('#cc8Cta')
  await page.waitForSelector('#cc8Gen[data-gen-phase="failed"]', { timeout: 15000 })
  check('untrusted image host rejected', (await page.locator('#cc8Gen').innerText()).includes('did not pass the media check'))
  const genImgs = await page.locator('#cc8GenCard img').evaluateAll(ns => ns.map(n => n.getAttribute('src')))
  check('no untrusted image rendered', genImgs.length === 0 || genImgs.every(s => s.startsWith('/assets/') || s.startsWith('./assets/')), JSON.stringify(genImgs))
  await ctx.close()
}

/* ---------- 8 screenshots at 390 ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await page.goto(BASE + '?gender=female&style=Realistic&ethnicity=latina&p=curvy&b=average&bu=big&ha=bob&hac=black&name=Ava&step=7', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const titles = []
  for (let n = 1; n <= 8; n++) {
    await page.evaluate(n => window.CC8.go(n), n)
    await page.waitForTimeout(250)
    const title = await page.locator('.cc8-step h2, .cc8-step .cc8-hero').first().innerText().catch(() => '')
    const top = await page.locator('.cc8-step h2, .cc8-step .cc8-hero').first().boundingBox().catch(() => null)
    titles.push({ n, title: title.replace(/\n/g, ' '), top: top && Math.round(top.y) })
    await page.screenshot({ path: SHOTS + `/screen-${n}.png` })
  }
  check('all 8 screens render a heading', titles.every(t => t.title.length > 0), JSON.stringify(titles))
  check('screen heading y consistent 78–94', titles.every(t => t.top >= 70 && t.top <= 100), JSON.stringify(titles.map(t => t.top)))
  const over = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)
  check('no horizontal overflow at 390', !over)
  await ctx.close()
}

/* ---------- 430 + desktop layout ---------- */
for (const vp of [{ w: 430, h: 932 }, { w: 1280, h: 900 }]) {
  const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, deviceScaleFactor: 1 })
  const page = await ctx.newPage()
  await page.route('**/api/**', r => json(r, { code: '00000', data: {} }))
  await page.goto(BASE + '?step=7&gender=female&style=Anime&ethnicity=black&p=curvy&b=big&bu=huge&ha=bob&hac=blue&name=Ava', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const over = await page.evaluate(() => document.documentElement.scrollWidth > innerWidth)
  check(`${vp.w} no horizontal overflow`, !over)
  await page.screenshot({ path: SHOTS + `/summary-${vp.w}.png`, fullPage: false })
  await ctx.close()
}

await browser.close()
const failed = results.filter(r => !r.ok)
console.log('\nSUMMARY ' + (results.length - failed.length) + '/' + results.length + ' passed')
if (failed.length) { console.log('FAILED: ' + failed.map(f => f.name).join(' | ')); process.exitCode = 1 }
