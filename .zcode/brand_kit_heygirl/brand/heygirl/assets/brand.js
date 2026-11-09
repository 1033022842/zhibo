/**
 * 品牌名按访问域名自动切换（heygirl 版 · 由 brand kit 整文件覆盖）
 *
 * 上游 js/brand.js 是 Sugus 版，若只靠 apply_brand.sh 的 sed 逐条替换，
 * DOMAINS 数组 / DEFAULT_BRAND / 正则三者会替换不一致（2026-11-09 事故：
 * 线上变成 DEFAULT_BRAND='heygirl.live' 但 RE=/heygirl\.ai/g，
 * 且默认分支提前 return 导致页脚 logo 停在 .ai）。
 * 因此与 kelymi / kiss 同做法：整文件用本文件覆盖，不走 sed。
 *
 * 覆盖范围：<title>、所有 <meta content>、正文 "heygirl.live" 文本、页脚 logo 后缀。
 */
(function () {
  'use strict'

  var DOMAINS = ['heygirl.live', 'heygirl.chat', 'heygirl.me']
  var DEFAULT_BRAND = 'heygirl.live'

  function brandOf(host) {
    host = String(host || '').toLowerCase().replace(/^www\./, '')
    for (var i = 0; i < DOMAINS.length; i++) {
      var d = DOMAINS[i]
      if (host === d || host.slice(-(d.length + 1)) === '.' + d) {
        return 'heygirl.' + d.split('.')[1]
      }
    }
    return DEFAULT_BRAND
  }

  var brand = brandOf(location.hostname)
  window.HEYGIRL_BRAND = brand
  // 兼容站内既有引用
  window.SUGUS_BRAND = brand

  /* 即使域名命中默认品牌也要跑 applyLogo()：
     页面 HTML 里是静态的 heygirl<em>.ai</em>，不跑就停在 .ai。 */
  var RE = /heygirl\.(ai|live|chat|me)/g

  function swap(s) {
    return String(s).replace(RE, brand)
  }

  function applyHead() {
    if (document.title && document.title.indexOf('heygirl.') >= 0) {
      document.title = swap(document.title)
    }
    var metas = document.querySelectorAll('meta[content]')
    for (var i = 0; i < metas.length; i++) {
      var c = metas[i].getAttribute('content')
      if (c && c.indexOf('heygirl.') >= 0) metas[i].setAttribute('content', swap(c))
    }
  }

  // 正文文本节点（跳过 script / style / textarea，避免动到代码）
  function applyBody() {
    if (!document.body || !window.NodeFilter) return
    var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, null)
    var hits = []
    var n
    while ((n = walker.nextNode())) {
      if (n.nodeValue && n.nodeValue.indexOf('heygirl.') >= 0) hits.push(n)
    }
    for (var i = 0; i < hits.length; i++) {
      var p = hits[i].parentNode
      if (!p) continue
      var tag = p.nodeName
      if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'TEXTAREA') continue
      hits[i].nodeValue = swap(hits[i].nodeValue)
    }
  }

  // 页脚 logo 是「heygirl」+ 粉色 <em>.ai</em> 两段，文本节点替换覆盖不到，这里单独改后缀
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
