(()=>{
    var ws = null
    var isConnected = false
    var currentCharacter = null
    var base = 'http://204.12.203.26:18550'
    
    // 暴露连接状态为全局变量
    window.isConnected = isConnected;
    
            // 初始化WebSocket连接
            function initWebSocket() {
                const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
                const wsUrl = `${base}/ws`;
                
                ws = new WebSocket(wsUrl);
                
                ws.onopen = function() {
                    console.log('WebSocket连接已建立');
                    isConnected = true;
                    window.isConnected = true; // 更新全局变量
                    // switchCharacter(document.getElementById('characterName').value)
                    switchCharacter("欧美美女2")
                    updateStatus('connected', 'Connected');
                    enableInput();
                };
                
                ws.onmessage = function(event) {
                    const data = JSON.parse(event.data);
                    handleWebSocketMessage(data);
                };
                
                ws.onclose = function() {
                    console.log('WebSocket连接已关闭');
                    isConnected = false;
                    window.isConnected = false; // 更新全局变量
                    updateStatus('disconnected', 'Disconnected');
                    disableInput();
                    // 尝试重新连接
                    setTimeout(initWebSocket, 3000);
                };
                
                ws.onerror = function(error) {
                    console.error('WebSocket错误:', error);
                    updateStatus('disconnected', 'Connection error');
                };
            }

            // 切换角色
            function switchCharacter(character) {
                if (!isConnected) return;
                
                currentCharacter = character;
                
                // 发送角色切换消息
                const message = {
                    type: 'switch_character',
                    character: character
                };
                
                ws.send(JSON.stringify(message));
            }
    
            // 更新状态显示
            function updateStatus(type, message) {
                const statusEl = document.getElementById('status');
                statusEl.className = `status ${type}`;
                statusEl.textContent = message;
            }
    
            // 启用输入
            function enableInput() {
                document.getElementById('userInput').disabled = false;
                document.getElementById('sendBtn').disabled = false;
            }
    
            // 禁用输入
            function disableInput() {
                document.getElementById('userInput').disabled = true;
                document.getElementById('sendBtn').disabled = true;
            }
    
            // 处理WebSocket消息
            function handleWebSocketMessage(data) {
                console.log('收到消息:', data);
                
                if (data.type === 'status') {
                    // 显示状态消息
                    showNotification(data.msg);
                } else if (data.type === 'response') {
                    // 显示识别结果
                    displayResult(data);
                    // 播放视频
                    playVideoSmooth(data.video);
                    // 添加AI回复到聊天历史
                    if (window.addChatMessage) {
                        window.addChatMessage(data.reply || 'Receive a reply', false);
                    }
                } else if (data.type === 'error') {
                    // 显示错误消息
                    showNotification(data.msg);
                    // 添加错误消息到聊天历史
                    if (window.addChatMessage) {
                        window.addChatMessage('Error: ' + data.msg, false);
                    }
                }
            }
    
            // 显示通知
            function showNotification(message) {
                // 简单的通知显示
                const notification = document.createElement('div');
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #667eea;
                    color: white;
                    padding: 15px 20px;
                    border-radius: 10px;
                    z-index: 1000;
                    animation: slideIn 0.3s ease;
                `;
                notification.textContent = message;
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }
    
            // 显示识别结果
            function displayResult(data) {
                console.log(data)
            }
    
            // 双video无缝切换
            let isTransitioning = false; // 防止重复执行标志
            function playVideoSmooth(videoPath) {
                if (isTransitioning) {
                    console.log('正在切换中，直接返回1')
                    return;
                }
                
                const videoA = document.getElementById('videoA');
                const videoB = document.getElementById('videoB');
                const videoPlayer = document.getElementById('videoPlayer');
                let showVideo, hideVideo;
                let currentVideo = document.getElementById('currentVideo').value;
                if (currentVideo === 'A') {
                    showVideo = videoB;
                    hideVideo = videoA;
                } else {
                    showVideo = videoA;
                    hideVideo = videoB;
                }
                if (videoPath === 'default.mp4') {
                    videoPath = document.getElementById('defaultVideo').value;
                }
                
                isTransitioning = true; // 开始切换
                
                // 清除之前的事件监听器
                showVideo.onloadeddata = null;
                showVideo.onerror = null;
                
                // --- Canvas兜底帧 ---
                // 先移除旧canvas（如果有）
                const oldCanvas = document.getElementById('videoTransitionCanvas');
                if (oldCanvas) oldCanvas.remove();
                // 只在hideVideo有内容时绘制canvas
                if (hideVideo.readyState >= 2) { // HAVE_CURRENT_DATA
                    const canvas = document.createElement('canvas');
                    canvas.id = 'videoTransitionCanvas';
                    canvas.width = hideVideo.videoWidth || hideVideo.clientWidth;
                    canvas.height = hideVideo.videoHeight || hideVideo.clientHeight;
                    canvas.style.position = 'absolute';
                    canvas.style.top = 0;
                    canvas.style.left = 0;
                    canvas.style.width = '100%';
                    canvas.style.height = '100%';
                    canvas.style.zIndex = 10;
                    canvas.style.transition = 'opacity 0.5s';
                    canvas.style.pointerEvents = 'none';
                    videoPlayer.appendChild(canvas);
                    try {
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(hideVideo, 0, 0, canvas.width, canvas.height);
                    } catch (e) {
                        console.warn('canvas绘制失败', e);
                    }
                }
                // --- End Canvas兜底帧 ---
                
                showVideo.src = videoPath;
                showVideo.load();
                
                // 使用 loadeddata 事件替代 canplay，只在视频数据首次加载完成时触发
                showVideo.onloadeddata = function () {
                    if (!isTransitioning) {
                        console.log('正在切换中，直接返回2')
                        return;
                    }
                    
                    console.log('视频数据加载完成，开始切换');
                    
                    showVideo.style.opacity = 0;
                    showVideo.style.display = 'block';
                    showVideo.style.transition = 'opacity 0.5s';
                    hideVideo.style.transition = 'opacity 0.5s';
                    showVideo.style.opacity = 1;
                    hideVideo.style.opacity = 0;
                    showVideo.play().catch(() => {});
                    
                    // 淡出并移除canvas
                    const canvas = document.getElementById('videoTransitionCanvas');
                    if (canvas) {
                        canvas.style.opacity = 0;
                        setTimeout(() => {
                            canvas.remove();
                        }, 500);
                    }
                    
                    setTimeout(() => {
                        hideVideo.style.display = 'none';
                        document.getElementById('currentVideo').value = currentVideo === 'A' ? 'B' : 'A';
                        isTransitioning = false; // 切换完成
                        console.log('切换完成，currentVideo:', document.getElementById('currentVideo').value);
                    }, 500);
                };
                
                showVideo.onerror = function () {
                    isTransitioning = false; // 出错时也要重置标志
                    showVideo.style.display = 'none';
                    hideVideo.style.opacity = 1;
                    hideVideo.style.display = 'block';
                    // 移除canvas
                    const canvas = document.getElementById('videoTransitionCanvas');
                    if (canvas) canvas.remove();
                    console.log('视频加载出错');
                };
            }
    
            // 发送消息
            function sendMessage(text) {
                if (!isConnected || !text.trim() || !currentCharacter) return;
                
                const message = {
                    type: 'text',
                    text: text.trim()
                };
                
                ws.send(JSON.stringify(message));
                
                // 注意：不清空输入框，让Game.html中的代码处理
            }

            // 全局聊天消息添加函数，供其他脚本调用
            window.addChatMessage = function(message, isUser = true) {
                const chatHistory = document.getElementById('chat-history');
                if (!chatHistory) return;
                
                const messageDiv = document.createElement('div');
                messageDiv.className = `mb-2 ${isUser ? 'text-right' : 'text-left'}`;
                
                const messageBubble = document.createElement('div');
                messageBubble.className = `inline-block px-3 py-2 rounded-lg text-sm max-w-xs break-words ${
                    isUser 
                        ? 'bg-gradient-to-r from-pink-500 to-purple-600 text-white' 
                        : 'bg-gray-700 text-white'
                }`;
                messageBubble.textContent = message;
                
                messageDiv.appendChild(messageBubble);
                chatHistory.appendChild(messageDiv);
                
                // 滚动到底部
                chatHistory.scrollTop = chatHistory.scrollHeight;
            };

            // 暴露sendMessage函数为全局函数
            window.sendWebSocketMessage = sendMessage;

            // 暴露playVideoSmooth函数为全局函数
            window.playVideoSmooth = playVideoSmooth;

            if(document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    // 初始化WebSocket
                    initWebSocket();
                    
                    // 不在这里绑定事件监听器，让Game.html中的代码处理
                    // 初始禁用输入
                    disableInput();
                })
            } else {
                initWebSocket()
            }
})()