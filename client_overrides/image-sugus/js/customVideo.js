/* Image.html — 参考站风格首屏：角色瀑布流 + 卡片详情弹窗 + 我的作品
   上传/生成视频弹窗已下线，这里只负责浏览、看详情、进聊天 */
(function () {
    var token = localStorage.getItem('live_access_token')
    var WORKS_API = '/api/customVideo/myList'

    function $(id) { return document.getElementById(id) }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    // 接口里部分文案被多重转义（例如 she&amp;#039;s），展示前逐层还原
    function decodeEntities(s) {
        s = String(s == null ? '' : s)
        for (var i = 0; i < 3 && s.indexOf('&') >= 0; i++) {
            var t = document.createElement('textarea')
            t.innerHTML = s
            if (t.value === s) break
            s = t.value
        }
        return s
    }

    /* ---------- 弹窗：通用壳（遮罩 .45 + blur8，#121212，16 圆角） ---------- */
    function openModal(id) {
        var m = $(id)
        if (!m) return
        m.classList.add('on')
        m.setAttribute('aria-hidden', 'false')
        document.documentElement.style.overflow = 'hidden'
    }

    function closeModal(id) {
        var m = $(id)
        if (!m) return
        m.classList.remove('on')
        m.setAttribute('aria-hidden', 'true')
        if (id === 'gsCardModal') {
            var v = $('gsGenVideo')
            if (v) v.pause()
            initAutoplay()
        }
        if (!document.querySelector('.gs-modal.on')) document.documentElement.style.overflow = ''
    }

    function bindModalClose() {
        document.querySelectorAll('[data-gs-close]').forEach(function (btn) {
            btn.addEventListener('click', function () { closeModal(btn.getAttribute('data-gs-close')) })
        })
        document.querySelectorAll('.gs-modal').forEach(function (m) {
            m.addEventListener('click', function (e) { if (e.target === m) closeModal(m.id) })
        })
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return
            document.querySelectorAll('.gs-modal.on').forEach(function (m) { closeModal(m.id) })
        })
    }

    /* ---------- 首屏瀑布流：站内模板素材（视频自动播放 + 封面兜底） ---------- */
    var gsList = []
    var gsFilter = 'all'
    var gsKw = ''
    var gsLoaded = false
    var gsLoading = false
    var gsObserver = null
    var GS_BASE = './media/gs/'

    function gsSkeleton() {
        var box = $('gsGrid')
        if (!box) return
        var cols = window.innerWidth >= 720 ? 2 : 2
        var html = ''
        for (var c = 0; c < cols; c++) {
            html += '<div class="gs-col">'
            for (var i = 0; i < 4; i++) html += '<div class="gs-skel"></div>'
            html += '</div>'
        }
        box.innerHTML = html
    }

    function loadGallery() {
        if (!$('gsGrid') || gsLoaded || gsLoading) return
        gsLoading = true
        gsSkeleton()
        fetch(GS_BASE + 'clips.json', { cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && Array.isArray(d.clips)) ? d.clips : []
                gsList = list.filter(function (c) {
                    return c && c.id && (c.video || c.image)
                })
            })
            .catch(function () { gsList = [] })
            .then(function () {
                gsLoading = false
                gsLoaded = true
                renderGallery()
            })
    }

    function gsView() {
        var list = gsList.slice()
        if (gsKw) {
            var k = gsKw.toLowerCase()
            list = list.filter(function (c) { return decodeEntities(c.title).toLowerCase().indexOf(k) >= 0 })
        }
        if (gsFilter === 'trending') {
            list.sort(function (a, b) { return (Number(b.duration) || 0) - (Number(a.duration) || 0) })
        } else if (gsFilter === 'new') {
            list.sort(function (a, b) { return String(b.id).localeCompare(String(a.id)) })
        } else if (gsFilter === 'vip') {
            list = list.slice(0, Math.ceil(list.length / 4))
        } else if (gsFilter === 'free') {
            list = list.slice(Math.ceil(list.length / 4))
        }
        return list
    }

    // 只让视口内的卡片播放，滚出即暂停（几十个视频同时解码会卡）
    // 视频真正出画才淡入；加载失败则一直显示封面，不会留黑框
    function initAutoplay() {
        var vids = document.querySelectorAll('#gsGrid video')
        if (!vids.length) return
        vids.forEach(function (v) {
            if (v.getAttribute('data-gs-bound')) return
            v.setAttribute('data-gs-bound', '1')
            v.addEventListener('playing', function () { v.classList.add('is-on') })
            v.addEventListener('error', function () { v.classList.remove('is-on') })
        })
        if (!('IntersectionObserver' in window)) {
            vids.forEach(function (v) {
                var s = v.getAttribute('data-src')
                if (s && !v.getAttribute('src')) v.src = s
                v.play && v.play().catch(function () {})
            })
            return
        }
        if (gsObserver) gsObserver.disconnect()
        gsObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                var v = en.target
                if (en.isIntersecting) {
                    if (!v.getAttribute('src')) {
                        var s = v.getAttribute('data-src')
                        if (s) v.src = s
                    }
                    v.preload = 'auto'
                    var p = v.play()
                    if (p && p.catch) p.catch(function () {})
                } else {
                    v.pause()
                }
            })
        }, { root: null, rootMargin: '200px 0px', threshold: 0.1 })
        vids.forEach(function (v) { gsObserver.observe(v) })
    }

    // 按卡片原始宽高比分配列，尽量让两列高度接近（源站也是这个思路）
    function distribute(list) {
        var cols = 2
        var buckets = []
        for (var i = 0; i < cols; i++) buckets.push([])
        var heights = [0, 0]
        list.forEach(function (c) {
            var ar = (Number(c.w) && Number(c.h)) ? Number(c.w) / Number(c.h) : 0.5625
            var target = heights[0] <= heights[1] ? 0 : 1
            buckets[target].push({ clip: c, ar: ar })
            // 列宽相同，卡片高度与 1/ar 成正比
            heights[target] += 1 / ar
        })
        return buckets
    }

    function cardHtml(c, ar) {
        var title = esc(decodeEntities(c.title))
        var poster = esc(GS_BASE + c.poster)
        var tags = (c.tags || []).slice(0, 1)
        var tagHtml = tags.length
            ? '<div class="gs-card__tags"><span class="gs-card__tag">' + esc(tags[0]) + '</span></div>'
            : ''
        var corners = (c.corners || []).map(function (f) {
            return '<span class="gs-card__mark" style="background-image:url(&quot;' +
                esc(GS_BASE + f) + '&quot;)"></span>'
        }).join('')

        var media
        if (c.video) {
            media = '<img class="gs-card__media" src="' + poster + '" alt="' + title + '" loading="lazy" decoding="async">' +
                '<video class="gs-card__video" data-src="' + esc(GS_BASE + c.video) + '" poster="' + poster +
                '" muted loop playsinline webkit-playsinline preload="none"></video>'
        } else {
            media = '<img class="gs-card__media" src="' + esc(GS_BASE + (c.image || c.poster)) +
                '" alt="' + title + '" loading="lazy" decoding="async">'
        }

        return '<a class="gs-card" href="javascript:void(0)" data-gs-card="' + esc(c.id) + '"' +
            ' style="--ar:' + ar.toFixed(4) + '">' +
            '<div class="gs-card__frame">' + media + '</div>' +
            (corners ? '<div class="gs-card__creator">' + corners + '</div>' : '') +
            '<span class="gs-card__details">' +
            '<span class="gs-card__title">' + title + '</span>' +
            '<span class="gs-card__creator-name">@' + esc(c.creator || 'genspicy') + '</span>' +
            tagHtml +
            '</span></a>'
    }

    function renderGallery() {
        var box = $('gsGrid')
        if (!box) return
        var list = gsView()
        if (!list.length) {
            box.innerHTML = '<div class="gs-empty">Nothing here yet.</div>'
            return
        }
        var buckets = distribute(list)
        box.innerHTML = buckets.map(function (bucket) {
            var inner = bucket.map(function (it) { return cardHtml(it.clip, it.ar) }).join('')
            return '<div class="gs-col">' + inner + '</div>'
        }).join('')
        initAutoplay()
    }

    /* ---------- 卡片弹窗 = 参考站的生成器面板（真接 /api/customVideo 生成） ---------- */
    var API = '/api/customVideo'
    var gen = {
        mode: 'video',          // video | outfit
        file: null,
        presets: [],
        imagePresets: [],
        sel: null,
        videoPrice: 0,
        imagePrice: 0,
        balance: 0,
        remain: 0,
        limit: 0,
        refill: 0,
        unlimited: false,
        submitting: false,
        pollTimer: null,
        current: null
    }

    function authJson(url, opts) {
        opts = opts || {}
        opts.headers = Object.assign({}, token ? { Authorization: 'Bearer ' + token } : {}, opts.headers || {})
        opts.cache = 'no-store'
        return fetch(url, opts).then(function (r) { return r.json() })
    }

    function toast(msg) {
        var el = $('gsToast')
        if (!el) {
            el = document.createElement('div')
            el.id = 'gsToast'
            el.style.cssText = 'position:fixed;left:50%;top:80px;transform:translateX(-50%);z-index:1200;padding:10px 18px;' +
                'border-radius:12px;background:rgba(20,20,22,.96);border:1px solid rgba(255,255,255,.14);color:#fff;' +
                'font-size:13px;font-weight:600;box-shadow:0 10px 30px rgba(0,0,0,.45);max-width:80vw'
            document.body.appendChild(el)
        }
        el.textContent = msg
        el.style.display = 'block'
        clearTimeout(el.__t)
        el.__t = setTimeout(function () { el.style.display = 'none' }, 2800)
    }

    function showNote(msg) {
        var n = $('gsGenNote')
        if (!n) return
        n.textContent = msg
        n.style.display = ''
    }

    function setState(txt) {
        var s = $('gsGenState')
        if (!s) return
        s.textContent = txt || ''
        s.style.display = txt ? '' : 'none'
    }

    function genPrice() { return gen.mode === 'outfit' ? gen.imagePrice : gen.videoPrice }

    function genPresets() { return gen.mode === 'outfit' ? gen.imagePresets : gen.presets }

    function renderCost() {
        var el = $('gsGenCost')
        if (!el) return
        var p = genPrice()
        el.textContent = (!gen.unlimited && gen.remain <= 0 && p > 0) ? '🪙 ' + p : 'Free'
    }

    function renderAddons() {
        var box = $('gsGenAddons')
        if (!box) return
        var list = genPresets()
        if (!list.length) {
            // 未登录或选项还没回来：给一行占位，避免面板出现空洞
            box.innerHTML = '<button type="button" class="gs-gen__addon" id="gsGenAddonHint">' +
                '<span>' + (token ? 'Loading styles…' : 'Sign in to pick a style') + '</span>' +
                '<span class="gs-gen__radio"></span></button>'
            var hint = $('gsGenAddonHint')
            if (hint && !token) {
                hint.addEventListener('click', function () {
                    location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
                })
            }
            return
        }
        box.innerHTML = list.map(function (p) {
            var on = gen.sel && gen.sel.key === p.key
            var lock = gen.mode === 'outfit' && !p.owned
            return '<button type="button" class="gs-gen__addon' + (on ? ' on' : '') + '" data-key="' + esc(p.key) + '">' +
                '<span>' + esc(p.label) + (lock ? ' · ' + esc(p.price || '') + ' in Shop' : '') + '</span>' +
                '<span class="gs-gen__radio"></span></button>'
        }).join('')
        box.querySelectorAll('.gs-gen__addon').forEach(function (b) {
            b.addEventListener('click', function () {
                var item = null
                for (var i = 0; i < list.length; i++) {
                    if (list[i].key === b.getAttribute('data-key')) { item = list[i]; break }
                }
                if (!item) return
                if (gen.mode === 'outfit' && !item.owned) {
                    toast('Unlock this outfit in the Shop first')
                    setTimeout(function () { location.href = './shop.html' }, 900)
                    return
                }
                gen.sel = item
                renderAddons()
            })
        })
    }

    function applyQuota(q) {
        if (!q) return
        if (q.unlimited || (typeof q.remaining === 'number' && q.remaining < 0)) {
            gen.unlimited = true
            gen.remain = -1
            renderCost()
            return
        }
        gen.unlimited = false
        if (typeof q.daily_limit === 'number') gen.limit = q.daily_limit
        if (q.refill_daily !== undefined) gen.refill = parseInt(q.refill_daily, 10) || 0
        if (typeof q.remaining === 'number') gen.remain = q.remaining
        renderCost()
    }

    function loadGenOptions() {
        if (!token) { renderAddons(); renderCost(); return }
        authJson(API + '/options').then(function (d) {
            if (!d || d.code !== '00000' || !d.data) return
            gen.presets = d.data.styles || d.data.presets || []
            gen.imagePresets = d.data.image_presets || []
            gen.videoPrice = Number(d.data.video_price) || 0
            gen.imagePrice = Number(d.data.image_price) || 0
            gen.balance = Number(d.data.balance) || 0
            applyQuota(d.data.quota)
            if (!gen.sel && genPresets().length) gen.sel = genPresets()[0]
            renderAddons()
            renderCost()
        }).catch(function () {})
    }

    function switchGenMode(m) {
        gen.mode = m
        gen.sel = null
        $('gsGenTabVideo').className = 'gs-gen__tab' + (m === 'video' ? ' on' : '')
        $('gsGenTabImage').className = 'gs-gen__tab' + (m === 'outfit' ? ' on' : '')
        $('gsGenSubmitText').textContent = m === 'outfit' ? 'Apply Outfit' : 'Generate'
        renderAddons()
        renderCost()
    }

    function openCardModal(id) {
        var c = null
        for (var i = 0; i < gsList.length; i++) {
            if (String(gsList[i].id) === String(id)) { c = gsList[i]; break }
        }
        if (!c || !$('gsCardModal')) return
        gen.current = c
        gen.file = null
        gen.sel = null
        gen.mode = 'video'

        // 弹窗打开时先把列表里的视频停掉，省解码
        document.querySelectorAll('#gsGrid video').forEach(function (x) { x.pause() })

        $('gsGenTitle').textContent = decodeEntities(c.title)
        var pills = [
            '<span class="gs-gen__owner">@' + esc(c.creator || 'genspicy') + '</span>',
            '<span class="gs-gen__dot">·</span>'
        ]
        var tags = []
        if (Number(c.duration) > 0) tags.push(Number(c.duration) + 's')
        ;(c.tags || []).slice(0, 3).forEach(function (t) { tags.push(t) })
        pills = pills.concat(tags.map(function (t) {
            return '<span class="gs-gen__pill">' + esc(t) + '</span>'
        }))
        $('gsGenPills').innerHTML = pills.join('')

        // 左框：参考图片（可被自己的图替换）
        var pv = $('gsGenPreview')
        pv.src = GS_BASE + (c.corners && c.corners.length ? c.corners[0] : c.poster)
        pv.style.display = 'block'
        $('gsGenUploadText').textContent = 'upload image'
        $('gsGenFile').value = ''

        // 右框：参考视频（静音循环自动播放）
        var outImg = $('gsGenOutImg')
        outImg.style.display = 'none'
        outImg.classList.remove('is-sharp')
        var v = $('gsGenVideo')
        v.style.display = 'block'
        v.muted = true
        v.loop = true
        v.src = c.video ? (GS_BASE + c.video) : ''
        if (!c.video) {
            v.pause()
            outImg.src = GS_BASE + (c.image || c.poster)
            outImg.style.display = 'block'
            v.style.display = 'none'
        }
        var play = v.play()
        if (play && play.catch) play.catch(function () {})
        var snd = $('gsGenSound')
        snd.style.display = 'inline-flex'
        snd.classList.add('is-off')
        $('gsGenNote').style.display = 'none'
        setState('')

        $('gsGenTabVideo').className = 'gs-gen__tab on'
        $('gsGenTabImage').className = 'gs-gen__tab'
        $('gsGenSubmitText').textContent = 'Generate'
        renderAddons()
        renderCost()
        openModal('gsCardModal')
        loadGenOptions()
    }

    function pickFile(f) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { toast('Only jpg / png / webp'); return }
        if (f.size > 10 * 1024 * 1024) { toast('Image must be under 10MB'); return }
        gen.file = f
        var img = $('gsGenPreview')
        img.src = URL.createObjectURL(f)
        img.style.display = 'block'
        $('gsGenUploadText').textContent = 'change image'
    }

    function submitGen(confirmed) {
        if (gen.submitting) return
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
            return
        }
        if (!gen.file) { toast('Please upload her picture first'); return }
        if (!gen.sel) { toast(gen.mode === 'outfit' ? 'Please pick an outfit' : 'Please pick a style'); return }
        var p = genPrice()
        if (confirmed !== true && !gen.unlimited && gen.remain <= 0 && p > 0) {
            if (gen.balance < p) {
                showNote('Free generations are used up. Each extra one costs ' + p +
                    ' tokens, but your balance is ' + gen.balance + ' tokens.')
                return
            }
            if (!window.confirm('Free generations used up. This one costs ' + p + ' tokens. Continue?')) return
            doSubmit(true)
            return
        }
        doSubmit(confirmed === true)
    }

    function doSubmit(confirmed) {
        gen.submitting = true
        var btn = $('gsGenSubmit'), txt = $('gsGenSubmitText')
        var idle = gen.mode === 'outfit' ? 'Apply Outfit' : 'Generate'
        btn.disabled = true
        txt.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', gen.file)
        fd.append('type', gen.mode === 'outfit' ? 'image' : 'video')
        fd.append('agreed_policy', '1')
        if (confirmed === true) fd.append('confirm_paid', '1')
        if (gen.mode !== 'outfit') fd.append('style', gen.sel.key)
        if (gen.mode === 'outfit' && gen.sel.item_id) fd.append('item_id', String(gen.sel.item_id))

        authJson(API + '/submit', { method: 'POST', body: fd }).then(function (d) {
            gen.submitting = false
            btn.disabled = false
            txt.textContent = idle
            if (!d || d.code !== '00000') {
                if (d && d.code === 'C0102') {
                    if (genPrice() > 0 && window.confirm('This one costs ' + genPrice() + ' tokens. Continue?')) doSubmit(true)
                    return
                }
                if (d && d.code === 'C0100') { showNote(d.msg || 'Not enough tokens.'); return }
                toast((d && d.msg) || 'Submit failed')
                return
            }
            if (d.data) {
                if (typeof d.data.balance === 'number') gen.balance = d.data.balance
                if (typeof d.data.remaining === 'number') applyQuota({ remaining: d.data.remaining, daily_limit: gen.limit, refill_daily: gen.refill })
                else if (typeof d.data.remain === 'number') applyQuota({ remaining: d.data.remain, daily_limit: gen.limit, refill_daily: gen.refill })
            }
            if (window.refreshTokenBalance) window.refreshTokenBalance()
            setState('Queued…')
            watchResult()
        }).catch(function () {
            gen.submitting = false
            btn.disabled = false
            txt.textContent = idle
            toast('Network error')
        })
    }

    function watchResult() {
        if (gen.pollTimer) clearInterval(gen.pollTimer)
        var tries = 0
        gen.pollTimer = setInterval(function () {
            tries++
            if (tries > 80) { clearInterval(gen.pollTimer); gen.pollTimer = null; return }
            authJson(API + '/myList').then(function (d) {
                var list = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
                if (!list.length) return
                var t = list[0]
                var meta = STATUS_META[t.status] || {}
                if (t.status === 'completed' && t.video_url) {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    showResult(t)
                } else if (t.status === 'failed') {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    setState('Failed — please try again')
                } else {
                    setState(meta.label || 'Generating…')
                }
            }).catch(function () {})
        }, 6000)
    }

    function showResult(t) {
        setState('')
        var img = $('gsGenOutImg'), v = $('gsGenVideo')
        if (t.kind === 'image') {
            img.src = t.video_url
            img.classList.add('is-sharp')
            img.style.display = 'block'
            v.style.display = 'none'
            $('gsGenSound').style.display = 'none'
        } else {
            v.src = t.video_url
            v.style.display = 'block'
            img.style.display = 'none'
            $('gsGenSound').style.display = 'inline-flex'
        }
    }

    function showQuotaInfo() {
        var n = $('gsGenNote')
        if (n.style.display !== 'none') { n.style.display = 'none'; return }
        if (!token) { showNote('Sign in to generate.'); return }
        var parts = []
        if (gen.unlimited) parts.push('Unlimited free generations')
        else {
            parts.push('Free left: ' + Math.max(0, gen.remain) + (gen.limit ? ' / ' + gen.limit : ''))
            if (gen.refill > 0) parts.push('refills +' + gen.refill + '/day')
        }
        if (gen.videoPrice) parts.push('extra video ' + gen.videoPrice + ' tokens')
        if (gen.imagePrice) parts.push('extra image ' + gen.imagePrice + ' tokens')
        parts.push('balance ' + gen.balance + ' tokens')
        showNote(parts.join(' · '))
    }

    function bindGen() {
        if (!$('gsGenSubmit')) return
        $('gsGenTabVideo').addEventListener('click', function () { switchGenMode('video') })
        $('gsGenTabImage').addEventListener('click', function () { switchGenMode('outfit') })
        $('gsGenUpload').addEventListener('click', function () { $('gsGenFile').click() })
        $('gsGenFile').addEventListener('change', function () {
            if (this.files && this.files[0]) pickFile(this.files[0])
        })
        $('gsGenSubmit').addEventListener('click', function () { submitGen(false) })
        $('gsGenInfo').addEventListener('click', showQuotaInfo)
        $('gsGenLike').addEventListener('click', function () { this.classList.toggle('on') })
        var snd = $('gsGenSound')
        if (snd) {
            snd.addEventListener('click', function () {
                var v = $('gsGenVideo')
                if (!v || !v.getAttribute('src')) return
                v.muted = !v.muted
                snd.classList.toggle('is-off', v.muted)
                if (!v.muted) {
                    var p = v.play()
                    if (p && p.catch) p.catch(function () {})
                }
            })
        }
    }

    function bindGallery() {
        var grid = $('gsGrid')
        if (grid && !grid.getAttribute('data-gs-bound')) {
            grid.setAttribute('data-gs-bound', '1')
            grid.addEventListener('click', function (e) {
                var a = e.target.closest ? e.target.closest('[data-gs-card]') : null
                if (!a) return
                e.preventDefault()
                openCardModal(a.getAttribute('data-gs-card'))
            })
        }
        var bar = document.querySelector('.gs-filter')
        if (bar) {
            bar.addEventListener('click', function (e) {
                var chip = e.target.closest ? e.target.closest('[data-gs-filter]') : null
                if (!chip) return
                gsFilter = chip.getAttribute('data-gs-filter')
                bar.querySelectorAll('[data-gs-filter]').forEach(function (c) { c.classList.remove('on') })
                chip.classList.add('on')
                renderGallery()
            })
        }
        var sBtn = $('gsSearchBtn'), sWrap = $('gsSearchWrap'), sInput = $('gsSearchInput')
        if (sBtn && sWrap && sInput) {
            sBtn.addEventListener('click', function () {
                var shown = sWrap.style.display !== 'none'
                sWrap.style.display = shown ? 'none' : ''
                if (shown) {
                    sInput.value = ''
                    gsKw = ''
                    renderGallery()
                } else {
                    sInput.focus()
                }
            })
            sInput.addEventListener('input', function () {
                gsKw = sInput.value.trim()
                renderGallery()
            })
        }
        loadGallery()
    }

    /* ---------- 我的作品（历史生成记录，只读） ---------- */
    var STATUS_META = {
        pending: { label: 'Queued', cls: 'wait' },
        accepted: { label: 'Queued', cls: 'wait' },
        processing: { label: 'Generating…', cls: 'run' },
        completed: { label: 'Ready ✨', cls: 'ok' },
        failed: { label: 'Failed', cls: 'err' },
        expired: { label: 'Expired', cls: 'wait' }
    }

    function workCard(t) {
        var m = STATUS_META[t.status] || { label: t.status, cls: 'wait' }
        var body
        if (t.status === 'completed' && t.video_url) {
            body = t.kind === 'image'
                ? '<a href="' + esc(t.video_url) + '" target="_blank"><img src="' + esc(t.video_url) + '" style="width:100%;display:block;background:#000" alt=""></a>'
                : '<video controls playsinline preload="metadata" src="' + esc(t.video_url) + '"></video>'
        } else if (t.status === 'failed') {
            body = '<div class="cv-wait-body">Generation failed — please retry later.</div>'
        } else {
            body = '<div class="cv-wait-body">⏳ Still generating…<br>usually 3-6 minutes</div>'
        }
        return '<div class="cv-work">' +
            '<div class="hd"><span class="n">' + esc(t.label || 'Custom video') + '</span>' +
            '<span class="cv-badge ' + m.cls + '">' + m.label + '</span></div>' +
            body +
            '<div class="ft"><span>' + esc((t.created_at || '').replace('T', ' ').slice(0, 16)) + '</span>' +
            (t.status === 'completed' && t.video_url ? '<a href="' + esc(t.video_url) + '" download>⬇ Download</a>' : '') +
            '</div></div>'
    }

    var lastSig = ''
    var lastList = null
    var pendingRender = false
    var pollTimer = null

    function anyVideoPlaying() {
        var vs = $('cvWorksGrid') ? $('cvWorksGrid').querySelectorAll('video') : []
        for (var i = 0; i < vs.length; i++) {
            if (!vs[i].paused && !vs[i].ended) return true
        }
        return false
    }

    function tryPendingRender() {
        if (pendingRender && !anyVideoPlaying() && lastList) {
            pendingRender = false
            renderWorks(lastList)
        }
    }

    function renderWorks(list) {
        lastList = list
        var sig = list.map(function (t) { return t.id + ':' + t.status + ':' + (t.video_url || '') }).join('|')
        if (sig === lastSig) return
        if (anyVideoPlaying()) { pendingRender = true; return }
        lastSig = sig
        var grid = $('cvWorksGrid'), empty = $('cvWorksEmpty')
        if (!grid || !empty) return
        empty.style.display = list.length ? 'none' : ''
        grid.innerHTML = list.map(workCard).join('')
        var vs = grid.querySelectorAll('video')
        for (var i = 0; i < vs.length; i++) {
            vs[i].addEventListener('pause', tryPendingRender)
            vs[i].addEventListener('ended', tryPendingRender)
        }
    }

    function loadWorks() {
        if (!token) {
            renderWorks([])
            return
        }
        fetch(WORKS_API, { headers: { Authorization: 'Bearer ' + token }, cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
                renderWorks(list)
                var active = list.some(function (t) { return ['pending', 'accepted', 'processing'].indexOf(t.status) >= 0 })
                if (active && !pollTimer) pollTimer = setInterval(loadWorks, 10000)
                if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null }
            })
            .catch(function () { renderWorks([]) })
    }

    function openWorks() {
        openModal('gsWorksModal')
        loadWorks()
    }

    function init() {
        if (!$('gsGrid')) return
        bindModalClose()
        bindGallery()
        bindGen()
        var works = $('cvTabWorks')
        if (works) works.addEventListener('click', openWorks)
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
