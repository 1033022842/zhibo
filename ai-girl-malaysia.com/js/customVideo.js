/* Image.html — AI Video Studio（上传图片 → H3 生成视频）candy 暗色风 */
(function () {
    var API = '/api/customVideo'
    var token = localStorage.getItem('live_access_token')

    var selFile = null
    var selPreset = null
    var mode = 'video'          // video | outfit
    var imagePresets = []
    var currentPresets = []

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function $(id) { return document.getElementById(id) }

    function toast(msg, type) {
        var el = $('cvToast')
        if (!el) {
            el = document.createElement('div')
            el.id = 'cvToast'
            el.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:10px;font-size:14px;font-weight:600;z-index:999;box-shadow:0 8px 30px rgba(0,0,0,.4);color:#fff;font-family:Poppins,sans-serif'
            document.body.appendChild(el)
        }
        el.style.background = type === 'error' ? '#ef4444' : type === 'warn' ? '#f59e0b' : '#10b981'
        el.textContent = msg
        el.style.display = 'block'
        setTimeout(function () { el.style.display = 'none' }, 3000)
    }

    function authJson(url, opts) {
        opts = opts || {}
        opts.headers = Object.assign({}, token ? { Authorization: 'Bearer ' + token } : {}, opts.headers || {})
        // 接口返回带时效签名地址，任何一层缓存都可能拿到过期链接（视频/图片裂开）
        opts.cache = 'no-store'
        return fetch(url, opts).then(function (r) { return r.json() })
    }

    /* ---------- Tab ---------- */
    function switchTab(create) {
        $('cvTabCreate').className = 'cv-tab' + (create ? ' on' : '')
        $('cvTabWorks').className = 'cv-tab' + (!create ? ' on' : '')
        $('cvCreatePanel').style.display = create ? '' : 'none'
        $('cvWorksPanel').style.display = create ? 'none' : ''
        if (!create) loadWorks()
    }

    /* ---------- 上传区 ---------- */
    function bindUpload() {
        var drop = $('cvDrop'), file = $('cvFile')
        drop.addEventListener('click', function () { file.click() })
        ;['dragenter', 'dragover'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.add('over') })
        })
        ;['dragleave', 'drop'].forEach(function (ev) {
            drop.addEventListener(ev, function (e) { e.preventDefault(); drop.classList.remove('over') })
        })
        drop.addEventListener('drop', function (e) {
            if (e.dataTransfer.files && e.dataTransfer.files[0]) pickFile(e.dataTransfer.files[0])
        })
        file.addEventListener('change', function () {
            if (file.files[0]) pickFile(file.files[0])
        })
        $('cvRemove').addEventListener('click', function () {
            selFile = null
            $('cvUploaded').style.display = 'none'
            drop.style.display = ''
            file.value = ''
        })
    }

    function pickFile(f) {
        if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { toast('Only jpg / png / webp', 'warn'); return }
        if (f.size > 10 * 1024 * 1024) { toast('Image must be under 10MB', 'warn'); return }
        selFile = f
        $('cvPreview').src = URL.createObjectURL(f)
        $('cvUploaded').style.display = ''
        $('cvDrop').style.display = 'none'
    }

    /* ---------- 风格卡片（key 对应后端 STYLES / worker 的 style 值） ---------- */
    var STYLE_ICONS = { fingering: '💋', mysticxxx: '✨' }
    var OUTFIT_ICONS = { qipao: '🥻', dress: '👗', jk: '🎀', maid: '🧹', office: '💼', wedding: '👰' }

    function renderPresets(presets) {
        var box = $('cvPresets')
        var isOutfit = mode === 'outfit'
        box.innerHTML = presets.map(function (p) {
            var sel = selPreset && selPreset.key === p.key
            var locked = isOutfit && !p.owned
            var thumb = p.cover_url
                ? '<img class="ic-img" src="' + esc(p.cover_url) + '" alt="" loading="lazy">'
                : '<div class="ic">' + ((isOutfit ? OUTFIT_ICONS : STYLE_ICONS)[p.key] || '✨') + '</div>'
            return '<div class="cv-preset' + (sel ? ' on' : '') + (locked ? ' lock' : '') + '" data-key="' + esc(p.key) + '">' +
                thumb +
                '<div class="n">' + esc(p.label) + '</div>' +
                '<div class="d">' + (locked ? ('<img src="./taocan_files/token-4f2c951e0a476a1cf59eadbc2108289323778ccb84fb232782ae923a8d1b8961.svg" alt="" style="width:12px;height:12px;display:inline-block;vertical-align:-2px;margin-right:3px">' + esc(p.price + ' · buy in Shop')) : esc(p.desc || '')) + '</div></div>'
        }).join('')
        box.querySelectorAll('.cv-preset').forEach(function (card) {
            card.addEventListener('click', function () {
                var item = presets.filter(function (p) { return p.key === card.getAttribute('data-key') })[0]
                if (isOutfit && item && !item.owned) {
                    toast('Buy this outfit in the Shop first', 'warn')
                    setTimeout(function () { location.href = './shop.html' }, 900)
                    return
                }
                selPreset = item
                // 只切 on 类，别把未购卡片的 lock 类一起抹掉
                box.querySelectorAll('.cv-preset').forEach(function (c) { c.classList.remove('on') })
                card.classList.add('on')
            })
        })
    }

    function loadOptions() {
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
            return
        }
        authJson(API + '/options').then(function (d) {
            if (!d || d.code !== '00000' || !d.data) { toast((d && d.msg) || 'Load failed', 'error'); return }
            imagePresets = d.data.image_presets || []
            currentPresets = d.data.styles || d.data.presets || []
            // 免费次数用尽后的代币单价 + 当前余额（后台「功能定价」可改）
            cvPrice = Number(d.data.video_price) || 0
            cvBalance = Number(d.data.balance) || 0
            renderPresets(mode === 'outfit' ? imagePresets : currentPresets)
            applyQuota(d.data.quota)
        }).catch(function () { toast('Network error', 'error') })

        fetch('/api/vip/status', { headers: { Authorization: 'Bearer ' + token } })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var vip = d && d.code === '00000' && d.data && d.data.is_vip
                $('cvVipGate').style.display = vip ? 'none' : ''
                $('cvCreateForm').style.display = vip ? '' : 'none'
            })
            .catch(function () { /* submit 兜底校验 */ })
    }

    // 剩余次数：quota 里 remaining<0 / unlimited 显示 ∞，否则显示「剩余 / 上限」并随每次生成递减
    // 额度规则：每天补充 refill_daily 次，余额最多累积到 daily_limit
    var cvDailyLimit = 0
    var cvRefillDaily = 0
    var cvPrice = 0      // 免费次数用尽后每次生成扣的代币
    var cvBalance = 0    // 当前代币余额
    function applyQuota(q) {
        var el = $('cvRemain')
        if (!el) return
        var tip = $('cvRefillTip')
        var note = $('cvPriceNote')
        if (!q) { el.textContent = '-'; return }
        if (q.unlimited || typeof q.remaining === 'number' && q.remaining < 0) {
            el.textContent = '∞'
            if (tip) tip.style.display = 'none'
            if (note) note.style.display = 'none'
            return
        }
        if (typeof q.daily_limit === 'number') cvDailyLimit = q.daily_limit
        if (q.refill_daily !== undefined) cvRefillDaily = parseInt(q.refill_daily, 10) || 0
        var left = typeof q.remaining === 'number' ? q.remaining : (qrLeft())
        el.textContent = left + (cvDailyLimit > 0 ? ' / ' + cvDailyLimit : ' left')
        // 「每天 +N，上限 M」说明，避免用户以为是固定每天重置满额
        if (tip) {
            var n = parseInt(q.refill_daily, 10)
            var parts = []
            if (n > 0 && cvDailyLimit > 0) parts.push('+' + n + '/day, max ' + cvDailyLimit)
            if (left <= 0 && cvPrice > 0) parts.push('then ' + cvPrice + ' tokens each')
            if (parts.length) {
                tip.textContent = ' · ' + parts.join(' · ')
                tip.style.display = ''
            } else {
                tip.style.display = 'none'
            }
        }
        // 免费次数用完后可用代币继续生成：常驻说明 + 当前余额
        if (note) {
            if (cvPrice > 0) {
                note.textContent = 'Free generations used up? Keep creating for ' + cvPrice +
                    ' tokens per generation · Balance: ' + cvBalance + ' tokens'
                note.style.display = ''
            } else {
                note.style.display = 'none'
            }
        }
    }
    function qrLeft() {
        // 无 remaining 字段的老响应：从「剩余 / 上限」文本里兜底
        var el = $('cvRemain')
        var m = el && /\d+/.exec(el.textContent || '')
        return m ? parseInt(m[0], 10) : 0
    }

    function switchMode(m) {
        if (mode === m) return
        mode = m
        selPreset = null
        $('cvModeVideo').className = 'cv-mode' + (m === 'video' ? ' on' : '')
        $('cvModeOutfit').className = 'cv-mode' + (m === 'outfit' ? ' on' : '')
        var isEdit = m === 'outfit'
        $('cvPromptWrap').style.display = isEdit ? '' : 'none'
        // 两种模式都要卡片：视频=风格，图片=商店已购服装
        $('cvPresetWrap').style.display = ''
        $('cvSubmit').textContent = isEdit ? '✨ Edit Image' : '✨ Generate Video'
        renderPresets(isEdit ? imagePresets : currentPresets)
    }

    /* ---------- 代币不足：引导充值 ---------- */
    function showTopUp(msg) {
        var text = msg || 'Not enough tokens. Please top up to keep generating.'
        if (window.SugusDialog && window.SugusDialog.confirm) {
            window.SugusDialog.confirm({
                title: 'Not enough tokens',
                message: text,
                confirmText: 'Top up',
                cancelText: 'Later'
            }).then(function (ok) {
                if (ok) location.href = './purchasePoints.html'
            })
            return
        }
        toast(text, 'error')
    }

    /* ---------- 提交 ---------- */
    function submit() {
        if (!selFile) { toast('Please upload her picture first', 'warn'); return }
        if (!selPreset) {
            toast(mode === 'outfit' ? 'Please pick an outfit to dress her in' : 'Please pick a style', 'warn')
            return
        }
        if (!$('cvPolicy').checked) { toast('Please confirm the upload policy', 'warn'); return }

        var btn = $('cvSubmit')
        btn.disabled = true
        btn.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', selFile)
        fd.append('type', mode === 'outfit' ? 'image' : 'video')
        fd.append('agreed_policy', '1')
        if (mode !== 'outfit') fd.append('style', selPreset.key)
        // 换装：把所选服饰的商品 id 带上（后端校验归属并用商品标题+描述拼「换上这套衣服」指令）
        if (mode === 'outfit' && selPreset && selPreset.item_id) fd.append('item_id', String(selPreset.item_id))

        authJson(API + '/submit', { method: 'POST', body: fd }).then(function (d) {
            btn.disabled = false
            btn.textContent = mode === 'outfit' ? '✨ Edit Image' : '✨ Generate Video'
            if (!d || d.code !== '00000') {
                // 免费次数用尽且代币不足：引导去充值
                if (d && d.code === 'C0100') { showTopUp(d.msg); return }
                toast((d && d.msg) || 'Submit failed', 'error')
                return
            }
            var paid = d.data ? Number(d.data.paid_tokens) || 0 : 0
            if (d.data && typeof d.data.balance === 'number' && d.data.balance >= 0) {
                cvBalance = d.data.balance
            } else if (paid > 0) {
                cvBalance = Math.max(0, cvBalance - paid)
            }
            // 提交成功即刻刷新剩余次数（remain=-1 不限量）；保留 refill 提示
            if (d.data && typeof d.data.remain === 'number') applyQuota({ remaining: d.data.remain, daily_limit: cvDailyLimit, refill_daily: cvRefillDaily })
            if (paid > 0) {
                if (window.refreshTokenBalance) window.refreshTokenBalance()
                toast('Paid ' + paid + ' tokens — task submitted!', 'success')
            } else {
                toast('Task submitted! Track it in My Works', 'success')
            }
            switchTab(false)
        }).catch(function () {
            btn.disabled = false
            btn.textContent = mode === 'outfit' ? '✨ Edit Image' : '✨ Generate Video'
            toast('Network error', 'error')
        })
    }

    /* ---------- My Works ---------- */
    var STATUS_META = {
        pending:    { label: 'Queued',      cls: 'wait' },
        accepted:   { label: 'Queued',      cls: 'wait' },
        processing: { label: 'Generating…', cls: 'run' },
        completed:  { label: 'Ready ✨',     cls: 'ok' },
        failed:     { label: 'Failed',      cls: 'err' },
        expired:    { label: 'Expired',     cls: 'wait' },
    }

    function workCard(t) {
        var m = STATUS_META[t.status] || { label: t.status, cls: 'wait' }
        var body
        if (t.status === 'completed' && t.video_url) {
            body = t.kind === 'image'
                ? '<a href="' + esc(t.video_url) + '" target="_blank"><img src="' + esc(t.video_url) + '" style="width:100%;display:block;background:#000" alt="outfit"></a>'
                : '<video controls playsinline preload="metadata" src="' + esc(t.video_url) + '"></video>'
        } else if (t.status === 'failed') {
            body = '<div class="cv-wait-body">Generation failed — please retry later.</div>'
        } else {
            body = '<div class="cv-wait-body">⏳ Your video is being generated…<br>usually 3-6 minutes</div>'
        }
        return '<div class="cv-work">' +
            '<div class="hd"><span class="n">' + esc(t.label || 'Custom video') + '</span>' +
            '<span class="cv-badge ' + m.cls + '">' + m.label + '</span></div>' +
            body +
            '<div class="ft"><span>' + esc((t.created_at || '').replace('T', ' ').slice(0, 16)) + '</span>' +
            (t.status === 'completed' && t.video_url ? '<a href="' + esc(t.video_url) + '" download>⬇ Download</a>' : '') +
            '</div></div>'
    }

    var pollTimer = null
    var lastSig = ''
    var lastList = null
    var pendingRender = false

    // 有视频正在播放时不重绘列表（避免 innerHTML 重建打断播放）
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
        // 数据没变化（id:状态:地址 签名一致）直接跳过，不重建 DOM
        var sig = list.map(function (t) { return t.id + ':' + t.status + ':' + (t.video_url || '') }).join('|')
        if (sig === lastSig) return
        // 数据变了但有视频在播：挂起，等 pause/ended 再补刷
        if (anyVideoPlaying()) { pendingRender = true; return }
        lastSig = sig
        var grid = $('cvWorksGrid'), empty = $('cvWorksEmpty')
        empty.style.display = list.length ? 'none' : ''
        grid.innerHTML = list.map(workCard).join('')
        var vs = grid.querySelectorAll('video')
        for (var i = 0; i < vs.length; i++) {
            vs[i].addEventListener('pause', tryPendingRender)
            vs[i].addEventListener('ended', tryPendingRender)
        }
    }

    function loadWorks(silent) {
        if (!token) return
        authJson(API + '/myList').then(function (d) {
            if (!d || d.code !== '00000' || !Array.isArray(d.data)) { if (!silent) toast((d && d.msg) || 'Load failed', 'error'); return }
            renderWorks(d.data)
            var active = d.data.some(function (t) { return ['pending', 'accepted', 'processing'].indexOf(t.status) >= 0 })
            if (active && !pollTimer) pollTimer = setInterval(function () { loadWorks(true) }, 10000)
            if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null }
        }).catch(function () { if (!silent) toast('Network error', 'error') })
    }

    function init() {
        if (!$('cvTabCreate')) return
        $('cvTabCreate').addEventListener('click', function () { switchTab(true) })
        $('cvTabWorks').addEventListener('click', function () { switchTab(false) })
        $('cvModeVideo').addEventListener('click', function () { switchMode('video') })
        $('cvModeOutfit').addEventListener('click', function () { switchMode('outfit') })
        $('cvSubmit').addEventListener('click', submit)
        bindUpload()
        loadOptions()
        loadWorks(true)
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
