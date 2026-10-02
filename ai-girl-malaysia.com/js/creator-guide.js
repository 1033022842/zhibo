/**
 * Creator Earning Guide - site-wide onboarding popup
 *
 * Behavior:
 *   · Auto-opens on every page load (1.2s delay); clicking "Don't show again" permanently disables it
 *     (localStorage: creator_guide_seen_v2=1)
 *   · Closing (X / Maybe later) does NOT persist, so it shows again on the next visit
 *   · "Start creating" goes to the Creator Center ./crowdfunding.html
 */
(function () {
  if (window.__creatorGuideLoaded) return;
  window.__creatorGuideLoaded = true;

  // Skip inside admin iframes
  try { if (window.self !== window.top) return; } catch (e) { return; }

  // v2 key: ignore the flag written by the older (Chinese) version, so it shows again
  var KEY = 'creator_guide_seen_v2';
  var CENTER_URL = './crowdfunding.html';
  // Force open via ?guide=1
  var FORCE = /[?&]guide=1/.test(location.search);

  function isSeen() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function setSeen() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  }

  var CSS = ''
    + '.cg-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.66);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:200;align-items:center;justify-content:center;padding:16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Poppins,Raleway,"PingFang SC","Microsoft YaHei",sans-serif}'
    + '.cg-overlay.cg-show{display:flex}'
    + '.cg-box{position:relative;background:#1b1b1b;border:1px solid rgba(255,255,255,.1);border-radius:20px;padding:30px 26px 26px;width:420px;max-width:100%;box-sizing:border-box;box-shadow:0 24px 60px rgba(0,0,0,.6)}'
    + '.cg-close{position:absolute;top:14px;right:16px;width:30px;height:30px;border-radius:50%;border:1px solid rgba(255,255,255,.14);background:transparent;color:rgba(255,255,255,.6);font-size:16px;line-height:1;cursor:pointer}'
    + '.cg-close:hover{color:#fff;border-color:rgba(255,255,255,.4)}'
    + '.cg-title{font-size:19px;font-weight:800;color:#fff;margin-bottom:6px}'
    + '.cg-sub{font-size:13px;color:rgba(255,255,255,.5);line-height:1.6;margin-bottom:20px}'
    + '.cg-step{display:flex;gap:12px;margin-bottom:16px}'
    + '.cg-idx{flex:0 0 26px;width:26px;height:26px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:#fff;background:linear-gradient(268deg,#fc768a,#e65073)}'
    + '.cg-txt{font-size:13px;color:rgba(255,255,255,.78);line-height:1.6}'
    + '.cg-txt b{color:#fff}'
    + '.cg-cta{width:100%;padding:14px;margin-top:6px;border:none;border-radius:12px;background:linear-gradient(268deg,#fc768a,#e65073);color:#fff;font-size:16px;font-weight:700;cursor:pointer;font-family:inherit}'
    + '.cg-later{display:block;width:100%;margin-top:10px;border:none;background:none;color:rgba(255,255,255,.4);font-size:13px;cursor:pointer;font-family:inherit}'
    + '.cg-never{display:block;width:100%;margin-top:6px;border:none;background:none;color:rgba(255,255,255,.28);font-size:12px;cursor:pointer;font-family:inherit;text-decoration:underline}'
    + '.cg-never:hover{color:rgba(255,255,255,.5)}';

  var HTML = ''
    + '<div class="cg-overlay" id="cgOverlay">'
    + '  <div class="cg-box">'
    + '    <button class="cg-close" type="button" data-cg="close">&#10005;</button>'
    + '    <div class="cg-title">Creator Earning Guide</div>'
    + '    <div class="cg-sub">Create your own AI characters on Sugus, publish them, and earn from their revenue.</div>'
    + '    <div class="cg-step"><div class="cg-idx">1</div><div class="cg-txt"><b>Create a character</b><br>Fill in the character details and upload assets</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">2</div><div class="cg-txt"><b>Choose how to create</b><br>Do it on your own, or start a crowdfunding project with the community</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">3</div><div class="cg-txt"><b>Publish and go live</b><br>Live streamer / Chat companion / Short drama (single choice)</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">4</div><div class="cg-txt"><b>Earn revenue</b><br>Same as live streaming, split by the backend-configured ratio (currently 5 : 5)</div></div>'
    + '    <button class="cg-cta" type="button" data-cg="start">Start creating</button>'
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
      else if (act === 'start') { hide(); location.href = CENTER_URL; }
    });
    return overlay;
  }

  function show() { ensure().classList.add('cg-show'); }
  function hide() { if (overlay) overlay.classList.remove('cg-show'); }

  function autoShow() {
    if (isSeen()) return;
    show();
  }

  function init() {
    ensure();
    if (FORCE) {
      setTimeout(autoShow, 200);
    } else if (!isSeen()) {
      // Small delay so it does not collide with the promo popup
      setTimeout(autoShow, 1200);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.CreatorGuide = { open: show, close: hide };
})();
