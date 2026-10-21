/**
 * CryptoPay —— 加密货币收款页
 *
 * 版式参考 CoinGate 的 invoice 页（浅灰底 + 居中卡片 + 灰头白身 + 币种选择器）。
 * 收款信息走后台「充值渠道」（lp_recharge_channel，后台可自定义币种/地址）：
 *   订阅：POST /api/v1/vip/order        { plan_id, channel_id } → 订单 + 收款地址
 *   充值：POST /api/v1/recharge/submit  { channel_id, amount }  → 订单 + 收款地址
 *   到账：GET  /api/v1/recharge/status?order_no=… 轮询（链上扫到自动入账）
 *
 * 入参（URL）：
 *   CryptoPay.html?kind=vip&plan_id=12
 *   CryptoPay.html?kind=coin&tokens=550&price=49.99
 */
(function () {
    'use strict'

    var API = '/api/v1'
    var POLL_MS = 5000

    // 币种图标：全部用官方币种 logo。先按渠道 type 精确匹配，未收录的 type 再按渠道名兜底；
    // 两者都没命中才退回「品牌色圆标 + 币种代号」，这样后台新增任何币种都不会显示成空白
    var COIN_ICON_BY_TYPE = {
        usdt_trc20: './assets/USDT.png',
        usdt_erc20: './assets/USDT.png',
        usdt_bep20: './assets/USDT.png',
        usdc_erc20: './assets/USDC.png',
        bnb:        './assets/BNB.png',
        trx:        './assets/TRX.png',
        eth:        './taocan_files/ethereum-v2-ecf882da6f36e48fb026b5731691a0247652e78fae91381e9cc9aa3609af0c83.svg',
        btc:        './taocan_files/bitcoin-v2-4140d64c9d7108dbd4a9db1a64c4eaceee0728a7c2a478b78c6840e29031e480.svg',
        ltc:        './taocan_files/litecoin-v2-97c556a98f274c6101b73d45322bbbef6705be18330cc00bc3ed442d28899336.svg'
    }

    var COIN_ICONS = [
        { re: /usdt|tether/i, src: './assets/USDT.png' },
        { re: /usdc/i, src: './assets/USDC.png' },
        { re: /bnb|binance/i, src: './assets/BNB.png' },
        { re: /\btrx\b|tron/i, src: './assets/TRX.png' },
        { re: /btc|bitcoin/i, src: './taocan_files/bitcoin-v2-4140d64c9d7108dbd4a9db1a64c4eaceee0728a7c2a478b78c6840e29031e480.svg' },
        { re: /eth|ethereum/i, src: './taocan_files/ethereum-v2-ecf882da6f36e48fb026b5731691a0247652e78fae91381e9cc9aa3609af0c83.svg' },
        { re: /ltc|litecoin/i, src: './taocan_files/litecoin-v2-97c556a98f274c6101b73d45322bbbef6705be18330cc00bc3ed442d28899336.svg' }
    ]

    // 币种代号 → 圆标底色（品牌色，仅用于兜底圆标）
    var COIN_COLORS = {
        USDT: '#26A17B', USDC: '#2775CA', DAI: '#F5AC37', TUSD: '#1B3B8B',
        BTC: '#F7931A', LTC: '#345D9D', BCH: '#8DC351', DOGE: '#C2A633',
        ETH: '#627EEA', TRX: '#EF0027', BNB: '#F0B90B', SOL: '#9945FF',
        TON: '#0098EA', XRP: '#23292F'
    }

    /** 渠道 → 币种代号：优先用后台下发的 unit，其次按渠道名/类型猜 */
    function unitOf(ch) {
        if (ch && ch.unit) { return String(ch.unit) }
        var t = String((ch && ch.type) || '') + ' ' + String((ch && ch.name) || '')
        if (/usdt|trc20|tether/i.test(t)) { return 'USDT' }
        if (/usdc/i.test(t)) { return 'USDC' }
        if (/dai/i.test(t)) { return 'DAI' }
        if (/tusd/i.test(t)) { return 'TUSD' }
        if (/btc|bitcoin/i.test(t)) { return 'BTC' }
        if (/ltc|litecoin/i.test(t)) { return 'LTC' }
        if (/bch|bitcoin cash/i.test(t)) { return 'BCH' }
        if (/doge/i.test(t)) { return 'DOGE' }
        if (/eth/i.test(t)) { return 'ETH' }
        if (/trx|tron/i.test(t)) { return 'TRX' }
        if (/bnb/i.test(t)) { return 'BNB' }
        if (/sol/i.test(t)) { return 'SOL' }
        if (/ton/i.test(t)) { return 'TON' }
        if (/xrp/i.test(t)) { return 'XRP' }
        var s = String((ch && ch.name) || '').trim().split(/[\s(（·/|-]+/)[0] || 'COIN'
        return s.toUpperCase()
    }

    /** 渠道 → 网络说明（后台渠道配置里带 chain_label，没有才退回渠道名） */
    function chainLabel(ch) {
        if (ch && ch.chain_label) { return String(ch.chain_label) }
        var t = String((ch && ch.type) || '')
        if (t === 'usdt_trc20') { return 'TRON (TRC-20)' }
        if (t === 'eth') { return 'Ethereum' }
        return String((ch && ch.name) || '')
    }

    function coinIcon(ch) {
        var t = String((ch && ch.type) || '')
        if (COIN_ICON_BY_TYPE[t]) { return COIN_ICON_BY_TYPE[t] }
        t += ' ' + String((ch && ch.name) || '')
        for (var i = 0; i < COIN_ICONS.length; i++) {
            if (COIN_ICONS[i].re.test(t)) { return COIN_ICONS[i].src }
        }
        return ''
    }

    function iconHtml(ch, cls) {
        var src = coinIcon(ch)
        if (src) { return '<span class="' + cls + '"><img src="' + src + '" alt=""></span>' }
        var unit = unitOf(ch)
        var color = COIN_COLORS[unit] || '#9CA3AF'
        var text = unit.length > 4 ? unit.slice(0, 4) : unit
        return '<span class="' + cls + ' cp-coin-badge" style="background:' + color + ';font-size:' +
            (text.length > 3 ? 8 : 10) + 'px">' + esc(text) + '</span>'
    }

    /**
     * 把币种图标画进「已存在」的元素里（原地改内容，不能替换 outerHTML——
     * 替换会把这个带 id 的节点从 DOM 里换掉，第二次选币种就再也找不到它了）
     */
    function fillIcon(el, ch) {
        if (!el) { return }
        var src = coinIcon(ch)
        var unit = unitOf(ch)
        var text = unit.length > 4 ? unit.slice(0, 4) : unit
        el.innerHTML = src
            ? '<img src="' + src + '" alt="">'
            : esc(text)
        el.classList.toggle('cp-coin-badge', !src)
        el.style.background = src ? '' : (COIN_COLORS[unit] || '#9CA3AF')
        el.style.fontSize = src ? '' : (text.length > 3 ? 8 : 10) + 'px'
    }

    var q = new URLSearchParams(location.search)
    var kind = String(q.get('kind') || 'vip').toLowerCase()
    var planId = parseInt(q.get('plan_id') || '0', 10)
    var tokens = String(q.get('tokens') || '')
    var price = String(q.get('price') || '')
    var packageId = parseInt(q.get('package_id') || '0', 10)
    // 法币通道（后台 lp_fiat_channel，单国本币收款）：kind=fiat&fiat_id=1
    var fiatId = parseInt(q.get('fiat_id') || '0', 10)
    var isFiat = fiatId > 0
    var fiatChannel = null

    var channels = []
    var picked = null
    var order = null
    var pollTimer = null
    var cdTimer = null

    function $(id) { return document.getElementById(id) }
    function token() { try { return localStorage.getItem('live_access_token') || '' } catch (e) { return '' } }

    function api(path, opts) {
        opts = opts || {}
        var headers = Object.assign({}, opts.headers || {})
        var tk = token()
        if (tk) { headers.token = tk }
        if (opts.body && !headers['Content-Type'] && typeof opts.body === 'string' && opts.form) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded'
        }
        return fetch(API + path, {
            method: opts.method || 'GET',
            headers: headers,
            body: opts.body
        }).then(function (r) { return r.json() })
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function money(v) {
        var n = parseFloat(v)
        if (isNaN(n)) { return '$' + v }
        return '$' + n.toFixed(2)
    }

    // ---------- 金额 / 描述 ----------
    function renderCoinSummary() {
        $('cp-desc').textContent = (tokens ? tokens + ' Tokens' : 'Token recharge')
        $('cp-price').textContent = price ? money(price) : '—'
    }

    function loadVipSummary() {
        return api('/vip/plans').then(function (r) {
            var list = (r && r.code === '00000' && r.data) ? r.data : []
            var plan = null
            for (var i = 0; i < list.length; i++) {
                if (parseInt(list[i].id, 10) === planId) { plan = list[i] }
            }
            if (!plan) {
                $('cp-desc').textContent = 'Plan not found'
                $('cp-price').textContent = '—'
                $('cp-continue').disabled = true
                return
            }
            var level = String(plan.level || 'premium') === 'premium_plus' ? 'Premium Plus' : 'Premium'
            var months = parseInt(plan.months, 10) || 1
            var tk = plan.tokens_month ? ' · ' + plan.tokens_month + ' tokens/mo' : ''
            $('cp-desc').textContent = level + ' | ' + months + ' Month' + (months > 1 ? 's' : '') + tk
            $('cp-price').textContent = money(plan.usd_price)
        })
    }

    // ---------- 币种选择 ----------
    function loadChannels() {
        return api('/recharge/channels').then(function (r) {
            channels = (r && r.code === '00000' && r.data) ? r.data : []
            renderCurList('')
            if (!channels.length) {
                $('cp-currency-name').textContent = 'No currency available'
                $('cp-continue').disabled = true
            }
        })
    }

    function renderCurList(kw) {
        var box = $('cp-cur-list')
        kw = String(kw || '').trim().toLowerCase()
        var rows = channels.filter(function (ch) {
            if (!kw) { return true }
            return (String(ch.name || '') + ' ' + String(ch.type || '')).toLowerCase().indexOf(kw) >= 0
        })
        if (!rows.length) {
            box.innerHTML = '<div style="padding:16px;color:#9CA3AF;font-size:13px;text-align:center;">No result</div>'
            return
        }
        box.innerHTML = rows.map(function (ch) {
            return '<button type="button" class="cp-item" data-id="' + esc(ch.id) + '">' +
                iconHtml(ch, 'cp-item-ico') +
                '<span class="cp-item-name">' + esc(ch.name) + '<div class="cp-item-sub">' + esc(chainLabel(ch)) + '</div></span>' +
                '</button>'
        }).join('')
    }

    function pickChannel(ch) {
        picked = ch
        var nameEl = $('cp-currency-name')
        nameEl.textContent = ch.name
        nameEl.classList.remove('is-placeholder')
        fillIcon($('cp-currency-ico'), ch)
        $('cp-net-row').hidden = false
        $('cp-net-value').textContent = chainLabel(ch)
        $('cp-continue').disabled = false
        // 等价金额：稳定币按 1:1（金额就是价格）；其它币种要下单后由服务端按汇率折算，这里不猜
        var stable = (typeof ch.pegged === 'boolean')
            ? ch.pegged
            : /usdt|usdc|tusd|dai/i.test(String(ch.type || '') + ' ' + String(ch.name || ''))
        var fiat = String(($('cp-price') || {}).textContent || '').replace(/[^0-9.]/g, '')
        $('cp-equiv').hidden = !(stable && fiat)
        if (!$('cp-equiv').hidden) {
            $('cp-equiv').innerHTML = iconHtml(ch, 'cp-select-ico') +
                '<span>≈ ' + fiat + ' ' + unitOf(ch) + '</span>'
        }
    }

    // ---------- 法币通道（单国本币收款） ----------
    /** 取通道配置（币种 / 后台汇率 / 精度），用于付款前预估 */
    function loadFiatChannel() {
        return api('/recharge/fiat-channels').then(function (r) {
            var list = (r && r.code === '00000' && r.data) ? r.data : []
            for (var i = 0; i < list.length; i++) {
                if (parseInt(list[i].id, 10) === fiatId) { fiatChannel = list[i] }
            }
            if (!fiatChannel) {
                $('cp-fiat-preview').textContent = 'Unavailable'
                $('cp-continue').disabled = true
                return
            }
            $('cp-continue').disabled = false
            renderFiatPreview()
        })
    }

    /** 当前本单的 USD 标价（套餐价 / 上页传来的充值金额） */
    function fiatUsd() {
        var n = parseFloat(String(($('cp-price') || {}).textContent || '').replace(/[^0-9.]/g, ''))
        return isNaN(n) ? 0 : n
    }

    /** USD → 本币：与后端 FiatChannelService::convert 同一算法，仅用于下单前预估展示 */
    function fiatAmount(usd, ch) {
        var d = Math.max(0, Math.min(8, parseInt(ch.decimals, 10) || 0))
        var p = Math.pow(10, d)
        return (Math.round(usd * parseFloat(ch.usd_rate) * p) / p).toFixed(d)
    }

    function fiatMoney(ch, amount) {
        var prefix = ch.symbol || ch.currency || ''
        return prefix === '' ? String(amount) : prefix + ' ' + amount
    }

    /** 下单前的前台预估：真正锁定的汇率以服务端下单结果为准 */
    function renderFiatPreview() {
        var usd = fiatUsd()
        if (!fiatChannel || usd <= 0) { return }
        $('cp-fiat-preview').textContent = fiatMoney(fiatChannel, fiatAmount(usd, fiatChannel))
        $('cp-fiat-meta').textContent = fiatChannel.name +
            ' · 1 USD = ' + fiatChannel.usd_rate + ' ' + fiatChannel.currency
    }

    /** 法币下单：服务端按后台汇率折算成本币，并把当次汇率锁进订单 */
    function createFiatOrder() {
        var req
        if (kind === 'vip') {
            req = api('/vip/order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: planId, fiat_channel_id: fiatId })
            })
        } else {
            req = api('/recharge/submit', {
                method: 'POST',
                form: true,
                body: 'fiat_channel_id=' + encodeURIComponent(fiatId) +
                    '&amount=' + encodeURIComponent(price) +
                    (tokens ? '&tokens=' + encodeURIComponent(tokens) : '') +
                    (packageId ? '&package_id=' + encodeURIComponent(packageId) : '')
            })
        }
        return req.then(function (r) {
            if (!r || r.code !== '00000' || !r.data) { throw new Error((r && r.msg) || 'Create order failed') }
            var d  = r.data
            var ch = d.fiat_channel || {}
            return {
                fiat: {
                    order_no: d.order_no,
                    amount: String(d.pay_amount),
                    currency: d.pay_currency || ch.currency || '',
                    symbol: (d.fiat && d.fiat.symbol) || ch.symbol || '',
                    usd: String(d.usd_amount),
                    rate: d.fx_rate,
                    name: d.channel_name || ch.name || '',
                    instructions: d.instructions || ch.instructions || '',
                    pay_url: d.pay_url || ''
                },
                expire_at: d.expire_at
            }
        })
    }

    // ---------- 下单 ----------
    function createOrder() {
        if (!isFiat && !picked) { return }
        var btn = $('cp-continue')
        btn.disabled = true
        btn.textContent = 'Creating order…'

        var req
        if (isFiat) {
            req = createFiatOrder()
        } else if (kind === 'vip') {
            req = api('/vip/order', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ plan_id: planId, channel_id: parseInt(picked.id, 10) })
            }).then(function (r) {
                if (!r || r.code !== '00000' || !r.data) { throw new Error((r && r.msg) || 'Create order failed') }
                return {
                    order_no: r.data.order_no,
                    pay_amount: r.data.pay_amount,
                    pay_address: r.data.pay_address,
                    expire_at: r.data.expire_at,
                    confirm_mode: r.data.confirm_mode,
                    // 币种/网络/精度都以后台下发的为准
                    channel: withSpec(picked, r.data)
                }
            })
        } else {
            var body = 'channel_id=' + encodeURIComponent(picked.id) + '&amount=' + encodeURIComponent(price)
            req = api('/recharge/submit', {
                method: 'POST',
                form: true,
                body: body
            }).then(function (r) {
                if (!r || r.code !== '00000' || !r.data) { throw new Error((r && r.msg) || 'Create order failed') }
                return {
                    order_no: r.data.order_no,
                    pay_amount: r.data.pay_amount,
                    pay_address: r.data.pay_address || (r.data.channel || {}).address || '',
                    expire_at: r.data.expire_at,
                    confirm_mode: r.data.confirm_mode,
                    channel: withSpec(picked, r.data)
                }
            })
        }

        req.then(function (o) {
            order = o
            showPayStep(o)
        }).catch(function (e) {
            btn.disabled = false
            btn.textContent = 'Continue'
            alert(e && e.message ? e.message : 'Create order failed')
        })
    }

    /** 后台下发的币种规格补进渠道对象（unit / chain_label / pegged 优先用后端的） */
    function withSpec(ch, data) {
        var out = {}
        for (var k in ch) { if (Object.prototype.hasOwnProperty.call(ch, k)) { out[k] = ch[k] } }
        if (data) {
            if (data.unit) { out.unit = data.unit }
            if (data.chain_label) { out.chain_label = data.chain_label }
            if (typeof data.pegged === 'boolean') { out.pegged = data.pegged }
            if (data.confirm_mode) { out.confirm_mode = data.confirm_mode }
            if (data.memo) { out.memo = data.memo }
            if (data.qr_code_url) { out.qr_code_url = data.qr_code_url }
        }
        return out
    }

    // ---------- 付款信息 ----------
    function showPayStep(o) {
        if (o.fiat) { showFiatStep(o); return }
        var ch = o.channel || {}
        var unit = unitOf(ch)
        $('cp-step1').hidden = true
        $('cp-step2').hidden = false
        $('cp-exact').textContent = String(o.pay_amount) + ' ' + unit
        $('cp-chain').textContent = chainLabel(ch)
        $('cp-address').textContent = o.pay_address || '—'
        $('cp-order').textContent = o.order_no || '—'

        // XRP / TON 这类必须带备注的链：把 Memo 单独列出来，防止用户漏填丢币
        var memo = String(o.memo || ch.memo || '')
        $('cp-memo-block').hidden = !memo
        if (memo) { $('cp-memo').textContent = memo }

        var auto = String(o.confirm_mode || 'auto') === 'auto'
        $('cp-note').textContent = auto
            ? 'Please send the exact amount — it is how we identify your order. Credited automatically after 1 network confirmation (usually within a minute).'
            : 'Please send the exact amount. Your order will be credited after our review (usually within a few minutes).'

        renderQr(o)
        startCountdown(o.expire_at)
        startPoll(o.order_no)
    }

    /**
     * 法币付款信息：金额是本币实付（下单时按后台汇率锁定），
     * 没有收款地址/二维码，有网关收银台才给「Pay now」
     */
    function showFiatStep(o) {
        var f = o.fiat
        $('cp-step1').hidden = true
        $('cp-step2').hidden = false

        $('cp-exact-label').textContent = 'Please pay'
        $('cp-exact').textContent = fiatMoney(f, f.amount)
        $('cp-exact-copy').hidden = true
        // 本币是本单锁定值，同时把折算前的 USD 与汇率标出来，方便用户对账
        $('cp-usd-note').hidden = false
        $('cp-usd-note').textContent = '= $' + f.usd + ' · 1 USD = ' + f.rate + ' ' + f.currency

        $('cp-addr-block').hidden = true
        $('cp-memo-block').hidden = true
        $('cp-order').textContent = f.order_no

        $('cp-note').textContent = f.instructions !== ''
            ? f.instructions
            : 'Pay ' + fiatMoney(f, f.amount) + ' via ' + f.name +
              '. Your order will be credited after our review (usually within a few minutes).'

        if (f.pay_url) {
            var pay = $('cp-fiat-pay')
            pay.href = f.pay_url
            pay.style.display = 'block'
        }
        $('cp-back').textContent = 'Choose another method'

        startCountdown(o.expire_at)
        startPoll(f.order_no)
    }

    /**
     * 二维码：先用收款地址在本机生成（永远不会失败，且与上方地址一致），
     * 后台上传了收款码再异步换成它——换图失败也不影响已经显示出来的二维码。
     */
    function renderQr(o) {
        var ch = o.channel || {}
        var box = $('cp-qr')
        var text = String(o.pay_address || ch.address || '')
        renderAddressQr(box, text)

        var custom = String(o.qr_code_url || ch.qr_code_url || '')
        if (!custom) { return }

        // 站内的 /storage/ 资源一律走「当前页面同源」地址：后端下发的是它那个域名的
        // 绝对地址（而且常被代理成 https://IP/…，证书只签了主域名），用户从别的域名、
        // 端口或 IP 进来时那张图会静默加载失败，二维码就空在那里
        var m = custom.match(/\/storage\/.*$/i)
        var url = m
            ? location.origin + m[0]
            : (/^https?:/i.test(custom) ? custom : location.origin + '/' + custom.replace(/^\/+/, ''))
        var img = new Image()
        img.alt = ''
        img.onload = function () { box.innerHTML = ''; box.appendChild(img) }
        img.src = url
    }

    /** 按收款地址现场生成二维码（qrcode-generator 输出 SVG） */
    function renderAddressQr(box, text) {
        if (!text || typeof window.qrcode !== 'function') { box.innerHTML = ''; return }
        try {
            var qr = window.qrcode(0, 'M')
            qr.addData(text)
            qr.make()
            box.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 2, scalable: true })
        } catch (e) {
            box.innerHTML = ''
        }
    }

    /** 服务端返回 'YYYY-MM-DD HH:mm:ss'，Safari 不认这种格式，换成 '/' 分隔 */
    function parseTime(s) {
        var t = Date.parse(String(s || '').replace(/-/g, '/'))
        return isNaN(t) ? 0 : t
    }

    function startCountdown(expireAt) {
        clearInterval(cdTimer)
        var end = parseTime(expireAt)
        if (!end) { $('cp-timer').textContent = '--:--'; return }
        var tick = function () {
            var left = Math.floor((end - Date.now()) / 1000)
            if (left <= 0) {
                $('cp-timer').textContent = '00:00'
                clearInterval(cdTimer)
                return
            }
            var m = Math.floor(left / 60), s = left % 60
            $('cp-timer').textContent = (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s
        }
        tick()
        cdTimer = setInterval(tick, 1000)
    }

    function startPoll(orderNo) {
        clearTimeout(pollTimer)
        if (!orderNo) { return }
        var run = function () {
            api('/recharge/status?order_no=' + encodeURIComponent(orderNo)).then(function (r) {
                var d = (r && r.code === '00000' && r.data) ? r.data : null
                if (d && parseInt(d.status, 10) === 1) {
                    var st = $('cp-state')
                    st.classList.add('is-ok')
                    st.textContent = kind === 'vip'
                        ? 'Payment received — your membership is active.'
                        : 'Payment received — your tokens have been credited.'
                    clearInterval(cdTimer)
                    $('cp-timer').textContent = '00:00'
                    return
                }
                pollTimer = setTimeout(run, POLL_MS)
            }).catch(function () { pollTimer = setTimeout(run, POLL_MS) })
        }
        run()
    }

    // ---------- 弹窗 / 事件 ----------
    function openModal(id) { $(id).hidden = false }
    function closeModal(id) { $(id).hidden = true }

    function renderInfo() {
        var f = order && order.fiat
        var rows
        if (f) {
            rows = [
                ['Merchant', 'Sugus.ai'],
                ['Order', $('cp-desc').textContent],
                ['Order number', f.order_no],
                ['Price', $('cp-price').textContent],
                ['Method', f.name],
                ['Currency', f.currency],
                ['Exchange rate', '1 USD = ' + f.rate + ' ' + f.currency],
                ['Amount to pay', fiatMoney(f, f.amount)]
            ]
        } else {
            var ch = (order && order.channel) || picked || {}
            rows = [
                ['Merchant', 'Sugus.ai'],
                ['Order', $('cp-desc').textContent],
                ['Order number', (order && order.order_no) || '—'],
                ['Price', $('cp-price').textContent],
                ['Currency', ch.name || '—'],
                ['Network', chainLabel(ch)],
                ['Amount to send', $('cp-exact').textContent]
            ]
            if (!$('cp-memo-block').hidden) {
                rows.push(['Memo / Tag', $('cp-memo').textContent])
            }
        }
        $('cp-info-list').innerHTML = rows.map(function (r) {
            return '<div class="cp-info-row"><span class="cp-info-key">' + esc(r[0]) + '</span>' +
                '<span class="cp-info-val">' + esc(r[1]) + '</span></div>'
        }).join('')
    }

    function bind() {
        $('cp-currency-btn').addEventListener('click', function () {
            $('cp-cur-search').value = ''
            renderCurList('')
            openModal('cp-cur-modal')
        })
        $('cp-cur-search').addEventListener('input', function () { renderCurList(this.value) })
        $('cp-cur-list').addEventListener('click', function (e) {
            var btn = e.target.closest('.cp-item')
            if (!btn) { return }
            var id = parseInt(btn.getAttribute('data-id'), 10)
            for (var i = 0; i < channels.length; i++) {
                if (parseInt(channels[i].id, 10) === id) { pickChannel(channels[i]) }
            }
            closeModal('cp-cur-modal')
        })
        Array.prototype.forEach.call(document.querySelectorAll('[data-close]'), function (el) {
            el.addEventListener('click', function () { closeModal(el.getAttribute('data-close')) })
        })
        Array.prototype.forEach.call(document.querySelectorAll('.cp-modal'), function (el) {
            el.addEventListener('click', function (e) { if (e.target === el) { el.hidden = true } })
        })
        $('cp-continue').addEventListener('click', createOrder)
        $('cp-info-btn').addEventListener('click', function () { renderInfo(); openModal('cp-info-modal') })
        $('cp-back').addEventListener('click', function () {
            clearTimeout(pollTimer)
            clearInterval(cdTimer)
            order = null
            $('cp-step2').hidden = true
            $('cp-step1').hidden = false
        })
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.cp-copy')
            if (!btn) { return }
            var text = ($(btn.getAttribute('data-copy')) || {}).textContent || ''
            if (!text || text === '—') { return }
            var done = function () {
                var old = btn.textContent
                btn.textContent = 'Copied'
                setTimeout(function () { btn.textContent = old }, 1200)
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, done)
            } else {
                var ta = document.createElement('textarea')
                ta.value = text
                ta.style.position = 'fixed'
                ta.style.opacity = '0'
                document.body.appendChild(ta)
                ta.select()
                try { document.execCommand('copy') } catch (err) {}
                document.body.removeChild(ta)
                done()
            }
        })
    }

    /**
     * 卡支付兜底入口：后台开关关掉 / 商户参数没配齐时，把这条「Continue with card」
     * 连同上面的「or choose other methods」一起藏掉，否则页面上会留一个点了没反应的死链接
     */
    function syncCardEntry() {
        api('/recharge/packages').then(function (r) {
            var fp = (r && r.code === '00000' && r.data) ? r.data.flowpay : null
            if (fp && fp.enabled) { return }
            [$('cp-or'), $('cp-alt-panel')].forEach(function (el) {
                if (el) { el.hidden = true }
            })
        }).catch(function () { /* 状态拿不到就保持原样，不影响下单 */ })
    }

    function init() {
        if (!token()) {
            location.replace('./Login.html?redirect=' + encodeURIComponent(location.pathname + location.search))
            return
        }
        bind()
        $('cp-continue').disabled = true
        // 卡支付兜底入口：从充值页进来的回充值页，从订阅页进来的回订阅页，
        // 不然用户点过去就得重新选一遍套餐
        if ($('cp-alt-card')) {
            $('cp-alt-card').href = kind === 'vip' ? './Subscriptions.html' : './purchasePoints.html'
        }
        syncCardEntry()
        // 预估价要用套餐价格，所以摘要渲染完再拉法币通道配置
        var summary = kind === 'vip' ? loadVipSummary() : Promise.resolve(renderCoinSummary())
        if (isFiat) {
            // 法币通道：币种由通道决定，不显示币种选择器，改成展示按后台汇率折算的预估价
            $('cp-pick-panel').hidden = true
            $('cp-fiat-panel').hidden = false
            summary.then(loadFiatChannel)
        } else {
            loadChannels()
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init)
    } else {
        init()
    }
})()
