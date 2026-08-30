/**
 * Financial 页专用：余额 + 提现（申请/记录/收款账户） + 资金流水
 * 对接新后端 v1 接口（Bearer token）
 */
(()=>{
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    var moneyTpl = ''
    var withdrawTpl = ''

    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

    setTimeout(init, 800)

    function init() {
        if (!token) {
            layer.msg('请先登录')
            setTimeout(function(){ location.href = './Login.html' }, 900)
            return
        }
        moneyTpl = $('#money-template').html()
        injectWithdrawZone()
        bindEvents()
        loadLedger()
        loadAccount()
        loadWithdrawRecords()
    }

    // ===== 注入提现操作区（页面原缺发起表单） =====
    function injectWithdrawZone() {
        var html =
        '<div id="fin-withdraw-zone" style="margin:0 auto 24px;width:350px" class="sm:w-[450px] md:w-[580px] lg:w-[846px] bg-zinc-900 rounded-[10px] border border-neutral-700 p-5">' +
          '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">' +
            '<div style="font-size:16px;font-weight:700;color:#fff">Withdraw</div>' +
            '<button id="fin-edit-account" style="padding:6px 14px;border:1px solid #E75275;border-radius:8px;background:transparent;color:#E75275;font-size:13px;cursor:pointer">Edit withdrawal account</button>' +
          '</div>' +
          '<div style="display:flex;gap:10px;align-items:center;margin-bottom:8px">' +
            '<input id="fin-wd-amount" type="number" placeholder="Diamonds amount" style="flex:1;padding:10px 14px;border-radius:8px;border:1px solid #444;background:#18181b;color:#fff;font-size:15px;outline:none">' +
            '<button id="fin-wd-preview" style="padding:10px 16px;border:none;border-radius:8px;background:#303030;color:#bbb;cursor:pointer;font-size:13px">Estimate</button>' +
          '</div>' +
          '<div id="fin-wd-result" style="font-size:13px;color:#9a9a9a;margin-bottom:10px;display:none"></div>' +
          '<button id="fin-wd-submit" style="width:100%;padding:12px;border:none;border-radius:10px;background:#E75275;color:#fff;font-weight:700;font-size:14px;cursor:pointer">Submit withdrawal</button>' +
          '<div style="font-size:11px;color:#666;margin-top:8px">人工打款，1-3 个工作日到账 · 需商家认证 · 最低 <span id="fin-wd-min">-</span> 钻</div>' +
        '</div>' +
        '<div id="fin-wd-records" style="margin:0 auto 24px;width:350px" class="sm:w-[450px] md:w-[580px] lg:w-[846px] bg-zinc-900 rounded-[10px] border border-neutral-700 p-5" hidden>' +
          '<div style="font-size:14px;font-weight:700;color:#fff;margin-bottom:10px">Withdrawal records</div>' +
          '<div id="fin-wd-list"></div>' +
        '</div>'
        $('#moneyLayout').before(html)
    }

    function bindEvents() {
        // 收款账户弹窗
        $('#fin-edit-account').on('click', function() {
            loadAccount(function(acc) {
                $('#user_withdrawal').val(acc.address)
                $('#user_network').val(acc.network === 'ERC20' ? 'ERC20' : acc.network)
                $('.withdrawal-modal').removeClass('hidden')
            })
        })
        $('.toggle-popup').on('click', function() { $('.withdrawal-modal').addClass('hidden') })
        $('.modal-backdrop').on('click', function() { $('.withdrawal-modal').addClass('hidden') })

        $('#saveWithdrawal').on('click', function() {
            var addr = $('#user_withdrawal').val().trim()
            var net = $('#user_network').val() || 'TRC20'
            if (!addr) { layer.msg('请输入收款地址'); return }
            $.ajax({
                url: base + '/withdraw/account',
                method: 'POST',
                headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
                data: JSON.stringify({ network: net, address: addr }),
                dataType: 'json',
            }).done(function(r) {
                if (r.code === '00000') {
                    layer.msg('保存成功')
                    $('.withdrawal-modal').addClass('hidden')
                } else { layer.msg(r.msg || '保存失败') }
            }).fail(function(){ layer.msg('网络错误') })
        })

        // 试算
        $('#fin-wd-preview').on('click', function() { preview(false) })
        $('#fin-wd-amount').on('input', function() { preview(true) })

        // 提交
        $('#fin-wd-submit').on('click', function() {
            var d = parseFloat($('#fin-wd-amount').val())
            if (!d || d <= 0) { layer.msg('请输入钻石数量'); return }
            preview(false, function(p) {
                layer.confirm(
                    '提现 <b style="color:#E75275">' + d + ' 钻</b> → 实际到账 <b style="color:#28c76f">' + p.actual + ' USDT</b>（手续费 ' + p.fee + '）',
                    { btn: ['确认提交', '取消'] },
                    function(i) { layer.close(i); doApply(d) }
                )
            })
        })
    }

    function preview(silent, cb) {
        var d = parseFloat($('#fin-wd-amount').val())
        if (!d || d <= 0) { $('#fin-wd-result').hide(); return }
        $.ajax({
            url: base + '/withdraw/preview?diamonds=' + d,
            headers: h(),
        }).done(function(r) {
            if (r.code !== '00000') { if (!silent) layer.msg(r.msg || '请先完成商家认证'); return }
            var p = r.data
            $('#fin-wd-min').text(p.min_diamonds)
            $('#fin-wd-result').show().html(
                '可提余额 <b style="color:#fff">' + p.balance + '</b> 钻 → ' +
                '<b style="color:#28c76f">' + p.actual + ' USDT</b>' +
                '（汇率 ' + p.rate + '，手续费率 ' + (p.fee_rate * 100).toFixed(1) + '%）'
            )
            if (cb) cb(p)
        })
    }

    function doApply(d) {
        var idx = layer.load(1, { shade: [.1, '#fff'] })
        $.ajax({
            url: base + '/withdraw/apply',
            method: 'POST',
            headers: Object.assign({ 'Content-Type': 'application/json' }, h()),
            data: JSON.stringify({ diamonds: d }),
            dataType: 'json',
        }).done(function(r) {
            layer.close(idx)
            if (r.code === '00000') {
                layer.msg('已提交，等待审核（1-3工作日打款）')
                $('#fin-wd-amount').val('')
                $('#fin-wd-result').hide()
                loadWithdrawRecords()
                loadLedger()
            } else { layer.msg(r.msg || '提交失败') }
        }).fail(function(){ layer.close(idx); layer.msg('网络错误') })
    }

    // ===== 收款账户 =====
    function loadAccount(cb) {
        $.ajax({ url: base + '/withdraw/account', headers: h() }).done(function(r) {
            if (r.code === '00000') { if (cb) cb(r.data) }
        })
    }

    // ===== 提现记录 =====
    function loadWithdrawRecords() {
        $.ajax({ url: base + '/withdraw/records', headers: h() }).done(function(r) {
            if (r.code !== '00000' || !r.data || !r.data.list || !r.data.list.length) return
            $('#fin-wd-records').prop('hidden', false)
            var rows = ''
            var cls = { 0: '#f59e0b', 1: '#28c76f', 2: '#ff5050' }
            r.data.list.forEach(function(w) {
                rows += '<div style="display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px solid #26262b;font-size:13px">' +
                    '<span style="color:#ccc">' + w.created_at.slice(0, 16) + '</span>' +
                    '<span style="color:#fff">' + w.diamond_amount + ' 钻 → ' + w.actual_usdt + ' U</span>' +
                    '<span style="color:' + (cls[w.status] || '#999') + '">' + w.status_text + '</span>' +
                '</div>'
            })
            $('#fin-wd-list').html(rows)
        })
    }

    // ===== 资金流水 =====
    function loadLedger(page) {
        page = page || 1
        $.ajax({ url: base + '/wallet/ledger?page=' + page + '&page_size=20', headers: h() }).done(function(r) {
            if (r.code !== '00000' || !r.data) return
            var list = r.data.list || []
            $('#moneyLayout').html(moneyTpl)
            list.forEach(function(l) {
                var isIn = l.direction === 1
                var html = moneyTpl
                    .replace(/{{form_user_username}}/g, l.type_text)
                    .replace(/{{Lv}}/g, l.direction === 1 ? 'IN' : 'OUT')
                    .replace(/{{en_memo}}/g, (l.remark || '') + ' · ' + l.created_at.slice(5, 16))
                    .replace(/{{money}}/g, (isIn ? '+' : '-') + l.amount + ' 钻')
                    .replace(/{{color}}/g, 'color:' + (isIn ? '#28c76f' : '#ff7b7b'))
                $('#moneyLayout').append(html)
            })
            // 余额头部
            if ($('#fin-balance-bar').length === 0) {
                $('#fin-withdraw-zone').prepend(
                    '<div id="fin-balance-bar" style="padding:12px 16px;margin-bottom:14px;border-radius:10px;background:rgba(231,82,117,.08);border:1px solid rgba(231,82,117,.25);display:flex;justify-content:space-between;align-items:center">' +
                    '<span style="font-size:13px;color:#bbb">Diamond balance</span>' +
                    '<span style="font-size:20px;font-weight:800;color:#E75275">' + r.data.balance + ' 钻</span>' +
                    '</div>'
                )
            } else {
                $('#fin-balance-bar').find('span:last').text(r.data.balance + ' 钻')
            }
        })
    }
})()
