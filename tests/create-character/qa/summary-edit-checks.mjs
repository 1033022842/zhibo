/* Supplementary checks: summary per-item edit + cancel, draft reload,
 * cross-ethnicity artwork, Middle Eastern label. Local only. */
import { chromium } from 'file:///D:/ever/bmserv/node_modules/playwright-core/index.mjs'
import fs from 'node:fs'
import path from 'node:path'

const BASE = 'http://127.0.0.1:8017/ai/createCharacter.html'
const EXE = 'C:/Users/a1033/AppData/Local/ms-playwright/chromium-1228/chrome-win64/chrome.exe'
const DEEP = BASE + '?g=female&s=realistic&e=black&a=30&p=average&b=average&bu=athletic&hac=blue&ha=bob&n=Valerie+Wright&knk=master-slave&knk=discipline&knk=chains&step=7'
const stamp = new Date().toISOString().replace(/[:.]/g, '-')
const outDir = path.join('tests/create-character/qa/evidence', stamp + '_summary-edit')
const shots = path.join(outDir, 'shots')
fs.mkdirSync(shots, { recursive: true })
const rows = []
const add = (name, ok, detail) => { rows.push({ name, ok: !!ok, detail: detail == null ? '' : String(detail) }); console.log((ok ? 'PASS ' : 'FAIL ') + name + (detail ? ' :: ' + detail : '')) }

const browser = await chromium.launch({ executablePath: EXE, headless: true })
const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 1, isMobile: true, hasTouch: true })
const page = await ctx.newPage()
let createCalls = 0
await page.route('**/*', route => {
  const u = new URL(route.request().url())
  if (u.hostname !== '127.0.0.1') return route.abort()
  if (u.pathname.includes('customRoleCreate') || u.pathname.includes('customRoleRegenerate')) createCalls++
  return route.continue()
})
const waitReady = async () => {
  await page.waitForSelector('#cc8Root[data-cc8-ready="1"]', { timeout: 8000 })
  await page.waitForTimeout(350)
}
const summary = () => page.evaluate(() => {
  const root = document.querySelector('#cc8Root')
  const cards = [...document.querySelectorAll('#cc8Inner [data-act="edit"][data-field]')]
    .filter(el => el.classList.contains('cc8-card'))
    .map(el => ({ field: el.getAttribute('data-field'), text: el.innerText.replace(/\s+/g, ' ').trim(), img: (el.querySelector('img') || {}).src || '', pending: !!el.querySelector('.cc8-ph') }))
  return { screen: root.getAttribute('data-cc8-screen'), cards }
})
const openEdit = async field => {
  await page.click('#cc8Inner .cc8-card[data-act="edit"][data-field="' + field + '"]')
  await page.waitForSelector('#cc8Modal .cc8-sheet', { timeout: 4000 })
}

// 1. deep link → summary, edit ethnicity to Middle Eastern
await page.goto(DEEP, { waitUntil: 'domcontentloaded' })
await waitReady()
let st = await summary()
add('深链落摘要 screen=8', st.screen === '8', st.screen)
const beforeEth = st.cards.find(c => c.field === 'ethnicity')
add('摘要族裔初始 Black 且有图', /Black/.test(beforeEth.text) && !beforeEth.pending && beforeEth.img, beforeEth.text + ' ' + beforeEth.img)

await openEdit('ethnicity')
await page.waitForSelector('#cc8Modal .cc8-opt[data-value="middle-eastern"]', { timeout: 3000 })
const modalArt = await page.evaluate(() => {
  const m = document.querySelector('#cc8Modal .cc8-opt[data-value="middle-eastern"]')
  return { text: m && m.innerText.trim(), hasImg: !!(m && m.querySelector('img')), src: m && m.querySelector('img') && m.querySelector('img').src }
})
add('编辑弹层 Middle Eastern 有标签且有图', /Middle Eastern/.test(modalArt.text) && modalArt.hasImg, JSON.stringify(modalArt))
await page.screenshot({ path: path.join(shots, '390_edit-ethnicity-modal.png') })
await page.click('#cc8Modal .cc8-opt[data-value="middle-eastern"]')
await page.click('#cc8Modal .cc8-save')
await page.waitForTimeout(400)
st = await summary()
const afterEth = st.cards.find(c => c.field === 'ethnicity')
add('保存后摘要 Middle Eastern', /Middle Eastern/.test(afterEth.text), afterEth.text)
add('保存后族裔图切换为 ME', /eth-middle-eastern|middle-eastern/i.test(afterEth.img) && !afterEth.pending, afterEth.img)
add('编辑弹层已关闭', await page.evaluate(() => !document.querySelector('#cc8Modal')))
await page.screenshot({ path: path.join(shots, '390_summary-middle-eastern.png') })

// 2. cancel path: hairstyle unchanged
const beforeHair = st.cards.find(c => c.field === 'hairstyle').text
await openEdit('hairstyle')
await page.click('#cc8Modal .cc8-opt[data-value="straight"]')
await page.click('#cc8Modal .cc8-cancel')
await page.waitForTimeout(300)
st = await summary()
add('取消编辑不改变发型', st.cards.find(c => c.field === 'hairstyle').text === beforeHair, st.cards.find(c => c.field === 'hairstyle').text)

// 3. draft reload via plain entry (deep link params would re-apply by design)
await page.goto(BASE, { waitUntil: 'domcontentloaded' })
await waitReady()
st = await summary()
const reloadEth = st.cards.find(c => c.field === 'ethnicity')
add('重开普通入口仍在摘要 screen=8', st.screen === '8', st.screen)
add('重开普通入口保留 Middle Eastern', /Middle Eastern/.test(reloadEth.text), reloadEth.text)
add('刷新后无创建请求', createCalls === 0, createCalls)

// 4. cross-ethnicity artwork: white vs black body average
const getBodySrc = async ethnicity => {
  await page.goto(BASE + '?g=female&s=realistic&e=' + ethnicity + '&p=average&step=8', { waitUntil: 'domcontentloaded' })
  await waitReady()
  return page.evaluate(() => {
    const el = document.querySelector('#cc8Inner .cc8-card[data-field="body"] img')
    return el ? el.src : ''
  })
}
const whiteSrc = await getBodySrc('white')
const blackSrc = await getBodySrc('black')
add('跨族裔同选项图片不同', !!whiteSrc && !!blackSrc && whiteSrc !== blackSrc, whiteSrc + ' vs ' + blackSrc)
add('全程游客创建请求为 0', createCalls === 0, createCalls)

await browser.close()
const pass = rows.filter(r => r.ok).length
const report = { base: BASE, viewport: '390x844 mobile emulation', browser: 'Chromium 149.0.7827.55 (playwright-core 1.62, executable chromium-1228)', time: new Date().toISOString(), pass, fail: rows.length - pass, rows, shots }
fs.writeFileSync(path.join(outDir, 'report.json'), JSON.stringify(report, null, 2))
fs.writeFileSync(path.join(outDir, 'report.md'), '# Summary edit / reload checks\n\n' + rows.map(r => '- ' + (r.ok ? 'PASS' : 'FAIL') + ' ' + r.name + (r.detail ? ' :: `' + r.detail + '`' : '')).join('\n') + '\n\nchecks: ' + pass + ' pass / ' + (rows.length - pass) + ' fail\n')
console.log('EVIDENCE ' + path.resolve(outDir))
console.log('checks: ' + pass + ' pass / ' + (rows.length - pass) + ' fail')
process.exit(rows.some(r => !r.ok) ? 1 : 0)
