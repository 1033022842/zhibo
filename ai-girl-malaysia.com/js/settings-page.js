/**
 * setting.html（新版设置页）功能适配
 *
 * 存档页原本由 candy.ai 服务端渲染、并把各种表单提交到 /current_user、/password_change、
 * /delete_user、/user_locale/edit 等端点。这里是静态克隆，那些端点并不存在（点了只会 404），
 * 所以统一改接到站点自己的接口：
 *
 *   回填资料   GET  /api/live/profile + POST /api/live/userInfo
 *   昵称/性别  PUT  /api/live/updateProfile
 *   改密码     POST /api/live/changePassword
 *   改邮箱     POST /api/live/changeEmail
 *   删除账户   POST /api/live/deleteAccount
 *   退出登录   POST /api/live/logout
 *   聊天定制   GET  /api/live/userSettings + POST /api/live/saveUserSettings
 *   我的人格   GET  /api/live/userPersona + POST /api/live/saveUserPersona
 *   订阅管理   GET  /api/live/subscription + POST /api/live/cancelSubscription
 *   支付方式   GET  /api/live/paymentOptions（公开；渲染真实可用渠道，不做假卡）
 *   语言切换   写 googtrans cookie + Google 翻译（与 shell-new.js 的语言菜单同一套机制）
 *
 * 注意 1：这台服务器上带 "-" 的路由匹配不到（/api/live/update-profile 会 404），
 *         所以接口路径全部用驼峰，写 PUT 时用 POST + _method=PUT 更稳妥。
 * 注意 2：未登录时不动个人信息卡里的存档快照，只有在登录后才覆盖成真实资料。
 */
