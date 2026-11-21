import { createRequire } from 'node:module'
import fs from 'node:fs'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const SHOTS = 'D:/ever/douyin/douyin/tests/create-character/frontend/shots'
fs.mkdirSync(SHOTS, { recursive: true })

const browser = await chromium.launch({ executablePath: EXE, headless: true })
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true })
const page = await ctx.newPage()
const errs = []
const api = []
page.on('pageerror', e => errs.push('PAGEERROR ' + e.message))
page.on('console', m => { if (m.type() === 'error') errs.push('CONSOLE ' + m.text()) })
await page.route('**/api/**', route => { api.push(route.request().url()); route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify({ code: '00000', data: {} }) }) })

await page.goto('http://127.0.0.1:4180/ai/createCharacter.html', { waitUntil: 'load' })
await page.waitForSelector('#cc8Root:not([hidden])', { timeout: 8000 })
await page.waitForTimeout(600)
console.log('url', page.url())
console.log('title', await page.title())
console.log('h1', await page.locator('.cc8-hero').first().textContent().catch(() => null))
console.log('progress', await page.locator('.cc8-progress span').count())
console.log('buttons', await page.locator('#cc8Root button').allTextContents())
console.log('body scroll', await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, iw: innerWidth, ih: innerHeight })))
await page.screenshot({ path: SHOTS + '/s0-entry.png', fullPage: false })
console.log('api calls', api)
console.log('errors', errs)
await browser.close()
