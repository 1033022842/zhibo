(()=>{
    var base = 'http://127.0.0.1:8000/api/live'
    var token = localStorage.getItem('live_access_token')
    var typeArr = ['Realistic', 'Anime']
    var ageArr = ['18+', '20s', '30s', '40-55']
    var eyeArr = ['Brown', 'Blue', 'Green']
    var hairArr = ['Straight', 'Braids', 'Bangs', 'Curly', 'Bun', 'Short', 'Long', 'Ponytail', 'Pigtails']
    var hobbyArr = ['Fitness', 'Vlogging', 'Traveling', 'Hiking', 'Gaming', 
      'Parties', 'Series', 'Anime', 'Cosplay', 'Self-Development', 'Writing', 'Diy Crafting',
    'Veganism', 'Photography', 'Volunteering', 'Cars', 'Art', 'Watching Netflix', 'Manga And Anime', 'Martial Arts']
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
                liveUrl = '#'
                liveClass = 'pointer-events-none opacity-50'
                liveText = 'Preparing...'
              }
              
              var rendered = template.replace(/{{name}}/g, name)
              .replace(/{{age}}/g, ageLabel)
              .replace(/{{describe}}/g, describe)
              .replace(/{{id}}/g, t.id)
              .replace(/{{archive_id}}/g, '')
              .replace(/{{type}}/g, "role1")
              .replace(/{{photo}}/g, t.photo || '')
              .replace(/{{status}}/g, statusText)
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
    var base = 'http://127.0.0.1:8000/api/live';
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
                html += '<div style="color:#E75275;font-size:20px;">▶</div>';
                html += '</div>';
            });
            html += '</div>';

            layer.open({
                type: 1,
                title: '📹 ' + personaName + ' - 历史切片',
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
