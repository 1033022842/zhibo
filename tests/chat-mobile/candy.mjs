#!/usr/bin/env node
/* 会话列表 candy 化 + 「我的角色进列表」+ 开场白不被吞 的本地回归。
   mock 全部 /api/live/**，登录态用 localStorage token。 */
import { createRequire } from 'node:module'
import path from 'node:path'
import fs from 'node:fs'

const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const BASE = process.env.BASE || 'http://127.0.0.1:8131'
const SHOTS = path.resolve('tests/chat-mobile/shots')
fs.mkdirSync(SHOTS, { recursive: true })

const ok = (m) => console.log(`[ok] ${m}`)
const bad = (m) => { console.log(`[FAIL] ${m}`); fails++ }
let fails = 0

const MY = {
  id: 279, name: 'Kai', photo: '/storage/custom_video/20261121/cr_sugus_867.webp',
  status: 2, source_fields: { gender: 'male', age: '28', race: 'asian', personality: 'playful', hairstyle: 'buzz', body: 'athletic', relation: 'boyfriend' }
}

const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' })
const page = await ctx.newPage()

const json = (body) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(body) })
/* Playwright 的路由是后注册优先，所以 catch-all 必须先注册 */
await page.route('**/api/live/**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await page.route('**/api/live/customOneList**', (r) => r.fulfill(json({ code: '00000', data: [MY] })))
await page.route('**/api/live/chatConversations**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await page.route('**/api/live/chatHistory**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await page.route('**/api/live/iceBreaker**', (r) => r.fulfill(json({ code: '00000', data: { text: 'There you are… I was waiting all night for you 💋' } })))
await page.route('**/*.{png,jpg,jpeg,webp,svg,gif}', (r) =>
  r.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') }))

await page.addInitScript(() => localStorage.setItem('live_access_token', 'test-token'))
await page.goto(`${BASE}/Chat.html?id=my-279`, { waitUntil: 'domcontentloaded', timeout: 60000 })
await page.waitForTimeout(2500)

/* 1. 我的角色出现在会话列表里（即使没有聊天记录） */
const card = page.locator('#message-list a.chat-obj[data-id="my-279"]')
;(await card.count()) >= 1 ? ok('会话列表包含我的角色 my-279') : bad('会话列表缺少 my-279')

await page.click('#nav-hamburger')
await page.waitForTimeout(400)
const style = await card.first().evaluate((el) => {
  const s = getComputedStyle(el)
  const av = el.querySelector('.fy-conv-avatar')
  return {
    bg: s.backgroundColor, radius: s.borderRadius, border: s.borderTopWidth,
    name: (el.querySelector('.fy-conv-name') || {}).textContent || '',
    preview: (el.querySelector('.fy-conv-preview') || {}).textContent || '',
    avatar: av ? Math.round(av.getBoundingClientRect().width) : 0,
    sideShown: (() => { const x = el.querySelector('.fy-conv-side'); return x ? getComputedStyle(x).display !== 'none' : false })(),
  }
})
const bgOk = style.bg === 'rgb(48, 48, 48)' || style.bg === 'rgb(58, 43, 48)'
bgOk ? ok(`会话卡背景 ${style.bg}`) : bad(`会话卡背景 ${style.bg}`)
style.radius.startsWith('10') ? ok(`圆角 ${style.radius}`) : bad(`圆角 ${style.radius}`)
style.avatar === 50 ? ok('头像 50px') : bad(`头像 ${style.avatar}px`)
style.name.trim() === 'Kai' ? ok('名字 Kai') : bad(`名字 ${style.name}`)
/created|companion|boyfriend/i.test(style.preview) ? ok('有一句简介') : bad(`简介 ${style.preview}`)
style.sideShown === false ? ok('时间/操作按钮已隐藏') : bad('时间/操作按钮仍显示')

/* 2. 开场白显示后不被吞 */
const bubble = page.locator('.fy-msg-ai').filter({ hasText: /[A-Za-z]{5,}/ })
await bubble.first().waitFor({ timeout: 8000 }).catch(() => {})
const n1 = await bubble.count(); const t1 = n1 ? (await bubble.first().textContent()) : ''
n1 === 1 && t1.trim().length > 8 ? ok(`开场白已显示：${t1.trim().slice(0, 26)}…`) : bad(`开场白未显示 (n=${n1} text=${t1.slice(0, 20)})`)
await page.waitForTimeout(6000)
await page.screenshot({ path: path.join(SHOTS, 'candy_390_chat.png') })
const n2 = await bubble.count(); const t2 = n2 ? (await bubble.first().textContent()) : ''
n2 === 1 && t2 === t1 ? ok('6 秒后开场白仍在（未被清空）') : bad(`开场白被吞：n=${n2} text=${t2.slice(0, 40)}`)

/* 3. 头部 candy 化：Chat 标题 + 关闭，无 All/Unread/Favorites 与 My AI 胶囊 */
const head = await page.evaluate(() => {
  const aside = document.querySelector('aside.list-container')
  return {
    title: (aside.querySelector('.fy-list-title') || {}).textContent || '',
    close: !!aside.querySelector('#chatlist-close-btn'),
    tabs: aside.querySelectorAll('.fy-filter').length,
    myAi: aside.querySelectorAll('.fy-list-my-ai').length,
    create: aside.querySelectorAll('.fy-create-btn').length,
    search: !!aside.querySelector('#search-field'),
  }
})
head.title.trim() === 'Chat' ? ok('标题 Chat') : bad(`标题 ${head.title}`)
head.close ? ok('有关闭按钮') : bad('缺关闭按钮')
head.tabs === 0 ? ok('已移除 All/Unread/Favorites') : bad(`仍有 ${head.tabs} 个 tab`)
head.myAi === 0 && head.create === 0 ? ok('已移除 My AI 胶囊 / Create new') : bad('仍有 My AI 或 Create new')
head.search ? ok('保留搜索框') : bad('缺搜索框')

/* 4. 手机抽屉：汉堡打开、全屏、从 48px 起 */
const drawer = await page.evaluate(() => {
  const a = document.querySelector('aside.list-container')
  const r = a.getBoundingClientRect()
  return { open: document.body.classList.contains('chatlist-open'), h: Math.round(r.height), top: Math.round(r.top), w: Math.round(r.width), z: getComputedStyle(a).zIndex }
})
drawer.open ? ok('汉堡打开会话抽屉') : bad('汉堡未打开抽屉')
drawer.top === 48 && drawer.w === 390 ? ok(`抽屉 ${drawer.w}×${drawer.h} 从 ${drawer.top}px 起`) : bad(`抽屉几何 ${JSON.stringify(drawer)}`)
await page.screenshot({ path: path.join(SHOTS, 'candy_390_open.png') })

await page.click('#chatlist-close-btn')
await page.waitForTimeout(400)
const closed = await page.evaluate(() => document.body.classList.contains('chatlist-open'))
closed === false ? ok('关闭键收起抽屉') : bad('关闭键未收起')
await page.screenshot({ path: path.join(SHOTS, 'candy_390_chat.png') })

/* 5. 桌面：列表常驻 340，无关闭键 */
const dctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' })
const dp = await dctx.newPage()
await dp.route('**/api/live/**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await dp.route('**/api/live/customOneList**', (r) => r.fulfill(json({ code: '00000', data: [MY] })))
await dp.route('**/api/live/chatConversations**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await dp.route('**/api/live/chatHistory**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await dp.route('**/api/live/iceBreaker**', (r) => r.fulfill(json({ code: '00000', data: { text: 'Hey you…' } })))
await dp.route('**/*.{png,jpg,jpeg,webp,svg,gif}', (r) => r.fulfill({ status: 200, contentType: 'image/png', body: Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64') }))
await dp.addInitScript(() => localStorage.setItem('live_access_token', 'test-token'))
await dp.goto(`${BASE}/Chat.html?id=my-279`, { waitUntil: 'domcontentloaded' })
await dp.waitForTimeout(2000)
const desk = await dp.evaluate(() => {
  const a = document.querySelector('aside.list-container')
  const r = a.getBoundingClientRect()
  return { w: Math.round(r.width), visible: getComputedStyle(a).display !== 'none', closeBtn: getComputedStyle(document.querySelector('#chatlist-close-btn')).display }
})
desk.w === 340 && desk.visible ? ok('桌面列表常驻 340px') : bad(`桌面列表 ${JSON.stringify(desk)}`)
desk.closeBtn === 'none' ? ok('桌面隐藏关闭键') : bad('桌面仍显示关闭键')
await dp.screenshot({ path: path.join(SHOTS, 'candy_1440.png') })

await browser.close()
console.log(`\nPASS=${process.exitCode || 0}`)
if (fails) { console.log(`FAIL=${fails}`); process.exit(1) }
console.log('ALL PASS')
