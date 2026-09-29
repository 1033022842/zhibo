/**
 * AI 女友端：短剧剧集弹窗（复刻 candy.ai/candy-shorts 的剧集弹窗）
 *
 * 布局对齐原站（实测值）：
 *   面板 920x730、圆角 16px、底色 #131313、边框 1px #282828，顶部粉色径向光晕；
 *   主体 flex 横排、间距 29px：左栏 9:16 视频（352x626、圆角 15px、黑底、
 *   关掉原生 controls 用自定义控制条 + 中心 80px 粉色描边播放圆钮），
 *   右栏 481x626（圆角 12px、边框 #282828、可纵向滚动）：标题 24px/500 白、
 *   简介 14px/500 #828282、1px 分隔线（外距 22px）、剧集行 409x51
 *   （圆角 10px、padding 14px 20px、行距 12px、当前集粉边框 #E75275）。
 *
 * 手机端（<1024px）：一集一屏的竖向滑动播放器（像刷短视频一样上滑/下滑切集），
 *   每屏铺满视口并 scroll-snap 吸附；滑到哪集就自动播哪集，其余暂停；
 *   顶部关闭按钮 + 集数，底部标题/简介 + 进度条与播放控制；未解锁的集
 *   显示模糊封面 + 解锁 CTA。
 *
 * 与原站的差异（本站是「按集收费」）：付费未解锁的剧集行右侧显示 白锁 + 价格
 * （原站只在 data 属性里存 token 成本，界面不显示价格）；点该集后视频区显示
 * 渐变 CTA，点 CTA 变 Confirm，再点才真正扣钻石解锁；免费集显示绿色 Free。
 *
 * 用法：CandySeries.open({ shortId: 15, title: 'Student Bodies' })
 */
