/* 聊天「看照片」动作卡片（第一版）
 *
 * 后端 /api/live/chat 在识别到「发张照片」这类明确请求时，会多返回一个
 *   action: { type: 'photo'|'none', role_key, count, need_login, label }
 * 本文件只做一件事：把 type=photo 渲染成气泡旁的卡片，点击后走**已有的**
 * 场景按钮链路（#scene-image-btn → /api/live/sendChatMedia），不新造素材系统。
 *
 * 回退：删掉 Chat.html 里的这一行 script 即可；后端也可以用
 * lp_platform_config.chat_photo_cta=0 单独关闭。
 */
(function () {
  'use strict'

  if (window.__fyPhotoCta) return
  window.__fyPhotoCta = true

  var CARD_ID = 'fy-photo-cta'
  var origFetch = window.fetch

  function messagesBox() {
    return document.getElementById('messages_turbo_frame') || document.getElementById('messages')
  }

  function dropCard() {
    var old = document.getElementById(CARD_ID)
    if (old && old.parentNode) old.parentNode.removeChild(old)
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    })
  }

  function paintCard(action) {
    dropCard()
    var box = messagesBox()
    if (!box) return

    var label = action.need_login ? 'Sign in to see photos' : (action.label || 'View photo')
    var sub = action.need_login ? 'Free to chat — photos unlock after sign in' : 'Tap to see what she picked'

    var row = document.createElement('div')
    row.id = CARD_ID
    row.className = 'fy-msg fy-msg-ai'
    row.innerHTML =
      '<div class="fy-msg-col">' +
      '  <button type="button" class="fy-photo-card" data-fy-photo-open="1">' +
      '    <span class="fy-photo-ic" aria-hidden="true">' +
      '      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' +
      '        <rect x="3" y="6" width="18" height="14" rx="3"/><circle cx="12" cy="13" r="3.4"/><path d="M8 6l1.2-2h5.6L16 6"/>' +
      '      </svg>' +
      '    </span>' +
      '    <span class="fy-photo-tx">' +
      '      <b>' + esc(label) + '</b>' +
      '      <i>' + esc(sub) + '</i>' +
      '    </span>' +
      '    <span class="fy-photo-arrow" aria-hidden="true">›</span>' +
      '  </button>' +
      '</div>'
    box.appendChild(row)
    if (typeof window.scrollMessagesToBottom === 'function') window.scrollMessagesToBottom()
    box.scrollTop = box.scrollHeight

    row.querySelector('[data-fy-photo-open]').addEventListener('click', function () {
      openPhoto(row)
    }, false)
  }

  /* 点击：优先复用页面上已有的场景按钮（chat.js 负责登录判断、typing、超时、落库） */
  function openPhoto(row) {
    // 按钮即使被 CSS 隐藏，click() 仍会走 chat.js 的监听（它自己判断 token/roleKey）
    var btn = document.getElementById('scene-image-btn')
    if (btn) {
      btn.click()
      row.style.opacity = '0.55'
      setTimeout(dropCard, 300)
      return
    }
    // 按钮还没渲染出来（chatMedia 仍在拉取）：给它一点时间，再退化到提示
    setTimeout(function () {
      var again = document.getElementById('scene-image-btn')
      if (again) { again.click(); dropCard(); return }
      var t = row.querySelector('.fy-photo-tx i')
      if (t) t.textContent = 'Photos are not available for this character right now'
    }, 1200)
  }

  function isChatCall(url) {
    return /\/api\/live\/chat(\?|$)/.test(String(url || ''))
  }

  window.fetch = function (input, init) {
    var url = typeof input === 'string' ? input : (input && input.url) || ''
    var p = origFetch.apply(this, arguments)
    if (!isChatCall(url)) return p
    return p.then(function (res) {
      try {
        res.clone().json().then(function (d) {
          var action = d && d.code === '00000' && d.data && d.data.action
          // 每次新回复先清掉旧卡，避免同一张卡挂在历史气泡上
          setTimeout(function () {
            if (!action || action.type !== 'photo') { dropCard(); return }
            paintCard(action)
          }, 80)
        }).catch(function () {})
      } catch (e) { /* 读响应失败不影响聊天 */ }
      return res
    })
  }

  /* 切换会话 / 重新载入历史时清卡 */
  window.addEventListener('pageshow', dropCard)
  document.addEventListener('click', function (e) {
    var t = e.target
    if (t && t.closest && t.closest('#message-list a.chat-obj')) dropCard()
  }, true)
})()
