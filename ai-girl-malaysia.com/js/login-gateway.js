(function () {
  'use strict'
  if (window.__liveLoginGatewayLoaded) return
  window.__liveLoginGatewayLoaded = true
  var options = null
  var pending = null
  var mountTimer = null
  var cooldownUntil = 0
  var API = '/api/live'
  // 品牌图标与短标签：官方标志，深色按钮下保持原色可辨识。
  var LABELS = { google: 'Google', discord: 'Discord', twitter: 'X', telegram: 'Telegram' }
  var ICONS = {
    telegram: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="#229ED9" d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.701 8.018l-1.998 9.418c-.15.664-.544.828-1.103.516l-3.047-2.247-1.47 1.415c-.163.163-.3.3-.612.3l.216-3.093 5.648-5.102c.245-.217-.053-.338-.378-.121l-6.985 4.397-3.008-.94c-.654-.204-.667-.654.136-.968l11.744-4.527c.544-.198 1.02.121.857.952z"/>' +
      '</svg>',
    google: '<svg viewBox="0 0 48 48" aria-hidden="true" focusable="false">' +
      '<path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>' +
      '<path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>' +
      '<path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>' +
      '<path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>' +
      '</svg>',
    discord: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="#5865F2" d="M20.317 4.3698a19.7913 19.7913 0 0 0-4.8851-1.5152.0741.0741 0 0 0-.0785.0371c-.211.3753-.4447.8648-.6083 1.2495-1.8447-.2762-3.68-.2762-5.4868 0-.1636-.3933-.4058-.8742-.6177-1.2495a.077.077 0 0 0-.0785-.037 19.7363 19.7363 0 0 0-4.8852 1.515.0699.0699 0 0 0-.0321.0277C.5334 9.0458-.319 13.5799.0992 18.0578a.0824.0824 0 0 0 .0312.0561c2.0528 1.5076 4.0413 2.4228 5.9929 3.0294a.0777.0777 0 0 0 .0842-.0276c.4616-.6304.8731-1.2952 1.226-1.9942a.076.076 0 0 0-.0416-.1057c-.6528-.2476-1.2743-.5495-1.8722-.8923a.077.077 0 0 1-.0076-.1277c.1258-.0943.2517-.1923.3718-.2914a.0743.0743 0 0 1 .0776-.0105c3.9278 1.7933 8.18 1.7933 12.0614 0a.0739.0739 0 0 1 .0785.0095c.1202.099.246.1981.3728.2924a.077.077 0 0 1-.0066.1276 12.2986 12.2986 0 0 1-1.873.8914.0766.0766 0 0 0-.0407.1067c.3604.698.7719 1.3628 1.225 1.9932a.076.076 0 0 0 .0842.0286c1.961-.6067 3.9495-1.5219 6.0023-3.0294a.077.077 0 0 0 .0313-.0552c.5004-5.177-.8382-9.6739-3.5485-13.6604a.061.061 0 0 0-.0312-.0286zM8.02 15.3312c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9555-2.4189 2.157-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.9555 2.4189-2.1569 2.4189zm7.9748 0c-1.1825 0-2.1569-1.0857-2.1569-2.419 0-1.3332.9554-2.4189 2.1569-2.4189 1.2108 0 2.1757 1.0952 2.1568 2.419 0 1.3332-.946 2.4189-2.1568 2.4189Z"/>' +
      '</svg>',
    twitter: '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">' +
      '<path fill="#FFFFFF" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231 5.451-6.231zm-1.161 17.52h1.833L7.084 4.126H5.117l11.966 15.644z"/>' +
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
      if (!requested && location.pathname.indexOf('Login.html') >= 0) requested = document.referrer
      var url = new URL(requested || location.href, location.href)
      if (url.origin === location.origin && url.pathname.indexOf('/ai/') === 0 &&
          url.pathname.toLowerCase().indexOf('login.html') < 0) return url.pathname + url.search
    } catch (e) { /* 非同源或非法返回地址采用首页。 */ }
    return '/ai/Home.html'
  }

  function context() {
    var data = { redirect: returnPath(), source_url: location.origin + location.pathname + location.search }
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
    localStorage.setItem('live_user_info', JSON.stringify({
      token: data.access_token, id: data.user.id, username: data.user.nickname,
      avatar: data.user.avatar, email: data.user.email || ''
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
      '.live-social-row{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin-top:10px}' +
      '.live-social-row button{display:flex;align-items:center;justify-content:center;gap:7px;min-width:0;' +
      'background:#292929;border:1px solid #444;color:#eee;font-size:13px;padding:10px 6px}' +
      '.live-social-row button svg{width:18px;height:18px;flex:0 0 18px;display:block;pointer-events:none}' +
      '.live-social-row button span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}' +
      '.live-login-methods button:hover:not(:disabled){background:#383036}' +
      '.live-login-methods button:disabled{opacity:.45;cursor:not-allowed}' +
      '.live-login-methods small{display:block;font-size:10px;font-weight:400;margin-top:4px;color:#bcbcbc}' +
      '.live-telegram-row{display:flex;justify-content:center;margin-top:12px;min-height:40px}' +
      '.live-telegram-row iframe{max-width:100%}' +
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

  function mountTelegram(box) {
    var provider = (options.providers || []).filter(function (item) {
      return item.enabled && item.id === 'telegram'
    })[0]
    if (!provider || !provider.bot) return
    var row = document.createElement('div')
    row.className = 'live-telegram-row'
    // widget 脚本由 telegram.org 加载，域名未在 bot 的 Allowed URLs 里时静默不渲染
    var script = document.createElement('script')
    script.src = 'https://telegram.org/js/telegram-widget.js?22'
    script.async = true
    script.setAttribute('data-telegram-login', provider.bot)
    script.setAttribute('data-size', 'large')
    script.setAttribute('data-radius', '10')
    script.setAttribute('data-onauth', '__liveTelegramAuth')
    script.setAttribute('data-userpic-single', 'true')
    row.appendChild(script)
    box.appendChild(row)
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
      // 只渲染已配置的提供商：未接入社交登录的站点不出现按钮，也不留占位。
      // telegram 是 widget 整行（见 mountTelegram），不进等宽按钮网格。
      var providers = (options.providers || []).filter(function (provider) {
        return provider.enabled && provider.id !== 'telegram'
      })
      if (providers.length) {
        var row = document.createElement('div')
        row.className = 'live-social-row'
        row.style.gridTemplateColumns = 'repeat(' + providers.length + ',minmax(0,1fr))'
        providers.forEach(function (provider) {
          var social = document.createElement('button')
          var label = LABELS[provider.id] || provider.label
          social.type = 'button'
          social.innerHTML = (ICONS[provider.id] || '') + '<span></span>'
          social.querySelector('span').textContent = label
          social.title = 'Authorize your ' + provider.label + ' account'
          social.onclick = function () {
            social.disabled = true
            post('/socialStart/' + provider.id, context()).then(function (data) {
              location.assign(data.authorize_url)
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

  function callback() {
    if (location.pathname.toLowerCase().indexOf('login.html') < 0) return
    var params = new URLSearchParams(location.hash.slice(1))
    var ticket = params.get('oauth_ticket')
    var error = params.get('oauth_error')
    if (!ticket && !error) return
    history.replaceState(null, '', location.pathname + location.search)
    if (error) { message('Authorization was cancelled or expired. Please try again.'); return }
    post('/socialExchange', { ticket: ticket }).then(afterLogin).catch(function (failure) { message(failure.message) })
  }

  function ready() {
    initialize()
    callback()
    new MutationObserver(function () {
      if (mountTimer) return
      mountTimer = setTimeout(function () { mountTimer = null; initialize() }, 60)
    }).observe(document.body, { childList: true, subtree: true })
    document.addEventListener('turbo:load', initialize)
    document.addEventListener('turbo:render', initialize)
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready)
  else ready()
})()