(function () {
    var DIALOG_ID = 'candy-series-dialog'
    var STYLE_ID = 'candy-series-style'
    var API_EPISODES = '/api/live/shortEpisodes'
    var API_UNLOCK = '/api/live/shortUnlock'
    var ACCENT = '#E75275'

    var SVG_PLAY = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'
    var SVG_PAUSE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>'
    var SVG_VOLUME = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 4V5L7 9H3zm13.5 3c0-1.8-1-3.3-2.5-4v8c1.5-.7 2.5-2.2 2.5-4z"/></svg>'
    var SVG_MUTE = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 9v6h4l5 4V5L7 9H3zm17.7 3 2.1-2.1-1.4-1.4-2.1 2.1-2.1-2.1-1.4 1.4 2.1 2.1-2.1 2.1 1.4 1.4 2.1-2.1 2.1 2.1 1.4-1.4-2.1-2.1z"/></svg>'
    var SVG_FULL = '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/></svg>'
    var SVG_BIG_PLAY = '<svg viewBox="0 0 24 24" width="30" height="30" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>'

    /* 会员「去模糊」由平台开关控制（window.LIVE_VIP.unblur_preview，默认关闭） */
    function vipUnblur() {
        return !!(window.LIVE_VIP && window.LIVE_VIP.is_vip && window.LIVE_VIP.unblur_preview)
    }
    var SVG_LOCK = '<svg class="cs-lock" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17 9V7a5 5 0 0 0-10 0v2a2 2 0 0 0-1 1.7V19a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2v-8.3A2 2 0 0 0 17 9zm-8-2a3 3 0 0 1 6 0v2H9V7zm3 10a1.6 1.6 0 0 1-1-2.8V13a1 1 0 0 1 2 0v1.2A1.6 1.6 0 0 1 12 17z"/></svg>'

    var RATES = [1, 1.25, 1.5, 2]

    var dlg = null
    var feedObserver = null
    var lastMobile = null
    var state = {
        shortId: 0,
        title: '',
        description: '',
        freeEpisodes: 0,
        episodes: [],
        currentId: 0,   // 正在展示的剧集（手机端=当前那一屏）
        confirmId: 0,   // CTA 已点过一次、等待二次确认的剧集
        rateIndex: 0,
        autoPlay: false, // 只有刚解锁成功那一集才自动播放，其余保持暂停待播（同原站）
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
    }

    function isMobile() {
        return window.matchMedia('(max-width:1023px)').matches
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

    /* ---------- 样式（原站弹窗实测尺寸与配色；独立注入，不依赖 Tailwind 成品包） ---------- */
    function injectStyle() {
        if (document.getElementById(STYLE_ID)) return
        var s = document.createElement('style')
        s.id = STYLE_ID
        s.textContent = [
            '#' + DIALOG_ID + '{border:0;margin:0;padding:0;width:100%;height:100%;max-width:100%;max-height:100%;background:transparent;color:#fff;}',
            '#' + DIALOG_ID + '::backdrop{background:rgba(0,0,0,.8);}',
            '#' + DIALOG_ID + ' .cs-wrap{position:fixed;inset:0;display:flex;align-items:stretch;justify-content:center;padding:0;}',
            '#' + DIALOG_ID + ' .cs-panel{position:relative;display:flex;flex-direction:column;width:100%;height:100%;background:#131313;border:1px solid #282828;overflow:hidden;}',
            // 桌面两栏布局容器：手机端整屏滑动切集时隐藏
            '#' + DIALOG_ID + ' .cs-scroll{display:none;flex:1 1 auto;overflow-y:auto;overflow-x:hidden;}',
            '#' + DIALOG_ID + ' .cs-inner{display:flex;flex-direction:column;align-items:center;gap:18px;min-height:100%;padding:16px 16px 28px;' +
                'background:radial-gradient(at center top, rgba(236,74,125,.67) 0%, rgba(0,0,0,0) 35%);}',
            '#' + DIALOG_ID + ' .cs-head{position:relative;display:flex;align-items:center;justify-content:center;width:100%;min-height:36px;}',
            '#' + DIALOG_ID + ' .cs-close{position:absolute;left:0;top:0;display:flex;align-items:center;justify-content:center;width:36px;height:36px;' +
                'border:1px solid rgba(255,255,255,.16);border-radius:9999px;background:rgba(0,0,0,.4);color:#fff;font-size:16px;line-height:1;cursor:pointer;}',
            '#' + DIALOG_ID + ' .cs-heading{margin:0;font-size:16px;font-weight:600;line-height:36px;color:#fff;}',
            '#' + DIALOG_ID + ' .cs-body{display:flex;flex-direction:column;align-items:stretch;gap:18px;width:100%;min-width:0;}',

            // 视频区（桌面左栏 / 手机整屏共用）
            '#' + DIALOG_ID + ' .cs-stage{position:relative;width:100%;height:75dvh;overflow:hidden;border-radius:8px;background:#000;}',
            '#' + DIALOG_ID + ' .cs-video{display:block;width:100%;height:100%;object-fit:cover;background:#000;}',
            '#' + DIALOG_ID + ' .cs-center{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);display:flex;align-items:center;justify-content:center;' +
                'width:80px;height:80px;border:2px solid ' + ACCENT + ';border-radius:9999px;background:rgba(0,0,0,.6);color:#fff;cursor:pointer;' +
                'backdrop-filter:blur(6px);}',
            '#' + DIALOG_ID + ' .cs-controls{position:absolute;left:0;right:0;bottom:0;padding:48px 16px 12px;' +
                'background:linear-gradient(to top, rgba(0,0,0,.82) 0%, rgba(0,0,0,.48) 50%, rgba(0,0,0,0) 100%);}',
            '#' + DIALOG_ID + ' .cs-progress{position:relative;height:4px;border-radius:9999px;background:rgba(255,255,255,.35);cursor:pointer;transition:height .15s;}',
            '#' + DIALOG_ID + ' .cs-progress:hover{height:8px;}',
            '#' + DIALOG_ID + ' .cs-progress-fill{position:absolute;left:0;top:0;bottom:0;width:0;border-radius:9999px;background:' + ACCENT + ';}',
            '#' + DIALOG_ID + ' .cs-bar{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:8px;}',
            '#' + DIALOG_ID + ' .cs-time{font-size:14px;font-weight:600;color:#fff;}',
            '#' + DIALOG_ID + ' .cs-time i{font-style:normal;color:rgba(255,255,255,.55);}',
            '#' + DIALOG_ID + ' .cs-btns{display:flex;align-items:center;gap:2px;}',
            '#' + DIALOG_ID + ' .cs-btn{display:flex;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:8px;' +
                'background:transparent;color:#fff;font-size:12px;font-weight:600;cursor:pointer;}',
            '#' + DIALOG_ID + ' .cs-btn:hover{background:rgba(255,255,255,.12);}',
            '#' + DIALOG_ID + ' .cs-stage[data-playing="1"] .cs-center{display:none;}',
            '#' + DIALOG_ID + ' .cs-stage[data-locked="1"] .cs-center,',
            '#' + DIALOG_ID + ' .cs-stage[data-locked="1"] .cs-controls{display:none;}',
            '#' + DIALOG_ID + ' .cs-stage[data-empty="1"] .cs-controls{display:none;}',

            // 锁定遮罩 + CTA
            '#' + DIALOG_ID + ' .cs-locked{position:absolute;inset:0;display:none;flex-direction:column;align-items:center;justify-content:center;gap:14px;padding:24px;' +
                'text-align:center;background:rgba(0,0,0,.55);}',
            '#' + DIALOG_ID + ' .cs-stage[data-locked="1"] .cs-locked{display:flex;}',
            '#' + DIALOG_ID + ' .cs-locked-poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;filter:blur(10px) brightness(.55);}',
            '#' + DIALOG_ID + ' .cs-locked-inner{position:relative;display:flex;flex-direction:column;align-items:center;gap:14px;}',
            '#' + DIALOG_ID + ' .cs-locked-text{margin:0;font-size:14px;font-weight:600;line-height:1.4;color:#fff;}',
            '#' + DIALOG_ID + ' .cs-cta{width:100%;max-width:280px;padding:12px 20px;border:0;border-radius:9999px;color:#fff;font-size:14px;font-weight:600;' +
                'font-family:inherit;cursor:pointer;background:linear-gradient(90deg,#FF7B91 0%,#E65073 100%);}',
            '#' + DIALOG_ID + ' .cs-cta[data-confirm="1"]{background:linear-gradient(90deg,#fdc706 0%,#ffa800 100%);color:#171717;}',
            '#' + DIALOG_ID + ' .cs-empty{margin:0;padding:0 24px;font-size:13px;line-height:1.6;color:#9aa0a6;text-align:center;}',

            // 右栏：文案 + 剧集
            '#' + DIALOG_ID + ' .cs-side{width:100%;min-width:0;border:1px solid #282828;border-radius:12px;overflow:hidden;' +
                'background:radial-gradient(at left top, rgba(40,40,40,.5), rgba(26,26,26,0) 70%);}',
            '#' + DIALOG_ID + ' .cs-side-inner{padding:20px 0 24px;}',
            '#' + DIALOG_ID + ' .cs-meta{padding:0 20px 12px;}',
            '#' + DIALOG_ID + ' .cs-title{margin:0;font-size:28px;font-weight:600;line-height:1.15;color:#fff;}',
            '#' + DIALOG_ID + ' .cs-desc{margin:12px 0 0;font-size:14px;font-weight:500;line-height:20px;color:#828282;}',
            '#' + DIALOG_ID + ' .cs-count{margin:10px 0 0;font-size:12px;font-weight:500;color:#828282;}',
            '#' + DIALOG_ID + ' .cs-divider{border:0;border-top:1px solid #282828;margin:22px 20px;}',
            '#' + DIALOG_ID + ' .cs-list{display:flex;flex-direction:column;gap:12px;padding:0 20px;}',
            '#' + DIALOG_ID + ' .cs-ep{display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;padding:14px 20px;' +
                'border:1px solid #525252;border-radius:10px;background:transparent;color:#fff;font-family:inherit;font-size:16px;font-weight:500;' +
                'line-height:21px;text-align:left;cursor:pointer;}',
            '#' + DIALOG_ID + ' .cs-ep:hover{border-color:rgba(255,255,255,.35);}',
            '#' + DIALOG_ID + ' .cs-ep[data-current="1"]{border-color:' + ACCENT + ';}',
            '#' + DIALOG_ID + ' .cs-ep-right{display:flex;align-items:center;gap:8px;flex:0 0 auto;}',
            '#' + DIALOG_ID + ' .cs-lock{width:16px;height:16px;fill:#fff;display:block;}',
            '#' + DIALOG_ID + ' .cs-price{font-size:14px;font-weight:600;color:#fff;white-space:nowrap;}',
            '#' + DIALOG_ID + ' .cs-price[data-owned="1"]{color:rgba(255,255,255,.5);}',
            '#' + DIALOG_ID + ' .cs-free{font-size:13px;font-weight:600;color:#7EE2A8;white-space:nowrap;}',
            '#' + DIALOG_ID + ' .cs-skeleton{padding:0 20px;font-size:13px;color:#9aa0a6;}',

            // ---------- 手机端：一集一屏的竖向滑动播放器 ----------
            '#' + DIALOG_ID + ' .cs-feed{position:relative;flex:1 1 auto;height:100%;overflow-y:auto;overflow-x:hidden;' +
                'scroll-snap-type:y mandatory;-webkit-overflow-scrolling:touch;background:#000;scrollbar-width:none;}',
            '#' + DIALOG_ID + ' .cs-feed::-webkit-scrollbar{display:none;}',
            '#' + DIALOG_ID + ' .cs-slide{position:relative;width:100%;height:100%;overflow:hidden;background:#000;scroll-snap-align:start;scroll-snap-stop:always;}',
            '#' + DIALOG_ID + ' .cs-slide .cs-empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;}',
            '#' + DIALOG_ID + ' .cs-slide-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;background:#000;}',
            '#' + DIALOG_ID + ' .cs-slide .cs-controls{pointer-events:none;padding-bottom:calc(12px + env(safe-area-inset-bottom));z-index:7;}',
            '#' + DIALOG_ID + ' .cs-slide .cs-progress,',
            '#' + DIALOG_ID + ' .cs-slide .cs-btn{pointer-events:auto;}',
            '#' + DIALOG_ID + ' .cs-slide .cs-center{z-index:6;}',
            '#' + DIALOG_ID + ' .cs-slide[data-playing="1"] .cs-center{display:none;}',
            '#' + DIALOG_ID + ' .cs-slide[data-locked="1"] .cs-locked{display:flex;}',
            '#' + DIALOG_ID + ' .cs-slide-top{position:absolute;left:0;right:0;top:0;z-index:6;display:flex;align-items:center;justify-content:center;pointer-events:none;}',
            '#' + DIALOG_ID + ' .cs-slide-top .cs-close{position:absolute;left:12px;top:calc(env(safe-area-inset-top) + 14px);pointer-events:auto;}',
            '#' + DIALOG_ID + ' .cs-slide-meta{position:absolute;left:0;right:0;bottom:0;z-index:5;padding:36px 16px calc(80px + env(safe-area-inset-bottom));pointer-events:none;' +
                'background:linear-gradient(to top, rgba(0,0,0,.8) 0%, rgba(0,0,0,.35) 45%, rgba(0,0,0,0) 100%);}',
            '#' + DIALOG_ID + ' .cs-slide-ep{margin:0 0 6px;font-size:12px;font-weight:700;letter-spacing:.02em;color:' + ACCENT + ';}',
            '#' + DIALOG_ID + ' .cs-slide-title{margin:0;font-size:18px;font-weight:600;line-height:1.25;color:#fff;}',
            '#' + DIALOG_ID + ' .cs-slide-desc{margin:6px 0 0;font-size:13px;line-height:1.5;color:rgba(255,255,255,.78);' +
                'display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;}',

            // 桌面：左右两栏（尺寸对齐原站：面板 920x730、body 862、左栏 352x626、间距 29、右栏 481）
            '@media (min-width:1024px){',
            '#' + DIALOG_ID + ' .cs-scroll{display:block;}',
            '#' + DIALOG_ID + ' .cs-feed{display:none;}',
            '#' + DIALOG_ID + ' .cs-wrap{align-items:center;padding:0;}',
            '#' + DIALOG_ID + ' .cs-panel{width:920px;height:730px;max-height:calc(100dvh - 128px);border-radius:16px;}',
            '#' + DIALOG_ID + ' .cs-inner{gap:16px;padding:20px 28px 24px;}',
            '#' + DIALOG_ID + ' .cs-body{flex-direction:row;align-items:flex-start;gap:29px;flex:0 0 auto;min-height:0;}',
            // 左栏用固定宽度（而不是靠 aspect-ratio 从高度反推）：锁定态的遮罩是绝对定位、
            // 不贡献内容宽度，靠反推会把宽度算成 0，导致解锁 CTA 被 overflow 裁掉、点不到
            '#' + DIALOG_ID + ' .cs-stage{flex:0 0 auto;width:352px;height:auto;aspect-ratio:9/16;border-radius:15px;align-self:flex-start;}',
            '#' + DIALOG_ID + ' .cs-side{flex:1 1 auto;align-self:stretch;max-height:626px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.25) transparent;}',
            '#' + DIALOG_ID + ' .cs-side-inner{padding:30px 0 36px;}',
            '#' + DIALOG_ID + ' .cs-meta{padding:0 30px 12px;}',
            '#' + DIALOG_ID + ' .cs-title{font-size:24px;font-weight:500;}',
            '#' + DIALOG_ID + ' .cs-divider{margin:22px 30px;}',
            '#' + DIALOG_ID + ' .cs-list{padding:0 30px;}',
            '#' + DIALOG_ID + ' .cs-skeleton{padding:0 30px;}',
            '}',
        ].join('\n')
        document.head.appendChild(s)
    }

    /* ---------- DOM ---------- */
    function build() {
        if (dlg) return dlg
        injectStyle()

        dlg = document.createElement('dialog')
        dlg.id = DIALOG_ID
        dlg.setAttribute('aria-label', 'Shorts')
        dlg.innerHTML =
            '<div class="cs-wrap" data-cs-backdrop>' +
              '<div class="cs-panel">' +
                // 桌面：左视频 / 右文案+剧集
                '<div class="cs-scroll">' +
                  '<div class="cs-inner">' +
                    '<div class="cs-head">' +
                      '<button type="button" class="cs-close" data-series-close aria-label="Close">&times;</button>' +
                      '<h2 class="cs-heading">Shorts</h2>' +
                    '</div>' +
                    '<div class="cs-body">' +
                      '<div class="cs-stage" data-cs-stage></div>' +
                      '<div class="cs-side">' +
                        '<div class="cs-side-inner">' +
                          '<div class="cs-meta">' +
                            '<h1 class="cs-title" data-series-title></h1>' +
                            '<p class="cs-desc" data-series-desc style="display:none;"></p>' +
                            '<p class="cs-count" data-series-count></p>' +
                          '</div>' +
                          '<hr class="cs-divider">' +
                          '<div class="cs-list" data-series-list></div>' +
                        '</div>' +
                      '</div>' +
                    '</div>' +
                  '</div>' +
                '</div>' +
                // 手机：一集一屏的竖向滑动播放器
                '<div class="cs-feed" data-cs-feed></div>' +
              '</div>' +
            '</div>'

        dlg.addEventListener('click', function (e) {
            var t = e.target

            if (t.closest && t.closest('[data-series-close]')) { close(); return }
            // 点面板外的遮罩也关闭
            if (t.closest && t.closest('[data-cs-backdrop]') && !t.closest('.cs-panel')) { close(); return }

            // 控制条的作用域：桌面=左栏 .cs-stage，手机=当前那一屏 .cs-slide
            var scope = t.closest ? (t.closest('[data-cs-stage]') || t.closest('[data-cs-slide]')) : null
            if (scope) {
                if (t.closest('[data-cs-cta]')) { onCtaClick(); return }
                if (t.closest('[data-cs-center]')) { togglePlay(scope); return }
                if (t.closest('[data-cs-play]')) { togglePlay(scope); return }
                if (t.closest('[data-cs-rate]')) { cycleRate(scope); return }
                if (t.closest('[data-cs-mute]')) { toggleMute(scope); return }
                if (t.closest('[data-cs-full]')) { toggleFull(scope); return }
                if (t.closest('[data-cs-progress]')) { seekFromEvent(e, scope); return }
            }

            var row = t.closest ? t.closest('[data-series-ep]') : null
            if (row) onEpisodeClick(Number(row.getAttribute('data-series-ep')) || 0)
        })

        document.body.appendChild(dlg)
        return dlg
    }

    /* ---------- 取值 ---------- */
    function episodeById(id) {
        for (var i = 0; i < state.episodes.length; i++) {
            if (Number(state.episodes[i].id) === Number(id)) return state.episodes[i]
        }
        return null
    }

    function indexOfEpisode(id) {
        for (var i = 0; i < state.episodes.length; i++) {
            if (Number(state.episodes[i].id) === Number(id)) return i
        }
        return -1
    }

    function current() {
        return episodeById(state.currentId) || state.episodes[0] || null
    }

    function episodeLabel(ep) {
        return 'Episode ' + ep.episode_no
    }

    function fmtTime(sec) {
        sec = Math.max(0, Math.floor(Number(sec) || 0))
        var m = Math.floor(sec / 60)
        var s = sec % 60
        return m + ':' + (s < 10 ? '0' : '') + s
    }

    /* ---------- 视频控制（桌面左栏 / 手机整屏共用，作用域 scope 内查找元素） ---------- */
    function centerHtml() {
        return '<button type="button" class="cs-center" data-cs-center aria-label="Play">' + SVG_BIG_PLAY + '</button>'
    }

    function controlsHtml() {
        return '<div class="cs-controls">' +
              '<div class="cs-progress" data-cs-progress><div class="cs-progress-fill" data-cs-fill></div></div>' +
              '<div class="cs-bar">' +
                '<span class="cs-time"><span data-cs-cur>0:00</span> <i>/</i> <span data-cs-dur>0:00</span></span>' +
                '<span class="cs-btns">' +
                  '<button type="button" class="cs-btn" data-cs-play aria-label="Play">' + SVG_PLAY + '</button>' +
                  '<button type="button" class="cs-btn" data-cs-rate aria-label="Speed">' + RATES[state.rateIndex] + '&times;</button>' +
                  '<button type="button" class="cs-btn" data-cs-mute aria-label="Volume">' + SVG_VOLUME + '</button>' +
                  '<button type="button" class="cs-btn" data-cs-full aria-label="Fullscreen">' + SVG_FULL + '</button>' +
                '</span>' +
              '</div>' +
            '</div>'
    }

    function lockedCta(ep, confirming) {
        return '<button type="button" class="cs-cta" data-cs-cta data-confirm="' + (confirming ? '1' : '0') + '">' +
            (confirming
                ? 'Confirm · ' + ep.price + ' 💎'
                : 'Unlock ' + esc(episodeLabel(ep)) + (ep.price > 0 ? ' · ' + ep.price + ' 💎' : '')) +
          '</button>'
    }

    function bindVideo(scope) {
        var v = scope.querySelector('video')
        if (!v) return
        v.playbackRate = RATES[state.rateIndex]
        var rateBtn = scope.querySelector('[data-cs-rate]')
        if (rateBtn) rateBtn.innerHTML = RATES[state.rateIndex] + '&times;'
        v.addEventListener('loadedmetadata', function () { syncTime(scope) })
        v.addEventListener('timeupdate', function () { syncTime(scope) })
        v.addEventListener('play', function () { setPlaying(scope, true) })
        v.addEventListener('pause', function () { setPlaying(scope, false) })
        v.addEventListener('ended', function () { setPlaying(scope, false) })
        v.addEventListener('volumechange', function () { syncVolumeBtn(scope) })
        syncVolumeBtn(scope)
    }

    // 自动播放：被浏览器拦截（无声音策略）时降级为静音播放，用户可再点音量键开声
    function tryPlay(scope) {
        var v = scope ? scope.querySelector('video') : null
        if (!v) return
        var p = v.play()
        if (p && p.catch) {
            p.catch(function () {
                v.muted = true
                var p2 = v.play()
                if (p2 && p2.catch) p2.catch(function () { /* 仍被拦截则留待用户点播放 */ })
                syncVolumeBtn(scope)
            })
        }
    }

    function setPlaying(scope, playing) {
        if (scope) scope.setAttribute('data-playing', playing ? '1' : '0')
        var btn = scope ? scope.querySelector('[data-cs-play]') : null
        if (btn) {
            btn.innerHTML = playing ? SVG_PAUSE : SVG_PLAY
            btn.setAttribute('aria-label', playing ? 'Pause' : 'Play')
        }
    }

    function syncTime(scope) {
        var v = scope ? scope.querySelector('video') : null
        if (!v) return
        var fill = scope.querySelector('[data-cs-fill]')
        var cur = scope.querySelector('[data-cs-cur]')
        var dur = scope.querySelector('[data-cs-dur]')
        var d = Number(v.duration) || 0
        if (fill && d > 0) fill.style.width = Math.min(100, (v.currentTime / d) * 100) + '%'
        if (cur) cur.textContent = fmtTime(v.currentTime)
        if (dur) dur.textContent = fmtTime(d)
    }

    function syncVolumeBtn(scope) {
        var v = scope ? scope.querySelector('video') : null
        var btn = scope ? scope.querySelector('[data-cs-mute]') : null
        if (!v || !btn) return
        var muted = v.muted || v.volume === 0
        btn.innerHTML = muted ? SVG_MUTE : SVG_VOLUME
        btn.setAttribute('aria-label', muted ? 'Unmute' : 'Volume')
    }

    function togglePlay(scope) {
        var v = scope ? scope.querySelector('video') : null
        if (!v) return
        if (v.paused) tryPlay(scope)
        else v.pause()
    }

    function cycleRate(scope) {
        state.rateIndex = (state.rateIndex + 1) % RATES.length
        var btn = scope ? scope.querySelector('[data-cs-rate]') : null
        if (btn) btn.innerHTML = RATES[state.rateIndex] + '&times;'
        var v = scope ? scope.querySelector('video') : null
        if (v) v.playbackRate = RATES[state.rateIndex]
    }

    function toggleMute(scope) {
        var v = scope ? scope.querySelector('video') : null
        if (!v) return
        v.muted = !v.muted
        syncVolumeBtn(scope)
    }

    function toggleFull(scope) {
        var box = scope || dlg
        if (!box || !box.requestFullscreen) return
        if (document.fullscreenElement) {
            if (document.exitFullscreen) document.exitFullscreen()
        } else {
            try { box.requestFullscreen() } catch (e) { /* 忽略 */ }
        }
    }

    function seekFromEvent(e, scope) {
        var v = scope ? scope.querySelector('video') : null
        var bar = scope ? scope.querySelector('[data-cs-progress]') : null
        if (!v || !bar) return
        var r = bar.getBoundingClientRect()
        if (r.width <= 0) return
        var ratio = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width))
        var d = Number(v.duration) || 0
        if (d > 0) {
            v.currentTime = ratio * d
            syncTime(scope)
        }
    }

    /* ---------- 桌面左栏：视频区 ---------- */
    function stageScope() {
        return dlg.querySelector('[data-cs-stage]')
    }

    function stageAttrs(locked, empty, playing) {
        var stage = stageScope()
        if (!stage) return
        stage.setAttribute('data-locked', locked ? '1' : '0')
        stage.setAttribute('data-empty', empty ? '1' : '0')
        stage.setAttribute('data-playing', playing ? '1' : '0')
    }

    function stageEmpty(html) {
        var stage = stageScope()
        if (!stage) return
        stageAttrs(false, true, false)
        stage.innerHTML = '<p class="cs-empty">' + html + '</p>'
    }

    function renderStage() {
        var stage = stageScope()
        var ep = current()
        if (!stage) return

        if (!ep) {
            stageEmpty('This series has no episode yet. Please add it in the admin panel.')
            return
        }

        // 锁住：模糊封面 + CTA（原站就是把 CTA 放在视频区遮罩里）
        if (!ep.unlocked) {
            var confirming = Number(state.confirmId) === Number(ep.id)
            stageAttrs(true, false, false)
            stage.innerHTML =
                '<div class="cs-locked">' +
                  (ep.poster ? '<img class="cs-locked-poster" style="' + (vipUnblur() ? 'filter:none;' : '') + '" alt="" src="' + esc(ep.poster) + '">' : '') +
                  '<div class="cs-locked-inner">' +
                    '<p class="cs-locked-text">' + esc(episodeLabel(ep)) + ' is locked' +
                      (ep.price > 0 ? ' · ' + ep.price + ' diamonds' : '') + '</p>' +
                    lockedCta(ep, confirming) +
                  '</div>' +
                '</div>'
            return
        }

        if (!ep.video_url) {
            stageEmpty('This episode has no video yet. Please add it in the admin panel.')
            return
        }

        // 同一集复用播放器，避免重建打断播放进度
        var existing = stage.querySelector('[data-cs-video]')
        if (existing && existing.getAttribute('src') === ep.video_url) {
            stageAttrs(false, false, !existing.paused)
            return
        }

        stageAttrs(false, false, false)
        stage.innerHTML =
            '<video class="cs-video" data-cs-video src="' + esc(ep.video_url) + '" playsinline preload="metadata"></video>' +
            centerHtml() + controlsHtml()

        bindVideo(stage)
        // 原站打开弹窗时是暂停待播（显示 80px 播放圆钮），只有刚解锁成功的那一集才自动播
        if (state.autoPlay) {
            state.autoPlay = false
            var v = stage.querySelector('video')
            if (v) { try { v.play() } catch (e) { /* 浏览器可能拦截自动播放，交给用户点播放 */ } }
        }
    }

    /* ---------- 手机端：一集一屏的竖向滑动播放器 ---------- */
    function feedScope() {
        return dlg.querySelector('[data-cs-feed]')
    }

    function feedTopHtml() {
        return '<div class="cs-slide-top">' +
            '<button type="button" class="cs-close" data-series-close aria-label="Close">&times;</button>' +
          '</div>'
    }

    function feedSlideHtml(ep, index) {
        var locked = !ep.unlocked
        var hasVideo = !!ep.video_url

        var body
        if (locked) {
            var confirming = Number(state.confirmId) === Number(ep.id)
            body = '<div class="cs-locked">' +
                (ep.poster ? '<img class="cs-locked-poster" style="' + (vipUnblur() ? 'filter:none;' : '') + '" alt="" src="' + esc(ep.poster) + '">' : '') +
                '<div class="cs-locked-inner">' +
                  '<p class="cs-locked-text">' + esc(episodeLabel(ep)) + ' is locked' +
                    (ep.price > 0 ? ' · ' + ep.price + ' diamonds' : '') + '</p>' +
                  lockedCta(ep, confirming) +
                '</div>' +
              '</div>'
        } else if (hasVideo) {
            body = '<video class="cs-slide-video" data-cs-video src="' + esc(ep.video_url) + '" playsinline preload="metadata"></video>' +
                centerHtml() + controlsHtml()
        } else {
            body = '<p class="cs-empty">This episode has no video yet. Please add it in the admin panel.</p>'
        }

        return '<div class="cs-slide" data-cs-slide data-slide-index="' + index + '" data-ep-id="' + esc(ep.id) + '"' +
            ' data-locked="' + (locked ? '1' : '0') + '" data-empty="' + (hasVideo || locked ? '0' : '1') + '"' +
            ' data-playing="0" data-active="0">' +
            body +
            feedTopHtml() +
            '<div class="cs-slide-meta">' +
              '<p class="cs-slide-ep">' + esc(episodeLabel(ep)) + ' · ' + (index + 1) + '/' + state.episodes.length + '</p>' +
              '<h1 class="cs-slide-title">' + esc(state.title) + '</h1>' +
              (state.description ? '<p class="cs-slide-desc">' + esc(state.description) + '</p>' : '') +
            '</div>' +
          '</div>'
    }

    function feedMessage(html) {
        var feed = feedScope()
        if (!feed) return
        feed.innerHTML = '<div class="cs-slide"><p class="cs-empty">' + html + '</p>' + feedTopHtml() + '</div>'
    }

    function renderFeed() {
        var feed = feedScope()
        if (!feed) return

        if (!state.episodes.length) {
            feedMessage('This series has no episode yet. Please add it in the admin panel.')
            return
        }

        var html = ''
        for (var i = 0; i < state.episodes.length; i++) html += feedSlideHtml(state.episodes[i], i)
        feed.innerHTML = html

        var slides = feed.querySelectorAll('[data-cs-slide]')
        for (var s = 0; s < slides.length; s++) {
            if (slides[s].querySelector('video')) bindVideo(slides[s])
        }

        var idx = indexOfEpisode(state.currentId)
        if (idx < 0) idx = 0
        feed.scrollTop = slides[idx] ? slides[idx].offsetTop : 0
        activateSlide(idx)
        setupFeedObserver()
    }

    function setupFeedObserver() {
        var feed = feedScope()
        if (!feed) return
        if (feedObserver) feedObserver.disconnect()
        if (!('IntersectionObserver' in window)) return
        feedObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.intersectionRatio < 0.6) return
                var slide = entry.target
                if (slide.getAttribute('data-active') === '1') return
                activateSlide(Number(slide.getAttribute('data-slide-index')) || 0)
            })
        }, { root: feed, threshold: [0.6] })

        var slides = feed.querySelectorAll('[data-cs-slide]')
        for (var i = 0; i < slides.length; i++) feedObserver.observe(slides[i])
    }

    // 切换当前屏：滑到哪集就播哪集，其余暂停；同时把 state.currentId 同步过去
    function activateSlide(index) {
        var feed = feedScope()
        if (!feed) return
        var slides = feed.querySelectorAll('[data-cs-slide]')
        if (!slides.length) return
        index = Math.max(0, Math.min(slides.length - 1, index))

        for (var i = 0; i < slides.length; i++) {
            var on = (i === index)
            slides[i].setAttribute('data-active', on ? '1' : '0')
            var v = slides[i].querySelector('video')
            if (!on && v && !v.paused) v.pause()
        }

        var ep = state.episodes[index]
        if (ep) state.currentId = Number(ep.id)
        state.autoPlay = false
        tryPlay(slides[index])
    }

    /* ---------- 右栏：文案 + 剧集列表（仅桌面） ---------- */
    function renderHeader() {
        var count = state.episodes.length

        var titleEl = dlg.querySelector('[data-series-title]')
        if (titleEl) titleEl.textContent = state.title

        var desc = dlg.querySelector('[data-series-desc]')
        if (desc) {
            desc.textContent = state.description || ''
            desc.style.display = state.description ? 'block' : 'none'
        }

        var meta = count + (count === 1 ? ' episode' : ' episodes')
        if (state.freeEpisodes > 0) meta += ' · first ' + state.freeEpisodes + ' free'
        var countEl = dlg.querySelector('[data-series-count]')
        if (countEl) countEl.textContent = meta
    }

    function renderList() {
        var html = ''
        for (var i = 0; i < state.episodes.length; i++) {
            var ep = state.episodes[i]
            var isCurrent = Number(ep.id) === Number(state.currentId)

            var right
            if (ep.unlocked && ep.free) {
                right = '<span class="cs-free">Free</span>'
            } else if (ep.unlocked) {
                right = '<span class="cs-price" data-owned="1">' + ep.price + ' 💎</span>'
            } else {
                right = SVG_LOCK + '<span class="cs-price">' + (ep.price > 0 ? ep.price + ' 💎' : 'Free') + '</span>'
            }

            html +=
                '<button type="button" class="cs-ep" data-series-ep="' + ep.id + '" data-current="' + (isCurrent ? '1' : '0') + '">' +
                  '<span>' + esc(episodeLabel(ep)) + '</span>' +
                  '<span class="cs-ep-right">' + right + '</span>' +
                '</button>'
        }
        var list = dlg.querySelector('[data-series-list]')
        if (list) list.innerHTML = html
    }

    function render() {
        lastMobile = isMobile()
        renderHeader()
        if (lastMobile) {
            renderFeed()
        } else {
            renderStage()
            renderList()
        }
    }

    /* ---------- 行为 ---------- */
    function selectEpisode(ep) {
        if (!ep) return
        state.currentId = Number(ep.id)
        state.confirmId = 0
        render()
    }

    function onEpisodeClick(id) {
        var ep = episodeById(id)
        if (!ep) return

        if (ep.unlocked) { selectEpisode(ep); return }

        if (needLogin()) return
        // 锁住的集：选中后由视频区的 CTA 走「Unlock → Confirm」两步，避免误扣
        selectEpisode(ep)
    }

    function onCtaClick() {
        var ep = current()
        if (!ep || ep.unlocked) return
        if (needLogin()) return

        if (Number(state.confirmId) !== Number(ep.id)) {
            state.confirmId = Number(ep.id)
            if (isMobile()) renderFeed()
            else renderStage()
            return
        }
        unlock(ep)
    }

    function unlock(ep) {
        fetch(API_UNLOCK, {
            method: 'POST',
            headers: headers({ 'Content-Type': 'application/x-www-form-urlencoded' }),
            body: 'episode_id=' + encodeURIComponent(ep.id),
        })
            .then(function (r) { return r.json() })
            .then(function (res) {
                if (!res || res.code !== '00000') {
                    alertBox(errText(res))
                    state.confirmId = 0
                    if (isMobile()) renderFeed()
                    else renderStage()
                    return
                }
                var fresh = res.data && res.data.episode ? res.data.episode : null
                ep.unlocked = 1
                ep.video_url = fresh ? (fresh.video_url || '') : ''
                state.confirmId = 0
                state.currentId = Number(ep.id)
                state.autoPlay = true
                if (window.layer) {
                    layer.msg(Number(res.data && res.data.charged) === 1 ? 'Unlocked!' : 'Already unlocked')
                }
                render()
            })
            .catch(function () {
                alertBox('Network error, please try again')
                state.confirmId = 0
                if (isMobile()) renderFeed()
                else renderStage()
            })
    }

    function load(shortId) {
        var list = dlg.querySelector('[data-series-list]')
        if (list) list.innerHTML = '<p class="cs-skeleton">Loading...</p>'

        fetch(API_EPISODES + '?short_id=' + encodeURIComponent(shortId), { method: 'GET', headers: headers() })
            .then(function (r) { return r.json() })
            .then(function (res) {
                if (!res || res.code !== '00000' || !res.data) {
                    var msg = esc((res && res.msg) || 'Failed to load episodes.')
                    if (list) list.innerHTML = '<p class="cs-skeleton">' + msg + '</p>'
                    if (isMobile()) feedMessage(msg)
                    else stageEmpty(msg)
                    return
                }

                var s = res.data.short || {}
                state.shortId = Number(s.id || shortId) || 0
                state.title = s.title || state.title || ''
                state.description = s.description || ''
                state.freeEpisodes = Number(s.free_episodes || 0)
                state.episodes = Array.isArray(res.data.episodes) ? res.data.episodes : []
                state.confirmId = 0

                // 没配剧集的剧：用剧自身的视频兜底成第 1 集（免费），
                // 这样任何卡片点开都是同一个弹窗，不会一半新样式一半旧样式
                if (!state.episodes.length && s.video_url) {
                    state.episodes = [{
                        id: 0,
                        short_id: state.shortId,
                        episode_no: 1,
                        title: state.title,
                        poster: s.poster || '',
                        duration: '',
                        price: 0,
                        free: 1,
                        unlocked: 1,
                        video_url: s.video_url,
                    }]
                }

                // 默认停在第一集：免费集直接播，锁着的第一集就展示它的解锁 CTA
                var first = state.episodes[0] || null
                state.currentId = first ? Number(first.id) : 0
                render()
            })
            .catch(function () {
                if (list) list.innerHTML = '<p class="cs-skeleton">Network error, please try again.</p>'
                if (isMobile()) feedMessage('Network error, please try again.')
                else stageEmpty('Network error, please try again.')
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
        state.rateIndex = 0
        state.autoPlay = false

        var list = d.querySelector('[data-series-list]')
        if (list) list.innerHTML = '<p class="cs-skeleton">Loading...</p>'
        if (isMobile()) feedMessage('Loading...')
        else stageEmpty('Loading...')
        renderHeader()

        if (!d.open) {
            try { if (d.showModal) d.showModal() } catch (e) { /* 忽略 */ }
            if (!d.open) d.setAttribute('open', '')
        }
        load(state.shortId)
    }

    function close() {
        if (!dlg) return
        var vids = dlg.querySelectorAll('video')
        for (var i = 0; i < vids.length; i++) {
            vids[i].pause()
            vids[i].removeAttribute('src')
            vids[i].load()
        }
        if (feedObserver) { feedObserver.disconnect(); feedObserver = null }
        if (document.fullscreenElement && document.exitFullscreen) {
            try { document.exitFullscreen() } catch (e) { /* 忽略 */ }
        }
        if (dlg.open) dlg.close()
    }

    // 断点切换（竖屏↔横屏 / 窗口缩放）时重排布局
    window.addEventListener('resize', function () {
        if (!dlg || !dlg.open) return
        if (isMobile() !== lastMobile) render()
    })

    window.CandySeries = { open: open, close: close }
})()
