/* 临时冒烟测试（跑完即删）：用极简 DOM stub 跑一遍 js/private_content.js，校验渲染结果 */
const fs = require('fs')
const vm = require('vm')

const code = fs.readFileSync('ai-girl-malaysia.com/js/private_content.js', 'utf8')

function mkEl(id) {
    const el = {
        id, innerHTML: '', value: '', className: '', attrs: {}, _h: {},
        classList: {
            _s: new Set(),
            add(c) { this._s.add(c); el.className = [...this._s].join(' ') },
            remove(c) { this._s.delete(c); el.className = [...this._s].join(' ') },
            contains(c) { return this._s.has(c) },
            toggle(c, on) { on ? this.add(c) : this.remove(c) }
        },
        setAttribute(k, v) { el.attrs[k] = v },
        getAttribute(k) { return el.attrs[k] },
        addEventListener(t, fn) { (el._h[t] = el._h[t] || []).push(fn) },
        focus() {},
        querySelector() { return null }
    }
    return el
}

const els = {}
;['private-content-lobby-content', 'private-content-search', 'private-content-search-toggle',
  'private-content-search-input', 'private-content-search-clear', 'private-content-lobby-tabs']
    .forEach(id => els[id] = mkEl(id))

const tabAll = mkEl('t1'); tabAll.attrs['data-tab'] = 'all'
const tabLiked = mkEl('t2'); tabLiked.attrs['data-tab'] = 'most_liked'
const calls = []

const items = [
    { id: 481, title: 'Billie 加班游戏', poster: 'p1.webp', avatar: 'a1', creator: 'Billie', price: 330, like_rate: 99, media_type: 'video', video_count: 1, duration: '09:09', image_count: 40, badge: 'new', purchase_url: 'https://x/481/purchase?origin=lobby_all' },
    { id: 49, title: 'Gym 相册', poster: 'p2.webp', avatar: 'a2', creator: 'Diana', price: 70, like_rate: 89, media_type: 'image', video_count: 0, duration: '', image_count: 5, badge: '', purchase_url: 'https://x/49/purchase?origin=lobby_all' },
    { id: 184, title: '无时长视频', poster: 'p3.webp', avatar: 'a3', creator: 'Luna', price: 120, like_rate: 91, media_type: 'mixed', video_count: 2, duration: '(Total 01:55)', image_count: 0, badge: '', purchase_url: 'https://x/184/purchase?origin=lobby_all' },
    { id: 246, title: '无时长视频2', poster: 'p4.webp', avatar: 'a4', creator: 'Mia', price: 90, like_rate: 90, media_type: 'video', video_count: 3, duration: '', image_count: 0, badge: '', purchase_url: 'https://x/246/purchase?origin=lobby_all' }
]

global.document = {
    addEventListener(t, fn) { if (t === 'DOMContentLoaded') global.__ready = fn },
    getElementById(id) { return els[id] || null },
    querySelectorAll(sel) { return sel.indexOf('[data-tab]') >= 0 ? [tabAll, tabLiked] : [] }
}
global.location = { search: '?tab=all' }
global.history = { replaceState() {} }
global.window = {}
global.fetch = function (url) {
    calls.push(url)
    return Promise.resolve({ json: () => Promise.resolve({ code: '00000', data: { list: items } }) })
}

vm.runInThisContext(code)
global.__ready()

function check(name, ok) { console.log((ok ? 'PASS  ' : 'FAIL  ') + name) }

