/**
 * 创作者赚钱指引 - 全站新手浮窗
 *
 * 行为：
 *   · 每次进入页面自动弹出（延迟 1.2s），点「不再提示」后永久关闭（localStorage: creator_guide_seen=1）
 *   · 关闭 / 稍后再说 不写入记录，下次进入仍会弹出
 *   · 点「开始创作」跳转创作中心 ./crowdfunding.html
 *   · 需求：指引用户"建立角色 → 发布作品 → 获得收益"
 */
(function () {
  if (window.__creatorGuideLoaded) return;
  window.__creatorGuideLoaded = true;

  // 后台 iframe 内不展示
  try { if (window.self !== window.top) return; } catch (e) { return; }

  var KEY = 'creator_guide_seen';
  var CENTER_URL = './crowdfunding.html';

  function isSeen() {
    try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; }
  }
  function setSeen() {
    try { localStorage.setItem(KEY, '1'); } catch (e) {}
  }

  var CSS = ''
    + '.cg-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.66);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);z-index:200;align-items:center;justify-content:center;padding:16px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}'
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
    + '    <div class="cg-title">创作者赚钱指引</div>'
    + '    <div class="cg-sub">在 Sugus 建立你自己的 AI 角色并发布作品，通过作品收益赚钱。</div>'
    + '    <div class="cg-step"><div class="cg-idx">1</div><div class="cg-txt"><b>建立角色</b><br>填写角色信息、上传资料</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">2</div><div class="cg-txt"><b>选择创作方式</b><br>独立完成，或发起众筹和大家一起共创</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">3</div><div class="cg-txt"><b>发布上线</b><br>直播角色 / 普通对话角色 / 短剧（单选）</div></div>'
    + '    <div class="cg-step"><div class="cg-idx">4</div><div class="cg-txt"><b>获得收益</b><br>与直播一致，按后台设定比例分成（当前 5 : 5）</div></div>'
    + '    <button class="cg-cta" type="button" data-cg="start">开始创作</button>'
    + '    <button class="cg-later" type="button" data-cg="close">稍后再说</button>'
    + '    <button class="cg-never" type="button" data-cg="never">不再提示</button>'
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
    if (!isSeen()) {
      // 稍微延迟，避免与进场弹窗同时出现
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
