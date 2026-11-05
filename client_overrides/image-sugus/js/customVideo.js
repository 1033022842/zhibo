/* Image.html — 参考站风格首屏：角色瀑布流 + 卡片详情弹窗 + 我的作品
   上传/生成视频弹窗已下线，这里只负责浏览、看详情、进聊天 */
(function () {
    var token = localStorage.getItem('live_access_token')
    var WORKS_API = '/api/aiShort/myList'
    var CV_API = '/api/customVideo'

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

    /* ---------- 收藏：登录走账号（/api/aiShort/favs + favToggle），
       未登录/接口异常退回 localStorage（按浏览器记忆）。收藏页 = 网格的子集 ---------- */
    var FAV_KEY = 'gs_favs'
    var favServer = new Map()      // code -> true（账号收藏，登录且加载成功后生效）
    var favServerLoaded = false    // false 时 favHas 先按本地值渲染，避免闪烁

    function favLocal() {
        try {
            var a = JSON.parse(localStorage.getItem(FAV_KEY) || '[]')
            return Array.isArray(a) ? a.map(String) : []
        } catch (e) { return [] }
    }
    function favList() { return favLocal() }
    function favHas(id) {
        id = String(id)
        if (token && favServerLoaded) return favServer.has(id)
        return favLocal().indexOf(id) >= 0
    }
    function favToggle(id) {
        id = String(id)
        if (token && favServerLoaded) {
            var on = !favServer.has(id)
            if (on) favServer.set(id, true); else favServer.delete(id)
            var fd = new FormData()
            fd.append('code', id)
            fd.append('on', on ? '1' : '0')
            authJson(API + '/favToggle', { method: 'POST', body: fd })
                .then(function (d) {
                    if (!d || d.code !== '00000') throw new Error(d && d.msg || 'fav failed')
                })
                .catch(function () {
                    // 失败回滚 + 提示，避免界面与账号状态不一致
                    if (on) favServer.delete(id); else favServer.set(id, true)
                    toast('Failed to save favorite — try again')
                })
            return on
        }
        var a = favLocal(), i = a.indexOf(id)
        if (i >= 0) a.splice(i, 1); else a.unshift(id)
        try { localStorage.setItem(FAV_KEY, JSON.stringify(a)) } catch (e) {}
        return i < 0    // true = 刚收藏
    }
    // 登录用户拉取账号收藏；失败则继续用本地值（favServerLoaded 保持 false）
    function loadFavs() {
        if (!token || favServerLoaded) return
        authJson(API + '/favs')
            .then(function (d) {
                if (!d || d.code !== '00000' || !Array.isArray(d.data.codes)) return
                favServer = new Map(d.data.codes.map(function (c) { return [String(c), true] }))
                favServerLoaded = true
                renderGallery()
            })
            .catch(function () {})
    }
    var HEART_SVG = '<svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1L12 21.2l7.7-7.8 1.1-1a5.5 5.5 0 0 0 0-7.8z"></path></svg>'
    // 金币图标：直接复用站里导航栏那颗（Chat_files/bottom_bar_tokens_default*.svg），
    // 拷到 img/coin.svg 后用 img 标签引用，避免把 11 条 path 内联进 JS。
    var COIN_SVG = '<img class="gs-coin" src="./img/coin.svg" alt="" aria-hidden="true">'

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
        // 收藏页：只保留收藏卡片（预设 id）的样片。
        // 必须用 favHas() 而不是 favList()：登录用户的收藏在服务端（favServer），
        // 本地 localStorage 是他未登录时的临时记录。直接读本地会让登录用户的
        // 收藏在 Favorites 里全部消失 —— 这正是之前点了爱心却看不到卡片的根因。
        if (gsMode === 'favs') {
            list = list.filter(function (c) { return favHas(c.id) })
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
        // 已提交（等待出片）时保持「Submitted ✓」不可点，直到任务结束或用户重开弹窗
        if (btn.classList.contains('is-done') && !gen.submitting) {
            btn.disabled = true
            btn.classList.remove('is-locked')
            if (txt) txt.textContent = 'Submitted ✓'
            var c0 = $('gsGenCost')
            if (c0) c0.style.display = 'none'
            return
        }
        var ready = !!gen.file && !gen.submitting
        btn.disabled = !ready
        btn.classList.toggle('is-locked', !gen.file)
        if (!gen.submitting) txt.textContent = gen.file ? 'Generate' : 'Upload image first'
    }

    /* 按钮上的费用显示
       ------------------
       三种状态：
         Free             有免费额度
         50 tokens        免费额度用完，点一下会扣这么多
         Not enough       余额不足，点了也发不出去
       价格来自 /quota（弹窗打开时查），不再等服务端 C0102 才在原生弹窗里告诉用户。 */
    function renderCost() {
        var el = $('gsGenCost')
        if (!el) return
        // 已提交态下不再显示价格：按钮已经是「Submitted ✓」，
        // 旁边再挂个「50 tokens」或「Not enough」只会让人以为还要再付一次。
        // loadQuota() 是异步的，回来后还会调本函数，所以必须在源头判断。
        var sb = $('gsGenSubmit')
        if (sb && sb.classList.contains('is-done')) {
            el.style.display = 'none'
            return
        }
        // 确认态下按钮文案已经写了「Confirm · 50 tokens」，徽章再显示一次就重复了
        if (sb && sb.classList.contains('is-confirm')) {
            el.style.display = 'none'
            return
        }
        var cost = gen.quota
        if (!cost) { el.textContent = ''; el.style.display = 'none'; return }
        el.style.display = ''
        var price = Number(cost.price) || 0

        if (gen.remain === null) { el.textContent = ''; el.style.display = 'none'; return }

        if (gen.remain > 0) {
            el.textContent = 'Free'
            el.className = 'gs-gen__cost'
            return
        }
        if (price <= 0) {
            // 后台把价格关了：额度用完就是不能再用
            el.textContent = 'Limit reached'
            el.className = 'gs-gen__cost gs-gen__cost--warn'
            return
        }
        if (Number(cost.balance) < price) {
            el.innerHTML = COIN_SVG + '<span>Not enough</span>'
            el.className = 'gs-gen__cost gs-gen__cost--warn'
            return
        }
        // 价格前面挂金币图标：站内现成的 token 图标（img/coin.svg），
        // 比纯文字「50 tokens」更直观，也和导航栏余额的视觉一致。
        el.innerHTML = COIN_SVG + '<span>' + esc(cost.price_text) + '</span>'
        el.className = 'gs-gen__cost gs-gen__cost--paid'
    }

    /** 是否需要付费确认（额度耗尽但余额够） */
    function needsPaidConfirm() {
        var cost = gen.quota
        if (!cost || gen.remain === null || gen.remain > 0) return false
        var price = Number(cost.price) || 0
        return price > 0 && Number(cost.balance) >= price
    }

    // 弹窗打开时拉一次额度与单价
    function loadQuota() {
        if (!token) {
            gen.quota = null
            gen.remain = null
            renderCost()
            return Promise.resolve()
        }
        var code = gen.preset ? encodeURIComponent(gen.preset.code) : ''
        return authJson(API + '/quota?kind=' + encodeURIComponent((gen.preset && gen.preset.kind) || 'video') + (code ? '&code=' + code : ''))
            .then(function (d) {
                if (!d || d.code !== '00000' || !d.data) return
                gen.quota = d.data
                gen.remain = Number(d.data.remain)
                renderCost()
            })
            .catch(function () {})
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
        //
        // 关键：不能在旧图上直接改 src。浏览器会一直显示上一张图，直到新图
        // 下载完才替换 —— 表现就是「先闪一下上个卡片的图」。所以先清空 src，
        // 再用 Image 预加载，下载好了才真正换上。
        var pv = $('gsGenPreview')
        pv.removeAttribute('src')
        pv.style.display = 'block'
        pv.style.opacity = '0'
        var want = GS_BASE + previewOf(c)
        var pre = new Image()
        pre.onload = function () {
            // 预加载期间可能已经切换到别的卡片，别把旧结果盖上去
            if (!gen.current || String(gen.current.slug || gen.current.id) !== String(c.slug || c.id)) return
            pv.src = want
            pv.style.opacity = '1'
        }
        pre.onerror = function () { pv.style.opacity = '1' }
        pre.src = want
        $('gsGenUploadText').textContent = 'upload image'
        $('gsGenFile').value = ''

        showReference()
        $('gsGenNote').style.display = 'none'
        $('gsGenNote').textContent = ''
        renderAddons()
        // 重置上一次残留的「二次确认」态，再拉当前额度
        gen.confirmPaid = false
        gen.quota = null
        gen.remain = null
        clearTimeout(gen.confirmTimer)
        var sb = $('gsGenSubmit')
        if (sb) sb.classList.remove('is-confirm', 'is-done')
        renderCost()
        syncSubmitState()
        syncGenLike()
        openModal('gsCardModal')
        loadQuota()
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
        img.style.opacity = '1'
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

        // 要扣费时，就地改成「二次确认」态：按钮文案变成
        // 「Confirm · 50 tokens — tap again」，再点一次才真正提交。
        // 不用 window.confirm —— 原生弹窗打断感强，也不能显示真实价格。
        if (needsPaidConfirm() && !gen.confirmPaid) {
            gen.confirmPaid = true
            var btn = $('gsGenSubmit'), txt = $('gsGenSubmitText')
            txt.textContent = 'Confirm · ' + gen.quota.price_text + ' tokens'
            btn.classList.add('is-confirm')
            renderCost()
            showNote('This will use ' + gen.quota.price_text + ' tokens from your balance. '
                + 'Tap the button again to confirm.')
            // 不自动超时退回：用户可能正在读文案，这时把按钮重置回「Generate」
            // 会让人以为点过的那一步没生效。确认态一直保留到再点一次或关掉弹窗。
            return
        }

        var paid = gen.confirmPaid === true
        gen.confirmPaid = false
        clearTimeout(gen.confirmTimer)
        var b2 = $('gsGenSubmit')
        if (b2) b2.classList.remove('is-confirm')
        $('gsGenNote').style.display = 'none'
        doSubmit(paid)
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
                    // 服务端仍要确认（例如余额在我们查 quota 之后变了）：
                    // 走同一条「就地二次确认」路径，不弹原生窗口
                    gen.remain = 0
                    if (d.data && typeof d.data.price_text === 'string') {
                        gen.quota = gen.quota || {}
                        gen.quota.price_text = d.data.price_text
                        gen.quota.price = Number(d.data.price) || gen.quota.price
                    }
                    renderCost()
                    showNote(d.msg || ('This costs ' + ((gen.quota && gen.quota.price_text) || 'tokens') + '.'))
                    if (gen.quota && gen.quota.price_text) {
                        gen.confirmPaid = false
                        $('gsGenSubmitText').textContent =
                            'Confirm · ' + gen.quota.price_text + ' tokens'
                        $('gsGenSubmit').classList.add('is-confirm')
                        $('gsGenSubmit').disabled = false
                        gen.confirmPaid = true
                    }
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
            if (typeof data.balance === 'number' && gen.quota) gen.quota.balance = data.balance
            if (window.refreshTokenBalance) window.refreshTokenBalance()
            // 提交成功后额度已变（免费次数 -1 或代币已扣），重查一次让按钮显示准确
            loadQuota()
            renderCost()
            // 明确反馈：按钮本身改成「已提交」，右侧画面给状态，再弹一条 toast。
            // 之前只有右侧一行小字，用户很容易以为没点上。
            var b3 = $('gsGenSubmit'), t3 = $('gsGenSubmitText')
            if (b3) { b3.disabled = true; b3.classList.add('is-done') }
            if (t3) t3.textContent = 'Submitted ✓'
            // is-done 是刚加上的，syncSubmitState 要再跑一次才会把费用徽章藏掉
            syncSubmitState()
            setState('Queued — generating…')
            var used = confirmed === true && gen.quota
                ? ('-' + gen.quota.price_text + ' tokens')
                : ''
            toast('Submitted! Your video is queued' + (used ? ' (' + used + ')' : '') +
                ' — check My Works in a few minutes.')
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

    /* My Works 卡片：照 genspicy.ai/collection 的排版重做。
       参考站规格（实测 1280 视口）：4:5 宽高比、固定 3 列、gap 12px。
       原来用 auto-fit minmax(300px)，只有 1 个视频时那一列被拉满整个宽度，
       视频被拉成巨幅，所以看起来"变形"。现在网格列数固定，卡片自己撑比例。 */
    /* 失败卡的退款提示
       ---------------
       用户为这条任务付过金币或免费次数，失败后必须让他看到钱回来了，
       否则只会觉得是「花了钱什么都没拿到」。三种情况：
         代币已退   → Refunded 50（带金币图标，绿色）
         免费次数退 → Free credit returned（绿色）
         没退成     → Refund pending, contact support（红色，需要人工介入）
       退款字段来自 /myList 的 refunded_tokens / refunded_credit / refunded_at。 */
    function refundLine(t) {
        var amt = Number(t.refunded_tokens) || 0
        var credit = Number(t.refunded_credit) || 0
        var at = String(t.refunded_at || '')
        if (amt > 0) {
            return '<span class="wc__refund wc__refund--ok">' + COIN_SVG +
                '<span>Refunded ' + esc(fmtAmount(amt)) + '</span></span>'
        }
        if (credit > 0) {
            return '<span class="wc__refund wc__refund--ok">' +
                '<span>Free credit returned</span></span>'
        }
        // 失败但没退款记录：付费任务才提示联系客服（免费的没损失，不必打扰）
        var paid = Number(t.paid_tokens) || 0
        if (paid > 0 && !at) {
            return '<span class="wc__refund wc__refund--bad">' +
                '<span>Refund pending — contact support</span></span>'
        }
        return ''
    }

    function fmtAmount(v) {
        return String(Math.round(Number(v) * 100) / 100)
    }

    function workCard(t) {
        var m = STATUS_META[t.status] || { label: t.status, cls: 'wait' }
        var done = t.status === 'completed' && t.video_url
        var media

        if (done) {
            if (t.kind === 'image') {
                media = '<img class="wc__media" src="' + esc(t.cover_url || t.video_url) +
                    '" alt="" loading="lazy">'
            } else {
                media = '<video class="wc__media" controls playsinline preload="metadata" src="' +
                    esc(t.video_url) + '" poster="' + esc(t.cover_url || '') + '"></video>'
            }
        } else if (t.status === 'failed' || t.status === 'expired') {
            media = '<div class="wc__state wc__state--err">' +
                '<svg width="22" height="22" viewBox="0 0 24 24" fill="none">' +
                '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>' +
                '<path d="M12 8v5M12 16v.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>' +
                '<span>' + (t.status === 'expired' ? 'Expired' : 'Generation failed') + '</span>' +
                '<span class="wc__state-sub">' + esc(decodeEntities((t.error || '')).slice(0, 80) || 'Please try again') + '</span>' +
                refundLine(t) + '</div>'
        } else {
            media = '<div class="wc__state wc__state--run"><span class="wc__spinner"></span>' +
                '<span>Generating…</span><span class="wc__state-sub">usually takes a few minutes</span></div>'
        }

        var dl = done ? '<a class="wc__dl" href="' + esc(t.video_url) + '" download ' +
            'aria-label="Download"><svg width="16" height="16" viewBox="0 0 24 24" fill="none">' +
            '<path d="M12 4v10m0 0 4-4m-4 4-4-4M5 18h14" stroke="currentColor" stroke-width="1.8" ' +
            'stroke-linecap="round" stroke-linejoin="round"/></svg></a>' : ''

        return '<article class="wc">' +
            '<div class="wc__frame">' + media +
            '<span class="wc__badge wc__badge--' + m.cls + '">' + esc(m.label) + '</span>' + dl +
            '</div>' +
            '<div class="wc__meta">' +
            '<span class="wc__title">' + esc(t.title || t.preset_code || 'Preset clip') + '</span>' +
            '<span class="wc__time">' + esc((t.created_at || '').replace('T', ' ').slice(0, 16)) + '</span>' +
            '</div></article>'
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
        // 两条通道合并：aiShort（卡片）+ customVideo（换装 image / 定制）
        Promise.all([
            fetch(WORKS_API, { headers: { Authorization: 'Bearer ' + token }, cache: 'no-store' }).then(function (r) { return r.json() }).catch(function () { return null }),
            fetch(CV_API + '/myList', { headers: { Authorization: 'Bearer ' + token }, cache: 'no-store' }).then(function (r) { return r.json() }).catch(function () { return null }),
        ]).then(function (res) {
            var a = res[0], b = res[1]
            var list = (a && a.code === '00000' && Array.isArray(a.data)) ? a.data : []
            var cv = (b && b.code === '00000' && Array.isArray(b.data)) ? b.data : []
            var merged = list.concat(cv.map(function (t) {
                return {
                    id: 'cv' + t.id, task_no: t.task_no, kind: t.kind, status: t.status,
                    video_url: t.video_url, cover_url: t.cover_url, created_at: t.created_at,
                    error: t.error, refunded_tokens: t.refunded_tokens, refunded_credit: t.refunded_credit, refunded_at: t.refunded_at,
                }
            }))
            merged.sort(function (x, y) { return String(y.created_at || '').localeCompare(String(x.created_at || '')) })
            renderWorks(merged)
            var active = merged.some(function (t) { return ['pending', 'accepted', 'processing'].indexOf(t.status) >= 0 })
            if (active && !pollTimer) pollTimer = setInterval(loadWorks, 10000)
            if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null }
        }).catch(function () { renderWorks([]) })
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

    /* ---------- Shop 换装（customVideo 通道，outfit）----------
       入口：筛选栏 Shop 按钮 → 服装列表（options.image_presets，owned/locked）；
       选已购 → 上传照片 → /customVideo/submit(type=image+item_id)；
       成品在 My Works 里以 kind=image 展示（loadWorks 已合并两通道）。 */
    var CV_API2 = '/api/customVideo'
    var shop = { items: [], sel: null, file: null, confirmPaid: false, price: 0, balance: 0, remain: null, loaded: false }

    function openShop() {
        if (!token) { location.href = './Login.html'; return }
        openModal('gsShopModal')
        if (shop.loaded) { renderShop(); return }
        renderShopLoading()
        authJson(CV_API2 + '/options').then(function (d) {
            if (!d || d.code !== '00000' || !d.data) throw new Error((d && d.msg) || 'Failed to load outfits')
            shop.items = d.data.image_presets || []
            shop.price = Number(d.data.image_price) || 0
            shop.balance = Number(d.data.balance) || 0
            shop.remain = d.data.quota != null && d.data.quota !== '' ? Number(d.data.quota) : null
            shop.loaded = true
            renderShop()
        }).catch(function (e) {
            $('gsShopGrid').innerHTML = '<div class="cv-empty">Failed to load outfits — try again.</div>'
            toast(e.message || 'Network error', 'error')
        })
    }

    function renderShopLoading() {
        var grid = $('gsShopGrid')
        if (grid) grid.innerHTML = '<div class="cv-empty">Loading…</div>'
    }

    function renderShop() {
        var grid = $('gsShopGrid')
        if (!grid) return
        if (!shop.items.length) {
            grid.innerHTML = '<div class="cv-empty">No outfits in the shop yet.</div>'
            return
        }
        grid.innerHTML = shop.items.map(function (p) {
            var owned = Number(p.owned) > 0
            return '<button type="button" class="gs-shop__card' + (owned ? '' : ' is-locked') + '" data-gs-outfit="' + esc(p.item_id) + '">' +
                '<span class="gs-shop__cover" style="background-image:url(&quot;' + esc(p.cover_url || '') + '&quot;)"></span>' +
                '<span class="gs-shop__meta">' +
                '<span class="gs-shop__label">' + esc(p.label || '') + '</span>' +
                '<span class="gs-shop__price">' + (owned ? '✓ Owned — tap to wear' : '🔒 ' + esc(fmtAmount(Number(p.price) || 0)) + ' · buy in Shop') + '</span>' +
                '</span></button>'
        }).join('')
    }

    function openOutfit(item) {
        closeModal('gsShopModal')
        shop.sel = item
        shop.file = null
        shop.confirmPaid = false
        $('gsOutfitTitle').textContent = item.label || 'Outfit'
        $('gsOutfitPills').innerHTML = '<span class="gs-gen__pill">' + esc(String(item.desc || '').slice(0, 48)) + '</span>'
        // 服装缩略图：小徽章展示，加载失败自动隐藏（外链图不强求大图）
        $('gsOutfitCover').src = item.cover_url || ''
        var thumb = $('gsOutfitThumb')
        thumb.style.display = 'none'
        thumb.removeAttribute('src')
        if (item.cover_url) {
            thumb.src = item.cover_url
            thumb.style.display = 'block'
            thumb.onerror = function () { thumb.style.display = 'none' }
        }
        $('gsOutfitPreview').style.display = 'none'
        $('gsOutfitPreview').removeAttribute('src')
        $('gsOutfitFile').value = ''
        $('gsOutfitUploadText').textContent = 'upload her photo'
        var sb = $('gsOutfitSubmit')
        if (sb) { sb.classList.remove('is-confirm'); sb.disabled = false }
        $('gsOutfitSubmitText').textContent = 'Apply Outfit'
        outfitCost()
        openModal('gsOutfitModal')
    }

    function outfitCost() {
        var el = $('gsOutfitCost')
        if (!el) return
        // 免费额度与 customVideo 通道一致（options.quota = 剩余免费次数）
        if (shop.remain !== null && shop.remain > 0) {
            el.textContent = 'Free'
        } else {
            el.textContent = shop.price > 0 ? ('💰 ' + fmtAmount(shop.price)) : 'Free'
        }
    }

    function outfitSubmit(confirmed) {
        if (shop.submitting) return
        if (!shop.file) { toast('Please upload her photo first', 'error'); return }
        shop.submitting = true
        var btn = $('gsOutfitSubmit'), txt = $('gsOutfitSubmitText')
        btn.disabled = true
        txt.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', shop.file)
        fd.append('type', 'image')
        fd.append('item_id', shop.sel.item_id)
        fd.append('agreed_policy', '1')
        if (confirmed) fd.append('confirm_paid', '1')

        authJson(CV_API2 + '/submit', { method: 'POST', body: fd }).then(function (d) {
            shop.submitting = false
            btn.disabled = false
            if (!d) { txt.textContent = 'Apply Outfit'; return }
            if (d.code === 'C0102') {
                // 免费额度用尽需付费：就地二次确认（与卡片生成一致）
                shop.confirmPaid = true
                btn.disabled = false
                txt.textContent = 'Confirm · ' + fmtAmount(shop.price) + ' tokens — tap again'
                return
            }
            if (d.code === 'C0100') {
                txt.textContent = 'Apply Outfit'
                toast('Not enough tokens — please top up', 'error')
                return
            }
            if (d.code !== '00000') {
                txt.textContent = 'Apply Outfit'
                toast(d.msg || 'Submit failed', 'error')
                return
            }
            shop.file = null
            shop.confirmPaid = false
            closeModal('gsOutfitModal')
            txt.textContent = 'Apply Outfit'
            toast('Outfit applied — check My Works', 'success')
            setMode('works')
            loadWorks()
        }).catch(function () {
            shop.submitting = false
            btn.disabled = false
            txt.textContent = 'Apply Outfit'
            toast('Network error', 'error')
        })
    }

    function bindShop() {
        var btn = $('gsShopBtn')
        if (btn) btn.addEventListener('click', openShop)
        var grid = $('gsShopGrid')
        if (grid && !grid.getAttribute('data-gs-bound')) {
            grid.setAttribute('data-gs-bound', '1')
            grid.addEventListener('click', function (e) {
                var card = e.target.closest ? e.target.closest('[data-gs-outfit]') : null
                if (!card) return
                var id = card.getAttribute('data-gs-outfit')
                var item = null
                for (var i = 0; i < shop.items.length; i++) {
                    if (String(shop.items[i].item_id) === String(id)) { item = shop.items[i]; break }
                }
                if (!item) return
                if (Number(item.owned) > 0) { openOutfit(item) } else {
                    toast('Buy this outfit in the Shop first', 'error')
                    setTimeout(function () { location.href = './shop.html' }, 900)
                }
            })
        }
        var up = $('gsOutfitUpload'), fl = $('gsOutfitFile'), sb = $('gsOutfitSubmit')
        if (up && fl) {
            up.addEventListener('click', function () { fl.click() })
            fl.addEventListener('change', function () {
                if (!this.files || !this.files[0]) return
                shop.file = this.files[0]
                var r = new FileReader()
                r.onload = function (ev) {
                    var pv = $('gsOutfitPreview')
                    pv.src = ev.target.result
                    pv.style.display = 'block'
                    $('gsOutfitUploadText').textContent = shop.file.name
                }
                r.readAsDataURL(shop.file)
            })
        }
        if (sb) sb.addEventListener('click', function () { outfitSubmit(shop.confirmPaid) })
    }

    function init() {
        if (!$('gsGrid')) return
        bindModalClose()
        bindGallery()
        bindGen()
        bindShop()
        var works = $('cvTabWorks')
        if (works) works.addEventListener('click', function () { setMode('works') })
        var exp = $('cvTabExplore')
        if (exp) exp.addEventListener('click', function () { setMode('explore') })
        var favs = $('cvTabFavs')
        if (favs) favs.addEventListener('click', function () { setMode('favs') })
        loadFavs()
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
