(()=>{
    var base = '/api/live'
    var token = localStorage.getItem('live_access_token')
    var typeArr = ['Realistic', 'Anime']
    var ageArr = ['18+', '20s', '30s', '40-55']
    var eyeArr = ['Brown', 'Blue', 'Green']
    var hairArr = ['Straight', 'Braids', 'Bangs', 'Curly', 'Bun', 'Short', 'Long', 'Ponytail', 'Pigtails']
    var hobbyArr = ['Fitness', 'Vlogging', 'Traveling', 'Hiking', 'Gaming', 
      'Parties', 'Series', 'Anime', 'Cosplay', 'Self-Development', 'Writing', 'Diy Crafting',
    'Veganism', 'Photography', 'Volunteering', 'Cars', 'Art', 'Watching Netflix', 'Manga And Anime', 'Martial Arts']

    // Intercept Create Character link, check merchant certification
    document.addEventListener('click', function(e) {
        var link = e.target.closest('a[href*="characters.html"]')
        if (!link) return
        if (!token) {
            e.preventDefault()
            layer.msg('Please log in first')
            setTimeout(function(){ location.href = './Login.html' }, 800)
            return
        }
        // Check merchant certification status
        e.preventDefault()
        layer.load(1)
        fetch('/api/v1/merchant/status', {
                headers: { 'Authorization': 'Bearer ' + token }
            }).then(function(r){ return r.json() }).then(function(d){
                layer.closeAll('loading')
                // Token invalid, go to login
                if (d.code && d.code !== '00000') {
                    layer.msg('Login expired, please log in again')
                    setTimeout(function(){ location.href = './Login.html' }, 800)
                    return
                }
                var status = (d.data && d.data.cert_status != null) ? d.data.cert_status : -1
            if (status === 1) {
                location.href = './characters.html'
            } else {
                layer.open({
                    type: 1,
                    title: false,
                    closeBtn: 0,
                    skin: 'mvp-layer',
                    shade: 0.65,
                    shadeClose: true,
                    area: ['380px', 'auto'],
                    content:
                        '<style>' +
                        '.mvp-layer{background:transparent!important;border:none!important;box-shadow:none!important;border-radius:20px!important;overflow:visible!important}' +
                        '.mvp-layer .layui-layer-content{background:transparent!important;overflow:visible!important}' +
                        '.mvp{padding:34px 26px 22px;border-radius:20px;text-align:center;border:1px solid rgba(255,255,255,.08);background:radial-gradient(120% 90% at 50% -20%,rgba(231,82,117,.20),transparent 60%),linear-gradient(180deg,#1c1a24,#141119);box-shadow:0 24px 60px rgba(0,0,0,.5);font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif}' +
                        '.mvp__icon{width:64px;height:64px;margin:0 auto 18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:30px;line-height:1;background:linear-gradient(135deg,rgba(231,82,117,.22),rgba(231,82,117,.05));border:1px solid rgba(231,82,117,.35)}' +
                        '.mvp__title{font-size:18px;font-weight:700;color:#fff;margin-bottom:10px}' +
                        '.mvp__desc{font-size:13.5px;line-height:1.6;color:rgba(255,255,255,.55);margin-bottom:24px}' +
                        '.mvp__btn{display:block;width:100%;padding:13px;border:none;border-radius:26px;font-size:15px;font-weight:700;color:#fff;cursor:pointer;background:linear-gradient(135deg,#e75275,#c9336a);box-shadow:0 8px 24px rgba(231,82,117,.35);transition:transform .15s ease,box-shadow .15s ease}' +
                        '.mvp__btn:hover{transform:translateY(-1px);box-shadow:0 10px 28px rgba(231,82,117,.45)}' +
                        '.mvp__btn:active{transform:translateY(0)}' +
                        '.mvp__later{display:block;width:100%;margin-top:10px;padding:8px;border:none;background:none;color:rgba(255,255,255,.4);font-size:13px;cursor:pointer;transition:color .15s ease}' +
                        '.mvp__later:hover{color:rgba(255,255,255,.7)}' +
                        '@media (max-width:460px){.mvp{padding:28px 20px 18px}}' +
                        '</style>' +
                        '<div class="mvp">' +
                        '<div class="mvp__icon">&#128737;&#65039;</div>' +
                        '<div class="mvp__title">Verification Required</div>' +
                        '<div class="mvp__desc">To create and customize your own AI characters, please complete merchant verification first.</div>' +
                        '<button class="mvp__btn" onclick="location.href=\'./certification.html\'">Verify Now</button>' +
                        '<button class="mvp__later" onclick="layer.closeAll(\'page\')">Maybe later</button>' +
                        '</div>',
                    btn: []
                })
            }
        }).catch(function(){
            layer.closeAll('loading')
            location.href = './characters.html'
        })
    })
    setTimeout(() => {
      var template = $('#role-template').html();
        customOneList(template)
    }, 1000);
    
    function customOneList(template){
        var headers = {}
        if(token) {
          headers = {
            'Authorization': 'Bearer ' + token
          }
        } else {
          layer.msg('Please log in first')
          setTimeout(() => { location.href = './Login.html' }, 800)
          return
        }
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/customOneList",
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
           if(res.code === "00000") {
            if (!template) return
            var list = res.data
            if (!list || list.length === 0) return
            
            list.forEach(function(t) {
              var sf = t.source_fields || {}
              var name = t.name
              // Build display info from source_fields
              var ageLabel = sf.age !== undefined ? ageArr[sf.age] + ' years' : ''
              var hairLabel = sf.hair !== undefined ? hairArr[sf.hair] : ''
              var eyeLabel = sf.eye !== undefined ? 'eye: ' + eyeArr[sf.eye] : ''
              var hobbyLabel = sf.hobby !== undefined ? 'hobby: ' + hobbyArr[sf.hobby] : ''
              var describe = [eyeLabel, hobbyLabel, hairLabel].filter(Boolean).join('. ')
              
              // room status: 0=closed, 1=live, 2=maintenance
              var hasLiveRoom = t.room && t.room.id && t.room.status === 1
              var isMaintenance = t.room && t.room.id && t.room.status === 2
              
              var liveUrl, liveClass, liveText
              if (hasLiveRoom) {
                liveUrl = '/home/live?roomId=' + t.room.id
                liveClass = ''
                liveText = 'View Live Stream'
              } else if (isMaintenance) {
                liveUrl = '#'
                liveClass = 'pointer-events-none opacity-50'
                liveText = 'Under maintenance...'
              } else {
                // 聊天伴侣角色（无直播间）：直接进 Chat
                liveUrl = './Chat.html?id=my-' + t.id
                liveClass = ''
                liveText = 'Chat now ⚡'
              }
              
              var rendered = template.replace(/{{name}}/g, name)
              .replace(/{{age}}/g, ageLabel)
              .replace(/{{describe}}/g, describe)
              .replace(/{{id}}/g, t.id)
              .replace(/{{archive_id}}/g, '')
              .replace(/{{type}}/g, "role1")
              .replace(/{{photo}}/g, (t.photo || '').replace(':8000/', ':8001/'))
              .replace(/{{liveUrl}}/g, liveUrl)
              .replace(/{{liveClass}}/g, liveClass)
              .replace(/{{liveText}}/g, liveText)
              
              $('#all_characters').append(rendered)
            });
          } else {
            if (res.code === "10003" || res.code === "10004") {
              layer.msg('Session expired, please log in again')
              localStorage.removeItem('live_access_token')
              localStorage.removeItem('live_user_info')
              setTimeout(() => { location.href = './Login.html' }, 800)
            } else {
              layer.msg(res.msg || 'Failed to load characters')
            }
          }
        }).catch(function(error) {
          console.error(error)
          layer.msg('Network error, please try again later')
        })
      }
})();

