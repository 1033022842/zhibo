(() => {
    var RECHARGE_BASE = '/api/v1/recharge'
    var LIVE_BASE = '/api/live'
    var COIN_ICONS = [
        './tokens_files/coin_1-20c5ab8c96a9268b33d18c0b6a2f00cc9aae9cf93484db35890df062588ce9ef.webp',
        './tokens_files/coin_2-8de047415dbc9fdda2a5b6419e19c5c82e0a5d2e4e277bd38cc5691b54e0cb5f.webp',
        './tokens_files/coin_3-93c4a360cb91a06412464426cf9e389daaf3a2c18943fe9842da534bd4075524.webp',
        './tokens_files/coin_4-7384f3ae326ebaab75eef8c23f7859db9a992732a230a086058930aa43d90ac9.webp',
        './tokens_files/coin_5-9a5e24f5a0754719837b8013162ac63db92c9791c24ca3f793bbe1e3b0585f89.webp',
        './tokens_files/coin_6-e52dead2f4c02b98fcea5e2c710efbdd94d0bd0339a5c824bcd230dea25545cf.webp'
    ]
    var CUSTOM_COIN = './tokens_files/coin_custom-81a484ff06abb2ce853b520dc59090edd3ec015802ca3240d30405c381994958.webp'

    var SLIDER_STEP = 50
    var DEFAULT_TOKENS = 550
    // 卡支付品牌标：与 Subscriptions.html 支付弹窗同一组素材（33x20 白底圆角，四个并排尺寸才齐）
    var APPLE_PAY_ICON = './subscriptions2_files/apple-pay-mark-f05534ee3bc2fbc2adcb2d15069c3d60c8555627e668a812f1452f4f5ee42356.svg'
    var GOOGLE_PAY_ICON = './subscriptions2_files/google-pay-mark.svg'
    var VISA_ICON = './subscriptions2_files/visa-57fb92185412d274068aa73ef3a863471f998aaf1c9917782e6df628b45532d8.svg'
    var MASTERCARD_ICON = './subscriptions2_files/mastercard-ffed11dee9eb67d50b5c8cf60a4ae891032a34d74f23d2a9987a3e36c0e918fe.svg'
    // 渐变胶囊按钮上并排的四个标，顺序与 Subscriptions.html 一致
    var CARD_PILL_ICONS = [
        { src: APPLE_PAY_ICON, alt: 'Apple Pay' },
        { src: GOOGLE_PAY_ICON, alt: 'Google Pay' },
        { src: VISA_ICON, alt: 'Visa' },
        { src: MASTERCARD_ICON, alt: 'Mastercard' }
    ]
    // 收单通道 → 品牌标：按通道名匹配，不在前端写死 gcode（与 Subscriptions.html 同一套规则）
    var CHANNEL_LOGOS = [
        { re: /apple/i, src: APPLE_PAY_ICON, alt: 'Apple Pay' },
        { re: /google|gpay/i, src: GOOGLE_PAY_ICON, alt: 'Google Pay' },
        { re: /visa/i, src: VISA_ICON, alt: 'Visa' },
        { re: /master/i, src: MASTERCARD_ICON, alt: 'Mastercard' }
    ]
    var packages = []
    var channels = []
    var flowpayEnabled = false
    // FlowPay 可选收单通道：[{ gcode, label }]，点开卡按钮后就地展开成多个通道按钮
    var flowpayChannels = []
    // 后台法币通道（lp_fiat_channel）：每个国家一条，下单时按后台汇率把 USD 折算成本币
    var fiatChannels = []
    var selected = null
    var customTokens = 0
    var customTimer = null
    var bubbleTimer = null
    var loading = false

    function token() {
        return localStorage.getItem('live_access_token') || ''
    }

    function money(n) {
        return '$' + Number(n || 0).toFixed(2)
    }

    function toast(msg) {
        if (window.layer && layer.msg) {
            layer.msg(msg)
        } else if (window.SugusDialog) {
            SugusDialog.alert(msg, { title: 'Notice' })
        } else {
            alert(msg)
        }
    }

    function api(url, options) {
        var tk = token()
        return $.ajax($.extend({
            url: url,
            method: 'GET',
            headers: tk ? { token: tk } : {}
        }, options || {}))
    }

    // ---------- 套餐渲染 ----------
    function renderPlans() {
        var $grid = $('#tk-plan-grid')
        if (!$grid.length) return
        if (!packages.length) {
            $grid.html('<div style="grid-column:1/-1;text-align:center;color:#a4a4a4;font-size:14px;">No packages available</div>')
            return
        }
        var html = ''
        packages.forEach(function (p, i) {
            var bonus = parseInt(p.bonus_percent || 0, 10)
            html += '<div class="tk-plan" data-index="' + i + '">'
            if (bonus > 0) {
                html += '<div class="tk-bonus">+' + bonus + '% bonus</div>'
            }
            html += '<div class="tk-plan-top">'
            html += '<div class="tk-coin"><img alt="" src="' + (COIN_ICONS[i] || CUSTOM_COIN) + '"></div>'
            html += '<div class="tk-qty">' + parseInt(p.tokens, 10) + '</div>'
            html += '</div>'
            html += '<div class="tk-bottom"><div class="tk-price"><span>' + money(p.price) + '</span></div></div>'
            html += '</div>'
        })
        $grid.html(html)
    }

    // ---------- 自选数量卡（连续点数，价格按相邻档位线性插值）----------
    function sortedPackages() {
        return packages.slice().sort(function (a, b) {
            return parseInt(a.tokens, 10) - parseInt(b.tokens, 10)
        })
    }

    function sliderStep(min, max) {
        return (min % SLIDER_STEP === 0 && max % SLIDER_STEP === 0) ? SLIDER_STEP : 1
    }

    function progressPct(tokens, min, max) {
        return max > min ? ((tokens - min) / (max - min)) * 100 : 100
    }

    // 与后端 RechargeService::quoteByTokens 完全一致的线性插值
    function quoteTokens(tokens) {
        var list = sortedPackages()
        if (!list.length) return null
        var min = parseInt(list[0].tokens, 10)
        var max = parseInt(list[list.length - 1].tokens, 10)
        tokens = Math.max(min, Math.min(max, tokens))

        var low = list[0], high = list[list.length - 1]
        for (var i = 0; i < list.length; i++) {
            if (parseInt(list[i].tokens, 10) <= tokens && i + 1 < list.length) {
                low = list[i]
                high = list[i + 1]
            }
        }
        var lt = parseInt(low.tokens, 10)
        var ht = parseInt(high.tokens, 10)
        var lowBonus = parseInt(low.bonus_percent || 0, 10)
        var highBonus = parseInt(high.bonus_percent || 0, 10)
        var ratio = ht > lt ? (tokens - lt) / (ht - lt) : 0
        var price = parseFloat(low.price) + ratio * (parseFloat(high.price) - parseFloat(low.price))

        return {
            tokens: tokens,
            min: min,
            max: max,
            price: Math.round(price * 100) / 100,
            bonus_percent: Math.round(lowBonus + ratio * (highBonus - lowBonus))
        }
    }

    function normalizeTokens(tokens) {
        var q = quoteTokens(tokens)
        if (!q) return 0
        var step = sliderStep(q.min, q.max)
        var snapped = q.min + Math.round((q.tokens - q.min) / step) * step
        return Math.max(q.min, Math.min(q.max, snapped))
    }

    function coinSrcFor(tokens) {
        var best = 0, bestDiff = -1
        packages.forEach(function (p, i) {
            var d = Math.abs(parseInt(p.tokens, 10) - tokens)
            if (bestDiff < 0 || d < bestDiff) { bestDiff = d; best = i }
        })
        return COIN_ICONS[best] || CUSTOM_COIN
    }

    function syncInputWidth() {
        var sizer = document.getElementById('tk-custom-sizer')
        var input = document.getElementById('tk-custom-input')
        if (!sizer || !input) return
        sizer.textContent = input.value
        input.style.width = sizer.offsetWidth + 'px'
    }

    function replay(el, cls) {
        if (!el || !el.classList) return
        el.classList.remove(cls)
        void el.offsetWidth
        el.classList.add(cls)
    }

    function updateBubble(tokens) {
        var el = document.getElementById('tk-custom-bubble')
        var slider = document.getElementById('tk-custom-slider')
        if (!el || !slider) return
        el.textContent = tokens
        var thumb = 26
        var pct = progressPct(tokens, parseFloat(slider.min), parseFloat(slider.max)) / 100
        el.style.left = (thumb / 2 + pct * Math.max(0, slider.clientWidth - thumb)) + 'px'
    }

    function showBubble() {
        var el = document.getElementById('tk-custom-bubble')
        if (el) el.classList.add('is-visible')
    }

    function hideBubble() {
        var el = document.getElementById('tk-custom-bubble')
        if (el) el.classList.remove('is-visible')
    }

    // 拖动停止后向服务端确认价格，保证「显示价 = 实际下单价」
    function scheduleQuoteCheck(tokens) {
        clearTimeout(customTimer)
        customTimer = setTimeout(function () {
            api(RECHARGE_BASE + '/quote', { data: { tokens: tokens } }).done(function (res) {
                if (typeof res === 'string') res = JSON.parse(res)
                if (res.code !== '00000' || !res.data) return
                if (parseInt(res.data.tokens, 10) !== customTokens) return
                var shown = parseFloat(String($('#tk-custom-price').text()).replace(/[^0-9.]/g, ''))
                var server = parseFloat(res.data.price)
                if (!isNaN(shown) && Math.abs(shown - server) < 0.005) return
                $('#tk-custom-price').text(money(server))
                replay(document.getElementById('tk-custom-price'), 'tk-custom-price-pop')
            })
        }, 250)
    }

    // opt.syncInput：是否把点数写回输入框（拖动/失焦时写，边打字时不写）
    // opt.serverCheck：是否向服务端确认价格
    function renderCustom(tokens, opt) {
        opt = opt || {}
        var q = quoteTokens(tokens)
        if (!q) return
        customTokens = q.tokens

        var slider = document.getElementById('tk-custom-slider')
        if (slider) {
            slider.min = String(q.min)
            slider.max = String(q.max)
            slider.step = String(sliderStep(q.min, q.max))
            slider.value = String(q.tokens)
            slider.style.setProperty('--token-slider-progress', progressPct(q.tokens, q.min, q.max) + '%')
        }

        if (opt.syncInput !== false) $('#tk-custom-input').val(q.tokens)
        $('#tk-custom-min').text(q.min)
        $('#tk-custom-max').text(q.max)
        $('#tk-custom-coin').attr('src', coinSrcFor(q.tokens))

        var priceText = money(q.price)
        if ($('#tk-custom-price').text() !== priceText) {
            $('#tk-custom-price').text(priceText)
            replay(document.getElementById('tk-custom-price'), 'tk-custom-price-pop')
        }

        var bonusText = '+' + q.bonus_percent + '% bonus'
        var chipText = document.getElementById('tk-custom-chip-text')
        var chip = document.getElementById('tk-custom-chip')
        if (chipText && chipText.textContent !== bonusText) {
            chipText.textContent = bonusText
            replay(chipText, 'is-pulse')
        }
        if (chip) chip.setAttribute('aria-hidden', q.bonus_percent > 0 ? 'false' : 'true')

        updateBubble(q.tokens)
        syncInputWidth()
        if (opt.serverCheck) scheduleQuoteCheck(q.tokens)
    }

    function loadPackages() {
        api(RECHARGE_BASE + '/packages').done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code !== '00000' || !res.data) return
            packages = res.data.list || []
            flowpayEnabled = !!(res.data.flowpay && res.data.flowpay.enabled)
            flowpayChannels = (res.data.flowpay && res.data.flowpay.channels) || []
            // 法币通道（后台 lp_fiat_channel）
            fiatChannels = res.data.fiat || []
            renderPlans()
            renderCustom(normalizeTokens(DEFAULT_TOKENS))
            if ($('#tk-payment-modal').css('display') === 'flex') renderMethods()
        })
        loadChannels()
    }

    // ---------- 链上地址收款渠道（ETH 等） ----------
    function loadChannels() {
        if (!token()) return
        api(RECHARGE_BASE + '/channels').done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code !== '00000' || !res.data) return
            channels = res.data || []
            if ($('#tk-payment-modal').css('display') === 'flex') renderMethods()
        })
    }

    // ---------- 余额 ----------
    function loadBalance() {
        var tk = token()
        if (!tk) return
        $('#tk-balance-row').css('display', 'block')
        api(LIVE_BASE + '/userInfo').done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code === '00000' && res.data) {
                var money = parseFloat(res.data.money || 0)
                $('#balance').html(money.toLocaleString('en-US'))
                var u = {}
                try { u = JSON.parse(localStorage.getItem('live_user_info') || '{}') } catch (e) { u = {} }
                u.money = money
                localStorage.setItem('live_user_info', JSON.stringify(u))
            }
        })
    }

    // ---------- 支付弹窗（Plan selected + 方式格子 + 底部 Pay 按钮） ----------
    // 选中的支付方式：card / crypto / cash；子选项分别落在 gcode / fiatId
    var PM = { method: '', gcode: '', fiatId: '' }

    var PM_CARD_ICON = '<img src="' + VISA_ICON + '" alt="Visa"><img src="' + MASTERCARD_ICON + '" alt="Mastercard">'
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

    /** 当前可用方式：卡（FlowPay 开关 + 收单通道）/ 加密货币（始终）/ 法币（后台配了几条） */
    function pmAvailable() {
        return {
            card: !!(flowpayEnabled && flowpayChannels.length),
            crypto: true,
            cash: fiatChannels.length > 0
        }
    }

    function renderMethods() {
        var box = $('#pm-grid')
        if (!box.length) return
        var avail = pmAvailable()
        if (!PM.method || !avail[PM.method]) {
            PM.method = avail.card ? 'card' : (avail.crypto ? 'crypto' : 'cash')
        }
        PM.gcode = avail.card ? (PM.gcode || String(flowpayChannels[0].gcode || '')) : ''
        if (avail.cash) {
            var hit = false
            fiatChannels.forEach(function (fc) { if (String(fc.id) === String(PM.fiatId)) hit = true })
            if (!hit) PM.fiatId = String(fiatChannels[0].id)
        } else {
            PM.fiatId = ''
        }

        // 版式：加密货币独占第一排，信用卡与各本币通道两列排；点任意一格直接进入支付
        var html = ''
        html += '<button type="button" class="pm-row" data-pm-method="crypto">' +
            '<span class="pm-row-text">Continue with</span>' +
            '<span class="pm-row-icons">' + PM_COIN_ICONS + '</span></button>'
        if (avail.card) {
            html += '<button type="button" class="pm-row" data-pm-method="card">' +
                '<span class="pm-row-text">Credit Card</span>' +
                '<span class="pm-row-icons">' + PM_CARD_ICON + '</span></button>'
        }
        if (avail.cash) {
            fiatChannels.forEach(function (fc) {
                var logo = fiatLogo(fc.name)
                var unit = fc.symbol || fc.currency || ''
                html += '<button type="button" class="pm-row" data-pm-method="cash" data-pm-fiat="' + parseInt(fc.id, 10) + '">' +
                    '<span class="pm-row-text">' + pmClean(fc.name) + (unit !== '' ? ' · ' + pmClean(unit) : '') + '</span>' +
                    (logo ? '<span class="pm-row-icons"><img src="' + logo.src + '" alt="' + logo.alt + '"></span>' : '') +
                    '</button>'
            })
        }
        box.html(html)

        renderPmSub(avail)
    }

    /** 信用卡有多条收单通道时，在方式下方给一排可切换的小胶囊（点了只切通道，不发起支付） */
    function renderPmSub(avail) {
        var box = $('#pm-sub')
        var html = ''
        if (avail.card && flowpayChannels.length > 1) {
            html += '<div class="pm-sub-label">Choose card network</div><div class="pm-chips">'
            flowpayChannels.forEach(function (ch) {
                var logo = channelLogo(ch.label)
                var label = pmCardLabel(ch.label)
                html += '<button type="button" class="pm-chip' + (String(ch.gcode) === String(PM.gcode) ? ' is-on' : '') +
                    '" data-pm-gcode="' + pmClean(ch.gcode) + '">' +
                    (logo ? '<img src="' + logo.src + '" alt="' + logo.alt + '">' : '') +
                    label + '</button>'
            })
            html += '</div>'
        }
        if (html) { box.html(html).prop('hidden', false) } else { box.html('').prop('hidden', true) }
    }

    /** 通道名 → 品牌标（匹配不到就只显示文字） */
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

    // sel: { tokens, price, packageId }
    function openModal(sel) {
        if (!sel) return
        selected = sel
        $('#pm-plan-title').text(sel.tokens + ' tokens')
        $('#pm-plan-price').text(money(sel.price))
        $('#pm-summary').text('One-time payment billed as ' + money(sel.price))
        renderMethods()
        $('#tk-payment-modal').css('display', 'flex')
        hideBubble()
    }

    function closeModal() {
        $('#tk-payment-modal').css('display', 'none')
        selected = null
    }

    function submitOrder(code, gateway, gcode) {
        if (loading || !selected) return
        if (!token()) {
            toast('Please log in first')
            location.href = './Login.html'
            return
        }
        loading = true
        $('#tk-payment-modal .pm-row, #tk-payment-modal .pm-chip').attr('disabled', true).css('opacity', .6)
        var payload = selected.packageId
            ? { package_id: selected.packageId, product: code }
            : { tokens: selected.tokens, product: code }
        if (gateway) payload.gateway = gateway
        // FlowPay 收单通道（用户点了哪个按钮就走哪条）
        if (gcode) payload.gcode = gcode
        api(RECHARGE_BASE + '/createOrder', {
            method: 'POST',
            data: payload
        }).done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code !== '00000' || !res.data) {
                toast(res.msg || 'Create order failed')
                return
            }
            var payUrl = res.data.pay_url || ''
            if (!payUrl) {
                toast('Payment url unavailable')
                return
            }
            savePending(res.data.order_no, payUrl)
            location.href = payUrl
        }).fail(function () {
            toast('Network error, please retry')
        }).always(function () {
            loading = false
            $('#tk-payment-modal .pm-row, #tk-payment-modal .pm-chip').removeAttr('disabled').css('opacity', 1)
        })
    }

    // ---------- 支付回跳 ----------
    var TK_PENDING_KEY = 'tk_pending_order'
    // 网关回调是服务端即时推送，正常几秒内就到；连续几次还没支付就按失败提示
    var TK_POLL_MS = 5000
    var TK_POLL_TRIES = 4
    // 待支付标记只当「刚支付回来」用，超过订单有效期（2 小时）就不再弹失败提示
    var TK_PENDING_TTL = 2 * 60 * 60 * 1000

    /** 记录待支付订单（含下单时间，用于判断是不是「刚支付回来」） */
    function savePending(orderNo, payUrl) {
        try { localStorage.setItem(TK_PENDING_KEY, JSON.stringify({ o: orderNo, t: Date.now(), u: payUrl || '', k: 'tk' })) } catch (e) {}
    }

    function readPending() {
        var raw = ''
        try { raw = localStorage.getItem(TK_PENDING_KEY) || '' } catch (e) { return null }
        if (!raw) return null
        try {
            var p = JSON.parse(raw)
            if (p && p.o) return { o: String(p.o), t: parseInt(p.t, 10) || 0 }
        } catch (e) {}
        return { o: raw, t: 0 } // 兼容旧格式（只存了订单号）
    }

    function clearPending() {
        try { localStorage.removeItem(TK_PENDING_KEY) } catch (e) {}
    }

    /** 本次是否需要轮询到账：网关回跳带的单号优先，其次看本地待支付标记 */
    function orderNoFromReturn() {
        var q = new URLSearchParams(window.location.search || '')
        var raw = q.get('params')
        if (raw) {
            try {
                var p = JSON.parse(raw)
                if (p && p.merchant_ref) return String(p.merchant_ref)
            } catch (e) {}
        }
        var fromUrl = q.get('order_no')
        if (fromUrl) return String(fromUrl)
        var pend = readPending()
        if (!pend) return ''
        if (pend.t > 0 && Date.now() - pend.t > TK_PENDING_TTL) {
            clearPending()
            return ''
        }
        return pend.o
    }

    /**
     * 轮询订单状态：已支付 → 到账提示；网关明确回未支付 / 连续几次都没付成功 →
     * 弹「Payment Unsuccessful」（与订阅页同一文案同一弹窗）
     */
    function pollOrder(orderNo, tries, known) {
        tries = tries === undefined ? TK_POLL_TRIES : tries
        if (!orderNo || tries <= 0) return
        api(RECHARGE_BASE + '/status', { data: { order_no: orderNo } }).done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            var d = (res && res.code === '00000' && res.data) ? res.data : null
            if (d) {
                known = true
                if (parseInt(d.status, 10) === 1) { paySucceeded(d, orderNo); return }
                // 支付状态 2 = 网关已明确回未支付
                if (parseInt(d.pay_state, 10) === 2) { payFailed(); return }
            }
            // 订单号查不到（后台已清理等）不算支付失败，只有确认过这笔单确实未支付才提示
            if (tries <= 1) { if (known) { payFailed() } return }
            setTimeout(function () { pollOrder(orderNo, tries - 1, known) }, TK_POLL_MS)
        }).fail(function () {
            // 请求失败只是没查到，不代表支付失败：继续重试，用尽就保持沉默等下次进页面再查
            if (tries <= 1) { if (known) { payFailed() } return }
            setTimeout(function () { pollOrder(orderNo, tries - 1, known) }, TK_POLL_MS)
        })
    }

    function paySucceeded(d, orderNo) {
        clearPending()
        toast('Payment successful, tokens credited')
        loadBalance()
        // 转化事件：充值成功（与服务端 CAPI 用同一个 event_id = 订单号，去重）
        try {
            if (window.SugusTrack) SugusTrack.event('recharge', { value: d.pay_amount }, orderNo || '')
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

    function init() {
        loadPackages()
        loadBalance()
        pollOrder(orderNoFromReturn())
    }

    $(function () {
        init()
        $(document).on('click', '#tk-plan-grid .tk-plan', function () {
            var pkg = packages[parseInt($(this).data('index'), 10)]
            if (!pkg) return
            if (!token()) {
                toast('Please log in first')
                location.href = './Login.html'
                return
            }
            openModal({ tokens: parseInt(pkg.tokens, 10), price: parseFloat(pkg.price), packageId: pkg.id })
        })
        $(document).on('click', '#tk-payment-modal [data-tk-close]', closeModal)
        // 支付方式格子：点哪个就按哪种方式发起支付
        $(document).on('click', '#tk-payment-modal [data-pm-method]', function () {
            if (!selected || loading) return
            var method = String($(this).attr('data-pm-method') || '')
            if (method === 'card') {
                submitOrder('', 'flowpay', PM.gcode)
                return
            }
            var base = './CryptoPay.html?kind=coin' +
                '&tokens=' + encodeURIComponent(selected.tokens) +
                '&price=' + encodeURIComponent(selected.price)
            if (method === 'crypto') {
                location.href = base
                return
            }
            var fid = $(this).attr('data-pm-fiat')
            if (method === 'cash' && fid) {
                location.href = base + '&fiat_id=' + encodeURIComponent(fid)
            }
        })
        // 卡组织切换：只改默认通道，真正发起支付仍点信用卡格子
        $(document).on('click', '#tk-payment-modal [data-pm-gcode]', function (e) {
            e.preventDefault()
            e.stopPropagation()
            PM.gcode = String($(this).attr('data-pm-gcode') || '')
            renderMethods()
        })
        $(document).on('input', '#tk-custom-slider', function () {
            showBubble()
            renderCustom(parseInt(this.value, 10), { serverCheck: true })
        })
        $(document).on('change', '#tk-custom-slider', hideBubble)
        $(document).on('pointerup mouseup touchend', '#tk-custom-slider', function () {
            clearTimeout(bubbleTimer)
            bubbleTimer = setTimeout(hideBubble, 700)
        })
        // 边输入边联动（不写回输入框，避免光标跳动）
        $(document).on('input', '#tk-custom-input', function () {
            var v = parseInt(String($(this).val()).replace(/[^0-9]/g, ''), 10)
            if (isNaN(v)) return
            renderCustom(v, { syncInput: false, serverCheck: true })
        })
        // 失焦/回车后吸附到 50 步进并写回
        $(document).on('change blur', '#tk-custom-input', function () {
            var v = parseInt(String($(this).val()).replace(/[^0-9]/g, ''), 10)
            renderCustom(normalizeTokens(isNaN(v) ? customTokens : v), { serverCheck: true })
        })
        $(document).on('keydown', '#tk-custom-input', function (e) {
            if (e.key === 'Enter') $(this).trigger('change')
        })
        $(document).on('click', '#tk-custom-buy', function () {
            if (!packages.length) return
            if (!token()) {
                toast('Please log in first')
                location.href = './Login.html'
                return
            }
            var q = quoteTokens(customTokens)
            if (!q) return
            openModal({ tokens: q.tokens, price: q.price, packageId: null })
        })
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal()
        })
    })
    $(document).on('turbo:load turbo:render', init)
})()
