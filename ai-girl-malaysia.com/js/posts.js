/**
 * AI 女友端 Discover（原 Posts 动态流）
 *
 * 素材全部来自「直播运营 → 短剧管理」（GET /api/live/shorts），不再混入动态/私密内容。
 * 交互沿用 candy.ai/feed/posts 的原站结构：
 *   - 竖向 scroll-snap 吸附，一次一屏一部剧
 *   - IntersectionObserver 给当前可见卡片加 is-visible 并自动播放（带声音；被浏览器拦截时静音起播，首次交互后恢复声音）
 *   - 点击媒体切换播放 / 暂停，暂停时显示共享的 .feed-play-overlay
 *   - 右侧点赞 HUD 跟随当前卡片，点赞真实落库（POST /api/live/shortLike，需登录）
 *   - 头像 / 剧名 / View Now 指向该剧的短剧详情（shorts.html?short=<id>）
 */
(() => {
    var API_SHORTS = '/api/live/shorts'
    var API_LIKE = '/api/live/shortLike'
    var CHAT_NOW = 'View Now'
    var VISIBLE_RATIO = 0.5          // 与原站一致：可见比例 > 0.5 视为当前卡片
    var ITEM_HEIGHT_FALLBACK = 955   // 原站默认卡片高度，兜底用
    var RESIZE_DELAY = 150

    var container = document.getElementById('feed-desktop-container')
    if (!container) return

    var content = document.getElementById('feed-desktop-content')
    var loading = container.querySelector('[data-feed-loading]')
    var playOverlay = document.getElementById('feed-play-overlay')
    var likeHud = document.getElementById('feed-desktop-like-hud')
    var likeIcon = document.getElementById('feed-desktop-like-icon')
    var likeCount = document.getElementById('feed-desktop-like-count')
    var likeButton = document.getElementById('feed-desktop-like-button')

    var topSpacer = content.firstElementChild
    var bottomSpacer = content.lastElementChild

    var posts = []
    var currentIndex = 0
    var observer = null
    var resizeTimer = null
    var unmuteArmed = false
    var UNMUTE_EVENTS = ['pointerdown', 'touchstart', 'keydown', 'wheel']

    function noop() { }

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

    /* ---------------- 卡片（等价于原站 createDesktopSlideElement） ---------------- */

    function mediaHtml(post) {
        var poster = post.poster_url ? ' poster="' + esc(post.poster_url) + '"' : ''
        if (post.video_url) {
            return '<div class="absolute inset-0">' +
                '<div class="absolute inset-0 flex items-center justify-center">' +
                  '<img class="absolute inset-0 w-full h-full object-cover" src="' + esc(post.poster_url) + '" alt="" aria-hidden="true">' +
                '</div>' +
                '<video src="' + esc(post.video_url) + '"' + poster + ' class="absolute inset-0 w-full h-full object-cover cursor-pointer feed-desktop-media" muted="" playsinline="" loop="" preload="metadata" webkit-playsinline="true" x-webkit-airplay="allow"></video>' +
              '</div>'
        }
        return '<img class="absolute inset-0 w-full h-full object-cover cursor-pointer feed-desktop-media" src="' + esc(post.poster_url) +
            '" alt="' + esc(post.character_name) + '" loading="lazy" />'
    }

    function overlayHtml(post, descriptionHtml) {
        var url = esc(post.character_url)
        var name = esc(post.character_name)
        var avatarSrc = post.character_avatar
        var avatar = avatarSrc
            ? '<a href="' + url + '" class="w-8 h-8 rounded-full overflow-hidden shrink-0">' +
                '<img src="' + esc(avatarSrc) + '" alt="' + name + '" class="w-full h-full object-cover">' +
              '</a>'
            : ''
        return '<div class="feed-desktop-overlay">' +
            '<div class="flex gap-[40px] items-end justify-end w-full">' +
              '<div class="flex-1 flex flex-col gap-3 min-w-0">' +
                '<div class="flex gap-2 items-center w-full">' +
                  avatar +
                  '<a href="' + url + '">' +
                    '<div class="text-white text-[18px] leading-6 font-semibold font-poppins whitespace-nowrap overflow-hidden text-ellipsis min-w-0">' + name + '</div>' +
                  '</a>' +
                  '<a href="' + url + '" class="backdrop-blur-[5px] bg-black/20 border border-white/40 rounded-[8px] px-[9px] py-[7px] shrink-0 flex items-center justify-center">' +
                    '<span class="text-white text-xs leading-4 font-medium font-poppins whitespace-nowrap">' + CHAT_NOW + '</span>' +
                  '</a>' +
                '</div>' + descriptionHtml +
              '</div>' +
            '</div>' +
          '</div>'
    }

    function itemHtml(post, index) {
        var descriptionHtml = post.description
            ? '<p class="text-[#e8e8e8] text-sm leading-[22px] font-medium font-poppins overflow-hidden line-clamp-3 cursor-pointer transition-all">' + esc(post.description) + '</p>'
            : ''
        return '<div class="feed-desktop-video feed-desktop-item" data-post-id="' + esc(post.post_id) + '" data-index="' + index + '">' +
            mediaHtml(post) + overlayHtml(post, descriptionHtml) +
          '</div>'
    }

    function render() {
        var html = ''
        for (var i = 0; i < posts.length; i++) html += itemHtml(posts[i], i)
        // 全部渲染（数据量小，不做原站的 windowBefore / windowAfter 虚拟化），插在上下 spacer 之间
        bottomSpacer.insertAdjacentHTML('beforebegin', html)
    }

    /* ---------------- 吸附高度：spacer 撑出与原站一致的可滚动区间 ---------------- */

    function measure() {
        var first = content.querySelector('.feed-desktop-item')
        var height = ITEM_HEIGHT_FALLBACK
        var gap = 0
        if (first) {
            var rect = first.getBoundingClientRect()
            var cs = window.getComputedStyle(first)
            gap = parseFloat(cs.marginBottom || '0') || 0
            if (rect.height > 0) height = rect.height
        }
        var full = height + gap
        topSpacer.style.height = '0px'
        // 手机端整屏卡片本身就是一屏一条，所有卡片都真实渲染了，再加撑高 spacer 会在末尾多出一段空白
        var isMobile = window.matchMedia('(max-width: 1023px)').matches
        bottomSpacer.style.height = (!isMobile && posts.length > 1 ? (posts.length - 1) * full : 0) + 'px'
    }

    /* ---------------- 播放控制（自动播放 + 声音） ---------------- */

    function showPlayOverlay(item) {
        if (!playOverlay) return
        if (playOverlay.parentNode !== item) item.appendChild(playOverlay)
        playOverlay.style.display = 'flex'
        playOverlay.classList.add('feed-play-overlay-visible')
    }

    function hidePlayOverlay() {
        if (!playOverlay) return
        playOverlay.style.display = 'none'
        playOverlay.classList.remove('feed-play-overlay-visible')
    }

    /* 先尝试带声音自动播放；被浏览器拦截时静音起播，并等用户第一次交互后恢复声音 */
    function playCardVideo(video) {
        if (!video) return
        hidePlayOverlay()
        video.muted = false
        var p = video.play()
        if (!p || !p.catch) return
        p.catch(function () {
            video.muted = true
            var q = video.play()
            if (q && q.catch) q.catch(noop)
            armUnmute()
        })
    }

    function armUnmute() {
        if (unmuteArmed) return
        unmuteArmed = true
        function resume() {
            UNMUTE_EVENTS.forEach(function (ev) { document.removeEventListener(ev, resume, true) })
            unmuteArmed = false
            var vids = content.querySelectorAll('video')
            for (var i = 0; i < vids.length; i++) {
                vids[i].muted = false
                if (!vids[i].paused) vids[i].play().catch(noop)
            }
        }
        UNMUTE_EVENTS.forEach(function (ev) { document.addEventListener(ev, resume, true) })
    }

    function setupObserver() {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var item = entry.target
                var index = parseInt(item.getAttribute('data-index'), 10)
                var video = item.querySelector('video')
                if (entry.intersectionRatio > VISIBLE_RATIO) {
                    item.classList.add('is-visible')
                    if (video && video.paused) playCardVideo(video)
                    if (!isNaN(index) && index !== currentIndex) {
                        currentIndex = index
                        updateLikeHud()
                    }
                } else {
                    item.classList.remove('is-visible')
                    if (video && !video.paused) video.pause()
                }
            })
        }, { root: container, threshold: [0, 0.25, 0.5, 0.75, 1] })

        var items = content.querySelectorAll('.feed-desktop-item')
        for (var i = 0; i < items.length; i++) observer.observe(items[i])
    }

    function onContentClick(e) {
        if (!e.target.closest) return
        if (e.target.closest('a')) return                       // 头像 / 剧名 / View Now 正常跳链接
        var item = e.target.closest('.feed-desktop-item')
        if (!item) return
        var video = item.querySelector('video')
        if (!video) {                                           // 没有预告片的卡片：整卡进短剧详情
            var link = item.querySelector('a[href]')
            if (link) location.href = link.getAttribute('href')
            return
        }
        if (video.paused) {
            video.muted = false
            video.play().catch(noop)
            hidePlayOverlay()
        } else {
            video.pause()
            showPlayOverlay(item)
        }
    }

    /* 视频拉不到时优雅降级：保留封面图，不报错 */
    function onMediaError(e) {
        var media = e.target
        if (!media || media.tagName !== 'VIDEO') return
        media.style.display = 'none'
    }

    /* ---------------- 点赞 HUD（真实落库） ---------------- */

    function updateLikeHud() {
        if (!likeHud) return
        var post = posts[currentIndex]
        if (!post) {
            likeHud.style.visibility = 'hidden'
            return
        }
        likeHud.style.visibility = 'visible'
        likeCount.textContent = post.likes || 0
        likeButton.setAttribute('data-post-id', post.post_id)
        likeButton.setAttribute('aria-pressed', post.liked ? 'true' : 'false')
        if (likeIcon) likeIcon.style.opacity = post.liked ? '1' : '.85'
    }

    function onLikeClick(e) {
        e.preventDefault()
        e.stopPropagation()
        var post = posts[currentIndex]
        if (!post || needLogin()) return
        if (likeButton.dataset.loading === '1') return

        var liked = !post.liked
        var likes = Math.max(0, (post.likes || 0) + (liked ? 1 : -1))

        // 先乐观更新，失败再回滚
        var prevLiked = post.liked
        var prevLikes = post.likes
        post.liked = liked
        post.likes = likes
        updateLikeHud()
        if (likeIcon) {
            likeIcon.classList.remove('feed-like-splash')
            void likeIcon.offsetWidth
            likeIcon.classList.add('feed-like-splash')
        }

        likeButton.dataset.loading = '1'
        fetch(API_LIKE, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: 'short_id=' + encodeURIComponent(post.short_id),
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                likeButton.dataset.loading = '0'
                if (!res || res.code !== '00000') {
                    post.liked = prevLiked
                    post.likes = prevLikes
                    updateLikeHud()
                    if (window.layer) layer.msg((res && res.msg) || 'Action failed')
                    return
                }
                post.liked = Number(res.data && res.data.liked) === 1
                post.likes = Number((res.data && res.data.likes) || 0)
                updateLikeHud()
            })
            .catch(function () {
                likeButton.dataset.loading = '0'
                post.liked = prevLiked
                post.likes = prevLikes
                updateLikeHud()
                if (window.layer) layer.msg('Network error, please try again')
            })
    }

    /* ---------------- 启动 ---------------- */

    function start() {
        if (loading) loading.classList.add('hidden')
        if (!posts.length) {
            if (likeHud) likeHud.style.display = 'none'
            return
        }
        render()
        measure()
        setupObserver()
        content.addEventListener('click', onContentClick)
        content.addEventListener('error', onMediaError, true)
        if (likeButton) likeButton.addEventListener('click', onLikeClick)
        updateLikeHud()
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer)
            resizeTimer = setTimeout(measure, RESIZE_DELAY)
        })
    }

    if (loading) loading.classList.remove('hidden')

    /* 短剧 → 卡片结构：视频用免费首集做预告，付费集不下发 */
    function normalizeShort(s) {
        var episodes = Number(s.episode_count) || 0
        var priceFrom = Number(s.price_from) || 0
        var desc = String(s.description || '').trim()
        if (desc === '' && episodes > 0) {
            desc = episodes + ' episodes' + (priceFrom > 0 ? ' · ' + priceFrom + ' tokens' : ' · Free')
        }
        return {
            short_id: Number(s.id) || 0,
            post_id: 'short-' + s.id,
            character_name: String(s.title || 'Short drama'),
            // 短剧没有角色头像字段，用剧封面当头像
            character_avatar: String(s.poster || ''),
            character_url: './shorts.html?short=' + encodeURIComponent(s.id),
            video_url: String(s.preview_url || s.video_url || ''),
            poster_url: String(s.poster || ''),
            description: desc,
            likes: Number(s.likes) || 0,
            liked: Number(s.liked) === 1
        }
    }

    fetch(API_SHORTS, { method: 'GET', headers: headers() })
        .then(function (r) { return r.json() })
        .then(function (d) {
            var list = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
            posts = list.map(normalizeShort)
            start()
        })
        .catch(function () {
            posts = []
            start()
        })
})()
