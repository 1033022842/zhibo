(()=>{
    var base = ''
       // 缓存对话模板
       var cacheTemplate = $('#messages-list').html()
       var token = localStorage.getItem('live_access_token')
     /** 
     * 获取指定的URL参数�?
     * URL:http://www.xxx.com/index?name=123
     * 参数：param URL参数 
     * 调用方法:getParam("name") 
     * 返回�?123
     * alert(getParam('date'));
     */ 
     function getParam(name) {  
        var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");  
        var r = location.search.substring(1).match(reg);  
        if (r != null) return decodeURI(decodeURI(r[2])); 
    }
    setTimeout(() => {
      if(token) {
          $('#sign-in-modal').css('display', 'none')
                $('#user-setting').css('display', 'flex')
                $('#user-login').css('display', 'none')
        }
        var id = getParam('id')
        // 登录态/跳登录页/通话的初始化必须最先做：下面会话初始化万一抛错也不会把它跳过
        setupAuthFlow()
        setupCall()
        setupLogout()
        // 会话列表是异步渲染的，点击必须用事件委托，否则后插入的条目不会带上事件
        $(document).on('click', '#message-list a.chat-obj', function(){
            selectConversation(String($(this).data('id') || ''))
        })
        channelDetail(id)
    }, 1000);

    // 会话列表(id -> 角色对象)，点击时用它取右侧资料
    var convItems = {}

    // 本地会话列表（首页/Explore 点进角色时写入）
    function readPageList() {
        var list = []
        try { list = JSON.parse(localStorage.getItem('pageList') || '[]') || [] } catch (e) { list = [] }
        return Array.isArray(list) ? list : []
    }

    // 会话条目默认结构，保证 getDetail 不会因为缺字段报错
    function emptyConv(id, title) {
        return {
            id: String(id || ''), title: title || String(id || ''), image: '', description: '', time: '',
            occupation: '-', hobbies: '-', relationship: '-', body: '-', age: '-', ethnicity: '-', messageList: []
        }
    }

    // 会话项 data-id -> 角色对象（原来直接传 id 字符串会让 getDetail 报错）
    function chatItemById(id) {
        if (id && typeof id === 'object') return id
        id = String(id || '')
        if (id === '') return null
        if (convItems[id]) return convItems[id]
        var list = readPageList()
        for (var i = 0; i < list.length; i++) {
            if (String(list[i] && list[i].id) === id) return list[i]
        }
        return emptyConv(id)
    }

    /* ---------- 已登录：不弹登录框，发送走站内 AI 接口 ---------- */
    var sending = false

    function hideAuthModals() {
        $('#registrationModal, [data-main-target="registrationModal"], [data-main-target="signInModal"]').css('display', 'none')
        $('#sign-in-modal').css('display', 'none')
    }

    /* ---------- 退出登录（右上角 My Profile → Logout） ---------- */
    // 该按钮原本放在 onsubmit="return false" 的表单里，没有脚本接管，点了没有任何反应
    function setupLogout() {
        $(document).on('click', '#logout, .logout', function (e) {
            e.preventDefault()
            var t = localStorage.getItem('live_access_token')
            localStorage.removeItem('live_access_token')
            localStorage.removeItem('live_user')
            localStorage.removeItem('live_user_info')
            // 本地会话缓存一并清掉，避免下一个登录的人看到上一个人的会话列表
            localStorage.removeItem('pageList')
            for (var i = localStorage.length - 1; i >= 0; i--) {
                var k = localStorage.key(i)
                if (k && k.indexOf('myPersona:') === 0) localStorage.removeItem(k)
            }

            var jumped = false
            function goLogin() {
                if (jumped) return
                jumped = true
                location.href = './Login.html'
            }
            // 先等接口吊销服务端 token（用 keepalive 避免跳转把请求打断），失败或超时也照样退出
            if (t) {
                fetch('/api/live/logout', { method: 'POST', headers: authHeaders(true), body: '{}', keepalive: true })
                    .then(function (r) { return r.text() })
                    .then(goLogin, goLogin)
                setTimeout(goLogin, 1500)
            } else {
                goLogin()
            }
        })
    }

    function currentConversation() {
        // 会话标识必须以「当前选中的会话」为准，否则切换到非首项会话时会存错/读错历史
        var a = $('#message-list .chat-obj.active').first()
        if (!a.length) {
            var wanted = String(getParam('id') || '')
            if (wanted !== '') a = $('#message-list .chat-obj[data-id="' + wanted + '"]').first()
        }
        if (!a.length) a = $('#message-list .chat-obj').first()
        return { id: a.attr('data-id') || '', name: a.find('.chat-name, .text-white').first().text().trim() }
    }

    // 首页跳过来的角色没有平台内容 id，需要前端把人设一起带上
    function currentPersona() {
        var conv = currentConversation()
        var list = []
        try { list = JSON.parse(localStorage.getItem('pageList') || '[]') || [] } catch (e) { list = [] }
        for (var i = 0; i < list.length; i++) {
            if (String(list[i] && list[i].id) === String(conv.id)) {
                return { name: list[i].title || conv.name, desc: list[i].description || '' }
            }
        }
        // 自建角色（my-N）：loadMyPersona 已缓存人设
        if (/^my-\d+$/.test(String(conv.id))) {
            try {
                var cached = JSON.parse(localStorage.getItem('myPersona:' + conv.id) || 'null')
                if (cached) return { name: cached.name || conv.name, desc: cached.desc || '' }
            } catch (e) {}
        }
        return { name: conv.name, desc: '' }
    }

    /* 我的角色（characters 向导创建）会话：customOneList 拉取并渲染 */
    function loadMyPersona(id) {
        var pid = String(id).slice(3)
        if (!token) {
            location.href = './Login.html?redirect=' + encodeURIComponent('./Chat.html?id=' + id)
            return
        }
        fetch('/api/live/customOneList', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token } })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (!d || d.code !== '00000' || !Array.isArray(d.data)) {
                    appendError((d && d.msg) || 'Failed to load character')
                    return
                }
                var t = null
                for (var i = 0; i < d.data.length; i++) {
                    if (String(d.data[i].id) === pid) { t = d.data[i]; break }
                }
                if (!t) { appendError('Character not found'); return }
                var sf = t.source_fields || {}
                var bits = []
                if (sf.age) bits.push(sf.age + ' years old')
                if (sf.race) bits.push(sf.race)
                if (sf.personality) bits.push(sf.personality + ' personality')
                if (sf.hairstyle || sf.hair) bits.push((sf.hairstyle || '') + ' ' + (sf.hair || '') + ' hair')
                if (sf.body) bits.push(sf.body + ' body')
                if (sf.clothing) bits.push('wearing ' + sf.clothing)
                if (sf.relation) bits.push('relationship: ' + sf.relation)
                var desc = 'The user created this AI girlfriend. Traits: ' + (bits.join(', ') || 'a sweet and loving companion')
                var img = String(t.photo || '')
                if (img && img.indexOf('://') < 0) img = img.replace(/^\./, '')
                var info = {
                    id: id,
                    title: t.name || 'My AI',
                    image: img,
                    description: desc,
                    occupation: sf.profession || '-',
                    hobbies: sf.hobby || '-',
                    relationship: sf.relation || '-',
                    body: sf.body || '-',
                    age: sf.age || '-',
                    ethnicity: sf.race || '-',
                    messageList: []
                }
                localStorage.setItem('myPersona:' + id, JSON.stringify({ name: info.title, desc: desc }))
                getInfo([info])
                selectConversation(String(id))
            })
            .catch(function () { appendError('Network error') })
    }

    function deviceId() {
        var k = 'live_device_id'
        var v = localStorage.getItem(k)
        if (!v) {
            v = 'web-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10)
            localStorage.setItem(k, v)
        }
        return v
    }

    function nowTime() {
        var d = new Date()
        var h = d.getHours()
        var ampm = h >= 12 ? 'PM' : 'AM'
        h = h % 12
        if (h === 0) h = 12
        return h + ':' + ('0' + d.getMinutes()).slice(-2) + ' ' + ampm
    }

    function scrollMessagesToBottom() {
        var box = document.getElementById('messages')
        if (box) box.scrollTop = box.scrollHeight
    }

    function messagesBox() {
        return document.getElementById('messages_turbo_frame') || document.getElementById('messages')
    }

    function appendBubble(text, mine, voice) {
        var box = messagesBox()
        if (!box) return null
        var row = document.createElement('div')
        row.className = 'user-response px-4 py-2'
        var tag = voice
            ? '<span style="display:inline-flex;align-items:center;height:18px;padding:0 7px;margin-right:7px;border-radius:99px;' +
              'background:' + (mine ? 'rgba(255,255,255,.14)' : 'rgba(0,0,0,.18)') + ';font-size:11px;font-weight:600;' +
              'line-height:1;color:rgba(255,255,255,.92);vertical-align:middle;white-space:nowrap">VOICE</span>'
            : ''
        row.innerHTML = '<div class="flex ' + (mine ? 'justify-end' : 'justify-start') + '">' +
            '<div class="flex-col gap-[9px] inline-flex ' + (mine ? 'ml-auto items-end' : '') + '">' +
            '<div class="js-bubble text-white text-sm font-normal" style="white-space:pre-wrap;' +
            (mine ? 'background:#303030;padding:10px 12px;border-radius:10px;max-width:80%'
                  : 'background:#D98491;padding:12px;border-radius:10px 10px 10px 0') + '"></div>' +
            '<div class="text-neutral-500 text-[13px] font-normal">' + nowTime() + '</div>' +
            '</div></div>'
        var bubble = row.querySelector('.js-bubble')
        if (tag) bubble.innerHTML = tag
        bubble.appendChild(document.createTextNode(text))
        box.appendChild(row)
        scrollMessagesToBottom()
        return row
    }

    function appendTyping() {
        var box = messagesBox()
        if (!box) return null
        var row = document.createElement('div')
        row.className = 'user-response px-4 py-2'
        row.innerHTML = '<div class="flex justify-start"><div class="px-8 py-3 inline-flex" ' +
            'style="background:#D98491;border-radius:10px 10px 10px 0">' +
            '<div class="snippet" data-title="dot-elastic"><div class="stage"><div class="dot-elastic"></div></div></div>' +
            '</div></div>'
        box.appendChild(row)
        scrollMessagesToBottom()
        return row
    }

    function appendError(msg) {
        var box = messagesBox()
        if (!box) return
        var row = document.createElement('div')
        row.className = 'px-4 py-1 text-[12px] text-rose-400'
        row.textContent = msg
        box.appendChild(row)
        scrollMessagesToBottom()
    }

    /* ---------- 聊天：历史 / 媒体消息卡片 / 好感度 / 特殊视频（参考 ai_web/chat.html） ---------- */
    var chatHis = []
    // 当前会话 id（左侧列表里被选中的那条，决定历史归档键）
    var activeConvId = ''
    // 是否已加载过一次历史（用于区分「首次加载」与「切换会话」）
    var historyLoadedOnce = false

    function uiLang() {
        return (document.documentElement.getAttribute('lang') || 'en').slice(0, 2)
    }

    // 当前会话对应的平台内容 id（首页/自建角色没有平台 id，返回 0）
    function convContentId() {
        var id = String(currentConversation().id || '')
        return /^\d+$/.test(id) ? id : '0'
    }

    // 非平台角色的归档标识（如 home-4），后端在 content_id=0 时靠它区分角色
    // 列表尚未渲染时以 URL ?id= 为准（避免回退到公共键后串到其它角色的历史）
    function convRoleKey() {
        var id = String(currentConversation().id || '')
        if (id === '') id = String(getParam('id') || '')
        if (id === '') return 'chat-default'
        return /^\d+$/.test(id) ? '' : id
    }

    function authHeaders(json) {
        var h = {}
        if (json) h['Content-Type'] = 'application/json'
        if (token) h.Authorization = 'Bearer ' + token
        return h
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    }

    function fmtDur(s) {
        s = Math.max(0, Math.round(s || 0))
        return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2)
    }

    function timeHtml() {
        return '<div class="text-neutral-500 text-[13px] font-normal">' + nowTime() + '</div>'
    }

    function voiceBarsHtml(color) {
        var hs = [8, 14, 20, 26, 16, 24, 12, 18, 28, 22, 10, 16, 24, 14, 20, 12]
        var out = ''
        for (var i = 0; i < hs.length; i++) {
            out += '<i style="display:inline-block;width:2px;height:' + hs[i] + 'px;background:' + color + ';border-radius:2px;margin-right:2px"></i>'
        }
        return out
    }

    function mediaRow(media, mine) {
        var box = messagesBox()
        if (!box) return null
        var row = document.createElement('div')
        row.className = 'user-response px-4 py-2'
        row.innerHTML = '<div class="flex ' + (mine ? 'justify-end' : 'justify-start') + '">' +
            '<div class="flex-col gap-[9px] inline-flex ' + (mine ? 'ml-auto items-end' : '') + '">' +
            '<div class="js-media"></div>' + timeHtml() + '</div></div>'
        box.appendChild(row)
        scrollMessagesToBottom()
        return row.querySelector('.js-media')
    }

    function paintMedia(node, media, mine) {
        if (!node) return
        if (!media) { node.innerHTML = ''; return }
        var price = Number(media.unlock_price || 0)
        var bg = mine ? '#303030' : '#D98491'
        var barColor = mine ? '#A1A1A1' : 'rgba(255,255,255,.9)'
        var label = media.kind === 'voice' ? 'Voice message' : 'Video message'

        if (media.unlocked === false) {
            node.innerHTML = '<div style="background:#262626;border:1px solid #434343;border-radius:10px;padding:12px 14px;min-width:200px">' +
                '<div style="display:flex;align-items:center;gap:10px;color:#A1A1A1;font-size:13px">' +
                '<svg viewBox="0 0 24 24" width="18" height="18" fill="#A1A1A1"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5zm-3 8V7a3 3 0 0 1 6 0v3H9z"/></svg>' +
                esc(label) + '</div>' +
                '<button class="js-unlock" style="margin-top:10px;width:100%;background:#E75275;border:0;border-radius:8px;color:#fff;font-size:13px;font-weight:600;padding:8px 10px;cursor:pointer">Unlock' +
                (price > 0 ? ' (' + price + ')' : '') + '</button></div>'
            var btn = node.querySelector('.js-unlock')
            if (btn) btn.addEventListener('click', function () { unlockChatMedia(media, node, mine) })
            return
        }

        if (media.kind === 'voice') {
            node.innerHTML = '<div class="js-voice" style="display:flex;align-items:center;gap:10px;background:' + bg + ';border-radius:10px;padding:10px 14px;min-width:180px">' +
                '<button class="js-voice-play" style="background:none;border:0;padding:0;cursor:pointer;display:flex;align-items:center">' +
                '<svg class="js-ic-play" viewBox="0 0 24 24" width="22" height="22" fill="#fff"><path d="M8 5v14l11-7z"/></svg>' +
                '<svg class="js-ic-pause" viewBox="0 0 24 24" width="22" height="22" fill="#fff" style="display:none"><path d="M6 5h4v14H6zM14 5h4v14h-4z"/></svg>' +
                '</button>' +
                '<div style="display:flex;align-items:flex-end;height:28px">' + voiceBarsHtml(barColor) + '</div>' +
                '<span class="js-voice-dur" style="color:' + barColor + ';font-size:12px">0:00</span></div>'
            bindVoicePlayer(node, media.url)
        } else {
            node.innerHTML = '<div style="max-width:260px"><video controls playsinline preload="metadata" src="' +
                esc(media.url) + '" style="display:block;width:260px;border-radius:10px"></video></div>'
        }
    }

    function bindVoicePlayer(node, url) {
        var btn = node.querySelector('.js-voice-play')
        var icPlay = node.querySelector('.js-ic-play')
        var icPause = node.querySelector('.js-ic-pause')
        var durEl = node.querySelector('.js-voice-dur')
        if (!btn || !url) return
        var audio = new Audio(url)
        audio.preload = 'metadata'
        btn.addEventListener('click', function () {
            if (audio.paused) audio.play().catch(function () {})
            else audio.pause()
        })
        audio.addEventListener('play', function () { icPlay.style.display = 'none'; icPause.style.display = '' })
        audio.addEventListener('pause', function () { icPlay.style.display = ''; icPause.style.display = 'none' })
        audio.addEventListener('ended', function () { icPlay.style.display = ''; icPause.style.display = 'none' })
        audio.addEventListener('loadedmetadata', function () {
            if (audio.duration && isFinite(audio.duration)) durEl.textContent = fmtDur(audio.duration)
        })
    }

    function unlockChatMedia(media, node, mine) {
        if (!token) { goLoginPage(); return }
        var price = Number(media.unlock_price || 0)
        if (price > 0 && !window.confirm('Unlock this message for ' + price + ' diamonds?')) return
        fetch('/api/live/unlockChatMedia', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ message_id: media.message_id })
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === '00000' && d.data) {
                    media.unlocked = true
                    media.url = d.data.video_url || d.data.url || ''
                    paintMedia(node, media, mine)
                } else {
                    appendError((d && d.msg) ? ('解锁失败：' + d.msg) : '解锁失败，请稍后重试')
                }
            })
            .catch(function () { appendError('网络错误，请稍后重试') })
    }

    // 全屏播放特殊视频（满好感度触发的剧情视频）
    function playSpecialVideo(url) {
        if (!url) return
        var box = document.getElementById('js-special-video')
        if (!box) {
            box = document.createElement('div')
            box.id = 'js-special-video'
            box.style.cssText = 'position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.85);display:flex;align-items:center;justify-content:center;padding:20px'
            box.innerHTML = '<video controls autoplay playsinline style="max-width:min(92vw,720px);max-height:86vh;border-radius:12px"></video>' +
                '<div class="js-close" style="position:absolute;top:18px;right:22px;color:#fff;font-size:28px;cursor:pointer;line-height:1">&times;</div>'
            document.body.appendChild(box)
            box.addEventListener('click', function (e) {
                if (e.target === box || e.target.classList.contains('js-close')) {
                    var v = box.querySelector('video')
                    v.pause()
                    v.removeAttribute('src')
                    v.load()
                    box.style.display = 'none'
                }
            })
        }
        var video = box.querySelector('video')
        video.src = url
        box.style.display = 'flex'
        video.play().catch(function () {})
    }

    // 好感度：在右侧资料面板显示进度（有数据才插入）
    function renderAffection(data) {
        if (!data) return
        var info = document.getElementById('infomation')
        if (!info || !info.parentNode) return
        var max = Number(data.max || 100) || 100
        var val = Math.max(0, Math.min(max, Number(data.affection || 0)))
        var box = document.getElementById('js-affection')
        if (!box) {
            box = document.createElement('div')
            box.id = 'js-affection'
            box.style.cssText = 'width:100%;margin-top:12px'
            box.innerHTML = '<div style="display:flex;justify-content:space-between;font-size:12px;color:#A1A1A1;margin-bottom:6px">' +
                '<span>Affection</span><span class="js-aff-val"></span></div>' +
                '<div style="height:6px;border-radius:999px;background:#303030;overflow:hidden">' +
                '<div class="js-aff-fill" style="height:100%;width:0;background:linear-gradient(90deg,#E75275,#F97187);border-radius:999px;transition:width .4s"></div></div>'
            info.parentNode.insertBefore(box, info.nextSibling)
        }
        box.querySelector('.js-aff-val').textContent = val + ' / ' + max
        box.querySelector('.js-aff-fill').style.width = Math.round(val / max * 100) + '%'
    }

    function loadAffection() {
        var cid = convContentId()
        if (!token || cid === '0') return
        fetch('/api/live/affection?content_id=' + cid, { headers: authHeaders(false) })
            .then(function (r) { return r.json() })
            .then(function (d) { if (d && d.code === '00000' && d.data) renderAffection(d.data) })
            .catch(function () {})
    }

    // 载入该角色的历史聊天（登录按用户，游客按 device_id；非平台角色按 role_key）
    function loadChatHistory() {
        chatHis = []
        var cid = convContentId()
        var rkey = convRoleKey()
        if (cid === '0' && rkey === '') { loadAffection(); return }
        var url = '/api/live/chatHistory?content_id=' + cid +
            '&device_id=' + encodeURIComponent(deviceId()) + '&limit=50'
        if (rkey !== '') url += '&role_key=' + encodeURIComponent(rkey)
        fetch(url, {
            headers: authHeaders(false)
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
                var rows = []
                for (var i = 0; i < list.length; i++) {
                    var m = list[i]
                    if (!m) continue
                    var mine = m.role !== 'assistant'
                    if (m.media && (m.media.url || m.media.unlocked === false)) rows.push({ media: m.media, mine: mine })
                    else if (m.content) rows.push({ text: m.content, mine: mine, role: m.role, voice: !!m.voice })
                }
                // 没有历史：首次加载保留页面自带的问候占位，切换会话时清空上一个会话的消息
                if (!rows.length) {
                    if (historyLoadedOnce) {
                        var emptyBox = messagesBox()
                        if (emptyBox) emptyBox.innerHTML = ''
                    }
                    historyLoadedOnce = true
                    return
                }
                historyLoadedOnce = true
                var box = messagesBox()
                if (box) box.innerHTML = ''
                for (var j = 0; j < rows.length; j++) {
                    var r = rows[j]
                    if (r.media) paintMedia(mediaRow(r.media, r.mine), r.media, r.mine)
                    else {
                        appendBubble(r.text, r.mine, r.voice)
                        chatHis.push({ role: r.role, content: r.text })
                    }
                }
                scrollMessagesToBottom()
            })
            .catch(function () {})
        loadAffection()
    }

    function sendMessage() {
        var input = document.getElementById('message_body')
        if (!input) return
        // 未登录不允许聊天（也不会产生记录）
        if (!token) { goLoginPage(); return }
        var text = (input.value || '').trim()
        if (text === '' || sending) return
        sending = true
        input.value = ''

        appendBubble(text, true)
        var typing = appendTyping()

        var cid = convContentId()
        var rkey = convRoleKey()
        var persona = currentPersona()
        var body = {
            message: text,
            content_id: cid,
            device_id: deviceId(),
            lang: uiLang(),
            history: chatHis.slice(-12)
        }
        // 首页等非平台角色：带上归档标识与人设
        if (rkey !== '') body.role_key = rkey
        if (cid === '0') {
            body.persona_name = persona.name || ''
            body.persona_desc = persona.desc || ''
        }

        fetch('/api/live/chat', { method: 'POST', headers: authHeaders(true), body: JSON.stringify(body) })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (typing) typing.remove()
                if (!d || d.code !== '00000' || !d.data) {
                    appendError((d && d.msg) ? ('发送失败：' + d.msg) : '发送失败，请稍后重试')
                    return
                }
                var data = d.data
                chatHis.push({ role: 'user', content: text })
                if (data.reply) {
                    appendBubble(data.reply, false)
                    chatHis.push({ role: 'assistant', content: data.reply })
                }
                if (data.media) paintMedia(mediaRow(data.media, false), data.media, false)
                if (data.affection) renderAffection(data.affection)
                if (data.special_video) playSpecialVideo(data.special_video)
                if (!data.reply && !data.media) appendError('未收到回复，请再试一次')
                if (chatHis.length > 24) chatHis = chatHis.slice(-24)
            })
            .catch(function () {
                if (typing) typing.remove()
                appendError('网络错误，请稍后重试')
            })
            .then(function () { sending = false })
    }

    /* ---------- AI 语音通话（参考 ai_web/chat.html：浏览器 TTS + STT） ---------- */
    var callActive = false
    var callMuted = false
    var callSpeaking = false
    var callRec = null

    var CALL_MIC_ON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3zm5-3h2a7 7 0 0 1-6 6.92V21h-2v-3.08A7 7 0 0 1 5 11h2a5 5 0 0 0 10 0z"/></svg>'
    var CALL_MIC_OFF = '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3zm5-3h2a7 7 0 0 1-6 6.92V21h-2v-3.08A7 7 0 0 1 5 11h2a5 5 0 0 0 10 0z"/><path d="M3 3l18 18" stroke="#fff" stroke-width="2" fill="none"/></svg>'
    var CALL_PHONE = '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M6.6 10.8c1.4 2.8 3.8 5.1 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.1.4 2.3.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1C10.6 21 3 13.4 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.2.2 2.4.6 3.6.1.3 0 .7-.2 1l-2.3 2.2z"/></svg>'

    function ensureCallStyle() {
        if (document.getElementById('js-call-style')) return
        var s = document.createElement('style')
        s.id = 'js-call-style'
        s.textContent = '@keyframes js-call-pulse{0%,100%{opacity:1}50%{opacity:.25}}'
        document.head.appendChild(s)
    }

    // 聊天头部：在线状态 + 通话控件（静音 / 挂断），效果与 ai_web/chat.html 一致
    function callHeaderUi() {
        var nameEl = document.getElementById('chat-name')
        if (!nameEl || !nameEl.parentNode) return null
        var holder = nameEl.parentNode
        var status = document.getElementById('js-call-status')
        if (!status) {
            status = document.createElement('div')
            status.id = 'js-call-status'
            status.style.cssText = 'display:flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:#A1A1A1;line-height:1.2'
            status.innerHTML = '<span class="js-dot" style="width:8px;height:8px;border-radius:50%;background:#3ddc84;display:inline-block"></span>' +
                '<span class="js-text">Online now</span>'
            holder.appendChild(status)
        }
        var bar = document.getElementById('js-call-controls')
        if (!bar) {
            bar = document.createElement('div')
            bar.id = 'js-call-controls'
            bar.style.cssText = 'display:none;align-items:center;gap:8px;margin-right:10px'
            bar.innerHTML =
                '<button type="button" id="js-call-mic" style="height:34px;min-width:34px;padding:0 10px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#262626;border:1px solid #434343;color:#fff;font-size:13px;font-weight:600;cursor:pointer">' +
                '<span class="js-mic-icon" style="display:flex">' + CALL_MIC_ON + '</span><span class="js-mic-label">Mute</span></button>' +
                '<button type="button" id="js-call-hangup" style="height:34px;min-width:34px;padding:0 10px;border-radius:99px;display:inline-flex;align-items:center;justify-content:center;gap:6px;background:#E7484F;border:1px solid #E7484F;color:#fff;font-size:13px;font-weight:600;cursor:pointer">' +
                '<span style="display:flex;transform:rotate(135deg)">' + CALL_PHONE + '</span><span>Hang up</span></button>'
            var phone = document.getElementById('phone-btn')
            if (phone && phone.parentNode) phone.parentNode.insertBefore(bar, phone)
            else holder.appendChild(bar)
            bar.querySelector('#js-call-mic').addEventListener('click', function (e) {
                e.preventDefault()
                e.stopPropagation()
                callMuted = !callMuted
                var btn = bar.querySelector('#js-call-mic')
                btn.querySelector('.js-mic-icon').innerHTML = callMuted ? CALL_MIC_OFF : CALL_MIC_ON
                btn.querySelector('.js-mic-label').textContent = callMuted ? 'Unmute' : 'Mute'
                btn.style.background = callMuted ? '#fff' : '#262626'
                btn.style.color = callMuted ? '#000' : '#fff'
                if (callMuted) callStopListening()
                else if (!callSpeaking) callStartListening()
            })
            bar.querySelector('#js-call-hangup').addEventListener('click', function (e) {
                e.preventDefault()
                e.stopPropagation()
                closeCall()
            })
        }
        return { status: status, bar: bar }
    }

    function callResetMic() {
        var bar = document.getElementById('js-call-controls')
        if (!bar) return
        var btn = bar.querySelector('#js-call-mic')
        if (!btn) return
        callMuted = false
        btn.style.background = '#262626'
        btn.style.color = '#fff'
        btn.querySelector('.js-mic-icon').innerHTML = CALL_MIC_ON
        btn.querySelector('.js-mic-label').textContent = 'Mute'
    }

    function callLocale() {
        return ({ zh: 'zh-CN', en: 'en-US', ja: 'ja-JP' })[uiLang()] || 'en-US'
    }

    function hashSeed(str) {
        var h = 0
        str = String(str || '')
        for (var i = 0; i < str.length; i++) { h = ((h << 5) - h) + str.charCodeAt(i); h |= 0 }
        return Math.abs(h)
    }

    function callRoleName() {
        var p = currentPersona()
        return p.name || currentConversation().name || 'AI'
    }

    // 同一个角色固定用同一个音色
    function pickVoice(seed) {
        if (!('speechSynthesis' in window) || !window.speechSynthesis) return null
        var voices = window.speechSynthesis.getVoices()
        if (!voices || !voices.length) return null
        var prefix = callLocale().split('-')[0].toLowerCase()
        var cands = []
        for (var i = 0; i < voices.length; i++) {
            if ((voices[i].lang || '').toLowerCase().indexOf(prefix) === 0) cands.push(voices[i])
        }
        if (!cands.length) return null
        var pref = []
        for (var j = 0; j < cands.length; j++) {
            if (/google|natural|neural|premium|online|aria|jenny|guy|libby|xiaoxiao/i.test(cands[j].name)) pref.push(cands[j])
        }
        var pool = pref.length ? pref : cands
        return pool[seed % pool.length]
    }

    // 通话状态：头部状态点 + 通话控件显隐（对齐 ai_web/chat.html）
    function callSetStatus(inCall) {
        var ui = callHeaderUi()
        if (!ui) return
        var dot = ui.status.querySelector('.js-dot')
        var text = ui.status.querySelector('.js-text')
        if (!dot || !text) return
        if (inCall) {
            dot.style.background = '#E75275'
            dot.style.animation = 'js-call-pulse 1s ease-in-out infinite'
            text.textContent = 'In call…'
            ui.bar.style.display = 'flex'
        } else {
            dot.style.background = '#3ddc84'
            dot.style.animation = ''
            text.textContent = 'Online now'
            ui.bar.style.display = 'none'
        }
    }

    function callStopListening() {
        if (callRec) { try { callRec.stop() } catch (e) {} }
    }

    function callSpeak(text) {
        if (!text) return
        if (!('speechSynthesis' in window) || !window.speechSynthesis) {
            if (callActive) callStartListening()
            return
        }
        callStopListening()
        window.speechSynthesis.cancel()
        callSpeaking = true
        var u = new SpeechSynthesisUtterance(text)
        var seed = hashSeed(callRoleName())
        var v = pickVoice(seed)
        if (v) u.voice = v
        u.lang = callLocale()
        u.rate = 0.98 + (seed % 9) * 0.01
        u.pitch = 0.95 + ((seed >> 3) % 7) * 0.06
        u.onend = function () { callSpeaking = false; if (callActive) callStartListening() }
        u.onerror = function () { callSpeaking = false; if (callActive) callStartListening() }
        window.speechSynthesis.speak(u)
    }

    function callStartListening() {
        if (!callActive || callMuted || callSpeaking) return
        var SR = window.SpeechRecognition || window.webkitSpeechRecognition
        if (!SR) {
            if (!window.jsCallNoSrTipShown) {
                window.jsCallNoSrTipShown = true
                appendError('This browser has no voice input — you can keep typing in the message box.')
            }
            return
        }
        if (!callRec) {
            callRec = new SR()
            callRec.lang = callLocale()
            callRec.continuous = false
            callRec.interimResults = false
            callRec.maxAlternatives = 1
            callRec.onresult = function (e) {
                var text = ''
                for (var i = 0; i < e.results.length; i++) text += e.results[i][0].transcript
                text = text.trim()
                if (text) callSend(text)
            }
            callRec.onerror = function () { if (callActive && !callMuted && !callSpeaking) setTimeout(callStartListening, 700) }
            callRec.onend = function () { if (callActive && !callMuted && !callSpeaking) setTimeout(callStartListening, 300) }
        }
        try { callRec.start() } catch (e) {}
    }

    // 通话里说的话同样走 /api/live/chat（带 voice:1），消息带 VOICE 标记，回复用语音念出来
    function callSend(text) {
        appendBubble(text, true, true)
        var cid = convContentId()
        var rkey = convRoleKey()
        var persona = currentPersona()
        var body = {
            message: text,
            content_id: cid,
            device_id: deviceId(),
            lang: uiLang(),
            history: chatHis.slice(-12),
            voice: 1
        }
        if (rkey !== '') body.role_key = rkey
        if (cid === '0') {
            body.persona_name = persona.name || ''
            body.persona_desc = persona.desc || ''
        }
        fetch('/api/live/chat', { method: 'POST', headers: authHeaders(true), body: JSON.stringify(body) })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var reply = (d && d.code === '00000' && d.data && typeof d.data.reply === 'string') ? d.data.reply : ''
                chatHis.push({ role: 'user', content: text })
                if (reply) {
                    chatHis.push({ role: 'assistant', content: reply })
                    if (chatHis.length > 24) chatHis = chatHis.slice(-24)
                    appendBubble(reply, false, true)
                    callSpeak(reply)
                } else {
                    callSpeak("I'm listening, go on.")
                }
            })
            .catch(function () { callSpeak('Bad signal, say that again?') })
    }

    function openCall() {
        if (!token) { goLoginPage(); return }
        if (callActive) return
        ensureCallStyle()
        callActive = true
        callSpeaking = false
        callHeaderUi()
        callResetMic()
        callSetStatus(true)
        var name = callRoleName()
        setTimeout(function () {
            if (!callActive) return
            var greet = 'Hi, this is ' + name + '. So glad you called!'
            appendBubble(greet, false, true)
            callSpeak(greet)
        }, 800)
    }

    function closeCall() {
        callActive = false
        callSpeaking = false
        callStopListening()
        if ('speechSynthesis' in window && window.speechSynthesis) window.speechSynthesis.cancel()
        callSetStatus(false)
    }

    function setupCall() {
        // 通话按钮：站内浏览器语音通话（不再走 candy 的 phone-call 控制器）
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest
                ? e.target.closest('#phone-btn, #call-btn, [data-phone-call-target="callButton"]')
                : null
            if (!el) return
            e.preventDefault()
            e.stopPropagation()
            openCall()
        }, true)
        if ('speechSynthesis' in window && window.speechSynthesis) {
            window.speechSynthesis.onvoiceschanged = function () { window.speechSynthesis.getVoices() }
            window.speechSynthesis.getVoices()
        }
        // 头部「Online now」状态与通话控件就位
        callHeaderUi()
    }

    // 未登录：站内原本「弹注册/登录框」的地方，统一改成跳转登录页（带上回跳地址）
    function goLoginPage() {
        hideAuthModals()
        var back = window.location.pathname + window.location.search
        window.location.href = './Login.html?redirect=' + encodeURIComponent(back)
    }

    function setupAuthFlow() {
        // 已登录：让页面的 Stimulus 控制器知道已登录，并切到登录态的发送按钮
        if (token) {
            document.body.setAttribute('data-main-is-current-user-signed-in', 'true')
        }
        var guestBtn = document.getElementById('send-question')
        var realBtn = document.getElementById('send-question-with-token')
        if (token) {
            if (guestBtn) guestBtn.classList.add('hidden')
            if (realBtn) realBtn.classList.remove('hidden')
        }

        var inChat = function (node) {
            var area1 = document.getElementById('messages')
            var area2 = document.getElementById('form')
            return (area1 && area1.contains(node)) || (area2 && area2.contains(node))
        }

        // 所有「打开注册/登录弹窗」的元素：不再弹窗
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest
                ? e.target.closest('[data-action*="openRegistrationModal"], [data-action*="openSignInModal"]')
                : null
            if (!el) return
            e.preventDefault()
            e.stopPropagation()
            hideAuthModals()
            if (!token) {
                goLoginPage()
                return
            }
            // 已登录时聊天区内的元素（发送、推荐语、语音等）直接发消息
            if (inChat(el)) sendMessage()
        }, true)

        // 输入框回车：未登录跳登录页，已登录直接发送
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || !e.target || e.target.id !== 'message_body') return
            e.preventDefault()
            e.stopPropagation()
            if (token) sendMessage()
            else goLoginPage()
        }, true)

        if (token && realBtn) {
            realBtn.addEventListener('click', function (e) {
                e.preventDefault()
                e.stopPropagation()
                sendMessage()
            }, true)
        }
    }
    function getInfo(data) {
        convItems = {}
        var template = String($('#user-template').html() || '')
        $('#message-list').find('a.chat-obj').remove()
        $.each(data, function(index, item) {
            if (!item || item.id === undefined || item.id === null || String(item.id) === '') return
            var cid = String(item.id)
            var conv = $.extend({}, item)
            conv.id = cid
            convItems[cid] = conv

            var rendered = template.replace("{{name}}", conv.title == null ? '' : conv.title)
            .replace("{{imgUrl}}", String(conv.image || '').split(',')[0])
            .replace("{{message}}", conv.description == null ? '' : conv.description)
            .replace("{{time}}", conv.time == null ? '' : conv.time)
            .replace("{{id}}", cid);

            $('#message-list').append(rendered);
        });
        // 当前会话置为选中态，保证刷新后归档键不丢
        var $active = $('#message-list').find('a.chat-obj[data-id="' + activeConvId + '"]').first()
        if ($active.length) $active.addClass('px-[6px] bg-[#303030] border border-zinc-600').addClass('active')
    }
    function getDetail(info) {
        if(!info) return
        var pictures = String(info.image || '').split(',')
        $('#chat-name').html(info.title)
        $('#infomation').html(info.description)
        $('#occupation').html(info.occupation)
        $('#hobbies').html(info.hobbies)
        $('#relationship').html(info.relationship)
        $('#body').html(info.body)
        $('#age').html(info.age)
        if (pictures[0]) $('#chat-img').attr('src', pictures[0])
        $('#ethnicity').html(info.ethnicity)
        $('#name').html(info.title)
       
        if(pictures[0]) {
            var html = ''
            pictures.forEach((t, index) => {
                if(index === 0) {
                    html += "<div class='carousel-item active'><img src='"+t+"' class='w-full object-top object-cover h-[483px]'></div>"
                }
                else {
                    html += "<div class='carousel-item'><img src='"+t+"' class='w-full object-top object-cover h-[483px]'></div>"
                }
            });
            $('.carousel').html(html)
        }
        $('#messages-list').html(cacheTemplate);
        $('#phone-btn').on('click', function () {
            location.href = './Game.html?id=' + info.id
          })
          $('#call-btn').on('click', function () {
            location.href = './Game.html?id=' + info.id
          })
        if(!(info.messageList && info.messageList.length> 0)) return
        getMessageBody(info.messageList)
    }

    function channelDetail(id){
        if (String(id).indexOf('my-') === 0) { loadMyPersona(id); return }
        // var token = localStorage.getItem('token')
        // var headers = {}
        // if(token) {
        //   headers = {
        //     token
        //   }
        // }
        // const form = new FormData();
        // form.append("id", id);
        // const settings = {
        //   "async": true,
        //   "crossDomain": true,
        //   "url":  base + "/api/user/channel_detail",
        //   "method": "post",
        //   "headers": headers,
        //   "processData": false,
        //   "contentType": false,
        //   "mimeType": "multipart/form-data",
        //   "data": form
        // };
        // $.ajax(settings).done(function (response) {
        //   if(typeof(response) === 'string') {
        //     response = JSON.parse(response)
        //   }
        //   var res = response
        //    if(res.code === 1) {
        //     getInfo(res.data)
        //   } else {
        //     alert(res.msg)
        //   }
        // }).catch(error => console.error(error))
        var local = readPageList()
        activeConvId = String(id || '') || String(getParam('id') || '')
        // 聊天记录只属于登录用户：未登录不展示会话列表与历史，只显示当前角色资料
        if (!token) {
            getInfo([])
            var guestConv = chatItemById(activeConvId)
            if (guestConv) getDetail(guestConv)
            return
        }
        if (activeConvId === '' && local.length > 0) activeConvId = String(local[0].id || '')

        // 先用本地记录渲染一版，接口慢时左侧也不会空白
        getInfo(buildConvItems([], local))

        // 再拉服务端会话列表（换设备/清了缓存也能看到历史），按最近聊天排序
        fetch('/api/live/chatConversations?device_id=' + encodeURIComponent(deviceId()), { headers: authHeaders(false) })
          .then(function (r) { return r.json() })
          .then(function (d) {
            var convs = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
            if (convs.length > 0) {
              getInfo(buildConvItems(convs, local))
              // URL 不带 ?id= 时补上，刷新后仍停留在同一会话
              if (String(getParam('id') || '') === '') {
                try { history.replaceState(null, '', './Chat.html?id=' + encodeURIComponent(activeConvId)) } catch (e) {}
              }
            }
            startConversation()
          })
          .catch(function () { startConversation() })
      }

    // 服务端会话 + 本地记录 → 左侧可渲染条目（本地记录补名字/头像，服务端给最近一条消息与时间）
    function buildConvItems(convs, local) {
        var items = []
        var used = {}
        for (var i = 0; i < convs.length; i++) {
            var conv = convs[i] || {}
            var cid = Number(conv.content_id || 0)
            var id = cid > 0 ? String(cid) : String(conv.role_key || '')
            if (id === '' || id === '0') continue
            var l = null
            for (var k = 0; k < local.length; k++) {
                if (String(local[k] && local[k].id) === id) { l = local[k]; break }
            }
            var persona = null
            if (/^my-\d+$/.test(id)) {
                try { persona = JSON.parse(localStorage.getItem('myPersona:' + id) || 'null') } catch (e) { persona = null }
            }
            used[id] = true
            var item = emptyConv(id, (l && l.title) || conv.title || (persona && persona.name) || id)
            item.image = (l && l.image) || conv.cover_url || ''
            item.description = conv.last_message || (l && l.description) || ''
            item.time = shortTime(conv.time) || (l && l.time) || ''
            if (l) {
                item.occupation = l.occupation || '-'
                item.hobbies = l.hobbies || '-'
                item.relationship = l.relationship || '-'
                item.body = l.body || '-'
                item.age = l.age || '-'
                item.ethnicity = l.ethnicity || '-'
            }
            items.push(item)
        }
        // 本地刚点开、还没聊过的角色也留在列表里
        for (var j = 0; j < local.length; j++) {
            var e = local[j] || {}
            var eid = String(e.id || '')
            if (eid === '' || used[eid]) continue
            used[eid] = true
            var it = emptyConv(eid, e.title)
            it.image = e.image || ''
            it.description = e.description || ''
            it.time = e.time || ''
            it.occupation = e.occupation || '-'
            it.hobbies = e.hobbies || '-'
            it.relationship = e.relationship || '-'
            it.body = e.body || '-'
            it.age = e.age || '-'
            it.ethnicity = e.ethnicity || '-'
            items.push(it)
        }
        return items
    }

    // '2026-09-16 14:37:02' → 今天显示 14:37，其它显示 09-16
    function shortTime(s) {
        s = String(s || '')
        if (s.length < 16) return ''
        var d = new Date()
        var today = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2)
        return s.slice(0, 10) === today ? s.slice(11, 16) : s.slice(5, 10)
    }

    // 列表就绪后选中会话（优先 URL ?id=，否则列表第一项）
    function startConversation() {
        var $cur = $('#message-list').find('a.chat-obj[data-id="' + activeConvId + '"]').first()
        if (!$cur.length) $cur = $('#message-list').find('a.chat-obj').first()
        var cid = String($cur.data('id') || '')
        if (cid !== '') { selectConversation(cid); return }
        // 列表里没有任何会话（直接打开 Chat.html）也要加载历史，否则刷新后看不到记录
        loadChatHistory()
    }

    // 切换会话：高亮 + 右侧资料 + 历史消息 + URL 同步
    function selectConversation(convId) {
        convId = String(convId || '')
        if (convId === '') return
        activeConvId = convId
        $('#message-list').find('a.chat-obj').each(function() {
            $(this).removeClass('px-[6px] bg-[#303030] border border-zinc-600').removeClass('active')
        })
        var $cur = $('#message-list').find('a.chat-obj[data-id="' + convId + '"]').first()
        if ($cur.length) $cur.addClass('px-[6px] bg-[#303030] border border-zinc-600').addClass('active')
        try { history.replaceState(null, '', './Chat.html?id=' + encodeURIComponent(convId)) } catch (e) {}
        getDetail(chatItemById(convId))
        loadChatHistory()
    }

})()