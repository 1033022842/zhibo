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
    { href: './Girls.html', label: 'Explore', icon: 'explore' },
    { href: './Chat.html', label: 'Chat', icon: 'chat' },
    { href: './Gallery.html', label: 'Gallery', icon: 'gallery' },
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
    { href: './Game.html', label: 'Games', icon: 'games' },
    { href: './index.html', label: 'Crowdfunding', icon: 'crowd' }
  ]

  // 侧边栏底部：沿用站点原有三项
  var NAV_BOTTOM = [
    { href: 'mailto:support@sugusmail.ai', label: 'Contact Us', icon: 'contact', attr: ' data-contact-us="1"' },
    { href: './Inviter.html', label: 'Affiliate Program', icon: 'affiliate' },
    { href: './Subscriptions.html', label: 'Become Premium', icon: 'premium' }
  ]

  // 语言列表（与 header/header.html 的下拉一致，复用 .lang-select 的既有逻辑）
  var LANGS = [
    ['vi', 'Tiếng Việt'],
    ['ms', 'Melayu'],
    ['th', 'แบบไทย'],
    ['km', 'ភាសាខ្មែរ'],
    ['lo', 'ພາສາລາວ'],
    ['en', 'English'],
    ['zh-CN', '中文'],
    ['ja', '日本語'],
    ['ko', '한국어'],
    ['ru', 'Русский'],
    ['es', 'español']
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
  function langButton(withLabel) {
    var menu = LANGS.map(function (l) {
      return '<a class="fy-lang-item lang-select notranslate" data-lang="' + l[0] + '" href="#googtrans(en|' + l[0] + ')">' + esc(l[1]) + '</a>'
    }).join('')
    return '<div class="fy-langwrap">' +
      '<button type="button" class="fy-langbtn' + (withLabel ? '' : ' fy-langbtn-sm') + '" aria-expanded="false">' +
      '<img class="' + (withLabel ? 'w-[14px]' : 'h-[24px]') + '" src="' + I.en.src + '" alt="en flag icon">' +
      (withLabel ? '<span class="fy-langbtn-label">English</span>' : '') +
      '</button>' +
      '<div class="fy-lang-menu hidden">' + menu + '</div>' +
      '</div>'
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
      '<li class="w-full">' + langButton(false) + '</li>' +

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
      '<a href="./charactersIndex.html"><img src="' + I.lady.src + '" alt=""><span>My AI</span></a>' +
      '<a href="./upload_media.html"><img src="' + I.wand.src + '" alt=""><span>Upload Media</span></a>' +
      '<a href="./Inviter.html"><img src="' + I.gift.src + '" alt=""><span>Get 100 tokens</span></a>' +
      '<a href="./setting.html"><img src="' + I.settings.src + '" alt=""><span>Settings</span></a>' +
      '<form method="post" onsubmit="return false">' +
      '<button data-turbo="false" id="logout" type="submit"><img src="' + I.logout.src + '" alt=""><span>Logout</span></button></form>'

    return '<div class="fy-navright">' +
      '<a class="fy-achv" href="./Game.html" title="Achievements" aria-label="Achievements">' +
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
    document.addEventListener('scroll', hideTip, true)

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

    // 语言下拉 / 抽屉资料菜单
    document.addEventListener('click', function (e) {
      var wrap = e.target.closest && e.target.closest('.fy-langwrap')
      document.querySelectorAll('.fy-langwrap').forEach(function (w) {
        var m = w.querySelector('.fy-lang-menu')
        var b = w.querySelector('.fy-langbtn')
        if (w === wrap && !e.target.closest('.fy-lang-menu')) {
          m.classList.toggle('hidden')
          if (b) b.setAttribute('aria-expanded', m.classList.contains('hidden') ? 'false' : 'true')
        } else if (!w.contains(e.target)) {
          m.classList.add('hidden')
          if (b) b.setAttribute('aria-expanded', 'false')
        }
      })
      var db = document.getElementById('fy-drawer-user-btn')
      var dm = document.getElementById('fy-drawer-user-menu')
      if (db && dm && db.contains(e.target)) dm.classList.toggle('hidden')
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

  /* ---------- 登录态：与 js/main.js 的 initMain 一致（少数页面没有引 main.js） ---------- */
  function syncAuth() {
    var token = null
    try { token = localStorage.getItem('live_access_token') } catch (e) { /* 隐私模式下忽略 */ }
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
      if (render()) bind()
      syncAuth()
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
