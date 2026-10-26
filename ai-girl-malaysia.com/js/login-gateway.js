(function () {
  'use strict'
  if (window.__liveLoginGatewayLoaded) return
  window.__liveLoginGatewayLoaded = true
  var options = null
  var pending = null
  var mountTimer = null
  var cooldownUntil = 0
  var API = '/api/live'

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
      data.reg_px = localStorage.getItem('promo_px') || ''
      data.reg_link_name = localStorage.getItem('promo_name') || ''
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
      '.live-social-row button{background:#292929;border:1px solid #444;color:#eee;font-size:13px;padding:10px 4px}' +
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
      var row = document.createElement('div')
      row.className = 'live-social-row'
      options.providers.forEach(function (provider) {
        var social = document.createElement('button')
        social.type = 'button'
        social.disabled = !provider.enabled
        social.textContent = provider.label
        social.title = provider.enabled ? 'Authorize your ' + provider.label + ' account' : 'Coming soon'
        if (!provider.enabled) {
          var note = document.createElement('small')
          note.textContent = 'Coming soon'
          social.appendChild(note)
        }
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
