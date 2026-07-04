(()=>{
    const base = 'https://api.kisss.ai'
    // const base = 'http://45.194.18.126:39200'
    const token = localStorage.getItem('live_access_token')
    const userJson = localStorage.getItem('live_user_info')
    const user = JSON.parse(userJson)
    let cacheTemplate = ''
    let cacheMoneyTemplate = ''

    setTimeout(() => {
        getVipList()
        getMoneyList()
        customPrice()

        if(token) {
            //  getUserInfo()
            $(".blanceamount").css("display","block")
             $("#balance").html(user.money)
             
              $(".blanceamount-yd").css("display","block")
             $("#balance-yd").html(user.money)
        }
    }, 1000)

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


    function initData(data) {
        cacheTemplate = $('#vip-Layout').html();
        $('#vip-Layout').html(cacheTemplate)
        
        $.each(data, function(index, item) {
            var template = $('#vip-template').html();

            var rendered = template
            // .replace("{{description}}", item.description)
            // .replace("{{title}}", item.title)
            // .replace("{{unit}}", (item.content[0].daily_cost + '').split('.')[0])
            // .replace("{{unit}}", (item.content[0].daily_cost + ''))
            // .replace("{{decimal}}", (item.content[0].price + ''))
            // .replace("{{decimal}}","")
            // .replace("{{id}}", item.id);

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

            var rendered = template
            // .replace("{{month}}", item.content[0].month)
            // .replace("{{description}}", item.description)
            // .replace("{{title}}", item.title)
            // .replace("{{unit}}", (item.content[0].daily_cost + '').split('.')[0])
            // .replace("{{unit}}", (item.content[0].daily_cost + ''))
            // .replace("{{decimal}}", (item.content[0].price + ''))
            // .replace("{{decimal}}","")
            // .replace("{{id}}", item.id);

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

            var rendered = template.replaceAll("{{textLabel}}", item.text)
            .replaceAll("{{value}}", item.value);

            $('#money-layout').append(rendered);
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
            .replaceAll("{{value}}", item.value);

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

    // 将事件绑定包装在document ready中
    $(document).ready(function() {
        
        // 尝试多种事件绑定方式
        $('.btnBuyPoint').on('click', function(e) {
            e.preventDefault()
            
            if(!token){
                layer.msg("Please log in first")
                window.location.href = './login.html'
            }
            if(!user){
                layer.msg("Please log in first")
                window.location.href = './login.html'
            }

            var headers = {}
            if(token) {
            headers = {
                token
            }
            }

            const amount = $('input[name="amount"]').val()
            const payType = $('input[name="payType"]').val()
            const payMethod = $('input[name="payMethod"]').val()

            console.log(amount, payType, payMethod)
            if(payType == 0){
                layer.msg("Please select pay type")
            }
            if(amount == 0){
                layer.msg("Please select amount")
                return
            }
    

            if(payType == 1){
                const usdtId = $('.usdt-group.sub-gradient-active').attr('data-text')
                if(!usdtId){
                    layer.msg("Please select usdt")
                    return
                }
            }



            const form = new FormData();
            form.append("amount", amount);
            form.append("type", 1);
            form.append("pay_type", payType);
            form.append("pay_method", payMethod);

            const settings = {
                "async": true,
                "crossDomain": true,
                "url":  base + "/api/user/buyPoints",
                "method": "post",
                "headers": headers,
                "processData": false,
                "contentType": false,
                "mimeType": "multipart/form-data",
                "data": form
            };

            // 显示loading
            layer.load(1, {
                shade: [0.1, '#000']
            });

            $.ajax(settings).done(function (response) {
                if(typeof(response) === 'string') {
                    response = JSON.parse(response)
                }
                var res = response
                if(res.code === 1) {
                    layer.closeAll('loading')
                    location.href = res.data.url
                } else {
                    layer.msg(res.msg)
                }
            }).catch(error => console.error(error))
        })
        
        // 备用绑定方式
        $(document).on('click', '.btnBuyPoint', function(e) {
            console.log('Button clicked via document delegation!')
            e.preventDefault()
            alert('Button clicked via delegation!')
        })
    })
    
})()