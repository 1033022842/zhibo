(()=>{
    var base = 'https://websocket.trd.lat'
    var slideshowItems = [];
    var token = localStorage.getItem('token')
    var durations = [3000, 7000, 13000]; // 每个项目的播放时间，单位为毫秒
    var currentIndex = -1; // 初始索引为 -1，因为点击按钮后会先增加到 0
    var bgMusic = document.getElementById("bgMusic");
    var pageList = []
    var audio = null
    var loadingCount = 0; // 用于跟踪正在进行的请求数量
    
    function getParam(name) {  
		var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");  
		var r = location.search.substring(1).match(reg);  
		if (r != null) return decodeURI(decodeURI(r[2])); 
	}
    var id = getParam('id')
    var type = getParam('type')

    // 显示loading
    function showLoading(text) {
        loadingCount++;
        const loadingEl = document.getElementById('loading-overlay');
        const loadingText = document.getElementById('loading-text');
        if (loadingEl) {
            loadingEl.style.display = 'flex';
        }
        if (loadingText && text) {
            loadingText.textContent = text;
        }
    }
    
    // 隐藏loading
    function hideLoading() {
        loadingCount--;
        if (loadingCount <= 0) {
            loadingCount = 0;
            const loadingEl = document.getElementById('loading-overlay');
            if (loadingEl) {
                loadingEl.style.display = 'none';
            }
        }
    }

    // 检查token是否存在
    if(token) {
        // 检查token是否过期
        var tokenExpire = localStorage.getItem('tokenExpire')
        if(tokenExpire) {
            var now = new Date().getTime()
            if(now > tokenExpire) {
                // 如果token过期，则重新登录
                localStorage.removeItem('token')
                localStorage.removeItem('userInfo')
                location.reload()
            }
        }
        getUserInfo()
    }else{
        location.href = './Login.html'
    }

    
    function loadData(id, retry = false){
        showLoading('Get video information...'); // 显示loading
        
        var token = localStorage.getItem('token')
        var headers = {}
        if(token) {
            headers = {
                token
            }
        }

        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/characters/" + id + "/default_video",
            "method": "get",
            "headers": headers,
            "processData": false,
            "contentType": false,
            "mimeType": "multipart/form-data",
        };
        $.ajax(settings).done(function (response) {
            if(typeof(response) === 'string') {
                response = JSON.parse(response)
            }
            var res = response
            if(res.code === 1) {
                var video = document.getElementById('videoA')
                if (video) {
                    // 更新loading文字为视频加载状态
                    const loadingText = document.getElementById('loading-text');
                    if (loadingText) {
                        loadingText.textContent = 'Loading video...';
                    }
                    
                    video.src = res.video_file
                    document.getElementById('defaultVideo').value = res.video_file
                    
                    // 监听video加载事件
                    video.addEventListener('loadeddata', function() {
                        console.log('视频加载完成');
                        video.play();
                        hideLoading(); // 视频加载完成后再隐藏loading
                    }, { once: true }); // 只监听一次
                    
                    // 监听video错误事件
                    video.addEventListener('error', function(e) {
                        console.error('视频加载失败:', e);
                        hideLoading(); // 视频加载失败也要隐藏loading
                    }, { once: true });
                    
                    // 设置超时，防止视频加载时间过长
                    setTimeout(function() {
                        if (video.readyState < 2) { // HAVE_CURRENT_DATA
                            console.log('视频加载超时，强制隐藏loading');
                            hideLoading();
                        }
                    }, 10000); // 10秒超时
                    
                } else {
                    console.error('找不到 video 元素')
                    hideLoading(); // 找不到video元素时隐藏loading
                }
            } else if(res.code === 0 && res.msg === "Role does not exist" && !retry) {
                // 失败且未重试过，延迟300ms后重试一次
                setTimeout(function() {
                    loadData(id, true)
                }, 300)
            } else {
                layer.msg(res.msg)
                hideLoading(); // 请求失败时隐藏loading
            }
        }).catch(error => {
            console.error(error)
            hideLoading(); // 请求异常时隐藏loading
        })
    }


    function getCharacterInfo(id){
        showLoading('Get role information...'); // 显示loading
        
        var token = localStorage.getItem('token')
        var headers = {}
        if(token) {
            headers = {
                token   
            }
        }
        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/characters/" + id + "/info",
            "method": "get",
            "headers": headers,
            "processData": false,
            "contentType": false,
            "mimeType": "multipart/form-data",
        };
        $.ajax(settings).done(function (response) {
            if(typeof(response) === 'string') {
                response = JSON.parse(response)
            }
            var res = response
            if(res.code === 1) {
                localStorage.setItem('characterInfo', JSON.stringify(res.character_info))
                document.getElementById('characterName').value = res.character_info.title
                
                // 如果 WebSocket 已连接，立即切换角色
                if (window.isConnected && window.switchCharacter) {
                    window.switchCharacter(res.character_info.title);
                }
            } else {    
                layer.msg(res.msg)
            }
            hideLoading(); // 隐藏loading
        }).catch(error => {
            console.error(error)
            hideLoading(); // 隐藏loading
        })
    }

    function getUserInfo(){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/user_info",
          "method": "POST",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
        };
        
        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
            localStorage.setItem('userInfo', JSON.stringify(res.data))
            
            // location.reload()
          } else {
           layer.msg(res.msg);  
          }
        }).catch(error => console.error(error))
       }

    // 等待 DOM 加载完成后再执行
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            loadData(id)
            getCharacterInfo(id)
        })
    } else {
        loadData(id)
        getCharacterInfo(id)
    }
})()