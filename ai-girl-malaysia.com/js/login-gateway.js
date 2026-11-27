(function () {
  'use strict'
  if (window.__liveLoginGatewayLoaded) return
  window.__liveLoginGatewayLoaded = true
  var options = null
  var pending = null
  var cooldownUntil = 0
  var activeReturn = ''
  var API = '/api/live'
  var HOME = '/ai/Home.html'
  var LOGIN = '/ai/Login.html'
  var onLoginPage = /^\/ai\/login\.html$/i.test(location.pathname) || /\/login\.html$/i.test(location.pathname)
  // 品牌图标：Google 保留官方四色（白底按钮）；Discord/Telegram/X 用 currentColor，
  // 跟随按钮文字色（分别白/白/深），否则图标与品牌底色同色会完全隐形。
  var LABELS = { google: 'Google', discord: 'Discord', twitter: 'X', telegram: 'Telegram' }
  var ICONS = {
    telegram: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="currentColor" d="M9.78 18.65l.28-4.23 7.68-6.92c.34-.31-.07-.46-.52-.19L7.74 13.3 3.64 12c-.88-.25-.89-.86.2-1.3l15.97-6.16c.73-.33 1.43.18 1.15 1.3l-2.72 12.81c-.19.91-.74 1.13-1.5.71L12.6 16.3l-1.99 1.93c-.23.23-.42.42-.83.42z"/>' +
      '</svg>',
    google: '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">' +
      '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>' +
      '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>' +
      '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>' +
      '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>' +
      '</svg>',
    discord: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="currentColor" d="M20.317 4.3698a19.7913 19.7913 0 0 0-4.8851-1.5152.0741.0741 0 0 0-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 0 0-.0785-.037 19.7363 19.7363 0 0 0-4.8852 1.515.0699.0699 0 0 0-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 0 0 .0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 0 0 .0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 0 0-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 0 1-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 0 1 .0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 0 1 .0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 0 1-.0066.1276 12.2986 12.2986 0 0 1-1.873.8914.0766.0766 0 0 0-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 0 0 .0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 0 0 .0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 0 0-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z"/>' +
      '</svg>',
    twitter: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="currentColor" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644z"/>' +
      '</svg>'
  }

  function post(path, data) {
    return fetch(API + path, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: new URLSearchParams(data).toString()
    }).then(function (response) { return response.json() }).then(function (result) {
      if (!result || result.code !== '00000') throw new Error((result && result.msg) || 'Sign-in failed. Please try again.')
      return result.data
    })
  }

  function message(text) {
    var dialogStatus = document.querySelector('#live-login-dialog[open] #live-login-status')
    if (dialogStatus) dialogStatus.textContent = text
    else if (window.layer && window.layer.msg) window.layer.msg(text)
    else window.alert(text)
  }

  function safeReturn(path) {
    if (!path) return ''
    try {
      var url = new URL(path, location.href)
      if (url.origin === location.origin && url.pathname.indexOf('/ai/') === 0 &&
          !/\/login\.html$/i.test(url.pathname)) return url.pathname + url.search
    } catch (e) { /* 非同源或非法返回地址忽略。 */ }
    return ''
  }

  function returnPath() {
    if (!onLoginPage) return activeReturn || safeReturn(location.href) || HOME
    var requested = new URL(location.href).searchParams.get('redirect')
    var stored = ''
    try { stored = sessionStorage.getItem('sugus_login_from') || sessionStorage.getItem('sugus_last_page') } catch (e) {}
    return safeReturn(requested) || safeReturn(document.referrer) || safeReturn(stored) || HOME
  }

  /* 记住站内上一个页面，供 referrer 丢失时兜底 */
  try {
    if (location.pathname.indexOf('Login.html') < 0 &&
        location.pathname.indexOf('/ai/') === 0) {
      sessionStorage.setItem('sugus_last_page', location.pathname + location.search)
    }
  } catch (e) {}

  function context() {
    var data = { redirect: returnPath(), source_url: location.origin + location.pathname + location.search }
    // 浏览器实际访问的 origin：CDN 回源会把 Host 改写成源站 IP，
    // 服务端无法据此判断用户在哪个域名，必须由前端显式上报（服务端按白名单校验）。
    data.origin = location.origin
    try {
      var attr = JSON.parse(localStorage.getItem('sugus.attr') || '{}')
      Object.keys(attr).forEach(function (key) {
        if (typeof attr[key] === 'string') data[key] = attr[key]
      })
      data.reg_domain = localStorage.getItem('reg_domain') || location.hostname
      data.reg_channel = localStorage.getItem('reg_channel') || ''
      data.reg_platform = localStorage.getItem('reg_platform') || ''
      data.reg_px = localStorage.getItem('promo_px') || ''
      data.reg_link_name = localStorage.getItem('promo_name') || ''
      data.reg_link = localStorage.getItem('promo_l') || ''
      var invite = document.querySelector('#live-login-dialog[open] [name=invite]') || document.getElementById('user_code_registration')
      data.invite_code = (invite && invite.value) || localStorage.getItem('code') || ''
    } catch (e) { /* 本地存储不可用不阻断登录。 */ }
    data.event_id = window.crypto && crypto.randomUUID ? crypto.randomUUID() :
      (window.SugusTrack && SugusTrack.newId ? SugusTrack.newId() : '')
    // 埋点会话ID：登录日志与行为分析关联（tracking.js 提供）
    try {
      if (window.SugusTrack && SugusTrack.ids) {
        var tids = SugusTrack.ids()
        data.vid = tids.vid || ''
        data.sid = tids.sid || ''
      }
    } catch (e2) { /* 埋点ID缺失不影响登录。 */ }
    data.redirect = returnPath()
    return data
  }

  function afterLogin(data, target, eventName) {
    if (!data || !data.access_token || !data.user) throw new Error('Invalid sign-in response.')
    if (window.LiveAuth) window.LiveAuth.save(data, true)
    else {
      localStorage.setItem('live_access_token', data.access_token)
      localStorage.setItem('live_refresh_token', data.refresh_token)
    }
    // 昵称兜底：第三方（Telegram/Discord/Google）可能不返回可用昵称，
    // 直接把 undefined 塞进 JSON 会被 stringify 丢弃，导致侧栏回退成 "My Profile"
    var nick = data.user.nickname
    if (typeof nick !== 'string' || !nick.trim() || nick === 'undefined' || nick === 'null') {
      nick = data.user.email ? String(data.user.email).split('@')[0] : 'Member'
    }
    localStorage.setItem('live_user_info', JSON.stringify({
      token: data.access_token, id: data.user.id, username: nick,
      avatar: data.user.avatar || '', email: data.user.email || ''
    }))
    try {
      if (window.SugusTrack) SugusTrack.event(eventName || (data.is_new_user ? 'register' : 'login'), {}, data.event_id)
    } catch (e) { /* 转化上报不影响登录。 */ }
    location.replace(safeReturn(target || data.redirect) || returnPath())
  }

  function installStyle() {
    if (document.getElementById('live-login-gateway-style')) return
    var style = document.createElement('style')
    style.id = 'live-login-gateway-style'
    style.textContent =
      '.live-login-methods{margin:22px 0 4px;font-family:inherit;display:flex;flex-direction:column}' +
      '.live-login-divider{display:flex;align-items:center;gap:12px;margin-bottom:16px;color:#8f8a97;font-size:13px}' +
      '.live-login-divider:before,.live-login-divider:after{content:"";height:1px;flex:1;background:rgba(255,255,255,.12)}' +
      '.live-login-methods button{box-sizing:border-box;font:inherit;cursor:pointer;border-radius:10px;min-height:46px}' +
      '.live-email-entry{order:9;width:100%;background:transparent;border:1px solid rgba(255,255,255,.16);color:#fff;' +
        'font-size:14px;font-weight:500;height:46px;padding:0 14px}' +
      '.live-email-entry:hover:not(:disabled){background:rgba(255,255,255,.06)}' +
      // 社交登录：整行 Google + 两列 Discord/Telegram，尺寸对齐 candy.ai（高 46、圆角 10、14px）
      '.live-social-row{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px}' +
      '.live-social-row .live-provider-google{grid-column:1 / -1}' +
      '.live-social-row button{display:flex;align-items:center;justify-content:center;width:100%;min-width:0;height:46px;' +
      'gap:10px;padding:0 12px;border:none;border-radius:10px;font-size:14px;font-weight:600;' +
      'line-height:1.2;transition:filter .15s ease,transform .1s ease}' +
      '.live-social-row button svg{width:22px;height:22px;flex:0 0 22px;display:block;pointer-events:none}' +
      '.live-social-row button span{min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
      '.live-social-row .live-provider-discord,.live-social-row .live-provider-telegram{font-size:13px}' +
      // 品牌配色（与 candy.ai 一致）
      '.live-social-row .live-provider-google{background:#fff;color:#344054}' +
      '.live-social-row .live-provider-discord{background:#5865F2;color:#fff}' +
      '.live-social-row .live-provider-twitter{background:#fff;color:#0f1419}' +
      '.live-social-row .live-provider-telegram{background:#229ED9;color:#fff}' +
      '.live-social-row button:hover:not(:disabled){filter:brightness(1.08)}' +
      '.live-social-row button:active:not(:disabled){transform:scale(.985)}' +
      '.live-email-entry:hover:not(:disabled){background:#383036}' +
      '.live-login-methods button:disabled{opacity:.45;cursor:not-allowed}' +
      '.live-login-methods small{display:block;font-size:10px;font-weight:400;margin-top:4px;color:#bcbcbc}' +
      '#email-signin-dialog{box-sizing:border-box;width:min(420px,92vw);max-height:90dvh;padding:0;margin:auto;' +
      'border:1px solid #3d3d3d;border-radius:16px;background:#191919;color:#eee;font-family:inherit;overflow:hidden}' +
      '#email-signin-dialog::backdrop{background:rgba(0,0,0,.72)}' +
      '#email-signin-form{display:flex;flex-direction:column;max-height:90dvh;margin:0}' +
      '#email-signin-dialog .email-signin-body{overflow:auto;padding:26px 26px 10px}' +
      '#email-signin-dialog h2{font-size:24px;margin:0 0 10px;color:#fff}' +
      '#email-signin-dialog p{font-size:13px;line-height:1.6;margin:0 0 20px;color:#aaa}' +
      '#email-signin-dialog label{display:block;font-size:13px;margin:0 0 16px}' +
      '#email-signin-dialog input{box-sizing:border-box;width:100%;min-width:0;background:#252525;color:#eee;' +
      'border:1px solid #404040;border-radius:9px;padding:12px;font:inherit;margin-top:8px}' +
      '#email-signin-dialog input:focus{outline:2px solid #E75275;outline-offset:1px}' +
      '#email-signin-dialog button{font:inherit;cursor:pointer}' +
      '#email-signin-dialog button:disabled{opacity:.5;cursor:not-allowed}' +
      '#email-signin-dialog .email-code-row{display:flex;align-items:stretch;gap:8px;margin-top:8px}' +
      '#email-signin-dialog .email-code-row input{margin:0;flex:1}' +
      '#email-signin-dialog .email-signin-send{flex-shrink:0;border:1px solid #E75275;border-radius:9px;' +
      'background:transparent;color:#f4a2b4;padding:10px}' +
      '#email-signin-dialog .email-signin-actions{flex-shrink:0;display:flex;gap:10px;padding:16px 26px 26px;background:#191919}' +
      '#email-signin-dialog .email-signin-actions button{border-radius:9px;padding:12px;min-height:44px}' +
      '#email-signin-dialog .email-signin-close{background:#292929;color:#ccc;border:1px solid #444}' +
      '#email-signin-dialog .email-signin-submit{flex:1;background:#E75275;color:white;border:0;font-weight:600}' +
      '#email-signin-status{font-size:13px;line-height:1.5;min-height:20px;color:#f4a2b4}' +
      /* 登录弹窗：版式对齐 candy.ai（左图 380px + 右表单，标题 24px、控件高 46px、圆角 10px） */
      '[data-main-target="signInModal"].live-legacy-off{display:none!important}' +
      '#live-login-dialog{box-sizing:border-box;width:min(850px,calc(100% - 32px));max-height:calc(100dvh - 32px);' +
        'margin:auto;padding:0;border:0;border-radius:12px;background:#141316;color:#fff;' +
        'font-family:inherit;overflow:hidden;position:relative;' +
        'box-shadow:0 30px 90px rgba(0,0,0,.6)}' +
      '#live-login-dialog::backdrop{background:rgba(0,0,0,.72);backdrop-filter:blur(3px)}' +
      '#live-login-dialog[open]{display:grid;grid-template-columns:380px minmax(0,1fr)}' +
      '#live-login-dialog [hidden]{display:none!important}' +
      '#live-login-dialog .live-login-art{position:relative;display:none;overflow:hidden;background:#1d1a20;min-height:100%}' +
      '#live-login-dialog .live-login-art img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center top}' +
      '#live-login-dialog .live-login-art:after{content:"";position:absolute;inset:0;' +
        'background:linear-gradient(180deg,rgba(0,0,0,0) 55%,rgba(0,0,0,.55) 100%)}' +
      '#live-login-dialog .live-login-brand{position:absolute;left:26px;bottom:22px;z-index:1;' +
        'font-size:27px;font-weight:800;letter-spacing:-.4px;color:#fff;' +
        'text-shadow:0 2px 16px rgba(0,0,0,.7),0 0 3px rgba(0,0,0,.5)}' +
      '#live-login-dialog .live-login-brand i{font-style:normal;color:#FF4E6A}' +
      '#live-login-dialog .live-login-wordmark{height:22px;width:auto;display:block}' +
      '#live-login-dialog .live-login-body{display:flex;flex-direction:column;padding:36px 40px 24px;min-width:0;overflow:auto;max-height:calc(100dvh - 32px)}' +
      '#live-login-dialog h2{margin:0 34px 22px 0;font-size:24px;font-weight:700;line-height:32px;letter-spacing:-.2px}' +
      '#live-login-dialog form{display:grid;gap:14px;margin:0}' +
      /* 标签文字只留给读屏：视觉上按 candy.ai 用 placeholder + 左侧图标 */
      '#live-login-dialog label{display:grid;gap:7px;text-align:left;font-size:0;font-weight:500;color:#b3aeb9}' +
      '#live-login-dialog label > input,#live-login-dialog label .live-login-row input{font-size:15px}' +
      '#live-login-dialog input{width:100%;min-width:0;box-sizing:border-box;height:46px;background:#EFEDF7;color:#151219;' +
        'border:1px solid transparent;border-radius:10px;padding:0 14px;font:inherit;font-size:15px;font-weight:500}' +
      '#live-login-dialog input::placeholder{color:#7d778c}' +
      '#live-login-dialog input[type=email],#live-login-dialog input[type=password]{padding-left:46px;' +
        'background-repeat:no-repeat;background-position:14px center;background-size:20px 20px}' +
      '#live-login-dialog input[type=email]{background-image:url("data:image/svg+xml;utf8,' + EMAIL_ICON + '")}' +
      '#live-login-dialog input[type=password]{background-image:url("data:image/svg+xml;utf8,' + LOCK_ICON + '")}' +
      '#live-login-dialog input:focus-visible{outline:0;border-color:#FF4E6A;box-shadow:0 0 0 3px rgba(255,78,106,.22)}' +
      '#live-login-dialog input:-webkit-autofill{-webkit-text-fill-color:#151219;box-shadow:0 0 0 60px #EFEDF7 inset}' +
      '#live-login-dialog input:focus-visible,#live-login-dialog button:focus-visible{outline-offset:2px}' +
      '#live-login-dialog button{font:inherit;cursor:pointer}' +
      '#live-login-dialog button:disabled{opacity:.55;cursor:wait}' +
      '#live-login-dialog .live-login-close{position:absolute;right:14px;top:12px;z-index:2;width:32px;height:32px;border:0;' +
        'border-radius:50%;background:rgba(255,255,255,.09);color:#e9e6ee;font-size:20px;line-height:1}' +
      '#live-login-dialog .live-login-close:hover{background:rgba(255,255,255,.16)}' +
      '#live-login-dialog .live-forgot-wrap{display:flex;justify-content:flex-end;margin:-4px 0 2px}' +
      '#live-login-dialog .live-login-submit{border:0;border-radius:10px;height:46px;color:#fff;' +
        'background:linear-gradient(92deg,#FF4E6A 0%,#FF8095 100%);font-size:15px;font-weight:600;' +
        'box-shadow:0 6px 20px rgba(255,78,106,.28);transition:filter .15s ease,transform .1s ease}' +
      '#live-login-dialog .live-login-submit:hover:not(:disabled){filter:brightness(1.05)}' +
      '#live-login-dialog .live-login-submit:active:not(:disabled){transform:scale(.99)}' +
      '#live-login-dialog .live-login-row{display:flex;align-items:center;gap:8px}' +
      '#live-login-dialog .live-login-row button{height:36px;padding:0 12px;border:1px solid rgba(255,255,255,.2);' +
        'border-radius:9px;color:#e7e3ec;background:none;white-space:nowrap;font-size:13px}' +
      '#live-login-dialog .live-login-link,#live-login-dialog a{border:0;padding:0;background:none;color:#c9c4d2;' +
        'font-size:13px;text-decoration:none}' +
      '#live-login-dialog .live-login-link:hover,#live-login-dialog a:hover{color:#fff;text-decoration:underline}' +
      '#live-login-dialog .live-login-footer{text-align:center;font-size:14px;margin:18px 0 0;padding-top:16px;' +
        'color:#9c96a4;border-top:1px solid rgba(255,255,255,.08)}' +
      '#live-login-dialog .live-login-footer .live-login-link{font-size:14px;font-weight:600;color:#FF7C90}' +
      '#live-login-dialog .live-dialog-methods{margin-top:2px}' +
      '#live-login-status{margin:0;color:#FF8FA3;font-size:13px;line-height:1.45}' +
      '#live-login-status:empty{display:none}' +
      '@media (min-width:821px){#live-login-dialog .live-login-art{display:block}}' +
      '@media (max-width:820px){' +
        '#live-login-dialog{box-sizing:border-box;width:auto;max-width:430px;max-height:calc(100dvh - 24px);margin:auto;left:0;right:0}' +
        '#live-login-dialog[open]{display:block}' +
        '#live-login-dialog .live-login-body{padding:26px 24px 20px}' +
        '#live-login-dialog h2{font-size:22px;line-height:28px;margin-bottom:18px}' +
      '}'
    document.head.appendChild(style)
  }

  function emailDialog() {
    var dialog = document.getElementById('email-signin-dialog')
    if (dialog) return dialog
    dialog = document.createElement('dialog')
    dialog.id = 'email-signin-dialog'
    dialog.setAttribute('aria-labelledby', 'email-signin-title')
    dialog.innerHTML = '<form id="email-signin-form"><div class="email-signin-body">' +
      '<h2 id="email-signin-title">Sign in with email</h2>' +
      '<p>No password needed. New here? Verifying your email creates an account.</p>' +
      '<label>Email<input type="email" name="email" autocomplete="email" maxlength="128" required></label>' +
      '<label>Sign-in code<div class="email-code-row"><input name="code" inputmode="numeric" ' +
      'autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required placeholder="6-digit code">' +
      '<button class="email-signin-send" type="button">Send code</button></div></label>' +
      '<div id="email-signin-status" role="status" aria-live="polite"></div></div>' +
      '<div class="email-signin-actions"><button type="button" class="email-signin-close">Cancel</button>' +
      '<button type="submit" class="email-signin-submit">Continue</button></div></form>'
    document.body.appendChild(dialog)
    var form = dialog.querySelector('form')
    var status = dialog.querySelector('#email-signin-status')
    var send = dialog.querySelector('.email-signin-send')
    var submit = dialog.querySelector('.email-signin-submit')
    dialog.querySelector('.email-signin-close').onclick = function () { dialog.close() }
    dialog.addEventListener('close', function () { form.elements.code.value = '' })
    send.onclick = function () {
      if (send.disabled || !form.elements.email.reportValidity()) return
      send.disabled = true
      send.textContent = 'Sending...'
      post('/sendEmailLoginCode', { email: form.elements.email.value.trim() }).then(function () {
        status.textContent = 'Code sent. Check your inbox and spam folder. It expires in 5 minutes.'
        cooldownUntil = Date.now() + 60000
        tick()
        var timer = setInterval(function () {
          tick()
          if (Date.now() >= cooldownUntil || !send.isConnected) clearInterval(timer)
        }, 1000)
      }).catch(function (error) {
        status.textContent = error.message
        send.disabled = false
        send.textContent = 'Send code'
      })
    }
    function tick() {
      var left = Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000))
      send.disabled = left > 0
      send.textContent = left ? left + 's' : 'Send code'
    }
    form.addEventListener('submit', function (event) {
      event.preventDefault()
      if (submit.disabled || !form.reportValidity()) return
      submit.disabled = true
      submit.textContent = 'Signing in...'
      var data = context()
      data.email = form.elements.email.value.trim()
      data.code = form.elements.code.value
      post('/emailCodeLogin', data).then(afterLogin).catch(function (error) {
        status.textContent = error.message
        submit.disabled = false
        submit.textContent = 'Continue'
      })
    })
    return dialog
  }

  function submitPassword(mode, values, redirect) {
    var signup = mode === 'signup'
    var data = Object.assign(signup ? context() : {}, values)
    var target = safeReturn(redirect) || returnPath()
    return post(signup ? '/registerFromAi' : '/login', data).then(function (result) {
      if (result && signup && !result.event_id) result.event_id = data.event_id
      afterLogin(result, target, signup ? 'register' : 'login')
    })
  }

  function loginPage(mode, redirect) {
    return LOGIN + '?mode=' + encodeURIComponent(mode) + '&redirect=' + encodeURIComponent(redirect)
  }

  /* 共用弹窗：不依赖页面残留的镜像表单；所有页面沿用同一套登录 API、会话和社交流程。 */
  /* 输入框左侧图标（内联 SVG，避免额外请求；# 需转义成 %23） */
  var EMAIL_ICON = '<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%234C4657%22 ' +
    'stroke-width=%221.8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><rect x=%222.5%22 y=%224.5%22 width=%2219%22 height=%2215%22 rx=%222.5%22/>' +
    '<path d=%22M3.5 6.5l8.5 6 8.5-6%22/></svg>'
  var LOCK_ICON = '<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22 fill=%22none%22 stroke=%22%234C4657%22 ' +
    'stroke-width=%221.8%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22><rect x=%224%22 y=%2210%22 width=%2216%22 height=%2211%22 rx=%222.5%22/>' +
    '<path d=%22M8 10V7.5a4 4 0 018 0V10%22/></svg>'

  /* 登录弹窗左侧配图：与 20 币欢迎弹窗共用同一批 art（assets/welcome/art-01..11.jpg） */
  var ART_DIR = './assets/welcome/'
  var ART_COUNT = 11
  function pickArt() {
    var n = String(Math.floor(Math.random() * ART_COUNT) + 1)
    while (n.length < 2) n = '0' + n
    return ART_DIR + 'art-' + n + '.jpg'
  }

  /* 左侧图片底部品牌字（两色：白 + 品牌粉，仿 candy.ai 的 candy.ai 字标） */
  var BRANDS = {
    'sugus.ai': ['Sugus', 'ai'],
    'heygirl.live': ['HeyGirl', 'live'],
    'kelymi.autos': ['Kelymi', 'autos'],
    'kisss.ai': ['Kisss', 'ai']
  }
  function brandMarkup() {
    var host = String(location.hostname || '').replace(/^www\./i, '').toLowerCase()
    var pair = BRANDS[host]
    if (!pair) {
      var parts = host.split('.')
      var first = parts[0] || 'AI'
      pair = [first.charAt(0).toUpperCase() + first.slice(1), parts.slice(1).join('.') || 'ai']
    }
    return pair[0] + '<i>.' + pair[1] + '</i>'
  }
  /* 站点头像字标：有 wordmark 图就用图（与首页左上角一致），没有则回退两色文字 */
  var WORDMARK_HOSTS = { 'kisss.ai': './sugus-wordmark.png' }
  function brandNode() {
    var host = String(location.hostname || '').replace(/^www\./i, '').toLowerCase()
    var src = WORDMARK_HOSTS[host]
    if (src) return '<img class="live-login-wordmark" src="' + src + '" alt="">'
    return brandMarkup()
  }

  function createLoginDialog() {
    var dialog = document.getElementById('live-login-dialog')
    if (dialog) return dialog
    installStyle()
    dialog = document.createElement('dialog')
    dialog.id = 'live-login-dialog'
    dialog.setAttribute('aria-labelledby', 'live-login-title')
    dialog.innerHTML = '<div class="live-login-art"><img alt="" src="' + pickArt() + '">' +
        '<span class="live-login-brand">' + brandNode() + '</span></div>' +
      '<div class="live-login-body">' +
      '<button class="live-login-close" type="button" aria-label="Close">&times;</button>' +
      '<h2 id="live-login-title"></h2><form data-turbo="false">' +
      '<label data-signup-only hidden>Username<input name="username" autocomplete="username" maxlength="20"></label>' +
      '<label>Email<input type="email" name="email" autocomplete="email" maxlength="128" required autofocus></label>' +
      '<label data-signup-only data-verify-row hidden>Email code<span class="live-login-row">' +
      '<input name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">' +
      '<button type="button" class="live-login-send">Send code</button></span></label>' +
      '<label>Password<input type="password" name="password" required></label>' +
      '<label data-signup-only hidden>Invitation code (optional)<input name="invite" autocomplete="off" maxlength="64"></label>' +
      '<div class="live-forgot-wrap"><button type="button" class="live-login-link live-login-forgot">Forgot password?</button></div>' +
      '<p id="live-login-status" role="status" aria-live="polite"></p>' +
      '<button class="live-login-submit" type="submit"></button></form><div class="live-dialog-methods"></div>' +
      '<p class="live-login-footer" data-signup-only hidden>By signing up, you agree to the <a href="/ai/terms.html">Terms of Service</a>.</p>' +
      '<p class="live-login-footer"><span class="live-login-prompt"></span> <button class="live-login-link live-login-switch" type="button"></button></p>' +
      '</div>'
    document.body.appendChild(dialog)
    var form = dialog.querySelector('form')
    // 标签文字视觉隐藏后，把标签文本搬进 placeholder（对齐 candy.ai 的无标签输入框）
    form.querySelectorAll('input').forEach(function (inp) {
      if (inp.placeholder) return
      var label = inp.closest('label')
      var text = label && label.firstChild && label.firstChild.nodeType === 3 ? label.firstChild.textContent : ''
      text = String(text || '').trim()
      if (text) inp.placeholder = text
    })
    var status = dialog.querySelector('#live-login-status')
    var submit = dialog.querySelector('.live-login-submit')
    var send = dialog.querySelector('.live-login-send')
    var toggle = dialog.querySelector('.live-login-switch')
    var verify = dialog.querySelector('[data-verify-row]')
    var signup = false, needsCode = false, configRequest = null, sendPending = false, sendUntil = 0, busy = false
    function loadRegistrationConfig() {
      if (configRequest) return
      configRequest = fetch(API + '/registerConfig', { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) { return r.json() })
        .then(function (result) {
          if (result && result.code === '00000' && result.data) {
            needsCode = !!Number(result.data.email_verify)
            syncVerification()
          }
        }).catch(function () { /* 服务端仍验证注册条件。 */ })
        .finally(function () { configRequest = null })
    }
    function syncVerification() {
      verify.hidden = !(signup && needsCode)
      form.elements.code.required = signup && needsCode
      form.elements.code.disabled = !(signup && needsCode)
    }
    function syncSend() {
      var left = Math.max(0, Math.ceil((sendUntil - Date.now()) / 1000))
      send.disabled = !!left || sendPending
      send.textContent = sendPending ? 'Sending…' : left ? left + 's' : 'Send code'
      return left
    }
    dialog.setLoginMode = function (mode) {
      var next = mode === 'signup'
      if (signup !== next) form.elements.password.value = ''
      signup = next
      dialog.dataset.mode = signup ? 'signup' : 'signin'
      dialog.querySelector('#live-login-title').textContent = signup ? 'Create an account' : 'Sign in'
      dialog.querySelectorAll('[data-signup-only]').forEach(function (el) { el.hidden = !signup })
      dialog.querySelector('.live-forgot-wrap').hidden = signup
      dialog.querySelector('.live-login-prompt').textContent = signup ? 'Already have an account?' : 'New here?'
      toggle.textContent = signup ? 'Sign in' : 'Create account'
      form.elements.username.required = signup
      form.elements.password.autocomplete = signup ? 'new-password' : 'current-password'
      if (signup) {
        form.elements.password.minLength = 6
        form.elements.password.maxLength = 20
        if (!form.elements.invite.value) {
          try { form.elements.invite.value = localStorage.getItem('code') || '' } catch (e) {}
        }
        loadRegistrationConfig()
      } else {
        form.elements.password.removeAttribute('minlength')
        form.elements.password.removeAttribute('maxlength')
      }
      syncVerification()
      syncSend()
      status.textContent = ''
      submit.textContent = signup ? 'Create account' : 'Sign in'
    }
    dialog.querySelector('.live-login-close').onclick = function () { dialog.close() }
    dialog.querySelector('.live-login-forgot').onclick = function () {
      if (window.liveOpenPasswordReset) window.liveOpenPasswordReset(form.elements.email.value)
      else location.assign(loginPage('reset', returnPath()))
    }
    dialog.addEventListener('close', function () {
      if (!dialog.open) {
        activeReturn = ''
        form.elements.password.value = ''
        form.elements.code.value = ''
        status.textContent = ''
      }
    })
    toggle.onclick = function () { if (!busy) { dialog.setLoginMode(signup ? 'signin' : 'signup'); form.elements.email.focus() } }
    send.onclick = function () {
      if (send.disabled || !form.elements.email.reportValidity()) return
      sendPending = true
      syncSend()
      post('/sendEmailCode', { email: form.elements.email.value.trim() }).then(function () {
        status.textContent = 'Code sent. Check your inbox.'
        sendUntil = Date.now() + 60000
        var timer = setInterval(function () { if (!syncSend() || !dialog.isConnected) clearInterval(timer) }, 1000)
      }).catch(function (error) { status.textContent = error.message })
        .finally(function () { sendPending = false; syncSend() })
    }
    form.addEventListener('submit', function (event) {
      event.preventDefault()
      if (busy || !form.reportValidity()) return
      var email = form.elements.email.value.trim()
      var values = signup ? { username: form.elements.username.value.trim(), email: email,
        invite_code: form.elements.invite.value.trim(), code: form.elements.code.value } : { account: email }
      values.password = form.elements.password.value
      busy = submit.disabled = toggle.disabled = true
      status.textContent = ''
      submitPassword(signup ? 'signup' : 'signin', values, returnPath()).catch(function (error) {
        status.textContent = error.message
        busy = submit.disabled = toggle.disabled = false
      })
    })
    return dialog
  }

  function openLogin(request) {
    request = request || {}
    var mode = request.mode === 'signup' ? 'signup' : 'signin'
    var target = safeReturn(request.redirect) || safeReturn(location.href) || returnPath()
    if (onLoginPage) {
      var switchForm = mode === 'signup' ? window.showSignUp : window.showSignIn
      if (typeof switchForm === 'function') switchForm()
      return true
    }
    if (!window.HTMLDialogElement || !HTMLDialogElement.prototype.showModal) {
      location.assign(loginPage(mode, target))
      return true
    }
    // 已经显示的流程保留自己的回跳；切换登录 / 注册不必创建第二层弹窗。
    var dialog = createLoginDialog()
    activeReturn = target
    dialog.setLoginMode(mode)
    if (!dialog.open) dialog.showModal()
    initialize()
    return true
  }

  function bindLoginEntries() {
    if (onLoginPage) return
    function onEntry(event) {
      if (event.defaultPrevented || event.ctrlKey || event.metaKey || event.altKey || event.shiftKey) return
      if (event.type === 'click' && event.button !== 0) return
      if (event.type === 'keydown' && event.key !== 'Enter' && event.key !== ' ') return
      var t = event.target
      var entry = t && t.closest && t.closest('[data-live-login], [data-action*="openSignInModal"], [data-action*="openRegistrationModal"], a[href], [onclick*="Login.html"]')
      if (!entry || entry.hasAttribute('data-live-login-page') || entry.getAttribute('target') === '_blank' || entry.hasAttribute('download')) return
      if (event.type === 'keydown' && entry.matches('a,button')) return
      var action = entry.getAttribute('data-action') || ''
      var explicit = entry.getAttribute('data-live-login')
      var mode = explicit || (/openRegistrationModal/.test(action) ? 'signup' : /openSignInModal/.test(action) ? 'signin' : '')
      var redirect = entry.getAttribute('data-redirect-url') || ''
      if (!mode) {
        var href = entry.getAttribute('href')
        if (href) {
          var url = new URL(href, location.href)
          if (url.origin !== location.origin || !/\/Login\.html$/i.test(url.pathname) || url.hash) return
          redirect = url.searchParams.get('redirect') || redirect
          mode = url.searchParams.get('mode') || ''
          if (mode === 'reset') return
        }
        var text = (entry.getAttribute('aria-label') || entry.textContent || '').trim()
        if (!mode && !/^(?:register|sign\s*up|join\s*free|create\s*(?:an?\s*)?account|log\s*in|sign\s*in)$/i.test(text)) return
        if (!mode) mode = /^(register|sign\s*up|join|create)/i.test(text) ? 'signup' : 'signin'
      }
      if (redirect === '/') redirect = '' // 旧镜像控件的默认占位值
      event.preventDefault()
      event.stopImmediatePropagation()
      openLogin({ mode: mode, redirect: redirect })
    }
    document.addEventListener('click', onEntry, true)
    document.addEventListener('keydown', onEntry, true)
  }

  /* ---------- Telegram Login Widget（整行渲染，官方 iframe 按钮不进等宽网格） ---------- */
  var telegramBusy = false
  // widget iframe 的 data-onauth 只认全局函数名，这里桥接到登录网关的提交流程
  window.__liveTelegramAuth = function (user) {
    if (!user || !user.id || !user.hash || telegramBusy) return
    telegramBusy = true
    var data = context()
    // widget 载荷字段最后覆盖，防止 localStorage 归因键意外碰撞签名字段
    Object.keys(user).forEach(function (key) { data[key] = user[key] })
    post('/telegramLogin', data).then(function (result) {
      telegramBusy = false
      afterLogin(result)
    }).catch(function (error) {
      telegramBusy = false
      message(error.message)
    })
  }

  /* Telegram 深链登录：点击开 TG 授权 -> 前台轮询确认 -> 自动关 TG 标签并落登录态。
     设计要点：
       - 拿不到 window 引用（noopener / 弹窗拦截）也继续轮询，用户可手动回到本站
       - 回到本页(visibilitychange)立即复查一次，不用等下一个定时器
       - 模块级锁，防止重复点击叠加多条轮询链
       - 成功后校验 token 是否真的写入，失败给出可操作提示 */
  var tgBusy = false
  var tgTimer = null
  var tgWin = null
  var tgButton = null

  function tgStop() {
    if (tgTimer) { clearTimeout(tgTimer); tgTimer = null }
    tgBusy = false
    if (tgButton) { tgButton.disabled = false; tgButton.classList.remove('is-busy'); tgButton = null }
    var w = tgWin
    tgWin = null
    if (w) { try { if (!w.closed) w.close() } catch (e) { /* 跨域窗口可能拒绝关闭 */ } }
  }

  /* 把 Telegram 按钮追加到统一的社交按钮行（与 Discord/Google 同款整行按钮） */
  function mountTelegram(box) {
    var provider = (options.providers || []).filter(function (item) {
      return item.enabled && item.id === 'telegram'
    })[0]
    if (!provider || !provider.bot) return
    var row = box.querySelector('.live-social-row')
    if (!row || row.querySelector('.live-provider-telegram')) return

    var tg = document.createElement('button')
    tg.type = 'button'
    tg.className = 'live-provider-telegram'
    tg.title = 'Continue with Telegram'
    tg.innerHTML = (ICONS.telegram || '') + '<span></span>'
    tg.querySelector('span').textContent = 'Telegram'

    var code = ''
    var browser = ''
    var loginContext = null
    var tries = 0
    var polling = false

    function pollOnce() {
      if (!tgBusy || tgButton !== tg || polling || !code) return
      polling = true
      post('/telegramPoll', Object.assign({}, loginContext, { code: code, browser: browser })).then(function (d) {
        polling = false
        if (!tgBusy) return
        if (d && d.pending) { schedule(1500); return }
        /* 登录成功 */
        var ok = false
        try {
          afterLogin(d)
          ok = true
        } catch (err) {
          if (window.console) console.error('[telegram] afterLogin failed', err)
          message('Signed in on Telegram, but this browser blocked storage. Please allow site data and retry.')
        }
        tgStop()
        if (!ok) return
      }).catch(function (err) {
        polling = false
        if (!tgBusy) return
        if (window.console) console.error('[telegram] poll error', err)
        schedule(2000)
      })
    }

    function schedule(ms) {
      if (!tgBusy) return
      if (tries-- <= 0) { tgStop(); message('Telegram sign-in timed out. Please try again.'); return }
      if (tgTimer) clearTimeout(tgTimer)
      tgTimer = setTimeout(pollOnce, ms)
    }

    /* 用户切回本页时立刻复查，避免“已经授权但还在等定时器” */
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden && tgButton === tg) {
        if (tgTimer) { clearTimeout(tgTimer); tgTimer = null }
        pollOnce()
      }
    })

    tg.onclick = function () {
      if (tgBusy) return
      tgBusy = true
      tgButton = tg
      code = ''
      tries = 40
      loginContext = context()
      tg.disabled = true
      tg.classList.add('is-busy')
      post('/telegramStart', loginContext).then(function (d) {
        if (!d || !d.deep_link) {
          tgStop()
          message('Telegram sign-in is unavailable right now.')
          return
        }
        code = d.code
        browser = d.browser
        /* 不加 noopener：需要窗口引用才能在成功后自动关闭 TG 标签。
           即使被拦截/返回 null，也不中断轮询。 */
        try { tgWin = window.open(d.deep_link, 'telegram_login') } catch (e) { tgWin = null }
        if (!tgWin) {
          message('Open t.me/' + provider.bot + ' and press START to finish signing in.')
        }
        schedule(1200)
      }).catch(function (err) {
        tgStop()
        message((err && err.message) || 'Telegram sign-in failed.')
      })
    }

    row.appendChild(tg)
  }

  function authMethods(getEmail) {
    // Google/Discord/X 走 OAuth，Telegram 走深链轮询；新弹窗与独立登录页公用入口。
    var providers = ['google', 'discord', 'twitter', 'telegram'].map(function (id) {
      return (options.providers || []).find(function (item) { return item.id === id && item.enabled })
    }).filter(Boolean)
    if (!options.email_code && !providers.length) return null
    var box = document.createElement('div')
    box.className = 'live-login-methods'
    var divider = document.createElement('div')
    divider.className = 'live-login-divider'
    divider.textContent = 'or continue with'
    box.appendChild(divider)
    if (options.email_code) {
      var email = document.createElement('button')
      email.type = 'button'
      email.className = 'live-email-entry'
      email.textContent = 'Email code - no password'
      email.onclick = function () {
        var dialog = emailDialog()
        dialog.querySelector('[name=email]').value = getEmail() || ''
        dialog.querySelector('#email-signin-status').textContent = ''
        if (!dialog.open) dialog.showModal()
      }
      box.appendChild(email)
    }
    if (providers.length) {
      var row = document.createElement('div')
      row.className = 'live-social-row'
      providers.forEach(function (provider) {
        if (provider.id === 'telegram') return
        var social = document.createElement('button')
        social.type = 'button'
        social.className = 'live-provider-' + provider.id
        social.innerHTML = (ICONS[provider.id] || '') + '<span></span>'
        social.querySelector('span').textContent = provider.id === 'google'
          ? 'Continue with ' + (LABELS[provider.id] || provider.label)
          : (LABELS[provider.id] || provider.label)
        social.onclick = function () { startOAuth(provider, social) }
        row.appendChild(social)
      })
      box.appendChild(row)
    }
    mountTelegram(box)
    if (row && !row.children.length) row.remove()
    return options.email_code || (row && row.children.length) ? box : null
  }

  function mount() {
    if (!options) return
    installStyle()
    document.querySelectorAll('.google-login-box').forEach(function (old) { old.remove() })
    var shared = document.querySelector('.live-dialog-methods')
    if (shared && !shared.firstChild) {
      var methods = authMethods(function () { return shared.parentNode.querySelector('[name=email]').value })
      if (methods) shared.appendChild(methods)
    }
    ;['signin-btn', 'signup-btn'].forEach(function (id) {
      var button = document.querySelector('button#' + id)
      if (!button || button.parentNode.querySelector('.live-login-methods')) return
      var box = authMethods(function () {
        var input = document.getElementById(id === 'signup-btn' ? 'user_email_registration' : 'user_email')
        return input && input.value
      })
      if (box) button.insertAdjacentElement('afterend', box)
    })
  }

  function initialize() {
    if (!document.querySelector('button#signin-btn, button#signup-btn, #live-login-dialog[open]')) return
    if (options) { mount(); return }
    if (pending) return
    pending = fetch(API + '/loginOptions', { credentials: 'same-origin', cache: 'no-store' })
      .then(function (response) { return response.json() })
      .then(function (result) {
        if (result.code === '00000') { options = result.data; mount() }
      }).catch(function () { /* 配置接口不可达时保留现有密码登录。 */ })
      .finally(function () { pending = null })
  }

  /* ---------- 跳转型 OAuth（Discord/Google/X）：弹窗登录，与 Telegram 体验一致 ----------
     主页面不跳转；授权页在弹窗里完成，回调仍在弹窗内兑换票据拿到 token，
     然后写 localStorage 通知主窗口（同源共享），主窗口取到即登录并关掉弹窗。 */
  var OAUTH_RESULT_KEY = 'oauth_result'
  var OAUTH_BROWSER_KEY = 'oauth_browser'
  var oauthPopupTimer = null
  var oauthPending = false

  /** 读浏览器绑定令牌：弹窗与主窗口的 sessionStorage 可能不共享，localStorage 兜底 */
  function readOAuthBrowser() {
    var v = ''
    try { v = sessionStorage.getItem(OAUTH_BROWSER_KEY) || '' } catch (e) {}
    if (!v) { try { v = localStorage.getItem(OAUTH_BROWSER_KEY) || '' } catch (e) {} }
    return v
  }
  function clearOAuthBrowser() {
    try { sessionStorage.removeItem(OAUTH_BROWSER_KEY) } catch (e) {}
    try { localStorage.removeItem(OAUTH_BROWSER_KEY) } catch (e) {}
  }

  function startOAuth(provider, button) {
    if (oauthPending || oauthPopupTimer) return
    oauthPending = true
    button.disabled = true
    var w = null
    // 必须在点击的用户手势内打开窗口；等网络请求结束才 open 会被手机端拦截。
    try { w = window.open('about:blank', 'oauth_' + provider.id, 'width=520,height=680,menubar=no,toolbar=no') } catch (e) {}
    try { localStorage.removeItem(OAUTH_RESULT_KEY) } catch (e) {}
    post('/socialStart/' + provider.id, context()).then(function (data) {
      if (!data || !data.authorize_url) throw new Error('This sign-in provider is unavailable right now.')
      if (w && w.closed) { button.disabled = false; return }
      // 浏览器绑定令牌：sessionStorage 兜底整页回调，localStorage 供同源弹窗兑换。
      try { sessionStorage.setItem(OAUTH_BROWSER_KEY, data.browser || '') } catch (e) {}
      try { localStorage.setItem(OAUTH_BROWSER_KEY, data.browser || '') } catch (e) {}
      if (!w) {
        button.disabled = false
        location.assign(data.authorize_url)
        return
      }
      w.location.replace(data.authorize_url)
      waitForOAuthPopup(w, button)
    }).catch(function (error) {
      if (w) { try { w.close() } catch (e) {} }
      button.disabled = false
      message(error.message)
    }).finally(function () { oauthPending = false })
  }

  function waitForOAuthPopup(w, button) {
    var started = Date.now()
    if (oauthPopupTimer) clearInterval(oauthPopupTimer)
    oauthPopupTimer = setInterval(function () {
      // 结果由弹窗内的 callback() 写入 localStorage（同源可读）
      var raw = null
      try { raw = localStorage.getItem(OAUTH_RESULT_KEY) } catch (e) {}
      if (raw) {
        try { localStorage.removeItem(OAUTH_RESULT_KEY) } catch (e) {}
        clearInterval(oauthPopupTimer); oauthPopupTimer = null
        try { w.close() } catch (e) {}
        var res = null
        try { res = JSON.parse(raw) } catch (e) {}
        if (res && res.error) {
          button && (button.disabled = false)
          message(res.error === 'cancelled'
            ? 'Authorization was cancelled. Please try again.'
            : 'Authorization failed. Please try again.')
          return
        }
        if (res && res.data) {
          try { afterLogin(res.data); return } catch (error) { message(error.message) }
        }
        button && (button.disabled = false)
        return
      }
      if (w.closed) {
        // 用户手动关掉弹窗但没完成授权
        clearInterval(oauthPopupTimer); oauthPopupTimer = null
        button && (button.disabled = false)
        return
      }
      if (Date.now() - started > 600000) { // 10 分钟兜底
        clearInterval(oauthPopupTimer); oauthPopupTimer = null
        try { w.close() } catch (e) {}
        button && (button.disabled = false)
        message('Sign-in timed out. Please try again.')
      }
    }, 700)
  }

  function callback() {
    if (!onLoginPage) return
    var params = new URLSearchParams(location.hash.slice(1))
    var ticket = params.get('oauth_ticket')
    var error = params.get('oauth_error')
    if (!ticket && !error) return
    // 在清除片段前读取弹窗标志；跨站导航的 COOP 可能切断 opener。
    var inPopup = params.get('oauth_pw') === '1' || (String(window.name || '').indexOf('oauth_') === 0) ||
      (!!window.opener && !window.opener.closed)
    history.replaceState(null, '', location.pathname + location.search)
    function finish(payload) {
      if (!inPopup) return
      try { localStorage.setItem(OAUTH_RESULT_KEY, JSON.stringify(payload)) } catch (e) {}
      setTimeout(function () { try { window.close() } catch (e) {} }, 120)
    }
    if (error) {
      finish({ error: 'cancelled' })
      if (!inPopup) message('Authorization was cancelled or expired. Please try again.')
      return
    }
    // 兑换票据时带上发起登录时存的 browser 令牌（CDN 吞 cookie 时的唯一绑定依据）
    var data = { ticket: ticket }
    var b = readOAuthBrowser()
    if (b) data.browser = b
    clearOAuthBrowser()
    post('/socialExchange', data).then(function (result) {
      finish({ data: result })
      if (!inPopup) afterLogin(result)
    }).catch(function (failure) {
      finish({ error: 'failed', message: failure && failure.message })
      if (!inPopup) message(failure.message)
    })
  }

  /**
   * 老站（kelymi / kiss 等模板）的登录弹窗是 Alpine 直接显示 `[data-main-target="signInModal"]`，
   * 不走点击事件，所以拦不住。这里监听它「变成可见」的瞬间：只要是纯登录（没有带上
   * profile_id / prompt 的生成上下文），就关掉旧的、打开新版 candy 风格弹窗。
   */
  function upgradeLegacyModal() {
    if (window.__liveLegacyWatch) return
    // 老站（kelymi / kiss 等）的登录弹窗由 Alpine 直接显示，turbo-frame 还可能整个重建。
    // 用常驻轮询最稳：发现它可见就强隐藏，并改开新版弹窗。
    window.__liveLegacyWatch = setInterval(function () {
      var legacy = document.querySelector('[data-main-target="signInModal"]')
      if (!legacy) return
      var shown = String(legacy.className || '').indexOf('hidden') < 0 && legacy.offsetWidth > 0
      if (!shown) return
      legacy.classList.add('live-legacy-off')
      try { legacy.style.setProperty('display', 'none', 'important') } catch (e) {}
      var live = document.getElementById('live-login-dialog')
      if (live && live.open) return
      try { openLogin({ mode: 'signin' }) } catch (e) { if (window.console) console.warn('[login] open failed', e) }
    }, 600)
  }

  function ready() {
    // 老站弹窗替换先跑：initialize() 依赖 /loginOptions 网络请求，失败时不能连带跳过替换
    try { upgradeLegacyModal() } catch (e) { if (window.console) console.warn('[login] legacy swap failed', e) }
    bindLoginEntries()
    initialize()
    callback()
    var queued = window.__liveLoginRequest
    delete window.__liveLoginRequest
    if (queued) openLogin(queued)
    document.addEventListener('turbo:load', initialize)
    document.addEventListener('turbo:render', initialize)
    document.addEventListener('turbo:frame-load', initialize)
  }
  window.LiveLogin = {
    open: function (request) {
      if (!document.body) { window.__liveLoginRequest = request || {}; return true }
      return openLogin(request)
    },
    submitPassword: submitPassword
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready)
  else ready()
})()
