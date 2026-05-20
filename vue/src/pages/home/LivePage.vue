﻿<template>
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
        </div>
        <button class="close-btn" type="button" @click="goBack">返回</button>
      </div>

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
            <div v-if="room.subtitle" class="comment">{{ room.subtitle }}</div>
            <div class="comment">
              当前房间支持
              <span>{{ room.interaction?.allow_chat ? '弹幕' : '只读' }}</span>
              <span> / </span>
              <span>{{ room.interaction?.allow_like ? '点赞' : '禁用点赞' }}</span>
              <span> / </span>
              <span>{{ room.interaction?.allow_gift ? '礼物' : '禁用礼物' }}</span>
            </div>
          </div>

          <div class="room-meta">
            <div class="meta-row">
              <img class="avatar" :src="avatarUrl" alt="" />
              <div class="host-name">{{ personaName }}</div>
              <div class="chat-input" :class="{ disabled: !room.interaction?.allow_chat }">
                {{ room.interaction?.allow_chat ? '说点什么...' : '当前房间不开放弹幕' }}
              </div>
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
import { _formatNumber } from '@/utils'
import { liveRoomDetail, type LiveRoom } from '@/api/live'
import { createLivePlaybackController, type LivePlaybackMode } from '@/utils/livePlayer'
import { useBaseStore } from '@/store/pinia'

const route = useRoute()
const router = useRouter()
const baseStore = useBaseStore()

const videoEl = ref<HTMLVideoElement | null>(null)
const room = ref<LiveRoom | null>(null)
const loading = ref(false)
const error = ref('')
const isMuted = ref(true)
const playbackMode = ref<LivePlaybackMode | ''>('')

let livePlaybackController: ReturnType<typeof createLivePlaybackController> | null = null

const routeRoomId = computed(() => {
  const queryRoomId = Number(route.query.roomId || 0)
  if (queryRoomId > 0) return queryRoomId
  const routeDataRoomId = Number((baseStore.routeData as any)?.room_id || 0)
  return routeDataRoomId > 0 ? routeDataRoomId : 0
})

const posterUrl = computed(() => room.value?.cover_url || '')
const personaName = computed(() => room.value?.persona?.name || room.value?.title || '直播间')
const avatarUrl = computed(() => room.value?.cover_url || '')
const badgeText = computed(() => room.value?.display?.badge_text || '直播中')
const onlineText = computed(() => normalizeCountText(room.value?.display?.online_text, '实时在线'))
const likeText = computed(() => normalizeCountText(room.value?.display?.like_text, '热度上升'))
const modeText = computed(() => {
  if (!room.value?.state?.mode) return '公共流'
  if (room.value.state.mode === 'public') return '公共流'
  if (room.value.state.mode === 'privilege') return '特权流'
  if (room.value.state.mode === 'interaction') return '互动流'
  return room.value.state.mode
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

function destroyLivePlayback() {
  livePlaybackController?.destroy()
  livePlaybackController = null
  playbackMode.value = ''
}

async function startLivePlayback() {
  if (!videoEl.value || !room.value) return

  destroyLivePlayback()
  livePlaybackController = createLivePlaybackController({
    videoEl: videoEl.value,
    webrtcUrl: room.value.play?.webrtc_url,
    hlsUrl: room.value.play?.hls_url,
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
  await startLivePlayback()
}

function goBack() {
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
    border-radius: 999rem;
    font-size: 12rem;
    color: rgba(255, 255, 255, 0.8);
    background: rgba(255, 255, 255, 0.1);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;

    &.disabled {
      opacity: 0.55;
    }
  }
}
</style>
