﻿﻿﻿<template>
  <div class="live-room-page">
    <video
      ref="videoEl"
      class="player"
      :poster="posterUrl"
      muted
      preload="auto"
      x5-video-player-type="h5-page"
      :x5-video-player-fullscreen="false"
      :webkit-playsinline="true"
      :x5-playsinline="true"
      :playsinline="true"
      :fullscreen="false"
      autoplay
    >
      <p>您的浏览器不支持 video 标签。</p>
    </video>

    <div class="overlay">
      <div class="top-bar">
        <div class="badges">
          <span class="pill hot">{{ badgeText }}</span>
          <span v-if="room?.room_no" class="pill subtle">{{ room.room_no }}</span>
          <span v-if="privilegeActive" class="pill privilege">{{ privilegeBadgeText }}</span>
        </div>
        <button class="close-btn" type="button" @click="goBack">返回</button>
      </div>
      <div v-if="privilegeToast" class="privilege-toast">{{ privilegeToast }}</div>

      <div v-if="loading" class="center-tip">房间加载中...</div>
      <div v-else-if="error" class="center-tip error">{{ error }}</div>

      <template v-else-if="room">
        <div class="playback-banner" :class="playModeBannerClass">
          <span class="mode-chip">{{ playModeTitle }}</span>
          <span class="mode-copy">{{ playModeDescription }}</span>
        </div>

        <div class="right-panel">
          <div class="metric">
            <span>在线</span>
            <strong>{{ onlineText }}</strong>
          </div>
          <div class="metric">
            <span>点赞</span>
            <strong>{{ likeText }}</strong>
          </div>
          <div class="metric">
            <span>模式</span>
            <strong>{{ modeText }}</strong>
          </div>
        </div>

        <div class="bottom-panel">
          <div class="comments">
            <div class="comment notice">欢迎来到 {{ room.title }}</div>
            <div class="comment ws-status" :class="`is-${wsState}`">{{ wsStatusText }}</div>
            <div
              v-for="item in chatMessages"
              :key="item.id"
              class="comment"
              :class="{ notice: item.kind !== 'chat', gift: item.kind === 'gift' }"
            >
              <template v-if="item.kind === 'chat'">
                <span class="comment-author">{{ item.nickname }}:</span>
                <span>{{ item.content }}</span>
              </template>
              <template v-else-if="item.kind === 'gift'">
                <span class="comment-author">{{ item.nickname }}</span>
                <span>{{ item.content }}</span>
              </template>
              <template v-else>{{ item.content }}</template>
            </div>
          </div>

          <div v-if="giftList.length > 0" class="gift-row">
            <button
              v-for="gift in giftList"
              :key="gift.gift_id"
              class="gift-chip"
              type="button"
              :class="{ active: sendingGiftId === gift.gift_id }"
              :disabled="!canSendGift || sendingGiftId !== null"
              @click="sendGift(gift)"
            >
              <span class="gift-name">{{ gift.name }}</span>
              <span class="gift-price">{{ gift.price }}{{ giftCurrencyName }}</span>
            </button>
          </div>

          <div class="room-meta">
            <div class="meta-row">
              <img class="avatar" :src="avatarUrl" alt="" />
              <div class="host-name">{{ personaName }}</div>
              <input
                v-model.trim="chatDraft"
                class="chat-input"
                :class="{ disabled: !canSendChat }"
                :disabled="!canSendChat"
                :placeholder="chatPlaceholder"
                maxlength="80"
                @keydown.enter.prevent="sendChat"
              />
              <button class="send-btn" type="button" :disabled="!canSendChat || sendingChat" @click="sendChat">
                {{ sendingChat ? '发送中' : '发送' }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { _formatNumber, _notice } from '@/utils'
import { liveRoomDetail, type LiveGiftInfo, type LiveRoom } from '@/api/live'
import { createLivePlaybackController, type LivePlaybackMode } from '@/utils/livePlayer'
import { useBaseStore } from '@/store/pinia'
import { getAccessToken } from '@/utils/auth'

type WsState = 'idle' | 'connecting' | 'connected' | 'error'

interface ChatMessageItem {
  id: string
  kind: 'system' | 'chat' | 'gift'
  nickname: string
  content: string
}

const route = useRoute()
const router = useRouter()
const baseStore = useBaseStore()

const videoEl = ref<HTMLVideoElement | null>(null)
const room = ref<LiveRoom | null>(null)
const loading = ref(false)
const error = ref('')
const isMuted = ref(true)
const playbackMode = ref<LivePlaybackMode | ''>('')
const wsState = ref<WsState>('idle')
const chatDraft = ref('')
const sendingChat = ref(false)
const sendingGiftId = ref<number | null>(null)
const chatMessages = ref<ChatMessageItem[]>([])
const realtimeOnlineCount = ref<number | null>(null)
const realtimeLikeCount = ref<number | null>(null)
const privilegeActive = ref(false)
const privilegeExpireAt = ref(0)
const privilegeCountdown = ref(0)
const privilegeGiftName = ref('')
const privilegeToast = ref('')
const interactionActive = ref(false)
const interactionHlsUrl = ref('')

let livePlaybackController: ReturnType<typeof createLivePlaybackController> | null = null
let ws: WebSocket | null = null
let heartbeatTimer: number | null = null
let reconnectTimer: number | null = null
let privilegeTimer: number | null = null
let socketRoomId = 0
let socketManuallyClosed = false

const routeRoomId = computed(() => {
  const queryRoomId = Number(route.query.roomId || 0)
  if (queryRoomId > 0) return queryRoomId
  const routeDataRoomId = Number((baseStore.routeData as any)?.room_id || 0)
  return routeDataRoomId > 0 ? routeDataRoomId : 0
})

const posterUrl = computed(() => room.value?.cover_url || '')
const personaName = computed(() => room.value?.persona?.name || room.value?.title || '直播间')
const avatarUrl = computed(() => room.value?.cover_url || '')
const currentNickname = computed(() => baseStore.userinfo.nickname || '现场观众')
const badgeText = computed(() => room.value?.display?.badge_text || '直播中')
const giftList = computed(() => room.value?.gift_panel?.quick_gifts || [])
const giftCurrencyName = computed(() => room.value?.gift_panel?.currency_name || '钻石')
const onlineText = computed(() =>
  realtimeOnlineCount.value !== null
    ? normalizeCountText(realtimeOnlineCount.value, '实时在线')
    : normalizeCountText(room.value?.display?.online_text, '实时在线')
)
const likeText = computed(() =>
  realtimeLikeCount.value !== null
    ? normalizeCountText(realtimeLikeCount.value, '热度上升')
    : normalizeCountText(room.value?.display?.like_text, '热度上升')
)
const modeText = computed(() => {
  if (privilegeActive.value) return '特权流'
  if (!room.value?.state?.mode) return '公共流'
  if (room.value.state.mode === 'public') return '公共流'
  if (room.value.state.mode === 'privilege') return '特权流'
  if (room.value.state.mode === 'interaction') return '互动流'
  return room.value.state.mode
})
const privilegeBadgeText = computed(() => {
  if (!privilegeActive.value) return ''
  if (privilegeCountdown.value > 0) {
    return `特权 ${privilegeCountdown.value}s`
  }
  return '特权流'
})
const playModeText = computed(() => {
  switch (playbackMode.value) {
    case 'webrtc':
      return 'WebRTC'
    case 'hls':
    case 'native-hls':
      return 'HLS'
    case 'preview':
      return '预览'
    default:
      return ''
  }
})
const playModeTitle = computed(() => {
  switch (playbackMode.value) {
    case 'webrtc':
      return '当前播放: WebRTC'
    case 'hls':
    case 'native-hls':
      return '当前播放: HLS'
    case 'preview':
      return '当前播放: 预览视频'
    default:
      return '当前播放: 加载中'
  }
})
const wsStatusText = computed(() => {
  switch (wsState.value) {
    case 'connecting':
      return '弹幕连接中...'
    case 'connected':
      return '弹幕已连接，可实时收发'
    case 'error':
      return '弹幕连接异常，正在回退重连'
    default:
      return room.value?.interaction?.allow_chat ? '准备连接弹幕服务' : '当前房间不开放弹幕'
  }
})
const canSendChat = computed(() => {
  return Boolean(room.value?.interaction?.allow_chat && wsState.value === 'connected' && routeRoomId.value > 0)
})
const canSendGift = computed(() => {
  return Boolean(room.value?.interaction?.allow_gift && wsState.value === 'connected' && routeRoomId.value > 0)
})
const chatPlaceholder = computed(() => {
  if (!room.value?.interaction?.allow_chat) return '当前房间不开放弹幕'
  if (wsState.value !== 'connected') return '弹幕连接中...'
  return '说点什么...'
})
const playModeDescription = computed(() => {
  switch (playbackMode.value) {
    case 'webrtc':
      return '实时流播放，延迟最低'
    case 'hls':
    case 'native-hls':
      return '直播切片流，依赖 channel-worker 持续产出'
    case 'preview':
      return '未连上直播流，已回退到预览视频'
    default:
      return '正在判断真实播放源'
  }
})
const playModeBannerClass = computed(() => {
  switch (playbackMode.value) {
    case 'webrtc':
      return 'is-webrtc'
    case 'hls':
    case 'native-hls':
      return 'is-hls'
    case 'preview':
      return 'is-preview'
    default:
      return 'is-pending'
  }
})

function normalizeCountText(value: string | number | undefined, fallback: string) {
  if (value === undefined || value === null || value === '') return fallback
  if (typeof value === 'string') return value
  return _formatNumber(value) ?? String(value)
}

function createTraceId(prefix: string) {
  return `${prefix}_${Date.now()}_${Math.random().toString(16).slice(2, 8)}`
}

function buildWsUrl() {
  const protocol = window.location.protocol === 'https:' ? 'wss' : 'ws'
  const host = window.location.hostname || '127.0.0.1'
  return `${protocol}://${host}:8788`
}

function appendChatMessage(item: ChatMessageItem) {
  const next = [...chatMessages.value, item]
  chatMessages.value = next.slice(-8)
}

function applyRoomSnapshot(snapshot: Record<string, any>) {
  realtimeOnlineCount.value = Number(snapshot.online_count ?? 0)
  realtimeLikeCount.value = Number(snapshot.like_count ?? 0)
}

function resetRealtimeState() {
  chatMessages.value = []
  realtimeOnlineCount.value = null
  realtimeLikeCount.value = null
  chatDraft.value = ''
  sendingChat.value = false
  sendingGiftId.value = null
  resetPrivilegeState()
}

function clearHeartbeat() {
  if (heartbeatTimer !== null) {
    window.clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}

function clearReconnect() {
  if (reconnectTimer !== null) {
    window.clearTimeout(reconnectTimer)
    reconnectTimer = null
  }
}

function clearPrivilegeTimer() {
  if (privilegeTimer !== null) {
    window.clearInterval(privilegeTimer)
    privilegeTimer = null
  }
}

function startPrivilegeCountdown(expireTs: number) {
  clearPrivilegeTimer()
  privilegeActive.value = true
  privilegeExpireAt.value = expireTs
  privilegeCountdown.value = Math.max(0, Math.ceil((expireTs - Date.now()) / 1000))

  privilegeTimer = window.setInterval(() => {
    const remaining = Math.max(0, Math.ceil((privilegeExpireAt.value - Date.now()) / 1000))
    privilegeCountdown.value = remaining
    if (remaining <= 0) {
      clearPrivilegeTimer()
      privilegeActive.value = false
      privilegeCountdown.value = 0
    }
  }, 500)
}

function resetPrivilegeState() {
  clearPrivilegeTimer()
  privilegeActive.value = false
  privilegeExpireAt.value = 0
  privilegeCountdown.value = 0
  privilegeGiftName.value = ''
  privilegeToast.value = ''
}

function destroyRoomSocket() {
  socketManuallyClosed = true
  clearHeartbeat()
  clearReconnect()
  clearPrivilegeTimer()
  if (ws) {
    ws.close()
    ws = null
  }
  wsState.value = 'idle'
}

function startHeartbeat() {
  clearHeartbeat()
  heartbeatTimer = window.setInterval(() => {
    if (ws?.readyState !== WebSocket.OPEN) return
    ws.send(JSON.stringify({
      type: 'heartbeat',
      trace_id: createTraceId('heartbeat')
    }))
  }, 15000)
}

function connectRoomSocket(currentRoom: LiveRoom) {
  const roomId = Number(currentRoom.room_id || 0)
  const playToken = currentRoom.play?.play_token || ''
  const expireAt = Number(currentRoom.play?.expire_at || 0)
  if (roomId <= 0 || playToken === '' || expireAt <= 0) {
    wsState.value = 'idle'
    return
  }

  socketManuallyClosed = false
  clearHeartbeat()
  clearReconnect()
  if (ws) {
    ws.close()
    ws = null
  }

  socketRoomId = roomId
  wsState.value = 'connecting'
  const socket = new WebSocket(buildWsUrl())
  ws = socket

  socket.onopen = () => {
    const accessToken = getAccessToken()
    if (accessToken) {
      socket.send(JSON.stringify({
        type: 'auth',
        trace_id: createTraceId('auth'),
        token: accessToken,
        room_id: roomId
      }))
    } else {
      socket.send(JSON.stringify({
        type: 'auth',
        trace_id: createTraceId('auth'),
        room_id: roomId,
        play_token: playToken,
        expire_at: expireAt,
        nickname: currentNickname.value
      }))
    }
  }

  socket.onmessage = (event) => {
    let payload: any = null
    try {
      payload = JSON.parse(String(event.data || '{}'))
    } catch (parseError) {
      return
    }

    const type = String(payload.type || '')
    const data = payload.data || {}
    if (type === 'auth_ok') {
      wsState.value = 'connected'
      startHeartbeat()
      socket.send(JSON.stringify({
        type: 'join_room',
        trace_id: createTraceId('join'),
        room_id: roomId
      }))
      appendChatMessage({
        id: createTraceId('system'),
        kind: 'system',
        nickname: '',
        content: `已进入 ${currentRoom.title}`
      })
      return
    }

    if (type === 'room_snapshot') {
      applyRoomSnapshot(data)
      return
    }

    if (type === 'chat_message') {
      appendChatMessage({
        id: String(data.message_id || createTraceId('chat')),
        kind: 'chat',
        nickname: String(data.user?.nickname || '观众'),
        content: String(data.content || '')
      })
      sendingChat.value = false
      return
    }

    if (type === 'gift_message') {
      const giftName = String(data.gift?.name || '礼物')
      const giftId = Number(data.gift?.gift_id || 0)
      const quantity = Math.max(1, Number(data.quantity || 1))
      const nickname = String(data.user?.nickname || '观众')
      const triggerMode = String(data.gift?.trigger_mode || 'none')
      appendChatMessage({
        id: String(data.order_no || createTraceId('gift')),
        kind: 'gift',
        nickname,
        content: `送出了 ${giftName} x${quantity}`
      })
      if (sendingGiftId.value === giftId && nickname === currentNickname.value) {
        sendingGiftId.value = null
      }
      if (triggerMode === 'privilege') {
        const durationSec = Number(data.gift?.trigger_duration_sec || 0)
        privilegeToast.value = `${nickname} 送出 ${giftName}，触发特权`
        window.setTimeout(() => { privilegeToast.value = '' }, 3000)
      }
      return
    }

    if (type === 'privilege_started') {
      const durationSec = Number(data.duration_sec || 0)
      const expireTs = Date.now() + durationSec * 1000
      const giftName = String(data.gift_name || '专属礼物')
      privilegeGiftName.value = giftName
      startPrivilegeCountdown(expireTs)
      privilegeToast.value = `${giftName} 已开启特权，持续 ${durationSec} 秒`
      window.setTimeout(() => { privilegeToast.value = '' }, 4000)
      return
    }

    if (type === 'privilege_ended') {
      resetPrivilegeState()
      privilegeToast.value = '特权已结束'
      window.setTimeout(() => { privilegeToast.value = '' }, 3000)
      return
    }

    if (type === 'stream_reload') {
      if (videoEl.value && room.value) {
        const hlsUrl = interactionActive.value ? interactionHlsUrl.value : undefined
        startLivePlayback(hlsUrl)
      }
      return
    }

    if (type === 'interaction_ready') {
      const durationSec = Number(data.duration_sec || 0)
      const taskNo = String(data.task_no || '')
      const baseHls = room.value?.play?.hls_url || ''
      const interactionUrl = baseHls.replace(/\.m3u8$/, '/interaction.m3u8')
      interactionActive.value = true
      interactionHlsUrl.value = interactionUrl
      privilegeToast.value = `AI 互动已就绪，持续 ${durationSec} 秒`
      window.setTimeout(() => { privilegeToast.value = '' }, 4000)
      if (videoEl.value && interactionUrl) {
        startLivePlayback(interactionUrl)
      }
      return
    }

    if (type === 'interaction_ended') {
      interactionActive.value = false
      interactionHlsUrl.value = ''
      privilegeToast.value = 'AI 互动已结束'
      window.setTimeout(() => { privilegeToast.value = '' }, 4000)
      if (videoEl.value) {
        startLivePlayback()
      }
      return
    }

    if (type === 'error') {
      sendingChat.value = false
      sendingGiftId.value = null
      if (payload.msg) {
        _notice(String(payload.msg))
      }
    }
  }

  socket.onerror = () => {
    wsState.value = 'error'
  }

  socket.onclose = () => {
    clearHeartbeat()
    if (ws === socket) {
      ws = null
    }
    if (socketManuallyClosed || socketRoomId !== roomId) {
      return
    }
    wsState.value = 'error'
    clearReconnect()
    reconnectTimer = window.setTimeout(() => {
      if (room.value && Number(room.value.room_id) === roomId) {
        connectRoomSocket(room.value)
      }
    }, 2000)
  }
}

function destroyLivePlayback() {
  livePlaybackController?.destroy()
  livePlaybackController = null
  playbackMode.value = ''
}

async function startLivePlayback(hlsUrlOverride?: string) {
  if (!videoEl.value || !room.value) return

  destroyLivePlayback()
  livePlaybackController = createLivePlaybackController({
    videoEl: videoEl.value,
    webrtcUrl: room.value.play?.webrtc_url,
    hlsUrl: hlsUrlOverride || room.value.play?.hls_url,
    previewUrl: room.value.preview_video_url,
    muted: isMuted.value,
    onModeChange: (mode) => {
      playbackMode.value = mode
    }
  })

  try {
    await livePlaybackController.play()
  } catch (playError) {
    console.warn('live room play failed', playError)
  }
}

function syncRouteDataDraft(roomId: number) {
  const draft = baseStore.routeData as LiveRoom | null
  if (draft && Number(draft.room_id) === roomId) {
    room.value = draft
  }
}

async function loadRoom(roomId: number) {
  destroyLivePlayback()
  destroyRoomSocket()
  resetRealtimeState()
  error.value = ''

  if (roomId <= 0) {
    room.value = null
    error.value = '房间不存在'
    return
  }

  syncRouteDataDraft(roomId)
  loading.value = true
  const res = await liveRoomDetail(roomId)
  loading.value = false

  if (!res.success) {
    error.value = '房间详情加载失败'
    return
  }

  room.value = res.data
  baseStore.routeData = res.data
  appendChatMessage({
    id: createTraceId('welcome'),
    kind: 'system',
    nickname: '',
    content: res.data.subtitle || `欢迎来到 ${res.data.title}`
  })
  await startLivePlayback()
  connectRoomSocket(res.data)
}

function sendChat() {
  const currentRoomId = routeRoomId.value
  const content = chatDraft.value.trim()
  if (!canSendChat.value || !ws || ws.readyState !== WebSocket.OPEN || currentRoomId <= 0) {
    if (room.value?.interaction?.allow_chat) {
      _notice('弹幕服务未连接')
    }
    return
  }
  if (content === '') {
    _notice('请输入弹幕内容')
    return
  }

  sendingChat.value = true
  ws.send(JSON.stringify({
    type: 'send_chat',
    trace_id: createTraceId('chat'),
    room_id: currentRoomId,
    content
  }))
  chatDraft.value = ''
}

function sendGift(gift: LiveGiftInfo) {
  const currentRoomId = routeRoomId.value
  if (!canSendGift.value || !ws || ws.readyState !== WebSocket.OPEN || currentRoomId <= 0) {
    if (room.value?.interaction?.allow_gift) {
      _notice('礼物服务未连接')
    }
    return
  }

  sendingGiftId.value = gift.gift_id
  ws.send(JSON.stringify({
    type: 'send_gift',
    trace_id: createTraceId('gift'),
    room_id: currentRoomId,
    gift_id: gift.gift_id,
    quantity: 1
  }))
}

function goBack() {
  clearPrivilegeTimer()
  if (window.history.length > 1) {
    router.back()
    return
  }
  router.push('/home')
}

watch(
  routeRoomId,
  (roomId) => {
    loadRoom(roomId)
  },
  { immediate: true }
)

watch(isMuted, (muted) => {
  if (videoEl.value) {
    videoEl.value.muted = muted
  }
})

onMounted(() => {
  if (videoEl.value) {
    videoEl.value.muted = isMuted.value
  }
  if (room.value) {
    startLivePlayback()
  }
})

onUnmounted(() => {
  destroyLivePlayback()
  destroyRoomSocket()
})
</script>

<style scoped lang="less">
.live-room-page {
  position: relative;
  width: 100%;
  height: calc(var(--vh, 1vh) * 100);
  overflow: hidden;
  background:
    radial-gradient(circle at top, rgba(255, 123, 84, 0.24), transparent 32%),
    radial-gradient(circle at bottom, rgba(113, 88, 255, 0.22), transparent 36%),
    #050505;
  color: white;

  .player {
    max-width: 100%;
    height: 100%;
    display: block;
    margin: 0 auto;
    background: #000;
  }

  .overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(to top, rgba(0, 0, 0, 0.78), rgba(0, 0, 0, 0.08) 42%, rgba(0, 0, 0, 0.32));
  }

  .top-bar {
    position: absolute;
    top: 14rem;
    left: 12rem;
    right: 12rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10rem;
  }

  .badges {
    display: flex;
    flex-wrap: wrap;
    gap: 8rem;
  }

  .pill {
    padding: 6rem 10rem;
    border-radius: 999rem;
    font-size: 11rem;
    line-height: 1;
    border: 1px solid transparent;

    &.hot {
      background: linear-gradient(135deg, rgba(255, 73, 120, 0.95), rgba(255, 154, 68, 0.95));
    }

    &.subtle {
      background: rgba(10, 10, 10, 0.4);
      border-color: rgba(255, 255, 255, 0.14);
    }

    &.privilege {
      background: linear-gradient(135deg, rgba(199, 68, 255, 0.92), rgba(255, 106, 184, 0.88));
      color: white;
      font-weight: 600;
      animation: privilegePulse 1.2s ease-in-out infinite;
    }
  }

  @keyframes privilegePulse {
    0%,
    100% {
      opacity: 1;
    }
    50% {
      opacity: 0.72;
    }
  }

  .privilege-toast {
    position: absolute;
    top: 52rem;
    left: 50%;
    transform: translateX(-50%);
    padding: 8rem 18rem;
    border-radius: 999rem;
    font-size: 11rem;
    font-weight: 500;
    color: #ffeabd;
    background: linear-gradient(135deg, rgba(199, 68, 255, 0.42), rgba(255, 106, 184, 0.36));
    border: 1px solid rgba(199, 68, 255, 0.46);
    backdrop-filter: blur(10rem);
    white-space: nowrap;
    z-index: 2;
    animation: toastSlideIn 0.28s ease-out;
  }

  @keyframes toastSlideIn {
    from {
      opacity: 0;
      transform: translateX(-50%) translateY(-8rem);
    }
    to {
      opacity: 1;
      transform: translateX(-50%) translateY(0);
    }
  }

  .close-btn {
    border: 0;
    color: white;
    font-size: 12rem;
    border-radius: 999rem;
    padding: 8rem 14rem;
    background: rgba(10, 10, 10, 0.5);
    backdrop-filter: blur(12rem);
  }

  .center-tip {
    position: absolute;
    left: 50%;
    top: 50%;
    transform: translate(-50%, -50%);
    min-width: 160rem;
    text-align: center;
    padding: 12rem 18rem;
    border-radius: 16rem;
    font-size: 14rem;
    background: rgba(10, 10, 10, 0.55);

    &.error {
      color: #ffd3d3;
    }
  }

  .right-panel {
    position: absolute;
    right: 12rem;
    bottom: 176rem;
    display: flex;
    flex-direction: column;
    gap: 10rem;
  }

  .metric {
    min-width: 64rem;
    padding: 8rem 10rem;
    border-radius: 14rem;
    text-align: center;
    background: rgba(10, 10, 10, 0.38);
    backdrop-filter: blur(14rem);
    border: 1px solid rgba(255, 255, 255, 0.1);

    span,
    strong {
      display: block;
    }

    span {
      font-size: 10rem;
      opacity: 0.78;
    }

    strong {
      margin-top: 3rem;
      font-size: 13rem;
      font-weight: 600;
    }
  }

  .playback-banner {
    position: absolute;
    left: 12rem;
    top: 56rem;
    display: inline-flex;
    align-items: center;
    gap: 8rem;
    max-width: calc(100% - 110rem);
    padding: 8rem 12rem;
    border-radius: 999rem;
    font-size: 11rem;
    line-height: 1;
    border: 1px solid rgba(255, 255, 255, 0.14);
    backdrop-filter: blur(14rem);
    background: rgba(10, 10, 10, 0.42);

    &.is-webrtc {
      .mode-chip {
        background: rgba(79, 209, 140, 0.22);
        color: #8df0b7;
      }
    }

    &.is-hls {
      .mode-chip {
        background: rgba(83, 160, 255, 0.22);
        color: #98c8ff;
      }
    }

    &.is-preview {
      .mode-chip {
        background: rgba(255, 176, 32, 0.22);
        color: #ffd47b;
      }
    }

    &.is-pending {
      .mode-chip {
        background: rgba(255, 255, 255, 0.16);
        color: rgba(255, 255, 255, 0.86);
      }
    }
  }

  .mode-chip {
    flex: 0 0 auto;
    padding: 6rem 10rem;
    border-radius: 999rem;
    font-weight: 600;
  }

  .mode-copy {
    min-width: 0;
    color: rgba(255, 255, 255, 0.78);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .bottom-panel {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 16rem 12rem 18rem;
  }

  .comments {
    display: flex;
    flex-direction: column;
    gap: 6rem;
    max-width: calc(100% - 84rem);
    margin-bottom: 14rem;
  }

  .comment {
    width: fit-content;
    max-width: 100%;
    padding: 6rem 10rem;
    line-height: 1.45;
    border-radius: 12rem;
    font-size: 12rem;
    background: rgba(10, 10, 10, 0.38);
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(12rem);

    &.notice {
      color: #c8e6ff;
    }

    &.gift {
      color: #ffd47b;
    }
  }

  .ws-status {
    &.is-connected {
      color: #8df0b7;
    }

    &.is-connecting {
      color: #98c8ff;
    }

    &.is-error {
      color: #ffd47b;
    }
  }

  .comment-author {
    margin-right: 4rem;
    color: rgba(255, 255, 255, 0.78);
  }

  .gift-row {
    display: flex;
    gap: 8rem;
    overflow-x: auto;
    padding-bottom: 8rem;
    margin: 0 84rem 10rem 0;
    -ms-overflow-style: none;
    scrollbar-width: none;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  .gift-chip {
    flex: 0 0 auto;
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 4rem;
    min-width: 74rem;
    padding: 8rem 10rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 14rem;
    color: white;
    background: rgba(18, 18, 24, 0.6);
    backdrop-filter: blur(12rem);

    &.active {
      border-color: rgba(255, 154, 68, 0.7);
      background: rgba(255, 154, 68, 0.24);
    }

    &:disabled {
      opacity: 0.56;
    }
  }

  .gift-name {
    font-size: 12rem;
    font-weight: 600;
  }

  .gift-price {
    font-size: 10rem;
    color: rgba(255, 255, 255, 0.7);
  }

  .room-meta {
    padding: 10rem 12rem;
    border-radius: 14rem;
    background: linear-gradient(180deg, rgba(14, 14, 18, 0.72), rgba(8, 8, 12, 0.86));
    border: 1px solid rgba(255, 255, 255, 0.08);
    backdrop-filter: blur(18rem);
  }

  .meta-row {
    display: flex;
    align-items: center;
    gap: 10rem;
  }

  .avatar {
    width: 40rem;
    height: 40rem;
    border-radius: 50%;
    object-fit: cover;
    background: rgba(255, 255, 255, 0.12);
  }

  .host-text {
    flex: 1;
    min-width: 0;
  }

  .host-name {
    flex: 0 0 auto;
    font-size: 12rem;
    color: rgba(255, 255, 255, 0.75);
    font-size: 12rem;
    white-space: nowrap;
  }

  .chat-input {
    flex: 1;
    min-width: 0;
    padding: 9rem 14rem;
    border: 0;
    outline: none;
    border-radius: 999rem;
    font-size: 12rem;
    -webkit-appearance: none;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.1);
    caret-color: white;

    &.disabled {
      opacity: 0.55;
    }

    &::placeholder {
      color: rgba(255, 255, 255, 0.48);
    }
  }

  .send-btn {
    flex: 0 0 auto;
    min-width: 48rem;
    padding: 8rem 12rem;
    border-radius: 999rem;
    border: 0;
    color: white;
    font-size: 12rem;
    background: linear-gradient(135deg, #ff4978, #ff9a44);

    &:disabled {
      opacity: 0.5;
    }
  }
}
</style>
