(()=>{
    var base = 'http://127.0.0.1:8049'
       // 缓存对话模板
       var cacheTemplate = $('#messages-list').html()
       var token = localStorage.getItem('token')
     /** 
     * 获取指定的URL参数值 
     * URL:http://www.xxx.com/index?name=123
     * 参数：param URL参数 
     * 调用方法:getParam("name") 
     * 返回值:123
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
    }, 1000);
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