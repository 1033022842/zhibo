/**
 * welcome-bonus.js — 新人注册礼弹窗 + AI Video Studio 充值解锁状态
 *
 * 职责：
 *   1. 登录态下拉一次 userInfo，把 has_recharged 写到 window.__userRecharged，
 *      并广播 live:userstate —— shell-new.js 的 syncStudioEntry 监听后原地刷新
 *      「AI Video Studio」入口（充值过才解锁，见 shell-new.js studioEntryVisible）。
 *   2. 后端注册即送 50 代币（ledger biz_type=welcome_bonus）。userInfo 返回
 *      welcome_bonus=true 且本浏览器未看过引导窗时，弹 candy 同款「50 FREE
 *      Tokens」引导窗（一张图 + 文案 + CTA）。CTA 按客户分流：sugus / heygirl 去
 *      Image.html（AI 工作室），kelymi / kiss 仍去 createCharacter.html。
 *   3. 登录窗口（Login.html 静态页 + 站内 Sign in 弹窗）的大图从
 *      assets/welcome/art-NN.jpg 随机取，连续两次不重复。
 *
 * 设计约束：
 *   - userInfo 每个页面至多发一次；不挂在 fetch 钩子上（shell-new.js 已排除 userInfo 自触发）。
 *   - 弹窗只弹给真正领到礼的用户（welcome_bonus 流水标记），老用户/未领取用户不弹。
 *   - 任何异常都吞掉，绝不影响页面本身。
 */
