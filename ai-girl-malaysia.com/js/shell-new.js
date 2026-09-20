/**
 * 新版外壳渲染（侧边栏 + 顶栏右上角）
 * 目标形态：Subscriptions.html / tokens.html 使用的新版外壳。
 *
 * 做法：只替换旧页面里「侧边栏 nav 的内容」与「#user-setting 的内容」，
 *      外层容器及其 data-main-target / webkit-transform-sidebar / pc-nav 全部保留，
 *      因此旧包的展开·收起逻辑（main_controller.js 的 toggleSidebar）继续生效。
 *      已经是新版外壳的页面（带 data-testid="new-shell-*"）直接跳过。
 */
(function () {
  'use strict'

  var T = './taocan_files/'
  var I = {
    home: T + 'home-18a7d73adabda5674f78dab4a3d6b3acebbdbba31f0ad19053537dabffade496.svg',
    explore: T + 'explore-3a4a39d3c6126c743969aa14b3a4841763f5b600e0f77e317a32d7c30afbcc13.svg',
    shorts: T + 'shorts-dd7eca01fe7f8a694cd100be90b5b6c20d241a5bf4c8b8c0f8633864125cffae.svg',
    candy: T + 'candy-shop-d549838a0f3746c8cdf58c1cf0684d40990ec9b275fa8ac042c47379182965d1.svg',
    chat: T + 'chat-1f356f466c9023c851a3a185fd1e607229737246758457a3f49b82e17bc5d82b.svg',
    gallery: T + 'gallery-b83c72cb24c980d5e254daba38d4e3690f281b07c3e5665a052e0f76fff031b8.svg',
    trophy: T + 'trophy-55ba15bcf830427b6fc776e11ff4465cce75c1c2a1a27ba0bd2eea0e5c03ff55.svg',
    wand: T + 'magic-wand-bc43a2a37c108cc6308370c0e6fada5385fd848143063f66116694fd9aa075b7.svg',
    lady: T + 'love-lady-760605daa886b7e49e77d5d26c1047bc0f815c005a978a5c76810db20bc355dd.svg',
    lock: T + 'heart-lock-c322a04a0a80c5ca1b50658063120fa33d2f209b5e0980e7be8e47cc9667f736.svg',
    token: T + 'token-4193ee36c35274edacea995ff69347bb8d3e75f4094143958306eac195fe9a83.svg',
    coin: T + 'token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg',
    expand: T + 'expand-cd76d60d9e4264d89102db00cc87d69cb1ecd6c96663e5cea3809a90dd47a9ab.svg',
    addWhite: T + 'add_white-d504ef86384a5fe4014170fa04483735c82f42d80301af36e1c50f979a2c2245.svg',
    user: T + 'user-136475e1e7120a61ab13061e84c88cdf96837d2fb8eafda7a8c5b73815512f42.svg',
    man: T + 'man-f06d242ecbaa154c05843d8e91f779c41dbef390319247606b1919b87169c74a.svg',
    premium: T + 'premium-3fa8ec9f951178508109d03de202bc4a381ed87451b7dc9f8aa5a931bd485e02.svg',
    gift: T + 'icon_gift-21fcbe4f1b1c5a97716a7735e28f760b007f6f05d08c337f2b1d16a56d6aa48d.svg',
    settings: T + 'settings-087d65eb2d9a7508f04c379467f39735f971ac6299872f159da8e6c1d735a710.svg',
    logout: T + 'logout-c5bab4efa12211db16618320220423a09cf3d7f5b562be32cdd5110df2a21c18.svg',
    en: T + 'en-337bbb31fb6de773da4d64ab2e90f99d4706f32ade21156a47b8603dfeccb1e4.svg',
    discord: T + 'discord-526bbe3abf7210fa8bd8e76658d4df5a5c67d569f1349a2441b1d0bbd170c84d.svg',
    help: T + 'help-center-1abd5651dab8abd889622a19eb9821ab4c19bd275da4ac1b9717820842a98dd8.svg',
    contact: T + 'contact-c50065a887c8d0061543beceae78cec1bdaaac55ec7d37f11601912b3250e119.svg',
    arrow: T + 'ask-arrow-5e9e3d502459a09cc6f7a6face21c887f7e5ff747265bdf33f1cc5eb16098469.svg'
  }

  // 导航项： [key, href, 文案, 图标, 角标(new|badge|invert|'')]
  var NAV = [
    ['home', './Home.html', 'Home', I.home, ''],
    ['discover', './posts.html', 'Discover', I.explore, ''],
    ['shorts', './shorts.html', 'Shorts', I.shorts, ''],
    ['candy-shop', './shop.html', 'Candy Shop', I.candy, 'new'],
    ['chat', './Chat.html', 'Chat', I.chat, 'badge'],
    ['gallery', './Gallery.html', 'Collection', I.gallery, ''],
    ['achievements', './Game.html', 'Achievements', I.trophy, 'invert'],
    ['character', './characters.html', 'Create Character', I.wand, ''],
    ['my_ai', './charactersIndex.html', 'My AI', I.lady, ''],
    ['private_content', './private_content.html', 'Private Content', I.lock, ''],
    ['tokens', './tokens.html', 'Buy Tokens', I.token, '']
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

  var CONTACT_HREF = 'mailto:support@sugusmail.ai'
  var DISCORD_HREF = 'https://discord.com/invite/candyai'
  var AFFILIATE_HREF = 'https://webforms.pipedrive.com/f/bYSTErXD8pcDoco3iakbUUKFRtWtAjNDJphn0GpYA3B88R2NYvCzVMM2wsNCjtVMkP'
  var HELP_HREF = 'https://everai.zendesk.com/hc/en-us'
  var TRUST_HREF = 'https://helloeverai.zendesk.com/'

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    })
  }

  function icon(item, size) {
    var cls = size + (item[4] === 'invert' ? ' shrink-0 brightness-0 invert' : '')
    return '<img class="' + cls + '" src="' + item[3] + '" alt="' + esc(item[2]) + '">'
  }

  // abs=true：收起态，绝对定位压在图标右上角；abs=false：展开态/抽屉，行内右对齐
  function badge(abs) {
    return '<span class="fy-badge ' + (abs ? 'fy-badge-abs' : 'fy-badge-row') + '"><span>5</span></span>'
  }

  function newTag() {
    return '<span class="fy-new">NEW</span>'
  }

  /* ---------- 收起态 100px ---------- */
  function collapsedSidebar() {
    var items = NAV.map(function (n) {
      var inner
      if (n[4] === 'badge') {
        inner = '<span class="relative">' + icon(n, 'w-6 h-6') + badge(true) + '</span>'
      } else {
        inner = icon(n, 'w-6 h-6')
      }
      return '<li class="relative w-full" data-fy-tip="' + esc(n[2]) + '">' +
        '<a class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer" href="' + n[1] + '">' +
        inner + '</a></li>'
    }).join('')

    return '<nav class="flex flex-col justify-between h-full">' +
      '<div class="flex flex-col">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6">' + items + '</ul>' +
      '</div>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end">' +
      '<li class="relative w-full">' + langButton(false) + '</li>' +
      linkLi(I.discord, 'Discord', 'h-[48px] w-[52px] mx-auto', DISCORD_HREF, true) +
      '<li class="relative w-full" data-fy-tip="Contact Us">' +
      '<a class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer" href="' + CONTACT_HREF + '">' +
      '<img class="w-6 h-6" src="' + I.contact + '" alt="Contact Us"></a></li>' +
      linkLi(I.trophy, 'Affiliate', 'h-[48px] w-[52px] mx-auto shrink-0 brightness-0 invert', AFFILIATE_HREF, true) +
      '</ul></nav>'
  }

  function linkLi(iconSrc, label, sizeCls, href, blank) {
    return '<li class="relative w-full" data-fy-tip="' + esc(label) + '">' +
      '<a class="hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 cursor-pointer ' + sizeCls + '"' +
      ' href="' + href + '"' + (blank ? ' target="_blank" rel="noopener noreferrer"' : '') + '>' +
      '<img class="w-6 h-6' + (sizeCls.indexOf('invert') > -1 ? ' shrink-0 brightness-0 invert' : '') + '" src="' + iconSrc + '" alt="' + esc(label) + '"></a></li>'
  }

  /* ---------- 展开态 220px ---------- */
  function expandedSidebar() {
    var items = NAV.map(function (n) {
      var inner
      if (n[4] === 'badge') {
        inner = '<img class="w-4 h-4" src="' + n[3] + '" alt="' + esc(n[2]) + '">' +
          '<span class="text-start text-grey-medium text-xxs font-medium leading-4">' + esc(n[2]) + '</span>' + badge(false)
      } else {
        inner = icon(n, 'w-4 h-4') +
          '<span class="text-start text-grey-medium text-xxs font-medium leading-4' + (n[4] === 'new' ? ' truncate' : '') + '">' + esc(n[2]) + '</span>' +
          (n[4] === 'new' ? newTag() : '')
      }
      return '<li class="relative w-full">' +
        '<a class="h-[40px] w-full hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer" href="' + n[1] + '">' +
        inner + '</a></li>'
    }).join('')

    var mid = '<li class="relative w-full">' + langButton(true) + '</li>' +
      '<li class="relative w-full">' +
      '<a class="h-[40px] hover:bg-zinc-700 rounded-[10px] border border-white/10 justify-start px-3 items-center gap-2 flex cursor-pointer w-full" href="' + DISCORD_HREF + '" target="_blank" rel="noopener noreferrer">' +
      '<img class="w-4 h-4" src="' + I.discord + '" alt="Discord">' +
      '<span class="text-start text-grey-medium text-xs font-medium leading-4">Discord</span></a></li>' +
      '<li class="relative w-full">' +
      '<a class="h-[40px] hover:bg-zinc-700 rounded-[10px] border border-white/10 justify-start px-3 items-center gap-2 flex cursor-pointer w-full" href="' + HELP_HREF + '" target="_blank" rel="noopener noreferrer">' +
      '<img class="w-4 h-4" src="' + I.help + '" alt="Help Center">' +
      '<span class="text-start text-grey-medium text-xs font-medium leading-4">Help Center</span></a></li>' +
      '<li class="relative w-full">' +
      '<a class="h-[40px] hover:bg-zinc-700 rounded-[10px] border border-white/10 justify-start px-3 items-center gap-2 flex cursor-pointer w-full" href="' + CONTACT_HREF + '">' +
      '<img class="w-4 h-4" src="' + I.contact + '" alt="Contact Us">' +
      '<span class="text-start text-grey-medium text-xs font-medium leading-4">Contact Us</span></a></li>' +
      '<li class="relative w-full">' +
      '<a class="hover:bg-zinc-700 rounded-[10px] border border-white/10 justify-start px-3 items-center gap-2 flex cursor-pointer w-full h-[40px]" href="' + AFFILIATE_HREF + '" target="_blank" rel="noopener noreferrer">' +
      '<img class="w-4 h-4 shrink-0 brightness-0 invert" src="' + I.trophy + '" alt="Affiliate">' +
      '<span class="text-start text-grey-medium text-xs font-medium leading-4">Affiliate</span></a></li>' +
      '<li class="fy-legal"><a href="./setting.html">Legal Terms</a>' +
      '<span class="fy-legal-dot"></span>' +
      '<a href="' + TRUST_HREF + '" target="_blank" rel="noopener noreferrer">Trust &amp; Safety</a></li>'

    return '<nav class="flex flex-col justify-between h-full overflow-hidden">' +
      '<div class="flex flex-col flex-1 min-h-0">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6 flex-1 min-h-0 overflow-y-auto">' + items + '</ul>' +
      '</div>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end shrink-0">' + mid + '</ul>' +
      '</nav>'
  }

  /* ---------- 语言切换按钮 + 下拉 ---------- */
  function langButton(withLabel) {
    var menu = LANGS.map(function (l) {
      return '<a class="fy-lang-item lang-select notranslate" data-lang="' + l[0] + '" href="#googtrans(en|' + l[0] + ')">' + esc(l[1]) + '</a>'
    }).join('')
    return '<div class="fy-langwrap">' +
      '<button type="button" class="fy-langbtn' + (withLabel ? '' : ' fy-langbtn-sm') + '" aria-expanded="false">' +
      '<img class="' + (withLabel ? 'w-[14px]' : 'h-[24px]') + '" src="' + I.en + '" alt="en flag icon">' +
      (withLabel ? '<span class="fy-langbtn-label">English</span>' : '') +
      '</button>' +
      '<div class="fy-lang-menu hidden">' + menu + '</div>' +
      '</div>'
  }

  /* ---------- 移动端抽屉 ---------- */
  function drawerItems() {
    return NAV.map(function (n) {
      var inner
      if (n[4] === 'badge') {
        inner = '<img class="w-5 h-5" src="' + n[3] + '" alt="' + esc(n[2]) + '">' +
          '<span class="text-start text-grey-medium text-xs font-semibold">' + esc(n[2]) + '</span>' + badge(false)
      } else {
        inner = '<img class="w-5 h-5' + (n[4] === 'invert' ? ' shrink-0 brightness-0 invert' : '') + '" src="' + n[3] + '" alt="' + esc(n[2]) + '">' +
          '<span class="text-start text-grey-medium text-xs font-semibold">' + esc(n[2]) + '</span>' +
          (n[4] === 'new' ? newTag() : '')
      }
      return '<li class="relative w-full">' +
        '<a class="w-full flex justify-start items-center gap-2.5" href="' + n[1] + '">' + inner + '</a></li>' +
        '<li class="fy-hr-li"></li>'
    }).join('')
  }

  function mobileDrawer() {
    var menu = '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./Subscriptions.html">' +
      '<img class="w-5 h-5" src="' + I.premium + '" alt="Subscription">' +
      '<span class="text-grey-medium text-xs font-semibold">Subscription</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./tokens.html">' +
      '<img class="w-5 h-5" src="' + I.coin + '" alt="Tokens">' +
      '<span class="text-grey-medium text-xs font-semibold">Tokens</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./charactersIndex.html">' +
      '<img class="w-5 h-5" src="' + I.lady + '" alt="My AI">' +
      '<span class="text-grey-medium text-xs font-semibold">My AI</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./upload_media.html">' +
      '<img class="w-5 h-5" src="' + I.wand + '" alt="Upload Media">' +
      '<span class="text-grey-medium text-xs font-semibold">Upload Media</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./Inviter.html">' +
      '<img class="w-5 h-5" src="' + I.gift + '" alt="">' +
      '<span class="text-grey-medium text-xs font-semibold">Get 100 tokens</span></a>' +
      '<a class="w-full justify-start items-center gap-2.5 inline-flex" href="./setting.html">' +
      '<img class="w-5 h-5" src="' + I.settings + '" alt="Settings">' +
      '<span class="text-grey-medium text-xs font-semibold">Settings</span></a>' +
      '<form method="post" onsubmit="return false">' +
      '<button type="submit" class="logout w-full justify-start items-center gap-2.5 inline-flex">' +
      '<img class="w-5 h-5" src="' + I.logout + '" alt="Logout">' +
      '<span class="text-grey-medium text-xs font-semibold">Logout</span></button></form>'

    return '<nav class="flex flex-col justify-between h-full w-60">' +
      '<div class="flex flex-col justify-between h-full">' +
      '<ul role="list" class="fy-drawer flex flex-col items-center justify-start px-5 pb-5 gap-2.5 bg-zinc-900 h-screen overflow-auto">' +

      // 我的资料（已登录）
      '<li class="fy-drawer-user login-pf relative w-full pt-2.5">' +
      '<button type="button" class="fy-drawer-userbtn user-dropdown" id="fy-drawer-user-btn" aria-expanded="false">' +
      '<span class="relative"><img class="w-6 h-6 rounded-full user-dropdown" src="' + I.man + '" alt="Man profile icon">' +
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
      drawerItems() +

      // 底部：Discord / Contact / Affiliate / 语言 / 法务
      '<li class="fy-drawer-foot w-full">' +
      '<div class="fy-drawer-foot-row">' +
      '<a class="fy-dpill" href="' + DISCORD_HREF + '" target="_blank" rel="noopener noreferrer"><span>Discord</span></a>' +
      '<a class="fy-dpill" href="' + CONTACT_HREF + '"><span>Contact Us</span></a>' +
      '</div>' +
      '<div class="fy-drawer-foot-row">' +
      '<a class="fy-dpill" href="' + AFFILIATE_HREF + '" target="_blank" rel="noopener noreferrer"><span>Affiliate</span></a>' +
      '<a class="fy-dpill" href="' + HELP_HREF + '" target="_blank" rel="noopener noreferrer"><span>Help Center</span></a>' +
      '</div>' +
      '<div class="fy-drawer-foot-row">' +
      '<a class="fy-dpill" href="./setting.html"><span>Legal Terms</span></a>' +
      '<a class="fy-dpill" href="' + TRUST_HREF + '" target="_blank" rel="noopener noreferrer"><span>Trust &amp; Safety</span></a>' +
      '</div>' +
      '</li>' +

      '</ul></div></nav>'
  }

  /* ---------- 顶栏右上角：成就徽章 + tokens 胶囊 + 头像菜单 ---------- */
  function tokenChip() {
    return '<div class="fy-chipwrap" id="token-chip-wrap">' +
      '<button type="button" class="fy-chip" id="token-chip-button" aria-expanded="false">' +
      '<img class="fy-chip-coin" src="' + I.coin + '" alt="Tokens">' +
      '<span data-target="user-token-balance">0</span>' +
      '<img class="fy-chip-arrow" src="' + I.expand + '" alt="">' +
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
      '<a class="fy-chip-pop-cta" href="./tokens.html">Buy more' +
      '<img src="' + I.addWhite + '" alt=""></a>' +
      '</div>' +
      '</div></div>'

    function row(label, val) {
      return '<div class="fy-chip-pop-row">' +
        '<div class="fy-chip-pop-label">' + label + '</div>' +
        '<div class="fy-chip-pop-val">' + val +
        '<img src="' + I.coin + '" alt=""></div></div>'
    }
  }

  function navRight() {
    var menu = '<a href="./Subscriptions.html"><img src="' + I.premium + '" alt=""><span>Subscription</span></a>' +
      '<a href="./tokens.html"><img src="' + I.coin + '" alt=""><span>Tokens</span></a>' +
      '<a href="./charactersIndex.html"><img src="' + I.lady + '" alt=""><span>My AI</span></a>' +
      '<a href="./upload_media.html"><img src="' + I.wand + '" alt=""><span>Upload Media</span></a>' +
      '<a href="./Inviter.html"><img src="' + I.gift + '" alt=""><span>Get 100 tokens</span></a>' +
      '<a href="./setting.html"><img src="' + I.settings + '" alt=""><span>Settings</span></a>' +
      '<form method="post" onsubmit="return false">' +
      '<button data-turbo="false" id="logout" type="submit"><img src="' + I.logout + '" alt=""><span>Logout</span></button></form>'

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
      '<img src="' + I.user + '" alt="Open user menu">' +
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
    if (document.querySelector('[data-testid="new-shell-closed-sidebar"]')) return false

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
