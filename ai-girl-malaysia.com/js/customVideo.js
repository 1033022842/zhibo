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

    /* ---------- 预设 ---------- */
    var PRESET_ICONS = { dance: '💃', wave: '💋', vlog: '☕' }
    var OUTFIT_ICONS = { qipao: '🥻', dress: '👗', jk: '🎀', maid: '🧹', office: '💼', wedding: '👰' }

    function renderPresets(presets) {
        var box = $('cvPresets')
        box.innerHTML = presets.map(function (p) {
            var sel = selPreset && selPreset.key === p.key
            return '<div class="cv-preset' + (sel ? ' on' : '') + '" data-key="' + esc(p.key) + '">' +
                '<div class="ic">' + ((mode === 'outfit' ? OUTFIT_ICONS : PRESET_ICONS)[p.key] || '✨') + '</div>' +
                '<div class="n">' + esc(p.label) + '</div>' +
                '<div class="d">' + esc(p.desc || '') + '</div></div>'
        }).join('')
        box.querySelectorAll('.cv-preset').forEach(function (card) {
            card.addEventListener('click', function () {
                selPreset = presets.filter(function (p) { return p.key === card.getAttribute('data-key') })[0]
                box.querySelectorAll('.cv-preset').forEach(function (c) { c.className = 'cv-preset' })
                card.className = 'cv-preset on'
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
            currentPresets = d.data.presets || []
            renderPresets(mode === 'outfit' ? imagePresets : currentPresets)
            $('cvRemain').textContent = d.data.quota ? (d.data.quota.daily_limit + '/day') : '-'
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

    function switchMode(m) {
        if (mode === m) return
        mode = m
        selPreset = null
        $('cvModeVideo').className = 'cv-tab' + (m === 'video' ? ' on' : '')
        $('cvModeOutfit').className = 'cv-tab' + (m === 'outfit' ? ' on' : '')
        var isEdit = m === 'outfit'
        $('cvPromptWrap').style.display = isEdit ? '' : 'none'
        $('cvPresetWrap').style.display = isEdit ? 'none' : ''
        $('cvSubmit').textContent = isEdit ? '✨ Edit Image' : '✨ Generate Video'
        if (!isEdit) renderPresets(currentPresets)
    }

    /* ---------- 提交 ---------- */
    function submit() {
        if (!selFile) { toast('Please upload her picture first', 'warn'); return }
        var editPrompt = ''
        if (mode === 'outfit') {
            editPrompt = ($('cvPrompt').value || '').trim()
            if (editPrompt.length < 3) { toast('Please describe the change (at least 3 characters)', 'warn'); return }
        } else if (!selPreset) {
            toast('Please pick a vibe', 'warn'); return
        }
        if (!$('cvPolicy').checked) { toast('Please confirm the upload policy', 'warn'); return }

        var btn = $('cvSubmit')
        btn.disabled = true
        btn.textContent = 'Submitting…'

        var fd = new FormData()
        fd.append('image', selFile)
        fd.append('type', mode)
        fd.append('agreed_policy', '1')
        if (mode === 'outfit') {
            fd.append('prompt', editPrompt)
        } else {
            fd.append('preset', selPreset.key)
        }

        authJson(API + '/submit', { method: 'POST', body: fd }).then(function (d) {
            btn.disabled = false
            btn.textContent = mode === 'outfit' ? '✨ Edit Image' : '✨ Generate Video'
            if (!d || d.code !== '00000') { toast((d && d.msg) || 'Submit failed', 'error'); return }
            if (d.data && typeof d.data.remain === 'number') $('cvRemain').textContent = d.data.remain + ' left'
            if (mode === 'outfit') $('cvPrompt').value = ''
            toast('Task submitted! Track it in My Works', 'success')
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

    function loadWorks(silent) {
        if (!token) return
        authJson(API + '/myList').then(function (d) {
            if (!d || d.code !== '00000' || !Array.isArray(d.data)) { if (!silent) toast((d && d.msg) || 'Load failed', 'error'); return }
            var grid = $('cvWorksGrid'), empty = $('cvWorksEmpty')
            empty.style.display = d.data.length ? 'none' : ''
            grid.innerHTML = d.data.map(workCard).join('')
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
        var pt = $('cvPrompt')
        if (pt) pt.addEventListener('input', function () { $('cvPromptCount').textContent = pt.value.length + '/500' })
        loadOptions()
        loadWorks(true)
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
