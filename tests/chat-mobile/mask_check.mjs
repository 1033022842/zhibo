import { createRequire } from 'node:module'
const require = createRequire('D:/ever/douyin/_clone_probe/drv/')
const { chromium } = require('playwright-core')

const SITES = [
  { name: 'kiss', base: 'https://kisss.ai/ai', id: 'home-26' },
  { name: 'sugus', base: 'https://www.sugus.ai/ai', id: 'home-155' },
]
const browser = await chromium.launch({
  executablePath: 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe',
  headless: true,
})
for (const s of SITES) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block', ignoreHTTPSErrors: true })
  const page = await ctx.newPage()
  await page.goto(`${s.base}/Chat.html?id=${s.id}`, { waitUntil: 'domcontentloaded', timeout: 60000 })
  // 首帧采样：遮罩是否生效（visibility hidden）
  const early = await page.evaluate(() => {
    const n = document.getElementById('chat-name')
    const vis = n ? getComputedStyle(n).visibility : 'none'
    return { txt: n ? n.textContent.trim() : null, vis, mask: document.body.classList.contains('fy-persona-wait') }
  })
  await page.waitForTimeout(3000)
  const late = await page.evaluate(() => {
    const n = document.getElementById('chat-name')
    return { txt: n ? n.textContent.trim() : null, vis: n ? getComputedStyle(n).visibility : 'none', mask: document.body.classList.contains('fy-persona-wait') }
  })
  console.log(s.name, 'EARLY', JSON.stringify(early), 'LATE', JSON.stringify(late))
  await ctx.close()
}
await browser.close()
