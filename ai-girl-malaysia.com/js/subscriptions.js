/**
 * Subscriptions 页（版式复刻 candy /payments · 套餐数据来自后台）
 *
 * 页面结构由参考页原样保留，本脚本只做四件事：
 *   1) 用 /api/v1/vip/plans 的数据回填卡片价格、tokens 档位下拉与权益文案
 *   2) 记录用户点选的（层级 · 时长 · tokens 档位）
 *   3) 卡支付走 FlowPay 收单；加密货币走站内收款页 CryptoPay.html（后台自持地址）
 *   4) 按登录态切换版式：未登录不展示 Premium / Premium Plus 切换，卡片也不带 tokens 档位下拉
 *   5) 支付回跳：付成功了提示到账；没付成功则弹「Payment Unsuccessful」
 */
;(function () {
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    // 登录态：token 过期（/vip/status 返回 A0100/A0101）时会翻成 false
    var authed = !!token
    var PENDING_KEY = 'vip_pending_order'
    var API_TIMEOUT = 15000
    // 回跳后轮询到账：网关回调是服务端即时推送，正常几秒内就到；
    // 连续 POLL_TRIES 次（0/5/10/15 秒）还没支付就按失败弹提示。
    var POLL_MS = 5000
    var POLL_TRIES = 4
    // 待支付标记只当「刚支付回来」用，超过订单有效期（2 小时）就不再弹失败提示
    var PENDING_TTL = 2 * 60 * 60 * 1000

    var plans = []
    // 最近点选的卡片：{ level, months, tokens }
    var picked = null
    // 会员状态（/vip/status）
    var vipState = null
    // FlowPay 可选收单通道：[{ gcode, label }]，可多条时在弹窗里横向切换
    var cardChannels = []
    // FlowPay 是否可用（运营开关 + 商户参数 + 有收单通道）
    var flowpayEnabled = false
    // 后台法币通道（lp_fiat_channel）：每个国家一条，点进去按后台汇率折算成本币收款
    var fiatChannels = []

    var PANEL_LEVEL = { 'premium': 'premium', 'premium-plus': 'premium_plus' }
    var TYPE_MONTHS = { yearly: 12, quarterly: 3, monthly: 1 }
    var MODAL_TITLE = { 'premium': '#sub-modal-plan-title', 'premium_plus': '#sub-modal-plan-title-premium-plus' }
    var MODAL_PRICE = { 'premium': '#sub-modal-plan-price', 'premium_plus': '#sub-modal-plan-price-premium-plus' }

    $(function () { bindGuestCardGuard(); setTimeout(init, 800) })

    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    function toast(msg) {
        if (window.layer && layer.msg) { layer.msg(msg) } else if (window.SugusDialog) { SugusDialog.alert(msg, { title: 'Notice' }) } else { alert(msg) }
    }

    function init() {
        bindEvents()
        loadPlans()
        loadStatus()
        loadCardChannels()
        pollOrder(pendingOrderNo(), POLL_TRIES)
    }

    /**
     * 未登录版式：给 <html> 加 no-auth 类，具体差异（切换器、卡片 tokens 档位下拉、卡片高度）
     * 全部由页面 CSS 控制。首屏未登录的情况已在 head 的内联脚本里加过，这里只补 token 过期的情况。
     */
    function applyGuestLayout() {
        document.documentElement.classList.add('no-auth')
    }

    /**
     * 未登录点套餐卡片：与参考站一致，弹注册弹窗而不是支付方式弹窗。
     * 监听挂在容器捕获阶段，早于卡片自身（以及文档级）的点击 handler，
     * 拦下后 stopPropagation，避免支付方式弹窗被同时打开。
     */
    function bindGuestCardGuard() {
        var host = document.getElementById('subscriptions-container')
        if (!host) { return }
        host.addEventListener('click', function (e) {
            if (authed) { return }
            var t = e.target
            if (!t || typeof t.closest !== 'function' || !t.closest('[data-plan-type]')) { return }
            e.preventDefault()
            e.stopPropagation()
            openRegisterModal()
        }, true)
    }

    /** 打开注册弹窗（站内通用 markup，显示方式与 candy 的 main#openRegistrationModal 一致） */
    function openRegisterModal() {
        $('[data-main-target="registrationModal"]').css('display', 'flex')
        $('[data-main-target="signInModal"]').css('display', 'none')
    }

    /**
     * 支付方式：/recharge/packages 下发 FlowPay 收单通道 + 后台法币通道
     */
    function loadCardChannels() {
        $.ajax({
            url: base + '/recharge/packages',
            timeout: API_TIMEOUT,
        }).done(function (r) {
            if (r && r.code === '00000' && r.data) {
                fiatChannels = r.data.fiat || []
                cardChannels = (r.data.flowpay && r.data.flowpay.channels) || []
                flowpayEnabled = !!(r.data.flowpay && r.data.flowpay.enabled) && cardChannels.length > 0
            }
            pmRenderAll()
        }).fail(pmRenderAll)
    }

    // 两个弹窗各有独立的选中状态（Premium / Premium Plus）
    var PM_MODALS = [
        { id: 'payment-method-modal', level: 'premium', grid: '#sub-pm-grid', sub: '#sub-pm-sub', cta: '#sub-pay-card' },
        { id: 'payment-method-modal-premium-plus', level: 'premium_plus', grid: '#sub-pm-grid-plus', sub: '#sub-pm-sub-plus', cta: '#sub-pay-card-plus' }
    ]
    var PM_STATE = {
        premium: { method: '', gcode: '', fiatId: '' },
        premium_plus: { method: '', gcode: '', fiatId: '' }
    }

    var PM_CARD_ICON = '<img src="./subscriptions2_files/visa-57fb92185412d274068aa73ef3a863471f998aaf1c9917782e6df628b45532d8.svg" alt="Visa">' +
        '<img src="./subscriptions2_files/mastercard-ffed11dee9eb67d50b5c8cf60a4ae891032a34d74f23d2a9987a3e36c0e918fe.svg" alt="Mastercard">'
    var PM_COIN_ICONS = '<img src="./taocan_files/bitcoin-eb1103743040983f739826c12ef909c787ed1e6104bb0f5eff7112d31fde0036.svg" alt="Bitcoin">' +
        '<img src="./taocan_files/eth-0f77f739edf37b7fc60d467b5fb8317f4467f3bfce952c865551f5d8f4953124.svg" alt="Ethereum">' +
        '<img src="./taocan_files/litecoin-39b215ee717df466cd2599dc7a1f63ad0e05d619dca3a8b1a74be7112954086d.svg" alt="Litecoin">'

    function pmClean(s) { return String(s || '').replace(/[<>"]/g, '') }

    /** 收单通道名 → 展示名（Amopay-applepay → Apple Pay） */
    function pmCardLabel(s) {
        var t = String(s || '')
        if (/apple/i.test(t)) { return 'Apple Pay' }
        if (/google|gpay|ggpay/i.test(t)) { return 'Google Pay' }
        if (/master/i.test(t)) { return 'Mastercard' }
        if (/visa/i.test(t)) { return 'Visa' }
        return pmClean(t.replace(/^amopay[-_\s]*/i, '')) || 'Card'
    }

    function pmRenderAll() {
        PM_MODALS.forEach(function (m) {
            if ($(m.grid).length) { pmRender(m) }
        })
    }

    /** 当前可用方式：卡（FlowPay 开关 + 收单通道）/ 加密货币（始终）/ 法币（后台配了几条） */
    function pmAvailable() {
        return {
            card: !!(flowpayEnabled && cardChannels.length),
            crypto: true,
            cash: fiatChannels.length > 0
        }
    }

    function pmRender(m) {
        var st = PM_STATE[m.level]
        var avail = pmAvailable()
        if (!st.method || !avail[st.method]) {
            st.method = avail.card ? 'card' : (avail.crypto ? 'crypto' : 'cash')
        }
        st.gcode = avail.card ? (st.gcode || String(cardChannels[0].gcode || '')) : ''
        if (avail.cash) {
            var hit = false
            fiatChannels.forEach(function (fc) { if (String(fc.id) === String(st.fiatId)) hit = true })
            if (!hit) st.fiatId = String(fiatChannels[0].id)
        } else {
            st.fiatId = ''
        }

        // 整行长条：加密货币固定在最上面，其次是信用卡，再往下每个本币通道一条
        var html = ''
        html += '<button type="button" class="pm-row' + (st.method === 'crypto' ? ' is-on' : '') + '" data-pm-method="crypto">' +
            '<span class="pm-row-text">Continue with</span>' +
            '<span class="pm-row-icons">' + PM_COIN_ICONS + '</span></button>'
        if (avail.card) {
            html += '<button type="button" class="pm-row' + (st.method === 'card' ? ' is-on' : '') + '" data-pm-method="card">' +
                '<span class="pm-row-text">Credit Card</span>' +
                '<span class="pm-row-icons">' + PM_CARD_ICON + '</span></button>'
        }
        if (avail.cash) {
            fiatChannels.forEach(function (fc) {
                var logo = fiatLogo(fc.name)
                var unit = fc.symbol || fc.currency || ''
                var on = st.method === 'cash' && String(fc.id) === String(st.fiatId)
                html += '<button type="button" class="pm-row' + (on ? ' is-on' : '') +
                    '" data-pm-method="cash" data-pm-fiat="' + parseInt(fc.id, 10) + '">' +
                    '<span class="pm-row-text">' + pmClean(fc.name) + (unit !== '' ? ' · ' + pmClean(unit) : '') + '</span>' +
                    (logo ? '<span class="pm-row-icons"><img src="' + logo.src + '" alt="' + logo.alt + '"></span>' : '') +
                    '</button>'
            })
        }
        $(m.grid).html(html)

        pmRenderSub(m, avail)
        $(m.cta).text(st.method === 'card' ? 'Pay with Card'
            : (st.method === 'crypto' ? 'Pay with Crypto' : 'Pay with Cash'))
    }

    /** 信用卡有多条收单通道时，在下方给一排小胶囊 */
    function pmRenderSub(m, avail) {
        var st = PM_STATE[m.level]
        var html = ''
        if (st.method === 'card' && avail.card && cardChannels.length > 1) {
            html += '<div class="pm-sub-label">Choose card network</div><div class="pm-chips">'
            cardChannels.forEach(function (ch) {
                var logo = channelLogo(ch.label)
                var label = pmCardLabel(ch.label)
                html += '<button type="button" class="pm-chip' + (String(ch.gcode) === String(st.gcode) ? ' is-on' : '') +
                    '" data-pm-gcode="' + pmClean(ch.gcode) + '">' +
                    (logo ? '<img src="' + logo.src + '" alt="' + logo.alt + '">' : '') + label + '</button>'
            })
            html += '</div>'
        }
        if (html) { $(m.sub).html(html).prop('hidden', false) } else { $(m.sub).html('').prop('hidden', true) }
    }

    /**
     * 收单通道对应的品牌标：按通道名匹配，不在前端写死 gcode，
     * 后台加减通道或换 gcode 都不用改这里。匹配不到就只显示文字。
     */
    var CHANNEL_LOGOS = [
        { re: /apple/i, src: './subscriptions2_files/apple-pay-mark-f05534ee3bc2fbc2adcb2d15069c3d60c8555627e668a812f1452f4f5ee42356.svg', alt: 'Apple Pay' },
        { re: /google|gpay/i, src: './subscriptions2_files/google-pay-mark.svg', alt: 'Google Pay' },
        { re: /visa/i, src: './subscriptions2_files/visa-57fb92185412d274068aa73ef3a863471f998aaf1c9917782e6df628b45532d8.svg', alt: 'Visa' },
        { re: /master/i, src: './subscriptions2_files/mastercard-ffed11dee9eb67d50b5c8cf60a4ae891032a34d74f23d2a9987a3e36c0e918fe.svg', alt: 'Mastercard' }
    ]

    function channelLogo(label) {
        var text = String(label || '')
        for (var i = 0; i < CHANNEL_LOGOS.length; i++) {
            if (CHANNEL_LOGOS[i].re.test(text)) { return CHANNEL_LOGOS[i] }
        }
        return null
    }

    // 法币通道品牌标（巴西 PIX / 越南 MKPay）：按通道名匹配，匹配不到只显示文字
    var FIAT_LOGOS = [
        { re: /\bpix\b/i, src: './assets/pix.svg', alt: 'PIX' },
        { re: /mkpay/i, src: './assets/mkpay.svg', alt: 'MkPay' }
    ]

    function fiatLogo(name) {
        var text = String(name || '')
        for (var i = 0; i < FIAT_LOGOS.length; i++) {
            if (FIAT_LOGOS[i].re.test(text)) { return FIAT_LOGOS[i] }
        }
        return null
    }

    /** 会员状态：用于渲染卡片按钮文案（Select / Extend / Current Plan） */
    function loadStatus() {
        if (!authed) { applyCardCta(); return }
        $.ajax({
            url: base + '/vip/status',
            headers: h(),
            timeout: API_TIMEOUT,
        }).done(function (r) {
            if (r && r.code === '00000' && r.data) { vipState = r.data }
            // 本地存了 token 但已失效：按未登录版渲染
            if (r && (r.code === 'A0100' || r.code === 'A0101')) {
                authed = false
                applyGuestLayout()
            }
            applyCardCta()
        }).fail(function () { applyCardCta() })
    }

    /**
     * 卡片底部按钮文案（参考页快照写死了抓取账号的订阅态，这里按真实状态渲染）
     *   未登录 → Get Started；非会员 → Select；当前层级时长一致 → Current Plan；其它 → Extend
     */
    function applyCardCta() {
        $('[data-tab-panel]').filter(function () {
            return $(this).find('[data-plan-type]').length > 0
        }).each(function () {
            var level = PANEL_LEVEL[$.trim($(this).attr('data-tab-panel') || '')] || 'premium'
            $(this).find('[data-plan-type]').each(function () {
                var $wrap = $(this)
                var months = TYPE_MONTHS[$wrap.attr('data-plan-type')] || 1
                var label = authed ? 'Select' : 'Get Started'
                if (vipState && vipState.is_vip) {
                    label = (vipState.level === level && (parseInt(vipState.plan_months, 10) || 0) === months)
                        ? 'Current Plan' : 'Extend'
                }
                // 卡片 CTA 文案的 span 统一带 text-[16px]（桌面/移动、两个层级都是）
                $wrap.find('span[class*="text-[16px]"]').text(label)
            })
        })
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
        // 支付方式长条：只做选中，真正下单统一走底部按钮
        $(document).on('click', '[data-pm-method]', function () {
            var m = pmModalOf(this)
            if (!m) { return }
            var st = PM_STATE[m.level]
            st.method = String($(this).attr('data-pm-method') || '')
            // 本币通道行同时带 data-pm-fiat，必须在同一次事件里取完再重渲染
            var fid = $(this).attr('data-pm-fiat')
            if (fid) { st.fiatId = String(fid) }
            pmRender(m)
        })
        // 卡组织 / 本币通道切换
        $(document).on('click', '[data-pm-gcode]', function () {
            var m = pmModalOf(this)
            if (!m) { return }
            PM_STATE[m.level].gcode = String($(this).attr('data-pm-gcode') || '')
            pmRender(m)
        })
        // 底部 Pay 按钮：按当前选中的方式执行
        $(document).on('click', '[data-pm-cta]', function () {
            var m = pmModalOf(this)
            if (!m) { return }
            var st = PM_STATE[m.level]
            if (st.method === 'card') {
                payLevel(m.level, 'flowpay', st.gcode)
            } else if (st.method === 'crypto') {
                cryptoPayLevel(m.level)
            } else if (st.method === 'cash' && st.fiatId) {
                fiatPay(st.fiatId, m.level)
            }
        })
    }

    /** 事件源所在的弹窗配置 */
    function pmModalOf(el) {
        var id = $(el).closest('dialog').attr('id') || ''
        for (var i = 0; i < PM_MODALS.length; i++) {
            if (PM_MODALS[i].id === id) { return PM_MODALS[i] }
        }
        return null
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
    /** 加密货币：跳站内收款页（后台链上收款渠道，金额取当前选中的套餐） */
    function cryptoPayLevel(level) {
        var sel = readSelection(level)
        var plan = findPlan(level, sel.months, sel.tokens)
        if (!plan) {
            toast('Please choose a plan first')
            return
        }
        location.href = './CryptoPay.html?kind=vip&plan_id=' + encodeURIComponent(plan.id)
    }

    /** 法币通道：同样跳站内收款页，金额由服务端按后台汇率折算成本币 */
    function fiatPay(fiatId, level) {
        var sel = readSelection(level)
        var plan = findPlan(level, sel.months, sel.tokens)
        if (!plan) {
            toast('Please choose a plan first')
            return
        }
        location.href = './CryptoPay.html?kind=vip&fiat_id=' + encodeURIComponent(fiatId) +
            '&plan_id=' + encodeURIComponent(plan.id)
    }

    function payLevel(level, gateway, gcode) {
        if (!token) {
            toast('Please log in first')
            setTimeout(function () { location.href = './Login.html' }, 900)
            return
        }

        var sel = readSelection(level)
        var plan = findPlan(level, sel.months, sel.tokens)
        if (!plan) {
            toast('Please choose a plan first')
            return
        }

        // 卡支付走 FlowPay 收单通道（用户点了哪个按钮就走哪条）；加密货币不走这里，走站内收款页
        var payload = { plan_id: plan.id, gateway: gateway }
        if (gcode) { payload.gcode = gcode }

        var loadIdx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/vip/order',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify(payload),
            dataType: 'json',
            timeout: API_TIMEOUT,
        }).done(function (r) {
            layer.close(loadIdx)
            if (r && (r.code === 'A0100' || r.code === 'A0101')) { backToLogin(r.msg); return }
            if (r && r.code === '00000' && r.data && r.data.pay_url) {
                savePending(r.data.order_no, r.data.pay_url)
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

    // ===== 支付回跳 =====
    /** 记录待支付订单（含下单时间，用于判断是不是「刚支付回来」） */
    function savePending(orderNo, payUrl) {
        try { localStorage.setItem(PENDING_KEY, JSON.stringify({ o: orderNo, t: Date.now(), u: payUrl || '', k: 'vip' })) } catch (e) {}
    }

    function readPending() {
        var raw = ''
        try { raw = localStorage.getItem(PENDING_KEY) || '' } catch (e) { return null }
        if (!raw) { return null }
        try {
            var p = JSON.parse(raw)
            if (p && p.o) { return { o: String(p.o), t: parseInt(p.t, 10) || 0 } }
        } catch (e) {}
        return { o: raw, t: 0 } // 兼容旧格式（只存了订单号）
    }

    function clearPending() {
        try { localStorage.removeItem(PENDING_KEY) } catch (e) {}
    }

    /** 本次是否需要轮询到账：网关回跳带的单号优先，其次看本地待支付标记 */
    function pendingOrderNo() {
        var raw = new URLSearchParams(window.location.search || '').get('params')
        if (raw) {
            try {
                var p = JSON.parse(decodeURIComponent(raw))
                if (p && p.merchant_ref) { return String(p.merchant_ref) }
            } catch (e) {}
        }
        var pend = readPending()
        if (!pend) { return '' }
        if (pend.t > 0 && Date.now() - pend.t > PENDING_TTL) {
            clearPending()
            return ''
        }
        return pend.o
    }

    /**
     * 轮询订单状态：
     *   已支付 → 成功提示；网关明确回未支付 / 连续几次都没付成功 → 弹「Payment Unsuccessful」
     */
    function pollOrder(orderNo, tries, known) {
        if (!orderNo || tries <= 0 || !token) { return }
        $.ajax({
            url: base + '/recharge/status',
            headers: h(),
            data: { order_no: orderNo },
            timeout: API_TIMEOUT,
        }).done(function (r) {
            if (r && (r.code === 'A0100' || r.code === 'A0101')) { clearPending(); return }
            var d = (r && r.code === '00000' && r.data) ? r.data : null
            if (d) {
                known = true
                if (parseInt(d.status, 10) === 1) { paySucceeded(d, orderNo); return }
                if (parseInt(d.pay_state, 10) === 2) { payFailed(); return }
            }
            // 订单号查不到（后台已清理等）不算支付失败，只有确认过这笔单确实未支付才提示
            if (tries <= 1) { if (known) { payFailed() } return }
            setTimeout(function () { pollOrder(orderNo, tries - 1, known) }, POLL_MS)
        }).fail(function () {
            // 请求失败只是没查到，不代表支付失败：继续重试，用尽就保持沉默等下次进页面再查
            if (tries <= 1) { if (known) { payFailed() } return }
            setTimeout(function () { pollOrder(orderNo, tries - 1, known) }, POLL_MS)
        })
    }

    function paySucceeded(d, orderNo) {
        clearPending()
        toast('Payment successful, membership activated')
        // 转化事件：订阅成功（与服务端 CAPI 用同一个 event_id = 订单号，去重）
        try {
            if (window.SugusTrack) SugusTrack.event('subscribe', { value: d.pay_amount }, orderNo || '')
        } catch (e) {}
    }

    /** 支付没成功：与参考站一致弹「Payment Unsuccessful」 */
    function payFailed() {
        clearPending()
        var msg = "We're sorry, but your payment could not be processed. Please check your payment details and try again."
        if (window.SugusDialog && SugusDialog.alert) {
            SugusDialog.alert(msg, { title: 'Payment Unsuccessful', okText: 'OK', icon: 'warn' })
            return
        }
        toast('Payment Unsuccessful')
    }
})()
