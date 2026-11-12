(function () {
  'use strict'
  if (window.__liveLoginGatewayLoaded) return
  window.__liveLoginGatewayLoaded = true
  var options = null
  var pending = null
  var mountTimer = null
  var cooldownUntil = 0
  var API = '/api/live'
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
    if (window.layer && window.layer.msg) window.layer.msg(text)
    else window.alert(text)
  }

  function returnPath() {
    try {
      var requested = new URL(location.href).searchParams.get('redirect')
      // 兜底顺序：URL 的 redirect -> 站内 referrer -> sessionStorage 记录的来路
      if (!requested && location.pathname.indexOf('Login.html') >= 0) {
        requested = document.referrer || ''
        if (!requested) {
          try { requested = sessionStorage.getItem('sugus_login_from') || '' } catch (e) {}
        }
        // 登录页首次进入时，把来路存下来（部分浏览器/隐私模式 referrer 为空）
        if (!requested) {
          try { requested = sessionStorage.getItem('sugus_last_page') || '' } catch (e) {}
        }
      }
      var url = new URL(requested || location.href, location.href)
      if (url.origin === location.origin && url.pathname.indexOf('/ai/') === 0 &&
          url.pathname.toLowerCase().indexOf('login.html') < 0) return url.pathname + url.search
    } catch (e) { /* 非同源或非法返回地址采用首页。 */ }
    return '/ai/Home.html'
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
      var invite = document.getElementById('user_code_registration')
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

  function afterLogin(data) {
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
      if (window.SugusTrack) SugusTrack.event(data.is_new_user ? 'register' : 'login', {}, data.event_id)
    } catch (e) { /* 转化上报不影响登录。 */ }
    var target = data.redirect || '/ai/Home.html'
    var url = new URL(target, location.origin)
    location.replace(url.origin === location.origin && url.pathname.indexOf('/ai/') === 0
      ? url.pathname + url.search : '/ai/Home.html')
  }

  function installStyle() {
    if (document.getElementById('live-login-gateway-style')) return
    var style = document.createElement('style')
    style.id = 'live-login-gateway-style'
    style.textContent =
      '.live-login-methods{margin:22px 0 12px;font-family:inherit}' +
      '.live-login-divider{display:flex;align-items:center;gap:12px;margin-bottom:16px;color:#999;font-size:12px}' +
      '.live-login-divider:before,.live-login-divider:after{content:"";height:1px;flex:1;background:#3a3a3a}' +
      '.live-login-methods button{box-sizing:border-box;font:inherit;cursor:pointer;border-radius:10px;min-height:44px}' +
      '.live-email-entry{width:100%;background:transparent;border:1px solid #E75275;color:#f4a2b4;font-size:14px;padding:11px}' +
      // 社交登录：整行按钮，风格对齐 candy.ai（白底 Google / 品牌色 Discord / 天蓝 Telegram）
      '.live-social-row{display:flex;flex-direction:column;gap:10px;margin-top:10px}' +
      '.live-social-row button{display:flex;align-items:center;justify-content:center;width:100%;' +
      'gap:12px;padding:11px 16px;border:none;border-radius:10px;font-size:14px;font-weight:500;' +
      'line-height:1.4;transition:filter .15s ease,transform .1s ease}' +
      '.live-social-row button svg{width:22px;height:22px;flex:0 0 22px;display:block;pointer-events:none}' +
      '.live-social-row button span{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}' +
      // 品牌配色（与 candy.ai 一致）
      '.live-social-row .live-provider-google{background:#fff;color:#344054}' +
      '.live-social-row .live-provider-discord{background:#5865F2;color:#fff}' +
      '.live-social-row .live-provider-twitter{background:#fff;color:#0f1419}' +
      '.live-social-row .live-provider-telegram{background:#229ED9;color:#fff}' +
      '.live-social-row button:hover:not(:disabled){filter:brightness(1.08)}' +
      '.live-social-row button:active:not(:disabled){transform:scale(.985)}' +
      '.live-login-methods button:hover:not(:disabled){background:#383036}' +
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
      '#email-signin-status{font-size:13px;line-height:1.5;min-height:20px;color:#f4a2b4}'
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

  function tgStop() {
    if (tgTimer) { clearTimeout(tgTimer); tgTimer = null }
    tgBusy = false
    var btn = document.querySelector('.live-provider-telegram')
    if (btn) { btn.disabled = false; btn.classList.remove('is-busy') }
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
    tg.querySelector('span').textContent = 'Continue with Telegram'

    var code = ''
    var browser = ''
    var tries = 0
    var polling = false

    function pollOnce() {
      if (!tgBusy || polling || !code) return
      polling = true
      post('/telegramPoll', { code: code, browser: browser }).then(function (d) {
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
      if (!document.hidden && tgBusy) {
        if (tgTimer) { clearTimeout(tgTimer); tgTimer = null }
        pollOnce()
      }
    })

    tg.onclick = function () {
      if (tgBusy) return
      tgBusy = true
      tries = 40
      tg.disabled = true
      tg.classList.add('is-busy')
      post('/telegramStart', context()).then(function (d) {
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

  function mount() {
    if (!options) return
    installStyle()
    document.querySelectorAll('.google-login-box').forEach(function (old) { old.remove() })
    ;['signin-btn', 'signup-btn'].forEach(function (id) {
      var button = document.getElementById(id)
      if (!button || button.parentNode.querySelector('.live-login-methods')) return
      var box = document.createElement('div')
      box.className = 'live-login-methods'
      var divider = document.createElement('div')
      divider.className = 'live-login-divider'
      divider.textContent = 'or continue with'
      box.appendChild(divider)
      var email = document.createElement('button')
      email.type = 'button'
      email.className = 'live-email-entry'
      email.disabled = !options.email_code
      email.textContent = options.email_code ? 'Email code - no password' : 'Email code - coming soon'
      email.title = options.email_code ? 'Receive a sign-in code by email' : 'Email sign-in is not configured yet'
      email.onclick = function () {
        var dialog = emailDialog()
        var input = document.getElementById(id === 'signup-btn' ? 'user_email_registration' : 'user_email')
        dialog.querySelector('[name=email]').value = (input && input.value) || ''
        dialog.querySelector('#email-signin-status').textContent = ''
        dialog.showModal()
      }
      box.appendChild(email)
      // 社交登录整行按钮（对齐 candy.ai）：Google/Discord/X 走 OAuth 弹窗，
      // Telegram 走深链轮询（mountTelegram 追加到同一行）。
      // 顺序固定为 Google → Discord → X → Telegram，缺失的自动跳过。
      var PROVIDER_ORDER = ['google', 'discord', 'twitter', 'telegram']
      var byId = {}
      ;(options.providers || []).forEach(function (p) { byId[p.id] = p })
      var providers = PROVIDER_ORDER
        .map(function (id) { return byId[id] })
        .filter(function (p) { return p && p.enabled })
      if (providers.length) {
        var row = document.createElement('div')
        row.className = 'live-social-row'
        providers.forEach(function (provider) {
          if (provider.id === 'telegram') return // 由 mountTelegram 统一追加，复用其轮询逻辑
          var social = document.createElement('button')
          var label = LABELS[provider.id] || provider.label
          social.type = 'button'
          social.className = 'live-provider-' + provider.id
          social.innerHTML = (ICONS[provider.id] || '') + '<span></span>'
          social.querySelector('span').textContent = 'Continue with ' + label
          social.onclick = function () {
            social.disabled = true
            post('/socialStart/' + provider.id, context()).then(function (data) {
              // 浏览器绑定令牌：同时写 session/local —— 弹窗有自己的 sessionStorage，
              // CDN 又会吞 Set-Cookie，localStorage 是同源共享的唯一可靠通道
              try { sessionStorage.setItem(OAUTH_BROWSER_KEY, data.browser || '') } catch (e) {}
              try { localStorage.setItem(OAUTH_BROWSER_KEY, data.browser || '') } catch (e) {}
              openOAuthPopup(provider, data.authorize_url, social)
            }).catch(function (error) {
              social.disabled = false
              message(error.message)
            })
          }
          row.appendChild(social)
        })
        box.appendChild(row)
      }
      mountTelegram(box)
      button.insertAdjacentElement('afterend', box)
    })
  }

  function initialize() {
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

  function openOAuthPopup(provider, url, button) {
    var wname = 'oauth_' + provider.id
    var w = window.open(url, wname, 'width=520,height=680,menubar=no,toolbar=no')
    if (!w) {
      // 弹窗被拦截：退回整页跳转（回调时由 callback() 处理）
      button && (button.disabled = false)
      message('Please allow pop-ups for this site, or the sign-in will open in this tab.')
      location.assign(url)
      return
    }
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
        if (res && res.data) { afterLogin(res.data); return }
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
    if (location.pathname.toLowerCase().indexOf('login.html') < 0) return
    var params = new URLSearchParams(location.hash.slice(1))
    var ticket = params.get('oauth_ticket')
    var error = params.get('oauth_error')
    if (!ticket && !error) return
    history.replaceState(null, '', location.pathname + location.search)
    // 弹窗模式判定：Discord/Google 的 COOP 头可能切断 opener，
    // 故同时看 window.name（openOAuthPopup 里设的）与 opener 是否存在
    var inPopup = (location.hash.indexOf('oauth_pw=1') >= 0) ||
      (String(window.name || '').indexOf('oauth_') === 0) ||
      (!!window.opener && !window.opener.closed)
    function finish(payload) {
      try { localStorage.setItem(OAUTH_RESULT_KEY, JSON.stringify(payload)) } catch (e) {}
      if (inPopup) { setTimeout(function () { try { window.close() } catch (e) {} }, 120) }
      return payload
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

  function ready() {
    initialize()
    callback()
    // 有界重试：仅在前几次 DOM 未就绪时补挂按钮，不做无限 MutationObserver 监听
    var retries = 8
    (function retryMount() {
      if (document.querySelector('.live-login-methods') || retries-- <= 0) return
      setTimeout(retryMount, 250)
    })()
    document.addEventListener('turbo:load', initialize)
    document.addEventListener('turbo:render', initialize)
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready)
  else ready()
})()
