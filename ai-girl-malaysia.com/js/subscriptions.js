/**
 * Subscriptions 页（版式复刻 candy /payments · 套餐数据来自后台 · star-pay USDT 收款）
 *
 * 页面结构由参考页原样保留，本脚本只做三件事：
 *   1) 用 /api/v1/vip/plans 的数据回填卡片价格、tokens 档位下拉与权益文案
 *   2) 记录用户点选的（层级 · 时长 · tokens 档位）
 *   3) 「Pay with」按钮 → /api/v1/vip/order 下单 → 跳转 star-pay 收银台，回跳后轮询到账
 */
;(function () {
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    // 收款地址类产品（商户仅启用 USDT 充值类，买币/刷卡类已下线）
    var PRODUCT = 'USDT-TRC20Deposit'
    var PENDING_KEY = 'vip_pending_order'
    var API_TIMEOUT = 15000

    var plans = []
    // 最近点选的卡片：{ level, months, tokens }
    var picked = null

    var PANEL_LEVEL = { 'premium': 'premium', 'premium-plus': 'premium_plus' }
    var TYPE_MONTHS = { yearly: 12, quarterly: 3, monthly: 1 }
    var MODAL_TITLE = { 'premium': '#sub-modal-plan-title', 'premium_plus': '#sub-modal-plan-title-premium-plus' }
    var MODAL_PRICE = { 'premium': '#sub-modal-plan-price', 'premium_plus': '#sub-modal-plan-price-premium-plus' }

    $(function () { setTimeout(init, 800) })

    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    function toast(msg) {
        if (window.layer && layer.msg) { layer.msg(msg) } else { alert(msg) }
    }

    function init() {
        bindEvents()
        loadPlans()
        pollOrder(pendingOrderNo(), 12)
    }

    // ===== 金额格式（整数金额不带小数，与参考页一致：$18 / $3.99）=====
    function money(v) {
        var n = Math.round((parseFloat(v) || 0) * 100) / 100
        return '$' + (n % 1 === 0 ? n.toFixed(0) : n.toFixed(2))
    }

    function monthLabel(months) {
        return months === 1 ? '1 Month' : months + ' Months'
    }

    function tierTitle(months, tokens) {
        return monthLabel(months) + ' · ' + tokens + ' tokens/mo'
    }

    // ===== 套餐数据回填 =====
    function loadPlans() {
        $.ajax({
            url: base + '/vip/plans',
            headers: h(),
            timeout: API_TIMEOUT,
        }).done(function (r) {
            if (r && r.code === '00000' && r.data) {
                plans = r.data
                renderPlans()
            }
        })
    }

    function renderPlans() {
        $.each(PANEL_LEVEL, function (tab, level) {
            var $panels = $('[data-tab-panel="' + tab + '"]').filter(function () {
                return $(this).find('[data-plan-type]').length > 0
            })
            if (!$panels.length) { return }

            var list = plans.filter(function (p) { return (p.level || 'premium') === level })
            if (!list.length) { return }

            $panels.each(function () {
                var $panel = $(this)
                $panel.find('[data-plan-type]').each(function () {
                    fillCard($(this), list)
                })
                fillPanelExtras($panel, level, list)
            })
        })
    }

    /** 单张卡片：写回档位属性、价格、折扣角标、tokens 下拉 */
    function fillCard($wrap, list) {
        var months = TYPE_MONTHS[$wrap.attr('data-plan-type')] || 1
        var $card = $wrap.find('[data-token-tier-card]').first()
        if (!$card.length) { return }

        var byTokens = {}
        list.forEach(function (p) {
            if (parseInt(p.months, 10) === months) { byTokens[String(p.tokens_month)] = p }
        })

        // 清掉参考页写死的档位属性，避免残留
        var attrs = $card[0].attributes
        for (var i = attrs.length - 1; i >= 0; i--) {
            if (attrs[i].name.indexOf('data-tier-') === 0) { $card.removeAttr(attrs[i].name) }
        }

        var keys = []
        var base = null
        $wrap.find('.token-tier-option').each(function () {
            var n = String($(this).attr('data-tokens') || '')
            if (!byTokens[n]) { $(this).hide(); return }
            keys.push(n)
            if (!base) { base = byTokens[n] }
        })
        if (!base) { return }

        var basePer = parseFloat(base.price_per_month) || 0
        var baseCmp = parseFloat(base.compare_price) || 0

        keys.forEach(function (n) {
            var p = byTokens[n]
            var per = parseFloat(p.price_per_month) || 0
            var amount = parseFloat(p.usd_price) || 0
            var cmp = parseFloat(p.compare_price) || 0
            $card.attr('data-tier-price' + n, money(per))
            $card.attr('data-tier-amount' + n, amount.toFixed(2))
            $card.attr('data-tier-modal-title' + n, tierTitle(months, n))
            if (cmp > 0) { $card.attr('data-tier-compare-price' + n, money(cmp)) }
            $card.attr('data-tier-benefit' + n, n + ' tokens per month')
            $card.attr('data-tier-billing' + n, billText(months, amount))
        })

        // 卡片默认显示最低档
        $wrap.find('[data-token-tier-price]').text(money(basePer))
        $wrap.find('[data-token-tier-compare-price]')
            .text(baseCmp > 0 ? money(baseCmp) : '')
            .toggle(baseCmp > 0)

        // 折扣角标：按 5% 向下取整（参考页 70% OFF / 35% OFF 同算法）
        if (baseCmp > basePer && baseCmp > 0) {
            var off = Math.floor((1 - basePer / baseCmp) * 100 / 5) * 5
            $wrap.find('p').filter(function () { return /% OFF/.test($(this).text()) }).text(off + '% OFF')
        }

        // 弹窗取值用（candy 的控制器点击卡片时读这三个属性）
        $card.attr('data-sub-card-button-plan-title-value', tierTitle(months, base.tokens_month))
        $card.attr('data-sub-card-button-plan-price-value', money(basePer))
        $card.attr('data-sub-card-button-plan-price-period-value', 'month')
        $wrap.attr('data-plan-price', (parseFloat(base.usd_price) || 0).toFixed(2))

        // tokens 下拉
        var firstKey = keys[0]
        $wrap.find('.token-tier-option').each(function () {
            var $opt = $(this)
            var n = String($opt.attr('data-tokens') || '')
            var p = byTokens[n]
            if (!p) { return }
            var per = parseFloat(p.price_per_month) || 0
            var cmp = parseFloat(p.compare_price) || 0
            var label = n + ' tokens/month'
            $opt.attr('data-label', label)
            $opt.find('.token-tier-option-value').text(label)
            if (n === firstKey) {
                $opt.find('.token-tier-option-delta').text('Included')
                $opt.find('.token-tier-option-savings').hide()
            } else {
                var delta = per - basePer
                $opt.find('.token-tier-option-delta').text('+$' + delta.toFixed(2) + '/mo')
                if (cmp > per && cmp > 0) {
                    $opt.find('.token-tier-option-savings')
                        .text('Save ' + (Math.floor((1 - per / cmp) * 100 / 5) * 5) + '%').show()
                } else {
                    $opt.find('.token-tier-option-savings').hide()
                }
            }
        })
        $wrap.find('.token-tier-trigger-value span').last().text(firstKey + ' tokens/month')
    }

    /** 面板级：权益里的 tokens 文案 + 结算说明 + 弹窗默认值 */
    function fillPanelExtras($panel, level, list) {
        var base = null
        list.forEach(function (p) {
            if ((parseInt(p.months, 10) || 1) === 12) { base = base || p }
        })
        base = base || list[0]
        var months = parseInt(base.months, 10) || 1
        var tokens = String(base.tokens_month)

        // 权益行里的「N tokens per month」
        var benefitText = tokens + ' tokens per month'
        $panel.find('[data-token-tier-benefit]').each(function () {
            var $el = $(this)
            $.each(list, function (_, p) {
                var n = String(p.tokens_month)
                $el.attr('data-tier-benefit' + n, n + ' tokens per month')
            })
            $el.text(benefitText)
        })

        // 「Annual payment billed as $47.88」等说明（按各时长的最低 tokens 档显示）
        $panel.find('[data-token-tier-picker-target="billingText"]').each(function () {
            var $el = $(this)
            var period = $el.closest('[data-subscription-target]').attr('data-subscription-target') || ''
            var m = /^annual/.test(period) ? 12 : (/^quarter/.test(period) ? 3 : 1)
            var lowest = null
            list.forEach(function (p) {
                if ((parseInt(p.months, 10) || 1) !== m) { return }
                var n = parseInt(p.tokens_month, 10) || 0
                var text = billText(m, p.usd_price)
                $el.attr('data-tier-billing' + n, text)
                if (lowest === null || n < lowest) { lowest = n; $el.text(text) }
            })
        })

        // 弹窗默认显示年付最低档
        var title = $(MODAL_TITLE[level]).first()
        var price = $(MODAL_PRICE[level]).first()
        if (title.length) { title.text(tierTitle(months, tokens)) }
        if (price.length) { price.text(money(base.price_per_month)) }
    }

    function billText(months, amount) {
        var word = months === 12 ? 'Annual' : (months === 3 ? 'Quarterly' : 'Monthly')
        return word + ' payment billed as $' + (parseFloat(amount) || 0).toFixed(2)
    }

    // ===== 点选记录 =====
    function bindEvents() {
        $(document).on('click', '[data-plan-type]', function () { remember($(this).closest('[data-plan-type]')) })
        $(document).on('click', '.token-tier-option, .token-tier-trigger', function () {
            var $w = $(this).closest('[data-plan-type]')
            if ($w.length) { remember($w) }
        })
        $(document).on('click', '#sub-pay-crypto, #sub-pay-crypto-plus', function (e) {
            e.preventDefault()
            pay($(this).attr('id'))
        })
    }

    function remember($w) {
        if (!$w.length) { return }
        var $panel = $w.closest('[data-tab-panel]')
        var tab = $.trim($panel.first().attr('data-tab-panel') || '')
        picked = {
            level: PANEL_LEVEL[tab] || 'premium',
            months: TYPE_MONTHS[$w.attr('data-plan-type')] || 1,
            tokens: readTokens($w),
        }
    }

    function readTokens($w) {
        var $sel = $w.find('.token-tier-option[aria-selected="true"]').first()
        var n = $sel.length ? String($sel.attr('data-tokens') || '') : ''
        if (!n) {
            var m = $.trim($w.find('.token-tier-trigger-value').text()).match(/(\d+)/)
            n = m ? m[1] : ''
        }
        return n
    }

    // ===== 下单 =====
    function pay(btnId) {
        if (!token) {
            toast('Please log in first')
            setTimeout(function () { location.href = './Login.html' }, 900)
            return
        }

        var level = btnId === 'sub-pay-crypto-plus' ? 'premium_plus' : 'premium'
        var sel = readSelection(level)
        var plan = findPlan(level, sel.months, sel.tokens)
        if (!plan) {
            toast('Please choose a plan first')
            return
        }

        var loadIdx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/vip/order',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify({ plan_id: plan.id, product: PRODUCT }),
            dataType: 'json',
            timeout: API_TIMEOUT,
        }).done(function (r) {
            layer.close(loadIdx)
            if (r && (r.code === 'A0100' || r.code === 'A0101')) { backToLogin(r.msg); return }
            if (r && r.code === '00000' && r.data && r.data.pay_url) {
                try { localStorage.setItem(PENDING_KEY, r.data.order_no) } catch (e) {}
                location.href = r.data.pay_url
            } else {
                toast((r && r.msg) || 'Create order failed')
            }
        }).fail(function (xhr) {
            layer.close(loadIdx)
            var res = xhr && xhr.responseJSON
            if (res && (res.code === 'A0100' || res.code === 'A0101')) { backToLogin(res.msg); return }
            toast((res && res.msg) || 'Network error')
        })
    }

    /** 以弹窗标题为准（candy 的控制器会随档位实时改写），点选记录兜底 */
    function readSelection(level) {
        var txt = $.trim($(MODAL_TITLE[level]).first().text())
        var mm = txt.match(/(\d+)\s*Month/i)
        var tm = txt.match(/(\d+)\s*tokens/i)
        var same = picked && picked.level === level
        return {
            months: mm ? parseInt(mm[1], 10) : (same ? picked.months : 0),
            tokens: tm ? tm[1] : (same ? String(picked.tokens || '') : ''),
        }
    }

    function findPlan(level, months, tokens) {
        var hit = null
        plans.forEach(function (p) {
            if ((p.level || 'premium') !== level) { return }
            if ((parseInt(p.months, 10) || 1) !== (parseInt(months, 10) || 0)) { return }
            if (String(p.tokens_month) !== String(tokens)) { return }
            hit = p
        })
        return hit
    }

    function backToLogin(msg) {
        try { localStorage.removeItem('live_access_token') } catch (e) {}
        toast(msg || 'Please log in again')
        setTimeout(function () { location.href = './Login.html' }, 900)
    }

    // ===== 支付回跳轮询 =====
    function pendingOrderNo() {
        var search = window.location.search
        if (search) {
            var raw = new URLSearchParams(search).get('params')
            if (raw) {
                try {
                    var p = JSON.parse(decodeURIComponent(raw))
                    if (p && p.merchant_ref) { return p.merchant_ref }
                } catch (e) {}
            }
        }
        try { return localStorage.getItem(PENDING_KEY) || '' } catch (e) { return '' }
    }

    function pollOrder(orderNo, times) {
        if (!orderNo || times <= 0 || !token) { return }
        $.ajax({
            url: base + '/recharge/status',
            headers: h(),
            data: { order_no: orderNo },
            timeout: API_TIMEOUT,
        }).done(function (r) {
            if (r && (r.code === 'A0100' || r.code === 'A0101')) {
                try { localStorage.removeItem(PENDING_KEY) } catch (e) {}
                return
            }
            if (r && r.code === '00000' && r.data && parseInt(r.data.status, 10) === 1) {
                try { localStorage.removeItem(PENDING_KEY) } catch (e) {}
                toast('Payment successful, membership activated')
                return
            }
            setTimeout(function () { pollOrder(orderNo, times - 1) }, 5000)
        }).fail(function () {
            setTimeout(function () { pollOrder(orderNo, times - 1) }, 5000)
        })
    }
})()
