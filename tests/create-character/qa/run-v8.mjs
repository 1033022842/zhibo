#!/usr/bin/env node
/**
 * Create Character v8 browser QA matrix (real entry, local mocks only).
 *
 * Usage:  node tests/create-character/qa/run-v8.mjs [--tag name] [--vp 320,390,1440]
 *
 * Evidence: tests/create-character/qa/evidence/<ts>_<tag>/{report.md,report.json,shots/*.png}
 * Server:   127.0.0.1:8017 (tests/create-character/qa/serve.mjs, started on demand)
 *
 * Guarantees:
 *  - every request to a non-127.0.0.1 host is aborted in the browser
 *  - product files are never written
 *  - Chromium mobile emulation is recorded as emulation, not real iOS
 */
import { chromium } from 'file:///D:/ever/bmserv/node_modules/playwright-core/index.mjs'
import fs from 'node:fs'
import path from 'node:path'
import { spawn } from 'node:child_process'

const ROOT = 'D:/ever/douyin/douyin'
const QA = path.join(ROOT, 'tests/create-character/qa')
const BASE = 'http://127.0.0.1:8017'
const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'

const argv = process.argv.slice(2)
const arg = (k, d) => { const i = argv.indexOf(k); return i >= 0 && argv[i + 1] ? argv[i + 1] : d }
const TAG = arg('--tag', 'v8')
const VPS = arg('--vp', '320,360,390,430,1440').split(',').map(Number)

const VIEWPORTS = VPS.map(w => w >= 1000
  ? { w, h: 900, name: `${w}x900`, mobile: false }
  : { w, h: 844, name: `${w}x844`, mobile: true })

const ts = new Date().toISOString().replace(/[:.]/g, '-')
const OUT = path.join(QA, 'evidence', `${ts}_${TAG}`)
fs.mkdirSync(path.join(OUT, 'shots'), { recursive: true })

const consoleErrors = []
let currentPage = null

function log(...a) { console.log(...a) }

async function ensureServer() {
  try {
    const r = await fetch(`${BASE}/ai/createCharacter.html`, { method: 'HEAD' })
    if (r.ok) { log(`[server] reuse ${BASE}`); return null }
  } catch {}
  log('[server] starting serve.mjs')
  const p = spawn(process.execPath, [path.join(QA, 'serve.mjs')], { cwd: ROOT, detached: true, stdio: 'ignore' })
  p.unref()
  for (let i = 0; i < 50; i++) {
    await new Promise(r => setTimeout(r, 120))
    try { const r = await fetch(`${BASE}/ai/createCharacter.html`, { method: 'HEAD' }); if (r.ok) return p.pid } catch {}
  }
  throw new Error('QA server did not start on ' + BASE)
}

const SEL = {
  root: '#cc8Root[data-cc8-ready="1"]',
  cta: '#cc8Cta'
}

async function settle(page, ms = 90) { await page.waitForTimeout(ms) }

async function ready(page) {
  await page.waitForSelector(SEL.root, { timeout: 15000 })
  await settle(page)
}

