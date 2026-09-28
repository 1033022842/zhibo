/**
 * 成就页（achievements.html）
 *
 * 数据源：GET /api/live/achievements
 *   → app\live\service\AchievementService：静态成就定义 + 实时统计，
 *     返回 { unlocked, total, points, total_points, signed_in, sections[] }
 *
 * 视觉：沿用存档页（settings）那套视觉——
 *   徽章  .ach-badge / .ach-badge--sm|md|xl / --earned / --hint-<rarity>
 *   布局  .achievement-lab--dashboard / .achievement-header / .achievement-lab__tile
 *   这些类的样式都在 ./settings_files/application-*.css 里，本页只负责填数据。
 *
 * 未登录：接口是公开的（optionalAuthUserId），会返回完整清单（全部未解锁），
 *         页面顶部给一条「登录后开始记录进度」的引导，不会出现空白页。
 */
(function () {
  'use strict'

  var API = { achievements: '/api/live/achievements' }

  var S = 'fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'

  /** 徽章图标（白色描边，跟着 .ach-badge 的 color 走） */
  var ICON = {
    user: '<circle cx="12" cy="8" r="3.6"/><path d="M4.5 20.5a7.5 7.5 0 0 1 15 0"/>',
    badge: '<circle cx="12" cy="9" r="5.2"/><path d="M8.6 13.4 7 21l5-2.9 5 2.9-1.6-7.6"/>',
    star: '<path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5-4.7-4.6 6.5-.9z"/>',
    chat: '<path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 9 9 0 0 1-3.8-.8L3 21l1.9-4.2A8.4 8.4 0 0 1 12 3a8.4 8.4 0 0 1 9 8.5z"/>',
    heart: '<path d="M12 20.3 4.6 13A5 5 0 0 1 12 6.3 5 5 0 0 1 19.4 13z"/>',
    lock: '<rect x="4.5" y="10" width="15" height="10" rx="2.5"/><path d="M8 10V7.4a4 4 0 0 1 8 0V10"/>',
    coin: '<path d="M6 3h12l4 6-10 12L2 9z"/><path d="M2 9h20"/><path d="M12 21 8 9l4-6 4 6z"/>',
    shop: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    gift: '<rect x="3" y="8" width="18" height="4" rx="1"/><path d="M5 12v9h14v-9"/><path d="M12 8v13"/><path d="M12 8S10.5 3 8 3a2.5 2.5 0 0 0 0 5zM12 8s1.5-5 4-5a2.5 2.5 0 0 1 0 5z"/>',
    crown: '<path d="M3 19h18"/><path d="m4 8 4 3 4-6 4 6 4-3-2 8H6z"/>',
    calendar: '<rect x="3" y="5" width="18" height="16" rx="2.5"/><path d="M8 3v4M16 3v4M3 10h18"/>',
    trophy: '<path d="M6 9a6 6 0 1 0 12 0V4H6z"/><path d="M6 6H3v2a3 3 0 0 0 3 3M18 6h3v2a3 3 0 0 1-3 3"/><path d="M12 15v3m-4 3h8"/>',
    check: '<path d="M20 6 9 17l-5-5"/>'
  }

  /** 分区图标（下拉菜单里用） */
  var CAT_ICON = {
    'Getting started': '<path d="M6 21V4"/><path d="M6 4.5h11l-1.6 4L17 13H6z"/>',
    'Chatting': ICON.chat,
    'Spending': ICON.coin,
    'Community': '<circle cx="9" cy="8" r="3.2"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16.5 5.4a3.3 3.3 0 0 1 0 6.2"/><path d="M18.4 20a6.4 6.4 0 0 0-1.5-4.3"/>',
    'Loyalty': ICON.calendar
  }

  var RARITY_LABEL = {
    common: 'Common',
    uncommon: 'Uncommon',
    rare: 'Rare',
    epic: 'Epic',
    legendary: 'Legendary'
  }

  var state = {
    sections: [],
    filter: 'all',
    category: 'all',
    signedIn: false,
    unlocked: 0,
    total: 0,
    points: 0,
    totalPoints: 0,
    error: '',
    ready: false
  }

  /* ---------------------------------------------------------------- 工具 */

  function esc(v) {
    return String(v == null ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
  }

  function icon(name, cls, style) {
    var path = ICON[name] || ICON.badge
    return '<svg class="' + (cls || 'ach-badge__icon') + '" viewBox="0 0 24 24" ' + S +
      (style ? ' style="' + style + '"' : '') +
      ' aria-hidden="true">' + path + '</svg>'
  }

  function catIcon(label) {
    var path = CAT_ICON[label] || ICON.badge
    return '<svg viewBox="0 0 24 24" ' + S + ' style="width:25px;height:25px;flex:none" aria-hidden="true">' +
      path + '</svg>'
  }

  function emptyBox(title, text, cta) {
    return '<div class="achievement-lab__empty"><h2>' + esc(title) + '</h2>' +
      (text ? '<p>' + esc(text) + '</p>' : '') +
      (cta ? '<a href="' + cta.href + '">' + '<button type="button">' + esc(cta.label) + '</button></a>' : '') +
      '</div>'
  }

  /* ---------------------------------------------------------------- 渲染 */

  function headerHtml() {
    var pct = state.total ? Math.round(state.unlocked / state.total * 100) : 0

    return '<header class="achievement-header">' +
      '<div class="achievement-header__identity">' +
        '<span class="ach-badge ach-badge--xl ach-badge--earned ach-badge--legendary ach-badge--halo" style="margin-right:22px">' +
          '<span class="ach-badge__plate"></span>' + icon('trophy') +
        '</span>' +
        '<span>' +
          '<h1>Achievements</h1>' +
          '<span class="achievement-header__eyebrow">Milestones you unlock by chatting, collecting, creating and staying with us.</span>' +
        '</span>' +
      '</div>' +

      '<div class="achievement-header__progress">' +
        '<div class="achievement-header__stats">' +
          '<p class="achievement-header__stat"><strong>' + state.unlocked +
            '<small>/' + state.total + '</small></strong><span>Unlocked</span></p>' +
          '<p class="achievement-header__stat"><strong>' + state.points +
            '</strong><span>Points</span></p>' +
        '</div>' +
        '<div class="achievement-header__completion">' +
          '<progress class="achievement-lab__progress achievement-lab__progress--overall" value="' +
            state.unlocked + '" max="' + (state.total || 1) + '"></progress>' +
          '<div class="achievement-lab__gauge-labels">' +
            '<span>' + pct + '% complete</span>' +
            '<span>' + state.unlocked + ' of ' + state.total + '</span>' +
          '</div>' +
        '</div>' +
      '</div>' +

      nextUpHtml() +
    '</header>'
  }

  /** 下一个目标：取未解锁里进度最接近完成的 3 条 */
  function nextUpHtml() {
    if (!state.signedIn) {
      return '<div class="achievement-header__next">' +
        '<span class="achievement-header__eyebrow">Sign in</span>' +
        '<div class="achievement-header__goals" style="align-content:center;display:block">' +
          '<p style="color:#bcaac3;margin:0 0 14px;font-size:12px;line-height:1.5">' +
            'Sign in to start tracking your progress. Every achievement below is waiting for you.' +
          '</p>' +
          '<a class="achievement-lab__cta" href="./Home.html">Sign in</a>' +
        '</div>' +
      '</div>'
    }

    var locked = []
    for (var i = 0; i < state.sections.length; i++) {
      var items = state.sections[i].items
      for (var j = 0; j < items.length; j++) {
        var it = items[j]
        if (it.unlocked) continue
        locked.push({ item: it, ratio: it.target ? it.value / it.target : 0 })
      }
    }
    locked.sort(function (a, b) { return b.ratio - a.ratio })
    locked = locked.slice(0, 3)

    if (!locked.length) {
      return '<div class="achievement-header__next">' +
        '<span class="achievement-header__eyebrow">Next up</span>' +
        '<div class="achievement-header__goals" style="align-content:center;display:block">' +
          '<p style="color:#99d6b0;margin:0;font-size:13px">All achievements unlocked. You are a legend.</p>' +
        '</div>' +
      '</div>'
    }

    var rows = ''
    for (var k = 0; k < locked.length; k++) {
      var it2 = locked[k].item
      rows += '<div class="achievement-header__goal">' +
        '<span class="ach-badge ach-badge--sm ach-badge--hint-' + esc(it2.rarity) + '">' +
          '<span class="ach-badge__plate"></span>' + icon(it2.icon) +
        '</span>' +
        '<span class="achievement-header__goal-open" role="group">' +
          '<span class="achievement-lab__name-text" style="color:#f7f4f8;display:block;font-size:13px">' + esc(it2.name) + '</span>' +
          '<progress class="achievement-lab__progress" style="margin-top:6px" value="' + it2.value + '" max="' + (it2.target || 1) + '"></progress>' +
          '<span class="achievement-lab__gauge-labels">' +
            '<span>' + it2.value + ' / ' + it2.target + '</span>' +
            '<span>+' + it2.points + ' pts</span>' +
          '</span>' +
        '</span>' +
      '</div>'
    }

    return '<div class="achievement-header__next">' +
      '<span class="achievement-header__eyebrow">Next up</span>' +
      '<div class="achievement-header__goals">' + rows + '</div>' +
    '</div>'
  }

  function toolbarHtml() {
    function f(key, label) {
      return '<button type="button" data-filter="' + key + '" aria-pressed="' +
        (state.filter === key ? 'true' : 'false') + '">' + label + '</button>'
    }

    var menu = '<button type="button" role="menuitemradio" data-cat="all" aria-checked="' +
      (state.category === 'all' ? 'true' : 'false') + '">' +
      '<span class="achievement-lab__all-symbol" aria-hidden="true">&#10022;</span>' +
      '<span>All categories</span><span class="achievement-lab__selected">&#10003;</span></button>'

    for (var i = 0; i < state.sections.length; i++) {
      var label = state.sections[i].label
      menu += '<button type="button" role="menuitemradio" data-cat="' + esc(label) + '" aria-checked="' +
        (state.category === label ? 'true' : 'false') + '">' +
        catIcon(label) + '<span>' + esc(label) + '</span>' +
        '<span class="achievement-lab__selected">&#10003;</span></button>'
    }

    var current = state.category === 'all' ? 'All categories' : state.category

    return '<div class="achievement-lab__toolbar">' +
      '<div class="achievement-lab__status" role="group" aria-label="Filter achievements">' +
        f('all', 'All') + f('unlocked', 'Unlocked') + f('locked', 'Locked') +
      '</div>' +
      '<div class="achievement-lab__category-picker">' +
        '<button type="button" class="achievement-lab__category-trigger" id="fy-ach-cat-trigger" ' +
          'aria-expanded="false" aria-haspopup="true">' +
          '<span id="fy-ach-cat-label">' + esc(current) + '</span>' +
          '<span class="achievement-lab__chevron" aria-hidden="true"></span>' +
        '</button>' +
        '<div class="achievement-lab__category-menu" id="fy-ach-cat-menu" role="menu" hidden ' +
          'style="position:absolute;left:auto;right:0;top:calc(100% + 6px);min-width:220px;max-height:340px">' +
          menu +
        '</div>' +
      '</div>' +
    '</div>'
  }

  /** 按当前筛选条件挑出要显示的分区 */
  function visibleSections() {
    var out = []
    for (var i = 0; i < state.sections.length; i++) {
      var sec = state.sections[i]
      if (state.category !== 'all' && sec.label !== state.category) continue

      var items = []
      for (var j = 0; j < sec.items.length; j++) {
        var it = sec.items[j]
        if (state.filter === 'unlocked' && !it.unlocked) continue
        if (state.filter === 'locked' && it.unlocked) continue
        items.push(it)
      }
      if (!items.length) continue

      var unlocked = 0
      for (var k = 0; k < items.length; k++) if (items[k].unlocked) unlocked++

      out.push({ label: sec.label, items: items, unlocked: unlocked, total: items.length })
    }
    return out
  }

  function tileHtml(it) {
    var unlocked = !!it.unlocked
    var rarity = esc(it.rarity)
    var badgeCls = 'ach-badge ach-badge--sm ' +
      (unlocked ? 'ach-badge--earned ' : 'ach-badge--hint-') + rarity

    return '<button type="button" class="achievement-lab__tile ' +
      (unlocked ? 'achievement-lab__tile--complete' : 'achievement-lab__tile--locked') + '">' +
      '<span class="achievement-lab__art">' +
        '<span class="' + badgeCls + '">' +
          '<span class="ach-badge__plate"></span>' + icon(it.icon) +
        '</span>' +
        (unlocked ? icon('check', 'achievement-lab__check', 'color:#7fd9a5') : '') +
      '</span>' +
      '<span class="achievement-lab__tile-body">' +
        '<span class="achievement-lab__name">' +
          '<span class="achievement-lab__name-text">' + esc(it.name) + '</span>' +
          '<span class="achievement-lab__pts">+' + it.points + '</span>' +
        '</span>' +
        '<span class="achievement-lab__description">' + esc(it.desc) + '</span>' +
        '<progress class="achievement-lab__progress" value="' + it.value + '" max="' + (it.target || 1) + '"></progress>' +
        '<span class="achievement-lab__gauge-labels">' +
          '<span>' + it.value + ' / ' + it.target + '</span>' +
          '<span>' + (RARITY_LABEL[it.rarity] || '') + '</span>' +
        '</span>' +
        '<span class="achievement-lab__state">' +
          icon(unlocked ? 'check' : 'lock', 'achievement-lab__tick') +
          (unlocked ? ' Unlocked' : ' Locked') +
        '</span>' +
      '</span>' +
    '</button>'
  }

  function bodyHtml() {
    var secs = visibleSections()
    if (!secs.length) {
      return '<div class="achievement-lab__empty"><h2>Nothing here yet</h2>' +
        '<p>No achievements match the current filter.</p></div>'
    }

    var html = ''
    for (var i = 0; i < secs.length; i++) {
      var sec = secs[i]
      var tiles = ''
      for (var j = 0; j < sec.items.length; j++) tiles += tileHtml(sec.items[j])

      html += '<section class="achievement-lab__category">' +
        '<div class="achievement-lab__category-heading">' +
          '<h2>' + esc(sec.label) + '</h2>' +
          '<span>' + sec.unlocked + ' / ' + sec.total + ' unlocked</span>' +
        '</div>' +
        '<div class="achievement-lab__tiles">' + tiles + '</div>' +
      '</section>'
    }
    return html
  }

  function render() {
    var root = document.getElementById('fy-ach-root')
    if (!root) return

    if (state.error) {
      root.innerHTML = emptyBox('Could not load achievements', state.error)
      return
    }
    if (!state.ready) return

    root.innerHTML = headerHtml() + toolbarHtml() + bodyHtml()
  }

  /** 只重画列表 + 工具条，避免整块重排导致滚动位置乱跳 */
  function repaint() {
    var root = document.getElementById('fy-ach-root')
    if (!root || !state.ready) return
    root.innerHTML = headerHtml() + toolbarHtml() + bodyHtml()
  }

  /* ---------------------------------------------------------------- 交互 */

  function closeMenu() {
    var menu = document.getElementById('fy-ach-cat-menu')
    var trigger = document.getElementById('fy-ach-cat-trigger')
    if (menu) menu.hidden = true
    if (trigger) trigger.setAttribute('aria-expanded', 'false')
  }

  function bind() {
    var root = document.getElementById('fy-ach-root')
    if (!root) return

    root.addEventListener('click', function (e) {
      var t = e.target
      if (!t || !t.closest) return

      var filterBtn = t.closest('[data-filter]')
      if (filterBtn) {
        state.filter = filterBtn.getAttribute('data-filter')
        repaint()
        return
      }

      if (t.closest('#fy-ach-cat-trigger')) {
        var menu = document.getElementById('fy-ach-cat-menu')
        var trigger = document.getElementById('fy-ach-cat-trigger')
        if (menu) {
          menu.hidden = !menu.hidden
          trigger.setAttribute('aria-expanded', menu.hidden ? 'false' : 'true')
        }
        return
      }

      var catBtn = t.closest('[data-cat]')
      if (catBtn) {
        state.category = catBtn.getAttribute('data-cat')
        closeMenu()
        repaint()
        return
      }

      closeMenu()
    })

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeMenu()
    })

    document.addEventListener('click', function (e) {
      if (e.target && e.target.closest && e.target.closest('.achievement-lab__category-picker')) return
      closeMenu()
    })
  }

  /* ---------------------------------------------------------------- 取数 */

  function token() {
    try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' }
  }

  function load() {
    var t = token()
    var headers = { 'Accept': 'application/json' }
    if (t) {
      headers['Authorization'] = 'Bearer ' + t
      headers['token'] = t
    }

    fetch(API.achievements, { headers: headers })
      .then(function (r) { return r.json() })
      .then(function (res) {
        var data = (res && res.data) || null
        if (!data || !data.sections) {
          state.error = (res && res.msg) ? String(res.msg) : 'Unexpected response from the server.'
          render()
          return
        }

        state.sections = data.sections
        state.unlocked = Number(data.unlocked) || 0
        state.total = Number(data.total) || 0
        state.points = Number(data.points) || 0
        state.totalPoints = Number(data.total_points) || 0
        state.signedIn = !!data.signed_in
        state.ready = true
        state.error = ''
        render()
      })
      .catch(function () {
        state.error = 'Network error. Please check your connection and try again.'
        render()
      })
  }

  function start() {
    var root = document.getElementById('fy-ach-root')
    if (!root) return
    bind()
    load()
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start)
  } else {
    start()
  }
})()