;(function () {
  if (window.__welcomeBonusLoaded) return
  window.__welcomeBonusLoaded = true

  var API_USER_INFO = '/api/live/userInfo'
  /* 50 币弹窗 CTA 去向：sugus / heygirl 直达 AI 工作室，kelymi / kiss 仍去建角色页。
     四个客户共用这一份上游代码，故按访问域名分流（品牌脚本不会改写这两条正则）。
     白名单之外的域名（含 IP / 本地预览）保持原行为 = createCharacter，宁可不改也不要误改别家。 */
  var CTA_CHARACTER = './createCharacter.html'
  var CTA_STUDIO = './Image.html'
  var STUDIO_CLIENT_HOST = /(^|\.)(sugus|heygirl)\.[a-z.]+$/i
  var SEEN_PREFIX = 'wb_popup_seen_'
  /* 登录/注册页不弹：登录后马上会跳走，弹窗留到落地页再看 */
  var SKIP_PAGES = /\/(login|register|registerFromAi)\.html$/i

  function lsGet(k) { try { return localStorage.getItem(k) } catch (e) { return null } }
  function lsSet(k, v) { try { localStorage.setItem(k, v) } catch (e) {} }

  /* ---------- 0. 随机配图（assets/welcome/art-NN.jpg）----------
     登录页 Sign in 窗口的大图与 50 币弹窗头图都从这里随机取，
     连两次不重复（记住上一次的下标，在其余 n-1 张里抽）。
     素材源：C:\Users\a1033\Pictures\随机图，等比压到 720x960 后入库。 */
  var ART_DIR = './assets/welcome/'
  var ART_COUNT = 11
  var ART_LAST_KEY = 'wb_art_last'

  function pickArt() {
    if (ART_COUNT < 1) return ''
    var last = parseInt(lsGet(ART_LAST_KEY), 10)
    var idx
    if (ART_COUNT > 1 && last >= 1 && last <= ART_COUNT) {
      idx = 1 + Math.floor(Math.random() * (ART_COUNT - 1))
      if (idx >= last) idx++
    } else {
      idx = 1 + Math.floor(Math.random() * ART_COUNT)
    }
    lsSet(ART_LAST_KEY, String(idx))
    return ART_DIR + 'art-' + (idx < 10 ? '0' + idx : idx) + '.jpg'
  }

  function isStudioCta() {
    var host = ''
    try { host = String(location.hostname || '') } catch (e) {}
    return STUDIO_CLIENT_HOST.test(host)
  }

  /* ---------- 0.5 登录窗口大图随机 ----------
     两处都要换：
       a) Login.html 的静态 CSS（.left-img 背景 / 手机端 body 背景）——在 head 注入同名覆盖规则，
          DOM 解析到 .left-img 时已是随机图，无闪白；
       b) 站内 Sign in 弹窗（#user-login-image 里的 <img>，30 个页面都有）——DOM 就绪后改 src。 */
  function applyLoginArt() {
    if (!/\/login\.html$/i.test(location.pathname || '')) return
    if (document.getElementById('wb-login-art')) return
    var url = pickArt()
    if (!url) return
    var css = '.left-img{background-image:url("' + url + '")!important}'
      + '@media (max-width:800px){body{background-image:url("' + url + '")!important}}'
    var style = document.createElement('style')
    style.id = 'wb-login-art'
    style.textContent = css
    ;(document.head || document.documentElement).appendChild(style)
  }
  applyLoginArt()

  function applyModalArt() {
    var imgs = document.querySelectorAll('#user-login-image img')
    if (!imgs.length) return
    var url = pickArt()
    if (!url) return
    for (var i = 0; i < imgs.length; i++) imgs[i].src = url
  }

  /* 每次点开 Sign in 弹窗都换一张（弹窗在首屏 HTML 里，不重新解析，只能靠点击时改） */
  document.addEventListener('click', function (e) {
    var el = e.target
    for (var i = 0; i < 3 && el; i++) {
      var act = el.getAttribute && el.getAttribute('data-action')
      if (act && act.indexOf('openSignInModal') >= 0) { setTimeout(applyModalArt, 0); return }
      el = el.parentNode
    }
  }, true)

  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;')
  }

  /* ---------- 1. 用户状态：充值标记 + 弹窗判定 ---------- */

  function applyUserState(data) {
    var d = data || {}
    window.__userRecharged = d.has_recharged === true
    try { document.dispatchEvent(new Event('live:userstate')) } catch (e) {}
    if (d.welcome_bonus === true && d.id && lsGet(SEEN_PREFIX + d.id) !== '1') {
      showBonusModal(d)
    }
  }

  function fetchUserState() {
    var tk = null
    try { tk = localStorage.getItem('live_access_token') } catch (e) { return }
    if (!tk) {
      /* 未登录：确保入口隐藏（跨标签页登出同步） */
      if (window.__userRecharged !== false) {
        window.__userRecharged = false
        try { document.dispatchEvent(new Event('live:userstate')) } catch (e) {}
      }
      return
    }
    if (window.__wbInflight) return
    window.__wbInflight = true
    /* 走全站单飞（window.FyUserInfo）：本页 shell-new / main / promo-popup 也都要 userInfo，
       各自发一次会打满 nginx 对该接口的 30r/m 限流，429 时各脚本静默失败 →
       顶栏余额 0 / 底栏 Studio 格消失 / 抽屉头像为空。 */
    var req = (window.FyUserInfo && window.FyUserInfo.get)
      ? window.FyUserInfo.get()
      : fetch(API_USER_INFO, { method: 'POST', headers: { token: tk } })
          .then(function (r) { return r.json() })
          .then(function (res) {
            return (res && (res.code === 1 || res.code === '00000')) ? (res.data || {}) : null
          })
    req
      .then(function (d) {
        window.__wbInflight = false
        if (!d) return
        applyUserState(d)
      })
      .catch(function () { window.__wbInflight = false })
  }

  /* 跨标签页登录/登出跟随 */
  window.addEventListener('storage', function (e) {
    if (!e || e.key !== 'live_access_token') return
    window.__wbInflight = false
    fetchUserState()
  })

  /* ---------- 2. candy 同款引导窗 ---------- */

  var CSS = ''
    + '.wb-overlay{position:fixed;inset:0;z-index:2147483000;background:rgba(6,5,10,.78);backdrop-filter:blur(6px);'
    + 'display:flex;align-items:center;justify-content:center;padding:16px;animation:wbFade .25s ease both;font-family:inherit}'
    + '@keyframes wbFade{from{opacity:0}to{opacity:1}}@keyframes wbPop{from{opacity:0;transform:translateY(18px) scale(.96)}to{opacity:1;transform:none}}'
    + '.wb-modal{position:relative;width:380px;max-width:94vw;background:linear-gradient(180deg,#1b1424 0%,#120d1a 100%);'
    + 'border:1px solid rgba(233,89,132,.35);border-radius:20px;overflow:hidden;box-shadow:0 24px 80px rgba(0,0,0,.6),'
    + '0 0 60px rgba(233,89,132,.18);animation:wbPop .3s ease both}'
    + '.wb-hero{position:relative;height:196px;background:#241a30}'
    + '.wb-hero img{width:100%;height:100%;object-fit:cover;object-position:center 28%;display:block}'
    + '.wb-hero::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,transparent 40%,rgba(18,13,26,.92) 100%)}'
    + '.wb-badge{position:absolute;top:12px;left:12px;z-index:2;background:linear-gradient(135deg,#ff5f8f,#c44cff);'
    + 'color:#fff;font-size:12px;font-weight:700;letter-spacing:.4px;padding:5px 10px;border-radius:999px;'
    + 'box-shadow:0 4px 14px rgba(255,95,143,.45)}'
    + '.wb-close{position:absolute;top:10px;right:10px;z-index:2;width:30px;height:30px;border-radius:999px;'
    + 'background:rgba(0,0,0,.45);border:none;color:#ddd;font-size:16px;line-height:30px;cursor:pointer;padding:0}'
    + '.wb-close:hover{background:rgba(0,0,0,.7)}'
    + '.wb-body{position:relative;padding:0 22px 20px;margin-top:-34px;text-align:center}'
    + '.wb-title{margin:0 0 6px;font-size:24px;line-height:1.2;font-weight:800;color:#fff;'
    + 'background:linear-gradient(135deg,#ffd166 0%,#ff5f8f 55%,#c44cff 100%);'
    + '-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}'
    + '.wb-sub{margin:0 0 16px;color:#cfc6da;font-size:13px;line-height:1.55}'
    + '.wb-sub b{color:#ffd166;font-weight:700}'
    + '.wb-cta{display:block;width:100%;box-sizing:border-box;border:none;cursor:pointer;'
    + 'background:linear-gradient(135deg,#ff5f8f,#c44cff);color:#fff;font-size:15px;font-weight:700;'
    + 'padding:13px 16px;border-radius:14px;letter-spacing:.2px;text-decoration:none;'
    + 'box-shadow:0 10px 26px rgba(233,89,132,.4);transition:transform .15s ease,box-shadow .15s ease}'
    + '.wb-cta:hover{transform:translateY(-1px);box-shadow:0 14px 30px rgba(233,89,132,.55)}'
    + '.wb-later{display:inline-block;margin-top:12px;background:none;border:none;color:#8f8799;'
    + 'font-size:12px;cursor:pointer;text-decoration:underline;text-underline-offset:3px;padding:4px}'
    + '.wb-later:hover{color:#cfc6da}'
    /* CTA 上的 18+ 角标（仅 sugus / heygirl：CTA 去 AI 工作室，那里产成人内容） */
    + '.wb-cta-wrap{position:relative}'
    + '.wb-cta-18{position:absolute;top:-8px;right:-6px;z-index:2;padding:1px 7px;border-radius:999px;'
    + 'background:#ff4d6d;color:#fff;font-size:10px;font-weight:800;letter-spacing:.5px;line-height:1.6;'
    + 'border:2px solid #150f1e;box-shadow:0 2px 8px rgba(255,77,109,.5);pointer-events:none}'
    + '@media (max-width:420px){.wb-hero{height:170px}.wb-body{padding:0 16px 16px}.wb-title{font-size:21px}}'

  function showBonusModal(user) {
    if (SKIP_PAGES.test(location.pathname || '')) return
    if (document.getElementById('wb-overlay')) return
    var seenKey = SEEN_PREFIX + user.id
    var toStudio = isStudioCta()
    var ctaHref = toStudio ? CTA_STUDIO : CTA_CHARACTER
    var ctaLabel = toStudio ? 'Open AI Studio ✨' : 'Create Your AI Girlfriend ✨'
    var subLine = toStudio
      ? 'Spend them in the AI Studio — generate photos &amp; videos of her.'
      : 'Spend them to create your own AI girlfriend — she\'s waiting for you.'
    var close = function () {
      lsSet(seenKey, '1')
      var el = document.getElementById('wb-overlay')
      if (el && el.parentNode) el.parentNode.removeChild(el)
    }
    var html = ''
      + '<div id="wb-overlay" class="wb-overlay" role="dialog" aria-modal="true" aria-label="Welcome bonus">'
      +   '<div class="wb-modal">'
      +     '<div class="wb-hero">'
      +       '<span class="wb-badge">🎁 WELCOME GIFT</span>'
      +       '<button type="button" class="wb-close" aria-label="Close">✕</button>'
      +       '<img src="' + esc(pickArt()) + '" alt="" onerror="this.style.display=\'none\'">'
      +     '</div>'
      +     '<div class="wb-body">'
      +       '<h2 class="wb-title">50 FREE Tokens!</h2>'
      +       '<p class="wb-sub">We just dropped <b>50 free tokens</b> into your wallet.<br>'
      +       subLine + '</p>'
      +       (toStudio
                ? '<div class="wb-cta-wrap"><span class="wb-cta-18">18+</span>'
                  + '<a class="wb-cta" href="' + esc(ctaHref) + '">' + esc(ctaLabel) + '</a></div>'
                : '<a class="wb-cta" href="' + esc(ctaHref) + '">' + esc(ctaLabel) + '</a>')
      +       '<button type="button" class="wb-later">Maybe later</button>'
      +     '</div>'
      +   '</div>'
      + '</div>'
    var wrap = document.createElement('div')
    wrap.innerHTML = html
    var overlay = wrap.firstChild
    ;(document.body || document.documentElement).appendChild(overlay)
    overlay.querySelector('.wb-close').addEventListener('click', close)
    overlay.querySelector('.wb-later').addEventListener('click', close)
    overlay.querySelector('.wb-cta').addEventListener('click', function () { lsSet(seenKey, '1') })
    overlay.addEventListener('click', function (e) { if (e.target === overlay) close() })
  }

  /* ---------- 3. 启动 ---------- */

  function start() {
    var css = document.createElement('style')
    css.textContent = CSS
    ;(document.head || document.documentElement).appendChild(css)
    /* 充值解锁闭环：shell-new.js 的 refreshTokenBalance 在充值/扣费后会被 fetch 钩子调用，
       这里包一层，30s 内至多一次延迟重取 userInfo，让「充值 → AI Video Studio 原地解锁」
       不需要跳页。userInfo 不在 fetch 钩子触发范围，不会循环。 */
    var lastPoll = 0
    var origRefresh = window.refreshTokenBalance
    window.refreshTokenBalance = function () {
      var r = origRefresh ? origRefresh.apply(this, arguments) : undefined
      var now = Date.now()
      if (now - lastPoll > 30000) {
        lastPoll = now
        setTimeout(fetchUserState, 1200)
      }
      return r
    }
    applyModalArt()
    fetchUserState()
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start)
  } else {
    start()
  }
})()
