(()=>{
    var ws = null
    var isConnected = false
    var currentCharacter = null
    var base = 'https://websocket.trd.lat'
    var pendingVideoData = null // 存储待处理的视频数据
    var currentClientId = null // 存储当前的clientId
    
    // intimacy points 定时器
    var intimacyPointsInterval = null
    var intimacyPointsIntervalMs = 5000 // 5秒获取一次
    
    // 重连相关变量
    var reconnectAttempts = 0
    var maxReconnectAttempts = 10
    var reconnectInterval = null
    var isReconnecting = false
    
    // 心跳检测相关变量
    var heartbeatInterval = null
    var heartbeatTimeout = null
    var lastHeartbeatTime = 0
    var heartbeatIntervalMs = 30000 // 30秒发送一次心跳
    var heartbeatTimeoutMs = 10000  // 10秒内没收到响应则认为连接断开
    
    // 暴露连接状态为全局变量
    window.isConnected = isConnected;
    window.isReconnecting = isReconnecting;
    
    // 检查WebSocket连接状态
    function checkConnection() {
        if (ws && ws.readyState === WebSocket.OPEN) {
            return true;
        }
        return false;
    }
    
    // 开始心跳检测
    function startHeartbeat() {
        // 清除之前的心跳定时器
        stopHeartbeat();
        
        heartbeatInterval = setInterval(() => {
            if (isConnected && ws && ws.readyState === WebSocket.OPEN) {
                try {
                    // 发送心跳消息
                    const heartbeatMsg = {
                        type: 'heartbeat',
                        timestamp: Date.now()
                    };
                    ws.send(JSON.stringify(heartbeatMsg));
                    lastHeartbeatTime = Date.now();
                    
                    // 设置超时检测
                    heartbeatTimeout = setTimeout(() => {
                        console.log('心跳超时，连接可能断开');
                        if (window.addChatMessage) {
                            window.addChatMessage('⚠️ 连接超时，正在重连...', false);
                        }
                        // 强制重连
                        ws.close();
                    }, heartbeatTimeoutMs);
                } catch (error) {
                    console.error('发送心跳失败:', error);
                }
            }
        }, heartbeatIntervalMs);
    }
    
    // 停止心跳检测
    function stopHeartbeat() {
        if (heartbeatInterval) {
            clearInterval(heartbeatInterval);
            heartbeatInterval = null;
        }
        if (heartbeatTimeout) {
            clearTimeout(heartbeatTimeout);
            heartbeatTimeout = null;
        }
    }

    function stopIntimacyPointsSubscription() {
        // 清除定时器
        if (intimacyPointsInterval) {
            clearInterval(intimacyPointsInterval);
            intimacyPointsInterval = null;
            console.log('停止定时获取intimacy points');
        }
        
        // 可选：发送停止订阅消息给后端
        if (currentClientId && isConnected && ws && ws.readyState === WebSocket.OPEN) {
            const message = {
                type: 'stop_intimacy_points_subscription',
                client_id: currentClientId
            };
            
            try {
                ws.send(JSON.stringify(message));
            } catch (error) {
                console.error('发送停止订阅消息失败:', error);
            }
        }
    }

    // 开始定时获取用户的intimacy points
    function startIntimacyPointsSubscription() {
        // 清除之前的定时器
        stopIntimacyPointsSubscription();
        
        if (!currentClientId) {
            console.warn('clientId未设置，无法订阅intimacy points');
            return;
        }
        
        // 立即获取一次
        getIntimacyPoints();
        
        // 设置定时器，每5秒获取一次
        intimacyPointsInterval = setInterval(() => {
            if (isConnected && ws && ws.readyState === WebSocket.OPEN) {
                getIntimacyPoints();
            }
        }, intimacyPointsIntervalMs);
        
        console.log('开始定时获取intimacy points，间隔:', intimacyPointsIntervalMs, 'ms');
    }
    
    // 获取intimacy points
    function getIntimacyPoints() {
        if (!currentClientId) {
            console.warn('clientId未设置，无法获取intimacy points');
            return;
        }
        
        const message = {
            type: 'get_character_intimacy_points',
            client_id: currentClientId
        };
        
        try {
            ws.send(JSON.stringify(message));
        } catch (error) {
            console.error('发送intimacy points请求失败:', error);
        }
    }
    // 手动重连函数
    function manualReconnect() {
        if (isReconnecting) {
            console.log('正在重连中，请稍候...');
            return;
        }
        
        console.log('手动重连...');
        reconnectAttempts = 0;
        isReconnecting = true;
        window.isReconnecting = true;
        
        // 清除之前的重连定时器
        if (reconnectInterval) {
            clearTimeout(reconnectInterval);
        }
        
        // 关闭现有连接
        if (ws) {
            ws.close();
        }
        
        // 立即尝试重连
        initWebSocket();
    }
    
    // 指数退避重连
    function scheduleReconnect() {
        if (reconnectAttempts >= maxReconnectAttempts) {
            console.log('达到最大重连次数，停止重连');
            updateStatus('disconnected', 'Connection failed - Max retries reached');
            isReconnecting = false;
            window.isReconnecting = false;
            return;
        }
        
        isReconnecting = true;
        window.isReconnecting = true;
        
        // 指数退避：1s, 2s, 4s, 8s, 16s...
        const delay = Math.min(1000 * Math.pow(2, reconnectAttempts), 30000);
        
        console.log(`第${reconnectAttempts + 1}次重连尝试，${delay/1000}秒后重试...`);
        updateStatus('reconnecting', `Reconnecting... (${reconnectAttempts + 1}/${maxReconnectAttempts})`);
        
        reconnectInterval = setTimeout(() => {
            reconnectAttempts++;
            initWebSocket();
        }, delay);
    }
    
    // 初始化WebSocket连接
    function initWebSocket() {
        const protocol = window.location.protocol === 'https:' ? 'wss:' : 'ws:';
        const userInfo = JSON.parse(localStorage.getItem('live_user_info'))
        const characterInfo = JSON.parse(localStorage.getItem('characterInfo'))
        const clientId = `browser_${userInfo.id}_${characterInfo.id}_${Date.now()}_${Math.random().toString(36).substr(2, 9)}`;
        currentClientId = clientId; // 保存clientId
        const wsUrl = `${base}/ws/${clientId}`;
        
        console.log('正在连接WebSocket:', wsUrl);
        updateStatus('connecting', 'Connecting...');
        
        ws = new WebSocket(wsUrl);
        
        ws.onopen = function() {
            console.log('WebSocket连接已建立');
            isConnected = true;
            isReconnecting = false;
            reconnectAttempts = 0;
            window.isConnected = true;
            window.isReconnecting = false;
            
            // 清除重连定时器
            if (reconnectInterval) {
                clearTimeout(reconnectInterval);
                reconnectInterval = null;
            }
            
            // 开始心跳检测
            startHeartbeat();

            // 订阅获取用户的intimacy points
            startIntimacyPointsSubscription();
            
            // 检查 characterName 是否为空，如果为空则等待
            const characterName = document.getElementById('characterName').value;
            if (characterName && characterName.trim()) {
                switchCharacter(characterName);
            } else {
                console.log('characterName 为空，等待获取角色信息...');
                // 等待角色信息加载完成后再切换角色
                waitForCharacterName();
            }
            
            updateStatus('connected', 'Connected');
            enableInput();
        };
        
        ws.onmessage = function(event) {
            const data = JSON.parse(event.data);
            handleWebSocketMessage(data);
        };
        
        ws.onclose = function(event) {
            console.log('WebSocket连接已关闭', event.code, event.reason);
            isConnected = false;
            window.isConnected = false;
            
            // 停止心跳检测
            stopHeartbeat();
            stopIntimacyPointsSubscription();
            // 如果不是手动关闭，则尝试重连
            if (event.code !== 1000) { // 1000是正常关闭
                scheduleReconnect();
            } else {
                isReconnecting = false;
                window.isReconnecting = false;
                updateStatus('disconnected', 'Disconnected');
            }
            
            disableInput();
        };
        
        ws.onerror = function(error) {
            console.error('WebSocket错误:', error);
            updateStatus('error', 'Connection error');
        };
    }

    // 等待角色名称加载完成
    function waitForCharacterName() {
        const checkInterval = setInterval(() => {
            const characterName = document.getElementById('characterName').value;
            if (characterName && characterName.trim()) {
                clearInterval(checkInterval);
                console.log('角色信息已加载，切换到角色:', characterName);
                switchCharacter(characterName);
            }
        }, 100); // 每100ms检查一次
        
        // 设置超时，避免无限等待
        setTimeout(() => {
            clearInterval(checkInterval);
            console.warn('等待角色信息超时');
        }, 10000); // 10秒超时
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
        if (statusEl) {
            statusEl.className = `status ${type}`;
            statusEl.textContent = message;
        }
        
        // 更新聊天历史中的连接状态
        // if (window.addChatMessage) {
        //     const statusMessages = {
        //         'connecting': '🔄 正在连接服务器...',
        //         'connected': '✅ 连接成功',
        //         'reconnecting': '🔄 正在重新连接...',
        //         'disconnected': '❌ 连接断开',
        //         'error': '⚠️ 连接错误'
        //     };
            
        //     if (statusMessages[type]) {
        //         window.addChatMessage(statusMessages[type], false);
        //     }
        // }
    }

    // 启用输入
    function enableInput() {
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');
        if (userInput) userInput.disabled = false;
        if (sendBtn) sendBtn.disabled = false;
    }

    // 禁用输入
    function disableInput() {
        const userInput = document.getElementById('userInput');
        const sendBtn = document.getElementById('sendBtn');
        if (userInput) userInput.disabled = true;
        if (sendBtn) sendBtn.disabled = true;
    }

    // 处理WebSocket消息
    function handleWebSocketMessage(data) {
        console.log('收到消息:', data);
        
        // 处理心跳响应
        if (data.type === 'heartbeat_response') {
            // 清除心跳超时定时器
            if (heartbeatTimeout) {
                clearTimeout(heartbeatTimeout);
                heartbeatTimeout = null;
            }
            return;
        }

        if(data.type === 'get_character_intimacy_points') {
            console.log('收到用户intimacy points:', data);
            document.getElementById('intimacy-points').textContent = data.user_intimacy_points;
            localStorage.setItem('intimacyPoints', data.user_intimacy_points);
            return
        }
        
        if (data.type === 'status') {
            // 显示状态消息
            showNotification(data.msg);
        } else if (data.type === 'response') {
            if(data.need_purchase_points) {
                document.getElementById('need-purchase-points-modal').style.display = 'block';
                return
            }
            if(data.need_confirm_purchase) {
                document.getElementById('need-confirm-purchase-modal').style.display = 'block';
                document.getElementById('confirm-purchase-btn').dataset.characterId = data.client_id.split('_')[2];
                document.getElementById('confirm-purchase-btn').dataset.points = data.intimacy_num;
                document.getElementById('confirm-purchase-btn').dataset.userId = data.client_id.split('_')[1];
                document.getElementById('confirm-purchase-btn').dataset.videoId = data.video_id;
                // 存储待处理的视频数据
                pendingVideoData = data;
                return
            }
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
        if (!isConnected || !text.trim() || !currentCharacter) {
            if (!isConnected) {
                console.log('WebSocket未连接，无法发送消息');
                if (window.addChatMessage) {
                    window.addChatMessage('⚠️ 连接断开，无法发送消息', false);
                }
            }
            return;
        }
        
        const message = {
            type: 'text',
            text: text.trim()
        };
        
        try {
            ws.send(JSON.stringify(message));
        } catch (error) {
            console.error('发送消息失败:', error);
            if (window.addChatMessage) {
                window.addChatMessage('❌ 发送消息失败', false);
            }
        }
        
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

    // 处理购买成功后的视频播放
    function handlePurchaseSuccess() {
        if (pendingVideoData) {
            console.log('购买成功，开始播放视频');
            // 显示识别结果
            displayResult(pendingVideoData);
            // 播放视频
            playVideoSmooth(pendingVideoData.video);
            // 添加AI回复到聊天历史
            if (window.addChatMessage) {
                window.addChatMessage(pendingVideoData.reply || 'Receive a reply', false);
            }
            // 清空待处理数据
            pendingVideoData = null;
        }
    }

    // 暴露函数为全局函数
    window.sendWebSocketMessage = sendMessage;
    window.playVideoSmooth = playVideoSmooth;
    window.switchCharacter = switchCharacter;
    window.checkConnection = checkConnection;
    window.manualReconnect = manualReconnect;
    window.startHeartbeat = startHeartbeat;
    window.stopHeartbeat = stopHeartbeat;
    window.handlePurchaseSuccess = handlePurchaseSuccess;
    window.startIntimacyPointsSubscription = startIntimacyPointsSubscription;
    window.stopIntimacyPointsSubscription = stopIntimacyPointsSubscription;
    window.getIntimacyPoints = getIntimacyPoints;

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