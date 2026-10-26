/* Image.html — 参考站风格首屏：角色瀑布流 + 卡片详情弹窗 + 我的作品
   上传/生成视频弹窗已下线，这里只负责浏览、看详情、进聊天 */
(function () {
    var token = localStorage.getItem('live_access_token')
    var WORKS_API = '/api/aiShort/myList'

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
    var gsWaitLayout = null
    var GS_BASE = './media/gs/'

    // 列数按「网格实际可用宽度」决定（页面有左侧栏，不能直接用 window.innerWidth）。
    // 阈值对齐参考站观感：约 1150→5 / 880→4 / 640→3 / 更窄→2，单卡宽约 240px。
    function gsWidth() {
        var box = $('gsGrid')
        var w = box ? box.clientWidth : 0
        if (!w) w = document.documentElement.clientWidth || window.innerWidth || 0
        return w || 0
    }
    function gsCols() {
        var w = gsWidth()
        if (w >= 1150) return 5
        if (w >= 880) return 4
        if (w >= 640) return 3
        return 2
    }

    function gsSkeleton() {
        var box = $('gsGrid')
        if (!box) return
        var cols = gsCols()
        var html = ''
        for (var c = 0; c < cols; c++) {
            html += '<div class="gs-col">'
            for (var i = 0; i < 3; i++) html += '<div class="gs-skel"></div>'
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
                // 内置浏览器 / 分屏场景下，首次渲染时视口宽度可能还没稳定，
                // 且宽度变化不一定触发 resize。这里在最初几秒内自适应校正列数。
                var tries = 0
                var tid = setInterval(function () {
                    tries++
                    if (gsCols() !== gsRenderedCols) renderGallery()
                    if (tries >= 20) clearInterval(tid)
                }, 300)
                setTimeout(function () { clearInterval(tid) }, 8000)
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

    // 按卡片原始宽高比分配列，尽量让各列高度接近（源站也是这个思路）
    var gsRenderedCols = 0
    function distribute(list) {
        var cols = gsCols()
        gsRenderedCols = cols
        var buckets = []
        var heights = []
        for (var i = 0; i < cols; i++) { buckets.push([]); heights.push(0) }
        list.forEach(function (c) {
            var ar = (Number(c.w) && Number(c.h)) ? Number(c.w) / Number(c.h) : 0.5625
            var target = 0
            for (var k = 1; k < cols; k++) if (heights[k] < heights[target]) target = k
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
        // 后台/未布局时 clientWidth 为 0，此时算列数会退回 2 列（卡片显得很大）。
        // 先渲染骨架，等真正拿到宽度再重排。
        if (!gsWidth() && list.length) {
            gsSkeleton()
            if (!gsWaitLayout) {
                var tries = 0
                gsWaitLayout = setInterval(function () {
                    tries++
                    if (gsWidth() || tries > 40) {
                        clearInterval(gsWaitLayout); gsWaitLayout = null
                        if (gsWidth()) renderGallery()
                    }
                }, 250)
            }
            return
        }
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

    /* ---------- 卡片弹窗 = 预设生成面板（AiShort 通道） ----------
       每条卡片就是一个预设（code = 卡片 id，如 gs-17），后端按 code 取
       专属提示词 + 4 层 LoRA 链，不是随机风格池。 */
    var API = '/api/aiShort'
    var gsPresetMap = {}          // code -> /presets 返回的元信息
    var gsPresetsLoaded = false

    var gen = {
        file: null,
        preset: null,             // { code, title, kind, duration_sec, category }
        duration: 0,              // 用户选的时长；0 = 用预设默认
        remain: null,             // null = 未知（提交后才拿到）
        balance: 0,
        submitting: false,
        pollTimer: null,
        current: null,
        taskId: 0
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
        el.__t = setTimeout(function () { el.style.display = 'none' }, 3200)
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

    // 预设列表是公开接口；拿到后按 code 建索引，用于时长/分类等元信息
    function loadPresets() {
        if (gsPresetsLoaded) return Promise.resolve(gsPresetMap)
        return fetch(API + '/presets', { cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && d.data && d.data.list) ? d.data.list : []
                list.forEach(function (p) { if (p && p.code) gsPresetMap[p.code] = p })
                gsPresetsLoaded = true
                return gsPresetMap
            })
            .catch(function () { return gsPresetMap })
    }

    function presetOf(card) {
        var p = gsPresetMap[card.id] || {}
        return {
            code: card.id,
            title: p.title || decodeEntities(card.title),
            kind: p.kind || card.kind || 'video',
            duration_sec: Number(p.duration_sec) || Number(card.duration) || 8,
            category: p.category || (card.tags && card.tags[0]) || 'other'
        }
    }

    // 标题与标签：预设元信息可能晚到，渲染单独抽出来便于二次刷新
    function renderHead(c) {
        if (!c || !gen.preset) return
        $('gsGenTitle').textContent = gen.preset.title
        var pills = [
            '<span class="gs-gen__owner">@' + esc(c.creator || 'genspicy') + '</span>',
            '<span class="gs-gen__dot">·</span>'
        ]
        var tags = [gen.preset.category]
        tags.push(gen.preset.kind === 'image' ? 'image' : gen.preset.duration_sec + 's')
        $('gsGenPills').innerHTML = pills.concat(tags.map(function (t) {
            return '<span class="gs-gen__pill">' + esc(t) + '</span>'
        })).join('')
    }

    // 卡片本身就是预设，不需要再选风格；这里只让用户微调时长
    function renderAddons() {
        var box = $('gsGenAddons')
        if (!box) return
        var p = gen.preset
        if (!p) { box.innerHTML = ''; return }
        if (p.kind === 'image') {
            box.innerHTML = '<div class="gs-gen__hint" style="margin:0">Still-image preset — no duration to pick.</div>'
            return
        }
        var def = p.duration_sec
        var opts = [3, 5, 8, 10]
        if (opts.indexOf(def) < 0) { opts.push(def); opts.sort(function (a, b) { return a - b }) }
        var cur = gen.duration || def
        box.innerHTML = opts.map(function (v) {
            var on = v === cur ? ' on' : ''
            var lab = v + 's' + (v === def ? ' · default' : '') + (v === 3 ? ' · fastest' : '')
            return '<button type="button" class="gs-gen__addon' + on + '" data-dur="' + v + '">' +
                '<span>' + esc(lab) + '</span><span class="gs-gen__radio"></span></button>'
        }).join('')
        box.querySelectorAll('[data-dur]').forEach(function (b) {
            b.addEventListener('click', function () {
                gen.duration = Number(b.getAttribute('data-dur'))
                renderAddons()
                renderCost()
            })
        })
    }

    function renderCost() {
        var el = $('gsGenCost')
        if (!el) return
        if (gen.remain === null || gen.remain < 0) { el.textContent = 'Free'; return }
        el.textContent = gen.remain > 0 ? 'Free' : 'Paid'
    }

    // 右侧画面回到「预设的参考片」
    function showReference() {
        var c = gen.current
        var outImg = $('gsGenOutImg'), v = $('gsGenVideo')
        if (!outImg || !v) return
        outImg.style.display = 'none'
        outImg.classList.remove('is-sharp')
        var src = c && c.video ? (GS_BASE + c.video) : ''
        if (src) {
            v.style.display = 'block'
            v.muted = true
            v.loop = true
            v.src = src
            var p = v.play()
            if (p && p.catch) p.catch(function () {})
            var snd = $('gsGenSound')
            if (snd) { snd.style.display = 'inline-flex'; snd.classList.add('is-off') }
        } else {
            v.pause()
            v.style.display = 'none'
            outImg.src = GS_BASE + ((c && (c.image || c.poster)) || '')
            outImg.style.display = 'block'
            var s2 = $('gsGenSound')
            if (s2) s2.style.display = 'none'
        }
        setState('Preview')
    }

    function openCardModal(id) {
        var c = null
        for (var i = 0; i < gsList.length; i++) {
            if (String(gsList[i].id) === String(id)) { c = gsList[i]; break }
        }
        if (!c || !$('gsCardModal')) return
        if (gen.pollTimer) { clearInterval(gen.pollTimer); gen.pollTimer = null }
        gen.current = c
        gen.file = null
        gen.duration = 0
        gen.taskId = 0
        gen.submitting = false
        gen.preset = presetOf(c)

        // 先把列表里的视频停掉，省解码
        document.querySelectorAll('#gsGrid video').forEach(function (x) { x.pause() })

        renderHead(c)

        // 左框：待上传的人像（默认显示预设的角色图）
        var pv = $('gsGenPreview')
        pv.src = GS_BASE + (c.corners && c.corners.length ? c.corners[0] : c.poster)
        pv.style.display = 'block'
        $('gsGenUploadText').textContent = 'upload image'
        $('gsGenFile').value = ''

        showReference()
        $('gsGenNote').style.display = 'none'
        $('gsGenNote').textContent = ''
        renderAddons()
        renderCost()
        $('gsGenSubmitText').textContent = 'Generate'
        openModal('gsCardModal')
        loadPresets().then(function () {
            // 预设元信息到齐后刷新标题、标签与时长选项（首次打开时可能还没加载完）
            if (gen.current !== c) return
            gen.preset = presetOf(c)
            renderHead(c)
            renderAddons()
        })
    }

    function pickFile(f) {
        if (!f) return
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { toast('JPG / PNG / WebP only'); return }
        if (f.size > 10 * 1024 * 1024) { toast('Image must be under 10MB'); return }
        gen.file = f
        var img = $('gsGenPreview')
        img.src = URL.createObjectURL(f)
        img.style.display = 'block'
        $('gsGenUploadText').textContent = 'change image'
    }

    function submitGen() {
        if (gen.submitting) return
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
            return
        }
        if (!gen.file) { toast('Please upload her picture first'); return }
        if (!gen.preset) return
        doSubmit(false)
    }

    function doSubmit(confirmed) {
        gen.submitting = true
        var btn = $('gsGenSubmit'), txt = $('gsGenSubmitText')
        btn.disabled = true
        txt.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', gen.file)
        fd.append('preset_code', gen.preset.code)
        fd.append('agreed_policy', '1')
        if (gen.duration && gen.duration !== gen.preset.duration_sec) {
            fd.append('duration_sec', String(gen.duration))
        }
        if (confirmed === true) fd.append('confirm_paid', '1')

        authJson(API + '/submit', { method: 'POST', body: fd }).then(function (d) {
            gen.submitting = false
            btn.disabled = false
            txt.textContent = 'Generate'
            if (!d || d.code !== '00000') {
                if (d && d.code === 'C0102') {
                    // 免费额度用尽：服务端已给出价格文案，确认后带 confirm_paid 重提
                    if (window.confirm(d.msg || 'This one costs tokens. Continue?')) doSubmit(true)
                    return
                }
                if (d && d.code === 'C0100') { showNote(d.msg || 'Not enough tokens.'); return }
                if (d && d.code === 'A0100') {
                    location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
                    return
                }
                toast((d && d.msg) || 'Submit failed')
                return
            }
            var data = d.data || {}
            if (typeof data.remain === 'number') gen.remain = data.remain
            if (typeof data.balance === 'number') gen.balance = data.balance
            gen.taskId = Number(data.task_id) || 0
            if (window.refreshTokenBalance) window.refreshTokenBalance()
            renderCost()
            setState('Queued…')
            watchResult(gen.taskId)
        }).catch(function () {
            gen.submitting = false
            btn.disabled = false
            txt.textContent = 'Generate'
            toast('Network error')
        })
    }

    // 按 task_id 轮询；5 秒一次、最长约 20 分钟（8 秒片实际约 10 分钟）
    function watchResult(taskId) {
        if (gen.pollTimer) clearInterval(gen.pollTimer)
        if (!taskId) { setState('Submitted — check My Works'); return }
        var tries = 0
        gen.pollTimer = setInterval(function () {
            tries++
            if (tries > 240) {
                clearInterval(gen.pollTimer)
                gen.pollTimer = null
                setState('Still generating — check My Works later')
                return
            }
            authJson(API + '/task?task_id=' + taskId).then(function (d) {
                if (!d || d.code !== '00000' || !d.data) return
                var t = d.data
                if (t.status === 'completed' && t.video_url) {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    showResult(t)
                } else if (t.status === 'failed' || t.status === 'expired') {
                    clearInterval(gen.pollTimer)
                    gen.pollTimer = null
                    setState(t.status === 'expired'
                        ? 'Expired — please retry'
                        : ('Failed — ' + (t.error || 'please try again')))
                } else {
                    var meta = STATUS_META[t.status] || {}
                    setState(meta.label || 'Generating…')
                }
            }).catch(function () {})
        }, 5000)
    }

    function showResult(t) {
        setState('')
        var img = $('gsGenOutImg'), v = $('gsGenVideo')
        if (t.kind === 'image') {
            img.src = t.video_url
            img.classList.add('is-sharp')
            img.style.display = 'block'
            v.pause()
            v.style.display = 'none'
            $('gsGenSound').style.display = 'none'
        } else {
            img.style.display = 'none'
            img.classList.remove('is-sharp')
            v.src = t.video_url
            v.style.display = 'block'
            v.muted = false
            v.loop = true
            var p = v.play()
            if (p && p.catch) p.catch(function () {})
            var snd = $('gsGenSound')
            if (snd) { snd.style.display = 'inline-flex'; snd.classList.remove('is-off') }
        }
    }

    function showQuotaInfo() {
        var n = $('gsGenNote')
        if (n.style.display !== 'none' && n.textContent) { n.style.display = 'none'; return }
        if (!token) { showNote('Sign in to generate.'); return }
        var parts = []
        if (gen.remain === null) parts.push('Free quota is checked on submit')
        else if (gen.remain < 0) parts.push('Unlimited free generations')
        else parts.push('Free left: ' + gen.remain)
        if (gen.balance) parts.push('balance ' + gen.balance + ' tokens')
        parts.push('8s clip takes ~10 min')
        showNote(parts.join(' · '))
    }

    function bindGen() {
        if (!$('gsGenSubmit')) return
        $('gsGenUpload').addEventListener('click', function () { $('gsGenFile').click() })
        $('gsGenFile').addEventListener('change', function () {
            if (this.files && this.files[0]) pickFile(this.files[0])
        })
        $('gsGenSubmit').addEventListener('click', submitGen)
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
        // 卡片即预设，隐藏旧的「Image to Video / Outfit」风格切换
        var tabs = document.querySelector('.gs-gen__tabs')
        if (tabs) tabs.style.display = 'none'
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
            // 列数随视口变化，重排一次（跟参考站一样按断点切换）
            // ResizeObserver 覆盖「容器尺寸变了但没触发 window.resize」的情况
            // （内置浏览器/分屏首帧宽度未定），window.resize 兜底老浏览器
            var lastCols = gsCols()
            var rt = null
            var schedule = function () {
                if (rt) clearTimeout(rt)
                rt = setTimeout(function () {
                    var now = gsCols()
                    if (now !== lastCols) {
                        lastCols = now
                        if (gsLoaded) renderGallery()
                    }
                }, 160)
            }
            if ('ResizeObserver' in window) {
                new ResizeObserver(schedule).observe(grid)
            }
            window.addEventListener('resize', schedule, { passive: true })
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
                ? '<a href="' + esc(t.video_url) + '" target="_blank"><img src="' + esc(t.cover_url || t.video_url) + '" style="width:100%;display:block;background:#000" alt=""></a>'
                : '<video controls playsinline preload="metadata" src="' + esc(t.video_url) + '" poster="' + esc(t.cover_url || '') + '"></video>'
        } else if (t.status === 'failed') {
            body = '<div class="cv-wait-body">Generation failed — please retry later.</div>'
        } else {
            body = '<div class="cv-wait-body">⏳ Still generating…<br>an 8s clip takes ~10 min</div>'
        }
        return '<div class="cv-work">' +
            '<div class="hd"><span class="n">' + esc(t.title || t.preset_code || 'Preset clip') + '</span>' +
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
