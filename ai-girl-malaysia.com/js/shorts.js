/**
 * AI 女友端 Candy Shorts 短剧页
 * 卡片数据来自后台「直播运营 → 短剧管理」（GET /api/live/shorts），
 * DOM 结构与 class 与原站 candy.ai/candy-shorts 保持一致（像素级复刻）。
 *
 * 观看：点卡片一律在本站弹窗播放（video_url 由后台「短剧管理」上传）；
 *       没配视频时弹窗展示封面并提示补传，不会再跳 candy.ai 站外链接。
 */
(function () {
    var API_LIST = '/api/live/shorts'

    var ICON_SPICY = './candy-shorts_files/flame-b8e6610ff74d5100d93b3a666ef494a20f840ddc26fa850943a495199527ae39.svg'

    var byId = {}

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    function num(v) {
        var n = Number(v)
        return isFinite(n) ? n : 0
    }

    var SECTION_LABEL = {
        continue_watching: 'Continue watching',
        top_series: 'Top 10 shows in the US this week',
        explore: 'Explore All Shorts',
    }

    /* ---------- 点击卡片：有剧集走剧集弹窗，否则沿用单视频弹窗 ---------- */
    function bindCards() {
        document.addEventListener('click', function (e) {
            var el = e.target.closest ? e.target.closest('[data-short-id]') : null
            if (!el) return
            var shortId = Number(el.getAttribute('data-short-id')) || 0
            if (!shortId) return
            var item = byId[String(shortId)] || null
            e.preventDefault()

            // 一律弹 candy.ai 同款的剧集弹窗（左视频 / 右介绍+剧集）：
            // 后台配了剧集就列多集，没配就用剧自身视频兜底成第 1 集
            // 观看历史卡片带 data-episode-id，直接恢复到当时那一集
            if (window.CandySeries) {
                window.CandySeries.open({
                    shortId: shortId,
                    title: item ? (item.title || '') : (el.getAttribute('aria-label') || ''),
                    description: item ? (item.description || '') : '',
                    episodeId: Number(el.getAttribute('data-episode-id')) || 0,
                })
                return
            }

            // 极端情况（新版弹窗脚本没加载出来）才回退到旧的单视频弹窗
            if (!item || !window.CandyWatch) return

            window.CandyWatch.open({
                title: item.title || '',
                subtitle: SECTION_LABEL[String(item.section)] || '',
                videoUrl: item.video_url || '',
                // 没配视频时展示封面大图，并提示去后台补视频文件
                images: item.poster ? [item.poster] : [],
                notice: item.video_url ? '' : 'This episode has no video yet.',
                emptyText: 'This episode has no video yet.',
            })
        })
    }

    function indexItems(list) {
        byId = {}
        for (var i = 0; i < list.length; i++) {
            if (list[i] && list[i].id !== undefined) byId[String(list[i].id)] = list[i]
        }
    }

    /* ---------- 卡片内的可选角标 ---------- */
    function spicyPill() {
        return '<span class="group/spicy flex size-5 items-center justify-center rounded-full border border-nsfw-spicy bg-black/75 backdrop-blur-[6px] transition-[width,padding] duration-[280ms] motion-reduce:transition-none md:size-7 md:min-w-7 md:hover:w-auto md:hover:px-2" role="img" aria-label="Spicy — adult content" title="Spicy — adult content">' +
            '<img class="size-2.5 shrink-0 object-contain md:size-3.5" alt="" aria-hidden="true" src="' + ICON_SPICY + '">' +
            '<span class="hidden max-w-0 overflow-hidden opacity-0 transition-[max-width,opacity,margin] duration-[280ms] motion-reduce:transition-none md:block md:group-hover/spicy:ml-1.5 md:group-hover/spicy:max-w-20 md:group-hover/spicy:opacity-100 whitespace-nowrap text-[9px] font-bold tracking-[0.03em] text-white md:text-[10px]">SPICY</span>' +
            '</span>'
    }

    /* 货架卡片右上角（class 顺序照存档页） */
    function spicyBadgeShelf() {
        return '<div class="absolute right-1.5 top-1.5 z-30 md:right-2 md:top-2">' + spicyPill() + '</div>'
    }

    /* 排行榜 / 网格卡片右上角 */
    function spicyBadge() {
        return '<div class="absolute z-30 right-1.5 top-1.5 md:right-2 md:top-2">' + spicyPill() + '</div>'
    }

    /* ---------- 右下角：集数 + 起售价 ----------
     * 原站没有这两个角标，是新功能的展示位；用内联样式，避免依赖
     * Tailwind 成品包里不存在的类名。withProgressBar=true 时抬高，
     * 避开 Continue watching 卡片底部的进度条。
     */
    var PILL_STYLE = 'padding:1px 6px;border-radius:9999px;background:rgba(0,0,0,.66);color:#fff;' +
        'font-size:10px;font-weight:600;line-height:16px;white-space:nowrap;backdrop-filter:blur(6px);'

    function episodesBadge(item, withProgressBar) {
        if (!item.has_episodes) return ''
        var count = Math.round(num(item.episode_count))
        if (count <= 0) return ''

        var from = Math.round(num(item.price_from))
        var pills = '<span style="' + PILL_STYLE + '">' + count + ' Eps</span>' +
            '<span style="' + PILL_STYLE + '">' + (from > 0 ? from + ' <img src="./taocan_files/token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg" alt="" style="width:10px;height:10px;display:inline-block;vertical-align:-1px;margin-left:1px">' : 'Free') + '</span>'

        return '<div style="position:absolute;right:6px;bottom:' + (withProgressBar ? '14px' : '6px') +
            ';z-index:30;display:flex;gap:4px;pointer-events:none;">' + pills + '</div>'
    }

    /* 左上角 New Episodes 角标 */
    function newEpisodesBadge() {
        return '<span role="status" class="pointer-events-none absolute top-0 z-30 flex items-center py-0.75 text-4xs font-bold uppercase leading-2.75 tracking-[0.02em] md:py-1.25 md:text-3xs md:leading-3.75 bg-[#f53b70] text-white left-0 rounded-br-xl pl-1.25 pr-1.5 md:pl-2 md:pr-2.25">' +
            '<span class="md:hidden">New Eps</span>' +
            '<span class="hidden md:inline">New Episodes</span>' +
            '</span>'
    }

    /* ---------- 排行榜 / 网格卡片共用的封面框 ----------
     * featured: '' 普通 / 'ring' 粉色描边 / 'gradient' 粉色渐变外框（原站三种高亮变体）
     */
    function cardBox(item, featured) {
        var inner = '<div class="relative w-full aspect-[240/342] overflow-hidden rounded-xl bg-black-default drop-shadow-candy-shorts-card ring-1 ring-white/5 transition duration-300 group-hover:ring-white/20' +
            (featured === 'ring' ? ' ring-2 ring-pink-ring/70 shadow-pink-lg transition-shadow duration-300 group-hover:shadow-pink-cta' : '') +
            '">' +
            '<img class="absolute inset-0 size-full object-cover transition-transform duration-300 ease-out group-hover:scale-[1.04]" loading="lazy" alt="" aria-hidden="true" src="' + esc(item.poster) + '">' +
            '<div class="absolute inset-x-0 bottom-0 h-1/4 bg-gradient-to-t from-black/50 to-transparent opacity-0 transition-opacity duration-300 group-hover:opacity-100" aria-hidden="true"></div>' +
            (item.new_episodes ? newEpisodesBadge() : '') +
            (item.spicy ? spicyBadge() : '') +
            episodesBadge(item, false) +
            '</div>'

        if (featured === 'gradient') {
            return '<div class="w-full p-[2px] rounded-[14px] bg-gradient-pink-cta shadow-[0_0_28px_4px_rgba(255,79,163,0.6)] transition-shadow duration-300 group-hover:shadow-[0_0_36px_6px_rgba(255,79,163,0.7)]">' + inner + '</div>'
        }
        return '<div class="w-full ">' + inner + '</div>'
    }

    /* ---------- Continue watching 货架卡片（带进度条） ---------- */
    function shelfCard(item) {
        var p = num(item.progress)
        return '<a class="cs-shelf-card group flex shrink-0 snap-start cursor-pointer flex-col text-left" data-short-id="' + esc(item.id) + '" data-episode-id="' + esc(item.episode_id || 0) + '" aria-label="' + esc(item.title) + '" href="javascript:void(0)">' +
            '<div class="relative aspect-[240/342] w-full overflow-hidden rounded-[11px] bg-black-default ring-1 ring-white/5 drop-shadow-candy-shorts-card lg:rounded-[18px] lg:transition lg:duration-300 lg:group-hover:ring-white/20">' +
            '<img class="absolute inset-0 size-full object-cover lg:transition-transform lg:duration-300 lg:ease-out lg:group-hover:scale-[1.04]" loading="lazy" alt="" aria-hidden="true" src="' + esc(item.poster) + '">' +
            '<div class="pointer-events-none absolute inset-x-0 bottom-0 hidden h-1/4 bg-gradient-to-t from-black/50 to-transparent opacity-0 lg:block lg:transition-opacity lg:duration-300 lg:group-hover:opacity-100" aria-hidden="true"></div>' +
            (item.spicy ? spicyBadgeShelf() : '') +
            '<div class="absolute inset-x-2 bottom-1 z-10 h-0.5 overflow-hidden rounded-full bg-white/50 lg:inset-x-0 lg:bottom-0 lg:h-1.5 lg:bg-white/30" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(p) + '" aria-label="' + esc(item.title) + ' viewing progress">' +
            '<div class="h-full rounded-full bg-white" style="width: ' + p.toFixed(1) + '%"></div>' +
            '</div>' +
            episodesBadge(item, true) +
            '</div>' +
            '</a>'
    }

    /* ---------- Top 10 条目（左侧名次数字 + 卡片） ---------- */
    function topShelfItem(item, index) {
        var rank = Math.round(num(item.rank)) || (index + 1)
        return '<div class="cs-top-shelf__item flex shrink-0 snap-start items-start" data-rank="' + rank + '">' +
            '<b class="cs-top-shelf__numeral font-poppins font-extrabold ' + (rank === 1 ? 'text-pink-dark' : 'text-white/60') + '" aria-hidden="true">' + rank + '</b>' +
            '<div class="cs-top-shelf__card relative z-10 shrink-0">' +
            '<a class="group flex w-full cursor-pointer flex-col text-left" data-short-id="' + esc(item.id) + '" aria-label="' + esc(item.title) + '" href="javascript:void(0)">' +
            cardBox(item, item.featured) +
            '</a>' +
            '</div>' +
            '</div>'
    }

    /* ---------- Explore 网格卡片（图下一行标题） ---------- */
    function libraryCard(item) {
        return '<a class="group flex flex-col gap-2 text-left cursor-pointer" data-short-id="' + esc(item.id) + '" aria-label="' + esc(item.title) + '" href="javascript:void(0)">' +
            cardBox(item, item.featured) +
            '<span class="text-xs leading-5 lg:text-md lg:leading-6 font-semibold text-white lg:text-white/80 transition-colors duration-200 lg:group-hover:text-white line-clamp-2">' + esc(item.title) + '</span>' +
            '</a>'
    }

    /* ---------- 三个分区渲染（分区为空时整块隐藏） ---------- */
    function renderContinueWatching(items) {
        var h2 = document.getElementById('candy-shorts-continue-watching-title')
        var section = h2 && h2.closest ? h2.closest('section') : null
        if (!section) return
        var row = section.querySelector('.cs-row-scroller')
        if (!row) return
        if (!items.length) {
            section.classList.add('hidden')
            return
        }
        var html = ''
        for (var i = 0; i < items.length; i++) html += shelfCard(items[i])
        row.innerHTML = html
        section.classList.remove('hidden')
        bindRowArrows(row)
    }

    function renderTopSeries(items) {
        var h2 = document.getElementById('candy-shorts-top-series-title')
        var section = h2 && h2.closest ? h2.closest('section') : null
        if (!section) return
        var row = section.querySelector('.cs-row-scroller')
        if (!row) return
        if (!items.length) {
            section.classList.add('hidden')
            return
        }
        var html = ''
        for (var i = 0; i < items.length; i++) html += topShelfItem(items[i], i)
        row.innerHTML = html
        section.classList.remove('hidden')
        bindRowArrows(row)
    }

    function renderExplore(items) {
        var frame = document.getElementById('candy-shorts-library-grid')
        if (!frame) return
        // 用 id 取标题：空结果提示会插在 grid 前面，previousElementSibling 不再可靠
        var title = document.getElementById('candy-shorts-explore-title')
        var grid = document.getElementById('candy-shorts-library-cards-all-all')
        if (!grid) return
        if (!items.length) {
            frame.classList.add('hidden')
            if (title) title.classList.add('hidden')
            return
        }
        var html = ''
        for (var i = 0; i < items.length; i++) html += libraryCard(items[i])
        grid.innerHTML = html
        frame.classList.remove('hidden')
        if (title) title.classList.remove('hidden')
    }

    /* ---------- 货架左右箭头（原站由 scroll-fade 控制器驱动，这里按同样规则还原） ---------- */
    function setArrow(btn, on) {
        if (!btn) return
        btn.disabled = !on
        btn.setAttribute('aria-hidden', on ? 'false' : 'true')
        if (on) {
            btn.classList.remove('opacity-0', 'pointer-events-none')
            btn.classList.add('opacity-100')
        } else {
            btn.classList.remove('opacity-100')
            btn.classList.add('opacity-0', 'pointer-events-none')
        }
    }

    function updateRow(row, prev, next) {
        var max = row.scrollWidth - row.clientWidth
        var canLeft = max > 1 && row.scrollLeft > 1
        var canRight = max > 1 && row.scrollLeft < max - 1
        setArrow(prev, canLeft)
        setArrow(next, canRight)
        row.setAttribute('data-fade', max <= 1 ? 'none' : (canLeft && canRight ? 'both' : (canLeft ? 'left' : 'right')))
    }

    function bindRowArrows(row) {
        var box = row.parentNode
        if (!box) return
        var prev = null
        var next = null
        var btns = box.querySelectorAll('button[aria-label]')
        for (var i = 0; i < btns.length; i++) {
            var label = btns[i].getAttribute('aria-label') || ''
            if (label.indexOf('Show previous ') === 0) prev = btns[i]
            if (label.indexOf('Show more ') === 0) next = btns[i]
        }

        function update() { updateRow(row, prev, next) }

        if (prev) {
            prev.addEventListener('click', function () {
                row.scrollBy({ left: -Math.max(240, row.clientWidth * 0.8), behavior: 'smooth' })
            })
        }
        if (next) {
            next.addEventListener('click', function () {
                row.scrollBy({ left: Math.max(240, row.clientWidth * 0.8), behavior: 'smooth' })
            })
        }
        row.addEventListener('scroll', update)
        window.addEventListener('resize', update)
        update()
    }

    /* ---------- 筛选栏：真筛选 ----------
     * 角色类型 tab（Character type）→ 按 item.type 过滤
     * 分类 pill（Categories）→ All / New(new_episodes) / Spicy(spicy) / 数据里出现的 category 标签
     * 选中态的视觉：存档页 Tailwind 包没编出 aria-selected 自身变体，由 shorts.html 里补的 CSS 负责
     */
    var allItems = []                 // 全量（不受筛选影响）
    var filter = { type: 'all', category: 'all' }
    var TYPE_ALL = 'all'
    var TYPE_LABELS = {
        'all': 'All',
        'creator': 'Candy Creators',
        'character': 'Candy characters',
        'other': 'Other characters',
    }
    var CAT_ALL = 'all'
    var CAT_NEW = '__new'
    var CAT_SPICY = '__spicy'

    var PILL_CLASS = 'inline-flex shrink-0 snap-start items-center gap-1.5 whitespace-nowrap rounded-full border border-white/10 bg-white/[0.04] px-3.5 py-1.5 text-sm text-white/70 transition-all duration-200 cursor-pointer hover:bg-white/10 hover:text-white'

    function itemType(item) {
        return String((item && item.type) || '')
    }

    function itemCategories(item) {
        var c = item && item.category
        return Object.prototype.toString.call(c) === '[object Array]' ? c : []
    }

    function matchType(item) {
        return filter.type === TYPE_ALL || itemType(item) === filter.type
    }

    function matchCategory(item) {
        var key = filter.category
        if (key === CAT_ALL) return true
        if (key === CAT_NEW) return !!item.new_episodes
        if (key === CAT_SPICY) return !!item.spicy
        return itemCategories(item).indexOf(key) >= 0
    }

    function applyFilter() {
        var list = []
        for (var i = 0; i < allItems.length; i++) {
            if (matchType(allItems[i]) && matchCategory(allItems[i])) list.push(allItems[i])
        }
        render(list)
        renderEmptyState(list.length === 0)
    }

    /* 分类 pill 按数据生成：All / New / Spicy + 数据里出现的分类标签 */
    function renderCategoryPills() {
        var host = document.querySelector('[role="tablist"][aria-label="Categories"]')
        if (!host) return

        var cats = []
        for (var i = 0; i < allItems.length; i++) {
            var arr = itemCategories(allItems[i])
            for (var j = 0; j < arr.length; j++) {
                if (cats.indexOf(arr[j]) < 0) cats.push(arr[j])
            }
        }
        cats.sort()

        var defs = [
            { key: CAT_ALL, label: 'All' },
            { key: CAT_NEW, label: 'New' },
            { key: CAT_SPICY, label: '🔥 Spicy' },
        ]
        for (var k = 0; k < cats.length; k++) defs.push({ key: cats[k], label: cats[k] })

        var html = ''
        for (var d = 0; d < defs.length; d++) {
            if (defs[d].key !== CAT_ALL && !hasItemsFor(defs[d].key)) continue
            html += '<button type="button" role="tab" data-filter="' + esc(defs[d].key) + '" aria-selected="' +
                (filter.category === defs[d].key ? 'true' : 'false') + '" class="' + PILL_CLASS + '">' +
                '<span>' + esc(defs[d].label) + '</span></button>'
        }
        host.innerHTML = html
    }

    /* 该分类下（在当前角色类型范围内）有没有内容，没有就不显示这个 pill */
    function hasItemsFor(key) {
        for (var i = 0; i < allItems.length; i++) {
            if (!matchType(allItems[i])) continue
            var saved = filter.category
            filter.category = key
            var hit = matchCategory(allItems[i])
            filter.category = saved
            if (hit) return true
        }
        return false
    }

    function renderEmptyState(isEmpty) {
        var box = document.getElementById('cs-filter-empty')
        if (!box) {
            var grid = document.getElementById('candy-shorts-library-grid')
            if (!grid || !grid.parentNode) return
            box = document.createElement('div')
            box.id = 'cs-filter-empty'
            box.className = 'order-3 mb-5 text-center text-sm text-white/50'
            box.style.display = 'none'
            grid.parentNode.insertBefore(box, grid)
        }
        box.textContent = isEmpty ? 'No series match this filter yet — try another one.' : ''
        box.style.display = isEmpty ? '' : 'none'
    }

    function selectType(key) {
        filter.type = key
        var host = document.querySelector('[role="tablist"][aria-label="Character type"]')
        if (host) {
            var tabs = host.querySelectorAll('[role="tab"]')
            for (var i = 0; i < tabs.length; i++) {
                var label = String(tabs[i].textContent || '').trim()
                var key2 = TYPE_ALL
                for (var k in TYPE_LABELS) {
                    if (TYPE_LABELS[k] === label) key2 = k
                }
                tabs[i].setAttribute('aria-selected', key2 === key ? 'true' : 'false')
            }
        }
        renderCategoryPills()   // 某些分类在该角色类型下可能没有内容
        if (filter.category !== CAT_ALL && filter.category !== CAT_NEW && filter.category !== CAT_SPICY
            && !hasItemsFor(filter.category)) {
            filter.category = CAT_ALL
            renderCategoryPills()
        }
        applyFilter()
    }

    function selectCategory(key) {
        filter.category = key
        var host = document.querySelector('[role="tablist"][aria-label="Categories"]')
        if (host) {
            var tabs = host.querySelectorAll('[role="tab"]')
            for (var i = 0; i < tabs.length; i++) {
                tabs[i].setAttribute('aria-selected', String(tabs[i].getAttribute('data-filter')) === key ? 'true' : 'false')
            }
        }
        applyFilter()
    }

    function bindFilterTabs() {
        var typeHost = document.querySelector('[role="tablist"][aria-label="Character type"]')
        if (typeHost) {
            typeHost.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('[role="tab"]') : null
                if (!btn || !typeHost.contains(btn)) return
                var label = String(btn.textContent || '').trim()
                var key = TYPE_ALL
                for (var k in TYPE_LABELS) {
                    if (TYPE_LABELS[k] === label) key = k
                }
                selectType(key)
            })
        }
        var catHost = document.querySelector('[role="tablist"][aria-label="Categories"]')
        if (catHost) {
            catHost.addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('[role="tab"]') : null
                if (!btn || !catHost.contains(btn)) return
                selectCategory(String(btn.getAttribute('data-filter') || CAT_ALL))
            })
        }
    }

    function groupBySection(list) {
        var groups = { continue_watching: [], top_series: [], explore: [] }
        for (var i = 0; i < list.length; i++) {
            var item = list[i] || {}
            var section = String(item.section || '')
            if (section === 'continue_watching' || section === 'top_series') groups[section].push(item)
            else groups.explore.push(item)
        }
        return groups
    }

    /* ---------- 当前用户的观看历史：登录且有记录时替换后台静态的 Continue watching ---------- */
    var userHistory = null

    function loadUserHistory() {
        if (!window.WatchHistory || !window.WatchHistory.token()) return
        window.WatchHistory.load('short', 20).then(function (list) {
            if (!list.length) return
            userHistory = list.map(function (h) {
                return {
                    id: h.id,
                    title: h.title,
                    poster: h.poster,
                    progress: h.progress,
                    episode_id: h.episode_id,
                }
            })
            renderContinueWatching(userHistory)
        })
    }

    function render(list) {
        var groups = groupBySection(list)
        renderContinueWatching(userHistory && userHistory.length ? userHistory : groups.continue_watching)
        renderTopSeries(groups.top_series)
        renderExplore(groups.explore)
    }

    function fetchList(url) {
        return fetch(url, { method: 'GET' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                return (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
            })
            .catch(function () { return [] })
    }

    function load() {
        // 公开短剧 + 私人短剧都要展示：接口默认排除 section=private，这里单独拉一次再合并
        Promise.all([fetchList(API_LIST), fetchList(API_LIST + '?section=private')])
            .then(function (parts) {
                var list = [], seen = {}
                for (var p = 0; p < parts.length; p++) {
                    var arr = parts[p] || []
                    for (var i = 0; i < arr.length; i++) {
                        var item = arr[i]
                        var id = item && item.id !== undefined ? String(item.id) : ''
                        if (id !== '' && seen[id]) continue
                        if (id !== '') seen[id] = 1
                        list.push(item)
                    }
                }
                allItems = list
                indexItems(list)
                renderCategoryPills()
                applyFilter()
                loadUserHistory()
                openDeepLink()
            })
            .catch(function () {
                allItems = []
                indexItems([])
                renderCategoryPills()
                applyFilter()
            })
    }

    /* Discover 卡片带 ?short=<id> 进来时，直接打开对应短剧的剧集弹窗 */
    function openDeepLink() {
        var id = 0
        try { id = parseInt(new URLSearchParams(location.search).get('short') || '0', 10) || 0 } catch (e) {}
        if (!id || !window.CandySeries) return
        var item = byId[String(id)]
        if (!item) return
        window.CandySeries.open({
            shortId: id,
            title: item.title || '',
            description: item.description || '',
        })
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindFilterTabs()
        bindCards()
        load()
    })
})()
