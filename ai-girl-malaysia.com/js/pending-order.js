/**
 * 未完成支付提醒（全站）
 *
 * 场景：用户点了订阅 / 购买代币，跳到收单网关后没有付款就返回或关掉页面。
 * 下次进入站内任意页面时，若本地还有待支付订单，弹窗询问「是否继续支付」。
 *
 * 数据来源（两个页面下单时写入）：
 *   localStorage.vip_pending_order = { o: 订单号, t: 下单时间戳, u: 支付地址, k: 'vip' }
 *   localStorage.tk_pending_order  = { o: 订单号, t: 下单时间戳, u: 支付地址, k: 'tk' }
 *
 * 校验：调用 /api/v1/recharge/status 确认订单仍待支付且未过期，避免给已付款/已过期单弹窗。
 * 点「稍后」按订单号记住时间，10 分钟内不再打扰（换页也不会反复弹）。
 *
 * 注意：Subscriptions / purchasePoints 页有自己的「支付回跳」轮询逻辑，不在此重复弹。
 */
;(function () {
  'use strict'
  if (window.__fyPendingOrder) return
  window.__fyPendingOrder = true

  var PATH = (location.pathname || '').toLowerCase()
  // 这些页面自己处理支付回跳 / 本身就是支付流程，不弹
  if (/\/(login|subscriptions|subscriptions2|purchasepoints|tokens|cryptopay|payment|test|install)\.html$/.test(PATH)) return
  if (/\/(admin|api)\//.test(PATH)) return

  var ORDER_TTL = 2 * 60 * 60 * 1000      // 订单有效期 2 小时（与下单页一致）
  var SNOOZE_MS = 10 * 60 * 1000          // 点「稍后」后 10 分钟内不再弹
  var SNOOZE_KEY = 'pending_order_snooze'
  var STATUS_URL = '/api/v1/recharge/status'

  var token = ''
  try { token = localStorage.getItem('live_access_token') || '' } catch (e) { token = '' }
  if (!token) return

  function read(key) {
    var raw = ''
    try { raw = localStorage.getItem(key) || '' } catch (e) { return null }
    if (!raw) return null
    try {
      var p = JSON.parse(raw)
      if (p && p.o) {
        return { o: String(p.o), t: parseInt(p.t, 10) || 0, u: String(p.u || ''), k: String(p.k || '') }
      }
    } catch (e) {}
    return { o: raw, t: 0, u: '', k: '' } // 兼容旧格式
  }

  function clear(key) {
    try { localStorage.removeItem(key) } catch (e) {}
  }

  function snoozedFor(orderNo) {
    try {
      var p = JSON.parse(localStorage.getItem(SNOOZE_KEY) || 'null')
      return !!(p && String(p.o) === String(orderNo) && Date.now() - (parseInt(p.t, 10) || 0) < SNOOZE_MS)
    } catch (e) { return false }
  }

  function snooze(orderNo) {
    try { localStorage.setItem(SNOOZE_KEY, JSON.stringify({ o: orderNo, t: Date.now() })) } catch (e) {}
  }

  function money(v) {
    var n = parseFloat(v)
    return isFinite(n) ? '$' + n.toFixed(2) : ''
  }

  // 两个待支付订单里取最新的一个
  var cands = []
  var vip = read('vip_pending_order')
  if (vip) { vip.key = 'vip_pending_order'; vip.label = 'VIP membership'; cands.push(vip) }
  var tk = read('tk_pending_order')
  if (tk) { tk.key = 'tk_pending_order'; tk.label = 'Tokens'; cands.push(tk) }
  if (!cands.length) return

  cands.sort(function (a, b) { return (b.t || 0) - (a.t || 0) })
  var cur = cands[0]

  // 本地兜底过期判断（服务端 expire_at 会在下面二次确认）
  if (cur.t > 0 && Date.now() - cur.t > ORDER_TTL) { clear(cur.key); return }
  if (snoozedFor(cur.o)) return

  function api(path, cb) {
    var xhr = new XMLHttpRequest()
    xhr.open('GET', path, true)
    xhr.setRequestHeader('Authorization', 'Bearer ' + token)
    xhr.timeout = 12000
    xhr.onload = function () {
      var d = null
      try { d = JSON.parse(xhr.responseText) } catch (e) { d = null }
      cb(d, xhr.status)
    }
    xhr.onerror = function () { cb(null, 0) }
    xhr.ontimeout = function () { cb(null, 0) }
    xhr.send()
  }

  function parseTime(s) {
    if (!s) return 0
    var t = Date.parse(String(s).replace(/-/g, '/'))
    return isFinite(t) ? t : 0
  }

  api(STATUS_URL + '?order_no=' + encodeURIComponent(cur.o), function (d) {
    if (!d) return                                   // 网络问题：这次不打扰，下次再说
    if (d.code === 'A0100' || d.code === 'A0101') return  // 未登录/登录失效：不处理

    if (d.code !== '00000' || !d.data) {             // 订单不存在（已被清理）
      clear(cur.key)
      return
    }

    var ord = d.data
    if (parseInt(ord.status, 10) === 1) {            // 已支付但回跳没提示到
      clear(cur.key)
      toast('Payment successful, your order has been credited')
      return
    }

    var exp = parseTime(ord.expire_at)
    if (exp > 0 && exp < Date.now()) {               // 已过期
      clear(cur.key)
      return
    }

    var payUrl = cur.u || ord.pay_url || ''
    var amount = money(ord.pay_amount)
    waitOtherPopup(function () { show(cur, amount, payUrl) }, 0)
  })

  /* ---------------- 弹窗（自包含样式，不依赖 SugusDialog） ---------------- */
  function toast(msg) {
    var el = document.createElement('div')
    el.textContent = msg
    el.style.cssText = 'position:fixed;left:50%;top:22px;transform:translateX(-50%);z-index:2147483000;' +
      'background:linear-gradient(90deg,#4346e6,#6143e6);color:#fff;padding:10px 18px;border-radius:10px;' +
      'font:600 13px/1.4 -apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;box-shadow:0 10px 30px rgba(0,0,0,.45)'
    document.body.appendChild(el)
    setTimeout(function () { el.style.transition = 'opacity .3s'; el.style.opacity = '0' }, 2600)
    setTimeout(function () { el.remove() }, 3000)
  }

  // 与其它登录后弹窗错峰：创作指南 / 18+ 弹窗 / 订阅弹窗打开时先等待
  function otherPopupOpen() {
    return !!(document.querySelector('.cg-overlay.cg-show') ||
      document.querySelector('.c-promo.open') ||
      document.querySelector('.sgd-overlay'))
  }

  function waitOtherPopup(fn, tries) {
    if (tries >= 20) { fn(); return }
    if (otherPopupOpen()) {
      setTimeout(function () { waitOtherPopup(fn, tries + 1) }, 700)
      return
    }
    fn()
  }

  function show(ord, amount, payUrl) {
    var mask = document.createElement('div')
    mask.setAttribute('role', 'dialog')
    mask.setAttribute('aria-modal', 'true')
    mask.style.cssText = 'position:fixed;inset:0;z-index:2147482000;background:rgba(0,0,0,.66);' +
      '-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;padding:20px;' +
      'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Poppins,sans-serif'

    var card = document.createElement('div')
    card.style.cssText = 'width:100%;max-width:380px;border-radius:18px;border:1px solid rgba(255,255,255,.12);' +
      'background:linear-gradient(180deg,#1d1d20 0%,#151517 100%);box-shadow:0 26px 64px rgba(0,0,0,.62);' +
      'color:#fff;padding:22px 22px 18px;box-sizing:border-box'

    var title = document.createElement('div')
    title.textContent = 'You have an unfinished payment'
    title.style.cssText = 'font-size:17px;font-weight:700;line-height:1.35'

    var desc = document.createElement('div')
    desc.textContent = 'We found an order that has not been paid yet' +
      (amount ? ' — ' + amount : '') + ' for ' + (ord.label || 'your order') +
      '. Would you like to continue the payment?'
    desc.style.cssText = 'margin-top:10px;font-size:13.5px;line-height:1.65;color:#b8b8c0'

    var hint = document.createElement('div')
    hint.textContent = 'Your order will be reserved for a limited time.'
    hint.style.cssText = 'margin-top:8px;font-size:12px;line-height:1.6;color:#74747c'

    var row = document.createElement('div')
    row.style.cssText = 'display:flex;gap:10px;margin-top:20px'

    var later = document.createElement('button')
    later.type = 'button'
    later.textContent = 'Later'
    later.style.cssText = 'flex:1;height:44px;border-radius:12px;border:1px solid rgba(255,255,255,.16);' +
      'background:rgba(255,255,255,.06);color:#e6e6ea;font-size:14px;font-weight:600;cursor:pointer'

    var go = document.createElement('button')
    go.type = 'button'
    go.textContent = 'Continue payment'
    go.style.cssText = 'flex:1.5;height:44px;border-radius:12px;border:1px solid rgba(255,255,255,.18);' +
      'background:linear-gradient(90deg,#4346e6 0%,#6143e6 100%);color:#fff;font-size:14px;font-weight:700;cursor:pointer'

    row.appendChild(later)
    row.appendChild(go)
    card.appendChild(title)
    card.appendChild(desc)
    card.appendChild(hint)
    card.appendChild(row)
    mask.appendChild(card)
    document.body.appendChild(mask)

    function close() { mask.remove() }

    later.addEventListener('click', function () {
      snooze(ord.o)
      close()
    })
    go.addEventListener('click', function () {
      if (payUrl) { location.href = payUrl; return }
      // 拿不到网关地址（老订单）：回到对应购买页重新下单
      location.href = ord.key === 'tk_pending_order' ? './purchasePoints.html' : './Subscriptions.html'
    })
    mask.addEventListener('click', function (e) { if (e.target === mask) { snooze(ord.o); close() } })
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { snooze(ord.o); close() }
    }, { once: true })
  }
})()
