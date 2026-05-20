<template>
  <div class="video-wrapper" ref="videoWrapper" :class="positionName">
    <Loading v-if="state.loading" style="position: absolute" />
    <!--    <video :src="item.video + '?v=123'"-->
    <video
      :poster="poster"
      ref="videoEl"
      :muted="state.isMuted"
      preload="true"
      loop
      x5-video-player-type="h5-page"
      :x5-video-player-fullscreen="false"
      :webkit-playsinline="true"
      :x5-playsinline="true"
      :playsinline="true"
      :fullscreen="false"
      :autoplay="isPlay"
    >
      <source
        v-if="shouldUseSourceList"
        v-for="(urlItem, index) in videoSourceList"
        :key="index"
        :src="urlItem"
        :type="getVideoType(urlItem)"
      />
      <p>您的浏览器不支持 video 标签。</p>
    </video>
    <Icon icon="fluent:play-28-filled" class="pause-icon" v-if="!props.isLive && !isPlaying" />
    <div class="float">
      <template v-if="isLive">
        <div class="live-top">
          <div class="live-pill">{{ liveBadgeText }}</div>
          <div v-if="liveRoomNo" class="live-pill subtle">{{ liveRoomNo }}</div>
          <div v-if="livePlaybackModeText" class="live-pill subtle">{{ livePlaybackModeText }}</div>
        </div>
        <div class="live-side">
          <div class="metric">
            <span>在线</span>
            <strong>{{ liveOnlineText }}</strong>
          </div>
          <div class="metric">
            <span>点赞</span>
            <strong>{{ liveLikeText }}</strong>
          </div>
        </div>
        <button
          class="living"
          type="button"
          @touchstart.stop.prevent
          @touchend.stop.prevent="enterLiveRoom"
          @mousedown.stop.prevent
          @click.stop.prevent="enterLiveRoom"
        >
          进入直播间
        </button>
        <ItemDesc :is-live="true" v-model:item="state.localItem" :position="position" />
      </template>
      <template v-else>
        <div :style="{ opacity: state.isMove ? 0 : 1 }" class="normal">
          <template v-if="!state.commentVisible">
            <ItemToolbar v-model:item="state.localItem" />
            <ItemDesc v-model:item="state.localItem" />
          </template>
          <div v-if="isMy" class="comment-status">
            <div class="comment">
              <div class="type-comment">
                <img src="../../assets/img/icon/head-image.jpeg" alt="" class="avatar" />
                <div class="right">
                  <p>
                    <span class="name">zzzzz</span>
                    <span class="time">2020-01-20</span>
                  </p>
                  <p class="text">北京</p>
                </div>
              </div>
              <transition-group name="comment-status" tag="div" class="loveds">
                <div class="type-loved" :key="i" v-for="i in state.test">
                  <img src="../../assets/img/icon/head-image.jpeg" alt="" class="avatar" />
                  <img src="../../assets/img/icon/love.svg" alt="" class="loved" />
                </div>
              </transition-group>
            </div>
          </div>
        </div>
        <div
          class="progress"
          :class="progressClass"
          ref="progressEl"
          @click="null"
          @touchstart="touchstart"
          @touchmove="touchmove"
          @touchend="touchend"
        >
          <div class="time" v-if="state.isMove">
            <span class="currentTime">{{ _duration(state.currentTime) }}</span>
            <span class="duration"> / {{ _duration(state.duration) }}</span>
          </div>
          <template v-if="state.duration > 15 || state.isMove || !isPlaying">
            <div class="bg"></div>
            <div class="progress-line" :style="durationStyle"></div>
            <div class="point"></div>
          </template>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { _checkImgUrl, _duration, _formatNumber, _stopPropagation, cloneDeep } from '@/utils'
