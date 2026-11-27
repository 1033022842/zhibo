import { createRequire } from 'node:module'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const TOKEN = process.argv[2]
const BASE = 'https://kisss.ai/ai'
const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', ignoreHTTPSErrors: true })
await ctx.addInitScript(t => { try { localStorage.setItem('live_access_token', t) } catch (e) {} }, TOKEN)
const page = await ctx.newPage()
const reqs = [], errs = [], toasts = []
page.on('console', m => { if (m.type() === 'error') errs.push(m.text().slice(0, 150)) })
page.on('pageerror', e => errs.push('PAGEERR ' + String(e).slice(0, 150)))
page.on('response', async r => {
  const u = r.url()
  if (u.includes('/api/customVideo') || u.includes('/api/live/vip')) {
    let s = ''
    try { s = (await r.text()).slice(0, 300) } catch (e) {}
    reqs.push({ st: r.status(), u: u.replace(BASE, '').slice(0, 55), s })
  }
})

await page.goto(BASE + '/Image.html', { waitUntil: 'domcontentloaded', timeout: 60000 })
await page.waitForTimeout(3500)
await page.locator('text=Maybe later').first().click({ timeout: 4000 }).catch(() => {})
await page.locator('.wb-close').first().click({ timeout: 2000 }).catch(() => {})
await page.waitForTimeout(1500)
const wbGone = await page.evaluate(() => !document.getElementById('wb-overlay'))
console.log('WELCOME_CLOSED', wbGone)

const shape = await page.evaluate(() => {
  const inputs = Array.from(document.querySelectorAll('input,textarea')).map(e => ({ tag: e.tagName, type: e.type || '', ph: e.placeholder || '', cls: (e.className || '').slice(0, 40) }))
  const btns = Array.from(document.querySelectorAll('button')).filter(b => b.offsetParent).map(b => ({ t: (b.textContent || '').trim().slice(0, 26), id: b.id || '' }))
  return { inputs, btns: btns.slice(0, 18) }
})
console.log('SHAPE', JSON.stringify(shape).slice(0, 1400))

// 点生成
const gen = page.locator('button', { hasText: /Generate Video/i }).first()
if (await gen.count()) {
  await gen.click({ timeout: 6000 }).catch(e => console.log('CLICK_ERR', String(e).slice(0, 80)))
}
await page.waitForTimeout(6000)
const after = await page.evaluate(() => {
  const t = document.body.innerText
  return { overlay: !!document.querySelector('[class*=modal],[class*=dialog]'), text: t.slice(0, 400).replace(/\n+/g, ' | ') }
})
console.log('AFTER_CLICK', JSON.stringify(after).slice(0, 700))
console.log('REQS', JSON.stringify(reqs.slice(0, 6)).slice(0, 1200))
console.log('ERRS', JSON.stringify(errs.slice(0, 5)))
await page.screenshot({ path: 'D:/ever/douyin/douyin/tests/chat-mobile/shots/kiss_gen_after.png' })
await browser.close()
