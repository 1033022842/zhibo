/**
 * Subscriptions 页（英文界面 · USDT 计价 · 钻石余额支付）
 */
(()=>{
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    var cacheTemplate = ''
    var plans = []
    var vipStatus = null

    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    setTimeout(init, 800)

    function init() {
        cacheTemplate = $('#vip-template').html()
        bindEvents()
        loadPlans()
        loadVipStatus()
        loadBalance()
    }

    function bindEvents() {
        $('#vip-Layout').on('click', '.vip-group', function() {
            $('#vip-Layout').find('.sub-gradient-active').each((i, d) => {
                $(d).removeClass('sub-gradient-active')
                $(d).addClass('sub-gradient')
            })
            $(this).removeClass('sub-gradient')
            $(this).addClass('sub-gradient-active')
        })

        $('#payment').on('click', function(e) { e.preventDefault(); buyVip() })
        $('#payment-yd').on('click', function(e) { e.preventDefault(); buyVip() })
        $('#buyPoint').on('click', goRecharge)
        $('#buyPoint-yd').on('click', goRecharge)
        $(document).on('click', '#vip-claim-btn:not(:disabled)', claimDaily)
        $(document).on('click', '.vip-confirm-mask, .vip-confirm-cancel', closeConfirm)
        $(document).on('click', '.vip-confirm-ok', function() { closeConfirm(); doBuy(pendingPlanId) })
    }

    function goRecharge() {
        var tk = localStorage.getItem('live_access_token') || ''
        window.location.href = 'http://38.181.44.164/me/recharge#token=' + encodeURIComponent(tk)
    }

    // ===== Plans =====
    function loadPlans() {
        $.ajax({
            url: base + '/vip/plans',
            headers: h(),
        }).done(function(r) {
            if (r.code === '00000' && r.data) {
                plans = r.data
                renderPlans(plans)
            }
        }).catch(function(){})
    }

    function renderPlans(list) {
        if (!cacheTemplate || !list.length) return
        var maxPerMonth = 0
        list.forEach(function(p) {
            var per = parseFloat(p.usdt_price) / (parseInt(p.months) || 1)
            if (per > maxPerMonth) maxPerMonth = per
        })
        $('#vip-Layout').empty()
        list.forEach(function(p) {
            var months = parseInt(p.months) || 1
            var usdt = parseFloat(p.usdt_price)
            var per = usdt / months
            var off = maxPerMonth > 0 ? Math.round((1 - per / maxPerMonth) * 100) : 0
            var html = cacheTemplate
                .replace(/{{id}}/g, p.id)
                .replace(/{{month}}/g, months)
                .replace(/{{description}}/g, off)
                .replace(/{{title}}/g, usdt.toFixed(2) + ' USDT')
                .replace(/\${{unit}}/g, per.toFixed(2) + ' USDT')
                .replace(/{{decimal}}/g, p.daily_diamond + '/day')
            $('#vip-Layout').append(html)
        })
        $('#vip-Layout .vip-group').first().trigger('click')
    }

    // ===== VIP status =====
    function loadVipStatus() {
        if (!token) return
        $.ajax({ url: base + '/vip/status', headers: h() }).done(function(r) {
            if (r.code === '00000' && r.data) {
                vipStatus = r.data
                renderStatus(vipStatus)
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
            txt.html('&#128081; Membership active &middot; <b style="color:#f59e0b">' + s.remain_days + '</b> days left (until ' + (s.expire_at || '').slice(0, 10) + ') &middot; ' + s.daily_diamond + ' diamonds daily')
            var btn = $('#vip-claim-btn')
            if (s.claimable_today) {
                btn.show().text('Claim today\'s ' + s.daily_diamond + ' diamonds').prop('disabled', false).css('opacity', 1)
            } else {
                btn.show().text('Already claimed today &#10003;').prop('disabled', true).css('opacity', .5)
            }
        } else {
            txt.html('Get membership: daily diamonds + exclusive badge')
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
                layer.msg('Claimed +' + (r.data.claimed || 0) + ' diamonds')
                loadVipStatus()
                loadBalance()
            } else {
                layer.msg(r.msg || 'Failed')
            }
        }).catch(function(){ layer.msg('Network error') })
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

    // ===== Purchase (styled English confirm) =====
    var pendingPlanId = null

    function buyVip() {
        if (!token) {
            layer.msg('Please log in first')
            setTimeout(function(){ location.href = './Login.html' }, 900)
            return
        }
        var id = $('#vip-Layout .vip-group.sub-gradient-active').data('id')
        if (!id) {
            layer.msg('Please select a plan')
            return
        }
        var plan = plans.find(function(p){ return p.id == id })
        pendingPlanId = id

        if ($('#vip-confirm-mask').length === 0) {
            $('body').append(
                '<div class="vip-confirm-mask" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:20px">' +
                '<div class="vip-confirm-box" style="width:100%;max-width:380px;background:linear-gradient(180deg,#1c1c26,#14141c);border:1px solid rgba(231,82,117,.35);border-radius:16px;padding:26px 24px;text-align:center;box-shadow:0 20px 60px rgba(0,0,0,.5)">' +
                '<div style="font-size:38px;margin-bottom:8px">&#128176;</div>' +
                '<div id="vc-title" style="font-size:17px;font-weight:700;color:#fff;margin-bottom:4px"></div>' +
                '<div id="vc-sub" style="font-size:13px;color:rgba(255,255,255,.5);margin-bottom:18px"></div>' +
                '<div id="vc-price" style="font-size:26px;font-weight:800;color:#f59e0b;margin-bottom:22px"></div>' +
                '<button class="vip-confirm-ok" style="width:100%;padding:13px;border:none;border-radius:12px;background:linear-gradient(135deg,#E75275,#c73b5c);color:#fff;font-size:15px;font-weight:700;cursor:pointer;margin-bottom:10px">Confirm Payment</button>' +
                '<button class="vip-confirm-cancel" style="width:100%;padding:11px;border:1px solid rgba(255,255,255,.15);border-radius:12px;background:transparent;color:rgba(255,255,255,.6);font-size:14px;cursor:pointer">Cancel</button>' +
                '</div></div>'
            )
        }
        $('#vc-title').text(plan ? plan.name : 'Membership')
        $('#vc-sub').text((plan ? plan.months : '') + ' months membership')
        $('#vc-price').html('<span style="font-size:15px;font-weight:400;color:rgba(255,255,255,.45)">pay with </span>' + (plan ? plan.diamond_price : '') + ' diamonds')
        $('.vip-confirm-mask').css('display', 'flex')
    }

    function closeConfirm() { $('.vip-confirm-mask').css('display', 'none') }

    function doBuy(planId) {
        var loadIdx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/vip/buy',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify({ plan_id: planId }),
            dataType: 'json',
        }).done(function(r) {
            layer.close(loadIdx)
            if (r.code === '00000') {
                layer.msg('Activated! Expires ' + (r.data.expire_to || '').slice(0, 10))
                loadVipStatus()
                loadBalance()
            } else if (r.msg && r.msg.indexOf('余额不足') >= 0) {
                layer.msg('Insufficient diamonds')
                setTimeout(goRecharge, 900)
            } else {
                layer.msg(r.msg || 'Purchase failed')
            }
        }).fail(function() {
            layer.close(loadIdx)
            layer.msg('Network error')
        })
    }
})()
