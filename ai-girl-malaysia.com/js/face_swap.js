(() => {
    var base = '/api/live'
    var token = localStorage.getItem('live_access_token')
    var templateMap = {}
    var selectedTemplateId = 0
    var pollTimer = null

    function authHeaders() {
        var h = {}
        if (token) h['Authorization'] = 'Bearer ' + token
        return h
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
        })
    }

    function showModal(id, show) {
        var m = $('#' + id)
        if (show) m.removeClass('hidden').addClass('flex')
        else m.addClass('hidden').removeClass('flex')
    }

    function formatTime(v) {
        return v ? String(v).substring(0, 16) : ''
    }

    // ---------------- 模板视频 ----------------

    function loadTemplates() {
        $.ajax({
            url: base + '/faceSwapTemplates',
            method: 'GET',
            dataType: 'json'
        }).done(function (res) {
            var list = (res && res.code === '00000' && res.data && Array.isArray(res.data.list)) ? res.data.list : []
            renderTemplateList(list)
        }).fail(function () {
            renderTemplateList([])
        })
    }

    function renderTemplateList(list) {
        var $list = $('#template-list')
        var $empty = $('#template-empty-state')
        $list.empty()
        templateMap = {}

        if (!list || list.length === 0) {
            $empty.removeClass('hidden')
            return
        }
        $empty.addClass('hidden')

        list.forEach(function (t) {
            templateMap[t.id] = t

            var poster = t.cover_url ? ' poster="' + escapeHtml(t.cover_url) + '"' : ''
            var media = '<video class="w-full h-[160px] object-cover" controls preload="metadata" muted playsinline' + poster + ' src="' + escapeHtml(t.video_url) + '"></video>'
            var duration = parseInt(t.duration_sec || 0, 10) > 0
                ? '<span class="px-2 py-0.5 rounded bg-[#1d1d1d] text-[#A1A1A1] text-[11px]">' + parseInt(t.duration_sec, 10) + 's</span>'
                : ''
            var desc = t.description ? '<div class="text-[#A1A1A1] text-xs mt-1.5 line-clamp-2">' + escapeHtml(t.description) + '</div>' : ''

            var li = '<li class="flex flex-col" data-template-id="' + t.id + '">' +
                '<div class="relative rounded-lg overflow-hidden bg-gray-800 group">' +
                media +
                '<div class="p-3 bg-gray-900">' +
                '<div class="flex items-center justify-between gap-2 mb-1">' +
                '<span class="text-white text-sm font-semibold truncate">' + escapeHtml(t.title || 'Template') + '</span>' +
                duration +
                '</div>' +
                desc +
                '<button type="button" class="use-template-btn mt-3 w-full px-3 py-2 bg-[#E75275] hover:bg-[#d64568] rounded-lg text-white text-xs font-semibold" data-id="' + t.id + '">Use This Template</button>' +
                '</div>' +
                '</div>' +
                '</li>'
            $list.append(li)
        })
    }

    function setTemplatePreview() {
        var t = templateMap[selectedTemplateId]
        var $box = $('#swap-template-preview')
        if (!t) {
            $box.html('<span class="text-sm text-gray-500">请先在列表中选择模板视频</span>')
            return
        }
        var cover = t.cover_url
            ? '<img class="w-[80px] h-[45px] object-cover rounded-[6px] shrink-0" src="' + escapeHtml(t.cover_url) + '" alt="">'
            : ''
        $box.html(cover + '<span class="text-sm text-white font-semibold truncate">' + escapeHtml(t.title || 'Template') + '</span>')
    }

    function openSwapModal(templateId) {
        if (templateId) selectedTemplateId = parseInt(templateId, 10)
        if (!selectedTemplateId) {
            var first = Object.keys(templateMap)[0]
            if (first) selectedTemplateId = parseInt(first, 10)
        }
        setTemplatePreview()
        resetSwapForm(true)
        showModal('swap-modal', true)
    }

    function resetSwapForm(keepPreview) {
        $('#swap-image-input').val('')
        $('#swap-image-preview-box').addClass('hidden')
        $('#swap-image-preview').attr('src', '')
        $('#swap-progress').addClass('hidden')
        $('#swap-progress-bar').css('width', '0%')
        $('#swap-progress-text').text('Submitting... 0%')
        if (!keepPreview) setTemplatePreview()
    }

    // ---------------- 提交换脸任务 ----------------

    function submitSwap() {
        var input = $('#swap-image-input')[0]
        var file = input && input.files[0]
        if (!selectedTemplateId || !templateMap[selectedTemplateId]) {
            layer.msg('请选择模板视频')
            return
        }
        if (!file) {
            layer.msg('请上传人脸图片')
            return
        }

        var formData = new FormData()
        formData.append('file', file)
        formData.append('template_id', String(selectedTemplateId))

        var submitBtn = $('#swap-submit-btn')
        submitBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed')
        $('#swap-progress').removeClass('hidden')

        $.ajax({
            url: base + '/faceSwapCreate',
            method: 'POST',
            headers: authHeaders(),
            processData: false,
            contentType: false,
            data: formData,
            xhr: function () {
                var xhr = new window.XMLHttpRequest()
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        var pct = Math.round((evt.loaded / evt.total) * 100)
                        $('#swap-progress-bar').css('width', pct + '%')
                        $('#swap-progress-text').text('Uploading... ' + pct + '%')
                    }
                }, false)
                return xhr
            }
        }).done(function (res) {
            if (res && res.code === '00000') {
                layer.msg('换脸任务已提交')
                showModal('swap-modal', false)
                resetSwapForm(false)
                loadSwapList()
            } else {
                layer.msg((res && res.msg) || '提交失败')
            }
        }).fail(function () {
            layer.msg('提交失败，请稍后重试')
        }).always(function () {
            submitBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed')
            $('#swap-progress').addClass('hidden')
        })
    }

    // ---------------- 我的换脸记录 ----------------

    function loadSwapList() {
        if (!token) {
            renderSwapList([])
            return
        }
        $.ajax({
            url: base + '/faceSwapTasks',
            method: 'GET',
            headers: authHeaders(),
            dataType: 'json'
        }).done(function (res) {
            var list = (res && res.code === '00000' && res.data && Array.isArray(res.data.list)) ? res.data.list : []
            renderSwapList(list)
        }).fail(function () {
            renderSwapList([])
        })
    }

    function renderSwapList(list) {
        var $list = $('#swap-list')
        var $empty = $('#swap-empty-state')
        var $count = $('#swap-list-count')
        $list.empty()

        if (!list || list.length === 0) {
            $empty.removeClass('hidden')
            $count.text('')
            stopPolling()
            return
        }
        $empty.addClass('hidden')
        $count.text('(' + list.length + ')')

        var hasPending = false

        list.forEach(function (t) {
            if (!t.finished) hasPending = true

            var media = ''
            if (t.status === 'completed' && t.video_url) {
                var poster = t.cover_url ? ' poster="' + escapeHtml(t.cover_url) + '"' : ''
                media = '<video class="w-full h-[160px] object-cover" controls preload="metadata" playsinline' + poster + ' src="' + escapeHtml(t.video_url) + '"></video>'
            } else {
                var text = t.status === 'failed' ? '换脸失败' : (t.status === 'expired' ? '已超时' : '处理中…')
                var color = t.status === 'failed' || t.status === 'expired' ? '#f87171' : '#A1A1A1'
                media = '<div class="flex flex-col items-center justify-center h-[160px] bg-black">' +
                    '<span class="text-sm" style="color:' + color + '">' + escapeHtml(text) + '</span>' +
                    '</div>'
            }

            var photo = t.face_image_url
                ? '<img class="w-8 h-8 object-cover rounded-full shrink-0" src="' + escapeHtml(t.face_image_url) + '" alt="">'
                : ''
            var statusStyle = t.status === 'completed'
                ? 'background:#1d1d1d;color:#34d399'
                : ((t.status === 'failed' || t.status === 'expired') ? 'background:#1d1d1d;color:#f87171' : 'background:#1d1d1d;color:#f59e0b')

            var li = '<li class="flex flex-col">' +
                '<div class="relative rounded-lg overflow-hidden bg-gray-800 group">' +
                media +
                '<div class="p-3 bg-gray-900">' +
                '<div class="flex items-center gap-2 mb-1">' +
                photo +
                '<span class="text-white text-sm font-semibold truncate">' + escapeHtml(t.title || 'Face Swap') + '</span>' +
                '</div>' +
                '<div class="flex items-center justify-between gap-2 mt-1">' +
                '<span class="px-2 py-0.5 rounded text-[11px] font-semibold" style="' + statusStyle + '">' + escapeHtml(t.status_text || '') + '</span>' +
                '<span class="text-[11px]" style="color:#6b6b6b">' + escapeHtml(formatTime(t.created_at)) + '</span>' +
                '</div>' +
                '</div>' +
                '</div>' +
                '</li>'
            $list.append(li)
        })

        if (hasPending) startPolling()
        else stopPolling()
    }

    function startPolling() {
        if (pollTimer) return
        pollTimer = setInterval(loadSwapList, 6000)
    }

    function stopPolling() {
        if (!pollTimer) return
        clearInterval(pollTimer)
        pollTimer = null
    }

    $(function () {
        loadTemplates()
        loadSwapList()

        $('#face-swap-btn, #face-swap-btn-mobile').on('click', function () {
            if (!token) {
                layer.msg('请先登录后再使用换脸功能')
                return
            }
            openSwapModal(0)
        })

        $('#template-list').on('click', '.use-template-btn', function (e) {
            e.stopPropagation()
            if (!token) {
                layer.msg('请先登录后再使用换脸功能')
                return
            }
            openSwapModal($(this).data('id'))
        })

        $('#close-swap-modal, #cancel-swap-btn').on('click', function () {
            showModal('swap-modal', false)
            resetSwapForm(false)
        })

        $('#swap-modal').on('click', function (e) {
            if (e.target && e.target.id === 'swap-modal') {
                showModal('swap-modal', false)
                resetSwapForm(false)
            }
        })

        $(document).on('change', '#swap-image-input', function () {
            var file = this.files && this.files[0]
            if (!file) return
            var reader = new FileReader()
            reader.onload = function (evt) {
                $('#swap-image-preview').attr('src', evt.target.result)
                $('#swap-image-preview-box').removeClass('hidden')
            }
            reader.readAsDataURL(file)
        })

        $('#face-swap-form').on('submit', function (e) {
            e.preventDefault()
            if (!token) {
                layer.msg('请先登录后再提交')
                return
            }
            submitSwap()
        })
    })
})()
