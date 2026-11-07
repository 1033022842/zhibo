/**
 * AI 女友端：本站观看弹窗（短剧 / 私密内容共用）
 *
 * 原站没有这个弹窗，是为了让「解锁后在本站观看」而新增的 UI。
 * 说明：各页面引用的 Tailwind 是从原站编译好的成品包，新造的类名不一定存在，
 *       所以这里的布局全部用内联样式，保证在任何页面都能正常显示。
 *
 * 用法：
 *   SiteWatch.open({
 *     title: '标题',
 *     subtitle: '副标题（角色名 / 时长等，可空）',
 *     videoUrl: '视频地址（可空）',
 *     images: ['图片地址', ...],   // 可空
 *     notice: '媒体下方的提示文案（可空）',
 *     emptyText: '没有媒体时的提示文案（可空）'
 *   })
 */
(function () {
    var DIALOG_ID = 'site-watch-dialog'
    var dlg = null

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    function build() {
        if (dlg) return dlg

        dlg = document.createElement('dialog')
        dlg.id = DIALOG_ID
        dlg.setAttribute('aria-label', 'Watch')
        dlg.style.cssText = 'border:0;margin:0;padding:0;width:100%;height:100%;max-width:100%;max-height:100%;' +
            'background:rgba(0,0,0,.86);color:#fff;overflow:auto;'

        dlg.innerHTML =
            '<div style="min-height:100%;display:flex;align-items:center;justify-content:center;padding:16px;">' +
              '<div style="width:100%;max-width:560px;background:#0b0b0d;border:1px solid rgba(255,255,255,.12);' +
                'border-radius:16px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.6);">' +
                '<div style="position:relative;background:#000;">' +
                  '<button type="button" data-watch-close aria-label="Close" ' +
                    'style="position:absolute;right:8px;top:8px;z-index:2;width:32px;height:32px;border:0;cursor:pointer;' +
                    'border-radius:9999px;background:rgba(0,0,0,.6);color:#fff;font-size:20px;line-height:1;">&times;</button>' +
                  '<div data-watch-media style="display:flex;align-items:center;justify-content:center;background:#000;' +
                    'min-height:240px;max-height:72vh;overflow:hidden;"></div>' +
                '</div>' +
                '<div style="padding:14px 16px 18px;">' +
                  '<h3 data-watch-title style="margin:0;font-size:15px;font-weight:600;line-height:1.45;"></h3>' +
                  '<p data-watch-sub style="margin:6px 0 0;font-size:12px;color:#9aa0a6;line-height:1.5;"></p>' +
                  '<p data-watch-notice style="display:none;margin:8px 0 0;font-size:12px;color:#ffac0b;line-height:1.5;"></p>' +
                  '<div data-watch-thumbs style="display:none;gap:8px;overflow-x:auto;margin-top:12px;"></div>' +
                '</div>' +
              '</div>' +
            '</div>'

        dlg.addEventListener('click', function (e) {
            if (e.target === dlg || (e.target.closest && e.target.closest('[data-watch-close]'))) {
                close()
            }
        })

        document.body.appendChild(dlg)
        return dlg
    }

    function mediaHtml(videoUrl, images, emptyText) {
        if (videoUrl) {
            return '<video src="' + esc(videoUrl) + '" controls autoplay playsinline ' +
                'style="width:100%;max-height:72vh;display:block;background:#000;"></video>'
        }
        if (images && images.length) {
            return '<img alt="" src="' + esc(images[0]) + '" data-watch-image ' +
                'style="width:100%;max-height:72vh;object-fit:contain;display:block;background:#000;">'
        }
        return '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">' +
            esc(emptyText || 'This content has no media yet. Please add it in the admin panel.') + '</p>'
    }

    /* ---------- 播放兜底 ----------
     * 后台允许上传任意 mp4，历史上混进过 HEVC(H.265) 素材（如 203459_00001__slp_aion1_rxl1）。
     * 浏览器「容器能解、编码解不了」时不会触发 error，而是静默只播音轨：
     * 用户看到的是黑屏 + 有声音 + 进度条在走。这种状态用 videoWidth 判定最可靠
     * （只有视频轨成功解出，videoWidth 才会非 0）。
     */
    function fallbackHtml(videoUrl, reason) {
        var body = reason === 'codec'
            ? 'This video’s picture track can’t be displayed by your browser (it may be encoded as HEVC/H.265). Try opening it in a new window, where your device player may decode it.'
            : 'This video could not be loaded. Please check your connection and try again.'
        return '<div style="padding:44px 22px;text-align:center;">' +
            '<p style="margin:0 0 8px;font-size:14px;font-weight:600;color:#fff;">This video can’t be played here</p>' +
            '<p style="margin:0 0 18px;font-size:12px;line-height:1.6;color:#9aa0a6;">' + esc(body) + '</p>' +
            '<a href="' + esc(videoUrl) + '" target="_blank" rel="noopener noreferrer" ' +
              'style="display:inline-block;padding:9px 20px;border-radius:10px;text-decoration:none;' +
              'background:linear-gradient(90deg,#E75275 0%,#FF6B9A 100%);color:#fff;font-size:13px;font-weight:600;">' +
              'Open in new window</a>' +
            '</div>'
    }

    function attachVideoGuards(media, videoUrl) {
        var v = media.querySelector('video')
        if (!v) return

        var dead = false
        function fail(reason) {
            if (dead) return
            dead = true
            try { v.pause() } catch (e) { /* 忽略 */ }
            media.innerHTML = fallbackHtml(videoUrl, reason)
        }

        // error：整条流取不到，或浏览器完全不认识这个容器/编码
        // 1=中断 2=网络 3=解码 4=不支持该媒体类型
        v.addEventListener('error', function () {
            var code = v.error && v.error.code
            fail(code === 3 || code === 4 ? 'codec' : 'network')
        })

        // 画面轨是否解出来，要等首帧真的解码完才准（部分浏览器 metadata 阶段 videoWidth 仍是 0），
        // 所以统一延后判定，避免误伤正常视频。
        var checked = false
        function checkPicture(delay) {
            setTimeout(function () {
                if (dead || checked) return
                checked = true
                if (!v.videoWidth) fail('codec')
            }, delay)
        }
        v.addEventListener('loadedmetadata', function () { checkPicture(2500) })
        v.addEventListener('playing', function () { checkPicture(1500) })
    }

    function thumbHtml(images) {
        if (!images || images.length < 2) return ''
        var html = ''
        for (var i = 0; i < images.length; i++) {
            html += '<button type="button" data-watch-thumb="' + i + '" aria-label="Photo ' + (i + 1) + '" ' +
                'style="flex:0 0 auto;width:56px;height:56px;padding:0;cursor:pointer;overflow:hidden;border-radius:8px;' +
                'border:1px solid rgba(255,255,255,.25);background:none;">' +
                '<img alt="" src="' + esc(images[i]) + '" style="width:100%;height:100%;object-fit:cover;display:block;">' +
                '</button>'
        }
        return html
    }

    function open(data) {
        var d = build()
        var opt = data || {}

        var media = d.querySelector('[data-watch-media]')
        var thumbs = d.querySelector('[data-watch-thumbs]')

        media.innerHTML = mediaHtml(opt.videoUrl, opt.images, opt.emptyText)
        if (opt.videoUrl) attachVideoGuards(media, opt.videoUrl)
        d.querySelector('[data-watch-title]').textContent = opt.title || ''
        d.querySelector('[data-watch-sub]').textContent = opt.subtitle || ''

        // 观看历史：打开即记一条；有视频时随播放同步进度（节流在 WatchHistory 内部）
        var hist = opt.history || null
        if (hist && hist.id && window.WatchHistory) {
            window.WatchHistory.report({ type: hist.type, id: hist.id, progress: 0 })
            var hv = media.querySelector('video')
            if (hv) {
                hv.addEventListener('timeupdate', function () {
                    if (!isFinite(hv.duration) || hv.duration <= 0) return
                    window.WatchHistory.report({
                        type: hist.type,
                        id: hist.id,
                        progress: (hv.currentTime / hv.duration) * 100,
                        position: hv.currentTime,
                        duration: hv.duration
                    })
                })
            }
        }

        var notice = d.querySelector('[data-watch-notice]')
        notice.textContent = opt.notice || ''
        notice.style.display = opt.notice ? 'block' : 'none'

        thumbs.innerHTML = thumbHtml(opt.images)
        thumbs.style.display = opt.images && opt.images.length > 1 ? 'flex' : 'none'
        thumbs.onclick = function (e) {
            var btn = e.target.closest ? e.target.closest('[data-watch-thumb]') : null
            if (!btn) return
            var img = media.querySelector('[data-watch-image]')
            if (img) img.src = opt.images[Number(btn.getAttribute('data-watch-thumb')) || 0]
        }

        if (!d.open) d.showModal()
    }

    function close() {
        if (!dlg) return
        var v = dlg.querySelector('video')
        if (v) {
            v.pause()
            v.removeAttribute('src')
            v.load()
        }
        if (dlg.open) dlg.close()
    }

    window.SiteWatch = { open: open, close: close }
})()
