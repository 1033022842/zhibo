/* Profile 头图（#pfp-carousel）：第 1 张静态人设图，第 2 张动态图，可来回切。
   实现要点：不新增 .carousel-item（会破坏 chat.js 的轮播布局），而是把 <video> 作为
   覆盖层放进同一个 item 里，用两个圆点切换「图 / 动图」两种模式。
   - 进入页面默认「图」，video 不下载；
   - 点第 2 个点：赋 src 并播放；
   - 点第 1 个点：暂停并回到静态图。
   接口：GET /api/live/personaProfile?role_key=<id> -> { data: { name, cover, loop_url } } */
(function () {
	'use strict';

	var API_BASE = '/api/live';
	var VIDEO_CLASS = 'fy-pfp-loop';
	var MODE_CLASS = 'fy-mode-video';
	var STYLE_ID = 'fyPfpLoopCss';

	var coverUrl = '';
	var loopUrl = '';
	var roleKey = '';
	var scheduled = 0;
	var observing = false;
	var mode = 'photo';

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

	/* ---------- DOM ---------- */
	function carousel() {
		return document.querySelector('#pfp-carousel .carousel');
	}

	function firstItem() {
		var c = carousel();
		return c ? c.querySelector('.carousel-item') : null;
	}

	function dots() {
		var box = document.querySelector('#pfp-carousel .carousel-dots');
		return box ? box.querySelectorAll('.dot') : [];
	}

	/* ---------- 样式：动图只是覆盖层 ---------- */
	function ensureCss() {
		if (document.getElementById(STYLE_ID)) return;
		var s = document.createElement('style');
		s.id = STYLE_ID;
		s.textContent =
			'#pfp-carousel .carousel-item{position:relative}' +
			'#pfp-carousel .carousel-item>.' + VIDEO_CLASS + '{position:absolute;inset:0;width:100%;height:100%;' +
			'object-fit:cover;object-position:center;opacity:0;transition:opacity .18s ease;pointer-events:none;z-index:2}' +
			'#pfp-carousel .carousel-item.' + MODE_CLASS + '>.' + VIDEO_CLASS + '.is-on{opacity:1}';
		(document.head || document.documentElement).appendChild(s);
	}

	/* ---------- 视频覆盖层 ---------- */
	function ensureVideo() {
		var item = firstItem();
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
			if (mode === 'video') v.classList.add('is-on');
		});
		v.addEventListener('error', function () {
			v.classList.remove('is-on');
		});
		item.appendChild(v);
		return v;
	}

	function playVideo() {
		var v = ensureVideo();
		var item = firstItem();
		if (!v || !item) return;
		var src = v.getAttribute('data-src') || loopUrl;
		if (!src) return;
		if (v.getAttribute('src') !== src) {
			v.setAttribute('src', src);
			try { v.load() } catch (e) {}   /* preload=none 时必须显式 load，否则永不发起请求 */
		}
		v.muted = true;
		v.preload = 'auto';
		item.classList.add(MODE_CLASS);
		var p = v.play();
		if (p && p.catch) p.catch(function () {});
		if (v.readyState >= 3) v.classList.add('is-on');
		/* 公网/签名 URL 首包慢，readyState 一次不够：等真正播起来再显示 */
		if (!v.getAttribute('data-bound')) {
			v.setAttribute('data-bound', '1');
			var show = function () { v.classList.add('is-on'); };
			v.addEventListener('loadeddata', show);
			v.addEventListener('playing', show);
			v.addEventListener('canplay', show);
			var tries = 0;
			var timer = setInterval(function () {
				tries++;
				if (v.readyState >= 2 || tries > 40) {
					clearInterval(timer);
					if (v.readyState >= 2) show();
				}
			}, 250);
		}
	}

	function stopVideo(drop) {
		var v = firstItem() ? firstItem().querySelector('.' + VIDEO_CLASS) : null;
		var item = firstItem();
		if (item) item.classList.remove(MODE_CLASS);
		if (!v) return;
		try { v.pause() } catch (e) {}
		v.classList.remove('is-on');
		if (drop && v.getAttribute('src')) {
			v.removeAttribute('src');
			try { v.load() } catch (e) {}
		}
	}

	function setMode(next) {
		if (next === 'video') {
			if (!loopUrl) return;
			mode = 'video';
		} else {
			mode = 'photo';
		}
		paintDots();
		if (mode === 'video') playVideo();
		else stopVideo(true);
	}

	function paintDots() {
		var ds = dots();
		for (var i = 0; i < ds.length; i++) {
			if (i === 0) ds[i].classList.toggle('active', mode === 'photo');
			else ds[i].classList.toggle('active', mode === 'video');
		}
	}

	/* 只留 2 个圆点：一个图、一个动图 */
	function ensureDots() {
		var box = document.querySelector('#pfp-carousel .carousel-dots');
		if (!box) return;
		var ds = box.querySelectorAll('.dot');
		var i;
		for (i = 2; i < ds.length; i++) box.removeChild(ds[i]);
		while (box.querySelectorAll('.dot').length < 2) {
			var d0 = box.querySelector('.dot');
			var d = d0 ? d0.cloneNode(false) : document.createElement('button');
			d.className = (d0 ? d0.className : 'dot').replace('active', '').replace(/\s+/g, ' ').trim();
			d.setAttribute('data-index', String(box.querySelectorAll('.dot').length));
			box.appendChild(d);
		}
		var list = box.querySelectorAll('.dot');
		list[0].setAttribute('data-index', '0');
		list[1].setAttribute('data-index', '1');
		list[0].setAttribute('title', 'Photo');
		list[1].setAttribute('title', 'Motion');
	}

	function sync() {
		if (!carousel()) return;
		ensureCss();
		if (coverUrl) {
			var img = firstItem() ? firstItem().querySelector('img') : null;
			if (img && img.getAttribute('src') !== coverUrl) img.setAttribute('src', coverUrl);
		}
		ensureDots();
		if (loopUrl) {
			var v = ensureVideo();
			if (v && !v.getAttribute('data-src')) v.setAttribute('data-src', loopUrl);
		}
		if (mode === 'video') playVideo();
		else stopVideo(false);
		paintDots();
	}

	function schedule(delay) {
		if (scheduled) return;
		scheduled = window.setTimeout(function () {
			scheduled = 0;
			sync();
		}, delay || 0);
	}

	function watch() {
		if (observing) return;
		var root = document.getElementById('pfp-carousel');
		if (!root || !window.MutationObserver) return;
		observing = true;
		var mo = new MutationObserver(function (recs) {
			/* chat.js 重绘 carousel 会把 video 层一起冲掉，需要补回 */
			for (var i = 0; i < recs.length; i++) {
				if (recs[i].type === 'childList' && recs[i].addedNodes.length) {
					schedule(0);
					return;
				}
			}
		});
		mo.observe(root, { childList: true, subtree: true });
	}

	/* ---------- 点击：捕获阶段拦下圆点，避免被 chat.js 的轮播逻辑带走 ---------- */
	function bindNav() {
		document.addEventListener('click', function (e) {
			var t = e.target;
			if (!t || !t.closest) return;
			var dot = t.closest('#pfp-carousel .carousel-dots .dot');
			var navPrev = t.closest('#pfp-carousel #prevBtn, #prevBtn');
			var navNext = t.closest('#pfp-carousel #nextBtn, #nextBtn');
			if (!dot && !navPrev && !navNext) return;
			e.preventDefault();
			e.stopPropagation();
			if (dot) {
				var ds = dots();
				var idx = Array.prototype.indexOf.call(ds, dot);
				if (idx < 0) idx = parseInt(dot.getAttribute('data-index'), 10) || 0;
				setMode(idx === 0 ? 'photo' : 'video');
				return;
			}
			/* 左右箭头：在「人设图 / 动图」之间来回切 */
			setMode(mode === 'video' ? 'photo' : 'video');
		}, true);
	}

	/* ---------- 数据 ---------- */
	function applyPayload(res) {
		var d = (res && res.data) || res || {};
		coverUrl = typeof d.cover === 'string' ? d.cover : (d.cover_url || '');
		loopUrl = typeof d.loop_url === 'string' ? d.loop_url : (d.loopUrl || '');
		if (!loopUrl && mode === 'video') mode = 'photo';
		schedule(0);
	}

	function load(key) {
		if (!key || !window.fetch) return;
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
		ensureCss();
		bindNav();
		watch();
		var tries = 0;
		var timer = window.setInterval(function () {
			tries++;
			var k = currentRoleKey();
			if (k && k !== roleKey) {
				roleKey = k;
				mode = 'photo';
				load(k);
			}
			sync();
			if (tries > 30) window.clearInterval(timer);
		}, 700);
		sync();
	}

	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
	else boot();
})();
