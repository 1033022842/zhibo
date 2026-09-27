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
    var ETH_ICON = './taocan_files/eth-0f77f739edf37b7fc60d467b5fb8317f4467f3bfce952c865551f5d8f4953124.svg'
    // 「Pay with Card」按钮图标（FlowPay 收银台）
    var CARD_ICONS = [
        './taocan_files/visa-e1d8b03b25a5af3b8320ae609ba6331e0e1787c5d24d0bbcb9bfb8e88d5cdd42.svg',
        './taocan_files/mastercard-a70ad911ccea0bdffda8287342443e4bb64898fecf51d8a4f86b1ecacf213e4a.svg'
    ]
    var packages = []
    var products = []
    var channels = []
    var flowpayEnabled = false
    var selected = null
    var customTokens = 0
    var customTimer = null
    var bubbleTimer = null
    var loading = false
    var ethPollTimer = null
    var ethCountTimer = null

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
            products = res.data.products || []
            flowpayEnabled = !!res.data.flowpay
            renderPlans()
            renderCustom(normalizeTokens(DEFAULT_TOKENS))
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

    function ethChannels() {
        return channels.filter(function (c) {
            return c && c.type === 'eth' && c.address
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

    // ---------- 支付弹窗 ----------
    // 「Pay with」按钮里的币种图标：与 Subscriptions.html 支付弹窗同一组
    var PAY_LOGOS = [
        { src: './taocan_files/bitcoin-eb1103743040983f739826c12ef909c787ed1e6104bb0f5eff7112d31fde0036.svg', alt: 'Bitcoin' },
        { src: './taocan_files/eth-0f77f739edf37b7fc60d467b5fb8317f4467f3bfce952c865551f5d8f4953124.svg', alt: 'Ethereum' },
        { src: './taocan_files/litecoin-39b215ee717df466cd2599dc7a1f63ad0e05d619dca3a8b1a74be7112954086d.svg', alt: 'Litecoin' }
    ]

    function renderMethods() {
        var code = products.length ? products[0].code : ''
        var logos = PAY_LOGOS.map(function (l) {
            return '<img class="tk-method-logo" src="' + l.src + '" alt="' + l.alt + '">'
        }).join('')
        var html =
            '<button type="button" class="tk-method"' + (code ? ' data-code="' + code + '"' : ' disabled') + '>' +
            '<span class="tk-method-text">Pay with</span>' +
            '<span class="tk-method-icons">' + logos + '</span>' +
            '</button>'
        // ETH 地址收款：后台开启 eth 渠道后自动出现
        ethChannels().forEach(function (ch) {
            html +=
                '<button type="button" class="tk-method" data-eth="' + ch.id + '">' +
                '<span class="tk-method-text">Pay with ETH</span>' +
                '<span class="tk-method-icons"><img class="tk-method-logo" src="' + ETH_ICON + '" alt="Ethereum"></span>' +
                '</button>'
        })
        // 银行卡收银台：FlowPay 商户参数配置齐全后自动出现
        if (flowpayEnabled) {
            var cardLogos = CARD_ICONS.map(function (src) {
                return '<img class="tk-method-logo" src="' + src + '" alt="Card">'
            }).join('')
            html +=
                '<button type="button" class="tk-method" data-flowpay="1">' +
                '<span class="tk-method-text">Pay with Card</span>' +
                '<span class="tk-method-icons">' + cardLogos + '</span>' +
                '</button>'
        }
        $('#tk-modal-methods').html(html)
        $('#tk-eth-panel').hide().empty()
    }

    // http 非安全上下文下 navigator.clipboard 不可用，用临时 textarea + execCommand 兜底
    function fallbackCopy(v) {
        try {
            var ta = document.createElement('textarea')
            ta.value = v
            ta.setAttribute('readonly', '')
            ta.style.position = 'fixed'
            ta.style.opacity = '0'
            document.body.appendChild(ta)
            ta.select()
            var ok = document.execCommand('copy')
            document.body.removeChild(ta)
            toast(ok ? 'Copied' : v)
        } catch (e) {
            toast(v)
        }
    }

    // ---------- ETH 链上转账下单 ----------
    function submitEthOrder(channelId) {
        if (loading || !selected) return
        if (!token()) {
            toast('Please log in first')
            location.href = './Login.html'
            return
        }
        loading = true
        var $btn = $('#tk-payment-modal .tk-method[data-eth="' + channelId + '"]')
        $btn.attr('disabled', true).css('opacity', .6)
        api(RECHARGE_BASE + '/submit', {
            method: 'POST',
            data: { channel_id: channelId, amount: selected.price }
        }).done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code !== '00000' || !res.data) {
                toast(res.msg || 'Create order failed')
                return
            }
            showEthPanel(res.data)
        }).fail(function () {
            toast('Network error, please retry')
        }).always(function () {
            loading = false
            $btn.removeAttr('disabled').css('opacity', 1)
        })
    }

    function qrUrl(raw) {
        if (!raw) return ''
        return String(raw).indexOf('http') === 0 ? raw : location.origin + '/' + String(raw).replace(/^\/+/, '')
    }

    function showEthPanel(order) {
        var ch = order.channel || {}
        var addr = ch.address || ''
        var qr = qrUrl(ch.qr_code_url || '')
        var eth = String(order.pay_amount || '')
        $('#tk-eth-panel').html(
            '<div class="tk-eth-head">Send exactly</div>' +
            '<div class="tk-eth-amount">' + eth + ' ETH' +
            '<button type="button" class="tk-eth-copy" data-tk-copy="' + eth + '">Copy</button></div>' +
            '<div class="tk-eth-label">to this ETH (Ethereum mainnet) address</div>' +
            '<div class="tk-eth-addr"><span>' + addr + '</span>' +
            '<button type="button" class="tk-eth-copy" data-tk-copy="' + addr + '">Copy</button></div>' +
            (qr ? '<div class="tk-eth-qr"><img src="' + qr + '" alt="ETH address QR"></div>' : '') +
            '<div class="tk-eth-note">Please send the exact amount — it is how we identify your order. Tokens are credited automatically after 1 confirmation (usually within 1 minute).</div>' +
            '<div class="tk-eth-status" id="tk-eth-status">Waiting for payment…</div>' +
            '<div class="tk-eth-order">Order ' + (order.order_no || '') + ' · expires in <span id="tk-eth-countdown">--:--</span></div>'
        ).show()
        startEthCountdown(order.expire_at)
        startEthPoll(order.order_no)
    }

    function stopEthTimers() {
        if (ethPollTimer) { clearInterval(ethPollTimer); ethPollTimer = null }
        if (ethCountTimer) { clearInterval(ethCountTimer); ethCountTimer = null }
    }

    function startEthCountdown(expireAt) {
        if (ethCountTimer) clearInterval(ethCountTimer)
        var end = expireAt ? new Date(String(expireAt).replace(/-/g, '/')).getTime() : 0
        function tick() {
            var $el = $('#tk-eth-countdown')
            if (!$el.length || !end) return
            var left = Math.max(0, Math.floor((end - Date.now()) / 1000))
            var m = Math.floor(left / 60)
            var s = left % 60
            $el.text(m + ':' + (s < 10 ? '0' : '') + s)
        }
        tick()
        ethCountTimer = setInterval(tick, 1000)
    }

    function startEthPoll(orderNo) {
        if (ethPollTimer) clearInterval(ethPollTimer)
        if (!orderNo) return
        ethPollTimer = setInterval(function () {
            api(RECHARGE_BASE + '/status', { data: { order_no: orderNo } }).done(function (res) {
                if (typeof res === 'string') res = JSON.parse(res)
                if (res.code !== '00000' || !res.data) return
                var st = parseInt(res.data.status, 10)
                var $s = $('#tk-eth-status')
                if (!$s.length) { stopEthTimers(); return }
                if (st === 1) {
                    stopEthTimers()
                    $s.text('Payment confirmed — tokens credited').addClass('is-ok')
                    toast('Payment successful, tokens credited')
                    loadBalance()
                    setTimeout(closeModal, 1500)
                } else if (st === 2 || st === 3) {
                    stopEthTimers()
                    $s.text('Order closed, please create a new one').addClass('is-fail')
                } else {
                    $s.text('Waiting for payment…')
                }
            })
        }, 5000)
    }

    // sel: { tokens, price, packageId }
    function openModal(sel) {
        if (!sel) return
        selected = sel
        renderMethods()
        $('#tk-plan-title').text(sel.tokens + ' tokens')
        $('#tk-plan-price').text(money(sel.price))
        $('#tk-modal-summary').text('One-time payment billed as ' + money(sel.price))
        $('#tk-payment-modal').css('display', 'flex')
        hideBubble()
    }

    function closeModal() {
        $('#tk-payment-modal').css('display', 'none')
        $('#tk-eth-panel').hide().empty()
        stopEthTimers()
        selected = null
    }

    function submitOrder(code, gateway) {
        if (loading || !selected) return
        if (!token()) {
            toast('Please log in first')
            location.href = './Login.html'
            return
        }
        loading = true
        $('#tk-payment-modal .tk-method').attr('disabled', true).css('opacity', .6)
        var payload = selected.packageId
            ? { package_id: selected.packageId, product: code }
            : { tokens: selected.tokens, product: code }
        if (gateway) payload.gateway = gateway
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
            try { localStorage.setItem('tk_pending_order', res.data.order_no) } catch (e) {}
            location.href = payUrl
        }).fail(function () {
            toast('Network error, please retry')
        }).always(function () {
            loading = false
            $('#tk-payment-modal .tk-method').removeAttr('disabled').css('opacity', 1)
        })
    }

    // ---------- 支付回跳轮询 ----------
    function orderNoFromReturn() {
        var search = window.location.search
        if (!search) return localStorage.getItem('tk_pending_order') || ''
        var q = new URLSearchParams(search)
        var raw = q.get('params')
        if (raw) {
            try {
                var p = JSON.parse(raw)
                if (p && p.merchant_ref) return p.merchant_ref
            } catch (e) {}
        }
        return q.get('order_no') || localStorage.getItem('tk_pending_order') || ''
    }

    function pollOrder(orderNo, times) {
        times = times === undefined ? 12 : times
        if (!orderNo || times <= 0) return
        api(RECHARGE_BASE + '/status', { data: { order_no: orderNo } }).done(function (res) {
            if (typeof res === 'string') res = JSON.parse(res)
            if (res.code === '00000' && res.data && parseInt(res.data.status, 10) === 1) {
                try { localStorage.removeItem('tk_pending_order') } catch (e) {}
                toast('Payment successful, tokens credited')
                loadBalance()
                return
            }
            setTimeout(function () { pollOrder(orderNo, times - 1) }, 5000)
        })
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
        $(document).on('click', '#tk-payment-modal .tk-method[data-code]', function () {
            submitOrder($(this).data('code'))
        })
        // 银行卡收银台（FlowPay）
        $(document).on('click', '#tk-payment-modal .tk-method[data-flowpay]', function () {
            submitOrder('', 'flowpay')
        })
        // ETH 地址收款
        $(document).on('click', '#tk-payment-modal .tk-method[data-eth]', function () {
            submitEthOrder(parseInt($(this).data('eth'), 10))
        })
        $(document).on('click', '#tk-eth-panel [data-tk-copy]', function () {
            var v = String($(this).attr('data-tk-copy') || '')
            if (!v) return
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(v).then(function () { toast('Copied') }, function () { fallbackCopy(v) })
            } else {
                fallbackCopy(v)
            }
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
