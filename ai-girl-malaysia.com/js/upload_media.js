(() => {
    var base = '/api/live'
    var token = localStorage.getItem('live_access_token')
    var mediaMap = {}
    var uploadCoverUrl = ''
    var editCoverUrl = ''

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

    // 标签/关键词：中英文逗号分隔 -> 数组
    function splitList(v) {
        return String(v || '').replace(/，/g, ',').split(',').map(function (s) { return s.trim() }).filter(function (s) { return s !== '' })
    }

    function showModal(id, show) {
        var m = $('#' + id)
        if (show) m.removeClass('hidden').addClass('flex')
        else m.addClass('hidden').removeClass('flex')
    }

    function onDescInput(elId, counterId) {
        var el = document.getElementById(elId)
        var counter = document.getElementById(counterId)
        if (!el || !counter) return
        var len = el.value.trim().length
        counter.textContent = len + ' / 20 字'
        counter.style.color = len >= 20 ? '#34d399' : (len > 0 ? '#f59e0b' : '#6b7280')
    }
    window.onMediaDescInput = function () { onDescInput('media-desc-input', 'media-desc-counter') }
    window.onEditDescInput = function () { onDescInput('edit-desc-input', 'edit-desc-counter') }

    // 通用图片上传（封面）
    function uploadImage(file, $preview, done) {
        var fd = new FormData()
        fd.append('file', file)
        $.ajax({
            url: base + '/upload',
            method: 'POST',
            headers: authHeaders(),
            processData: false,
            contentType: false,
            data: fd
        }).done(function (res) {
            var url = (res && res.code === '00000' && res.data) ? (res.data.fullurl || res.data.url || '') : ''
            if (url) {
                $preview.html('<img class="w-full h-full object-cover" src="' + escapeHtml(url) + '" alt="">')
                done(url)
            } else {
                layer.msg((res && res.msg) || '封面上传失败')
            }
        }).fail(function () {
            layer.msg('封面上传失败，请稍后重试')
        })
    }

    window.uploadMediaCover = function (input) {
        var file = input.files && input.files[0]
        if (!file) return
        uploadImage(file, $('#media-cover-preview'), function (url) { uploadCoverUrl = url })
    }

    function resetForm() {
        $('#media-file-input').val('')
        $('#media-title-input').val('')
        $('#media-desc-input').val('')
        $('#media-type-select').val('video')
        $('#media-scene-select').val('public')
        $('#media-persona-select').val('')
        $('#media-tags-input').val('')
        $('#media-style-select').val('')
        $('#media-mood-select').val('')
        $('#media-resolution-select').val('')
        $('#media-adult-select').val('0')
        $('#media-keywords-input').val('')
        $('#media-weight-input').val('1')
        $('#media-duration-input').val('')
        $('#media-cover-input').val('')
        $('#media-cover-preview').html('<span class="text-[11px] text-gray-500">点击上传</span>')
        uploadCoverUrl = ''
        onMediaDescInput()
        $('#upload-progress').addClass('hidden')
        $('#upload-progress-bar').css('width', '0%')
        $('#upload-progress-text').text('Uploading... 0%')
    }

    function loadPersonas($select, currentName) {
        if (!token) {
            $select.html('<option value="">登录后可选择角色</option>')
            return
        }
        $.ajax({
            url: base + '/customOneList',
            method: 'POST',
            headers: authHeaders(),
            dataType: 'json'
        }).done(function (res) {
            var list = (res && res.code === '00000' && Array.isArray(res.data)) ? res.data : []
            $select.empty()
            $select.append('<option value="">选择角色（可空）</option>')
            list.forEach(function (p) {
                var name = p.name || ('角色' + p.id)
                var selected = (currentName && name === currentName) ? ' selected' : ''
                $select.append('<option value="' + p.id + '"' + selected + '>' + escapeHtml(name) + '</option>')
            })
        }).fail(function () {
            $select.html('<option value="">角色加载失败</option>')
        })
    }

    function uploadMedia() {
        var fileInput = $('#media-file-input')[0]
        var file = fileInput && fileInput.files[0]
        if (!file) {
            layer.msg('请选择素材文件')
            return
        }

        var description = ($('#media-desc-input').val() || '').trim()
        if (description.length < 20) {
            layer.msg('素材描述不能少于20字（当前 ' + description.length + ' 字）')
            return
        }

        var tags = splitList($('#media-tags-input').val())
        if (!tags.length) {
            layer.msg('请至少填写一个标签')
            return
        }
        if (tags.length > 10) {
            layer.msg('标签最多填写10个')
            return
        }

        var style = $('#media-style-select').val() || ''
        if (!style) {
            layer.msg('请选择素材风格')
            return
        }

        var formData = new FormData()
        formData.append('file', file)
        formData.append('asset_type', $('#media-type-select').val() || 'video')
        formData.append('scene_type', $('#media-scene-select').val() || 'public')
        formData.append('title', ($('#media-title-input').val() || '').trim())
        formData.append('persona_id', $('#media-persona-select').val() || '')
        formData.append('keywords', ($('#media-keywords-input').val() || '').trim())
        formData.append('weight', $('#media-weight-input').val() || '1')
        formData.append('description', description)
        formData.append('tags', tags.join(','))
        formData.append('style', style)
        formData.append('mood', $('#media-mood-select').val() || '')
        formData.append('resolution', $('#media-resolution-select').val() || '')
        formData.append('is_adult', $('#media-adult-select').val() || '0')
        if (uploadCoverUrl) formData.append('cover_url', uploadCoverUrl)

        var duration = parseInt($('#media-duration-input').val() || '0', 10)
        if (duration > 0) formData.append('duration_ms', String(duration))

        $('#upload-progress').removeClass('hidden')
        $('#upload-progress-bar').css('width', '0%')
        $('#upload-progress-text').text('Uploading... 0%')

        var submitBtn = $('#upload-media-form button[type="submit"]')
        submitBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed')

        $.ajax({
            url: base + '/uploadMediaAsset',
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
                        $('#upload-progress-bar').css('width', pct + '%')
                        $('#upload-progress-text').text('Uploading... ' + pct + '%')
                    }
                }, false)
                return xhr
            }
        }).done(function (res) {
            if (res && res.code === '00000') {
                layer.msg('素材上传成功')
                showModal('upload-modal', false)
                resetForm()
                loadMediaList()
            } else {
                layer.msg((res && res.msg) || '上传失败')
            }
        }).fail(function () {
            layer.msg('上传失败，请稍后重试')
        }).always(function () {
            submitBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed')
            $('#upload-progress').addClass('hidden')
        })
    }

    function loadMediaList() {
        if (!token) {
            renderMediaList([])
            return
        }
        $.ajax({
            url: base + '/mediaAssetList',
            method: 'GET',
            headers: authHeaders(),
            dataType: 'json'
        }).done(function (res) {
            var list = (res && res.code === '00000' && res.data && Array.isArray(res.data.list)) ? res.data.list : []
            renderMediaList(list)
        }).fail(function () {
            renderMediaList([])
        })
    }

    function renderMediaList(list) {
        var $list = $('#media-list')
        var $empty = $('#media-empty-state')
        var $count = $('#media-list-count')
        $list.empty()
        mediaMap = {}

        if (!list || list.length === 0) {
            $empty.removeClass('hidden')
            $count.text('')
            return
        }
        $empty.addClass('hidden')
        $count.text('(' + list.length + ')')

        list.forEach(function (m) {
            mediaMap[m.id] = m

            var mediaHtml = ''
            var poster = m.cover_url ? ' poster="' + escapeHtml(m.cover_url) + '"' : ''
            if (m.asset_type === 'image') {
                mediaHtml = '<img class="w-full h-[160px] object-cover" src="' + escapeHtml(m.file_url) + '" alt="">'
            } else if (m.asset_type === 'audio') {
                mediaHtml = '<div class="flex items-center justify-center h-[160px] bg-black"><audio class="w-full px-4" controls preload="metadata" src="' + escapeHtml(m.file_url) + '"></audio></div>'
            } else {
                mediaHtml = '<video class="w-full h-[160px] object-cover" controls preload="metadata"' + poster + ' src="' + escapeHtml(m.file_url) + '"></video>'
            }

            var actions = '<div class="absolute top-2 right-2 flex gap-2">' +
                '<button class="edit-media-btn bg-gray-700 hover:bg-gray-600 text-white p-2 rounded-full" data-id="' + m.id + '" title="Edit">' +
                '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>' +
                '</button>' +
                '<button class="delete-media-btn bg-red-600 hover:bg-red-700 text-white p-2 rounded-full" data-id="' + m.id + '" title="Delete">' +
                '<svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>' +
                '</button>' +
                '</div>'

            var badges = '<div class="flex items-center gap-1.5">' +
                (parseInt(m.is_adult) === 1 ? '<span class="px-2 py-0.5 rounded bg-red-600 text-white text-[11px] font-bold">18+</span>' : '') +
                '<span class="px-2 py-0.5 rounded bg-[#1d1d1d] text-[#A1A1A1] text-[11px] uppercase">' + escapeHtml(m.asset_type || '') + '</span>' +
                '</div>'

            var tagArr = splitList(m.tags)
            var tagsHtml = tagArr.length
                ? '<div class="flex flex-wrap gap-1 mt-1.5">' + tagArr.slice(0, 6).map(function (t) {
                    return '<span class="px-2 py-0.5 rounded-full bg-[#1d1d1d] text-[#c9a6f7] text-[11px]">' + escapeHtml(t) + '</span>'
                }).join('') + '</div>'
                : ''
            var desc = m.description ? '<div class="text-[#A1A1A1] text-xs mt-1.5 line-clamp-2">' + escapeHtml(m.description) + '</div>' : ''
            var keywords = m.keywords ? '<div class="text-[#6b6b6b] text-[11px] truncate mt-1">触发词：' + escapeHtml(m.keywords) + '</div>' : ''

            var li = '<li class="flex flex-col">' +
                '<div class="relative rounded-lg overflow-hidden bg-gray-800 group">' +
                mediaHtml +
                actions +
                '<div class="p-3 bg-gray-900">' +
                '<div class="flex items-center justify-between gap-2 mb-1">' +
                '<span class="text-white text-sm font-semibold truncate">' + escapeHtml(m.title || 'Untitled') + '</span>' +
                badges +
                '</div>' +
                desc +
                tagsHtml +
                keywords +
                '</div>' +
                '</div>' +
                '</li>'
            $list.append(li)
        })
    }

    function openEdit(id) {
        var m = mediaMap[id]
        if (!m) return
        $('#edit-id-input').val(m.id)
        $('#edit-file-input').val('')
        $('#edit-title-input').val(m.title || '')
        $('#edit-type-select').val(m.asset_type || 'video')
        $('#edit-scene-select').val(m.scene_type || 'public')
        $('#edit-keywords-input').val(m.keywords || '')
        $('#edit-weight-input').val(m.weight || 1)
        $('#edit-duration-input').val(m.duration_ms || '')
        $('#edit-desc-input').val(m.description || '')
        $('#edit-tags-input').val(m.tags || '')
        $('#edit-style-select').val(m.style || '')
        $('#edit-mood-select').val(m.mood || '')
        $('#edit-resolution-select').val(m.resolution || '')
        $('#edit-adult-select').val(parseInt(m.is_adult) === 1 ? '1' : '0')
        $('#edit-cover-input').val('')
        editCoverUrl = m.cover_url || ''
        $('#edit-cover-preview').html(
            editCoverUrl
                ? '<img class="w-full h-full object-cover" src="' + escapeHtml(editCoverUrl) + '" alt="">'
                : '<span class="text-[11px] text-gray-500">点击上传</span>'
        )
        onEditDescInput()
        loadPersonas($('#edit-persona-select'), m.persona)
        showModal('edit-modal', true)
    }

    function saveEdit() {
        var id = $('#edit-id-input').val()
        if (!id) return

        var description = ($('#edit-desc-input').val() || '').trim()
        if (description.length < 20) {
            layer.msg('素材描述不能少于20字（当前 ' + description.length + ' 字）')
            return
        }
        var tags = splitList($('#edit-tags-input').val())
        if (!tags.length) {
            layer.msg('请至少填写一个标签')
            return
        }
        if (tags.length > 10) {
            layer.msg('标签最多填写10个')
            return
        }
        var style = $('#edit-style-select').val() || ''
        if (!style) {
            layer.msg('请选择素材风格')
            return
        }

        var formData = new FormData()
        formData.append('id', id)

        var fileInput = $('#edit-file-input')[0]
        var file = fileInput && fileInput.files[0]
        if (file) formData.append('file', file)

        var coverInput = $('#edit-cover-input')[0]
        var coverFile = coverInput && coverInput.files[0]
        if (coverFile) formData.append('cover_file', coverFile)

        formData.append('title', ($('#edit-title-input').val() || '').trim())
        formData.append('asset_type', $('#edit-type-select').val() || 'video')
        formData.append('scene_type', $('#edit-scene-select').val() || 'public')
        formData.append('persona_id', $('#edit-persona-select').val() || '')
        formData.append('keywords', ($('#edit-keywords-input').val() || '').trim())
        formData.append('weight', $('#edit-weight-input').val() || '1')
        formData.append('description', description)
        formData.append('tags', tags.join(','))
        formData.append('style', style)
        formData.append('mood', $('#edit-mood-select').val() || '')
        formData.append('resolution', $('#edit-resolution-select').val() || '')
        formData.append('is_adult', $('#edit-adult-select').val() || '0')
        if (!coverFile) formData.append('cover_url', editCoverUrl)

        var duration = parseInt($('#edit-duration-input').val() || '0', 10)
        if (duration >= 0) formData.append('duration_ms', String(duration))

        var submitBtn = $('#edit-submit-btn')
        submitBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed').text('Saving...')

        $.ajax({
            url: base + '/mediaAssetEdit',
            method: 'POST',
            headers: authHeaders(),
            processData: false,
            contentType: false,
            data: formData
        }).done(function (res) {
            if (res && res.code === '00000') {
                layer.msg('更新成功')
                showModal('edit-modal', false)
                loadMediaList()
            } else {
                layer.msg((res && res.msg) || '更新失败')
            }
        }).fail(function () {
            layer.msg('更新失败，请稍后重试')
        }).always(function () {
            submitBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed').text('Save')
        })
    }

    function deleteMedia(id) {
        layer.confirm('确定删除该素材吗？', { btn: ['删除', '取消'] }, function (index) {
            var formData = new FormData()
            formData.append('id', id)

            $.ajax({
                url: base + '/mediaAssetDelete',
                method: 'POST',
                headers: authHeaders(),
                processData: false,
                contentType: false,
                data: formData
            }).done(function (res) {
                if (res && res.code === '00000') {
                    layer.msg('删除成功')
                    loadMediaList()
                } else {
                    layer.msg((res && res.msg) || '删除失败')
                }
            }).fail(function () {
                layer.msg('删除失败，请稍后重试')
            })

            layer.close(index)
        })
    }

    $(function () {
        $('#upload-media-btn, #upload-media-btn-mobile').on('click', function () {
            showModal('upload-modal', true)
            loadPersonas($('#media-persona-select'))
        })

        $('#close-upload-modal, #cancel-upload-btn').on('click', function () {
            showModal('upload-modal', false)
            resetForm()
        })

        $('#upload-modal').on('click', function (e) {
            if (e.target && e.target.id === 'upload-modal') {
                showModal('upload-modal', false)
                resetForm()
            }
        })

        $('#close-edit-modal, #cancel-edit-btn').on('click', function () {
            showModal('edit-modal', false)
        })

        $('#edit-modal').on('click', function (e) {
            if (e.target && e.target.id === 'edit-modal') {
                showModal('edit-modal', false)
            }
        })

        $('#upload-media-form').on('submit', function (e) {
            e.preventDefault()
            if (!token) {
                layer.msg('请先登录后再上传素材')
                return
            }
            uploadMedia()
        })

        $('#edit-media-form').on('submit', function (e) {
            e.preventDefault()
            if (!token) {
                layer.msg('请先登录后再操作')
                return
            }
            saveEdit()
        })

        $(document).on('change', '#edit-cover-input', function () {
            var file = this.files && this.files[0]
            if (!file) return
            uploadImage(file, $('#edit-cover-preview'), function (url) { editCoverUrl = url })
        })

        $('#media-list').on('click', '.edit-media-btn', function (e) {
            e.stopPropagation()
            openEdit($(this).data('id'))
        })

        $('#media-list').on('click', '.delete-media-btn', function (e) {
            e.stopPropagation()
            deleteMedia($(this).data('id'))
        })

        loadMediaList()
    })
})()