setTimeout(function () {
    const box = els['private-content-lobby-content']
    const html = box.innerHTML
    const n = (re) => (html.match(re) || []).length

    check('fetch 首次请求 ?tab=all', calls[0] === '/api/live/privateContents?tab=all')
    check('渲染 4 张卡片', n(/id="private-content-card-/g) === 4)
    check('div 标签闭合', n(/<div/g) === n(/<\/div>/g))
    check('span 标签闭合', n(/<span/g) === n(/<\/span>/g))
    check('无未替换占位符 {{', html.indexOf('{{') === -1)
    check('grid class', html.indexOf('grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4') >= 0)
    check('封面永久模糊 scale-110 blur-[7px]', html.indexOf('object-top pointer-events-none scale-110 blur-[7px]') >= 0)
    check('底部 62% 渐变 / 顶部 h-16 渐变', html.indexOf('h-[62%] bg-linear-to-t from-[#050608]/95 via-[#050608]/55 to-transparent') >= 0 && html.indexOf('h-16 bg-linear-to-b from-black/55 to-transparent') >= 0)
    check('上半部透明链接 bottom-[44%] href=purchase_url', html.indexOf('top-0 bottom-[44%] z-10 block" aria-label="Billie" href="https://x/481/purchase?origin=lobby_all"') >= 0)
    check('角色头像 ring-1 ring-white/40', html.indexOf('w-7 h-7 object-cover object-top rounded-full ring-1 ring-white/40 shrink-0') >= 0)
    check('Unlock 红→粉渐变', html.indexOf('bg-linear-to-r from-[#FA2A55] to-[#FF6B9A]') >= 0)
    check('Confirm 黄橙渐变', html.indexOf('bg-linear-to-l from-[#fdc706] to-[#ffa800] md:h-[42px]  ') >= 0)
    check('价格橙色 / 点赞绿色 + 99%', html.indexOf('text-[#ffac0b] text-xs md:text-sm font-semibold">330</span>') >= 0 && html.indexOf('text-[#7EE2A8] text-xs font-semibold shrink-0') >= 0 && html.indexOf('99%') >= 0)
    check('描述容器 truncate-2-lines', html.indexOf('truncate-2-lines text-left text-white/80 text-[11px] md:text-xs font-medium leading-tight') >= 0)
    check('第 1 张（video+image）有播放钮 + 1 x + 09:09 + 40', html.indexOf('data-mpc-card-play') >= 0 && html.indexOf('<span>1 x</span>') >= 0 && html.indexOf('<span>09:09</span>') >= 0 && html.indexOf('<span>40</span>') >= 0)

    function card(id) {
        const key = 'id="private-content-card-' + id + '"'
        const i = html.indexOf(key)
        if (i < 0) return ''
        const j = html.indexOf('id="private-content-card-', i + 1)
        return html.slice(i, j < 0 ? html.length : j)
    }
    const c49 = card(49)
    check('第 2 张（纯图）无播放钮 / 无视频胶囊 / 有图片数 5', c49.indexOf('data-mpc-card-play') === -1 && c49.indexOf('video-icon') === -1 && c49.indexOf('<span>5</span>') >= 0 && c49.indexOf('image-icon') >= 0)
    const c184 = card(184)
    check('第 3 张（混合）有播放钮 + 视频胶囊 + 无图片胶囊', c184.indexOf('data-mpc-card-play') >= 0 && c184.indexOf('<span>2 x</span>') >= 0 && c184.indexOf('<span>(Total 01:55)</span>') >= 0 && c184.indexOf('image-icon') === -1)
    const c246 = card(246)
    const badge246 = c246.slice(c246.indexOf('data-mpc-card-center'), c246.indexOf('data-mpc-card-top'))
    const badge184 = c184.slice(c184.indexOf('data-mpc-card-center'), c184.indexOf('data-mpc-card-top'))
    console.log('        len(badge184) =', badge184.length, ' len(badge246) =', badge246.length)
    check('第 4 张（无时长）视频胶囊里不渲染时长 span',
        badge246.length > 0
        && /<span>3 x<\/span>/.test(badge246)
        && badge246.indexOf('video-icon') >= 0
        && !/<span>\d{2}:\d{2}<\/span>/.test(badge246)
        && !/<span>\(Total[^<]*\)<\/span>/.test(badge246)
        && /<span>\d{2}:\d{2}<\/span>/.test(badge184)
        && badge246.split('<span').length === badge184.split('<span').length - 1)
    check('角标 New 只在 badge=new 的卡片上', n(/>New</g) === 1)

    // 搜索（前端过滤）
    const input = els['private-content-search-input']
    const clear = els['private-content-search-clear']
    input.value = 'diana'; input._h.input[0]()
    check('搜索 diana → 1 张卡片', (box.innerHTML.match(/id="private-content-card-/g) || []).length === 1)
    check('搜索后清空按钮显示（去掉 hidden 加 flex）', clear.className.indexOf('hidden') === -1 && clear.className.indexOf('flex') >= 0)
    input.value = 'gym 相册'; input._h.input[0]()
    check('搜索命中 title（中文）', (box.innerHTML.match(/id="private-content-card-/g) || []).length === 1)
    input.value = 'zzz'; input._h.input[0]()
    check('空结果 → 居中灰色文案', box.innerHTML.indexOf('col-span-full') >= 0 && box.innerHTML.indexOf('text-center text-white/60 text-sm') >= 0)
    clear._h.click[0]()
    check('点击清空 → 恢复全部 + 按钮隐藏', (box.innerHTML.match(/id="private-content-card-/g) || []).length === 4 && clear.className.indexOf('hidden') >= 0)

    // 搜索展开
    const toggle = els['private-content-search-toggle']
    toggle._h.click[0]()
    check('点击放大镜 → 输入框展开 w-40', input.className.indexOf('w-40') >= 0 && input.className.indexOf('w-0') === -1)

    // 标签切换
    const wrap = els['private-content-lobby-tabs']
    wrap._h.click[0]({ preventDefault() {}, target: { closest: () => tabLiked } })
    setTimeout(function () {
        check('All 变为 inactive class', tabAll.className === 'shrink-0 h-9 px-4 inline-flex items-center rounded-full text-xs md:text-sm font-semibold transition-colors border bg-white/5 text-white/80 border-white/15 hover:bg-white/10')
        check('Most liked 变为 active class', tabLiked.className === 'shrink-0 h-9 px-4 inline-flex items-center rounded-full text-xs md:text-sm font-semibold transition-colors border bg-white text-black border-white')
        check('切换标签重新拉接口 ?tab=most_liked', calls[calls.length - 1] === '/api/live/privateContents?tab=most_liked')

        // Unlock → Confirm → 提交提示
        global.window.layer = { msg: (m) => console.log('        layer.msg =', m) }
        global.layer = global.window.layer
        const formEl = { classList: { hidden: true, add() { this.hidden = true }, remove() { this.hidden = false } } }
        const unlockEl = { classList: { added: '', add(c) { this.added = c } }, parentNode: { querySelector: () => formEl } }
        els['private-content-lobby-content']._h.click[0]({ target: { closest: (s) => s === '[data-content-pack-unlock]' ? unlockEl : null } })
        check('Unlock 点击 → Unlock 隐藏 / Confirm 表单显示', unlockEl.classList.added === 'hidden' && formEl.classList.hidden === false)
        let prevented = false
        els['private-content-lobby-content']._h.submit[0]({ preventDefault() { prevented = true } })
        check('Confirm 提交被拦截（不接真实购买）', prevented === true)
    }, 20)
}, 20)