(function () {
  'use strict'

  var API = {
    userInfo:       '/api/live/userInfo',
    profile:        '/api/live/profile',
    updateProfile:  '/api/live/updateProfile',
    changePassword: '/api/live/changePassword',
    changeEmail:    '/api/live/changeEmail',
    deleteAccount:  '/api/live/deleteAccount',
    userSettings:   '/api/live/userSettings',
    saveUserSettings: '/api/live/saveUserSettings',
    userPersona:    '/api/live/userPersona',
    saveUserPersona: '/api/live/saveUserPersona',
    subscription:   '/api/live/subscription',
    cancelSubscription: '/api/live/cancelSubscription',
    paymentOptions: '/api/live/paymentOptions',
    achievements:   '/api/live/achievements',
    vipStatus:      '/api/vip/status',
    logout:         '/api/live/logout'
  }

  // lp_user_profile.gender：0=未知 / 1=男 / 2=女
  var GENDER_TEXT = { 1: 'Men', 2: 'Women', 0: '' }
  var GENDER_CODE = { male: 1, female: 2 }

  /* ---------------- 小工具 ---------------- */

  function token() {
    if (window.LiveAuth && LiveAuth.token) return LiveAuth.token()
    try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' }
  }

  function readJson(key) {
    try { return JSON.parse(localStorage.getItem(key) || 'null') } catch (e) { return null }
  }

  function writeJson(key, obj) {
    try { localStorage.setItem(key, JSON.stringify(obj)) } catch (e) { /* 隐私模式忽略 */ }
  }

  function toast(msg, ok) {
    if (window.layer && layer.msg) { layer.msg(msg); return }
    var el = document.createElement('div')
    el.textContent = msg
    el.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);padding:10px 20px;' +
      'border-radius:8px;font-size:14px;font-weight:600;z-index:99999;color:#fff;background:' +
      (ok === false ? '#ef4444' : '#10b981')
    document.body.appendChild(el)
    setTimeout(function () { el.parentNode.removeChild(el) }, 2600)
  }

  function loading(on) {
    if (!window.layer) return
    if (on) { if (!loading.id) loading.id = layer.load(2) }
    else if (loading.id) { layer.close(loading.id); loading.id = 0 }
  }

  /** 带登录态的 JSON 请求；PUT/DELETE 用 POST + _method 伪装，避免个别代理丢方法 */
  function api(url, data, method) {
    var m = (method || 'POST').toUpperCase()
    var target = m === 'POST' ? url : url + '?_method=' + m
    return fetch(target, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', token: token() },
      body: JSON.stringify(data || {})
    }).then(function (r) { return r.json() }).then(function (res) {
      if (res && (res.code === '00000' || res.code === 1 || res.code === '1')) {
        return { ok: true, data: res.data, msg: res.msg }
      }
      return { ok: false, msg: (res && res.msg) || 'Request failed', code: res && res.code }
    })
  }

  function needLogin() {
    if (token()) return false
    toast('Please sign in first', false)
    setTimeout(function () { location.href = './Login.html?redirect=' + encodeURIComponent('./setting.html') }, 800)
    return true
  }

  /** 关闭弹窗：存档页里点 .toggle-popup 是"切换 .{名字} 上的 hidden"，这里直接置为隐藏 */
  function closeModal(name) {
    var els = document.querySelectorAll('.' + name)
    for (var i = 0; i < els.length; i++) els[i].classList.add('hidden')
  }

  /** Save 按钮带着存档页的 loading-button-state 控制器，提交后会锁在 submitting 状态；
      提交已被我们接管，Turbo 不会再回调，所以每次拿到结果都手动复位 */
  function resetButton(form) {
    if (!form) return
    var btns = form.querySelectorAll('button')
    for (var i = 0; i < btns.length; i++) {
      btns[i].setAttribute('data-loading-button-state-is-submitting-value', 'false')
      btns[i].classList.remove('opacity-50', 'cursor-not-allowed')
      btns[i].removeAttribute('disabled')
    }
  }

  /* ---------------- 个人信息卡 ---------------- */

  /** 按标签文字定位右侧的值节点（存档页这些值没有 id，只能按标签找） */
  function valueOf(label) {
    var labels = document.querySelectorAll('div.text-neutral-400')
    for (var i = 0; i < labels.length; i++) {
      if ((labels[i].textContent || '').trim() !== label) continue
      var row = labels[i].parentElement                      // 标签 + 编辑按钮
      var box = row && row.parentElement                     // 整块：标签行 + 值
      if (!box) continue
      var vs = box.querySelectorAll('div.text-white.truncate')
      if (vs.length) return vs[vs.length - 1]
      return box.lastElementChild
    }
    return null
  }

  function setValue(label, text) {
    var el = valueOf(label)
    if (el) el.textContent = text || ''
  }

  function avatarEl() {
    var imgs = document.querySelectorAll('img.rounded-full')
    for (var i = 0; i < imgs.length; i++) {
      if ((imgs[i].className || '').indexOf('w-[100px]') >= 0) return imgs[i]
    }
    return imgs[0] || null
  }

  function fillProfile(d) {
    d = d || {}
    if (d.nickname) setValue('Nickname', d.nickname)
    if (d.email) setValue('Email', d.email)
    if (d.hasOwnProperty('gender')) setValue('Gender', GENDER_TEXT[parseInt(d.gender, 10)] || '')

    var el = avatarEl()
    if (el && d.avatar) el.src = d.avatar

    var nick = document.getElementById('user_nickname')
    if (nick && d.nickname) nick.value = d.nickname
    var mail = document.getElementById('user_email')
    if (mail && d.email) mail.value = d.email
    var gender = document.getElementById('user_gender')
    if (gender && d.hasOwnProperty('gender')) {
      gender.value = parseInt(d.gender, 10) === 2 ? 'female' : 'male'
    }
  }

  /** 把改完的结果同步进本地会话，其它页面不用重新登录就能拿到新昵称 */
  function patchSession(patch) {
    ['live_user_info', 'live_user'].forEach(function (k) {
      var u = readJson(k)
      if (!u || typeof u !== 'object') return
      Object.keys(patch).forEach(function (f) { u[f] = patch[f] })
      writeJson(k, u)
    })
  }

  function loadUser() {
    if (!token()) return
    fetch(API.profile, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (!res || res.code !== '00000' || !res.data) return
        fillProfile(res.data)
      })
      .catch(function () { /* 拉取失败保持原样 */ })

    fetch(API.userInfo, { method: 'POST', headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (!res || res.code !== '00000' || !res.data) return
        fillProfile({ nickname: res.data.username, email: res.data.email, avatar: res.data.avatar })
      })
      .catch(function () { /* 同上 */ })
  }

  /* ---------------- 昵称 / 性别 ----------------
     注意：页面里有两个 id="edit_user"（另一个是开关表单），
     所以必须用弹窗容器限定，不能 getElementById。 */

  function bindNickname() {
    var form = document.querySelector('#edit-nickname-form form')
    if (!form) return
    form.addEventListener('submit', function (e) {
      e.preventDefault()
      if (needLogin()) return

      var nickname = (document.getElementById('user_nickname') || {}).value || ''
      nickname = nickname.trim()
      if (!nickname) { toast('Nickname is required', false); return }

      var g = (document.getElementById('user_gender') || {}).value
      var payload = { nickname: nickname, gender: GENDER_CODE[g] || 1 }

      loading(true)
      api(API.updateProfile, payload, 'PUT').then(function (r) {
        loading(false)
        resetButton(form)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Saved', true)
        fillProfile(payload)
        patchSession({ username: nickname, gender: payload.gender })
        closeModal('nickname-modal')
      })
    })
  }

  /* ---------------- 密码 ---------------- */

  function bindPassword() {
    var form = document.querySelector('#password-change-form form')
    if (!form) return
    form.addEventListener('submit', function (e) {
      e.preventDefault()
      if (needLogin()) return

      var oldPwd = (document.getElementById('user_current_password') || {}).value || ''
      var newPwd = (document.getElementById('user_password') || {}).value || ''
      if (!oldPwd) { toast('Old password is required', false); return }
      if (newPwd.length < 6 || newPwd.length > 20) { toast('New password must be 6-20 characters', false); return }

      loading(true)
      api(API.changePassword, { current_password: oldPwd, password: newPwd }).then(function (r) {
        loading(false)
        resetButton(form)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Password updated', true)
        document.getElementById('user_current_password').value = ''
        document.getElementById('user_password').value = ''
        closeModal('password-modal')
      })
    })
  }

  /* ---------------- 邮箱 ----------------
     存档页的邮箱弹窗只有邮箱一个输入框，改登录邮箱不带密码确认不安全，
     这里按页面既有的输入框样式补一个 Current Password 字段（只在点开弹窗后可见）。 */

  function ensureEmailPasswordField() {
    var email = document.getElementById('user_email')
    if (!email || document.getElementById('fy_email_password')) return
    var box = email.parentElement
    if (!box) return
    var wrap = document.createElement('div')
    wrap.className = 'self-stretch flex-col justify-start items-start gap-1.5 flex relative mt-3'
    wrap.innerHTML =
      '<div class="w-5 h-5 absolute top-[13px] left-[10px]">' +
      '<img src="./settings_files/lock-04efd07dd546792702a534536fd638a4bc066c8c3bc6bdb5d5febf85c22f567e.svg">' +
      '</div>' +
      '<input id="fy_email_password" type="password" placeholder="Current Password" required ' +
      'class="w-full self-stretch pl-10 px-3.5 py-2.5 rounded-lg shadow-2xs border border-gray-300 ' +
      'justify-start items-center gap-2 inline-flex text-white bg-stone-900">'
    box.parentElement.appendChild(wrap)
  }

  function bindEmail() {
    var form = document.querySelector('#email-edit-form form')
    if (!form) return
    ensureEmailPasswordField()
    form.addEventListener('submit', function (e) {
      e.preventDefault()
      if (needLogin()) return

      var email = ((document.getElementById('user_email') || {}).value || '').trim()
      var pwd = (document.getElementById('fy_email_password') || {}).value || ''
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { toast('Email is invalid', false); return }
      if (!pwd) { toast('Current password is required', false); return }

      loading(true)
      api(API.changeEmail, { email: email, password: pwd }).then(function (r) {
        loading(false)
        resetButton(form)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Email updated', true)
        setValue('Email', email)
        patchSession({ email: email })
        var pwdEl = document.getElementById('fy_email_password')
        if (pwdEl) pwdEl.value = ''
        closeModal('email-modal')
      })
    })
  }

  /* ---------------- 删除账户 ----------------
     同样有两个 id="delete_account"（第二个才是最终确认表单），取带提交按钮的那个 */

  function deleteForm() {
    var forms = document.querySelectorAll('form#delete_account')
    for (var i = 0; i < forms.length; i++) {
      if (forms[i].querySelector('button[type="submit"]')) return forms[i]
    }
    return forms[forms.length - 1] || null
  }

  function bindDeleteAccount() {
    var form = deleteForm()
    if (!form) return
    form.addEventListener('submit', function (e) {
      e.preventDefault()
      if (needLogin()) return

      var reason = (document.getElementById('reason') || {}).value || ''
      var desc = (document.getElementById('description') || {}).value || ''

      loading(true)
      api(API.deleteAccount, { reason: reason, description: desc }).then(function (r) {
        loading(false)
        resetButton(form)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Account deleted', true)
        if (window.LiveAuth && LiveAuth.clear) LiveAuth.clear()
        setTimeout(function () { location.href = './Login.html' }, 900)
      })
    })
  }

  /* ---------------- 退出登录 ---------------- */

  function logout() {
    loading(true)
    api(API.logout, {}).then(function () {
      loading(false)
      if (window.LiveAuth && LiveAuth.clear) LiveAuth.clear()
      else {
        try {
          localStorage.removeItem('live_access_token')
          localStorage.removeItem('live_refresh_token')
          localStorage.removeItem('live_token_expires_at')
          localStorage.removeItem('live_user_info')
          localStorage.removeItem('live_user')
        } catch (err) { /* 忽略 */ }
      }
      location.href = './Login.html'
    })
  }

  function bindLogout() {
    // 存档页的退出是 POST /users/sign_out；两侧边栏会被 shell-new.js 换掉，这里只是兜底
    document.addEventListener('submit', function (e) {
      var f = e.target
      if (!f || f.tagName !== 'FORM') return
      if ((f.getAttribute('action') || '') !== '/users/sign_out') return
      e.preventDefault()
      logout()
    }, true)
  }

  /* ---------------- 语言切换 ---------------- */

  function currentLang() {
    var m = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/)
    if (!m) return 'en'
    var parts = decodeURIComponent(m[1]).split('/')
    return parts[parts.length - 1] || 'en'
  }

  // 写 googtrans cookie：先清掉 host-only 与各级域上的旧值再写入，避免重复 cookie 盖住新值
  function writeGoogtrans(value) {
    var host = location.hostname
    var domains = ['']
    if (host.indexOf('.') > -1) {
      domains.push(host, '.' + host)
      var parts = host.split('.')
      if (parts.length > 2) domains.push('.' + parts.slice(-2).join('.'))
    }
    var past = 'expires=Thu, 01 Jan 1970 00:00:00 GMT'
    for (var i = 0; i < domains.length; i++) {
      document.cookie = 'googtrans=;path=/;' + (domains[i] ? 'domain=' + domains[i] + ';' : '') + past
    }
    for (var j = 0; j < domains.length; j++) {
      document.cookie = 'googtrans=' + value + ';path=/;' + (domains[j] ? 'domain=' + domains[j] : '')
    }
  }

  // 清掉 URL 上遗留的 #googtrans(...) 哈希：否则 Google 翻译会用它覆盖新 cookie，导致只切换一次生效
  function clearLangHash() {
    if (!/googtrans/i.test(location.hash)) return
    try {
      history.replaceState(null, '', location.pathname + location.search)
    } catch (e) { /* 忽略 */ }
  }

  function switchLang(code) {
    writeGoogtrans('/en/' + code)
    clearLangHash()
    try { localStorage.setItem('agigpt-lang', location.href.split('#')[0]) } catch (e) { /* 忽略 */ }
    try { localStorage.setItem('agigpt-lang-href', '#googtrans(en|' + code + ')') } catch (e) { /* 忽略 */ }
    location.reload()
  }

  function bindLanguage() {
    document.addEventListener('submit', function (e) {
      var f = e.target
      if (!f || f.tagName !== 'FORM') return
      if ((f.getAttribute('action') || '') !== '/user_locale/edit') return
      e.preventDefault()
      var input = f.querySelector('input[name="new_locale"]')
      var code = input ? input.value : ''
      if (!code || code === currentLang()) return
      switchLang(code)
    }, true)
  }

  /* ---------------- 全局聊天定制 ----------------
     存档页这部分由 chat-customization 控制器管，保存时提交到 candy 的端点；
     这里改成读页面控件当前值 → 存到站点 /api/live/saveUserSettings，
     而站点聊天（Live::chat）会把这些设置真正落进提示词与 max_tokens。 */

  var PREMIUM_PLUS = false
  var CHAT_SETTINGS = null

  function ccModal() {
    return document.getElementById('chat-customization-global-modal')
  }

  function ccTarget(name) {
    return document.querySelector('[data-chat-customization-target="' + name + '"]')
  }

  function ccTimezoneSelect() {
    var m = ccModal()
    return m ? m.querySelector('[data-action*="lockedTimezoneChange"]') : null
  }

  function closeChatModal() {
    var m = ccModal()
    if (!m) return
    if (typeof m.close === 'function') {
      try { m.close(); return } catch (e) { /* 不是 dialog 时退回加 hidden */ }
    }
    m.classList.add('hidden')
  }

  function applyChatSettings(s) {
    var m = ccModal()
    if (!m || !s) return

    var model = ccTarget('modelInput')
    if (model && s.chat_mode) {
      model.value = s.chat_mode
      var card = m.querySelector('[data-chat-customization-target="modelCard"][data-model-key="' + s.chat_mode + '"]')
      // 存档页点卡片会顺带刷新选中态；被锁的卡片点了会弹兜售，跳过
      if (card && card.dataset.locked !== 'true') card.click()
    }

    var len = ccTarget('lengthInput')
    if (len && s.reply_length !== undefined) {
      len.value = String(s.reply_length)
      len.dispatchEvent(new Event('input', { bubbles: true }))
    }

    var nar = ccTarget('narrationInput')
    if (nar) {
      var want = Number(s.narration) === 1
      if (nar.checked !== want) nar.click()
    }

    var tz = ccTimezoneSelect()
    if (tz && s.timezone) {
      // 不派发 change：存档页的 change 处理是 Premium+ 的兜售逻辑，会把值强行拉回 off
      tz.value = s.timezone
    }
  }

  function loadChatSettings() {
    if (!token()) return
    fetch(API.userSettings, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (res && res.code === '00000' && res.data) {
          CHAT_SETTINGS = res.data
          applyChatSettings(res.data)
        }
      })
      .catch(function () { /* 拉取失败保持存档默认 */ })
  }

  /** 打开弹窗时补一次回填：chat-customization 控制器 connect 时会把值重置成存档默认 */
  function bindChatOpen() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-toggle-dialog-dialog-outlet="#chat-customization-global-modal"]')
      if (!btn || !CHAT_SETTINGS) return
      setTimeout(function () { applyChatSettings(CHAT_SETTINGS) }, 80)
    }, true)
  }

  function loadVipLevel() {
    if (!token()) return
    fetch(API.vipStatus, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        var d = res && (res.code === '00000' || res.code === 1) ? res.data : null
        PREMIUM_PLUS = !!(d && d.level === 'premium_plus')
      })
      .catch(function () { /* 拉取失败按非会员处理 */ })
  }

  function bindChatSave() {
    // 捕获阶段拦下存档页自己的保存行为，改成存站点
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-action*="chat-customization#save"]')
      if (!btn) return
      e.preventDefault()
      e.stopImmediatePropagation()
      if (needLogin()) return

      var payload = {}
      var model = ccTarget('modelInput')
      if (model) payload.chat_mode = model.value
      var len = ccTarget('lengthInput')
      if (len) payload.reply_length = Number(len.value)
      var nar = ccTarget('narrationInput')
      if (nar) payload.narration = nar.checked ? 1 : 0
      var tz = ccTimezoneSelect()
      if (tz) payload.timezone = tz.value || 'off'

      loading(true)
      api(API.saveUserSettings, payload).then(function (r) {
        loading(false)
        resetButton(btn.parentNode)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Saved', true)
        // 回填服务端生效后的值：非会员选到的 Premium+ 项会被降级
        if (r.data) {
          CHAT_SETTINGS = r.data
          applyChatSettings(r.data)
        }
        closeChatModal()
      })
    }, true)
  }

  function bindTimezonePremium() {
    // 时区感知是 Premium Plus 专属：会员自己改就保留，非会员交给存档页的兜售逻辑
    document.addEventListener('change', function (e) {
      if (!PREMIUM_PLUS) return
      var tz = ccTimezoneSelect()
      if (tz && e.target === tz) e.stopImmediatePropagation()
    }, true)
  }

  /* ---------------- 我的人格（Your personas 弹窗） ---------------- */

  var PERSONA = null

  function personaForm() {
    return document.querySelector('[data-user-personas-target="form"]')
  }

  function personaField(f, field) {
    return f.querySelector('.persona-field[data-field="' + field + '"]')
  }

  function fire(el) {
    el.dispatchEvent(new Event('input', { bubbles: true }))
    el.dispatchEvent(new Event('change', { bubbles: true }))
  }

  function closePersonaModal() {
    var m = document.getElementById('user-personas-modal-new')
    if (!m) return
    if (typeof m.close === 'function') {
      try { m.close(); return } catch (e) { /* 不是 dialog 时退回加 hidden */ }
    }
    m.classList.add('hidden')
  }

  /** 读表单 -> 接口入参（多选字段的隐藏空值会被过滤掉） */
  function collectPersona() {
    var f = personaForm()
    if (!f) return null

    var out = {
      name: '', age_bucket: '', gender: '', location: '', occupation: '',
      height_cm: 0, body_type: '', ethnicity: '', hair_color: '', hair_length: '',
      genders_liked: [], looking_for: [], hobby_types: [], active: 1
    }

    new FormData(f).forEach(function (value, key) {
      var m = /^user_persona\[([a-z_]+)\](\[\])?$/.exec(key)
      if (!m) return
      var field = m[1]
      var text = String(value)

      if (m[2]) {
        if (text !== '' && out[field].indexOf(text) < 0) out[field].push(text)
      } else if (field === 'height_cm') {
        out.height_cm = parseInt(text, 10) || 0
      } else if (field === 'active') {
        out.active = (text === 'true' || text === '1') ? 1 : 0
      } else {
        out[field] = text
      }
    })

    return out
  }

  /** 下拉字段（年龄 / 所在地）：点中同值 option，由存档页的 persona-dropdown 控制器回填 */
  function setPersonaDropdown(f, field, value) {
    if (!value) return
    var box = personaField(f, field)
    if (!box) return
    var options = box.querySelectorAll('[data-persona-dropdown-target="option"]')
    for (var i = 0; i < options.length; i++) {
      if (options[i].dataset.value === String(value)) { options[i].click(); return }
    }
  }

  /**
   * 同步胶囊的选中视觉态。
   *
   * 不用 label.click() 驱动：存档页 label 上挂着 pill-select#select，
   * 它与浏览器对 label 的原生激活行为会各切一次 checkbox，互相抵消（时序还不稳定），
   * 单选能歪打正着、多选就会全部回填失败。所以这里直接写 checked，再手工对齐样式。
   */
  function syncPills(box, selector) {
    var list = box.querySelectorAll(selector)
    for (var i = 0; i < list.length; i++) {
      var el = list[i]
      var label = el.id ? document.querySelector('label[for="' + el.id + '"]') : null
      if (!label) continue
      label.setAttribute('data-selected', el.checked ? 'true' : 'false')
      label.classList.toggle('border-white', el.checked)
      label.classList.toggle('border-[rgba(255,255,255,0.15)]', !el.checked)
    }
  }

  /** 单值胶囊字段：写 checked + 同步视觉 */
  function setPersonaRadio(f, field, value) {
    if (!value) return
    var box = personaField(f, field)
    if (!box) return
    var selector = 'input[type="radio"][name="user_persona[' + field + ']"]'
    var inputs = box.querySelectorAll(selector)
    var hit = null
    for (var i = 0; i < inputs.length; i++) {
      if (inputs[i].value !== String(value)) continue
      inputs[i].checked = true
      hit = inputs[i]
      break
    }
    if (!hit) return
    syncPills(box, selector)
    fire(hit)
  }

  /** 多值胶囊字段：按选中集合逐项对齐勾选状态 */
  function setPersonaChecks(f, field, values) {
    var box = personaField(f, field)
    if (!box) return
    var wanted = (values || []).map(String)
    var selector = 'input[type="checkbox"][name="user_persona[' + field + '][]"]'
    var inputs = box.querySelectorAll(selector)
    var first = null
    for (var i = 0; i < inputs.length; i++) {
      inputs[i].checked = wanted.indexOf(inputs[i].value) >= 0
      if (!first) first = inputs[i]
    }
    syncPills(box, selector)
    if (first) fire(first)
  }

  function ftIn(cm) {
    var inches = Math.round(Number(cm) / 2.54)
    return Math.floor(inches / 12) + "'" + (inches % 12) + '"'
  }

  function setPersonaHeight(f, cm) {
    cm = parseInt(cm, 10) || 0
    if (cm <= 0) return
    var box = personaField(f, 'height_cm')
    if (!box) return
    var slider = box.querySelector('[data-persona-height-target="slider"]')
    if (slider) {
      slider.value = String(cm)
      fire(slider)
    }
    // 存档页的滑杆处理挂在 data-action 上（默认 click 事件），补一次兜底
    var hidden = box.querySelector('[data-persona-height-target="input"]')
    if (hidden && String(hidden.value) !== String(cm)) hidden.value = String(cm)
    var display = box.querySelector('[data-persona-height-target="display"]')
    if (display) display.textContent = ftIn(cm)
  }

  function applyPersona(p) {
    var f = personaForm()
    if (!f || !p) return

    var name = f.querySelector('#persona-new-name')
    if (name) { name.value = p.name || ''; fire(name) }

    // 种族（Premium+ 专属，非会员是 disabled 输入框；disabled 不参与 FormData，所以不会误清）
    var ethnicity = f.querySelector('#persona-new-ethnicity')
    if (ethnicity && !ethnicity.disabled) { ethnicity.value = p.ethnicity || ''; fire(ethnicity) }

    setPersonaDropdown(f, 'age_bucket', p.age_bucket)
    setPersonaDropdown(f, 'location', p.location)

    ;['gender', 'occupation', 'body_type', 'hair_color', 'hair_length'].forEach(function (field) {
      setPersonaRadio(f, field, p[field])
    })
    ;['genders_liked', 'looking_for', 'hobby_types'].forEach(function (field) {
      setPersonaChecks(f, field, p[field])
    })

    setPersonaHeight(f, p.height_cm)
  }

  /** 「Your personas」区块：有存档时在「Create your Persona」上方插一张可点击的卡片 */
  function renderPersonaCard(p) {
    var wrap = document.querySelector('[data-controller="persona-list"]')
    if (!wrap) return
    var card = wrap.querySelector('[data-fy-persona-card]')

    if (!p || !p.name) {
      if (card) card.parentNode.removeChild(card)
      return
    }

    if (!card) {
      card = document.createElement('button')
      card.type = 'button'
      card.setAttribute('data-fy-persona-card', '')
      card.setAttribute('data-controller', 'toggle-dialog')
      card.setAttribute('data-toggle-dialog-dialog-outlet', '#user-personas-modal-new')
      card.setAttribute('data-action', 'click->toggle-dialog#open')
      card.className = 'mb-3 flex w-full items-center gap-3 rounded-[12px] border border-white/15 p-4 text-left text-sm font-semibold text-white hover:bg-white/5'
      card.innerHTML = '<span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-gradient-to-r from-[#a855f7] to-[#f97187] text-lg font-semibold text-white"></span>'
        + '<span class="min-w-0 flex-1 truncate"></span>'
        + '<span class="shrink-0 text-xs font-medium text-white/40">Edit</span>'
      wrap.insertBefore(card, wrap.firstChild)
    }

    card.children[0].textContent = String(p.name).charAt(0).toUpperCase()
    card.children[1].textContent = p.name
  }

  function loadPersona() {
    if (!token()) return
    fetch(API.userPersona, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (res && res.code === '00000' && res.data) {
          PERSONA = res.data
          applyPersona(res.data)
          renderPersonaCard(res.data)
        }
      })
      .catch(function () { /* 拉取失败保持存档默认 */ })
  }

  function bindPersonaSave() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-action*="user-personas#save"]')
      if (!btn) return
      e.preventDefault()
      e.stopImmediatePropagation()
      if (needLogin()) return

      var payload = collectPersona()
      if (!payload) return
      if (!payload.name) { toast('Please enter your name', false); return }

      loading(true)
      api(API.saveUserPersona, payload).then(function (r) {
        loading(false)
        resetButton(btn.parentNode)
        if (!r.ok) { toast(r.msg, false); return }
        toast('Saved', true)
        PERSONA = r.data
        if (r.data) { applyPersona(r.data); renderPersonaCard(r.data) }
        closePersonaModal()
      })
    }, true)
  }

  /** 打开弹窗时补一次回填：user-personas 控制器 connect 时会把值重置成存档默认 */
  function bindPersonaOpen() {
    document.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-toggle-dialog-dialog-outlet="#user-personas-modal-new"]')
      if (!btn || !PERSONA) return
      setTimeout(function () { applyPersona(PERSONA) }, 80)
    }, true)
  }

  /* ---------------- 订阅管理（Current plan / 取消订阅） ---------------- */

  var SUBS = null
  var MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
    'July', 'August', 'September', 'October', 'November', 'December']

  /** 归档页用的日期格式 DD.MM.YYYY */
  function dmy(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s || ''))
    return m ? (m[3] + '.' + m[2] + '.' + m[1]) : ''
  }

  /** 取消弹窗文案里用的长日期格式 October 03, 2026 */
  function longDate(s) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s || ''))
    return m ? (MONTHS[parseInt(m[2], 10) - 1] + ' ' + m[3] + ', ' + m[1]) : ''
  }

  /** 「标签 → 值」这种两格布局：按标签文字找，改它下一个兄弟节点 */
  function setAfterLabel(labelText, value) {
    var divs = document.querySelectorAll('div')
    var label = labelText.toLowerCase()
    for (var i = 0; i < divs.length; i++) {
      if ((divs[i].textContent || '').trim().toLowerCase() !== label) continue
      var next = divs[i].nextElementSibling
      if (!next) continue
      next.textContent = value
      return true
    }
    return false
  }

  function unsubscribeFlow() {
    return document.getElementById('unsubscribe-flow')
  }

  function applySubscription(s) {
    if (!s) return

    setAfterLabel('Current plan', s.level_name || 'Free')
    setAfterLabel('Payment date:', dmy(s.paid_at) || '—')

    // 已取消续订、或本来就没有生效订阅时：不显示下一次续订日期，并收起取消入口
    var cancelled = s.auto_renew === false
    setAfterLabel('Subscription renewal:', cancelled ? '—' : (dmy(s.expire_at) || '—'))

    var flow = unsubscribeFlow()
    if (flow) flow.classList.toggle('hidden', !s.is_vip || cancelled)

    // 取消弹窗里的兜底文案：把存档页那天的日期换成真实到期日
    var expire = longDate(s.expire_at)
    if (expire) {
      var prompt = document.querySelector('#unsubscribe-flow [data-unsubscribe-flow-target="prompt"] p')
      if (prompt) {
        prompt.textContent = "This won't affect your current subscription. "
          + "You'll still have access until " + expire + '.'
      }
    }
  }

  function closeUnsubscribe() {
    var modal = document.querySelector('[data-unsubscribe-flow-target="cancelModal"]')
    if (modal) modal.classList.add('hidden')
  }

  function loadSubscription() {
    if (!token()) return
    fetch(API.subscription, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        if (res && res.code === '00000' && res.data) {
          SUBS = res.data
          applySubscription(res.data)
        }
      })
      .catch(function () { /* 拉取失败保持存档默认 */ })
  }

  function bindCancelSubscription() {
    // 拦截存档页「Confirm unsubscribe」那一步（原始 action 是 POST /cancel_subscription，本站没有这个端点）
    document.addEventListener('submit', function (e) {
      var form = e.target
      if (!form || form.getAttribute('action') !== '/cancel_subscription') return
      e.preventDefault()
      e.stopImmediatePropagation()
      if (needLogin()) return

      var reason = ''
      var radios = document.querySelectorAll('#cancel-subscription-form input[name="feedback[reason]"]')
      for (var i = 0; i < radios.length; i++) {
        if (radios[i].checked) { reason = radios[i].value; break }
      }
      var descEl = document.getElementById('feedback_description')

      loading(true)
      api(API.cancelSubscription, { reason: reason, description: descEl ? descEl.value : '' })
        .then(function (r) {
          loading(false)
          if (!r.ok) { toast(r.msg, false); return }
          SUBS = r.data
          applySubscription(r.data)
          closeUnsubscribe()
          toast('Subscription cancelled', true)
        })
    }, true)
  }

  /* ---------------- 支付方式（Payment methods 区块） ---------------- */

  /* 站点没有卡 token 化能力，这里只如实列出可用的支付方式并给入口。
     图标沿用站点已有的资源，未知渠道退回首枚金币图标。 */
  var PAYMENT_ICON_DEFAULT = './settings_files/token-bd6936abf2035ff2e4304ca9c5d68b249ff8caeda952c11fd85ff70658419690.svg'
  var PAYMENT_ICONS = {
    card: './settings_files/mastercard-ffed11dee9eb67d50b5c8cf60a4ae891032a34d74f23d2a9987a3e36c0e918fe.svg',
    eth: './tokens_files/eth-0f77f739edf37b7fc60d467b5fb8317f4467f3bfce952c865551f5d8f4953124.svg',
    usdt_trc20: './assets/USDT-TRC20.png',
    usdt_erc20: './assets/USDT-ERC20.png'
  }

  function paymentRow(option) {
    var row = document.createElement('div')
    row.className = 'flex w-full flex-wrap items-center gap-2.5 py-3.5 sm:gap-3.5'

    var left = document.createElement('div')
    left.className = 'flex min-w-0 grow basis-0 items-center gap-3 sm:basis-44 sm:gap-3.5'

    var iconBox = document.createElement('span')
    iconBox.className = 'flex h-7 w-11 shrink-0 items-center justify-center rounded-[5px] bg-white'
    var icon = document.createElement('img')
    icon.className = 'h-5 w-[33px] object-contain'
    icon.alt = ''
    icon.src = PAYMENT_ICONS[option.key] || PAYMENT_ICON_DEFAULT
    iconBox.appendChild(icon)

    var textBox = document.createElement('span')
    textBox.className = 'min-w-0 flex-1'
    var name = document.createElement('span')
    name.className = 'block min-w-0 truncate text-sm font-semibold text-white'
    name.textContent = option.name || option.key || ''
    var desc = document.createElement('span')
    desc.className = 'mt-0.5 block truncate text-xs text-grey-default'
    desc.textContent = option.desc || ''
    textBox.appendChild(name)
    textBox.appendChild(desc)

    left.appendChild(iconBox)
    left.appendChild(textBox)

    var right = document.createElement('div')
    right.className = 'ml-auto flex shrink-0 items-center justify-end gap-1 sm:gap-2'
    var use = document.createElement('a')
    use.className = 'shrink-0 rounded-lg border border-grey-control px-3 py-2 text-xs font-medium text-white transition-colors hover:border-grey-default'
    use.href = option.url || './purchasePoints.html'
    use.textContent = 'Use'
    right.appendChild(use)

    row.appendChild(left)
    row.appendChild(right)
    return row
  }

  function loadPaymentOptions() {
    var box = document.getElementById('fy-payment-methods')
    if (!box) return

    fetch(API.paymentOptions)
      .then(function (r) { return r.json() })
      .then(function (res) {
        var list = res && res.code === '00000' && res.data ? res.data : []
        box.innerHTML = ''
        list.forEach(function (option) { box.appendChild(paymentRow(option)) })
      })
      .catch(function () { /* 拉取失败保持空列表 */ })
  }

  /* Achievements 卡：存档页是抓取账号的假进度，这里换成真实数据 */
  function loadAchievementsSummary() {
    var el = document.getElementById('fy-ach-summary')
    if (!el) return

    fetch(API.achievements, { headers: { token: token() } })
      .then(function (r) { return r.json() })
      .then(function (res) {
        var d = res && res.code === '00000' && res.data ? res.data : null
        if (!d || !d.total) return
        el.textContent = d.unlocked + ' unlocked of ' + d.total + ' · ' + d.points + ' pts'
      })
      .catch(function () { /* 拉取失败保留原文案 */ })
  }

  /* ---------------- 启动 ---------------- */

  function init() {
    bindNickname()
    bindPassword()
    bindEmail()
    bindDeleteAccount()
    bindLogout()
    bindLanguage()
    bindChatSave()
    bindChatOpen()
    bindTimezonePremium()
    bindPersonaSave()
    bindPersonaOpen()
    bindCancelSubscription()
    loadUser()
    loadChatSettings()
    loadVipLevel()
    loadPersona()
    loadSubscription()
    loadPaymentOptions()
    loadAchievementsSummary()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init)
  } else {
    init()
  }
})()
