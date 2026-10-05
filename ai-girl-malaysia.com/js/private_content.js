/**
 * AI 女友端 Private Content 私密内容页
 * 卡片数据来自后台「直播运营 → 私密内容管理」（GET /api/live/privateContents?tab=all|most_liked），
 * DOM 结构 / class 与原站 candy.ai/private-content 保持一致（见 .trae/_private_templates.html）。
 *
 * 与原站行为差异（前端实现）：
 *   · 搜索：原站由服务端按关键词过滤，这里改为前端按 title / creator 做 includes 过滤；
 *   · 购买：未解锁 → Unlock → Confirm → POST /api/live/privateUnlock（扣代币，需登录）；
 *           已解锁 → 点卡片在本站弹窗观看（媒体地址只有解锁后才会由接口下发）；
 *           卡片一律不跳站外，未解锁时点卡片即展开 Confirm。
 */
(() => {
    var API_LIST = '/api/live/privateContents'
    var API_UNLOCK = '/api/live/privateUnlock'

    var VIDEO_ICON = './private-content_files/video-icon-dd7dcd618ba6d4bd2e3688728cbadce1a04c069fd72c2bf6137a79df332a60a2.svg'
    var IMAGE_ICON = './private-content_files/image-icon-bf418de39456539897833eaddf02154524833c9b2e8dc51cdffe77df10b9e36d.svg'
    var TOKEN_ICON = './private-content_files/token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg'

    // 标签 pill 的两套 class（原站 data-mpc--lobby-active-class/inactive-class-value）
    var TAB_BASE = 'shrink-0 h-9 px-4 inline-flex items-center rounded-full text-xs md:text-sm font-semibold transition-colors border'
    var TAB_ACTIVE = 'bg-white text-black border-white'
    var TAB_INACTIVE = 'bg-white/5 text-white/80 border-white/15 hover:bg-white/10'

    var GRID_CLASS = 'grid grid-cols-2 md:grid-cols-4 xl:grid-cols-5 gap-3 md:gap-4'
    var SEARCH_OPEN_CLASS = 'w-40'

    var items = []
    var tab = 'all'
    // 分类（对应首页顶栏 Girls/Anime/Guys 控件）：girls / anime / guys
    var cat = 'girls'
    var query = ''

    function getParam(name) {
        var reg = new RegExp('(^|&)' + name + '=([^&]*)(&|$)', 'i')
        var r = location.search.substring(1).match(reg)
        return r != null ? decodeURIComponent(r[2]) : ''
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    /* ---------- 登录态 / 请求头 ---------- */
    function token() {
        try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' }
    }

    function headers(extra) {
        var h = extra || {}
        var t = token()
        if (t) h.Authorization = 'Bearer ' + t
        return h
    }

    // 未登录：提示并跳登录页；返回 true 表示已被拦下
    function needLogin() {
        if (token()) return false
        if (window.layer) layer.msg('Please sign in first')
        setTimeout(function () { location.href = './Login.html' }, 1000)
        return true
    }

    /* ---------- 失败提示 ----------
     * 1) 不能用 layer.msg：60% 黑底、3 秒自动消失，深色站上用户基本看不到；
     * 2) 不能用 layer（普通 DOM + z-index）：站内其它弹窗是原生 modal <dialog>，
     *    处于浏览器 top layer，任何 z-index 都盖不过它，提示会被整个挡住。
     *    所以用原生 <dialog>.showModal()：后开的 dialog 在同一 top layer 里更靠上。
     */
    function errText(res) {
        var code = res && res.code
        if (code === 'C0100') return 'Not enough tokens. Please top up first.'
        if (code === 'C0101') return 'Your wallet is frozen. Please contact support.'
        if (code === 'A0100' || code === 'A0101') return 'Session expired. Please sign in again.'
        return (res && res.msg) || 'Something went wrong. Please try again.'
    }

    function alertBox(msg) {
        if (!document.getElementById('candy-alert-skin')) {
            var s = document.createElement('style')
            s.id = 'candy-alert-skin'
            s.textContent = '#candy-alert-dialog::backdrop{background:rgba(0,0,0,.55)}'
            document.head.appendChild(s)
        }
        var d = document.getElementById('candy-alert-dialog')
        if (!d) {
            d = document.createElement('dialog')
            d.id = 'candy-alert-dialog'
            d.setAttribute('aria-label', 'Notice')
            d.style.cssText = 'margin:auto;padding:0;border:0;border-radius:14px;background:#1b1b1b;color:#fff;' +
                'box-shadow:0 16px 48px rgba(0,0,0,.55);width:340px;max-width:86vw;'
            d.innerHTML =
                '<div style="padding:24px 24px 18px;text-align:center;">' +
                '<p data-alert-text style="margin:0 0 18px;font-size:15px;font-weight:600;line-height:1.5;color:#fff;"></p>' +
                '<button type="button" data-alert-ok style="min-width:120px;padding:9px 26px;border:0;border-radius:10px;' +
                'background:linear-gradient(90deg,#E75275 0%,#FF6B9A 100%);color:#fff;font-size:14px;font-weight:600;cursor:pointer;">OK</button>' +
                '</div>'
            document.body.appendChild(d)
            d.querySelector('[data-alert-ok]').addEventListener('click', function () { d.close() })
        }
        d.querySelector('[data-alert-text]').textContent = msg
        if (!d.open) {
            // showModal 在极端时序下可能不生效（节点已建但没显示出来），兜一次底，
            // 保证提示一定可见：modal 失败就退化成普通 open
            try { if (d.showModal) d.showModal() } catch (e) { /* 忽略，走下面的退化显示 */ }
            if (!d.open) d.setAttribute('open', '')
        }
    }

    function findItem(id) {
        for (var i = 0; i < items.length; i++) {
            if (String(items[i].id) === String(id)) return items[i]
        }
        return null
    }

    function content() {
        return document.getElementById('private-content-lobby-content')
    }

    /* ---------- 卡片里的三块媒体信息（原站按视频/图片组合渲染） ---------- */
    function playHtml() {
        return '<span class="w-12 h-12 md:w-13 md:h-13 rounded-full bg-black/45 border border-white/50 backdrop-blur-[10px] flex items-center justify-center transition-transform duration-300 group-hover:scale-110" data-mpc-card-play="">' +
            '<svg viewBox="0 0 24 24" class="w-5 h-5 -translate-x-[1px]" fill="white" aria-hidden="true">' +
              '<path d="M8 5.14v13.72c0 .8.87 1.3 1.56.88l10.5-6.86a1.03 1.03 0 0 0 0-1.76L9.56 4.26A1.03 1.03 0 0 0 8 5.14Z"></path>' +
            '</svg>' +
          '</span>'
    }

    // 视频胶囊：多视频时 duration 形如 "(Total 01:55)"，无时长则不渲染时长 span
    function videoBadgeHtml(videoCount, duration) {
        return '<span class="px-2 py-1 md:px-2.5 md:py-1.5 rounded-md bg-black/60 backdrop-blur-[10px] inline-flex items-center whitespace-nowrap gap-1 md:gap-1.5 text-white text-[10px] md:text-xs font-semibold leading-none tracking-wide">' +
            '<span>' + esc(videoCount) + ' x</span>' +
            '<img class="w-3 h-3 md:w-3.5 md:h-3.5 shrink-0" alt="" src="' + VIDEO_ICON + '">' +
            (duration ? '<span>' + esc(duration) + '</span>' : '') +
          '</span>'
    }

    function imageBadgeHtml(imageCount) {
        return '<span class="px-2 py-1 md:px-2.5 md:py-1.5 rounded-md bg-black/60 backdrop-blur-[10px] inline-flex items-center whitespace-nowrap gap-1 md:gap-1.5 text-white text-[10px] md:text-xs font-semibold leading-none tracking-wide">' +
            '<img class="w-3 h-3 md:w-3.5 md:h-3.5 shrink-0" alt="" src="' + IMAGE_ICON + '">' +
            '<span>' + esc(imageCount) + '</span>' +
          '</span>'
    }

    function badgeHtml() {
        return '<div class="h-6 px-2.5 bg-red-badge rounded-full inline-flex items-center shrink-0">' +
            '<span class="text-white text-[10px] md:text-xs font-semibold leading-none">New</span>' +
          '</div>'
    }

    /* 会员「去模糊」由平台开关控制（window.LIVE_VIP.unblur_preview，默认关闭） */
    function vipUnblur() {
        return !!(window.LIVE_VIP && window.LIVE_VIP.is_vip && window.LIVE_VIP.unblur_preview)
    }

    /* ---------- 卡片（原站 lobby-card，class 逐字保留） ---------- */
    function cardHtml(item) {
        var videoCount = Number(item.video_count || 0)
        var imageCount = Number(item.image_count || 0)
        if (String(item.media_type) === 'video' && videoCount < 1) videoCount = 1
        var hasVideo = videoCount > 0
        var hasImage = imageCount > 0
        var isNew = String(item.badge || '').toLowerCase() === 'new'
        // 已解锁：不再打码，点卡片在本站弹窗观看
        var unlocked = Number(item.unlocked || 0) === 1
        // 未解锁时封面是模糊的 teaser，解锁后展示原图；会员去模糊由平台开关控制
        var posterClass = 'w-full h-full object-cover object-top pointer-events-none' +
            ((unlocked || vipUnblur()) ? '' : ' scale-110 blur-[7px]')

        return '<div id="private-content-card-' + esc(item.id) + '" data-private-card="' + esc(item.id) + '" class="relative w-full aspect-[9/16] rounded-2xl overflow-hidden bg-[#14171B] group ">' +
            '<div id="content_pack_' + esc(item.id) + '" class="absolute inset-0">' +
              '<span class="pointer-events-none absolute inset-1 z-30 rounded-xl"></span>' +

              '<!-- Media (always the blurred tease) -->' +
              '<div class="absolute inset-0 rounded-2xl overflow-hidden" data-mpc-card-media="">' +
                '<img alt="" class="' + posterClass + '" src="' + esc(item.poster) + '">' +
                '<div class="absolute inset-x-0 bottom-0 h-[62%] bg-linear-to-t from-[#050608]/95 via-[#050608]/55 to-transparent pointer-events-none" data-mpc-card-bottom-veil=""></div>' +
                '<div class="absolute inset-x-0 top-0 h-16 bg-linear-to-b from-black/55 to-transparent pointer-events-none" data-mpc-card-top-veil=""></div>' +
              '</div>' +

              '<!-- Tap the artwork = sneak peek / 未解锁点它走解锁，已解锁点它在本站观看。绝不跳站外 -->' +
              '<a class="absolute inset-x-0 top-0 bottom-[44%] z-10 block" aria-label="' + esc(item.creator) + '" href="javascript:void(0)"></a>' +

              '<!-- Centered player / content info -->' +
              '<div class="absolute left-1/2 top-[38%] -translate-x-1/2 -translate-y-1/2 z-20 w-max flex flex-col items-center gap-2 pointer-events-none" data-mpc-card-center="">' +
                (hasVideo ? playHtml() : '') +
                '<div class="flex flex-col items-center gap-1">' +
                  (hasVideo ? videoBadgeHtml(videoCount, String(item.duration || '')) : '') +
                  (hasImage ? imageBadgeHtml(imageCount) : '') +
                '</div>' +
              '</div>' +

              '<!-- Top row -->' +
              '<div class="absolute top-3 inset-x-3 z-20 flex items-center justify-between gap-2 pointer-events-none" data-mpc-card-top="">' +
                '<div class="flex items-center gap-2 min-w-0">' +
                  '<span class="text-white text-sm font-semibold leading-tight truncate drop-shadow" data-mpc-card-name="">' + esc(item.creator) + '</span>' +
                '</div>' +
                (isNew ? badgeHtml() : '') +
              '</div>' +

              '<!-- Bottom stack -->' +
              '<div class="absolute bottom-0 inset-x-0 z-20 p-2.5 md:p-3 flex flex-col gap-1 md:gap-2" data-mpc-card-bottom="">' +
                '<div class="flex items-center justify-between gap-2" data-mpc-card-meta="">' +
                  '<div class="flex items-center gap-2" data-token-display="">' +
                    '<span class="inline-flex items-center gap-1.5">' +
                      '<img class="w-4 h-4 shrink-0" alt="" src="' + TOKEN_ICON + '">' +
                      '<span class="text-[#ffac0b] text-xs md:text-sm font-semibold">' + esc(item.price) + '</span>' +
                    '</span>' +
                  '</div>' +

                  '<span class="inline-flex items-center gap-1 text-[#7EE2A8] text-xs font-semibold shrink-0">' +
                    '<svg viewBox="0 0 24 24" class="w-3.5 h-3.5" fill="currentColor" aria-hidden="true">' +
                      '<path d="M2 21h4V9H2v12ZM22 11c0-1.1-.9-2-2-2h-6.3l1-4.6v-.3c0-.4-.2-.8-.4-1.1L13.2 2 6.6 8.6c-.4.4-.6.9-.6 1.4v9c0 1.1.9 2 2 2h9c.8 0 1.5-.5 1.8-1.2l3-7.1c.1-.2.2-.5.2-.7v-1Z"></path>' +
                    '</svg>' +
                    esc(item.like_rate) + '%' +
                  '</span>' +
                '</div>' +

                (unlocked
                  // 绿色渐变用内联样式：Tailwind 是从原站编译的成品包，没有 #28A76B / #7EE2A8 这两个任意值
                  ? '<button type="button" data-content-pack-watch style="background:linear-gradient(90deg,#28A76B 0%,#7EE2A8 100%);" class="flex h-[38px] w-full min-w-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl px-0 transition-[filter] hover:brightness-110 md:h-[42px] md:px-3">' +
                      '<span class="min-w-0 truncate text-xxs font-semibold text-black-default md:text-sm">Watch</span>' +
                    '</button>'
                  : '<button type="button" data-content-pack-unlock class="flex h-[38px] w-full min-w-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-linear-to-r from-[#FA2A55] to-[#FF6B9A] px-0 transition-[filter] hover:brightness-110 md:h-[42px] md:px-3  ">' +
                      '<span class="min-w-0 truncate text-xxs font-semibold text-white md:text-sm">Unlock</span>' +
                    '</button>' +

                    '<form class="hidden w-full" data-content-pack-confirm accept-charset="UTF-8" method="post">' +
                       // 必须是 submit：下单逻辑挂在表单的 submit 上（form 无 action，submit 里已 preventDefault，不会跳站外）
                       '<button type="submit" class="flex h-[38px] w-full cursor-pointer items-center justify-center rounded-xl bg-linear-to-l from-[#fdc706] to-[#ffa800] md:h-[42px]  ">' +
                         '<span class="text-xxs md:text-sm text-black-default font-semibold">Confirm</span>' +
                       '</button>' +
                     '</form>') +
              '</div>' +
            '</div>' +
          '</div>'
    }

    /* ---------- 渲染（搜索只做前端过滤） ---------- */
    function filtered() {
        var q = query.trim().toLowerCase()
        if (q === '') return items
        var list = []
        for (var i = 0; i < items.length; i++) {
            var title = String(items[i].title || '').toLowerCase()
            var creator = String(items[i].creator || '').toLowerCase()
            if (title.indexOf(q) !== -1 || creator.indexOf(q) !== -1) list.push(items[i])
        }
        return list
    }

    function render() {
        var box = content()
        if (!box) return
        var list = filtered()
        var html = ''
        for (var i = 0; i < list.length; i++) html += cardHtml(list[i])
        if (html === '') html = '<p class="col-span-full py-16 text-center text-white/60 text-sm">No matching content</p>'
        box.innerHTML = '<div class="' + GRID_CLASS + '">' + html + '</div>'
    }

    function load() {
        var url = API_LIST + '?tab=' + encodeURIComponent(tab) + '&category=' + encodeURIComponent(cat)
        fetch(url, { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (d) {
                items = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
                render()
            })
            .catch(function () {
                items = []
                render()
            })
    }

    /* ---------- 标签栏：All / Most liked ---------- */
    function setTabClasses() {
        var tabs = document.querySelectorAll('#private-content-lobby-tabs [data-tab]')
        for (var i = 0; i < tabs.length; i++) {
            var on = tabs[i].getAttribute('data-tab') === tab
            tabs[i].className = TAB_BASE + ' ' + (on ? TAB_ACTIVE : TAB_INACTIVE)
            tabs[i].setAttribute('aria-current', on ? 'true' : 'false')
        }
    }

    /* ---------- 顶栏分类：Girls / Anime / Guys（与首页 #head-btn-group 同一控件） ---------- */
    // 手机端（<768px）fy.css 会隐藏 #head-btn-group，改用 shell-new 注入的紧凑下拉 #fyCatDd，
    // 其菜单项默认跳 Girls/Anime/Guys 列表页；这里同步它的图标，并在本页改为原地过滤。
    var CAT_ICON_SRC = {
        girls: './tokens_files/female-grey-f8778c0424271c5cb4b1b50b2e50d7e8d5a2c79851c6f905e5d3ef4069956bef.svg',
        anime: './tokens_files/anime-grey-1f1c9de8c17364c1634470e5d2ab1d4d05109fa8e80c1e1b83ac0478eb2d5dac.svg',
        guys: './tokens_files/male-grey-48c8b386c214b3f674b7ef04bc51c9f7e967c49023e308fc58032fa0cc10c2e7.svg'
    }

    function syncMobileCatIcon() {
        var ic = document.querySelector('#fyCatDd .fy-catdd-ic')
        if (ic && CAT_ICON_SRC[cat]) ic.src = CAT_ICON_SRC[cat]
    }

    function setCatClasses() {
        var btns = document.querySelectorAll('#head-btn-group [data-cat]')
        for (var i = 0; i < btns.length; i++) {
            var on = btns[i].getAttribute('data-cat') === cat
            btns[i].classList.toggle('active', on)
            if (!on) {
                // 站点 main.js 可能给上一个 tab 留下粉色残留，清掉
                btns[i].classList.remove('border-rose-400', 'text-rose-400')
                var inner = btns[i].querySelectorAll('*')
                for (var j = 0; j < inner.length; j++) {
                    inner[j].classList.remove('border-rose-400', 'text-rose-400')
                }
            }
        }
        syncMobileCatIcon()
    }

    // 标签 / 分类变化时同步地址栏，刷新或分享后保持当前筛选
    function syncUrl() {
        try {
            history.replaceState(null, '', './private_content.html?tab=' + encodeURIComponent(tab) + '&category=' + encodeURIComponent(cat))
        } catch (e) {}
    }

    function applyCat(next) {
        if (['girls', 'anime', 'guys'].indexOf(next) === -1 || next === cat) return
        cat = next
        setCatClasses()
        syncUrl()
        load()
    }

    function bindCatTabs() {
        var bar = document.getElementById('head-btn-group')
        if (!bar) return
        // 用捕获阶段拦截：站点 main.js 也给 .head-btn 绑了 click（会去拉 /pagelist 并覆盖
        // localStorage.pageList），这里命中分类按钮时阻止其继续冒泡，避免多余请求与串数据
        bar.addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('[data-cat]') : null
            if (!b) return
            var next = String(b.getAttribute('data-cat') || '')
            if (['girls', 'anime', 'guys'].indexOf(next) === -1) return
            e.preventDefault()
            e.stopPropagation()
            applyCat(next)
        }, true)
    }

    // 手机端紧凑下拉：拦截菜单项跳转，改为原地过滤（shell-new 在本页脚本之后才注入，需重试几次）
    var catDdTries = 0
    function bindMobileCatDropdown() {
        var panel = document.getElementById('fyCatDdPanel')
        if (!panel) {
            if (catDdTries++ < 20) setTimeout(bindMobileCatDropdown, 250)
            return
        }
        panel.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('a') : null
            if (!a || !panel.contains(a)) return
            var href = a.getAttribute('href') || ''
            var k = href.indexOf('Anime') >= 0 ? 'anime' : (href.indexOf('Guys') >= 0 ? 'guys' : 'girls')
            e.preventDefault()
            e.stopPropagation()
            panel.classList.add('hidden')
            var btn = document.getElementById('fyCatDdBtn')
            if (btn) btn.setAttribute('aria-expanded', 'false')
            applyCat(k)
        }, true)
        syncMobileCatIcon()
    }

    function bindTabs() {
        var wrap = document.getElementById('private-content-lobby-tabs')
        if (!wrap) return
        wrap.addEventListener('click', function (e) {
            var a = e.target.closest ? e.target.closest('[data-tab]') : null
            if (!a) return
            e.preventDefault()
            var next = String(a.getAttribute('data-tab') || 'all')
            if (next === tab) return
            tab = next
            setTabClasses()
            syncUrl()
            load()
        })
    }

    /* ---------- 搜索框：放大镜展开 / 清空按钮显示隐藏 / 前端过滤 ---------- */
    function bindSearch() {
        var wrap = document.getElementById('private-content-search')
        var toggle = document.getElementById('private-content-search-toggle')
        var input = document.getElementById('private-content-search-input')
        var clear = document.getElementById('private-content-search-clear')
        if (!wrap || !toggle || !input || !clear) return

        function expanded() {
            return input.classList.contains(SEARCH_OPEN_CLASS)
        }

        function collapse() {
            input.classList.remove(SEARCH_OPEN_CLASS)
            input.classList.add('w-0')
            toggle.setAttribute('aria-expanded', 'false')
        }

        function syncClear() {
            var hasText = input.value !== ''
            clear.classList.toggle('hidden', !hasText)
            clear.classList.toggle('flex', hasText)
        }

        toggle.addEventListener('click', function () {
            if (expanded()) {
                input.value = ''
                query = ''
                collapse()
                syncClear()
                render()
                return
            }
            input.classList.remove('w-0')
            input.classList.add(SEARCH_OPEN_CLASS)
            toggle.setAttribute('aria-expanded', 'true')
            input.focus()
        })

        input.addEventListener('input', function () {
            query = input.value
            syncClear()
            render()
        })

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                input.value = ''
                query = ''
                syncClear()
                render()
            }
        })

        clear.addEventListener('click', function () {
            input.value = ''
            query = ''
            syncClear()
            render()
        })

        syncClear()
    }

    /* ---------- 观看弹窗（已解锁的卡片） ---------- */
    function watch(item) {
        if (!window.CandyWatch) return
        var sub = []
        if (item.creator) sub.push(item.creator)
        var vc = Number(item.video_count || 0)
        var ic = Number(item.image_count || 0)
        if (vc > 0) sub.push(vc + (vc > 1 ? ' videos' : ' video') + (item.duration ? ' · ' + item.duration : ''))
        if (ic > 0) sub.push(ic + (ic > 1 ? ' photos' : ' photo'))

        window.CandyWatch.open({
            title: item.creator || 'Private Content',
            subtitle: sub.join(' · '),
            videoUrl: item.video_url || '',
            images: Array.isArray(item.images) ? item.images : [],
            emptyText: 'This pack has no media yet. Please add video or images in the admin panel.',
        })
    }

    /* ---------- 解锁：POST /api/live/privateUnlock ---------- */
    function unlock(item, btn) {
        if (!item || needLogin()) return
        if (btn.disabled) return
        btn.disabled = true

        fetch(API_UNLOCK, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: 'id=' + encodeURIComponent(item.id),
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                btn.disabled = false
                if (!res || res.code !== '00000') {
                    alertBox(errText(res))
                    return
                }

                var fresh = (res.data && res.data.item) ? res.data.item : null
                item.unlocked = 1
                if (fresh) {
                    item.video_url = fresh.video_url || ''
                    item.images = Array.isArray(fresh.images) ? fresh.images : []
                }
                render()
                if (window.layer) {
                    layer.msg(Number(res.data && res.data.charged) === 1 ? 'Unlocked!' : 'Already unlocked')
                }
                watch(item)
            })
            .catch(function () {
                btn.disabled = false
                alertBox('Network error, please try again')
            })
    }

    /* ---------- 卡片交互：未解锁走 Unlock → Confirm；已解锁点卡片即观看 ---------- */
    function bindCards() {
        var box = content()
        if (!box) return

        box.addEventListener('click', function (e) {
            var card = e.target.closest ? e.target.closest('[data-private-card]') : null
            if (!card) return
            var item = findItem(card.getAttribute('data-private-card'))
            if (!item) return

            var unlocked = Number(item.unlocked || 0) === 1

            if (unlocked) {
                // 已解锁：点卡片任意位置即在本站观看
                e.preventDefault()
                watch(item)
                return
            }

            // Confirm 区内的点击必须交给表单的 submit 处理：这里绝不能 preventDefault，
            // 否则点击的默认动作被取消，submit 永不触发，下单逻辑也不会执行
            if (e.target.closest && e.target.closest('[data-content-pack-confirm]')) return

            // 其余位置：点卡片任意处都展开 Confirm，绝不跳站外
            e.preventDefault()
            var unlockBtn = card.querySelector('[data-content-pack-unlock]')
            var form = card.querySelector('[data-content-pack-confirm]')
            if (unlockBtn) unlockBtn.classList.add('hidden')
            if (form) form.classList.remove('hidden')
        })

        // Confirm 真正下单扣代币（submit 在卡片内，用事件委托）
        box.addEventListener('submit', function (e) {
            var form = e.target.closest ? e.target.closest('[data-content-pack-confirm]') : null
            if (!form) return
            e.preventDefault()
            var card = form.closest('[data-private-card]')
            var item = card ? findItem(card.getAttribute('data-private-card')) : null
            var btn = form.querySelector('button')
            if (item && btn) unlock(item, btn)
        })
    }

    document.addEventListener('DOMContentLoaded', function () {
        var from = String(getParam('tab') || '')
        tab = from === 'most_liked' ? 'most_liked' : 'all'
        var fromCat = String(getParam('category') || '').toLowerCase()
        cat = ['girls', 'anime', 'guys'].indexOf(fromCat) !== -1 ? fromCat : 'girls'
        setTabClasses()
        setCatClasses()
        bindTabs()
        bindCatTabs()
        bindMobileCatDropdown()
        bindSearch()
        bindCards()
        load()
    })

    // 会员状态是异步拿到的，拿到后按需重绘一次（只影响封面是否模糊）
    document.addEventListener('live:vip', function () {
        try { render() } catch (e) {}
    })
})()
