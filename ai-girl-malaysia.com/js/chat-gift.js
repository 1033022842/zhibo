/* chat-gift.js — 聊天礼物：登录后输入框左侧送礼按钮 + 礼物卡片 + 送礼（扣费/好感/消息/轮播追加收礼视频）
   与 chat.js 通过 DOM 解耦，不直接引用其闭包。 */
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

  /* ---------- 礼物按钮：与输入框同一行（输入框自动缩短，不挤布局） ---------- */
  function ensureButton() {
    var input = document.getElementById('message_body');
    var holder = input && input.parentElement;      // .bg-[#252525] 输入行容器（flex）
    var btn = document.getElementById('cg-gift-btn');
    if (!holder || !input) return;
    if (!btn) {
      btn = document.createElement('button');
      btn.type = 'button';
      btn.id = 'cg-gift-btn';
      btn.title = 'Send a gift';
      btn.innerHTML = '<svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="4" rx="1"/><path d="M12 8v13M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-7"/><path d="M7.5 8a2.5 2.5 0 0 1 0-5C11 3 12 8 12 8s1-5 4.5-5a2.5 2.5 0 0 1 0 5"/></svg>';
      btn.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); openPanel() });
      holder.insertBefore(btn, input);
      // 输入框改为弹性填充（覆盖模板写死的 w-[87%]，按钮不出现时也不影响）
      input.style.flex = '1 1 auto';
      input.style.width = 'auto';
      input.style.minWidth = '0';
    }
    btn.style.cssText = 'flex:0 0 auto;width:34px;height:34px;margin-right:8px;border:none;border-radius:10px;' +
      'background:linear-gradient(268deg,#fc768a,#e65073);cursor:pointer;display:none;align-items:center;justify-content:center;' +
      'box-shadow:0 3px 10px rgba(231,82,117,.3)';
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

  /* 站内风格确认框（原生 confirm 兜底） */
  function confirmSend(name, price) {
    return new Promise(function (resolve) {
      var msg = 'Send "' + name + '" for ' + price + ' tokens?'
      if (window.SugusDialog) {
        SugusDialog.confirm({
          title: 'Send a gift',
          message: msg,
          confirmText: 'Send 🎁',
          cancelText: 'Cancel'
        }).then(resolve)
      } else {
        resolve(window.confirm(msg))
      }
    })
  }

  /* ---------- 送礼：扣费 → 消息区礼物气泡 + 好感刷新 + 轮播追加收礼视频 ---------- */
  function sendGift(item, panel) {
    if (sending) return;
    var tk = token();
    if (!tk) { location.href = './Login.html?redirect=' + encodeURIComponent(location.pathname + location.search); return }
    var rk = roleKey();
    var id = item.getAttribute('data-id');
    var name = item.getAttribute('data-name');
    var price = item.getAttribute('data-price');
    confirmSend(name, price).then(function (ok) {
      if (!ok) return;
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
            var failMsg = (d && d.msg) || 'Please try again later';
            if (window.SugusDialog) {
              SugusDialog.alert(failMsg, { title: 'Send failed' })
            } else {
              alert(failMsg)
            }
            return;
          }
          panel.remove();
          onGiftSent(d.data, name);
          // 顶栏余额实时刷新
          try { if (window.refreshTokenBalance) window.refreshTokenBalance() } catch (e) {}
        })
        .catch(function () {
          sending = false; item.style.opacity = 1;
          alert('Network error, please try again');
        });
    });
  }

  /* 送礼成功：消息气泡 + 好感进度 + 轮播追加 */
  function onGiftSent(data, name) {
    var media = (data && data.media) || {};

    // 1) 消息区：礼物气泡（用户侧 + 收礼视频，可点播）
    var box = document.getElementById('messages_turbo_frame') || document.getElementById('messages');
    if (box && media.url) {
      var row = document.createElement('div');
      row.className = 'user-response px-4 py-2';
      row.innerHTML =
        '<div class="flex justify-end"><div class="flex-col gap-[9px] inline-flex ml-auto items-end">' +
        '<div class="js-bubble text-white text-sm font-normal" style="white-space:pre-wrap;background:#303030;padding:10px 12px;border-radius:10px;max-width:80%">🎁 You sent a <b style="color:#fbbf24">' + esc(media.title || name) + '</b>' +
        (data.affection && data.affection.gain ? ' <span style="color:#F97187">+' + esc(data.affection.gain) + ' ❤</span>' : '') +
        '</div>' +
        '<div style="background:#303030;border-radius:10px;overflow:hidden;max-width:80%"><video src="' + esc(media.url) + '" controls playsinline style="display:block;max-width:260px;max-height:340px"></video></div>' +
        '<div class="text-neutral-500 text-[13px] font-normal">now</div>' +
        '</div></div>';
      box.appendChild(row);
      box.scrollTop = box.scrollHeight;
    }

    // 2) 右侧好感进度条刷新（与 chat.js 的 spendBox 同一套 DOM）
    if (data.affection) {
      var affBox = document.getElementById('js-spend');
      if (affBox) {
        var max = Number(data.affection.max || 100) || 100;
        var val = Math.max(0, Math.min(max, Number(data.affection.affection || 0)));
        var affEl = affBox.querySelector('.js-sp-aff');
        var valEl = affBox.querySelector('.js-sp-aff-val');
        var fillEl = affBox.querySelector('.js-sp-aff-fill');
        if (affEl) affEl.style.display = 'block';
        if (valEl) valEl.textContent = val + ' / ' + max;
        if (fillEl) fillEl.style.width = Math.round(val / max * 100) + '%';
      }
    }

    // 3) 右侧资料轮播：追加收礼视频并重绑导航（新 item 可切换，视频静音自动循环播放）
    if (media.url) {
      giftSlides.push(media.url);
      appendCarouselSlide(media.url);
      watchCarousel();
    }
  }

  /* 本页送礼的收礼视频（Turbo 二次初始化会重置 carousel，观察变化后重放） */
  var giftSlides = [];
  var carouselWatched = false;
  var selfMutating = false;

  function appendCarouselSlide(url) {
    var carousel = document.querySelector('.carousel');
    if (!carousel) return;
    var slide = document.createElement('div');
    slide.className = 'carousel-item';
    slide.innerHTML = '<video src="' + esc(url) + '" muted autoplay loop playsinline style="width:100%;height:100%;object-fit:cover;background:#000"></video>';
    selfMutating = true;
    carousel.appendChild(slide);
    rebindCarousel();
    setTimeout(function () { selfMutating = false }, 0);
  }

  /* carousel 被外部重置（chat.js 的 getDetail 用角色图重建）时，把礼物视频补回去 */
  function watchCarousel() {
    if (carouselWatched) return;
    var carousel = document.querySelector('.carousel');
    if (!carousel || typeof MutationObserver === 'undefined') return;
    carouselWatched = true;
    var observer = new MutationObserver(function () {
      if (selfMutating) return;
      // 外部重置：检查礼物视频是否还在，不在则重放
      var urls = Array.prototype.map.call(carousel.querySelectorAll('video'), function (v) { return v.getAttribute('src') || '' });
      var missing = giftSlides.filter(function (u) { return urls.indexOf(u) === -1 });
      if (missing.length) {
        missing.forEach(appendCarouselSlide);
      } else if (carousel.querySelectorAll('.carousel-item').length > 1 && carousel.querySelectorAll('.dot').length !== carousel.querySelectorAll('.carousel-item').length) {
        rebindCarousel();   // item 数与圆点数不一致 → 只需重绑
      }
    });
    observer.observe(carousel, { childList: true });
  }

  /* 轮播导航重绑：application.js 的旧绑定按快照工作（看不到新 item），这里 off 后统一接管 + 重建圆点 */
  function rebindCarousel() {
    var $items = document.querySelectorAll('.carousel-item');
    var dotsWrap = document.querySelector('.carousel-dots');
    var prev = document.getElementById('prevBtn');
    var next = document.getElementById('nextBtn');
    if (!$items.length || !prev || !next) return;
    // 重建圆点
    if (dotsWrap) {
      dotsWrap.innerHTML = '';
      for (var i = 0; i < $items.length; i++) {
        var d = document.createElement('span');
        d.className = 'dot inline-block w-[10px] h-[10px] bg-gray-400 rounded-full mx-1 cursor-pointer';
        d.setAttribute('data-index', i);
        dotsWrap.appendChild(d);
      }
    }
    // 解除旧绑定（含 application.js 的），统一接管
    prev.outerHTML = prev.outerHTML;
    next.outerHTML = next.outerHTML;
    prev = document.getElementById('prevBtn');
    next = document.getElementById('nextBtn');
    var cur = document.querySelector('.carousel-item.active');
    var curIdx = Array.prototype.indexOf.call($items, cur);
    if (curIdx < 0) curIdx = 0;

    function show(i) {
      curIdx = (i + $items.length) % $items.length;
      for (var k = 0; k < $items.length; k++) $items[k].classList.toggle('active', k === curIdx);
      var dots = document.querySelectorAll('.dot');
      for (var j = 0; j < dots.length; j++) dots[j].classList.toggle('active', j === curIdx);
      // 当前视频静音循环播放
      var v = $items[curIdx] && $items[curIdx].querySelector('video');
      if (v) { v.muted = true; try { v.play() } catch (e) {} }
    }
    next.addEventListener('click', function () { show(curIdx + 1) });
    prev.addEventListener('click', function () { show(curIdx - 1) });
    if (dotsWrap) dotsWrap.addEventListener('click', function (e) {
      var t = e.target.closest('.dot');
      if (t) show(parseInt(t.getAttribute('data-index'), 10) || 0);
    });
    show(curIdx);
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
  // 切换会话后刷新按钮可见性
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('#message-list a.chat-obj')) setTimeout(refresh, 60);
  }, true);
})();
