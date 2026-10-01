/**
 * 提现页（Withdrawal.html）专用：收款账户 + 提现申请 + 提现记录
 * 对接后端 v1 接口（Bearer token）
 *
 * 流程：申请冻结代币 → 后台审核 → 审核通过后自动上链打款 → 链上确认到账
 * 门槛：登录即可，无商家认证；但必须先绑定收款地址，且金额在 [最低额度, 可提余额] 之间
 *
 * 单据状态 status：0 待审核 / 1 已打款 / 2 已拒绝
 * 打款状态 pay_state：0 待打款 / 1 已广播待确认 / 2 已确认 / 3 打款失败 / 4 处理中
 */
(()=>{
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')

    var account = { network: '', address: '' }   // 当前收款账户
    var rate = 0, feeRate = 0, minDiamonds = 0, balance = 0
    var estTimer = null

    var EXPLORER = {
        TRC20: 'https://tronscan.org/#/transaction/',
        ERC20: 'https://etherscan.io/tx/',
        BEP20: 'https://bscscan.com/tx/',
    }

    // 地址格式先在前端挡一道，减少打款失败
    var ADDR_RE = {
        TRC20: /^T[1-9A-HJ-NP-Za-km-z]{33}$/,
        ERC20: /^0x[0-9a-fA-F]{40}$/,
        BEP20: /^0x[0-9a-fA-F]{40}$/,
    }

    var addressHints = {
        TRC20: 'Enter TRC20 address (T...)',
        ERC20: 'Enter ERC20 address (0x...)',
        BEP20: 'Enter BEP20 address (0x...)',
    }

    $(function() {
        if (!token) {
            layer.msg('请先登录')
            setTimeout(function(){ location.href = './Login.html' }, 900)
            return
        }
        bindEvents()
        loadAccount()
        loadMeta()
        loadRecords()
    })

    function bindEvents() {
        // 收款账户弹窗
        $('#fin-edit-account').on('click', function() {
            loadAccount(function(acc) {
                $('#user_withdrawal').val(acc.address)
                var net = ['TRC20', 'ERC20', 'BEP20'].indexOf(acc.network) >= 0 ? acc.network : 'TRC20'
                $('#user_network').val(net)
                syncAddressPlaceholder()
                $('.withdrawal-modal').removeClass('hidden')
            })
        })
        $('#user_network').on('change', syncAddressPlaceholder)
        $('.toggle-popup').on('click', function() { $('.withdrawal-modal').addClass('hidden') })
        $('.modal-backdrop').on('click', function() { $('.withdrawal-modal').addClass('hidden') })

        $('#saveWithdrawal').on('click', function() {
            var addr = $('#user_withdrawal').val().trim()
            var net = $('#user_network').val() || 'TRC20'
            if (!addr) { layer.msg('请输入收款地址'); return }
            if (ADDR_RE[net] && !ADDR_RE[net].test(addr)) {
                layer.msg(net === 'TRC20' ? 'TRC20 地址应为 T 开头的 34 位地址' : net + ' 地址应为 0x 开头的 42 位地址')
                return
            }
            var $btn = $(this)
            $btn.prop('disabled', true)
            $.ajax({
                url: base + '/withdraw/account',
                method: 'POST',
                headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
                data: JSON.stringify({ network: net, address: addr }),
                dataType: 'json',
            }).done(function(r) {
                if (r.code === '00000') {
                    account = { network: net, address: addr }
                    renderAccount()
                    $('.withdrawal-modal').addClass('hidden')
                    layer.msg('收款账户已更新')
                } else if (isTokenDead(r.code)) {
                    relogin()
                } else {
                    layer.msg(r.msg || '保存失败')
                }
                syncSubmitState()
            }).fail(function(){ layer.msg('网络错误') })
              .always(function(){ $btn.prop('disabled', false) })
        })

        // 金额输入：本地实时试算，不逐次打接口
        $('#fin-wd-amount').on('input', function() {
            clearTimeout(estTimer)
            var d = parseFloat($(this).val())
            if (!d || d <= 0) {
                showEl('#fin-wd-est', 'none')
                hideMsg()
                syncSubmitState()
                return
            }
            estTimer = setTimeout(function() { renderEstimate(d) }, 250)
        })

        $('#fin-wd-max').on('click', function() {
            if (!balance) { layer.msg('暂无可提余额'); return }
            $('#fin-wd-amount').val(Math.floor(balance)).trigger('input')
        })

        // 提交
        $('#fin-wd-submit').on('click', function() {
            var d = parseFloat($('#fin-wd-amount').val())
            if (!d || d <= 0) { msg('Please enter the amount of diamonds.', 'warn'); return }
            if (d < minDiamonds) { msg('Minimum withdrawal is ' + num(minDiamonds) + ' diamonds' + minUsdtText() + '.', 'warn'); return }
            if (d > balance) { msg('Amount exceeds your withdrawable balance (' + num(balance) + ' diamonds' + usdtText(balance) + ').', 'warn'); return }
            if (!account.address) { msg('Add a payout address first.', 'warn'); return }

            var usdt = d * rate
            var fee = usdt * feeRate
            layer.confirm(
                '<div style="line-height:2;font-size:14px">' +
                '提现 <b style="color:#E75275">' + num(d) + ' diamonds</b><br>' +
                '到账 <b style="color:#28c76f">' + num(usdt - fee, 6) + ' USDT</b>（手续费 ' + num(fee, 6) + '）<br>' +
                '网络 <b style="color:#fff">' + esc(account.network) + '</b> · 地址 <span style="color:#bbb">' + esc(short(account.address)) + '</span>' +
                '</div>',
                { title: '确认提现', btn: ['确认提交', '取消'] },
                function(i) { layer.close(i); doApply(d) }
            )
        })
    }

    function doApply(d) {
        var $btn = $('#fin-wd-submit')
        if ($btn.data('busy')) return
        $btn.data('busy', true).prop('disabled', true).text('Submitting…')
        var idx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/withdraw/apply',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify({ diamonds: d }),
            dataType: 'json',
        }).done(function(r) {
            layer.close(idx)
            $btn.data('busy', false).text('Submit withdrawal')
            if (r.code === '00000') {
                layer.msg('已提交：审核通过后自动上链打款')
                $('#fin-wd-amount').val('')
                showEl('#fin-wd-est', 'none')
                hideMsg()
                loadRecords()
                loadMeta()
            } else if (isTokenDead(r.code)) {
                relogin()
            } else {
                msg(esc(r.msg || '提交失败'), 'error')
            }
            syncSubmitState()
        }).fail(function(){
            layer.close(idx)
            $btn.data('busy', false).text('Submit withdrawal')
            msg('Network error, please retry.', 'error')
            syncSubmitState()
        })
    }

    // ===== 试算 =====
    // 汇率/手续费率/最低额度/余额由 loadMeta 一次性取回，随后本地实时算
    function renderEstimate(d) {
        var usdt = d * rate
        var fee = usdt * feeRate
        $('#fin-est-actual').text(num(usdt - fee, 6) + ' USDT')
        $('#fin-est-fee').text(num(fee, 6) + ' USDT (' + num(feeRate * 100, 2) + '%)')
        $('#fin-est-rate').text('1 diamond = ' + num(rate, 6) + ' USDT')
        showEl('#fin-wd-est', 'block')

        if (d < minDiamonds) {
            msg('Minimum withdrawal is ' + num(minDiamonds) + ' diamonds' + minUsdtText() + '.', 'warn')
        } else if (d > balance) {
            msg('Amount exceeds your withdrawable balance (' + num(balance) + ' diamonds' + usdtText(balance) + ').', 'warn')
        } else {
            hideMsg()
        }
        syncSubmitState()
    }

    // 最低额度的 USDT 换算文案，如 " (≈ 10 USDT)"
    function minUsdtText() {
        return (minDiamonds > 0 && rate > 0) ? ' (≈ ' + num(minDiamonds * rate, 2) + ' USDT)' : ''
    }

    // 任意钻石数的 USDT 换算文案，如 " ≈ 123.45 USDT"
    function usdtText(diamonds) {
        return (diamonds > 0 && rate > 0) ? ' ≈ ' + num(diamonds * rate, 2) + ' USDT' : ''
    }

    function syncSubmitState() {
        var d = parseFloat($('#fin-wd-amount').val())
        var ok = !!account.address && d > 0 && d >= minDiamonds && d <= balance
        $('#fin-wd-submit').prop('disabled', !ok).css({
            background: ok ? '#E75275' : '#3a3a3f',
            color: ok ? '#fff' : '#8a8a8a',
            cursor: ok ? 'pointer' : 'not-allowed',
        })
    }

    // 用最小数量的试算把「汇率/手续费率/最低额度/余额」取回来
    // （preview 只按数量算费率，不校验最低额度，传 1 是安全的只读调用）
    function loadMeta() {
        $.ajax({ url: base + '/withdraw/preview?diamonds=1', headers: h() }).done(function(r) {
            if (r.code !== '00000') {
                if (isTokenDead(r.code)) relogin()
                return
            }
            var p = r.data || {}
            rate = parseFloat(p.rate) || 0
            feeRate = parseFloat(p.fee_rate) || 0
            minDiamonds = parseFloat(p.min_diamonds) || 0
            $('#fin-wd-min').text(num(minDiamonds))
            // 最低额度的自动换算（如 ≈ 10 USDT）
            $('#fin-wd-min-usdt').text(minDiamonds > 0 && rate > 0 ? ' ≈ ' + num(minDiamonds * rate, 2) + ' USDT' : '')
            setBalance(p.balance)
            syncSubmitState()
        })
    }

    // ===== 收款账户 =====
    function loadAccount(cb) {
        $.ajax({ url: base + '/withdraw/account', headers: h() }).done(function(r) {
            if (r.code !== '00000') {
                if (isTokenDead(r.code)) relogin()
                return
            }
            account = r.data || { network: 'TRC20', address: '' }
            renderAccount()
            syncSubmitState()
            if (cb) cb(account)
        })
    }

    function renderAccount() {
        if (account.address) {
            $('#fin-account-text').html(
                '<span style="color:#E75275">' + esc(account.network) + '</span> · ' + esc(short(account.address))
            )
        } else {
            $('#fin-account-text').html('<span style="color:#f59e0b">Not set — click “Edit withdrawal account”</span>')
        }
    }

    // 按网络提示地址格式
    function syncAddressPlaceholder() {
        var net = $('#user_network').val() || 'TRC20'
        $('#user_withdrawal').attr('placeholder', addressHints[net] || 'Enter Withdrawal Account')
    }

    // ===== 提现记录 =====
    function loadRecords() {
        $.ajax({ url: base + '/withdraw/records', headers: h() }).done(function(r) {
            if (r.code !== '00000') {
                if (isTokenDead(r.code)) relogin()
                return
            }
            var list = (r.data && r.data.list) ? r.data.list : []
            if (!list.length) {
                $('#fin-wd-list').html('<div style="padding:14px 0;font-size:13px;color:#666">No withdrawals yet.</div>')
                return
            }
            var rows = ''
            list.forEach(function(w) { rows += recordRow(w) })
            $('#fin-wd-list').html(rows)
        })
    }

    function recordRow(w) {
        var st = statusOf(w)
        var hash = String(w.tx_hash || '')
        var extra = ''
        if (hash) {
            var url = (EXPLORER[w.network] || '') + hash
            extra += '<div style="margin-top:5px;font-size:11.5px">' +
                '<a href="' + esc(url) + '" target="_blank" rel="noopener" style="color:#E75275;text-decoration:none">TxHash ' + esc(short(hash)) + ' ↗</a></div>'
        }
        if (parseInt(w.status, 10) === 2 && w.reject_reason) {
            extra += '<div style="margin-top:5px;font-size:11.5px;color:#ff7b7b">Reason: ' + esc(w.reject_reason) + '</div>'
        } else if (parseInt(w.pay_state, 10) === 3 && w.pay_error) {
            extra += '<div style="margin-top:5px;font-size:11.5px;color:#ff7b7b">' + esc(w.pay_error) + '</div>'
        }
        return '<div style="padding:12px 0;border-bottom:1px solid #26262b">' +
            '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px">' +
              '<span style="font-size:13px;font-weight:600;color:#fff">' + num(w.diamond_amount) + ' → ' + num(w.actual_usdt, 6) + ' USDT</span>' +
              '<span style="font-size:11.5px;padding:2px 9px;border-radius:999px;white-space:nowrap;background:' + st.bg + ';color:' + st.color + '">' + st.text + '</span>' +
            '</div>' +
            '<div style="display:flex;justify-content:space-between;gap:10px;margin-top:4px;font-size:11.5px;color:#7a7a7a">' +
              '<span>' + esc(w.network || '') + (w.address ? ' · ' + esc(short(w.address)) : '') + '</span>' +
              '<span>' + esc(String(w.created_at || '').slice(0, 16)) + '</span>' +
            '</div>' + extra +
        '</div>'
    }

    function statusOf(w) {
        if (parseInt(w.status, 10) === 2) {
            return { text: 'Rejected', color: '#ff7b7b', bg: 'rgba(255,123,123,.12)' }
        }
        var p = parseInt(w.pay_state, 10) || 0
        if (p === 2) return { text: 'Paid', color: '#28c76f', bg: 'rgba(40,199,111,.12)' }
        if (p === 3) return { text: 'Payout failed', color: '#ff7b7b', bg: 'rgba(255,123,123,.12)' }
        if (p === 1) return { text: 'On-chain confirming', color: '#4ea8ff', bg: 'rgba(78,168,255,.12)' }
        if (p === 4) return { text: 'Payout processing', color: '#f59e0b', bg: 'rgba(245,158,11,.12)' }
        return { text: 'Under review', color: '#f59e0b', bg: 'rgba(245,158,11,.12)' }
    }

    // ===== 工具 =====
    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    function isTokenDead(code) { return code === 'A0100' || code === 'A0101' }

    function relogin() {
        layer.msg('登录已失效，请重新登录')
        setTimeout(function(){ location.href = './Login.html' }, 900)
    }

    function setBalance(v) {
        balance = parseFloat(v) || 0
        $('#fin-balance').text(num(balance) + ' diamonds')
        syncSubmitState()
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    // 去掉多余小数 0，最多保留 6 位
    function num(v, d) {
        var n = parseFloat(v)
        if (!isFinite(n)) return '0'
        var s = n.toFixed(d == null ? 6 : d)
        if (s.indexOf('.') >= 0) s = s.replace(/0+$/, '').replace(/\.$/, '')
        return s
    }

    function short(addr) {
        addr = String(addr || '')
        return addr.length > 14 ? addr.slice(0, 6) + '…' + addr.slice(-6) : addr
    }

    // 元素自带内联 display（flex 等）时，hidden 属性和 jQuery show() 会被内联样式盖掉，这里直接改 display
    function showEl(sel, display) { $(sel).css('display', display) }

    function msg(text, type) {
        var c = {
            info:  ['rgba(255,255,255,.05)', '#333', '#bbb'],
            warn:  ['rgba(245,158,11,.08)', 'rgba(245,158,11,.35)', '#f59e0b'],
            error: ['rgba(255,80,80,.08)', 'rgba(255,80,80,.35)', '#ff7b7b'],
        }[type || 'info']
        $('#fin-wd-msg').css({ display: 'block', background: c[0], border: '1px solid ' + c[1], color: c[2] }).html(text)
    }

    function hideMsg() { showEl('#fin-wd-msg', 'none') }
})()
