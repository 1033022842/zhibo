/**
 * 全站追踪与客服（各页面 <head> 引入）
 *
 * 1) 像素：从后台「营销推广」拉配置，动态注入多条像素（Meta / TikTok / Google / 自定义代码，条数不限）
 *    每条像素可单独设置要接收的事件、生效的推广域名、以及测试回调编码
 * 2) 转化事件：window.SugusTrack.event('register'|'recharge'|'subscribe', {value, currency})
 * 3) 客服：右下角悬浮按钮 + 统一客服弹窗；页面里原有的 Contact Us 入口改为打开同一个弹窗
 * 4) 渠道点击会回写计数（后台「客服渠道」列表可看）
 *
 * 配置来源：GET /api/v1/site/config（后台保存后 60 秒内生效，无需重新发版）
 * 全部逻辑都做了降级：拿不到配置、脚本被拦截、接口报错都不影响页面本身。
 */
(function () {
  'use strict'

  var CFG_URL = '/api/v1/site/config'
  var CLICK_URL = '/api/v1/site/csClick'
  var CACHE_KEY = 'sugus.site.cfg.v2'
  var CACHE_TTL = 60000

  var cfg = null

  /* ---------------- 配置读取（带 60s 会话缓存，减少后台压力） ---------------- */

  function readCache() {
    try {
      var raw = sessionStorage.getItem(CACHE_KEY)
      if (!raw) return null
      var o = JSON.parse(raw)
      if (!o || !o.t || Date.now() - o.t > CACHE_TTL) return null
      return o.d
    } catch (e) { return null }
  }

  function writeCache(data) {
    try { sessionStorage.setItem(CACHE_KEY, JSON.stringify({ t: Date.now(), d: data })) } catch (e) { /* 隐私模式忽略 */ }
  }

  function start() {
    var cached = readCache()
    if (cached) { boot(cached); return }
    var url = CFG_URL + '?t=' + Math.floor(Date.now() / CACHE_TTL)
    var req
    try { req = fetch(url, { credentials: 'omit' }) } catch (e) { return }
    req.then(function (r) { return r.json() })
      .then(function (d) {
        if (!d || d.code !== '00000' || !d.data) return
        writeCache(d.data)
        boot(d.data)
      })
      .catch(function () { /* 接口不可用则整站保持原样 */ })
  }

  function boot(data) {
    cfg = data || {}
    try { injectPixels(cfg.pixels || {}) } catch (e) { /* 统计脚本失败不影响页面 */ }
    onReady(function () { try { initCs(cfg.cs || {}) } catch (e) { /* 客服失败不影响页面 */ } })
  }

  function onReady(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn)
    else fn()
  }

  /* ---------------- 像素注入（支持多条像素，每条有自己的渠道 / 事件 / 域名 / 测试编码） ---------------- */

  // 各渠道在广告后台对应的事件名（与后端 PromoService::PLATFORM_EVENTS 保持一致）
  var PLATFORM_EVENTS = {
    facebook: { register: 'CompleteRegistration', recharge: 'Purchase',        subscribe: 'Subscribe',       login: 'Lead' },
    tiktok:   { register: 'CompleteRegistration', recharge: 'CompletePayment', subscribe: 'Subscribe',       login: 'Login' },
    google:   { register: 'sign_up',              recharge: 'purchase',        subscribe: 'subscribe',       login: 'login' }
  }

  var ALL_EVENTS = ['register', 'recharge', 'subscribe']

  // 本次页面实际启用的像素：[{ platform, events, test }]
  var targets = []
  var fbReady = false
  var ttReady = false
  var gtagReady = false

  function addScript(src, async) {
    var s = document.createElement('script')
    s.async = async !== false
    s.src = src
    document.head.appendChild(s)
    return s
  }

  /** 推广域名过滤：留空 = 全部域名；支持 a.com 与 *.a.com */
  function domainMatched(domains) {
    if (!domains || !domains.length) return true
    var host = (location.hostname || '').toLowerCase()
    for (var i = 0; i < domains.length; i++) {
      var d = String(domains[i] || '').toLowerCase().replace(/^https?:\/\//, '').replace(/[/:].*$/, '')
      if (!d || d === '*') return true
      if (d === host) return true
      if (d.charAt(0) === '*' && host.slice(-(d.length - 1)) === d.slice(1)) return true
    }
    return false
  }

  function ensureFb() {
    if (fbReady) return
    ;(function (f, b, e, v, n, t, s) {
      if (f.fbq) return
      n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments) }
      if (!f._fbq) f._fbq = n
      n.push = n
      n.loaded = true
      n.version = '2.0'
      n.queue = []
      t = b.createElement(e)
      t.async = true
      t.src = v
      s = b.getElementsByTagName(e)[0]
      s.parentNode.insertBefore(t, s)
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js')
    fbReady = true
  }

  function ensureTt() {
    if (ttReady) return
    ;(function (w, d, t) {
      w.TiktokAnalyticsObject = t
      var ttq = w[t] = w[t] || []
      ttq.methods = ['page', 'track', 'identify', 'instances', 'debug', 'on', 'off', 'once', 'ready', 'alias', 'group', 'enableCookie', 'disableCookie', 'holdConsent', 'revokeConsent', 'grantConsent']
      ttq.setAndDefer = function (obj, method) {
        obj[method] = function () { obj.push([method].concat(Array.prototype.slice.call(arguments, 0))) }
      }
      for (var i = 0; i < ttq.methods.length; i++) ttq.setAndDefer(ttq, ttq.methods[i])
      ttq.instance = function (id) {
        var inst = ttq._i[id] || []
        for (var j = 0; j < ttq.methods.length; j++) ttq.setAndDefer(inst, ttq.methods[j])
        return inst
      }
      ttq.load = function (id) {
        var url = 'https://analytics.tiktok.com/i18n/pixel/events.js'
        ttq._i = ttq._i || {}
        ttq._i[id] = []
        ttq._i[id]._u = url
        ttq._t = ttq._t || {}
        ttq._t[id] = +new Date()
        ttq._o = ttq._o || {}
        ttq._o[id] = {}
        var s = d.createElement('script')
        s.type = 'text/javascript'
        s.async = true
        s.src = url + '?sdkid=' + id + '&lib=' + t
        var first = d.getElementsByTagName('script')[0]
        first.parentNode.insertBefore(s, first)
      }
    })(window, document, 'ttq')
    ttReady = true
  }

  /** GA4 / Ads：多个 ID 共用一个 gtag.js 与 dataLayer */
  function ensureGtag(firstId) {
    window.dataLayer = window.dataLayer || []
    window.gtag = window.gtag || function () { window.dataLayer.push(arguments) }
    if (gtagReady) return
    window.gtag('js', new Date())
    if (firstId) addScript('https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(firstId))
    gtagReady = true
  }

  function loadGtm(id) {
    ;(function (w, d, s, l, i) {
      w[l] = w[l] || []
      w[l].push({ 'gtm.start': new Date().getTime(), event: 'gtm.js' })
      var f = d.getElementsByTagName(s)[0]
      var j = d.createElement(s)
      j.async = true
      j.src = 'https://www.googletagmanager.com/gtm.js?id=' + encodeURIComponent(i)
      f.parentNode.insertBefore(j, f)
    })(window, document, 'script', 'dataLayer', id)
  }

  /** 自定义像素代码：原样注入页面；含 <script> 的重新执行一次 */
  function injectCode(code) {
    var html = String(code || '').trim()
    if (!html) return
    var box = document.createElement('div')
    box.style.display = 'none'
    box.innerHTML = html
    var found = box.getElementsByTagName('script')
    var list = []
    for (var i = 0; i < found.length; i++) list.push(found[i])
    for (var j = 0; j < list.length; j++) {
      var old = list[j]
      var s = document.createElement('script')
      for (var k = 0; k < old.attributes.length; k++) {
        s.setAttribute(old.attributes[k].name, old.attributes[k].value)
      }
      s.text = old.text || old.innerHTML || ''
      document.head.appendChild(s)
      old.parentNode.removeChild(old)
    }
    if (box.childNodes.length) document.body.appendChild(box)
  }

  function injectPixels(px) {
    var items = (px && px.items) || []
    targets = []
    fbReady = !!window.fbq
    ttReady = !!window.ttq

    for (var i = 0; i < items.length; i++) {
      var it = items[i] || {}
      if (!domainMatched(it.domains)) continue

      var platform = String(it.platform || 'other')
      var pixel = String(it.pixel || '').trim()
      var code = String(it.code || '').trim()

      // 单条像素出问题不能影响其它像素（脚本被拦截、ID 非法等）
      try {
        if (platform === 'facebook' && pixel) {
          ensureFb()
          window.fbq('init', pixel)
        } else if (platform === 'tiktok' && pixel) {
          ensureTt()
          window.ttq.load(pixel)
        } else if (platform === 'google' && pixel) {
          if (pixel.toUpperCase().indexOf('GTM-') === 0) {
            loadGtm(pixel)
          } else {
            ensureGtag(pixel)
            window.gtag('config', pixel)
          }
        }
        if (code) injectCode(code)
      } catch (e) { /* 该条像素失败，继续处理下一条 */ }

      targets.push({
        platform: platform,
        events: (it.events && it.events.length) ? it.events : ALL_EVENTS,
        test: String(it.test || '').trim()
      })
    }

    if (fbReady) { try { window.fbq('track', 'PageView') } catch (e) {} }
    if (ttReady) { try { window.ttq.page() } catch (e) {} }
  }

  /* ---------------- 转化事件 ---------------- */

  function eventEnabled(name) {
    var ev = (cfg && cfg.pixels && cfg.pixels.events) || {}
    if (name === 'register') return ev.register !== false
    if (name === 'recharge') return ev.recharge !== false
    if (name === 'subscribe') return ev.subscribe !== false
    return true
  }

  /**
   * 上报转化事件：只发给「事件选择里勾了这个事件」的像素
   * 带测试回调编码的像素会把测试标记一起发出去，方便在广告后台「测试事件」面板验证
   */
  function track(name, params) {
    if (!name || !eventEnabled(name)) return
    var p = params || {}
    var payload = {}
    if (p.value != null && isFinite(Number(p.value))) {
      payload.value = Number(p.value)
      payload.currency = p.currency || 'USD'
    }
    for (var i = 0; i < targets.length; i++) {
      var t = targets[i]
      if (t.events.indexOf(name) < 0) continue
      var map = PLATFORM_EVENTS[t.platform]
      if (!map || !map[name]) continue
      var evt = map[name]
      var opt = t.test ? { test_event_code: t.test } : undefined
      try {
        if (t.platform === 'facebook' && window.fbq) window.fbq('track', evt, payload, opt)
        else if (t.platform === 'tiktok' && window.ttq) window.ttq.track(evt, payload, opt)
        else if (t.platform === 'google' && window.gtag) window.gtag('event', evt, payload)
      } catch (e) {}
    }
  }

  window.SugusTrack = { event: track, config: function () { return cfg }, pixels: function () { return targets } }

  /* ---------------- 客服 ---------------- */

  var CS_ICON = {
    telegram: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21.5 3.5 2.8 10.9c-.9.4-.9 1.6.1 1.9l4.3 1.3 1.6 4.8c.3.9 1.5 1 2 .2l2.2-3 4.3 3.2c.7.5 1.7.1 1.9-.8l3-12.6c.2-.9-.7-1.6-1.5-1.4Z"/><path d="m7.2 14.1 10.4-7.4-7.9 8.4"/></svg>',
    whatsapp: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M20.5 11.6a8.5 8.5 0 0 1-12.6 7.5L3.5 20.5l1.4-4.4A8.5 8.5 0 1 1 20.5 11.6Z"/><path d="M9 8.6c.3-.6 1-.5 1.2 0l.6 1.4c.1.3 0 .6-.2.8l-.5.4c.5 1.1 1.4 2 2.5 2.5l.4-.5c.2-.2.5-.3.8-.2l1.4.6c.5.2.6.9 0 1.2-1 .6-2.3.5-3.4-.2a7 7 0 0 1-2.9-2.9c-.6-1.1-.7-2.4.1-3.1Z"/></svg>',
    line: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10.6c0 4.4-4 8-9 8-1 0-2-.1-2.9-.4L4 20.5l1.3-3.5A7.6 7.6 0 0 1 3 10.6c0-4.4 4-8 9-8s9 3.6 9 8Z"/><path d="M8 9v4M8 9l3 3.6V9"/></svg>',
    messenger: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3c4.9 0 8.8 3.4 8.8 7.7 0 4.2-3.9 7.6-8.8 7.6-1 0-2-.1-2.9-.4L5 20v-3.4A7.4 7.4 0 0 1 3.2 10.7C3.2 6.4 7.1 3 12 3Z"/><path d="m8.6 12.4 3-3.6 3 3.6"/></svg>',
    email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><rect x="2.5" y="4.5" width="19" height="15" rx="3"/><path d="m2.8 7.2 9.2 6 9.2-6"/></svg>',
    link: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M10.5 13.5a4 4 0 0 0 5.7 0l2.6-2.6a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M13.5 10.5a4 4 0 0 0-5.7 0l-2.6 2.6a4 4 0 1 0 5.7 5.7l1-1"/></svg>',
    embed: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5c0 4-4 7.2-9 7.2-1 0-2-.1-2.9-.4L4.5 20v-3.2A7 7 0 0 1 3 11.5c0-4 4-7.2 9-7.2s9 3.2 9 7.2Z"/><path d="M8.5 11.5h.01M12 11.5h.01M15.5 11.5h.01"/></svg>'
  }
  var CS_TYPE_LABEL = {
    telegram: 'Telegram', whatsapp: 'WhatsApp', line: 'LINE',
    messenger: 'Messenger', email: 'Email', link: 'Support', embed: 'Live chat'
  }

  var CSS = [
    '.fy-cs-fab{position:fixed;right:20px;bottom:24px;z-index:125;width:56px;height:56px;border:none;border-radius:50%;padding:0;cursor:pointer;background:linear-gradient(268deg,#fc768a,#e65073);box-shadow:0 10px 26px rgba(231,82,117,.42);display:flex;align-items:center;justify-content:center;transition:transform .18s,box-shadow .18s}',
    '.fy-cs-fab:hover{transform:translateY(-2px);box-shadow:0 14px 30px rgba(231,82,117,.55)}',
    '.fy-cs-fab img{width:100%;height:100%;border-radius:50%;object-fit:cover}',
    '.fy-cs-fab svg{width:26px;height:26px;color:#fff}',
    '.fy-cs-fab .fy-cs-dot{position:absolute;top:6px;right:6px;width:10px;height:10px;border-radius:50%;background:#28c76f;border:2px solid #e65073}',
    '.fy-cs-tip{position:fixed;right:86px;bottom:38px;z-index:125;padding:7px 12px;border-radius:10px;background:#18181b;border:1px solid #2f2f34;color:#fff;font-size:12.5px;font-weight:600;white-space:nowrap;opacity:0;pointer-events:none;transform:translateX(6px);transition:opacity .18s,transform .18s}',
    '.fy-cs-fab:hover + .fy-cs-tip{opacity:1;transform:translateX(0)}',
    '.fy-cs-mask{position:fixed;inset:0;z-index:128;background:rgba(0,0,0,.66);-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)}',
    '.fy-cs-panel{position:fixed;z-index:129;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100vw - 32px);max-width:392px;max-height:calc(100vh - 60px);overflow:hidden;border-radius:18px;border:1px solid #2f2f34;background:#0f0f11;box-shadow:0 26px 64px rgba(0,0,0,.62);font-family:Poppins,-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif;display:flex;flex-direction:column}',
    '.fy-cs-head{position:relative;padding:20px 20px 14px;background:linear-gradient(160deg,rgba(231,82,117,.16),rgba(15,15,17,0) 72%)}',
    '.fy-cs-headrow{display:flex;align-items:center;gap:12px}',
    '.fy-cs-avatar{width:46px;height:46px;border-radius:50%;object-fit:cover;flex:0 0 auto;background:#1c1c20;display:flex;align-items:center;justify-content:center}',
    '.fy-cs-avatar svg{width:24px;height:24px;color:#E75275}',
    '.fy-cs-title{font-size:16px;font-weight:800;color:#fff;line-height:1.3}',
    '.fy-cs-sub{font-size:12.5px;color:#a4a4a4;line-height:1.5;margin-top:2px}',
    '.fy-cs-x{position:absolute;top:12px;right:12px;width:32px;height:32px;border:none;border-radius:50%;background:rgba(255,255,255,.06);color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:6px}',
    '.fy-cs-x svg{width:18px;height:18px}',
    '.fy-cs-list{padding:4px 12px 14px;overflow-y:auto}',
    '.fy-cs-item{display:flex;align-items:center;gap:12px;width:100%;padding:12px 12px;border-radius:12px;border:1px solid #232327;background:#16161a;color:#fff;text-decoration:none;cursor:pointer;margin-top:8px;transition:border-color .16s,background .16s}',
    '.fy-cs-item:hover{border-color:rgba(231,82,117,.5);background:#1b1b20}',
    '.fy-cs-ic{width:34px;height:34px;border-radius:10px;background:rgba(231,82,117,.13);display:flex;align-items:center;justify-content:center;flex:0 0 auto}',
    '.fy-cs-ic svg{width:19px;height:19px;color:#f97187}',
    '.fy-cs-ic img{width:20px;height:20px;object-fit:contain}',
    '.fy-cs-name{font-size:14px;font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}',
    '.fy-cs-type{font-size:11px;color:#7d7d86;flex:0 0 auto}',
    '.fy-cs-arrow{color:#6b6b73;font-size:14px;flex:0 0 auto}',
    '.fy-cs-empty{padding:18px 4px;color:#8a8a92;font-size:13px;text-align:center}',
    '.fy-cs-frame{flex:1;min-height:min(70vh,560px);background:#fff}',
    '.fy-cs-frame iframe{width:100%;height:100%;border:0;display:block}',
    '.fy-cs-back{display:inline-flex;align-items:center;gap:6px;margin:10px 14px 12px;padding:7px 12px;border-radius:9px;border:1px solid #2f2f34;background:#16161a;color:#cfcfd6;font-size:12.5px;cursor:pointer}',
    '@media (max-width:1023px){.fy-cs-fab{right:16px;bottom:86px}}',
    '@media (max-height:640px){.fy-cs-panel{top:auto;bottom:0;transform:translateX(-50%);border-radius:18px 18px 0 0;max-height:88vh}}'
  ].join('')

  var csState = { items: [], panel: null }

  function initCs(cs) {
    if (!cs || cs.enabled === false) return
    csState.items = Array.isArray(cs.items) ? cs.items : []
    if (!csState.items.length) return // 没配渠道就当没启用，不打扰用户

    style()

    if (cs.float !== false) fab(cs)
    intercept()
  }

  function style() {
    if (document.getElementById('fyCsStyle')) return
    var s = document.createElement('style')
    s.id = 'fyCsStyle'
    s.textContent = CSS
    document.head.appendChild(s)
  }

  function avatarHtml(logo) {
    if (logo) return '<img src="' + esc(logo) + '" alt="">'
    return CS_ICON.embed
  }

  function fab(cs) {
    var btn = document.createElement('button')
    btn.type = 'button'
    btn.id = 'fyCsFab'
    btn.className = 'fy-cs-fab'
    btn.setAttribute('aria-label', cs.title || 'Contact us')
    btn.innerHTML = avatarHtml(cs.logo) + '<span class="fy-cs-dot"></span>'
    btn.addEventListener('click', function () { open(cs) })
    document.body.appendChild(btn)

    var hint = (cs.hint || '').trim()
    if (hint) {
      var tip = document.createElement('div')
      tip.className = 'fy-cs-tip'
      tip.textContent = hint
      document.body.appendChild(tip)
    }
  }

  /* 页面里原有的 Contact Us 入口（侧边栏/抽屉 data-contact-us）改开客服弹窗。
     必须挂在 window 的捕获阶段：各页面自己还留着一份「打开自带 contactUsModalContainer」的
     document 捕获监听，document 上的监听按注册顺序都会执行、stopPropagation 拦不住它，
     会出现两个客服弹窗同时弹出、互相重叠。window 捕获早于 document，
     这里 stopPropagation 后页面那份监听（以及元素自身的监听）都不会再触发。 */
  function intercept() {
    window.addEventListener('click', function (e) {
      var el = e.target && e.target.closest ? e.target.closest('[data-contact-us]') : null
      if (!el) return
      e.preventDefault()
      e.stopPropagation()
      var cs = (cfg && cfg.cs) || {}
      open(cs)
    }, true)
  }

  function open(cs) {
    if (csState.panel) { return }
    style()

    var mask = document.createElement('div')
    mask.className = 'fy-cs-mask'
    mask.addEventListener('click', close)

    var panel = document.createElement('div')
    panel.className = 'fy-cs-panel'
    panel.setAttribute('role', 'dialog')
    panel.innerHTML =
      '<div class="fy-cs-head">' +
        '<button type="button" class="fy-cs-x" aria-label="Close">' +
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
        '</button>' +
        '<div class="fy-cs-headrow">' +
          '<div class="fy-cs-avatar">' + avatarHtml(cs.logo) + '</div>' +
          '<div><div class="fy-cs-title">' + esc(cs.title || 'Contact us') + '</div>' +
          '<div class="fy-cs-sub">' + esc(cs.subtitle || '') + '</div></div>' +
        '</div>' +
      '</div>' +
      '<div class="fy-cs-list">' + listHtml() + '</div>'

    panel.querySelector('.fy-cs-x').addEventListener('click', close)
    panel.querySelector('.fy-cs-list').addEventListener('click', function (e) {
      var item = e.target.closest ? e.target.closest('.fy-cs-item') : null
      if (!item) return
      e.preventDefault()
      hit(Number(item.getAttribute('data-id')) || 0)
      var url = item.getAttribute('data-url') || ''
      var type = item.getAttribute('data-type') || 'link'
      if (type === 'embed') { showFrame(panel, url); return }
      if (type === 'email') { location.href = 'mailto:' + url.replace(/^mailto:/i, ''); close(); return }
      window.open(url, '_blank', 'noopener')
      close()
    })

    document.body.appendChild(mask)
    document.body.appendChild(panel)
    csState.panel = { mask: mask, panel: panel }
    document.addEventListener('keydown', onKey)
  }

  function listHtml() {
    if (!csState.items.length) return '<div class="fy-cs-empty">No channel configured yet.</div>'
    return csState.items.map(function (it) {
      var type = it.type || 'link'
      var icon = it.icon
        ? '<img src="' + esc(it.icon) + '" alt="">'
        : (CS_ICON[type] || CS_ICON.link)
      return '<a class="fy-cs-item" href="' + esc(it.url || '#') + '" data-id="' + (it.id || 0) + '"' +
        ' data-url="' + esc(it.url || '') + '" data-type="' + esc(type) + '" target="_blank" rel="noopener">' +
          '<span class="fy-cs-ic">' + icon + '</span>' +
          '<span class="fy-cs-name">' + esc(it.name || CS_TYPE_LABEL[type] || 'Support') + '</span>' +
          '<span class="fy-cs-type">' + esc(CS_TYPE_LABEL[type] || '') + '</span>' +
          '<span class="fy-cs-arrow">&rsaquo;</span>' +
        '</a>'
    }).join('')
  }

  /* 嵌入型客服（tawk.to 等）：在弹窗里用 iframe 打开 */
  function showFrame(panel, url) {
    if (!url) return
    panel.innerHTML =
      '<button type="button" class="fy-cs-x" aria-label="Close">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>' +
      '</button>' +
      '<button type="button" class="fy-cs-back">&lsaquo; Back</button>' +
      '<div class="fy-cs-frame"><iframe src="' + esc(url) + '" allow="microphone; camera; clipboard-write"></iframe></div>'
    panel.querySelector('.fy-cs-back').addEventListener('click', close)
    panel.querySelector('.fy-cs-x').addEventListener('click', close)
  }

  function hit(id) {
    if (!id) return
    try {
      fetch(CLICK_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id }),
        keepalive: true
      }).catch(function () {})
    } catch (e) { /* 统计失败无所谓 */ }
  }

  function onKey(e) { if (e.key === 'Escape') close() }

  function close() {
    if (!csState.panel) return
    csState.panel.mask.remove()
    csState.panel.panel.remove()
    csState.panel = null
    document.removeEventListener('keydown', onKey)
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    })
  }

  start()
})()
