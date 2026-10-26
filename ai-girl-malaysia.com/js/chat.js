(()=>{
    var base = ''
       // 缓存对话模板
       var cacheTemplate = $('#messages-list').html()
       var token = localStorage.getItem('live_access_token')
    // 聊天/通话消费价格（后端平台可配），进页面时拉一次
    var consumeOptions = { chat_overage_price: 0, call_price_per_minute: 0 }
    // 好感度奖励清单缓存键（character:affection），避免每发一条消息都重复拉取
    var rewardsKey = ''
    function loadConsumeOptions() {
        fetch('/api/live/consumeOptions')
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === '00000' && d.data) {
                    consumeOptions.chat_overage_price = Number(d.data.chat_overage_price || 0)
                    consumeOptions.call_price_per_minute = Number(d.data.call_price_per_minute || 0)
                    renderSpendHint()
                }
            })
            .catch(function () {})
    }
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
    // 初始化入口：首次加载 + Turbo 导航返回（快照恢复不重跑脚本，必须手动重建聊天区）
    var chatBooted = false
    var lastBootAt = 0
    // 页面错误收集（只读诊断用：浏览器控制台里 window.__chatErrors 可查）
    window.addEventListener('error', function (ev) {
        try {
            window.__chatErrors = window.__chatErrors || []
            window.__chatErrors.push(String(ev.message) + ' @ ' + String(ev.filename || '').split('/').pop() + ':' + ev.lineno)
        } catch (e) {}
    })
    window.addEventListener('unhandledrejection', function (ev) {
        try {
            window.__chatErrors = window.__chatErrors || []
            var r = ev && ev.reason
            window.__chatErrors.push('promise: ' + String((r && r.message) || r))
        } catch (e) {}
    })
    function bootChat(force) {
        var now = Date.now()
        if (!force && now - lastBootAt < 2500) return
        lastBootAt = now
        if (token) {
            $('#sign-in-modal').css('display', 'none')
            $('#user-setting').css('display', 'flex')
            $('#user-login').css('display', 'none')
        }
        preloadRoleList()
        var id = getParam('id')
        if (!chatBooted) {
            // 登录态/跳登录页/通话的初始化必须最先做且只绑一次：下面会话初始化万一抛错也不会把它跳过
            chatBooted = true
            setupAuthFlow()
            setupCall()
            setupLogout()
            loadConsumeOptions()
            loadTokens()
            // 会话列表是异步渲染的，点击必须用事件委托，否则后插入的条目不会带上事件
            $(document).on('click', '#message-list a.chat-obj', function(){
                selectConversation(String($(this).data('id') || ''))
            })
            // 重启会话按钮（原模板内联 confirm 已摘除，走美化确认框）
            setupRebootDialog()
            avatarFallback()
        }
        // 场景按钮（candy 新版 Show me the scene）每次进入重绑（Turbo 快照恢复丢监听）
        setupSceneButtons()
        // 首页角色目录预热：home-N 人设不再依赖 localStorage（被清/被替换会导致串号或空白）
        loadHomeRoles(function () {
            var info = chatItemById(activeConvId)
            if (info && info.title && String(activeConvId).indexOf('home-') === 0) {
                convItems[activeConvId] = info
                // 只补人设，不动消息区（否则会把已显示的开场问候清掉）
                var cur = document.getElementById('chat-name')
                if (!cur || cur.textContent !== info.title) paintPersona(info)
            }
        })
        // PC 端资料面板：默认展开（右侧人设区），并按用户上次的手动折叠选择恢复
        // （之前不持久化 + Turbo 快照恢复，会把折叠态带进下一次进入，表现为「右侧那块区域没了」）
        initProfilePanel()
        // 会话/历史/开场白每次进入都要重建（Turbo 恢复的快照是旧 DOM）
        channelDetail(id)
    }
    // chat.js 是 body 中段的同步脚本：必须等 DOM 就绪再初始化（否则 document.body 为 null 直接崩）
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { bootChat(true) })
    } else {
        bootChat(true)
    }
    document.addEventListener('turbo:load', function () {
        if (/Chat\.html/i.test(location.pathname || '')) bootChat(true)
    })

    /* ---------- 重启会话：美化确认框替代原生 confirm ---------- */
    function setupRebootDialog() {
        // 必须绑在 #message-list 上：比 document 上的 a.chat-obj 选择会话处理器更近一层，
        // stopPropagation 才能阻止冒泡触发切换会话（原模板内联 onclick 的 stopPropagation 已移除）
        $('#message-list').off('click', '[data-reboot-conv]').on('click', '[data-reboot-conv]', function (e) {
            e.preventDefault()
            e.stopPropagation()
            var a = $(this).closest('a.chat-obj')
            var name = a.length ? (a.find('.chat-name').first().text() || a.find('div.text-white').first().text() || '').trim() : ''
            var msg = name
                ? 'Are you sure you want to reboot the conversation with ' + name + '? All messages will be lost.'
                : 'Are you sure you want to reboot this conversation? All messages will be lost.'
            function doReload() { location.reload() }
            if (window.SugusDialog) {
                SugusDialog.confirm({
                    title: 'Reboot conversation',
                    message: msg,
                    confirmText: 'Reboot',
                    cancelText: 'Cancel',
                    icon: 'refresh'
                }).then(function (ok) { if (ok) doReload() })
            } else if (window.confirm(msg)) {
                doReload()
            }
        })
    }

    // 会话列表(id -> 角色对象)，点击时用它取右侧资料
    var convItems = {}

    // 本地会话列表（首页/Explore 点进角色时写入）
    // 素材已迁到独立存储：接口下发的是带时效的签名地址（如 .../sec/...?e=&s=），
    // 这里缓存的绝对地址会随签名过期 / 旧版裸路径失效，读取时统一清掉，触发重新拉取。
    var PAGE_LIST_TTL = 30 * 60 * 1000   // 缓存 30 分钟，保证签名始终有效

    function signedUrlAlive(url) {
        if (!url) return true
        var s = String(url)
        // 未签名的 /storage/ 裸路径（含旧数据里的本站绝对地址）：主站已禁止直读，一律视为过期
        if (/^\/(?:public\/)?storage\//.test(s) || /^https?:\/\/[^/]+\/(?:public\/)?storage\//.test(s)) return false
        var m = /[?&]e=(\d+)/.exec(s)
        if (m) return Number(m[1]) * 1000 > Date.now() + 60 * 1000
        return true
    }

    function convAlive(item) {
        if (!item || typeof item !== 'object') return false
        var urls = [item.cover_url, item.video_url, item.image]
        for (var i = 0; i < urls.length; i++) {
            if (urls[i] && !signedUrlAlive(urls[i])) return false
        }
        return true
    }

    function readPageList() {
        var list = []
        try { list = JSON.parse(localStorage.getItem('pageList') || '[]') || [] } catch (e) { list = [] }
        if (!Array.isArray(list)) return []
        var alive = list.filter(convAlive)
        if (alive.length !== list.length) {
            // 有失效条目：写回干净列表，并让上层重新拉取互补数据
            try { localStorage.setItem('pageList', JSON.stringify(alive)) } catch (e) {}
            try { localStorage.removeItem('pageList_saved_at') } catch (e) {}
        }
        return alive
    }

    // 缓存是否新鲜（过期则强制重新拉取，避免签名失效后图片裂开）
    function pageListFresh() {
        try {
            var t = Number(localStorage.getItem('pageList_saved_at') || 0)
            if (!t) return false
            return Date.now() - t < PAGE_LIST_TTL
        } catch (e) { return false }
    }

    // 会话条目默认结构，保证 getDetail 不会因为缺字段报错
    function emptyConv(id, title) {
        return {
            id: String(id || ''), title: title || String(id || ''), image: '', description: '', time: '',
            occupation: '-', hobbies: '-', relationship: '-', body: '-', age: '-', ethnicity: '-', messageList: []
        }
    }

    // 会话项 data-id -> 角色对象（原来直接传 id 字符串会让 getDetail 报错）
    // 首页角色服务端目录（lp_home_character）：home-N 的权威人设来源，免疫 localStorage 被清/被替换
    var homeRolesLoading = false
    var homeRolesCbs = []
    function homeRoleMap() {
        return window.__homeRoleMap || (window.__homeRoleMap = {})
    }
    function loadHomeRoles(cb) {
        if (cb) homeRolesCbs.push(cb)
        if (homeRolesLoading) return
        if (Object.keys(homeRoleMap()).length > 0) { flushHomeRoles(); return }
        homeRolesLoading = true
        var cats = ['girls', 'anime', 'guys']
        var done = 0
        var map = homeRoleMap()
        cats.forEach(function (cat) {
            fetch('/api/v1/home/characters?limit=50&category=' + cat)
                .then(function (r) { return r.json() })
                .then(function (d) {
                    var list = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
                    list.forEach(function (c) { if (c && c.id) map['home-' + c.id] = c })
                    if (++done === cats.length) { homeRolesLoading = false; flushHomeRoles() }
                })
                .catch(function () { if (++done === cats.length) { homeRolesLoading = false; flushHomeRoles() } })
        })
    }
    function flushHomeRoles() {
        var cbs = homeRolesCbs; homeRolesCbs = []
        cbs.forEach(function (cb) { try { cb() } catch (e) {} })
    }

    function chatItemById(id) {
        if (id && typeof id === 'object') return id
        id = String(id || '')
        if (id === '') return null
        // home-N 优先走服务端首页角色目录：convItems/pageList 里可能存着早前数字撞号写入的错误条目
        if (/^home-\d+$/.test(id)) {
            var hr0 = homeRoleMap()[id]
            if (hr0) {
                var it0 = {
                    id: id,
                    title: hr0.name || '',
                    image: hr0.cover_url || '',
                    description: hr0.description || hr0.tagline || '',
                    time: '',
                    age: hr0.age || '',
                    hobbies: (hr0.tags || []).join(', '),
                    occupation: '', relationship: '', body: '', ethnicity: '',
                    messageList: []
                }
                convItems[id] = it0
                return it0
            }
        }
        if (convItems[id]) return convItems[id]
        var list = readPageList()
        // 1) 先按完整 id 精确匹配（Home 页写入的条目 id 就是 'home-14' 这种完整键）
        for (var i = 0; i < list.length; i++) {
            if (String(list[i] && list[i].id) === id) return list[i]
        }
        // 2) home-N 没精确命中：回退「首页角色服务端目录」（lp_home_character，按 id 命中）。
        //    绝不能拿数字 N 去 pageList 里撞——数字 id 是平台内容角色（lp_ai_content）的命名空间，
        //    两套 id 大量同号（如 home-54=Alice Bloom vs 内容表 54=Grace），撞上就会显示成别人的角色
        if (/^home-\d+$/.test(id)) {
            var hr = homeRoleMap()[id]
            if (hr) {
                return {
                    id: id,
                    title: hr.name || '',
                    image: hr.cover_url || '',
                    description: hr.description || hr.tagline || '',
                    time: '',
                    age: hr.age || '',
                    hobbies: (hr.tags || []).join(', '),
                    occupation: '', relationship: '', body: '', ethnicity: '',
                    messageList: []
                }
            }
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
        fetch('/api/live/customOneList', { method: 'POST', headers: { 'Authorization': 'Bearer ' + token }, cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (!d || d.code !== '00000' || !Array.isArray(d.data)) {
                    appendError(safeServerMsg(d && d.msg, 'Failed to load character'))
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
                  : 'background:#D98491;padding:12px;border-radius:10px') + '"></div>' +
            '<div class="text-neutral-500 text-[13px] font-normal">' + nowTime() + '</div>' +
            '</div></div>'
        var bubble = row.querySelector('.js-bubble')
        if (tag) bubble.innerHTML = tag
        bubble.appendChild(document.createTextNode(text))
        box.appendChild(row)
        scrollMessagesToBottom()
        if (callActive) callScreenCaption(text, mine)
        return row
    }

    function appendTyping() {
        var box = messagesBox()
        if (!box) return null
        var row = document.createElement('div')
        row.className = 'user-response px-4 py-2'
        row.innerHTML = '<div class="flex justify-start"><div class="px-8 py-3 inline-flex" ' +
            'style="background:#D98491;border-radius:10px">' +
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

    // 聊天链路的对外提示：服务端技术错误（cURL / HTTP / 网关报文）只写日志，
    // 页面按错误码给友好文案，避免把接口地址、超时详情暴露给用户
    function friendlyChatError(d) {
        var code = d && d.code
        if (code === 'H0001') return 'The AI is a little busy right now. Please try again in a moment.'
        if (code === 'C0100') return 'Not enough tokens to continue.'
        if (code === 'G0100') return 'Daily free messages are used up.'
        if (code === 'A0001') return 'That message could not be sent. Please check it and try again.'
        return 'Something went wrong. Please try again in a moment.'
    }

    // 服务端业务提示里可能夹带接口地址 / cURL / HTTP 细节，这类内容一律换成兜底文案
    function safeServerMsg(msg, fallback) {
        var s = String(msg || '').trim()
        if (!s) return fallback
        if (/curl|https?:\/\/|api\.|timed out|timeout|ssl|tls|socket|connection|HTTP\s*\d{3}|exception|trace/i.test(s)) {
            return fallback
        }
        return s
    }

    /* 免费额度相关提示：用尽时给升级入口，快用完时给剩余条数 */
    function appendQuotaHint(msg, withUpgrade) {
        var box = messagesBox()
        if (!box) return
        var row = document.createElement('div')
        row.className = 'px-4 py-2'
        row.innerHTML = withUpgrade
            ? '<div style="display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:12px;' +
                'background:linear-gradient(135deg,rgba(231,82,117,.18),rgba(97,67,230,.18));border:1px solid rgba(231,82,117,.35)">' +
                '<div style="flex:1;font-size:12px;line-height:18px;color:#fff">' + msg + '</div>' +
                '<a href="./Subscriptions.html" style="flex:0 0 auto;padding:8px 14px;border-radius:10px;' +
                'background:linear-gradient(135deg,#E75275,#c73b5c);color:#fff;font-size:12px;font-weight:700;text-decoration:none">Upgrade</a>' +
              '</div>'
            : '<div style="font-size:11px;color:#a1a1aa">' + msg + '</div>'
        box.appendChild(row)
        scrollMessagesToBottom()
    }

    /* 免费额度用尽：给「用代币继续聊」+「升级会员」两个入口（text 为本轮被拦下的消息，点击后原样重发） */
    function appendOverageHint(text) {
        var box = messagesBox()
        if (!box) return
        var price = Number(consumeOptions.chat_overage_price || 0)
        if (!(price > 0)) { appendQuotaHint('Free messages for today are used up. Upgrade for unlimited chat.', true); return }
        var row = document.createElement('div')
        row.className = 'px-4 py-2'
        row.innerHTML = '<div style="display:flex;flex-direction:column;gap:10px;padding:10px 12px;border-radius:12px;' +
            'background:linear-gradient(135deg,rgba(231,82,117,.18),rgba(97,67,230,.18));border:1px solid rgba(231,82,117,.35)">' +
            '<div style="font-size:12px;line-height:18px;color:#fff">Daily free messages used up. Continue this chat for ' +
            '<b>' + price + '</b> tokens per message?</div>' +
            '<div style="display:flex;gap:8px">' +
            '<button type="button" class="js-pay-continue" style="flex:1;padding:8px 14px;border:0;border-radius:10px;' +
            'background:linear-gradient(135deg,#E75275,#c73b5c);color:#fff;font-size:12px;font-weight:700;cursor:pointer">Continue (' + price + ')</button>' +
            '<a href="./Subscriptions.html" style="flex:0 0 auto;padding:8px 14px;border-radius:10px;' +
            'background:#3a3a3a;color:#fff;font-size:12px;font-weight:700;text-decoration:none">Upgrade</a>' +
            '</div></div>'
        box.appendChild(row)
        scrollMessagesToBottom()
        var btn = row.querySelector('.js-pay-continue')
        if (btn) btn.addEventListener('click', function () {
            row.remove()
            // 上一条气泡已展示，重发时不再重复插入，仅带上 pay_tokens
            sendMessage({ text: text, skipBubble: true, payTokens: true })
        })
    }

    /* ---------- 聊天：历史 / 媒体消息卡片 / 好感度 / 特殊视频（参考 ai_web/chat.html） ---------- */
    var chatHis = []
    // 当前会话 id（左侧列表里被选中的那条，决定历史归档键）
    var activeConvId = ''
    // 是否已加载过一次历史（用于区分「首次加载」与「切换会话」）
    var historyLoadedOnce = false

    function uiLang() {
        // 聊天语言跟随用户在「Select your language」弹窗的选择（googtrans cookie，如 /en/fr → fr、/en/zh-CN → zh-cn）；
        // Google Translate 不会改 <html lang>，所以 cookie 优先，拿不到再退回 html lang
        try {
            var m = document.cookie.match(/(?:^|;\s*)googtrans=([^;]*)/)
            if (m) {
                var parts = decodeURIComponent(m[1]).split('/')
                var code = (parts[parts.length - 1] || '').toLowerCase()
                // 允许带地区码（zh-cn / zh-tw / pt-br），其余非法值退回 <html lang>
                if (/^[a-z]{2}(?:-[a-z]{2})?$/.test(code)) return code
            }
        } catch (e) { /* ignore */ }
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

    // 可消费内容 id（好感度购买/解锁用）：首页角色 home-N 与纯数字都映射到 lp_ai_content.id；
    // 自建角色 my-N 没有平台内容，返回 0。历史归档仍按 convContentId() 走，避免老记录串号。
    function spendContentId() {
        var id = String(currentConversation().id || '')
        if (id === '') id = String(getParam('id') || '')
        var m = /^home-(\d+)$/.exec(id)
        if (m) return m[1]
        return /^\d+$/.test(id) ? id : '0'
    }

    function authHeaders(json) {
        var h = {}
        if (json) h['Content-Type'] = 'application/json'
        if (token) h.Authorization = 'Bearer ' + token
        return h
    }

    function toastMsg(msg) {
        var el = document.createElement('div')
        el.textContent = msg
        el.style.cssText = 'position:fixed;left:50%;bottom:120px;transform:translateX(-50%);background:rgba(20,20,20,.95);color:#fff;font-size:13px;padding:10px 18px;border-radius:10px;z-index:9999;box-shadow:0 6px 20px rgba(0,0,0,.4)'
        document.body.appendChild(el)
        setTimeout(function () { el.remove() }, 2600)
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
                '<svg class="js-ic-pause" viewBox="0 0 24 24" width="22" height="22" fill="#fff" style="display:none"><path d="M6 5h4v14H6zM14 5h4v14H6z" xmlns="http://www.w3.org/2000/svg"/></svg>' +
                '</button>' +
                '<div style="display:flex;align-items:flex-end;height:28px">' + voiceBarsHtml(barColor) + '</div>' +
                '<span class="js-voice-dur" style="color:' + barColor + ';font-size:12px">0:00</span></div>'
            bindVoicePlayer(node, media.url)
        } else if (media.kind === 'chat_scene_image') {
            // 场景图片（点 Image 按钮发来的）：粉色气泡里一张图，点击放大
            node.innerHTML = '<div style="background:' + bg + ';border-radius:10px;padding:6px;max-width:280px">' +
                '<a href="' + esc(media.url) + '" target="_blank" rel="noopener"><img src="' + esc(media.url) + '" loading="lazy" alt="" style="display:block;width:260px;border-radius:8px;object-fit:cover"></a></div>'
        } else {
            // 聊天礼物：视频上方加礼物标签（历史与送礼后的实时渲染一致）
            var giftTag = media.kind === 'chat_gift'
                ? '<div style="color:' + (mine ? '#fbbf24' : '#fff') + ';font-size:12.5px;font-weight:700;margin-bottom:6px">🎁 Gift · ' + esc(media.title || '') + '</div>'
                : ''
            node.innerHTML = '<div style="max-width:260px">' + giftTag + '<video controls playsinline preload="metadata" src="' +
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
        // 付费内容先弹美化确认框，确认后再发起解锁请求
        if (price > 0 && window.SugusDialog) {
            SugusDialog.confirm({
                title: 'Unlock message',
                message: 'Unlock this message for ' + price + ' tokens?',
                confirmText: 'Unlock (' + price + ')',
                cancelText: 'Cancel',
                icon: 'lock'
            }).then(function (ok) { if (ok) requestUnlockChatMedia(media, node, mine) })
            return
        }
        if (price > 0 && !window.confirm('Unlock this message for ' + price + ' tokens?')) return
        requestUnlockChatMedia(media, node, mine)
    }

    function requestUnlockChatMedia(media, node, mine) {
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
                    appendError('Unlock failed: ' + safeServerMsg(d && d.msg, 'please try again later'))
                }
            })
            .catch(function () { appendError('Network error, please try again later') })
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

    /* ---------- 右侧资料面板「消费区」：好感度购买 + 代币余额（任何角色都能看到消费入口） ---------- */
    function ensureSpendBox() {
        var box = document.getElementById('js-spend')
        if (box) return box
        var info = document.getElementById('infomation')
        if (!info || !info.parentNode) return null
        box = document.createElement('div')
        box.id = 'js-spend'
        box.style.cssText = 'width:100%;margin-top:12px'
        box.innerHTML =
            '<div class="js-sp-aff" style="display:none">' +
                '<div style="display:flex;justify-content:space-between;font-size:12px;color:#A1A1A1;margin-bottom:6px">' +
                '<span>Affection</span><span class="js-sp-aff-val"></span></div>' +
                '<div style="height:6px;border-radius:999px;background:#303030;overflow:hidden">' +
                '<div class="js-sp-aff-fill" style="height:100%;width:0;background:linear-gradient(90deg,#E75275,#F97187);border-radius:999px;transition:width .4s"></div></div>' +
                '<button type="button" class="js-sp-aff-buy" style="margin-top:10px;width:100%;height:34px;border:0;border-radius:9px;' +
                'background:linear-gradient(135deg,#E75275,#c73b5c);color:#fff;font-size:13px;font-weight:700;cursor:pointer">Buy Affection (1 token / point)</button>' +
                '<div class="js-sp-aff-panel" style="display:none;gap:8px;margin-top:8px">' +
                '<input type="number" class="js-sp-amt" min="1" step="1" value="10" style="flex:1;min-width:0;height:34px;padding:0 10px;' +
                'border-radius:9px;border:1px solid #434343;background:#262626;color:#fff;font-size:13px">' +
                '<button type="button" class="js-sp-aff-confirm" style="height:34px;padding:0 14px;border:0;border-radius:9px;' +
                'background:#E75275;color:#fff;font-size:13px;font-weight:700;cursor:pointer">Buy</button>' +
                '</div>' +
                '<div class="js-sp-aff-hint" style="margin-top:8px;font-size:11px;color:#8b8b92;line-height:1.5"></div>' +
                '<div class="js-sp-rewards" style="display:none;flex-direction:column;gap:6px;margin-top:8px"></div>' +
            '</div>' +
            '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-top:12px;padding:10px 12px;' +
            'border-radius:10px;background:#262626;border:1px solid #3a3a3a">' +
            '<div style="line-height:1.25"><div style="font-size:11px;color:#A1A1A1">Tokens</div>' +
            '<div class="js-sp-bal" style="font-size:16px;font-weight:700;color:#fff">-</div></div>' +
            '<a href="./tokens.html" style="padding:8px 12px;border-radius:9px;background:linear-gradient(135deg,#E75275,#c73b5c);' +
            'color:#fff;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap">Get Tokens</a>' +
            '</div>' +
            '<div class="js-sp-hint" style="margin-top:6px;font-size:11px;color:#8b8b92;line-height:1.5"></div>'
        info.parentNode.insertBefore(box, info.nextSibling)
        box.querySelector('.js-sp-aff-buy').addEventListener('click', function () {
            var panel = box.querySelector('.js-sp-aff-panel')
            panel.style.display = panel.style.display === 'flex' ? 'none' : 'flex'
        })
        box.querySelector('.js-sp-aff-confirm').addEventListener('click', function () {
            buyAffection(parseInt(box.querySelector('.js-sp-amt').value, 10))
        })
        renderSpendHint()
        return box
    }

    // 消费价格提示（通话/续聊单价，来自 /api/live/consumeOptions）
    function renderSpendHint() {
        var box = document.getElementById('js-spend')
        // 发送按钮上的价格标签：与后端配置（chat_overage_price）同步，不再写死
        var price = Number(consumeOptions.chat_overage_price || 0)
        if (price > 0) {
            try { localStorage.setItem('sugus.chat_price', String(price)) } catch (e) {}
            var priceTag = document.querySelector('#send-question-with-token .text-white')
            if (priceTag) priceTag.textContent = String(price)
        }
        if (!box) return
        var hint = []
        if (Number(consumeOptions.call_price_per_minute || 0) > 0) {
            hint.push('Voice call ' + consumeOptions.call_price_per_minute + ' tokens/min')
        }
        if (price > 0) {
            hint.push('Extra chat ' + price + ' token/message after daily free limit')
        }
        box.querySelector('.js-sp-hint').textContent = hint.join(' · ')
    }

    // 好感度进度 + Buy 按钮（平台内容才有；自建角色不展示这一块）
    function renderAffection(data) {
        var box = ensureSpendBox()
        if (!box || !data) return
        var max = Number(data.max || 100) || 100
        var val = Math.max(0, Math.min(max, Number(data.affection || 0)))
        box.querySelector('.js-sp-aff').style.display = 'block'
        box.querySelector('.js-sp-aff-val').textContent = val + ' / ' + max
        box.querySelector('.js-sp-aff-fill').style.width = Math.round(val / max * 100) + '%'
        box.querySelector('.js-sp-aff-buy').style.display = val >= max ? 'none' : 'block'
        if (val >= max) box.querySelector('.js-sp-aff-panel').style.display = 'none'
        // 满好感度解锁该角色的「专属视频」，拉一次清单（未满时用于提示还差多少）
        var cid = spendContentId()
        if (cid !== '0') {
            var key = cid + ':' + val
            if (key !== rewardsKey) {
                rewardsKey = key
                loadAffectionRewards(cid, val >= max)
            }
        }
    }

    // 好感度奖励：满 100 可观看该角色的专属视频（scene_type=affection）
    function loadAffectionRewards(cid, unlocked) {
        fetch('/api/live/roleMedia?content_id=' + cid + '&type=special', { headers: authHeaders(false), cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                var box = document.getElementById('js-spend')
                if (!box || !d || d.code !== '00000' || !Array.isArray(d.data)) return
                var all = d.data
                var playable = all.filter(function (m) { return m && m.video_url })
                var hint = box.querySelector('.js-sp-aff-hint')
                var wrap = box.querySelector('.js-sp-rewards')
                if (unlocked && playable.length) {
                    hint.innerHTML = '<span style="color:#7ee0a2;font-weight:600">🎉 Exclusive videos unlocked</span> · tap to watch'
                    wrap.style.display = 'flex'
                    wrap.innerHTML = playable.map(function (m) {
                        return '<button type="button" class="js-sp-play" data-url="' + esc(m.video_url) + '" style="display:flex;align-items:center;gap:8px;' +
                            'width:100%;padding:8px 10px;border:1px solid #434343;border-radius:9px;background:#262626;color:#fff;' +
                            'font-size:12px;font-weight:600;cursor:pointer;text-align:left">' +
                            '<span style="flex:0 0 auto;color:#E75275">▶</span><span style="flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' +
                            esc(m.title || 'Exclusive video') + '</span></button>'
                    }).join('')
                    var btns = wrap.querySelectorAll('.js-sp-play')
                    for (var i = 0; i < btns.length; i++) {
                        btns[i].addEventListener('click', function () {
                            playSpecialVideo(this.getAttribute('data-url'))
                        })
                    }
                } else {
                    wrap.style.display = 'none'
                    wrap.innerHTML = ''
                    hint.textContent = all.length
                        ? ('Chat daily (+1/day) or buy tokens · Reach 100 to unlock ' + all.length + ' exclusive video' + (all.length > 1 ? 's' : ''))
                        : 'Chat daily (+1/day) or buy tokens to raise her affection'
                }
            })
            .catch(function () {})
    }

    // 代币余额（任何角色都展示，作为消费/充值入口）
    function renderTokens(balance) {
        var box = ensureSpendBox()
        if (!box) return
        var n = Math.round(Number(balance || 0) * 100) / 100
        box.querySelector('.js-sp-bal').textContent = n.toLocaleString()
    }

    // 购买好感度（1 代币 = 1 点，后端扣费并给作者分成）
    function buyAffection(amount) {
        if (!token) { goLoginPage(); return }
        var cid = spendContentId()
        if (cid === '0') { appendError('Affection purchase is not available for this character.'); return }
        amount = parseInt(amount, 10)
        if (!(amount > 0)) { appendError('Please enter a valid amount.'); return }
        fetch('/api/live/buyAffection', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ content_id: cid, amount: amount })
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === '00000' && d.data) {
                    renderAffection({ affection: d.data.affection, max: 100 })
                    renderTokens(d.data.balance)
                    if (window.SugusDialog) {
                        SugusDialog.alert('Purchase successful. Balance: ' + d.data.balance + ' tokens.', { title: 'Affection', icon: 'info' })
                    }
                } else if (d && d.code === 'C0100') {
                    if (window.SugusDialog) SugusDialog.alert(safeServerMsg(d.msg, 'Insufficient token balance.'), { title: 'Not enough tokens', icon: 'warn' })
                    else appendError(safeServerMsg(d.msg, 'Insufficient token balance.'))
                } else {
                    appendError('Purchase failed: ' + safeServerMsg(d && d.msg, 'please try again later'))
                }
            })
            .catch(function () { appendError('Network error, please try again.') })
    }

    function loadAffection() {
        var box = document.getElementById('js-spend')
        var cid = spendContentId()
        // 非平台角色 / 未登录：没有好感度，隐藏好感度区（代币余额入口仍然保留）
        if (cid === '0' || !token) { if (box) box.querySelector('.js-sp-aff').style.display = 'none'; return }
        fetch('/api/live/affection?content_id=' + cid, { headers: authHeaders(false), cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) { if (d && d.code === '00000' && d.data) renderAffection(d.data) })
            .catch(function () {})
    }

    // 代币余额（消费入口展示）
    function loadTokens() {
        if (!token) return
        fetch('/api/live/userInfo', { headers: authHeaders(false), cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) { if (d && d.code === '00000' && d.data) renderTokens(d.data.money) })
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
            headers: authHeaders(false),
            cache: 'no-store'
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
                // 没有历史：清掉页面自带的空问候占位（无音频 play 按钮），换成按人设生成的火热开场白
                if (!rows.length) {
                    var emptyBox = messagesBox()
                    if (emptyBox) emptyBox.innerHTML = ''
                    historyLoadedOnce = true
                    showIceBreaker()
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
        // loadAffection() 由调用方与历史请求并行发起（串行会多等一个 RTT，切换会话明显变卡）
    }

    /* ---------- 开场问候：无历史时按人设生成火热开场白（本地模板轮换，替代页面自带的空音频占位） ---------- */
    var ICE_BREAKERS = [
        'Mmm, finally you\'re here… I was just thinking about you and getting a little impatient. How\'s my favorite person today? 💋',
        'Hey you… I\'ve been waiting all day for this. Come closer and tell me — what have you been doing without me? 😏',
        'There you are… I couldn\'t stop imagining you tonight. Stay a while and make it worth my while 🔥',
        'Hi baby… I\'m in a dangerously playful mood right now. Curious how naughty I can get? 😈',
        'Finally… don\'t make me wait like that again. I need your attention — all of it, right now 💦'
    ]

    function pickIceBreaker() {
        var conv = currentConversation()
        var seed = String(conv.id || '') + String(conv.title || '')
        var h = 0
        for (var i = 0; i < seed.length; i++) h = (h * 31 + seed.charCodeAt(i)) >>> 0
        return ICE_BREAKERS[h % ICE_BREAKERS.length]
    }

    function showIceBreaker() {
        var conv = chatItemById(activeConvId)
        try { (window.__chatTrace = window.__chatTrace || []).push('showIceBreaker id=' + String(activeConvId) + ' title=' + String(conv && conv.title)) } catch (e) {}
        // 没有真实角色（如 chat-default 空状态）不生成开场白
        if (!conv || !conv.title || conv.title === String(conv.id || '')) return
        // 幂等：按「当前消息容器」判断——同一个 DOM 容器只发起一次（bootChat 会跑多次）；
        // Turbo 导航换新 body 后容器是全新元素，天然放行（旧容器上的异步回调落到已丢弃的 DOM，无害）
        var dupBox = messagesBox()
        if (!dupBox) return
        if (window.__iceBox === dupBox) return
        window.__iceBox = dupBox
        // 后端按人设生成（角色维度缓存，同一角色固定一条）；超时/失败用本地模板兜底
        var typing = appendTyping()
        var done = false
        var cid = convContentId()
        var rkey = convRoleKey()
        var persona = currentPersona()
        var qs = '?content_id=' + encodeURIComponent(cid) +
            '&role_key=' + encodeURIComponent(rkey) +
            '&persona_name=' + encodeURIComponent(persona.name || '') +
            '&persona_desc=' + encodeURIComponent(String(persona.desc || '').slice(0, 600)) +
            '&lang=' + encodeURIComponent(uiLang())
        var finish = function (text) {
            if (done) return
            done = true
            if (typing) typing.remove()
            appendBubble(text, false)
        }
        var fallbackTimer = setTimeout(function () { finish(pickIceBreaker()) }, 8000)
        fetch('/api/live/iceBreaker' + qs, { headers: authHeaders(false), cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                clearTimeout(fallbackTimer)
                var t = (d && d.code === '00000' && d.data && d.data.text) ? String(d.data.text) : ''
                finish(t || pickIceBreaker())
            })
            .catch(function () {
                clearTimeout(fallbackTimer)
                finish(pickIceBreaker())
            })
    }

    /* ---------- Suggestion 建议语：点击直接作为消息发送（捕获阶段拦截，避免触发原站注册弹窗） ---------- */
    /* ---------- 场景媒体（candy 新版「Show me the scene」：点按钮发图/发视频） ---------- */
    var sceneMedia = { images: [], videos: [], loading: false, sending: false }

    // 场景媒体的角色键：home-N 原样、纯数字（= lp_ai_content.id）补成 home-N；自建/默认会话无媒体
    // 场景媒体的角色键：只有 home-N（首页角色）有素材目录；数字=平台内容角色、my-*=自建 → 无场景媒体
    // 注意：数字 id 与 lp_home_character.id 是两套空间，不能把数字补成 home-N（会串到别的角色的素材）
    function sceneRoleKey() {
        var id = String(currentConversation().id || '')
        if (id === '') id = String(getParam('id') || '')
        return /^home-\d+$/.test(id) ? id : ''
    }

    function refreshSceneRow() {
        // 按钮与输入框同一行：无素材的按钮直接隐藏；有图片但没视频（男角色）→ Video 显示但置灰
        var imgBtn = document.getElementById('scene-image-btn')
        var vidBtn = document.getElementById('scene-video-btn')
        var hasImg = sceneMedia.images.length > 0
        var hasVid = sceneMedia.videos.length > 0
        if (imgBtn) imgBtn.style.display = hasImg ? '' : 'none'
        if (vidBtn) {
            vidBtn.style.display = (hasVid || hasImg) ? '' : 'none'
            vidBtn.disabled = !hasVid   // 男角色等无视频 → 置灰
        }
        // 手机端输入区据此决定是否折成两行（有场景按钮才需要第二行）
        var formEl = document.getElementById('form')
        if (formEl) formEl.classList.toggle('fy-scene-on', hasImg || hasVid)
    }

    function loadSceneMedia() {
        refreshSceneRow()
        var rkey = sceneRoleKey()
        sceneMedia.roleKey = rkey
        if (rkey === '' || sceneMedia.loading) return
        sceneMedia.loading = true
        fetch('/api/live/chatMedia?role_key=' + encodeURIComponent(rkey), { headers: authHeaders(false), cache: 'no-store' })
            .then(function (r) { return r.json() })
            .then(function (d) {
                sceneMedia.loading = false
                var data = (d && d.code === '00000' && d.data) ? d.data : null
                sceneMedia.images = (data && data.images) || []
                sceneMedia.videos = (data && data.videos) || []
                refreshSceneRow()
            })
            .catch(function () { sceneMedia.loading = false })
    }

    function sendSceneMedia(type) {
        if (!token) { goLoginPage(); return }
        if (sceneMedia.sending) {  return }
        // 用加载素材时的角色键（与按钮可见性一致）；现场重算可能在会话切换后拿到空键
        var rkey = sceneMedia.roleKey || sceneRoleKey()
        
        if (rkey === '') {  return }
        var btn = document.getElementById(type === 'video' ? 'scene-video-btn' : 'scene-image-btn')
        if (btn) btn.classList.add('loading')
        sceneMedia.sending = true
        var typing = appendTyping()
        // 90s 超时：LLM 文案慢或网络悬挂时自动解除 sending，按钮不会永久失灵
        var abortCtl = (typeof AbortController !== 'undefined') ? new AbortController() : null
        var abortTimer = abortCtl ? setTimeout(function () { abortCtl.abort() }, 90000) : null
        fetch('/api/live/sendChatMedia', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ role_key: rkey, type: type, index: 0, device_id: deviceId(), lang: uiLang() }),
            signal: abortCtl ? abortCtl.signal : undefined
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                sceneMedia.sending = false
                if (abortTimer) clearTimeout(abortTimer)
                if (btn) btn.classList.remove('loading')
                if (typing) typing.remove()
                if (!d || d.code !== '00000' || !d.data) {
                    appendError(safeServerMsg(d && d.msg, 'Send failed, please try again later'))
                    return
                }
                // 素材消息（左侧角色侧）+ 人设化文案气泡
                if (d.data.media) paintMedia(mediaRow(d.data.media, false), d.data.media, false)
                if (d.data.caption) appendBubble(d.data.caption, false)
                scrollMessagesToBottom()
            })
            .catch(function () {
                sceneMedia.sending = false
                if (abortTimer) clearTimeout(abortTimer)
                if (btn) btn.classList.remove('loading')
                if (typing) typing.remove()
                appendError('Network error, please try again later')
            })
    }

    function initProfilePanel() {
        try {
            var pcEl = document.querySelector('.profile-container')
            var mcEl = document.querySelector('.messages-container')
            if (!pcEl || !mcEl) return
            var desktop = window.matchMedia('(min-width: 1024px)').matches
            if (!desktop) return
            var collapsed = '0'
            try { collapsed = localStorage.getItem('chatPanelCollapsed') || '0' } catch (e) {}
            if (collapsed === '1') {
                pcEl.classList.remove('lg:block')
                mcEl.classList.add('w-[70%]')
            } else {
                pcEl.classList.add('lg:block')
                mcEl.classList.remove('w-[70%]')
            }
        } catch (e) {}
    }

    // 记住用户对右侧面板的折叠选择（candy 的 profile-toggle 控制器点完后再读一次结果）
    if (!window.__chatPanelPrefBound) {
        window.__chatPanelPrefBound = true
        document.addEventListener('click', function (e) {
            var t = e.target && e.target.closest ? e.target.closest('[data-action*="profile-toggle#toggle"]') : null
            if (!t) return
            setTimeout(function () {
                try {
                    var pcEl = document.querySelector('.profile-container')
                    if (!pcEl) return
                    localStorage.setItem('chatPanelCollapsed', pcEl.className.indexOf('lg:block') < 0 ? '1' : '0')
                } catch (err) {}
            }, 350)
        })
    }

    function setupSceneButtons() {
        // 事件委托绑在 document 上：Turbo 快照恢复/局部重渲染不会丢监听
        if (!window.__sceneBtnDelegated) {
            window.__sceneBtnDelegated = true
            document.addEventListener('click', function (e) {
                var b = e.target && e.target.closest ? e.target.closest('#scene-image-btn, #scene-video-btn') : null
                if (!b || b.disabled) return
                sendSceneMedia(b.id === 'scene-video-btn' ? 'video' : 'image')
            })
        }
        var imgBtn = document.getElementById('scene-image-btn')
        var vidBtn = document.getElementById('scene-video-btn')
        if (imgBtn) imgBtn.addEventListener('click', function () { sendSceneMedia('image') })
        if (vidBtn) vidBtn.addEventListener('click', function () { sendSceneMedia('video') })
        refreshSceneRow()
    }

    /* ---------- 头像兜底：图片裂开时换成角色首字母渐变占位 ---------- */
    function avatarFallback() {
        // PC 与手机顶部头像是两个 <img>，都要挂兜底
        ['chat-img', 'chat-img-mobile'].forEach(function (id) {
            var img = document.getElementById(id)
            if (!img) return
            img.addEventListener('error', function () {
                var conv = chatItemById(activeConvId) || {}
                var name = String(conv.title || 'AI')
                var letter = (name.trim()[0] || 'A').toUpperCase()
                img.onerror = null
                img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
                    '<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">' +
                    '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
                    '<stop offset="0" stop-color="#fc768a"/><stop offset="1" stop-color="#e65073"/>' +
                    '</linearGradient></defs>' +
                    '<rect width="120" height="120" fill="url(#g)"/>' +
                    '<text x="60" y="76" font-family="Arial,sans-serif" font-size="52" font-weight="700" fill="#fff" text-anchor="middle">' + letter + '</text></svg>')
            })
        })
    }

    function sendMessage(opts) {
        opts = opts || {}
        var input = document.getElementById('message_body')
        var fromInput = (opts.text === undefined)
        if (!input && fromInput) return
        // 未登录不允许聊天（也不会产生记录）
        if (!token) { goLoginPage(); return }
        var text = fromInput ? (input.value || '').trim() : String(opts.text || '').trim()
        if (text === '' || sending) return
        sending = true
        if (fromInput) input.value = ''

        if (!opts.skipBubble) appendBubble(text, true)
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
        if (opts.payTokens) body.pay_tokens = 1
        if (cid === '0') {
            body.persona_name = persona.name || ''
            body.persona_desc = persona.desc || ''
        }

        fetch('/api/live/chat', { method: 'POST', headers: authHeaders(true), body: JSON.stringify(body) })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (typing) typing.remove()
                if (!d || d.code !== '00000' || !d.data) {
                    // 免费用户每日消息额度用尽：给「代币续聊」+「升级会员」入口
                    if (d && d.code === 'G0100') {
                        appendOverageHint(text)
                        return
                    }
                    appendError(friendlyChatError(d))
                    return
                }
                var data = d.data
                chatHis.push({ role: 'user', content: text })
                if (data.balance !== undefined) renderTokens(data.balance)
                if (data.reply) {
                    appendBubble(data.reply, false)
                    chatHis.push({ role: 'assistant', content: data.reply })
                }
                if (data.media) paintMedia(mediaRow(data.media, false), data.media, false)
                if (data.affection) renderAffection(data.affection)
                if (data.special_video) playSpecialVideo(data.special_video)
                if (!data.reply && !data.media) appendError('No reply received, please try again')
                // 免费用户：剩余条数不多时提醒一次（用代币续聊的这一条不再提示）
                if (!data.paid_overage && data.quota && data.quota.remaining >= 0 && data.quota.remaining <= 3) {
                    appendQuotaHint(data.quota.remaining > 0
                        ? ('Free messages left today: ' + data.quota.remaining)
                        : 'Free messages for today are used up. Upgrade for unlimited chat.', data.quota.remaining <= 0)
                }
                if (chatHis.length > 24) chatHis = chatHis.slice(-24)
            })
            .catch(function () {
                if (typing) typing.remove()
                appendError('Network error, please try again later')
            })
            .then(function () { sending = false })
    }

    /* ---------- AI 语音通话（参考 ai_web/chat.html：浏览器 TTS + STT） ---------- */
    var callActive = false
    var callMuted = false
    var callSpeaking = false
    var callRec = null
    var callBillTimer = null
    var callScreenEl = null
    var callTickTimer = null
    var callStartedAt = 0

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

    /* ---------- 手机端全屏通话界面 ----------
       站内语音通话原来只在聊天头部显示状态点和 Mute / Hang up 两个小胶囊，
       手机上会把头像、电话图标、汉堡菜单挤扁（320 宽时电话与菜单直接跑出屏幕）。
       <=1023px 改成独立全屏通话页：人像铺满 + 顶部姓名/状态/计时 + 底部实时字幕
       + 静音/挂断；桌面端仍沿用头部胶囊。 */
    function ensureCallScreenStyle() {
        if (document.getElementById('js-call-screen-style')) return
        var s = document.createElement('style')
        s.id = 'js-call-screen-style'
        s.textContent = [
            '#js-call-screen{position:fixed;inset:0;z-index:2000;display:none;flex-direction:column;justify-content:space-between;background:#0d0a0f;overflow:hidden}',
            '#js-call-screen.on{display:flex}',
            '@media (min-width:1024px){#js-call-screen.on{display:none}}',
            '#js-call-screen .jcs-bg{position:absolute;inset:0;background-color:#0d0a0f;background-size:cover;background-position:center top;background-repeat:no-repeat}',
            '#js-call-screen::before{content:"";position:absolute;left:0;right:0;top:0;height:30vh;z-index:1;pointer-events:none;background:linear-gradient(180deg,rgba(0,0,0,.72) 0%,rgba(0,0,0,.34) 55%,rgba(0,0,0,0) 100%)}',
            '#js-call-screen::after{content:"";position:absolute;left:0;right:0;bottom:0;height:46vh;z-index:1;pointer-events:none;background:linear-gradient(0deg,rgba(0,0,0,.9) 0%,rgba(0,0,0,.5) 45%,rgba(0,0,0,0) 100%)}',
            '#js-call-screen .jcs-top{position:relative;z-index:2;width:100%;padding:calc(env(safe-area-inset-top,0px) + 44px) 20px 0;text-align:center;color:#fff}',
            '#js-call-screen .jcs-name{font-size:30px;font-weight:700;line-height:1.2;max-width:86vw;margin:0 auto;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;text-shadow:0 2px 12px rgba(0,0,0,.55)}',
            '#js-call-screen .jcs-status{display:flex;align-items:center;justify-content:center;gap:6px;margin-top:6px;font-size:14px;color:rgba(255,255,255,.92);text-shadow:0 1px 8px rgba(0,0,0,.6)}',
            '#js-call-screen .jcs-dot{width:8px;height:8px;border-radius:50%;background:#E75275;animation:js-call-pulse 1.1s ease-in-out infinite}',
            '#js-call-screen .jcs-sub{margin-top:4px;font-size:13px;color:rgba(255,255,255,.72);font-variant-numeric:tabular-nums;text-shadow:0 1px 8px rgba(0,0,0,.6)}',
            '#js-call-screen .jcs-bottom{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;gap:16px;padding:0 16px calc(env(safe-area-inset-bottom,0px) + 26px)}',
            '#js-call-screen .jcs-cap{display:none;width:100%;max-width:460px;min-height:52px;max-height:24vh;overflow-y:auto;padding:12px 14px;border-radius:14px;background:rgba(18,18,18,.45);border:1px solid rgba(255,255,255,.12);color:#eee;font-size:14px;font-weight:500;line-height:1.5;text-shadow:0 1px 6px rgba(0,0,0,.5);-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px);scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.22) transparent}',
            '#js-call-screen .jcs-cap.on{display:block}',
            '#js-call-screen .jcs-cap::-webkit-scrollbar{width:4px}',
            '#js-call-screen .jcs-cap::-webkit-scrollbar-thumb{background:rgba(255,255,255,.22);border-radius:4px}',
            '#js-call-screen .jcs-cap::-webkit-scrollbar-track{background:transparent}',
            '#js-call-screen .jcs-actions{display:flex;align-items:center;justify-content:center;gap:22px}',
            '#js-call-screen .jcs-mic{display:inline-flex;align-items:center;justify-content:center;gap:7px;height:48px;min-width:96px;padding:0 18px;border-radius:999px;background:rgba(40,40,40,.72);border:1px solid rgba(255,255,255,.18);color:#fff;font-size:14px;font-weight:600;cursor:pointer;-webkit-backdrop-filter:blur(10px);backdrop-filter:blur(10px)}',
            '#js-call-screen .jcs-mic.is-on{background:#fff;color:#111}',
            '#js-call-screen .jcs-mic .jcs-mic-ic{display:flex}',
            '#js-call-screen .jcs-end{display:inline-flex;align-items:center;justify-content:center;width:68px;height:68px;padding:0;border:0;border-radius:999px;background:#E7484F;cursor:pointer;box-shadow:0 10px 26px rgba(231,72,79,.45)}',
            '#js-call-screen .jcs-end-ic{display:flex;color:#fff;transform:rotate(135deg)}',
            '#js-call-screen .jcs-end-ic svg{width:26px;height:26px}'
        ].join('')
        document.head.appendChild(s)
    }

    function callScreenNode() {
        if (callScreenEl && document.body.contains(callScreenEl)) return callScreenEl
        ensureCallScreenStyle()
        var el = document.createElement('div')
        el.id = 'js-call-screen'
        el.setAttribute('role', 'dialog')
        el.setAttribute('aria-label', 'Voice call')
        el.innerHTML =
            '<div class="jcs-bg" aria-hidden="true"></div>' +
            '<div class="jcs-top">' +
            '<div class="jcs-name"></div>' +
            '<div class="jcs-status"><span class="jcs-dot"></span><span class="jcs-status-text">Connecting…</span></div>' +
            '<div class="jcs-sub"></div>' +
            '</div>' +
            '<div class="jcs-bottom">' +
            '<div class="jcs-cap" aria-live="polite"></div>' +
            '<div class="jcs-actions">' +
            '<button type="button" class="jcs-mic" aria-pressed="false">' +
            '<span class="jcs-mic-ic">' + CALL_MIC_ON + '</span>' +
            '<span class="jcs-mic-label">Mute</span>' +
            '</button>' +
            '<button type="button" class="jcs-end" aria-label="End call">' +
            '<span class="jcs-end-ic">' + CALL_PHONE + '</span>' +
            '</button>' +
            '</div></div>'
        document.body.appendChild(el)
        el.querySelector('.jcs-mic').addEventListener('click', function (e) {
            e.preventDefault()
            e.stopPropagation()
            // 与头部胶囊共用同一份静音状态
            var btn = document.getElementById('js-call-mic')
            if (btn) btn.click()
        })
        el.querySelector('.jcs-end').addEventListener('click', function (e) {
            e.preventDefault()
            e.stopPropagation()
            closeCall()
        })
        callScreenEl = el
        return el
    }

    function callScreenTick() {
        var el = callScreenEl
        if (!el || !callActive) return
        var secs = Math.max(0, Math.floor((Date.now() - callStartedAt) / 1000))
        var mm = (secs >= 600 ? '' : '0') + Math.floor(secs / 60)
        var ss = (secs % 60 < 10 ? '0' : '') + (secs % 60)
        var ppm = Number(consumeOptions.call_price_per_minute || 0)
        el.querySelector('.jcs-sub').textContent = mm + ':' + ss + (ppm > 0 ? ' · ' + ppm + ' tokens/min' : '')
        var stateText
        if (callMuted) stateText = 'Muted'
        else if (callSpeaking) stateText = 'Speaking…'
        else if (callListenState) stateText = callListenState
        else stateText = secs < 2 ? 'Connecting…' : 'Listening…'
        el.querySelector('.jcs-status-text').textContent = stateText
        if (el.getAttribute('data-muted') !== (callMuted ? '1' : '0')) {
            el.setAttribute('data-muted', callMuted ? '1' : '0')
            var mic = el.querySelector('.jcs-mic')
            mic.classList.toggle('is-on', !!callMuted)
            mic.setAttribute('aria-pressed', callMuted ? 'true' : 'false')
            mic.querySelector('.jcs-mic-ic').innerHTML = callMuted ? CALL_MIC_OFF : CALL_MIC_ON
            mic.querySelector('.jcs-mic-label').textContent = callMuted ? 'Unmute' : 'Mute'
        }
    }

    // 通话中最新一句（自己说的 / AI 回的）显示在字幕条
    function callScreenCaption(text, mine) {
        var el = callScreenEl
        if (!el || !el.classList.contains('on') || !text) return
        var cap = el.querySelector('.jcs-cap')
        cap.textContent = text
        cap.style.opacity = mine ? '.82' : '1'
        cap.classList.add('on')
        cap.scrollTop = cap.scrollHeight
    }

    function callScreenOpen() {
        if (window.innerWidth > 1023) return
        var el = callScreenNode()
        el.querySelector('.jcs-name').textContent = callDisplayName()
        el.querySelector('.jcs-status-text').textContent = 'Connecting…'
        el.querySelector('.jcs-sub').textContent = ''
        var cap = el.querySelector('.jcs-cap')
        cap.textContent = ''
        cap.classList.remove('on')
        var img = document.getElementById('chat-img-mobile') || document.getElementById('chat-img')
        var src = img ? (img.currentSrc || img.getAttribute('src') || '') : ''
        el.querySelector('.jcs-bg').style.backgroundImage = src ? 'url("' + src + '")' : 'none'
        el.classList.add('on')
        callStartedAt = Date.now()
        callScreenTick()
        if (callTickTimer) clearInterval(callTickTimer)
        callTickTimer = setInterval(callScreenTick, 500)
    }

    function callScreenClose() {
        if (callTickTimer) { clearInterval(callTickTimer); callTickTimer = null }
        if (callScreenEl) callScreenEl.classList.remove('on')
    }

    function callLocale() {
        return ({
            zh: 'zh-CN', 'zh-cn': 'zh-CN', 'zh-tw': 'zh-TW',
            en: 'en-US', ja: 'ja-JP', ko: 'ko-KR',
            fr: 'fr-FR', de: 'de-DE', es: 'es-ES', it: 'it-IT',
            ru: 'ru-RU', hi: 'hi-IN',
            th: 'th-TH', vi: 'vi-VN', ms: 'ms-MY', id: 'id-ID',
            ar: 'ar-SA', tr: 'tr-TR', km: 'km-KH', lo: 'lo-LA'
        })[uiLang()] || 'en-US'
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

    // 通话界面/开场白的角色名以页面顶部显示的角色为准：
    // 左侧会话列表的预览名可能过期（例如 ?id=home-26 顶部是 Xia Xingyao，列表里仍挂着 Addison），
    // 直接读 currentPersona() 会把别人的名字带进通话
    function callDisplayName() {
        var el = document.getElementById('chat-name')
        var t = el ? String(el.textContent || '').trim() : ''
        return t || callRoleName()
    }

    // 女声 / 男声识别：各平台音色命名差异很大，先认常见女声，
    // 认不出时排除明显男声，避免把 David / Mark / Guy 这类音色分给女生角色
    var FEMALE_VOICE_RE = /(aria|jenny|michelle|ana|zira|samantha|karen|moira|tessa|victoria|serena|allison|ava|susan|joanna|kendra|kimberly|salli|ivy|amy|emma|olivia|sophia|isabella|chloe|grace|hazel|libby|sonia|natasha|clara|emily|jessica|lily|lucy|mia|nova|sky|xiaoxiao|xiaoyi|xiaohan|xiaomeng|xiaomo|xiaoxuan|xiaoyan|huihui|yaoyao|tingting|meijia|sinji|kyoko|nanami|ayumi|haruka|seoyeon|yuna|sora|female|woman|girl)/i
    var MALE_VOICE_RE = /(\bmale\b|\bman\b|\bboy\b|david|mark|guy|alex|daniel|fred|george|james|ryan|thomas|oliver|arthur|diego|jorge|luca|matteo|paul|richard|xander|yannick|filip|ravi|rishi|hemant|kumar|liam|eric|andrew|brian|christopher|steven|tony|william|jason|kevin|peter|robert)/i
    // Chrome 的「Google US English」等音色名字里没有 female，但实际是女声，单独认一下
    var FEMALE_NEUTRAL_RE = /google\s+(us|uk|australia|canada|india|new zealand|ireland|south africa)?\s*english/i

    /**
     * 同一角色固定同一个音色；不同角色在可选女声里轮换。
     * 同地区女声不够时依次补：同语种其它地区女声（en-GB / en-AU / en-IN …）→
     * 同地区中性音色（Google US English 等）→ 其它语种女声，
     * 避免像以前那样「高质量女声只有 1 个」导致所有角色听起来是同一个人。
     */
    function pickVoice(seed) {
        if (!('speechSynthesis' in window) || !window.speechSynthesis) return null
        var voices = window.speechSynthesis.getVoices()
        if (!voices || !voices.length) return null
        var locale = callLocale().toLowerCase()
        var prefix = locale.split('-')[0]

        var exact = []       // 同地区女声
        var sameLang = []    // 同语种其它地区女声
        var neutral = []     // 同地区非男声
        var otherFemale = [] // 其它语种女声（最后兜底）
        var seen = {}
        for (var i = 0; i < voices.length; i++) {
            var v = voices[i]
            var name = String(v.name || '')
            var lang = String(v.lang || '').toLowerCase()
            var key = String(v.voiceURI || name)
            if (seen[key]) continue
            seen[key] = 1
            var female = !MALE_VOICE_RE.test(name) && (FEMALE_VOICE_RE.test(name) || FEMALE_NEUTRAL_RE.test(name))
            var male = MALE_VOICE_RE.test(name)
            if (lang === locale) {
                if (female) exact.push(v)
                else if (!male) neutral.push(v)
            } else if (lang.indexOf(prefix + '-') === 0 || lang === prefix) {
                if (female) sameLang.push(v)
            } else if (female) {
                otherFemale.push(v)
            }
        }

        // 高质量音色优先，其余按名称排序，保证同一角色每次拿到同一个音色
        function rank(name) {
            if (/natural|neural|premium|enhanced/i.test(name)) return 0
            if (/google/i.test(name)) return 1
            return 2
        }
        function byQuality(a, b) {
            var d = rank(String(a.name || '')) - rank(String(b.name || ''))
            return d !== 0 ? d : String(a.name || '').localeCompare(String(b.name || ''))
        }
        exact.sort(byQuality); sameLang.sort(byQuality); neutral.sort(byQuality); otherFemale.sort(byQuality)

        var pool = exact.concat(sameLang)
        if (pool.length < 2) pool = pool.concat(neutral)
        if (!pool.length) pool = otherFemale
        if (!pool.length) return null
        return pool[seed % pool.length]
    }

    // 角色维度的音色种子：优先 role_key（home-N / my-N），其次内容 id 与角色名
    function callVoiceSeed() {
        return hashSeed(String(convRoleKey()) + '|' + String(convContentId()) + '|' + String(callDisplayName()))
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
            var ppm = Number(consumeOptions.call_price_per_minute || 0)
            text.textContent = ppm > 0 ? ('In call… · ' + ppm + ' tokens/min') : 'In call…'
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
        // 录音兜底：挂断/静音/AI 开始说话时，丢弃这段录音
        if (callMicStream || callMicNode) callStopRecording(true)
    }

    var callAudioEl = null
    var callTtsSeq = 0
    // 服务端 STT 兜底（浏览器没有 SpeechRecognition 时用 WebAudio 录 WAV 上传）
    var callRecCtx = null
    var callMicStream = null
    var callMicNode = null
    var callMicSource = null
    var callMicSink = null
    var callMicSamples = null
    var callMicPermStream = null   // 通话期间常驻的麦克风流（在手势内申请，避免每轮重新要权限）
    var callMicStartedAt = 0
    var callMicLastVoice = 0
    var callMicSawVoice = false
    var callMicAbort = false
    var callSttBusy = false
    var callSttFailures = 0
    var callListenState = ''   // 'Recording…' / 'Processing…'，仅用于通话界面状态文案
    // 20ms 静音 WAV：浏览器没有 Web Speech（微信/IG 内置浏览器、iOS Chrome）时改走服务端 TTS，
    // 用 <audio> 播放；iOS 要求首次 play() 发生在用户手势内，先用它把元素解锁
    var CALL_SILENT_WAV = 'data:audio/wav;base64,UklGRsQAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YaAAAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICAgICA'

    function callNativeTts() {
        return ('speechSynthesis' in window) && !!window.speechSynthesis
    }

    function callAudio() {
        if (callAudioEl) return callAudioEl
        callAudioEl = new Audio()
        callAudioEl.preload = 'auto'
        callAudioEl.setAttribute('playsinline', '')
        callAudioEl.addEventListener('ended', function () {
            if (callAudioEl._silent) return
            callSpeaking = false
            if (callActive && !callMuted) callStartListening()
        })
        callAudioEl.addEventListener('error', function () {
            if (callAudioEl._silent) return
            callSpeaking = false
            if (callActive && !callMuted) callStartListening()
        })
        return callAudioEl
    }

    // 移动端（尤其 iOS Safari）要求第一次发声发生在用户手势内，
    // 否则整通电话只有字幕没有声音。通话按钮的点击里先同步解锁两条播放通道：
    // 原生 TTS（speechSynthesis）与服务端 TTS（<audio>）。
    function callUnlockSpeech() {
        if (callNativeTts() && !window.jsCallSpeechUnlocked) {
            var synth = window.speechSynthesis
            try {
                if (synth.paused) synth.resume()
                window.jsCallSpeechUnlocked = true
                var u = new SpeechSynthesisUtterance(' ')
                u.volume = 0
                u.lang = callSafeLang()
                synth.speak(u)
            } catch (e) {}
        }
        if (window.jsCallAudioUnlocked) {
            // 录音兜底也要用同一个 AudioContext：iOS 只允许在用户手势内创建/恢复
            if (callRecCtx && callRecCtx.state === 'suspended') { try { callRecCtx.resume() } catch (e) {} }
            return
        }
        try {
            var a = callAudio()
            a._silent = true
            a.src = CALL_SILENT_WAV
            var p = a.play()
            if (p && p.then) {
                p.then(function () { try { a.pause(); a.currentTime = 0 } catch (e) {} }).catch(function () {})
            }
            // 服务端 STT 兜底：录音/分析都用这个 AudioContext，必须在点击手势里创建
            if (!callRecCtx) {
                var AC = window.AudioContext || window.webkitAudioContext
                if (AC) callRecCtx = new AC()
            }
            if (callRecCtx && callRecCtx.state === 'suspended') { try { callRecCtx.resume() } catch (e) {} }
            // iOS 部分 WebView 只允许在用户手势内 getUserMedia：点通话时就把麦克风权限/流拿到，
            // 后续每轮录音直接复用，避免「自动开始录音」时被系统拒绝
            if (!callMicPermStream && navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
                    if (callActive) {
                        callMicPermStream = s
                    } else {
                        try { s.getTracks().forEach(function (t) { t.stop() }) } catch (e) {}
                    }
                }).catch(function () {})
            }
            window.jsCallAudioUnlocked = true
        } catch (e) {}
    }

    // 目标语种在设备上没有任何音色时（如部分 Android 无 ms-MY），继续用该 lang 会整句静音：
    // 优先退到同语种音色，再退英文默认音色——音色不完美，但至少能听到
    function callSafeLang() {
        var want = callLocale()
        var prefix = want.split('-')[0]
        var voices = (window.speechSynthesis && window.speechSynthesis.getVoices) ? window.speechSynthesis.getVoices() : []
        for (var i = 0; i < voices.length; i++) {
            var lang = String(voices[i].lang || '')
            if (lang.toLowerCase().indexOf(prefix) === 0) return lang
        }
        return 'en-US'
    }

    function callSpeak(text) {
        if (!text) return
        callStopListening()
        callSpeaking = true
        callTtsSeq++   // 让在途的服务端 TTS 结果作废
        if (callAudioEl && !callAudioEl.paused) { try { callAudioEl.pause() } catch (e) {} }
        if (callNativeTts()) callSpeakNative(text)
        else callSpeakServer(text, callTtsSeq)
    }

    // 服务端 TTS：/api/live/tts 返回 audio/mpeg（xAI /v1/tts 代理）
    function callSpeakServer(text, seq) {
        fetch('/api/live/tts', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ text: text, lang: uiLang() })
        })
            .then(function (r) {
                var ct = (r.headers.get('content-type') || '').toLowerCase()
                if (!r.ok || ct.indexOf('audio') !== 0) throw new Error('tts ' + r.status)
                return r.blob()
            })
            .then(function (blob) {
                if (seq !== callTtsSeq || !callActive) return
                var a = callAudio()
                a._silent = false
                if (a._url) { try { URL.revokeObjectURL(a._url) } catch (e) {} }
                a._url = URL.createObjectURL(blob)
                a.src = a._url
                return a.play()
            })
            .catch(function () {
                if (seq !== callTtsSeq || !callActive) return
                callSpeaking = false
                if (!window.jsCallTtsErrorShown) {
                    window.jsCallTtsErrorShown = true
                    appendError('Voice is unavailable right now. Please try again in a moment.')
                }
                if (callActive && !callMuted) callStartListening()
            })
    }

    function callSpeakNative(text) {
        var synth = window.speechSynthesis

        var speak = function () {
            var u = new SpeechSynthesisUtterance(text)
            var seed = callVoiceSeed()
            var v = pickVoice(seed)
            if (v) u.voice = v
            u.lang = (v && v.lang) ? v.lang : callSafeLang()
            // 同一角色固定，不同角色再用音高/语速拉开区别
            u.rate = 0.94 + (seed % 7) * 0.018
            u.pitch = 0.88 + ((seed >> 3) % 9) * 0.05
            u.onend = function () { callSpeaking = false; if (callActive) callStartListening() }
            u.onerror = function (ev) {
                // canceled/interrupted 是我们自己切句造成的，只有真正的播放失败才提示
                var err = String((ev && ev.error) || '')
                if (/not-allowed|synthesis-failed|audio-busy|audio-hardware/i.test(err) && !window.jsCallTtsErrorShown) {
                    window.jsCallTtsErrorShown = true
                    appendError('Voice could not start. Check the silent switch and media volume, then tap Call again.')
                }
                callSpeaking = false
                if (callActive) callStartListening()
            }
            if (synth.paused) { try { synth.resume() } catch (e) {} }
            // iOS 上 cancel() 与 speak() 同一 tick 连用会把新句子吞掉，隔一帧再播
            if (synth.speaking || synth.pending) {
                synth.cancel()
                setTimeout(function () { try { synth.speak(u) } catch (e) {} }, 80)
            } else {
                try { synth.speak(u) } catch (e) {}
            }
        }

        // 音色列表在部分浏览器里是异步加载的：首次通话时立刻 speak 会退回浏览器默认音色，
        // 这里等 voiceschanged（最多 800ms）再读，保证每个角色都能拿到各自的音色
        if (synth.getVoices && synth.getVoices().length) { speak(); return }
        var fired = false
        var run = function () {
            if (fired) return
            fired = true
            synth.onvoiceschanged = null
            speak()
        }
        synth.onvoiceschanged = run
        setTimeout(run, 800)
    }

    function callStartListening() {
        if (!callActive || callMuted || callSpeaking || callSttBusy) return
        var SR = window.jsCallSrDisabled ? null : (window.SpeechRecognition || window.webkitSpeechRecognition)
        if (!SR) {
            // 内置浏览器/iOS Chrome 没有 SpeechRecognition：改走录音 + 服务端 STT
            callStartRecording()
            return
        }
        if (!callRec) {
            callRec = new SR()
            callRec.lang = callLocale()
            callRec.continuous = false
            callRec.interimResults = false
            callRec.maxAlternatives = 1
            callRec.onresult = function (e) {
                window.jsCallSrFailures = 0
                var text = ''
                for (var i = 0; i < e.results.length; i++) text += e.results[i][0].transcript
                text = text.trim()
                if (text) callSend(text)
            }
            callRec.onerror = function (e) {
                var err = String((e && e.error) || '')
                callDiag('sr-error', { err: err })
                // 权限被拒 / 无麦克风 / 无语音服务 / 网络：都直接改走录音 + 服务端 STT（录音失败会再提示）
                if (/not-allowed|service-not-allowed|audio-capture|network/i.test(err)) {
                    window.jsCallSrDisabled = true
                    if (callActive && !callMuted && !callSpeaking) callStartRecording()
                    return
                }
                // 有些 WebView 暴露了 SpeechRecognition 却没有可用引擎：连续失败两次就改走录音 + 服务端 STT
                window.jsCallSrFailures = (window.jsCallSrFailures || 0) + 1
                if (window.jsCallSrFailures >= 2) {
                    window.jsCallSrDisabled = true
                    if (callActive && !callMuted && !callSpeaking) callStartRecording()
                    return
                }
                if (callActive && !callMuted && !callSpeaking) setTimeout(callStartListening, 700)
            }
            callRec.onend = function () { if (callActive && !callMuted && !callSpeaking) setTimeout(callStartListening, 300) }
        }
        try { callRec.start() } catch (e) {
            // 有些 WebView 里 start() 直接同步抛错（不会有 onerror）：立刻改走录音 + 服务端 STT
            callDiag('sr-throw', { name: (e && e.name) || '', msg: String((e && e.message) || e) })
            window.jsCallSrFailures = (window.jsCallSrFailures || 0) + 1
            if (window.jsCallSrFailures >= 1) {
                window.jsCallSrDisabled = true
                if (callActive && !callMuted && !callSpeaking) callStartRecording()
            } else {
                setTimeout(callStartListening, 500)
            }
        }
    }

    // 通话能力/阶段上报：线上定位「说话没反应」时看 php 日志的 [voiceDiag]
    function callDiag(stage, extra) {
        if (callDiag.count >= 30) return
        callDiag.count = (callDiag.count || 0) + 1
        try {
            var body = {
                stage: stage,
                ua: navigator.userAgent,
                tts: ('speechSynthesis' in window),
                sr: !!(window.SpeechRecognition || window.webkitSpeechRecognition),
                media: !!(navigator.mediaDevices && navigator.mediaDevices.getUserMedia),
                ctx: !!(window.AudioContext || window.webkitAudioContext),
                sp: !!(callRecCtx && callRecCtx.createScriptProcessor)
            }
            if (extra) for (var k in extra) if (extra.hasOwnProperty(k)) body[k] = extra[k]
            fetch('/api/live/voiceDiag', {
                method: 'POST',
                headers: authHeaders(true),
                body: JSON.stringify(body)
            }).catch(function () {})
        } catch (e) {}
    }

    function callMicHint(msg) {
        callListenState = ''
        if (!window.jsCallSttErrorShown) {
            window.jsCallSttErrorShown = true
            appendError(msg)
            if (callActive) callScreenCaption(msg, false)
        }
    }

    /* ---------- 录音 + 服务端 STT（无 SpeechRecognition 的浏览器） ----------
       用 WebAudio 直接收 PCM（避开 MediaRecorder 的 webm 编码，xAI STT 对容器格式有限制）：
       静音 900ms 或最长 12s 自动结束，降采样到 16k 转 WAV 上传 /api/live/stt。 */
    function callGetMic() {
        var live = callMicPermStream && callMicPermStream.getAudioTracks
            ? callMicPermStream.getAudioTracks().some(function (t) { return t.readyState === 'live' })
            : false
        if (live) return Promise.resolve(callMicPermStream)
        return navigator.mediaDevices.getUserMedia({ audio: true }).then(function (s) {
            callMicPermStream = s
            return s
        })
    }

    function callStartRecording() {
        if (callMicStream || callSttBusy || !callActive || callMuted || callSpeaking) return
        if (!callRecCtx || !callRecCtx.createScriptProcessor) {
            callMicHint('Voice input is not supported in this browser — please type your message.')
            return
        }
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            callMicHint('Voice input is not supported in this browser — please type your message.')
            return
        }
        callGetMic().then(function (stream) {
            if (!callActive || callMuted || callSpeaking) {
                // 流是通话级常驻的，这里只放弃本轮，不 stop（closeCall 统一释放）
                return
            }
            var ctx = callRecCtx
            if (ctx.state === 'suspended' && ctx.resume) { try { ctx.resume() } catch (e) {} }
            callMicStream = stream
            callMicSamples = []
            callMicAbort = false
            callMicSawVoice = false
            callMicStartedAt = Date.now()
            callMicLastVoice = Date.now()
            callListenState = 'Recording…'
            callDiag('rec-start', { cstate: ctx.state, rate: ctx.sampleRate })
            callMicSource = ctx.createMediaStreamSource(stream)
            callMicNode = ctx.createScriptProcessor(4096, 1, 1)
            callMicSink = ctx.createGain()
            callMicSink.gain.value = 0   // 必须连到 destination 才会跑，但增益 0 避免回声
            callMicNode.onaudioprocess = function (e) {
                if (!callMicStream || callMicAbort) return
                var input = e.inputBuffer.getChannelData(0)
                var peak = 0
                for (var i = 0; i < input.length; i++) {
                    var v = input[i] < 0 ? -input[i] : input[i]
                    if (v > peak) peak = v
                }
                var now = Date.now()
                if (peak > 0.012) { callMicSawVoice = true; callMicLastVoice = now }
                var copy = new Float32Array(input.length)
                copy.set(input)
                callMicSamples.push(copy)
                // 有语音后静音 900ms 视为说完；最长 12s；一直没检测到语音 4s 就放弃
                if ((callMicSawVoice && now - callMicLastVoice > 900) || now - callMicStartedAt > 12000
                    || (!callMicSawVoice && now - callMicStartedAt > 4000)) {
                    callStopRecording(false)
                }
            }
            callMicSource.connect(callMicNode)
            callMicNode.connect(callMicSink)
            callMicSink.connect(ctx.destination)
        }).catch(function (err) {
            callListenState = ''
            callDiag('mic-error', { name: (err && err.name) || '', msg: String((err && err.message) || err) })
            callMicHint('Microphone permission is required for voice input — allow it and try again.')
        })
    }

    function callStopRecording(abort) {
        var hadStream = !!(callMicStream || callMicNode)
        callMicAbort = !!abort
        if (callMicNode) { try { callMicNode.disconnect() } catch (e) {} }
        if (callMicSource) { try { callMicSource.disconnect() } catch (e) {} }
        if (callMicSink) { try { callMicSink.disconnect() } catch (e) {} }
        // 常驻麦克风流不在这里停止（每轮录音复用），挂断时统一 stop
        var chunks = callMicSamples || []
        var rate = (callRecCtx && callRecCtx.sampleRate) || 48000
        callMicNode = null
        callMicSource = null
        callMicSink = null
        callMicStream = null
        callMicSamples = null
        callListenState = ''
        if (!hadStream || callMicAbort || !callActive || callMuted || callSpeaking) return

        var total = 0
        for (var i = 0; i < chunks.length; i++) total += chunks[i].length
        callDiag('rec-stop', { samples: total, ms: Date.now() - callMicStartedAt, chunks: chunks.length })
        if (total < rate * 0.3) { setTimeout(callStartListening, 250); return }
        var all = new Float32Array(total)
        var off = 0
        for (var j = 0; j < chunks.length; j++) { all.set(chunks[j], off); off += chunks[j].length }
        var wav = callEncodeWav(callDownsample(all, rate, 16000), 16000)

        callSttBusy = true
        callListenState = 'Processing…'
        callTranscribe(wav).then(function (text) {
            callSttBusy = false
            callSttFailures = 0
            callListenState = ''
            if (!callActive) return
            text = String(text || '').trim()
            if (text) callSend(text)
            else setTimeout(callStartListening, 300)
        }).catch(function () {
            callSttBusy = false
            callListenState = ''
            if (!callActive) return
            callSttFailures++
            if (callSttFailures >= 2) {
                callMicHint('Voice input is unavailable right now — you can type in the message box.')
                return
            }
            setTimeout(callStartListening, 1000)
        })
    }

    function callTranscribe(blob) {
        var lang = callLocale().split('-')[0]
        var ctrl = window.AbortController ? new AbortController() : null
        var timer = ctrl ? setTimeout(function () { try { ctrl.abort() } catch (e) {} }, 20000) : null
        var done = function () { if (timer) { clearTimeout(timer); timer = null } }
        return fetch('/api/live/stt?lang=' + encodeURIComponent(lang), {
            method: 'POST',
            headers: (function () {
                var h = { 'Content-Type': 'audio/wav' }
                if (token) h.Authorization = 'Bearer ' + token
                return h
            })(),
            body: blob,
            signal: ctrl ? ctrl.signal : undefined
        }).then(function (r) {
            return r.json()
        }).then(function (d) {
            done()
            if (!d || d.code !== '00000') throw new Error('stt ' + (d && d.code))
            return (d.data && d.data.text) || ''
        }).catch(function (e) {
            done()
            callDiag('stt-error', { name: (e && e.name) || '', msg: String((e && e.message) || e) })
            throw e
        })
    }

    // 降采样（取窗口均值）+ 16bit 单声道 WAV 编码
    function callDownsample(buf, inRate, outRate) {
        if (inRate === outRate) return buf
        var ratio = inRate / outRate
        var outLen = Math.floor(buf.length / ratio)
        var out = new Float32Array(outLen)
        for (var i = 0; i < outLen; i++) {
            var start = Math.floor(i * ratio)
            var end = Math.min(buf.length, Math.floor((i + 1) * ratio))
            var sum = 0
            for (var j = start; j < end; j++) sum += buf[j]
            out[i] = end > start ? sum / (end - start) : 0
        }
        return out
    }

    function callEncodeWav(samples, sampleRate) {
        var buffer = new ArrayBuffer(44 + samples.length * 2)
        var view = new DataView(buffer)
        var writeStr = function (off, s) {
            for (var i = 0; i < s.length; i++) view.setUint8(off + i, s.charCodeAt(i))
        }
        writeStr(0, 'RIFF')
        view.setUint32(4, 36 + samples.length * 2, true)
        writeStr(8, 'WAVE')
        writeStr(12, 'fmt ')
        view.setUint32(16, 16, true)
        view.setUint16(20, 1, true)
        view.setUint16(22, 1, true)
        view.setUint32(24, sampleRate, true)
        view.setUint32(28, sampleRate * 2, true)
        view.setUint16(32, 2, true)
        view.setUint16(34, 16, true)
        writeStr(36, 'data')
        view.setUint32(40, samples.length * 2, true)
        for (var i = 0; i < samples.length; i++) {
            var s = Math.max(-1, Math.min(1, samples[i]))
            view.setInt16(44 + i * 2, s < 0 ? s * 0x8000 : s * 0x7FFF, true)
        }
        return new Blob([view], { type: 'audio/wav' })
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
                if (!d || d.code !== '00000') {
                    // AI 短暂不可用不挂断通话：给友好提示后继续收音
                    if (d && d.code === 'H0001') {
                        appendError(friendlyChatError(d))
                        callSpeaking = false
                        if (callActive && !callMuted) setTimeout(callStartListening, 800)
                        return
                    }
                    // 通话未接通/已到期或余额问题：结束通话并提示
                    appendError('Call ended. ' + friendlyChatError(d))
                    closeCall()
                    return
                }
                var reply = (d.data && typeof d.data.reply === 'string') ? d.data.reply : ''
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

    // 通话按分钟计费：返回 true 表示可继续（余额足够或未开启计费）
    function callChargeOnce() {
        if (!(Number(consumeOptions.call_price_per_minute || 0) > 0)) return Promise.resolve(true)
        return fetch('/api/live/callCharge', {
            method: 'POST',
            headers: authHeaders(true),
            body: JSON.stringify({ content_id: convContentId() })
        })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (d && d.code === '00000') {
                    if (d.data && d.data.balance !== undefined) renderTokens(d.data.balance)
                    return true
                }
                appendError('Call ended. ' + friendlyChatError(d))
                return false
            })
            .catch(function () { return true })
    }

    function openCall() {
        if (!token) { goLoginPage(); return }
        if (callActive) return
        // 必须在点击手势内同步解锁 TTS；放到 callChargeOnce().then 里就晚了（iOS/Android 会静音）
        callUnlockSpeech()
        // 一次性上报浏览器通话能力，线上定位「说话没反应」时直接看 php 日志的 [voiceDiag]
        if (!window.jsCallDiagSent) {
            window.jsCallDiagSent = true
            callDiag('call-start')
        }
        // 先扣第一分钟，余额不足则不接通
        callChargeOnce().then(function (ok) {
            if (!ok || callActive) return
            ensureCallStyle()
            callActive = true
            callSpeaking = false
            callHeaderUi()
            callResetMic()
            callSetStatus(true)
            callScreenOpen()
            // 每分钟续扣一次，余额不足自动挂断
            if (Number(consumeOptions.call_price_per_minute || 0) > 0) {
                callBillTimer = setInterval(function () {
                    callChargeOnce().then(function (ok2) { if (!ok2) closeCall() })
                }, 60000)
            }
            var name = callDisplayName()
            setTimeout(function () {
                if (!callActive) return
                var greet = 'Hi, this is ' + name + '. So glad you called!'
                appendBubble(greet, false, true)
                callSpeak(greet)
            }, 800)
        })
    }

    function closeCall() {
        callActive = false
        callSpeaking = false
        callTtsSeq++
        callScreenClose()
        callStopListening()
        if (callAudioEl) { try { callAudioEl.pause(); callAudioEl.currentTime = 0 } catch (e) {} }
        if (callMicPermStream) { try { callMicPermStream.getTracks().forEach(function (t) { t.stop() }) } catch (e) {} ; callMicPermStream = null }
        if (callBillTimer) { clearInterval(callBillTimer); callBillTimer = null }
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
            // 点击委托仍在用户手势内，先解锁再走异步扣费
            callUnlockSpeech()
            openCall()
        }, true)
        // 页面任意首次触摸也预热一次，避免用户先点别处再打电话时解锁失败
        document.addEventListener('pointerdown', callUnlockSpeech, { passive: true, once: true })
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
        if (token && document.body) {
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
    // 会话头像兜底：没有封面图时生成「渐变 + 首字母」占位，避免左侧列表一排裂图
    function avatarPlaceholder(title) {
        var name = String(title || 'AI').trim()
        var letter = (name[0] || 'A').toUpperCase()
        var hue = 0
        for (var i = 0; i < name.length; i++) hue = (hue * 31 + name.charCodeAt(i)) % 360
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(
            '<svg xmlns="http://www.w3.org/2000/svg" width="96" height="96">' +
            '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1">' +
            '<stop offset="0" stop-color="hsl(' + hue + ',60%,55%)"/><stop offset="1" stop-color="hsl(' + ((hue + 40) % 360) + ',65%,45%)"/>' +
            '</linearGradient></defs>' +
            '<rect width="96" height="96" fill="url(#g)"/>' +
            '<text x="48" y="61" font-family="Arial,sans-serif" font-size="40" font-weight="700" fill="#fff" text-anchor="middle">' + letter + '</text></svg>')
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

            var img = String(conv.image || '').split(',')[0]
            if (!img) img = avatarPlaceholder(conv.title)
            var rendered = template.replace("{{name}}", conv.title == null ? '' : conv.title)
            .replace("{{imgUrl}}", img)
            .replace("{{message}}", conv.description == null ? '' : conv.description)
            .replace("{{time}}", conv.time == null ? '' : conv.time)
            .replace("{{id}}", cid);

            var $row = $(rendered)
            // 封面 URL 存在但加载失败（失效域名/404）→ 同样换占位
            $row.find('img').one('error', function () { this.src = avatarPlaceholder(conv.title) })
            $('#message-list').append($row);
        });
        // 当前会话置为选中态，保证刷新后归档键不丢
        var $active = $('#message-list').find('a.chat-obj[data-id="' + activeConvId + '"]').first()
        if ($active.length) $active.addClass('px-[6px] bg-[#303030] border border-zinc-600').addClass('active')
    }
    // 只刷人设（名字/资料/轮播），不碰消息区——用于补人设时不打断已渲染的开场问候
    function paintPersona(info) {
        if (!info) return
        var pictures = String(info.image || '').split(',')
        $('#chat-name').html(info.title)
        $('#infomation').html(info.description)
        $('#occupation').html(info.occupation)
        $('#hobbies').html(info.hobbies)
        $('#relationship').html(info.relationship)
        $('#body').html(info.body)
        $('#age').html(info.age)
        // 顶部头像：PC(#chat-img) 与手机(#chat-img-mobile) 两份都要换，否则手机端一直显示默认头像
        if (pictures[0]) { $('#chat-img').attr('src', pictures[0]); $('#chat-img-mobile').attr('src', pictures[0]) }
        $('#ethnicity').html(info.ethnicity)
        $('#name').html(info.title)
        if (pictures[0]) {
            var html = ''
            pictures.forEach(function (t, index) {
                html += "<div class='carousel-item" + (index === 0 ? ' active' : '') + "'><img src='" + t + "' class='w-full object-top object-cover h-[483px]'></div>"
            })
            $('.carousel').html(html)
        }
    }

    function getDetail(info) {
        if(!info) return
        try { (window.__chatTrace = window.__chatTrace || []).push('getDetail id=' + String(info.id) + ' title=' + String(info.title)) } catch (e) {}
        paintPersona(info)
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
        try { (window.__chatTrace = window.__chatTrace || []).push('channelDetail id=' + String(id) + ' param=' + String(getParam('id'))) } catch (e) {}
        if (String(id).indexOf('my-') === 0) { loadMyPersona(id); return }
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
        var urlId = String(id || '') || String(getParam('id') || '')
        if (urlId === '' && activeConvId !== '') {
            // 重复初始化（Turbo 返回会再跑一次 bootChat）：保留本页面已选中的会话，不重新随机
        } else {
            activeConvId = urlId
        }
        // chat-default 不是真实角色（旧版默认兜底键）：清掉，走「随机首页角色」
        if (activeConvId === 'chat-default') {
            activeConvId = ''
            try { history.replaceState(null, '', './Chat.html') } catch (e) {}
        }
        // 直接进入聊天页（没指定角色）：随机挑一个首页角色开场，而不是固定落在上次聊过的角色
        if (activeConvId === '') {
            var pool = []
            for (var pi = 0; pi < local.length; pi++) {
                if (/^\d+$/.test(String(local[pi] && local[pi].id))) pool.push(local[pi])
            }
            if (pool.length > 0) {
                activeConvId = String(pool[Math.floor(Math.random() * pool.length)].id)
            } else {
                // 本机没有角色缓存（新设备）：等预热拉取完成（轮询 pageList），拿到后随机开场
                waitForRoleList(function (list) {
                    if (list.length > 0 && activeConvId === '') {
                        var pick = list[Math.floor(Math.random() * list.length)]
                        activeConvId = String(pick.id)
                        var convObj = { id: activeConvId, title: pick.title || '', image: pick.cover_url || '', description: pick.description || '' }
                        convItems[activeConvId] = convObj
                        getDetail(convObj)
                    }
                    continueChannelDetail(readPageList())
                })
                return
            }
        }
        // 聊天记录只属于登录用户：未登录左侧不显示任何会话（历史为空），右侧仍展示随机角色资料
        continueChannelDetail(local)
    }

    // channelDetail 的公共后半段：左侧 = 真实聊天会话列表（聊过的角色），只对登录用户渲染；
    // 直接进入时随机会话不在列表里 → 插入列表顶部，保证「随机会话」也是正常一条聊天记录。
    // 等待角色目录就绪（内存 / pageList 缓存，bootChat 已异步预热）；最多 8 秒
    function waitForRoleList(cb, tries) {
        var cached = roleListCached()
        if (cached.length > 0) { cb(cached); return }
        if ((tries || 0) >= 40) { cb([]); return }
        if ((tries || 0) % 10 === 9) preloadRoleList()   // 2 秒还没到就再发一次预热
        setTimeout(function () { waitForRoleList(cb, (tries || 0) + 1) }, 200)
    }

    // 角色目录读取：内存优先（localStorage 被禁/异常时仍可用）
    function roleListCached() {
        if (window.__roleList && window.__roleList.length > 0) return window.__roleList
        return readPageList()
    }

    // 角色目录预热：独立请求写缓存（随机开场/资料补全都依赖它）
    function preloadRoleList() {
        // 缓存里的签名地址有时效：过期或超过 TTL 都重新拉，否则封面/预览视频会裂开
        if (roleListCached().length > 0 && pageListFresh()) return
        if (window.__roleListLoading) return
        window.__roleListLoading = true
        try {
            fetch('/api/live/pagelist', { method: 'POST', headers: authHeaders(false), cache: 'no-store' })
                .then(function (r) { return r.json() })
                .then(function (d) {
                    window.__roleListLoading = false
                    var list = (d && d.code === '00000' && d.data && Array.isArray(d.data.list)) ? d.data.list : []
                    if (list.length > 0) {
                        window.__roleList = list
                        // 合并而非整表替换：保留 Home 页写入的 home-N / 自建 my-N 条目，
                        // 否则按 ?id=home-N 进入时会退化成数字 N 命中错误的平台角色
                        try {
                            var prev = []
                            try { prev = JSON.parse(localStorage.getItem('pageList') || '[]') || [] } catch (e2) {}
                            var keep = prev.filter(function (t) { return t && !/^\d+$/.test(String(t.id)) && convAlive(t) })
                            localStorage.setItem('pageList', JSON.stringify(keep.concat(list)))
                            localStorage.setItem('pageList_saved_at', String(Date.now()))
                        } catch (e) {}
                    }
                })
                .catch(function () { window.__roleListLoading = false })
        } catch (e) { window.__roleListLoading = false }
    }

    function continueChannelDetail(local) {
        // 未登录：左侧保持空（没有历史会话可显示），右侧展示当前角色资料 + 开场白（清掉页面自带的空音频占位）
        if (!token) {
            getInfo([])
            ensureCurrentInList(local)
            var guestConv = chatItemById(activeConvId)
            if (guestConv && guestConv.title && guestConv.title !== activeConvId && guestConv.title !== 'AI Chat') getDetail(guestConv)
            // 清掉页面自带的空音频占位气泡，直接展示开场白
            var guestBox = messagesBox()
            if (guestBox) guestBox.innerHTML = ''
            showIceBreaker()
            // 场景按钮（未登录也展示，点击跳登录——candy 同款门禁）
            loadSceneMedia()
            return
        }
        // 本机没有角色缓存（新设备）：等预热拉取完成后继续（会话列表来自服务端，不依赖它）
        if (local.length === 0) {
            waitForRoleList(function (list) { continueChannelDetail(list) })
            return
        }

        // 随机/指定的当前会话先入列表；列表已有内容时不清空（重复初始化/Turbo 返回时避免闪成 1 条，
        // 尤其视频播放占用带宽、会话接口 pending 的窗口期），等会话接口回来再整体替换
        if ($('#message-list').find('a.chat-obj').length === 0) {
            getInfo([])
            ensureCurrentInList(local)
        } else {
            ensureCurrentInList(local)
        }

        // 当前会话的聊天历史与「会话列表」并行拉（历史上是串行，白等一个 RTT）；未登录无用户历史可拉
        if (activeConvId !== '' && token) loadChatHistory()

        // 拉服务端会话列表（真实聊天记录，按最近聊天排序）
        fetch('/api/live/chatConversations?device_id=' + encodeURIComponent(deviceId()), { headers: authHeaders(false), cache: 'no-store' })
          .then(function (r) { return r.json() })
          .then(function (d) {
            var convs = (d && d.code === '00000' && Array.isArray(d.data)) ? d.data : []
            getInfo(buildConvItems(convs, local))
            ensureCurrentInList(local)
            // URL 不带 ?id= 时补上，刷新后仍停留在同一会话
            if (String(getParam('id') || '') === '' && activeConvId !== '') {
                try { history.replaceState(null, '', './Chat.html?id=' + encodeURIComponent(activeConvId)) } catch (e) {}
            }
            startConversation()
          })
          .catch(function () { startConversation() })
      }

    // 把当前随机会话插到列表顶部（无头像时用角色占位，聊过一条后由服务端会话数据接管）
    function ensureCurrentInList(local) {
        if (activeConvId === '') return
        var $existing = $('#message-list').find('a.chat-obj[data-id="' + activeConvId + '"]').first()
        if ($existing.length > 0) {
            // 当前正在聊的会话始终置顶：右侧聊谁，左侧第一条就是谁，两边保持一致
            $('#message-list').prepend($existing)
            return
        }
        var src = null
        // 资料匹配：先按完整 id 精确匹配（Home 页条目 id='home-14'），再回退按数字 N 匹配目录
        for (var i = 0; i < (local || []).length; i++) {
            if (String(local[i] && local[i].id) === activeConvId) { src = local[i]; break }
        }
        if (!src) {
            var mHome = /^home-(\d+)$/.exec(activeConvId)
            if (mHome) {
                // 数字 N 在 pageList 里撞号（同号平台内容角色）——改用服务端首页角色目录
                var hrSrc = homeRoleMap()[activeConvId]
                if (hrSrc) {
                    src = { title: hrSrc.name || '', cover_url: hrSrc.cover_url || '', description: hrSrc.description || hrSrc.tagline || '' }
                } else {
                    for (var k = 0; k < (local || []).length; k++) {
                        if (String(local[k] && local[k].id) === mHome[1]) { src = local[k]; break }
                    }
                }
            }
        }
        var conv = chatItemById(activeConvId) || {}
        // 资料缺失（如 pagelist 拉取失败）时标题兜底为 AI Chat，绝不显示 home-14 这类裸归档键
        var fallbackTitle = ((src && src.title) || conv.title || '')
        if (fallbackTitle === '' || fallbackTitle === activeConvId) fallbackTitle = 'AI Chat'
        var item = {
            id: activeConvId,
            title: fallbackTitle,
            image: (src && src.cover_url) || conv.image || '',
            description: (src && src.description) || conv.description || '',
            time: 'now'
        }
        var template = String($('#user-template').html() || '')
        var img = String(item.image || '').split(',')[0] || avatarPlaceholder(item.title)
        var rendered = template.replace('{{name}}', item.title)
            .replace('{{imgUrl}}', img)
            .replace('{{message}}', item.description)
            .replace('{{time}}', 'now')
            .replace('{{id}}', item.id)
        var $row = $(rendered)
        $row.find('img').one('error', function () { this.src = avatarPlaceholder(item.title) })
        $row.addClass('px-[6px] bg-[#303030] border border-zinc-600 active')
        $('#message-list').prepend($row)
        convItems[item.id] = item
      }

    // 服务端会话 + 本地记录 → 左侧可渲染条目（本地记录补名字/头像，服务端给最近一条消息与时间）
    function buildConvItems(convs, local) {
        var items = []
        var used = {}
        for (var i = 0; i < convs.length; i++) {
            var conv = convs[i] || {}
            var cid = Number(conv.content_id || 0)
            var id = cid > 0 ? String(cid) : String(conv.role_key || '')
            if (id === '' || id === '0' || id === 'chat-default') continue
            var l = null
            for (var k = 0; k < local.length; k++) {
                if (String(local[k] && local[k].id) === id) { l = local[k]; break }
            }
            var persona = null
            if (/^my-\d+$/.test(id)) {
                try { persona = JSON.parse(localStorage.getItem('myPersona:' + id) || 'null') } catch (e) { persona = null }
            }
            used[id] = true
            // 名字兜底：人设名 → 服务端标题 → 「AI Chat」，绝不把裸 id 当名字显示
            var item = emptyConv(id, (l && l.title) || conv.title || (persona && persona.name) || 'AI Chat')
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
        // 左侧只显示真实聊天会话：不再把本地角色目录全量塞进列表（那就是「几十个会话」的来源）
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
        try { (window.__chatTrace = window.__chatTrace || []).push('startConversation active=' + String(activeConvId)) } catch (e) {}
        var $cur = $('#message-list').find('a.chat-obj[data-id="' + activeConvId + '"]').first()
        if (!$cur.length) {
            // 没匹配到当前会话：随机挑一条（原来是固定第一条，永远落在最近聊过的那个角色）
            var all = $('#message-list').find('a.chat-obj')
            $cur = all.length ? $(all[Math.floor(Math.random() * all.length)]) : $()
        }
        var cid = String($cur.data('id') || '')
        if (cid !== '') { selectConversation(cid, true); return }
        // 列表里没有任何会话（直接打开 Chat.html）也要加载历史，否则刷新后看不到记录
        loadChatHistory()
        loadAffection()
        loadSceneMedia()
    }

    // 切换会话：高亮 + 右侧资料 + 历史消息 + URL 同步（skipHistory=初始化时历史已并行预拉，避免重复请求）
    function selectConversation(convId, skipHistory) {
        convId = String(convId || '')
        if (convId === '') return
        var sameConv = convId === activeConvId
        activeConvId = convId
        $('#message-list').find('a.chat-obj').each(function() {
            $(this).removeClass('px-[6px] bg-[#303030] border border-zinc-600').removeClass('active')
        })
        var $cur = $('#message-list').find('a.chat-obj[data-id="' + convId + '"]').first()
        if ($cur.length) $cur.addClass('px-[6px] bg-[#303030] border border-zinc-600').addClass('active')
        try { history.replaceState(null, '', './Chat.html?id=' + encodeURIComponent(convId)) } catch (e) {}
        getDetail(chatItemById(convId))
        // 立即清空消息区并显示加载动画：旧角色的消息不再滞留（历史接口回来自然替换），消除"卡住不切换"的观感
        if (!skipHistory || !sameConv) {
            var swBox = messagesBox()
            if (swBox) {
                swBox.innerHTML = ''
                appendTyping()
            }
        }
        // 场景媒体（Image/Video 按钮）：按角色素材显示/隐藏、置灰（须在早退前——初始化选中当前会话走 skipHistory&&sameConv 分支）
        loadSceneMedia()
        if (skipHistory && sameConv) return
        // 历史与好感并行（各省一个 RTT 的串行等待）
        loadChatHistory()
        loadAffection()
    }

})()

/* 顶栏分类（Girls / Anime / Guys）：Chat 页没有分类列表，点了统一回首页并选中对应类型 */
(function () {
    if (!/Chat\.html/i.test(location.pathname || '')) return
    document.addEventListener('click', function (e) {
        var a = e.target.closest ? e.target.closest('a.head-btn, #fyCatDdPanel a') : null
        if (!a) return
        var href = String(a.getAttribute('href') || '')
        var m = href.match(/[?&]cat=(girls|anime|guys)/i)
        var cat = m ? m[1].toLowerCase()
            : (/Anime/i.test(href) ? 'anime' : (/Guys/i.test(href) ? 'guys' : (/Girls/i.test(href) ? 'girls' : '')))
        if (!cat) return
        e.preventDefault()
        location.href = './Home.html?cat=' + cat
    }, true)
})()