/* Write-a-prompt path: open from entry, fill, continue to summary. */
import { chromium } from 'file:///D:/ever/bmserv/node_modules/playwright-core/index.mjs'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:8017/ai/createCharacter.html'
const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const outDir = path.join('tests/create-character/qa/evidence', stamp + '_prompt')
const shots = path.join(outDir, 'shots')
fs.mkdirSync(shots, { recursive: true })
const rows = []
const add = (n, ok, d) => { rows.push({ name: n, ok: !!ok, detail: d == null ? '' : String(d) }); console.log((ok ? 'PASS ' : 'FAIL ') + n + (d ? ' :: ' + d : '')) }

const browser = await chromium.launch({ executablePath: EXE, headless: true })
const page = await browser.newPage({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true })
let createCalls = 0
await page.route('**/*', r => {
  const u = new URL(r.request().url())
  if (u.hostname !== '127.0.0.1') return r.abort()
  if (/customRole(Create|Regenerate)/.test(u.pathname)) createCalls++
  return r.continue()
})
await page.goto(BASE, { waitUntil: 'domcontentloaded' })
await page.waitForSelector('#cc8Root[data-cc8-ready="1"]')
await page.click('[data-act="style"][data-style="Realistic"]')
await page.waitForTimeout(250)
await page.click('[data-act="prompt"]')
await page.waitForSelector('#cc8Prompt', { timeout: 4000 })
add('prompt 页打开', true)
const metrics = await page.evaluate(() => {
  const p = document.querySelector('#cc8Prompt')
  const btns = [...p.querySelectorAll('.cc8-prompt-actions button')].map(b => ({ text: b.textContent.trim(), r: b.getBoundingClientRect().toJSON() }))
  return { scrollW: p.scrollWidth, clientW: p.clientWidth, bodyScrollW: document.documentElement.scrollWidth, btns, title: p.querySelector('h2').textContent }
})
add('prompt 无横向溢出', metrics.bodyScrollW === 390 && metrics.scrollW <= metrics.clientW + 1, JSON.stringify({ doc: metrics.bodyScrollW, sw: metrics.scrollW, cw: metrics.clientW }))
add('prompt 标题含 Describe', /Describe your AI girlfriend/.test(metrics.title), metrics.title)
add('prompt 三按钮齐全', metrics.btns.map(b => b.text).join('|') === 'Surprise me|Use presets|Continue', metrics.btns.map(b => b.text).join('|'))
const cont = metrics.btns.find(b => b.text === 'Continue')
add('Continue 独占底部且完整可见', cont && cont.r.bottom <= 844 && cont.r.width > 200, JSON.stringify(cont && cont.r))
await page.screenshot({ path: path.join(shots, '390_prompt.png') })

await page.fill('#cc8PromptName', 'Aria QA')
await page.fill('#cc8Appearance', 'A woman with silver hair and a leather jacket, cinematic light.')
await page.fill('#cc8Personality', 'Dry humour, quietly protective.')
await page.click('[data-act="prompt-continue"]')
await page.waitForTimeout(500)
const st = await page.evaluate(() => ({
  screen: document.querySelector('#cc8Root').getAttribute('data-cc8-screen'),
  cards: [...document.querySelectorAll('#cc8Inner .cc8-card')].map(e => e.innerText.replace(/\s+/g, ' ').trim()),
  hasCustom: !!document.querySelector('#cc8Inner [data-field="appearance_description"]'),
  broken: [...document.images].filter(i => i.complete && i.naturalWidth === 0).length,
  overflow: document.documentElement.scrollWidth > window.innerWidth,
  placeholders: document.querySelectorAll('#cc8Inner .cc8-ph').length,
  emptyAppearance: document.querySelectorAll('#cc8Inner .cc8-card-appearance, #cc8Inner .cc8-card-hair, #cc8Inner .cc8-card-eth').length,
  sub: (document.querySelector('#cc8Inner .cc8-sub') || {}).textContent || ''
}))
add('prompt 继续后到摘要', st.screen === '8', st.screen)
add('摘要含 Custom appearance', st.hasCustom, st.cards.join(' / '))
add('摘要含姓名 Aria QA', st.cards.some(t => /Aria QA/.test(t)), st.cards.join(' / '))
add('摘要无横向溢出', !st.overflow, st.overflow)
add('prompt 摘要无 Art pending 占位', st.placeholders === 0, st.placeholders)
add('prompt 摘要无空外观卡', st.emptyAppearance === 0, st.emptyAppearance)
add('prompt 摘要副标题为 written description', /written description is saved/.test(st.sub), st.sub)
add('prompt 流程创建请求 0', createCalls === 0, createCalls)
await page.screenshot({ path: path.join(shots, '390_prompt_summary.png'), fullPage: false })

await browser.close()
const pass = rows.filter(r => r.ok).length
fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify({ base: BASE, viewport: '390x844', time: new Date().toISOString(), pass, fail: rows.length - pass, rows, metrics }, null, 2))
fs.writeFileSync(path.join(outDir, 'report.md'), '# Prompt path checks\n\n' + rows.map(r => '- ' + (r.ok ? 'PASS' : 'FAIL') + ' ' + r.name + (r.detail ? ' :: `' + r.detail + '`' : '')).join('\n') + `\n\nchecks: ${pass} pass / ${rows.length - pass} fail\n`)
console.log('EVIDENCE ' + path.resolve(outDir))
console.log(`checks: ${pass} pass / ${rows.length - pass} fail`)
process.exit(rows.some(r => !r.ok) ? 1 : 0)
