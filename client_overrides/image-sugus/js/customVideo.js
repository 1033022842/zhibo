/* Image.html — 参考站风格首屏：角色瀑布流 + 卡片详情弹窗 + 我的作品
   上传/生成视频弹窗已下线，这里只负责浏览、看详情、进聊天 */
(function () {
    var token = localStorage.getItem('live_access_token')
    var WORKS_API = '/api/aiShort/myList'

    function $(id) { return document.getElementById(id) }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    // 接口里部分文案被多重转义（例如 she&amp;#039;s），展示前逐层还原
    function decodeEntities(s) {
        s = String(s == null ? '' : s)
        for (var i = 0; i < 3 && s.indexOf('&') >= 0; i++) {
            var t = document.createElement('textarea')
            t.innerHTML = s
            if (t.value === s) break
            s = t.value
        }
        return s
    }

    /* ---------- 收藏：localStorage 按卡片（预设）id 存，收藏页 = 网格的子集 ---------- */
    var FAV_KEY = 'gs_favs'
    function favList() {
        try {
            var a = JSON.parse(localStorage.getItem(FAV_KEY) || '[]')
            return Array.isArray(a) ? a.map(String) : []
        } catch (e) { return [] }
    }
    function favHas(id) { return favList().indexOf(String(id)) >= 0 }
    function favToggle(id) {
        var a = favList(), i = a.indexOf(String(id))
        if (i >= 0) a.splice(i, 1); else a.unshift(String(id))
        try { localStorage.setItem(FAV_KEY, JSON.stringify(a)) } catch (e) {}
        return i < 0    // true = 刚收藏
    }
    var HEART_SVG = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"></path></svg>'

    /* ---------- 弹窗：通用壳（遮罩 .45 + blur8，#121212，16 圆角） ---------- */
    function openModal(id) {
        var m = $(id)
        if (!m) return
        m.classList.add('on')
        m.setAttribute('aria-hidden', 'false')
        document.documentElement.style.overflow = 'hidden'
    }

    function closeModal(id) {
        var m = $(id)
        if (!m) return
        m.classList.remove('on')
        m.setAttribute('aria-hidden', 'true')
        if (id === 'gsCardModal') {
            var v = $('gsGenVideo')
            if (v) v.pause()
            initAutoplay()
        }
        if (!document.querySelector('.gs-modal.on')) document.documentElement.style.overflow = ''
    }

    function bindModalClose() {
        document.querySelectorAll('[data-gs-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.getAttribute('data-gs-close')) })
        })
        document.querySelectorAll('.gs-modal').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) closeModal(m.id) })
        })
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return
            document.querySelectorAll('.gs-modal.on').forEach(function (m) { closeModal(m.id) })
        })
    }

    /* ---------- 首屏瀑布流：站内模板素材 ----------
       性能策略（对齐参考站 genspicy.ai）：
         · 首屏只渲染一页（36 条），每张卡只加载封面 jpg（几十 KB）
         · 视频用 data-src 挂着，IntersectionObserver 看到才真正开始下载播放
         · 卡片左上角是人设缩略图（40×50 斜贴 + 序号角标），比封面还小，
           用来一眼区分「同一提示词、不同人设」的样片
         · 翻页用数字分页，不堆 DOM，291 条也只是一页 36 条 */
    var gsList = []
    var gsFilter = 'all'
    var gsKw = ''
    var gsMode = 'explore'    // explore | works | favs
    var gsLoaded = false
    var gsLoading = false
    var gsObserver = null
    var gsWaitLayout = null
    var GS_BASE = './media/gs/'
    // 每页 = 列数 × 行数。固定 36 时，5 列会分成 7/7/7/7/8，
    // 最后一排只剩单张卡片孤零零挂着；按列数取整就不可能出现。
    // 参考站也是这个思路（5 列 × 6 行 = 30 条一页）。
    var GS_PAGE_ROWS = 6
    function pageSize() { return gsCols() * GS_PAGE_ROWS }
    var gsPage = 1

    // 列数按「网格实际可用宽度」决定（页面有左侧栏，不能直接用 window.innerWidth）。
    // 侧栏收起后图标栏仍占 100px（lg:pl-[100px]），1280 视口下网格约 1117px。
    // 参考站同宽度是 5 列、单卡约 237px，所以 5 列阈值放到 1050，
    // 否则我们只会排 4 列、单卡 270px，看起来比参考站更"胖"更空。
    function gsWidth() {
        var box = $('gsGrid')
        var w = box ? box.clientWidth : 0
        if (!w) w = document.documentElement.clientWidth || window.innerWidth || 0
        return w || 0
    }
    function gsCols() {
        var w = gsWidth()
        if (w >= 1560) return 6
        if (w >= 1050) return 5
        if (w >= 860) return 4
        if (w >= 620) return 3
        return 2
    }

    function gsSkeleton() {
        var box = $('gsGrid')
        if (!box) return
        var cols = gsCols()
        var html = ''
        for (var c = 0; c < cols; c++) {
            html += '<div class="gs-col">'
            for (var i = 0; i < 3; i++) html += '<div class="gs-skel"></div>'
            html += '</div>'
        }
        box.innerHTML = html
    }

    function loadGallery() {
        if (!$('gsGrid') || gsLoaded || gsLoading) return
        gsLoading = true
        gsSkeleton()
        fetch(GS_BASE + 'clips.json', { cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && Array.isArray(d.clips)) ? d.clips : []
                gsList = shuffleInterleave(list.filter(function (c) {
                    return c && c.id && (c.video || c.image)
                }))
            })
            .catch(function () { gsList = [] })
            .then(function () {
                gsLoading = false
                gsLoaded = true
                renderGallery()
                // 内置浏览器 / 分屏场景下，首次渲染时视口宽度可能还没稳定，
                // 且宽度变化不一定触发 resize。这里在最初几秒内自适应校正列数。
                var tries = 0
                var tid = setInterval(function () {
                    tries++
                    if (gsCols() !== gsRenderedCols) renderGallery()
                    if (tries >= 20) clearInterval(tid)
                }, 300)
                setTimeout(function () { clearInterval(tid) }, 8000)
            })
    }


    /* ---------- 排列：同姿势的样片不要扎堆 ----------
       原始 clips.json 是按卡片分组排的，一页 36 条会整屏都是同一个姿势。
       做法：先按卡片分组，再轮转交叉（每组取一条轮流铺开），最后整体洗一遍。
       这样每一页都混着多种姿势，同组样片也不会出现在同一处。 */
    function shuffleInterleave(list) {
        var byCard = {}, order = []
        list.forEach(function (c) {
            var k = String(c.id)
            if (!byCard[k]) { byCard[k] = []; order.push(k) }
            byCard[k].push(c)
        })
        // 卡片顺序随机，组内也随机
        order = shuffled(order)
        order.forEach(function (k) { byCard[k] = shuffled(byCard[k]) })

        var out = [], more = true
        while (more) {
            more = false
            for (var i = 0; i < order.length; i++) {
                var g = byCard[order[i]]
                if (g.length) {
                    out.push(g.shift())
                    if (g.length) more = true
                }
            }
        }
        return out
    }

    function shuffled(arr) {
        var a = arr.slice()
        for (var i = a.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1))
            var t = a[i]; a[i] = a[j]; a[j] = t
        }
        return a
    }

    function gsView() {
        var list = gsList.slice()
        // 收藏页：只保留收藏卡片（预设 id）的样片
        if (gsMode === 'favs') {
            var favs = favList()
            list = list.filter(function (c) { return favs.indexOf(String(c.id)) >= 0 })
        }
        if (gsKw) {
            var k = gsKw.toLowerCase()
            list = list.filter(function (c) { return decodeEntities(c.title).toLowerCase().indexOf(k) >= 0 })
        }
        if (gsFilter === 'trending') {
            list.sort(function (a, b) { return (Number(b.duration) || 0) - (Number(a.duration) || 0) })
        } else if (gsFilter === 'new') {
            list.sort(function (a, b) { return String(b.id).localeCompare(String(a.id)) })
        } else if (gsFilter === 'vip') {
            list = list.slice(0, Math.ceil(list.length / 4))
        } else if (gsFilter === 'free') {
            list = list.slice(Math.ceil(list.length / 4))
        }
        return list
    }

    /* 分页切分：保证每一页的条数都是「列数」的整数倍。
       只按固定条数切的话，尾页余数常常是 1 或 2，瀑布流最后一行就会
       孤零零挂一两张卡。这里把总数均摊到各页，每页按列数取整，
       多出来的零头（<列数）直接不展示 —— 那些都是同卡片的多余样片。 */
    function pagePlan() {
        var all = gsView()
        var n = all.length
        var cols = gsCols()
        var rowsPerPage = GS_PAGE_ROWS
        if (!n) return { total: 1, sizes: [], shown: 0 }
        var nPages = Math.max(1, Math.ceil(n / (cols * rowsPerPage)))
        var groupsPerPage = Math.floor(n / (cols * nPages))   // 每页占几个「列数」组
        if (groupsPerPage < 1) { nPages = 1; groupsPerPage = Math.floor(n / cols) }
        if (groupsPerPage < 1) { groupsPerPage = 1 }
        var sizes = []
        var used = 0
        for (var i = 0; i < nPages; i++) { sizes.push(groupsPerPage * cols); used += groupsPerPage * cols }
        // 剩余按整组补给前面的页
        var rest = n - used
        var extra = Math.floor(rest / cols)
        for (var j = 0; j < extra; j++) { sizes[j % nPages] += cols }
        var shown = sizes.reduce(function (a, b) { return a + b }, 0)
        return { total: nPages, sizes: sizes, shown: shown }
    }

    function gsPageCount() {
        return pagePlan().total
    }

    /** 当前页的卡片（条数恒为列数的整数倍，末排不会出现孤卡） */
    function gsPageItems() {
        var plan = pagePlan()
        var all = gsView()
        var start = 0
        for (var i = 0; i < gsPage - 1 && i < plan.sizes.length; i++) start += plan.sizes[i]
        var size = plan.sizes[gsPage - 1] || 0
        return all.slice(start, start + size)
    }

    // 分页条：首尾 + 当前页前后各 1 页，其余用省略号，样式对齐参考站
    function renderPager() {
        var nav = $('gsPager')
        if (!nav) return
        var total = gsPageCount()
        if (total <= 1) { nav.innerHTML = ''; nav.style.display = 'none'; return }
        nav.style.display = 'flex'

        var pages = []
        for (var p = 1; p <= total; p++) {
            if (p === 1 || p === total || Math.abs(p - gsPage) <= 1) pages.push(p)
            else if (pages[pages.length - 1] !== '…') pages.push('…')
        }
        var arrow = function (dir, disabled) {
            var d = dir === 'prev' ? 'M18 9l-6 6-6-6' : 'M6 9l6 6 6-6'
            return '<button type="button" class="gs-pager__arrow"' + (disabled ? ' disabled' : '') +
                ' data-page="' + (disabled ? '' : (dir === 'prev' ? gsPage - 1 : gsPage + 1)) + '"' +
                ' aria-label="' + (dir === 'prev' ? 'Previous page' : 'Next page') + '">' +
                '<svg width="24" height="24" fill="none" viewBox="0 0 24 24"><path d="' + d +
                '" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>' +
                '</button>'
        }
        var html = arrow('prev', gsPage <= 1)
        pages.forEach(function (p) {
            if (p === '…') html += '<span class="gs-pager__gap">…</span>'
            else html += '<button type="button" class="gs-pager__page' +
                (p === gsPage ? ' is-active' : '') + '" data-page="' + p + '">' + p + '</button>'
        })
        html += arrow('next', gsPage >= total)
        nav.innerHTML = html
    }

    function gotoPage(p) {
        var total = gsPageCount()
        p = Math.max(1, Math.min(total, p | 0 || 1))
        if (p === gsPage) return
        gsPage = p
        // 翻页先回顶部，否则新一页在视口外，用户以为没反应
        var wrap = document.querySelector('.gs-page')
        if (wrap) wrap.scrollIntoView({ block: 'start' })
        renderGallery()
    }

    // 只让视口内的卡片播放，滚出即暂停（几十个视频同时解码会卡）
    // 视频真正出画才淡入；加载失败则一直显示封面，不会留黑框
    function initAutoplay() {
        var vids = document.querySelectorAll('#gsGrid video')
        if (!vids.length) return
        vids.forEach(function (v) {
            if (v.getAttribute('data-gs-bound')) return
            v.setAttribute('data-gs-bound', '1')
            v.addEventListener('playing', function () { v.classList.add('is-on') })
            v.addEventListener('error', function () { v.classList.remove('is-on') })
        })
        if (!('IntersectionObserver' in window)) {
            vids.forEach(function (v) {
                var s = v.getAttribute('data-src')
                if (s && !v.getAttribute('src')) v.src = s
                v.play && v.play().catch(function () {})
            })
            return
        }
        if (gsObserver) gsObserver.disconnect()
        gsObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var v = en.target
                if (en.isIntersecting) {
                    loadAndPlay(v)
                } else {
                    v.pause()
                }
            })
        }, { root: null, rootMargin: '200px 0px', threshold: 0.1 })
        vids.forEach(function (v) { gsObserver.observe(v) })
        // 兜底：内嵌 webview / 分屏下 IntersectionObserver 可能完全不回调
        // （实测 IAB 里手动 new IO 也不触发），用 scroll/rAF 主动判可视区。
        bindScrollFallback()
    }

    function loadAndPlay(v) {
        if (!v.getAttribute('src')) {
            var s = v.getAttribute('data-src')
            if (s) v.src = s
        }
        v.preload = 'auto'
        var p = v.play()
        if (p && p.catch) p.catch(function () {})
    }

    // 只在「IO 不可用或被吞」时兜底，正常浏览器走 IO 就不必每帧算布局
    var gsFallbackBound = false
    function bindScrollFallback() {
        if (gsFallbackBound) return
        gsFallbackBound = true
        var ticking = false
        var run = function () {
            ticking = false
            var vh = window.innerHeight || document.documentElement.clientHeight
            var vids = document.querySelectorAll('#gsGrid video')
            for (var i = 0; i < vids.length; i++) {
                var v = vids[i]
                var r = v.getBoundingClientRect()
                if (r.bottom > -120 && r.top < vh + 200) {
                    if (!v.getAttribute('src')) loadAndPlay(v)
                } else if (!v.paused) {
                    v.pause()
                }
            }
        }
        var onScroll = function () {
            if (!ticking) { ticking = true; requestAnimationFrame(run) }
        }
        window.addEventListener('scroll', onScroll, { passive: true })
        window.addEventListener('resize', onScroll, { passive: true })
        // 首屏先跑一次；IO 正常的话这里只是把已经可见的补齐，无副作用
        setTimeout(run, 300)
        setTimeout(run, 1200)
    }

    // 按卡片原始宽高比分配列，尽量让各列高度接近（源站也是这个思路）
    var gsRenderedCols = 0
    function distribute(list) {
        var cols = gsCols()
        gsRenderedCols = cols
        var buckets = []
        var heights = []
        for (var i = 0; i < cols; i++) { buckets.push([]); heights.push(0) }
        list.forEach(function (c) {
            var ar = (Number(c.w) && Number(c.h)) ? Number(c.w) / Number(c.h) : 0.5625
            var target = 0
            for (var k = 1; k < cols; k++) if (heights[k] < heights[target]) target = k
            buckets[target].push({ clip: c, ar: ar })
            // 列宽相同，卡片高度与 1/ar 成正比
            heights[target] += 1 / ar
        })
        return buckets
    }

    function cardHtml(c, ar) {
        var title = esc(decodeEntities(c.title))
        var poster = esc(GS_BASE + c.poster)
        var tags = (c.tags || []).slice(0, 1)
        var tagHtml = tags.length
            ? '<div class="gs-card__tags"><span class="gs-card__tag">' + esc(tags[0]) + '</span></div>'
            : ''

        // 左上角人设缩略图：同一条提示词有很多人设版本，靠它区分
        var mark = ''
        if (c.persona) {
            mark = '<div class="gs-card__creator"><span class="gs-card__mark" ' +
                'style="background-image:url(&quot;' + esc(GS_BASE + c.persona) + '&quot;)"></span></div>'
        }

        // 首屏只加载封面；视频挂在 data-src，进入视口才下载
        var media
        if (c.video) {
            media = '<img class="gs-card__media" src="' + poster + '" alt="' + title +
                '" loading="lazy" decoding="async">' +
                '<video class="gs-card__video" data-src="' + esc(GS_BASE + c.video) + '" poster="' + poster +
                '" muted loop playsinline webkit-playsinline preload="none"></video>'
        } else {
            media = '<img class="gs-card__media" src="' + esc(GS_BASE + (c.image || c.poster)) +
                '" alt="' + title + '" loading="lazy" decoding="async">'
        }

        // 右上角收藏爱心：点它不进详情（委托里拦掉）
        var like = '<span class="gs-card__like' + (favHas(c.id) ? ' on' : '') + '" data-gs-fav="' +
            esc(c.id) + '" role="button" aria-label="Favorite">' + HEART_SVG + '</span>'

        return '<a class="gs-card" href="javascript:void(0)" data-gs-card="' + esc(c.slug || c.id) + '"' +
            ' data-gs-id="' + esc(c.id) + '"' +
            ' style="--ar:' + ar.toFixed(4) + '">' +
            '<div class="gs-card__frame">' + media + like + '</div>' +
            mark +
            '<span class="gs-card__details">' +
            '<span class="gs-card__title">' + title + '</span>' +
            '<span class="gs-card__creator-name">@' + esc(c.creator || 'genspicy') + '</span>' +
            tagHtml +
            '</span></a>'
    }

    function renderGallery() {
        var box = $('gsGrid')
        if (!box) return
        var list = gsView()
        // 后台/未布局时 clientWidth 为 0，此时算列数会退回 2 列（卡片显得很大）。
        // 先渲染骨架，等真正拿到宽度再重排。
        //
        // 这里不能像早期版本那样只轮询 10 秒就放弃：Explore 面板首帧可能是
        // display:none，宽度要等切到该 tab 才出现，超过 10 秒很常见，放弃后
        // 画廊会永久停在骨架屏。改为「宽度一出现就重排」——ResizeObserver 负责
        // 尺寸变化，轮询只做兜底且不设上限。
        if (!gsWidth() && list.length) {
            gsSkeleton()
            if (!gsWaitLayout) {
                gsWaitLayout = setInterval(function () {
                    if (gsWidth()) {
                        clearInterval(gsWaitLayout)
                        gsWaitLayout = null
                        renderGallery()
                    }
                }, 250)
            }
            return
        }
        if (gsWaitLayout) {
            clearInterval(gsWaitLayout)
            gsWaitLayout = null
        }
        if (!list.length) {
            var emptyTxt = gsMode === 'favs'
                ? 'No favorites yet — tap the ♥ on any card.'
                : 'Nothing here yet.'
            box.innerHTML = '<div class="gs-empty">' + emptyTxt + '</div>'
            renderPager()
            return
        }
        // 只渲染当前页，避免 291 张卡片同时进 DOM
        var totalPages = gsPageCount()
        if (gsPage > totalPages) gsPage = totalPages
        var buckets = distribute(gsPageItems())
        box.innerHTML = buckets.map(function (bucket) {
            var inner = bucket.map(function (it) { return cardHtml(it.clip, it.ar) }).join('')
            return '<div class="gs-col">' + inner + '</div>'
        }).join('')
        renderPager()
        initAutoplay()
    }

    /* ---------- 卡片弹窗 = 预设生成面板（AiShort 通道） ----------
       每条卡片就是一个预设（code = 卡片 id，如 gs-17），后端按 code 取
       专属提示词 + 4 层 LoRA 链，不是随机风格池。 */
    var API = '/api/aiShort'
    var gsPresetMap = {}          // code -> /presets 返回的元信息
    var gsPresetsLoaded = false

    var gen = {
        file: null,
        preset: null,             // { code, title, kind, duration_sec, category }
        duration: 0,              // 用户选的时长；0 = 用预设默认
        remain: null,             // null = 未知（提交后才拿到）
        balance: 0,
        submitting: false,
        pollTimer: null,
        current: null,
        taskId: 0
    }

    function authJson(url, opts) {
        opts = opts || {}
        opts.headers = Object.assign({}, token ? { Authorization: 'Bearer ' + token } : {}, opts.headers || {})
        opts.cache = 'no-store'
        return fetch(url, opts).then(function (r) { return r.json() })
    }

    function toast(msg) {
        var el = $('gsToast')
        if (!el) {
            el = document.createElement('div')
            el.id = 'gsToast'
            el.style.cssText = 'position:fixed;left:50%;top:80px;transform:translateX(-50%);z-index:1200;padding:10px 18px;' +
                'border-radius:12px;background:rgba(20,20,22,.96);border:1px solid rgba(255,255,255,.14);color:#fff;' +
                'font-size:13px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.45);max-width:80vw'
            document.body.appendChild(el)
        }
        el.textContent = msg
        el.style.display = 'block'
        clearTimeout(el.__t)
        el.__t = setTimeout(function () { el.style.display = 'none' }, 3200)
    }

    function showNote(msg) {
        var n = $('gsGenNote')
        if (!n) return
        n.textContent = msg
        n.style.display = ''
    }

    // 弹窗爱心与收藏状态对齐（打开弹窗、点击收藏后都要同步）
    function syncGenLike() {
        var b = $('gsGenLike')
        if (!b || !gen.current) return
        b.classList.toggle('on', favHas(gen.current.id))
    }

    function setState(txt) {
        var s = $('gsGenState')
        if (!s) return
        s.textContent = txt || ''
        s.style.display = txt ? '' : 'none'
    }

    // 预设列表是公开接口；拿到后按 code 建索引，用于时长/分类等元信息
    function loadPresets() {
        if (gsPresetsLoaded) return Promise.resolve(gsPresetMap)
        return fetch(API + '/presets', { cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && d.data && d.data.list) ? d.data.list : []
                list.forEach(function (p) { if (p && p.code) gsPresetMap[p.code] = p })
                gsPresetsLoaded = true
                return gsPresetMap
            })
            .catch(function () { return gsPresetMap })
    }

    function presetOf(card) {
        var p = gsPresetMap[card.id] || {}
        return {
            code: card.id,
            title: p.title || decodeEntities(card.title),
            kind: p.kind || card.kind || 'video',
            duration_sec: Number(p.duration_sec) || Number(card.duration) || 8,
            category: p.category || (card.tags && card.tags[0]) || 'other'
        }
    }

    // 标题与标签：预设元信息可能晚到，渲染单独抽出来便于二次刷新
    function renderHead(c) {
        if (!c || !gen.preset) return
        $('gsGenTitle').textContent = gen.preset.title
        var pills = [
            '<span class="gs-gen__owner">@' + esc(c.creator || 'genspicy') + '</span>',
            '<span class="gs-gen__dot">·</span>'
        ]
        var tags = [gen.preset.category]
        tags.push(gen.preset.kind === 'image' ? 'image' : gen.preset.duration_sec + 's')
        $('gsGenPills').innerHTML = pills.concat(tags.map(function (t) {
            return '<span class="gs-gen__pill">' + esc(t) + '</span>'
        })).join('')
    }

    // 卡片本身就是预设，不需要再选风格；这里只让用户微调时长
    function renderAddons() {
        var box = $('gsGenAddons')
        if (!box) return
        var p = gen.preset
        if (!p) { box.innerHTML = ''; return }
        // 时长不再让用户选：所有卡片固定用预设时长（10s），
        // 这里改成上传指引，说明什么样的图能出好结果。
        box.innerHTML = '<div class="gs-gen__tips">' +
            '<span class="gs-gen__tips-hd">Generate Tips</span>' +
            'Please upload a clear, original waist-up photo of one person facing the camera, ' +
            'with the full face visible.<br>' +
            'Please keep the image clean: no face coverings, other people, body parts.' +
            '</div>'
    }

    /** 未上传图片时按钮置灰并拦住提交 */
    function syncSubmitState() {
        var btn = $('gsGenSubmit'), txt = $('gsGenSubmitText')
        if (!btn) return
        var ready = !!gen.file && !gen.submitting
        btn.disabled = !ready
        btn.classList.toggle('is-locked', !gen.file)
        if (!gen.submitting) txt.textContent = gen.file ? 'Generate' : 'Upload image first'
    }

    function renderCost() {
        var el = $('gsGenCost')
        if (!el) return
        if (gen.remain === null || gen.remain < 0) { el.textContent = 'Free'; return }
        el.textContent = gen.remain > 0 ? 'Free' : 'Paid'
    }

    // 右侧画面回到「预设的参考片」
    function showReference() {
        var c = gen.current
        var outImg = $('gsGenOutImg'), v = $('gsGenVideo')
        if (!outImg || !v) return
        outImg.style.display = 'none'
        outImg.classList.remove('is-sharp')
        var src = c && c.video ? (GS_BASE + c.video) : ''
        if (src) {
            v.style.display = 'block'
            v.muted = true
            v.loop = true
            v.src = src
            var p = v.play()
            if (p && p.catch) p.catch(function () {})
            var snd = $('gsGenSound')
            if (snd) { snd.style.display = 'inline-flex'; snd.classList.add('is-off') }
        } else {
            v.pause()
            v.style.display = 'none'
            outImg.src = GS_BASE + ((c && (c.image || c.poster)) || '')
            outImg.style.display = 'block'
            var s2 = $('gsGenSound')
            if (s2) s2.style.display = 'none'
        }
        setState('Preview')
    }

    /** 人设大图：persona_020.webp -> prev_persona_020.webp */
    function previewOf(c) {
        if (c && c.persona) return 'prev_' + c.persona
        return (c && (c.poster || c.image)) || ''
    }

    function openCardModal(key) {
        // key 是 slug（每条样片唯一）。同一张卡片有十几条样片，它们的 id 相同，
        // 早期版本按 id 查找会永远命中第一条，导致点 #2 / #3 播的都是 #1。
        var c = null
        for (var i = 0; i < gsList.length; i++) {
            var it = gsList[i]
            if (String(it.slug || '') === String(key)) { c = it; break }
        }
        if (!c) {
            for (var j = 0; j < gsList.length; j++) {
                if (String(gsList[j].id) === String(key)) { c = gsList[j]; break }
            }
        }
        if (!c || !$('gsCardModal')) return
        if (gen.pollTimer) { clearInterval(gen.pollTimer); gen.pollTimer = null }
        gen.current = c
        gen.file = null
        gen.duration = 0
        gen.taskId = 0
        gen.submitting = false
        gen.preset = presetOf(c)

        // 先把列表里的视频停掉，省解码
        document.querySelectorAll('#gsGrid video').forEach(function (x) { x.pause() })

        renderHead(c)

        // 左框：待上传的人像。默认展示该样片所属人设的缩略图做预览；
        // 用户必须自己上传一张才会解锁提交按钮。
        // 弹窗左框约 260×360，用左上角那张 120×156 的角标缩略图放大必然糊。
        // 这里换成同人设的 480×640 大图（prev_persona_XXX.webp）。
        var pv = $('gsGenPreview')
        pv.src = GS_BASE + previewOf(c)
        pv.style.display = 'block'
        $('gsGenUploadText').textContent = 'upload image'
        $('gsGenFile').value = ''

        showReference()
        $('gsGenNote').style.display = 'none'
        $('gsGenNote').textContent = ''
        renderAddons()
        renderCost()
        syncSubmitState()
        syncGenLike()
        openModal('gsCardModal')
        loadPresets().then(function () {
            // 预设元信息到齐后刷新标题、标签与时长选项（首次打开时可能还没加载完）
            if (gen.current !== c) return
            gen.preset = presetOf(c)
            renderHead(c)
            renderAddons()
        })
    }

    function pickFile(f) {
        if (!f) return
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { toast('JPG / PNG / WebP only'); return }
        if (f.size > 10 * 1024 * 1024) { toast('Image must be under 10MB'); return }
        gen.file = f
        var img = $('gsGenPreview')
        img.src = URL.createObjectURL(f)
        img.style.display = 'block'
        $('gsGenUploadText').textContent = 'change image'
        syncSubmitState()
    }

    function submitGen() {
        if (gen.submitting) return
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
            return
        }
        if (!gen.file) { toast('Please upload her picture first'); return }
        if (!gen.preset) return
        doSubmit(false)
    }

    function doSubmit(confirmed) {
        gen.submitting = true
        var btn = $('gsGenSubmit'), txt = $('gsGenSubmitText')
        btn.disabled = true
        txt.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', gen.file)
        fd.append('preset_code', gen.preset.code)
        fd.append('agreed_policy', '1')
        if (gen.duration && gen.duration !== gen.preset.duration_sec) {
            fd.append('duration_sec', String(gen.duration))
        }
        if (confirmed === true) fd.append('confirm_paid', '1')

        authJson(API + '/submit', { method: 'POST', body: fd }).then(function (d) {
            gen.submitting = false
            syncSubmitState()
            if (!d || d.code !== '00000') {
                if (d && d.code === 'C0102') {
                    // 免费额度用尽：服务端已给出价格文案，确认后带 confirm_paid 重提
                    if (window.confirm(d.msg || 'This one costs tokens. Continue?')) doSubmit(true)
                    return
                }
                if (d && d.code === 'C0100') { showNote(d.msg || 'Not enough tokens.'); return }
                if (d && d.code === 'A0100') {
                    location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
                    return
                }
                toast((d && d.msg) || 'Submit failed')
                return
            }
            var data = d.data || {}
            if (typeof data.remain === 'number') gen.remain = data.remain
            if (typeof data.balance === 'number') gen.balance = data.balance
            gen.taskId = Number(data.task_id) || 0
            if (window.refreshTokenBalance) window.refreshTokenBalance()
            renderCost()
            setState('Queued…')
            watchResult(gen.taskId)
        }).catch(function () {
            gen.submitting = false
            syncSubmitState()
            toast('Network error')
        })
    }

    // 按 task_id 轮询；5 秒一次、最长约 20 分钟（8 秒片实际约 10 分钟）
    function watchResult(taskId) {
        if (gen.pollTimer) clearInterval(gen.pollTimer)
        if (!taskId) { setState('Submitted — check My Works'); return }
        var tries = 0
        gen.pollTimer = setInterval(function () {
            tries++
            if (tries > 240) {
                clearInterval(gen.pollTimer)
                gen.pollTimer = null
                setState('Still generating — check My Works later')
                return
            }
            authJson(API + '/task?task_id=' + taskId).then(function (d) {
                if (!d || d.code !== '00000' || !d.data) return
                var t = d.data
                if (t.status === 'completed' && t.video_url) {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    showResult(t)
                } else if (t.status === 'failed' || t.status === 'expired') {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    setState(t.status === 'expired'
                        ? 'Expired — please retry'
                        : ('Failed — ' + (t.error || 'please try again')))
                } else {
                    var meta = STATUS_META[t.status] || {}
                    setState(meta.label || 'Generating…')
                }
            }).catch(function () {})
        }, 5000)
    }

    function showResult(t) {
        setState('')
        var img = $('gsGenOutImg'), v = $('gsGenVideo')
        if (t.kind === 'image') {
            img.src = t.video_url
            img.classList.add('is-sharp')
            img.style.display = 'block'
            v.pause()
            v.style.display = 'none'
            $('gsGenSound').style.display = 'none'
        } else {
            img.style.display = 'none'
            img.classList.remove('is-sharp')
            v.src = t.video_url
            v.style.display = 'block'
            v.muted = false
            v.loop = true
            var p = v.play()
            if (p && p.catch) p.catch(function () {})
            var snd = $('gsGenSound')
            if (snd) { snd.style.display = 'inline-flex'; snd.classList.remove('is-off') }
        }
    }

    function showQuotaInfo() {
        var n = $('gsGenNote')
        if (n.style.display !== 'none' && n.textContent) { n.style.display = 'none'; return }
        if (!token) { showNote('Sign in to generate.'); return }
        var parts = []
        if (gen.remain === null) parts.push('Free quota is checked on submit')
        else if (gen.remain < 0) parts.push('Unlimited free generations')
        else parts.push('Free left: ' + gen.remain)
        if (gen.balance) parts.push('balance ' + gen.balance + ' tokens')
        parts.push('8s clip takes ~10 min')
        showNote(parts.join(' · '))
    }

    function bindGen() {
        if (!$('gsGenSubmit')) return
        $('gsGenUpload').addEventListener('click', function () { $('gsGenFile').click() })
        $('gsGenFile').addEventListener('change', function () {
            if (this.files && this.files[0]) pickFile(this.files[0])
        })
        $('gsGenSubmit').addEventListener('click', submitGen)
        $('gsGenInfo').addEventListener('click', showQuotaInfo)
        // 弹窗里的爱心 = 收藏当前卡片，与网格卡片上的爱心同一份状态
        $('gsGenLike').addEventListener('click', function () {
            if (!gen.current) return
            favToggle(gen.current.id)
            syncGenLike()
            // 网格里同卡片的爱心同步；收藏页里取消收藏则当场移除
            var on = favHas(gen.current.id)
            document.querySelectorAll('[data-gs-fav="' + gen.current.id + '"]').forEach(function (s) {
                s.classList.toggle('on', on)
            })
            if (gsMode === 'favs') renderGallery()
        })
        var snd = $('gsGenSound')
        if (snd) {
            snd.addEventListener('click', function () {
                var v = $('gsGenVideo')
                if (!v || !v.getAttribute('src')) return
                v.muted = !v.muted
                snd.classList.toggle('is-off', v.muted)
                if (!v.muted) {
                    var p = v.play()
                    if (p && p.catch) p.catch(function () {})
                }
            })
        }
        // 卡片即预设，隐藏旧的「Image to Video / Outfit」风格切换
        var tabs = document.querySelector('.gs-gen__tabs')
        if (tabs) tabs.style.display = 'none'
    }

    function bindGallery() {
        var grid = $('gsGrid')
        if (grid && !grid.getAttribute('data-gs-bound')) {
            grid.setAttribute('data-gs-bound', '1')
            grid.addEventListener('click', function (e) {
                // 收藏爱心：先于卡片详情处理，点爱心不弹窗
                var fav = e.target.closest ? e.target.closest('[data-gs-fav]') : null
                if (fav) {
                    e.preventDefault()
                    e.stopPropagation()
                    var fid = fav.getAttribute('data-gs-fav')
                    favToggle(fid)
                    var on = favHas(fid)
                    fav.classList.toggle('on', on)
                    // 详情弹窗若正开着这张卡，弹窗里的爱心同步
                    if (gen.current && String(gen.current.id) === String(fid)) syncGenLike()
                    // 收藏页里取消收藏，卡片要当场消失
                    if (gsMode === 'favs') renderGallery()
                    return
                }
                var a = e.target.closest ? e.target.closest('[data-gs-card]') : null
                if (!a) return
                e.preventDefault()
                openCardModal(a.getAttribute('data-gs-card'))
            })
            // 列数随视口变化，重排一次（跟参考站一样按断点切换）
            // ResizeObserver 覆盖「容器尺寸变了但没触发 window.resize」的情况
            // （内置浏览器/分屏首帧宽度未定），window.resize 兜底老浏览器
            var lastCols = gsCols()
            var rt = null
            var schedule = function () {
                if (rt) clearTimeout(rt)
                rt = setTimeout(function () {
                    var now = gsCols()
                    // 列数没变也要重排：首帧宽度为 0 时骨架屏用的就是 2 列，
                    // 之后宽度恢复但列数仍算 2 列（窄容器）时，只比列数会永远不渲染。
                    if (gsLoaded && (!gsRenderedCols || now !== lastCols || gsWaitLayout)) {
                        lastCols = now
                        renderGallery()
                    }
                }, 160)
            }
            if ('ResizeObserver' in window) {
                new ResizeObserver(schedule).observe(grid)
            }
            window.addEventListener('resize', schedule, { passive: true })
        }
        var bar = document.querySelector('.gs-filter')
        if (bar) {
            bar.addEventListener('click', function (e) {
                var chip = e.target.closest ? e.target.closest('[data-gs-filter]') : null
                if (!chip) return
                gsFilter = chip.getAttribute('data-gs-filter')
                gsPage = 1          // 换筛选条件回到第 1 页，否则可能停在越界页
                bar.querySelectorAll('[data-gs-filter]').forEach(function (c) { c.classList.remove('on') })
                chip.classList.add('on')
                renderGallery()
            })
        }
        // 分页条（数字 + 左右箭头）
        var pager = $('gsPager')
        if (pager && !pager.getAttribute('data-gs-bound')) {
            pager.setAttribute('data-gs-bound', '1')
            pager.addEventListener('click', function (e) {
                var b = e.target.closest ? e.target.closest('[data-page]') : null
                if (!b || b.disabled) return
                var p = b.getAttribute('data-page')
                if (p) gotoPage(parseInt(p, 10))
            })
        }
        var sBtn = $('gsSearchBtn'), sWrap = $('gsSearchWrap'), sInput = $('gsSearchInput')
        if (sBtn && sWrap && sInput) {
            sBtn.addEventListener('click', function () {
                var shown = sWrap.style.display !== 'none'
                sWrap.style.display = shown ? 'none' : ''
                if (shown) {
                    sInput.value = ''
                    gsKw = ''
                    gsPage = 1
                    renderGallery()
                } else {
                    sInput.focus()
                }
            })
            sInput.addEventListener('input', function () {
                gsKw = sInput.value.trim()
                gsPage = 1
                renderGallery()
            })
        }
        loadGallery()
    }

    /* ---------- 我的作品（历史生成记录，只读） ---------- */
    var STATUS_META = {
        pending: { label: 'Queued', cls: 'wait' },
        accepted: { label: 'Queued', cls: 'wait' },
        processing: { label: 'Generating…', cls: 'run' },
        completed: { label: 'Ready ✨', cls: 'ok' },
        failed: { label: 'Failed', cls: 'err' },
        expired: { label: 'Expired', cls: 'wait' }
    }

    function workCard(t) {
        var m = STATUS_META[t.status] || { label: t.status, cls: 'wait' }
        var body
        if (t.status === 'completed' && t.video_url) {
            body = t.kind === 'image'
                ? '<a href="' + esc(t.video_url) + '" target="_blank"><img src="' + esc(t.cover_url || t.video_url) + '" style="width:100%;display:block;background:#000" alt=""></a>'
                : '<video controls playsinline preload="metadata" src="' + esc(t.video_url) + '" poster="' + esc(t.cover_url || '') + '"></video>'
        } else if (t.status === 'failed') {
            body = '<div class="cv-wait-body">Generation failed — please retry later.</div>'
        } else {
            body = '<div class="cv-wait-body">⏳ Still generating…<br>an 8s clip takes ~10 min</div>'
        }
        return '<div class="cv-work">' +
            '<div class="hd"><span class="n">' + esc(t.title || t.preset_code || 'Preset clip') + '</span>' +
            '<span class="cv-badge ' + m.cls + '">' + m.label + '</span></div>' +
            body +
            '<div class="ft"><span>' + esc((t.created_at || '').replace('T', ' ').slice(0, 16)) + '</span>' +
            (t.status === 'completed' && t.video_url ? '<a href="' + esc(t.video_url) + '" download>⬇ Download</a>' : '') +
            '</div></div>'
    }

    var lastSig = ''
    var lastList = null
    var pendingRender = false
    var pollTimer = null

    function anyVideoPlaying() {
        var vs = $('cvWorksGrid') ? $('cvWorksGrid').querySelectorAll('video') : []
        for (var i = 0; i < vs.length; i++) {
            if (!vs[i].paused && !vs[i].ended) return true
        }
        return false
    }

    function tryPendingRender() {
        if (pendingRender && !anyVideoPlaying() && lastList) {
            pendingRender = false
            renderWorks(lastList)
        }
    }

    function renderWorks(list) {
        lastList = list
        var sig = list.map(function (t) { return t.id + ':' + t.status + ':' + (t.video_url || '') }).join('|')
        if (sig === lastSig) return
        if (anyVideoPlaying()) { pendingRender = true; return }
        lastSig = sig
        var grid = $('cvWorksGrid'), empty = $('cvWorksEmpty')
        if (!grid || !empty) return
        empty.style.display = list.length ? 'none' : ''
        grid.innerHTML = list.map(workCard).join('')
        var vs = grid.querySelectorAll('video')
        for (var i = 0; i < vs.length; i++) {
            vs[i].addEventListener('pause', tryPendingRender)
            vs[i].addEventListener('ended', tryPendingRender)
        }
    }

    function loadWorks() {
        if (!token) {
            renderWorks([])
            return
        }
        fetch(WORKS_API, { headers: { Authorization: 'Bearer ' + token }, cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
                renderWorks(list)
                var active = list.some(function (t) { return ['pending', 'accepted', 'processing'].indexOf(t.status) >= 0 })
                if (active && !pollTimer) pollTimer = setInterval(loadWorks, 10000)
                if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null }
            })
            .catch(function () { renderWorks([]) })
    }

    /* ---------- 页签切换：Explore / My Works / Favorites 三态 ----------
       My Works 不再开弹窗：网格区整页切换成历史生成记录；
       Favorites 复用同一网格，数据源换成收藏卡片的样片子集。 */
    function setMode(m) {
        gsMode = m
        ;[['explore', 'cvTabExplore'], ['works', 'cvTabWorks'], ['favs', 'cvTabFavs']].forEach(function (t) {
            var b = $(t[1])
            if (b) b.classList.toggle('on', t[0] === m)
        })
        var exploring = m !== 'works'
        // Explore / Favorites 共用网格 + 筛选 + 搜索 + 分页
        ;['gsGrid', 'gsPager'].forEach(function (id) {
            var el = $(id)
            if (el) el.style.display = exploring ? '' : 'none'
        })
        var fbar = document.querySelector('.gs-filter')
        if (fbar) fbar.style.display = exploring ? '' : 'none'
        var sbtn = $('gsSearchBtn')
        if (sbtn) sbtn.style.display = exploring ? '' : 'none'
        var swrap = $('gsSearchWrap')
        if (swrap && m === 'works') swrap.style.display = 'none'
        var wp = $('cvWorksPage')
        if (wp) wp.style.display = m === 'works' ? '' : 'none'
        if (m === 'works') {
            // 网格不可见时把样片视频都停掉，省解码
            document.querySelectorAll('#gsGrid video').forEach(function (x) { x.pause() })
            loadWorks()
        } else {
            gsPage = 1
            renderGallery()
        }
    }

    function init() {
        if (!$('gsGrid')) return
        bindModalClose()
        bindGallery()
        bindGen()
        var works = $('cvTabWorks')
        if (works) works.addEventListener('click', function () { setMode('works') })
        var exp = $('cvTabExplore')
        if (exp) exp.addEventListener('click', function () { setMode('explore') })
        var favs = $('cvTabFavs')
        if (favs) favs.addEventListener('click', function () { setMode('favs') })
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
