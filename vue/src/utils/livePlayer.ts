import Hls from 'hls.js'
export type LivePlaybackMode = 'webrtc' | 'hls' | 'native-hls' | 'preview'

interface LivePlaybackOptions {
  videoEl: HTMLVideoElement
  webrtcUrl?: string
  previewUrl?: string
  muted?: boolean
  onModeChange?: (mode: LivePlaybackMode) => void
}

interface LivePlaybackController {
  play: () => Promise<LivePlaybackMode>
  destroy: () => void
}

interface SrsRtcPlayer {
  stream: MediaStream
  pc: RTCPeerConnection
  play: (url: string) => Promise<void>
  close: () => void
}

function canUseRtcPlayer() {
  return typeof window !== 'undefined' && typeof RTCPeerConnection !== 'undefined'
}

function createSrsRtcPlayer(): SrsRtcPlayer {
  const stream = new MediaStream()
  const pc = new RTCPeerConnection(null)

  pc.ontrack = (event) => {
    stream.addTrack(event.track)
  }

  return {
    stream,
    pc,
    async play(url: string) {
      pc.addTransceiver('audio', { direction: 'recvonly' })
      pc.addTransceiver('video', { direction: 'recvonly' })

      const offer = await pc.createOffer()
      await pc.setLocalDescription(offer)

      const response = await fetch(url, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/sdp'
        },
        body: offer.sdp ?? ''
      })

      if (!response.ok) {
        throw new Error(`WHEP play failed: ${response.status}`)
      }

      const answer = await response.text()
      await pc.setRemoteDescription(
        new RTCSessionDescription({
          type: 'answer',
          sdp: answer
        })
      )
    },
    close() {
      stream.getTracks().forEach((track) => track.stop())
      pc.close()
    }
  }
}

function resetVideoElement(videoEl: HTMLVideoElement) {
  videoEl.pause()
  videoEl.loop = false
  videoEl.removeAttribute('src')
  videoEl.srcObject = null
  videoEl.load()
}


function canUseNativeHls(): boolean {
  if (typeof document === 'undefined') return false
  const video = document.createElement('video')
  return Boolean(video.canPlayType('application/vnd.apple.mpegurl'))
}


function waitForFrames(videoEl: HTMLVideoElement, timeoutMs = 5000): Promise<boolean> {
  if (videoEl.readyState >= 2) return Promise.resolve(true)
  return new Promise((resolve) => {
    let done = false
    const finish = (ok: boolean) => {
      if (done) return
      done = true
      clearTimeout(timer)
      videoEl.removeEventListener('loadeddata', onCheck)
      videoEl.removeEventListener('canplay', onCheck)
      resolve(ok)
    }
    const onCheck = () => {
      if (videoEl.readyState >= 2) finish(true)
    }
    const timer = setTimeout(() => finish(false), timeoutMs)
    videoEl.addEventListener('loadeddata', onCheck)
    videoEl.addEventListener('canplay', onCheck)
  })
}

async function playHls(
  videoEl: HTMLVideoElement,
  hlsUrl: string,
  onModeChange?: (mode: LivePlaybackMode) => void,
): Promise<boolean> {
  if (!hlsUrl) return false

  resetVideoElement(videoEl)

  if (canUseNativeHls()) {
    // Safari / iOS 原生 HLS
    videoEl.src = hlsUrl
    videoEl.loop = false
    videoEl.play().catch(() => undefined)
    onModeChange?.('native-hls')
    return true
  }

  const HlsCtor = (Hls as unknown as { default?: typeof Hls }).default ?? Hls
  if (!HlsCtor?.isSupported?.()) return false

  const hls = new HlsCtor({ lowLatencyMode: false, liveSyncDurationCount: 1, maxBufferLength: 10, backBufferLength: 10, enableWorker: false })
  ;(videoEl as HTMLVideoElement & { _hls?: Hls })._hls = hls
  hls.attachMedia(videoEl)
  hls.loadSource(hlsUrl)
  await new Promise<void>((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('hls manifest timeout')), 12000)
    hls.on(HlsCtor.Events.MANIFEST_PARSED, () => {
      clearTimeout(timer)
      resolve()
    })
    hls.on(HlsCtor.Events.ERROR, (_event, data) => {
      if (data.fatal) {
        clearTimeout(timer)
        reject(new Error(`hls fatal: ${data.details}`))
      }
    })
  })
  videoEl.play().catch(() => undefined)
  onModeChange?.('hls')
  return true
}

async function playPreview(videoEl: HTMLVideoElement, previewUrl: string) {
  resetVideoElement(videoEl)
  videoEl.loop = true
  videoEl.src = previewUrl
  videoEl.load()
  await videoEl.play()
  return 'preview' as const
}

