(()=>{
    var base = 'http://127.0.0.1:8000/api/live'
    var token = localStorage.getItem('live_access_token')
	
	// var userJson = localStorage.getItem('userInfo')
	
	// var user = JSON.parse(userJson)
    var isSubmittingValue = false;
    var ageArr = ['18+', '20s', '30s', '40-55']
      setTimeout(() => {
          
          $("#right-side").addClass("notranslate")
          $('.headerfy').load('./header/header.html');
        if(token) {
             if(window.screen.width >1000){
              $(".van-popover--light").css("left","72%")
        }
  
          $('#sign-in-modal').css('display', 'none')
            $('#user-setting').css('display', 'flex')
            $('.user-login').css('display', 'none!important')
			$('.login-pf').css('display', 'block')
			 
        }else{
            $('.user-login').css('display', 'flex')
            $('#user-setting').css('display', 'none')
        }
        cacheTemplate = $('#profilesLayout').html();
        $('.logout').on('click', function () {
          localStorage.removeItem('live_access_token')
          localStorage.removeItem('live_user_info')
          layer.msg("Logout successful")
           setTimeout(() => {
            location.reload()
            }, 800);
        })
         $('#logout').on('click', function () {
          localStorage.removeItem('live_access_token')
          localStorage.removeItem('live_user_info')
          layer.msg("Logout successful")
           setTimeout(() => {
            location.reload()
            }, 800);
       
        })
       
    //     $('#signup-btn').on('click', function(e) {
    //       // 阻止表单默认提交行为
    //       e.preventDefault();
         
    //       // 触发表单验证
    //       if (this.checkValidity()) {
    //          // 表单验证通过，可以执行提交操作
    //          console.log('Form is valid, submitting...');
    //          // this.submit();
    //         //  register()
    //       } else {
    //          // 表单验证失败，可以处理错误
    //          console.error('Form is invalid. Fix errors before submitting.');
    //       }
    //   })

    //   $('#signin-btn').on('click', function (e) {
    //     // 阻止表单默认提交行为
    //     e.preventDefault();
         
    //     // 触发表单验证
    //     if (this.checkValidity()) {
    //       // 表单验证通过，可以执行提交操作
    //       console.log('Form is valid, submitting...');
    //       login()
    //       // this.submit();
    //     } else {
    //       // 表单验证失败，可以处理错误
    //       console.error('Form is invalid. Fix errors before submitting.');
    //     }
    //   })
      initOp()
      }, 100);

    //   function register() {
    //     const form = new FormData();
    //     form.append("email", $('#user_email_registration').val());
    //     form.append("password", $('#user_password_registration').val());
    //      form.append("code", $('#user_code_registration').val())

    //     const settings = {
    //       "async": true,
    //       "crossDomain": true,
    //       "url":  base + "/api/user/register",
    //       "method": "POST",
    //       "headers": {},
    //       "processData": false,
    //       "contentType": false,
    //       "mimeType": "multipart/form-data",
    //       "data": form
    //     };

    //     $.ajax(settings).done(function (response) {
    //       if(typeof(response) === 'string') {
    //         response = JSON.parse(response)
    //       }
    //       var res = response
    //       if(res.code === 1) {
    //         alert('注册成功')
    //         $('#registration-modal .w-auto').trigger('click')
    //         $('#user-login .text-sm').trigger('click')
    //       } else {
    //         alert(res.msg)
    //       }
    //     }).catch(error => console.error(error))

    //   }

    //   function login() {
    //     const form = new FormData();
    //     form.append("account", $('#user_email').val());
    //     form.append("password", $('#user_password').val());
        
    //     const settings = {
    //       "async": true,
    //       "crossDomain": true,
    //       "url":  base + "/api/user/login",
    //       "method": "POST",
    //       "headers": {},
    //       "processData": false,
    //       "contentType": false,
    //       "mimeType": "multipart/form-data",
    //       "data": form
    //     };

    //     $.ajax(settings).done(function (response) {
    //       if(typeof(response) === 'string') {
    //         response = JSON.parse(response)
    //       }
    //       var res = response
    //       if(res.code === 1) {
    //         $('#sign-in-modal').css('display', 'none')
    //         $('#user-setting').css('display', 'flex')
    //         $('#user-login').css('display', 'none')
    //         localStorage.setItem('token', res.data.userinfo.token)
    //         token = res.data.userinfo.token
    //         getUserInfo()
    //         alert('登录成功')
    //       } else {
    //         alert(res.msg)
    //       }
    //     }).catch(error => console.error(error))
    //   }
      window.getUserInfo = function(){
        var headers = {}
        if(token) {
          headers = {
            Authorization: 'Bearer ' + token
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
           
            setTimeout(() => {
            location.reload()
            }, 800);
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
      }


      //
      $('#create-flow').submit(function(event){
          event.preventDefault(); // 阻止表单默认提交行为
 
          var formData = new FormData(this); // 创建FormData对象
        //   console.log(formData)
      })

      function initOp(){
        $('#ethnicity .rounded-xl').on('click', function() {
            $('#ethnicity').find('.selected').each((i, d) => {
                $(d).removeClass('selected')
            })
            $(this).addClass('selected')
            $('#ethnicity').find('.pill-active').each((i, d) => {
                $(d).removeClass('pill-active')
            })
            $(this).find('.pill').addClass('pill-active')
            
           
            $("#ethnicity input").each(function(){
              $(this).removeAttr('checked')
            });

            $(this).find("input").attr("checked", "checked")
        })
        
        $('#age-select .justify-center').on('click', function() {
            $('#age-select').find('.border-pink-default').each((i, d) => {
                $(d).removeClass('border-pink-default')
            })
            $(this).addClass('border-pink-default')
            $("#profile_age").val($(this).attr("data-id"))
        })
       
       $('#eyes .rounded-xl').on('click', function() {
            $('#eyes').find('.selected').each((i, d) => {
                $(d).removeClass('selected')
            })
            $(this).addClass('selected')
            
             $('#eyes').find('.pill-active').each((i, d) => {
                $(d).removeClass('pill-active')
            })
            $(this).find('.pill').addClass('pill-active')
            
             $("#eyes input").each(function(){
              $(this).removeAttr('checked')
            });

            $(this).find("input").attr("checked", "checked")
            
            
        })
          
        $('.create-next-btn').on('click', function(){
             if(token) {
                 
             }else{
                 layer.msg("Please log in first")
                 return
             }
            
          var id = $(this).data('id')
          var step = $(this).data('step')
          var url = $(this).data('url')
          var name = $(this).data('name')
          var res = {}
          var cacheData = localStorage.getItem('characterData')
          if(cacheData && cacheData != '{}') {
            res = JSON.parse(cacheData)
          }
          var value = $('#' + id + ' .selected input[name="profile[category]"').val()
          
          
          res[name] = parseInt(value)
          localStorage.setItem('characterData', JSON.stringify(res))
          var summary = {}
          summary.style = $('#category>.selected img').attr('src')
          summary.styleName = $('#category>.selected img').attr('alt')
          localStorage.setItem('characterSummary', JSON.stringify(summary))
          localStorage.setItem('characterStep', 1)
          if(value === '2') {
            location.href = './chooseEthnicityAnime.html'
          }
          else {
            location.href = './chooseEthnicity.html'
          }
        })

        $('#submitCreateFlow').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              var age = parseInt($('input[name="profile[age]"]').val())
              var eye = parseInt($('input[name="profile[eyes_color]"]:checked').val())
              var race = parseInt($('input[name="profile[ethnicity]"]:checked').val())
          
              if(!age || !eye || !race) {
                layer.msg('Please select race, age, glasses color') 
                return
              }
              if(res.type == '2') {
                location.href = './chooseHairStyleAnime.html'
              }
              else {
                location.href = './chooseHairStyle.html'
              }
            }
          }
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.age = parseInt($('input[name="profile[age]"]').val())
              res.eye = parseInt($('input[name="profile[eyes_color]"]:checked').val())
              res.race = parseInt(parseInt($('input[name="profile[ethnicity]"]:checked').val()))
              var summary =  JSON.parse(localStorage.getItem('characterSummary'))
              summary.ethnicity = ($('#ethnicity>.selected img').attr('src'))
              summary.ethnicityName = $('#ethnicity>.selected img').attr('alt')
              summary.eyes = ($('#eyes>.selected img').attr('src'))
              summary.eyesName = $('#eyes>.selected img').attr('alt')
              summary.age = ageArr[res.age - 1]
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 2)
            }
        })

        $('#submitCreateFlowHairButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              var hair = parseInt($('input[name="profile[hair_color]"]:checked').val())
              var hairstyle = parseInt($('input[name="profile[hair_style]"]:checked').val())
              if(!hair || !hairstyle) {
                // alert('Please finish your selection2')
                layer.msg("Please select a hairstyle and color")
                return
              }
              if(res.type == '2') {
                location.href = './chooseBodyTypeAnime.html'
              }
              else {
                location.href = './chooseBodyType.html'
              }
            }
          } 
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.hair = parseInt($('input[name="profile[hair_color]"]:checked').val())
              res.hairstyle = (parseInt($('input[name="profile[hair_style]"]:checked').val()))
              var colors = ['#C8A27B', '#8B5337', 'black', '#DA5151', '#DA4FA2', 'white', '#48A7D0', '#39AB37', '#AC70C9', 'linear-gradient(95deg, #DA5151 0.95%, #C55EB4 34.13%, #AC70C9 75.24%, #64CA62 98.96%)']
              var w = ['Blonde', 'Brunette', 'Black', 'Redhead', 'Pink', 'White', 'Blue', 'Green', 'Purple', 'Multicolor']
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              summary.hairStyle = ($('#hairStyle>.selected img').attr('src'))
              summary.hairStyleName = $('#hairStyle>.selected img').attr('alt')
              summary.hairColor1 = colors[res.hair - 1]
              summary.hairColor2 = w[res.hair - 1]
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 3)
            }
        })

        $('#submitCreateFlowBodyButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              var body = parseInt($('input[name="profile[body]"]:checked').val())
              var breast = parseInt($('input[name="profile[breast_size]"]:checked').val())
              var hip = parseInt($('input[name="profile[butt_size]"]:checked').val())
              
              
              if(!body || !breast || !hip) {
                // alert('Please finish your selection3') 
                layer.msg("Please select body type and size")
                return
              }
              if(res.type == '2') {
                location.href = './choosePersonalityAnime.html'
              }
              else {
                location.href = './choosePersonality.html'
              }
            }
          }
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.body = parseInt($('input[name="profile[body]"]:checked').val())
              res.breast = parseInt($('input[name="profile[breast_size]"]:checked').val())
              res.hip = parseInt($('input[name="profile[butt_size]"]:checked').val())
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              summary.body = ($('#bodyType>.selected img').attr('src'))
              summary.bodyName = $('#bodyType>.selected img').attr('alt')
              summary.breast = ($('#breastSize>.selected img').attr('src'))
              summary.breastName = ($('#breastSize>.selected img').attr('alt'))
              summary.hip =  ($('#buttSize>.selected img').attr('src'))
              summary.hipName =  ($('#buttSize>.selected img').attr('alt'))
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 4)
            }
        })

        $('#submitCreateFlowPersonalityButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var personality = parseInt($('input[name="profile[personality]"]:checked').val())
              if(!personality) {
                // alert('Please finish your selection4') 
                layer.msg("Please select personality")
                return
              }
              var res = JSON.parse(cacheData)
              res.personality = parseInt($('input[name="profile[personality]"]:checked').val())
              localStorage.setItem('characterData', JSON.stringify(res))
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              summary.personality = ($('#personality>.selected img').attr('src'))
              summary.personalityName = ($('#personality>.selected img').attr('alt'))
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterStep', 5)
              if(res.type == '2') {
                location.href = './chooseOccupationAnime.html'
              }
              else {
                location.href = './chooseOccupation.html'
              }
            }
          } 
          // else {
          //   debugger
          //   var cacheData = localStorage.getItem('characterData')
          //   if(cacheData && cacheData != '{}') {
          //     var res = JSON.parse(cacheData)
          //     res.personality = parseInt(parseInt($('#personality>.selected img').attr('alt')))
          //     localStorage.setItem('characterData', JSON.stringify(res))
          //     localStorage.setItem('characterStep', 'personality')
          //   }
          // }
        })

        $('#submitCreateFlowOccupationButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var hobbys = []
            $('input[name="profile[hobby_types][]"]:checked').each(function() {
              hobbys.push(parseInt($(this).val()))
            });
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var profession = parseInt($('input[name="profile[occupation]"]:checked').val())
              var hobby = hobbys.join(',')
              if(!profession || hobbys.length === 0 || !hobby) {
                // alert('Please finish your selection5') 
                layer.msg("Please select your occupation and interests")
                return
              }
              var res = JSON.parse(cacheData)
              res.profession = parseInt($('input[name="profile[occupation]"]:checked').val())
              res.hobby = hobbys.join(',')
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              var arrs = []
              $('#hobbies .pill-option-active').each(function() {
                arrs.push($(this).data('value'))
              });
              summary.hobbies = arrs
              summary.occupation = ($('#occupation .pill-option-active').data('value'))
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 6)
              if(res.type == '2') {
                location.href = './chooseRelationshipAnime.html'
              }
              else {
                location.href = './chooseRelationship.html'
              }
            }
          } 
          // else {
          //   debugger
          //   var cacheData = localStorage.getItem('characterData')
          //   if(cacheData && cacheData != '{}') {
          //     var res = JSON.parse(cacheData)
          //     res.personality = parseInt(parseInt($('#personality>.selected img').attr('alt')))
          //     localStorage.setItem('characterData', JSON.stringify(res))
          //     localStorage.setItem('characterStep', 'personality')
          //   }
          // }
        })

        $('#submitCreateFlowRelationshipButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              var relation = parseInt($('input[name="profile[relationship]"]:checked').val())
              if(!relation) {
                // alert('Please finish your selection6') 
                layer.msg("Please select a relationship")
                return
              }
              if(res.type == '2') {
                location.href = './chooseClothingAnime.html'
              }
              else {
                location.href = './chooseClothing.html'
              }
            }
          } 
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.relation = parseInt($('input[name="profile[relationship]"]:checked').val())
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              summary.relationship = ($('#relationship>.selected img').attr('src'))
              summary.relationshipName = ($('#relationship>.selected img').attr('alt'))
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 7)
            }
        })

        $('#submitCreateFlowClothingButton').on('click', function (e) {
          if(e.originalEvent.isTrusted) {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var clothing = parseInt($('input[name="profile[clothing]"]:checked').val())
              if(!clothing) {
                // alert('Please finish your selection7') 
                layer.msg("Please select clothing")
                return
              }
              location.href = './summary.html'
            }
          } 
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.clothing = parseInt($('input[name="profile[clothing]"]:checked').val())
              var summary =   JSON.parse(localStorage.getItem('characterSummary'))
              summary.clothing = ($('#clothing .pill-option-active').data('value'))
              localStorage.setItem('characterSummary', JSON.stringify(summary))
              localStorage.setItem('characterData', JSON.stringify(res))
              localStorage.setItem('characterStep', 8)
            }
        })
        initSummary()
        function initSummary() {
          var template = $('#summary-template').html();
          if (!template) return
          var summaryStr = localStorage.getItem('characterSummary')
          if(summaryStr && summaryStr != '{}'){
            var summary = JSON.parse(summaryStr)
            var hobbies = ''
            summary.hobbies.forEach(element => {
              hobbies += '<div class="text-[14px] mb-1 rounded-xl text-white w-fit px-3 py-2 leading-[14px] select-none pill-option-inactive">'+element+'</div>'
            });
            
            
            var rendered = template.replace("{{style}}", summary.style)
                .replace("{{styleName}}", summary.styleName)
                .replace("{{ethnicity}}", summary.ethnicity)
                .replace("{{ethnicityName}}", summary.ethnicityName)
                .replace("{{eyes}}", summary.eyes)
                .replace("{{eyesName}}", summary.eyesName)
                .replace("{{age}}", summary.age)
                .replace("{{hairStyle}}", summary.hairStyle)
                .replace("{{hairStyleName}}", summary.hairStyleName)
                .replace("{{hairColor1}}", summary.hairColor1)
                .replace("{{hairColor2}}", summary.hairColor2)
                .replace("{{body}}", summary.body)
                .replace("{{bodyName}}", summary.bodyName)
                .replace("{{breast}}", summary.breast)
                .replace("{{breastName}}", summary.breastName)
                .replace("{{hip}}", summary.hip)
                .replace("{{hipName}}", summary.hipName)
                .replace("{{personality}}", summary.personality)
                .replace("{{personalityName}}", summary.personalityName)
                .replace("{{hobbies}}", hobbies)
                .replace("{{occupation}}", summary.occupation)
                .replace("{{relationship}}", summary.relationship)
                .replace("{{relationshipName}}", summary.relationshipName)
                .replace("{{clothing}}", summary.clothing)
                .replace("{{profession}}", summary.profession)
                $('#summary-group').html(rendered)
          }
          
          // 初始化 character name 和 image 的存储
          initCharacterNameAndImage()
        }
        
        function initCharacterNameAndImage() {
          // 延迟执行以确保元素已经渲染到 DOM
          setTimeout(function() {
            // 从 localStorage 恢复 character name
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              if(res.characterName && $('#character-name').length > 0) {
                $('#character-name').val(res.characterName)
              }
              // 恢复图片预览
              if(res.characterImageUrl && $('#character-image-preview').length > 0) {
                $('#character-image-preview').attr('src', res.characterImageUrl).show()
              }
            }
          }, 100)
          
          // 监听 character name 变化
          $(document).on('input', '#character-name', function() {
            var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var res = JSON.parse(cacheData)
              res.characterName = $(this).val()
              localStorage.setItem('characterData', JSON.stringify(res))
            }
          })
          
          // 监听 character image 变化 - 直接上传到后端API
          $(document).on('change', '#character-image', function() {
            var file = this.files[0]
            if(!file) return
            
            var previewImg = $('#character-image-preview')
            var fileInput = $(this)
            
            // 显示上传中状态
            previewImg.hide()
            fileInput.prop('disabled', true)
            
            // 创建 FormData
            var formData = new FormData()
            formData.append("file", file)
            
            // 准备请求头
            var headers = {}
            if(token) {
              headers = {
                Authorization: 'Bearer ' + token
              }
            }
            
            // 上传图片到后端API
            $.ajax({
              "async": true,
              "crossDomain": true,
              "url": base + "/upload",
              "method": "POST",
              "headers": headers,
              "processData": false,
              "contentType": false,
              "mimeType": "multipart/form-data",
              "data": formData
            }).done(function(response) {
              if(typeof(response) === 'string') {
                response = JSON.parse(response)
              }
              var res = response
              
              if(res.code === "00000") {
                var data = res.data
                var imageUrl = data.fullurl || data.url || data.imageUrl
                
                // 显示预览图片
                previewImg.attr('src', imageUrl).show()
                
                // 存储 URL 到 localStorage
                var cacheData = localStorage.getItem('characterData')
                if(cacheData && cacheData != '{}') {
                  var characterData = JSON.parse(cacheData)
                  characterData.characterImageUrl = imageUrl
                  localStorage.setItem('characterData', JSON.stringify(characterData))
                }
              } else {
                layer.msg(res.msg || "Failed to upload image")
              }
            }).fail(function(error) {
              layer.msg("Failed to upload image, please try again")
              console.error("Upload error:", error)
            }).always(function() {
              // 恢复文件输入框状态
              fileInput.prop('disabled', false)
            })
          })
        }
        previous()
        function previous() {
          var arrs = {
            type: 'category',
            age:  'age',
            eye:  'eyes_color',
            race:  'ethnicity',
            hair:  'hair_color',
            hairstyle:  'hair_style',
            body:  'body',
            breast:  'breast_size',
            hip:  'butt_size',
            personality:  'personality',
            profession:  'occupation',
            hobby:  'hobby_types',
            relation:  'relationship',
            clothing:  'clothing',
          }
          var cacheData = localStorage.getItem('characterData')
          if(cacheData && cacheData != '{}') {
            var obj = JSON.parse(cacheData)
            Object.keys(obj).forEach(key => {
              if(key === 'hobby') {
                var data = obj[key].split(',')
                $('input[name="profile[hobby_types][]"]').each(function() {
                  data.forEach(t => {
                    if($(this).val() == t) {
                      $(this).parent().trigger('click')
                    }
                  })
                });
              } else if(key === 'age') {
                $('.ageUl > li').each(function () {
                  if($(this).data('id') == ageArr[obj[key]-1]) {
                    $(this).trigger('click')
                  }
                })
              }
              else {
                var name = arrs[key]
                $('input[name="profile['+name+']"]').each(function() {
                  if($(this).val() == obj[key]) {
                    $(this).parent().trigger('click')
                  }
                })
              }
            });
          }
        }

        $('#Previous').on('click', function () {
          var cacheData = localStorage.getItem('characterData')
          if(cacheData && cacheData != '{}') {
            var res = JSON.parse(cacheData)
            if(res.type == '2') {
              location.href = './chooseClothingAnime.html'
            }
            else {
              location.href = './chooseClothing.html'
            }
          }
        })

        $('#bring').on('click', function() {
          var cacheData = localStorage.getItem('characterData')
            if(cacheData && cacheData != '{}') {
              var obj = JSON.parse(cacheData)
              
              // 验证 character name
              var characterName = $('#character-name').val()
              if(characterName) {
                characterName = characterName.trim()
              } else if(obj.characterName) {
                characterName = obj.characterName.trim()
              }
              
              if(!characterName || characterName === '') {
                layer.msg("Please enter character name")
                return
              }
              
              // 验证 character image URL
              var characterImageUrl = obj.characterImageUrl
              if(!characterImageUrl || characterImageUrl.trim() === '') {
                layer.msg("Please upload character cover image")
                return
              }
              
              // 更新 localStorage 中的值
              obj.characterName = characterName
              localStorage.setItem('characterData', JSON.stringify(obj))
              
              // 提交表单数据
              submitFormData(obj)
            }
        })
        
        function submitFormData(obj) {
          const formData = new FormData();

          for (let key in obj) {
              if (obj.hasOwnProperty(key)) {
                  // 跳过旧的 base64 图片字段（如果存在）
                  if(key === 'characterImage' || key === 'characterImageName') {
                    continue
                  }
                  // 直接添加所有字段，包括 characterImageUrl（字符串URL）
                  formData.append(key, obj[key])
              }
          }
          customRoleOne(formData)
        }

        function customRoleOne(data){
          var headers = {}
          if(token) {
            headers = {
              Authorization: 'Bearer ' + token
            }
          }else{
               layer.msg("Please log in first")
               return
          }
          const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/customRoleOne",
            "method": "POST",
            "headers": headers,
            "processData": false,
            "contentType": false,
            "mimeType": "multipart/form-data",
            "data": data
          };

          $.ajax(settings).done(function (response) {
            if(typeof(response) === 'string') {
              response = JSON.parse(response)
            }
            var res = response
            
            // 检查是否是认证失败
            if(res.code !== "00000" && (res.code === "10003" || res.code === "10004")) {
              layer.msg(res.msg || "请登录后操作")
              localStorage.removeItem('live_access_token')
              localStorage.removeItem('live_user_info')
              setTimeout(() => {
                location.href = './Login.html'
              }, 800);
              return
            }
            
            if(res.code === "00000") {
                layer.msg(res.msg || '创建成功')
              localStorage.removeItem('characterSummary')
              localStorage.removeItem('characterData')
              localStorage.removeItem('characterStep')
              
               setTimeout(() => {
                 location.href = './charactersIndex.html'
                }, 800);
            
            } else {
              layer.msg(res.msg)
            }
          }).catch(
            error => {
              // console.error(error)
              var isUnauthorized = false
              var errorMsg = "Failed to create character, please try again later"
              
              // 检查 HTTP 状态码
              if(error.status === 401) {
                isUnauthorized = true
              }
              // 检查响应体中的 code
              else if(error.responseJSON && error.responseJSON.code === 401) {
                isUnauthorized = true
                errorMsg = error.responseJSON.msg || "请登录后操作"
              }
              
              if(isUnauthorized) {
                layer.msg(errorMsg)
                localStorage.removeItem('live_access_token')
                localStorage.removeItem('live_user_info')
                setTimeout(() => {
                  location.href = './Login.html'
                }, 800);
              } else {
                layer.msg("Failed to create character, please try again later")
              }
            }
          )
            }
            customPrice()
            function customPrice(){
              if(!token) return
              const settings = {
                "async": true,
                "crossDomain": true,
                "url":  base + "/customPrice",
                "method": "POST",
                "headers": {
                  'Authorization': 'Bearer ' + token
                },
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
                  var template = $('#audio-template').html();
                   if (!template) return
                  for(var i =1; i <=3; i++) {
                    var a = (res.data && res.data['customtwoyp' + i]) ? res.data['customtwoyp' + i] : ''
                    if(a && !a.includes("http")) {
                      a = 'http://' + a
                    }
                    var rendered = template.replace("{{src}}", a)
                    .replace("{{index}}", i)
                    $('#audio-group').append(rendered)
                  }
                } else {
                  layer.msg(res.msg)
                }
              }).catch(error => console.error(error))
            }
            $('#upload').on('click', function(){
                if(!token) {
                 
                  layer.msg("Please log in first")
                  return
              }
              $('#uploadImage').trigger('click')
            })
            $('#uploadImage').on('change', function(event){ 
                
                //   e.preventDefault();
         
                // // 触发表单验证
                // if (this.checkValidity()) {
                //   // 表单验证通过，可以执行提交操作
                //   console.log('Form is valid, submitting...');
                // //   login()
                //   // this.submit();
                // } else {
                //   // 表单验证失败，可以处理错误
                //   console.error('Form is invalid. Fix errors before submitting.');
                // }
              var file = event.target.files[0];     
              var image = document.getElementById('imagePreview');
              var submitButton = document.getElementById('submitButton');
              
              var headers = {}
              if(token) {
                  headers = {
                  Authorization: 'Bearer ' + token
                  }
              }
              const form = new FormData();
              form.append("file",file);
              const settings = {
                  "async": true,
                  "crossDomain": true,
                  "url":  base + "/upload",
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
                      var data = res.data
                      image.src = data.fullurl;
                      image.style.display = 'block';  // 显示预览图片
      
                      // 将上传后的图片 URL 填入隐藏字段中
                      document.getElementById('photo').value = data.fullurl;
      
                      // 启用提交按钮
                      submitButton.disabled = false;
                  } else {
                      layer.msg(res.msg)
                  }
              }).catch(error => console.error(error))
            })

            $('#submitInfo').on('click', () => {
              var photo = $('#photo').val()
              var weight = $('#weight').val()
              var height = $('#height').val()
              
              if( !photo ) {
                layer.msg("Please upload a picture")
                return
              }
              
              if(!height) {
                layer.msg('Please enter your height') 
                return
              }
              
              if( !weight ) {
                layer.msg('Please enter your weight') 
                return
              }
              if($('#audio-group .voice-selected').length === 0) {
                layer.msg('Please select a sound') 
                return
              }
              var sound = $('#audio-group .voice-selected source').attr('src')

              var form = new FormData()
              form.append('photo', photo)
              form.append('weight', parseInt(weight))
              form.append('height', parseInt(height))
              form.append('sound', sound)


              var headers = {}
              if(token) {
                  headers = {
                  Authorization: 'Bearer ' + token
                  }
              }
              const settings = {
                  "async": true,
                  "crossDomain": true,
                  "url":  base + "/api/user/customRoleTwo",
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
                     layer.msg(res.msg)
                     setTimeout(() => {
                    location.reload()
                    }, 800);
                  } else {
                      layer.msg(res.msg)
                  }
              }).catch(error => console.error(error))
            })
      }
})()
