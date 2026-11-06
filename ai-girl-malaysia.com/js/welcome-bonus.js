/**
 * welcome-bonus.js — 新人注册礼弹窗 + AI Video Studio 充值解锁状态
 *
 * 职责：
 *   1. 登录态下拉一次 userInfo，把 has_recharged 写到 window.__userRecharged，
 *      并广播 live:userstate —— shell-new.js 的 syncStudioEntry 监听后原地刷新
 *      「AI Video Studio」入口（充值过才解锁，见 shell-new.js studioEntryVisible）。
 *   2. 后端注册即送 50 代币（ledger biz_type=welcome_bonus）。userInfo 返回
 *      welcome_bonus=true 且本浏览器未看过引导窗时，弹 candy 同款「50 FREE
 *      Tokens」引导窗（一张图 + 文案 + CTA），点击去 createCharacter.html 花免费代币建角色。
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
  var CTA_HREF = './createCharacter.html'
  var HERO_IMG = './assets/login-bg-full.jpg'
  var SEEN_PREFIX = 'wb_popup_seen_'
  /* 登录/注册页不弹：登录后马上会跳走，弹窗留到落地页再看 */
  var SKIP_PAGES = /\/(login|register|registerFromAi)\.html$/i

  function lsGet(k) { try { return localStorage.getItem(k) } catch (e) { return null } }
  function lsSet(k, v) { try { localStorage.setItem(k, v) } catch (e) {} }

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
    fetch(API_USER_INFO, { method: 'POST', headers: { token: tk } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (!res || (res.code !== 1 && res.code !== '00000')) return
        applyUserState(res.data || {})
      })
      .catch(function () {})
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
    + '@media (max-width:420px){.wb-hero{height:170px}.wb-body{padding:0 16px 16px}.wb-title{font-size:21px}}'

  function showBonusModal(user) {
    if (SKIP_PAGES.test(location.pathname || '')) return
    if (document.getElementById('wb-overlay')) return
    var seenKey = SEEN_PREFIX + user.id
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
      +       '<img src="' + esc(HERO_IMG) + '" alt="" onerror="this.style.display=\'none\'">'
      +     '</div>'
      +     '<div class="wb-body">'
      +       '<h2 class="wb-title">50 FREE Tokens!</h2>'
      +       '<p class="wb-sub">We just dropped <b>50 free tokens</b> into your wallet.<br>'
      +       'Spend them to create your own AI girlfriend — she\'s waiting for you.</p>'
      +       '<a class="wb-cta" href="' + esc(CTA_HREF) + '">Create Your AI Girlfriend ✨</a>'
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
    fetchUserState()
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start)
  } else {
    start()
  }
})()
