/* Image.html — VIP 定制角色视频（Create / My Works） */
(function () {
    var API = '/api/customVideo'
    var token = localStorage.getItem('live_access_token')

    var selPersona = null   // {id, name, cover, mine}
    var selAction = null    // {key, label}
    var remainEl = null

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function toast(msg, type) {
        var el = document.getElementById('cvToast')
        if (!el) {
            el = document.createElement('div')
            el.id = 'cvToast'
            el.style.cssText = 'position:fixed;top:80px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;z-index:999;box-shadow:0 8px 30px rgba(0,0,0,.4);color:#fff'
            document.body.appendChild(el)
        }
        el.style.background = type === 'error' ? '#ef4444' : type === 'warn' ? '#f59e0b' : '#10b981'
        el.textContent = msg
        el.style.display = 'block'
        setTimeout(function () { el.style.display = 'none' }, 2800)
    }

    function authJson(url, opts) {
        opts = opts || {}
        opts.headers = Object.assign({ 'Content-Type': 'application/json' }, token ? { Authorization: 'Bearer ' + token } : {})
        return fetch(url, opts).then(function (r) { return r.json() })
    }

    /* ---------- Tab 切换 ---------- */
    function switchTab(create) {
        document.getElementById('cvTabCreate').className = 'flex-1 py-2.5 rounded-full text-sm font-semibold cursor-pointer ' + (create ? 'text-white bg-[#E75275]' : 'text-[#A1A1A1]')
        document.getElementById('cvTabWorks').className = 'flex-1 py-2.5 rounded-full text-sm font-semibold cursor-pointer ' + (!create ? 'text-white bg-[#E75275]' : 'text-[#A1A1A1]')
        document.getElementById('cvCreatePanel').classList.toggle('hidden', !create)
        document.getElementById('cvWorksPanel').classList.toggle('hidden', create)
        if (!create) loadWorks()
    }

    /* ---------- Create：选项加载 ---------- */
    function loadOptions() {
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Image.html')
            return
        }
        authJson(API + '/options').then(function (d) {
            if (!d || d.code !== '00000' || !d.data) { toast((d && d.msg) || 'Load failed', 'error'); return }
            var data = d.data

            // VIP 门禁（接口没查 VIP 状态，由 submit 兜底；这里先用 vip 状态接口提示更友好）
            renderPersonas(data.platform || [], data.mine || [])
            renderActions(data.actions || [])
            checkVip()
        }).catch(function () { toast('Network error', 'error') })
    }

    function checkVip() {
        fetch('/api/vip/status', { headers: token ? { Authorization: 'Bearer ' + token } : {} })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var vip = (d && d.code === '00000' && d.data && d.data.is_vip)
                document.getElementById('cvVipGate').classList.toggle('hidden', !!vip)
                document.getElementById('cvCreateForm').classList.toggle('hidden', !vip)
            })
            .catch(function () {
                // 状态接口失败不拦截，submit 会再校验
                document.getElementById('cvCreateForm').classList.remove('hidden')
            })
    }

    function personaCard(p) {
        var sel = selPersona && selPersona.id === p.id
        return '<div class="cv-pcard relative rounded-xl overflow-hidden cursor-pointer border-2 ' + (sel ? 'border-[#E75275]' : 'border-transparent') + '" data-id="' + p.id + '" data-name="' + esc(p.name) + '" data-cover="' + esc(p.cover) + '" data-mine="' + (p.mine ? 1 : 0) + '">' +
            '<img src="' + esc(p.cover) + '" class="w-full h-28 md:h-32 object-cover object-top" onerror="this.src=\'./assets/images/placeholder.png\'">' +
            '<div class="absolute bottom-0 inset-x-0 bg-black/60 px-2 py-1.5 text-white text-xs font-semibold truncate">' + esc(p.name) + (p.mine ? ' <span class="text-[#fbbf24]">★</span>' : '') + '</div>' +
            '</div>'
    }

    function renderPersonas(platform, mine) {
        var grid = document.getElementById('cvPersonaGrid')
        var html = ''
        if (mine.length) {
            html += '<div class="col-span-3 md:col-span-4 text-[#A1A1A1] text-xs font-semibold pt-1">MY CHARACTERS</div>'
            mine.forEach(function (p) { html += personaCard(p) })
            html += '<div class="col-span-3 md:col-span-4 text-[#A1A1A1] text-xs font-semibold pt-2">PLATFORM GIRLS</div>'
        }
        platform.forEach(function (p) { html += personaCard(p) })
        grid.innerHTML = html
        grid.querySelectorAll('.cv-pcard').forEach(function (card) {
            card.addEventListener('click', function () {
                selPersona = {
                    id: parseInt(card.getAttribute('data-id'), 10),
                    name: card.getAttribute('data-name'),
                    cover: card.getAttribute('data-cover'),
                    mine: card.getAttribute('data-mine') === '1',
                }
                grid.querySelectorAll('.cv-pcard').forEach(function (c) {
                    c.className = c.className.replace('border-[#E75275]', 'border-transparent')
                })
                card.className = card.className.replace('border-transparent', 'border-[#E75275]')
            })
        })
    }

    function renderActions(actions) {
        var grid = document.getElementById('cvActionGrid')
        grid.innerHTML = actions.map(function (a) {
            var sel = selAction && selAction.key === a.key
            return '<button class="cv-abtn px-4 py-2.5 rounded-full border text-sm font-medium cursor-pointer ' + (sel ? 'border-[#E75275] bg-[#E75275]/15 text-white' : 'border-[#434343] text-[#A1A1A1]') + '" data-key="' + a.key + '" data-label="' + esc(a.label) + '">' + esc(a.label) + '</button>'
        }).join('')
        grid.querySelectorAll('.cv-abtn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                selAction = { key: btn.getAttribute('data-key'), label: btn.getAttribute('data-label') }
                grid.querySelectorAll('.cv-abtn').forEach(function (b) {
                    b.className = b.className.replace('border-[#E75275] bg-[#E75275]/15 text-white', 'border-[#434343] text-[#A1A1A1]')
                })
                btn.className = btn.className.replace('border-[#434343] text-[#A1A1A1]', 'border-[#E75275] bg-[#E75275]/15 text-white')
            })
        })
    }

    /* ---------- Create：提交 ---------- */
    function submit() {
        if (!selPersona) { toast('Please choose your girl', 'warn'); return }
        if (!selAction) { toast('Please choose an action', 'warn'); return }
        var btn = document.getElementById('cvSubmit')
        btn.disabled = true
        btn.textContent = 'Submitting…'
        authJson(API + '/submit', {
            method: 'POST',
            body: JSON.stringify({ persona_id: selPersona.id, action: selAction.key }),
        }).then(function (d) {
            btn.disabled = false
            btn.textContent = 'Generate ✨'
            if (!d || d.code !== '00000') {
                toast((d && d.msg) || 'Submit failed', 'error')
                return
            }
            if (remainEl) remainEl.textContent = d.data.remain
            toast('Task submitted! Track it in My Works', 'success')
            switchTab(false)
        }).catch(function () {
            btn.disabled = false
            btn.textContent = 'Generate ✨'
            toast('Network error', 'error')
        })
    }

    /* ---------- My Works ---------- */
    var STATUS_META = {
        pending:    { label: 'Queued',      cls: 'bg-[#434343] text-[#A1A1A1]' },
        accepted:   { label: 'Queued',      cls: 'bg-[#434343] text-[#A1A1A1]' },
        processing: { label: 'Generating…', cls: 'bg-[#f59e0b]/20 text-[#fbbf24]' },
        completed:  { label: 'Ready ✨',     cls: 'bg-[#10b981]/20 text-[#10b981]' },
        failed:     { label: 'Failed',      cls: 'bg-[#ef4444]/20 text-[#ef4444]' },
        expired:    { label: 'Expired',     cls: 'bg-[#434343] text-[#A1A1A1]' },
    }

    function workCard(t) {
        var meta = STATUS_META[t.status] || { label: t.status, cls: 'bg-[#434343] text-[#A1A1A1]' }
        var body = ''
        if (t.status === 'completed' && t.video_url) {
            body = '<video controls playsinline preload="metadata" src="' + esc(t.video_url) + '" class="w-full rounded-lg bg-black"></video>'
        } else if (t.status === 'failed') {
            body = '<div class="text-[#ef4444] text-xs py-6 text-center">Generation failed — quota not consumed, please retry later.</div>'
        } else {
            body = '<div class="text-[#A1A1A1] text-xs py-8 text-center">⏳ Your video is being generated…</div>'
        }
        return '<div class="rounded-xl border border-[#282828] bg-[#1a1a1a] overflow-hidden">' +
            '<div class="flex items-center justify-between px-4 py-3">' +
            '<div class="text-white text-sm font-semibold truncate">' + esc(t.persona_name || 'Custom video') + '</div>' +
            '<span class="text-[11px] font-semibold px-2.5 py-1 rounded-full ' + meta.cls + '">' + meta.label + '</span>' +
            '</div>' +
            '<div class="px-4 pb-4">' + body +
            (t.status === 'completed' && t.video_url ? '<a href="' + esc(t.video_url) + '" download class="inline-block mt-2 text-[#E75275] text-xs font-semibold">⬇ Download</a>' : '') +
            '</div>' +
            '<div class="px-4 pb-3 text-[#6b6b6b] text-[11px]">' + esc((t.created_at || '').replace('T', ' ').slice(0, 16)) + '</div>' +
            '</div>'
    }

    var pollTimer = null

    function loadWorks(silent) {
        if (!token) return
        authJson(API + '/myList').then(function (d) {
            if (!d || d.code !== '00000' || !Array.isArray(d.data)) { if (!silent) toast((d && d.msg) || 'Load failed', 'error'); return }
            var grid = document.getElementById('cvWorksGrid')
            var empty = document.getElementById('cvWorksEmpty')
            empty.classList.toggle('hidden', d.data.length > 0)
            grid.innerHTML = d.data.map(workCard).join('')
            // 有进行中任务 → 轮询
            var active = d.data.some(function (t) { return t.status === 'pending' || t.status === 'accepted' || t.status === 'processing' })
            if (active && !pollTimer) pollTimer = setInterval(function () { loadWorks(true) }, 10000)
            if (!active && pollTimer) { clearInterval(pollTimer); pollTimer = null }
        }).catch(function () { if (!silent) toast('Network error', 'error') })
    }

    function init() {
        if (!document.getElementById('cvTabCreate')) return
        remainEl = document.getElementById('cvRemain')
        document.getElementById('cvTabCreate').addEventListener('click', function () { switchTab(true) })
        document.getElementById('cvTabWorks').addEventListener('click', function () { switchTab(false) })
        document.getElementById('cvSubmit').addEventListener('click', submit)
        loadOptions()
        loadWorks(true)
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init)
    else init()
})()
