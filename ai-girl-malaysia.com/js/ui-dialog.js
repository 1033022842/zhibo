/**
 * SugusDialog — 站点统一美化对话框（替代浏览器原生 confirm/alert）
 * 用法：
 *   SugusDialog.confirm({ title, message, confirmText, cancelText, icon }) -> Promise<boolean>
 *   SugusDialog.alert(message, { title, okText, icon })                    -> Promise<void>
 * icon 可选：'refresh' | 'lock' | 'info' | 'warn'，默认 'info'
 */
(function () {
  'use strict'
  if (window.SugusDialog) return

  var CSS = [
    '.sgd-overlay{position:fixed;inset:0;background:rgba(0,0,0,.62);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px);',
    'z-index:100200;display:flex;align-items:center;justify-content:center;padding:16px;opacity:0;transition:opacity .18s ease}',
    '.sgd-overlay.sgd-show{opacity:1}',
    '.sgd-card{width:100%;max-width:400px;background:linear-gradient(180deg,#232327 0%,#1A1A1D 100%);',
    'border:1px solid rgba(255,255,255,.09);border-radius:20px;padding:26px 24px 22px;box-shadow:0 18px 50px rgba(0,0,0,.55);',
    'transform:translateY(14px) scale(.96);transition:transform .18s ease;font-family:inherit}',
    '.sgd-show .sgd-card{transform:translateY(0) scale(1)}',
    '.sgd-icon{width:54px;height:54px;border-radius:16px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;',
    'background:rgba(231,82,117,.13);border:1px solid rgba(231,82,117,.25)}',
    '.sgd-icon svg{width:26px;height:26px}',
    '.sgd-title{text-align:center;font-size:17px;font-weight:700;color:#fff;margin:0 0 8px;line-height:1.4}',
    '.sgd-msg{text-align:center;font-size:14px;line-height:1.65;color:rgba(255,255,255,.62);margin:0 0 20px;word-break:break-word}',
    '.sgd-btns{display:flex;gap:10px}',
    '.sgd-btn{flex:1;height:44px;border-radius:12px;font-size:14px;font-weight:700;cursor:pointer;border:none;',
    'transition:filter .15s ease;letter-spacing:.2px}',
    '.sgd-btn:active{transform:scale(.98)}',
    '.sgd-btn:hover{filter:brightness(1.08)}',
    '.sgd-btn-cancel{background:#2A2A2E;color:#E4E4E7;border:1px solid rgba(255,255,255,.1)}',
    '.sgd-btn-ok{background:linear-gradient(268deg,#fc768a,#e65073);color:#fff;box-shadow:0 6px 18px rgba(231,82,117,.35)}'
  ].join('')

  var ICONS = {
    refresh: '<svg viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-2.64-6.36"/><path d="M21 3v6h-6"/></svg>',
    lock: '<svg viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="10" width="16" height="10" rx="2.5"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>',
    warn: '<svg viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 2.5 20h19z"/><path d="M12 10v4"/><path d="M12 17.5h.01"/></svg>',
    info: '<svg viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 11v5"/><path d="M12 8h.01"/></svg>'
  }

  var overlay = null
  var activeResolve = null

  function ensureCss() {
    if (document.getElementById('sgd-style')) return
    var s = document.createElement('style')
    s.id = 'sgd-style'
    s.textContent = CSS
    document.head.appendChild(s)
  }

  function build(opts, buttons) {
    ensureCss()
    close(false)

    overlay = document.createElement('div')
    overlay.className = 'sgd-overlay'

    var card = document.createElement('div')
    card.className = 'sgd-card'
    card.setAttribute('role', 'dialog')
    card.setAttribute('aria-modal', 'true')

    var icon = document.createElement('div')
    icon.className = 'sgd-icon'
    icon.innerHTML = ICONS[opts.icon] || ICONS.info
    card.appendChild(icon)

    if (opts.title) {
      var t = document.createElement('div')
      t.className = 'sgd-title'
      t.textContent = opts.title
      card.appendChild(t)
    }
    if (opts.message) {
      var m = document.createElement('div')
      m.className = 'sgd-msg'
      m.textContent = opts.message
      card.appendChild(m)
    }

    var row = document.createElement('div')
    row.className = 'sgd-btns'
    buttons.forEach(function (b) {
      var el = document.createElement('button')
      el.type = 'button'
      el.className = 'sgd-btn ' + b.cls
      el.textContent = b.text
      el.addEventListener('click', function () { close(b.value) })
      row.appendChild(el)
    })
    card.appendChild(row)
    overlay.appendChild(card)

    overlay.addEventListener('click', function (e) { if (e.target === overlay) close(false) })
    document.addEventListener('keydown', onKey)
    document.body.appendChild(overlay)
    // 后台标签页 rAF 会被节流，这里用 rAF + 定时器双保险确保样式类一定加上
    var show = function () { if (overlay) overlay.classList.add('sgd-show') }
    if (typeof requestAnimationFrame === 'function') requestAnimationFrame(show)
    setTimeout(show, 120)
  }

  function onKey(e) {
    if (e.key === 'Escape') close(false)
  }

  function close(value) {
    if (!overlay) return
    var el = overlay
    overlay = null
    document.removeEventListener('keydown', onKey)
    el.classList.remove('sgd-show')
    setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el) }, 180)
    if (activeResolve) {
      var r = activeResolve
      activeResolve = null
      r(value)
    }
  }

  function open(opts, buttons, resolve) {
    activeResolve = resolve
    build(opts, buttons)
    var ok = overlay && overlay.querySelector('.sgd-btn-ok')
    if (ok) ok.focus()
  }

  window.SugusDialog = {
    /**
     * @param {object} opts {title, message, confirmText='Confirm', cancelText='Cancel', icon='info'}
     * @returns {Promise<boolean>} 点击确定 resolve(true)，取消/Esc/遮罩 resolve(false)
     */
    confirm: function (opts) {
      opts = opts || {}
      return new Promise(function (resolve) {
        open(opts, [
          { text: opts.cancelText || 'Cancel', cls: 'sgd-btn-cancel', value: false },
          { text: opts.confirmText || 'Confirm', cls: 'sgd-btn-ok', value: true }
        ], resolve)
      })
    },
    /**
     * @param {string} message
     * @param {object} opts {title='', okText='OK', icon='info'}
     * @returns {Promise<void>}
     */
    alert: function (message, opts) {
      opts = opts || {}
      opts.message = message
      return new Promise(function (resolve) {
        open(opts, [{ text: opts.okText || 'OK', cls: 'sgd-btn-ok', value: true }], resolve)
      })
    }
  }
})()
