/* 邀请页社交分享：Discord / Telegram / X / WhatsApp
 * - X / Telegram / WhatsApp 走官方分享 URL（新标签打开）
 * - Discord 没有网页分享入口：优先调系统分享面板（移动端会列出 Discord/Telegram 等 App），
 *   不支持时复制「文案 + 链接」并提示去 Discord 粘贴
 * 链接取页面里已有的邀请链接显示位，用户自定义链接后无需改这里
 */
(function () {
  'use strict'

  function inspectLink() {
    var el = document.querySelector('[data-invite-share-target="linkDisplay"], #invite-link-display')
    var t = el ? String(el.textContent || '').trim() : ''
    if (!t) return ''
    if (/^https?:\/\//i.test(t)) return t
    // 有些站点只渲染短码，补成绝对地址
    if (/^\/?r\//i.test(t)) return location.origin + (t.charAt(0) === '/' ? t : '/' + t)
    return t
  }

  function shareText() {
    var name = (document.querySelector('meta[property="og:site_name"]') || {}).content || document.title || ''
    name = String(name).replace(/\s*[|·—-].*$/, '').trim() || 'this app'
    return 'Come join me on ' + name + ' — we both get bonus tokens 💝'
  }

  function copy(text) {
    var ta = document.createElement('textarea')
    ta.value = text
    ta.setAttribute('readonly', '')
    ta.style.cssText = 'position:fixed;top:-1000px;left:-1000px;opacity:0'
    document.body.appendChild(ta)
    ta.select()
    var ok = false
    try { ok = document.execCommand('copy') } catch (e) { ok = false }
    ta.remove()
    return ok
  }

  var toastTimer = 0
  function toast(msg) {
    var el = document.getElementById('fy-invite-toast')
    if (!el) {
      el = document.createElement('div')
      el.id = 'fy-invite-toast'
      el.style.cssText = 'position:fixed;left:50%;bottom:88px;transform:translateX(-50%);z-index:9999;' +
        'max-width:86vw;padding:10px 16px;border-radius:10px;background:rgba(28,28,28,.96);' +
        'border:1px solid rgba(255,255,255,.12);color:#fff;font:500 13px/18px -apple-system,Segoe UI,Roboto,sans-serif;' +
        'text-align:center;opacity:0;transition:opacity .18s ease;pointer-events:none'
      document.body.appendChild(el)
    }
    el.textContent = msg
    el.style.opacity = '1'
    clearTimeout(toastTimer)
    toastTimer = setTimeout(function () { el.style.opacity = '0' }, 2400)
  }

  function openExternal(url) {
    var w = window.open(url, '_blank', 'noopener,noreferrer')
    if (!w) location.href = url
  }

  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('[data-invite-share-to]')
    if (!btn) return
    e.preventDefault()
    e.stopPropagation()

    var url = inspectLink()
    if (!url) { toast('Invite link is not ready yet'); return }
    var text = shareText()
    var to = String(btn.getAttribute('data-invite-share-to') || '').toLowerCase()

    if (to === 'x' || to === 'twitter') {
      openExternal('https://twitter.com/intent/tweet?text=' + encodeURIComponent(text) + '&url=' + encodeURIComponent(url))
      return
    }
    if (to === 'telegram') {
      openExternal('https://t.me/share/url?url=' + encodeURIComponent(url) + '&text=' + encodeURIComponent(text))
      return
    }
    if (to === 'whatsapp') {
      openExternal('https://wa.me/?text=' + encodeURIComponent(text + ' ' + url))
      return
    }
    if (to === 'facebook') {
      openExternal('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url))
      return
    }
    // discord / 其它：系统分享面板优先，退化为复制
    if (to === 'discord' && navigator.share) {
      navigator.share({ title: document.title, text: text, url: url }).catch(function () {})
      return
    }
    var ok = copy(text + ' ' + url)
    if (to === 'discord') {
      toast(ok ? 'Link copied — paste it in your Discord community' : 'Copy failed, long-press the link above')
      if (ok) setTimeout(function () { openExternal('https://discord.com/app') }, 900)
      return
    }
    toast(ok ? 'Link copied' : 'Copy failed, long-press the link above')
  }, true)

  /* Baseline template carries another site's domain (heygirl.live) in invite
     links and JSON-LD. On sugus/kelymi/kiss that sends users to the wrong site.
     Normalise everything to the current origin. */
  function normalizeOrigin() {
    var origin = location.origin
    var re = /https?:\\?\/\\?\/[a-z0-9.-]*heygirl\.live/gi
    var brand = location.hostname.replace(/^www\./, '')
    function fix(t) {
      var out = String(t).replace(re, origin)
      if (location.hostname.indexOf('heygirl') === -1) out = out.replace(/heygirl\.live/gi, brand)
      return out
    }
    Array.prototype.forEach.call(document.querySelectorAll('script[type="application/ld+json"]'), function (el) {
      if (el.textContent && /heygirl\.live/i.test(el.textContent)) el.textContent = fix(el.textContent)
    })
    Array.prototype.forEach.call(document.querySelectorAll('a[href*="heygirl.live"]'), function (a) {
      a.setAttribute('href', fix(a.getAttribute('href')))
    })
    Array.prototype.forEach.call(document.querySelectorAll('[data-invite-share-target="linkDisplay"], #invite-link-display'), function (el) {
      if (el.textContent && /heygirl\.live/i.test(el.textContent)) el.textContent = fix(el.textContent)
    })
    if (location.hostname.indexOf('heygirl') === -1) {
      Array.prototype.forEach.call(document.querySelectorAll('body *'), function (el) {
        if (!el.children.length && el.textContent && /heygirl\.live/i.test(el.textContent)) el.textContent = fix(el.textContent)
      })
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', normalizeOrigin)
  else normalizeOrigin()
  window.addEventListener('load', normalizeOrigin)
})()
