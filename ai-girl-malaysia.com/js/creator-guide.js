/**
 * Creator Earning Guide - site-wide onboarding popup
 *
 * Behavior:
 *   · Shows once per login session: auto-opens 1.2s after load, then stays hidden on further page
 *     views. It opens again when the login token changes (i.e. next login).
 *   · 未登录（含登录页）从不弹。
 *   · Clicking "Don't show again" disables it permanently (localStorage: creator_guide_seen_v2=1)
 *   · Closing (X / Maybe later) does not disable it
 *   · "Start creating" goes to the Creator Center ./crowdfunding.html
 *   · 创作中心（crowdfunding.html）不自动弹，但可通过 CreatorGuide.open() 手动打开完整指引
 */
(function () {
  if (window.__creatorGuideLoaded) return;
  window.__creatorGuideLoaded = true;

  // Skip inside admin iframes
  try { if (window.self !== window.top) return; } catch (e) { return; }

  // Creator Center (crowdfunding.html) has its own inline tutorial: skip auto-open there,
  // but keep CreatorGuide.open() available so its "How it works?" / "Full guide" buttons work
  var SKIP_AUTO = /crowdfunding\.html$/i.test(location.pathname || '');

  var KEY = 'creator_guide_seen_v2';
  var CENTER_URL = './crowdfunding.html';
  // Force open via ?guide=1
  var FORCE = /[?&]guide=1/.test(location.search);

  // Track which login this guide was already shown for (per browser session)
  var TOKEN_KEY = 'live_access_token';
  var SESS_KEY = 'creator_guide_tok';
  var SESSION_KEY = 'creator_guide_session';
  var LAST_KEY = 'creator_guide_last';

  /** 后台配置（GET /api/live/popupConfig），失败时保持旧版行为 */
  var DEFAULTS = { enabled: true, delay: 1, frequency: 'login', interval_hours: 24 };

  function getToken() {
    try { return localStorage.getItem(TOKEN_KEY) || ''; } catch (e) { return ''; }
  }
  function shownToken() {
    try { return sessionStorage.getItem(SESS_KEY); } catch (e) { return null; }
  }
  function markShown(freq) {
    try {
      if (freq === 'login') sessionStorage.setItem(SESS_KEY, getToken());
      else if (freq === 'session') sessionStorage.setItem(SESSION_KEY, '1');
      else if (freq === 'interval') localStorage.setItem(LAST_KEY, String(Date.now()));
      else if (freq === 'once') setSeen();
    } catch (e) {}
  }

  function isSeen() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function setSeen() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  }

  function loadConfig() {
    return fetch('/api/live/popupConfig?t=' + Date.now(), { cache: 'no-store' })
      .then(function (r) { return r.json() })
      .then(function (d) {
        var c = d && d.code === '00000' && d.data && d.data.guide;
        if (!c) return DEFAULTS;
        return {
          enabled: c.enabled !== false,
          delay: parseInt(c.delay, 10) || 0,
          frequency: c.frequency || 'login',
          interval_hours: parseInt(c.interval_hours, 10) || 24
        };
      })
      .catch(function () { return DEFAULTS; });
  }

  /**
   * 是否到弹出时机（登录后才弹；同一登录/会话/间隔/永久只弹一次）
   */
  function freqAllows(freq, hours) {
    var tok = getToken();
    if (tok === '') return false;

    if (freq === 'session') {
      try { return sessionStorage.getItem(SESSION_KEY) !== '1'; } catch (e) { return true; }
    }
    if (freq === 'interval') {
      var last = 0;
      try { last = parseInt(localStorage.getItem(LAST_KEY) || '0', 10) || 0; } catch (e) { last = 0; }
      return Date.now() - last >= Math.max(1, hours) * 3600 * 1000;
    }
    if (freq === 'always' || freq === 'once') return true;

    // login（默认）：每次登录后的第一个页面弹一次，同一登录的后续页面不再弹
    var prev = shownToken();
    if (prev === null || prev === '') return true;
    return tok !== prev;
  }

  var CSS = ''
    + '.cg-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.66);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:200;align-items:center;justify-content:center;padding:16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Poppins,Raleway,"PingFang SC","Microsoft YaHei",sans-serif}'
    + '.cg-overlay.cg-show{display:flex}'
    + '.cg-box{position:relative;background:#1b1b1b;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:20px 18px 18px;width:360px;max-width:100%;max-height:calc(100vh - 32px);overflow-y:auto;box-sizing:border-box;box-shadow:0 24px 60px rgba(0,0,0,.6)}'
    + '.cg-close{position:absolute;top:14px;right:16px;width:36px;height:36px;border-radius:50%;border:1px solid rgba(255,255,255,.2);background:transparent;color:rgba(255,255,255,.7);font-size:20px;line-height:1;cursor:pointer}'
    + '.cg-close:hover{color:#fff;border-color:rgba(255,255,255,.4)}'
    + '.cg-title{font-size:17px;font-weight:800;color:#fff;margin-bottom:6px}'
    + '.cg-sub{font-size:13px;color:rgba(255,255,255,.5);line-height:1.6;margin-bottom:14px}'
    + '.cg-step{display:flex;gap:10px;margin-bottom:10px}'
    + '.cg-idx{flex:0 0 26px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;background:linear-gradient(268deg,#fc768a,#e65073)}'
    + '.cg-txt{font-size:13px;color:rgba(255,255,255,.78);line-height:1.6}'
    + '.cg-txt b{color:#fff}'
    + '.cg-cta{width:100%;padding:14px;margin-top:6px;border:none;border-radius:12px;background:linear-gradient(268deg,#fc768a,#e65073);color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}'
    + '.cg-later{display:block;width:100%;margin-top:10px;border:none;background:none;color:rgba(255,255,255,.62);font-size:13px;cursor:pointer;font-family:inherit}'
    + '.cg-later:hover{color:rgba(255,255,255,.85)}'
    + '.cg-never{display:block;width:100%;margin-top:6px;border:none;background:none;color:rgba(255,255,255,.5);font-size:12.5px;cursor:pointer;font-family:inherit;text-decoration:underline}'
    + '.cg-never:hover{color:rgba(255,255,255,.8)}';

  var HTML = ''
    + '<div class="cg-overlay" id="cgOverlay">'
    + '  <div class="cg-box">'
    + '    <button class="cg-close" type="button" data-cg="close">&#10005;</button>'
    + '    <div class="cg-title">Creator Earning Guide</div>'
    + '    <div class="cg-sub">Create your own AI characters on Sugus, publish them, and earn from their revenue.</div>'
    + '    <div class="cg-step"><div class="cg-idx">1</div><div class="cg-txt"><b>Create a character</b><br>Fill in the character details and upload the cover &amp; persona assets</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">2</div><div class="cg-txt"><b>Choose how to create</b><br>Create it on your own, or launch a crowdfunding project with the community</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">3</div><div class="cg-txt"><b>Set the publish type</b><br>Live streamer / Chat companion / Short drama (single choice), plus gifts or episodes</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">4</div><div class="cg-txt"><b>Submit for review</b><br>Our team checks the assets before going live — track the status in My Creations</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">5</div><div class="cg-txt"><b>Go live and earn</b><br>Revenue from chats, gifts and episodes is split by the platform ratio</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">6</div><div class="cg-txt"><b>Withdraw anytime</b><br>Payouts are after tax; your income is listed in the Creator Center</div></div>'
    + '    <button class="cg-cta" type="button" data-cg="start">' + (SKIP_AUTO ? 'Got it' : 'Start creating') + '</button>'
    + '    <button class="cg-later" type="button" data-cg="close">Maybe later</button>'
    + '    <button class="cg-never" type="button" data-cg="never">Don\'t show again</button>'
    + '  </div>'
    + '</div>';

  var overlay = null;

  function ensure() {
    if (overlay) return overlay;
    var style = document.createElement('style');
    style.id = 'cgStyle';
    style.textContent = CSS;
    document.head.appendChild(style);

    var wrap = document.createElement('div');
    wrap.innerHTML = HTML;
    overlay = wrap.firstChild;
    document.body.appendChild(overlay);

    overlay.addEventListener('click', function (e) {
      var t = e.target;
      if (t === overlay) { hide(); return; }
      var act = t && t.getAttribute ? t.getAttribute('data-cg') : null;
      if (act === 'close') { hide(); }
      else if (act === 'never') { setSeen(); hide(); }
      else if (act === 'start') {
        hide();
        if (SKIP_AUTO) {
          // 创作中心里不跳走，直接带用户去看「选择创作方式」
          var tabs = document.getElementById('tabs');
          if (tabs) tabs.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
          location.href = CENTER_URL;
        }
      }
    });
    return overlay;
  }

  var scrollLocked = false;
  function lockScroll(on) {
    var root = document.documentElement;
    if (!root) return;
    if (on && !scrollLocked) { root.style.overflow = 'hidden'; scrollLocked = true; }
    else if (!on && scrollLocked) { root.style.overflow = ''; scrollLocked = false; }
  }
  function show() { ensure().classList.add('cg-show'); lockScroll(true); }
  function hide() { if (overlay) overlay.classList.remove('cg-show'); lockScroll(false); }

  function autoShow(force) {
    if (!force && isSeen()) return;
    show();
  }

  function init() {
    if (FORCE) {
      ensure();
      setTimeout(function(){ autoShow(true) }, 200);
      return;
    }
    // 创作中心：页面自带内联指引，弹窗只在点「How it works?」时按需打开
    if (SKIP_AUTO) return;
    ensure();
    if (isSeen()) return;
    if (getToken() === '') return;

    loadConfig().then(function (cfg) {
      if (!cfg || !cfg.enabled) return;
      if (!freqAllows(cfg.frequency, cfg.interval_hours)) return;
      var delay = Math.max(0, Math.min(60, parseInt(cfg.delay, 10) || 0)) * 1000;
      setTimeout(function () {
        if (isSeen()) return;
        autoShow(true);
        markShown(cfg.frequency);
      }, delay);
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.CreatorGuide = { open: show, close: hide };
})();
