/**
 * Financial 页（资金流水）专用：余额 + 资金流水列表
 * 提现已独立成页：Withdrawal.html（js/withdrawal.js），本文件不再管提现
 * 对接后端 v1 接口（Bearer token）
 */
(()=>{
    var base = '/api/v1'
    var token = localStorage.getItem('live_access_token')
    var moneyTpl = ''

    setTimeout(init, 800)

    function init() {
        if (!token) {
            layer.msg('请先登录')
            setTimeout(function(){ location.href = './Login.html' }, 900)
            return
        }
        moneyTpl = $('#money-template').html()
        injectBalanceBar()
        loadLedger()
    }

    // 余额条（插在流水列表上方）
    function injectBalanceBar() {
        $('#moneyLayout').before(
            '<div id="fin-balance-bar" style="margin:0 auto 16px;width:350px;padding:12px 16px;border-radius:10px;background:rgba(231,82,117,.08);border:1px solid rgba(231,82,117,.25);display:flex;justify-content:space-between;align-items:center" class="sm:w-[450px] md:w-[580px] lg:w-[846px]">' +
            '<span style="font-size:13px;color:#bbb">Token balance</span>' +
            '<span id="fin-balance" style="font-size:20px;font-weight:800;color:#E75275">-</span>' +
            '</div>'
        )
    }

    // ===== 资金流水 =====
    function loadLedger(page) {
        page = page || 1
        $.ajax({ url: base + '/wallet/ledger?page=' + page + '&page_size=20', headers: h() }).done(function(r) {
            if (r.code !== '00000' || !r.data) return
            var list = r.data.list || []
            $('#fin-balance').text(num(r.data.balance) + ' tokens')

            $('#moneyLayout').html(moneyTpl)
            if (!list.length) {
                $('#moneyLayout').append('<div style="padding:16px 6px;font-size:13px;color:#666">No records yet.</div>')
                return
            }
            list.forEach(function(l) {
                var isIn = l.direction === 1
                var html = moneyTpl
                    .replace(/{{form_user_username}}/g, esc(l.type_text))
                    .replace(/{{Lv}}/g, isIn ? 'IN' : 'OUT')
                    .replace(/{{en_memo}}/g, esc((l.remark || '') + ' · ' + String(l.created_at).slice(5, 16)))
                    .replace(/{{money}}/g, (isIn ? '+' : '-') + l.amount + ' 代币')
                    .replace(/{{color}}/g, 'color:' + (isIn ? '#28c76f' : '#ff7b7b'))
                $('#moneyLayout').append(html)
            })
        })
    }

    // ===== 工具 =====
    function h() { return token ? { 'Authorization': 'Bearer ' + token } : {} }

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
})()
