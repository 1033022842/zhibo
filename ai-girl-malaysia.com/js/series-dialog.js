/**
 * AI 女友端：短剧剧集弹窗（多集 + 按集解锁 + 介绍）
 *
 * 原站没有这个弹窗，是为「一部剧多集、每集可单独收费」新增的 UI。
 * 说明：各页面引用的 Tailwind 是从原站编译好的成品包，新造的类名不一定存在，
 *       所以这里的布局全部用内联样式，保证在任何页面都能正常显示。
 *
 * 用法：
 *   CandySeries.open({ shortId: 15, title: 'Student Bodies' })
 *
 * 交互：
 *   - 免费集（集号 <= 剧的「前N集免费」或单集价格 0）与已解锁集：点击即播放
 *   - 锁住的集：第一次点击把按钮变成 Confirm，再点才真正扣钻石解锁
 *   - 钻石不足等失败提示用原生 dialog（layer 的 z-index 盖不过 modal dialog）
 */
(function () {
    var DIALOG_ID = 'candy-series-dialog'
    var API_EPISODES = '/api/live/shortEpisodes'
    var API_UNLOCK = '/api/live/shortUnlock'

    var dlg = null
    var state = {
        shortId: 0,
        title: '',
        description: '',
        freeEpisodes: 0,
        episodes: [],
        currentId: 0,
        confirmId: 0, // 已点过一次、等待二次确认解锁的剧集
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

    /* ---------- 失败提示（原生 dialog，避免被 modal 的 top layer 挡住） ---------- */
    function errText(res) {
        var code = res && res.code
        if (code === 'C0100') return 'Not enough diamonds. Please top up first.'
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

    /* ---------- DOM ---------- */
    function build() {
        if (dlg) return dlg

        dlg = document.createElement('dialog')
        dlg.id = DIALOG_ID
        dlg.setAttribute('aria-label', 'Series')
        dlg.style.cssText = 'border:0;margin:0;padding:0;width:100%;height:100%;max-width:100%;max-height:100%;' +
            'background:rgba(0,0,0,.86);color:#fff;overflow:auto;'

        dlg.innerHTML =
            '<div style="min-height:100%;display:flex;align-items:center;justify-content:center;padding:16px;">' +
              '<div style="width:100%;max-width:560px;background:#0b0b0d;border:1px solid rgba(255,255,255,.12);' +
                'border-radius:16px;overflow:hidden;box-shadow:0 18px 50px rgba(0,0,0,.6);">' +
                '<div style="position:relative;background:#000;">' +
                  '<button type="button" data-series-close aria-label="Close" ' +
                    'style="position:absolute;right:8px;top:8px;z-index:2;width:32px;height:32px;border:0;cursor:pointer;' +
                    'border-radius:9999px;background:rgba(0,0,0,.6);color:#fff;font-size:20px;line-height:1;">&times;</button>' +
                  '<div data-series-media style="display:flex;align-items:center;justify-content:center;background:#000;' +
                    'min-height:220px;max-height:60vh;overflow:hidden;"></div>' +
                '</div>' +
                '<div style="padding:14px 16px 18px;">' +
                  '<h3 data-series-title style="margin:0;font-size:16px;font-weight:600;line-height:1.4;"></h3>' +
                  '<p data-series-meta style="margin:6px 0 0;font-size:12px;color:#9aa0a6;line-height:1.5;"></p>' +
                  '<p data-series-desc style="display:none;margin:8px 0 0;font-size:12px;color:#c8ccd0;line-height:1.6;' +
                    'max-height:96px;overflow:auto;"></p>' +
                  '<div data-series-list style="margin-top:12px;max-height:300px;overflow:auto;"></div>' +
                '</div>' +
              '</div>' +
            '</div>'

        // 所有点击都在这里统一处理：按钮和整行走同一套逻辑，避免同一次点击被处理两次
        dlg.addEventListener('click', function (e) {
            if (e.target === dlg || (e.target.closest && e.target.closest('[data-series-close]'))) {
                close()
                return
            }

            var unlockBtn = e.target.closest ? e.target.closest('[data-series-unlock]') : null
            if (unlockBtn) {
                var ep = episodeById(Number(unlockBtn.getAttribute('data-series-unlock')) || 0)
                if (ep) unlock(ep)
                return
            }

            var row = e.target.closest ? e.target.closest('[data-series-ep]') : null
            if (row) {
                onEpisodeClick(Number(row.getAttribute('data-series-ep')) || 0)
            }
        })

        document.body.appendChild(dlg)
        return dlg
    }

    function episodeById(id) {
        for (var i = 0; i < state.episodes.length; i++) {
            if (Number(state.episodes[i].id) === Number(id)) return state.episodes[i]
        }
        return null
    }

    function episodeLabel(ep) {
        var name = ep.title ? ('Ep.' + ep.episode_no + ' ' + ep.title) : ('Episode ' + ep.episode_no)
        return name
    }

    /* ---------- 渲染 ---------- */
    function renderHeader() {
        dlg.querySelector('[data-series-title]').textContent = state.title
        var meta = state.episodes.length + (state.episodes.length > 1 ? ' episodes' : ' episode')
        if (state.freeEpisodes > 0) {
            meta += ' · first ' + state.freeEpisodes + ' free'
        }
        dlg.querySelector('[data-series-meta]').textContent = meta

        var desc = dlg.querySelector('[data-series-desc]')
        desc.textContent = state.description || ''
        desc.style.display = state.description ? 'block' : 'none'
    }

    function mediaHtml(ep) {
        if (!ep) {
            return '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">' +
                'This series has no episode yet. Please add it in the admin panel.</p>'
        }
        if (ep.unlocked && ep.video_url) {
            return '<video src="' + esc(ep.video_url) + '" controls autoplay playsinline ' +
                'style="width:100%;max-height:60vh;display:block;background:#000;"></video>'
        }
        if (ep.unlocked) {
            return '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">' +
                'This episode has no video yet. Please add it in the admin panel.</p>'
        }
        // 锁住：封面（模糊）+ 价格 + 解锁按钮
        var poster = ep.poster
            ? '<img alt="" src="' + esc(ep.poster) + '" style="position:absolute;inset:0;width:100%;height:100%;' +
              'object-fit:cover;filter:blur(8px) brightness(.6);display:block;">'
            : ''
        return '<div style="position:relative;width:100%;min-height:220px;display:flex;flex-direction:column;' +
            'align-items:center;justify-content:center;gap:12px;">' + poster +
            '<div style="position:relative;text-align:center;padding:0 20px;">' +
              '<p style="margin:0;font-size:13px;font-weight:600;">' + esc(episodeLabel(ep)) + ' is locked</p>' +
              '<p style="margin:6px 0 0;font-size:12px;color:#c8ccd0;">Unlock this episode to watch · ' + ep.price + ' diamonds</p>' +
            '</div>' +
            '<button type="button" data-series-unlock="' + ep.id + '" style="position:relative;border:0;cursor:pointer;' +
              'padding:10px 26px;border-radius:10px;font-size:14px;font-weight:600;color:#fff;' +
              'background:' + (state.confirmId === Number(ep.id)
                ? 'linear-gradient(90deg,#fdc706 0%,#ffa800 100%)'
                : 'linear-gradient(90deg,#E75275 0%,#FF6B9A 100%)') + ';">' +
              (state.confirmId === Number(ep.id) ? 'Confirm · ' + ep.price : 'Unlock · ' + ep.price) +
            '</button>' +
            '</div>'
    }

    function renderList() {
        var html = ''
        for (var i = 0; i < state.episodes.length; i++) {
            var ep = state.episodes[i]
            var isCurrent = Number(ep.id) === Number(state.currentId)
            var isConfirm = Number(ep.id) === Number(state.confirmId)
            var buttonText, buttonBg
            if (ep.unlocked) {
                buttonText = isCurrent ? 'Playing' : 'Play'
                buttonBg = 'rgba(255,255,255,.12)'
            } else if (isConfirm) {
                buttonText = 'Confirm · ' + ep.price
                buttonBg = 'linear-gradient(90deg,#fdc706 0%,#ffa800 100%)'
            } else {
                buttonText = ep.price + ' 💎'
                buttonBg = 'linear-gradient(90deg,#E75275 0%,#FF6B9A 100%)'
            }

            var sub = []
            if (ep.duration) sub.push(esc(ep.duration))
            if (ep.free) sub.push('Free')
            else if (ep.unlocked) sub.push('Unlocked')
            else sub.push('Locked')

            html +=
                '<div data-series-ep="' + ep.id + '" style="display:flex;align-items:center;gap:10px;padding:8px;' +
                  'border-radius:10px;cursor:pointer;margin-bottom:6px;' +
                  'background:' + (isCurrent ? 'rgba(231,82,117,.14)' : 'rgba(255,255,255,.04)') + ';' +
                  'border:1px solid ' + (isCurrent ? 'rgba(231,82,117,.5)' : 'transparent') + ';">' +
                  '<div style="position:relative;flex:0 0 auto;width:76px;height:46px;border-radius:8px;overflow:hidden;background:#000;">' +
                    (ep.poster
                      ? '<img alt="" src="' + esc(ep.poster) + '" style="width:100%;height:100%;object-fit:cover;display:block;' +
                        (ep.unlocked ? '' : 'filter:blur(3px) brightness(.7);') + '">'
                      : '') +
                    '<span style="position:absolute;left:4px;top:4px;padding:0 6px;border-radius:6px;background:rgba(0,0,0,.66);' +
                      'font-size:10px;font-weight:600;line-height:16px;">' + ep.episode_no + '</span>' +
                  '</div>' +
                  '<div style="flex:1 1 auto;min-width:0;">' +
                    '<div style="font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' +
                      esc(episodeLabel(ep)) + '</div>' +
                    '<div style="font-size:11px;color:#9aa0a6;margin-top:2px;">' + sub.join(' · ') + '</div>' +
                  '</div>' +
                  '<button type="button" data-series-act="' + ep.id + '" style="flex:0 0 auto;border:0;cursor:pointer;' +
                    'padding:7px 12px;border-radius:8px;font-size:12px;font-weight:600;color:#fff;background:' + buttonBg + ';">' +
                    buttonText + '</button>' +
                '</div>'
        }
        dlg.querySelector('[data-series-list]').innerHTML = html
    }

    function render() {
        renderHeader()
        var current = episodeById(state.currentId) || state.episodes[0] || null
        if (current) state.currentId = Number(current.id)
        dlg.querySelector('[data-series-media]').innerHTML = mediaHtml(current)
        renderList()
    }

    /* ---------- 行为 ---------- */
    function playEpisode(ep) {
        if (!ep || !ep.unlocked) return
        state.currentId = Number(ep.id)
        state.confirmId = 0
        render()
    }

    function onEpisodeClick(id) {
        var ep = episodeById(id)
        if (!ep) return

        if (ep.unlocked) {
            playEpisode(ep)
            return
        }

        if (needLogin()) return

        // 锁住：第一次点击进入确认态，第二次点击才扣钻石
        if (Number(state.confirmId) !== Number(ep.id)) {
            state.confirmId = Number(ep.id)
            render()
            return
        }
        unlock(ep)
    }

    function unlock(ep) {
        var id = Number(ep.id)
        if (state.confirmId !== id) {
            state.confirmId = id
            render()
            return
        }

        fetch(API_UNLOCK, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: 'episode_id=' + encodeURIComponent(id),
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                if (!res || res.code !== '00000') {
                    alertBox(errText(res))
                    return
                }
                var fresh = res.data && res.data.episode ? res.data.episode : null
                ep.unlocked = 1
                ep.video_url = fresh ? (fresh.video_url || '') : ''
                state.confirmId = 0
                if (window.layer) {
                    layer.msg(Number(res.data && res.data.charged) === 1 ? 'Unlocked!' : 'Already unlocked')
                }
                playEpisode(ep)
            })
            .catch(function () { alertBox('Network error, please try again') })
    }

    function load(shortId) {
        var url = API_EPISODES + '?short_id=' + encodeURIComponent(shortId)
        fetch(url, { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (res) {
                if (!res || res.code !== '00000' || !res.data) {
                    dlg.querySelector('[data-series-media]').innerHTML =
                        '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">' +
                        esc((res && res.msg) || 'Failed to load episodes.') + '</p>'
                    return
                }
                var s = res.data.short || {}
                state.shortId = Number(s.id || shortId) || 0
                state.title = s.title || state.title || ''
                state.description = s.description || ''
                state.freeEpisodes = Number(s.free_episodes || 0)
                state.episodes = Array.isArray(res.data.episodes) ? res.data.episodes : []
                state.confirmId = 0

                // 默认播放第一集（免费集优先，没有可看的就展示第一集的解锁态）
                var first = state.episodes[0] || null
                for (var i = 0; i < state.episodes.length; i++) {
                    if (state.episodes[i].unlocked) { first = state.episodes[i]; break }
                }
                state.currentId = first ? Number(first.id) : 0
                render()
            })
            .catch(function () {
                dlg.querySelector('[data-series-media]').innerHTML =
                    '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">' +
                    'Network error, please try again.</p>'
            })
    }

    function open(opts) {
        var opt = opts || {}
        var d = build()

        state.shortId = Number(opt.shortId || 0)
        state.title = opt.title || ''
        state.description = opt.description || ''
        state.freeEpisodes = 0
        state.episodes = []
        state.currentId = 0
        state.confirmId = 0

        d.querySelector('[data-series-media]').innerHTML =
            '<p style="margin:0;padding:56px 24px;color:#9aa0a6;font-size:13px;text-align:center;">Loading...</p>'
        d.querySelector('[data-series-list]').innerHTML = ''
        renderHeader()

        if (!d.open) d.showModal()
        load(state.shortId)
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

    window.CandySeries = { open: open, close: close }
})()
