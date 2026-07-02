(()=>{
    var base = 'http://127.0.0.1:8000/api/live'
    var token = localStorage.getItem('token')
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
              
              // status: 1=准备中, 2=已启用
              var statusText = t.status === 1 ? 'Preparing...' : t.status === 2 ? 'Active' : 'Disabled'
              var hasLiveRoom = t.room && t.room.id && t.room.status === 1
              
              var rendered = template.replace(/{{name}}/g, name)
              .replace(/{{age}}/g, ageLabel)
              .replace(/{{describe}}/g, describe)
              .replace(/{{id}}/g, t.id)
              .replace(/{{archive_id}}/g, '')
              .replace(/{{type}}/g, "role1")
              .replace(/{{photo}}/g, t.photo || '')
              .replace(/{{status}}/g, statusText)
              .replace(/{{liveUrl}}/g, hasLiveRoom ? '/home/live?roomId=' + t.room.id : '#')
              .replace(/{{liveClass}}/g, hasLiveRoom ? '' : 'pointer-events-none opacity-50')
              .replace(/{{liveText}}/g, hasLiveRoom ? 'View Live Stream' : 'Preparing...')
              
              $('#all_characters').append(rendered)
            });
          } else {
            if (res.code === "10003" || res.code === "10004") {
              layer.msg('Session expired, please log in again')
              localStorage.removeItem('token')
              localStorage.removeItem('userInfo')
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
})()