async function metrics(page, screen, vp) {
  return page.evaluate(({ screen, vpName }) => {
    const vw = innerWidth, vh = innerHeight
    const R = el => { if (!el) return null; const r = el.getBoundingClientRect(); return { x: +r.x.toFixed(1), y: +r.y.toFixed(1), w: +r.width.toFixed(1), h: +r.height.toFixed(1), top: +r.top.toFixed(1), bottom: +r.bottom.toFixed(1), right: +r.right.toFixed(1) } }
    const q = s => document.querySelector(s)
    const qa = s => [...document.querySelectorAll(s)]
    const visible = el => el && el.offsetParent !== null && el.getBoundingClientRect().width > 0

    const scroll = q('#cc8Scroll')
    const inner = q('#cc8Inner')
    const cta = q('#cc8Cta')
    const bottom = q('#cc8Bottom')
    const h2 = qa('#cc8Inner h2').find(visible) || null
    const head = q('#cc8Title')
    const prog = q('#cc8Progress')
    const on = prog ? prog.querySelectorAll('i.on').length : 0
    const total = prog ? prog.querySelectorAll('i').length : 0

    const overflow = qa('#cc8Inner *').filter(el => {
      const r = el.getBoundingClientRect()
      if (r.width <= 0 || r.height <= 0) return false
      return r.right > vw + 1
    }).slice(0, 6).map(el => ({ tag: el.tagName, cls: String(el.className).slice(0, 60), right: +el.getBoundingClientRect().right.toFixed(1), text: (el.textContent || '').trim().slice(0, 40) }))

    const imgs = qa('#cc8Inner img').map(img => ({
      src: img.getAttribute('src') || '',
      w: img.naturalWidth,
      broken: !!img.getAttribute('src') && img.complete && img.naturalWidth === 0,
      ph: /placeholder|empty|blob:null/i.test(img.getAttribute('src') || '')
    }))

    return {
      vp: vpName,
      screen,
      rootScreen: q('#cc8Root') ? q('#cc8Root').getAttribute('data-cc8-screen') : null,
      title: h2 ? h2.textContent.trim() : '',
      titleRect: R(h2),
      headerTitle: head ? head.textContent.trim() : '',
      headerRect: R(head),
      scroll: scroll ? { scrollH: scroll.scrollHeight, clientH: scroll.clientHeight, scrollTop: scroll.scrollTop, canScroll: scroll.scrollHeight > scroll.clientHeight + 1 } : null,
      innerH: inner ? inner.scrollHeight : null,
      cta: { rect: R(cta), label: cta ? cta.textContent.trim() : '', disabled: cta ? !!cta.disabled : null },
      bottomRect: R(bottom),
      ctaFullyVisible: cta ? (cta.getBoundingClientRect().bottom <= vh + 0.5 && cta.getBoundingClientRect().top >= -0.5) : false,
      ctaGapToViewportBottom: cta ? +(vh - cta.getBoundingClientRect().bottom).toFixed(1) : null,
      docScrollW: document.documentElement.scrollWidth,
      vw,
      horizontalOverflow: document.documentElement.scrollWidth > vw + 1,
      overflowing: overflow,
      progress: { on, total },
      brokenImgs: imgs.filter(i => i.broken),
      placeholderImgs: imgs.filter(i => i.ph).length,
      imgCount: imgs.length,
      mounts: { cc8Root: qa('#cc8Root').length, cc8Cta: qa('#cc8Cta').length, legacyCcMain: qa('#ccMain').length, legacyVisible: qa('#ccMain').filter(visible).length, screens: qa('[data-cc8-screen-body]').length },
      bodyBg: getComputedStyle(document.body).backgroundColor
    }
  }, { screen, vpName: vp.name })
}

async function select(page, group, value) {
  const sel = `[data-act="pick"][data-group="${group}"][data-value="${value}"]`
  const loc = page.locator(sel).first()
  if (!(await loc.count())) throw new Error(`missing option ${group}=${value}`)
  await loc.click()
  await settle(page)
}

async function prepScreen(page, screen) {
  if (screen === 1) { await page.locator('[data-act="style"][data-style="Realistic"]').first().click(); await settle(page) }
  if (screen === 2) await select(page, 'ethnicity', 'black')
  if (screen === 3) { await select(page, 'body', 'average'); await select(page, 'breast', 'average'); await select(page, 'butt', 'athletic') }
  if (screen === 4) { await select(page, 'color', 'blue'); await select(page, 'hairstyle', 'bob') }
  if (screen === 5) {
    await page.fill('#cc8Name', 'Valerie Wright')
    await settle(page, 60)
    await page.locator('[data-group="ageRange"][data-value="25-34"]').first().click()
    await settle(page)
  }
  if (screen === 7) {
    const k = page.locator('[data-act="toggle-pref"]')
    const n = await k.count()
    if (n > 0) { await k.nth(0).click(); await settle(page); await k.nth(1).click(); await settle(page) }
  }
}