import Loading from '../Loading.vue'
import ItemToolbar from './ItemToolbar.vue'
import ItemDesc from './ItemDesc.vue'
import bus, { EVENT_KEY } from '../../utils/bus'
import { SlideItemPlayStatus } from '@/utils/const_var'
import { computed, onMounted, onUnmounted, provide, reactive, watch } from 'vue'
import { Icon } from '@iconify/vue'
import { _css } from '@/utils/dom'
import { createLivePlaybackController, type LivePlaybackMode } from '@/utils/livePlayer'
import { useBaseStore } from '@/store/pinia'

defineOptions({
  name: 'BaseVideo'
})

const props = defineProps({
  item: {
    type: Object,
    default: () => {
      return {}
    }
  },
  position: {
    type: Object,
    default: () => {
      return {}
    }
  },
  //用于第一条数据，自动播放，如果都用事件去触发播放，有延迟
  isPlay: {
    type: Boolean,
    default: () => {
      return true
    }
  },
  isMy: {
    type: Boolean,
    default: () => {
      return false
    }
  },
  isLive: {
    type: Boolean,
    default: () => {
      return false
    }
  }
})

provide(
  'isPlaying',
  computed(() => isPlaying)
)
provide(
  'isMuted',
  computed(() => state.isMuted)
)
provide(
  'position',
  computed(() => props.position)
)
provide(
  'item',
  computed(() => props.item)
)

