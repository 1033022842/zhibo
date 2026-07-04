(()=>{
    var base = 'https://api.kisss.ai'
    // var base = 'http://45.194.18.126:39200'
    var token = localStorage.getItem('live_access_token')
    var userJson = localStorage.getItem('live_user_info')
    	var user = JSON.parse(userJson)
    var cacheTemplate = ''
    var cacheMoneyTemplate = ''
    setTimeout(() => {
        getVipList()
        getMoneyList()
        $('#payment').on('click', function() {
            buyGroup()
        })
        $('#payment-yd').on('click', function() {
            buyGroup_yd()
        })
        $('#buyPoint').on('click', function() {
            checkVip(1)
        })
         $('#buyPoint-yd').on('click', function() {
            checkVip(2)
        })
        
        $('.usdt-group').on('click', function() {
            $('#usdt-layout').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        $('.usdt-group-yd').on('click', function() {
            $('#usdt-layout-yd').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        
        $('.recharge-group').on('click', function() {
            $('#recharge-layout').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
         $('.recharge-group-yd').on('click', function() {
            $('#recharge-layout-yd').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        
        $('.hairColor').on('click', function() {
           var pay_type= $(this).find('input[name="pay_type"]').val()
           
            $(this).siblings().removeClass('selected')
            $(this).addClass('selected')
            if(pay_type==1){
                $("#usdt-layout").css("display","flex")
                 $('input[name="hidtype"]').val(1)
                 $('input[name="payType"]').val(1)
               
            }else{
                 $("#usdt-layout").css("display","none")
                  $('input[name="hidtype"]').val(0)
                  $('input[name="payType"]').val(3)
            }
        })
        
        $('.hairColor-yd').on('click', function() {
           var pay_type= $(this).find('input[name="pay_type-yd"]').val()
           
            $(this).siblings().removeClass('selected')
            $(this).addClass('selected')
            if(pay_type==1){
                $("#usdt-layout-yd").css("display","flex")
                 $('input[name="hidtype-yd"]').val(1)
               
            }else{
                 $("#usdt-layout-yd").css("display","none")
                  $('input[name="hidtype-yd"]').val(2)
            }
        })
        if(token) {
            //  getUserInfo()
            $(".blanceamount").css("display","block")
             $("#balance").html(user.money)
             
              $(".blanceamount-yd").css("display","block")
             $("#balance-yd").html(user.money)
        }
     
        
        customPrice()
      
    }, 1000);
    
    function copy(){
    
        
        
        const text = $('#usdt_url').html();

          const textarea = document.createElement('textarea');
          textarea.value = text;
          document.body.appendChild(textarea);
          textarea.select();
        
          try {
            // 尝试执行复制操作
            const success = document.execCommand('copy');
            if (success) {
            
             layer.msg("Copy Success")
            } else {
            
              layer.msg("Copy Failure")
            }
          } catch (error) {
          
          layer.msg("Copy Failure")
          }
        
          document.body.removeChild(textarea);
        
          
      }

    function initData(data) {
        cacheTemplate = $('#vip-Layout').html();
        $('#vip-Layout').html(cacheTemplate)
        
        $.each(data, function(index, item) {
            var template = $('#vip-template').html();

            var rendered = template.replace("{{month}}", item.content[0].month)
            .replace("{{description}}", item.description)
            .replace("{{title}}", item.title)
            // .replace("{{unit}}", (item.content[0].daily_cost + '').split('.')[0])
            .replace("{{unit}}", (item.content[0].daily_cost + ''))
            // .replace("{{decimal}}", (item.content[0].price + ''))
            .replace("{{decimal}}","")
            .replace("{{id}}", item.id);

            $('#vip-Layout').append(rendered);
        });
        $('.vip-group').on('click', function() {
            $('#vip-Layout').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        //手机端
        
                cacheTemplate = $('#vip-Layout-yd').html();
        $('#vip-Layout-yd').html(cacheTemplate)
        
        $.each(data, function(index, item) {
            var template = $('#vip-template-yd').html();

            var rendered = template.replace("{{month}}", item.content[0].month)
            .replace("{{description}}", item.description)
            .replace("{{title}}", item.title)
            // .replace("{{unit}}", (item.content[0].daily_cost + '').split('.')[0])
            .replace("{{unit}}", (item.content[0].daily_cost + ''))
            // .replace("{{decimal}}", (item.content[0].price + ''))
            .replace("{{decimal}}","")
            .replace("{{id}}", item.id);

            $('#vip-Layout-yd').append(rendered);
        });
        $('.vip-group-yd').on('click', function() {
            $('#vip-Layout-yd').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        
    }
    function initMoneyData(data) {
        cacheMoneyTemplate = $('#money-layout').html();
        $('#money-layout').html(cacheMoneyTemplate)
        
        $.each(data, function(index, item) {
            var template = $('#money-template').html();

            // var rendered = template.replaceAll("{{textLabel}}", item.text)
            // .replace("{{value}}", item.value);

            // $('#money-layout').append(rendered);
        });
        $('.money-group').on('click', function() {
            $('#money-layout').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
        
        
        
          cacheMoneyTemplate = $('#money-layout-yd').html();
        $('#money-layout-yd').html(cacheMoneyTemplate)
        
        $.each(data, function(index, item) {
            var template = $('#money-template-yd').html();

            var rendered = template.replaceAll("{{textLabel}}", item.text)
            .replace("{{value}}", item.value);

            $('#money-layout-yd').append(rendered);
        });
        $('.money-group-yd').on('click', function() {
            $('#money-layout-yd').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })
    }
    function getVipList(id) {
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/vip_list",
          "method": "post",
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
            initData(res.data)
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
      }
    function buyGroup() {
       

        
        
        var id =$('.vip-group.sub-gradient-active').data('id')
        
        var usdt_id =$('.usdt-group.sub-gradient-active').attr('data-text')
        
       var hidtype =$('input[name="hidtype"]').val()

       var pay_type =$('input[name="payType"]').val()

        if(!id) {
                layer.msg('Please select your membership type')
                return
            }
       if(hidtype==1){
           
             if(!usdt_id) {
                layer.msg('Please select a payment network')
                return
            }
        }
        var headers = {}
        if(token) {
            headers = {
                token
            }
        }else{
            layer.msg("Please log in first")
        }
        const form = new FormData();
        form.append("id", $('#vip-Layout .sub-gradient-active').data('id'));
        form.append("usdt_id",usdt_id);
        form.append("hidtype",hidtype);
        form.append("pay_type",pay_type);
        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/user/buygroup",
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
                layer.msg(res.msg)
                 getUserInfo();
                 if(res.data.url!=""){
                  setTimeout(() => {
                 //  location.href = res.data.url
                 }, 800);
                }else{
                    setTimeout(() => {
                    location.reload()
                    }, 800); 
                }
               
                
                // layer.open({
                //     type: 1
                    
                //     ,title: "Payment Address" //不显示标题
                //      ,area: ['420px', '']
                //     ,offset: "auto" //具体配置参考：https://www.layui1.com/doc/modules/layer.html#offset
                //     ,id: 'layerDemo' //防止重复弹出
                //     ,content: '<div style="padding: 8px 130px;"><img src="https://api.qrserver.com/v1/create-qr-code/?data='+res.data.payment_url+'" width="150px">'+""+'</div><p style="text-align:center;font-size:28px;">$ '+res.data.money+'</p><p style="text-align:center;font-size:12px;" id="usdt_url">'+res.data.payment_url+'</p><div style="padding: 3px 180px;"><div id="copybutton" class="px-4 py-2 bg-neutral-700 rounded-[10px] md:flex block w-fit mt-5 md:mt-0"><div class="text-white text-sm font-semibold" id="copyhtmlbutton">copy</div></div></div><p style="margin-top:10px;margin-bottom:30px;color:#FC768A;text-align:center;">Please pay the same amount as the order, otherwise the money will not be credited to your account</>'
                //     // ,btn: 'close'
                //     ,btnAlign: 'c' //按钮居中
                //     ,shade: 0 //不显示遮罩
                //     ,yes: function(){
                //       layer.closeAll();
                //     }
                //     ,success: function(){
                //             // 获取按钮，绑定点击事件
                //             document.getElementById('copybutton').onclick = function(){
                //                 copy()
                //             };
                //         }
                //      });
                
            } else {
              layer.msg(res.msg)
            }
        }).catch(error => console.error(error))
    }
    
    
    function buyGroup_yd() {
       

        
        
        var id =$('.vip-group-yd.sub-gradient-active').data('id')
        
        var usdt_id =$('.usdt-group-yd.sub-gradient-active').attr('data-text')
        
       var hidtype =$('input[name="hidtype-yd"]').val()

       var pay_type =$('input[name="pay_type-yd"]').val()
        if(!id) {
                layer.msg('Please select your membership type')
                return
            }
       if(hidtype==1){
           
             if(!usdt_id) {
                layer.msg('Please select a payment network')
                return
            }
        }
        var headers = {}
        if(token) {
            headers = {
                token
            }
        }else{
            layer.msg("Please log in first")
        }
        const form = new FormData();
        form.append("id", $('#vip-Layout-yd .sub-gradient-active').data('id'));
        form.append("usdt_id",usdt_id);
        form.append("hidtype",hidtype);
        form.append("pay_type",pay_type);
        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/user/buygroup",
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
              
              
                 if(res.data.payment_url!==""){
                     setTimeout(() => {
                    // window.open('/payment.html?pay='+res.data.payment_url, '_blank');
                    }, 800);
                }else{
                      layer.msg(res.msg)
                       getUserInfo();
                    setTimeout(() => {
                    location.reload()
                    }, 800); 
                }
                
            } else {
              layer.msg(res.msg)
            }
        }).catch(error => console.error(error))
    }
    
    function getMoneyList(id) {
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }
        const settings = {
          "async": true,
          "crossDomain": true,
          "url":  base + "/api/user/money_list",
          "method": "post",
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
            initMoneyData(res.data)
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => console.error(error))
      }

    function checkVip(index) {
        var headers = {}
        if(token) {
          headers = {
            token
          }
        }else{
            layer.msg("Please log in first")
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
                moneySubmit(index)
            } else {
                layer.msg(res.msg)
            }
          } else {
            layer.msg(res.msg)
          }
        }).catch(error => {
            console.error(error)
        })
    }
    function moneySubmit(index) {
        if(index==1){
                 var id =$('.money-group.sub-gradient-active').data('text')
            
            var usdt_id =$('.recharge-group.sub-gradient-active').attr('data-text')
            
           
            if(!id) {
                layer.msg('Please select the recharge amount')
                return
            }
             if(!usdt_id) {
                layer.msg('Please select a payment network')
                return
            }
            
            var headers = {}
            if(token) {
                headers = {
                    token
                }
            }
            const form = new FormData();
            form.append("money", parseInt($('#money-layout .sub-gradient-active').data('text')));
            form.append("usdt_id", usdt_id);
            const settings = {
                "async": true,
                "crossDomain": true,
                "url":  base + "/api/user/money_submit",
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
                    // window.open('/payment.html?pay='+res.data.payment_url+"&t=b", '_blank');
    
                } else {
                   layer.msg(res.msg)
                }
            }).catch(error => console.error(error))
        }else{
             var id =$('.money-group-yd.sub-gradient-active').data('text')
        
        var usdt_id =$('.recharge-group-yd.sub-gradient-active').attr('data-text')
        
       
        if(!id) {
            layer.msg('Please select the recharge amount')
            return
        }
         if(!usdt_id) {
            layer.msg('Please select a payment network')
            return
        }
        
        var headers = {}
        if(token) {
            headers = {
                token
            }
        }
        const form = new FormData();
        form.append("money", parseInt($('#money-layout-yd .sub-gradient-active').data('text')));
        form.append("usdt_id", usdt_id);
        const settings = {
            "async": true,
            "crossDomain": true,
            "url":  base + "/api/user/money_submit",
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
                 if(res.data.payment_url!==""){
                     setTimeout(() => {
                     window.open('/payment.html?pay='+res.data.payment_url+"&t=b", '_blank');
                    }, 800);
                }else{
                    layer.msg(res.msg)
                    getUserInfo();
                    setTimeout(() => {
                    location.reload()
                    }, 800); 
                }

            } else {
               layer.msg(res.msg)
            }
        }).catch(error => console.error(error))
        }
       
    }
    function customPrice(){
      var headers = {}
      if(token) {
        headers = {
          token
        }
      }
      const settings = {
        "async": true,
        "crossDomain": true,
        "url":  base + "/api/user/customPrice",
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
          $('#roleOne').html('$' + res.data.customoneprice)
          $('#roleTwo').html('$' + res.data.customtwoprice)
        } else {
          layer.msg(res.msg)
        }
      }).catch(error => console.error(error))
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
    
})()