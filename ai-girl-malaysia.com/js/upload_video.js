(()=>{
    var base = 'https://api.kisss.ai'
    var token = localStorage.getItem('live_access_token')
    var giftList = []

    var id = new URLSearchParams(window.location.search).get('id')
    var archive_id = new URLSearchParams(window.location.search).get('archive_id')
    
    // 页面加载完成后初始化
    setTimeout(() => {
        initUploadVideo()
        loadGiftList()
        loadVideoList(id, archive_id)
    }, 100);

    function initUploadVideo() {
        // 绑定上传按钮事件
        $('#upload-video-btn, #upload-video-btn-mobile').on('click', function() {
            $('#upload-modal').removeClass('hidden').addClass('flex')
        })

        // 关闭上传模态框
        $('#close-upload-modal, #cancel-upload-btn').on('click', function() {
            $('#upload-modal').addClass('hidden').removeClass('flex')
            resetUploadForm()
        })

        // 点击模态框背景关闭
        $('#upload-modal').on('click', function(e) {
            if ($(e.target).attr('id') === 'upload-modal') {
                $(this).addClass('hidden').removeClass('flex')
                resetUploadForm()
            }
        })

        // 处理上传表单提交
        $('#upload-video-form').on('submit', function(e) {
            e.preventDefault()
            uploadVideo()
        })

        // 绑定 Is Sensitive 复选框事件
        $('#video-is-sensitive-input').on('change', function() {
            if ($(this).is(':checked')) {
                $('#gift-select-container').removeClass('hidden')
                // 如果礼物列表还未加载，等待加载完成
                if (giftList.length === 0) {
                    loadGiftList()
                } else {
                    renderGiftSelect(giftList)
                }
            } else {
                $('#gift-select-container').addClass('hidden')
                $('#gift-select-input').val('')
            }
        })
    }

    function resetUploadForm() {
        $('#video-file-input').val('')
        $('#video-intimacy-input').val('50')
        $('#video-keywords-input').val('')
        $('#video-is-sensitive-input').prop('checked', false)
        $('#video-is-default-input').prop('checked', false)
        $('#gift-select-container').addClass('hidden')
        $('#gift-select-input').val('')
        $('#upload-progress').addClass('hidden')
        $('#upload-progress-bar').css('width', '0%')
        $('#upload-progress-text').text('Uploading... 0%')
    }

    function uploadVideo() {
        var fileInput = $('#video-file-input')[0]
        var file = fileInput.files[0]

        if (!file) {
            layer.msg('Please select a video file')
            return
        }

        // 检查文件大小（例如限制为 10MB）
        var maxSize = 10 * 1024 * 1024 // 10MB
        if (file.size > maxSize) {
            layer.msg('Video file is too large. Maximum size is 10MB')
            return
        }

        // 检查文件类型
        if (!file.type.startsWith('video/')) {
            layer.msg('Please select a valid video file')
            return
        }

        // 验证 Intimacy 值
        var intimacy = parseInt($('#video-intimacy-input').val())
        if (isNaN(intimacy) || intimacy < 1 || intimacy > 100) {
            layer.msg('Intimacy must be a number between 1 and 100')
            return
        }

        var formData = new FormData()
        formData.append('file', file)
        formData.append('intimacy_num', intimacy)
        
        var keywords = $('#video-keywords-input').val().trim()
        if (keywords) {
            formData.append('action_keyword', keywords)
        }
        
        var isSensitive = $('#video-is-sensitive-input').is(':checked')
        formData.append('is_sensitive', isSensitive ? '1' : '0')
        
        var isDefault = $('#video-is-default-input').is(':checked')
        formData.append('is_default', isDefault ? '1' : '0')

        // 如果 Is Sensitive 被选中，需要选择礼物
        var isSensitive = $('#video-is-sensitive-input').is(':checked')
        if (isSensitive) {
            var selectedGiftId = $('#gift-select-input').val()
            if (!selectedGiftId) {
                layer.msg('Please select a gift for sensitive content')
                return
            }
            formData.append('gift_id', selectedGiftId)
        }

        formData.append('id', id)
        formData.append('cms_archive_id', archive_id)

        var headers = {}
        if (token) {
            headers = {
                token: token
            }
        }

        // 显示上传进度
        $('#upload-progress').removeClass('hidden')
        $('#upload-progress-bar').css('width', '0%')
        $('#upload-progress-text').text('Uploading... 0%')

        // 禁用提交按钮
        var submitBtn = $('#upload-video-form button[type="submit"]')
        submitBtn.prop('disabled', true).addClass('opacity-50 cursor-not-allowed')

        const settings = {
            "async": true,
            "crossDomain": true,
            "url": base + "/api/user/upload_video",
            "method": "POST",
            "headers": headers,
            "processData": false,
            "contentType": false,
            "mimeType": "multipart/form-data",
            "data": formData,
            "xhr": function() {
                var xhr = new window.XMLHttpRequest()
                // 上传进度
                xhr.upload.addEventListener("progress", function(evt) {
                    if (evt.lengthComputable) {
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100)
                        $('#upload-progress-bar').css('width', percentComplete + '%')
                        $('#upload-progress-text').text('Uploading... ' + percentComplete + '%')
                    }
                }, false)
                return xhr
            }
        }

        $.ajax(settings).done(function (response) {
            if (typeof(response) === 'string') {
                response = JSON.parse(response)
            }
            var res = response
            if (res.code === 1) {
                layer.msg('Video uploaded successfully')
                $('#upload-modal').addClass('hidden').removeClass('flex')
                resetUploadForm()
                // 重新加载视频列表
                loadVideoList(id, archive_id)
            } else {
                layer.msg(res.msg || 'Upload failed')
            }
        }).catch(error => {
            console.error(error)
            layer.msg('Upload failed. Please try again.')
        }).always(function() {
            // 恢复提交按钮
            submitBtn.prop('disabled', false).removeClass('opacity-50 cursor-not-allowed')
            $('#upload-progress').addClass('hidden')
        })
    }

    function loadVideoList(id, archive_id) {
        var headers = {}
        if (token) {
            headers = {
                token: token
            }
        }

        const settings = {
            "async": true,
            "crossDomain": true,
            "url": base + "/api/user/video_list?id=" + id + "&archive_id=" + archive_id,
            "method": "POST",
            "headers": headers,
            "processData": false,
            "contentType": false,
            "mimeType": "multipart/form-data",
        }

        $.ajax(settings).done(function (response) {
            if (typeof(response) === 'string') {
                response = JSON.parse(response)
            }
            var res = response
            if (res.code === 1) {
                renderVideoList(res.data || [])
            } else {
                layer.msg(res.msg || 'Failed to load video list')
                renderVideoList([])
            }
        }).catch(error => {
            console.error(error)
            layer.msg('Failed to load video list')
            renderVideoList([])
        })
    }

    function renderVideoList(videos) {
        var $videoList = $('#video-list')
        var template = $('#video-item-template').html()
        
        $videoList.empty()

        if (!videos || videos.length === 0) {
            $('#empty-state').removeClass('hidden')
            return
        }

        $('#empty-state').addClass('hidden')

        if (!template) {
            console.error('Video item template not found')
            return
        }

        videos.forEach(function(video) {
            // 处理 is_sensitive 和 is_default 的显示
            var isSensitive = video.is_sensitive === 1 || video.is_sensitive === true || video.is_sensitive === '1'
            var isDefault = video.is_default === 1 || video.is_default === true || video.is_default === '1'
            
            var sensitiveBadge = isSensitive ? '<span class="px-2 py-1 bg-red-600 text-white text-xs rounded">Sensitive</span>' : ''
            var defaultBadge = isDefault ? '<span class="px-2 py-1 bg-[#E75275] text-white text-xs rounded">Default</span>' : ''
            
            // 处理 keywords 的显示
            var keywordsDisplay = ''
            if (video.action_keyword && video.action_keyword.trim()) {
                keywordsDisplay = '<p class="text-gray-400 text-xs mb-2"><span class="text-gray-500">Keywords:</span> ' + (video.action_keyword || '') + '</p>'
            }

            var giftDisplay = ''
            if (video.gift_id && video.gift_id !== 0 && giftList && giftList.length > 0) {
                var gift = giftList.find(function(g) {
                    return g.id == video.gift_id || g.id === video.gift_id
                })
                if (gift) {
                    giftDisplay = '<p class="text-gray-400 text-xs mb-2"><span class="text-gray-500">Gift:</span> ' + (gift.name || 'Unknown') + ' ($' + (gift.price || 0) + ')' + '</p>'
                } else {
                    // 如果找不到礼物，显示礼物ID
                    giftDisplay = '<p class="text-gray-400 text-xs mb-2"><span class="text-gray-500">Gift ID:</span> ' + video.gift_id + '</p>'
                }
            }

            var rendered = template
                .replace(/\{\{id\}\}/g, video.id || '')
                .replace(/\{\{video_url\}\}/g, video.video_url || video.url || '')
                .replace(/\{\{intimacy\}\}/g, video.intimacy || video.intimacy_value || '50')
                .replace(/\{\{is_sensitive_badge\}\}/g, sensitiveBadge)
                .replace(/\{\{is_default_badge\}\}/g, defaultBadge)
                .replace(/\{\{keywords_display\}\}/g, keywordsDisplay)
                .replace(/\{\{gift\}\}/g, giftDisplay)
                .replace(/\{\{upload_date\}\}/g, formatDate(video.upload_date || video.created_at || video.uploadtime*1000))
                .replace(/\{\{file_size\}\}/g, formatFileSize(video.file_size || video.size || 0))

            $videoList.append(rendered)
        })

        // 绑定删除按钮事件
        $('.delete-video-btn').on('click', function(e) {
            e.stopPropagation()
            var videoId = $(this).data('video-id')
            if (videoId) {
                deleteVideo(videoId)
            }
        })
    }

    function deleteVideo(videoId) {
        layer.confirm('Are you sure you want to delete this video?', {
            btn: ['Delete', 'Cancel']
        }, function(index) {
            var headers = {}
            if (token) {
                headers = {
                    token: token
                }
            }

            var formData = new FormData()
            formData.append('video_id', videoId)

            const settings = {
                "async": true,
                "crossDomain": true,
                "url": base + "/api/user/delete_video",
                "method": "POST",
                "headers": headers,
                "processData": false,
                "contentType": false,
                "mimeType": "multipart/form-data",
                "data": formData
            }

            $.ajax(settings).done(function (response) {
                if (typeof(response) === 'string') {
                    response = JSON.parse(response)
                }
                var res = response
                if (res.code === 1) {
                    layer.msg('Video deleted successfully')
                    loadVideoList(id, archive_id) // 重新加载列表
                } else {
                    layer.msg(res.msg || 'Failed to delete video')
                }
            }).catch(error => {
                console.error(error)
                layer.msg('Failed to delete video')
            })

            layer.close(index)
        })
    }

    function formatDate(dateString) {
        if (!dateString) return 'Unknown'
        try {
            var date = new Date(dateString)
            var now = new Date()
            var diff = now - date
            var seconds = Math.floor(diff / 1000)
            var minutes = Math.floor(seconds / 60)
            var hours = Math.floor(minutes / 60)
            var days = Math.floor(hours / 24)

            if (days > 7) {
                return date.toLocaleDateString()
            } else if (days > 0) {
                return days + ' day' + (days > 1 ? 's' : '') + ' ago'
            } else if (hours > 0) {
                return hours + ' hour' + (hours > 1 ? 's' : '') + ' ago'
            } else if (minutes > 0) {
                return minutes + ' minute' + (minutes > 1 ? 's' : '') + ' ago'
            } else {
                return 'Just now'
            }
        } catch (e) {
            return dateString
        }
    }

    function formatFileSize(bytes) {
        if (!bytes || bytes === 0) return '0 B'
        var k = 1024
        var sizes = ['B', 'KB', 'MB', 'GB']
        var i = Math.floor(Math.log(bytes) / Math.log(k))
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
    }

    // 加载礼物列表
    function loadGiftList() {
        var headers = {}
        if (token) {
            headers = {
                token: token
            }
        }

        const settings = {
            "async": true,
            "crossDomain": true,
            "url": base + "/api/common/giftlist",
            "method": "GET",
            "headers": headers,
            "processData": false,
            "contentType": false,
        }

        $.ajax(settings).done(function (response) {
            if (typeof(response) === 'string') {
                response = JSON.parse(response)
            }
            var res = response
            if (res.code === 1 && res.data) {
                giftList = res.data
                // 如果 Is Sensitive 已经被选中，更新下拉框
                if ($('#video-is-sensitive-input').is(':checked')) {
                    renderGiftSelect(giftList)
                }
            } else {
                console.error('加载礼物列表失败:', res.msg)
            }
        }).catch(error => {
            console.error('加载礼物列表错误:', error)
        })
    }

    // 渲染礼物下拉选择框
    function renderGiftSelect(gifts) {
        var $select = $('#gift-select-input')
        $select.empty()
        
        // 添加默认选项
        $select.append('<option value="">Select a gift...</option>')
        
        if (gifts && gifts.length > 0) {
            gifts.forEach(function(gift) {
                var option = $('<option></option>')
                option.attr('value', gift.id)
                option.text(gift.name + (gift.price ? ' ($' + gift.price + ')' : ''))
                $select.append(option)
            })
        } else {
            $select.append('<option value="">No gifts available</option>')
        }
    }
})()
