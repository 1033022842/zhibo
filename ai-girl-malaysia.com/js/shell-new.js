/**
 * 新版外壳渲染（侧边栏 + 顶栏右上角）
 * 样式目标：Subscriptions.html / tokens.html 使用的新版外壳。
 * 菜单内容：沿用站点原有的菜单项与顺序（Home / Explore / Chat / Generate Image /
 *          Create Character / My AI / Upload Media / Face Swap / Shop / Shorts / Posts /
 *          Private Content / Tokens / Crowdfunding，底部 Contact Us / Affiliate Program /
 *          Become Premium），常用功能直达，其余收进 More，不删除入口。
 *
 * 做法：只替换旧页面里「侧边栏 nav 的内容」与「#user-setting 的内容」，
 *      外层容器及其 data-main-target / webkit-transform-sidebar / pc-nav 全部保留，
 *      因此旧包的展开·收起逻辑（main_controller.js 的 toggleSidebar）继续生效。
 *      自带新版外壳的页面（Subscriptions.html）：左侧菜单与顶栏右上角都统一成这一套。
 */
(function () {
  'use strict'

  /* ---------- 关闭旧包遗留的 Turbo Drive（修「点导航后底栏变 4 格」的 bug） ----------
   * 镜像页带的 candy 旧 application.js 里 Turbo Drive 会拦截 <a> 点击做局部换页；
   * 换页后 head 里的 shell-new.js 不会重跑，页面就露出自带的老底栏（shorts 页只有 4 格，
   * 且没有 Home/Chat），局部换页还经常卡死不动。静态镜像站不需要 Turbo，统一整页跳转。 */
  ;(function turboOff() {
    if (window.__fyTurboOff) return
    window.__fyTurboOff = true
    function off() {
      try { if (window.Turbo && window.Turbo.session) window.Turbo.session.drive = false } catch (e) {}
    }
    off()
    document.addEventListener('DOMContentLoaded', off)
    window.addEventListener('load', off)
    setTimeout(off, 300)
    setTimeout(off, 1500)
    // 兜底：Drive 已经发起某次 visit 时，取消它并强制整页跳转
    document.addEventListener('turbo:before-visit', function (e) {
      off()
      var url = e.detail && e.detail.url
      e.preventDefault()
      if (url) location.href = url
    })
  })()

  /* ---------- 手机底栏滚动自动隐藏（参数照 candy bottom-bar-autohide） ----------
   * 往下滑(>4px 且页面滚过 60px)隐藏；往上滑或回到顶部(40px 内)显示；停止滚动 420ms 自动显示。 */
  ;(function bnavAutohide() {
    if (window.__fyBnavAutohide) return
    window.__fyBnavAutohide = true
    var DELTA = 4, HIDE_AFTER = 60, SHOW_BELOW = 40, SETTLE = 420
    var lastY = window.scrollY || 0
    var timer = null
    function bar() { return document.querySelector('.fy-bnav') }
    function show() { var b = bar(); if (b) b.classList.remove('is-hidden') }
    function hide() { var b = bar(); if (b) b.classList.add('is-hidden') }
    function onScroll() {
      var y = window.scrollY || 0
      var dy = y - lastY
      lastY = y
      if (dy > DELTA && y > HIDE_AFTER) hide()
      else if (dy < -DELTA || y < SHOW_BELOW) show()
      clearTimeout(timer)
      timer = setTimeout(show, SETTLE)
    }
    window.addEventListener('scroll', onScroll, { passive: true })
  })()

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

    // 手机端底部导航 / Create 面板（照参考站「图片/home.html」的新版底栏素材）
    bHome: { src: T + 'home-07943db4fc88e0be4ac57c26c4968aee020ec6f2903009e2cc4d74beec45d2b9.svg' },
    bHomeActive: { src: T + 'home-active-3800812b352d1be9b9af117691cfab97a991dc10d1105e7be1774b832c7b8530.svg' },
    bShorts: { src: T + 'shorts-dd7eca01fe7f8a694cd100be90b5b6c20d241a5bf4c8b8c0f8633864125cffae.svg' },
    bShortsActive: { src: './candy-shorts_files/shorts-active-d87391fb4c634b244c7e1c80b1944af6d857c3b542ac401e1995d96e35e3e0ea.svg' },
    bCreate: { src: './settings_files/nav-create-036106b767f610ef441faeb0d313e5e2e666ec71dd35bd4df95a36eccc82ff49.svg' },
    bChat: { src: T + 'chat-dbf86a760f2ab098a3a5d34346fb0a7c8619ed5050e90bd062c835416f510a0f.svg' },
    bChatActive: { src: './Chat_files/chat-active-72433ffefb1d56901c13b981a4e08104de6b14b49e04b80fbb1468f56aa68f44.svg' },
    bTokens: { src: './settings_files/bottom_bar_tokens_default-273d841343a05f1ba983a3ee4f6ef88b7180effec130b26b7cbb8442c6fc6040.svg' },
    bTokensActive: { src: T + 'bottom_bar_tokens_active-ac95a95e99f8c952759ad5c52b0d0fa17a37feebd3bf8b777be1cdfe65495244.svg' },
    bExplore: { src: './Girls_files/explore-271b6507dab3d31b26d45e198ec8046b2b8f148862ca272167fb8ce5455002e8.svg' },
    bExploreActive: { src: './Image_files/explore-active-ca279297c446de8d9227ffbadf9fef01e001cd35d665ec3620b02c9c35cab08c.svg' },
    bGenerate: { src: './Girls_files/generate-e0a4445659c35cbc82e7046cf089cf881179d55eaa82d4bf32dae229a06666c1.svg' },
    bWand: { src: './Girls_files/mobile-magic-wand-d445265367be8ddfbfe1c22810893d0ea4dbdaf1eadd7c4496861113d5388d78.svg' },
    bPremium: { src: T + 'premium-3fa8ec9f951178508109d03de202bc4a381ed87451b7dc9f8aa5a931bd485e02.svg' },
    bPremiumStar: { src: T + 'icon-513af0057e4b673067a8049311ea31fe24a74b04337db95bf10d618a16abcfb7.svg' },
    bGirlfriend: { src: T + 'cmai-56f830cb372139095fd5e9d4cd3c3a361cc6f1dfca2f172b8762199be44fa57d.svg' },
    bDirector: { src: T + 'director-b863278cedaf35fdc858a917d422a23859467cf0a57b1f6bae1f51f42fef3638.svg' },
    bGroup: { src: T + 'group-chats-a5bcd2972c364d94db640c13a7fc401b9bad495fccb2facf168333a5ecf5084a.svg' },
    bClose: { src: './candy-shorts_files/close-82846767821d6ff36201253b1387b41e9a60573769fcd82c2985d153e9351594.svg' },

    // 抽屉底部 Category 切换（照参考站 footer 的性别/分类图标）
    catFemale: { src: T + 'female-white-b6edc81820cdf305c0e1644c0c5d2124b2aa2ecef701a01eff46baad9a05dd26.svg' },
    catAnime: { src: T + 'anime-white-02986b4e7107a1be01a284a4818231a6edf19aa880d8a9680fb1a7874416f532.svg' },
    catMale: { src: T + 'male-white-b1c60bef78d9065208e08ef4f03bea1d2d50bc8b33c3fb2fe9361a3c83646a92.svg' },
    catChevron: { src: T + 'vector-down-grey-ac9e18f44b255e3510c3e32a682be2b5f5f736fb7b6c7df2d867107522ebdd5d.svg' },

    // 内联图标（站点旧侧边栏同款形状）
    upload: { svg: '<svg viewBox="0 0 24 24" ' + S + '><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>' },
    faceswap: { svg: '<svg viewBox="0 0 24 24" ' + S + '><circle cx="9" cy="8.5" r="4.5"/><circle cx="15" cy="15.5" r="4.5"/><path d="M7.6 7.9h.01M10.4 7.9h.01M8.2 10.6c.5.4 1.1.4 1.6 0"/></svg>' },
    posts: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="3" y="4" width="18" height="16" rx="3"/><circle cx="8.8" cy="9.8" r="1.5"/><path d="M4.2 18.2l4.6-4.6 3.6 3.6 2.8-2.4 4.6 3.4"/></svg>' },
    private: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="4" y="10" width="16" height="10" rx="2.5"/><path d="M8 10V7.4a4 4 0 0 1 8 0V10"/></svg>' },
    games: { svg: '<svg viewBox="0 0 24 24" ' + S + '><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>' },
    crowd: { svg: '<svg viewBox="0 0 24 24" ' + S + '><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' },
    contact: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 7 10 6 10-6"/></svg>' },
    affiliate: { svg: '<svg viewBox="0 0 24 24" ' + S + '><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.6" y1="10.7" x2="15.4" y2="6.3"/><line x1="8.6" y1="13.3" x2="15.4" y2="17.7"/></svg>' },
    appdl: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M12 7.5v6m0 0l-2.4-2.4M12 13.5l2.4-2.4"/></svg>' },
    banknote: { svg: '<svg viewBox="0 0 24 24" ' + S + '><rect x="2.5" y="6" width="19" height="12" rx="3"/><circle cx="12" cy="12" r="2.75"/><path d="M6 9.5v5M18 9.5v5"/></svg>' },

    // 头像下拉「Notifications」：沿用原站 settings_files 里的铃铛
    bell: { src: './settings_files/bell-5e96b0cd2e66a4ce6eb9b4f17fecd9f952a1780b89d2be511c619832e96f5897.png' }
  }

  // 侧边栏菜单：沿用站点原有菜单项与顺序（Discover = 原 Posts 广场，固定在 Home 下方）
  var NAV = [
    { href: './Home.html', label: 'Home', icon: 'home' },
    { href: './posts.html', label: 'Discover', icon: 'posts' },
    { href: './index.html', label: 'Crowdfunding', icon: 'crowd' },
    { href: './Chat.html', label: 'Chat', icon: 'chat' },
    { href: './achievements.html', label: 'Achievements', icon: 'achv' },
    { href: './Image.html', label: 'AI Video Studio', icon: 'generate', top: true },
    { href: './characters.html', label: 'Create Character', icon: 'wand' },
    { href: './charactersIndex.html', label: 'My AI', icon: 'lady' },
    { href: './upload_media.html', label: 'Upload Media', icon: 'upload' },
    { href: './shop.html', label: 'Shop', icon: 'candy' },
    { href: './shorts.html', label: 'Shorts', icon: 'shorts' },
    { href: './private_content.html', label: 'Private Content', icon: 'private' },
    { href: './purchasePoints.html', label: 'Tokens', icon: 'token' }
  ]

  // 侧边栏底部：沿用站点原有三项
  var NAV_BOTTOM = [
    { href: 'mailto:support@sugusmail.ai', label: 'Contact Us', icon: 'contact', attr: ' data-contact-us="1"' },
    { href: './Inviter.html', label: 'Affiliate Program', icon: 'affiliate' },
    { href: './Subscriptions.html', label: 'Become Premium', icon: 'premium' }
  ]

  var MAIN_NAV = ['Home', 'Discover', 'Chat', 'Shorts', 'Private Content', 'AI Video Studio', 'Create Character', 'Tokens'].map(function (label) {
    return NAV.filter(function (n) { return n.label === label })[0]
  })
  var MORE_NAV = NAV.filter(function (n) { return MAIN_NAV.indexOf(n) === -1 })
    .concat(NAV_BOTTOM.filter(function (n) { return n.icon !== 'contact' }))
    .concat([{ href: './Home.html#install-app', label: 'Get App', icon: 'appdl' }])

  /* 支持的语言：原站“Select your language”大弹窗（13 种）基础上扩展常用语种
     （新增 zh-CN / zh-TW / ko / ru / hi / th / vi / ms / id / ar / tr / km / lo，
     并把 it 并入可聊天语种，共 26 种，分 Interface + Chat / Interface only 两组；
     中文两项固定排在最后）。
     约定：[显示名, 弹窗里的两位代码, assets/flags 下的旗标文件名] */
  var LANG_META = {
    'en': ['English', 'EN', 'us'],
    'ja': ['日本語', 'JP', 'jp'],
    'ko': ['한국어', 'KO', 'kr'],
    'fr': ['Français', 'FR', 'fr'],
    'de': ['Deutsch', 'DE', 'de'],
    'es': ['Español', 'ES', 'es'],
    'it': ['Italiano', 'IT', 'it'],
    'ru': ['Русский', 'RU', 'ru'],
    'hi': ['हिन्दी', 'HI', 'in'],
    'th': ['ภาษาไทย', 'TH', 'th'],
    'vi': ['Tiếng Việt', 'VI', 'vn'],
    'ms': ['Bahasa Melayu', 'MY', 'my'],
    'id': ['Bahasa Indonesia', 'ID', 'id'],
    'ar': ['العربية', 'AR', 'ar'],
    'tr': ['Türkçe', 'TR', 'tr'],
    'km': ['ភាសាខ្មែរ', 'KH', 'kh'],
    'lo': ['ພາສາລາວ', 'LA', 'la'],
    'zh-CN': ['简体中文', 'CN', 'cn'],
    'zh-TW': ['繁體中文', 'TW', 'tw'],
    'da': ['Dansk', 'DA', 'da'],
    'fi': ['Suomi', 'FI', 'fi'],
    'nl': ['Nederlands', 'NL', 'nl'],
    'no': ['Norsk', 'NO', 'no'],
    'pl': ['Polski', 'PL', 'pl'],
    'pt-BR': ['Português', 'BR', 'pt-BR'],
    'sv': ['Svenska', 'SE', 'sv']
  }
  var LANG_CHAT = ['en', 'ja', 'ko', 'fr', 'de', 'es', 'it', 'ru', 'hi', 'th', 'vi', 'ms', 'id', 'ar', 'tr', 'km', 'lo', 'zh-CN', 'zh-TW']
  var LANG_UI = ['da', 'fi', 'nl', 'no', 'pl', 'pt-BR', 'sv']
  /* 先在语言弹窗里隐藏的语种（要重新开放，把代码从这里删掉即可；LANG_META 保留不动） */
  var LANG_HIDDEN = ['zh-CN']

  function langFlag(code) {
    return './assets/flags/' + (LANG_META[code] || LANG_META.en)[2] + '.svg'
  }

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
    var items = MAIN_NAV.map(function (n) {
      return '<li class="relative w-full" data-fy-tip="' + esc(n.label) + '">' +
        '<a class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer"' + linkAttr(n) + '>' +
        iconHtml(n.icon, 'w-6 h-6') + '</a></li>'
    }).join('')

    return '<nav class="flex flex-col justify-between h-full">' +
      '<div class="flex flex-col">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6">' + items + '</ul>' +
      '</div>' +
      sidebarFooter(false, false) + '</nav>'
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
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6 flex-1 min-h-0 overflow-y-auto">' + rows(MAIN_NAV) + '</ul>' +
      '</div>' +
      sidebarFooter(true, false) + '</nav>'
  }

  function sidebarFooter(withLabel, mobile) {
    var compact = !withLabel && !mobile
    return '<div class="fy-sidebar-footer' + (compact ? ' is-compact' : '') + '">' +
      '<div class="fy-sidebar-tools">' + langButton(!compact, true) +
      '<button type="button" class="fy-foot-btn fy-more-button" data-fy-more-open ' +
      'aria-label="More" aria-expanded="false" aria-haspopup="menu" aria-controls="fy-more-menu">' +
      '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
      '<circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>' +
      (compact ? '' : '<span>More</span>') + '</button></div>' +
      '<a class="fy-support-button" href="' + esc(supportHref()) + '" data-fy-support-open aria-label="Contact Us">' +
      iconHtml('contact', '') + (compact ? '' : '<span>Contact Us</span>') + '</a></div>'
  }

  var moreTrigger = null
  var supportTrigger = null
  var supportConfig = null
  var supportConfigTime = 0
  var supportConfigRequest = null

  function supportHref() {
    var items = supportConfig && supportConfig.enabled !== false && supportConfig.items
    if (Array.isArray(items)) {
      for (var i = 0; i < items.length; i++) {
        if (!items[i] || items[i].type !== 'email') continue
        var email = String(items[i].url || '').trim().replace(/^mailto:/i, '').trim()
        if (/^[^\s@<>?#%]+@[^\s@<>?#%]+\.[^\s@<>?#%]+$/.test(email)) return 'mailto:' + email
      }
    }
    return NAV_BOTTOM[0].href
  }

  function syncSupportDetails(loading) {
    var href = supportHref()
    var buttons = document.querySelectorAll('[data-fy-support-open]')
    for (var i = 0; i < buttons.length; i++) buttons[i].setAttribute('href', href)
    var panel = document.getElementById('fy-support-panel')
    if (!panel) return
    var email = panel.querySelector('.fy-support-email')
    var send = panel.querySelector('.fy-support-actions a')
    var copy = panel.querySelector('[data-fy-support-copy]')
    var status = panel.querySelector('.fy-support-status')
    copy.disabled = loading
    if (loading) {
      email.textContent = 'Loading support details...'
      email.removeAttribute('href')
      send.removeAttribute('href')
      send.setAttribute('aria-disabled', 'true')
      status.textContent = ''
      return
    }
    email.textContent = href.replace(/^mailto:/i, '').split('?')[0]
    email.setAttribute('href', href)
    send.setAttribute('href', href)
    send.removeAttribute('aria-disabled')
    status.textContent = ''
  }

  function loadSupportConfig() {
    if (supportConfigRequest) return supportConfigRequest
    if (supportConfigTime && Date.now() - supportConfigTime < 60000) return null
    // Read independently of tracking.js so this works on every sidebar page.
    var controller = window.AbortController ? new AbortController() : null
    supportConfigRequest = new Promise(function (resolve) {
      var settled = false
      var timer = setTimeout(function () {
        if (controller) controller.abort()
        finish()
      }, 8000)
      function finish(cs) {
        if (settled) return
        settled = true
        clearTimeout(timer)
        if (cs) supportConfig = cs
        supportConfigTime = Date.now()
        resolve()
      }
      try {
        fetch('/api/v1/site/config?t=' + Math.floor(Date.now() / 60000), {
          credentials: 'omit',
          cache: 'no-store',
          signal: controller ? controller.signal : undefined
        }).then(function (response) {
          if (!response.ok) throw new Error('Support configuration unavailable')
          return response.json()
        }).then(function (result) {
          if (!result || result.code !== '00000' || !result.data || !result.data.cs ||
              typeof result.data.cs !== 'object') throw new Error('Invalid support configuration')
          finish(result.data.cs)
        }).catch(function () { finish() })
      } catch (e) { finish() }
    }).then(function () {
      supportConfigRequest = null
      syncSupportDetails(false)
    })
    return supportConfigRequest
  }

  function closeMore(restoreFocus) {
    var menu = document.getElementById('fy-more-menu')
    var wasOpen = menu && !menu.hidden
    if (menu) menu.hidden = true
    if (moreTrigger) {
      moreTrigger.setAttribute('aria-expanded', 'false')
      if (restoreFocus && wasOpen) moreTrigger.focus()
    }
  }

  function openMore(button) {
    var menu = document.getElementById('fy-more-menu')
    if (!menu) {
      menu = document.createElement('div')
      menu.id = 'fy-more-menu'
      menu.className = 'fy-more-menu'
      menu.setAttribute('role', 'menu')
      menu.setAttribute('aria-label', 'More')
      menu.innerHTML = '<div class="fy-more-title">More</div>' + MORE_NAV.map(function (n) {
        return '<a role="menuitem" tabindex="-1"' + linkAttr(n) + '>' +
          iconHtml(n.icon, '') + '<span>' + esc(n.label) + '</span></a>'
      }).join('') + '<div class="fy-more-divider"></div>' + CATEGORIES.map(function (c) {
        return '<a role="menuitem" tabindex="-1" href="' + c.href + '">' +
          iconHtml(c.icon, '') + '<span>' + c.label + '</span></a>'
      }).join('')
      document.body.appendChild(menu)
    }
    var opening = menu.hidden || moreTrigger !== button
    closeMore(false)
    if (!opening) return
    moreTrigger = button
    hideTip()
    menu.hidden = false
    button.setAttribute('aria-expanded', 'true')
    // Portal to body: the sidebar's overflow and transforms must not clip the menu.
    var r = button.getBoundingClientRect()
    var mobile = window.innerWidth < 1024
    menu.style.maxHeight = mobile ? Math.min(520, Math.max(100, r.top - 24)) + 'px' : ''
    var width = menu.offsetWidth
    var height = menu.offsetHeight
    var side = button.closest('[data-main-target]')
    var right = side ? side.getBoundingClientRect().right : r.right
    var left = Math.max(12, Math.min(right + 12, window.innerWidth - width - 12))
    var top = Math.max(12, Math.min((mobile ? r.top - 8 : r.bottom) - height, window.innerHeight - height - 12))
    menu.style.left = left + 'px'
    menu.style.top = top + 'px'
    var first = menu.querySelector('[role="menuitem"]')
    if (first) first.focus({ preventScroll: true })
  }

  function closeSupport() {
    var panel = document.getElementById('fy-support-panel')
    if (panel) panel.hidden = true
    if (supportTrigger) supportTrigger.focus()
  }

  function openSupport(button) {
    closeMore(false)
    supportTrigger = button
    var panel = document.getElementById('fy-support-panel')
    if (!panel) {
      var href = supportHref()
      var email = href.replace(/^mailto:/i, '').split('?')[0]
      panel = document.createElement('div')
      panel.id = 'fy-support-panel'
      panel.className = 'fy-support-panel'
      panel.innerHTML = '<div class="fy-support-mask" data-fy-support-close></div>' +
        '<div class="fy-support-card" role="dialog" aria-modal="true" aria-labelledby="fy-support-title">' +
        '<button type="button" class="fy-support-close" aria-label="Close" data-fy-support-close>&times;</button>' +
        '<h2 id="fy-support-title">Contact Us</h2>' +
        '<p>For account or payment questions, contact our support team by email.</p>' +
        '<a class="fy-support-email" href="' + esc(href) + '">' + esc(email) + '</a>' +
        '<div class="fy-support-actions"><button type="button" data-fy-support-copy>Copy email</button>' +
        '<a href="' + esc(href) + '">Send email</a></div>' +
        '<p class="fy-support-status" role="status"></p></div>'
      document.body.appendChild(panel)
    }
    syncSupportDetails(!!loadSupportConfig())
    panel.hidden = false
    panel.querySelector('button[data-fy-support-close]').focus()
  }

  function bindSidebarTools() {
    if (document.__fySidebarTools) return
    document.__fySidebarTools = true
    loadSupportConfig()
    document.addEventListener('click', function (e) {
      var target = e.target
      if (!target || !target.closest) return
      var more = target.closest('[data-fy-more-open]')
      var support = target.closest('[data-fy-support-open]')
      if (more || support) {
        e.preventDefault()
        e.stopPropagation()
        if (more) openMore(more)
        else openSupport(support)
        return
      }
      if (target.closest('[data-fy-support-close]')) {
        e.preventDefault()
        e.stopPropagation()
        closeSupport()
        return
      }
      if (target.closest('[data-fy-support-copy]')) {
        var panel = document.getElementById('fy-support-panel')
        if (!panel || target.closest('[data-fy-support-copy]').disabled) return
        var email = panel.querySelector('.fy-support-email').textContent
        var status = panel.querySelector('.fy-support-status')
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(email).then(function () {
            status.textContent = 'Email copied.'
          }).catch(function () { window.prompt('Copy this email address:', email) })
        } else window.prompt('Copy this email address:', email)
        return
      }
      if (target.closest('[data-fy-lang-open]')) closeMore(false)
      var menu = document.getElementById('fy-more-menu')
      if (menu && !menu.hidden && (!menu.contains(target) || target.closest('a'))) closeMore(false)
    }, true)
    document.addEventListener('keydown', function (e) {
      var support = document.getElementById('fy-support-panel')
      var menu = document.getElementById('fy-more-menu')
      if (e.key === 'Escape') {
        if (support && !support.hidden) { e.preventDefault(); closeSupport(); return }
        closeMore(true)
      }
      if (support && !support.hidden && e.key === 'Tab') {
        var focusable = support.querySelectorAll('a[href], button:not(:disabled)')
        var first = focusable[0], last = focusable[focusable.length - 1]
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus() }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus() }
      }
      if (!menu || menu.hidden || !menu.contains(e.target)) return
      var items = Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]'))
      var index = items.indexOf(document.activeElement)
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Home' || e.key === 'End') {
        e.preventDefault()
        var next = e.key === 'Home' ? 0 : (e.key === 'End' ? items.length - 1 :
          (index + (e.key === 'ArrowDown' ? 1 : -1) + items.length) % items.length)
        items[next].focus()
      }
      if (e.key === 'Tab') closeMore(false)
    })
    window.addEventListener('resize', function () { closeMore(false) })
    document.addEventListener('scroll', function (e) {
      var menu = document.getElementById('fy-more-menu')
      if (menu && !menu.contains(e.target)) closeMore(false)
    }, true)
  }

  /* ---------- 语言切换按钮：点开原站那颗 “Select your language” 大弹窗 ---------- */
  /* 外观与同排的其它按钮完全一致：
     桌面收起栏 = 侧栏图标项（48x52，w-6 图标）
     桌面展开栏 = 侧栏文字项（h-40，w-4 图标 + text-xxs 文案）
     手机抽屉底部 = 那排小按钮的 .fy-foot-btn */
  function langButton(withLabel, mobile) {
    var cur = currentLang()
    var meta = LANG_META[cur] || LANG_META.en
    var flag = langFlag(cur)
    if (mobile) {
      return '<button type="button" data-fy-lang-open class="fy-foot-btn">' +
        '<img class="fy-langflag" alt="Flag icon" src="' + flag + '">' +
        '<span>' + esc(meta[0]) + '</span>' +
        '</button>'
    }
    if (withLabel) {
      return '<button type="button" data-fy-lang-open class="h-[40px] w-full hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer">' +
        '<img class="fy-langflag w-4 h-4" alt="Flag icon" src="' + flag + '">' +
        '<span class="text-start text-grey-medium text-xxs font-medium leading-4">' + esc(meta[0]) + '</span>' +
        '</button>'
    }
    return '<button type="button" data-fy-lang-open class="h-[48px] w-[52px] hover:bg-zinc-700 rounded-[10px] border border-white/10 flex justify-start px-3 items-center gap-2 mx-auto cursor-pointer">' +
      '<img class="fy-langflag w-6 h-6" alt="Flag icon" src="' + flag + '">' +
      '</button>'
  }

  /* ---------- 原站那颗 “Select your language” 大弹窗（内容/类名 1:1 照参考页） ---------- */
  function langModalHtml() {
    var cur = currentLang()
    function rows(codes) {
      return codes.filter(function (code) { return LANG_HIDDEN.indexOf(code) === -1 }).map(function (code) {
        var meta = LANG_META[code]
        var active = code === cur
        return '<form data-turbo="true" data-turbo-stream="true" class="button_to" method="get" action="/user_locale/edit">' +
          '<button' + (active ? ' disabled="disabled"' : '') + ' data-fy-locale="' + code + '" class="w-full p-4 pr-[40px] relative hover:bg-neutral-800 rounded-[10px] cursor-pointer flex gap-2 justify-start items-center text-grey-medium text-sm font-medium ' +
          (active ? 'border-3 border-primary' : 'border border-neutral-700') + '" type="submit">' +
          '<img class="w-[14px]" alt="' + code + ' flag icon" src="' + langFlag(code) + '">' +
          '<div>' + esc(meta[0]) + '</div>' +
          '<div class="text-neutral-400">' + esc(meta[1]) + '</div>' +
          (active ? '<img class="w-[24px] absolute right-[16px]" alt="" src="./assets/shell/check.svg">' : '') +
          '</button><input type="hidden" name="new_locale" value="' + code + '"></form>'
      }).join('')
    }
    return '<div class="hidden" id="language-select-modal" data-fy-langmodal="1">' +
      '<div class="fixed inset-0 z-90 bg-black/50 backdrop-blur-sm overflow-y-auto" data-fy-lang-mask="1">' +
      '<div class="md:justify-center p-0 md:p-4 flex items-center min-h-[100svh] text-center w-full">' +
      '<div class="relative bg-[#131313] md:rounded-xl w-full md:max-w-[715px] overflow-hidden shadow-xl text-left">' +
      '<button type="button" class="absolute right-4 top-4 w-4 h-4 z-50" data-fy-lang-close="1">' +
      '<span class="sr-only">Close</span>' +
      '<img alt="Close" class="w-full h-full" src="./assets/shell/close.svg">' +
      '</button>' +
      '<div class="p-[40px] w-full flex flex-col gap-[40px]">' +
      '<div class="text-white font-bold text-1xl">Select your language</div>' +
      '<div><div class="text-white font-semibold">Interface + Chat</div>' +
      '<div class="text-neutral-400 font-medium text-sm mb-6">Website and chat fully supported</div>' +
      '<div class="w-full grid grid-cols-1 md:grid-cols-3 gap-4">' + rows(LANG_CHAT) + '</div></div>' +
      '<hr class="border-t-neutral-700">' +
      '<div><div class="text-white font-semibold">Interface only</div>' +
      '<div class="text-neutral-400 font-medium text-sm mb-6">Website available, chat not yet supported</div>' +
      '<div class="w-full grid grid-cols-1 md:grid-cols-3 gap-4">' + rows(LANG_UI) + '</div></div>' +
      '</div></div></div></div></div>'
  }

  /* 弹窗节点：页面自带同款大弹窗时换成同一份内容（保证各页一致、且高亮当前语言） */
  function langModalEl() {
    var m = document.getElementById('language-select-modal')
    if (m && m.getAttribute('data-fy-langmodal') === '1') return m
    var box = document.createElement('div')
    box.innerHTML = langModalHtml()
    var fresh = box.firstElementChild
    if (m && m.parentNode) m.parentNode.replaceChild(fresh, m)
    else document.body.insertBefore(fresh, document.body.firstChild)   // 放在最前：避开站内 #goog-gt-tt ~ div{display:none}
    return fresh
  }

  function openLangModal() {
    var m = langModalEl()
    m.classList.remove('hidden')
    m.style.display = 'block'
  }

  function closeLangModal() {
    var m = document.getElementById('language-select-modal')
    if (!m) return
    m.classList.add('hidden')
    m.style.display = 'none'
  }

  /* 页面自带（非外壳侧栏）的语言按钮：换成站内新弹窗的同时，把按钮上的旗标/名称也同步成当前语言，
     否则切到中文/其它语种后这些按钮仍显示 “English / en 旗标”，看起来像没生效。 */
  function syncPageLangButtons() {
    var btns = document.querySelectorAll('[data-toggle-modal-modal-outlet="#language-select-modal"]')
    if (!btns.length) return
    var cur = currentLang()
    var meta = LANG_META[cur] || LANG_META.en
    for (var i = 0; i < btns.length; i++) {
      var img = btns[i].querySelector('img')
      if (img) img.setAttribute('src', langFlag(cur))
      var lbl = btns[i].querySelector('div, span')
      if (lbl) lbl.textContent = meta[0]
    }
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

  /* 写 googtrans cookie：先清掉 host-only 及各级域上的同名旧值，再统一写入，
     避免上一次遗留的重复 cookie 盖住新值。 */
  function writeGoogtrans(value) {
    var host = location.hostname
    var domains = ['']
    if (host.indexOf('.') > -1) {
      domains.push(host, '.' + host)
      var parts = host.split('.')
      if (parts.length > 2) domains.push('.' + parts.slice(-2).join('.'))
    }
    var past = 'expires=Thu, 01 Jan 1970 00:00:00 GMT'
    for (var i = 0; i < domains.length; i++) {
      document.cookie = 'googtrans=;path=/;' + (domains[i] ? 'domain=' + domains[i] + ';' : '') + past
    }
    for (var j = 0; j < domains.length; j++) {
      document.cookie = 'googtrans=' + value + ';path=/;' + (domains[j] ? 'domain=' + domains[j] : '')
    }
  }

  /* 清掉 URL 上遗留的 #googtrans(...) 哈希。
     Google 翻译在页面加载时若发现该哈希，会按哈希里的语言套用并用它覆盖 googtrans cookie；
     上一次切换留下的旧哈希因此会让「第二次切换」失效（cookie 被改回上一次的语言）。
     这里让 cookie 成为唯一依据，保证每次切换都生效。 */
  function clearLangHash() {
    if (!/googtrans/i.test(location.hash)) return
    try {
      history.replaceState(null, '', location.pathname + location.search)
    } catch (e) { /* 忽略：老浏览器不支持时保持原样 */ }
  }

  function switchLang(code) {
    writeGoogtrans('/en/' + code)
    clearLangHash()
    try {
      localStorage.setItem('agigpt-lang', location.href.split('#')[0])
      localStorage.setItem('agigpt-lang-href', '#googtrans(en|' + code + ')')
    } catch (e) { /* 隐私模式忽略 */ }
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
        '</a></li>'
    }
    // 参考站：分隔线只出现在两行之间，最后一行后面没有
    return MAIN_NAV.map(row).join('<li class="fy-hr-li"></li>')
  }

  /* 头像下拉 / 移动端抽屉「我的资料」共用菜单（内容统一） */
  function profileMenu() {
    function row(href, icon, label) {
      return '<a href="' + href + '">' + iconHtml(icon, '') +
        '<span>' + esc(label) + '</span></a>'
    }
    return row('./Subscriptions.html', 'premium', 'Subscription') +
      row('./purchasePoints.html', 'coin', 'Tokens') +
      row('./Withdrawal.html', 'banknote', 'Withdraw') +
      row('./Inviter.html', 'gift', 'Get 100 tokens') +
      row('./setting.html', 'settings', 'Settings') +
      // 消息通知：原站头像下拉里就有这一项，点开是通知面板
      '<button type="button" class="fy-notif-btn">' + iconHtml('bell', '') +
      '<span>' + esc('Notifications') + '</span></button>' +
      '<form method="post" onsubmit="return false">' +
      '<button data-turbo="false" class="logout" type="submit">' +
      '<img src="' + I.logout.src + '" alt=""><span>Logout</span></button></form>'
  }

  /* ---------- 消息通知面板 ----------
     原站头像下拉里的「Notifications」由 account-notifications 控制器 + /account_notifications
     驱动；本站后端没有该接口，这里照原站弹窗的样式做一个纯前端面板，
     内容沿用站内既有的示例通知。 */
  var NOTIFS = [
    { tag: 'New character', datetime: '2026-09-26T02:54:04Z', time: '2:54 AM', text: 'Sadie Ashworth is now available.' },
    { tag: 'New character', datetime: '2026-09-26T01:24:57Z', time: '1:24 AM', text: 'Lexi Luna is now available.' },
    { tag: 'New character', datetime: '2026-09-24T04:15:42Z', time: '4:15 AM', text: 'Rosie Rios is now available.' },
    { tag: 'New character', datetime: '2026-09-23T05:35:21Z', time: '5:35 AM', text: 'Alexis Ivyedge is now available.' }
  ]

  var NOTIF_CSS = '' +
    '.fy-notif{position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;}' +
    '.fy-notif[hidden]{display:none;}' +
    '.fy-notif-veil{position:absolute;inset:0;background:rgba(0,0,0,.65);}' +
    '.fy-notif-card{position:relative;display:flex;flex-direction:column;width:100%;max-width:760px;max-height:calc(100dvh - 24px);padding:24px;border:1px solid #2b2b2b;border-radius:12px;background:#1A1A1A;box-shadow:0 20px 40px rgba(0,0,0,.5);}' +
    '.fy-notif-x{position:absolute;right:16px;top:16px;width:27px;height:27px;padding:0;border:0;background:transparent;background-image:none;color:#fff;cursor:pointer;transition:opacity .2s;}' +
    '.fy-notif-x:hover{opacity:.8;}' +
    '.fy-notif-x svg{display:block;width:100%;height:100%;}' +
    '.fy-notif-title{margin:0;font-size:24px;line-height:24px;font-weight:600;color:#fff;}' +
    '.fy-notif-sub{margin:8px 0 0;font-size:14px;font-weight:500;line-height:20px;color:#a3a3a3;}' +
    '.fy-notif-list{display:flex;flex-direction:column;gap:12px;margin-top:8px;padding-right:4px;max-height:calc(100dvh - 180px);overflow-y:auto;overscroll-behavior:contain;}' +
    '.fy-notif-row{display:flex;align-items:flex-start;gap:12px;padding:12px 16px;border:1px solid #2b2b2b;border-radius:10px;background:rgba(255,255,255,.03);}' +
    '.fy-notif-dot{flex:0 0 auto;width:8px;height:8px;margin-top:8px;border-radius:9999px;background:#E75275;}' +
    '.fy-notif-body{flex:1 1 auto;min-width:0;}' +
    '.fy-notif-meta{display:flex;align-items:center;gap:8px;margin-bottom:8px;}' +
    '.fy-notif-tag{padding:2px 6px;border-radius:4px;background:rgba(231,82,117,.2);color:#E75275;font-size:10px;font-weight:600;line-height:16px;}' +
    '.fy-notif-time{color:#8a8a8a;font-size:11px;line-height:16px;}' +
    '.fy-notif-text{margin:0;font-size:12px;font-weight:500;line-height:20px;color:#a3a3a3;}' +
    '@media (min-width:640px){.fy-notif-list{max-height:520px;}}' +
    '@media (max-width:639px){' +
    '.fy-notif{align-items:flex-end;}' +
    '.fy-notif-card{max-width:none;border-radius:12px 12px 0 0;border-bottom:0;}' +
    '}'

  function notifPanelHtml() {
    function row(n) {
      return '<div class="fy-notif-row">' +
        '<span class="fy-notif-dot" aria-hidden="true"></span>' +
        '<div class="fy-notif-body">' +
        '<div class="fy-notif-meta">' +
        '<span class="fy-notif-tag">' + esc(n.tag) + '</span>' +
        '<time class="fy-notif-time" datetime="' + esc(n.datetime) + '">' + esc(n.time) + '</time>' +
        '</div>' +
        '<p class="fy-notif-text">' + esc(n.text) + '</p>' +
        '</div></div>'
    }
    return '<div class="fy-notif" id="fyNotif" role="dialog" aria-modal="true" aria-label="Notifications" hidden>' +
      '<div class="fy-notif-veil" data-fy-notif-close></div>' +
      '<div class="fy-notif-card">' +
      '<button type="button" class="fy-notif-x" aria-label="Close" data-fy-notif-close>' +
      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">' +
      '<path d="M6 6l12 12M18 6L6 18"/></svg></button>' +
      '<h2 class="fy-notif-title">Notifications</h2>' +
      '<p class="fy-notif-sub">Important notifications from Sugus.ai.</p>' +
      '<div class="fy-notif-list">' + NOTIFS.map(row).join('') + '</div>' +
      '</div></div>'
  }

  function openNotifs() {
    if (!document.getElementById('fyNotifStyle')) {
      var st = document.createElement('style')
      st.id = 'fyNotifStyle'
      st.textContent = NOTIF_CSS
      document.head.appendChild(st)
    }
    var el = document.getElementById('fyNotif')
    if (!el) {
      var box = document.createElement('div')
      box.innerHTML = notifPanelHtml()
      el = box.firstChild
      document.body.appendChild(el)
    }
    el.hidden = false
    document.body.style.overflow = 'hidden'
  }

  function closeNotifs() {
    var el = document.getElementById('fyNotif')
    if (!el || el.hidden) return
    el.hidden = true
    document.body.style.overflow = ''
  }

  /* 抽屉底部（照参考站 footer 的排布）：上面一个大按钮「Category: Girls」，
     下面四个小按钮两行两列（Contact Us / Affiliate / Get App / 语言）。
     内容沿用本站既有去处：Contact Us 用 NAV 里的同款邮箱，Affiliate 指向 Inviter.html，
     Get App 指向 Home.html 的「添加到主屏幕」教程（原 Discord 位，本站没有 Discord）。 */
  var CATEGORIES = [
    { key: 'girls', label: 'Girls', href: './Girls.html', icon: 'catFemale' },
    { key: 'anime', label: 'Anime', href: './Anime.html', icon: 'catAnime' },
    { key: 'guys', label: 'Guys', href: './Guys.html', icon: 'catMale' }
  ]

  /* 分类导航的落点：
     首页（Home.html）用页内 [data-cat] 按钮切换，本来就是对的；
     其它页面（Chat / Game / purchasePoints …）的顶栏 Girls/Anime/Guys 是
     <a href="./Girls.html">，点了会跳到分类列表页。
     客户希望「在聊天页点这三个按钮依然是回到首页选角色」，所以这些页面统一
     改写为 Home.html?cat=<key>，由 Home.html 读取该参数选中对应分类。

     Girls/Anime/Guys.html 三个分类列表页除外：它们自身的分类互跳保持原样
     （这三个页面只可能被旧链接/书签到达，别顺手改掉它们的行为）。 */
  function isHomePage() {
    var p = (location.pathname || '').toLowerCase()
    return p === '/' || /\/home\.html$/.test(p)
  }

  function isCatListPage() {
    var p = (location.pathname || '').toLowerCase()
    return /\/(girls|anime|guys)\.html$/.test(p)
  }

  function catKeyFromHref(href) {
    if (!href) return ''
    if (/girls\.html/i.test(href)) return 'girls'
    if (/anime\.html/i.test(href)) return 'anime'
    if (/guys\.html/i.test(href)) return 'guys'
    return ''
  }

  // 非首页 / 非分类列表页：把分类链接改写成回首页带分类（保持 <a href> 形态，新窗口/中键都正常）
  function normalizeCatLinks() {
    if (isHomePage() || isCatListPage()) return
    var links = document.querySelectorAll(
      'a.head-btn[href], #fyCatDdPanel a[href], a.fy-cat-item[href]'
    )
    for (var i = 0; i < links.length; i++) {
      var k = catKeyFromHref(links[i].getAttribute('href'))
      if (k) links[i].setAttribute('href', './Home.html?cat=' + k)
    }
  }

  // Home.html 那个站内分类切换（顶栏 .c-tab / 移动端 #catDdPanel，都由 [data-cat] 驱动）
  function pageCatBtn(key) {
    var list = document.querySelectorAll('[data-cat="' + key + '"]')
    for (var i = 0; i < list.length; i++) {
      if (!(list[i].closest && list[i].closest('.fy-drawer-foot'))) return list[i]
    }
    return null
  }

  function pageCategory() {
    var lbl = document.getElementById('catDdLabel')
    var v = lbl ? String(lbl.textContent || '').trim().toLowerCase() : ''
    for (var i = 0; i < CATEGORIES.length; i++) {
      if (CATEGORIES[i].key === v) return CATEGORIES[i]
    }
    return null
  }

  function currentCategory() {
    // 页面自带分类切换时以页面为准，否则按当前页面判断（Girls/Anime/Guys 三个列表页）
    var fromPage = pageCategory()
    if (fromPage) return fromPage
    var p = location.pathname.toLowerCase()
    for (var i = 0; i < CATEGORIES.length; i++) {
      if (p.indexOf(CATEGORIES[i].href.replace('./', '/').toLowerCase()) > -1) return CATEGORIES[i]
    }
    return CATEGORIES[0]
  }

  // 抽屉上显示的分类跟着页面走（页面上切分类 → 抽屉同步）
  function setDrawerCat(key) {
    var v = document.querySelector('.fy-cat-v')
    if (!v) return
    for (var i = 0; i < CATEGORIES.length; i++) {
      if (CATEGORIES[i].key !== key) continue
      v.textContent = CATEGORIES[i].label
      var ic = document.querySelector('#fy-cat-btn .fy-cat-icon img')
      if (ic) ic.src = I[CATEGORIES[i].icon].src
      return
    }
  }

  function bindPageCatSync() {
    var lbl = document.getElementById('catDdLabel')
    if (!lbl || lbl.__fyCatSync) return
    lbl.__fyCatSync = true
    if (window.MutationObserver) {
      new MutationObserver(function () {
        var c = pageCategory()
        if (c) setDrawerCat(c.key)
      }).observe(lbl, { childList: true, characterData: true, subtree: true })
    }
    setDrawerCat(currentCategory().key)
  }

  function drawerFooter() {
    return sidebarFooter(true, true)
  }

  /* 结构照参考站新版外壳的 mobile drawer（Subscriptions.html 里保存的原始 DOM）：
     nav > div.flex.flex-col.justify-between.h-full > [ ul 列表 , footer 底部固定栏 ] */
  function mobileDrawer() {
    var menu = profileMenu()

    return '<nav class="flex flex-col justify-between h-full w-60">' +
      '<div class="flex flex-col justify-between h-full">' +
      '<ul role="list" class="fy-drawer flex flex-col items-center justify-start px-5 pb-5 gap-2.5 bg-zinc-900 h-screen overflow-auto">' +

      // 我的资料（已登录）
      '<li class="fy-drawer-user login-pf user-dropdown relative w-full pt-2.5">' +
      '<button type="button" class="fy-drawer-userbtn user-dropdown w-full flex items-center gap-2.5 text-xs" id="fy-drawer-user-btn" aria-expanded="false">' +
      '<span class="relative"><img class="w-6 h-6 rounded-full user-dropdown" src="' + I.man.src + '" alt="Man profile icon">' +
      '<span class="fy-drawer-dot"></span></span>' +
      '<span class="fy-drawer-userlabel user-dropdown flex items-center justify-between flex-1">' +
      '<span class="user-dropdown text-xs font-semibold text-grey-medium">My Profile</span>' +
      '<svg class="user-dropdown w-6 h-6 text-white" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">' +
      '<path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"></path>' +
      '</svg></span></button>' +
      '<div class="fy-drawer-menu hidden" id="fy-drawer-user-menu">' + menu + '</div></li>' +

      // 未登录：注册 / 登录（顶栏有登录块的页面已挪到右上角，这里只给没有顶栏登录块的页面兜底）
      (document.querySelector('.pc-rg-login') ? '' :
        '<li class="user-login w-full py-4 gap-2.5">' +
        '<a href="./Login.html" class="flex-1 h-8 px-2 py-1.5 bg-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer">' +
        '<span class="text-white text-sm font-semibold leading-tight">Register</span></a>' +
        '<a href="./Login.html" class="flex-1 h-8 px-2 py-1.5 border-[#E75275] border text-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer">' +
        '<span class="text-sm font-semibold leading-tight">Login</span></a></li>') +

      '<li class="fy-hr-li"></li>' +
      drawerRows() +

      '</ul>' +
      drawerFooter() +
      '</div></nav>'
  }

  /* ---------- 顶栏右上角：成就徽章 + tokens 胶囊 + 头像菜单 ---------- */
  /* ---------- 顶栏余额刷新：扣代币/金币后调用（解锁等） ---------- */
  window.refreshTokenBalance = function () {
    var tk = null
    try { tk = localStorage.getItem('live_access_token') } catch (e) { return }
    if (!tk) return
    // 单飞：上一次请求未回来前不重复发（防抖之外的兜底，任何调用方都不会造成并发风暴）
    if (window.__tokenBalanceInflight) return
    window.__tokenBalanceInflight = true
    var done = function () { window.__tokenBalanceInflight = false }
    fetch('/api/live/userInfo', { method: 'POST', headers: { token: tk } })
      .then(function (r) { return r.json() })
      .then(function (d) {
        if (!d || (d.code !== 1 && d.code !== '00000')) return
        var money = (d.data && (d.data.money != null ? d.data.money : d.data.diamond_balance)) || 0
        document.querySelectorAll('[data-target="user-token-balance"]').forEach(function (el) {
          el.textContent = money
        })
      })
      .catch(function () {})
      .then(done, done)
  }

  // 站内 API（/api/live/）请求完成后自动刷新顶栏余额（聊天扣费/解锁/充值后实时更新），300ms 防抖合并
  ;(function () {
    if (window.__fyFetchHooked) return
    window.__fyFetchHooked = true
    var orig = window.fetch
    var refreshTimer = null
    function scheduleRefresh() {
      if (refreshTimer) return
      refreshTimer = setTimeout(function () {
        refreshTimer = null
        try { window.refreshTokenBalance() } catch (e) {}
      }, 300)
    }
    window.fetch = function () {
      var p = orig.apply(this, arguments)
      try {
        var u = String((arguments[0] && (arguments[0].url || arguments[0])) || '')
        // 站内实时接口的变更类调用（POST）后刷新余额；只读接口不刷
        // ⚠️ 必须排除 userInfo 本身：refreshTokenBalance() 就是 POST /api/live/userInfo，
        //    若不排除会「userInfo 完成 → 触发刷新 → 再发 userInfo」无限循环，
        //    每个标签页 300ms 一次，数个标签页即可吃满 PHP-FPM 40 个 worker，
        //    导致 customVideo/submit 等请求排队到超时（2026-10-22 线上事故）
        if (/\/api\/live\//.test(u) && !/\/api\/live\/userInfo(\?|$)/.test(u)) {
          var opts = arguments[1] || {}
          var method = String(opts.method || (arguments[0] && arguments[0].method) || 'GET').toUpperCase()
          if (method === 'POST') p.then(function () { scheduleRefresh() }).catch(function () {})
        }
      } catch (e) {}
      return p
    }
  })()

  function tokenChip() {
    return '<div class="fy-chipwrap" id="token-chip-wrap">' +
      '<button type="button" class="fy-chip" id="token-chip-button" aria-expanded="false">' +
      '<img class="fy-chip-coin" src="' + I.coin.src + '" alt="Tokens">' +
      '<span data-target="user-token-balance">0</span>' +
      '<img class="fy-chip-arrow" src="' + I.expand.src + '" alt="">' +
      '</button>' +
      '<div class="fy-chip-pop hidden" id="navbar-tokens-dropdown">' +
      '<div class="fy-chip-pop-in">' +
      row('Message cost', '<b>' + (function () { try { return localStorage.getItem('sugus.chat_price') || '2' } catch (e) { return '2' } })() + '</b> tokens') +
      '<span class="fy-chip-pop-sep"></span>' +
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
    var menu = profileMenu()

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

  /* ---------- 手机端底部导航（≤1023px）----------
     参考站：图片/home.html 的 mobile_bottom_bar
     登录态 5 格 Home/Shorts/Create/Chat/Tokens；未登录 5 格 Home/Explore/Generate/Create/Premium。
     两种状态都只在这里渲染，页面自带的老版底栏会被就地替换，保证全站底栏完全一致。
     （Create 面板照参考站 create-menu） */
  var BOTTOM_AUTH = [
    { href: './Home.html', label: 'Home', icon: 'bHome', activeIcon: 'bHomeActive', key: 'home' },
    { href: './shorts.html', label: 'Shorts', icon: 'bShorts', activeIcon: 'bShortsActive', key: 'shorts' },
    { create: true, label: 'Create', icon: 'bCreate' },
    { href: './Chat.html', label: 'Chat', icon: 'bChat', activeIcon: 'bChatActive', key: 'chat' },
    { href: './purchasePoints.html', label: 'Tokens', icon: 'bTokens', activeIcon: 'bTokensActive', key: 'tokens' }
  ]

  var BOTTOM_GUEST = [
    { href: './Home.html', label: 'Home', icon: 'bHome', activeIcon: 'bHomeActive', key: 'home' },
    { href: './Girls.html', label: 'Explore', icon: 'bExplore', activeIcon: 'bExploreActive', key: 'explore' },
    { href: './Image.html', label: 'Generate', icon: 'bGenerate', key: 'generate' },
    { href: './characters.html', label: 'Create', icon: 'bWand', key: 'create' },
    { href: './Subscriptions.html', label: 'Premium', icon: 'bPremium', key: 'premium', premium: true }
  ]

  var CREATE_MENU = [
    { href: './characters.html', icon: 'bGirlfriend', title: 'AI Girlfriend', sub: 'Create your own AI girlfriend' },
    { href: './Image.html', icon: 'bDirector', title: 'Generate spicy videos', sub: 'Build your own spicy movie', badge: 'NEW' },
    { href: './Chat.html', icon: 'bGroup', title: 'Group chat', sub: 'Start a chat with up to 3 characters' }
  ]

  /* 当前页面对应的底栏高亮项（参考站：当前页那一格用激活版图标） */
  function activeBottomKey(signedIn) {
    var p = (location.pathname || '').toLowerCase()
    if (/\/home\.html$/.test(p)) return 'home'
    if (/shorts\.html$/.test(p)) return 'shorts'
    if (/chat\.html$/.test(p)) return 'chat'
    if (/purchasepoints\.html$|\/tokens\.html$/.test(p)) return 'tokens'
    /* Subscriptions 是会员/代币充值页：登录态对应第 5 格 Tokens，未登录对应 Premium 格 */
    if (/\/subscriptions\.html$/.test(p)) return signedIn ? 'tokens' : 'premium'
    if (/\/girls\.html$/.test(p)) return 'explore'
    if (/\/image\.html$/.test(p)) return 'generate'
    if (/\/characters\.html$/.test(p)) return 'create'
    return ''
  }

  function bottomItemHtml(item, key) {
    var isActive = item.key && item.key === key
    var icon = isActive && item.activeIcon ? item.activeIcon : item.icon
    var cls = 'fy-bnav-item' + (isActive ? ' is-active' : '')
    if (item.create) {
      return '<button type="button" class="fy-bnav-item fy-bnav-create" data-fy-createmenu-open aria-haspopup="dialog">' +
        '<img alt="' + esc(item.label) + '" src="' + I[icon].src + '">' +
        '<span class="fy-bnav-label">' + esc(item.label) + '</span></button>'
    }
    /* Premium 格：金色渐变底 + 左右两颗小星（照参考站底栏 Premium 页签）
       所有链接都带 data-turbo="false"：旧包 Turbo 对 frame 内链接即使 drive=false 也会拦截 */
    if (item.premium) {
      return '<a class="' + cls + '" data-turbo="false" href="' + item.href + '">' +
        '<span class="fy-bnav-premium">' +
        '<img class="fy-bnav-star fy-bnav-star-l" alt="" src="' + I.bPremiumStar.src + '">' +
        '<img class="fy-bnav-star fy-bnav-star-r" alt="" src="' + I.bPremiumStar.src + '">' +
        '<img alt="' + esc(item.label) + '" src="' + I[icon].src + '">' +
        '</span>' +
        '<span class="fy-bnav-label">' + esc(item.label) + '</span></a>'
    }
    return '<a class="' + cls + '" data-turbo="false" href="' + item.href + '">' +
      '<img alt="' + esc(item.label) + '" src="' + I[icon].src + '">' +
      '<span class="fy-bnav-label">' + esc(item.label) + '</span></a>'
  }

  /* 页面自带的老版底栏（Explore/Generate/Create/Premium） */
  function findBottomBar() {
    var nodes = document.querySelectorAll('div')
    for (var i = 0; i < nodes.length; i++) {
      var c = nodes[i].classList
      if (c && c.contains('fixed') && c.contains('bottom-0') && c.contains('h-[70px]')) return nodes[i]
    }
    return null
  }

  function createMenuHtml() {
    var items = CREATE_MENU.map(function (m) {
      return '<a class="fy-createmenu-item" data-turbo="false" href="' + m.href + '">' +
        '<span class="fy-createmenu-tile"><img alt="" src="' + I[m.icon].src + '"></span>' +
        '<span class="fy-createmenu-text">' +
        '<span class="fy-createmenu-title">' + esc(m.title) +
        (m.badge ? '<span class="fy-createmenu-badge">' + esc(m.badge) + '</span>' : '') +
        '</span>' +
        '<span class="fy-createmenu-sub">' + esc(m.sub) + '</span>' +
        '</span></a>'
    }).join('')

    return '<div class="fy-createmenu" data-fy-createmenu>' +
      '<div class="fy-createmenu-veil" data-fy-createmenu-close></div>' +
      '<div class="fy-createmenu-card" role="dialog" aria-modal="true">' + items + '</div>' +
      '<button type="button" class="fy-createmenu-close" data-fy-createmenu-close aria-label="Close">' +
      '<img alt="" src="' + I.bClose.src + '"></button>' +
      '</div>'
  }

  function openCreateMenu() {
    var el = document.querySelector('[data-fy-createmenu]')
    if (el) el.classList.add('is-open')
  }

  function closeCreateMenu() {
    var el = document.querySelector('[data-fy-createmenu]')
    if (el) el.classList.remove('is-open')
  }

  function renderBottomBar() {
    var signedIn = !!LiveAuth.token()
    var bar = findBottomBar()

    // 页面自带的老版底栏就地改造成统一版；登录/未登录都走同一套，保证全站底栏一致
    if (!bar) {
      bar = document.createElement('div')
      document.body.appendChild(bar)
    }

    var key = activeBottomKey(signedIn)
    var items = signedIn ? BOTTOM_AUTH : BOTTOM_GUEST
    bar.className = 'fy-bnav'
    bar.setAttribute('data-variant', signedIn ? 'auth' : 'guest')
    bar.innerHTML = items.map(function (it) { return bottomItemHtml(it, key) }).join('')
    /* 挂到 body 最外层：个别页（Girls）老底栏在 <turbo-frame> 里，留在原位会被 frame 拦截/换掉 */
    if (bar.parentElement !== document.body) document.body.appendChild(bar)
  }

  function bindBottomBar() {
    if (!document.querySelector('[data-fy-createmenu]')) {
      var holder = document.createElement('div')
      holder.innerHTML = createMenuHtml()
      document.body.appendChild(holder.firstChild)
    }
    if (document.__fyBnavBound) return
    document.__fyBnavBound = true

    document.addEventListener('click', function (e) {
      var t = e.target
      if (!t || !t.closest) return
      if (t.closest('[data-fy-createmenu-open]')) { e.preventDefault(); openCreateMenu(); return }
      if (t.closest('[data-fy-createmenu-close]')) { e.preventDefault(); closeCreateMenu() }
    })
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeCreateMenu()
    })
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
    // 语言：侧栏/抽屉按钮 -> 打开原站那颗 Select your language 大弹窗；弹窗里选语言 -> 走站内 switchLang
    document.addEventListener('click', function (e) {
      var t = e.target
      if (!t || !t.closest) return
      if (t.closest('[data-fy-lang-open]')) {
        e.preventDefault()
        e.stopPropagation()
        openLangModal()
        return
      }
      // 页面自带（非外壳）的语言按钮：Stimulus toggle-modal#openModal 指向 #language-select-modal，
      // 打开的是页面里那份旧大弹窗（只有 13 种语言）。这里统一改走站内新弹窗。
      var ob = t.closest('[data-toggle-modal-modal-outlet]')
      if (ob && ob.getAttribute('data-toggle-modal-modal-outlet') === '#language-select-modal') {
        e.preventDefault()
        e.stopPropagation()
        openLangModal()
        return
      }
      var modal = t.closest('#language-select-modal')
      if (!modal) return
      if (t.closest('[data-fy-lang-close]') ||
        (t.getAttribute && (t.getAttribute('data-fy-lang-mask') || /closeModal/.test(t.getAttribute('data-action') || '')))) {
        e.preventDefault()
        closeLangModal()
        return
      }
      var el = t.closest('[data-fy-locale]')
      var form = t.closest('form')
      var input = form ? form.querySelector('input[name="new_locale"]') : null
      var code = el ? el.getAttribute('data-fy-locale') : (input ? input.value : '')
      if (!code) return
      e.preventDefault()
      e.stopPropagation()
      closeLangModal()
      if (code !== currentLang()) switchLang(code)
    }, true)

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

    // 消息通知：头像下拉 / 抽屉资料菜单里的「Notifications」打开面板
    document.addEventListener('click', function (e) {
      var t = e.target
      if (t.closest && t.closest('.fy-notif-btn')) {
        e.preventDefault()
        var menu = document.getElementById('profileDropdownMenu')
        var btn = document.getElementById('user-menu-button')
        if (menu) menu.style.display = 'none'
        if (btn) btn.setAttribute('aria-expanded', 'false')
        var dm = document.getElementById('fy-drawer-user-menu')
        if (dm) dm.classList.add('hidden')
        openNotifs()
        return
      }
      if (t.closest && t.closest('[data-fy-notif-close]')) {
        e.preventDefault()
        closeNotifs()
      }
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

    // 抽屉底部的 Category 项：页面自带站内分类切换（Home.html 的顶栏 Tab / 移动端下拉）时
    // 直接驱动它（不跳页），两处状态由 bindPageCatSync 保持同步；页面没有时才走链接跳转
    document.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('.fy-cat-item')
      if (!a) return
      var btn = pageCatBtn(a.getAttribute('data-fy-cat'))
      if (!btn) return
      e.preventDefault()
      btn.click()
      var m = document.getElementById('fy-cat-menu')
      if (m) m.classList.add('hidden')
      var b = document.getElementById('fy-cat-btn')
      if (b) b.setAttribute('aria-expanded', 'false')
    }, true)

    // 抽屉底部的 Category 大按钮：同样要断冒泡（否则抽屉会被收起）
    document.addEventListener('click', function (e) {
      var w = e.target.closest && e.target.closest('.fy-cat-wrap')
      if (!w || (e.target.closest && e.target.closest('.fy-cat-menu'))) return
      e.stopPropagation()
      var m = w.querySelector('.fy-cat-menu')
      var b = w.querySelector('.fy-cat-btn')
      if (!m) return
      var opening = m.classList.contains('hidden')
      m.classList.toggle('hidden')
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
      var cm = document.getElementById('fy-cat-menu')
      if (cm && !(e.target.closest && e.target.closest('.fy-cat-wrap'))) cm.classList.add('hidden')
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
      closeNotifs()
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
      if (isLogin) {
        resetReloadGuard()   // 重新登录后清掉自动重载的节流计数
        // 通知其它脚本「本次是重新登录」（进场弹窗据此清掉关闭标记，跳转后重新弹）
        try { document.dispatchEvent(new CustomEvent('live:login')) } catch (e) { /* 忽略 */ }
      }
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
  /* ---------- 手机端分类下拉（照 candy.ai 手机端 header：图标 + 下箭头 → 点开 Girls/Anime/Guys） ---------- */
  var CAT_ICONS = {
    girls: { g: 'female-grey-f8778c0424271c5cb4b1b50b2e50d7e8d5a2c79851c6f905e5d3ef4069956bef.svg', w: 'female-white-b6edc81820cdf305c0e1644c0c5d2124b2aa2ecef701a01eff46baad9a05dd26.svg', label: 'Girls', href: './Girls.html' },
    anime: { g: 'anime-grey-1f1c9de8c17364c1634470e5d2ab1d4d05109fa8e80c1e1b83ac0478eb2d5dac.svg', w: 'anime-white-02986b4e7107a1be01a284a4818231a6edf19aa880d8a9680fb1a7874416f532.svg', label: 'Anime', href: './Anime.html' },
    guys: { g: 'male-grey-48c8b386c214b3f674b7ef04bc51c9f7e967c49023e308fc58032fa0cc10c2e7.svg', w: 'male-white-b1c60bef78d9065208e08ef4f03bea1d2d50bc8b33c3fb2fe9361a3c83646a92.svg', label: 'Guys', href: './Guys.html' }
  }
  var CAT_CHEV = './tokens_files/vector-down-grey-ac9e18f44b255e3510c3e32a682be2b5f5f736fb7b6c7df2d867107522ebdd5d.svg'

  function currentCat() {
    var p = (location.pathname || '').toLowerCase()
    if (p.indexOf('anime') >= 0) return 'anime'
    if (p.indexOf('guys') >= 0 || p.indexOf('boyfriend') >= 0) return 'guys'
    return 'girls'
  }

  /* 顶栏分类栏（Girls/Anime/Guys）：页面自带的用页面自己的，没自带的统一在这里补一份。
     内联 SVG 与 candy.ai 原版逐字一致，选中态由 fy.css 的 .head-btn.active 上粉。 */
  var CAT_BTN_SVG = {
    girls: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">' +
      '<path d="M13.1562 5.15625C13.1562 2.30856 10.8477 0 8 0C5.15231 0 2.84375 2.30856 2.84375 5.15625C2.84375 7.68334 4.66306 9.78184 7.0625 10.2233V11.75H4.71875V13.625H7.0625V16H8H8.9375V13.625H11.2812V11.75H8.9375V10.2233C11.3369 9.78184 13.1562 7.68334 13.1562 5.15625ZM8 8.4375C6.19091 8.4375 4.71875 6.96534 4.71875 5.15625C4.71875 3.34716 6.19091 1.875 8 1.875C9.80909 1.875 11.2812 3.34716 11.2812 5.15625C11.2812 6.96534 9.80909 8.4375 8 8.4375Z" fill="#FFF" class="group-hover:fill-[#F97187] transition-colors duration-300"></path></svg>',
    anime: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 18 18" fill="none">' +
      '<path d="M11.188 10.0603C11.188 10.0603 10.6133 12.6045 8.08961 11.8994C4.70614 10.9542 8.00663 3.51085 13.5463 5.98647C20.4381 9.06585 14.1912 18.6576 5.86198 16.0584C-1.5586 13.7427 0.928903 2.12495 9.99479 1.5" stroke-width="1.9" stroke-linecap="square" stroke="#FFFFFF" class="group-hover:stroke-[#F97187] transition-colors duration-300"></path></svg>',
    guys: '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none">' +
      '<path d="M6.51176 14.6667C3.65643 14.6667 1.3335 12.3438 1.3335 9.48844C1.3335 6.63324 3.65643 4.3103 6.51176 4.3103C9.36696 4.3103 11.6899 6.63324 11.6899 9.48844C11.6899 12.3438 9.36696 14.6667 6.51176 14.6667ZM6.51176 6.24697C4.7243 6.24697 3.27003 7.7011 3.27003 9.48857C3.27003 11.276 4.72416 12.7303 6.51176 12.7303C8.2991 12.7303 9.7535 11.2762 9.7535 9.48857C9.75336 7.7011 8.2991 6.24697 6.51176 6.24697Z" fill="#F97187" class="group-hover:fill-[#F97187] transition-colors duration-300"></path>' +
      '<path d="M14.668 6.51164H12.7315V3.26991H9.48975V1.33337H14.668V6.51164Z" fill="#F97187" class="group-hover:fill-[#F97187] transition-colors duration-300"></path>' +
      '<path d="M13.0142 1.61597L14.3833 2.9851L10.174 7.19439L8.80487 5.82526L13.0142 1.61597Z" fill="#F97187" class="group-hover:fill-[#F97187] transition-colors duration-300"></path></svg>'
  }

  function catBarHtml() {
    var cur = currentCat()
    function btn(k) {
      return '<button type="button" data-cat="' + k + '" class="head-btn' + (k === cur ? ' active' : '') +
        ' w-[93px] h-full justify-center items-center inline-flex mr-5 md:mr-0 border-b-4 border-transparent group">' +
        '<div class="flex items-center">' +
        '<div class="w-4 h-4 relative mr-1 transition-colors duration-300 delay-300">' + CAT_BTN_SVG[k] + '</div>' +
        '<div class="text-white text-[13px] font-semibold leading-normal">' + CAT_ICONS[k].label + '</div>' +
        '</div></button>'
    }
    return '<div class="h-6 w-px bg-gray-900/10 lg:hidden" aria-hidden="true"></div>' +
      '<div id="head-btn-group" class="flex flex-grow-0 flex-shrink basis-0 absolute md:relative justify-center md:justify-start h-[42px] md:h-full w-full top-[64px] md:top-auto bg-[#212121] md:bg-transparent ml-0 md:ml-12 -mb-[2px] pl-0 left-0 md:left-auto">' +
      btn('girls') + btn('anime') + btn('guys') + '</div>'
  }

  /* 顶栏缺分类栏的页面就地补一份。只补「内容按 顶栏64+分类栏41 预留了高度（mt-[105px]）」的页面，
     即本来就是照原版结构做的、只是丢了这段 markup 的页面，避免影响没有该栏的定制页。 */
  function ensureCatBar(topbar) {
    if (!topbar || document.getElementById('head-btn-group')) return
    if (!document.querySelector('[class~="mt-[105px]"]')) return
    var anchor = null
    for (var i = 0; i < topbar.children.length; i++) {
      if (topbar.children[i].tagName === 'A') { anchor = topbar.children[i]; break }
    }
    if (!anchor) return
    anchor.insertAdjacentHTML('afterend', catBarHtml())
  }

  function injectCatDropdown() {
    var group = document.getElementById('head-btn-group')
    if (!group || document.getElementById('fyCatDd')) return
    var cur = currentCat()
    var ic = CAT_ICONS[cur]
    function item(k) {
      var c = CAT_ICONS[k]
      return '<a href="' + c.href + '"><img src="./tokens_files/' + c.w + '" alt=""><span>' + c.label + '</span></a>'
    }
    var wrap = document.createElement('div')
    wrap.className = 'fy-catdd'
    wrap.id = 'fyCatDd'
    wrap.innerHTML =
      '<button type="button" class="fy-catdd-btn" id="fyCatDdBtn" aria-expanded="false" aria-label="Category">' +
      '<img class="fy-catdd-ic" src="./tokens_files/' + ic.g + '" alt="">' +
      '<img class="fy-catdd-chev" src="' + CAT_CHEV + '" alt="">' +
      '</button>' +
      '<div class="fy-catdd-panel hidden" id="fyCatDdPanel">' + item('girls') + item('anime') + item('guys') + '</div>'
    group.parentNode.insertBefore(wrap, group)
    var btn = document.getElementById('fyCatDdBtn')
    var panel = document.getElementById('fyCatDdPanel')
    btn.addEventListener('click', function (e) {
      e.stopPropagation()
      var open = panel.classList.contains('hidden')
      panel.classList.toggle('hidden', !open)
      btn.setAttribute('aria-expanded', open ? 'true' : 'false')
    })
    document.addEventListener('click', function (e) {
      if (!panel.classList.contains('hidden') && !panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.add('hidden')
        btn.setAttribute('aria-expanded', 'false')
      }
    })

    // 分类切换只换内容区（Turbo Frame），顶栏不重建：这里同步高亮与下拉按钮图标
    document.addEventListener('click', function (e) {
      var a = e.target.closest ? e.target.closest('a.head-btn') : null
      if (!a) return
      var href = a.getAttribute('href') || ''
      // href 可能已被改写成 ./Home.html?cat=anime，用统一解析避免误判成 girls
      var k = catKeyFromHref(href) ||
        (href.indexOf('Anime') >= 0 ? 'anime' : (href.indexOf('Guys') >= 0 ? 'guys' : 'girls'))
      var list = document.querySelectorAll('a.head-btn')
      for (var i = 0; i < list.length; i++) {
        if (list[i] === a) {
          list[i].classList.add('active')
        } else {
          // 站点自带 main.js 会给被点的 tab 加 border-rose-400 / text-rose-400（含内层文字 div），
          // Turbo 只换内容区、顶栏不重建，这里把旧 tab 的残留粉色清掉
          list[i].classList.remove('active')
          list[i].classList.remove('border-rose-400')
          list[i].classList.remove('text-rose-400')
          var inner = list[i].querySelectorAll('*')
          for (var j = 0; j < inner.length; j++) {
            inner[j].classList.remove('border-rose-400')
            inner[j].classList.remove('text-rose-400')
          }
          var lbl = list[i].querySelector('div:last-child')
          if (lbl) lbl.classList.add('text-white')
        }
      }
      var ic = document.querySelector('#fyCatDd .fy-catdd-ic')
      if (ic) ic.src = './tokens_files/' + CAT_ICONS[k].g
    })
  }

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
    // 标记顶栏：手机端 logo/分类下拉/登录注册之间的间距由 fy.css 里 .fy-topbar 规则统一控制
    if (topbar) topbar.classList.add('fy-topbar')
    var us = findUserSetting(topbar)
    if (us) {
      if (topbar && us.parentElement !== topbar) topbar.appendChild(us)
      us.innerHTML = navRight()
    }
    ensureCatBar(topbar)
    injectCatDropdown()
    normalizeCatLinks()
    return true
  }

  function run() {
    try {
      var __bound = render()
      if (__bound) bind()
      bindSidebarTools()
      // 独立于 render()：页面没有侧栏容器时 render() 会提前 return，
      // 但顶栏分类链接仍然要改写（Chat / Game 等页就是这种情况）
      normalizeCatLinks()
      syncPageLangButtons()
      syncAuth()
      bindPageCatSync()
      renderBottomBar()
      bindBottomBar()
      bindAuthWatch()
      LiveAuth.ensureFresh()
      startAuthTimer()
      if (currentLang() !== 'en') ensureTranslate()
      // 顶栏余额：页面加载即拉真实值（模板初始是 0），登录态才拉
      try { if (localStorage.getItem('live_access_token')) window.refreshTokenBalance() } catch (e) {}
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

/* ---------- 在线状态：全站 WebSocket 长连接 ----------
 * 登录后连上 ws(s)://<host>/ws/ 并完成 auth，服务端随即把该会员登记为「在线」；
 * 关标签页 / 页内跳转 / 退出登录时连接断开，服务端立刻置为「离线」。
 * 后台会员列表的「在线/离线」因此是实时的（不再靠 5 分钟的心跳窗口猜）。
 * 游客（没有 token）不建立连接。
 */
;(function () {
  'use strict'
  if (window.__fyPresence) return
  window.__fyPresence = true

  var HEARTBEAT_MS = 30000   // 服务端 heartbeat_ttl=150s，留足「后台标签页被浏览器节流」的余量
  var RETRY_MIN = 3000
  var RETRY_MAX = 30000
  var WS_PATH = '/ws/'

  var sock = null
  var hbTimer = null
  var retryTimer = null
  var retryDelay = RETRY_MIN

  function token() {
    try {
      if (window.LiveAuth && window.LiveAuth.token) return window.LiveAuth.token()
      return localStorage.getItem('live_access_token') || ''
    } catch (e) { return '' }
  }

  function send(obj) {
    if (!sock || sock.readyState !== 1) return
    try { sock.send(JSON.stringify(obj)) } catch (e) { /* 忽略 */ }
  }

  function stopHeartbeat() {
    if (hbTimer) { clearInterval(hbTimer); hbTimer = null }
  }

  function clearRetry() {
    if (retryTimer) { clearTimeout(retryTimer); retryTimer = null }
  }

  function scheduleRetry() {
    if (retryTimer || !token()) return
    retryTimer = setTimeout(function () {
      retryTimer = null
      retryDelay = Math.min(retryDelay * 2, RETRY_MAX)
      connect()
    }, retryDelay)
  }

  function connect() {
    if (sock && (sock.readyState === 0 || sock.readyState === 1)) return
    if (!token()) return

    var url = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.host + WS_PATH
    try { sock = new WebSocket(url) } catch (e) { sock = null; scheduleRetry(); return }

    sock.onopen = function () {
      retryDelay = RETRY_MIN
      send({ type: 'auth', token: token() })
    }

    sock.onmessage = function (ev) {
      var msg = null
      try { msg = JSON.parse(ev.data) } catch (e) { return }
      if (!msg || msg.type !== 'auth_ok') return
      stopHeartbeat()
      hbTimer = setInterval(function () {
        send({ type: 'heartbeat', timestamp: Date.now() })
      }, HEARTBEAT_MS)
    }

    sock.onclose = function () {
      sock = null
      stopHeartbeat()
      scheduleRetry()
    }

    sock.onerror = function () { /* onclose 随后触发，重连交给它 */ }
  }

  /** 主动断开：服务端收到连接关闭后立即判定「离线」 */
  function teardown() {
    stopHeartbeat()
    clearRetry()
    if (sock) {
      try { sock.onclose = null; sock.close() } catch (e) { /* 忽略 */ }
      sock = null
    }
  }

  // 登录成功后立刻连上（登录页不跳转也生效）
  document.addEventListener('live:login', function () { retryDelay = RETRY_MIN; connect() })

  // 退出登录：点了按钮就先断开，不等接口返回与跳转
  document.addEventListener('click', function (e) {
    var el = e.target
    while (el && el !== document) {
      if (el.classList && el.classList.contains('logout')) { teardown(); return }
      el = el.parentNode
    }
  }, true)

  // 关标签页 / 页面跳转
  window.addEventListener('pagehide', teardown)
  window.addEventListener('beforeunload', teardown)
  // 从往返缓存恢复（手机端返回上一页）：重新连上
  window.addEventListener('pageshow', function (e) {
    if (e && e.persisted) { retryDelay = RETRY_MIN; connect() }
  })

  // 回到前台：后台标签页里定时器被节流，连接可能已被服务端回收，补一次
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState !== 'visible') return
    if (!sock || sock.readyState > 1) connect()
  })

  // 其它标签页退出登录
  window.addEventListener('storage', function (e) {
    if (e && e.key === 'live_access_token' && !e.newValue) teardown()
  })

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', connect)
  else connect()
})()
