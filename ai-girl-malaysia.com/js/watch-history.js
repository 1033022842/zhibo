/**
 * AI 女友端：观看历史（Continue watching）
 *
 * 用法：
 *   WatchHistory.load('all' | 'short' | 'private', limit) -> Promise<item[]>
 *   WatchHistory.report({ type, id, episode_id, progress, position, duration })
 *   WatchHistory.card(item, { width: 190, aspect: '7 / 10' })  -> HTML 字符串
 *   WatchHistory.bind()   页面级点击委托（短剧 → 剧集弹窗；私密内容 → 详情弹窗）
 *
 * 未登录时 load 直接返回空数组，report 静默丢弃，不打扰游客浏览。
 */
(function () {
    var REPORT_API = '/api/live/watchReport'
    var LIST_API = '/api/live/watchHistory'
    var PRIVATE_DETAIL_API = '/api/live/privateContent'

    function token() {
        try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' }
    }

    function headers(extra) {
        var h = extra || {}
        var t = token()
        if (t) h.Authorization = 'Bearer ' + t
        return h
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    /* ---------- 上报（同一内容 4 秒内只发一次，避免 timeupdate 刷爆接口） ---------- */
    var sentAt = {}

    function report(payload) {
        if (!payload || !payload.id || !token()) return
        var type = payload.type === 'private' ? 'private' : 'short'
        var key = type + ':' + payload.id
        var now = Date.now()
        if (sentAt[key] && now - sentAt[key] < 4000) return
        sentAt[key] = now

        var body = new URLSearchParams()
        body.set('type', type)
        body.set('id', String(payload.id))
        if (payload.episode_id) body.set('episode_id', String(payload.episode_id))
        body.set('progress', Math.max(0, Math.min(100, Number(payload.progress) || 0)).toFixed(2))
        body.set('position', String(Math.max(0, Math.floor(Number(payload.position) || 0))))
        body.set('duration', String(Math.max(0, Math.floor(Number(payload.duration) || 0))))

        fetch(REPORT_API, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: body.toString(),
            keepalive: true
        }).catch(function () { /* 上报失败不影响观看 */ })
    }

    /* ---------- 读取 ---------- */
    function load(type, limit) {
        if (!token()) return Promise.resolve([])
        var url = LIST_API + '?type=' + encodeURIComponent(type || 'all') + '&limit=' + (limit || 20)
        return fetch(url, { headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (d) {
                return (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
            })
            .catch(function () { return [] })
    }

    /* ---------- 卡片（带进度条；风格对齐 candy 的 Continue watching） ---------- */
    function card(item, opts) {
        var o = opts || {}
        var p = Math.max(0, Math.min(100, Number(item.progress) || 0))
        var w = Number(o.width) || 190
        var aspect = o.aspect || '7 / 10'
        var badge = item.type === 'private' ? 'Private' : 'Short'
        return '<a class="wh-card" href="javascript:void(0)" draggable="false"' +
            ' data-wh-type="' + esc(item.type) + '"' +
            ' data-wh-id="' + esc(item.id) + '"' +
            ' data-wh-episode="' + esc(item.episode_id || 0) + '"' +
            ' data-wh-title="' + esc(item.title) + '"' +
            ' aria-label="' + esc(item.title) + '"' +
            ' style="display:block;width:' + w + 'px;flex:0 0 auto;text-decoration:none;">' +
            '<div style="position:relative;width:100%;aspect-ratio:' + aspect + ';border-radius:14px;overflow:hidden;' +
                'background:#131313;border:1px solid rgba(255,255,255,.07);">' +
              '<img src="' + esc(item.poster) + '" alt="" loading="lazy" ' +
                'style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block;">' +
              '<span style="position:absolute;left:8px;top:8px;padding:2px 7px;border-radius:6px;' +
                'background:rgba(0,0,0,.6);font-size:10px;font-weight:700;line-height:14px;color:#fff;' +
                'letter-spacing:.03em;backdrop-filter:blur(4px);">' + badge + '</span>' +
              '<div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' + Math.round(p) + '" ' +
                'style="position:absolute;left:8px;right:8px;bottom:8px;height:3px;border-radius:9999px;' +
                'background:rgba(255,255,255,.35);overflow:hidden;">' +
                '<i style="display:block;height:100%;width:' + p.toFixed(1) + '%;background:#fff;border-radius:9999px;"></i>' +
              '</div>' +
            '</div>' +
          '</a>'
    }

    /* ---------- 打开一条历史 ---------- */
    function open(item) {
        if (!item || !item.id) return
        if (item.type === 'short') {
            if (window.CandySeries) {
                window.CandySeries.open({
                    shortId: item.id,
                    title: item.title || '',
                    episodeId: item.episode_id || 0
                })
            }
            return
        }

        // 私密内容：先取详情（只有已解锁才带媒体地址）
        fetch(PRIVATE_DETAIL_API + '?id=' + encodeURIComponent(item.id), { headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (res) {
                var it = res && res.code === '00000' && res.data ? res.data.item : null
                if (!it) return
                if (!Number(it.unlocked)) {
                    // 未解锁：回私密内容页自行解锁
                    location.href = './private_content.html'
                    return
                }
                if (!window.CandyWatch) return
                var sub = []
                if (it.creator) sub.push(it.creator)
                if (Number(it.video_count) > 0) sub.push(it.video_count + (Number(it.video_count) > 1 ? ' videos' : ' video'))
                if (Number(it.image_count) > 0) sub.push(it.image_count + (Number(it.image_count) > 1 ? ' photos' : ' photo'))
                window.CandyWatch.open({
                    title: it.title || it.creator || 'Private Content',
                    subtitle: sub.join(' · '),
                    videoUrl: it.video_url || '',
                    images: Array.isArray(it.images) ? it.images : [],
                    emptyText: 'This pack has no media yet.',
                    history: { type: 'private', id: it.id }
                })
            })
            .catch(function () { /* 忽略 */ })
    }

    var bound = false
    function bind() {
        if (bound) return
        bound = true
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest ? e.target.closest('[data-wh-type]') : null
            if (!el) return
            e.preventDefault()
            open({
                type: el.getAttribute('data-wh-type'),
                id: Number(el.getAttribute('data-wh-id')) || 0,
                episode_id: Number(el.getAttribute('data-wh-episode')) || 0,
                title: el.getAttribute('data-wh-title') || ''
            })
        })
    }

    window.WatchHistory = {
        report: report,
        load: load,
        card: card,
        open: open,
        bind: bind,
        token: token,
        headers: headers
    }
})()
