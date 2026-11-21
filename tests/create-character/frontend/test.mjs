import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const BASE = 'http://127.0.0.1:4180/ai/createCharacter.html'
const SHOTS = 'D:/ever/douyin/douyin/tests/create-character/frontend/shots'
fs.mkdirSync(SHOTS, { recursive: true })

const results = []
function check(name, ok, detail = '') {
  results.push({ name, ok, detail })
  console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? ' — ' + detail : ''))
}
function json(route, obj) { return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(obj) }) }

const browser = await chromium.launch({ executablePath: EXE, headless: true })

/* ---------- guest: deep link normalisation, 0 creation calls, gate ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  const errs = []
  const creation = []
  page.on('pageerror', e => errs.push(e.message))
  page.on('request', r => { if (/customRole(Create|Regenerate|Save|ImageStatus)/.test(r.url())) creation.push(r.url()) })
  await page.route('**/api/**', route => json(route, { code: '00000', data: {} }))
  await page.goto(BASE + '?step=7&gender=female&style=Anime&ethnicity=latina&p=curvy&b=average&bu=athletic&ha=bob&a=30&name=Ava', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const st = await page.evaluate(() => window.CC8.getState())
  check('deep link lands on summary', st.screen === 8, 'screen=' + st.screen)
  check('bu=athletic is a manifest butt option', st.draft.butt === 'athletic', 'butt=' + JSON.stringify(st.draft.butt))
  check('age 30 → 25-34', st.draft.ageRange === '25-34', st.draft.ageRange)
  check('p=curvy applied', st.draft.body === 'curvy', st.draft.body)
  check('b=average applied', st.draft.breast === 'average', st.draft.breast)
  check('ha=bob applied / step 7 → summary', st.draft.hairstyle === 'bob' && st.screen === 8, st.draft.hairstyle)

  await page.evaluate(() => {
    window.__loginReq = null
    window.FyLogin = { open: req => { window.__loginReq = req }, load: () => {} }
  })
  await page.click('#cc8Cta')
  await page.waitForSelector('#cc8Gen[data-gen-phase="gate"]', { timeout: 15000 })
  check('guest preview reaches gate', true)
  check('guest makes zero creation requests', creation.length === 0, creation.join(','))
  check('gate offers signup + login', await page.locator('#cc8Signup').count() === 1 && await page.locator('#cc8Signin').count() === 1)
  await page.screenshot({ path: SHOTS + '/s8-guest-gate.png' })
  await page.click('#cc8Signup')
  const req = await page.evaluate(() => window.__loginReq)
  check('FyLogin mode=signup with same-origin redirect', !!req && req.mode === 'signup' && req.redirect.startsWith('/ai/createCharacter.html'), JSON.stringify(req))
  check('no page errors (guest)', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- 320 header + placeholder duplication ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 320, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  await page.route('**/api/**', route => json(route, { code: '00000', data: {} }))
  await page.goto(BASE + '?step=2&gender=female&style=Anime&ethnicity=south-asian', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  const title = await page.evaluate(() => {
    const el = document.querySelector('.cc8-top-title')
    return { sw: el.scrollWidth, cw: el.clientWidth, text: el.textContent }
  })
  check('320 title not clipped', title.sw <= title.cw + 1, JSON.stringify(title))
  const opts = page.locator('.cc8-opt')
  check('320 shows all six ethnicity options', await opts.count() === 6, String(await opts.count()))
  const imgs = await page.locator('.cc8-opt img').evaluateAll(ns => ns.map(n => ({ ok: n.complete && n.naturalWidth > 0, src: n.getAttribute('src') })))
  check('320 ethnicity art all loads', imgs.length === 6 && imgs.every(i => i.ok), JSON.stringify(imgs))
  check('320 no art-pending placeholder for female', await page.locator('.cc8-opt .cc8-ph').count() === 0)
  await page.screenshot({ path: SHOTS + '/s2-320.png' })
  await ctx.close()
}

/* ---------- logged-in: create once, prices, status, save ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  await ctx.addInitScript(() => localStorage.setItem('live_access_token', 'test-token'))
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  const calls = { create: 0, status: 0, save: 0, regen: 0 }
  let createBody = null
  let regenBody = null
  let statusCalls = 0
  await page.route('**/storage/**', route => route.fulfill({ status: 200, contentType: 'image/webp', body: fs.readFileSync('D:/ever/douyin/douyin/ai-girl-malaysia.com/assets/create/entry-realistic.webp') }))
  await page.route('**/api/**', route => {
    const u = new URL(route.request().url())
    if (u.pathname.endsWith('/customRoleCreate')) {
      calls.create++
      createBody = JSON.parse(route.request().postData() || '{}')
      return json(route, { code: '00000', data: { id: 777, image_task_id: 0, instant: true, prices: { regen: 2, save: 5 }, balance: 7 } })
    }
    if (u.pathname.endsWith('/customRoleImageStatus')) {
      calls.status++
      statusCalls++
      if (statusCalls < 2) return json(route, { code: '00000', data: { persona_id: 777, status: 'pending', image_url: '' } })
      return json(route, { code: '00000', data: { persona_id: 777, status: 'completed', image_url: '/storage/persona/ava.webp' } })
    }
    if (u.pathname.endsWith('/customRoleSave')) { calls.save++; return json(route, { code: '00000', data: { balance: 2 } }) }
    if (u.pathname.endsWith('/customRoleRegenerate')) { calls.regen++; regenBody = JSON.parse(route.request().postData() || '{}'); return json(route, { code: '00000', data: { image_task_id: 88, balance: 5 } }) }
    return json(route, { code: '00000', data: {} })
  })
  await page.goto(BASE + '?step=7&gender=female&style=Realistic&ethnicity=white&p=slim&b=average&bu=big&ha=bun&hac=black&a=30&name=Ava', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.click('#cc8Cta')
  await page.click('#cc8Cta').catch(() => {})
  await page.waitForSelector('#cc8Gen[data-gen-phase="done"]', { timeout: 20000 })
  check('logged-in create exactly once on double click', calls.create === 1, 'create=' + calls.create)
  check('payload has gender + canonical breast/butt', createBody && createBody.gender === 'female' && createBody.breast === 'Medium' && createBody.butt_size === 'Big', JSON.stringify(createBody))
  check('payload age_range + ethnicity', createBody && createBody.age_range === '25-34' && createBody.ethnicity === 'White', JSON.stringify({ a: createBody?.age_range, e: createBody?.ethnicity }))
  const img = await page.getAttribute('#cc8GenCard img', 'src')
  check('completed image uses safe local URL', img === '/storage/persona/ava.webp', String(img))
  await page.screenshot({ path: SHOTS + '/s8-done.png' })

  await page.click('[data-act="gen-regen"]')
  check('regen opens confirm with price/balance', await page.locator('#cc8Confirm').count() === 1)
  const cfText = await page.locator('#cc8Confirm').innerText()
  check('confirm shows regen price and balance', /2/.test(cfText) && /7/.test(cfText), cfText.replace(/\n/g, ' | '))
  await page.screenshot({ path: SHOTS + '/s8-confirm.png' })
  await page.click('[data-act="confirm-cancel"]')
  check('cancel sends no regen request', calls.regen === 0, 'regen=' + calls.regen)
  await page.click('[data-act="gen-save"]')
  await page.click('[data-act="confirm-cancel"]')
  check('cancel sends no save request', calls.save === 0, 'save=' + calls.save)
  await page.click('[data-act="gen-regen"]')
  await page.click('[data-act="confirm-do"]')
  await page.waitForTimeout(500)
  check('regen sends only persona_id', calls.regen === 1 && JSON.stringify(regenBody) === '{"persona_id":777}', JSON.stringify(regenBody))
  await page.waitForSelector('#cc8Gen[data-gen-phase="done"]', { timeout: 20000 })
  await page.click('[data-act="gen-save"]')
  await page.click('[data-act="confirm-do"]')
  await page.waitForSelector('#cc8GoChat', { timeout: 8000 })
  check('confirm sends exactly one save', calls.save === 1, 'save=' + calls.save)
  check('chat link present after save', (await page.getAttribute('#cc8GoChat', 'href')).includes('Chat.html?id=my-777'))
  check('no page errors (logged-in)', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- male branch payload ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  await ctx.addInitScript(() => localStorage.setItem('live_access_token', 'test-token'))
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  let body = null
  await page.route('**/api/**', route => {
    const u = new URL(route.request().url())
    if (u.pathname.endsWith('/customRoleCreate')) {
      body = JSON.parse(route.request().postData() || '{}')
      return json(route, { code: '00000', data: { id: 778, image_task_id: 9, instant: false, prices: { regen: 1, save: 10 }, balance: 20 } })
    }
    if (u.pathname.endsWith('/customRoleImageStatus')) return json(route, { code: '00000', data: { persona_id: 778, status: 'failed', msg: 'mock failure' } })
    return json(route, { code: '00000', data: {} })
  })
  await page.goto(BASE + '?gender=male&style=Realistic&step=7&ethnicity=asian&p=muscular&b=average&bu=athletic&ha=fade&name=Kai', { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  check('male header title', (await page.locator('.cc8-top-title').innerText()).includes('boyfriend'), await page.locator('.cc8-top-title').innerText())
  await page.click('#cc8Cta')
  await page.waitForSelector('#cc8Gen[data-gen-phase="failed"]', { timeout: 10000 })
  check('male payload no female-only fields', body && body.gender === 'male' && !('breast' in body) && !('voice' in body) && body.clothing !== 'Bikini', JSON.stringify(body))
  check('male payload omits butt (not in reference)', body && !('butt_size' in body), JSON.stringify(body && { butt: body.butt_size }))
  await page.screenshot({ path: SHOTS + '/s8-male-failed.png' })
  check('no page errors (male)', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- restore verifies ownership before showing cached portrait ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  await ctx.addInitScript(() => localStorage.setItem('live_access_token', 'test-token'))
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  let statusCalls = 0
  let statusMode = 'completed'
  await page.route('**/api/**', route => {
    const u = new URL(route.request().url())
    if (u.pathname.endsWith('/customRoleCreate')) return json(route, { code: '00000', data: { id: 901, image_task_id: 31, instant: false, prices: { regen: 1, save: 10 }, balance: 20 } })
    if (u.pathname.endsWith('/customRoleImageStatus')) {
      statusCalls++
      if (statusMode === 'failed') return json(route, { code: 'A0002', msg: 'Character not found' })
      return json(route, { code: '00000', data: { persona_id: 901, status: 'completed', image_url: '/storage/persona/restored.webp' } })
    }
    return json(route, { code: '00000', data: {} })
  })
  await page.route('**/storage/persona/restored.webp', route => route.fulfill({ status: 200, contentType: 'image/webp', path: 'D:/ever/douyin/douyin/ai-girl-malaysia.com/assets/create/entry-realistic.webp' }))
  const url = BASE + '?gender=female&style=Realistic&step=7&ethnicity=white&p=slim&b=average&bu=big&ha=bun&hac=black&a=30&name=Ava'
  await page.goto(url, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.click('#cc8Cta')
  await page.waitForSelector('#cc8Gen[data-gen-phase="done"]', { timeout: 20000 })
  const saved = await page.evaluate(() => localStorage.getItem('cc8_result_v1'))
  check('result persisted after generation', !!saved && JSON.parse(saved).personaId === 901, String(saved).slice(0, 120))
  statusCalls = 0
  await page.reload({ waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.waitForSelector('#cc8Gen[data-gen-phase="done"]', { timeout: 15000 })
  check('restore verifies via status GET before showing', statusCalls >= 1, 'status=' + statusCalls)
  check('restore shows verified portrait', (await page.getAttribute('#cc8GenCard img', 'src')) === '/storage/persona/restored.webp')
  await page.screenshot({ path: SHOTS + '/s8-restored.png' })

  statusMode = 'failed'
  statusCalls = 0
  await page.reload({ waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.waitForTimeout(800)
  check('unverifiable cached result is cleared', await page.evaluate(() => localStorage.getItem('cc8_result_v1')) === null)
  check('unverifiable cached result closes generation', await page.locator('#cc8Gen[data-open="1"]').count() === 0)
  check('no page errors (restore)', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- v8d layout: entry actions+video, grid5, swatch row, pref groups ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(e.message))
  await page.route('**/api/**', route => json(route, { code: '00000', data: {} }))
  await page.goto(BASE, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')

  const entry = await page.evaluate(() => {
    const box = document.querySelector('#cc8Bottom .cc8-entry-actions')
    const cta = document.getElementById('cc8Cta')
    const ghost = box ? box.querySelector('[data-act="prompt"]') : null
    const rw = b => (b ? Math.round(b.getBoundingClientRect().width * 10) / 10 : -1)
    return {
      has: !!box,
      prompt: box ? box.querySelectorAll('[data-act="prompt"]').length : -1,
      cta: box ? box.querySelectorAll('[data-act="cta"]').length : -1,
      label: cta ? cta.textContent.trim() : '',
      linkbtn: document.querySelectorAll('.cc8-linkbtn').length,
      ctaInActions: !!(box && box.contains(cta)),
      ghostW: rw(ghost),
      ctaW: rw(cta),
    }
  })
  check('screen 1 bottom = .cc8-entry-actions holding one prompt + one cta', entry.has && entry.prompt === 1 && entry.cta === 1 && entry.ctaInActions, JSON.stringify(entry))
  check('screen 1 CTA reads Begin', entry.label === 'Begin', entry.label)
  check('screen 1 no longer renders .cc8-linkbtn', entry.linkbtn === 0, 'linkbtn=' + entry.linkbtn)
  check('screen 1 two buttons have equal width', entry.ghostW > 0 && entry.ghostW === entry.ctaW, `ghost=${entry.ghostW} cta=${entry.ctaW}`)

  await page.waitForFunction(() => {
    const v = document.querySelector('.cc8-entry-card .cc8-entry-motion')
    return !!v && v.classList.contains('is-ready') && v.currentTime > 0 &&
      getComputedStyle(v).opacity === '1'   // 等 .35s 淡入结束，避免采样到过渡中间态
  }, null, { timeout: 20000 })
  const vid = await page.evaluate(() => {
    const v = document.querySelector('.cc8-entry-card .cc8-entry-motion')
    const img = document.querySelector('.cc8-entry-card img')
    return {
      ready: v.classList.contains('is-ready'),
      t: Math.round(v.currentTime * 100) / 100,
      opacity: getComputedStyle(v).opacity,
      src: v.querySelector('source').getAttribute('src'),
      hasImg: !!img,
    }
  })
  check('female entry plays motion over the static image', vid.ready && vid.t > 0 && vid.opacity === '1' && vid.hasImg, JSON.stringify(vid))
  check('entry video points at the female realistic mp4', vid.src === './assets/create/entry-realistic.mp4', vid.src)

  /* 真实路径：首屏必须选风格才有图（manifest key 带 style），随后直接跳第 3 屏 */
  await page.click('[data-act="style"][data-style="Realistic"]')
  await page.evaluate(() => window.CC8.go(3))
  const s3 = await page.evaluate(() => {
    const cards = [...document.querySelectorAll('.cc8-grid5 .cc8-opt')]
    const imgs = cards.map(c => c.querySelector('img')).filter(Boolean)
    const scroll = document.querySelector('.cc8-scroll')
    return {
      grids: document.querySelectorAll('.cc8-grid5').length,
      rows: document.querySelectorAll('.cc8-row').length,
      cards: cards.length,
      imgs: imgs.length,
      emptySrc: imgs.filter(i => !i.getAttribute('src')).length,
      placeholders: document.querySelectorAll('.cc8-grid5 .cc8-ph').length,
      broken: imgs.filter(i => i.complete && i.naturalWidth === 0).length,
      belowFold: cards.filter(c => c.getBoundingClientRect().bottom > innerHeight + 1).length,
      vScroll: scroll.scrollHeight - scroll.clientHeight,
    }
  })
  check('screen 3 renders 3 × .cc8-grid5 with 15 cards', s3.grids === 3 && s3.cards === 15 && s3.rows === 0, JSON.stringify(s3))
  check('screen 3 keeps all 15 cards above the fold', s3.belowFold === 0, 'belowFold=' + s3.belowFold)
  check('screen 3 does not scroll vertically', s3.vScroll <= 1, 'vScroll=' + s3.vScroll)
  check('screen 3 shows 15 real images, no empty src, no placeholder', s3.imgs === 15 && s3.emptySrc === 0 && s3.placeholders === 0 && s3.broken === 0, JSON.stringify(s3))

  await page.evaluate(() => window.CC8.go(4))
  await page.click('.cc8-swatches .cc8-swatch[data-value="brunette"]')
  const s4 = await page.evaluate(() => {
    const sw = [...document.querySelectorAll('.cc8-swatches .cc8-swatch')]
    const on = document.querySelector('.cc8-swatch.on')
    const hair = [...document.querySelectorAll('.cc8-hair-grid .cc8-opt')]
    const hairImgs = hair.map(h => h.querySelector('img')).filter(Boolean)
    const tops = hair.slice(0, 5).map(h => Math.round(h.getBoundingClientRect().top))
    const row = document.querySelector('.cc8-swatches')
    return {
      n: sw.length,
      labelled: sw.filter(s => s.querySelector('.cc8-swatch-label')).length,
      rows: new Set(sw.map(s => Math.round(s.getBoundingClientRect().top))).size,
      onW: on ? Math.round(on.getBoundingClientRect().width) : 0,
      onLabel: on && on.querySelector('.cc8-swatch-label') ? on.querySelector('.cc8-swatch-label').textContent : '',
      labelShown: on ? getComputedStyle(on.querySelector('.cc8-swatch-label')).display !== 'none' : false,
      hairPerRow: tops.filter(t => t === tops[0]).length,
      hairImgs: hairImgs.length,
      hairEmptySrc: hairImgs.filter(i => !i.getAttribute('src')).length,
      hairPlaceholders: document.querySelectorAll('.cc8-hair-grid .cc8-ph').length,
      stripScrolls: row.scrollWidth > row.clientWidth,
      pageStretched: document.documentElement.scrollWidth > innerWidth,
    }
  })
  check('hair strip lists all 10 female colors, each with a label span', s4.n === 10 && s4.labelled === 10, JSON.stringify({ n: s4.n, l: s4.labelled }))
  check('hair strip stays single-line and scrolls inside itself, page not stretched', s4.rows === 1 && s4.stripScrolls && !s4.pageStretched, JSON.stringify({ rows: s4.rows, strip: s4.stripScrolls, page: s4.pageStretched }))
  check('selected swatch expands and shows its name', s4.onW >= 120 && s4.labelShown && s4.onLabel === 'Brunette', JSON.stringify({ w: s4.onW, label: s4.onLabel }))
  check('hairstyle grid is 4 columns', s4.hairPerRow === 4, 'perRow=' + s4.hairPerRow)
  check('hairstyle shows 8 real images with non-empty src', s4.hairImgs === 8 && s4.hairEmptySrc === 0 && s4.hairPlaceholders === 0, JSON.stringify({ imgs: s4.hairImgs, empty: s4.hairEmptySrc, ph: s4.hairPlaceholders }))

  await page.evaluate(() => window.CC8.go(7))
  const s7a = await page.evaluate(() => {
    const h2 = document.querySelector('.cc8-step[data-screen="7"] .cc8-h2')
    return {
      groups: document.querySelectorAll('.cc8-kink-group').length,
      titles: [...document.querySelectorAll('.cc8-kink-group')].map(g => g.textContent.trim()),
      heading: h2 ? h2.textContent.trim() : '',
      chips: document.querySelectorAll('.cc8-kinks .cc8-kink').length,
    }
  })
  check('screen 7 renders 2 grouped sections', s7a.groups === 2 && s7a.titles.join(',') === 'Popular,Mild', JSON.stringify(s7a.titles))
  check('screen 7 heading carries the (n/5) counter', /\(0\/5\)$/.test(s7a.heading), s7a.heading)
  check('screen 7 keeps all 20 chips selectable', s7a.chips === 20, 'chips=' + s7a.chips)
  await page.click('.cc8-kinks .cc8-kink[data-value="Cuddling"]')
  const s7b = await page.evaluate(() => document.querySelector('.cc8-step[data-screen="7"] .cc8-h2').textContent.trim())
  check('screen 7 counter updates immediately', /\(1\/5\)$/.test(s7b), s7b)

  /* male: no motion layer, two body groups */
  await page.evaluate(() => window.CC8.reset())
  await page.click('#cc8Root [data-act="gender"][data-gender="male"]')
  await page.waitForSelector('#cc8Root[data-cc8-gender="male"]')
  const male = await page.evaluate(() => ({
    videos: document.querySelectorAll('.cc8-entry-motion, .cc8-entry-card video').length,
    imgs: document.querySelectorAll('.cc8-entry-card img').length,
  }))
  check('male entry loads no video layer', male.videos === 0 && male.imgs === 2, JSON.stringify(male))
  await page.evaluate(() => window.CC8.go(3))
  const m3 = await page.evaluate(() => document.querySelectorAll('.cc8-grid5').length)
  check('male screen 3 has 2 body groups', m3 === 2, 'grid5=' + m3)
  await page.evaluate(() => window.CC8.go(4))
  const m4 = await page.evaluate(() => document.querySelectorAll('.cc8-swatches .cc8-swatch').length)
  check('male hair strip lists all 9 male colors', m4 === 9, 'swatches=' + m4)
  check('no page errors (v8d layout)', errs.length === 0, errs.join('|'))
  await ctx.close()
}

/* ---------- legacy fallback loads only old wizard ---------- */
{
  const ctx = await browser.newContext({ viewport: { width: 390, height: 844 } })
  const page = await ctx.newPage()
  await page.route('**/api/**', route => json(route, { code: '00000', data: {} }))
  await page.goto(BASE + '?legacy=1', { waitUntil: 'load' })
  await page.waitForTimeout(800)
  const legacy = await page.locator('#ccMain .cc-screen, #ccMain').count()
  const cc8 = await page.locator('#cc8Root').count()
  const hidden = cc8 ? await page.locator('#cc8Root').getAttribute('hidden') : 'missing'
  check('legacy renders old wizard', legacy > 0)
  check('legacy leaves cc8 root hidden', hidden !== null, String(hidden))
  check('legacy did not boot v8', await page.evaluate(() => !window.CC8))
  await page.screenshot({ path: SHOTS + '/legacy.png' })
  await ctx.close()
}

await browser.close()
const failed = results.filter(r => !r.ok)
console.log('\nSUMMARY ' + (results.length - failed.length) + '/' + results.length + ' passed')
if (failed.length) { console.log('FAILED: ' + failed.map(f => f.name).join(' | ')); process.exitCode = 1 }
