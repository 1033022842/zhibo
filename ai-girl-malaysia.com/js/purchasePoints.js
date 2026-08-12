(()=>{
    const base = 'http://38.181.44.164/api'

    function initPurchasePoints() {
        const token = localStorage.getItem('live_access_token')
        const userJson = localStorage.getItem('live_user_info')
        let user = null
        try { user = JSON.parse(userJson) } catch(e) { user = {} }

        // 从本地 wallet 读取余额显示
        getUserInfoFromLocal()

        if(token) {
            $(".blanceamount").css("display","block")
            $(".blanceamount-yd").css("display","block")
        }

        // 从本地后端获取最新余额
        function getUserInfoFromLocal() {
            if (!token) return
            $.ajax({
                url: base + '/live/userInfo',
                method: 'post',
                headers: { token: token },
                processData: false,
                contentType: false,
            }).done(function(response) {
                if (typeof response === 'string') response = JSON.parse(response)
                if (response.code === '00000' && response.data) {
                    const money = response.data.money || 0
                    $("#balance").html(money)
                    $("#balance-yd").html(money)
                    const u = JSON.parse(localStorage.getItem('live_user_info') || '{}')
                    u.money = money
                    localStorage.setItem('live_user_info', JSON.stringify(u))
                } else {
                    $("#balance").html((user||{}).money || 0)
                    $("#balance-yd").html((user||{}).money || 0)
                }
            }).catch(function() {
                $("#balance").html((user||{}).money || 0)
                $("#balance-yd").html((user||{}).money || 0)
            })
        }
    }

    // 初始化
    $(function() {
        initPurchasePoints()
        bindBuyButton()
    })
    // Turbo 导航后重新初始化
    $(document).on('turbo:load turbo:render', function() {
        initPurchasePoints()
        bindBuyButton()
    })

    function bindBuyButton() {
        // 先解绑避免重复绑定
        $(document).off('click.purchase', '.btnBuyPoint')
        $(document).on('click.purchase', '.btnBuyPoint', function(e) {
            e.preventDefault()
            const tk = localStorage.getItem('live_access_token')
            if(!tk){
                layer.msg("Please log in first")
                window.location.href = './login.html'
                return
            }
            window.location.href = 'http://localhost:3000/me/recharge#token=' + encodeURIComponent(tk)
        })
    }
})()