/**
 * Transactions 页（Financial.html）
 * 三个页签：Token activity（代币余额变动）/ Recharges（充值订单）/ Subscriptions（订阅记录）
 * 充值订单页签看到的就是「回调是否已入账」的最终状态：网关/链上确认后订单变 Completed 并加到余额
 * 对接后端 v1 接口（Bearer token）
 */
(() => {
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')

    var TABS = ['activity', 'recharge', 'vip']
    var HINTS = {
        activity: 'Every balance change — top-ups, purchases, gifts and refunds — is listed here.',
        recharge: 'Recharge is credited automatically once the payment gateway confirms. Pending orders refresh automatically.',
        vip: 'Your membership orders: token payments, online payments and renewals.'
    }

    var state = {
        tab: 'activity',
        page: { activity: 1, recharge: 1, vip: 1 },
        total: { activity: 0, recharge: 0, vip: 0 },
        loading: false,
        polls: 0
    }
    var pollTimer = null

    var ICONS = {
        in: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14"/><path d="m6 13 6 6 6-6"/></svg>',
        out: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5"/><path d="m6 11 6-6 6 6"/></svg>'
    }

    setTimeout(init, 600)

    function init() {
        if (!token) {
            layer.msg('Please sign in first')
            setTimeout(function () { location.href = './Login.html' }, 900)
            return
        }
        bind()
        var hash = (location.hash || '').replace('#', '')
        if (TABS.indexOf(hash) >= 0) state.tab = hash
        syncTabs()
        if (state.tab !== 'activity') loadBalance()
        load(state.tab, 1, false)
    }

    function loadBalance() {
        api('/wallet/ledger?page=1&page_size=1').done(function (r) {
            if (ok(r) && r.data) setBalance(r.data.balance)
        })
    }

    function bind() {
        $('#tx-tabs').on('click', '.tx-tab', function () {
            if (state.loading) return
            var tab = $(this).data('tab')
            if (!tab || tab === state.tab) return
            clearTimeout(pollTimer)
            state.tab = tab
            state.page[tab] = 1
            state.total[tab] = 0
            state.polls = 0
            syncTabs()
            renderEmpty('')
            load(tab, 1, false)
        })

        $('#tx-more').on('click', function () {
            if (state.loading) return
            var tab = state.tab
            state.page[tab] += 1
            load(tab, state.page[tab], true)
        })
    }

    function syncTabs() {
        $('.tx-tab').removeClass('is-active').filter('[data-tab="' + state.tab + '"]').addClass('is-active')
        $('#tx-hint').text(HINTS[state.tab] || '')
        $('#tx-more').hide()
    }

    // ===== 数据加载 =====
    function load(tab, page, append, silent) {
        state.loading = true
        if (!append && !silent) renderEmpty('Loading…')
        api(urlFor(tab, page)).done(function (r) {
            if (!ok(r)) {
                if (!append) renderEmpty('Failed to load. Please refresh.')
                return
            }
            var d = r.data || {}
            state.total[tab] = Number(d.total || 0)
            if (typeof d.balance !== 'undefined') setBalance(d.balance)

            var list = d.list || []
            // 充值订单列表混着会员单（biz_type=vip），归到 Subscriptions 页签展示
            if (tab === 'recharge') {
                list = list.filter(function (o) { return String(o.biz_type || 'coin') !== 'vip' })
            }
            var rows = list.map(rowFor(tab))
            if (!append && !rows.length) {
                renderEmpty(emptyText(tab))
            } else {
                if (!append) $('#tx-list').empty()
                $('#tx-list').append(rows.map(rowHtml).join(''))
            }
            syncMore()
            if (tab === 'recharge') schedulePoll(list)
        }).fail(function () {
            if (!append) renderEmpty('Failed to load. Please refresh.')
            layer.msg('Network error, please retry')
        }).always(function () {
            state.loading = false
        })
    }

    function urlFor(tab, page) {
        if (tab === 'recharge') return '/recharge/orders?page=' + page
        if (tab === 'vip') return '/vip/orders?page=' + page + '&page_size=20'
        return '/wallet/ledger?page=' + page + '&page_size=20'
    }

    // 未支付订单 10s 自动刷新（回调是服务端即时推送，正常几秒内到账），最多 12 次
    function schedulePoll(list) {
        clearTimeout(pollTimer)
        var pending = list.some(function (o) { return Number(o.status) === 0 })
        if (!pending || state.tab !== 'recharge' || state.polls >= 12) return
        state.polls += 1
        pollTimer = setTimeout(function () {
            if (state.tab !== 'recharge' || state.loading) return
            state.page.recharge = 1
            load('recharge', 1, false, true)
        }, 10000)
    }

    // ===== 行数据 =====
    function rowFor(tab) {
        if (tab === 'recharge') return rechargeRow
        if (tab === 'vip') return vipRow
        return activityRow
    }

    function activityRow(l) {
        var isIn = Number(l.direction) === 1
        var bits = []
        if (l.remark) bits.push(l.remark)
        if (l.balance_after != null) bits.push('Balance ' + num(l.balance_after))
        return {
            dir: isIn ? 1 : -1,
            title: l.type_text || l.biz_type || 'Token activity',
            sub: bits.join(' · '),
            amount: (isIn ? '+' : '-') + num(l.amount) + ' tokens',
            amountCls: isIn ? 'in' : 'out',
            time: fmtTime(l.created_at)
        }
    }

    function rechargeRow(o) {
        var st = Number(o.status)
        var paid = Number(o.pay_state || 0) === 1
        var done = st === 1
        var pill, cls
        if (done) {
            pill = 'Completed'; cls = 'ok'
        } else if (st === 2) {
            pill = 'Rejected'; cls = 'bad'
        } else if (st === 3) {
            pill = 'Closed'; cls = 'muted'
        } else if (paid) {
            pill = 'Processing'; cls = 'warn'
        } else {
            pill = 'Awaiting payment'; cls = 'warn'
        }
        return {
            dir: done ? 1 : 0,
            title: 'Token top-up',
            sub: 'Order ' + (o.order_no || '') + (done ? ' · credited to balance' : ''),
            amount: '+' + num(o.diamond_amount) + ' tokens',
            amountCls: done ? 'in' : 'muted',
            pill: pill,
            pillCls: cls,
            time: fmtTime(o.created_at)
        }
    }

    function vipRow(o) {
        var months = Number(o.months || 0)
        var paidTokens = Number(o.diamond_amount || 0)
        var bits = ['Order ' + (o.order_no || '')]
        if (o.expire_to) bits.push('Valid until ' + String(o.expire_to).slice(0, 10))
        // 后台套餐名可能是中文，英文站统一按「Membership · N months」展示
        var planName = String(o.plan_name || '')
        if (!planName || /[\u4e00-\u9fa5]/.test(planName)) planName = 'Membership'
        return {
            dir: paidTokens > 0 ? -1 : 1,
            title: planName +
                (months > 0 ? ' · ' + months + (months > 1 ? ' months' : ' month') : ''),
            sub: bits.join(' · '),
            amount: paidTokens > 0 ? '-' + num(paidTokens) + ' tokens' : 'Paid online',
            amountCls: paidTokens > 0 ? 'out' : 'muted',
            pill: o.status_text || '',
            pillCls: Number(o.is_active) ? 'ok' : (Number(o.status) === 1 ? 'muted' : 'bad'),
            time: fmtTime(o.created_at)
        }
    }

    function rowHtml(o) {
        var dirCls = o.dir > 0 ? 'in' : 'out'
        return '<div class="tx-row">' +
            '<div class="tx-ico ' + dirCls + '">' + (o.dir > 0 ? ICONS.in : ICONS.out) + '</div>' +
            '<div class="tx-body">' +
                '<div class="tx-title"><span class="tx-name">' + esc(o.title) + '</span>' +
                (o.pill ? '<span class="tx-pill ' + o.pillCls + '">' + esc(o.pill) + '</span>' : '') +
                '</div>' +
                '<div class="tx-sub">' + esc(o.sub) + '</div>' +
            '</div>' +
            '<div class="tx-right">' +
                '<div class="tx-amount ' + (o.amountCls || '') + '">' + esc(o.amount) + '</div>' +
                '<div class="tx-time">' + esc(o.time) + '</div>' +
            '</div>' +
        '</div>'
    }

    function renderEmpty(text) {
        if (!text) { $('#tx-list').empty(); return }
        $('#tx-list').html('<div class="tx-empty">' + esc(text) + '</div>')
    }

    function emptyText(tab) {
        if (tab === 'recharge') return 'No recharge orders yet.'
        if (tab === 'vip') return 'No subscriptions yet.'
        return 'No token activity yet.'
    }

    function syncMore() {
        // 列表接口每页 20 条，只要还有下一页就允许继续加载
        var hasMore = state.page[state.tab] * 20 < state.total[state.tab]
        $('#tx-more').toggle(hasMore)
    }

    function setBalance(v) {
        $('#tx-balance').text(num(v))
    }

    // ===== 工具 =====
    function api(url) {
        return $.ajax({ url: base + url, headers: h(), dataType: 'json', cache: false })
    }

    function ok(r) {
        if (typeof r === 'string') {
            try { r = JSON.parse(r) } catch (e) {}
        }
        if (r && r.code === '00000') return true
        var code = r && r.code
        if (code === 'A0100' || code === 'A0101') {
            try { localStorage.removeItem('live_access_token') } catch (e) {}
            layer.msg('Session expired, please sign in again')
            setTimeout(function () { location.href = './Login.html' }, 900)
        } else {
            layer.msg((r && r.msg) || 'Request failed')
        }
        return false
    }

    function h() {
        return token ? { 'Authorization': 'Bearer ' + token } : {}
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function fmtTime(t) {
        return String(t == null ? '' : t).replace('T', ' ').slice(0, 16)
    }

    // 去掉多余小数 0，最多保留 6 位
    function num(v, d) {
        var n = parseFloat(v)
        if (!isFinite(n)) return '0'
        var s = n.toFixed(d == null ? 2 : d)
        if (s.indexOf('.') >= 0) s = s.replace(/0+$/, '').replace(/\.$/, '')
        return s
    }
})()
