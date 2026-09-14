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
        channelDetail(id)
        $('#message-list').find('a').on('click', function(){
            $('#message-list').find('a').each(function() {
                $(this).removeClass('px-[6px] bg-[#303030] border border-zinc-600');
              });
            $(this).addClass('px-[6px] bg-[#303030] border border-zinc-600')
            getDetail($(this).data('id'))
        })
        $('#message-list').find('a').eq(0).trigger('click')
        if(token) setupSignedInChat()
    }, 1000);

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

    function sendMessage() {
        var input = document.getElementById('message_body')
        if (!input) return
        var text = (input.value || '').trim()
        if (text === '' || sending) return
        sending = true
        input.value = ''

        appendBubble(text, true)
        var typing = appendTyping()

        var conv = currentConversation()
        var contentId = /^\d+$/.test(String(conv.id)) ? String(conv.id) : '0'
        var persona = currentPersona()

        var fd = new FormData()
        fd.append('message', text)
        fd.append('content_id', contentId)
        fd.append('device_id', deviceId())
        fd.append('lang', (document.documentElement.getAttribute('lang') || 'en').slice(0, 2))
        if (contentId === '0') {
            fd.append('persona_name', persona.name || '')
            fd.append('persona_desc', persona.desc || '')
        }
        var headers = {}
        if (token) headers.Authorization = 'Bearer ' + token

        fetch('/api/live/chat', { method: 'POST', headers: headers, body: fd })
            .then(function (r) { return r.json() })
            .then(function (d) {
                if (typing) typing.remove()
                if (d && d.code === '00000' && d.data && d.data.reply) {
                    appendBubble(d.data.reply, false)
                } else {
                    appendError((d && d.msg) ? ('发送失败：' + d.msg) : '发送失败，请稍后重试')
                }
            })
            .catch(function () {
                if (typing) typing.remove()
                appendError('网络错误，请稍后重试')
            })
            .then(function () { sending = false })
    }

    function setupSignedInChat() {
        // 让页面的 Stimulus 控制器知道已登录，避免走「弹注册框」分支
        document.body.setAttribute('data-main-is-current-user-signed-in', 'true')

        // 切到登录态的发送按钮（游客版会弹注册框）
        var guestBtn = document.getElementById('send-question')
        var realBtn = document.getElementById('send-question-with-token')
        if (guestBtn) guestBtn.classList.add('hidden')
        if (realBtn) realBtn.classList.remove('hidden')

        var inChat = function (node) {
            var area1 = document.getElementById('messages')
            var area2 = document.getElementById('form')
            return (area1 && area1.contains(node)) || (area2 && area2.contains(node))
        }

        // 聊天区里「点击弹注册框」的元素：改成直接发消息
        document.addEventListener('click', function (e) {
            var el = e.target && e.target.closest ? e.target.closest('[data-action*="openRegistrationModal"]') : null
            if (!el || !inChat(el)) return
            e.preventDefault()
            e.stopPropagation()
            hideAuthModals()
            sendMessage()
        }, true)

        // 输入框回车：不再弹框，直接发送
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' || !e.target || e.target.id !== 'message_body') return
            e.preventDefault()
            e.stopPropagation()
            sendMessage()
        }, true)

        if (realBtn) {
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