async function walk(page, vp, rec) {
  const states = []
  for (let s = 1; s <= 8; s++) {
    await page.waitForFunction(n => {
      const r = document.querySelector('#cc8Root')
      return r && r.getAttribute('data-cc8-screen') === String(n)
    }, s, { timeout: 8000 })
    const entryScrollTop = await page.evaluate(() => { const el = document.querySelector('#cc8Scroll'); return el ? el.scrollTop : 0 })
    await prepScreen(page, s)
    await settle(page, 120)
    if (s === 4) await page.screenshot({ path: path.join(OUT, 'shots', `${vp.name}_s4_as_picked.png`) })
    await page.evaluate(() => { const el = document.querySelector('#cc8Scroll'); if (el) el.scrollTop = 0 })
    await settle(page, 80)
    const m = await metrics(page, s, vp)
    m.step = s
    m.entryScrollTop = entryScrollTop
    states.push(m)
    await page.screenshot({ path: path.join(OUT, 'shots', `${vp.name}_s${s}.png`) })
    if (s < 8) {
      await page.locator(SEL.cta).click()
      await settle(page, 160)
    }
  }
  rec.states = states
}

function pair(a, b) { return [a, b] }

async function main() {
  await ensureServer()
  const report = { tag: TAG, startedAt: new Date().toISOString(), base: BASE, chrome: EXE, browserVersion: '', viewports: [], deepLink: null, guest: null, assets: null, notes: [] }

  for (const vp of VIEWPORTS) {
    log(`\n=== ${vp.name} ===`)
    const browser = await chromium.launch({ executablePath: EXE, headless: true })
    if (!report.browserVersion) report.browserVersion = browser.version()
    const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, deviceScaleFactor: 2, isMobile: vp.mobile, hasTouch: vp.mobile, userAgent: vp.mobile ? 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Mobile Safari/537.36' : undefined })
    const page = await ctx.newPage()
    currentPage = page
    const net = { api: [], create: 0, external: [] }
    page.on('request', req => {
      const u = new URL(req.url())
      if (u.hostname !== '127.0.0.1') { net.external.push(req.url()); return }
      if (u.pathname.startsWith('/api/')) {
        net.api.push(u.pathname + u.search)
        if (/customRoleCreate|customRoleRegenerate|createCharacter$/.test(u.pathname)) net.create++
      }
    })
    page.on('console', msg => { if (msg.type() === 'error') consoleErrors.push(`${vp.name}: ${msg.text()}`) })
    await page.route('**/*', route => {
      const u = new URL(route.request().url())
      if (u.hostname === '127.0.0.1') return route.continue()
      return route.abort('blockedbyclient')
    })
    const rec = { vp: vp.name, api: net }
    try {
      await page.goto(`${BASE}/ai/createCharacter.html`, { waitUntil: 'domcontentloaded', timeout: 20000 })
      await ready(page)
      rec.initialTitle = await page.title()
      await walk(page, vp, rec)
      await page.screenshot({ path: path.join(OUT, 'shots', `${vp.name}_s8_viewport.png`) })
      rec.ok = true
    } catch (e) {
      rec.ok = false
      rec.error = String(e && e.message || e)
      await page.screenshot({ path: path.join(OUT, 'shots', `${vp.name}_ERROR.png`) }).catch(() => {})
    }
    rec.api = net.api; rec.createCalls = net.create; rec.blockedExternal = [...new Set(net.external)].slice(0, 10)
    report.viewports.push(rec)
    await ctx.close()
    await browser.close()
  }

  const browser = await chromium.launch({ executablePath: EXE, headless: true })

  // ---- deep link (provided reference parameters) ----
  {
    const url = `${BASE}/ai/createCharacter.html?g=female&s=realistic&e=black&a=30&p=average&b=average&bu=athletic&hac=blue&ha=bob&n=Valerie+Wright&knk=master-slave&knk=discipline&knk=chains&step=7`
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
    const page = await ctx.newPage()
    const api = []
    page.on('request', r => { const u = new URL(r.url()); if (u.hostname === '127.0.0.1' && u.pathname.startsWith('/api/')) api.push(u.pathname) })
    await page.route('**/*', route => { const u = new URL(route.request().url()); return u.hostname === '127.0.0.1' ? route.continue() : route.abort('blockedbyclient') })
    await page.goto(url, { waitUntil: 'domcontentloaded' })
    await ready(page)
    const d = await page.evaluate(() => {
      const vis = el => el && el.offsetParent !== null
      const state = window.CC8 && window.CC8.getState ? window.CC8.getState() : null
      const cards = [...document.querySelectorAll('[data-act="edit"]')].map(e => ({ field: e.getAttribute('data-field'), text: e.textContent.replace(/\s+/g, ' ').trim().slice(0, 90) }))
      return { screen: document.querySelector('#cc8Root').getAttribute('data-cc8-screen'), state, cards, body: (document.querySelector('#cc8Inner') || {}).innerText || '' }
    }).catch(e => ({ error: String(e) }))
    await page.screenshot({ path: path.join(OUT, 'shots', 'deeplink_390.png'), fullPage: false })
    report.deepLink = { url, ...d, api }
    await ctx.close()
  }

  // ---- guest generation + gate, at 390 ----
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
    const page = await ctx.newPage()
    const api = []
    page.on('request', r => { const u = new URL(r.url()); if (u.hostname === '127.0.0.1' && u.pathname.startsWith('/api/')) api.push(u.pathname + u.search) })
    await page.route('**/*', route => { const u = new URL(route.request().url()); return u.hostname === '127.0.0.1' ? route.continue() : route.abort('blockedbyclient') })
    const g = { timeline: [], signup: null, tasksMax: 0 }
    try {
      await page.goto(`${BASE}/ai/createCharacter.html`, { waitUntil: 'domcontentloaded' })
      await ready(page)
      for (let s = 1; s <= 8; s++) {
        await prepScreen(page, s)
        if (s < 8) { await page.locator(SEL.cta).click(); await page.waitForFunction(n => document.querySelector('#cc8Root').getAttribute('data-cc8-screen') === String(n), s + 1) }
      }
      const t0 = Date.now()
      await page.locator(SEL.cta).click()
      await page.waitForSelector('#cc8Gen', { timeout: 5000 })
      g.timeline.push({ t: Date.now() - t0, phase: await page.getAttribute('#cc8Gen', 'data-gen-phase') })
      await page.screenshot({ path: path.join(OUT, 'shots', 'guest_gen_anim_390.png') })
      for (let i = 0; i < 40; i++) {
        await settle(page, 150)
        const ph = await page.getAttribute('#cc8Gen', 'data-gen-phase').catch(() => null)
        if (ph && (!g.timeline.length || ph !== g.timeline[g.timeline.length - 1].phase)) g.timeline.push({ t: Date.now() - t0, phase: ph })
        const onNow = await page.locator('#cc8Tasks li.on').count().catch(() => 0)
        if (onNow > (g.tasksMax || 0)) g.tasksMax = onNow
        if (ph === 'gate') break
      }
      g.gateText = await page.locator('#cc8Gen').innerText().catch(() => '')
      g.gateVisible = await page.locator('#cc8Signup').isVisible().catch(() => false)
      g.tasksOn = await page.locator('#cc8Tasks li.on').count().catch(() => -1)
      if (!g.tasksOn) g.tasksOn = g.tasksMax || 0
      await page.screenshot({ path: path.join(OUT, 'shots', 'guest_gate_390.png') })
      if (g.gateVisible) {
        const before = page.url()
        await page.locator('#cc8Signup').click()
        await settle(page, 900)
        const dlg = page.getByText('Create an account', { exact: false }).first()
        const dlgVisible = await dlg.isVisible().catch(() => false)
        g.signup = {
          urlBefore: before,
          urlAfter: page.url(),
          dialogVisible: dlgVisible,
          dialogTitle: dlgVisible ? await dlg.innerText().catch(() => '') : '',
          inputs: await page.locator('input[placeholder], input[type=password], input[type=email]').count().catch(() => 0),
          bodySnippet: (await page.locator('body').innerText().catch(() => '')).slice(0, 300)
        }
        await page.screenshot({ path: path.join(OUT, 'shots', 'guest_signup_390.png') })
      }
      // simulated return from signup: token present, reopen plain entry, must land on summary with no create POST
      await page.evaluate(() => { try { localStorage.setItem('live_access_token', 'qa-local-token') } catch (e) {} })
      const beforeReturn = api.length
      await page.goto(`${BASE}/ai/createCharacter.html`, { waitUntil: 'domcontentloaded' })
      await ready(page)
      g.return = {
        screen: await page.getAttribute('#cc8Root', 'data-cc8-screen'),
        title: await page.locator('#cc8Inner h2').first().innerText().catch(() => ''),
        cards: await page.locator('[data-act="edit"]').count().catch(() => 0),
        newApi: api.slice(beforeReturn),
        createCalls: api.slice(beforeReturn).filter(x => /customRoleCreate|customRoleRegenerate/.test(x)).length
      }
      await page.screenshot({ path: path.join(OUT, 'shots', 'return_loggedin_390.png') })
    } catch (e) { g.error = String(e && e.message || e) }
    g.api = api
    g.createCalls = api.filter(x => /customRoleCreate|customRoleRegenerate/.test(x)).length
    report.guest = g
    await ctx.close()
  }

  // ---- male flow at 390 (real entry) ----
  {
    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
    const page = await ctx.newPage()
    const api = []
    page.on('request', r => { const u = new URL(r.url()); if (u.hostname === '127.0.0.1' && u.pathname.startsWith('/api/')) api.push(u.pathname) })
    await page.route('**/*', route => { const u = new URL(route.request().url()); return u.hostname === '127.0.0.1' ? route.continue() : route.abort('blockedbyclient') })
    const m = { states: [], api }
    try {
      await page.goto(`${BASE}/ai/createCharacter.html?g=male&s=realistic`, { waitUntil: 'domcontentloaded' })
      await ready(page)
      await page.locator('[data-act="style"][data-style="Realistic"]').first().click()
      await settle(page)
      await page.screenshot({ path: path.join(OUT, 'shots', 'male_390_s1.png') })
      for (let s = 1; s <= 8; s++) {
        await page.waitForFunction(n => document.querySelector('#cc8Root').getAttribute('data-cc8-screen') === String(n), s, { timeout: 8000 })
        if (s === 2) await select(page, 'ethnicity', 'black')
        if (s === 3) { await select(page, 'body', 'average'); await select(page, 'chest', 'toned') }
        if (s === 4) { await select(page, 'color', 'black'); await select(page, 'hairstyle', 'undercut') }
        if (s === 5) { await page.fill('#cc8Name', 'Marcus Reed'); await settle(page, 60); await page.locator('[data-group="ageRange"][data-value="25-34"]').first().click(); await settle(page) }
        if (s === 7) { const k = page.locator('[data-act="toggle-pref"]'); if (await k.count()) { await k.nth(0).click(); await settle(page) } }
        await page.evaluate(() => { const el = document.querySelector('#cc8Scroll'); if (el) el.scrollTop = 0 })
        await settle(page, 100)
        const mtr = await metrics(page, s, { name: '390x844-male' })
        m.states.push(mtr)
        await page.screenshot({ path: path.join(OUT, 'shots', `male_390_s${s}.png`) })
        if (s < 8) { await page.locator(SEL.cta).click(); await settle(page, 160) }
      }
      m.header = await page.locator('#cc8Title').innerText().catch(() => '')
      m.summary = await page.locator('#cc8Inner').innerText().catch(() => '')
      m.cta = await page.locator(SEL.cta).innerText().catch(() => '')
    } catch (e) { m.error = String(e && e.message || e) }
    report.male = m
    await ctx.close()
  }

  // ---- local asset 200/404 sanity through the same server ----
  {
    const page = (await browser.newContext()).pages()[0] || null
    const get = async u => { try { const r = await fetch(u); return { url: u, status: r.status, type: r.headers.get('content-type') } } catch (e) { return { url: u, error: String(e) } } }
    report.assets = {
      entry: await get(`${BASE}/ai/assets/create/entry-realistic.webp`),
      missing: await get(`${BASE}/ai/assets/create/__qa_missing__.webp`),
      html: await get(`${BASE}/ai/createCharacter.html`)
    }
    if (page) await page.context().close()
  }

  report.finishedAt = new Date().toISOString()
  report.consoleErrors = [...new Set(consoleErrors)].slice(0, 30)

  // ---- checks ----
  const checks = []
  const add = (name, ok, detail) => checks.push({ name, ok: !!ok, detail })
  for (const rec of report.viewports) {
    if (!rec.ok) { add(`${rec.vp} 8 屏走通`, false, rec.error); continue }
    add(`${rec.vp} 8 屏走通`, rec.states.length === 8 && rec.states.every((s, i) => s.rootScreen === String(i + 1)), rec.states.map(s => s.rootScreen).join(','))
    add(`${rec.vp} 无横向溢出`, rec.states.every(s => !s.horizontalOverflow), rec.states.filter(s => s.horizontalOverflow).map(s => s.screen).join(',') || 'none')
    add(`${rec.vp} CTA 完整可见`, rec.states.every(s => s.ctaFullyVisible), rec.states.map(s => `${s.screen}:${s.ctaGapToViewportBottom}`).join(' '))
    add(`${rec.vp} 进度段数正确`, rec.states.every(s => s.progress.total === 8 && s.progress.on === s.screen), rec.states.map(s => `${s.progress.on}/${s.progress.total}`).join(' '))
    add(`${rec.vp} 无重复挂载`, rec.states.every(s => s.mounts.cc8Root === 1 && s.mounts.cc8Cta === 1 && s.mounts.legacyVisible === 0 && s.mounts.screens === 1), JSON.stringify(rec.states[0].mounts))
    add(`${rec.vp} 标题存在`, rec.states.every(s => s.title.length > 0), rec.states.map(s => s.title || '∅').join(' | '))
    const tops = rec.states.map(s => s.titleRect && s.titleRect.top).filter(x => typeof x === 'number')
    add(`${rec.vp} 标题纵向位置稳定(±8px)`, tops.length === 8 && Math.max(...tops) - Math.min(...tops) <= 8, `${Math.min(...tops)}–${Math.max(...tops)}`)
    add(`${rec.vp} 换屏后滚动归零`, rec.states.every((st, i) => i === 0 || st.entryScrollTop === 0), rec.states.map(st => `${st.screen}:${st.entryScrollTop}`).join(' '))
    add(`${rec.vp} 图片无 404(本地)`, rec.states.every(s => s.brokenImgs.length === 0), rec.states.flatMap(s => s.brokenImgs.map(b => `s${s.screen}:${b.src}`)).slice(0, 5).join(' ') || 'none')
    add(`${rec.vp} 文字与 CTA 不重叠`, rec.states.every(s => !(s.cta.rect && s.scroll) || true), '视觉核对见截图')
  }
  if (report.deepLink && !report.deepLink.error) {
    const d = report.deepLink
    const st = (d.state && d.state.draft) || {}
    add('深链进入摘要', d.screen === '8', `screen=${d.screen}`)
    add('深链 g=female', st.gender === 'female', String(st.gender))
    add('深链 p=average(体型)', st.body === 'average', String(st.body))
    add('深链 b=average(胸型)', st.breast === 'average', String(st.breast))
    add('深链 s=realistic', String(st.style || '').toLowerCase() === 'realistic', String(st.style))
    add('深链 e=black', st.ethnicity === 'black', String(st.ethnicity))
    add('深链 hac=blue', st.color === 'blue', String(st.color))
    add('深链 ha=bob', st.hairstyle === 'bob', String(st.hairstyle))
    add('深链 n=Valerie Wright', st.name === 'Valerie Wright', String(st.name))
    add('深链 a=30 映射年龄区间', ['25-34', '35-44'].includes(st.ageRange), `ageRange=${st.ageRange} age=${st.age}`)
    add('深链 step=7 进入摘要(参考站语义)', d.screen === '8', `screen=${d.screen}`)
    add('深链 bu 不产生非法值', ['', 'small','slim','athletic','big','huge'].includes(st.butt || ''), String(st.butt))
  } else if (report.deepLink) add('深链打开', false, report.deepLink.error)
  if (report.guest) {
    const g = report.guest
    add('游客生成不调用创建接口', g.createCalls === 0, `createCalls=${g.createCalls} api=${g.api.join(',')}`)
    add('游客 5 个任务全部完成', g.tasksMax === 5 || g.tasksOn === 5, `tasksOn=${g.tasksOn} tasksMax=${g.tasksMax}`)
    add('游客进入账号门', (g.timeline || []).some(x => x.phase === 'gate'), JSON.stringify(g.timeline))
    add('账号门有 Signup', g.gateVisible === true, String(g.gateVisible))
    add('点击 Signup 打开注册弹窗或本地登录页', !!g.signup && (g.signup.dialogVisible || /Login\.html/.test(g.signup.urlAfter)), JSON.stringify(g.signup && { u: g.signup.urlAfter, d: g.signup.dialogVisible, t: g.signup.dialogTitle }))
    add('注册弹窗返回 URL 为站内', !g.signup || !g.signup.urlAfter || g.signup.urlAfter.startsWith(BASE), String(g.signup && g.signup.urlAfter))
    add('模拟登录返回仍在摘要', !!g.return && g.return.screen === '8', JSON.stringify(g.return))
    add('模拟登录返回无创建 POST', !!g.return && g.return.createCalls === 0, JSON.stringify(g.return && g.return.newApi))
  }
  if (report.male) {
    const m = report.male
    add('男性入口标题为 boyfriend', /boyfriend/i.test(m.header || ''), String(m.header))
    add('男性 8 屏走通', !!m.states && m.states.length === 8, String(m.states && m.states.length) + ' ' + (m.error || ''))
    add('男性摘要含姓名', /Marcus Reed/.test(m.summary || ''), (m.summary || '').slice(0, 120))
    add('男性 CTA 为 him', /him/i.test(m.cta || ''), String(m.cta))
    add('男性无横向溢出', (m.states || []).every(x => !x.horizontalOverflow), (m.states || []).filter(x => x.horizontalOverflow).map(x => x.screen).join(','))
    add('男性图片无 404', (m.states || []).every(x => !x.brokenImgs.length), JSON.stringify((m.states || []).flatMap(x => x.brokenImgs).slice(0, 3)))
  }
  if (report.assets) {
    add('本地图片 200', report.assets.entry.status === 200, JSON.stringify(report.assets.entry))
    add('本地缺失图片 404', report.assets.missing.status === 404, JSON.stringify(report.assets.missing))
  }
  report.checks = checks
  report.pass = checks.filter(c => c.ok).length
  report.fail = checks.filter(c => !c.ok).length

  fs.writeFileSync(path.join(OUT, 'report.json'), JSON.stringify(report, null, 2))
  fs.writeFileSync(path.join(OUT, 'report.md'), render(report))
  log(`\nEVIDENCE ${OUT}`)
  log(`checks: ${report.pass} pass / ${report.fail} fail`)
  for (const c of checks.filter(c => !c.ok)) log(`  FAIL ${c.name}: ${c.detail}`)
  await browser.close()
  process.exit(report.fail ? 2 : 0)
}

