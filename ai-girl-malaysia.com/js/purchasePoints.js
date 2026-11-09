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
        return (window.SITE_CURRENCY || '$') + Number(n || 0).toFixed(2)
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
        // 只替换套餐卡，不能用 .html() 整块重写：
        // 充值页的样式块/支付弹窗在部分浏览器解析下会落在 #tk-plan-grid 内，
        // 整块重写会把它们一起删掉，导致样式全丢、弹窗消失。
        $grid.find('.tk-plan, .tk-empty').remove()
        if (!packages.length) {
            $grid.prepend('<div class="tk-empty" style="grid-column:1/-1;text-align:center;color:#a4a4a4;font-size:14px;">No packages available</div>')
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
        $grid.prepend(html)
    }

    // ---------- 自选金额卡（按 USD 输入，最低 $3.99 入门档）----------
    // 金额 → 代币由后端 /quote-amount 按相邻套餐档位线性插值算，
    // 保证「显示价 = 实际下单价」，与 globalsky 自定义金额商品的 amount 严格一致。
    // 默认值只是首屏占位：真实区间以服务端 range 为准（见 syncRangeFromServer）。
    var CUSTOM_MIN = 3.99
    var CUSTOM_MAX = 299.99
    // 首次购买入门档的加赠比例（服务端下发，仅用于文案）
    var firstBuyBonus = 0
    var customAmount = CUSTOM_MIN
    var customTokensPreview = 0
    /* 首屏预估值用的基准（与后端最低套餐一致：$10.99 = 199 tokens）：
       只用于接口返回前的占位，接口一到就以服务端数据为准。 */
    var ENTRY_PRICE = 10.99
    var ENTRY_TOKENS = 199
    var FIRST_BUY_PERCENT = 80

    function progressPct(value, min, max) {
        return max > min ? ((value - min) / (max - min)) * 100 : 100
    }

    function clampAmount(v) {
        v = parseFloat(v)
        if (isNaN(v)) return CUSTOM_MIN
        v = Math.round(v * 100) / 100
        return Math.max(CUSTOM_MIN, Math.min(CUSTOM_MAX, v))
    }

    function amountText(v) {
        return (Math.round(Number(v) * 100) / 100).toFixed(2)
    }

    function syncInputWidth() {
        var input = document.getElementById('tk-custom-input')
        if (!input) return
        var text = input.value || '0'
        var cs = getComputedStyle(input)
        var fs = parseFloat(cs.fontSize) || 24
        // 数字字形宽度约为字号的 0.62（tabular-nums 等宽），
        // 再留 1.6 字余量给光标和「小数点后第二位的视觉空间」
        var need = (text.length + 1.6) * fs * 0.62
        input.style.width = Math.ceil(need) + 'px'
    }

    function replay(el, cls) {
        if (!el || !el.classList) return
        el.classList.remove(cls)
        void el.offsetWidth
        el.classList.add(cls)
    }

    function updateBubble(val) {
        var el = document.getElementById('tk-custom-bubble')
        var slider = document.getElementById('tk-custom-slider')
        if (!el || !slider) return
        el.textContent = '$' + amountText(val)
        var thumb = 30
        var pct = progressPct(val, parseFloat(slider.min), parseFloat(slider.max)) / 100
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

    // 金额变化后向服务端确认代币数，保证预览与实际到账一致
    function scheduleQuoteCheck(amount) {
        clearTimeout(customTimer)
        customTimer = setTimeout(function () {
            api(RECHARGE_BASE + '/quote-amount', { data: { amount: amount } }).done(function (res) {
                if (typeof res === 'string') res = JSON.parse(res)
                // 区间（含最低档 3.99）与是否首充加赠都无条件同步：
                // 以前失败分支直接 return，CUSTOM_MIN 会一直停留在前端兜底值
                syncRangeFromServer(res)
                if (res.code !== '00000' || !res.data) {
                    // 报价失败（金额越界等）：清掉过期的代币预览，避免显示错误数字
                    customTokensPreview = 0
                    renderYield(0, 0)
                    return
                }
                if (Math.abs(parseFloat(res.data.price) - customAmount) > 0.001) return
                customTokensPreview = parseInt(res.data.tokens, 10) || 0
                var bonus = parseInt(res.data.bonus_percent, 10) || 0
                // 入门档首充优惠：未登录时服务端也按「可能享」返回，用于展示促销
                var eligible = res.data.first_buy_eligible === true || bonus > 0
                renderYield(customTokensPreview, eligible ? bonus : 0)
                // 卡顶角标：与套餐卡「+20% bonus」同款样式
                renderFirstBuyChip(eligible ? bonus : 0)
            })
        }, 250)
    }

    /**
     * 当前金额能获得多少代币（tk-custom-head 的核心展示）
     *
     * @param {number} total  加赠后的总代币数
     * @param {number} bonus  首充加赠百分比（0=无优惠）
     */
    function renderYield(total, bonus) {
        var num = document.getElementById('tk-custom-yield-num')
        if (num) num.textContent = (total > 0 ? total.toLocaleString('en-US') : '—')
        renderCoinIcon(customAmount)
        var sub = document.getElementById('tk-custom-yield-sub')
        if (!sub) return
        if (total > 0 && bonus > 0) {
            // 反推基础数（加赠前），让用户看清「72 + 58 bonus」的构成
            var base = Math.round(total / (1 + bonus / 100))
            var extra = total - base
            sub.innerHTML = base + ' + <span class="tk-custom-bonus">' + extra
                + ' bonus (' + bonus + '%)</span>'
            sub.hidden = false
        } else {
            sub.textContent = ''
            sub.hidden = true
        }
    }

    /**
     * 金币图标随金额档位递增（1=少量金币 … 6=满袋）
     *
     * 按「当前金额在 [CUSTOM_MIN, CUSTOM_MAX] 区间里的位置」均分 6 段：
     * 首屏 3.99 是最低档 → coin_1，拖到区间中部 → coin_3~4，拉满 → coin_6。
     */
    function renderCoinIcon(amount) {
        var img = document.getElementById('tk-custom-coin')
        if (!img || !COIN_ICONS.length) return
        var span = CUSTOM_MAX - CUSTOM_MIN
        var ratio = span > 0 ? (amount - CUSTOM_MIN) / span : 0
        ratio = Math.max(0, Math.min(1, ratio))
        // 6 个图标：0→coin_1，1→coin_6
        var idx = Math.min(COIN_ICONS.length - 1, Math.floor(ratio * COIN_ICONS.length))
        var src = COIN_ICONS[idx] || CUSTOM_COIN
        if (img.getAttribute('src') !== src) img.setAttribute('src', src)
    }

    /** 首充角标：有加赠就亮起并写比例，否则隐藏（不改动 DOM 结构） */
    function renderFirstBuyChip(bonus) {
        var chip = document.getElementById('tk-custom-chip')
        var text = document.getElementById('tk-custom-chip-text')
        if (!chip || !text) return
        if (bonus > 0) {
            /* 文案必须写英文：整站靠 Google Translate 翻译（pageLanguage=en），
               写中文的话翻译器认为已是外文、不会再翻成其它语言。 */
            text.textContent = '+' + bonus + '% first purchase bonus'
            chip.setAttribute('aria-hidden', 'false')
            text.classList.remove('is-pulse')
            void text.offsetWidth
            text.classList.add('is-pulse')
        } else {
            chip.setAttribute('aria-hidden', 'true')
        }
    }

    // 服务端区间 → 前端滑条上下限。最低档以服务端为准（3.99），
    // 前端不再自己写死，避免两端不一致导致「标 3.99 却报 10.99」。
    function syncRangeFromServer(res) {
        if (!res || !res.data) return
        var range = res.data.range
        if (!range) return
        var min = parseFloat(range.min)
        var max = parseFloat(range.max)
        if (min > 0 && min !== CUSTOM_MIN) {
            CUSTOM_MIN = min
            if (customAmount < CUSTOM_MIN) customAmount = CUSTOM_MIN
        }
        if (max > 0) CUSTOM_MAX = max
        var slider = document.getElementById('tk-custom-slider')
        if (slider) {
            slider.min = String(CUSTOM_MIN)
            slider.max = String(CUSTOM_MAX)
        }
        $('#tk-custom-min').text('$' + amountText(CUSTOM_MIN))
        $('#tk-custom-max').text('$' + amountText(CUSTOM_MAX))
        // 首充比例以服务端为准；未登录时服务端按「可能享」返回，eligible 为 true
        firstBuyBonus = (res.data.first_buy_eligible === true)
            ? (parseInt(res.data.bonus_percent, 10) || 0)
            : 0
    }

    // opt.syncInput：是否把金额写回输入框（拖动/失焦时写，边打字时不写）
    // opt.serverCheck：是否向服务端确认代币数
    function renderCustom(amount, opt) {
        opt = opt || {}
        var val = clampAmount(amount)
        customAmount = val

        var slider = document.getElementById('tk-custom-slider')
        if (slider) {
            slider.min = String(CUSTOM_MIN)
            slider.max = String(CUSTOM_MAX)
            slider.step = '0.01'
            slider.value = String(val)
            slider.style.setProperty('--token-slider-progress', progressPct(val, CUSTOM_MIN, CUSTOM_MAX) + '%')
        }

        if (opt.syncInput !== false) $('#tk-custom-input').val(amountText(val))
        $('#tk-custom-min').text('$' + amountText(CUSTOM_MIN))
        $('#tk-custom-max').text('$' + amountText(CUSTOM_MAX))

        var priceText = money(val)
        if ($('#tk-custom-price').text() !== priceText) {
            $('#tk-custom-price').text(priceText)
            replay(document.getElementById('tk-custom-price'), 'tk-custom-price-pop')
        }

        /* 首屏先给本地估算，别让用户对着「—」等接口：
           入门档按首充 80% 预估（与后端同一公式），其它金额用「每美元代币数」粗算。
           接口回来后 scheduleQuoteCheck → renderYield 会以服务端数值覆盖。 */
        if (!opt.skipYield) prefillYield(val)

        updateBubble(val)
        syncInputWidth()
        if (opt.serverCheck) scheduleQuoteCheck(val)
    }

    /* 本地预估值：仅用于首屏，服务端报价到达后被替换 */
    function prefillYield(amount) {
        var isEntry = Math.abs(amount - CUSTOM_MIN) < 0.005
        if (isEntry) {
            var base = Math.max(1, Math.round(amount * (ENTRY_TOKENS / ENTRY_PRICE)))
            var total = Math.round(base * (1 + FIRST_BUY_PERCENT / 100))
            renderYield(total, FIRST_BUY_PERCENT)
            renderFirstBuyChip(FIRST_BUY_PERCENT)
            return
        }
        // 非入门档：用套餐最低档单价粗算，不带首充优惠
        var perUsd = ENTRY_TOKENS / ENTRY_PRICE
        renderYield(Math.max(1, Math.round(amount * perUsd)), 0)
        renderFirstBuyChip(0)
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
            renderCustom(CUSTOM_MIN, { serverCheck: true })
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

    /** 固定金额档位的网关（HissPay）：金额不在档位内就不展示，避免用户点到必失败的方式 */
    function fiatAllowed(fc, price) {
        if (!fc || !fc.amounts || !fc.amounts.length) return true
        var p = parseFloat(price)
        if (!isFinite(p) || p <= 0) return true
        var needle = p.toFixed(2)
        for (var i = 0; i < fc.amounts.length; i++) {
            if (String(fc.amounts[i]) === needle) return true
        }
        return false
    }

    function cashChannelsFor(price) {
        return fiatChannels.filter(function (fc) { return fiatAllowed(fc, price) })
    }

    /** 当前可用方式：卡（FlowPay 开关 + 收单通道）/ 加密货币（后台还有启用的链上渠道）/ 法币（后台配了几条） */
    function pmAvailable() {
        return {
            card: !!(flowpayEnabled && flowpayChannels.length),
            crypto: channels.length > 0,
            cash: cashChannelsFor(selected && selected.price).length > 0
        }
    }

    function renderMethods() {
        var box = $('#pm-grid')
        if (!box.length) return
        var avail = pmAvailable()
        var cashList = cashChannelsFor(selected && selected.price)
        if (!PM.method || !avail[PM.method]) {
            PM.method = avail.card ? 'card' : (avail.crypto ? 'crypto' : (avail.cash ? 'cash' : ''))
        }
        PM.gcode = avail.card ? (PM.gcode || String(flowpayChannels[0].gcode || '')) : ''
        if (avail.cash) {
            var hit = false
            cashList.forEach(function (fc) { if (String(fc.id) === String(PM.fiatId)) hit = true })
            if (!hit) PM.fiatId = String(cashList[0].id)
        } else {
            PM.fiatId = ''
        }

        // 版式：加密货币独占第一排，信用卡与各本币通道两列排；点任意一格直接进入支付
        var html = ''
        if (avail.crypto) {
            html += '<button type="button" class="pm-row" data-pm-method="crypto">' +
                '<span class="pm-row-text">Continue with</span>' +
                '<span class="pm-row-icons">' + PM_COIN_ICONS + '</span></button>'
        }
        if (avail.card) {
            html += '<button type="button" class="pm-row" data-pm-method="card">' +
                '<span class="pm-row-text">Credit Card</span>' +
                '<span class="pm-row-icons">' + PM_CARD_ICON + '</span></button>'
        }
        if (avail.cash) {
            var walletShown = false
            cashList.forEach(function (fc) {
                var isWallet = fc.gateway === 'wisonpay'
                if (isWallet && walletShown) return
                if (isWallet) walletShown = true
                var marks = fiatMarks(fc)
                var unit = fc.symbol || fc.currency || ''
                var title = isWallet ? 'WisonPay' : fc.name
                // 右侧已有品牌标/货币符号时，标题里不再重复币种
                var unitText = (isWallet || marks.length) ? '' : (unit !== '' ? ' · ' + pmClean(unit) : '')
                html += '<button type="button" class="pm-row" data-pm-method="cash" data-pm-fiat="' + parseInt(fc.id, 10) + '">' +
                    '<span class="pm-row-text">' + pmClean(title) + unitText + '</span>' +
                    (marks.length ? '<span class="pm-row-icons">' + fiatMarksHtml(marks) + '</span>' : '') +
                    '</button>'
            })
        }
        // 后台把所有支付方式都关了：给一句提示，避免弹窗空白
        if (!html) {
            html = '<div style="padding:16px;color:#9CA3AF;font-size:13px;text-align:center;">No payment methods available</div>'
        }
        box.html(html)

        renderPmSub(avail)
        updateFeeNote()
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

    // 法币通道右侧标记：本币用货币标识，美元收单通道用对应支付品牌标
    var FIAT_MARKS = [
        { currency: 'BRL', src: './assets/pix.svg', alt: 'PIX' },
        { currency: 'INR', text: '₹', alt: 'INR' },
        { currency: 'VND', text: '₫', alt: 'VND' },
        { currency: 'MXN', text: 'Mex$', alt: 'MXN' }
    ]

    // 收单网关 → 品牌标（素材与卡支付同一组）
    var FIAT_GATEWAY_MARKS = {
        wisonpay: [
            { src: APPLE_PAY_ICON, alt: 'Apple Pay' },
            { src: GOOGLE_PAY_ICON, alt: 'Google Pay' }
        ],
        aipowerpay: [
            { src: VISA_ICON, alt: 'Visa' },
            { src: MASTERCARD_ICON, alt: 'Mastercard' }
        ],
        hisspay: [
            { src: VISA_ICON, alt: 'Visa' },
            { src: MASTERCARD_ICON, alt: 'Mastercard' }
        ],
        skytrade: [
            { src: VISA_ICON, alt: 'Visa' },
            { src: MASTERCARD_ICON, alt: 'Mastercard' }
        ],
        cregis: [
            { src: './assets/USDT.png', alt: 'USDT' },
            { src: './taocan_files/bitcoin-eb1103743040983f739826c12ef909c787ed1e6104bb0f5eff7112d31fde0036.svg', alt: 'Bitcoin' }
        ]
    }

    /** 通道右侧标记（可能多个）：先按本币匹配，其次按收单网关匹配 */
    function fiatMarks(fc) {
        var cur = String((fc && fc.currency) || '').toUpperCase()
        for (var i = 0; i < FIAT_MARKS.length; i++) {
            if (FIAT_MARKS[i].currency === cur) { return [FIAT_MARKS[i]] }
        }
        return FIAT_GATEWAY_MARKS[String((fc && fc.gateway) || '')] || []
    }

    /** 标记 HTML：品牌标用图片，货币符号用文字（内联样式，免改各页 CSS） */
    function fiatMarkHtml(mark) {
        if (mark.src) { return '<img src="' + mark.src + '" alt="' + mark.alt + '">' }
        return '<span aria-label="' + mark.alt + '" style="display:block;font-size:16px;font-weight:600;line-height:1;white-space:nowrap">' + mark.text + '</span>'
    }

    function fiatMarksHtml(marks) {
        return marks.map(fiatMarkHtml).join('')
    }

    // sel: { tokens, price, packageId }
    function openModal(sel) {
        if (!sel) return
        selected = sel
        if (typeof sel.amount === 'number') {
            $('#pm-plan-title').text('Custom amount · ' + money(sel.amount)
                + (sel.tokens ? ' (~' + sel.tokens + ' tokens)' : ''))
        } else {
            $('#pm-plan-title').text(sel.tokens + ' tokens')
        }
        $('#pm-plan-price').text(money(sel.price))
        $('#pm-summary').text('One-time payment billed as ' + money(sel.price))
        renderMethods()
        updateFeeNote()
        $('#tk-payment-modal').css('display', 'flex')
        hideBubble()
    }

    /* 卡支付走 Stripe/Link 收银台：收银台可能按访问者地区换成人民币等本币结算，
       再由发卡行折算回卡币种，实际账单会比标价高几个百分点（$3.99 实扣约 $4.23）。
       这条说明只对卡支付显示，避免给加密/法币通道的用户错误预期。 */
    function updateFeeNote() {
        var note = document.getElementById('pm-fee-note')
        if (!note) return
        var byCard = String(PM.method || '') === 'card'
        note.style.display = byCard ? '' : 'none'
    }

    function closeModal() {
        $('#tk-payment-modal').css('display', 'none')
        selected = null
    }

    function submitOrder(code, gateway, gcode) {
        if (loading || !selected) return
        if (!token()) {
            toast('Please log in first')
            location.href = './Login.html?redirect=' + encodeURIComponent(location.pathname + location.search)
            return
        }
        loading = true
        /* 行为埋点：充值按钮点击（含入口类型/金额/渠道）*/
        try {
            if (window.SugusTrackEvt) {
                window.SugusTrackEvt('pay_click', {
                    entry: selected.packageId ? 'package' : (typeof selected.amount === 'number' ? 'custom' : 'tokens'),
                    amount: selected.amount || 0,
                    package_id: selected.packageId || 0,
                    gateway: gateway || 'default',
                    product: code || ''
                })
            }
        } catch (e) {}
        $('#tk-payment-modal .pm-row, #tk-payment-modal .pm-chip').attr('disabled', true).css('opacity', .6)
        var payload
        if (selected.packageId) {
            payload = { package_id: selected.packageId, product: code }
        } else if (typeof selected.amount === 'number') {
            // 自选金额：直接把 USD 金额传后端（订单金额 = 用户输入的金额）
            payload = { amount: selected.amount, product: code }
        } else {
            payload = { tokens: selected.tokens, product: code }
        }
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
            /* 行为埋点：订单创建成功 */
            try {
                if (window.SugusTrackEvt) {
                    window.SugusTrackEvt('pay_order', {
                        order_no: res.data.order_no,
                        amount: res.data.pay_amount,
                        gateway: gateway || 'default'
                    })
                }
            } catch (e) {}
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
        // 自选金额卡先渲染首屏（不依赖 /packages，接口慢或失败也能看到促销与代币数）
        renderCustom(customAmount, { skipYield: false })
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
                location.href = './Login.html?redirect=' + encodeURIComponent(location.pathname + location.search)
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
            /* 行为埋点：加密货币/法币通道点击（不走 submitOrder，单独埋）*/
            try {
                if (window.SugusTrackEvt) {
                    window.SugusTrackEvt('pay_click', {
                        entry: selected.packageId ? 'package' : (typeof selected.amount === 'number' ? 'custom' : 'tokens'),
                        amount: selected.amount || selected.price || 0,
                        package_id: selected.packageId || 0,
                        gateway: method === 'crypto' ? 'crypto' : 'fiat',
                        product: ''
                    })
                }
            } catch (e) {}
            var base = './CryptoPay.html?kind=coin' +
                '&tokens=' + encodeURIComponent(selected.tokens) +
                '&price=' + encodeURIComponent(selected.price) +
                (typeof selected.amount === 'number' ? '&amount=' + encodeURIComponent(selected.amount) : '') +
                (selected.packageId ? '&package_id=' + encodeURIComponent(selected.packageId) : '')
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
            renderCustom(parseFloat(this.value), { serverCheck: true })
        })
        $(document).on('change', '#tk-custom-slider', hideBubble)
        $(document).on('pointerup mouseup touchend', '#tk-custom-slider', function () {
            clearTimeout(bubbleTimer)
            bubbleTimer = setTimeout(hideBubble, 700)
        })
        // 边输入边联动（不写回输入框，避免光标跳动）
        $(document).on('input', '#tk-custom-input', function () {
            var v = parseFloat(String($(this).val()).replace(/[^0-9.]/g, ''))
            if (isNaN(v)) return
            renderCustom(v, { syncInput: false, serverCheck: true })
        })
        // 失焦/回车后吸附到 50 步进并写回
        $(document).on('change blur', '#tk-custom-input', function () {
            var v = parseFloat(String($(this).val()).replace(/[^0-9.]/g, ''))
            renderCustom(isNaN(v) ? customAmount : v, { serverCheck: true })
        })
        $(document).on('keydown', '#tk-custom-input', function (e) {
            if (e.key === 'Enter') $(this).trigger('change')
        })
        $(document).on('click', '#tk-custom-buy', function () {
            if (!packages.length) return
            if (!token()) {
                toast('Please log in first')
                location.href = './Login.html?redirect=' + encodeURIComponent(location.pathname + location.search)
                return
            }
            openModal({
                amount: customAmount,
                tokens: customTokensPreview || 0,
                price: customAmount,
                packageId: null
            })
        })
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape') closeModal()
        })
    })
    $(document).on('turbo:load turbo:render', init)
})()
