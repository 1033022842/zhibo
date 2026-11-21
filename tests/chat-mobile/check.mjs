// Chat 页手机端会话列表抽屉：本地结构校验（不连后端，全部 mock）
// 用法: node tests/chat-mobile/check.mjs
import { createRequire } from 'node:module'
import fs from 'node:fs'
import path from 'node:path'

const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const ROOT = 'D:/ever/douyin/douyin/ai-girl-malaysia.com'
const BASE = process.env.BASE || 'http://127.0.0.1:8131'
const SHOTS = path.join(ROOT, '../tests/chat-mobile/shots')
fs.mkdirSync(SHOTS, { recursive: true })

const PNG = Buffer.from(
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
  'base64')

const CONVS = [
  { role_key: 'my-279', content_id: 0, title: 'Kai', last_message: 'Hey, I missed you…', time: '2026-11-21 22:44:00', cover_url: '' },
  { role_key: 'my-276', content_id: 0, title: 'Ava', last_message: 'Good night', time: '2026-11-21 21:10:00', cover_url: '' },
  { role_key: '', content_id: 36, title: 'Moira', last_message: 'Tell me more about your day', time: '2026-11-20 20:00:00', cover_url: '' },
]

const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
const results = []
const fail = (m) => results.push(['FAIL', m])
const ok = (m) => results.push(['ok', m])

async function makePage(width, height) {
  const ctx = await browser.newContext({ viewport: { width, height }, deviceScaleFactor: 2, serviceWorkers: 'block' })
  const page = await ctx.newPage()
  await page.addInitScript(() => {
    localStorage.setItem('live_access_token', 'test-token')
    localStorage.setItem('live_user_info', JSON.stringify({ id: 322, name: 'Tester' }))
  })
  await page.route('**/*.{png,jpg,jpeg,webp,svg,gif,woff,woff2,ttf}', (r) =>
    r.fulfill({ status: 200, contentType: 'image/png', body: PNG }))
  await page.route('**/api/live/**', async (route) => {
    const url = route.request().url()
    let body = { code: '00000', data: [] }
    if (/chatConversations/.test(url)) body = { code: '00000', data: CONVS }
    else if (/customOneList/.test(url)) body = { code: '00000', data: [{ id: 279, name: 'Kai', photo: '', status: 2 }] }
    else if (/consumeOptions/.test(url)) body = { code: '00000', data: { chat: 1, call: 10 } }
    else if (/userInfo/.test(url)) body = { code: '00000', data: { id: 322, token_balance: 100 } }
    else if (/chatHistory/.test(url)) body = { code: '00000', data: [] }
    await route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })
  })
  return { ctx, page }
}

async function tap(page, sel, label) {
  try {
    await page.click(sel, { timeout: 5000 })
    ok(`点击 ${label}`)
    return true
  } catch (e) {
    fail(`点击失败 ${label}: ${String(e.message).split('\n')[0]}`)
    return false
  }
}

async function dismissCreatorOverlay(page) {
  const later = page.locator('text=Maybe later').first()
  if (await later.count()) await later.click({ timeout: 3000 }).catch(() => {})
  await page.evaluate(() => {
    const o = document.getElementById('cgOverlay')
    if (o) { o.classList.remove('cg-show'); o.style.display = 'none' }
  })
  await page.waitForTimeout(300)
}

async function state(page) {
  return page.evaluate(() => {
    const aside = document.querySelector('aside.list-container')
    const cs = getComputedStyle(aside)
    return {
      open: document.body.classList.contains('chatlist-open'),
      display: cs.display,
      rectW: Math.round(aside.getBoundingClientRect().width),
      rectTop: Math.round(aside.getBoundingClientRect().top),
      convs: document.querySelectorAll('#message-list a.chat-obj').length,
      bodyOverflow: getComputedStyle(document.body).overflow,
    }
  })
}

