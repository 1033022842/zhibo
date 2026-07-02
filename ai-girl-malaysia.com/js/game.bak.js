(()=>{
     var base = 'http://127.0.0.1:8049'
     var slideshowItems = [];
     var token = localStorage.getItem('token')
     var durations = [3000, 7000, 13000]; // 每个项目的播放时间，单位为毫秒
     var currentIndex = -1; // 初始索引为 -1，因为点击按钮后会先增加到 0
     var bgMusic = document.getElementById("bgMusic");
     var pageList = []
     var audio = null
    function getParam(name) {  
		var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i");  
		var r = location.search.substring(1).match(reg);  
		if (r != null) return decodeURI(decodeURI(r[2])); 
	}
    var id = getParam('id')
    var type = getParam('type')
    if(type === 'role1') {
      customOneDetail(id)
      return
    } else if(type === 'role2'){
      customTwoDetail(id)
      return
    } else{
      loadData(id)
    }
    function loadData(id){
        var token = localStorage.getItem('token')
        var headers = {}
        if(token) {
            headers = {
                token
            }
        }
        const form = new FormData();
        form.append("id", id);
        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/user/channel_detail",
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
               
                if(res.data.group.length === 0) {
                    layer.msg('No data yet')
                   
                }
             
                
              initializeCarousel(res.data.buyrole.images)
              start()
            //   loadData(res.data.buyrole.user_id)
                
            //   pageList = res.data.group  
                pageList = res.data.group || []
                initBtn(res.data.group, id)
            } else {
                layer.msg(res.msg)
            }
        }).catch(error => console.error(error))
    }

    function customTwoDetail(id){
      var headers = {}
      if(token) {
        headers = {
          token
        }
      }
      var form = new FormData()
      form.append('id', id)
      const settings = {
        "async": true,
        "crossDomain": true,
        "url":  base + "/api/user/customTwoDetail",
        "method": "POST",
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
               
                if(res.data.group.length === 0) {
                    layer.msg('Please wait for AI to generate content')
                   
                }
             
                
              initializeCarousel(res.data.buyrole.images)
              start()
            //   loadData(res.data.buyrole.user_id)
                
            //   pageList = res.data.group  
                pageList = res.data.group || []
                initBtn(res.data.group, id)
            } else {
                layer.msg(res.msg)
            }
      }).catch(error => console.error(error))
    }
    function customOneDetail(id){
      var headers = {}
      if(token) {
        headers = {
          token
        }
      }
      var form = new FormData()
      form.append('id', id)
      const settings = {
        "async": true,
        "crossDomain": true,
        "url":  base + "/api/user/customOneDetail",
        "method": "POST",
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
               
                if(res.data.group.length === 0) {
                    layer.msg('Please wait for AI to generate content')
                   
                }
             
                
              initializeCarousel(res.data.buyrole.images)
              start()
            //   loadData(res.data.buyrole.user_id)
                
            //   pageList = res.data.group  
                pageList = res.data.group || []
                initBtn(res.data.group, id)
            } else {
                layer.msg(res.msg)
            }
      }).catch(error => console.error(error))
    }

    //初始化按钮
    function initBtn(data, id){
        var html = ''
        data.forEach((item, index) => {
            html += '<li class="mx-auto mt-6 md:mb-0 mb-10 grid max-w-2xl grid-cols-2 gap-5 sm:grid-cols-2 lg:mx-0 lg:max-w-none "><button class="dialogue-button" data-id="'+item.id+'">'+ item.description +'</button></li>'
        })
        $('#button-container').html(html)
        
        $('.dialogue-button').on('click', function () {
            var itemId = $(this).data('id')
          
            var item = pageList.find(t => {
                return t.id = itemId
            })
            
            //   console.log(item)
            checkAuth(item, id);
        })
    }
    function checkAuth(item, id) {
            // console.log(item)
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }else{
           layer.msg('Please log in first') 
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
            if(res.data.group_id === 2) {
                
                layer.confirm('Are you sure you want to buy？', {
                    title:"information",
                    btn : [ 'Sure', 'Cancel' ]//按钮
                }, function(index) {
                   
                var money = parseFloat(item.money)
                if(money < item.amount) {
                    layer.msg('The current balance is insufficient, please go to recharge')
                    return
                }else{
                     buyRole(item, id)
                }
          
                   
            
                });
                
                
				
             
                //var money = parseFloat(res.data.money)
                // if(money < item.amount) {
                //     alert('当前积分不足，请前往充值')
                // }
                // else{
                //     initializeCarousel(item.images)
                //     start()
                //     loadData(item.id)
                // }

            } else {
                 layer.msg('Only VIP users can use')
                // location.href="./Subscriptions.html"
            }
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => {
            console.error(error)
        })
    }

    function buyRole(item, id){
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const form = new FormData();
        form.append("id", id);
        form.append("schedule_id", item.id);
        form.append("type", type);
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/buy_role",
          "method": "POST",
          "headers": headers,
          "processData": false,
          "contentType": false,
          "mimeType": "multipart/form-data",
          'data': form
        };

        $.ajax(settings).done(function (response) {
          if(typeof(response) === 'string') {
            response = JSON.parse(response)
          }
          var res = response
           if(res.code === 1) {
               layer.msg(res.msg)
               setTimeout(() => {
            location.reload()
            }, 2000);
             
            //   initializeCarousel(item.images)
            //   start()
            //   loadData(item.user_id)
            //   layer.closeAll()
          } else {
          
            layer.msg(res.msg)
          }
        }).catch(error => {
            console.error(error)
        })
    }
    function createCarouselItem(item) {
        if(getFileType(item) === 'music') {
        //   var bg = document.getElementById('music-source')
        //   bg.src = item;
        if(audio) {
            audio.pause();
        }
        audio = null
        // 创建audio元素
            audio = new Audio(item);
            // 自动播放音乐
            audio.autoplay = true;
            // 循环播放
            audio.loop = true;
            
            // 为了兼容不同浏览器，可能需要监听一些事件
            audio.addEventListener('play', function() {
            // console.log('音乐播放中');
            }, false);
            audio.addEventListener('error', function() {
            // console.log('播放出错！');
            }, false);
          return null
        }
          const div = document.createElement('div');
          div.classList.add('fleassasax');
           div.classList.add("class1", "class2", "class3");
          if (getFileType(item) === 'image') {
               if (item.indexOf("http") == -1) {
     
              var kurl="http://localhost:8049/"+item
            }
              const img = document.createElement('img');
              img.src = kurl;
              img.alt = 'Carousel Image';
              slideshowItems.push(img)
              div.appendChild(img);
          } else if (getFileType(item) === 'video') {
              const video = document.createElement('video');
               if (item.indexOf("http") == -1) {
     
              var kurl="http://localhost:8049/"+item
            }
              video.src = kurl;
              video.controls = true;
              
            
                          
              slideshowItems.push(video)
              div.appendChild(video);
          }
          return div;
    }
    function getFileType(fileName) {
    const extension = fileName.slice((fileName.lastIndexOf(".") - 1 >>> 0) + 2);
    switch (extension.toLowerCase()) {
        case "jpg":
        case "jpeg":
        case "png":
        case "gif":
        case "bmp":
        case "webp":
        case "heic": // iOS 照片格式
        return "image";
        case "mp4":
        case "mov":
        case "m4v":
        case "mpg":
        case "mpeg":
        case "webm":
        case "avi":
        case "wmv":
        return "video";
        case "mp3":
        case "m4a":
        case "ogg":
        case "wav":
        case "flac":
        return "music";
        default:
        return "Unknown";
    }
    }
    
    function initializeCarousel(carouselData) {
        const carouselInner = document.getElementById('slideshow');
        var list = carouselData.split(',')
        list.forEach((item, index) => {
            const carouselItem = createCarouselItem(item);
            if (carouselItem) {
            if (index === 0) {
                carouselItem.classList.add('active');
            }
            carouselInner.appendChild(carouselItem);
            } 
        });
        start()
    }
    function showNextItem() {
    // 显示下一个项目之前先隐藏当前项目
    if (currentIndex >= 0) {
        slideshowItems[currentIndex].style.display = "none";

        // 如果当前项目是视频，则停止视频播放
        // if (slideshowItems[currentIndex].tagName === "VIDEO") {
        // slideshowItems[currentIndex].pause();
        // slideshowItems[currentIndex].currentTime = 0;
        // }
    }

    // 更新索引以显示下一个项目
    currentIndex = (currentIndex + 1) % slideshowItems.length;

    // 显示下一个项目
    slideshowItems[currentIndex].style.display = "block";

    // 如果下一个项目是视频，则自动播放视频
    // if (slideshowItems[currentIndex].tagName === "VIDEO") {
    //     bgMusic.pause(); // 暂停背景音乐
    //     slideshowItems[currentIndex].play(); // 播放视频
    //     slideshowItems[currentIndex].addEventListener('ended', function() {
    //     bgMusic.play(); // 视频播放结束后恢复背景音乐
    //     });
    // } else {
    //     // 否则继续播放背景音乐
    //     //bgMusic.play();
    // }

    // 计算下一个项目的显示时间
    var nextDuration = durations[currentIndex];

    // 设置定时器，等待显示下一个项目
    setTimeout(showNextItem, nextDuration);
    }
    function audioAutoPlay(audio){
      play = function(){
        audio.play();
        document.removeEventListener("touchstart",play, false);
      };

      audio.play();
      document.addEventListener("WeixinJSBridgeReady", function () {
        play();
      }, false);

      document.addEventListener('YixinJSBridgeReady', function() {
        play();
      }, false);

      document.addEventListener("touchstart",play, false);
    }
    function start() {
        // 隐藏所有项目
      slideshowItems.forEach(function(item) {
        item.style.display = "none";
      });
      showNextItem()
    }
})()