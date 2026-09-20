/**
 * Subscriptions 页（英文界面 · USDT 计价 · star-pay 网关支付）
 */
(()=>{
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    var tmplPc = ''
    var tmplYd = ''
    var plans = []
    var products = []
    var product = 'TRC20Buy'
    // 当前支付方式：card 卡支付（收银台内可选 Visa/MasterCard/Apple Pay 等）| crypto 币种
    var method = 'card'
    var PENDING_KEY = 'vip_pending_order'
    // 页面币种入口 → star-pay 产品（data-text：1 ETH / 2 TRX / 3 USDT-ERC20 / 4 USDT-TRC20 / 5 BSC）
    // USDT 两个入口走「收款地址」类产品（自己钱包转账），ETH/TRX 走买币类产品（收银台刷卡）
    var COIN_PRODUCT = {
        '1': 'ERC20Buy',
        '2': 'TRC20Buy',
        '3': 'USDT-ERC20Deposit',
        '4': 'USDT-TRC20Deposit'
    }
    // 卡支付下单使用的产品（默认与商户默认产品一致）
    var CARD_PRODUCT = 'TRC20Buy'
    // 会员层级（与后台 lp_vip_plan.level 对应）
    var LEVELS = [
        { code: 'premium', name: 'Premium' },
        { code: 'premium_plus', name: 'Premium Plus' }
    ]
    var level = 'premium'
    // 默认支付方式与默认币种：USDT-TRC20 收款地址（各网络均可用，不依赖第三方刷卡页）
    var DEFAULT_METHOD = 'crypto'
    var DEFAULT_PRODUCT = 'USDT-TRC20Deposit'

    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    function toast(msg) {
        if (window.layer && layer.msg) { layer.msg(msg) } else { alert(msg) }
    }

    setTimeout(init, 800)

    function init() {
        tmplPc = $('#vip-template').html()
        tmplYd = $('#vip-template-yd').html()
        setupPayMethods()
        bindEvents()
        loadProducts()
        loadPlans()
        loadVipStatus()
        loadBalance()
        pollOrder(pendingOrderNo(), 12)
    }

    // ===== 支付方式 =====
    function setupPayMethods() {
        // 网关不支持 BSC，隐藏该入口（保留原站 DOM 结构）
        $('.usdt-group[data-text="5"], .usdt-group-yd[data-text="5"]').hide()
        setMethod(DEFAULT_METHOD)
    }

    // 切换「卡支付 / 币种支付」：卡支付收起币种列表，币种支付选中一个币种
    function setMethod(next) {
        method = next === 'crypto' ? 'crypto' : 'card'
        var showCrypto = method === 'crypto'
        $('#usdt-layout, #usdt-layout-yd').css('display', showCrypto ? 'flex' : 'none')
        // 入口选中态跟随切换（带 easypay 图片的为卡支付）
        $('#hairColor .hairColor, #hairColor-yd .hairColor-yd').each(function() {
            $(this).toggleClass('selected', ($(this).find('img').length > 0) === !showCrypto)
        })
        if (!showCrypto) {
            product = cardProduct()
            return
        }
        var $active = $('#usdt-layout .usdt-group.sub-gradient-active').first()
        if ($active.length) {
            product = COIN_PRODUCT[String($active.data('text'))] || product
        } else {
            selectDefaultCoin()
        }
    }

    function cardProduct() {
        var enabled = products.map(function(p) { return p.code })
        if (enabled.indexOf(CARD_PRODUCT) >= 0) { return CARD_PRODUCT }
        return enabled.length ? enabled[0] : CARD_PRODUCT
    }

    function bindEvents() {
        $('#vip-Layout').on('click', '.vip-group', function() { selectPlan('#vip-Layout', $(this), 'vip-group') })
        $('#vip-Layout-yd').on('click', '.vip-group-yd', function() { selectPlan('#vip-Layout-yd', $(this), 'vip-group-yd') })

        $('#usdt-layout, #usdt-layout-yd').on('click', '.usdt-group, .usdt-group-yd', function() {
            var $box = $(this).closest('#usdt-layout, #usdt-layout-yd')
            $box.find('.sub-gradient-active').removeClass('sub-gradient-active').addClass('sub-gradient')
            $(this).removeClass('sub-gradient').addClass('sub-gradient-active')
            product = COIN_PRODUCT[String($(this).data('text'))] || product
        })

        // 带 easypay 图标的入口为卡支付，另一个为币种支付
        $('#hairColor, #hairColor-yd').on('click', '.hairColor, .hairColor-yd', function() {
            setMethod($(this).find('img').length ? 'card' : 'crypto')
        })

        $('#payment').on('click', function(e) { e.preventDefault(); pay() })
        $('#payment-yd').on('click', function(e) { e.preventDefault(); pay() })
        $('#buyPoint').on('click', goRecharge)
        $('#buyPoint-yd').on('click', goRecharge)
        $(document).on('click', '#vip-claim-btn:not(:disabled)', claimDaily)
    }

    function selectPlan(scope, $item, cls) {
        $(scope).find('.' + cls).each(function() {
            $(this).removeClass('sub-gradient-active').addClass('sub-gradient')
        })
        $item.removeClass('sub-gradient').addClass('sub-gradient-active')
    }

    function goRecharge() {
        location.href = './purchasePoints.html'
    }

    // token 失效（A0100 Token无效 / A0101 Token已过期）：清登录态并回登录页
    function backToLogin(msg) {
        try { localStorage.removeItem('live_access_token') } catch (e) {}
        toast(msg || 'Please log in again')
        setTimeout(function(){ location.href = './Login.html' }, 900)
    }

    // ===== 支付方式可用性（以后台启用产品为准）=====
    function loadProducts() {
        $.ajax({ url: base + '/recharge/packages' }).done(function(r) {
            if (r.code === '00000' && r.data && r.data.products) {
                products = r.data.products || []
                applyProductAvailability()
            }
            setMethod(method)
        }).catch(function() { setMethod(method) })
    }

    function applyProductAvailability() {
        var enabled = {}
        products.forEach(function(p) { enabled[p.code] = true })
        if (!Object.keys(enabled).length) return
        $('#usdt-layout .usdt-group, #usdt-layout-yd .usdt-group-yd').each(function() {
            var code = COIN_PRODUCT[String($(this).data('text'))]
            if (!code || !enabled[code]) { $(this).hide() }
        })
        // 卡支付产品未启用时，整排「卡/币种」选择入口一并隐藏（只保留可支付的币种列表）
        if (!enabled[CARD_PRODUCT]) {
            $('#hairColor, #hairColor-yd').closest('fieldset').hide()
            if (method === 'card') { setMethod('crypto') }
        }
    }

    function selectDefaultCoin() {
        var $target = $('#usdt-layout .usdt-group').filter(function() {
            return COIN_PRODUCT[String($(this).data('text'))] === DEFAULT_PRODUCT
        }).filter(':visible').first()
        if (!$target.length) { $target = $('#usdt-layout .usdt-group:visible').first() }
        if ($target.length) { $target.trigger('click') } else { product = cardProduct() }
    }

    // ===== Plans =====
    function loadPlans() {
        $.ajax({
            url: base + '/vip/plans',
            headers: h(),
        }).done(function(r) {
            if (r.code === '00000' && r.data) {
                plans = r.data
                renderPlans()
            }
        }).catch(function(){})
    }

    /** 当前层级下的时长档位（缺层级字段时按基础层处理） */
    function levelPlans() {
        var list = plans.filter(function(p) { return (p.level || 'premium') === level })
        return list.length ? list : plans
    }

    function fill(tpl, p, list) {
        var months = parseInt(p.months, 10) || 1
        var usd = parseFloat(p.usd_price) || 0
        var per = usd / months
        var maxPerMonth = 0
        list.forEach(function(item) {
            var v = (parseFloat(item.usd_price) || 0) / (parseInt(item.months, 10) || 1)
            if (v > maxPerMonth) maxPerMonth = v
        })
        var off = maxPerMonth > 0 ? Math.round((1 - per / maxPerMonth) * 100) : 0
        return tpl
            .replace(/{{id}}/g, p.id)
            .replace(/{{month}}/g, months)
            .replace(/{{description}}/g, off)
            .replace(/{{title}}/g, usd.toFixed(2) + ' USDT')
            .replace(/\${{unit}}/g, per.toFixed(2))
            .replace(/{{decimal}}/g, String(parseFloat(p.daily_diamond) || 0))
    }

    /** 层级切换条：Premium / Premium Plus（每次渲染刷新选中态） */
    function ensureLevelBar(barId, layoutSel, yd) {
        if (!$(barId).length) {
            $(layoutSel).before('<div id="' + barId.replace('#', '') + '" style="display:flex;gap:10px;margin-bottom:14px"></div>')
            $(barId).on('click', '[data-level]', function() {
                level = String($(this).data('level'))
                renderPlans()
            })
        }
        $(barId).html(LEVELS.map(function(l) {
            var on = l.code === level
            return '<button type="button" data-level="' + l.code + '"' + (yd ? ' data-yd="1"' : '') +
                ' style="flex:1;padding:11px 14px;border-radius:12px;font:600 14px Poppins,sans-serif;cursor:pointer;' +
                (on
                    ? 'border:0;color:#fff;background:linear-gradient(135deg,#E75275,#6143E6)'
                    : 'border:1px solid rgba(255,255,255,.18);background:transparent;color:rgba(255,255,255,.72)') +
                '">' + l.name + '</button>'
        }).join(''))
    }

    /** 当前层级的权益列表 */
    function renderBenefits(boxSel, layoutSel, list) {
        var benefits = (list[0] && list[0].benefits) || []
        var $box = $(boxSel)
        if (!$box.length) {
            $(layoutSel).before('<div id="' + boxSel.replace('#', '') +
                '" style="margin-bottom:18px;padding:14px 16px;border-radius:12px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)"></div>')
            $box = $(boxSel)
        }
        if (!benefits.length) { $box.hide(); return }
        $box.show().html(benefits.map(function(t) {
            return '<div style="display:flex;align-items:flex-start;gap:8px;margin:6px 0;font:500 13px/18px Poppins,sans-serif;color:#fff">' +
                '<span style="color:#E75275;line-height:18px">&#10003;</span><span>' + t + '</span></div>'
        }).join(''))
    }

    function renderPlans() {
        var list = levelPlans()
        if (!list.length) { return }
        ensureLevelBar('#vip-level-bar', '#vip-Layout', false)
        ensureLevelBar('#vip-level-bar-yd', '#vip-Layout-yd', true)
        renderBenefits('#vip-benefits', '#vip-Layout', list)
        renderBenefits('#vip-benefits-yd', '#vip-Layout-yd', list)

        var htmlPc = ''
        var htmlYd = ''
        list.forEach(function(p) {
            htmlPc += fill(tmplPc || '', p, list)
            htmlYd += fill(tmplYd || '', p, list)
        })
        $('#vip-Layout').empty().append(htmlPc)
        $('#vip-Layout-yd').empty().append(htmlYd)
        $('#vip-Layout .vip-group').first().trigger('click')
        $('#vip-Layout-yd .vip-group-yd').first().trigger('click')
    }

    // ===== VIP status =====
    function loadVipStatus() {
        if (!token) return
        $.ajax({ url: base + '/vip/status', headers: h() }).done(function(r) {
            if (r.code === '00000' && r.data) {
                renderStatus(r.data)
            }
        }).catch(function(){})
    }

    function renderStatus(s) {
        var bar = $('#vip-status-bar')
        if (bar.length === 0) {
            $('#vip-Layout').before(
                '<div id="vip-status-bar" style="margin-bottom:16px;padding:16px;border-radius:12px;border:1px solid rgba(255,154,68,.35);background:rgba(255,154,68,.08)">' +
                '<div id="vip-status-text" style="font-size:14px;color:#fff;font-weight:600"></div>' +
                '<button id="vip-claim-btn" style="margin-top:12px;display:none;width:100%;padding:11px;border:none;border-radius:10px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;font-weight:700;cursor:pointer"></button>' +
                '</div>'
            )
        }
        var txt = $('#vip-status-text')
        if (s.is_vip) {
            txt.html('&#128081; ' + (s.level_name || 'Premium') + ' active &middot; <b style="color:#f59e0b">' + s.remain_days + '</b> days left (until ' + (s.expire_at || '').slice(0, 10) + ') &middot; ' + s.daily_diamond + ' diamonds daily')
            var btn = $('#vip-claim-btn')
            if (s.claimable_today) {
                btn.show().text('Claim today\'s ' + s.daily_diamond + ' diamonds').prop('disabled', false).css('opacity', 1)
            } else {
                btn.show().text('Already claimed today &#10003;').prop('disabled', true).css('opacity', .5)
            }
        } else {
            var quota = s.chat_quota || {}
            var freeTip = quota.limit > 0 ? ' &middot; ' + quota.remaining + ' free messages left today' : ''
            txt.html('Get membership: unlimited chatting + daily diamonds' + freeTip)
            $('#vip-claim-btn').hide()
        }
    }

    function claimDaily() {
        $.ajax({
            url: base + '/vip/claim-daily',
            method: 'POST',
            headers: h(),
        }).done(function(r) {
            if (r.code === '00000') {
                toast('Claimed +' + (r.data.claimed || 0) + ' diamonds')
                loadVipStatus()
                loadBalance()
            } else {
                toast(r.msg || 'Failed')
            }
        }).catch(function(){ toast('Network error') })
    }

    function loadBalance() {
        if (!token) return
        $.ajax({
            url: '/api/live/userInfo',
            method: 'POST',
            headers: { 'token': token },
        }).done(function(r) {
            if (r.code === 1 || r.code === '00000') {
                var money = (r.data && (r.data.money != null ? r.data.money : r.data.diamond_balance)) || 0
                $(".blanceamount").css("display", "block")
                $("#balance").html(money)
                $(".blanceamount-yd").css("display", "block")
                $("#balance-yd").html(money)
            }
        }).catch(function(){})
    }

    // ===== 下单支付 =====
    function pay() {
        if (!token) {
            toast('Please log in first')
            setTimeout(function(){ location.href = './Login.html' }, 900)
            return
        }
        var $sel = $('#vip-Layout .vip-group.sub-gradient-active, #vip-Layout-yd .vip-group-yd.sub-gradient-active').first()
        var id = $sel.data('id')
        if (!id) {
            toast('Please select a plan')
            return
        }

        var loadIdx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/vip/order',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify({ plan_id: id, product: product }),
            dataType: 'json',
        }).done(function(r) {
            layer.close(loadIdx)
            if (r && (r.code === 'A0100' || r.code === 'A0101')) { backToLogin(r.msg); return }
            if (r && r.code === '00000' && r.data && r.data.pay_url) {
                try { localStorage.setItem(PENDING_KEY, r.data.order_no) } catch (e) {}
                location.href = r.data.pay_url
            } else {
                toast((r && r.msg) || 'Create order failed')
            }
        }).fail(function(xhr) {
            layer.close(loadIdx)
            var res = xhr && xhr.responseJSON
            if (res && (res.code === 'A0100' || res.code === 'A0101')) { backToLogin(res.msg); return }
            toast((res && res.msg) || 'Network error')
        })
    }

    // ===== 支付回跳轮询 =====
    function pendingOrderNo() {
        var search = window.location.search
        if (search) {
            var raw = new URLSearchParams(search).get('params')
            if (raw) {
                try {
                    var p = JSON.parse(decodeURIComponent(raw))
                    if (p && p.merchant_ref) return p.merchant_ref
                } catch (e) {}
            }
        }
        try { return localStorage.getItem(PENDING_KEY) || '' } catch (e) { return '' }
    }

    function pollOrder(orderNo, times) {
        if (!orderNo || times <= 0 || !token) return
        $.ajax({
            url: base + '/recharge/status',
            headers: h(),
            data: { order_no: orderNo },
        }).done(function(r) {
            if (r && (r.code === 'A0100' || r.code === 'A0101')) {
                try { localStorage.removeItem(PENDING_KEY) } catch (e) {}
                return
            }
            if (r && r.code === '00000' && r.data && parseInt(r.data.status, 10) === 1) {
                try { localStorage.removeItem(PENDING_KEY) } catch (e) {}
                toast('Payment successful, membership activated')
                loadVipStatus()
                loadBalance()
                return
            }
            setTimeout(function(){ pollOrder(orderNo, times - 1) }, 5000)
        }).fail(function() {
            setTimeout(function(){ pollOrder(orderNo, times - 1) }, 5000)
        })
    }
})()