// Global function for replay clips modal (called from onclick in HTML template)
function showReplayClips(personaId, personaName) {
    var base = '/api/live';
    var token = localStorage.getItem('live_access_token');
    if (!token) {
        layer.msg('Please log in first');
        return;
    }

    // Show loading
    var loadIdx = layer.load(1);

    $.ajax({
        url: base + '/replayClips',
        method: 'GET',
        data: { persona_id: personaId },
        headers: { 'Authorization': 'Bearer ' + token },
        success: function(response) {
            layer.close(loadIdx);
            if (typeof response === 'string') response = JSON.parse(response);
            if (response.code !== '00000') {
                layer.msg(response.msg || 'Failed to load clips');
                return;
            }
            var clips = response.data || [];
            if (clips.length === 0) {
                layer.msg('No replay clips yet');
                return;
            }

            // Build modal HTML
            var html = '<div style="max-height:400px;overflow-y:auto;padding:10px 0;">';
            clips.forEach(function(c) {
                var d = c.live_date || '';
                var dur = c.duration ? Math.floor(c.duration / 60) + 'm ' + (c.duration % 60) + 's' : '';
                html += '<div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #333;cursor:pointer;" onclick="window.open(\'' + c.video_url + '\', \'_blank\')">';
                html += '<div><div style="color:#fff;font-size:14px;font-weight:500;">' + c.title + '</div>';
                html += '<div style="color:#888;font-size:12px;margin-top:4px;">' + d + (dur ? ' · ' + dur : '') + '</div></div>';
                html += '<div style="color:#E75275;font-size:20px;">&#9654;</div>';
                html += '</div>';
            });
            html += '</div>';

            layer.open({
                type: 1,
                title: '📹 ' + personaName + ' - Replay Clips',
                content: html,
                area: ['500px', 'auto'],
                skin: 'layui-layer-demo',
                shadeClose: true,
            });
        },
        error: function() {
            layer.close(loadIdx);
            layer.msg('Network error');
        }
    });
}