// ---------- 手机端 ----------
{
  const { ctx, page } = await makePage(390, 844)
  await page.goto(`${BASE}/Chat.html?id=my-279`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2500)

  const s0 = await state(page)
  s0.display === 'none' ? ok('初始列表隐藏') : fail(`初始列表应隐藏，实际 display=${s0.display}`)
  s0.convs >= 1 ? ok(`会话项已渲染 ${s0.convs} 条`) : fail('会话项未渲染')
  await page.screenshot({ path: path.join(SHOTS, '390_chat_closed.png') })

  await tap(page, '#nav-hamburger', '#nav-hamburger')
  await page.waitForTimeout(500)
  const s1 = await state(page)
  s1.open ? ok('点汉堡后抽屉打开') : fail('点汉堡后抽屉未打开')
  s1.display !== 'none' ? ok('列表可见') : fail('列表仍隐藏')
  s1.rectW === 390 ? ok(`列表占满宽度 ${s1.rectW}px`) : fail(`列表宽度应 390，实际 ${s1.rectW}`)
  s1.rectTop === 48 ? ok('列表从导航下方开始') : fail(`列表 top 应 48，实际 ${s1.rectTop}`)
  s1.bodyOverflow === 'hidden' ? ok('打开时锁滚动') : fail('打开时未锁滚动')
  const veil = await page.isVisible('#chatlist-veil')
  veil ? ok('遮罩可见') : fail('遮罩不可见')
  const myAi = await page.isVisible('.fy-list-my-ai')
  myAi ? ok('抽屉里有 My AI 入口') : fail('抽屉缺少 My AI 入口')
  await page.screenshot({ path: path.join(SHOTS, '390_chat_open.png') })

  // 再点汉堡应关闭
  await tap(page, '#nav-hamburger', '#nav-hamburger')
  await page.waitForTimeout(400)
  ;(await state(page)).open ? fail('再点汉堡未关闭') : ok('再点汉堡关闭抽屉')

  // 关闭按钮
  await tap(page, '#nav-hamburger', '#nav-hamburger')
  await page.waitForTimeout(300)
  await tap(page, '[data-chatlist-close]', '[data-chatlist-close]')
  await page.waitForTimeout(300)
  ;(await state(page)).open ? fail('关闭按钮无效') : ok('关闭按钮有效')

  // 选会话后自动收起
  await tap(page, '#nav-hamburger', '#nav-hamburger')
  await page.waitForTimeout(300)
  await tap(page, '#message-list a.chat-obj >> nth=0', '会话项')
  await page.waitForTimeout(600)
  ;(await state(page)).open ? fail('选会话后抽屉未收起') : ok('选会话后抽屉收起')
  await page.screenshot({ path: path.join(SHOTS, '390_after_select.png') })

  await ctx.close()
}

// ---------- 首页底栏 My AI 入口 ----------
{
  const { ctx, page } = await makePage(390, 844)
  await page.goto(`${BASE}/index.html`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1800)
  await dismissCreatorOverlay(page)
  const item = page.locator('a.fy-bnav-item', { hasText: 'My AI' }).first()
  const n = await item.count()
  n ? ok('首页底栏有 My AI 入口') : fail('首页底栏缺少 My AI 入口')
  if (n) {
    const href = await item.getAttribute('href')
    ;/charactersIndex\.html/.test(String(href)) ? ok(`My AI 指向 ${href}`) : fail(`My AI 链接异常 ${href}`)
    await page.screenshot({ path: path.join(SHOTS, '390_index_nav.png') })
    await item.click()
    await page.waitForTimeout(1500)
    page.url().includes('charactersIndex.html') ? ok('底栏 My AI 进入我的角色页') : fail(`未进入我的角色页 ${page.url()}`)
  }
  await ctx.close()
}

// ---------- My AI 页：我的角色可见且能进聊天 ----------
{
  const { ctx, page } = await makePage(390, 844)
  await page.goto(`${BASE}/charactersIndex.html`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(1800)
  const later = page.locator('text=Maybe later').first()
  if (await later.count()) await later.click({ timeout: 3000 }).catch(() => {})
  await page.waitForTimeout(500)

  const cards = page.locator('#all_characters a[href*="Chat.html?id=my-"]')
  const n = await cards.count()
  n >= 1 ? ok(`My AI 列出 ${n} 个我的角色`) : fail('My AI 页未列出我的角色')
  await page.screenshot({ path: path.join(SHOTS, '390_my_ai.png') })
  if (n) {
    const href = await cards.first().getAttribute('href')
    ;/Chat\.html\?id=my-279/.test(href) ? ok(`角色卡指向 ${href}`) : fail(`卡片链接异常 ${href}`)
    await cards.first().click()
    await page.waitForURL(/Chat\.html\?id=my-279/, { timeout: 10000 }).catch(() => {})
    await page.waitForTimeout(300)
  }
  await ctx.close()
}

// ---------- 桌面端回归 ----------
{
  const { ctx, page } = await makePage(1440, 900)
  await page.goto(`${BASE}/Chat.html?id=my-279`, { waitUntil: 'domcontentloaded' })
  await page.waitForTimeout(2200)
  const d0 = await state(page)
  d0.display !== 'none' ? ok('桌面端列表常驻可见') : fail('桌面端列表被隐藏')
  d0.rectW > 300 ? ok(`桌面端列表宽度 ${d0.rectW}px`) : fail(`桌面端宽度异常 ${d0.rectW}`)
  await page.screenshot({ path: path.join(SHOTS, '1440_chat.png') })
  // 桌面端汉堡不应触发抽屉
  await tap(page, '#nav-hamburger', '#nav-hamburger').catch(() => {})
  await page.waitForTimeout(400)
  ;(await state(page)).open ? fail('桌面端误触发抽屉') : ok('桌面端汉堡未触发抽屉')
  await ctx.close()
}

await browser.close()

const fails = results.filter((r) => r[0] === 'FAIL')
results.forEach(([k, m]) => console.log(`${k === 'ok' ? '[ok]' : '[FAIL]'} ${m}`))
console.log(`\nPASS=${results.length - fails.length} FAIL=${fails.length}`)
process.exit(fails.length ? 1 : 0)
