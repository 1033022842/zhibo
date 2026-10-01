/**
 * 品牌名按访问域名自动切换
 * 同一个站跑四个域名：sugus.ai / sugus.live / sugus.chat / sugus.me。
 * 页面文案统一按「Sugus.ai」写，本脚本在打开页面时按当前域名把品牌换成对应的那个，
 * 覆盖范围：<title>、所有 <meta content>（description / og:title / og:description /
 * og:site_name / apple-mobile-web-app-title …）、页面正文里的 "Sugus.ai" 文本。
 * 其它域名（本地预览、测试域名等）保持「Sugus.ai」不变。
 */
(function () {
  'use strict'

  var DOMAINS = ['sugus.ai', 'sugus.live', 'sugus.chat', 'sugus.me']
  var DEFAULT_BRAND = 'Sugus.ai'

  function brandOf(host) {
    host = String(host || '').toLowerCase().replace(/^www\./, '')
    for (var i = 0; i < DOMAINS.length; i++) {
      var d = DOMAINS[i]
      if (host === d || host.slice(-(d.length + 1)) === '.' + d) {
        return 'Sugus.' + d.split('.')[1]   // Sugus.ai / Sugus.live / Sugus.chat / Sugus.me
      }
    }
    return DEFAULT_BRAND
  }

  var brand = brandOf(location.hostname)
  window.SUGUS_BRAND = brand
  if (brand === DEFAULT_BRAND) return

  var RE = /Sugus\.ai/g

  function swap(s) {
    return String(s).replace(RE, brand)
  }

  // 标题 + meta（此时 <head> 已解析完，能拿到 title / description / og:*）
  function applyHead() {
    if (document.title && document.title.indexOf('Sugus.ai') >= 0) {
      document.title = swap(document.title)
    }
    var metas = document.querySelectorAll('meta[content]')
    for (var i = 0; i < metas.length; i++) {
      var c = metas[i].getAttribute('content')
      if (c && c.indexOf('Sugus.ai') >= 0) metas[i].setAttribute('content', swap(c))
    }
  }

  // 正文文本节点（跳过 script / style / textarea，避免动到代码）
  function applyBody() {
    if (!document.body || !window.NodeFilter) return
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null)
    var hits = []
    var n
    while ((n = walker.nextNode())) {
      if (n.nodeValue && n.nodeValue.indexOf('Sugus.ai') >= 0) hits.push(n)
    }
    for (var i = 0; i < hits.length; i++) {
      var p = hits[i].parentNode
      if (!p) continue
      var tag = p.nodeName
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA') continue
      hits[i].nodeValue = swap(hits[i].nodeValue)
    }
  }

  // 页脚 logo 是「Sugus」+ 粉色 <em>.ai</em> 两段，文本节点替换覆盖不到，这里单独改后缀
  function applyLogo() {
    var suffix = '.' + brand.split('.')[1]
    var ems = document.querySelectorAll('.c-footer-brand .logo em')
    for (var i = 0; i < ems.length; i++) ems[i].textContent = suffix
  }

  function applyAll() {
    applyBody()
    applyLogo()
  }

  applyHead()
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyAll)
  } else {
    applyAll()
  }
})()
