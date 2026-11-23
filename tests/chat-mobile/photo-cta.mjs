import { createRequire } from 'node:module'
import path from 'node:path'
import fs from 'node:fs'

const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const BASE = process.env.BASE || 'http://127.0.0.1:8131'
const SHOTS = path.resolve('tests/chat-mobile/shots')
fs.mkdirSync(SHOTS, { recursive: true })

const results = []
const ok = (m) => results.push(['ok', m])
const bad = (m) => results.push(['fail', m])

const json = (x) => ({ status: 200, contentType: 'application/json', body: JSON.stringify(x) })
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64')

const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, serviceWorkers: 'block' })
const page = await ctx.newPage()

let chatAction = { type: 'photo', role_key: 'home-12', count: 3, need_login: false, label: 'View photo' }
let lastSend = null

/* catch-all 必须先注册：Playwright 后注册的路由优先 */
await page.route('**/api/live/**', (r) => r.fulfill(json({ code: '00000', data: [] })))
await page.route('**/api/live/chat', (r) => {
  const body = r.request().postData() || ''
  lastSend = body
  return r.fulfill(json({ code: '00000', data: { reply: 'Sure — here is one from this morning.', action: chatAction, quota: { used: 1, limit: 20, remaining: 19 } } }))
})
await page.route('**/api/live/chatMedia**', (r) => r.fulfill(json({
  code: '00000',
  data: { enabled: true, persona: '/storage/chat_media/home-12/persona.jpg', images: ['/storage/chat_media/home-12/1.jpg', '/storage/chat_media/home-12/2.jpg'], videos: [] },
})))
await page.route('**/*.{png,jpg,jpeg,webp,svg,gif,mp4}', (r) => r.fulfill({ status: 200, contentType: 'image/png', body: PNG }))
await page.addInitScript(() => { localStorage.setItem('live_access_token', 'test-token') })

const send = async (text) => {
  await page.fill('#message_body', text)
  await page.evaluate(() => {
    const a = document.getElementById('send-question-with-token')
    const b = document.getElementById('send-question')
    const el = (a && a.getClientRects().length) ? a : b
    if (el) el.click()
  })
  await page.waitForTimeout(700)
}

await page.goto(`${BASE}/Chat.html?id=home-12`, { waitUntil: 'domcontentloaded' })
await page.waitForTimeout(1500)

/* 1. 明确请求 → 出卡 */
await send('send me a photo please')
const card = page.locator('#fy-photo-cta .fy-photo-card')
const n1 = await card.count()
n1 === 1 ? ok('明确请求照片后出现「看照片」卡片') : bad(`未出现卡片 (n=${n1})`)
if (n1 === 1) {
  const label = (await card.innerText()).replace(/\s+/g, ' ').trim()
  label.includes('View photo') ? ok(`卡片文案：${label.slice(0, 46)}`) : bad(`卡片文案异常：${label.slice(0, 46)}`)
}
await page.screenshot({ path: path.join(SHOTS, 'photo_cta_card.png') })

/* 2. 点击 → 走已有 sendChatMedia 链路 */
await card.first().click()
await page.waitForTimeout(900)
const cardGone = await page.locator('#fy-photo-cta').count()
cardGone === 0 ? ok('点击后卡片收起') : bad('点击后卡片仍在')

/* 3. 无素材 → 不出卡 */
chatAction = { type: 'none', reason: 'no_media' }
await send('can i see a selfie?')
await page.waitForTimeout(500)
const n2 = await page.locator('#fy-photo-cta').count()
n2 === 0 ? ok('无素材时不出卡') : bad('无素材仍出卡')

/* 4. 否定句 → 后端不返回 action，前端不出卡 */
chatAction = undefined
await send("don't send photos, let's just talk")
await page.waitForTimeout(500)
const n3 = await page.locator('#fy-photo-cta').count()
n3 === 0 ? ok('否定句不出卡') : bad('否定句仍出卡')

/* 5. 游客态：need_login 用登录文案 */
chatAction = { type: 'photo', role_key: 'home-12', count: 2, need_login: true, label: 'Sign in to see photos' }
await send('show me your picture')
const guestCard = page.locator('#fy-photo-cta .fy-photo-card')
const n4 = await guestCard.count()
if (n4 === 1) {
  const t = (await guestCard.innerText()).replace(/\s+/g, ' ')
  t.includes('Sign in to see photos') ? ok('游客态显示登录文案') : bad(`游客文案异常：${t.slice(0, 40)}`)
} else bad('游客态未出卡')

await browser.close()

const fails = results.filter((r) => r[0] === 'fail').length
results.forEach(([k, m]) => console.log(k === 'ok' ? `[ok] ${m}` : `[FAIL] ${m}`))
console.log(`PASS=${results.length - fails} FAIL=${fails}`)
process.exit(fails ? 1 : 0)
