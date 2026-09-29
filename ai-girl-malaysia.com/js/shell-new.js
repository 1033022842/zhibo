/**
 * 新版外壳渲染（侧边栏 + 顶栏右上角）
 * 样式目标：Subscriptions.html / tokens.html 使用的新版外壳。
 * 菜单内容：沿用站点原有的菜单项与顺序（Home / Explore / Chat / Gallery / Generate Image /
 *          Create Character / My AI / Upload Media / Face Swap / Shop / Shorts / Posts /
 *          Private Content / Tokens / Games / Crowdfunding，底部 Contact Us / Affiliate Program /
 *          Become Premium），只换外观，不改入口。
 *
 * 做法：只替换旧页面里「侧边栏 nav 的内容」与「#user-setting 的内容」，
 *      外层容器及其 data-main-target / webkit-transform-sidebar / pc-nav 全部保留，
 *      因此旧包的展开·收起逻辑（main_controller.js 的 toggleSidebar）继续生效。
 *      自带新版外壳的页面（Subscriptions.html）：左侧菜单与顶栏右上角都统一成这一套。
 */
(function () {
  'use strict'

  var T = './taocan_files/'
  // 参考页没有的菜单项：形状沿用站点旧侧边栏，描边统一成新版侧栏的浅灰
  var S = 'fill="none" stroke="#D1D1D1" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"'

  var I = {
    // 图片图标（新版侧栏同款）
    home: { src: T + 'home-18a7d73adabda5674f78dab4a3d6b3acebbdbba31f0ad19053537dabffade496.svg' },
    explore: { src: T + 'explore-3a4a39d3c6126c743969aa14b3a4841763f5b600e0f77e317a32d7c30afbcc13.svg' },
    chat: { src: T + 'chat-1f356f466c9023c851a3a185fd1e607229737246758457a3f49b82e17bc5d82b.svg' },
    gallery: { src: T + 'gallery-b83c72cb24c980d5e254daba38d4e3690f281b07c3e5665a052e0f76fff031b8.svg' },
    achv: { src: T + 'trophy-55ba15bcf830427b6fc776e11ff4465cce75c1c2a1a27ba0bd2eea0e5c03ff55.svg' },
    candy: { src: T + 'candy-shop-d549838a0f3746c8cdf58c1cf0684d40990ec9b275fa8ac042c47379182965d1.svg' },
    shorts: { src: T + 'shorts-dd7eca01fe7f8a694cd100be90b5b6c20d241a5bf4c8b8c0f8633864125cffae.svg' },
    wand: { src: T + 'magic-wand-bc43a2a37c108cc6308370c0e6fada5385fd848143063f66116694fd9aa075b7.svg' },
    lady: { src: T + 'love-lady-760605daa886b7e49e77d5d26c1047bc0f815c005a978a5c76810db20bc355dd.svg' },
    lock: { src: T + 'heart-lock-c322a04a0a80c5ca1b50658063120fa33d2f209b5e0980e7be8e47cc9667f736.svg' },
    token: { src: T + 'token-4193ee36c35274edacea995ff69347bb8d3e75f4094143958306eac195fe9a83.svg' },
    premium: { src: T + 'premium-3fa8ec9f951178508109d03de202bc4a381ed87451b7dc9f8aa5a931bd485e02.svg' },
    generate: { src: './characters_files/generate-33f95391d5d800565b7ea9ba3f6effa0a3d0a2b2eeafdba4ccc526aef7ae3498.svg' },
    en: { src: T + 'en-337bbb31fb6de773da4d64ab2e90f99d4706f32ade21156a47b8603dfeccb1e4.svg' },
    coin: { src: T + 'token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg' },
    expand: { src: T + 'expand-cd76d60d9e4264d89102db00cc87d69cb1ecd6c96663e5cea3809a90dd47a9ab.svg' },
    addWhite: { src: T + 'add_white-d504ef86384a5fe4014170fa04483735c82f42d80301af36e1c50f979a2c2245.svg' },
    user: { src: T + 'user-136475e1e7120a61ab13061e84c88cdf96837d2fb8eafda7a8c5b73815512f42.svg' },
    man: { src: T + 'man-f06d242ecbaa154c05843d8e91f779c41dbef390319247606b1919b87169c74a.svg' },
    gift: { src: T + 'icon_gift-21fcbe4f1b1c5a97716a7735e28f760b007f6f05d08c337f2b1d16a56d6aa48d.svg' },
    settings: { src: T + 'settings-087d65eb2d9a7508f04c379467f39735f971ac6299872f159da8e6c1d735a710.svg' },
    logout: { src: T + 'logout-c5bab4efa12211db16618320220423a09cf3d7f5b562be32cdd5110df2a21c18.svg' },

    // 内联图标（站点旧侧边栏同款形状）
    upload: { svg: '<svg viewBox="0 0 24 24" ' + S + '><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>' },
    faceswap: { svg: '<svg viewBox="0 0 24 24" ' + S + '><circle cx="9" cy="8.5" r="4.5"/><circle cx="15" cy="15.5" r="4.5"/><path d="M7.6 7.9h.01M10.4 7.9h.01M8.2 10.6c.5.4 1.1.4 1.6 0"/></svg>' },
    posts: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.8" cy="9.8" r="1.5"/><path d="M4.2 18.2l4.6-4.6 3.6 3.6 2.8-2.4 4.6 3.4"/></svg>' },
    games: { svg: '<svg viewBox="0 0 24 24" ' + S + '><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>' },
    crowd: { svg: '<svg viewBox="0 0 24 24" ' + S + '><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
    contact: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>' },
    affiliate: { svg: '<svg viewBox="0 0 24 24" ' + S + '><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="10.7" x2="15.4" y2="6.3"/><line x1="8.6" y1="13.3" x2="15.4" y2="17.7"/></svg>' }
  }

  // 侧边栏菜单：沿用站点原有菜单项与顺序
  var NAV = [
    { href: './Home.html', label: 'Home', icon: 'home' },
    { href: './index.html', label: 'Crowdfunding', icon: 'crowd' },
    { href: './Girls.html', label: 'Explore', icon: 'explore' },
    { href: './Chat.html', label: 'Chat', icon: 'chat' },
    { href: './Gallery.html', label: 'Gallery', icon: 'gallery' },
    { href: './achievements.html', label: 'Achievements', icon: 'achv' },
    { href: './Image.html', label: 'Generate Image', icon: 'generate', top: true },
    { href: './characters.html', label: 'Create Character', icon: 'wand' },
    { href: './charactersIndex.html', label: 'My AI', icon: 'lady' },
    { href: './upload_media.html', label: 'Upload Media', icon: 'upload' },
    { href: './face_swap.html', label: 'Face Swap', icon: 'faceswap' },
    { href: './shop.html', label: 'Shop', icon: 'candy' },
    { href: './shorts.html', label: 'Shorts', icon: 'shorts' },
    { href: './posts.html', label: 'Posts', icon: 'posts' },
    { href: './private_content.html', label: 'Private Content', icon: 'lock' },
    { href: './purchasePoints.html', label: 'Tokens', icon: 'token' },
    { href: './Game.html', label: 'Games', icon: 'games' }
  ]

  // 侧边栏底部：沿用站点原有三项
  var NAV_BOTTOM = [
    { href: 'mailto:support@sugusmail.ai', label: 'Contact Us', icon: 'contact', attr: ' data-contact-us="1"' },
    { href: './Inviter.html', label: 'Affiliate Program', icon: 'affiliate' },
    { href: './Subscriptions.html', label: 'Become Premium', icon: 'premium' }
  ]

  // 语言列表（与 header/header.html 的下拉一致，复用 .lang-select 的既有逻辑）；第三位为国旗 emoji
  var LANGS = [
    ['vi', 'Tiếng Việt', 'vn'],
    ['ms', 'Melayu', 'my'],
    ['th', 'แบบไทย', 'th'],
    ['km', 'ភាសាខ្មែរ', 'kh'],
    ['lo', 'ພາສາລາວ', 'la'],
    ['en', 'English', 'us'],
    ['zh-CN', '中文', 'cn'],
    ['ja', '日本語', 'jp'],
    ['ko', '한국어', 'kr'],
    ['ru', 'Русский', 'ru'],
    ['es', 'español', 'es']
  ]

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    })
  }

  function iconHtml(key, size) {
    var ic = I[key]
    if (ic.svg) return ic.svg.replace('<svg ', '<svg class="' + size + '" ')
    return '<img class="' + size + '" src="' + ic.src + '" alt="">'
  }

  function linkAttr(item) {
    return ' href="' + item.href + '"' +
      (item.top ? ' target="_top"' : '') +
      (item.attr || '')
  }

  /* ---------- 收起态 100px ---------- */
  function collapsedSidebar() {
    var items = NAV.map(function (n) {
      return '<li class="relative w-full" data-fy-tip="' + esc(n.label) + '">' +
        '<a class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer"' + linkAttr(n) + '>' +
        iconHtml(n.icon, 'w-6 h-6') + '</a></li>'
    }).join('')

    var bottom = NAV_BOTTOM.map(function (n) {
      return '<li class="relative w-full" data-fy-tip="' + esc(n.label) + '">' +
        '<a class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer"' + linkAttr(n) + '>' +
        iconHtml(n.icon, 'w-6 h-6') + '</a></li>'
    }).join('')

    return '<nav class="flex flex-col justify-between h-full">' +
      '<div class="flex flex-col">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6">' + items + '</ul>' +
      '</div>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end">' +
      '<li class="relative w-full">' + langButton(false) + '</li>' + bottom +
      '</ul></nav>'
  }

  /* ---------- 展开态 220px ---------- */
  function expandedSidebar() {
    function rows(list) {
      return list.map(function (n) {
        return '<li class="relative w-full">' +
          '<a class="h-[40px] w-full hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer"' + linkAttr(n) + '>' +
          iconHtml(n.icon, 'w-4 h-4') +
          '<span class="text-start text-grey-medium text-xxs font-medium leading-4">' + esc(n.label) + '</span>' +
          '</a></li>'
      }).join('')
    }

    return '<nav class="flex flex-col justify-between h-full overflow-hidden">' +
      '<div class="flex flex-col flex-1 min-h-0">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6 flex-1 min-h-0 overflow-y-auto">' + rows(NAV) + '</ul>' +
      '</div>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end shrink-0">' +
      '<li class="relative w-full">' + langButton(true) + '</li>' + rows(NAV_BOTTOM) +
      '</ul></nav>'
  }

  /* ---------- 语言切换按钮 + 下拉 ---------- */
  function langButton(withLabel, mobile) {
    var cur = currentLang()
    var curRow = null
    LANGS.forEach(function (l) { if (l[0] === cur) curRow = l })
    if (!curRow) curRow = LANGS[5]
    var curFlag = './assets/flags/' + curRow[2] + '.svg'
    var menu = LANGS.map(function (l) {
      return '<a class="fy-lang-item lang-select notranslate" data-lang="' + l[0] + '" href="#googtrans(en|' + l[0] + ')"><img class="fy-lang-flag" src="./assets/flags/' + l[2] + '.svg" alt="">' + esc(l[1]) + '</a>'
    }).join('')
    return '<div class="fy-langwrap' + (mobile ? ' fy-langwrap-m' : '') + '">' +
      '<button type="button" class="fy-langbtn' + (mobile ? ' fy-langbtn-m' : (withLabel ? '' : ' fy-langbtn-sm')) + '" aria-expanded="false">' +
      '<img class="' + (mobile ? 'fy-lang-cur' : (withLabel ? 'w-[14px]' : 'h-[24px]')) + '" src="' + (mobile ? curFlag : I.en.src) + '" alt="language flag">' +
      (mobile
        ? '<span class="fy-langbtn-label">Language · ' + esc(curRow[1]) + '</span>' +
          '<svg class="fy-lang-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>'
        : (withLabel ? '<span class="fy-langbtn-label">English</span>' : '')) +
      '</button>' +
      '<div class="fy-lang-menu hidden">' + menu + '</div>' +
      '</div>'
  }

  /* ---------- 语言切换 ----------
     header/header.html 里那段 .lang-select 处理器只有它被注入进 .headerfy 时才存在，
     而不少页面（Gallery / Chat / Login / Subscriptions 等）根本没有 .headerfy，
     侧栏语言项点了就只改个 hash、不刷新也不翻译。这里自己兜底。 */
  var TRANSLATE_SRC = 'https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit'

  function currentLang() {
    var m = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/)
    if (!m) return 'en'
    var parts = decodeURIComponent(m[1]).split('/')
    return parts[parts.length - 1] || 'en'
  }

  function switchLang(code) {
    var value = '/en/' + code
    document.cookie = 'googtrans=' + value + ';path=/'
    if (location.hostname.indexOf('.') > -1) {
      document.cookie = 'googtrans=' + value + ';path=/;domain=.' + location.hostname
    }
    try {
      localStorage.setItem('agigpt-lang', location.href.split('#')[0])
      localStorage.setItem('agigpt-lang-href', '#googtrans(en|' + code + ')')
    } catch (e) { /* 隐私模式忽略 */ }
    location.hash = '#googtrans(en|' + code + ')'
    location.reload()
  }

  // 选了语言之后才需要 Google 翻译组件来套用（等价于 header.html 末尾那两段）
  function ensureTranslate() {
    if (window.__fyTranslate || !document.body) return
    window.__fyTranslate = true
    if (!document.getElementById('google_translate_element')) {
      var holder = document.createElement('div')
      holder.id = 'google_translate_element'
      holder.style.cssText = 'position:fixed;left:-9999px;top:-9999px;width:1px;height:1px;overflow:hidden'
      document.body.appendChild(holder)
    }
    window.googleTranslateElementInit = function () {
      if (!window.google || !window.google.translate || window.__fyGte) return
      window.__fyGte = true
      new google.translate.TranslateElement({ pageLanguage: 'en', autoDisplay: false }, 'google_translate_element')
      applyTranslated()
    }
    var s = document.createElement('script')
    s.src = TRANSLATE_SRC
    s.async = true
    document.body.appendChild(s)
  }

  // 组件偶尔不会自己套用 cookie/hash（Home.html 上实测到过），等语言下拉就绪后手动选一次
  function applyTranslated() {
    var code = currentLang()
    if (code === 'en') return
    var tries = 0
    var timer = setInterval(function () {
      var combo = document.querySelector('.goog-te-combo')
      if (combo && combo.options && combo.options.length) {
        clearInterval(timer)
        if (combo.value !== code) {
          combo.value = code
          combo.dispatchEvent(new Event('change', { bubbles: true }))
        }
      } else if (++tries > 40) {
        clearInterval(timer)
      }
    }, 500)
  }

  // 语言弹层：按按钮位置算 fixed 坐标（侧栏 ul 的 overflow 会把 absolute 弹层裁成一条边）
  function placeLangMenu(menu, btn, wrap) {
    if (!menu || !btn) return
    if (wrap.classList.contains('fy-langwrap-m')) return   // 移动端抽屉：菜单内联展开，不需要定位
    var r = btn.getBoundingClientRect()
    var mw = menu.offsetWidth
    var mh = menu.offsetHeight
    var collapsed = wrap.closest && wrap.closest('[data-main-target="mainSidebarClosedContainer"]')
    var left = collapsed ? r.right + 8 : r.left
    left = Math.max(8, Math.min(left, window.innerWidth - mw - 8))
    var top = r.top - 8 - mh
    if (top < 8) top = Math.max(8, Math.min(r.bottom + 8, window.innerHeight - mh - 8))
    menu.style.left = left + 'px'
    menu.style.top = top + 'px'
  }

  function hideLangMenus() {
    var menus = document.querySelectorAll('.fy-langwrap .fy-lang-menu')
    for (var i = 0; i < menus.length; i++) {
      if (menus[i].closest('.fy-langwrap-m')) continue   // 内联菜单随抽屉滚动，不隐藏
      if (menus[i].classList.contains('hidden')) continue
      menus[i].classList.add('hidden')
      var b = menus[i].parentNode.querySelector('.fy-langbtn')
      if (b) b.setAttribute('aria-expanded', 'false')
    }
  }

  /* ---------- 移动端抽屉语言区：元素级直绑（不依赖文档委托链） ----------
     背景：抽屉容器上的 Stimulus 动作与部分脚本会吞掉冒泡，
     文档级委托在抽屉内不可靠，这里把监听直接挂在按钮/菜单元素上。 */
  function bindMobileLang(root) {
    var wrap = root && root.querySelector ? root.querySelector('.fy-langwrap-m') : null
    if (!wrap || wrap.__mBound) return
    wrap.__mBound = true
    var btn = wrap.querySelector('.fy-langbtn')
    var menu = wrap.querySelector('.fy-lang-menu')
    if (btn && menu) {
      btn.addEventListener('click', function (e) {
        e.preventDefault()
        e.stopPropagation()
        var opening = menu.classList.contains('hidden')
        menu.classList.toggle('hidden')
        wrap.classList.toggle('open', opening)
        btn.setAttribute('aria-expanded', opening ? 'true' : 'false')
      })
      menu.addEventListener('click', function (e) {
        var item = e.target.closest && e.target.closest('.lang-select')
        if (!item) return
        e.preventDefault()
        e.stopPropagation()
        var m = /googtrans\([^|)]*\|([^)]+)\)/.exec(item.getAttribute('href') || '')
        var code = m ? m[1] : (item.getAttribute('data-lang') || '')
        if (code && code !== currentLang()) switchLang(code)
      })
    }
  }

  /* ---------- 移动端抽屉 ---------- */
  function drawerRows() {
    function row(n) {
      return '<li class="relative w-full">' +
        '<a class="w-full flex justify-start items-center gap-2.5"' + linkAttr(n) + '>' +
        iconHtml(n.icon, 'w-5 h-5') +
        '<span class="text-start text-grey-medium text-xs font-semibold">' + esc(n.label) + '</span>' +
        '</a></li><li class="fy-hr-li"></li>'
    }
    return NAV.map(row).join('') + NAV_BOTTOM.map(row).join('')
  }

  function mobileDrawer() {
    var menu = '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./Subscriptions.html">' +
      '<img class="w-5 h-5" src="' + I.premium.src + '" alt="Subscription">' +
      '<span class="text-grey-medium text-xs font-semibold">Subscription</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./purchasePoints.html">' +
      '<img class="w-5 h-5" src="' + I.coin.src + '" alt="Tokens">' +
      '<span class="text-grey-medium text-xs font-semibold">Tokens</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./charactersIndex.html">' +
      '<img class="w-5 h-5" src="' + I.lady.src + '" alt="My AI">' +
      '<span class="text-grey-medium text-xs font-semibold">My AI</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./upload_media.html">' +
      '<img class="w-5 h-5" src="' + I.wand.src + '" alt="Upload Media">' +
      '<span class="text-grey-medium text-xs font-semibold">Upload Media</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./achievements.html">' +
      '<img class="w-5 h-5" src="' + I.achv.src + '" alt="Achievements">' +
      '<span class="text-grey-medium text-xs font-semibold">Achievements</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./Inviter.html">' +
      '<img class="w-5 h-5" src="' + I.gift.src + '" alt="">' +
      '<span class="text-grey-medium text-xs font-semibold">Get 100 tokens</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./setting.html">' +
      '<img class="w-5 h-5" src="' + I.settings.src + '" alt="Settings">' +
      '<span class="text-grey-medium text-xs font-semibold">Settings</span></a>' +
      '<form method="post" onsubmit="return false">' +
      '<button type="submit" class="logout w-full justify-start items-center gap-2.5 inline-flex">' +
      '<img class="w-5 h-5" src="' + I.logout.src + '" alt="Logout">' +
      '<span class="text-grey-medium text-xs font-semibold">Logout</span></button></form>'

    return '<nav class="flex flex-col justify-between h-full w-60">' +
      '<div class="flex flex-col justify-between h-full">' +
      '<ul role="list" class="fy-drawer flex flex-col items-center justify-start px-5 pb-5 gap-2.5 bg-zinc-900 h-screen overflow-auto">' +

      // 我的资料（已登录）
      '<li class="fy-drawer-user login-pf relative w-full pt-2.5">' +
      '<button type="button" class="fy-drawer-userbtn user-dropdown" id="fy-drawer-user-btn" aria-expanded="false">' +
      '<span class="relative"><img class="w-6 h-6 rounded-full user-dropdown" src="' + I.man.src + '" alt="Man profile icon">' +
      '<span class="fy-drawer-dot"></span></span>' +
      '<span class="fy-drawer-userlabel user-dropdown"><span class="user-dropdown">My Profile</span>' +
      '<svg class="user-dropdown w-6 h-6 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
      '<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"></path>' +
      '</svg></span></button>' +
      '<div class="fy-drawer-menu hidden" id="fy-drawer-user-menu">' + menu + '</div></li>' +

      // 未登录：注册 / 登录
      '<li class="user-login w-full py-4 gap-2.5">' +
      '<a href="./Login.html" class="flex-1 h-8 px-2 py-1.5 bg-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer">' +
      '<span class="text-white text-sm font-semibold leading-tight">Register</span></a>' +
      '<a href="./Login.html" class="flex-1 h-8 px-2 py-1.5 border-[#E75275] border text-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer">' +
      '<span class="text-sm font-semibold leading-tight">Login</span></a></li>' +

      '<li class="fy-hr-li"></li>' +
      drawerRows() +
      '<li class="w-full">' + langButton(false, true) + '</li>' +

      '</ul></div></nav>'
  }

  /* ---------- 顶栏右上角：成就徽章 + tokens 胶囊 + 头像菜单 ---------- */
  function tokenChip() {
    return '<div class="fy-chipwrap" id="token-chip-wrap">' +
      '<button type="button" class="fy-chip" id="token-chip-button" aria-expanded="false">' +
      '<img class="fy-chip-coin" src="' + I.coin.src + '" alt="Tokens">' +
      '<span data-target="user-token-balance">0</span>' +
      '<img class="fy-chip-arrow" src="' + I.expand.src + '" alt="">' +
      '</button>' +
      '<div class="fy-chip-pop hidden" id="navbar-tokens-dropdown">' +
      '<div class="fy-chip-pop-in">' +
      row('Voice cost', '<b>1</b>/20s') +
      '<span class="fy-chip-pop-sep"></span>' +
      row('Image cost', '<b>4</b>') +
      '<span class="fy-chip-pop-sep"></span>' +
      row('Call cost', '3/min') +
      '</div>' +
      '<div class="fy-chip-pop-foot">' +
      '<a class="fy-chip-pop-cta" href="./purchasePoints.html">Buy more' +
      '<img src="' + I.addWhite.src + '" alt=""></a>' +
      '</div>' +
      '</div></div>'

    function row(label, val) {
      return '<div class="fy-chip-pop-row">' +
        '<div class="fy-chip-pop-label">' + label + '</div>' +
        '<div class="fy-chip-pop-val">' + val +
        '<img src="' + I.coin.src + '" alt=""></div></div>'
    }
  }

  function navRight() {
    var menu = '<a href="./Subscriptions.html"><img src="' + I.premium.src + '" alt=""><span>Subscription</span></a>' +
      '<a href="./purchasePoints.html"><img src="' + I.coin.src + '" alt=""><span>Tokens</span></a>' +
      '<a href="./Inviter.html"><img src="' + I.gift.src + '" alt=""><span>Get 100 tokens</span></a>' +
      '<a href="./setting.html"><img src="' + I.settings.src + '" alt=""><span>Settings</span></a>' +
      '<form method="post" onsubmit="return false">' +
      '<button data-turbo="false" id="logout" type="submit"><img src="' + I.logout.src + '" alt=""><span>Logout</span></button></form>'

    return '<div class="fy-navright">' +
      '<a class="fy-achv" href="./achievements.html" title="Achievements" aria-label="Achievements">' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M6 9a6 6 0 1 0 12 0V4H6z"></path>' +
      '<path d="M6 6H3v2a3 3 0 0 0 3 3M18 6h3v2a3 3 0 0 1-3 3"></path>' +
      '<path d="M12 15v3m-4 3h8"></path></svg>' +
      '<span class="fy-achv-tag">New</span></a>' +
      tokenChip() +
      '<div id="profileDropdownWrap" class="fy-profilewrap">' +
      '<button type="button" class="fy-avatar-btn" id="user-menu-button" aria-expanded="false">' +
      '<span class="sr-only">Open user menu</span>' +
      '<img src="' + I.user.src + '" alt="Open user menu">' +
      '<span class="fy-avatar-dot"></span></button>' +
      '<div id="profileDropdownMenu" class="fy-profile-menu" style="display:none">' + menu + '</div>' +
      '</div></div>'
  }

  /* ---------- 悬浮提示（旧包没有 navbar-tooltip 控制器） ---------- */
  var tipEl = null
  function showTip(target) {
    if (!tipEl) {
      tipEl = document.createElement('div')
      tipEl.className = 'fy-tip'
      document.body.appendChild(tipEl)
    }
    tipEl.textContent = target.getAttribute('data-fy-tip')
    tipEl.style.display = 'block'
    var r = target.getBoundingClientRect()
    var h = tipEl.offsetHeight
    tipEl.style.left = Math.round(r.right + 12) + 'px'
    tipEl.style.top = Math.round(r.top + r.height / 2 - h / 2) + 'px'
  }
  function hideTip() {
    if (tipEl) tipEl.style.display = 'none'
  }

  function bind() {
    document.addEventListener('mouseover', function (e) {
      var li = e.target.closest && e.target.closest('[data-fy-tip]')
      if (li) showTip(li)
      else hideTip()
    })
    document.addEventListener('scroll', function (e) {
      hideTip()
      // 语言弹层自身是滚动容器（11 种语言超出高度），它内部滚动不该把它收起来；
      // 移动端抽屉里的语言菜单是内联展开的，抽屉滚动也不应收起（否则刚点开就被惯性滚动收掉）
      if (e.target && e.target.closest && (e.target.closest('.fy-lang-menu') || e.target.closest('.fy-langwrap-m'))) return
      hideLangMenus()
    }, true)

    // 头像下拉
    document.addEventListener('click', function (e) {
      var btn = document.getElementById('user-menu-button')
      var menu = document.getElementById('profileDropdownMenu')
      if (btn && menu) {
        if (btn.contains(e.target)) {
          var open = menu.style.display !== 'block'
          menu.style.display = open ? 'block' : 'none'
          btn.setAttribute('aria-expanded', open ? 'true' : 'false')
        } else if (!menu.contains(e.target)) {
          menu.style.display = 'none'
          btn.setAttribute('aria-expanded', 'false')
        }
      }
      closePopovers(e)
    })

    // 移动端抽屉语言区：捕获阶段拦截点击。
    // 抽屉容器上挂着 Stimulus 动作（click->main#setMobileSidebarValueToDefault），
    // 点语言按钮会把整个抽屉收起、菜单跟着消失（表现为"点不动"），
    // 所以这里 stopPropagation 断掉冒泡，并手动执行同一套开合逻辑；
    // 菜单项（.lang-select）不在拦截范围内，正常冒泡走文档级切换逻辑。
    document.addEventListener('click', function (e) {
      var w = e.target.closest && e.target.closest('.fy-langwrap-m')
      if (!w || (e.target.closest && e.target.closest('.fy-lang-menu'))) return
      e.stopPropagation()
      var m = w.querySelector('.fy-lang-menu')
      var b = w.querySelector('.fy-langbtn')
      if (!m) return
      var opening = m.classList.contains('hidden')
      m.classList.toggle('hidden')
      w.classList.toggle('open', opening)
      if (b) b.setAttribute('aria-expanded', opening ? 'true' : 'false')
    }, true)

    // 语言下拉 / 抽屉资料菜单
    document.addEventListener('click', function (e) {
      var wrap = e.target.closest && e.target.closest('.fy-langwrap')
      document.querySelectorAll('.fy-langwrap').forEach(function (w) {
        var m = w.querySelector('.fy-lang-menu')
        var b = w.querySelector('.fy-langbtn')
        if (!m) return
        if (w === wrap && !e.target.closest('.fy-lang-menu')) {
          var opening = m.classList.contains('hidden')
          m.classList.toggle('hidden')
          w.classList.toggle('open', opening)
          if (opening) placeLangMenu(m, b, w)   // 先显示再量高度，同一帧内不会闪
          if (b) b.setAttribute('aria-expanded', opening ? 'true' : 'false')
        } else if (!w.contains(e.target)) {
          m.classList.add('hidden')
          if (b) b.setAttribute('aria-expanded', 'false')
        }
      })
      var db = document.getElementById('fy-drawer-user-btn')
      var dm = document.getElementById('fy-drawer-user-menu')
      if (db && dm && db.contains(e.target)) dm.classList.toggle('hidden')
    })

    // 语言项：自己完成切换（不依赖 header.html 的处理器是否注入成功）
    document.addEventListener('click', function (e) {
      var item = e.target.closest && e.target.closest('.lang-select')
      if (!item) return
      var m = /googtrans\([^|)]*\|([^)]+)\)/.exec(item.getAttribute('href') || '')
      var code = m ? m[1] : (item.getAttribute('data-lang') || '')
      if (!code) return
      // 先阻止默认的 hash 跳转：Turbo 页面（Subscriptions）会把它当成一次访问，把侧栏整块换掉
      e.preventDefault()
      if (code === currentLang()) return
      switchLang(code)
    })

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Escape') return
      hideTip()
      closePopovers()
    })
  }

  function closePopovers(e) {
    var chipWrap = document.getElementById('token-chip-wrap')
    if (chipWrap && e && chipWrap.contains(e.target)) return
    var pop = document.getElementById('navbar-tokens-dropdown')
    if (pop) pop.classList.add('hidden')
    if (chipWrap) chipWrap.classList.remove('open')
    var chipBtn = document.getElementById('token-chip-button')
    if (chipBtn) chipBtn.setAttribute('aria-expanded', 'false')
  }

  /* ---------- 会话管理：token 续期 + 登录态同步 ----------
   * 背景：access_token 只有 2 小时（服务端 JwtService ACCESS_TTL），
   *      而前端一直没使用 7 天有效的 refresh_token 续期，
   *      且登录态 UI 只看 localStorage 里有没有 token、不校验有效性，
   *      结果就是「token 悄悄过期、界面还显示已登录，点什么都报 Session expired」。
   */
  var LiveAuth = (function () {
    var K_TOKEN   = 'live_access_token'
    var K_REFRESH = 'live_refresh_token'
    var K_EXPIRE  = 'live_token_expires_at'
    var REFRESH_URL = '/api/live/refreshToken'   // 注意是驼峰：下划线形式 /refresh_token 会 404
    var THRESHOLD = 10 * 60 * 1000   // 剩余不足 10 分钟就提前续期
    var RELOAD_WINDOW = 20 * 1000    // 自动重载的最小间隔
    var MAX_RELOADS = 2              // 单个标签页内自动重载次数上限，避免个别接口持续报未登录时反复重载
    var K_RELOAD_AT = 'live_auth_reload_at'
    var K_RELOAD_CNT = 'live_auth_reload_cnt'
    var pending = null

    function read(k) { try { return localStorage.getItem(k) || '' } catch (e) { return '' } }
    function write(k, v) { try { localStorage.setItem(k, v) } catch (e) { /* 隐私模式下忽略 */ } }
    function drop(k) { try { localStorage.removeItem(k) } catch (e) { /* 隐私模式下忽略 */ } }
    function readS(k) { try { return sessionStorage.getItem(k) || '' } catch (e) { return '' } }
    function writeS(k, v) { try { sessionStorage.setItem(k, v) } catch (e) { /* 忽略 */ } }
    function dropS(k) { try { sessionStorage.removeItem(k) } catch (e) { /* 忽略 */ } }

    function token() { return read(K_TOKEN) }
    function hasRefresh() { return !!read(K_REFRESH) }

    function canReload() {
      if ((parseInt(readS(K_RELOAD_CNT), 10) || 0) >= MAX_RELOADS) return false
      return Date.now() - (parseInt(readS(K_RELOAD_AT), 10) || 0) > RELOAD_WINDOW
    }

    function markReload() {
      writeS(K_RELOAD_AT, String(Date.now()))
      writeS(K_RELOAD_CNT, String((parseInt(readS(K_RELOAD_CNT), 10) || 0) + 1))
    }

    function resetReloadGuard() {
      dropS(K_RELOAD_AT)
      dropS(K_RELOAD_CNT)
    }

    /** 从 JWT 自身解析 exp（毫秒）；解析失败返回 0 */
    function tokenExp() {
      var parts = read(K_TOKEN).split('.')
      if (parts.length < 2) return 0
      try {
        var pad = parts[1].replace(/-/g, '+').replace(/_/g, '/')
        while (pad.length % 4) pad += '='
        var exp = parseInt(JSON.parse(atob(pad)).exp, 10)
        return exp > 0 ? exp * 1000 : 0
      } catch (e) {
        return 0
      }
    }

    /** 剩余有效毫秒；以 JWT 自身的 exp 为准，取不到再退回本地记录的时间；都没有返回 null */
    function remain() {
      var jwtExp = tokenExp()
      if (jwtExp > 0) return jwtExp - Date.now()
      var v = parseInt(read(K_EXPIRE), 10)
      return v > 0 ? v - Date.now() : null
    }

    function expired() {
      var r = remain()
      return r !== null && r <= 0
    }

    /** 登录/注册成功后落地整个会话（含 refresh_token 与到期时间） */
    function save(data, isLogin) {
      if (!data || !data.access_token) return
      write(K_TOKEN, data.access_token)
      if (data.refresh_token) write(K_REFRESH, data.refresh_token)
      var ttl = parseInt(data.expires_in, 10)
      if (ttl > 0) write(K_EXPIRE, String(Date.now() + ttl * 1000))
      else drop(K_EXPIRE)
      if (isLogin) resetReloadGuard()   // 重新登录后清掉自动重载的节流计数
    }

    function clear() {
      drop(K_TOKEN)
      drop(K_REFRESH)
      drop(K_EXPIRE)
      drop('live_user_info')
      drop('live_user')
    }

    /** 续期：同一标签页内单飞；跨标签页用 localStorage 锁避让，
     *  因为服务端 refresh 会轮换 refresh_token，两个标签页同时刷会让其中一个失效 */
    function refresh() {
      if (pending) return pending
      pending = doRefresh(false).then(function (ok) { pending = null; return ok })
      return pending
    }

    var K_LOCK = 'live_token_refresh_lock'

    function wait(ms) {
      return new Promise(function (resolve) { setTimeout(resolve, ms) })
    }

    function doRefresh(retried) {
      var rt = read(K_REFRESH)
      if (!rt) return Promise.resolve(false)

      var lockTs = parseInt(read(K_LOCK), 10)
      if (!retried && lockTs > 0 && Date.now() - lockTs < 15000) {
        // 另一个标签页正在续期：等它把新 token 写进来
        return wait(1500).then(function () {
          var r = remain()
          if (r !== null && r > THRESHOLD) return true
          return doRefresh(true)
        })
      }
      write(K_LOCK, String(Date.now()))

      return fetch(REFRESH_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ refresh_token: rt })
      }).then(function (r) { return r.json() }).then(function (res) {
        if (res && res.code === '00000' && res.data && res.data.access_token) {
          save(res.data)
          return true
        }
        return false
      }).catch(function () { return false })
    }

    /** 接口返回 A0100/A0101 时的统一处理 */
    function onUnauthorized() {
      if (!token()) return

      // 本地已经是新鲜 token（刚被本页或其他标签页续过），只是本页脚本还在用旧的：直接重载
      var r = remain()
      if (r !== null && r > THRESHOLD) {
        if (canReload()) { markReload(); location.reload() }
        return
      }

      var before = token()
      refresh().then(function (ok) {
        if (!ok) {
          // refresh_token 也过期/被吊销：清会话并把界面同步成未登录
          clear()
          syncAuth()
          if (window.layer && layer.msg) layer.msg('Session expired. Please sign in again.')
          return
        }
        if (token() === before) return          // 没换来新 token（别的标签页刚续过），无需重载
        if (!canReload()) return                // 重载次数已达上限仍报未登录：不是 token 的问题，不再折腾
        markReload()
        location.reload()                       // 整页重载一次，让各页面脚本用新 token 重新取数
      })
    }

    /** 页面加载与定时巡检：快到期就提前续，续不动就同步成未登录态 */
    function ensureFresh() {
      if (!token()) return
      var r = remain()
      if (r === null || r > THRESHOLD) return
      refresh().then(function (ok) {
        if (!ok) { clear(); syncAuth() }
      })
    }

    return {
      token: token,
      hasRefresh: hasRefresh,
      remain: remain,
      expired: expired,
      save: save,
      clear: clear,
      refresh: refresh,
      onUnauthorized: onUnauthorized,
      ensureFresh: ensureFresh
    }
  })()
  window.LiveAuth = LiveAuth

  /* ---------- 未登录统一拦截：任何 /api 响应带 A0100/A0101 都触发续期或同步 ---------- */
  function isAuthError(res) {
    if (!res || typeof res !== 'object') return false
    var code = String(res.code || '')
    return code === 'A0100' || code === 'A0101'
  }

  function inspectText(text) {
    if (typeof text !== 'string' || text.indexOf('A010') < 0) return
    try { if (isAuthError(JSON.parse(text))) LiveAuth.onUnauthorized() } catch (e) { /* 非 JSON 忽略 */ }
  }

  function bindAuthWatch() {
    // jQuery 请求（站内绝大多数接口调用）
    if (window.jQuery) {
      jQuery(document).ajaxComplete(function (e, xhr) {
        if (xhr && typeof xhr.responseText === 'string') inspectText(xhr.responseText)
      })
    }
    // 原生 fetch（shorts.js / purchasePoints.js 等）
    if (window.fetch && !window.__fyFetchWrapped) {
      window.__fyFetchWrapped = true
      var rawFetch = window.fetch.bind(window)
      window.fetch = function () {
        var p = rawFetch.apply(null, arguments)
        p.then(function (resp) {
          try {
            if (!resp || !resp.url || resp.url.indexOf('/api/') < 0) return
            resp.clone().text().then(inspectText).catch(function () { /* 忽略 */ })
          } catch (e) { /* clone 失败忽略 */ }
        }, function () { /* 网络错误忽略 */ })
        return p
      }
    }
  }

  var authTimerStarted = false
  function startAuthTimer() {
    if (authTimerStarted) return
    authTimerStarted = true
    setInterval(function () { LiveAuth.ensureFresh() }, 5 * 60 * 1000)
  }

  /* ---------- 登录态：与 js/main.js 的 initMain 一致（少数页面没有引 main.js） ---------- */
  function syncAuth() {
    var token = LiveAuth.token()
    // 已过期且没有 refresh_token 可续：直接按未登录渲染，避免「显示已登录但接口全失效」
    if (token && LiveAuth.expired() && !LiveAuth.hasRefresh()) {
      LiveAuth.clear()
      token = ''
    }
    each('.user-login', token ? 'none' : 'flex')
    each('.login-pf', token ? 'block' : 'none')
    each('.pc-rg-login', token ? 'none' : 'flex')

    // 少数页面（Gallery / create）顶栏没有未登录态的 Register/Login 块，右上角保持常显
    var boxes = document.querySelectorAll('.user-login, #user-login')
    var hasLoginBox = false
    for (var i = 0; i < boxes.length; i++) {
      // 排除移动端抽屉里我们自己渲染的那块
      if (!(boxes[i].closest && boxes[i].closest('.fy-drawer'))) { hasLoginBox = true; break }
    }
    var us = document.getElementById('user-setting')
    if (us) us.style.display = (token || !hasLoginBox) ? 'flex' : 'none'
    var login = document.getElementById('user-login')
    if (login) login.style.display = token ? 'none' : 'flex'
  }
  function each(sel, display) {
    var list = document.querySelectorAll(sel)
    for (var i = 0; i < list.length; i++) list[i].style.display = display
  }

  /* ---------- 顶栏右侧容器：优先用既有的 #user-setting；
     少数页面（Gallery / create）没有该容器，就地把旧的「Subscribe 75% OFF + My Profile」整块改成它 ---------- */
  function findUserSetting(topbar) {
    var us = document.getElementById('user-setting')
    if (us) return us
    if (!topbar) return null
    var all = topbar.querySelectorAll('*')
    for (var i = 0; i < all.length; i++) {
      var el = all[i]
      if (el.children.length) continue
      if (!/My Profile|Subscribe 75% OFF/.test(el.textContent || '')) continue
      var node = el
      while (node.parentElement && node.parentElement !== topbar) node = node.parentElement
      if (node.parentElement === topbar) {
        node.id = 'user-setting'
        node.style.display = 'none'
        return node
      }
    }
    return null
  }

  /* ---------- 渲染入口 ---------- */
  function render() {
    var closed = document.querySelector('[data-main-target="mainSidebarClosedContainer"]')
    var opened = document.querySelector('[data-main-target="mainSidebarOpenedContainer"]')
    var drawer = document.querySelector('[data-main-target="mainMobileSidebarContainer"]')
    if (!closed && !opened && !drawer) return false

    if (closed) closed.innerHTML = collapsedSidebar()
    if (opened) opened.innerHTML = expandedSidebar()
    if (drawer) {
      drawer.classList.remove('mt-[64px]')
      drawer.innerHTML = mobileDrawer()
      bindMobileLang(drawer)
    }

    // 自带新版外壳的页面（Subscriptions.html）：右上角也换成同一套，并摘掉它原本的成就徽章/胶囊/头像块
    var builtInBar = document.querySelector('[data-testid="new-shell-navbar"]')
    if (builtInBar) {
      var anchor = builtInBar.querySelector('[data-testid="achievements-navbar-badge"]')
      var rightSlot = anchor && anchor.parentElement
      if (rightSlot) {
        rightSlot.id = 'user-setting'
        rightSlot.style.display = 'flex'
        rightSlot.innerHTML = navRight()
      }
      var legacyProfile = builtInBar.querySelectorAll('[data-testid="new-shell-profile-button-desktop"]')
      for (var k = 0; k < legacyProfile.length; k++) legacyProfile[k].parentNode.removeChild(legacyProfile[k])
      return true
    }

    // 顶栏右上角：统一成新版形态（并修正 #user-setting 落在顶栏之外的历史结构）
    var hamburger = document.getElementById('nav-hamburger')
    var topbar = hamburger && hamburger.parentElement
    var us = findUserSetting(topbar)
    if (us) {
      if (topbar && us.parentElement !== topbar) topbar.appendChild(us)
      us.innerHTML = navRight()
    }
    return true
  }

  function run() {
    try {
      var __bound = render()
      if (__bound) bind()
      syncAuth()
      bindAuthWatch()
      LiveAuth.ensureFresh()
      startAuthTimer()
      if (currentLang() !== 'en') ensureTranslate()
    } finally {
      document.documentElement.classList.remove('fy-shell-pending')
    }
  }

  document.documentElement.classList.add('fy-shell-pending')
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run)
  } else {
    run()
  }
})()
