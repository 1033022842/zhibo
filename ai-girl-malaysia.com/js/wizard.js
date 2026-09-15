/* characters 八步向导接活：
 * 每步 Next 把本页已选 radio 存 localStorage.wizardProfile，按 steps 顺序跳下一页；
 * 最终页（chooseClothing）补 Name 输入，提交 POST /api/live/customRoleCreate。
 * 仅影响 Realistic 线（chooseXxx.html）；Anime 线（chooseXxxAnime.html）走同一脚本亦可。 */
(function () {
    var STORE_KEY = 'wizard_profile'
    var STEPS = [
        { page: 'characters',       next: './chooseEthnicity.html' },
        { page: 'chooseEthnicity',  next: './chooseHairStyle.html' },
        { page: 'chooseHairStyle',  next: './chooseBodyType.html' },
        { page: 'chooseBodyType',   next: './choosePersonality.html' },
        { page: 'choosePersonality',next: './chooseOccupation.html' },
        { page: 'chooseOccupation', next: './chooseRelationship.html' },
        { page: 'chooseRelationship', next: './chooseClothing.html' },
        { page: 'chooseClothing',   next: null },
    ]

    function pageName() {
        var p = location.pathname.split('/').pop().replace('.html', '')
        return p.replace(/Anime$/, '')
    }

    function getProfile() {
        try { return JSON.parse(localStorage.getItem(STORE_KEY) || '{}') } catch (e) { return {} }
    }

    function saveField(name, value) {
        var p = getProfile()
        p[name] = value
        localStorage.setItem(STORE_KEY, JSON.stringify(p))
    }

    // 收集本页所有已选 profile[xxx]
    function collectPage() {
        document.querySelectorAll('input[type="radio"][name^="profile["]:checked').forEach(function (r) {
            var m = r.name.match(/^profile\[(.+)\]$/)
            if (m) saveField(m[1], r.value)
        })
    }

    // 恢复本页历史选择
    function restorePage() {
        var p = getProfile()
        document.querySelectorAll('input[type="radio"][name^="profile["]').forEach(function (r) {
            var m = r.name.match(/^profile\[(.+)\]$/)
            if (m && p[m[1]] === r.value) {
                r.checked = true
                r.dispatchEvent(new Event('change', { bubbles: true }))
            }
        })
    }

    // profile[xxx] → 后端短键
    var KEY_MAP = {
        category: 'type', gender: null,
        ethnicity: 'race', age: 'age', eyes_color: 'eye',
        hair_color: 'hair', hair_style: 'hairstyle',
        body: 'body', breast_size: 'breast', butt_size: 'hip',
        personality: 'personality', occupation: 'profession',
        relationship: 'relation', clothing: 'clothing',
    }

    // 最终页提交
    function submitFinal(nameInput) {
        collectPage()
        var p = getProfile()
        var payload = { name: (nameInput && nameInput.value.trim()) || ('My AI ' + new Date().toISOString().slice(5, 10)) }
        Object.keys(KEY_MAP).forEach(function (k) {
            var shortKey = KEY_MAP[k]
            if (shortKey && p[k]) payload[shortKey] = p[k]
        })

        var token = localStorage.getItem('live_access_token')
        if (!token) {
            localStorage.setItem('wizard_redirect_back', '1')
            location.href = './Login.html?redirect=' + encodeURIComponent('./chooseClothing.html')
            return
        }

        var btn = document.getElementById('submitCreateFlowClothingButton')
        if (btn) { btn.disabled = true }

        fetch('/api/live/customRoleCreate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + token },
            body: JSON.stringify(payload),
        }).then(function (r) { return r.json() }).then(function (d) {
            if (d.code === '00000') {
                localStorage.removeItem(STORE_KEY)
                showToast('Character created! Redirecting…', 'success')
                setTimeout(function () { location.href = './charactersIndex.html' }, 900)
            } else if (d.code === '10003' || d.code === '10004') {
                localStorage.removeItem('live_access_token')
                location.href = './Login.html?redirect=' + encodeURIComponent('./chooseClothing.html')
            } else {
                if (btn) btn.disabled = false
                showToast(d.msg || 'Create failed', 'error')
            }
        }).catch(function () {
            if (btn) btn.disabled = false
            showToast('Network error', 'error')
        })
    }

    function showToast(msg, type) {
        var el = document.getElementById('cfToast') || document.querySelector('.cf-toast')
        if (!el) {
            el = document.createElement('div')
            el.id = 'cfToast'
            el.style.cssText = 'position:fixed;top:70px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;z-index:999;box-shadow:0 8px 30px rgba(0,0,0,.4);color:#fff;background:' + (type === 'error' ? '#ef4444' : type === 'warn' ? '#f59e0b' : '#10b981')
            document.body.appendChild(el)
        }
        el.textContent = msg
        el.style.display = 'block'
        setTimeout(function () { el.style.display = 'none' }, 2600)
    }
    window.wizardToast = showToast

    function init() {
        var page = pageName()
        var step = STEPS.find(function (s) { return s.page === page })
        if (!step) return

        restorePage()

        if (page === 'characters') {
            // 向导起点：清空旧数据
            localStorage.removeItem(STORE_KEY)
        }

        if (step.next !== null) {
            // 中间步骤：拦所有跳转按钮 → 收集后进入下一步
            document.querySelectorAll('button[data-create-flow-target="nextButton"], a[data-create-flow-target="nextButton"], #submitCreateFlowButton').forEach(function (b) {
                b.addEventListener('click', function (e) {
                    e.preventDefault()
                    e.stopPropagation()
                    collectPage()
                    location.href = step.next
                }, true)
            })
        } else if (page === 'chooseClothing') {
            // 最终页：注入 Name 输入并接管提交
            var form = document.getElementById('create-flow') || document.querySelector('form')
            if (form && !document.getElementById('wizardNameInput')) {
                var wrap = document.createElement('div')
                wrap.style.cssText = 'margin:14px 0 20px;display:flex;flex-direction:column;gap:8px;max-width:420px'
                wrap.innerHTML = '<label style="color:#A1A1A1;font-size:13px;font-weight:600">Name your AI *</label>' +
                    '<input id="wizardNameInput" maxlength="30" placeholder="e.g. Luna" style="background:#262626;border:1px solid #363636;border-radius:10px;padding:12px 14px;color:#fff;font-size:14px;outline:none" />'
                var submitBtn = document.getElementById('submitCreateFlowClothingButton')
                if (submitBtn && submitBtn.parentNode) {
                    submitBtn.parentNode.insertBefore(wrap, submitBtn)
                } else {
                    form.appendChild(wrap)
                }
            }
            var submit = document.getElementById('submitCreateFlowClothingButton')
            if (submit) {
                submit.addEventListener('click', function (e) {
                    e.preventDefault()
                    e.stopPropagation()
                    submitFinal(document.getElementById('wizardNameInput'))
                }, true)
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init)
    } else {
        init()
    }
})()
