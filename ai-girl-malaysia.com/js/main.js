(()=>{
    var cacheTemplate = ''
    var base = '/api/live'
    var isSubmittingValue = false;

    // 订阅促销位：已是会员的用户不该再看到促销（顶栏 "Subscribe 75% OFF" 与首充横幅）
    var VIP_PROMO_SEL = '.nav-zk, a.w-full.h-14.cursor-pointer.z-10'
    var vipChecked = false
    var isVipUser = false

    function applyVipPromo(){
        if(!vipChecked) return
        if(isVipUser) $(VIP_PROMO_SEL).css('display', 'none')
    }

    function checkVipStatus(token){
        if(vipChecked || !token) return
        fetch('/api/vip/status', { headers: { Authorization: 'Bearer ' + token } })
            .then(function(r){ return r.json() })
            .then(function(d){
                var ok = d && (d.code === '00000' || d.code === 1)
                var data = (ok && d.data) ? d.data : {}
                isVipUser = !!data.is_vip
                vipChecked = true
                // 暴露给其它脚本：内容去模糊、聊天额度提示等
                window.LIVE_VIP = data
                document.dispatchEvent(new CustomEvent('live:vip', { detail: data }))
                applyVipPromo()
            })
            .catch(function(){ /* 拉取失败则保持原样，不影响页面 */ })
    }

    // 顶栏 tokens 余额胶囊：右上角新版导航形态，余额取用户真实代币数
    function loadTokenBalance(token){
        if(!token) return
        fetch('/api/live/userInfo', { method: 'POST', headers: { token: token } })
            .then(function(r){ return r.json() })
            .then(function(d){
                if(!d || (d.code !== 1 && d.code !== '00000')) return
                var money = (d.data && (d.data.money != null ? d.data.money : d.data.diamond_balance)) || 0
                $('[data-target="user-token-balance"]').text(money)
            })
            .catch(function(){ /* 拉取失败保持 0，不影响页面 */ })
    }

    /* 会话落地：access_token / refresh_token / 到期时间 一起存。
       旧代码只存了 access_token，2 小时过期后无法续期，只能重新登录 */
    function saveSession(data) {
        if (window.LiveAuth) { LiveAuth.save(data, true); return }
        if (data && data.access_token) { localStorage.setItem('live_access_token', data.access_token) }
    }

    function clearSession() {
        if (window.LiveAuth) { LiveAuth.clear(); return }
        localStorage.removeItem('live_access_token')
        localStorage.removeItem('live_user_info')
    }

    function initMain() {
        var token = localStorage.getItem('live_access_token')
        var usercode = localStorage.getItem('code')

        if(usercode){
            $('#user_code_registration').val(usercode) 
        }
         
        if(token) {
            $('#sign-in-modal').css('display', 'none')
            $('#user-setting').css('display', 'flex')
            $('.user-login').css('display', 'none')
            // 已登录：隐藏顶栏 Register / Login（顶栏与侧栏两处）
            $('.pc-rg-login').hide()
            $('.login-pf').css('display', 'block')
            checkVipStatus(token)
            loadTokenBalance(token)
            applyVipPromo()
        } else {
            $('.user-login').css('display', 'flex')
            $('.pc-rg-login').css('display', 'flex')
            $('#user-setting').css('display', 'none')
        }
        cacheTemplate = $('#profilesLayout').html();
        getChannelType()
    }

    setTimeout(function() { initMain() }, 100)
    // Turbo 导航后重新初始化
    $(document).on('turbo:load turbo:render', function() { initMain() })

    // 顶栏 tokens 胶囊下拉（新版导航形态，不依赖 Stimulus 控制器）
    $(document).on('click', '#token-chip-button', function(e) {
        e.stopPropagation()
        var $pop = $('#navbar-tokens-dropdown')
        var open = $pop.hasClass('hidden')
        $pop.toggleClass('hidden', !open)
        $('#token-chip-wrap').toggleClass('open', open)
        $(this).attr('aria-expanded', open ? 'true' : 'false')
    })
    $(document).on('click', function(e) {
        if($(e.target).closest('#token-chip-wrap').length) return
        $('#navbar-tokens-dropdown').addClass('hidden')
        $('#token-chip-wrap').removeClass('open')
        $('#token-chip-button').attr('aria-expanded', 'false')
    })
    $(document).on('keydown', function(e) {
        if(e.key !== 'Escape') return
        $('#navbar-tokens-dropdown').addClass('hidden')
        $('#token-chip-wrap').removeClass('open')
        $('#token-chip-button').attr('aria-expanded', 'false')
    })

    // 事件委托：兼容 Turbo Frame 异步加载
    $(document).on('click', '#signup-btn', function(e) {
      e.preventDefault();
      if (this.checkValidity()) { register() }
    })
    $(document).on('click', '#signin-btn', function(e) {
      e.preventDefault();
      if (this.checkValidity()) { login() }
    })
    $(document).on('click', '.logout, #logout', function() {
      clearSession()
      location.reload()
    })

      function handleClick(e3) {
        if (isSubmittingValue) {
          e3.preventDefault();
          return;
        }
        disableSubmitButton();
      }
      function disableSubmitButton() {
        isSubmittingValue = true;
        $('#profiles-layout button[type="submit"]').each(function(){
            $(this).removeClass('bg-[#434343] cursor-not-allowed')
            $(this).addClass('cursor-pointer bg-[#262626]')
            $(this).removeAttr("disabled");
        })
      }
      function addLoaderToButton() {
        
      }
       
      function makeAjaxRequest(method, url, data) {
        var base = '/'
        url = base + url
        headers = headers || {};
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
     
        // 设置自定义headers
        Object.keys(headers).forEach(function(header) {
            xhr.setRequestHeader(header, headers[header]);
        });
     
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4) {
                if (xhr.status === 200) {
                    console.log(xhr.responseText);
                } else {
                    console.error('Error: ' + xhr.status);
                }
            }
        };
      }
     
      // 邀请码：优先取注册表单里的「invitation Code」，否则用邀请链接带进来的 ?code（存在 localStorage）
      function inviteCode() {
        var code = $('#user_code_registration').val() || ''
        if (!code) {
          try { code = localStorage.getItem('code') || '' } catch (e) { code = '' }
        }
        return code
      }

      function register() {
        const form = new FormData();
        form.append("username", $('#user_username_registration').val());
        form.append("email", $('#user_email_registration').val());
        form.append("password", $('#user_password_registration').val());
        form.append("invite_code", inviteCode());

        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/registerFromAi",
          "method": "POST",
          "headers": {},
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };

        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === "00000") {
            var userinfo = {
              token: res.data.access_token,
              id: res.data.user.id,
              username: res.data.user.nickname,
              avatar: res.data.user.avatar,
              email: res.data.user.email || ''
            }
            localStorage.setItem('live_user_info', JSON.stringify(userinfo))
            saveSession(res.data)
            token = res.data.access_token
            // 转化事件：注册成功
            try { if (window.SugusTrack) SugusTrack.event('register') } catch (e) {}
           layer.msg('Registration successful')
            setTimeout(() => {
            window.location.replace(backUrl())
            }, 800);
          } else {
           layer.msg(res.msg);  
          }
        }).catch(error => console.error(error))
      }

      function login() {
        const form = new FormData();
        form.append("account", $('#user_email').val());
        form.append("password", $('#user_password').val());
        
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/login",
          "method": "POST",
          "headers": {},
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };

        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === "00000") {
            var userinfo = {
              token: res.data.access_token,
              id: res.data.user.id,
              username: res.data.user.nickname,
              avatar: res.data.user.avatar,
              email: res.data.user.email || ''
            }
            localStorage.setItem('live_user_info', JSON.stringify(userinfo))
            saveSession(res.data)
            token = res.data.access_token
            // 转化事件：登录成功（FB 上报为 Lead）
            try { if (window.SugusTrack) SugusTrack.event('login') } catch (e) {}
           layer.msg('Login successful')
            setTimeout(function() {
            var redirect = getUrlParam(window.location.href, 'redirect')
            if (redirect && redirect !== 'null') {
              window.location.replace(decodeURIComponent(redirect))
            } else {
              window.location.replace(backUrl())
            }
            }, 800);
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
      }

      function getChannelType(){
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/channelType",
          "method": "get",
          "headers": {},
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
        };

        $.get(base + "/channelType").then(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
		   // console.log(response.data)
          var res = response
           if(res.code === 1 || res.code === '00000') {
            if(res.data && !Array.isArray(res.data) && typeof(res.data) === 'object') {
              var template = $('#head-template').html();
              if (!template) return
			  // for (var i=0;i<res.data.length;i++)
			  // { 
			      
			  // }
                var rendered = template.replace("{{id}}", res.data.id)
                .replace("{{name}}", res.data.name);
        
                $('#head-btn-group').append(rendered);
            }
            else {
              $.each(res.data, function(index, item) {
                var template = $('#head-template').html();
        
                var rendered = template.replace("{{id}}", item.id)
                .replace("{{name}}", item.name);
        
                $('#head-btn-group').append(rendered);
              });
            }
            changeChannelType()
			 var currentUrl = window.location.href;
			
			 
			 const substringa = "Guys";
			 const substringb = "Anime";
			 const substringc = "Girls";
			  const substringd = "?code";
			  
			  const substringe = ".html";
			  
			
			  if (currentUrl.includes(substringa)) {
						
				 var sid =2
			  } else  if (currentUrl.includes(substringb)) {
				var sid =1
			  } else  if (currentUrl.includes(substringc)) {
				var sid =0
			  }else  if (currentUrl.includes(substringd)) {
			    
			     var code= getUrlParam(currentUrl,"code")
			     
			     if(code!==""&&code!==null&&code!=="null"){
			         var resultString = code.replace("#googtrans(en|zh-CN)", '');
			         
			        
			         	 localStorage.setItem('code', resultString)
		    	window.location.href='./Girls.html'
			     }
			    
		    
			  }else  if (!currentUrl.includes(substringd)&& !currentUrl.includes(substringe)) {
			       var sid =0
			  }
			  else{
			      var sid =99999
			  }
			 // console.log(currentUrl.includes(substringd))
			 // console.log(sid)
			 // return
			// window.location.href = './Guys.htmlm';
			
			// if(sid==0){
			// 	window.location.href = './Girls.html';
			// }
			if(sid!==99999){
			     $('.head-btn').eq(sid).trigger('click') 
			}
			
          
          } else {
            layer.msg(res.msg);  
           
          }
        }).catch(error => console.error(error))
      }
      
      function getUrlParam(url, paramName) {
          const reg = new RegExp('(^|&)' + paramName + '=([^&]*)(&|$)', 'i');
          const result = url.slice(url.indexOf('?') + 1).match(reg);
          if (result !== null) {
            return decodeURIComponent(result[2]);
          }
          return null;
        }

      // 登录成功后的去处：优先 URL 上的 ?redirect=，其次是「点登录前所在的页面」（同源 referrer），最后回 Home.html
      function backUrl() {
        var referrer = document.referrer || ''
        if (referrer) {
          try {
            var from = new URL(referrer)
            if (from.origin === window.location.origin && from.pathname.indexOf('Login.html') < 0) {
              return from.pathname + from.search
            }
          } catch (e) { /* 非法 referrer 直接走兜底 */ }
        }
        return './Home.html'
      }

      // 关闭登录/注册页（点 ×）：退回进入登录页之前的那个页面
      // 去向与登录成功后一致：?redirect= 优先，其次来源页(referrer)，最后 Home.html
      function backFromLogin() {
        var redirect = getUrlParam(window.location.href, 'redirect')
        if (redirect && redirect !== 'null') {
          try {
            var to = new URL(redirect, window.location.href)
            if (to.origin === window.location.origin && to.pathname.indexOf('Login.html') < 0) {
              return to.pathname + to.search
            }
          } catch (e) { /* 非法 redirect 忽略，走兜底 */ }
        }
        return backUrl()
      }
      window.liveBackFromLogin = backFromLogin

      function changeChannelType () {
		  var currentUrl = window.location.href;
		 
		  
		  const substringa = "Guys";
		  const substringb = "Anime";
		  const substringc = "Girls";
		  

		  
		  
        $('.head-btn').on('click', function(){
          $('.head-btn .border-rose-400 .text-rose-400').removeClass('text-rose-400').addClass('text-white')
          $('.head-btn .border-rose-400').removeClass('border-rose-400').addClass('border-transparent')
          $(this).addClass('border-rose-400').removeClass('border-transparent')
          $(this).find('.text-white').addClass('text-rose-400').removeClass('text-white')
          $(this).find('path').attr('fill', '#F97187')
          var id = $(this).data('id')
	
	// console.log($(this).data('url'))
	// if(id==35){
	// 	window.location.href = './Guys.html';
	// }
	
	 if (!currentUrl.includes(substringa) && id==35) {
		 window.location.href = './Guys.html';
	 }
	 if (!currentUrl.includes(substringb) && id==36) {
	 		 window.location.href = './Anime.html';
	 }
	 if (!currentUrl.includes(substringa) && id==35) {
	 		 window.location.href = './Guys.html';
	 }
	 if (!currentUrl.includes(substringc) && id==27) {
	 		 window.location.href = './Girls.html';
	 }


          getPageList(id)
        })
      }
      function getPageList(id) {
        var headers = {}
        if(token) {
          headers = {
            Authorization: 'Bearer ' + token
          }
        }
        const form = new FormData();
        form.append("page", 1);
        form.append("type", id);
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/pagelist",
          "method": "post",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1 || res.code === '00000') {
            localStorage.setItem('pageList', JSON.stringify(res.data.pagelist.data))
            getInfoList(res.data.pagelist.data)
          } else {
            layer.msg(res.msg);  
           
          }
        }).catch(error => console.error(error))
      }
      function getInfoList(data) {
        $('#profilesLayout').html(cacheTemplate)
        
          $.each(data, function(index, item) {
            var template = $('#list-template').html();
            if(!template) return
            var rendered = template.replace("{{shortName}}", item.title)
            .replace("{{imgUrl}}", item.image.split(',')[0])
            .replace("{{imgUrl1}}", item.image.split(',')[1])
            .replace("{{infomation}}", item.description)
            .replace("{{age}}", item.price)
            .replace("{{id}}", item.id);
    
            $('#profilesLayout').append(rendered);
          });
      }

      /* ==================== Google 登录（GIS 令牌模式） ====================
         原站设计里登录/注册按钮下方就是「or sign in with」分割线 + 白色 Google 按钮，
         这里按同款位置/样式注入；Client ID 由后端 /api/live/googleClientId 下发，
         未配置时不出按钮，页面表现与之前一致。 */
      var GOOGLE_GSI_SRC = 'https://accounts.google.com/gsi/client'
      var googleClientId = ''
      var googleTokenClient = null
      var GOOGLE_BOX_SEL = '.google-login-box'
      var GOOGLE_BOX_CSS = [
        '.google-login-box{margin:32px 0 0;}',
        '.google-login-box.is-signup{margin:24px 0 0;}',
        '.google-login-divider{display:flex;flex-direction:row;align-items:center;}',
        '.google-login-divider i{flex:1;height:1px;}',
        '.google-login-divider i.l{background:linear-gradient(270deg,rgba(255,255,255,.30) 0%,rgba(255,255,255,0) 100%);}',
        '.google-login-divider i.r{background:linear-gradient(270deg,rgba(255,255,255,0) 0%,rgba(255,255,255,.30) 100%);}',
        '.google-login-divider em{font-style:normal;color:#fff;font-size:14px;font-weight:500;margin:0 16px;}',
        '.google-login-btn{display:inline-flex;align-items:center;justify-content:center;width:100%;margin:20px 0 0;padding:10px 16px;background:#fff;border:0;border-radius:10px;color:#344054;font-size:14px;font-weight:400;line-height:24px;cursor:pointer;}',
        '.google-login-btn img{width:24px;height:24px;margin-right:12px;}',
        '.google-login-btn:hover{background:#f2f2f2;}'
      ].join('')

      function googleMsg(text) {
        if (window.layer && typeof layer.msg === 'function') { layer.msg(text) } else { alert(text) }
      }

      function googleAfterLogin(response) {
        if (typeof(response) === 'string') { response = JSON.parse(response) }
        var res = response
        if (!res || res.code !== '00000' || !res.data || !res.data.access_token) {
          googleMsg((res && res.msg) || 'Login failed')
          return
        }
        var userinfo = {
          token: res.data.access_token,
          id: res.data.user.id,
          username: res.data.user.nickname,
          avatar: res.data.user.avatar,
          email: res.data.user.email || ''
        }
        localStorage.setItem('live_user_info', JSON.stringify(userinfo))
        saveSession(res.data)
        token = res.data.access_token
        // 转化事件：Google 登录成功
        try { if (window.SugusTrack) SugusTrack.event('login') } catch (e) {}
        googleMsg('Login successful')
        setTimeout(function() {
          var redirect = getUrlParam(window.location.href, 'redirect')
          if (redirect && redirect !== 'null') {
            window.location.replace(decodeURIComponent(redirect))
          } else {
            window.location.replace(backUrl())
          }
        }, 800)
      }

      function googleLogin(accessToken) {
        const form = new FormData();
        form.append("access_token", accessToken);

        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/googleLogin",
          "method": "POST",
          "headers": {},
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          "data": form
        };

        $.ajax(settings).done(function (response) {
          googleAfterLogin(response)
        }).catch(function (error) {
          console.error(error)
          googleMsg('Google login failed')
        })
      }

      function initGoogleTokenClient() {
        if (!(window.google && google.accounts && google.accounts.oauth2)) return false
        googleTokenClient = google.accounts.oauth2.initTokenClient({
          client_id: googleClientId,
          scope: 'openid email profile',
          callback: function (tokenResponse) {
            if (tokenResponse && tokenResponse.access_token) {
              googleLogin(tokenResponse.access_token)
            } else if (tokenResponse && tokenResponse.error && tokenResponse.error !== 'access_denied') {
              googleMsg('Google login failed')
            }
          }
        })
        return true
      }

      function loadGoogleScript() {
        return new Promise(function (resolve, reject) {
          if (window.google && window.google.accounts) { resolve(); return }
          var script = document.getElementById('google-gsi-script')
          if (!script) {
            script = document.createElement('script')
            script.id = 'google-gsi-script'
            script.src = GOOGLE_GSI_SRC
            script.async = true
            script.defer = true
            document.head.appendChild(script)
          }
          script.addEventListener('load', function () { resolve() })
          script.addEventListener('error', function () { reject(new Error('google gsi load failed')) })
        })
      }

      function onGoogleButtonClick(e) {
        e.preventDefault()
        if (googleTokenClient) { googleTokenClient.requestAccessToken(); return }
        loadGoogleScript().then(function () {
          if (initGoogleTokenClient()) { googleTokenClient.requestAccessToken() }
        }).catch(function () { googleMsg('Google login unavailable') })
      }

      function mountGoogleLogin() {
        if (!googleClientId) return
        if (!$('#google-login-style').length) {
          var style = document.createElement('style')
          style.id = 'google-login-style'
          style.textContent = GOOGLE_BOX_CSS
          document.head.appendChild(style)
        }
        $(GOOGLE_BOX_SEL).remove()
        var targets = [
          { btn: $('#signin-btn'), label: 'or sign in with', cls: '' },
          { btn: $('#signup-btn'), label: 'or continue with', cls: ' is-signup' }
        ]
        targets.forEach(function (target) {
          if (!target.btn.length) return
          target.btn.after(
            '<div class="google-login-box' + target.cls + '">' +
              '<div class="google-login-divider"><i class="l"></i><em>' + target.label + '</em><i class="r"></i></div>' +
              '<button type="button" class="google-login-btn"><img src="./assets/google-auth.png" alt="Google"><span>Google</span></button>' +
            '</div>'
          )
        })
      }

      function initGoogleLogin() {
        if (!$('#signin-btn').length && !$('#signup-btn').length) return
        if (googleClientId) { mountGoogleLogin(); return }
        $.get(base + '/googleClientId').done(function (response) {
          if (typeof(response) === 'string') { response = JSON.parse(response) }
          var res = response
          if (!res || (res.code !== '00000' && res.code !== 1)) return
          googleClientId = (((res.data || {}).client_id) || '').trim()
          if (!googleClientId) return
          mountGoogleLogin()
          loadGoogleScript().then(initGoogleTokenClient).catch(function () { /* 脚本不可达时保留按钮，点击再试 */ })
        }).catch(function () { /* 接口不可用时静默降级 */ })
      }

      $(document).on('click', '.google-login-btn', onGoogleButtonClick)
      setTimeout(initGoogleLogin, 120)
      $(document).on('turbo:load turbo:render', function () { initGoogleLogin() })
      $(document).on('click', '[data-action*="openSignInModal"], [data-action*="openRegistrationModal"]', function () { initGoogleLogin() })

})()
