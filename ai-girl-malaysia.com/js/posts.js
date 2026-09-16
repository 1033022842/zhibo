/**
 * AI 女友端 Posts 动态流（1:1 复刻 candy.ai/feed/posts 的 Posts 页）
 * 动态由后台「直播运营 → 动态管理」配置（GET /api/live/posts），
 * DOM 结构 / class 与原站 .feed-desktop-* 保持一致：
 *   - 竖向 scroll-snap 吸附，一次一屏一条
 *   - IntersectionObserver 给当前可见卡片加 is-visible（原站靠它把媒体下移 75px）并自动播放
 *   - 点击媒体切换播放 / 暂停，暂停时显示共享的 .feed-play-overlay
 *   - 右侧点赞 HUD 跟随当前卡片，点击只切换 UI 状态
 */
(() => {
    var API_LIST = '/api/live/posts'
    var LIKE_ICON = './posts_files/like-6bfb7f5a54d0259524bf6b37730926d02f493c54d1a3aa1db5fdd3a281f15d37.svg'
    var CHAT_NOW = 'Chat Now'
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

    function noop() { }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    /* ---------------- 卡片（等价于原站 createDesktopSlideElement） ---------------- */

    function mediaHtml(post) {
        if (post.video_url) {
            var poster = post.poster_url ? ' poster="' + esc(post.poster_url) + '"' : ''
            return '<div class="absolute inset-0">' +
                '<div class="absolute inset-0 flex items-center justify-center">' +
                  '<div class="w-16 h-16 border-4 border-zinc-700 border-t-white rounded-full animate-spin-slow"></div>' +
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
        return '<div class="feed-desktop-overlay">' +
            '<div class="flex gap-[40px] items-end justify-end w-full">' +
              '<div class="flex-1 flex flex-col gap-3 min-w-0">' +
                '<div class="flex gap-2 items-center w-full">' +
                  '<a href="' + url + '" class="w-8 h-8 rounded-full overflow-hidden shrink-0">' +
                    '<img src="' + esc(post.character_avatar) + '" alt="' + name + '" class="w-full h-full object-cover">' +
                  '</a>' +
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
        bottomSpacer.style.height = (posts.length > 1 ? (posts.length - 1) * full : 0) + 'px'
    }

    /* ---------------- 播放控制 ---------------- */

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

    function setupObserver() {
        observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                var item = entry.target
                var index = parseInt(item.getAttribute('data-index'), 10)
                var video = item.querySelector('video')
                if (entry.intersectionRatio > VISIBLE_RATIO) {
                    item.classList.add('is-visible')
                    if (video && video.paused) {
                        hidePlayOverlay()          // 换页时先收掉上一张的暂停按钮
                        video.play().catch(noop)
                    }
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
        if (e.target.closest('a')) return                       // 头像 / 角色名 / Chat Now 正常跳链接
        var item = e.target.closest('.feed-desktop-item')
        if (!item) return
        var video = item.querySelector('video')
        if (!video) return                                      // 图片卡片没有播放概念
        if (video.paused) {
            video.play().catch(noop)
            hidePlayOverlay()
        } else {
            video.pause()
            showPlayOverlay(item)
        }
    }

    /* 视频外链（candy.ai CDN）拉不到时优雅降级：有封面就用封面，没有就留黑底，不报错 */
    function onMediaError(e) {
        var media = e.target
        if (!media || media.tagName !== 'VIDEO') return
        var item = media.closest ? media.closest('.feed-desktop-item') : null
        if (!item) return
        var wrap = media.parentNode
        var spinner = wrap ? wrap.querySelector('.animate-spin-slow') : null
        if (spinner && spinner.parentNode) spinner.parentNode.style.display = 'none'
        var post = posts[parseInt(item.getAttribute('data-index'), 10)]
        if (post && post.poster_url) {
            var img = document.createElement('img')
            img.className = 'absolute inset-0 w-full h-full object-cover cursor-pointer feed-desktop-media'
            img.src = post.poster_url
            img.alt = post.character_name || ''
            media.style.display = 'none'
            wrap.insertBefore(img, media)
        } else {
            media.style.display = 'none'
        }
    }

    /* ---------------- 点赞 HUD（只切 UI，不落库） ---------------- */

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
    }

    function onLikeClick(e) {
        e.preventDefault()
        e.stopPropagation()
        var post = posts[currentIndex]
        if (!post) return
        post.likes = Math.max(0, (post.likes || 0) + (post.liked ? -1 : 1))
        post.liked = !post.liked
        updateLikeHud()
        // 原站会换成 unlike 图标，本地没有该资源，用原站的 likeSplash 动画表示状态切换
        likeIcon.classList.remove('feed-like-splash')
        void likeIcon.offsetWidth
        likeIcon.classList.add('feed-like-splash')
    }

    /* ---------------- 启动 ---------------- */

    function start() {
        if (loading) loading.classList.add('hidden')
        if (!posts.length) {
            updateLikeHud()
            return
        }
        render()
        measure()
        setupObserver()
        updateLikeHud()
        if (likeButton) likeButton.addEventListener('click', onLikeClick)
        content.addEventListener('click', onContentClick)
        content.addEventListener('error', onMediaError, true)
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer)
            resizeTimer = setTimeout(measure, RESIZE_DELAY)
        })
    }

    if (loading) loading.classList.remove('hidden')

    fetch(API_LIST, { method: 'GET' })
        .then(function (r) { return r.json() })
        .then(function (d) {
            posts = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
            start()
        })
        .catch(function () {
            posts = []
            start()
        })
})()