export function createLivePlaybackController(options: LivePlaybackOptions): LivePlaybackController {
  const { videoEl, webrtcUrl, hlsUrl, previewUrl, muted = true, onModeChange } = options
  const teardownList: Array<() => void> = []
  let rtcPlayer: SrsRtcPlayer | null = null
  let reconnectTimer: ReturnType<typeof setTimeout> | null = null
  let destroyed = false

  videoEl.muted = muted
  videoEl.playsInline = true
  videoEl.autoplay = true

  async function attemptWebrtc(): Promise<boolean> {
    if (!webrtcUrl || !canUseRtcPlayer()) return false

    try {
      rtcPlayer?.close()
      resetVideoElement(videoEl)
      rtcPlayer = createSrsRtcPlayer()
      videoEl.srcObject = rtcPlayer.stream
      await rtcPlayer.play(webrtcUrl)
      await Promise.race([
        videoEl.play(),
        new Promise((resolve) => setTimeout(resolve, 3000)),
      ]).catch(() => undefined)
      onModeChange?.('webrtc')
      return true
    } catch (error) {
      console.warn('WebRTC play failed', error)
      rtcPlayer?.close()
      rtcPlayer = null
      return false
    }
  }

  function startReconnectLoop() {
    let retries = 0
    const maxRetries = 5

    async function tryReconnect() {
      if (destroyed) return

      const ok = await attemptWebrtc()
      if (ok) {
        // 重连成功，监听下一次断连
        watchConnection()
        return
      }

      retries++
      if (retries < maxRetries) {
        console.warn(`WebRTC reconnect attempt ${retries}/${maxRetries}, retrying in 1.5s...`)
        reconnectTimer = setTimeout(tryReconnect, 1500)
      } else {
        console.warn('WebRTC reconnect exhausted, falling back to HLS')
        if (hlsUrl && !destroyed) {
          playHls(videoEl, hlsUrl, onModeChange).catch(() => undefined)
          return
        }
        if (previewUrl && !destroyed) {
          playPreview(videoEl, previewUrl).then((mode) => {
            onModeChange?.(mode)
          })
        }
      }
    }

    tryReconnect()
  }

  function watchConnection() {
    if (!rtcPlayer) return
    const pc = rtcPlayer.pc

    const handler = () => {
      if (destroyed) return
      const state = pc.iceConnectionState
      if (state === 'disconnected' || state === 'failed') {
        console.warn(`WebRTC ${state}, starting reconnect...`)
        pc.removeEventListener('iceconnectionstatechange', handler)
        startReconnectLoop()
      }
    }

    pc.addEventListener('iceconnectionstatechange', handler)
    teardownList.push(() => pc.removeEventListener('iceconnectionstatechange', handler))
  }

  return {
    async play() {
      // 默认 HLS（秒开、稳定）；WebRTC 仅作 HLS 失败时的备用
      if (hlsUrl) {
        try {
          const okHls = await playHls(videoEl, hlsUrl, onModeChange)
          if (okHls) return 'hls' as const
        } catch (hlsError) {
          console.warn('HLS play failed, trying WebRTC', hlsError)
        }
      }

      const ok = await attemptWebrtc()
      if (ok) {
        watchConnection()
        // 信令成功≠出帧（ICE可能永远打不通）：限时等帧，超时放弃
        const hasFrames = await waitForFrames(videoEl, 5000)
        if (hasFrames) return 'webrtc'
        console.warn('WebRTC signaling ok but no frames in 5s')
        rtcPlayer?.close()
        rtcPlayer = null
        resetVideoElement(videoEl)
      }

      // 次选：HLS（经 CDN，延迟约 10-15s）
      if (hlsUrl) {
        try {
          const okHls = await playHls(videoEl, hlsUrl, onModeChange)
          if (okHls) return 'hls' as const
        } catch (hlsError) {
          console.warn('HLS play failed', hlsError)
        }
      }

      // 兜底：预览视频
      if (previewUrl) {
        const mode = await playPreview(videoEl, previewUrl)
        onModeChange?.(mode)
        return mode
      }

      throw new Error('No playable source found')
    },
    suspend() {
      // 滑走挂起：停下载但保留实例，回来秒续（避免整条 HLS 握手重来）
      videoEl.pause()
      const vh = videoEl as HTMLVideoElement & { _hls?: Hls }
      vh._hls?.stopLoad()
    },

    async resume() {
      const vh = videoEl as HTMLVideoElement & { _hls?: Hls }
      if (vh._hls) vh._hls.startLoad(-1)
      try {
        await videoEl.play()
      } catch {
        // autoplay 拒绝时静默，等待用户手势
      }
    },

    destroy() {
      destroyed = true
      if (reconnectTimer) {
        clearTimeout(reconnectTimer)
        reconnectTimer = null
      }
      teardownList.splice(0).forEach((fn) => fn())
      const vh = videoEl as HTMLVideoElement & { _hls?: Hls }
      vh._hls?.destroy()
      vh._hls = undefined
      rtcPlayer?.close()
      rtcPlayer = null
      resetVideoElement(videoEl)
    }
  }
}
