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

    /* ---------- 点击卡片：一律在本站弹窗观看，不再跳 candy.ai ---------- */
    function bindCards() {
        document.addEventListener('click', function (e) {
            var el = e.target.closest ? e.target.closest('[data-short-id]') : null
            if (!el) return
            var item = byId[String(el.getAttribute('data-short-id') || '')]
            if (!item) return
            e.preventDefault()
            if (!window.CandyWatch) return

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
            '</div>'

        if (featured === 'gradient') {
            return '<div class="w-full p-[2px] rounded-[14px] bg-gradient-pink-cta shadow-[0_0_28px_4px_rgba(255,79,163,0.6)] transition-shadow duration-300 group-hover:shadow-[0_0_36px_6px_rgba(255,79,163,0.7)]">' + inner + '</div>'
        }
        return '<div class="w-full ">' + inner + '</div>'
    }

    /* ---------- Continue watching 货架卡片（带进度条） ---------- */
    function shelfCard(item) {
        var p = num(item.progress)
        return '<a class="cs-shelf-card group flex shrink-0 snap-start cursor-pointer flex-col text-left" data-short-id="' + esc(item.id) + '" aria-label="' + esc(item.title) + '" href="javascript:void(0)">' +
            '<div class="relative aspect-[240/342] w-full overflow-hidden rounded-[11px] bg-black-default ring-1 ring-white/5 drop-shadow-candy-shorts-card lg:rounded-[18px] lg:transition lg:duration-300 lg:group-hover:ring-white/20">' +
            '<img class="absolute inset-0 size-full object-cover lg:transition-transform lg:duration-300 lg:ease-out lg:group-hover:scale-[1.04]" loading="lazy" alt="" aria-hidden="true" src="' + esc(item.poster) + '">' +
            '<div class="pointer-events-none absolute inset-x-0 bottom-0 hidden h-1/4 bg-gradient-to-t from-black/50 to-transparent opacity-0 lg:block lg:transition-opacity lg:duration-300 lg:group-hover:opacity-100" aria-hidden="true"></div>' +
            (item.spicy ? spicyBadgeShelf() : '') +
            '<div class="absolute inset-x-2 bottom-1 z-10 h-0.5 overflow-hidden rounded-full bg-white/50 lg:inset-x-0 lg:bottom-0 lg:h-1.5 lg:bg-white/30" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(p) + '" aria-label="' + esc(item.title) + ' viewing progress">' +
            '<div class="h-full rounded-full bg-white" style="width: ' + p.toFixed(1) + '%"></div>' +
            '</div>' +
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
        var title = frame.previousElementSibling
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

    /* ---------- 筛选栏：只切换选中态（下划线 / 反白由原有 class 驱动），不做真实筛选 ---------- */
    function bindFilterTabs() {
        var lists = document.querySelectorAll('[role="tablist"]')
        for (var i = 0; i < lists.length; i++) {
            lists[i].addEventListener('click', function (e) {
                var btn = e.target && e.target.closest ? e.target.closest('[role="tab"]') : null
                if (!btn || !this.contains(btn)) return
                var tabs = this.querySelectorAll('[role="tab"]')
                for (var j = 0; j < tabs.length; j++) {
                    tabs[j].setAttribute('aria-selected', tabs[j] === btn ? 'true' : 'false')
                }
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

    function render(list) {
        var groups = groupBySection(list)
        renderContinueWatching(groups.continue_watching)
        renderTopSeries(groups.top_series)
        renderExplore(groups.explore)
    }

    function load() {
        fetch(API_LIST, { method: 'GET' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
                indexItems(list)
                render(list)
            })
            .catch(function () {
                indexItems([])
                render([])
            })
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindFilterTabs()
        bindCards()
        load()
    })
})()
