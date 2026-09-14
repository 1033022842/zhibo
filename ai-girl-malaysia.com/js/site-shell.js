/**
 * 站点共享外壳（侧边栏 / 顶栏 / 移动端导航）
 * 用法：页面 <head> 引入本文件，<body> 开头调用 SiteShell.render('crowdfunding')
 * 依赖同目录的 Girls_files/application-*.css、assets/fy.css、js/main.js、js/event.js
 */
(function () {
  var ICON = {
    home: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 12 3l9 6.5"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/></svg>',
    crowdfunding: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#a855f7" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
    explore: '<img class="w-6 h-6" src="./characters_files/explore-3a4a39d3c6126c743969aa14b3a4841763f5b600e0f77e317a32d7c30afbcc13.svg">',
    chat: '<img class="w-6 h-6" src="./Chat_files/chat-1f356f466c9023c851a3a185fd1e607229737246758457a3f49b82e17bc5d82b.svg">',
    generate: '<img class="w-6 h-6" src="./characters_files/generate-33f95391d5d800565b7ea9ba3f6effa0a3d0a2b2eeafdba4ccc526aef7ae3498.svg">',
    create: '<img class="w-6 h-6" src="./characters_files/magic-wand-bc43a2a37c108cc6308370c0e6fada5385fd848143063f66116694fd9aa075b7.svg">',
    myai: '<img class="w-6 h-6" src="./characters_files/love-lady-760605daa886b7e49e77d5d26c1047bc0f815c005a978a5c76810db20bc355dd.svg">',
    upload: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg>',
    gallery: '<img class="w-6 h-6" src="./Chat_files/gallery-b83c72cb24c980d5e254daba38d4e3690f281b07c3e5665a052e0f76fff031b8.svg">',
    game: '<svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="6" y1="11" x2="10" y2="11"/><line x1="8" y1="9" x2="8" y2="13"/><line x1="15" y1="12" x2="15.01" y2="12"/><line x1="18" y1="10" x2="18.01" y2="10"/><rect x="2" y="6" width="20" height="12" rx="4"/></svg>',
    premium: '<img class="w-6 h-6" src="./characters_files/premium1-e465052b5a9b526cb7a071ff7f325ebdcb1a106e9add7d713e4a19b7a6b8fa52.svg">'
  };

  // 统一导航项：[key, href, 文字, 图标key, 附加属性]
  var NAV = [
    ['home', './Home.html', 'Home', 'home', ''],
    ['explore', './Girls.html', 'Explore', 'explore', ''],
    ['chat', './Chat.html', 'Chat', 'chat', ''],
    ['generate', './Image.html', 'Generate Image', 'generate', ' target="_top"'],
    ['create', './characters.html', 'Create Character', 'create', ''],
    ['myai', './charactersIndex.html', 'My AI', 'myai', ''],
    ['upload', './upload_media.html', 'Upload Media', 'upload', ''],
    ['gallery', './Gallery.html', 'Gallery', 'gallery', ''],
    ['game', './Game.html', 'Games', 'game', ''],
    ['crowdfunding', './index.html', 'Crowdfunding', 'crowdfunding', '']
  ];
  var PREMIUM = ['./Subscriptions.html', 'Become Premium', 'premium'];

  // 收起态图标栏（100px）
  function collapsedSidebar(active) {
    function item(row) {
      var isActive = row[0] === active;
      return '<li class="relative w-full"><a href="' + row[1] + '" title="' + row[2] + '" class="relative h-[48px] w-[52px] hover:bg-zinc-700 ' + (isActive ? 'bg-[#303030] ' : '') + 'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 flex mx-auto cursor-pointer">' + ICON[row[3]] + '</a></li>';
    }
    return '' +
      '<div data-main-target="mainSidebarClosedContainer" class="lg:fixed lg:pt-[63px] z-20 border-[#363636] border-r border-t lg:inset-y-0 lg:left-0 lg:w-[100px] lg:pb-5 w-[100px] fixed top-0 left-0 h-full webkit-transition-sidebar webkit-transform-sidebar" style="background: linear-gradient(0deg, #131313 0%, #131313 100%); max-height: 100vh">' +
      '<nav class="flex flex-col justify-between h-full">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6 overflow-y-auto overflow-x-auto">' +
      NAV.map(item).join('') +
      '</ul>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end">' +
      '<li class="relative w-full"><a href="' + PREMIUM[0] + '" title="' + PREMIUM[1] + '" class="h-[48px] w-[52px] hover:bg-zinc-700 ' + (active === 'premium' ? 'bg-[#303030] ' : '') + 'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 flex mx-auto cursor-pointer">' + ICON[PREMIUM[2]] + '</a></li>' +
      '</ul></nav></div>';
  }

  // 展开态侧边栏（220px）
  function expandedSidebar(active) {
    function item(row) {
      var isActive = row[0] === active;
      return '<li class="relative w-full"><a href="' + row[1] + '"' + (row[4] || '') + ' class="relative h-[40px] w-full hover:bg-zinc-700 ' + (isActive ? 'bg-[#303030] ' : '') + 'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 flex cursor-pointer">' +
        ICON[row[3]].replace(/class="w-6 h-6"/, 'class="w-4 h-4"') +
        '<span class="text-start ' + (isActive ? 'text-white' : 'text-grey-medium') + ' text-xxs font-medium leading-4">' + row[2] + '</span></a></li>';
    }
    return '' +
      '<div data-main-target="mainSidebarOpenedContainer" class="lg:fixed lg:pt-[63px] z-20 border-[#363636] border-r border-t lg:inset-y-0 lg:left-0 lg:w-[220px] lg:pb-5 w-[220px] fixed top-0 left-0 h-full webkit-transition-sidebar pc-nav" style="background: linear-gradient(0deg, #131313 0%, #131313 100%); max-height: 100vh">' +
      '<nav class="flex flex-col justify-between h-full">' +
      '<ul role="list" class="py-8 lg:py-6 flex flex-col gap-2 items-center justify-start px-6 overflow-y-auto overflow-x-auto">' +
      NAV.map(item).join('') +
      '</ul>' +
      '<ul role="list" class="flex flex-col gap-2 pt-6 px-6 border-[#363636] border-t items-center justify-end">' +
      '<li class="relative w-full"><a href="' + PREMIUM[0] + '" class="h-[40px] hover:bg-zinc-700 ' + (active === 'premium' ? 'bg-[#303030] ' : '') + 'rounded-[10px] border border-white border-opacity-10 justify-start px-3 items-center gap-2 flex cursor-pointer">' + ICON[PREMIUM[2]].replace(/class="w-6 h-6"/, 'class="w-4 h-4"') + '<span class="text-start text-pink-default text-xxs font-medium leading-4">' + PREMIUM[1] + '</span></a></li>' +
      '</ul></nav></div>';
  }

  // 移动端抽屉
  function mobileDrawer(active) {
    function row(row2) {
      var isActive = row2[0] === active;
      return '<li class="relative w-full py-4 border-b border-white border-opacity-10"><a href="' + row2[1] + '" class="w-full flex justify-start items-center gap-2">' +
        ICON[row2[3]].replace(/class="w-6 h-6"/, 'class="w-5 h-5"') +
        '<span class="text-start ' + (isActive ? 'text-white' : 'text-grey-medium') + ' text-xs leading-5 font-semibold">' + row2[2] + '</span></a></li>';
    }
    return '' +
      '<div data-main-target="mainMobileSidebarContainer" class="inset-y-0 w-[100vw] mt-[64px] z-[100] pr-[40vw] no-scroll fixed left-0 webkit-transition-sidebar webkit-transform-sidebar">' +
      '<nav class="flex flex-col justify-between h-full w-[240px]" style="background: linear-gradient(0deg, #131313 0%, #131313 100%)">' +
      '<div class="flex flex-col justify-between h-full">' +
      '<div class="flex flex-1 self-stretch lg:gap-x-6 bg-main user-login"><div class="gap-x-2 flex px-5 py-5">' +
      '<div class="flex gap-x-4 lg:gap-x-6"><a href="./Login.html" class="w-[90px] h-8 px-2 md:px-4 py-1.5 bg-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer no-underline"><div class="text-white text-sm font-semibold leading-tight">Register</div></a></div>' +
      '<div class="flex gap-x-4 lg:gap-x-6"><a href="./Login.html" class="w-[90px] h-8 px-2 md:px-4 py-1.5 border-[#E75275] border text-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer no-underline"><div class="text-sm font-semibold leading-tight">Login</div></a></div>' +
      '</div></div>' +
      '<ul role="list" class="flex flex-col items-center justify-start px-5 bg-zinc-900 h-screen overflow-auto">' +
      NAV.map(row).join('') +
      '<li class="relative w-full py-4 border-b border-white border-opacity-10"><a href="' + PREMIUM[0] + '" class="w-full flex justify-start items-center gap-2">' + ICON[PREMIUM[2]].replace(/class="w-6 h-6"/, 'class="w-5 h-5"') + '<span class="text-start text-pink-default text-xs leading-5 font-semibold">' + PREMIUM[1] + '</span></a></li>' +
      '</ul></div></nav></div>';
  }

  // 顶部导航栏
  function topbar() {
    function channel(href, label, svg) {
      return '<form class="w-[93px] h-full inline-flex mr-5 md:mr-0" onsubmit="return false;" action="' + href + '" accept-charset="UTF-8" method="post">' +
        '<button type="button" class="head-btn w-full h-full justify-center items-center inline-flex border-b-4 border-transparent group" onclick="location.href=\'' + href + '\'">' +
        '<div class="flex items-center"><div class="w-4 h-4 relative mr-1">' + svg + '</div>' +
        '<div class="text-white text-[13px] font-semibold leading-normal">' + label + '</div></div></button></form>';
    }
    var girls = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M13.1562 5.15625C13.1562 2.30856 10.8477 0 8 0C5.15231 0 2.84375 2.30856 2.84375 5.15625C2.84375 7.68334 4.66306 9.78184 7.0625 10.2233V11.75H4.71875V13.625H7.0625V16H8H8.9375V13.625H11.2812V11.75H8.9375V10.2233C11.3369 9.78184 13.1562 7.68334 13.1562 5.15625ZM8 8.4375C6.19091 8.4375 4.71875 6.96534 4.71875 5.15625C4.71875 3.34716 6.19091 1.875 8 1.875C9.80909 1.875 11.2812 3.34716 11.2812 5.15625C11.2812 6.96534 9.80909 8.4375 8 8.4375Z" fill="#FFF"></path></svg>';
    var anime = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 18 18" fill="none"><path d="M11.188 10.0603C11.188 10.0603 10.6133 12.6045 8.08961 11.8994C4.70614 10.9542 8.00663 3.51085 13.5463 5.98647C20.4381 9.06585 14.1912 18.6576 5.86198 16.0584C-1.5586 13.7427 0.928903 2.12495 9.99479 1.5" stroke-width="1.9" stroke-linecap="square" stroke="#FFFFFF"></path></svg>';
    var guys = '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="none"><path d="M6.51176 14.6667C3.65643 14.6667 1.3335 12.3438 1.3335 9.48844C1.3335 6.63324 3.65643 4.3103 6.51176 4.3103C9.36696 4.3103 11.6899 6.63324 11.6899 9.48844C11.6899 12.3438 9.36696 14.6667 6.51176 14.6667ZM6.51176 6.24697C4.7243 6.24697 3.27003 7.7011 3.27003 9.48857C3.27003 11.276 4.72416 12.7303 6.51176 12.7303C8.2991 12.7303 9.7535 11.2762 9.7535 9.48857C9.75336 7.7011 8.2991 6.24697 6.51176 6.24697Z" fill="#F97187"></path><path d="M14.668 6.51164H12.7315V3.26991H9.48975V1.33337H14.668V6.51164Z" fill="#F97187"></path><path d="M13.0142 1.61597L14.3833 2.9851L10.174 7.19439L8.80487 5.82526L13.0142 1.61597Z" fill="#F97187"></path></svg>';

    return '' +
      '<div class="bg-main fixed w-full top-0 z-40 flex h-16 shrink-0 items-center border-b border-[#363636] bg-main sm:px-4 px-2 shadow-sm sm:gap-x-2 lg:px-8">' +
      '<button id="nav-hamburger" type="button" class="py-2 sm:mr-0 md:mr-[10px] mr-0 text-gray-700 nav-hamburger"><span class="sr-only">Open sidebar</span>' +
      '<svg class="nav-hamburger h-8 w-8 mr-1 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"></path></svg></button>' +
      '<a href="./Girls.html"><img src="./Girls_files/sugusai-navbar-d321c31d58617371f7caf9e34a4e3f9624dce580afcceb6fecdf48c78beafd83.svg" class="h-[30px] md:h-[30px] w-[115px]"></a>' +
      '<div class="h-6 w-px bg-gray-900/10 lg:hidden" aria-hidden="true"></div>' +
      '<div id="head-btn-group" class="flex flex-grow-0 flex-shrink basis-0 absolute md:relative justify-center md:justify-start h-[42px] md:h-full w-full top-[64px] md:top-auto bg-[#212121] md:bg-transparent ml-0 md:ml-12 -mb-[2px] pl-0 left-0 md:left-auto">' +
      channel('./Girls.html', 'Girls', girls) + channel('./Anime.html', 'Anime', anime) + channel('./Guys.html', 'Guys', guys) +
      '</div>' +
      '<div class="flex flex-1 self-stretch lg:gap-x-6 bg-main user-login pc-user-login">' +
      '<div class="gap-x-2 flex pc-rg-login ml-auto items-center">' +
      '<div class="flex items-center gap-x-4 lg:gap-x-6"><a href="./Login.html" class="w-[90px] h-8 px-2 md:px-4 py-1.5 bg-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer no-underline"><div class="text-white text-sm font-semibold leading-tight">Register</div></a></div>' +
      '<div class="flex items-center gap-x-4 lg:gap-x-6"><a href="./Login.html" class="w-[90px] h-8 px-2 md:px-4 py-1.5 border-[#E75275] border text-[#E75275] rounded-lg justify-center items-center gap-2 inline-flex cursor-pointer no-underline"><div class="text-sm font-semibold leading-tight">Login</div></a></div>' +
      '</div></div>' +
      '<div id="user-setting" style="display:none;" class="flex flex-1 self-stretch lg:gap-x-6 bg-main">' +
      '<div class="flex items-center gap-x-4 lg:gap-x-6 ml-auto">' +
      '<a href="./charactersIndex.html" class="justify-center items-center flex gap-2 px-3 py-1.5 rounded-xl bg-[#1f1f1f] border border-white border-opacity-10 no-underline"><img src="./characters_files/love-lady-760605daa886b7e49e77d5d26c1047bc0f815c005a978a5c76810db20bc355dd.svg" class="w-5 h-5"><div class="text-white text-sm font-semibold">My AI</div></a>' +
      '<a href="./upload_media.html" class="hidden md:inline-flex justify-center items-center gap-2 px-3 py-1.5 rounded-xl bg-[#1f1f1f] border border-white border-opacity-10 no-underline"><svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="#E75275" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/></svg><div class="text-white text-sm font-semibold">Upload Media</div></a>' +
      '<button id="shell-logout" type="button" class="justify-center items-center flex gap-2 px-3 py-1.5 rounded-xl bg-[#1f1f1f] border border-white border-opacity-10"><div class="text-white text-sm font-semibold">Logout</div></button>' +
      '</div></div>' +
      '</div>';
  }

  // 移动端底部导航
  function bottomNav() {
    function tab(href, img, label) {
      return '<a href="' + href + '" class="flex flex-col items-center"><img src="' + img + '" class="w-6 h-6"><span class="text-[#A1A1A1] text-[10px] mt-1">' + label + '</span></a>';
    }
    return '<div><div class="flex px-5 py-2 justify-between w-full h-[70px] fixed bottom-0 z-50 border-t border-white border-opacity-20 bg-black-default lg:hidden">' +
      tab('./Girls.html', './Image_files/explore-active-ca279297c446de8d9227ffbadf9fef01e001cd35d665ec3620b02c9c35cab08c.svg', 'Explore') +
      tab('./Image.html', './Girls_files/generate-e0a4445659c35cbc82e7046cf089cf881179d55eaa82d4bf32dae229a06666c1.svg', 'Generate') +
      tab('./characters.html', './Girls_files/mobile-magic-wand-d445265367be8ddfbfe1c22810893d0ea4dbdaf1eadd7c4496861113d5388d78.svg', 'Create') +
      tab('./Subscriptions.html', './Girls_files/premium-bef4c0d01b6b99b6f2764cf40b75597debc307331857ca26f97628c6a38eec.svg', 'Premium') +
      '</div></div>';
  }

  function render(active) {
    active = active || 'home';
    var html = collapsedSidebar(active) + expandedSidebar(active) + mobileDrawer(active) + topbar() + bottomNav();
    document.body.insertAdjacentHTML('afterbegin', html);
    bind(active);
  }

  function bind(active) {
    var closedEl = document.querySelector('[data-main-target="mainSidebarClosedContainer"]');
    var openedEl = document.querySelector('[data-main-target="mainSidebarOpenedContainer"]');
    var drawerEl = document.querySelector('[data-main-target="mainMobileSidebarContainer"]');
    var pcOpened = true, drawerOpen = false;

    function applyPc() {
      if (closedEl) closedEl.classList.toggle('webkit-transform-sidebar', pcOpened);
      if (openedEl) openedEl.classList.toggle('webkit-transform-sidebar', !pcOpened);
      applyContentOffset();
    }
    function applyContentOffset() {
      // 内容区避让侧栏：展开态 220px，收起态 100px（与主站一致）
      var main = document.querySelector('.main-content-container main');
      if (!main) return;
      if (window.innerWidth >= 1024) {
        main.style.marginLeft = (pcOpened ? 220 : 100) + 'px';
      } else {
        main.style.marginLeft = '';
      }
    }
    function applyDrawer() {
      if (drawerEl) drawerEl.classList.toggle('webkit-transform-sidebar', !drawerOpen);
    }
    window.addEventListener('resize', applyContentOffset);
    // 内容区可能在脚本之后才解析，DOM 就绪后再应用一次避让
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', applyContentOffset);
    }

    var btn = document.getElementById('nav-hamburger');
    if (btn) {
      btn.addEventListener('click', function () {
        if (window.innerWidth < 1024) { drawerOpen = !drawerOpen; applyDrawer(); }
        else { pcOpened = !pcOpened; applyPc(); }
      });
    }
    if (drawerEl) {
      drawerEl.addEventListener('click', function (e) {
        // 点击抽屉右侧的透明区域（背景）时关闭
        if (!drawerEl.querySelector('nav').contains(e.target)) { drawerOpen = false; applyDrawer(); }
      });
    }
    var logout = document.getElementById('shell-logout');
    if (logout) {
      logout.addEventListener('click', function () {
        localStorage.removeItem('live_access_token');
        localStorage.removeItem('live_user');
        location.href = './Login.html';
      });
    }

    // 登录态：有 token 显示个人区，否则显示 Register/Login
    var token = localStorage.getItem('live_access_token');
    var userSetting = document.getElementById('user-setting');
    var loginBoxes = document.querySelectorAll('.user-login');
    if (token) {
      if (userSetting) userSetting.style.display = 'flex';
      for (var i = 0; i < loginBoxes.length; i++) loginBoxes[i].style.display = 'none';
    } else {
      if (userSetting) userSetting.style.display = 'none';
      for (var j = 0; j < loginBoxes.length; j++) loginBoxes[j].style.display = 'flex';
    }

    applyPc();
    applyDrawer();
  }

  window.SiteShell = { render: render };
})();
