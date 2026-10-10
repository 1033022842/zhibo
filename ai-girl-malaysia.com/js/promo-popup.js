/* 进场弹窗：已登录用户进入任意页面自动弹出；登录成功后清掉「本会话已关闭」标记，跳转后重新弹出。
   结构与样式对应 .c-promo*（原实现在 Home.html 内联，现抽为公共脚本供全站页面引用） */
(function () {
  if (window.__sugusPromoReady) return
  window.__sugusPromoReady = true

  /* 总开关：弹窗导流到 face_swap（AI 换脸），该功能尚未上线，暂时不弹；
     上线后改回 true 恢复（HTML 引用版本号记得同步升级） */
  var ENABLED = false

  var DISMISS_KEY = 'sugus.promo.dismissed'
  var LOGIN_EVENT = 'live:login'

  var CSS = [
    '.c-promo{display:none}',
    '.c-promo.open{display:block}',
    '.c-promo-mask{position:fixed;inset:0;z-index:120;background:rgba(0,0,0,.7);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}',
    '.c-promo-panel{position:fixed;left:50%;top:50%;transform:translate(-50%,-50%);z-index:121;box-sizing:border-box;width:calc(100vw - 32px);max-width:327px;padding:0;overflow:hidden;border:1.5px solid #5b3a43;border-radius:20px;background:#0d0a0f;box-shadow:0 24px 60px rgba(0,0,0,.6);font-family:Poppins,-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Microsoft YaHei",sans-serif}',
    '.c-promo-x{position:absolute;top:20px;right:20px;z-index:2;width:32px;height:32px;padding:6px;border:none;border-radius:50%;background:rgba(0,0,0,.32);color:#fff;cursor:pointer;-webkit-backdrop-filter:blur(18px);backdrop-filter:blur(18px);display:flex;align-items:center;justify-content:center}',
    '.c-promo-x svg{width:20px;height:20px;display:block}',
    '.c-promo-media{position:relative;width:100%;aspect-ratio:4/5;overflow:hidden;background:#161616}',
    '.c-promo-media img{width:100%;height:100%;display:block;object-fit:cover;object-position:center 20%}',
    '.c-promo-media:after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(to top,#0d0a0f 0%,rgba(13,10,15,0) 55%)}',
    '.c-promo-body{position:relative;z-index:1;margin-top:-34px;padding:0 16px 20px;display:flex;flex-direction:column;align-items:center;gap:14px}',
    '.c-promo-title{margin:0;font-size:18px;font-weight:800;line-height:125%;color:#fff;text-align:center}',
    '.c-promo-title em{font-style:normal;color:#f97187}',
    '.c-promo-sub{margin:0;font-size:12.5px;font-weight:500;line-height:18px;color:#a4a4a4;text-align:center}',
    '.c-promo-cta{display:flex;align-items:center;justify-content:center;width:100%;height:48px;border-radius:12px;font-size:15px;font-weight:700;color:#fff;text-decoration:none;background:linear-gradient(268deg,#fc768a,#e65073);box-shadow:0 8px 22px rgba(231,82,117,.35)}',
    '@media (max-height:680px){.c-promo-media{aspect-ratio:1/1}}',
  ].join('')

  var HTML = [
    '<div class="c-promo-mask" data-promo-close></div>',
    '<div class="c-promo-panel">',
    '  <button class="c-promo-x" type="button" data-promo-close aria-label="Close">',
    '    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    '  </button>',
    '  <div class="c-promo-media"><img src="./assets/login-bg-full.jpg" alt=""></div>',
    '  <div class="c-promo-body">',
    '    <h2 class="c-promo-title" id="cPromoTitle">Upload Any Photo of her and Get a <em>hot 18+ Video</em></h2>',
    '    <p class="c-promo-sub">Fully uncensored &middot; 160+ hot poses &middot; Free to try</p>',
    '    <a class="c-promo-cta" href="./face_swap.html">Try Free Now</a>',
    '  </div>',
    '</div>',
  ].join('\n')

  function readS(k) { try { return sessionStorage.getItem(k) || '' } catch (e) { return '' } }
  function writeS(k, v) { try { sessionStorage.setItem(k, v) } catch (e) { /* 隐私模式忽略 */ } }
  function dropS(k) { try { sessionStorage.removeItem(k) } catch (e) { /* 忽略 */ } }

  function token() {
    try {
      if (window.LiveAuth && LiveAuth.token) return LiveAuth.token() || ''
      return localStorage.getItem('live_access_token') || ''
    } catch (e) { return '' }
  }

  function close() {
    var el = document.getElementById('cPromo')
    if (el) el.classList.remove('open')
    writeS(DISMISS_KEY, '1')
  }

  function build() {
    if (document.getElementById('cPromo')) return true
    if (!document.body) return false
    var style = document.createElement('style')
    style.textContent = CSS
    document.head.appendChild(style)
    var el = document.createElement('div')
    el.id = 'cPromo'
    el.className = 'c-promo'
    el.setAttribute('role', 'dialog')
    el.setAttribute('aria-modal', 'true')
    el.setAttribute('aria-labelledby', 'cPromoTitle')
    el.innerHTML = HTML
    document.body.appendChild(el)
    el.addEventListener('click', function (e) {
      if (e.target.closest && e.target.closest('[data-promo-close]')) close()
    })
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && el.classList.contains('open')) close()
    })
    return true
  }

  /* 已登录 + 本会话没关闭过 → 弹出 */
  function maybeShow() {
    if (!ENABLED) return
    if (!build()) return
    if (!token()) return
    if (readS(DISMISS_KEY) === '1') return
    document.getElementById('cPromo').classList.add('open')
  }

  /* 登录成功：清掉关闭标记，跳转后的新页面会重新弹 */
  document.addEventListener(LOGIN_EVENT, function () { dropS(DISMISS_KEY) })

  function boot() { maybeShow() }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot)
  } else {
    boot()
  }
  // Turbo 局部导航会换掉 body，重新注入并判断
  document.addEventListener('turbo:load', boot)
  document.addEventListener('turbo:render', boot)
})()