const videoEl = $ref<HTMLVideoElement>()
const progressEl = $ref<HTMLDivElement>()
let state = reactive({
  loading: false,
  paused: false,
  isMuted: window.isMuted,
  status: props.isPlay ? SlideItemPlayStatus.Play : SlideItemPlayStatus.Pause,
  duration: 0,
  step: 0,
  currentTime: -1,
  playX: 0,
  start: { x: 0 },
  last: { x: 0, time: 0 },
  height: 0,
  width: 0,
  isMove: false,
  ignoreWaiting: false, //忽略waiting事件。因为改变进度会触发waiting事件，烦的一批
  test: [1, 2],
  localItem: props.item,
  progressBarRect: {
    height: 0,
    width: 0
  },
  videoScreenHeight: 0,
  commentVisible: false,
  livePlaybackMode: '' as LivePlaybackMode | '',
  livePlaybackSignature: ''
})
let livePlaybackController: ReturnType<typeof createLivePlaybackController> | null = null
const baseStore = useBaseStore()
const poster = $computed(() => {
  if (props.isLive) return ''
  return _checkImgUrl(
    props.item?.video?.poster ?? props.item?.video?.cover?.url_list?.[0] ?? props.item?.cover_url
  )
})
const shouldUseSourceList = $computed(() => {
  return !(props.isLive && hasLivePlaySource.value)
})
const videoSourceList = $computed(() => {
  const currentItem = props.item ?? {}
  const sources = [
    currentItem?.preview_video_url,
    currentItem?.play?.hls_url,
    currentItem?.play?.flv_url,
    ...(currentItem?.video?.play_addr?.url_list ?? [])
  ].filter(Boolean)
  return [...new Set(sources)]
})
const hasLivePlaySource = computed(() => {
  return Boolean(props.item?.play?.webrtc_url || props.item?.play?.hls_url)
})
const livePlaybackModeText = $computed(() => {
  switch (state.livePlaybackMode) {
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
const durationStyle = $computed(() => {
  return { width: state.playX + 'px' }
})
const isPlaying = $computed(() => {
  return state.status === SlideItemPlayStatus.Play
})
const positionName = $computed(() => {
  return 'item-' + Object.values(props.position).join('-')
})
const progressClass = $computed(() => {
  if (state.isMove) {
    return 'move'
  } else {
    return isPlaying ? '' : 'stop'
  }
})
const liveBadgeText = $computed(() => {
  return props.item?.display?.badge_text || (props.item?.state?.privilege_active ? '特权直播' : '直播中')
})
const liveRoomNo = $computed(() => {
  if (props.item?.room_no) return props.item.room_no
  if (props.item?.room_id) return `房间 ${props.item.room_id}`
  return ''
})
const liveOnlineText = $computed(() => {
  return normalizeCountText(
    props.item?.display?.online_text ?? props.item?.statistics?.comment_count,
    '实时在线'
  )
})
const liveLikeText = $computed(() => {
  return normalizeCountText(
    props.item?.display?.like_text ?? props.item?.statistics?.digg_count,
    '热度上升'
  )
})

function normalizeCountText(value: string | number | undefined, fallback: string) {
  if (value === undefined || value === null || value === '') return fallback
  if (typeof value === 'string') return value
  return _formatNumber(value) ?? String(value)
}

function getVideoType(url: string) {
  return url.includes('.m3u8') ? 'application/x-mpegURL' : 'video/mp4'
}

function enterLiveRoom() {
  const roomId = Number(props.item?.room_id || 0)
  if (!props.isLive || roomId <= 0) return
  baseStore.routeData = cloneDeep(props.item)
  bus.emit(EVENT_KEY.NAV, {
    path: '/home/live',
    query: {
      roomId: String(roomId)
    }
  })
}

function destroyLivePlayback() {
  livePlaybackController?.destroy()
  livePlaybackController = null
  state.livePlaybackSignature = ''
  state.livePlaybackMode = ''
}

async function ensureLivePlayback(force = false) {
  if (!props.isLive || !hasLivePlaySource.value) return

  const signature = `${props.item?.play?.webrtc_url || ''}|${props.item?.play?.hls_url || ''}`
  if (!force && livePlaybackController && state.livePlaybackSignature === signature) {
    return
  }

  destroyLivePlayback()
  livePlaybackController = createLivePlaybackController({
    videoEl,
    webrtcUrl: props.item?.play?.webrtc_url,
    hlsUrl: props.item?.play?.hls_url,
    previewUrl: props.item?.preview_video_url,
    muted: state.isMuted,
    onModeChange: (mode) => {
      state.livePlaybackMode = mode
      state.loading = false
    }
  })
  state.livePlaybackSignature = signature
  state.loading = true
  await livePlaybackController.play()
}

onMounted(() => {
  // console.log('video', this.localItem.aweme_id)
  // console.log(this.commentVisible)
  state.height = document.body.clientHeight
  state.width = document.body.clientWidth
  videoEl.currentTime = 0
  let fun = (e) => {
    state.currentTime = Math.ceil(e.target.currentTime)
    state.playX = (state.currentTime - 1) * state.step
  }
  videoEl.addEventListener('loadedmetadata', () => {
    state.videoScreenHeight = videoEl.videoHeight / (videoEl.videoWidth / state.width)
    state.duration = videoEl.duration
    if (!props.isLive && progressEl) {
      state.progressBarRect = progressEl.getBoundingClientRect()
      state.step = state.progressBarRect.width / Math.floor(state.duration)
      videoEl.addEventListener('timeupdate', fun)
    }
  })

  let eventTester = (e, t: string) => {
    videoEl.addEventListener(
      e,
      () => {
        // console.log('eventTester', e, state.item.aweme_id)
        if (e === 'playing') state.loading = false
        if (e === 'waiting') {
          if (!state.paused && !state.ignoreWaiting) {
            state.loading = true
          }
        }
        let s = false
        if (s) {
          console.log(e, t)
        }
      },
      false
    )
  }

  // eventTester("loadstart", '客户端开始请求数据'); //客户端开始请求数据
  // eventTester("abort", '客户端主动终止下载（不是因为错误引起）'); //客户端主动终止下载（不是因为错误引起）
  // eventTester("loadstart", '客户端开始请求数据'); //客户端开始请求数据
  // eventTester("progress", '客户端正在请求数据'); //客户端正在请求数据
  // // eventTester("suspend", '延迟下载'); //延迟下载
  // eventTester("abort", '客户端主动终止下载（不是因为错误引起），'); //客户端主动终止下载（不是因为错误引起），
  // eventTester("error", '请求数据时遇到错误'); //请求数据时遇到错误
  // eventTester("stalled", '网速失速'); //网速失速
  // eventTester("play", 'play()和autoplay开始播放时触发'); //play()和autoplay开始播放时触发
  // eventTester("pause", 'pause()触发'); //pause()触发
  // eventTester("loadedmetadata", '成功获取资源长度'); //成功获取资源长度
  // eventTester("loadeddata"); //
  eventTester('waiting', '等待数据，并非错误') //等待数据，并非错误
  eventTester('playing', '开始回放') //开始回放
  // eventTester("canplay", '/可以播放，但中途可能因为加载而暂停'); //可以播放，但中途可能因为加载而暂停
  // eventTester("canplaythrough", '可以播放，歌曲全部加载完毕'); //可以播放，歌曲全部加载完毕
  // eventTester("seeking", '寻找中'); //寻找中
  // eventTester("seeked", '寻找完毕'); //寻找完毕
  // // eventTester("timeupdate",'播放时间改变'); //播放时间改变
  // eventTester("ended", '播放结束'); //播放结束
  // eventTester("ratechange", '播放速率改变'); //播放速率改变
  // eventTester("durationchange", '资源长度改变'); //资源长度改变
  // eventTester("volumechange", '音量改变'); //音量改变

  // console.log('mounted')
  // bus.off('singleClickBroadcast')
  bus.on(EVENT_KEY.SINGLE_CLICK_BROADCAST, click)
  bus.on(EVENT_KEY.DIALOG_MOVE, onDialogMove)
  bus.on(EVENT_KEY.DIALOG_END, onDialogEnd)
  bus.on(EVENT_KEY.OPEN_COMMENTS, onOpenComments)
  bus.on(EVENT_KEY.CLOSE_COMMENTS, onCloseComments)
  bus.on(EVENT_KEY.OPEN_SUB_TYPE, onOpenSubType)
  bus.on(EVENT_KEY.CLOSE_SUB_TYPE, onCloseSubType)

  bus.on(EVENT_KEY.REMOVE_MUTED, removeMuted)
})

onUnmounted(() => {
  destroyLivePlayback()
  // console.log('unmounted')
  bus.off(EVENT_KEY.SINGLE_CLICK_BROADCAST, click)
  bus.off(EVENT_KEY.DIALOG_MOVE, onDialogMove)
  bus.off(EVENT_KEY.DIALOG_END, onDialogEnd)
  bus.off(EVENT_KEY.OPEN_COMMENTS, onOpenComments)
  bus.off(EVENT_KEY.CLOSE_COMMENTS, onCloseComments)
  bus.off(EVENT_KEY.OPEN_SUB_TYPE, onOpenSubType)
  bus.off(EVENT_KEY.CLOSE_SUB_TYPE, onCloseSubType)
  bus.off(EVENT_KEY.REMOVE_MUTED, removeMuted)
})

watch(
  () => [props.item?.play?.webrtc_url, props.item?.play?.hls_url],
  async () => {
    if (!props.isLive || !hasLivePlaySource.value) return
    if (state.status !== SlideItemPlayStatus.Play) return
    try {
      await ensureLivePlayback(true)
    } catch (error) {
      console.warn('restart live playback failed', error)
    }
  }
)

function removeMuted() {
  state.isMuted = false
  videoEl.muted = false
}

function onOpenSubType() {
  state.commentVisible = true
}

function onCloseSubType() {
  state.commentVisible = false
}

function onDialogMove({ tag, e }) {
  if (state.commentVisible && tag === 'comment') {
    _css(videoEl, 'transition-duration', `0ms`)
    _css(videoEl, 'height', `calc(var(--vh, 1vh) * 30 + ${e}px)`)
  }
}

function onDialogEnd({ tag, isClose }) {
  if (state.commentVisible && tag === 'comment') {
    console.log('isClose', isClose)
    _css(videoEl, 'transition-duration', `300ms`)
    if (isClose) {
      state.commentVisible = false
      _css(videoEl, 'height', '100%')
    } else {
      _css(videoEl, 'height', 'calc(var(--vh, 1vh) * 30)')
    }
  }
}

function onOpenComments(id) {
  if (id === props.item.aweme_id) {
    _css(videoEl, 'transition-duration', `300ms`)
    _css(videoEl, 'height', 'calc(var(--vh, 1vh) * 30)')
    state.commentVisible = true
  }
}

function onCloseComments() {
  if (state.commentVisible) {
    _css(videoEl, 'transition-duration', `300ms`)
    _css(videoEl, 'height', '100%')
    state.commentVisible = false
  }
}

function click({ uniqueId, index, type }) {
  if (props.position.uniqueId === uniqueId && props.position.index === index) {
    if (type === EVENT_KEY.ITEM_TOGGLE) {
      if (props.isLive) {
        return
      }
      if (state.status === SlideItemPlayStatus.Play) {
        pause()
      } else {
        play()
      }
    }
    if (type === EVENT_KEY.ITEM_STOP) {
      if (!props.isLive) {
        videoEl.currentTime = 0
      }
      state.ignoreWaiting = true
      pause()
      setTimeout(() => (state.ignoreWaiting = false), 300)
    }
    if (type === EVENT_KEY.ITEM_PLAY) {
      if (!props.isLive) {
        videoEl.currentTime = 0
      }
      state.ignoreWaiting = true
      play(props.isLive)
      setTimeout(() => (state.ignoreWaiting = false), 300)
    }
  }
}

async function play(forceRestart = false) {
  state.status = SlideItemPlayStatus.Play
  videoEl.volume = 1
  try {
    if (props.isLive) {
      await ensureLivePlayback(forceRestart)
    }
    await videoEl.play()
  } catch (error) {
    state.loading = false
    console.warn('play video failed', error)
  }
}

function pause() {
  state.status = SlideItemPlayStatus.Pause
  if (props.isLive) {
    destroyLivePlayback()
    state.loading = false
    return
  }
  videoEl.pause()
}

function touchstart(e) {
  _stopPropagation(e)
  state.start.x = e.touches[0].pageX
  state.last.x = state.playX
  state.last.time = state.currentTime
}

function touchmove(e) {
  // console.log('move',e)
  _stopPropagation(e)
  state.isMove = true
  pause()
  let dx = e.touches[0].pageX - state.start.x
  state.playX = state.last.x + dx
  state.currentTime = state.last.time + Math.ceil(Math.ceil(dx) / state.step)
  if (state.currentTime <= 0) state.currentTime = 0
  if (state.currentTime >= state.duration) state.currentTime = state.duration
}

function touchend(e) {
  // console.log('end', e)
  _stopPropagation(e)
  if (isPlaying) return
  setTimeout(() => (state.isMove = false), 1000)
  videoEl.currentTime = state.currentTime
  play()
}
</script>

<style scoped lang="less">
.video-wrapper {
  position: relative;
  font-size: 14rem;
  width: 100%;
  height: 100%;
  text-align: center;

  video {
    max-width: 100%;
    height: 100%;
    transition:
      height,
      margin-top 0.3s;
    //background: black;
    /*position: absolute;*/
  }

  .float {
    position: absolute;
    left: 0;
    top: 0;
    height: 100%;
    width: 100%;

    .normal {
      position: absolute;
      bottom: 0;
      width: 100%;
      transition: all 0.3s;

      .comment-status {
        display: flex;
        align-items: center;

        .comment {
          .type-comment {
            display: flex;
            background: rgb(130, 21, 44);
            border-radius: 50px;
            padding: 3px;
            margin-bottom: 20px;

            .avatar {
              width: 36px;
              height: 36px;
              border-radius: 50%;
            }

            .right {
              margin: 0 10px;
              color: var(--second-text-color);

              .name {
                margin-right: 10px;
              }

              .text {
                color: white;
              }
            }
          }

          .loveds {
          }

          .type-loved {
            width: 40px;
            height: 40px;
            position: relative;
            margin-bottom: 20px;
            animation: test 1s;
            animation-delay: 0.5s;

            .avatar {
              width: 36px;
              height: 36px;
              border-radius: 50%;
            }

            .loved {
              position: absolute;
              bottom: 0;
              left: 20px;
              width: 10px;
              height: 10px;
              background: red;
              padding: 3px;
              border-radius: 50%;
              border: 2px solid white;
            }
          }

          @keyframes test {
            from {
              display: block;
              transform: translate3d(0, 0, 0);
            }
            to {
              display: none;
              transform: translate3d(0, -60px, 0);
            }
          }
        }
      }
    }

    .live-top {
      position: absolute;
      top: 16rem;
      left: 12rem;
      display: flex;
      gap: 8rem;

      .live-pill {
        padding: 5rem 10rem;
        border-radius: 999rem;
        font-size: 11rem;
        font-weight: 600;
        color: white;
        background: linear-gradient(135deg, rgba(255, 80, 120, 0.95), rgba(255, 148, 67, 0.95));

        &.subtle {
          background: rgba(15, 15, 15, 0.5);
          border: 1px solid rgba(255, 255, 255, 0.18);
        }
      }
    }

    .live-side {
      position: absolute;
      right: 12rem;
      bottom: 140rem;
      display: flex;
      flex-direction: column;
      gap: 8rem;

      .metric {
        min-width: 58rem;
        padding: 8rem 10rem;
        border-radius: 14rem;
        text-align: center;
        backdrop-filter: blur(12rem);
        background: rgba(0, 0, 0, 0.35);
        border: 1px solid rgba(255, 255, 255, 0.08);

        span,
        strong {
          display: block;
          color: white;
        }

        span {
          font-size: 10rem;
          opacity: 0.75;
        }

        strong {
          margin-top: 2rem;
          font-size: 13rem;
        }
      }
    }

    .progress {
      z-index: 10;
      @w: 90%;
      position: absolute;
      bottom: -1rem;
      height: 10rem;
      left: calc((100% - @w) / 2);
      width: @w;
      display: flex;
      align-items: flex-end;
      margin-bottom: 2rem;

      .time {
        position: absolute;
        z-index: 9;
        font-size: 24px;
        bottom: 50px;
        left: 0;
        right: 0;
        color: white;
        text-align: center;

        .duration {
          color: darkgray;
        }
      }

      @radius: 10rem;

      @h: 2rem;
      @tr: height 0.3s;

      .bg {
        transition: @tr;
        position: absolute;
        width: 100%;
        height: @h;
        background: #4f4f4f;
        border-radius: @radius;
      }

      @p: 50px;

      .progress-line {
        transition: @tr;
        height: calc(@h + 0.5rem);
        width: @p;
        border-radius: @radius 0 0 @radius;
        background: #777777;
        z-index: 1;
      }

      .point {
        transition: all 0.2s;
        width: @h+2;
        height: @h+2;
        border-radius: 50%;
        background: gray;
        z-index: 2;
        transform: translate(-1rem, 1rem);
      }
    }

    & .move {
      @h: 10rem;

      .bg {
        height: @h;
        background: var(--active-main-bg);
      }

      .progress-line {
        height: @h;
        background: var(--second-text-color);
      }

      .point {
        width: @h+2;
        height: @h+2;
        background: white;
      }
    }

    & .stop {
      @h: 4rem;

      .bg {
        height: @h;
      }

      .progress-line {
        height: @h;
        background: white;
      }

      .point {
        width: @h+2;
        height: @h+2;
        background: white;
      }
    }
  }
}

.living {
  position: absolute;
  left: 50%;
  z-index: 4;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 168rem;
  border: 0;
  font-size: 16rem;
  font-weight: 600;
  border-radius: 50rem;
  border: 1px solid rgba(255, 255, 255, 0.35);
  background: rgba(9, 9, 9, 0.28);
  padding: 14rem 24rem;
  line-height: 1;
  color: white;
  top: 70%;
  transform: translate(-50%, -50%);
  backdrop-filter: blur(16rem);
  cursor: pointer;
}
</style>
