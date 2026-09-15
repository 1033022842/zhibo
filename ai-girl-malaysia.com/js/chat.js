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
        // 登录态/跳登录页/通话的初始化必须最先做：下面 trigger('click') 万一抛错也不会把它跳过
        setupAuthFlow()
        setupCall()
        channelDetail(id)
        $('#message-list').find('a').on('click', function(){
            $('#message-list').find('a').each(function() {
                $(this).removeClass('px-[6px] bg-[#303030] border border-zinc-600');
              });
            $(this).addClass('px-[6px] bg-[#303030] border border-zinc-600')
            getDetail(chatItemById($(this).data('id')))
            loadChatHistory()
        })
        try {
            $('#message-list').find('a').eq(0).trigger('click')
        } catch (e) {
            console.warn('初始化会话失败', e)
            loadChatHistory()
        }
    }, 1000);

    // 会话项 data-id -> pageList 里的角色对象（原来直接传 id 字符串会让 getDetail 报错）
    function chatItemById(id) {
        if (id && typeof id === 'object') return id
        var list = []
        try { list = JSON.parse(localStorage.getItem('pageList') || '[]') || [] } catch (e) { list = [] }
        for (var i = 0; i < list.length; i++) {
            if (String(list[i] && list[i].id) === String(id)) return list[i]
        }
        return null
    }

    /* ---------- 已登录：不弹登录框，发送走站内 AI 接口 ---------- */
    var sending = false

    function hideAuthModals() {
        $('#registrationModal, [data-main-target="registrationModal"], [data-main-target="signInModal"]').css('display', 'none')
        $('#sign-in-modal').css('display', 'none')
    }

    function currentConversation() {
        var a = $('#message-list .chat-obj').first()
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
        return { name: conv.name, desc: '' }
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

    function appendBubble(text, mine) {
        var box = messagesBox()
        if (!box) return null
        var row = document.createElement('div')
        row.className = 'user-response px-4 py-2'
        row.innerHTML = '<div class="flex ' + (mine ? 'justify-end' : 'justify-start') + '">' +
            '<div class="flex-col gap-[9px] inline-flex ' + (mine ? 'ml-auto items-end' : '') + '">' +
            '<div class="js-bubble text-white text-sm font-normal" style="white-space:pre-wrap;' +
            (mine ? 'background:#303030;padding:10px 12px;border-radius:10px;max-width:80%'
                  : 'background:#D98491;padding:12px;border-radius:10px 10px 10px 0') + '"></div>' +
            '<div class="text-neutral-500 text-[13px] font-normal">' + nowTime() + '</div>' +
            '</div></div>'
        row.querySelector('.js-bubble').textContent = text
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

    function uiLang() {
        return (document.documentElement.getAttribute('lang') || 'en').slice(0, 2)
    }

    // 当前会话对应的平台内容 id（首页/自建角色没有平台 id，返回 0）
    function convContentId() {
        var id = String(currentConversation().id || '')
        return /^\d+$/.test(id) ? id : '0'
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

    // 载入该角色的历史聊天（登录按用户，游客按 device_id）
    function loadChatHistory() {
        chatHis = []
        var box = messagesBox()
        if (box) box.innerHTML = ''
        var cid = convContentId()
        fetch('/api/live/chatHistory?content_id=' + cid + '&device_id=' + encodeURIComponent(deviceId()) + '&limit=50', {
            headers: authHeaders(false)
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var list = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
                for (var i = 0; i < list.length; i++) {
                    var m = list[i]
                    if (!m) continue
                    var mine = m.role !== 'assistant'
                    if (m.media && (m.media.url || m.media.unlocked === false)) {
                        paintMedia(mediaRow(m.media, mine), m.media, mine)
                    } else if (m.content) {
                        appendBubble(m.content, mine)
                        chatHis.push({ role: m.role, content: m.content })
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
        var text = (input.value || '').trim()
        if (text === '' || sending) return
        sending = true
        input.value = ''

        appendBubble(text, true)
        var typing = appendTyping()

        var cid = convContentId()
        var persona = currentPersona()
        var body = {
            message: text,
            content_id: cid,
            device_id: deviceId(),
            lang: uiLang(),
            history: chatHis.slice(-12)
        }
        // 首页等非平台角色：把人设一起带给接口
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
    var callTimer = null
    var callSec = 0

    var CALL_MIC_ON = '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3zm5-3h2a7 7 0 0 1-6 6.92V21h-2v-3.08A7 7 0 0 1 5 11h2a5 5 0 0 0 10 0z"/></svg>'
    var CALL_MIC_OFF = '<svg viewBox="0 0 24 24" width="18" height="18" fill="#fff"><path d="M12 14a3 3 0 0 0 3-3V6a3 3 0 0 0-6 0v5a3 3 0 0 0 3 3zm5-3h2a7 7 0 0 1-6 6.92V21h-2v-3.08A7 7 0 0 1 5 11h2a5 5 0 0 0 10 0z"/><path d="M3 3l18 18" stroke="#fff" stroke-width="2" fill="none"/></svg>'

    function callModal() {
        return document.getElementById('phoneCallModal')
    }

    function callPart(name) {
        var m = callModal()
        return m ? m.querySelector('[data-phone-call-target="' + name + '"]') : null
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

    function callLog(who, text) {
        var box = callPart('transcribedText')
        if (!box) return
        var line = document.createElement('div')
        line.style.cssText = 'margin:2px 0;line-height:1.35;color:' + (who === 'me' ? '#fff' : '#F97187')
        line.textContent = (who === 'me' ? 'You: ' : callRoleName() + ': ') + text
        box.appendChild(line)
        box.scrollTop = box.scrollHeight
    }

    function callSetStatus(state) {
        var r = callPart('ringingText')
        var l = callPart('listeningText')
        if (r) r.style.display = state === 'ringing' ? '' : 'none'
        if (l) l.style.display = state === 'listening' ? 'flex' : 'none'
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
            var box = callPart('transcribedText')
            if (box && !box.getAttribute('data-no-sr')) {
                box.setAttribute('data-no-sr', '1')
                var tip = document.createElement('div')
                tip.style.cssText = 'margin:2px 0;color:#A1A1A1'
                tip.textContent = 'This browser has no voice input — you can keep typing in the message box.'
                box.appendChild(tip)
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

    // 通话里说的话同样走 /api/live/chat（带 voice:1），回复用语音念出来
    function callSend(text) {
        callLog('me', text)
        var cid = convContentId()
        var persona = currentPersona()
        var body = {
            message: text,
            content_id: cid,
            device_id: deviceId(),
            lang: uiLang(),
            history: chatHis.slice(-12),
            voice: 1
        }
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
                    callLog('ai', reply)
                    appendBubble(reply, false)
                    callSpeak(reply)
                } else {
                    callSpeak("I'm listening, go on.")
                }
            })
            .catch(function () { callSpeak('Bad signal, say that again?') })
    }

    function callMicBtn() {
        return document.getElementById('js-call-mic')
    }

    // 静音键（参考页有，candy 弹窗里没有，按需加一个）
    function addCallMuteBtn() {
        var modal = callModal()
        if (!modal || callMicBtn()) return
        var hang = modal.querySelector('[data-action*="phone-call#endPhoneCall"]')
        if (!hang || !hang.parentNode) return
        var btn = document.createElement('div')
        btn.id = 'js-call-mic'
        btn.title = 'Mute'
        btn.style.cssText = 'position:absolute;bottom:80px;left:calc(50% - 70px);width:40px;height:40px;border-radius:999px;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;cursor:pointer'
        btn.innerHTML = CALL_MIC_ON
        btn.addEventListener('click', function (e) {
            e.preventDefault()
            e.stopPropagation()
            callMuted = !callMuted
            btn.innerHTML = callMuted ? CALL_MIC_OFF : CALL_MIC_ON
            btn.style.background = callMuted ? 'rgba(231,82,117,.9)' : 'rgba(255,255,255,.18)'
            if (callMuted) callStopListening()
            else if (!callSpeaking) callStartListening()
        })
        hang.parentNode.insertBefore(btn, hang)
    }

    function openCall() {
        if (!token) { goLoginPage(); return }
        var modal = callModal()
        if (!modal) return
        var name = callRoleName()
        var nameEl = modal.querySelector('.top-div span')
        if (nameEl) nameEl.textContent = name
        var img = $('#message-list .chat-obj').first().find('img').attr('src')
        var card = modal.querySelector('.relative.flex.flex-col')
        if (card && img) card.style.backgroundImage = "url('" + img + "')"
        var box = callPart('transcribedText')
        if (box) box.innerHTML = ''
        var timer = callPart('timer')
        if (timer) timer.textContent = ''
        callSec = 0
        callMuted = false
        callSpeaking = false
        callActive = true
        modal.style.display = 'flex'
        callSetStatus('ringing')
        addCallMuteBtn()
        var ring = callPart('ringingSound')
        if (ring) { try { ring.currentTime = 0; ring.play().catch(function () {}) } catch (e) {} }

        setTimeout(function () {
            if (!callActive) return
            if (ring) { try { ring.pause() } catch (e) {} }
            callSetStatus('listening')
            if (callTimer) clearInterval(callTimer)
            callTimer = setInterval(function () {
                callSec++
                var t = callPart('timer')
                if (t) t.textContent = ('0' + Math.floor(callSec / 60)).slice(-2) + ':' + ('0' + (callSec % 60)).slice(-2)
            }, 1000)
            var greet = 'Hi, this is ' + name + '. So glad you called!'
            callLog('ai', greet)
            callSpeak(greet)
        }, 1200)
    }

    function closeCall() {
        callActive = false
        callSpeaking = false
        callStopListening()
        if ('speechSynthesis' in window && window.speechSynthesis) window.speechSynthesis.cancel()
        var ring = callPart('ringingSound')
        if (ring) { try { ring.pause() } catch (e) {} }
        if (callTimer) { clearInterval(callTimer); callTimer = null }
        var modal = callModal()
        if (modal) modal.style.display = 'none'
        callSetStatus('ringing')
    }

    function setupCall() {
        // 通话按钮：改用站内浏览器语音通话（不再走 candy 的 phone-call 控制器）
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest
                ? e.target.closest('#phone-btn, #call-btn, [data-phone-call-target="callButton"]')
                : null
            if (!el) return
            e.preventDefault()
            e.stopPropagation()
            openCall()
        }, true)
        // 挂断
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest
                ? e.target.closest('#phoneCallModal [data-action*="phone-call#endPhoneCall"]')
                : null
            if (!el) return
            e.preventDefault()
            e.stopPropagation()
            closeCall()
        }, true)
        if ('speechSynthesis' in window && window.speechSynthesis) {
            window.speechSynthesis.onvoiceschanged = function () { window.speechSynthesis.getVoices() }
            window.speechSynthesis.getVoices()
        }
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
          $.each(data, function(index, item) {
            var template = $('#user-template').html();
    
            var rendered = template.replace("{{name}}", item.title)
            .replace("{{imgUrl}}", item.image.split(',')[0])
            .replace("{{message}}", item.description)
            .replace("{{time}}", item.time)
            .replace("{{id}}", item.id);
    
            $('#message-list').append(rendered);
            getDetail(item)
          });
    }
    function getDetail(info) {
        if(!info) return
        var pictures =  info.image.split(',')
        $('#chat-name').html(info.title)
        $('#infomation').html(info.description)
        $('#occupation').html(info.occupation)
        $('#hobbies').html(info.hobbies)
        $('#relationship').html(info.relationship)
        $('#body').html(info.body)
        $('#age').html(info.age)
        $('#chat-img').attr('src', pictures[0])
        $('#ethnicity').html(info.ethnicity)
        $('#name').html(info.title)
       
        if(pictures && pictures.length > 0) {
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
        var list = JSON.parse(localStorage.getItem('pageList'))
        console.log(list)
        console.log(id)
        //localStorage.removeItem('pageList')
        var data = list.filter(function(t){
          return t.id == id
        })
        console.log(data)
        getInfo(data)
      }
    
})()