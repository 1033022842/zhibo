/**
 * AI 女友端 Candy Shop 商店
 * 商品数据来自后台「直播运营 → 商品管理」（GET /api/live/shopItems），
 * DOM 结构 / class 与原站 candy.ai/shop/items 保持一致。
 *
 * 购买：Buy now → Confirm → POST /api/live/shopBuy（扣钻石，需登录），
 *       买到的商品进入 Inventory（GET /api/live/inventory）。
 */
(() => {
    var API_LIST = '/api/live/shopItems'
    var API_ITEM = '/api/live/shopItem'
    var API_BUY = '/api/live/shopBuy'
    var API_INVENTORY = '/api/live/inventory'
    var TOKEN_ICON = './shop_files/token-f75f9cb0c7c7d6e061d16253167966aeb3fa8bc051f416cc3f6cba4c29aac39d.svg'
    var MAX_QTY = 10

    var items = []
    var inventory = []
    // 详情弹窗里的媒体顺序：0=视频，1=封面图，2+=附加图片
    var media = []
    var current = null
    var qty = 1

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

    function ownedBadge(n, text) {
        if (!n) return ''
        return '<span style="position:absolute;left:8px;top:8px;z-index:3;padding:2px 8px;border-radius:9999px;' +
            'background:rgba(0,0,0,.66);color:#fff;font-size:10px;font-weight:600;line-height:16px;">' +
            esc(text) + '</span>'
    }

    /* ---------- 星级条：与原站 DOM 完全一致 ---------- */
    function starsHtml(item, starClass, reviewClass) {
        var rating = Number(item.rating || 0).toFixed(1)
        var pct = Math.max(0, Math.min(100, Number(item.rating_pct || 0)))
        return '<span class="relative inline-block ' + starClass + ' leading-none" role="img" aria-label="Rated ' + rating + ' out of 5">' +
            '<span aria-hidden="true" class="text-white/20">★★★★★</span>' +
            '<span aria-hidden="true" class="absolute inset-y-0 left-0 overflow-hidden whitespace-nowrap text-[#FFB300]" style="width: ' + pct + '%">★★★★★</span>' +
            '</span>' +
            '<span class="' + reviewClass + ' font-medium text-grey-default" aria-label="(' + item.reviews + ' reviews)">(' + item.reviews + ')</span>'
    }

    /* ---------- 商品卡片（原站 article 结构） ---------- */
    function cardHtml(item) {
        return '<article class="flex flex-col" data-candy-shop-item-id="' + item.id + '">' +
            '<a class="flex h-full flex-col gap-2.5" data-shop-item="' + item.id + '" href="./shop.html?item=' + item.id + '">' +
              '<div class="relative aspect-[3/4] w-full overflow-hidden rounded-2xl bg-black-light">' +
                ownedBadge(Number(item.owned || 0), 'Owned × ' + Number(item.owned || 0)) +
                '<div class="absolute inset-0 size-full" data-shop-card>' +
                  '<video preload="none" loop muted playsinline poster="' + esc(item.cover_url) + '" class="absolute inset-0 size-full object-cover object-top">' +
                    '<source src="' + esc(item.video_url) + '" type="video/mp4; codecs=avc1.4D401E">' +
                  '</video>' +
                '</div>' +
              '</div>' +
              '<div class="flex flex-col">' +
                '<div class="flex h-5 items-center">' +
                  '<div class="flex shrink-0 items-center font-poppins gap-0.5">' + starsHtml(item, 'text-xxs', 'text-[9px]') + '</div>' +
                '</div>' +
                '<h3 class="truncate font-poppins text-sm font-semibold text-white"><span class=" ">' + esc(item.title) + '</span></h3>' +
                '<p class="mt-0.5 flex items-center gap-1 font-poppins text-xxs font-bold text-white">' +
                  '<img class="size-4 shrink-0" alt="" src="' + TOKEN_ICON + '"><span>' + item.price + '</span>' +
                '</p>' +
              '</div>' +
            '</a></article>'
    }

    function renderGrid() {
        var grid = document.getElementById('shop-grid')
        if (!grid) return
        if (!items.length) {
            grid.innerHTML = '<p class="col-span-full py-16 text-center font-poppins text-xs leading-5 text-grey-default">No items yet.</p>'
            return
        }
        var html = ''
        for (var i = 0; i < items.length; i++) html += cardHtml(items[i])
        grid.innerHTML = html
    }

    function loadItems(cb) {
        fetch(API_LIST, { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (d) {
                items = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
                renderGrid()
                if (cb) cb()
            })
            .catch(function () {
                items = []
                renderGrid()
                if (cb) cb()
            })
    }

    /* ---------- 列表交互：hover 播放预览视频 / 点击打开详情 ---------- */
    function bindGrid() {
        var grid = document.getElementById('shop-grid')
        if (!grid) return

        grid.addEventListener('mouseover', function (e) {
            var card = e.target.closest ? e.target.closest('[data-shop-card]') : null
            if (!card) return
            var video = card.querySelector('video')
            if (video) { video.play().catch(function () {}) }
        })
        grid.addEventListener('mouseout', function (e) {
            var card = e.target.closest ? e.target.closest('[data-shop-card]') : null
            if (!card) return
            var video = card.querySelector('video')
            if (video) { video.pause(); video.currentTime = 0 }
        })

        grid.addEventListener('click', function (e) {
            var link = e.target.closest ? e.target.closest('[data-shop-item]') : null
            if (!link) return
            e.preventDefault()
            var id = String(link.getAttribute('data-shop-item') || '')
            if (id !== '') history.replaceState(null, '', './shop.html?item=' + encodeURIComponent(id))
            openModal(id)
        })
    }

    /* ---------- Shop / Inventory 切换 ---------- */
    function switchTab(tabId) {
        var TAB_ACTIVE = 'bg-linear-to-r from-pink-default to-pink-dark text-white'
        var tabs = document.querySelectorAll('[role="tab"][data-tab-id]')
        for (var i = 0; i < tabs.length; i++) {
            var el = tabs[i]
            var on = el.getAttribute('data-tab-id') === tabId
            el.setAttribute('aria-selected', on ? 'true' : 'false')
            var cls = el.className
                .replace(/bg-linear-to-r|from-pink-default|to-pink-dark|text-white|text-grey-default|hover:text-white/g, '')
                .replace(/\s+/g, ' ').trim()
            el.className = cls + (on ? ' ' + TAB_ACTIVE : ' text-grey-default hover:text-white')
        }
        var panels = document.querySelectorAll('[data-tab-panel]')
        for (var j = 0; j < panels.length; j++) {
            var p = panels[j]
            var active = p.getAttribute('data-tab-panel') === tabId
            p.classList.toggle('hidden', !active)
            p.classList.toggle('flex', active)
        }
    }

    function bindTabs() {
        var tabs = document.querySelectorAll('[role="tab"][data-tab-id]')
        for (var i = 0; i < tabs.length; i++) {
            tabs[i].addEventListener('click', function () {
                var id = String(this.getAttribute('data-tab-id') || '')
                switchTab(id)
                if (id === 'shop-inventory') loadInventory()
            })
        }
    }

    /* ---------- 背包（Inventory）：已购商品 + 已解锁私密内容 ---------- */
    function loadInventory() {
        if (!token()) {
            inventory = []
            renderInventory()
            return
        }
        fetch(API_INVENTORY, { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (res) {
                inventory = (res && res.code === '00000' && res.data && Array.isArray(res.data.list)) ? res.data.list : []
                renderInventory()
            })
            .catch(function () {
                inventory = []
                renderInventory()
            })
    }

    function inventoryCard(item) {
        var badge = item.item_type === 'private' ? 'Private' : ('Owned × ' + Number(item.quantity || 1))
        var sub = item.creator ? esc(item.creator) : ''
        return '<article class="flex flex-col" data-inventory-item="' + esc(item.item_id) + '">' +
            '<div class="flex h-full flex-col gap-2.5">' +
              '<div class="relative aspect-[3/4] w-full overflow-hidden rounded-2xl bg-black-light">' +
                ownedBadge(1, badge) +
                '<img alt="" loading="lazy" class="absolute inset-0 size-full object-cover object-top" src="' + esc(item.cover_url) + '">' +
              '</div>' +
              '<div class="flex flex-col">' +
                '<h3 class="truncate font-poppins text-sm font-semibold text-white">' + esc(item.title) + '</h3>' +
                '<p class="mt-0.5 flex items-center gap-1 font-poppins text-xxs font-bold text-white">' +
                  '<img class="size-4 shrink-0" alt="" src="' + TOKEN_ICON + '"><span>' + Number(item.price || 0) + '</span>' +
                  (sub ? '<span class="ml-1 font-medium text-grey-default">' + sub + '</span>' : '') +
                '</p>' +
              '</div>' +
            '</div></article>'
    }

    function renderInventory() {
        var box = document.getElementById('shop-inventory-body')
        if (!box) return
        var count = document.getElementById('candy-shop-my-items-count')
        if (count) count.textContent = '(' + inventory.length + ')'

        var gridClass = 'grid grid-cols-2 items-stretch gap-3 md:grid-cols-3 lg:grid-cols-4 lg:gap-4 xl:grid-cols-5'
        if (!token() || !inventory.length) {
            box.className = 'flex flex-1 items-center justify-center py-24'
            box.innerHTML = '<p class="font-poppins text-xs leading-5 text-grey-default">' +
                (token() ? 'No items yet.' : 'Sign in to see your items.') + '</p>'
            return
        }

        var html = ''
        for (var i = 0; i < inventory.length; i++) html += inventoryCard(inventory[i])
        box.className = 'flex flex-1 flex-col'
        box.innerHTML = '<div class="' + gridClass + '">' + html + '</div>'
    }

    /* ---------- 商品详情弹窗 ---------- */
    function dialog() {
        return document.getElementById('candy-shop-product-modal-dialog')
    }

    function buildMedia(item) {
        var list = []
        if (item.video_url) list.push({ kind: 'video', src: item.video_url, poster: item.cover_url })
        if (item.cover_url) list.push({ kind: 'image', src: item.cover_url })
        var extra = Array.isArray(item.images) ? item.images : []
        for (var i = 0; i < extra.length; i++) list.push({ kind: 'image', src: extra[i] })
        return list
    }

    function renderTrack() {
        var track = document.getElementById('shop-modal-track')
        var thumbs = document.getElementById('shop-modal-thumbs')
        if (!track || !thumbs) return

        var html = ''
        var th = ''
        for (var i = 0; i < media.length; i++) {
            var m = media[i]
            if (m.kind === 'video') {
                html += '<video class="size-full shrink-0 basis-full snap-start object-cover object-top" poster="' + esc(m.poster) + '" autoplay loop muted playsinline preload="auto" aria-label="Video preview">' +
                    '<source src="' + esc(m.src) + '" type="video/mp4"></video>'
                th += '<button type="button" aria-pressed="true" aria-label="Video preview" class="relative size-14 shrink-0 overflow-hidden rounded-lg border-2 border-transparent transition aria-pressed:border-pink-default" data-shop-thumb="' + i + '">' +
                    '<img alt="" loading="lazy" class="size-full object-cover object-top" src="' + esc(m.poster) + '">' +
                    '<span aria-hidden="true" class="absolute inset-0 flex items-center justify-center bg-black/40 [&>svg]:size-3 [&>svg]:fill-white">' +
                    '<svg xmlns="http://www.w3.org/2000/svg" width="17" height="20" viewBox="0 0 17 20" fill="none"><path d="M3.32828 0.811786C1.85601 -0.0641227 0 1.0095 0 2.73706V17.263C0 18.9905 1.85601 20.0641 3.32828 19.1882L15.5363 11.9252C16.9879 11.0616 16.9879 8.93838 15.5363 8.07472L3.32828 0.811786Z" fill="white"></path></svg>' +
                    '</span></button>'
            } else {
                html += '<img alt="" loading="lazy" decoding="async" class="size-full shrink-0 basis-full snap-start object-cover object-top" src="' + esc(m.src) + '">'
                th += '<button type="button" aria-pressed="false" aria-label="Photo ' + i + '" class="relative size-14 shrink-0 overflow-hidden rounded-lg border-2 border-transparent transition aria-pressed:border-pink-default" data-shop-thumb="' + i + '">' +
                    '<img alt="" loading="lazy" class="size-full object-cover object-top" src="' + esc(m.src) + '">' +
                    '</button>'
            }
        }
        track.innerHTML = html
        thumbs.innerHTML = th
        track.scrollLeft = 0
    }

    function selectSlide(index) {
        var track = document.getElementById('shop-modal-track')
        if (!track || !media.length) return
        index = Math.max(0, Math.min(media.length - 1, index))
        track.scrollLeft = track.clientWidth * index
        var thumbs = document.querySelectorAll('#shop-modal-thumbs [data-shop-thumb]')
        for (var i = 0; i < thumbs.length; i++) {
            thumbs[i].setAttribute('aria-pressed', String(i) === String(index) ? 'true' : 'false')
        }
    }

    function fillModal(item) {
        document.getElementById('shop-modal-title').textContent = item.title || ''
        document.getElementById('shop-modal-desc').textContent = item.description || ''
        document.getElementById('shop-modal-score-fill').style.width = Math.max(0, Math.min(100, Number(item.rating_pct || 0))) + '%'
        document.getElementById('shop-modal-score').setAttribute('aria-label', 'Rated ' + Number(item.rating || 0).toFixed(1) + ' out of 5')
        document.getElementById('shop-modal-reviews').textContent = '(' + item.reviews + ' reviews)'
        document.getElementById('shop-modal-reviews').setAttribute('aria-label', '(' + item.reviews + ' reviews)')
    }

    function updateTotal() {
        var total = (current ? Number(current.price || 0) : 0) * qty
        document.getElementById('shop-qty-input').value = qty
        document.getElementById('shop-buy-total').textContent = total
        document.getElementById('shop-qty-minus').disabled = qty <= 1
        document.getElementById('shop-qty-plus').disabled = qty >= MAX_QTY
    }

    function resetBuyBar() {
        qty = 1
        document.getElementById('shop-buy-btn').classList.remove('hidden')
        document.getElementById('shop-confirm-btn').classList.add('hidden')
        document.getElementById('shop-confirm-btn').disabled = false
        updateTotal()
    }

    /* ---------- 已购提示（弹窗底部购买栏上方） ---------- */
    function syncOwnedNote() {
        var form = document.getElementById('shop-buy-form')
        if (!form || !current) return
        var note = document.getElementById('shop-owned-note')
        if (!note) {
            note = document.createElement('p')
            note.id = 'shop-owned-note'
            note.style.cssText = 'margin:0 0 8px;font-size:11px;font-weight:600;color:#7EE2A8;'
            form.parentNode.insertBefore(note, form)
        }
        var owned = Number(current.owned || 0)
        note.textContent = owned > 0 ? ('Owned × ' + owned) : ''
        note.style.display = owned > 0 ? 'block' : 'none'
    }

    /* ---------- 下单：POST /api/live/shopBuy ---------- */
    function doBuy() {
        if (!current || needLogin()) return

        var btn = document.getElementById('shop-confirm-btn')
        if (btn.disabled) return
        btn.disabled = true

        fetch(API_BUY, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: 'id=' + encodeURIComponent(current.id) + '&quantity=' + encodeURIComponent(qty),
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                btn.disabled = false
                if (!res || res.code !== '00000') {
                    if (window.layer) layer.msg((res && res.msg) || 'Purchase failed')
                    return
                }

                var owned = Number((res.data && res.data.owned) || 0)
                current.owned = owned
                for (var i = 0; i < items.length; i++) {
                    if (String(items[i].id) === String(current.id)) items[i].owned = owned
                }
                renderGrid()
                resetBuyBar()
                syncOwnedNote()
                if (window.layer) layer.msg('Purchased! It is in your Inventory now')
                loadInventory()
            })
            .catch(function () {
                btn.disabled = false
                if (window.layer) layer.msg('Network error, please try again')
            })
    }

    function openModal(id) {
        var d = dialog()
        if (!d || !id) return
        fetch(API_ITEM + '?id=' + encodeURIComponent(id), { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (res) {
                if (!res || res.code !== '00000' || !res.data) {
                    if (window.layer) layer.msg((res && res.msg) || 'Item not found')
                    return
                }
                current = res.data
                media = buildMedia(current)
                renderTrack()
                fillModal(current)
                resetBuyBar()
                syncOwnedNote()
                if (!d.open) d.showModal()
            })
            .catch(function () { if (window.layer) layer.msg('Network error, please try again') })
    }

    function closeModal() {
        var d = dialog()
        if (!d) return
        var video = d.querySelector('video')
        if (video) video.pause()
        if (d.open) d.close()
        history.replaceState(null, '', './shop.html')
    }

    function bindModal() {
        var d = dialog()
        if (!d) return

        document.getElementById('shop-modal-close').addEventListener('click', closeModal)

        // 点击遮罩关闭（dialog 内部区域不算）
        d.addEventListener('click', function (e) {
            if (e.target === d) closeModal()
        })

        document.getElementById('shop-modal-prev').addEventListener('click', function () {
            var track = document.getElementById('shop-modal-track')
            selectSlide(Math.round(track.scrollLeft / Math.max(1, track.clientWidth)) - 1)
        })
        document.getElementById('shop-modal-next').addEventListener('click', function () {
            var track = document.getElementById('shop-modal-track')
            selectSlide(Math.round(track.scrollLeft / Math.max(1, track.clientWidth)) + 1)
        })

        document.getElementById('shop-modal-thumbs').addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('[data-shop-thumb]') : null
            if (!btn) return
            selectSlide(Number(btn.getAttribute('data-shop-thumb') || 0))
        })

        document.getElementById('shop-qty-minus').addEventListener('click', function () {
            if (qty > 1) { qty--; updateTotal() }
        })
        document.getElementById('shop-qty-plus').addEventListener('click', function () {
            if (qty < MAX_QTY) { qty++; updateTotal() }
        })

        // 两步下单：Buy now 先展开 Confirm（与原站一致），Confirm 真正下单扣钻石
        document.getElementById('shop-buy-btn').addEventListener('click', function () {
            if (needLogin()) return
            document.getElementById('shop-buy-btn').classList.add('hidden')
            document.getElementById('shop-confirm-btn').classList.remove('hidden')
        })

        document.getElementById('shop-buy-form').addEventListener('submit', function (e) {
            e.preventDefault()
            doBuy()
        })
    }

    document.addEventListener('DOMContentLoaded', function () {
        bindGrid()
        bindTabs()
        bindModal()
        renderInventory()
        if (token()) loadInventory()
        loadItems(function () {
            var deep = String(getParam('item') || '')
            if (deep !== '') openModal(deep)
        })
    })
})()
