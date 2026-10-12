/* chat-gift.js — 聊天礼物：登录后输入框左侧送礼按钮 + 礼物卡片 + 送礼（扣费/消息/轮播追加收礼视频）
   依赖：chat.js 暴露的 convRoleKey() / authHeaders() / appendBubble 等通过事件解耦，不直接引用闭包。 */
(function () {
  'use strict'

  var API = '/api/live'
  var giftsCache = null       // [{id,gift_key,name,icon,price}]
  var enabledCache = null     // 当前角色是否有素材
  var checkedKey = ''         // 已查询 enabled 的角色键
  var sending = false

  function token() {
    try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' }
  }

  function roleKey() {
    // 与 chat.js 的归档规则一致：数字 content_id 或 home-N（读当前 URL，聊天页的权威来源）
    var id = '';
    try { id = new URLSearchParams(location.search).get('id') || '' } catch (e) {}
    if (id === '') {
      var active = document.querySelector('#message-list a.chat-obj.active');
      if (active) id = String(active.getAttribute('data-id') || '');
    }
    if (id === '' || id === 'chat-default') return '';
    return id;
  }

  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  }) }

  /* ---------- 礼物按钮（输入框左侧）：登录 + 当前角色有素材才显示 ---------- */
  function ensureButton() {
    var bar = document.querySelector('#form .bg-\\[\\#252525\\]') || document.getElementById('message_body');
    var holder = bar && bar.parentElement;
    var btn = document.getElementById('cg-gift-btn');
    if (!holder || btn) return;
    btn = document.createElement('button');
    btn.type = 'button';
    btn.id = 'cg-gift-btn';
    btn.style.cssText = 'flex:0 0 auto;width:38px;height:38px;margin-right:8px;border:none;border-radius:50%;background:linear-gradient(268deg,#fc768a,#e65073);cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(231,82,117,.35)';
    btn.title = 'Send a gift';
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>';
    btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openPanel() });
    holder.insertBefore(btn, holder.firstChild);
    refresh();
  }

  /* 拉礼物配置 + 当前角色是否可用；决定按钮显示 */
  function refresh() {
    var btn = document.getElementById('cg-gift-btn');
    if (!btn) return;
    var tk = token();
    var rk = roleKey();
    if (!tk || rk === '') { btn.style.display = 'none'; return }
    if (giftsCache && checkedKey === rk) {
      btn.style.display = enabledCache && giftsCache.length ? 'flex' : 'none';
      return;
    }
    fetch(API + '/chatGifts?role_key=' + encodeURIComponent(rk))
      .then(function (r) { return r.json() })
      .then(function (d) {
        if (!d || d.code !== '00000' || !d.data) return;
        giftsCache = d.data.gifts || [];
        enabledCache = !!d.data.enabled;
        checkedKey = rk;
        btn.style.display = enabledCache && giftsCache.length ? 'flex' : 'none';
      })
      .catch(function () {});
  }

  /* ---------- 礼物卡片 ---------- */
  function openPanel() {
    var old = document.getElementById('cg-panel');
    if (old) { old.remove(); return }
    var gifts = giftsCache || [];
    var cards = gifts.map(function (g) {
      return '<button type="button" class="cg-item" data-id="' + esc(g.id) + '" data-price="' + esc(g.price) + '" data-name="' + esc(g.name) + '" data-icon="' + esc(g.icon) + '">' +
        '<img src="' + esc(g.icon) + '" alt="" loading="lazy">' +
        '<div class="cg-name">' + esc(g.name) + '</div>' +
        '<div class="cg-price"><svg width="11" height="11" viewBox="0 0 24 24" fill="#fbbf24"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>' + esc(g.price) + '</div>' +
        '</button>';
    }).join('');
    var el = document.createElement('div');
    el.id = 'cg-panel';
    el.innerHTML =
      '<div class="cg-mask" data-close="1"></div>' +
      '<div class="cg-box">' +
      '<button type="button" class="cg-x" data-close="1">✕</button>' +
      '<div class="cg-title">Send a gift 🎁</div>' +
      '<div class="cg-sub">She\'ll love it — a special moment unlocked</div>' +
      '<div class="cg-grid">' + cards + '</div>' +
      '</div>';
    document.body.appendChild(el);
    el.addEventListener('click', function (e) {
      if (e.target.closest('[data-close]')) { el.remove(); return }
      var item = e.target.closest('.cg-item');
      if (item) { sendGift(item, el) }
    });
  }

  /* ---------- 送礼：扣费 → 消息区礼物气泡 + 轮播追加收礼视频 ---------- */
  function sendGift(item, panel) {
    if (sending) return;
    var tk = token();
    if (!tk) { location.href = './Login.html?redirect=' + encodeURIComponent(location.pathname + location.search); return }
    var rk = roleKey();
    var id = item.getAttribute('data-id');
    var name = item.getAttribute('data-name');
    var price = item.getAttribute('data-price');
    if (!window.confirm('Send ' + name + ' for ' + price + ' tokens?')) return;
    sending = true;
    item.style.opacity = .5;

    var fd = new FormData();
    fd.append('role_key', rk);
    fd.append('gift_id', id);
    fd.append('device_id', (function () { try { return localStorage.getItem('live_device_id') || 'web' } catch (e) { return 'web' } })());
    fetch(API + '/sendChatGift', { method: 'POST', headers: { Authorization: 'Bearer ' + tk }, body: fd })
      .then(function (r) { return r.json() })
      .then(function (d) {
        sending = false;
        item.style.opacity = 1;
        if (!d || d.code !== '00000' || !d.data) {
          alert((d && d.msg) ? ('Failed: ' + d.msg) : 'Failed to send gift');
          return;
        }
        panel.remove();
        onGiftSent(d.data, name);
        try { if (window.refreshTokenBalance) window.refreshTokenBalance() } catch (e) {}
      })
      .catch(function () {
        sending = false; item.style.opacity = 1;
        alert('Network error, please try again');
      });
  }

  /* 送礼成功：消息区追加礼物视频气泡 + 右侧轮播追加 */
  function onGiftSent(data, name) {
    var media = (data && data.media) || {};
    // 1) 消息区：礼物气泡（用户侧 + 收礼视频）
    var box = document.getElementById('messages_turbo_frame') || document.getElementById('messages');
    if (box && media.url) {
      var row = document.createElement('div');
      row.className = 'user-response px-4 py-2';
      row.innerHTML =
        '<div class="flex justify-end"><div class="flex-col gap-[9px] inline-flex ml-auto items-end">' +
        '<div class="js-bubble text-white text-sm font-normal" style="white-space:pre-wrap;background:#303030;padding:10px 12px;border-radius:10px;max-width:80%">🎁 You sent a <b>' + esc(media.title || name) + '</b></div>' +
        '<div style="background:#303030;border-radius:10px;overflow:hidden;max-width:80%"><video src="' + esc(media.url) + '" controls playsinline style="display:block;max-width:260px;max-height:340px"></video></div>' +
        '<div class="text-neutral-500 text-[13px] font-normal">now</div>' +
        '</div></div>';
      box.appendChild(row);
      box.scrollTop = box.scrollHeight;
    }
    // 2) 右侧资料轮播：追加收礼视频（与角色人设图并列，可左右切换）
    var carousel = document.querySelector('.carousel');
    if (carousel && media.url) {
      var slide = document.createElement('div');
      slide.className = 'carousel-item';
      slide.innerHTML = '<video src="' + esc(media.url) + '" controls playsinline style="width:100%;height:100%;object-fit:cover;background:#000"></video>';
      carousel.appendChild(slide);
    }
  }

  /* ---------- 样式 + 启动 ---------- */
  var css = document.createElement('style');
  css.textContent =
    '#cg-panel{position:fixed;inset:0;z-index:150;font-family:Poppins,-apple-system,sans-serif}' +
    '#cg-panel .cg-mask{position:absolute;inset:0;background:rgba(0,0,0,.7);-webkit-backdrop-filter:blur(4px);backdrop-filter:blur(4px)}' +
    '#cg-panel .cg-box{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);width:calc(100vw - 32px);max-width:480px;background:#171317;border:1px solid rgba(255,255,255,.09);border-radius:18px;padding:22px 18px 18px;box-shadow:0 24px 60px rgba(0,0,0,.6)}' +
    '#cg-panel .cg-x{position:absolute;top:12px;right:14px;width:30px;height:30px;border:none;border-radius:50%;background:rgba(255,255,255,.08);color:#fff;cursor:pointer;font-size:13px}' +
    '#cg-panel .cg-title{color:#fff;font-size:17px;font-weight:800;margin-bottom:4px}' +
    '#cg-panel .cg-sub{color:rgba(255,255,255,.45);font-size:12px;margin-bottom:16px}' +
    '#cg-panel .cg-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}' +
    '#cg-panel .cg-item{background:#241d22;border:1px solid rgba(255,255,255,.07);border-radius:14px;padding:12px 8px;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:7px;transition:transform .12s}' +
    '#cg-panel .cg-item:hover{transform:translateY(-2px);border-color:rgba(249,113,135,.5)}' +
    '#cg-panel .cg-item img{width:52px;height:52px;object-fit:cover;border-radius:10px}' +
    '#cg-panel .cg-name{color:#fff;font-size:12.5px;font-weight:600}' +
    '#cg-panel .cg-price{color:#fbbf24;font-size:12px;font-weight:700;display:flex;align-items:center;gap:3px}';
  document.head.appendChild(css);

  function boot() { ensureButton() }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  document.addEventListener('turbo:load', function () { boot(); refresh() });
  // 切换会话后刷新按钮可见性（会话点击由 chat.js 委托，这里用面板 DOM 变化兜底）
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('#message-list a.chat-obj')) setTimeout(refresh, 60);
  }, true);
})();
