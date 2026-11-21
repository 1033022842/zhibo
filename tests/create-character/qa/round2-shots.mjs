// v8d/v8d2 目检：390×844 与 425×900 走真实交互，截第 1/2/3/4/7/8 屏到 evidence/round2/{w}x{h}_s{n}.png，并打印几何量测。
import fs from 'node:fs'
import path from 'node:path'
import { createRequire } from 'node:module'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const BASE = process.env.BASE || 'http://127.0.0.1:8131/createCharacter.html'
const OUT = path.resolve('tests/create-character/qa/evidence/round2')
fs.mkdirSync(OUT, { recursive: true })
const CHROME = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'

const browser = await chromium.launch({ executablePath: CHROME, headless: true })
const report = {}

for (const vp of [{ w: 390, h: 844 }, { w: 425, h: 900 }]) {
  const tag = `${vp.w}x${vp.h}`
  const ctx = await browser.newContext({ viewport: { width: vp.w, height: vp.h }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
  const page = await ctx.newPage()
  const errs = []
  page.on('pageerror', e => errs.push(String(e).slice(0, 200)))
  await page.goto(BASE, { waitUntil: 'load' })
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
  await page.waitForFunction(() => {
    const v = document.querySelector('.cc8-entry-card .cc8-entry-motion')
    return !!v && v.classList.contains('is-ready') && v.currentTime > 0 && getComputedStyle(v).opacity === '1'
  }, null, { timeout: 20000 })
  await page.waitForTimeout(300)
  report[tag] = {}
  const shot = n => page.screenshot({ path: path.join(OUT, `${tag}_s${n}.png`) })

  // ── s1 入口
  report[tag].s1 = await page.evaluate(() => {
    const bar = document.querySelector('#cc8Root .cc8-bottom')
    const btns = bar ? Array.from(bar.querySelectorAll('button')) : []
    const v = document.querySelector('.cc8-entry-card .cc8-entry-motion')
    return {
      buttons: btns.map(b => ({ act: b.getAttribute('data-act'), text: (b.textContent || '').trim(), w: Math.round(b.getBoundingClientRect().width * 10) / 10 })),
      video: v ? { ready: v.classList.contains('is-ready'), t: Math.round(v.currentTime * 100) / 100, opacity: getComputedStyle(v).opacity, src: v.querySelector('source').getAttribute('src') } : null,
      vScroll: document.documentElement.scrollHeight - innerHeight,
    }
  })
  await shot(1)

  // ── 真实路径：选 Realistic → Begin 进第 2 屏
  await page.click('#cc8Root [data-act="style"][data-style="Realistic"]')
  await page.waitForTimeout(200)
  await page.click('#cc8Root [data-act="cta"]')
  await page.waitForTimeout(500)
  report[tag].s2 = await page.evaluate(() => {
    const on = document.querySelector('.cc8-grid3 .cc8-opt.on')
    return {
      screen: Number(document.getElementById('cc8Root').getAttribute('data-cc8-screen')),
      highlighted: on ? on.getAttribute('data-value') : '',
      ethnicity: window.CC8.getState().draft.ethnicity,
      cards: document.querySelectorAll('.cc8-grid3 .cc8-opt').length,
    }
  })
  await shot(2)

  // ── s3
  await page.evaluate(() => window.CC8.go(3))
  await page.waitForTimeout(900)
  report[tag].s3 = await page.evaluate(() => {
    const cards = Array.from(document.querySelectorAll('.cc8-grid5 .cc8-opt'))
    const imgs = cards.map(c => c.querySelector('img')).filter(Boolean)
    const scroll = document.querySelector('.cc8-scroll')
    return {
      grids: document.querySelectorAll('.cc8-grid5').length,
      cards: cards.length,
      imgs: imgs.length,
      placeholders: document.querySelectorAll('.cc8-grid5 .cc8-ph').length,
      broken: imgs.filter(i => i.complete && i.naturalWidth === 0).length,
      sampleSrc: imgs[0] ? imgs[0].getAttribute('src') : '',
      lastCardBottom: Math.round(cards[cards.length - 1].getBoundingClientRect().bottom),
      innerH: innerHeight,
      belowFold: cards.filter(c => c.getBoundingClientRect().bottom > innerHeight + 1).length,
      vScroll: scroll.scrollHeight - scroll.clientHeight,
      overflowX: document.documentElement.scrollWidth > innerWidth,
    }
  })
  await shot(3)

  // ── s4
  await page.evaluate(() => window.CC8.go(4))
  await page.waitForTimeout(700)
  await page.click('.cc8-swatches .cc8-swatch[data-value="brunette"]')
  await page.waitForTimeout(350)
  report[tag].s4 = await page.evaluate(() => {
    const sw = Array.from(document.querySelectorAll('.cc8-swatches .cc8-swatch'))
    const on = document.querySelector('.cc8-swatch.on')
    const row = document.querySelector('.cc8-swatches')
    const hair = Array.from(document.querySelectorAll('.cc8-hair-grid .cc8-opt'))
    const hairImgs = hair.map(h => h.querySelector('img')).filter(Boolean)
    const tops = hair.slice(0, 5).map(h => Math.round(h.getBoundingClientRect().top))
    return {
      swatches: sw.length,
      swatchRows: new Set(sw.map(s => Math.round(s.getBoundingClientRect().top))).size,
      stripScrolls: row.scrollWidth > row.clientWidth,
      stripScrollW: row.scrollWidth,
      stripClientW: row.clientWidth,
      pageStretched: document.documentElement.scrollWidth > innerWidth,
      onW: on ? Math.round(on.getBoundingClientRect().width) : 0,
      onLabel: on ? on.querySelector('.cc8-swatch-label').textContent.trim() : '',
      labelShown: on ? getComputedStyle(on.querySelector('.cc8-swatch-label')).display : 'none',
      hairImgs: hairImgs.length,
      hairPlaceholders: document.querySelectorAll('.cc8-hair-grid .cc8-ph').length,
      hairBroken: hairImgs.filter(i => i.complete && i.naturalWidth === 0).length,
      hairSample: hairImgs[0] ? hairImgs[0].getAttribute('src') : '',
      hairPerRow: tops.filter(t => t === tops[0]).length,
    }
  })
  await shot(4)

  // ── s7
  await page.evaluate(() => window.CC8.go(7))
  await page.waitForTimeout(400)
  await page.click('.cc8-kinks .cc8-kink[data-value="Cuddling"]')
  await page.click('.cc8-kinks .cc8-kink[data-value="Bondage"]')
  await page.waitForTimeout(250)
  report[tag].s7 = await page.evaluate(() => ({
    groups: Array.from(document.querySelectorAll('.cc8-kink-group')).map(g => g.textContent.trim()),
    heading: document.querySelector('.cc8-step[data-screen="7"] .cc8-h2').textContent.trim(),
    chips: document.querySelectorAll('.cc8-kinks .cc8-kink').length,
    on: Array.from(document.querySelectorAll('.cc8-kink.on')).map(c => c.textContent.trim()),
  }))
  await shot(7)

  // ── s8 生成页
  await page.evaluate(() => window.CC8.go(5))
  await page.waitForTimeout(250)
  await page.fill('#cc8Name', 'Ava')
  await page.waitForTimeout(200)
  await page.evaluate(() => window.CC8.go(8))
  await page.waitForTimeout(300)
  await page.click('#cc8Root [data-act="cta"]')
  await page.waitForSelector('#cc8Gen', { timeout: 15000 })
  await page.waitForTimeout(1200)
  report[tag].s8 = await page.evaluate(() => {
    const gen = document.getElementById('cc8Gen')
    const card = document.getElementById('cc8GenCard').getBoundingClientRect()
    const acts = document.getElementById('cc8GenActions').getBoundingClientRect()
    return {
      phase: gen.getAttribute('data-gen-phase'),
      cardTop: Math.round(card.top),
      cardBottom: Math.round(card.bottom),
      actionsBottom: Math.round(acts.bottom),
      innerH: innerHeight,
      blankBelow: Math.round(innerHeight - acts.bottom),
      genScroll: gen.scrollHeight - gen.clientHeight,
    }
  })
  await shot(8)
  report[tag].errs = errs
  await ctx.close()
}

await browser.close()
console.log(JSON.stringify(report, null, 2))