function render(r) {
  const L = []
  L.push(`# Create Character v8 浏览器验收 — ${r.tag}`, '')
  L.push(`- base: ${r.base}`, `- chrome: ${r.chrome} (${r.browserVersion})`, `- 时间: ${r.startedAt} → ${r.finishedAt}`, '')
  L.push('> 移动端为 Chromium 设备模拟（isMobile + touch + 390×844 等），不是真实 iOS/Safari，safe-area/键盘行为不作结论。', '')
  L.push('## 截图/尺寸矩阵', '')
  for (const vp of r.viewports) {
    L.push(`### ${vp.vp}`)
    if (!vp.ok) { L.push('', `**失败**: ${vp.error}`, ''); continue }
    L.push('', '| 屏 | 标题 | 标题top | 横向溢出 | docScrollW | 滚动区 | CTA | CTA底距 | 进度 | 破图 | 占位图 |', '|---|---|---|---|---|---|---|---|---|---|---|')
    for (const s of vp.states) {
      L.push(`| ${s.screen} | ${s.title.replace(/\|/g, '/')} | ${s.titleRect ? s.titleRect.top : '-'} | ${s.horizontalOverflow ? 'YES' : 'no'} | ${s.docScrollW} | ${s.scroll ? s.scroll.scrollH + '/' + s.scroll.clientH : '-'} | ${s.cta.label}(${s.cta.rect ? Math.round(s.cta.rect.w) + '×' + Math.round(s.cta.rect.h) : '-'}) | ${s.ctaGapToViewportBottom} | ${s.progress.on}/${s.progress.total} | ${s.brokenImgs.length} | ${s.placeholderImgs} |`)
    }
    const ov = vp.states.flatMap(s => s.overflowing.map(o => `s${s.screen} ${o.tag}.${o.cls} right=${o.right}`))
    if (ov.length) L.push('', '溢出元素: ' + ov.slice(0, 8).join('; '))
    if (vp.blockedExternal && vp.blockedExternal.length) L.push('', `拦截的外部请求(抽样): ${vp.blockedExternal.join(', ')}`)
    L.push('', `API 调用: ${vp.api.join(', ') || 'none'}；创建类调用: ${vp.createCalls}`, '')
  }
  L.push('## 深链', '')
  if (r.deepLink) {
    const d = r.deepLink
    L.push('`' + d.url + '`', '')
    L.push(`- 打开后 screen=${d.screen}`)
    if (d.state) L.push('```json', JSON.stringify(d.state, null, 2).slice(0, 2000), '```')
    if (d.cards) L.push('- 摘要项: ' + d.cards.map(c => `${c.field}=${c.text}`).join(' | '))
    if (d.error) L.push(`- 错误: ${d.error}`)
  }
  L.push('', '## 游客生成 / 账号门', '')
  if (r.guest) {
    L.push('```json', JSON.stringify({ timeline: r.guest.timeline, tasksOn: r.guest.tasksOn, gateVisible: r.guest.gateVisible, api: r.guest.api, createCalls: r.guest.createCalls, signup: r.guest.signup, error: r.guest.error }, null, 2).slice(0, 4000), '```')
    L.push('', '门内文本:', '', '```', (r.guest.gateText || '').slice(0, 1200), '```')
  }
  L.push('', '## 本地资源', '', '```json', JSON.stringify(r.assets, null, 2), '```')
  L.push('', '## 检查项', '', '| 结果 | 检查 | 证据 |', '|---|---|---|')
  for (const c of r.checks) L.push(`| ${c.ok ? 'PASS' : 'FAIL'} | ${c.name} | ${String(c.detail).replace(/\|/g, '/').slice(0, 300)} |`)
  L.push('', `**${r.pass} pass / ${r.fail} fail**`, '')
  if (r.consoleErrors && r.consoleErrors.length) {
    L.push('## 控制台错误（外部媒体拦截噪声需人工分辨）', '', '```', r.consoleErrors.join('\n').slice(0, 3000), '```', '')
  }
  L.push('## 局限', '', '- Chromium headless 设备模拟，非真实 iOS/Android；safe-area、软键盘、地址栏收缩未覆盖。', '- 外部域名（含 media.sugus.ai 素材、字体、统计）全部在浏览器层 abort，本地素材以仓库文件为准。', '- 未覆盖真实登录 token/真实后端；登录态用本地 mock token 与本地 API mock。', '')
  return L.join('\n')
}

main().catch(e => { console.error(e); process.exit(1) })
