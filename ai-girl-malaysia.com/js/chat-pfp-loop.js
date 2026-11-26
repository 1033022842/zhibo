/* Profile 头图轮播（#pfp-carousel）的动态图渐进加载。
   第 1 张：角色静态封面 cover 直接显示。
   第 2 张：img 先当 poster（也是 cover），只有这一张第一次变成 active 时才把 loop_url 赋给 <video> 并播放；
           切走就 pause，回到第 1 张不会多耗流量。
   接口：GET /api/live/personaProfile?role_key=<id> -> { data: { name, cover, loop_url } }
   该文件不依赖 chat.js，也不改 chat.js：chat.js 的 paintPersona 会整块重写 .carousel，
   所以这里用 MutationObserver 兜底，重写后自动补回 video。 */
(function () {
	'use strict';

	var API_BASE = '/api/live';
	var VIDEO_CLASS = 'fy-pfp-loop';

	var coverUrl = '';
	var loopUrl = '';
	var roleKey = '';
	var scheduled = 0;
	var observing = false;

	/* ---------- role_key：地址栏 ?id= 优先，回退消息列表里选中的角色 ---------- */
	function urlRoleKey() {
		var m = /[?&]id=([^&#]*)/.exec(window.location.search || '');
		if (!m) return '';
		var v = m[1].replace(/\+/g, ' ');
		try { v = decodeURIComponent(v) } catch (e) {}
		return v;
	}

	function activeRoleKey() {
		var a = document.querySelector('#message-list a.chat-obj.active, #message-list a.chat-obj[data-active="1"]');
		if (!a) a = document.querySelector('#message-list a.chat-obj');
		return a ? (a.getAttribute('data-id') || '') : '';
	}

	function currentRoleKey() {
		var k = urlRoleKey();
		if (k) return k;
		return activeRoleKey();
	}

	/* ---------- DOM 工具 ---------- */
	function carousel() {
		return document.querySelector('#pfp-carousel .carousel');
	}

	function slides() {
		var c = carousel();
		return c ? c.querySelectorAll('.carousel-item') : [];
	}

	function imgOf(item) {
		return item ? item.querySelector('img') : null;
	}

	function isActive(item) {
		if (!item) return false;
		return (' ' + (item.className || '') + ' ').indexOf(' active ') > -1;
	}

	function activeIndex() {
		var list = slides();
		for (var i = 0; i < list.length; i++) {
			if (isActive(list[i])) return i;
		}
		return -1;
	}

	/* ---------- 视频：第 2 张里的一层，激活前 src 为空 ---------- */
	function ensureVideo(item) {
		if (!item) return null;
		var v = item.querySelector('.' + VIDEO_CLASS);
		if (v) return v;
		v = document.createElement('video');
		v.className = VIDEO_CLASS;
		v.muted = true;
		v.defaultMuted = true;
		v.loop = true;
		v.setAttribute('muted', '');
		v.setAttribute('loop', '');
		v.setAttribute('playsinline', '');
		v.setAttribute('webkit-playsinline', '');
		v.setAttribute('preload', 'none');
		v.setAttribute('aria-hidden', 'true');
		if (loopUrl) v.setAttribute('data-src', loopUrl);
		v.addEventListener('canplay', function () {
			if (isActive(item)) v.classList.add('is-on');
		});
		v.addEventListener('error', function () {
			v.classList.remove('is-on');
		});
		item.appendChild(v);
		return v;
	}

	function pauseVideo(v, drop) {
		if (!v) return;
		try { v.pause() } catch (e) {}
		v.classList.remove('is-on');
		if (drop && v.getAttribute('src')) {
			v.removeAttribute('src');
			try { v.load() } catch (e) {}
		}
	}

	function playVideo(v) {
		if (!v) return;
		var src = v.getAttribute('data-src') || loopUrl;
		if (!src) return;
		if (!v.getAttribute('src')) v.setAttribute('src', src);
		v.muted = true;
		var p = v.play();
		if (p && p.catch) p.catch(function () {});
		if (v.readyState >= 3) v.classList.add('is-on');
	}

	function addSecondSlide() {
		var box = carousel();
		if (!box) return;
		var first = box.querySelector('.carousel-item');
		if (!first) return;
		var clone = document.createElement('div');
		clone.className = 'carousel-item';
		var im = document.createElement('img');
		im.className = first.querySelector('img') ? first.querySelector('img').className : 'w-full h-full object-cover';
		im.setAttribute('alt', '');
		clone.appendChild(im);
		box.appendChild(clone);
		/* 补一个圆点，保证两张可以来回切 */
		var dotsBox = document.querySelector('#pfp-carousel .carousel-dots');
		if (dotsBox && dotsBox.querySelectorAll('.dot').length < 2) {
			var d0 = dotsBox.querySelector('.dot');
			var d1 = d0 ? d0.cloneNode(false) : document.createElement('button');
			d1.className = (d0 ? d0.className : 'dot').replace(' active', '');
			d1.setAttribute('data-index', '1');
			dotsBox.appendChild(d1);
		}
	}

	/* ---------- 同步：封面 + 第 2 张的按需动图 ---------- */
	function sync() {
		if (!carousel()) return;
		var list = slides();
		if (!list.length) return;
		/* chat.js 的 paintPersona 只画一张，这里补出第 2 张用作动图位 */
		if (list.length < 2) {
			addSecondSlide();
			list = slides();
		}
		if (list.length < 2) return;

		if (coverUrl) {
			var i0 = imgOf(list[0]);
			if (i0 && i0.getAttribute('src') !== coverUrl) i0.setAttribute('src', coverUrl);
			var i1 = imgOf(list[1]);
			if (i1 && i1.getAttribute('src') !== coverUrl) i1.setAttribute('src', coverUrl);
		}

		var second = list[1];
		var v = ensureVideo(second);
		if (!v) return;

		/* loop_url 为空：第 2 张保持静态图 */
		if (!loopUrl) {
			pauseVideo(v, true);
			return;
		}
		if (!v.getAttribute('data-src')) v.setAttribute('data-src', loopUrl);

		if (isActive(second)) playVideo(v);
		else pauseVideo(v, true);
	}

	function schedule(delay) {
		if (scheduled) return;
		scheduled = window.setTimeout(function () {
			scheduled = 0;
			sync();
		}, delay || 0);
	}

	/* ---------- 监听：class 变化（本身/自动轮播）+ 结构变化（chat.js 重写 carousel） ---------- */
	function watch() {
		if (observing) return;
		var root = document.getElementById('pfp-carousel');
		if (!root) return;
		if (!window.MutationObserver) return;
		observing = true;
		var mo = new MutationObserver(function () { schedule(0) });
		mo.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
	}

	/* ---------- 兜底切页：页面上没有其它控制器时，dots / 左右箭头仍然可用 ---------- */
	function show(index) {
		var list = slides();
		if (!list.length) return;
		if (index < 0) index = list.length - 1;
		if (index >= list.length) index = 0;
		for (var i = 0; i < list.length; i++) {
			if (i === index) list[i].classList.add('active');
			else list[i].classList.remove('active');
		}
		var dots = document.querySelectorAll('#pfp-carousel .carousel-dots .dot');
		for (var j = 0; j < dots.length; j++) {
			if (j === index) dots[j].classList.add('active');
			else dots[j].classList.remove('active');
		}
		schedule(0);
	}

	function step(delta) {
		var list = slides();
		var cur = activeIndex();
		if (cur < 0) cur = 0;
		show((cur + delta + list.length) % list.length);
	}

	/* 记录点击前的下标；如果别的脚本（chat-gift.js）已经切过页就不再动手 */
	function bindNav() {
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.closest) return;
			var hit = 0;
			if (t.closest('#pfp-carousel .carousel-dots .dot')) hit = 1;
			else if (t.closest('#nextBtn')) hit = 2;
			else if (t.closest('#prevBtn')) hit = 3;
			if (!hit) return;
			var before = activeIndex();
			window.setTimeout(function () {
				if (activeIndex() !== before) return;
				if (hit === 1) {
					var dot = t.closest('#pfp-carousel .carousel-dots .dot');
					if (!dot) return;
					var idx = parseInt(dot.getAttribute('data-index'), 10);
					if (!isNaN(idx)) show(idx);
					return;
				}
				step(hit === 2 ? 1 : -1);
			}, 0);
		}, false);
	}

	/* ---------- 数据 ---------- */
	function applyPayload(res) {
		var d = (res && res.data) || res || {};
		coverUrl = typeof d.cover === 'string' ? d.cover : (d.cover_url || '');
		loopUrl = typeof d.loop_url === 'string' ? d.loop_url : (d.loopUrl || '');
		schedule(0);
	}

	function load(key) {
		if (!key) return;
		if (!window.fetch) return;
		fetch(API_BASE + '/personaProfile?role_key=' + encodeURIComponent(key), {
			credentials: 'same-origin',
			headers: { 'Accept': 'application/json' }
		}).then(function (r) {
			return r.json();
		}).then(function (res) {
			if (key !== roleKey) return;
			applyPayload(res);
		}).catch(function () {});
	}

	function boot() {
		roleKey = currentRoleKey();
		watch();
		bindNav();
		schedule(0);
		load(roleKey);
		/* 页面里可能先是占位内容，稍后再对齐一次；有 MutationObserver 时只作为弱兜底 */
		window.setTimeout(sync, 400);
		window.setTimeout(sync, 1200);
		window.setTimeout(function () {
			if (!roleKey) {
				roleKey = currentRoleKey();
				load(roleKey);
			}
			sync();
		}, 2000);
		/* chat.js 的 paintPersona 可能在更晚的时机整块重写轮播，这里持续兜底补齐第 2 张 */
		var tries = 0;
		var tick = window.setInterval(function () {
			tries += 1;
			sync();
			if (tries >= 20) window.clearInterval(tick);
		}, 800);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
