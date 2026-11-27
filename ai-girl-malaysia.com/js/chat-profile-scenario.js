/* Chat 右侧 Profile 面板：真实角色资料 + Scenario
 * 零侵入：不改 chat.js，只读 /api/live/personaProfile 填充面板。
 * role_key 取地址栏 id：home-12 / 12 / my-279
 */
(function () {
  'use strict';

  var CSS_ID = 'fyScenarioCss';
  var BLOCK_ID = 'fy-scenario';
  var apiBase = '/api/live';

  function urlRoleKey() {
    try {
      var p = new URLSearchParams(location.search);
      return (p.get('id') || '').trim();
    } catch (e) { return ''; }
  }

  function activeRoleKey() {
    var active = document.querySelector('#message-list a.chat-obj.active, #message-list a.chat-obj[data-active="1"]');
    if (!active) return '';
    return (active.getAttribute('data-id') || '').trim();
  }

  function currentRoleKey() {
    // 1) 地址栏的 home-* 才是用户点的那个角色：会话列表可能还停在旧角色上
    var u = urlRoleKey();
    if (/^home-\d+$/.test(u)) return u;
    // 2) 其次用会话列表当前选中项
    var a = activeRoleKey();
    if (a) return a;
    // 3) 最后回退到地址栏（my-* 等）
    return u;
  }

  function injectCss() {
    if (document.getElementById(CSS_ID)) return;
    var css = ''
      + '.fy-scenario--under-gen{margin:12px 24px 0}'
      + '.fy-scenario{margin:14px 18px 0;padding:14px 16px;background:#1b1b1b;border:1px solid #2b2b2b;border-radius:12px}'
      + '.fy-scenario-h{display:flex;align-items:center;gap:8px;font-size:11px;letter-spacing:.06em;text-transform:uppercase;color:#9a9a9a;font-weight:600}'
      + '.fy-scenario-h::before{content:"";width:6px;height:6px;border-radius:50%;background:#e75275;flex:0 0 auto}'
      + '.fy-scenario-t{margin-top:8px;font-size:13px;line-height:1.62;color:#e6e6e6;white-space:pre-wrap}'
      + '.fy-scenario-t.clamp{display:-webkit-box;-webkit-line-clamp:6;-webkit-box-orient:vertical;overflow:hidden}'
      + '.fy-scenario-more{margin-top:8px;font-size:12px;color:#e75275;cursor:pointer;user-select:none}'
      + '.fy-profile-meta{margin:12px 18px 0;display:flex;flex-wrap:wrap;gap:8px}'
      + '.fy-profile-chip{padding:5px 10px;border-radius:999px;background:#232323;color:#ddd;font-size:12px}'
      + '.fy-profile-chip b{color:#fff;font-weight:600}';
    var s = document.createElement('style');
    s.id = CSS_ID;
    s.textContent = css;
    document.head.appendChild(s);
  }

  function panelRoot() {
    var pc = document.querySelector('.profile-container');
    if (!pc) return null;
    var turbo = pc.querySelector('#profile-partial-turbo') || pc;
    return turbo;
  }

  function fillBasics(d) {
    // 面板里已有静态占位（#age / #ethnicity / #hobbies / #relationship），有真实值就覆盖
    var set = function (id, v) {
      if (!v) return;
      var el = document.getElementById(id);
      if (el) el.textContent = v;
    };
    set('age', d.age);
    set('ethnicity', d.ethnicity);
    set('hobbies', d.hobbies);
    set('relationship', d.relationship);
  }

  function renderChips(root, d) {
    if (root.querySelector('.fy-profile-meta')) return;
    var chips = [];
    if (d.age) chips.push('<span class="fy-profile-chip">Age <b>' + esc(d.age) + '</b></span>');
    if (d.ethnicity) chips.push('<span class="fy-profile-chip">Ethnicity <b>' + esc(d.ethnicity) + '</b></span>');
    if (d.personality) chips.push('<span class="fy-profile-chip">Personality <b>' + esc(d.personality) + '</b></span>');
    if (d.hobbies) chips.push('<span class="fy-profile-chip">Into <b>' + esc(d.hobbies) + '</b></span>');
    if (!chips.length) return;
    var box = document.createElement('div');
    box.className = 'fy-profile-meta';
    box.innerHTML = chips.join('');
    var firstSection = root.querySelector('.flex-col.justify-start.items-start.gap-6');
    if (firstSection) firstSection.parentNode.insertBefore(box, firstSection);
    else root.appendChild(box);
  }

  function renderScenario(root, text) {
    if (!text) return;
    var old = document.getElementById(BLOCK_ID);
    if (old) old.remove();
    var wrap = document.createElement('div');
    wrap.className = 'fy-scenario';
    wrap.id = BLOCK_ID;
    wrap.innerHTML = '<div class="fy-scenario-h">Scenario</div>'
      + '<div class="fy-scenario-t clamp" id="fy-scenario-t"></div>'
      + '<div class="fy-scenario-more" id="fy-scenario-more">Read more</div>';
    // 需求：Scenario 卡片放到右栏 “Generate Image” 按钮下方（不再跟在简介后面）
    var genLink = document.querySelector('#profile-partial-turbo a[href*="Image.html"]');
    var genBox = genLink ? (genLink.closest('.flex-1') || genLink.parentElement) : null;
    // 需求：Scenario 作为独立卡片放在年龄/属性胶囊之前（#age 所在容器的上一级）
    var ageEl = document.querySelector('#profile-partial-turbo #age') || document.getElementById('age');
    var ageHolder = ageEl ? (ageEl.closest('.flex-wrap') || ageEl.parentNode) : null;
    if (ageHolder && ageHolder.parentNode) {
      // 需求：Scenario 独立卡片，位置在 Age 那一段之后、Generate Image 之前
      var after = ageHolder.nextSibling;
      if (genBox && genBox.parentNode === ageHolder.parentNode) after = genBox;
      ageHolder.parentNode.insertBefore(wrap, after);
    } else if (ageEl && ageEl.parentNode) {
      ageEl.parentNode.insertBefore(wrap, ageEl);
    } else {
      root.appendChild(wrap);
    }
    var body = wrap.querySelector('#fy-scenario-t');
    body.textContent = text;
    var more = wrap.querySelector('#fy-scenario-more');
    // 内容不超长就不显示按钮
    requestAnimationFrame(function () {
      if (body.scrollHeight <= body.clientHeight + 2) {
        body.classList.remove('clamp');
        more.style.display = 'none';
      }
    });
    more.addEventListener('click', function () {
      var clamped = body.classList.toggle('clamp');
      more.textContent = clamped ? 'Read more' : 'Show less';
    });
  }

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }


  /* 会话列表加载完会把「上一次会话」设成 active，从而把 URL 指定的角色顶掉。
     这里主动把目标项点一下，让 chat.js 自己走正确的加载流程。 */
  function lockActiveList(key) {
    var tries = 0;
    var tick = function () {
      if (currentRoleKey() !== key) return;
      var items = document.querySelectorAll('#message-list a.chat-obj');
      var target = null;
      for (var i = 0; i < items.length; i++) {
        if ((items[i].getAttribute('data-id') || '') === key) { target = items[i]; break; }
      }
      if (target && !target.classList.contains('active')) {
        target.click();
        return;
      }
      tries += 1;
      if (tries < 12) setTimeout(tick, 500);
    };
    setTimeout(tick, 300);
  }

  /* 头部名字/头像一旦被别的会话覆盖，立刻写回（20 秒后交还给页面） */
  function guardHeader(d, key) {
    var name = (d.name || '').trim();
    if (!name) return;
    var hits = 0;
    var apply = function () {
      if (currentRoleKey() !== key) return false;
      var h = document.getElementById('chat-name');
      var n = document.getElementById('name');
      if (h && h.textContent.trim() !== name) h.textContent = name;
      if (n && n.textContent.trim() !== name) n.textContent = name;
      if (d.cover) {
        ['chat-img', 'chat-img-mobile'].forEach(function (id) {
          var im = document.getElementById(id);
          if (im && im.getAttribute('src') !== d.cover) im.setAttribute('src', d.cover);
        });
      }
      return true;
    };
    var host = document.getElementById('chat-name');
    if (!host || !host.parentNode) return;
    var obs = new MutationObserver(function () {
      if (currentRoleKey() !== key) { obs.disconnect(); return; }
      apply();
      hits += 1;
      if (hits > 60) obs.disconnect();
    });
    obs.observe(host.parentNode, { childList: true, subtree: true, characterData: true });
    setTimeout(function () { obs.disconnect(); }, 20000);
  }

  function revealName() {
    try { document.documentElement.classList.remove('fy-await-name') } catch (e) {}
  }

  function syncHeader(d, key) {
    if (!d) return;
    var tries = 0;
    var apply = function () {
      if (currentRoleKey() !== key) return; // 用户已切到别的会话就别再改
      var name = (d.name || '').trim();
      if (name) {
        var h = document.getElementById('chat-name');
        if (h && h.textContent.trim() !== name) h.textContent = name;
        revealName();
      }
      if (d.cover) {
        ['chat-img', 'chat-img-mobile'].forEach(function (id) {
          var im = document.getElementById(id);
          if (im && im.getAttribute('src') !== d.cover) im.setAttribute('src', d.cover);
        });
        var big = document.querySelector('#pfp-carousel .carousel-item img');
        if (big && big.getAttribute('src') !== d.cover) big.setAttribute('src', d.cover);
      }
      var nameEl = document.getElementById('name');
      if (name && nameEl && nameEl.textContent.trim() !== name) nameEl.textContent = name;
      var descEl = document.getElementById('infomation');
      if (d.description && descEl && descEl.textContent.trim() !== d.description.trim()) descEl.textContent = d.description;
      tries += 1;
      if (tries < 4) setTimeout(apply, 700);
    };
    apply();
  }

  function load(retry) {
    var key = currentRoleKey();
    // 随机开场：chat.js 决定角色后才把 ?id=home-N 写回地址栏，这里等它就绪
    if (!key) {
      if ((retry || 0) < 25) setTimeout(function () { load((retry || 0) + 1) }, 240);
      return;
    }
    var url = apiBase + '/personaProfile?role_key=' + encodeURIComponent(key);
    fetch(url, { headers: { 'Accept': 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        var d = (res && res.data) || null;
        if (!d) return;
        var root = panelRoot();
        if (!root) return;
        injectCss();
        fillBasics(d);
        renderChips(root, d);
        renderScenario(root, (d.scenario || '').trim());
        syncHeader(d, key);
        lockActiveList(key);
        guardHeader(d, key);
      })
      .catch(function () { /* 面板资料失败不影响聊天 */ });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
  // 弱网/首屏时序兜底：面板可能晚于脚本渲染
  window.addEventListener('load', function () { setTimeout(load, 600); });
})();
