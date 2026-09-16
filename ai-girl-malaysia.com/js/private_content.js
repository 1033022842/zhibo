/**
 * AI 女友端 Private Content 私密内容页
 * 卡片数据来自后台「直播运营 → 私密内容管理」（GET /api/live/privateContents?tab=all|most_liked），
 * DOM 结构 / class 与原站 candy.ai/private-content 保持一致（见 .trae/_private_templates.html）。
 *
 * 与原站行为差异（前端实现）：
 *   · 搜索：原站由服务端按关键词过滤，这里改为前端按 title / creator 做 includes 过滤；
 *   · 购买：原站点 Confirm 走 POST 下单，这里只弹提示，未接入支付。
 */
(() => {
    var API_LIST = '/api/live/privateContents'

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

    /* ---------- 卡片（原站 lobby-card，class 逐字保留） ---------- */
    function cardHtml(item) {
        var videoCount = Number(item.video_count || 0)
        var imageCount = Number(item.image_count || 0)
        if (String(item.media_type) === 'video' && videoCount < 1) videoCount = 1
        var hasVideo = videoCount > 0
        var hasImage = imageCount > 0
        var isNew = String(item.badge || '').toLowerCase() === 'new'
        var url = esc(item.purchase_url)

        return '<div id="private-content-card-' + esc(item.id) + '" class="relative w-full aspect-[9/16] rounded-2xl overflow-hidden bg-[#14171B] group ">' +
            '<div id="content_pack_' + esc(item.id) + '" class="absolute inset-0">' +
              '<span class="pointer-events-none absolute inset-1 z-30 rounded-xl"></span>' +

              '<!-- Media (always the blurred tease) -->' +
              '<div class="absolute inset-0 rounded-2xl overflow-hidden" data-mpc-card-media="">' +
                '<img alt="" class="w-full h-full object-cover object-top pointer-events-none scale-110 blur-[7px]" src="' + esc(item.poster) + '">' +
                '<div class="absolute inset-x-0 bottom-0 h-[62%] bg-linear-to-t from-[#050608]/95 via-[#050608]/55 to-transparent pointer-events-none" data-mpc-card-bottom-veil=""></div>' +
                '<div class="absolute inset-x-0 top-0 h-16 bg-linear-to-b from-black/55 to-transparent pointer-events-none" data-mpc-card-top-veil=""></div>' +
              '</div>' +

              '<!-- Tap the artwork = sneak peek -->' +
              '<a class="absolute inset-x-0 top-0 bottom-[44%] z-10 block" aria-label="' + esc(item.creator) + '" href="' + url + '"></a>' +

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
                  '<img alt="' + esc(item.creator) + '" class="w-7 h-7 object-cover object-top rounded-full ring-1 ring-white/40 shrink-0" data-mpc-card-avatar="true" src="' + esc(item.avatar) + '">' +
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

                '<div class="truncate-2-lines text-left text-white/80 text-[11px] md:text-xs font-medium leading-tight" data-mpc-card-description="">' + esc(item.title) + '</div>' +

                '<button type="button" data-content-pack-unlock class="flex h-[38px] w-full min-w-0 cursor-pointer items-center justify-center overflow-hidden rounded-xl bg-linear-to-r from-[#FA2A55] to-[#FF6B9A] px-0 transition-[filter] hover:brightness-110 md:h-[42px] md:px-3  ">' +
                  '<span class="min-w-0 truncate text-xxs font-semibold text-white md:text-sm">Unlock</span>' +
                '</button>' +

                '<form class="hidden w-full" data-content-pack-confirm action="' + url + '" accept-charset="UTF-8" method="post">' +
                  '<button class="flex h-[38px] w-full cursor-pointer items-center justify-center rounded-xl bg-linear-to-l from-[#fdc706] to-[#ffa800] md:h-[42px]  ">' +
                    '<span class="text-xxs md:text-sm text-black-default font-semibold">Confirm</span>' +
                  '</button>' +
                '</form>' +
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
        fetch(API_LIST + '?tab=' + encodeURIComponent(tab), { method: 'GET' })
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
            history.replaceState(null, '', './private_content.html?tab=' + encodeURIComponent(tab))
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

    /* ---------- 卡片交互：Unlock → Confirm；Confirm 暂未接入支付 ---------- */
    function bindCards() {
        var box = content()
        if (!box) return

        box.addEventListener('click', function (e) {
            var unlock = e.target.closest ? e.target.closest('[data-content-pack-unlock]') : null
            if (!unlock) return
            var form = unlock.parentNode.querySelector('[data-content-pack-confirm]')
            unlock.classList.add('hidden')
            if (form) form.classList.remove('hidden')
        })

        box.addEventListener('submit', function (e) {
            e.preventDefault()
            if (window.layer) layer.msg('Purchases are not available yet')
        })
    }

    document.addEventListener('DOMContentLoaded', function () {
        var from = String(getParam('tab') || '')
        tab = from === 'most_liked' ? 'most_liked' : 'all'
        setTabClasses()
        bindTabs()
        bindSearch()
        bindCards()
        load()
    })
})()
