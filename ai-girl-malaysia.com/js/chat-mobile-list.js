/* Chat 页手机端会话列表抽屉
 * 手机端左上角按钮（导航汉堡）改为打开「会话列表」，不再打开站点导航抽屉。
 * 桌面端（>1024px）保持原样：列表常驻左侧、汉堡打开导航。
 */
(function () {
  var BP = 1024
  var CLASS = 'chatlist-open'

  function isMobile() { return window.innerWidth <= BP }
  function open() { document.body.classList.add(CLASS) }
  function close() { document.body.classList.remove(CLASS) }
  function isOpen() { return document.body.classList.contains(CLASS) }

  function toggle() { isOpen() ? close() : open() }

  document.addEventListener('click', function (e) {
    var t = e.target

    /* 左上角汉堡：拦截 stimulus 的 main#handleMenuClick，改为开关会话列表 */
    if (t.closest && t.closest('#nav-hamburger') && isMobile()) {
      e.preventDefault()
      e.stopPropagation()
      toggle()
      return
    }

    /* 底部导航的 Chat 项：直接打开列表 */
    if (t.closest && t.closest('[data-chatlist-open]') && isMobile()) {
      e.preventDefault()
      e.stopPropagation()
      open()
      return
    }

    /* 关闭：遮罩、关闭按钮 */
    if (t.closest && (t.closest('#chatlist-veil') || t.closest('[data-chatlist-close]'))) {
      e.preventDefault()
      close()
      return
    }

    /* 选了某个会话就收起抽屉（延迟一拍，让选择逻辑先跑） */
    if (t.closest && t.closest('#message-list a.chat-obj') && isOpen()) {
      setTimeout(close, 80)
    }
  }, true)

  /* 返回聊天页时若从 bfcache 恢复，别停留在打开态 */
  window.addEventListener('pageshow', function () { close() })
  window.addEventListener('resize', function () { if (!isMobile()) close() })
})()
