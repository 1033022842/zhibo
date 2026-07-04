(()=>{
    var cacheTemplate = ''
    var base = 'http://127.0.0.1:8000/api/live'
    var token = localStorage.getItem('live_access_token')
	
	var userJson = localStorage.getItem('live_user_info')
	var usercode = localStorage.getItem('code')
	var user = JSON.parse(userJson)
    var isSubmittingValue = false;
      setTimeout(() => {
        //   $('.headerfy').load('./header/header.html');
		  if(usercode){
		     $('#user_code_registration').val(usercode) 
		  }
		  
        if(token) {
          $('#sign-in-modal').css('display', 'none')
                $('#user-setting').css('display', 'flex')
                $('.user-login').css('display', 'none')
				 // $('#useravatar').attr("src",user.avatar);
				 	$('.login-pf').css('display', 'block')
        }else{
            $('.user-login').css('display', 'flex')
             $('#user-setting').css('display', 'none')
        }
        cacheTemplate = $('#profilesLayout').html();
        getChannelType()
        $('#profiles-layout button[type="submit"]').on('click', function(e) {
            handleClick(e)
            $(this).removeClass('bg-[#262626] cursor-pointer')
            $(this).addClass('bg-[#434343] cursor-not-allowed')
            $(this).attr("disabled","");
            getInfoList()
            isSubmittingValue = false;
        })
        $('.logout').on('click', function () {
          localStorage.removeItem('live_access_token')
          localStorage.removeItem('live_user_info')
          location.reload()
        })
         $('#logout').on('click', function () {
          localStorage.removeItem('live_access_token')
          localStorage.removeItem('live_user_info')
          location.reload()
        })
        getInfoList()
        $('#signup-btn').on('click', function(e) {
          // 阻止表单默认提交行为
           e.preventDefault();
         
           // 触发表单验证
           if (this.checkValidity()) {
             // 表单验证通过，可以执行提交操作
             console.log('Form is valid, submitting...');
             // this.submit();
             register()
           } else {
             // 表单验证失败，可以处理错误
             console.error('Form is invalid. Fix errors before submitting.');
           }
       })

       $('#signin-btn').on('click', function (e) {
        // 阻止表单默认提交行为
        e.preventDefault();
         
        // 触发表单验证
        if (this.checkValidity()) {
          // 表单验证通过，可以执行提交操作
          console.log('Form is valid, submitting...');
          login()
          // this.submit();
        } else {
          // 表单验证失败，可以处理错误
          console.error('Form is invalid. Fix errors before submitting.');
        }
       })
      }, 100);

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
        var base = 'http://127.0.0.1:8049/'
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
     
      function register() {
        const form = new FormData();
        form.append("username", $('#user_username_registration').val());
        form.append("email", $('#user_email_registration').val());
        form.append("password", $('#user_password_registration').val());

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
            localStorage.setItem('live_access_token', res.data.access_token)
            token = res.data.access_token
           layer.msg(res.msg || '注册成功')
            setTimeout(() => {
            location.reload()
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
            localStorage.setItem('live_access_token', res.data.access_token)
            token = res.data.access_token
           layer.msg(res.msg || '登录成功')
            setTimeout(function() {
              var redirect = getUrlParam(window.location.href, 'redirect')
              window.location.href = redirect && redirect !== 'null' ? decodeURIComponent(redirect) : './Girls.html'
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
           if(res.code === 1) {
            if(typeof(res.data) === 'object') {
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
           if(res.code === 1) {
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
      
})()
